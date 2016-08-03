<?php
/*
Get any test information from the encol database.

Parameters:
    id: The id of the test record to return. String
    debug: Turn on debugging

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    test: a test object e.g. the secret code used for testing with user 'test1'

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';

error_log("----- getTest.php ---------------------------"); // Announce us in the log

// Array for JSON response
$response = array();

$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use

    if (isset($_GET['id'])) {
        $id = escape($con, 'id', 0); // Escape to avoid injection vunerability
        // error_log('getTest: id: $id');
    
        // Get a test record
        $select = "SELECT * FROM test WHERE id=$id";
        $result = mysqli_query($con, $select);
        $response["query"] = "$select";

        // Check for empty result
        if (mysqli_num_rows($result) > 0) {
            // Get the first result
            $response["test"] = mysqli_fetch_assoc($result);
            http_response_code(200); // Success
            $response["message"] = "Success";
            $response["sqlerror"] = "";
        } else {
            http_response_code(401); // Failure. No questions found
            $response["message"] = "No test information found for 'test$id'";
        };
    } else { // no id present
        http_response_code(402); // Failure
        $response["message"] = "Missing id parameter";
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
