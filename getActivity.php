<?php
/*
Get a list of activity records a particular type(for) and a particular instance(id) from the encol database.

Parameters:
    cat: the category object that we want activity for e.g. 'whistle'. String
    catid: the identifier within the category. String

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    activity: an array of activity objects

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';

error_log("----- getActivity.php ---------------------------"); // Announce us in the log

// Array for JSON response
$response = array();

$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use

    if ( isset($_GET['catid']) and isset($_GET['cat']) ) {
        // Escape the values to ensure no injection vunerability
        $catid = escape($con, 'catid', '');
        $cat = escape($con, 'cat', '');

        // Get a list of activity
        $query = "SELECT * FROM activity WHERE catid='$catid' AND cat='$cat'";
        $response["query"] = $query;
        $result = mysqli_query($con, $query);

        // Check for empty result
        if (mysqli_num_rows($result) > 0) {
            // Loop through all results
            $activity = array();
            
            while ($act = mysqli_fetch_assoc($result)) {
                $activity[] = $act;
                error_log("content: ".$act['content']);
                $act['content1'] = $act['content']; // Bug: Content is being retuned as NaN in the receiving app. 
            }
            $response["activity"] = $activity;

            http_response_code(200); // Success
            $response["message"] = "Success";
            $response["sqlerror"] = "";
            
        } else {
            // no activity found
            http_response_code(200); // Success but no activity
            $response["query"] = $query;
            $response["message"] = "No activity found";
        };
    } else {
        error_log("'catid' and 'cat' must be provided");
        http_response_code(402); // Failure
        $response["message"] = "'cat' and 'catid' must be provided";
        $response["sqlerror"] = "";
    };
};
echo json_encode($response);

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
