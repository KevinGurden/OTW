<?php
/*
Create a new survey records in the encol database.

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

function create($con, $cat, $id, $value, $loc, $sdate, $user, $anon, $cid) {
    // Issue the database create
    $cols = "cat, id, value, location, subdate, user, anon, company_id";
    $vals = "'$cat', $id, '$value', '$loc', '$sdate', '$user', $anon, '$cid'";

    $insert = "INSERT INTO answers($cols) VALUES($vals)";
    error_log("INSERT: $insert");
    $result = mysqli_query($con, $insert);
    error_log("INSERT result: $result");
    return $result;
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
    mysqli_set_charset($con, "utf8");
	$_POST = json_decode(file_get_contents('php://input'), true);

	// Escape the values to ensure no injection vunerability
	$answers = $_POST['answers'];
    error_log("answers: $answers");
    $loc = escape($con, 'location', '');
	$subdate = escape($con, 'subdate', '');
	$user = escape($con, 'user', '');
	$anon = escape($con, 'anon', 0);
    error_log("anon: $anon");
	$company_id = escape($con, 'company_id', 0); // Default to 0-Unknown

    $total_created = 0;
    foreach ($answers as $answer) {
        error_log("answer: $answer");
        $created = create($con, $answer['cat'], $answer['id'], $answer['value'], $loc, $subdate, $user, $anon, $company_id);
        if ($created) {
            $total_created +$total_created + 1;
        } else {
            $response["sqlerror"] = mysqli_error($con);
        };
    };

    if ($total_created == count($answers)) { // Did we successfully create all records?
        $response["status"] = 200;
        $response["message"] = "$total_created answers created";
        $response["sqlerror"] = "";
    } else { // Failure
        $response["status"] = 402;
        $response["message"] = "One or more creates failed";
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
