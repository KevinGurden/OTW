<?php
/*
Get scores for health by day from the encol database.

Parameters:
    id: company identifier. Integer

Return:
    status: 200 for success, 300+ for error
    message: High level error message
    sqlerror: detailed sql error
    scores: an array of whistle objects

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';

$response = array(); // Array for JSON response

$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {

    $id = escape($con, 'id', 0); // Escape company id to avoid injection vunerability

    // Get a list of whistles
    $select = "SELECT * FROM health WHERE company_id=$id";
    $result = mysqli_query($con, $select);
    $response["query"] = "$select";

    if ($result === false) { // Will either be false or an array
        // query failed to run
        http_response_code(400);
        $response["message"] = "Query failed";
        $response["sqlerror"] = mysqli_error($con);
    } else {
        $scores = array();

        // Check for empty result
        if (mysqli_num_rows($result) > 0) {
            // Loop through all results
            
            
            while ($score = mysqli_fetch_assoc($result)) {
                $scores[] = $score;
            };
            $response["scores"] = $scores;

            http_response_code(200); // Success
            $response["message"] = "Success";
            $response["sqlerror"] = "";
        } else {
            http_response_code(200); // Success but null return
            $response["message"] = "No scores found for company '$id'";
            $response["scores"] = $scores;
        };
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
