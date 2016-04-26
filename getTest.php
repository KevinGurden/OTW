<?php
/*
Get any test information from the encol database.

Parameters:
    id: the id of the test record to return. String

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

// Array for JSON response
$response = array();

$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
    $response["status"] = 401;
    $response["message"] = "Failed to connect to DB";
    $response["sqlerror"] = mysqli_connect_error();
    echo json_encode($response);
} else {
    if (isset($_GET['id'])) {
        $id = mysqli_real_escape_string($con, $_GET['id']); // Escape to avoid injection vunerability
    
        // Get a test record
        $result = mysqli_query(
            $con, "SELECT * FROM test WHERE id=$id"
        );

        // Check for empty result
        if (mysqli_num_rows($result) > 0) {
            // Get the first result
            $response["test"] = mysqli_fetch_assoc($result);
            $response["status"] = 200;
            $response["message"] = "Success";
            $response["sqlerror"] = "";

            // Echoing JSON response
            echo json_encode($response);
        } else {
            // no record found
            $response["status"] = 401;
            $response["message"] = "No test information found for 'test$id'";

            // echo no whistles JSON
            echo json_encode($response);
        };
    } else { // no id present
        $response["status"] = 402;
        $response["message"] = "Missing id parameter";
        echo json_encode($response);
    };
};

/* 
Useful stuff:
    SSH for mac:
        chmod 400 otwkey.pem to encrypt key. Do this in the otwkey directory
        ssh -i otwkey.pem ec2-user@52.38.155.255 to start ssh for the server
        cd ~/../../var/log/httpd to get to the log files on opsworks stack
        sudo cat getwhistlesphp-error.log | more to show the error log
 */

?>
