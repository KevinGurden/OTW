<?php
/*
Update a user in the encol database.

Security: Requires JWT "Bearer <token>" 

Parameters:
	name: User's full name. Optional string
	nick: User's nickname. Optional string
	email: User's email address. Optional string
	username: User's username. String
	password: User's password. Optional string
	oneTime: Whether this password is a use-once: Optional boolean
	expire: The date the password expires: Optional timestamp
	use_encol: Permission to use. Optional boolean
    use_comp_all: Permission to use. Optional boolean
    use_comp_assign: Permission to use. Optional boolean
    use_register: Permission to use. Optional boolean
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

function does_exist($con, $user, $id) { // Is this new user unique
	$select = "SELECT * FROM users WHERE LOWER(username)=LOWER('$user') AND company_id=$id";
    debug('select: '.$select);
    $result = mysqli_query($con, $select);
    
    // Check for result
    return $result != false && mysqli_num_rows($result) == 1;
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
		$name = escape($con, 'name', false);
		$nick = escape($con, 'nick', false);
		$email = escape($con, 'email', false); 
		$username = escape($con, 'username', false);
		$password_hash = escape($con, 'password', false);
		$use_once = escape($con, 'oneTime', 1);
		$expire_date = escape($con, 'expire', false);
		$use_encol = escape($con, 'use_encol', 1);
		$use_comp_all = escape($con, 'use_comp_all', 0);
		$use_comp_assign = escape($con, 'use_comp_assign', 0);
		$use_register = escape($con, 'use_register', 0);
		$use_ceo360 = escape($con, 'use_ceo360', 0);
		$company_id = escape($con, 'company_id', 0); // Default to 0-Unknown
		    
		// Check that the user already exists
		if (does_exist($con, $username, $company_id)) {
			/*
			UPDATE users SET name=$name, etc WHERE username="kg@rxh.not" AND company_id=$company_id;
			*/
			$sets = array();
			if ($name != false) {$sets[] = "name='$name'";};
			if ($nick != false) {$sets[] = "nick='$nick'";};
			if ($email != false) {$sets[] = "email='$email'";};
			if ($password_hash != false && $expire_date != false) {
				$sets[] = "password='$password_hash'";
				$sets[] = "one_time_use=$use_once";
				$sets[] = "one_time_expire='$expire_date'";
			};
			$sets[] = "use_comp_all=$use_comp_all"; $sets[] = "use_comp_assign=$use_comp_assign";
			$sets[] = "use_encol=$use_encol"; $sets[] = "use_register=$use_register"; $sets[] = "use_ceo360=$use_ceo360";

			$sets_comma = implode(',', $sets);
			
			$update = "UPDATE users SET $sets_comma WHERE username='$username' AND company_id=$company_id";
			$update = mysqli_query($con, $update);
			debug("update: ".$update);

			if ($result) { // Success
		        http_response_code(200);
		        $response["message"] = "User updated";
		        $response["id"] = mysqli_insert_id($con); // Return the id of the record added
		        $response["sqlerror"] = "";
			} else { // Failure
		        http_response_code(402);
		        debug("insert result: ".mysqli_error($con));
		        $response["message"] = "Update user failed";
		        $response["sqlerror"] = mysqli_error($con);
		    };
		} else { // User already exists
			http_response_code(409);
	        debug("User not found");
	        $response["message"] = "User name not found";
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
