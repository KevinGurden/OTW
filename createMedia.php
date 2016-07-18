<?php
/*
Create a new media item in the 'media' encol database.

Data passed:
	file64:     Base64 encoded file. Blob
    type:       Type of media object e.g. 'photo', 'video'. String
    photo_type: Type of photo if type=photo e.g. 'jpeg'. String
    user:       Username. String
    cId:        Company identifier in the 'company' table within the encol database. Integer

Return:
    status: 200 for success, 300+ for error
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

error_log("----- createMedia.php ---------------------------"); // Announce us in the log

// Array for JSON response
$response = array();

$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    // mysqli_set_charset($con, "utf8");

	$_POST = json_decode(file_get_contents('php://input'), true);

	// Escape the values to ensure no injection vunerability
    $type = escape($con, 'type', 'photo');
    $photo_type = escape($con, 'photo_type', '');
	$user = escape($con, 'user', '');
	$cId = escape($con, 'cId', 0); // Default to 0-Unknown
    // error_log('createMedia: '.$type.', '.$user.', '.$cId);
    $file64 = $_POST['file64'];

    // Issue the database create
    $cols = "type, photo_type, file, user, company_id";
    $vals = "'$type', '$photo_type', '$file64', '$user', $cId";

    $insert = "INSERT INTO media($cols) VALUES($vals)";
    error_log('createMedia: insert: '.$insert);
    $result = mysqli_query($con, $insert);
    if ($result) {
        http_response_code(200);
        $id = mysqli_insert_id($con);
        $response["id"] = $id;
        $response["message"] = "media ".$id." created";
        $response["sqlerror"] = "";
    } else { // Failure
        http_response_code(304);
        $response["message"] = "media create failed";
        $response["sqlerror"] = mysqli_error($con);
    };
    header('Content-Type: application/json');
    echo json_encode($response);
};

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
