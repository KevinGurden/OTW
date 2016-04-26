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

function escape($con, $field, $default) {
    if (isset($_POST[$field])) {
    	return mysqli_real_escape_string($con, $_POST[$field]);
    } else {
    	return $default;
    };
}

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

	// Escape the values to ensure no injection vunerability
	$title = escape('title', '');
	$description = escape($con, 'description', '');
	$status = escape($con, 'status', '');
	$cat = escape($con, 'cat','');
	$subdate = escape($con, 'subdate', '');
	$date = escape($con, 'date', '');
	$type_selected = escape($con, 'type_selected', '');
	$type_policy = escape($con, 'type_policy', '');
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
	if (isset($_POST['company_id'])) {
		$company_id = mysqli_real_escape_string($con, $_POST['company_id']);
	} else {
		$company_id = 0; // Default to 0-Unknown
	};
	    
	// Issue the database create
	$cols = "title, description, status, cat, subdate, date, type_selected, type_policy, ";
	$vals = "'$title', '$description', '$status', '$cat', '$subdate', '$date', '$type_selected', '$type_policy', ";

	$cols = $cols . "loc_main, loc_detail, user, anon, company_id";
	$vals = $vals . "'$loc_main','$loc_detail', '$user', '$anon', $company_id";

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
