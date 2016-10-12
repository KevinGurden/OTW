<?php
/*
Delete a media file from the encol database.

Security: Requires JWT "Bearer <token>" 

Parameters:
    id: company identifier. Integer
    user: userid. String
    mediaId: Integer
    debug: Turn on debug statements. Boolean

Return:
    status: 200 for success, 300+ for error
    message: High level error message
    sqlerror: detailed sql error
    media: an array of media objects

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';
include 'fn_jwt.php';
include 'fn_debug.php';

announce(__FILE__, $_GET); // Announce us in the log
$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK

    $con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
    if (connected($con, $response)) {
        // mysqli_set_charset($con, "utf8"); // Set the character set to use

        $id = escape($con, 'id', 0); // Escape to avoid injection vunerability
        $user = escape($con, 'user', '');
        $mediaId = escape($con, 'mediaId', '');
        debug("deleting ".$id."/".$mediaId);

        // Get 1 record only
        $delete = "DELETE FROM media WHERE company_id=$id AND user='$user' AND id=$mediaId";
        debug("delete: ".$delete);
        $result = mysqli_query($con, $delete);
        $response["query"] = "$delete";

        if ($result === false) { // Will either be false or an array
            // query failed to run
            http_response_code(400);
            $response["message"] = "Delete failed";
            $response["sqlerror"] = mysqli_error($con);
        } else {
            http_response_code(200); // Success
            $response["message"] = "Deleted";
            $response["sqlerror"] = "";
        };
    };
} else {
    http_response_code($claims['status']); // Token Failure
    $response["message"] = $claims['message'];
};

echo json_encode($response); // Echo JSON response

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