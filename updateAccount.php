<?php
/*
Update account information in the encol database.

Security: Requires JWT "Bearer <token>" 

Data passed:
	hash: The hashed new password. String
    nickname: The nickname of the user. String
    username: The username of the user. String
	company_id: The id of the company in the company table
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
        $new_password_hash = $_POST['hash'];
        $new_nickname = escape($con, 'nickname', '');
        $username = escape($con, 'username', ''); 
        $company_id = escape($con, 'company_id', null); 

        if ($username != '' && $company_id != null) { // We have enough to target the user
            $sets = array();
            if ($new_password_hash != '') {
                $sets[] = "password='".$new_password_hash."',one_time_use=0"; // Turn off one time as well as the password has been changed
            };

            if ($new_nickname != '') {
                $sets[] = "nick='".$new_nickname."'";
            };

            // Issue the database update
            // UPDATE users 
            //     SET password='$new_password_hash', nickname='$new_nickname'
            //     WHERE username='$username' AND company_id=$company_id
            
            if (count($sets) > 0) {
                $sets_comma = implode(',', $sets);
                $update = "UPDATE users SET ".$sets_comma." WHERE username='$username' AND company_id=$company_id";
                debug('update: '.$update);  
                $result = mysqli_query($con, $update);
                if ($result) { // Success
                    http_response_code(200);
                    $response["message"] = "Account updated";
                    $response["sqlerror"] = "";
                } else { // Failure
                    debug("$result: from $update");
                    http_response_code(402);
                    $response["message"] = "Update Account failed";
                    $response["sqlerror"] = mysqli_error($con);
                };
            } else { // Success, but nothing to do
                http_response_code(200);
                $response["message"] = "Nothing to update";
                $response["sqlerror"] = "";
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
