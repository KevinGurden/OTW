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

function insert($con, $dh, $elements, $cid) { // Insert a new record into 'health'
    // Which fields are affected?
    $cols = ''; $vals = '';
    foreach($elements as $el) {
        $el_count_label = $el.'_count'; // e.g. c1_count
        $el_count = $dh[$el_count_label];
        if ($el_count > 0) { // One of the elements that were affected by an answer's weighting
            $el_score_label = $el.'_score';
            $el_score = $dh[$el_score_label];
            $cols = $cols . $el_count_label . $el_score_label;  // Add the new column names
            $vals = $vals . $el_count . $el_score;              // Add the new column values
        };
    };

    $insert = "INSERT INTO health($cols) VALUES($vals)"; // Issue the database insert
    error_log("INSERT: $insert");
    $result = mysqli_query($con, $insert);
    error_log("INSERT result: $result");
    return $result;
};

function weight($cat, $effect, $ans, $dh, $el) {
    $value = $ans['value100'];      // Answer value (0-100)
    
    if ($ans['cat'] == $cat) {      // Are we the correct category
        error_log("weight 1: $cat, $day, $cid");
    
        $new_value = $effect/100 * $value;

        $score_label = $el.'_score';    // Score field label in the 'health' table
        $count_label = $el.'_count';    // Count field label in the 'health' table
        $el_count = $dh[$count_label];  // Current count
        if ($el_count == 0) { // Ignore current score if this is a new record
            $day_health[$score_label] = $new_value;
            $day_health[$count_label] = 1;
        } else { // We have a non-zero count to calculate the combined average
            $el_score = $dh[$score_label];  // Current score 
            $day_health[$score_label] = (($el_score * $el_count) + $new_value) / $el_count + 1;
            $day_health[$count_label] = $el_count + 1;
        };
        error_log("weight 2: $value, $day_health[$score_label], $new_value");
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
    
    $elements = array('c1','c2','c3','e1','v1','v2','v3','v4','v5','v6','v7');

    $result = getHealth($con, $day, $company_id); // Get the current day score
    if (mysqli_num_rows($result) == 0) { // No record so create one
        $insert = true;
        foreach($elements as $e) {
            $label = $e.'_count';
            $day_health[$label] = 0;
        };
    } else { // We found at least 1 record
        $insert = false;
        $day_health = mysqli_fetch_assoc($result); // Just take the first
    };
        
    foreach ($answers as $answer) {
        weightSurvey($answer, $day_health); // Adjust for an individual answer
    };
    if ($insert) {
        $result = insert($con, $day_health, $company_id, $elements);
    } else {
        error_log("pretend update");
        // $result = update();
    };

    if ($result) {
        // Success
        $response["status"] = 200;
        $response["message"] = "Success";
        $response["sqlerror"] = "";
    } else {
        // Failure
        $response["status"] = 403;
        $response["message"] = "Failed to create/update record";
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
