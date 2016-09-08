<?php
/*
Create a new user in the encol database and email the new user.

Security: Requires JWT "Bearer <token>" 

Parameters:
	name: User's full name. String
	nick: User's nickname. String
	email: User's email address. String
	username: User's username. String
	password: User's password. String
	oneTime: Whether this password is a use-once: Boolean
	expire: The date the password expires: Timestamp
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

function doesnt_exist($con, $user, $id) { // Is this new user unique
	$select = "SELECT * FROM users WHERE LOWER(username)=LOWER('$user') AND company_id=$id";
    debug('select: '.$select);
    $result = mysqli_query($con, $select);
    
    // Check for empty result
    return $result != false && mysqli_num_rows($result) == 0;
};

$_POST = json_decode(file_get_contents('php://input'), true);
announce(__FILE__, $_POST);

$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK

	$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
	if (connected($con, $response)) {
	    mysqli_set_charset($con, "utf8"); // Set the character set to use

		// Escape the values to ensure no injection vunerability
		$name = escape($con, 'name', '');
		$nick = escape($con, 'nick', '');
		$email = escape($con, 'email', ''); 
		$username = escape($con, 'username', '');
		$password_hash = escape($con, 'password','');
		$use_once = escape($con, 'oneTime', true) == 1;
		$expire_date = escape($con, 'expire', '');
		$company_id = escape($con, 'company_id', 0); // Default to 0-Unknown
		    
		// Check that the user doesn't already exist
		if (doesnt_exist($con, $email, $company_id)) {
			$cols = "name, nick, email, username, password, one_time_use, one_time_expire, company_id";
			$vals = "'$name', '$nick', '$email', '$username', '$password_hash', $use_once, '$expire_date', $company_id";
			
			$insert = "INSERT INTO users($cols) VALUES($vals)";
			$result = mysqli_query($con, $insert);
			debug("insert: ".$insert);

			if ($result) { // Success
		        http_response_code(200);
		        $response["message"] = "User created";
		        $response["id"] = mysqli_insert_id($con); // Return the id of the record added
		        $response["sqlerror"] = "";
			} else { // Failure
		        http_response_code(402);
		        debug("insert result: ".mysqli_error($con));
		        $response["message"] = "Create user failed";
		        $response["sqlerror"] = mysqli_error($con);
		    };
		} else { // User already exists
			http_response_code(409);
	        debug("User exists already");
	        $response["message"] = "User name is taken";
	        $response["sqlerror"] = "";
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
