<?php
/*
Update the the grow health metrics in the health table for a particular day.
This should be executed after a grow has been submitted to the database, but can be called to also create a 'today' entry if one doesn't exist

Security: Requires JWT "Bearer <token>" 

Parameters:
    day: The day to apply the scores. Date stamp
    company_id: company that this applies to. Integer
    events: An array of event objects
    debug: Turn on debug statements. Boolean

Return:
    status: 200 for success, 300+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_post_escape.php';
include 'fn_scoring.php';
include 'fn_jwt.php';
include 'fn_debug.php';

function insert($con, $cid, $day) { // Insert a new record into 'health' or update if it already exists
    /* 
    INSERT INTO health  
        SET day='$day',
            lookup=$cid:$day,
            company_id=$cid,
            grow_closed_met = (
                SELECT COUNT(*) FROM goals WHERE company_id = $cid AND status = 'achieved' AND submitted<='$day'
            ),
            grow_closed_not_met = (
                SELECT COUNT(*) FROM goals WHERE company_id = $cid AND status = 'fail' AND submitted<='$day'
            ),
            grow_open_3m = (
                SELECT COUNT(*) FROM goals WHERE company_id = $cid AND status = 'ongoing' AND submitted<='$day' AND datediff(curdate(),submitted)<=90
            )
    ON DUPLICATE KEY 
        UPDATE grow_closed_met = etc
    */
    $lookup = $cid . ':' . $day;
    $days_90 = "DATEDIFF(CURDATE(),submitted)<=90";
    $before = "submitted<='$day'";

    $grow_closed_met = "grow_closed_met = (SELECT COUNT(*) FROM goals WHERE company_id=$cid AND status='achieved' AND $before)";
    $grow_closed_not_met = "grow_closed_not_met = (SELECT COUNT(*) FROM goals WHERE company_id=$cid AND status='fail' AND $before)";
    $grow_open_3m = "grow_open_3m = (SELECT COUNT(*) FROM goals WHERE company_id=$cid AND status='ongoing' AND $before AND $days_90)";
    $grow_fields = "$grow_closed_met, $grow_closed_not_met, $grow_open_3m";

    $on_dup = "ON DUPLICATE KEY UPDATE $grow_fields";
    $insert = "INSERT INTO health SET day='$day', lookup='$lookup', company_id=$cid, $grow_fields $on_dup";
    // error_log("insert grows: $insert");
    $insert_result = mysqli_query($con, $insert);
    return $insert_result;
};

$_POST = json_decode(file_get_contents('php://input'), true);
announce('createComment', $_POST); // Announce us in the log

$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK
    $con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
    if (connected($con, $response)) {
        mysqli_set_charset($con, "utf8"); // Set the character set to use

        // Escape the values to ensure no injection vunerability
        $_POST = json_decode(file_get_contents('php://input'), true);
        $day = escape($con, 'day', '');
        $company_id = got_int('company_id', 0);
        $events = $_POST['events'];
        
        $db_result = insert($con, $company_id, $day);
        // error_log('db_result: ' . $db_result);

        if ($db_result) {
            // Success
            http_response_code(200);
            // $response["status"] = 200;
            $response["message"] = "Success";
            $response["sqlerror"] = "";
            // error_log('success');

            // Finally update the overall health scores
            $response["day"] = score_health($con, $company_id, $day, $events, "scoreGrow");
        } else {
            // Failure
            http_response_code(304);
            // $response["status"] = 304;
            $response["message"] = "Failed to create/update record";
            $response["sqlerror"] = mysqli_error($con);
            error_log('Failed to create/update record');
        };
    };
} else {
    http_response_code($claims['status']); // Token Failure
    $response["message"] = $claims['message'];
};

echo json_encode($response); // Echo JSON response

/* 
Useful stuff:
    SSH for mac:
        chmod 400 otwkey.pem to encrypt key. Do this in the otwkey directory
        ssh -i otwkey.pem ec2-user@52.38.155.255 to start ssh for the server
        cd ~/../../var/log/httpd to get to the log files on opsworks stack
        sudo cat getwhistlesphp-error.log | more to show the error log
    GIT commands
        'git status' then 'git add <file>.php' then 'git commit -m 'message'' then 'git push origin master'
 */
?>
