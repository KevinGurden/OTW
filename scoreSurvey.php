<?php
/*
Update the the survey health metrics in the health table for a particular day.
This should be executed after a survey has been submitted to the database.

Parameters:
    day: The day to apply the scores. Date stamp
    answers: An array of answers
    company_id: company that this applies to. Integer

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    questions: an array of question objects

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_escape.php';

function getHealth($con, $day, $cid) { // Get a day score
    $select = "SELECT * FROM health WHERE when='$day' AND company_id=$cid";
    $res = mysqli_query($con, $select);
    return $res;
};

function weight($cat, $effect, $ans, $dh, $el) {
    if ($el == 'e1') {
        error_log("weight: $cat, $day, $cid");
    };
    $value = $ans['value100'];      // Answer value (0-100)
    $score_label = $el.'_score';    // Score field label in the 'health' table
    $count_label = $el.'_count';    // Count field label in the 'health' table
    $el_score = $dh[$score_label];  // Current score 
    $el_count = $dh[$count_label];  // Current count
    if ($ans['cat'] == $cat) {      // Are we the correct category
        $new_value = $effect/100 * $value;
        $day_health[$score_label] = (($el_score * $el_count) + $new_value) / $el_count + 1;
        $day_health[$count_label] = $el_count + 1;
        if ($el == 'e1') {
            error_log("$value, $day_health[$score_label], $new_value");
        };
    }; 
};

function weightSurvey($ans, $day_health) {
    // Table of weighting effects:
    // e.g. 'c1_score' has a 10% effect on C1 (Committment) given a 'Vital Base' answer
    $id = $ans['id'];
    error_log("weightSurvey: $id");

    weight('Vital Base', 10, $ans, $day_health, 'c1');      // C1: Committment
    weight('Vital Base', 20, $ans, $day_health, 'c2');      // C2: Communication
    weight('Vital Base', 20, $ans, $day_health, 'c3');      // C3: Care
    weight('Vital Base', 20, $ans, $day_health, 'e1');      // E1: Environment
    weight('Vital Base', 20, $ans, $day_health, 'v1');      // V1: Vision
    weight('Vital Base', 20, $ans, $day_health, 'v2');      // V2: Values
    weight('Vital Base', 20, $ans, $day_health, 'v3');      // V3: Value
    weight('Vital Base', 20, $ans, $day_health, 'v4');      // V4: Vulnerability
    weight('Vital Base', 10, $ans, $day_health, 'v5');      // V5: Victory
    weight('Vital Base', 20, $ans, $day_health, 'v6');      // V6: Vitality
    weight('Vital Base', 100, $ans, $day_health, 'v7');     // V7: Vital Base

    weight('Vision', 10, $ans, $day_health, 'c1');          // C1: Committment
    weight('Vision', 20, $ans, $day_health, 'c2');          // C2: Communication
    weight('Vision', 20, $ans, $day_health, 'c3');          // C3: Care
    weight('Vision', 10, $ans, $day_health, 'e1');          // E1: Environment
    weight('Vision', 100, $ans, $day_health, 'v1');         // V1: Vision
    weight('Vision', 25, $ans, $day_health, 'v2');          // V2: Values
    weight('Vision', 20, $ans, $day_health, 'v3');          // V3: Value
    weight('Vision', 10, $ans, $day_health, 'v4');          // V4: Vulnerability
    weight('Vision', 0, $ans, $day_health, 'v5');           // V5: Victory
    weight('Vision', 20, $ans, $day_health, 'v6');          // V6: Vitality
    weight('Vision', 20, $ans, $day_health, 'v7');          // V7: Vital Base
};

// Array for JSON response
$response = array();
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8");
    $_POST = json_decode(file_get_contents('php://input'), true);

    // Escape the values to ensure no injection vunerability
    $answers = $_POST['answers'];
    $day = escape($con, 'day', '');
    $company_id = got_int('company_id', 0);
    
    $result = getHealth($con, $day, $company_id); // Get the current day score
    if (mysqli_num_rows($result) == 0) { // No record so create one
        error_log("no record");
        $response["status"] = 400;
        $response["message"] = "No health for that day for company '$company_id'";
        $response["sqlerror"] = "";
    } else { // We found at least 1 record
        $day_health = mysqli_fetch_assoc($result); // Just take the first
        foreach ($answers as $answer) {
            weightSurvey($answer, $day_health); // Adjust for an individual answer
        };

        // Success
        $response["status"] = 200;
        $response["message"] = "Success";
        $response["sqlerror"] = "";
    };

    // Echoing JSON response
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
