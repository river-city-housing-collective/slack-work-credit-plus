<?php

function debug($value, $compact = null) {
    if (is_array($value)) {
        if ($compact) {
            $value = json_encode($value);
        }
        else {
            $value = json_encode($value, JSON_PRETTY_PRINT);
        }
    }

    echo "\r\n" . '~START~ ' . "\r\n" . $value . "\r\n" . ' ~END~' . "\r\n";
}

function getSlackConfig($conn) {
    // get config from db
    $sql = "SELECT * FROM sl_config";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $config[$row['sl_key']] = $row['sl_value'];
    };

    return $config;
}

function signInWithSlack($conn) {
    // get config from db
    $config = getSlackConfig($conn);

    // previous login
    if (isset($_COOKIE['token'])) {
        $config['user_token'] = $_COOKIE['token'];
    }
    // new login
    else if (isset($_GET['code'])) {
        $auth = json_decode(file_get_contents(
            'https://slack.com/api/oauth.access?' .
            'client_id=' . $config['CLIENT_ID'] .
            '&client_secret=' . $config['CLIENT_SECRET'] .
            '&code=' . $_GET['code']
        ), true);

        $config['user_token'] = $auth['access_token'];
        setcookie('token', $config['user_token'], strtotime( '+30 days' ), '/');
    }
    // no access
    else {
        return false;
    }

    return new Slack($conn, 'user', $config);
}

class Slack {
    public $conn;
    public $config;
    public $userInfo;
    public $authed;
    public $admin;

    public $dbFields = array(
        'sl_users' => array(
            'slack_user_id' => 's',
            'real_name' => 's',
            'display_name' => 's',
            'email' => 's',
            'phone' => 's',
            'house_id' => 's',
            'room_id' => 'i',
            'committee_id' => 's',
            'is_boarder' => 'i',
            'is_guest' => 'i',
            'is_admin' => 'i',
            'deleted' => 'i'
        ),
        'wc_time_records' => array(
            'slack_user_id' => 's',
            'hours_completed' => 'i',
            'hour_type_id' => 'i',
            'contribution_date' => 's',
            'description' => 's',
            'requirement_id' => 'i'
        )
    );

    public function __construct($conn, $type = 'bot', $config = null) {
        $this->conn = $conn;

        // if skipped sign in step, get config (bot)
        if ($type == 'bot') {
            $this->config = getSlackConfig($conn);
        }
        else {
            $this->config = $config;

            $identityCheck = $this->apiCall('users.identity', array(), 'user');

            $this->userInfo = $identityCheck['user'];
            $this->authed = $identityCheck['ok'];
        }

        if ($this->authed) {
            $this->userGroups = $this->apiCall('usergroups.list');

            // save user id for future calls
            $this->userId = $this->userInfo['id'];

            // get specifics about logged in user
            $slackUserInfo = $this->apiCall(
                'users.info',
                'user=' . $this->userId,
                'read',
                true
            )['user'];
            
            $this->admin = $slackUserInfo['is_admin'];
            $slackUserInfo = $slackUserInfo['profile'];

            $houseDetails = $this->conn->query("
                select h.name, u.committee_id from sl_users as u left join sl_houses as h on u.house_id = h.id where u.slack_user_id = '$this->userId'
            ")->fetch_assoc();

            // pare down to the essentials
            $this->userInfo = array(
                'display_name' => $slackUserInfo['display_name'],
                'avatar' => $slackUserInfo['image_72'],
                'house' => $houseDetails['name'],
                'type' => $houseDetails['committee_id'] == 0 ? 'Boarder' : 'Resident'
            );
        }
    }

    public function apiCall($method, $params = array(), $type = 'read', $urlencoded = null) {
        if ($type == 'user') {
            $token = $this->config['user_token'];
        }
        else if ($type == 'bot') {
            $token = $this->config['BOT_TOKEN'];
        }
        else if ($type == 'write') {
            $token = $this->config['WRITE_TOKEN'];
        }
        else {
            $token = $this->config['READ_TOKEN'];
        }

        // echo $method;
        // echo json_encode($params);
        // echo $type;
        // echo $urlencoded;
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://slack.com/api/" . $method,
            CURLOPT_POSTFIELDS => $urlencoded ? $params : json_encode($params),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/" . ($urlencoded ? "x-www-form-urlencoded" : "json"),
                "Authorization: Bearer " . $token
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST"      
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response, true);
    }

