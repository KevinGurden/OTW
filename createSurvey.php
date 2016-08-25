<?php
/*
Create a new survey records in the encol database.

Security: Requires JWT "Bearer <token>" 

Data passed:
	answer:     Array of answers. Array
                If [] then assume this is a refusal to fill in a survey
    loc:        Coded location, e.g Work. String
    subdate:    Date of submission for these questions. yyyy-mm-dd
    user:       username. String
    anon:       Whether the answers were provided anonymously. Boolean (Integer0, 1)
    company_id: Company identifier in the 'company' table within the encol database

Return:
    status: 200 for success, 300+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
*/
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_post_escape.php';
include 'fn_jwt.php';
include 'fn_debug.php';

function create($con, $cat, $id, $value, $loc, $sdate, $user, $anon, $cid) {
    // Issue the database create
    $cols = "cat, id, value100, location, subdate, user, anon, company_id, refused";
    $vals = "'$cat', $id, '$value', '$loc', '$sdate', '$user', $anon, $cid, 0";

    $insert = "INSERT INTO answers($cols) VALUES($vals)";
    $result = mysqli_query($con, $insert);
    return $result;
};

function refuse($con, $sdate, $user, $anon, $cid) {
    // Issue the database create
    $cols = "subdate, user, anon, company_id, refused";
    $vals = "'$sdate', '$user', $anon, $cid, 1";

    $insert = "INSERT INTO answers($cols) VALUES($vals)";
    $result = mysqli_query($con, $insert);
    return $result;
};

$_POST = json_decode(file_get_contents('php://input'), true);
announce('createSurvey', $_POST); // Announce us in the log

$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK

    $con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
    if (connected($con, $response)) {
        mysqli_set_charset($con, "utf8");

    	// Escape the values to ensure no injection vunerability
    	$answers = $_POST['answers'];
        $loc = escape($con, 'location', '');
    	$subdate = escape($con, 'subdate', '');
    	$user = escape($con, 'user', '');
    	$anon = got_int('anon', 0);
    	$company_id = escape($con, 'company_id', 0); // Default to 0-Unknown

        if (count($answers) == 0) {
            $res = refuse($con, $subdate, $user, $anon, $company_id); // User refused to answer
            $total_created = 1;
            if (!$res) { // Error
                error_log('error is '. mysqli_error($con));
                $response["sqlerror"] = mysqli_error($con);
            };
        } else {
            // Normal response
            $total_created = 0;
            foreach ($answers as $answer) {
                $created = create($con, $answer['cat'], $answer['id'], $answer['value100'], $loc, $subdate, $user, $anon, $company_id, 0);
                if ($created) {
                    $total_created = $total_created + 1;
                } else {
                    $response["sqlerror"] = mysqli_error($con);
                };
            };
            $res = $total_created == count($answers);
        };

        if ($res) { // Did we successfully create all records?
            http_response_code(200);
            $response["message"] = "$total_created answer(s) created";
            $response["sqlerror"] = "";
        } else { // Failure
            http_response_code(304);
            $response["message"] = "One or more creates failed";
            $response["sqlerror"] = mysqli_error($con);
        };
    };
} else {
    http_response_code($claims['status']); // Token Failure
    $response["message"] = $claims['message'];
};

header('Content-Type: application/json');
echo json_encode($response);

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
