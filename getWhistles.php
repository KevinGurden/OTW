<?php
/*
Get a list of whistles from the encol database.

Parameters:
    id: company identifier. Integer
    user: username. String

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    whistles: an array of whistle objects

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
    if (isset($_GET['id']) && isset($_GET['user'])) {
        $id = mysqli_real_escape_string($con, $_GET['id']); // Escape to avoid injection vunerability
        $user = mysqli_real_escape_string($con, $_GET['user']); // Escape to avoid injection vunerability
    
        // Get a list of whistles
        $result = mysqli_query(
            $con, "SELECT * FROM whistles WHERE user='$user' company_id=$id"
        );

        // Check for empty result
        if (mysqli_num_rows($result) > 0) {
            // Loop through all results
            $whistles = array();
            
            while ($whistle = mysqli_fetch_assoc($result)) {
                $whistles[] = $whistle;
            }
            $response["whistles"] = $whistles;

            // Success
            $response["status"] = 200;
            $response["message"] = "Success";
            $response["sqlerror"] = "";

            // Echoing JSON response
            echo json_encode($response);
        } else {
            // no whistles found
            $response["status"] = 200;
            $response["message"] = "No whistles found for user '$user' and company '$id'";

            // echo no whistles JSON
            echo json_encode($response);
        };
    } else { // no id or user present
        $response["status"] = 402;
        $response["message"] = "Missing 'id' or 'user' parameter";
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
