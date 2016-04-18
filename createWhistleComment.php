<?php
/*
Create a new whistle comment in the encol database.

Data passed:
	tba

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
*/
header('Access-Control-Allow-Methods: GET, POST, JSONP, OPTIONS');
header('Access-Control-Allow-Origin: *');

// Array for JSON response
$response = array();

// Connect to db
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
    $response["status"] = 401;
    $response["message"] = "Failed to connect to DB";
    $response["sqlerror"] = mysqli_connect_error();
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
	$rest_json = file_get_contents("php://input");
	$_POST = json_decode($rest_json, true);
	$response["received"] = $_POST;

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
	$cols = "cat, catid, type, comment, from, date, anon";
	$vals = "'whistle', $catid, 'comment', '$comment', '$from', '$date', '$anon'";

	$insert = "INSERT INTO activity($cols) VALUES($vals)";
	$result = mysqli_query($con, $insert);
	if ($result) { // Success
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
