<?php
/*
Update anonymous access information in user table of the encol database.

Security: Requires JWT "Bearer <token>" 

Data passed:
	access_hash: The hashed access. String
    company_id: The id of the company to target the user. Integer
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

        // Various updates are possible
        $access_hash = $_POST['access_hash'];
        $company_id = got_int('company_id', -1);
        $username = $claims['usr'];

        if ($username != '' && $company_id >= 0) { // We have enough to target the user
            // Issue the database update
            // UPDATE users 
            //     SET anon_access#='$access_hash',anon_used=1
            //     WHERE username='$username' AND company_id=$company_id
            
            $update = "UPDATE users SET anon_access#='".$access_hash."',anon_used=1 WHERE username='".$username."' AND company_id='".$company_id."'";
            debug('update: '.$update);  
            $result = mysqli_query($con, $update);
            if ($result) { // Success
                http_response_code(200);
                $response["message"] = "Access code updated";
                $response["sqlerror"] = "";
            } else { // Failure
                debug("result ".$result." from ".$update);
                http_response_code(402);
                $response["message"] = "Access code update failed";
                $response["sqlerror"] = mysqli_error($con);
            };

        } else { // Failure
            debug("Need username and company id");
            http_response_code(401);
            $response["message"] = "Need username and company id";
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
