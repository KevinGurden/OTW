<?php
/*
Delete a user from the encol database.

Security: Requires JWT "Bearer <token>" 

Parameters:
	id: User's identifier. Integer
	company_id: Integer
	debug: Turn on debug statements. Boolean

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
*/
header('Access-Control-Allow-Methods: GET OPTIONS');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';
include 'fn_jwt.php';
include 'fn_debug.php';

announce(__FILE__, $_GET);

$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK

	$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
	if (connected($con, $response)) {
	    mysqli_set_charset($con, "utf8"); // Set the character set to use

		// Escape the values to ensure no injection vunerability
		$id = got_int('id', -1);
		$company_id = escape($con, 'company_id', 0); // Default to 0-Unknown
		    
		// Check that we have the correct parameters
		if ($id > 0 && $company_id > 0) {

			$delete = "DELETE FROM users WHERE id=$id && compnay_id=$company_id";
			$result = mysqli_query($con, $delete);
			debug("delete: ".$delete);

			if ($result == true) { // SQL Success
				if (mysqli_affected_rows($con) > 0) { // Something was deleted
					http_response_code(200);
			        $response["message"] = "User deleted";
			        $response["sqlerror"] = "";
				} else { // Failure
		        	http_response_code(404);
		        	debug("user not found");
		        	$response["message"] = "User not found";
		        	$response["sqlerror"] = "";
		    	};
			} else { // SQL error
				http_response_code(409);
		        debug("User exists already");
		        $response["message"] = "User name is taken";
		        $response["sqlerror"] = mysqli_error($con);
		    };
		} else { // Bad params
        	http_response_code(412);
        	debug("No id or company");
        	$response["message"] = "Missing id or company";
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