<?php

require_once('dbconnect.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'resources/PHPMailer/src/Exception.php';
require 'resources/PHPMailer/src/PHPMailer.php';
require 'resources/PHPMailer/src/SMTP.php';

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

    // new login
    if (isset($_GET['code'])) {
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
    // previous login
    else if (isset($_COOKIE['sl_hash'])) {
        $hash = $_COOKIE['sl_hash'];

        $result = $conn->query("select sl_token from sl_login_tokens where sl_hash = '$hash'");
        $config['user_token'] = $result->fetch_assoc()['sl_token'];

        if (!isset($config['user_token'])) {
            unset($_COOKIE['sl_hash']);
            setcookie("sl_hash", "", time() - 3600);

            $redirect = urlencode($_SERVER['PHP_SELF']);

            include_once($_SERVER['DOCUMENT_ROOT'] . '/includes.php');
            include_once($_SERVER['DOCUMENT_ROOT'] . '/members-only/login.php');

            exit();
        };
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
                'house_id' => $additionalUserDetails['house_id'],
                'house' => $additionalUserDetails['name'],
                'type' => implode('/', $roles),
                'is_boarder' => $this->admin ? 0 : $additionalUserDetails['is_boarder'], // override boarder restrictions if admin
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

//        debug($returnResult);

        return $returnJson ? json_encode($returnResult) : $returnResult;
    }

    public function sqlInsert($table, $data, $returnId = false) {
        $dbFields = array();
        $primaryKey = '';

        $fields = $this->conn->query("SHOW COLUMNS FROM $table");

        while($fieldRow = $fields->fetch_assoc()) {
            $dbFields[$fieldRow['Field']] = strpos($fieldRow['Type'], 'int') ? 'i' : 's';

            if ($fieldRow['Key'] == 'PRI') {
                $primaryKey = $fieldRow['Field'];
            }
        }

        $fieldKeys = array_keys($dbFields);
//        $primaryKey = ($table == 'sl_view_states' ? 'slack_view_id' : 'slack_user_id');

        if ($table == 'event_logs') {
            unset($primaryKey);
        }

        $fields = array();
        $values = array();
        $preparedValues = array();
        $updates = null;
        $params = '';

        foreach ($data as $key => $value) {
            if (in_array($key, $fieldKeys)) {
                $fields[] = $key;
                $values[] = $value == '' ? null : $value;
                $params .= $dbFields[$key];
            }
        }

        if (isset($primaryKey)) {
            foreach ($fields as $field) {
                if ($field != $primaryKey) {
                    $updates[] = $field . ' = values(' . $field . ')';
                }
            }
        }

        for ($i=0; $i < sizeOf($fields); $i++){
            $preparedValues[] = '?';
        }

        $fields = implode(', ', $fields);
        $preparedValues = implode(', ', $preparedValues);

        $sql = "
            insert into $table ($fields)
                values ($preparedValues)
        ";

        if (isset($updates)) {
            $updates = implode(', ', $updates);

            $sql .= " on duplicate key update $updates";
        }

//         debug($sql);
//         debug($params);
//         debug($values);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($params, ...$values);
        $stmt->execute();

        if ($returnId) {
            return $this->sqlSelect("SELECT LAST_INSERT_ID();");
        }

        return $stmt->affected_rows;
    }

    public function email($toAddresses, $subject, $body, $ccAddresses = null, $user_id = null, $attachment = null) {
        $mail = new PHPMailer(true);                              // Passing `true` enables exceptions
        $config = parse_ini_file(
            in_array($_SERVER["REMOTE_ADDR"], ['127.0.0.1', '::1', 'localhost', null]) ?
                'config.ini' :
                '/home/isaneu/private/config.ini'
        );

        try {
            //Server settings
            $mail->SMTPDebug = 0;                                 // Enable verbose debug output
            $mail->isSMTP();                                      // Set mailer to use SMTP
            $mail->Host = $config['smtp_host'];                   // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Username = $config['smtp_email'];              // SMTP username
            $mail->Password = $config['smtp_password'];           // SMTP password
            $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 587;                                    // TCP port to connect to

            //Recipients
            $mail->setFrom($config['smtp_email'], $config['smtp_username']);          //This is the email your form sends From

            if (!is_array($toAddresses)) {
                $toAddresses = array($toAddresses);
            }

            foreach ($toAddresses as $address) {
                $mail->addAddress($address); // Add a recipient address
            }

//        if (isset($ccAddresses)) {
//            foreach ($ccAddresses as $address) {
//                $mail->addCC($address);
//            }
//        }

            //$mail->addAddress('contact@example.com');               // Name is optional
            //$mail->addReplyTo('info@example.com', 'Information');
            //$mail->addBCC('bcc@example.com');

            //Attachments
            if (isset($attachment)) {
                $mail->AddStringAttachment($attachment['contents'], $attachment['name']);       // Add attachments
                //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
            }

            //Content
//        $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $body;
            //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            if ($mail->send()) {
                $event_type = 'emailSent';
                $details = 'Successfully sent email. Subject: "' . $subject . '"';
                //        echo 'Message has been sent';
            }
            else {
                $event_type = 'emailError';
                $details = 'Failed send step.';
            }
        } catch (Exception $e) {
            $event_type = 'emailError';
            $details = 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
        }

        $this->sqlInsert('event_logs', array(
            'slack_user_id' => isset($user_id) ? $user_id : null,
            'event_type' => $event_type,
            'details' => $details
        ));
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

    // todo optimize
    public function importSlackUsersToDb($users = null) {
        if (!isset($users)) {
            $users = $this->apiCall('users.list')['members'];
        }

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

    // todo more testing with new email function
    public function emailCommunity($fromUserId, $subject, $body, $house_id, $debug = false) {
        $sql = "
            select
                slack_user_id,
                email,
                house_id,
                is_admin
            from sl_users
            where deleted <> 1";

        $users = $this->sqlSelect($sql);

        $fromEmail = '';
        $userEmails = array();
        $adminEmails = array();

        // get email lists
        foreach ($users as $user) {
            if ($user['slack_user_id'] == $fromUserId) {
                $fromEmail = $user['email'];
            }
            if ($user['is_admin'] == 1) {
                $adminEmails[] = $user['email'];
            }

            if ($house_id != 'all') {
                if ($user['house_id'] == $house_id) {
                    $userEmails[] = $user['email'];
                }
            }
            else {
                $userEmails[] = $user['email'];
            }
        }

        // make sure author is included in email
        $userEmails = array_unique(array_merge($userEmails, array($fromEmail)), SORT_REGULAR);

        // if house specific, get user readable name
        if ($house_id !== 'all') {
            $house = $this->sqlSelect("select name from sl_houses where slack_group_id = '$house_id'");

            $recipientsStr = 'all residents and boarders of ' . $house;
        }
        else {
            $recipientsStr = 'all current RCHC community members'; //todo generalize
        }

        $body .= "\r\n\r\n" . '[This message was sent on behalf of ' . $fromEmail . ' to ' . $recipientsStr . ']';

        // if debug mode enabled, only send to admins
        if ($debug) {
            $toEmails = $this->config['CRON_EMAIL'];
            $emailDump = implode("\r\n", $userEmails);

            $subject = '[RCHC EMAIL TEST] ' . $subject;
            $body .= "\r\n\r\n" . '---' . "\r\n" . "This email was intended for the following email addresses: " . "\r\n" . $emailDump;
        }
        else {
            // send to all relevant community members
            $toEmails = $userEmails;

            // CC all current admins
            $cc = $adminEmails;

            $subject = '[RCHC] ' . $subject;
        }

        $this->email($toEmails, $subject, $body, isset($cc) ? $cc : null, $fromUserId);
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

    public function buildWorkCreditModal($user_id, $hideDesc = false, $admin_user_id = null) {
        $workCreditCheck = $this->sqlSelect("
                select * from wc_time_debits
                where slack_user_id = '$user_id'
                order by date_effective desc limit 1
            ");

        // user hasn't set up profile yet
        if (!$workCreditCheck) {
            return json_decode(file_get_contents('views/work-credit-warning.json'), TRUE);
        }

        $viewJson = json_decode(file_get_contents('views/submit-time-modal.json'), TRUE);
        $optionTemp = json_decode(file_get_contents('views/other-req-option.json'), true);

        $optionsLookup = $this->sqlSelect("select * from wc_lookup_other_req_types");

        foreach ($optionsLookup as $option) {
            $optionTemp['text']['text'] = $option['slack_emoji'] . ' ' . $option['name'];
            $optionTemp['value'] = $option['id'];
            $viewJson['blocks'][3]['accessory']['options'][] = $optionTemp;
        }

        if ($hideDesc) {
            $index = sizeof($viewJson['blocks']) - 1;

            unset($viewJson['blocks'][$index]);
        }

        if (isset($admin_user_id)) {
            $otherUserName = $this->sqlSelect("select real_name from sl_users where slack_user_id = '$user_id'");

            $userInfoBlock = array(
                'type' => 'section',
                'text' => array(
                    'type' => 'mrkdwn',
                    'text' => ":warning: You are logging time on behalf of *$otherUserName*. Please use responsibly."
                )
            );

            array_unshift($viewJson['blocks'], $userInfoBlock);

            $viewJson['private_metadata'] = $user_id;
        }

        return $viewJson;
    }

    public function getWorkCreditData($user_id = null, $dataOnly = false) {
        // build query to get hour counts
        $hourTypes = $this->sqlSelect('select * from wc_lookup_hour_types');
        $hourFields = array(
            'hours_credited' => 'wc_time_credits',
            'hours_debited' => 'wc_time_debits'
        );

        foreach($hourTypes as $type) {
            $label = $type['name'];

            $reportData['hourTypesLookup'][$type['id']] = $label;

            $combinedFields[] = array(
                'key' => strtolower($label) . '_hours',
                'label' => $label
            );

            foreach($hourFields as $field => $table) {
                $id = $type['id'];
                $newField = $field . '_' . $id;

                // get lifetime credit/debit totals
                $hourSubQueries[] = "
                    (select
                        sum(case when hour_type_id = $id
                            then $field
                            else 0 end)
                        from $table
                        where slack_user_id = u.slack_user_id
                    ) as '$newField'
                ";

                if ($table == 'wc_time_debits') {
                    $newField = 'next_debit_qty' . '_' . $id;

                    // get most recent hours due
                    $hourSubQueries[] = "
                        (select
                            $field
                            from $table
                            where slack_user_id = u.slack_user_id
                            and hour_type_id = $id
                            order by date_effective desc limit 1
                        ) as '$newField'
                    ";
                }
            }
        }

        $hourSubQueries = implode(', ', $hourSubQueries);

        $sql = "
            select
                u.slack_user_id,
                u.real_name,
                h.name as 'house',
                case when r.room is null
                    then 'Boarder'
                    else r.room end
                as 'room',
                $hourSubQueries
            from sl_users u
            left join sl_houses as h on h.slack_group_id = u.house_id
            left join sl_rooms as r on r.id = u.room_id and r.house_id = u.house_id
            where u.deleted <> 1 and u.is_guest <> 1 and u.house_id is not null
        ";

        if (isset($user_id)) {
            $sql .= " and u.slack_user_id = '$user_id'";
        }

        $sql .= " group by u.slack_user_id
            order by h.name, isnull(r.id), r.id, u.real_name
        ";

        // get user info (w/ hours)
        $memberData = $this->sqlSelect($sql, null, true);

        $hourFields = array_keys($hourFields);

        foreach($memberData as $data) {
            $house = $data['house'];
            unset($data['house']);

            foreach($hourTypes as $type) {
                $hourTypeId = $type['id'];
                $hourTypeLabel = $type['name'];

                $creditField = $hourFields[0];
                $debitField = $hourFields[1];
                $nextDebitField = 'next_debit_qty';

                $oldCreditField = $creditField . '_' . $hourTypeId;
                $oldDebitField = $debitField . '_' . $hourTypeId;
                $oldNextDebitField = $nextDebitField . '_' . $hourTypeId;

                $cellStyle = '';

                $combinedField = strtolower($hourTypeLabel) . '_hours';

                if (!$data[$oldNextDebitField]) {
                    $data['_cellVariants'][$combinedField] = '';
                }

                // subtract total debits from earned hours
                $hoursDiff = $data[$oldCreditField] - $data[$oldDebitField];

                if ($hoursDiff != 0) {
                    $data[$creditField][$hourTypeId] = $data[$oldNextDebitField] + $hoursDiff;
                }
                else {
                    $data[$creditField][$hourTypeId] = floatval($data[$oldDebitField]);
                }

                // strip empty decimal places
                $data[$nextDebitField][$hourTypeId] = floatval($data[$oldNextDebitField]);

                if (isset($data[$creditField][$hourTypeId]) && $hoursDiff >= 0) {
                    $cellStyle = 'positive';
                }
                else if ($hoursDiff < -$data[$oldNextDebitField]) {
                    $cellStyle = 'negative';
                }

                $data['_cellVariants'][$combinedField] = $cellStyle;

                unset($data[$oldNextDebitField]);
                unset($data[$oldDebitField]);
                unset($data[$oldCreditField]);
            }

            unset($data['slack_user_id']);

            if ($data['real_name'] == $this->userInfo['real_name']) {
                $reportData['userRecord'] = $data;
            }

            if (isset($user_id)) {
                $formattedHourTypes = array();

                foreach ($hourTypes as $type) {
                    $formattedHourTypes[$type['id']] = $type;
                }

                if ($dataOnly) {
                    return $data;
                }
                else {
                    return array(
                        'userData' => $data,
                        'hourTypes' => $formattedHourTypes
                    );
                }


            }
            else {
                $groupedMemberData[$house][] = $data;
            }
        }

        $reportData['members']['items'] = $groupedMemberData;

        $fields = array(
            array(
                'key' => 'real_name',
                'label' => 'Name'
            ),
            array(
                'key' => 'room',
                'label' => 'Room'
            )
        );

        $reportData['members']['fields'] = array_merge($fields, $combinedFields);
        $reportData['members']['mobileFields'] = array_merge($fields, array(array('key' => 'hours')));

        $reportData['currentPage'] = 1;
        $reportData['perPage'] = 3;

        $submissionData = $this->getWorkCreditSubmissions($user_id);

        if ($submissionData) {
            $fields = array(
                array(
                    'key' => 'timestamp',
                    'label' => 'Timestamp',
                    'tdClass' => 'timestamp-col'
                ),
                array(
                    'key' => 'real_name',
                    'label' => 'Member'
                ),
                array(
                    'key' => 'hours_credited',
                    'label' => 'Hours',
                    'thClass' => 'hours-col',
                    'tdClass' => 'hours-col'
                ),
                array(
                    'key' => 'name', // todo db fields could use some refactoring
                    'label' => 'Type'
                ),
                array(
                    'key' => 'contribution_date',
                    'label' => 'Date of Contribution'
                ),
                array(
                    'key' => 'description',
                    'label' => 'Description of Work Completed',
                    'tdClass' => 'description-col'
                )
            );

            if ($this->admin) {
                $fields = array_merge(array(array('key' => 'delete', 'label' => '', 'thClass' => 'delete-col', 'tdClass' => 'delete-col')), $fields);
            }

            $reportData['submissions'] = array(
                'fields' => $fields,
                'items' => $submissionData,
                'currentPage' => 1,
                'perPage' => 50
            );
        }
        else {
            $reportData['submissions'] = false;
        }

        return $reportData;
    }

    public function getMemberList() {
        $memberData = $this->sqlSelect("
            select
                slack_user_id,
                real_name,
                case when is_boarder = 1 and room_id is null
                    then 'Boarder'
                    else room end
                as 'room',
                email,
                phone,
                case when sh.name is null
                    then '[Other]'
                    else sh.name end
                as 'house'
            from sl_users su
            left join sl_houses sh on su.house_id = sh.slack_group_id
            left join sl_rooms sr on su.room_id = sr.id and su.house_id = sr.house_id
            where deleted = 0
            order by house, real_name
        ");

        foreach($memberData as $data) {
            $house = $data['house'];
            unset($data['house']);

//            if ($data['real_name'] == $this->userInfo['real_name']) {
//                $reportData['userRecord'] = $data;
//            }

            $groupedMemberData[$house][] = $data;
        }

        return array(
            'members' => $groupedMemberData,
            'fields' => array(
                array(
                    'key' => 'slack_user_id',
                    'label' => '',
                    'thClass' => 'hours-col',
                    'tdClass' => 'hours-col'
                ),
                array(
                    'key' => 'real_name',
                    'label' => 'Name'
                ),
                array(
                    'key' => 'room',
                    'label' => 'Room'
                ),
                array(
                    'key' => 'email',
                    'label' => 'Email'
                ),
                array(
                    'key' => 'phone',
                    'label' => 'Phone'
                )
            )
        );
    }

    public function getWorkCreditSubmissions($user_id = null) {
        $sql = "
            select
                " . (!isset($user_id) ? "tc.id," : "") . "
                date_format(tc.timestamp, '%c/%e/%Y %l:%i:%s %p') as timestamp,
                " . (!isset($user_id) ? "u.real_name," : "") . "
                tc.hours_credited,
                lht.name,
                tc.contribution_date,
                concat_ws(
                    ' ',
                    (case when tc.description is null
                         then lort.name
                    else tc.description end),
                    (case when tc.submitted_by is not null
                        then concat(
                            '[Submitted by ',
                            (select real_name from sl_users where slack_user_id = tc.submitted_by),
                            ' on behalf of this user]'
                        )
                    end)
                ) as description
            from wc_time_credits as tc
            left join sl_users as u on u.slack_user_id = tc.slack_user_id
            left join wc_lookup_hour_types as lht on lht.id = tc.hour_type_id
            left join wc_lookup_other_req_types as lort on lort.id = tc.other_req_id
        ";

        if (isset($user_id)) {
            $sql .= " where u.slack_user_id = '$user_id'";
        }

        $sql .= " order by tc.id desc";

        return $this->sqlSelect($sql, null, true);
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

        return isset($inputValues) ? $inputValues : null;
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
            $profileData = array(
                'user' => $user_id,
                'profile' => array(
                    'real_name' => $inputValues['real_name'],
                    'display_name' => $inputValues['display_name'],
                    'phone' => $inputValues['phone']
                )
            );

            if (isset($this->config['PRONOUNS_FIELD_ID'])) {
                $profileData['fields'] = array(
                    $this->config['PRONOUNS_FIELD_ID'] => array(
                        'value' => $inputValues['pronouns']
                    )
                );
            }

            $result = $this->apiCall(
                'users.profile.set',
                $profileData,
                'write'
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

    // todo - for adding modifiers to requirements
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

    public function scheduleHoursDebit($user_id, $typeId, $newMember = false) {
        $userInfo = $this->sqlSelect("select is_boarder, wc_only from sl_users where slack_user_id = '$user_id'");
        $hourTypes = $this->sqlSelect("select name, default_qty, default_qty_boarder from wc_lookup_hour_types where id = $typeId");

        $defaultHours = $userInfo['is_boarder'] == 1 ? $hourTypes['default_qty_boarder'] : $hourTypes['default_qty'];

        $hoursMod = $this->sqlSelect("select qty_modifier from wc_user_req_modifiers where hour_type_id = $typeId and slack_user_id = '$user_id'");

        if ($userInfo['wc_only'] == 1) {
            if ($hoursMod) {
                $hours = $hoursMod;
            }
            else {
                return false;
            }
        }
        else {
            $hours = $defaultHours + $hoursMod;
        }

        if ($newMember && $typeId != 3) {
            $hours = $hours / 2;
        }

        $interval = '+1 month';
        $dateFormat = 'Y-m-01';

        if ($typeId == 3) {
            $interval = '+1 year';
            $dateFormat = 'Y-m-01';
        }
        $date = strtotime($interval, strtotime(date("Y-m-d")));
        $date = date($dateFormat, $date);

        $this->sqlInsert('wc_time_debits', array(
            'date_effective' => $date,
            'slack_user_id' => $user_id,
            'hours_debited' => $hours,
            'hour_type_id' => $typeId,
            'description' => 'Automated ' . ($typeId == 3 ? 'yearly' : 'monthly') . ' hours debit'
        ));

        return $hourTypes['name'];
    }
}