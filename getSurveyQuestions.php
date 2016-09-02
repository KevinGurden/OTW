<?php
/*
Get a list of survey questions from the encol database. 
Return any that have the right company_id or a null company_id.

Security: Requires JWT "Bearer <token>" 

Parameters:
    company_id: id of company within company table. Integer
    debug: Turn on debug statements. Boolean

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    questions: an array of question object

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';
include 'fn_jwt.php';
include 'fn_debug.php';

announce(__FILE__, $_GET); // Announce us in the log
$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK

    $con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
    if (connected($con, $response)) {
        mysqli_set_charset($con, "utf8"); // Set the character set to use
        
        $company_id = escape($con, 'company_id', -1); // Escape to avoid injection vunerability
        
        // Get a list of questions
        $select = "SELECT * FROM questions WHERE company_id=".$company_id." OR company_id IS null";
        debug('select: '.$select);
        $result = mysqli_query($con, $select);
        $response["query"] = "$select";

        // Check for empty result
        if (mysqli_num_rows($result) > 0) {
            // Loop through all results
            $questions = array();
            
            while ($question = mysqli_fetch_assoc($result)) {
                $questions[] = $question;
                $response["lastquestion"] = $question;
            }
            $response["questions"] = $questions;

            http_response_code(200); // Success
            $response["message"] = "Success";
            $response["sqlerror"] = "";
        } else {
            http_response_code(200); // Success but no questions found
            $response["message"] = "No questions found";
        };
    };
} else {
    http_response_code($claims['status']); // Token Failure
    $response["message"] = $claims['message'];
};
    
echo json_encode($response);

/* 
Useful stuff:
    SSH for mac:
        chmod 400 otwkey.pem to encrypt key. Do this in the otwkey directory
        ssh -i otwkey.pem ec2-user@52.38.155.255 to start ssh for the server
        cd ~/../../var/log/httpd to get to the log files on opsworks stack
        sudo cat getwhistlesphp-error.log | more to show the error log
 */

?>
