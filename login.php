<?php
/*
Login and get any general info specific to the user's company from the encol database.

Parameters:
    username: the username e.g. 'test1'. String
    password: the identifier within the category. String
    force: (test only) force the return of a specific company information. Integer
    events: if set will return events information as well. Integer boolean; 0 or 1

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    init: a json object of company specific information
    debug: Turn on debug statements. Boolean

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_post_escape.php';
include 'fn_debug.php';

function collect_events($query) { // Mop up the eents query into an array
    if (mysqli_num_rows($query) > 0) {
        $events = array();
        
        while ($event = mysqli_fetch_assoc($query)) { // Loop through all results
            $events[] = $event;
        };
        return $events; 
    } else {
        return array();
    };
};
        
announce('login', $_GET); // Announce us in the log

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
        if (array_key_exists('force', $_GET)) {
            $company = $_GET['force'];
        } else {
            $company = 1; // Default to Acme
        };
        $events_needed = isset($_GET['events']) && $_GET['events']==1;

        $query = "SELECT * FROM company JOIN licences ON company_id=id WHERE id=$company";
        if ($events_needed) {
            $query = $query."; SELECT * FROM events";
        };

        $init_result = mysqli_multi_query($con, $query);

        // Check for bad or empty result
        if ($init_result == false) { // no init found
            http_response_code(204);
            $response["query"] = $query;
            $response["message"] = "No initialisation match for company $company";
        } else { // Init success
            $init_store = mysqli_store_result($con);
            $init = mysqli_fetch_assoc($init_store);
            
            if ($events_needed) {
                
                $events_result = mysqli_next_result($con);
                if ($events_result == false) { // no events found
                    http_response_code(204);
                    $response["query"] = $query;
                    $response["message"] = "No initialisation match for company $company";
                    $response["init"] = $init;
                } else { // Init & events success
                    $events_store = mysqli_store_result($con);
                    $events = collect_events($events_store); 

                    http_response_code(200);
                    $response["message"] = "Success";
                    $response["init"] = $init;
                    $response["events"] = $events;
                    $response["sqlerror"] = "";
                };
            } else {
                http_response_code(200);
                $response["message"] = "Success";
                $response["init"] = $init;
                $response["sqlerror"] = "";
            };
        };
    } else {
        error_log("'username' and 'password' must be provided");
        http_response_code(402);
        $response["message"] = "'username' and 'password' must be provided";
        $response["sqlerror"] = "";
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
