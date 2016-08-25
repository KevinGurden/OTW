<?php
/*
Create a new whistle in the encol database.

Security: Requires JWT "Bearer <token>" 

Data passed:
	tba

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
announce('createWhistle', $_POST); // Announce us in the log

$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK

	$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
	if (connected($con, $response)) {
	    mysqli_set_charset($con, "utf8"); // Set the character set to use

		$_POST = json_decode(file_get_contents('php://input'), true);

		// Escape the values to ensure no injection vunerability
		$title = escape($con, 'title', '');
		$description = escape($con, 'description', '');
		$recommendation = escape($con, 'recommendation', '');
		$status = escape($con, 'status', '');
		$cat = escape($con, 'cat','');
		$subdate = escape($con, 'subdate', '');
		$date = escape($con, 'date', '');
		$type_selected = escape($con, 'type_selected', '');
		$type_policy = escape($con, 'type_policy', '');
		$loc_main = escape($con, 'loc_main', '');
		$loc_detail = escape($con, 'loc_detail', '');
		$user = escape($con, 'user', '');
		$nick = escape($con, 'user_nick', '');
		$anon = escape($con, 'anon', 0);
		$company_id = escape($con, 'company_id', 0); // Default to 0-Unknown
		    
		// Issue the database create
		$cols = "title, description, recommendation, status, cat, subdate, date, type_selected, type_policy, ";
		$vals = "'$title', '$description', '$recommendation', '$status', '$cat', '$subdate', '$date', '$type_selected', '$type_policy', ";

		$cols = $cols . "loc_main, loc_detail, user, user_nick, anon, company_id";
		$vals = $vals . "'$loc_main','$loc_detail', '$user', '$nick', '$anon', $company_id";

		$insert = "INSERT INTO whistles($cols) VALUES($vals)";
		$result = mysqli_query($con, $insert);
		if ($result) { // Success
	        http_response_code(200);
	        $response["message"] = "Whistle created";
	        $response["id"] = mysqli_insert_id($con); // Return the id of the record added
	        $response["sqlerror"] = "";
		} else { // Failure
			error_log("$result: from $insert");
	        http_response_code(402);
	        $response["message"] = "Create whistle failed";
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
