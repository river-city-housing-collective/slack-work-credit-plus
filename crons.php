<?php
require_once('functions.php');
require_once('vendor/autoload.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = new Slack($conn);

use Spipu\Html2Pdf\Html2Pdf;

$logMsg = '';

switch($argv[1]) {
    case 'scheduleHoursDebit':
        $users = $slack->sqlSelect('select * from sl_users where is_guest = 0 and deleted = 0 and house_id is not null and (wc_pause_expiration_date <= curdate() or wc_pause_expiration_date is null)');

        $results = array();

        foreach ($users as $user) {
            $user_id = $user['slack_user_id'];

            $returnLabel = $slack->scheduleHoursDebit($user_id, 1);
//            $results[] = $returnLabel;

            if($user['is_boarder'] == 0) {
                $returnLabel = $slack->scheduleHoursDebit($user_id, 2);
//                $results[] = $returnLabel;

                // start of new leasing period - schedule maintenance hours for next
                if (date('n') == 8 || $argv[2] == '-init') {
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
//        $slack->email($slack->config['CRON_EMAIL'], 'Work Credit Hours Debit', $logMsg);

        break;
    case 'checkLeaseTerminations':
        $terminatedUsers = $slack->sqlSelect("select * from sl_users where lease_termination_date <= curdate()", null, true);

        if (!$terminatedUsers) {
            exit("no expired leases found \n");
        }

        foreach($terminatedUsers as $user) {
            ob_start();

            $user['pdf'] = new Html2Pdf();

            $user_id = $user['slack_user_id'];
            $real_name = $user['real_name'];

            $additionalUserInfo = $slack->sqlSelect("
                select
                    su.*,
                    sh.name as 'house_name',
                    sr.room as 'room_number'
                from sl_users su
                left join sl_houses sh on su.house_id = sh.slack_group_id
                left join sl_rooms sr on su.room_id = sr.id and su.house_id = sr.house_id
                where slack_user_id = '$user_id'
            ");

            $data = $slack->getWorkCreditData($user_id, true);
            $hourTypes = $slack->sqlSelect('select * from wc_lookup_hour_types');

            $subject = $slack->config['EMAIL_LABEL'] . " Final Work Credit Report for $real_name";
            $body = "Hi!\n\nToday is $real_name's last day at RCHC. I've compiled this report about their work credit progress:\n\n";

             foreach($hourTypes as $typeInfo) {
                $hourLabel = $typeInfo['name'];
                $hourDiff = $data['hours_credited'][$typeInfo['id']];

                // store for pdf
                $user['hoursData'][] = array(
                    "label" => $hourLabel,
                    "diff" => $hourDiff,
                    "color" => $hourDiff >= 0 ? "#49beb7" : "#ff5959"
                );

                $body .= "$hourLabel Hours: $hourDiff\n";
            }

            $body .= "\nI've also attached a more detailed report for your reference. ";

            if (!$user['is_deleted']) {
                $body .= "According to my records, this person's Slack account is still active, so be sure to disable it (assuming they aren't sticking around as a boarder)!";
            }

            $body .= "\n\nLet me know if you need anything else!\n\nLove,\n" . $slack->config['BOT_NAME'];

            $submissions = $slack->getWorkCreditSubmissions($user_id);

             include('userReport.php');
             $output = ob_get_clean();

            $user['pdf']->writeHTML($output);
             $pdf = $user['pdf']->output('report.pdf', 'S');
//
             $real_name = str_replace(' ', '_', strtolower($real_name));

             $file = array(
                 'name' => "report_" . $real_name . "_" . date("m-d-Y") . ".pdf",
                 'contents' => $pdf
             );

            $reportEmails = $slack->config['REPORT_EMAILS'];
            $reportEmails = $slack->sqlSelect("select email from sl_users where slack_user_id in ($reportEmails)");

            $completed = $slack->email($reportEmails, $subject, $body, null, null, $file);

            if ($completed) {
                $slack->sqlInsert('sl_users', array('slack_user_id' => $user_id, 'lease_termination_date' => null));

                echo "successfully processed termination for ". $real_name . "\n";
            }
            else {
                echo "failed to process termination for ". $real_name . "\n";
            }

            ob_flush();
            ob_end_clean();
         }

        $logMsg = 'Processed terminations for ' . sizeof($terminatedUsers) . ' users.';

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