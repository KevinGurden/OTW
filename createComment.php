<?php
/*
Create a new comment in the activity table within the encol database.

Parameters:
    cat: what type of comment; 'whistle', 'flag', etc. String
    catid: The id of the whistle/flag etc. Integer
    type: 'comment' or 'feedback' if the cat item is now in closed status. String
    content: The text of the comment. String
    etc

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
*/

header('Access-Control-Allow-Methods: GET, POST, JSONP, OPTIONS');
header('Access-Control-Allow-Headers: Cache-Control, Pragma, Origin, Authorization, Content-Type, X-Requested-With');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_escape.php';

error_log("----- createComment.php ---------------------------"); // Announce us in the log

// Array for JSON response
$response = array();

$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use

	$_POST = json_decode(file_get_contents('php://input'), true);
	$response["received"] = $_POST;

	// Escape the values to ensure no injection vunerability
	$cat = escape($con, 'cat', '');
    error_log('cat:'.$cat);
	$catid = got_int('catid', -1);
    error_log('catid:'.$catid);
	$type = escape($con, 'type', 'comment');
    error_log('type:'.$type);
	$content = escape($con, 'content', '');
    error_log('content:'.$content);
	$fromuser = escape($con, 'fromuser', '');
    error_log('fromuser:'.$fromuser);
    $fromnick = escape($con, 'fromnick', '');
    error_log('fromnick:'.$fromnick);
	$date = escape($con, 'date', '');
    error_log('date:'.$date);
	$anon = $_POST['anon'];
    error_log('anon:'.$anon);
	$company_id = got_int('company_id', 0); // Default to 0-Unknown
    error_log('company_id:'.$company_id);
	    
	// Issue the database create
	$cols = "cat, catid, type, content, fromuser, fromnick, date, anon, company_id";
	$vals = "'$cat', $catid, '$type', '$content', '$fromuser', '$fromnick', '$date', $anon, $company_id";

	$insert = "INSERT INTO activity($cols) VALUES($vals)";
	$result = mysqli_query($con, $insert);
	if ($result) { // Success
        http_response_code(200);
        $response["message"] = "Comment created";
        $response["id"] = mysqli_insert_id($con); // Return the id of the record added
        $response["sqlerror"] = "";
	} else { // Failure
		error_log("$result: from $insert");
        http_response_code(402);
        $response["message"] = "Create comment failed";
        $response["query"] = "$insert";
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