    public function sqlSelect($sql) {
        $result = $this->conn->query($sql);

        if (!$result->num_rows) {
            return false;
        }

        $columns = array();
        $returnResult = array();

        while ($row = $result->fetch_assoc()) {
            if (!$columns) {
                $columns = array_keys($row);
            }

            // if there's only one column, return as string
            if (sizeOf($columns) == 1) {
                $rowData = $row[$columns[0]];
            }
            else {
                // add assoc to array
                foreach ($columns as $column) {
                    $rowData[$column] = $row[$column];
                }
            }

            $returnResult[] = $rowData;
        };

        // if key value pairs, simplify
        if ($columns[0] == 'key' && $columns[1] == 'value') {
            $newReturnResult = array();

            foreach ($returnResult as $row) {
                $key = $row['key'];
                $value = $row['value'];

                $newReturnResult[$key] = $value;
            }

            $returnResult = $newReturnResult;
        }
        // if there's only one row, simplify
        else if (sizeOf($returnResult) == 1) {
            $returnResult = $returnResult[0];
        }

        return $returnResult;
    }

    public function sqlInsert($table, $data) {
        $dbFields = $this->dbFields[$table];
        $fieldKeys = array_keys($dbFields);

        $fields = array();
        $values = array();
        $preparedValues = array();
        $updates = array();
        $params = '';

        foreach ($data as $key => $value) {
            if (in_array($key, $fieldKeys)) {
                $fields[] = $key;
                $values[] = $value;
                $params .= $dbFields[$key];
            }
        }
        
        foreach ($fields as $field) {
            if ($field != 'slack_user_id') {
                $updates[] = $field . ' = values(' . $field . ')';
            }
        }

        for ($i=0; $i < sizeOf($fields); $i++){
            $preparedValues[] = '?';
        }

        $fields = implode(', ', $fields);
        $preparedValues = implode(', ', $preparedValues);
        $updates = implode(', ', $updates);

        // save updated profile info to db
        $sql = "
            insert into $table ($fields)
                values ($preparedValues)
            on duplicate key update
                $updates
        ";

        // debug($sql);
        // debug($params);
        // debug($values);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($params, ...$values);
        $stmt->execute();
    }

    public function addToUsergroup($user_id, $usergroup_id) {
        // get current list of users
        $users = $this->apiCall(
            'usergroups.users.list',
            'usergroup=' . $usergroup_id,
            'read',
            true
        )['users'];

        // add new user
        array_push($users, $user_id);

        // save updated list
        $this->apiCall(
            'usergroups.users.update',
            array(
                'usergroup' => $usergroup_id,
                'users' => $users
            ),
            'write'
        );
    }

    public function importSlackUsersToDb() {
        $users = $this->apiCall('users.list')['members'];

        $sql = 'insert into sl_users (
            slack_user_id,
            real_name,
            display_name,
            email,
            phone,
            is_admin,
            deleted
        ) values ';
        $values = array();

        // todo figure out how to get pronouns and committees

        foreach ($users as $user) {
            if (!$user['is_bot'] && $user['id'] != 'USLACKBOT') {
                $values[] = "('" .
                    $user['id'] . "', '" .
                    $user['profile']['real_name'] . "', '" .
                    $user['profile']['display_name'] . "', '" .
                    $user['profile']['email'] . "', '" .
                    $user['profile']['phone'] . "', " .
                    ($user['is_admin'] ? 'true' : 'false') . ", " .
                    ($user['deleted'] ? 'true' : 'false') . ")";
            }
        }

        $sql .= implode(',', $values);
        $sql .= ' on duplicate key update
        slack_user_id = values(slack_user_id),
        real_name = values(real_name),
        display_name = values(display_name),
        email = values(email),
        phone = values(phone),
        is_admin = values(is_admin),
        deleted = values(deleted)';

