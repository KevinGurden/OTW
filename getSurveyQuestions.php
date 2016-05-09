<?php
/*
Get a list of survey questions from the encol database.

Parameters:
    none

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    questions: an array of question objects

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
    // if (isset($_GET['id']) && isset($_GET['user'])) {
        //$id = mysqli_real_escape_string($con, $_GET['id']); // Escape to avoid injection vunerability
        //$user = mysqli_real_escape_string($con, $_GET['user']); // Escape to avoid injection vunerability
        mysqli_set_charset($con, "utf8");

        // Get a list of questions
        $select = "SELECT * FROM questions";
        $result = mysqli_query($con, $select);
        $response["query"] = "$select";

        // Check for empty result
        if (mysqli_num_rows($result) > 0) {
            // Loop through all results
            $questions = array();
            
            while ($question = mysqli_fetch_assoc($result)) {
                $questions[] = $question;
                $response["lastquestion"] = $question;
            }
            $response["questions"] = $questions;

            // Success
            $response["status"] = 200;
            $response["message"] = "Success";
            $response["sqlerror"] = "";

            // Echoing JSON response
            echo json_encode($response);
        } else {
            // no questions found
            $response["status"] = 200;
            $response["message"] = "No questions found";

            // echo no questions JSON
            echo json_encode($response);
        };
    // } else { // no id or user present
    //     $response["status"] = 402;
    //     $response["message"] = "Missing 'id' or 'user' parameter";
    //     echo json_encode($response);
    // };
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
