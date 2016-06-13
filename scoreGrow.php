<?php
/*
Update the the grow health metrics in the health table for a particular day.
This should be executed after a grow has been submitted to the database, but can be called to also create a 'today' entry if one doesn't exist

Parameters:
    day: The day to apply the scores. Date stamp
    company_id: company that this applies to. Integer

Return:
    status: 200 for success, 300+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';
include 'fn_score_health.php';

function insert($con, $cid, $day) { // Insert a new record into 'health' or update if it already exists
    /* 
    INSERT INTO health  
        SET day='$day',
            lookup=$cid:$day,
            company_id=$cid,
            grow_closed_met = (
                SELECT COUNT(*) FROM goals WHERE company_id = $cid AND status = 'achieved'
            ),
            grow_closed_not_met = (
                SELECT COUNT(*) FROM goals WHERE company_id = $cid AND status = 'fail'
            ),
            grow_open_3m = (
                SELECT COUNT(*) FROM goals WHERE company_id = $cid AND status = 'ongoing' AND submitted >= CURRENT_DATE() - INTERVAL 3 MONTH
            )
    ON DUPLICATE KEY 
        UPDATE grow_closed_met = (
                SELECT COUNT(*) FROM goals WHERE company_id = $cid AND status = 'achieved'
            ),
            grow_closed_not_met = (
                SELECT COUNT(*) FROM goals WHERE company_id = $cid AND status = 'fail'
            ),
            grow_open_3m = (
                SELECT COUNT(*) FROM goals WHERE company_id = $cid AND status = 'ongoing' AND submitted >= CURRENT_DATE() - INTERVAL 3 MONTH
            );
    */
    $lookup = $cid . ':' . $day;
    $grow_closed_met = "grow_closed_met = (SELECT COUNT(*) FROM goals WHERE company_id=$cid AND status='achieved')";
    $grow_closed_not_met = "grow_closed_not_met = (SELECT COUNT(*) FROM goals WHERE company_id=$cid AND status='fail')";
    $grow_open_3m = "grow_open_3m = (SELECT COUNT(*) FROM goals WHERE company_id = $cid AND status = 'ongoing' AND submitted >= CURRENT_DATE() - INTERVAL 3 MONTH)";
    $grow_fields = "$grow_closed_met, $grow_closed_not_met, $grow_open_3m";

    $on_dup = "ON DUPLICATE KEY UPDATE $grow_fields";
    $insert = "INSERT INTO health SET day='$day', lookup='$lookup', company_id=$cid, $grow_fields $on_dup";
    // error_log("insert grows: $insert");
    $insert_result = mysqli_query($con, $insert);
    return $insert_result;
};

$response = array(); // Array for JSON response
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use

    // Escape the values to ensure no injection vunerability
    $day = escape($con, 'day', '');
    $company_id = got_int('company_id', 0);
    
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
        $response["day"] = score_health($con, $company_id, $day);
    } else {
        // Failure
        http_response_code(304);
        // $response["status"] = 304;
        $response["message"] = "Failed to create/update record";
        $response["sqlerror"] = mysqli_connect_error();
        error_log('failure');
    };
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
