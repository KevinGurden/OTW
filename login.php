<?php
/*
Login and get any general info specific to the user's company from the encol database.

Parameters:
    username: the username e.g. 'test1'. String
    password: the identifier within the category. String
    force: (test only) force the return of a specific company information. Integer

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    init: a json object of company specific information

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_escape.php';

error_log("----- login.php ---------------------------"); // Announce us in the log

// Array for JSON response
$response = array();

// Connect to db
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use
    if ( isset($_GET['username']) and isset($_GET['password']) ) {
        // Escape the values to ensure no injection vunerability
        $username = escape($con, 'username', '');
        $password = escape($con, 'password', '');

        // Assume username and password are ok

        // Get company defaults
        if (isset($_GET['force'])) {
            $company = $_GET['force'];
        } else {
            $company = 1; // Default to Acme
        };
        error_log('login: force:'.$_GET['force'].', company: '.$company);
        $query = "SELECT * FROM company WHERE id=$company";
        $result = mysqli_query($con, $query);

        // Check for bad or empty result
        if ($result == false || mysqli_num_rows($result) == 0) { // no init found
            http_response_code(200);
            $response["query"] = $query;
            $response["message"] = "No initialisation match for company $company";

            // echo no init JSON
            echo json_encode($response);
        } else { // Success
            http_response_code(200);
            $response["message"] = "Success";
            $response["init"] = mysqli_fetch_assoc($result);
            $response["sqlerror"] = "";

            // Echoing JSON response
            echo json_encode($response);
        };
    } else {
        error_log("'username' and 'password' must be provided");
        http_response_code(402);
        $response["message"] = "'username' and 'password' must be provided";
        $response["sqlerror"] = "";
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
