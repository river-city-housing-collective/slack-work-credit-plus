<?php

require_once('/home/isaneu/rchc.coop/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = new Slack($conn);

$logMsg = '';

switch($argv[1]) {
    case 'scheduleHoursDebit':
        $users = $slack->sqlSelect('select * from sl_users where is_guest = 0 and deleted = 0 and house_id is not null');

        $results = array();

        foreach ($users as $user) {
            $user_id = $user['slack_user_id'];

            $returnLabel = $slack->scheduleHoursDebit($user_id, 1);
//            $results[] = $returnLabel;

            if($user['is_boarder'] == 0) {
                $returnLabel = $slack->scheduleHoursDebit($user_id, 2);
//                $results[] = $returnLabel;

                // last month of the year - schedule maintenance hours for next
                if (date('n') == 12 || $argv[2] == '-init') {
                    $returnLabel = $slack->scheduleHoursDebit($user_id, 3);
//                    $results[] = $returnLabel;
                }
            }
        }

        // for returning specifics
//        $labels = array_unique($results);
//        $counts = array();
//
//        foreach ($labels as $label) {
//            $counts[$label] = array_count_values($results);
//        }

        $logMsg = 'Successfully created hour debits for ' . sizeof($users) . ' members.';
        email('izneuhaus@gmail.com', 'RCHC Hours Debit', $logMsg);

        break;
    default:
        echo $argv[1];

    break;
}

// log event in db
$slack->sqlInsert('event_logs', array(
    'slack_user_id' => $slack->userId,
    'event_type' => $argv[1],
    'details' => $logMsg,
    'initiated_by_cron' => 1
));