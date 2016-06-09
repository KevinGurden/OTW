<?php
/*
Update the the whistle health metrics in the health table for a particular day.
This should be executed after a whistle has been submitted to the database.

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
            whistle_open = (
                SELECT 
                    COUNT(*) FROM whistles
                    WHERE company_id = $cid AND status != 'closed' AND cat = 'whistle'
            )
    ON DUPLICATE KEY 
        UPDATE whistle_open = (
            SELECT 
                COUNT(*) FROM whistles
                WHERE company_id = 1 AND status != 'closed' AND cat = 'whistle'
        );
    */
    $lookup = $cid . ':' . $day;
    $whistles_open = "SELECT COUNT(*) FROM whistles WHERE company_id=$cid AND status != 'closed' AND cat = 'whistle'";
    $on_dup = "ON DUPLICATE KEY UPDATE whistle_open = ($whistles_open)";
    $insert = "INSERT INTO health SET day='$day', lookup='$lookup', company_id=$cid, whistle_open = ($whistles_open) $on_dup";
    error_log("insert: $insert");
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
    error_log('db_result: ' . $db_result);

    if ($db_result) {
        // Success
        http_response_code(200);
        // $response["status"] = 200;
        $response["message"] = "Success";
        $response["sqlerror"] = "";
        error_log('success');

        // Finally update the overall health scores
        score_health($con, $company_id, $day);
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
