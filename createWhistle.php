<?php
/*
Create a new whistle in the encol database.

Data passed:
	tba

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
*/
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

// Array for JSON response
$response = array();

// require_once __DIR__ . '/db_config.php';
// Connect to db
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
    $response["status"] = 401;
    $response["message"] = "Failed to connect to DB";
    $response["sqlerror"] = mysqli_connect_error();
    echo json_encode($response);
} else {
	var_dump($_POST);
	// Escape the values to ensure no injection
	$response["post"] = $_POST;
	$title = mysqli_real_escape_string($con, $_POST['title']);
	$id = mysqli_real_escape_string($con, $_POST['id']);
	$description = mysqli_real_escape_string($con, $_POST['description']);
	$status = mysqli_real_escape_string($con, $_POST['status']);
	$cat = mysqli_real_escape_string($con, $_POST['cat']);
	$subdate = mysqli_real_escape_string($con, $_POST['subdate']);
	$date = mysqli_real_escape_string($con, $_POST['date']);
	$type_selected = mysqli_real_escape_string($con, $_POST['type_selected']);
	$type_policy = mysqli_real_escape_string($con, $_POST['type_policy']);
	$location_main = mysqli_real_escape_string($con, $_POST['location_main']);
	$location_detail = mysqli_real_escape_string($con, $_POST['location_detail']);
	$user = mysqli_real_escape_string($con, $_POST['user']);
	$anon = mysqli_real_escape_string($con, $_POST['anon']);
	    
	// Issue the database create
	$cols = "title, id, description, status, cat, subdate, date, type_selected, type_policy, ";
	$vals = "'$title', '$id', '$description', '$status', '$cat', '$subdate', '$date', '$type_selected', '$type_policy'";

	$cols = $cols . "location_main, location_detail, user, anon";
	$vals = $vals . "'$location_main','$location_detail', '$user', '$anon'";

	$insert = "INSERT INTO whistles($cols) VALUES($vals)";
	$result = mysqli_query($con, $insert);
	if ($result) { // Success
        $response["status"] = 200;
        $response["message"] = "Whistle created";
        $response["sqlerror"] = "";
	} else { // Failure
		error_log("$result: from $insert");
        $response["status"] = 402;
        $response["message"] = "Create whistle failed";
        $response["sqlerror"] = "";
    };
};

/* 
Useful stuff:
    SSH for mac:
        chmod 400 otwkey.pem to encrypt key. Do this in the otwkey directory
        ssh -i otwkey.pem ec2-user@52.38.155.255 to start ssh for the server
        cd ~/../../var/log/httpd to get to the log files on opsworks stack
        cat getwhistlesphp-error.log | more to show the error log
 */
?>
