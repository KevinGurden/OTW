<?php
/*
Create a new spot in the encol database.

Security: Requires JWT "Bearer <token>" 

Parameters:
	title: String
	description: String
	status: String
	status_detail: String
	cat: String
	sub_date: Date string
	date: String
	priority: Integer
	type_selected: String
	loc_main: String
	loc_detail: String
	raised_user: String
	raised_nick: String
	owned_user: String
	owned_nick: String
	media_large: Comma separated string list
	media_photos: Comma separated string list
	anon: 0=False, 1=True. Integer
	company_id: Integer
	debug: Turn on debug statements. Boolean

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
*/
header('Access-Control-Allow-Methods: POST OPTIONS');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_post_escape.php';
include 'fn_jwt.php';
include 'fn_debug.php';

$_POST = json_decode(file_get_contents('php://input'), true);
announce(__FILE__, $_POST);

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
		$status = escape($con, 'status', ''); 
		$status_detail = escape($con, 'status_detail', '');
		$cat = escape($con, 'cat','');
		$sub_date = escape($con, 'sub_date', '');
		$date = escape($con, 'date', '');
		$priority = escape($con, 'priority', 2);
		$type_selected = escape($con, 'type_selected', '');
		$loc_main = escape($con, 'loc_main', '');
		$loc_detail = escape($con, 'loc_detail', '');
		$raised_user = escape($con, 'raised_user', '');
		$raised_nick = escape($con, 'raised_nick', '');
		$owned_user = escape($con, 'owned_user', '');
		$owned_nick = escape($con, 'owned_nick', '');
		$media_large = escape($con, 'media_large', '');
		$media_photos = escape($con, 'media_photos', '');
		$anon = escape($con, 'anon', 0);
		$company_id = escape($con, 'company_id', 0); // Default to 0-Unknown
		    
		// Issue the database create
		$cols1 = "title, description, status, status_detail, cat, sub_date, date, priority, media_large, media_photos";
		$cols2 = "type_selected, loc_main, loc_detail, raised_user, raised_nick, owned_user, owned_nick, anon, company_id";
		$vals1 = "'$title', '$description', '$status', '$status_detail', '$cat', '$sub_date', '$date', '$priority', '$media_large', '$media_photos'";
		$vals2 = "'$type_selected', '$loc_main', '$loc_detail', '$raised_user', '$raised_nick', '$owned_user', '$owned_nick', '$anon', $company_id";
		
		$insert = "INSERT INTO spots($cols1, $cols2) VALUES($vals1, $vals2)";
		$result = mysqli_query($con, $insert);
		debug("insert: ".$insert);

		if ($result) { // Success
	        http_response_code(200);
	        $response["message"] = "Spot created";
	        $response["id"] = mysqli_insert_id($con); // Return the id of the record added
	        $response["sqlerror"] = "";
		} else { // Failure
	        http_response_code(402);
	        debug("insert result: ".mysqli_error($con));
	        $response["message"] = "Create spot failed";
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
