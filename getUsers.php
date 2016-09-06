<?php
/*
Get a list of users from the encol database.

Security: Requires JWT "Bearer <token>" 

Parameters:
    id: company identifier. Integer
    debug: Turn on debug statements. Boolean

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    spots: an array of spot objects
    debug: Turn on debug statements. Boolean

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';
include 'fn_jwt.php';
include 'fn_debug.php';

announce(__FILE__, $_GET);
$response = array(); // Array for JSON response

$claims = token($response);
if ($claims['result'] == true) { // Token was OK

    $con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
    if (connected($con, $response)) {
        mysqli_set_charset($con, "utf8"); // Set the character set to use

        if (isset($_GET['id'])) {
            debug('got id');
            $id = escape($con, 'id', 0); // Escape to avoid injection vunerability
        
            // Get a list of users
            $select = "SELECT * FROM users WHERE company_id=$id";
            debug('select: '.$select);
            $result = mysqli_query($con, $select);
            $response["query"] = "$select";

            // Check for empty result
            if ($result != false) {
                if (mysqli_num_rows($result) > 0) { 
                    $users = array();
                    while ($user = mysqli_fetch_assoc($result)) { // Loop through all results
                        $users[] = $user;
                    };
                    $response["users"] = $users;

                    http_response_code(200); // Success
                    $response["message"] = "Success";
                    $response["sqlerror"] = "";
                } else {
                    http_response_code(200); // Success but no users found
                    $response["message"] = "No users found for company '$id'";
                };
            } else {
                http_response_code(402); // Error in SQL
                $response["message"] = "Query failed";
                $response["sqlerror"] = mysqli_error($con);
            };
        } else { // no id or user present
            http_response_code(412); // Failure
            $response["message"] = "Missing 'id' or 'user' parameter";
            $response["sqlerror"] = "";
        };
    };
} else {
    http_response_code($claims['status']); // Token Failure
    $response["message"] = $claims['message'];
    $response["sqlerror"] = "";
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