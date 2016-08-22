<?php
/*
Update account information in the encol database.

Data passed:
	pass: The hashed new password. String
    nick: The nickname of the user. String
	company_id: The id of the company in the company table

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
*/
header('Access-Control-Allow-Methods: GET, POST, JSONP, OPTIONS');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_escape.php';

error_log("----- updateAnything.php ---------------------------"); // Announce us in the log

// Array for JSON response
$response = array();

// Connect to db
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use

    $_POST = json_decode(file_get_contents('php://input'), true);
    $id = got_int('id', null);
    $field1 = escape($con, 'field1', ''); $val1 = $_POST['val1'];
    $field2 = escape($con, 'field2', ''); $val2 = $_POST['val2'];

	// Issue the database update
	/*
	UPDATE $table 
		SET $field1=$val1,$field1=$val1 
		WHERE id=$id
	 */
	$sets = "SET ".$field1."=".$val1;
	if (isset($field2) && isset($val2)) {
		$sets = $sets.",".$field2."=".$val2;
	};
	$update = "UPDATE whistles ".$sets." WHERE id=$id";
	
	$result = mysqli_query($con, $update);
	if ($result) { // Success
        http_response_code(200);
        $response["message"] = "Whistle updated";
        $response["sqlerror"] = "";
	} else { // Failure
		error_log("$result: from $update");
        http_response_code(402);
        $response["status"] = 402;
        $response["message"] = "Update whistle failed";
        $response["sqlerror"] = mysqli_error($con);
    };
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
