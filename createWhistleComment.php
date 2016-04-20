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
header('Access-Control-Allow-Origin: *');
//header('Content-Type: application/json');

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
	$nonjson = file_get_contents("php://input", true);
	error_log("nonjson true double: " . $nonjson);
	$_POST = json_decode($nonjson, true);
	error_log("json true double: " . $_POST);
	$response["received1d"] = $_POST;

	$nonjson = file_get_contents("php://input", false);
	error_log("nonjson false double: " . $nonjson);
	$_POST = json_decode($nonjson, true);
	error_log("json false double: " . $_POST);
	$response["received0d"] = $_POST;

	$nonjson = file_get_contents('php://input', true);
	error_log("nonjson true single: " . $nonjson);
	$_POST = json_decode($nonjson, true);
	error_log("json true single: " . $_POST);
	$response["received1s"] = $_POST;

	$nonjson = file_get_contents('php://input', false);
	error_log("nonjson false single: " . $nonjson);
	$_POST = json_decode($nonjson, true);
	error_log("json false single: " . $_POST);
	$response["received0s"] = $_POST;

	// Escape the values to ensure no injection vunerability
	$cat = mysqli_real_escape_string($con, $_POST['cat']);
	$catid = mysqli_real_escape_string($con, $_POST['catid']);
	$content = mysqli_real_escape_string($con, $_POST['content']);
	$from = mysqli_real_escape_string($con, $_POST['from']);
	$date = mysqli_real_escape_string($con, $_POST['date']);
	if (isset($_POST['anon'])) {
		$anon = mysqli_real_escape_string($con, $_POST['anon']);
	} else {
		$anon = '0';
	};
	    
	// Issue the database create
	$cols = "cat, catid, type, content, from, date, anon";
	$vals = "'whistle', '$catid', 'comment', '$content', '$from', '$date', '$anon'";

	$insert = "INSERT INTO activity($cols) VALUES($vals)";
	error_log("createWhistleComment: inset=" . $insert);
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