        $this->conn->query($sql);
    }

    public function sendEmail($fromUserId, $subject, $body, $house_id = null, $reallySend = false) {
        $sql = "
            select
                slack_user_id,
                slack_username,
                email,
                house_id,
                is_admin
            from sl_users
            where deleted <> 1";

        $result = $this->conn->query($sql);

        // get email lists
        while ($row = $result->fetch_assoc()) {
            if ($row['slack_user_id'] == $fromUserId) {
                $fromEmail = $row['email'];
            }
            if ($row['is_admin'] == 1) {
                $adminEmails[] = $row['email'];
            }
            if (isset($house_id)) {
                if ($row['house_id'] == $house_id) {
                    $userEmails[] = $row['email'];
                }
            }
            else {
                $userEmails[] = $row['email'];
            }
        }

        // make sure author is included in email
        $userEmails = array_unique(array_merge($userEmails, array($fromEmail)), SORT_REGULAR);

        // note for bottom of email
        $house = 'all current RCHC community members';

        // if house specific, get user readable name
        if ($house_id) {
            $sql = "select name from sl_houses where id = $house_id";
            $result = $this->conn->query($sql);
            $house = $result->fetch_assoc()['name'];
            $house = 'all residents and boarders of ' . $house;
        }

        $body .= "\r\n\r\n" . '[This message was sent on behalf of ' . $fromEmail . ' to ' . $house . ']';

        // check if this is a legit send or if we're just testing
        if ($reallySend) {
            // send to all relevant community members
            $toEmails = $userEmails;

            // CC all current admins
            $cc = implode(',', $adminEmails);

            $subject = '[RCHC] ' . $subject;
        }
        else {
            $toEmails = $adminEmails;
            $emailDump = implode("\r\n", $userEmails);

            $subject = '[RCHC EMAIL TEST] ' . $subject;
            $body .= "\r\n\r\n" . '---' . "\r\n" . "This email was intended for the following email addresses: " . "\r\n" . $emailDump;
        }

        $toEmails = implode(',', $toEmails);
        $headers = 'From: baby-yoda@rchc.coop' . (isset($cc) ? "\r\n" . 'Cc: ' . $cc : '');

        mail($toEmails, $subject, $body, $headers);
    }

    public function openView($trigger_id, $viewJson, $view_id = null) {
        $json = array(
            'trigger_id' => $trigger_id,
            'view' => $viewJson
        );

        if ($view_id) {
            $json['view_id'] = $view_id;
        }

        echo json_encode($this->apiCall(
            $view_id ? 'views.update' : 'views.open',
            $json,
            'bot'
        ));
    }

    public function buildProfileModal($profileData) {
        $viewJson = json_decode(file_get_contents('views/edit-profile-modal.json'), TRUE);

        if ($profileData['is_guest'] == '0') {
            $additionalBlocks = json_decode(file_get_contents('views/profile-member-details.json'), TRUE);

            // get houses
            $additionalBlocks[sizeOf($additionalBlocks) - 1]['accessory']['options'] = $this->getOptions('sl_houses');;

            if ($profileData['is_boarder'] == '0') {
                $residentBlocks = json_decode(file_get_contents('views/profile-resident-details.json'), TRUE);

                // get rooms and committees
                $residentBlocks[0]['accessory']['options'] = $this->getOptions('sl_rooms', $profileData['house_id']);
                $residentBlocks[1]['accessory']['options'] = $this->getOptions('sl_committees', $profileData['is_admin']);

                $additionalBlocks = array_merge($additionalBlocks, $residentBlocks);
            }

            $viewJson['blocks'] = array_merge($viewJson['blocks'], $additionalBlocks);
        }

        $viewJson = $this->setInputValues($viewJson, $profileData);

        return $viewJson;
    }

    public function getOptions($table, $filter = null) {
        if ($table == 'sl_houses' || $table == 'sl_committees') {
            $key = 'name';
            $value = 'slack_group_id';

            $sql = "select * from $table";

            if ($table == 'sl_committees' && $filter) {
                $sql .= " where name <> 'Board Officers'";
            }
        }
        else if ($table == 'sl_rooms') {
            $key = 'room';
            $value = 'id';

            $sql = "select id, room from sl_rooms";

            if ($filter) {
                $sql .= " where house_id = '$filter'";
            }
        }

        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $options[] = array(
                'text' => array(
                    'type' => 'plain_text',
                    'text' => $row[$key]
                ),
                'value' => $row[$value]
            );
        }

        return $options;
    }

    public function getInputValues($valuesObj) {
        foreach ($valuesObj as $field => $data) {
            $data = $data['value'];
            $type = $data['type'];
    
            if ($type == 'datepicker') {
                $value = $data['selected_date'];
            }
            else if ($type == 'static_select') {
                if (isset($data['selected_option'])) {
                    $value = $data['selected_option']['value'];
                }
                else {
                    continue;
                }
            }
            else {
                $value = $data['value'];
            }
    
            $inputValues[$field] = $value;
        }

        return $inputValues;
    }

    public function setInputValues($viewJson, $values) {
        foreach ($values as $key => $value) {
            if (isset($value)) {
                $values[$key] = $value;

                $keys[] = $key;
            }
        }

        foreach ($viewJson['blocks'] as $index => $block) {
            if (isset($block['block_id'])) {
                // if data exists for this block
                if (in_array($block['block_id'], $keys)) {
                    $value = $values[$block['block_id']];

                    if (isset($block['accessory'])) {
                        if ($block['accessory']['type'] == 'static_select') {
                            if (isset($value)) {
                                foreach ($block['accessory']['options'] as $option) {
                                    $optionLookup[$option['value']] = $option;
                                }
            
                                $viewJson
                                    ['blocks']
                                    [$index]
                                    ['accessory']
                                    ['initial_option'] = $optionLookup[$value];
                            }
                        }
                    }
                    else {
                        $viewJson
                            ['blocks']
                            [$index]
                            ['element']
                            ['initial_value'] = $value;
                    }
                }
            }
        }

        return $viewJson;
    }

    public function updateUserProfile($user_id, $inputValues = 'null') {
        if ($inputValues) {
            $result = $this->apiCall(
                'users.profile.set',
                array(
                    'user' => $user_id,
                    'profile' => array(
                        'real_name' => $inputValues['real_name'],
                        'display_name' => $inputValues['display_name'],
                        'phone' => $inputValues['phone'],
                        'fields' => array(
                            $this->config['PRONOUNS_FIELD_ID'] => array(
                                'value' => $inputValues['pronouns']
                            )
                        )
                    ),
                'write'
                )
            );

            // if errors saving profile
            if (!$result['ok']) {
                if ($result['error'] == 'invalid_name_specials') {
                    echo json_encode(array(
                        'response_action' => 'errors',
                        'errors' => array(
                            $result['field'] => 'Mostly, names can’t contain punctuation. (Apostrophes, spaces, and periods are fine.)'
                        )
                    ));

                    die();
                }
            }
        }
        else {
            $inputValues = $this->apiCall(
                'users.profile.get',
                array('user' => $user_id)
            );
        }

        // if changing to boarder status, wipe out room and committee
        if (isset($inputValues['is_boarder'])) {
            if ($inputValues['is_boarder'] == '1') {
                $inputValues['room_id'] = null;
                $inputValues['committee_id'] = null;
            }
        }

        debug($inputValues);

        $inputValues['slack_user_id'] = $user_id;

        $this->sqlInsert('sl_users', $inputValues);
    }

    public function getStoredViewData($user_id, $view_id) {
        $sql = "
            select vs.`key`, vs.`value`
            from sl_view_states as vs
            inner join (
                select `key`, max(timestamp) as ts
                from sl_view_states
                group by `key`
            ) as maxt on (vs.`key` = maxt.`key` and vs.timestamp = maxt.ts)
            where slack_user_id = '$user_id' and slack_view_id = '$view_id'
        ";

        return $this->sqlSelect($sql);
    }
}