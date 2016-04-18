<?php
/*
Get a list of activity records a particular type(for) and a particular instance(id) from the encol database.

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

// Array for JSON response
$response = array();

// Connect to db
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
    $response["status"] = 401;
    $response["message"] = "Failed to connect to DB";
    $response["sqlerror"] = mysqli_connect_error();
    echo json_encode($response);
} else {
    if ( isset($_GET['id']) and isset($_GET['for']) ) {
        // Escape the values to ensure no injection vunerability
        $id = mysqli_real_escape_string($con, $_GET['id']);
        $for = mysqli_real_escape_string($con, $_GET['for']);

        // Get a list of activity
        $query = "SELECT * FROM activity WHERE id=$id AND for=$for";
        $result = mysqli_query($con, $query);

        // Check for empty result
        if (mysqli_num_rows($result) > 0) {
            // Loop through all results
            $activity = array();
            
            while ($act = mysqli_fetch_assoc($result)) {
                $activity[] = $act;
            }
            $response["activity"] = $activity;

            // Success
            $response["status"] = 200;
            $response["message"] = "Success";
            $response["sqlerror"] = "";

            // Echoing JSON response
            echo json_encode($response);
        } else {
            // no activity found
            $response["status"] = 200;
            $response["query"] = $query;
            $response["message"] = "No activity found";

            // echo no whistles JSON
            echo json_encode($response);
        };
    } else {
        error_log("'id' and 'for' must be provided");
        $response["status"] = 402;
        $response["message"] = "'id' and 'for' must be provided";
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
