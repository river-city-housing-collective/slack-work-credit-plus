<?php

function signInWithSlack($client_id, $client_secret, $web_token, $password) {
    // previous login
    if (isset($_COOKIE['token'])) {
        $token = $_COOKIE['token'];
    }
    // new login
    else if (isset($_GET['code'])) {
        $auth = json_decode(file_get_contents(
            'https://slack.com/api/oauth.access?' .
            'client_id=' . $client_id .
            '&client_secret=' . $client_secret .
            '&code=' . $_GET['code']
        ), true);

        $token = $auth['access_token'];
        setcookie('token', $token, strtotime( '+30 days' ), '/');
    }
    // no access
    else {
        return false;
    }

    return new Slack($token, $web_token, $password);
}

class Slack {
    public $token;
    public $userInfo;
    public $authed;

    public function __construct($token, $web_token, $password) {
        $this->token = $token;

        $identityCheck = json_decode($this->apiCall('users.identity'), true);

        $this->userInfo = $identityCheck['user'];
        $this->authed = $identityCheck['ok'];

        if ($this->authed) {
            $this->db = new Database($password);

            // authorized user - replace their token with elevated one
            $this->token = $web_token;

            // get list of all users for reference?
            // $this->userLookup = json_decode($this->apiCall('users.list'), true);

            $this->userGroups = json_decode($this->apiCall('usergroups.list'), true);

            // save user id for future calls
            $this->userId = $this->userInfo['id'];

            // get specifics about logged in user
            $slackUserInfo = json_decode($this->apiCall('users.profile.get',
                array('user' => $this->userId)
            ), true)['profile'];

            $houseDetails = $this->db->query("
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

    public function apiCall($method, $params = array()) {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://slack.com/api/" . $method,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->token
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
}

class Database {
    public $conn;

    public function __construct($password) {
        $DB_SERVERNAME = "mysql.rchc.coop";
        $DB_USERNAME = "rchccoop1";
        $DB_DATABASE = "rchc_coop_1";

        // Create connection
        $this->conn = new mysqli($DB_SERVERNAME, $DB_USERNAME, $password, $DB_DATABASE);

        // Check connection
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        // get slack config
        // $sql = 'select * from sl_config';
        // $result = $conn->query($sql);
        // while ($row = $result->fetch_assoc()) {
        //     $config[$row['sl_key']] = $row['sl_value'];
        // }
    }
    public function query($sql) {
        $result = $this->conn->query($sql);

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        else {
            return false;
        }
    }
    public function userUpdate($user_id, $update) {
        $conn = $this->conn;

        $columns = array_keys($update);
        $values = array();

        foreach ($columns as $column) {
            $values[] = $update[$column];
        }

        $columnList = implode(',', $columns);
        $valueList = '"' . implode('","', $values) . '"';

        $sql = "insert into sl_users (slack_user_id, $columnList) values ('$user_id', $valueList) on duplicate key update slack_username = values(slack_username), house_id = values(house_id), committee_id = values(committee_id)";

        $result = $conn->query($sql);

        return json_encode($result);
    }
}