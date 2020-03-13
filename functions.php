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

    // todo enable! it's ready
    public function sendEmail($house = null, $subject, $body) {
        $sql = "select email from sl_users where house_id = $house";
        $result = $this->conn->query($sql);

        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email'];
        }

        $body .= implode(',', $emails);

        $to = "izneuhaus@gmail.com";
        $headers = "From: baby.yoda@rchc.coop";

        mail($to,$subject,$body,$headers);
    }
}