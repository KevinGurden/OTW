<?php
/*
Update account information in the encol database.

Data passed:
	password: The hashed new password. String
    nickname: The nickname of the user. String
    username: The username of the user. String
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
include 'fn_debug.php';

function verifyJWT($token, $time, $leeway, $ttl, $algorithm, $secret) {
    $algorithms = array('HS256'=>'sha256','HS384'=>'sha384','HS512'=>'sha512');
    if (!isset($algorithms[$algorithm])) return false;
    $hmac = $algorithms[$algorithm];
    $token = explode('.',$token);
    if (count($token)<3) return false;
    $header = json_decode(base64_decode(strtr($token[0],'-_','+/')),true);
    if (!$secret) return false;
    if ($header['typ']!='JWT') return false;
    if ($header['alg']!=$algorithm) return false;
    $signature = bin2hex(base64_decode(strtr($token[2],'-_','+/')));
    if ($signature!=hash_hmac($hmac,"$token[0].$token[1]",$secret)) return false;
    $claims = json_decode(base64_decode(strtr($token[1],'-_','+/')),true);
    if (!$claims) return false;
    if (isset($claims['nbf']) && $time+$leeway<$claims['nbf']) return false;
    if (isset($claims['iat']) && $time+$leeway<$claims['iat']) return false;
    if (isset($claims['exp']) && $time-$leeway>$claims['exp']) return false;
    if (isset($claims['iat']) && !isset($claims['exp'])) {
        if ($time-$leeway>$claims['iat']+$ttl) return false;
    }
    return $claims;
};

$_POST = json_decode(file_get_contents('php://input'), true);
announce('updateAccount', $_POST); // Announce us in the log

$token = null;
$headers = apache_request_headers();
if (isset($headers['Authorization'])) {
    debug('got Auth in header: '.$headers['Authorization']);
    $matches = array();
    preg_match('/Bearer token="(.*)"/', $headers['Authorization'], $matches);
    if (isset($matches[1])) {
        $token = $matches[1];
    };
}; 

// Array for JSON response
$response = array();

if ()
// Connect to db
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use

    // Various updates are possible
    $new_password = $_POST['password'];
    $new_nickname = escape($con, 'nickname', '');
    $username = escape($con, 'username', ''); 
    $company_id = escape($con, 'company_id', null); 

    if ($username != '' && $company_id != null) { // We have enough to target the user
        $sets = array();
        if ($new_password != '') {
            $sets[] = "password='".$new_password."',one_time_use=0"; // Turn off one time as well as the password has been changed
        };

        if ($new_nickname != '') {
            $sets[] = "nick='".$new_nickname."'";
        };

        // Issue the database update
        // UPDATE users 
        //     SET password='$new_password', nickname='$new_nickname'
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
                error_log("$result: from $update");
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
