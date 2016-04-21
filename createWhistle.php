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
header('Access-Control-Allow-Methods: GET, POST, JSONP, OPTIONS');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Array for JSON response
$response = array();

// Connect to db
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

	// $rest_json = file_get_contents("php://input");
	// $_POST = json_decode($rest_json, true);
	// $response["received"] = $_POST;

	// Escape the values to ensure no injection vunerability
	$title = mysqli_real_escape_string($con, $_POST['title']);
	$description = mysqli_real_escape_string($con, $_POST['description']);
	$status = mysqli_real_escape_string($con, $_POST['status']);
	$cat = mysqli_real_escape_string($con, $_POST['cat']);
	$subdate = mysqli_real_escape_string($con, $_POST['subdate']);
	$date = mysqli_real_escape_string($con, $_POST['date']);
	$type_selected = mysqli_real_escape_string($con, $_POST['type_selected']);
	$type_policy = mysqli_real_escape_string($con, $_POST['type_policy']);
	if (isset($_POST['loc_main'])) {
		$loc_main = mysqli_real_escape_string($con, $_POST['loc_main']);
	} else {
		$loc_main = '';
	};if (isset($_POST['loc_detail'])) {
		$loc_detail = mysqli_real_escape_string($con, $_POST['loc_detail']);
	} else {
		$loc_detail = '';
	};
	if (isset($_POST['user'])) {
		$user = mysqli_real_escape_string($con, $_POST['user']);
	} else {
		$user = '';
	};
	if (isset($_POST['anon'])) {
		$anon = mysqli_real_escape_string($con, $_POST['anon']);
	} else {
		$anon = '0';
	};
	    
	// Issue the database create
	$cols = "title, description, status, cat, subdate, date, type_selected, type_policy, ";
	$vals = "'$title', '$description', '$status', '$cat', '$subdate', '$date', '$type_selected', '$type_policy', ";

	$cols = $cols . "loc_main, loc_detail, user, anon";
	$vals = $vals . "'$loc_main','$loc_detail', '$user', '$anon'";

	$insert = "INSERT INTO whistles($cols) VALUES($vals)";
	$result = mysqli_query($con, $insert);
	if ($result) { // Success
        $response["status"] = 200;
        $response["message"] = "Whistle created";
        $response["id"] = mysqli_insert_id($con); // Return the id of the record added
        $response["sqlerror"] = "";
	} else { // Failure
		error_log("$result: from $insert");
        $response["status"] = 402;
        $response["message"] = "Create whistle failed";
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
