<?php

function signInWithSlack($client_id, $client_secret) {
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

    return new Slack($token);
}

class Slack {
    public $token;
    public $userInfo;
    public $authed;

    public function __construct($token) {
        $this->token = $token;

        $identityCheck = json_decode($this->apiCall('users.identity'), true);

        $this->userInfo = $identityCheck['user'];
        $this->authed = $identityCheck['ok'];

        if ($this->authed) {
            // authorized user - replace their token with elevated one
            $this->token = $WEB_TOKEN;

            $this->userLookup = json_decode($this->apiCall('users.list'), true);

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

    public function __construct($servername, $username, $password, $database) {
        // Create connection
        $this->conn = new mysqli($servername, $username, $password, $database);

        // Check connection
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        // get slack config
        $sql = 'select * from sl_config';
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $config[$row['sl_key']] = $row['sl_value'];
        }
    }
    public function userLookup($id) {
        $sql = "select * from sl_users where user_id = '$id'";
        $result = $this->conn->query($sql);
    
        // user exists - return info
        if ($result->num_rows > 0) {
            return json_encode($result->fetch_assoc());
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