<?php
/*
Get a list of whistles from the encol database. If the user is '' then return all whistles for the company

Security: Requires JWT "Bearer <token>" 

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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';
include 'fn_jwt.php';
include 'fn_debug.php';

announce('getWhistles', $_GET); // Announce us in the log
$response = array();

$claims = token();
if ($claims != false) { // Token was OK
    debug('claims: '.$cls);

    $con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
    if (connected($con, $response)) {
        mysqli_set_charset($con, "utf8"); // Set the character set to use
        if (isset($_GET['id']) && isset($_GET['user'])) {
            $id = escape($con, 'id', 0); // Escape to avoid injection vunerability
            $user = escape($con, 'user', ''); // Escape to avoid injection vunerability
        
            // Get a list of whistles
            if ($user == '') {
                $and_user = '';                     // Return whistles for all users
            } else {
                $and_user = "AND user='$user'";     // Limit to a particular user
            }
            $select = "SELECT * FROM whistles WHERE company_id=$id $and_user";
            $result = mysqli_query($con, $select);
            $response["query"] = "$select";

            // Check for empty result
            if (mysqli_num_rows($result) > 0) {
                // Loop through all results
                $whistles = array();
                
                while ($whistle = mysqli_fetch_assoc($result)) {
                    $whistles[] = $whistle;
                }
                $response["whistles"] = $whistles;

                http_response_code(200); // Success
                $response["message"] = "Success";
                $response["sqlerror"] = "";
            } else {
                http_response_code(200); // Success but no whistles found
                $response["message"] = "No whistles found for user '$user' and company '$id'";
            };
        } else { // no id or user present
            http_response_code(402); // Failure
            $response["message"] = "Missing 'id' or 'user' parameter";
        };
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
 */

?>
