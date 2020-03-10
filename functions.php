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

    return new Slack($conn, $config);
}

class Slack {
    public $conn;
    public $config;
    public $userInfo;
    public $authed;

    public function __construct($conn, $config = null) {
        $this->conn = $conn;

        // if skipped sign in step, get config (bot)
        if ($config == 'bot') {
            $this->config = getSlackConfig($conn);
        }
        else {
            $this->config = $config;

            $identityCheck = json_decode($this->apiCall('users.identity', null, 'user'), true);

            $this->userInfo = $identityCheck['user'];
            $this->authed = $identityCheck['ok'];
        }

        if ($this->authed) {
            // get list of all users for reference?
            // $this->userLookup = json_decode($this->apiCall('users.list'), true);

            $this->userGroups = json_decode($this->apiCall('usergroups.list'), true);

            // save user id for future calls
            $this->userId = $this->userInfo['id'];

            // get specifics about logged in user
            $slackUserInfo = json_decode($this->apiCall('users.profile.get',
                array('user' => $this->userId)
            ), true)['profile'];

            $houseDetails = $this->conn->query("
                select h.name, u.boarder from sl_users as u left join sl_houses as h on u.house_id = h.id where u.slack_user_id = '$this->userId'
            ");

            // pare down to the essentials
            $this->userInfo = array(
                'display_name' => $slackUserInfo['display_name'],
                'avatar' => $slackUserInfo['image_72'],
                'house' => $houseDetails['name'],
                'boarder' => $houseDetails['boarder']
            );
        }
    }

    public function apiCall($method, $params = array(), $type = 'read', $urlencoded) {
        if ($type == 'user') {
            $token = $this->config['token'];
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
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://slack.com/api/" . $method,
            CURLOPT_POSTFIELDS => $urlencoded ? $params : json_encode($params),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/" . $urlencoded ? "x-www-form-urlencoded" : "json",
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
        $users = $this->apiCall(
            'usergroups.users.list',
            'usergroup=' . $usergroup_id,
            'read',
            true
        );

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
}