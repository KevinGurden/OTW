<?php
/*
 * Get a list of whjistles
 * See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Array for JSON response
$response = array();

require_once __DIR__ . '/db_config.php';
// Connect to db
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
    $response["success"] = 0;
    $response["message"] = "Failed to connect to DB";
    $response["sqlerror"] = mysqli_connect_error();
    echo json_encode($response);
} else {

    // Get a list of whistles
    $result = mysqli_query(
        $con,
        "SELECT * FROM whistles"
    ) or die(mysqli_error($con));

    // Check for empty result
    if (mysqli_num_rows($result) > 0) {
        // Loop through all results
        $response["whistles"] = array();
        
        while ($row = mysqli_fetch_array($result)) {
            $whistle = array();
            $whistle["id"] = $row["id"];
            $whistle["title"] = $row["title"];

            // Push single link into final response array
            array_push($response["whistles"], $whistle);
        }
        $response["quaryresult"] = $result;

        // Success
        $response["success"] = 1;

        // Echoing JSON response
        echo json_encode($response);
    } else {
        // no songs found
        $response["success"] = 0;
        $response["message"] = "No whistles found";

        // echo no users JSON
        echo json_encode($response);
    };
};

/* 
Useful stuff:
    SSH for mac:
        chmod 400 otwkey.pem to encrypt key. Do this in the otwkey directory
        ssh -i otwkey.pem ec2-user@52.38.155.255 to start ssh for the server
        cd ~/../../var/log/httpd to get to the log files on opsworks stack
        cat getwhistlesphp-error.log | more to show the error log
 */

?>
