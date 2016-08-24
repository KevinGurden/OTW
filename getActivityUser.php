<?php
/*
Get a list of activity records a particular user from the encol database.

Security: Requires JWT "Bearer <token>" 

Parameters:
    cat: the category object that we want activity for e.g. 'whistles'. String
    tables: the table names to check a user match. Comma separated string
    user: the users identifier or an anonymous secret id. String

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    <cat-name> array: array of activity objects

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

announce('getActivityUser', $_GET); // Announce us in the log
$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK
    debug('got claims');

    // Connect to db
    $con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
    if (connected($con, $response)) {
        mysqli_set_charset($con, "utf8"); // Set the character set to use

        if ( isset($_GET['user']) and isset($_GET['tables']) ) {
            // Escape the values to ensure no injection vunerability
            $user = escape($con, 'user', '');
            $tables = escape($con, 'tables', '');

            /*
            SELECT a.*,t0.title AS 'cat_title' 
                FROM whistles t0 
                    INNER JOIN activity a ON t0.id = a.catid WHERE t0.user='$user' AND a.cat='whistle'
            UNION
            SELECT a.*,t1.title AS 'cat_title' 
                FROM flags t0 
                    INNER JOIN activity a ON t1.id = a.catid WHERE t1.user='$user' AND a.cat='flag'
            */

            $tables = explode(" ", $tables);
            $selects = array();
            foreach ($tables as $count => $table) {
                $cat = substr($table, 0, -1); // Drop the s at the end of the table name
                $cols = "a.*,t".$count.".title AS 'cat_title'";
                $from = $table." t".$count;
                $on = "t".$count.".id=a.catid";
                $where = "t".$count.".user='".$user."' AND a.cat='".$cat."'";
                $selects[$count] = "SELECT ".$cols." FROM ".$from." INNER JOIN activity a ON ".$on." WHERE ".$where;
            };
            $query = implode(" UNION ", $selects);
            // error_log("getActivityUser: query: ".$query);

            $result = mysqli_query($con, $query);
            
            if (mysqli_num_rows($result) > 0) { // Check for empty result
                // Loop through all results
                $activity = array();
                
                while ($act = mysqli_fetch_assoc($result)) {
                    $activity[] = $act;
                }
                $response["activity"] = $activity;

                http_response_code(200); // Success
                $response["message"] = "Success";
                $response["sqlerror"] = "";

            } else {
                http_response_code(200); // Success but no activity found
                $response["query"] = $query;
                $response["message"] = "No activity found for user '$user'";
            };
        } else {
            error_log("getActivityUser: 'user' and 'table' must be provided");
            http_response_code(402); // Failure
            $response["message"] = "'user' and 'table' must be provided";
            $response["sqlerror"] = "";
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
 */

?>
