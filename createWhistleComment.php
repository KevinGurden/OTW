<?php
/*
Create a new whistle comment in the encol database.

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

function escape($con, $field, $default) {
    if (isset($_POST[$field])) {
    	return mysqli_real_escape_string($con, $_POST[$field]);
    } else {
    	return $default;
    };
};

// Array for JSON response
$response = array();

// Connect to db
error_log("createWhistleComment: Start");
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (!$con) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
    $response["status"] = 401;
    $response["message"] = "Failed to connect to DB";
    $response["sqlerror"] = mysqli_connect_error();
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
	$_POST = json_decode(file_get_contents('php://input'), true);
	$response["received"] = $_POST;

	// Escape the values to ensure no injection vunerability
	$cat = escape($con, 'cat', '');
	$catid = escape($con, 'catid', 0);
	$type = escape($con, 'type', 'comment');
	$content = escape($con, 'content');
	$fromuser = escape($con, 'fromuser', '');
	$date = escape($con, 'date', '');
	$anon = escape($con, 'anon', 0);
	$company_id = escape($con, 'company_id', 0); // Default to 0-Unknown
	    
	// Issue the database create
	$cols = "cat, catid, type, content, fromuser, date, anon, company_id";
	$vals = "'whistle', $catid, '$type', '$content', '$fromuser', '$date', $anon, $company_id";

	$insert = "INSERT INTO activity($cols) VALUES($vals)";
	$result = mysqli_query($con, $insert);
	if ($result) { // Success
		error_log("createWhistleComment: success");
        $response["status"] = 200;
        $response["message"] = "Comment created";
        $response["id"] = mysqli_insert_id($con); // Return the id of the record added
        $response["sqlerror"] = "";
	} else { // Failure
		error_log("$result: from $insert");
        $response["status"] = 402;
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
 */
?>
