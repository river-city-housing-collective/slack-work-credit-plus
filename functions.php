<?php

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

    public function __construct($conn, $type = 'bot', $config = null) {
        $this->conn = $conn;

        // if skipped sign in step, get config (bot)
        if ($type == 'bot') {
            $this->config = getSlackConfig($conn);
        }
        else {
            $this->config = $config;

            $identityCheck = json_decode($this->apiCall('users.identity', array(), 'user'), true);

            $this->userInfo = $identityCheck['user'];
            $this->authed = $identityCheck['ok'];
        }

        if ($this->authed) {
            $this->userGroups = json_decode($this->apiCall('usergroups.list'), true);

            // save user id for future calls
            $this->userId = $this->userInfo['id'];

            // get specifics about logged in user
            $slackUserInfo = json_decode($this->apiCall(
                'users.info',
                'user=' . $this->userId,
                'read',
                true
            ), true)['user'];
            
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

    // todo clean up - add option to return json or not
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
            CURLOPT_POSTFIELDS => $urlencoded ? $params : json_encode($params), // todo auto format array into urlencoded
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
        return $response;
    }

    public function addToUsergroup($user_id, $usergroup_id) {
        // get current list of users
        $users = json_decode($this->apiCall(
            'usergroups.users.list',
            'usergroup=' . $usergroup_id,
            'read',
            true
        ), true)['users'];

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
        $users = json_decode($this->apiCall(
            'users.list'
        ), true)['members'];

        $sql = 'insert into sl_users (
            slack_user_id,
            slack_username,
            first_name,
            last_name,
            real_name,
            is_admin,
            deleted,
            email
        ) values ';
        $values = array();

        foreach ($users as $user) {
            if (!$user['is_bot'] && $user['id'] != 'USLACKBOT') {
                $values[] = "('" .
                    $user['id'] . "', '" .
                    $user['name'] . "', '" .
                    $user['profile']['first_name'] . "', '" .
                    $user['profile']['last_name'] . "', '" .
                    $user['profile']['real_name'] . "', " .
                    ($user['is_admin'] ? 'true' : 'false') . ", " .
                    ($user['deleted'] ? 'true' : 'false') . ", '" .
                    $user['profile']['email'] . "')";
            }
        }

        $sql .= implode(',', $values);
        $sql .= ' on duplicate key update
        slack_user_id = values(slack_user_id),
        slack_username = values(slack_username),
        first_name = values(first_name),
        last_name = values(last_name),
        real_name = values(real_name),
        is_admin = values(is_admin),
        deleted = values(deleted),
        email = values(email)';

        echo $this->conn->query($sql);
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

    public function openModal($trigger_id, $view, $params = null) {
        $viewJson = json_decode(file_get_contents('views/' . $view . '.json'), TRUE);

        if ($view == 'send-email-modal' && isset($params['body'])) {
            // todo auto address based on channel origin
            // $channel_id = $params['channel_id'];
            // $result = $this->conn->query("select slack_usergroup_id from sl_houses where slack_channel_id = $channel_id");
            // $house_id = $result->fetch_assoc()['id'];

            $viewJson['blocks'][2]['element']['initial_value'] = $params['body'];
        }

        $json = array(
            'trigger_id' => $trigger_id,
            'view' => $viewJson
        );

        echo $this->apiCall(
            'views.open',
            $json,
            'bot'
        );
    }

    public function getInputValues($valuesObj) {
        foreach ($valuesObj as $field => $data) {
            $data = $data['value'];
            $type = $data['type'];
    
            if ($type == 'datepicker') {
                $value = $data['selected_date'];
            }
            else if ($type == 'static_select') {
                $value = $data['selected_option']['value'];
            }
            else {
                $value = $data['value'];
            }
    
            $inputValues[$field] = $value;
        }

        return $inputValues;
    }
}