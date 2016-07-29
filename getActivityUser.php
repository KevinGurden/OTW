<?php
/*
Get a list of activity records a particular user from the encol database.

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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';

error_log("----- getActivityUser.php ---------------------------"); // Announce us in the log

// Array for JSON response
$response = array();

// Connect to db
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use

    if ( isset($_GET['user']) and isset($_GET['tables']) ) {
        // Escape the values to ensure no injection vunerability
        $user = escape($con, 'user', '');
        $tables = escape($con, 'tables', '');
        error_log("tables: '".$tables."'");

        /*
        SELECT a.*,t0.title AS 'whistle_title' 
            FROM whistles t0 
                INNER JOIN activity a ON t0.id = a.catid WHERE t0.user='$user' AND a.cat='whistle'
        UNION
        SELECT a.*,t1.title AS 'flag_title1' 
            FROM flags t0 
                INNER JOIN activity a ON t1.id = a.catid WHERE t1.user='$user' AND a.cat='flag'
        */

        $tables = explode(" ", $tables);
        $selects = array();
        foreach ($tables as $count => $table) {
            $cat = substr($table, 0, -1); // Drop the s at the end of the table name
            $cols = "a.*,t".$count.".title AS '".$cat."_title'";
            $from = $table." t".$count;
            $on = "t".$count.".id=a.catid";
            $where = "t".$count.".user='".$user."' AND a.cat='".$cat."'";
            $selects[$count] = "SELECT ".$cols." FROM ".$from." INNER JOIN activity a ON ".$on." WHERE ".$where;
        };
        $query = implode(" UNION ", $selects);
        error_log("getActivityUser: query: ".$query);

        // Get a list of activity. Select any table record that has the correct user defined and return any activity associiated with that user.
        // if ($table == 'whistles' || $table == 'flags') { // Add whistle/flag title
        //     $query = "SELECT a.*, t.title AS 'cat_title' FROM $table t INNER JOIN activity a ON t.id = a.catid WHERE t.user='$user'";
        // } else {
        //     $query = "SELECT a.* FROM $table t INNER JOIN activity a ON t.id = a.catid WHERE t.user='$user'";
        // };
        $result = mysqli_query($con, $query);
        
        if (mysqli_num_rows($result) > 0) { // Check for empty result
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
            $response["message"] = "No activity found for user '$user'";

            // echo no whistles JSON
            echo json_encode($response);
        };
    } else {
        error_log("getActivityUser: 'user' and 'table' must be provided");
        $response["status"] = 402;
        $response["message"] = "'user' and 'table' must be provided";
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
