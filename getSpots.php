<?php
/*
Get a list of spots from the encol database. 
If the user is '' then return all spots for the company otherwise return spots that are owned, raised or watched by the user.

Security: Requires JWT "Bearer <token>" 

Parameters:
    id: company identifier. Integer
    user: username. To return owned and watched spots for a user. String
    location: location. To return all spots for a location. String
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

        if (isset($_GET['id']) && ( isset($_GET['user']) || isset($_GET['user']) )) {
            debug('got id and (user or location)');
            $id = escape($con, 'id', 0); // Escape to avoid injection vunerability
            $user = escape($con, 'user', ''); 
            $location = escape($con, 'location', ''); 
        
            // Get a list of flags
            $and_loc = "";
            if ($user == '') { // Return flags for all users
                $and_user = ''; 
                if ($location != '') {
                    $and_loc = "AND loc_main='$location'";
                };
            } else { // Limit to a particular user
                $and_user = "AND (owned_user='$user' OR raised_user='$user' OR watchers LIKE '%$user%')";   
                if ($location != '') {
                    $and_loc = "AND loc_main='$location'";
                };
            };

            $select = "SELECT * FROM spots WHERE company_id=$id $and_user" $and_loc;
            debug('select: '.$select);
            $result = mysqli_query($con, $select);
            $response["query"] = "$select";

            // Check for empty result
            if (mysqli_num_rows($result) > 0) { 
                $spots = array();
                
                while ($spot = mysqli_fetch_assoc($result)) { // Loop through all results
                    $spots[] = $spot;
                }
                $response["spots"] = $spots;

                http_response_code(200); // Success
                $response["message"] = "Success";
                $response["sqlerror"] = "";
            } else {
                http_response_code(200); // Success but no flags found
                $response["message"] = "No spots found for user '$user' and company '$id'";
            };
        } else { // no id or user present
            http_response_code(402); // Failure
            $response["message"] = "Missing 'id' or 'user' parameter";
        };
    };
} else {
    http_response_code($claims['status']); // Token Failure
    $response["message"] = $claims['message'];
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