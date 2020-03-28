<?php
require_once('dbconnect.php');

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

function signInWithSlack($conn, $adminOnly = false, $silent = false) {
    // get config from db
    $config = getSlackConfig($conn);

    // previous login
    if (isset($_COOKIE['sl_hash'])) {
        $hash = $_COOKIE['sl_hash'];

        $result = $conn->query("select sl_token from sl_login_tokens where sl_hash = '$hash'");
        $config['user_token'] = $result->fetch_assoc()['sl_token'];
    }
    // new login
    else if (isset($_GET['code'])) {
        $auth = json_decode(file_get_contents(
            'https://slack.com/api/oauth.access?' .
            'client_id=' . $config['CLIENT_ID'] .
            '&client_secret=' . $config['CLIENT_SECRET'] .
            '&code=' . $_GET['code']
        ), true);

        $token = $auth['access_token'];

        $hash = hash('whirlpool', $token . time());

        // set cookie and refresh
        setcookie('sl_hash', $hash, strtotime( '+30 days' ), '/');

        $stmt = $conn->prepare("
            insert into sl_login_tokens (sl_hash, sl_token)
            values (?, ?)
        ");
        $stmt->bind_param('ss', $hash, $token);
        $stmt->execute();

        if (isset($_GET['state'])) {
            $redirect = urldecode($_GET['state']);

            header("Location: $redirect");
            exit();
        }
    }
    else {
        $redirect = urlencode($_SERVER['PHP_SELF']);

        include_once($_SERVER['DOCUMENT_ROOT'] . '/includes.php');
        include_once($_SERVER['DOCUMENT_ROOT'] . '/members-only/login.php');

        exit();
    }

    // create new slack object
    if ($config['user_token']) {
        $slack = new Slack($conn, $config);
    }
    else {
        return false;
    }
    
    // if not authed, show sign in button
    if (!$slack->authed) {
        die('login failed');
    }

    // if not admin, die
    if ($adminOnly && !$slack->admin) {
        die('you do not have permission to access this page');
    }

    // show member info popup (unless post request)
    if (!$silent) {
        include_once($_SERVER['DOCUMENT_ROOT'] . '/includes.php');
    }

    return $slack;
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
        'sl_view_states' => array(
            'slack_view_id' => 's',
            'slack_user_id' => 's',
            'sl_key' => 's',
            'sl_value' => 's'
        ),
        'wc_time_credits' => array(
            'slack_user_id' => 's',
            'hours_credited' => 's',
            'hour_type_id' => 'i',
            'contribution_date' => 's',
            'description' => 's',
            'other_req_id' => 'i',
            'submit_source' => 'i'
        ),
        'wc_time_debits' => array(
            'date_effective' => 's',
            'slack_user_id' => 's',
            'hours_debited' => 's',
            'hour_type_id' => 'i',
            'description' => 's'
        ),
        'wc_user_reqs' => array(
            'slack_user_id' => 's',
            'required_qty' => 'i',
            'hours_type_id' => 'i',
            'other_type_id' => 'i'
        )
    );

    public function __construct($conn, $config = null, $userToken = null) {
        $this->conn = $conn;

        // if skipped sign in step, get config
        if (!$config) {
            $this->config = getSlackConfig($conn);

            if (isset($userToken)) {
                $this->config['user_token'] = $userToken;
            }
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

            $additionalUserDetails = $this->conn->query("
                select * from sl_users as u left join sl_houses as h on u.house_id = h.slack_group_id where u.slack_user_id = '$this->userId'
            ")->fetch_assoc();

            if ($additionalUserDetails['is_boarder']) {
                $roles[] = 'Boarder';
            }
            else if (!$additionalUserDetails['is_guest']) {
                $roles[] = 'Resident';
            }
            
            if ($additionalUserDetails['is_admin']) {
                $roles[] = 'Admin';
            }

            // pare down to the essentials
            $this->userInfo = array(
                'real_name' => $slackUserInfo['real_name'],
                'display_name' => $slackUserInfo['display_name'],
                'avatar' => $slackUserInfo['image_72'],
                'house' => $additionalUserDetails['name'],
                'type' => implode('/', $roles),
                'is_boarder' => $additionalUserDetails['is_boarder'],
                'is_guest' => $additionalUserDetails['is_guest']
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

    public function sqlSelect($sql, $returnJson = null, $noSimplify = false) {
        $result = $this->conn->query($sql);

        if (!isset($result->num_rows) || $result->num_rows == 0) {
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
        if ($columns[0] == 'sl_key' && $columns[1] == 'sl_value') {
            $newReturnResult = array();

            foreach ($returnResult as $row) {
                $key = $row['sl_key'];
                $value = $row['sl_value'];

                $newReturnResult[$key] = $value;
            }

            $returnResult = $newReturnResult;
        }
        // if there's only one row, simplify (or not)
        else if (sizeOf($returnResult) == 1 && !$noSimplify) {
            $returnResult = $returnResult[0];
        }

        return $returnJson ? json_encode($returnResult) : $returnResult;
    }

    public function sqlInsert($table, $data) {
        $dbFields = $this->dbFields[$table];
        $fieldKeys = array_keys($dbFields);
        $primaryKey = ($table == 'sl_view_states' ? 'slack_view_id' : 'slack_user_id');

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
            if ($field != $primaryKey) {
                $updates[] = $field . ' = values(' . $field . ')';
            }
        }

        for ($i=0; $i < sizeOf($fields); $i++){
            $preparedValues[] = '?';
        }

        $fields = implode(', ', $fields);
        $preparedValues = implode(', ', $preparedValues);
        $updates = implode(', ', $updates);

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

        return $stmt->affected_rows;
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

    public function removeFromUsergroups($user_id, $usergroup_id, $table) {
        $usergroups = $this->sqlSelect("select slack_group_id, slack_channel_id from $table where slack_group_id <> '$usergroup_id'");

        foreach ($usergroups as $usergroup) {
            $usergroup_id = $usergroup['slack_group_id'];
            $channel_id = $usergroup['slack_channel_id'];

            // get current list of users
            $users = $this->apiCall(
                'usergroups.users.list',
                'usergroup=' . $usergroup_id,
                'read',
                true
            )['users'];

            // remove user, if exists
            if (($key = array_search($user_id, $users)) !== false) {
                unset($users[$key]);

                // save updated list
                $this->apiCall(
                    'usergroups.users.update',
                    array(
                        'usergroup' => $usergroup_id,
                        'users' => $users
                    ),
                    'write'
                );

                // remove user from matching channel
                debug($this->apiCall(
                    'conversations.kick',
                    array(
                        'channel' => $channel_id,
                        'users' => $user_id
                    ),
                    'write'
                ));
            }
        }
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

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        // todo return something more helpful (affected_rows returns 1 or 2)
        return $stmt->affected_rows;
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

    public function buildProfileModal($profileData) {
        $viewJson = json_decode(file_get_contents('views/edit-profile-modal.json'), TRUE);

        $is_guest = isset($profileData['is_guest']) ? $profileData['is_guest'] : false;
        $is_boarder = isset($profileData['is_boarder']) ? $profileData['is_boarder'] : false;
        $is_admin = isset($profileData['is_admin']) ? $profileData['is_admin'] : false;

        if (!$is_guest) {
            $additionalBlocks = json_decode(file_get_contents('views/profile-member-details.json'), TRUE);

            // get houses
            $additionalBlocks[sizeOf($additionalBlocks) - 1]['accessory']['options'] = $this->getOptions('sl_houses');

            if (!$is_boarder) {
                $residentBlocks = json_decode(file_get_contents('views/profile-resident-details.json'), TRUE);

                // display room_id (if exists) and get committees
                if (isset($profileData['room_id'])) {
                    $room_id = $profileData['room_id'];
                    $house_id = $profileData['house_id'];

                    $residentBlocks[0]['accessory']['text']['text'] = $this->sqlSelect("select room from sl_rooms where id = $room_id and house_id = '$house_id'");
                }
                $residentBlocks[1]['accessory']['options'] = $this->getOptions('sl_committees', $is_admin);

                $additionalBlocks = array_merge($additionalBlocks, $residentBlocks);
            }

            $viewJson['blocks'] = array_merge($viewJson['blocks'], $additionalBlocks);
        }

        $viewJson = $this->setInputValues($viewJson, $profileData);

        return $viewJson;
    }

    public function getOptions($table, $filter = null) {
        if ($table == 'sl_houses' || $table == 'sl_committees') {
            $fieldKey = 'name';
            $fieldValue = 'slack_group_id';

            $sql = "select * from $table";

            if ($table == 'sl_committees' && !$filter) {
                $sql .= " where name <> 'Board Officers'";
            }

            $sql .= ' order by name asc';
        }
        else if ($table == 'sl_rooms') {
            $fieldKey = 'room';
            $fieldValue = 'id';

            $sql = "
                select
                    r.id,
                    r.room,
                    group_concat(
                        u.real_name
                        order by real_name asc
                        separator ', '
                    ) as residents
                from sl_rooms as r
                left join sl_users as u
                    on u.room_id = r.id
                    and u.house_id = r.house_id
                where r.house_id = '$filter'
                group by r.room
                order by id asc
            ";
        }

        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $value = $row[$fieldValue];
            $label = $row[$fieldKey];
            
            if (isset($row['residents'])) {
                $label = $label . ' (' . $row['residents'] . ')';
            }

            $options[] = array(
                'text' => array(
                    'type' => 'plain_text',
                    'text' => $label
                ),
                'value' => $value
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
                        $type = 'accessory';
                    }
                    else if (isset($block['element'])) {
                        if ($block['element']['type'] == 'static_select') {
                            $type = 'element';
                        }
                    }

                    if (isset($type)) {
                        if ($block[$type]['type'] == 'static_select') {
                            if (isset($value)) {
                                foreach ($block[$type]['options'] as $option) {
                                    $optionLookup[$option['value']] = $option;
                                }
            
                                $viewJson
                                    ['blocks']
                                    [$index]
                                    [$type]
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

    public function updateUserProfile($user_id, $inputValues = null) {
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

        $inputValues['slack_user_id'] = $user_id;

        $this->sqlInsert('sl_users', $inputValues);

        if (!isset($inputValues['is_guest'])) {
            $inputValues['is_guest'] = $this->sqlSelect("select is_guest from sl_users where slack_user_id = '$user_id'");
        }

        if ($inputValues['is_guest'] == '0') {
            $workCreditCheck = $this->sqlSelect("
                select * from wc_time_debits
                where slack_user_id = '$user_id'
                order by date_effective desc limit 1
            ");

            if (!$workCreditCheck) {
                $hourTypes = $this->sqlSelect("select id from wc_lookup_hour_types");

                foreach ($hourTypes as $id) {
                    $this->scheduleHoursDebit($user_id, $id, true);
                }
            };
        }
    }

    public function getStoredViewData($user_id, $view_id, $inputValues = null) {
        $sql = "
            select vs.sl_key, vs.sl_value
            from sl_view_states as vs
            inner join (
                select sl_key, max(timestamp) as ts
                from sl_view_states
                group by sl_key
            ) as maxt on (vs.sl_key = maxt.sl_key and vs.timestamp = maxt.ts)
            where slack_user_id = '$user_id' and slack_view_id = '$view_id'
        ";

        $storedValues = $this->sqlSelect($sql);

        if ($storedValues) {
            foreach ($storedValues as $field => $value) {
                $inputValues[$field] = $value;
            }
        }

        return $inputValues;
    }

    // todo - for adding modifiers to requirments
    public function modifyWorkCreditUserReqs($user_id, $data = null) {
        $lookups = array(
            'hours' => $this->sqlSelect("select id, default_qty from wc_lookup_hour_types"),
            'other' => $this->sqlSelect("select id, default_qty from wc_lookup_other_req_types")
        );

        // no specifics - use defaults
        if (!$data) {
            foreach ($lookups as $type => $lookup) {
                foreach ($lookup as $values) {
                    $data[] = array(
                        'slack_user_id' => $user_id,
                        'required_qty' => $values['default_qty'],
                        $type . '_type_id' => $values['id']
                    );
                }
            }
        }
        // todo allow updating with param

        // todo multi-insert
        foreach ($data as $row) {
            $this->sqlInsert('wc_user_req_modifiers', $row);
        }
    }

    // todo maybe check to make dates are unique
    // todo cron jobs - monthly and yearly
    public function scheduleHoursDebit($user_id, $typeId, $newMember = false) {
        $is_boarder = $this->sqlSelect("select is_boarder from sl_users where slack_user_id = '$user_id'");
        $hourTypes = $this->sqlSelect("select name, default_qty, default_qty_boarder from wc_lookup_hour_types where id = $typeId");

        $defaultHours = $is_boarder ? $hourTypes['default_qty_boarder'] : $hourTypes['default_qty'];

        $hoursMod = $this->sqlSelect("select qty_modifier from wc_user_req_modifiers where hour_type_id = $typeId and slack_user_id = $user_id");
        $hours = $defaultHours + $hoursMod;

        if ($newMember && $typeId != 3) {
            $hours = $hours / 2;
        }

        $interval = '+1 month';
        $dateFormat = 'Y-m-01';

        if ($typeId == 3) {
            $interval = '+1 year';
            $dateFormat = 'Y-01-01';
        }
        $date = strtotime($interval, strtotime(date("Y-m-d")));
        $date = date($dateFormat, $date);

        $this->sqlInsert('wc_time_debits', array(
            'date_effective' => $date,
            'slack_user_id' => $user_id,
            'hours_debited' => $hours,
            'hour_type_id' => $typeId
        ));

        return $hourTypes['name'];
    }
}