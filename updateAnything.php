<?php
/*
Update any table with an id in the encol database.

Security: Requires JWT "Bearer <token>" 

Data passed:
	table: The encol table name. String
    id: The unique id of the item. Integer
	set1: A field to be updated, string
	val1: The value to assign to set1
	set2: A field to be updated, string
	val2: The value to assign to set2
    set3: A field to be updated, string
    val3: The value to assign to set3
    debug: Turn on debug statements. Boolean

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
*/
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_post_escape.php';
include 'fn_jwt.php';
include 'fn_debug.php';

$_POST = json_decode(file_get_contents('php://input'), true);
announce(__FILE__, $_POST); // Announce us in the log

$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK

    // Connect to db
    $con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
    if (connected($con, $response)) {
        mysqli_set_charset($con, "utf8"); // Set the character set to use

        $id = got_int('id', null);
        $table = escape($con, 'table', '');
        $field1 = escape($con, 'field1', ''); $val1 = $_POST['val1'];
        $field2 = escape($con, 'field2', ''); $val2 = $_POST['val2'];
        $field3 = escape($con, 'field3', ''); $val3 = $_POST['val3'];

    	// Issue the database update
    	/*
    	UPDATE $table 
    		SET $field1=$val1,$field2=$val2, etc 
    		WHERE id=$id
    	 */
    	$sets = "SET ".$field1."=".$val1;
    	if (isset($field2) && isset($val2)) {
    		$sets = $sets.",".$field2."=".$val2;
    	};
        if (isset($field3) && isset($val3)) {
            $sets = $sets.",".$field3."=".$val3;
        };
    	$update = "UPDATE ".$table." ".$sets." WHERE id=$id";
    	
    	$result = mysqli_query($con, $update);
    	if ($result) { // Success
            http_response_code(200);
            $response["message"] = "Updated";
            $response["sqlerror"] = "";
    	} else { // Failure
    		error_log("$result: from $update");
            http_response_code(402);
            $response["status"] = 402;
            $response["message"] = "Update failed";
            $response["sqlerror"] = mysqli_error($con);
        };
    };
} else {
    http_response_code($claims['status']); // Token Failure
    $response["message"] = $claims['message'];
};

header('Content-Type: application/json');
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
