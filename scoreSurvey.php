<?php
/*
Update the the survey health metrics in the health table for a particular day.
This should be executed after a survey has been submitted to the database.

Parameters:
    day: The day to apply the scores. Date stamp
    answers: An array of answers. Can be []
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
include 'fn_http_response.php';
include 'fn_escape.php';

function getHealth($con, $day, $cid) { // Get a day score
    $select = "SELECT * FROM health WHERE day='$day' AND company_id=$cid";
    $res = mysqli_query($con, $select);
    return $res;
};

function insert($con, $dh, $cid, $day, $elements) { // Insert a new record into 'health'
    // Which fields are affected?
    $cols = 'lookup, day, company_id'; $vals = "'$cid . ':' . $day', '$day', $cid";
    foreach($elements as $el) {
        $el_count_label = $el.'_survey_count'; // e.g. c1_count
        $el_count = $dh[$el_count_label];
        // error_log("INSERT: $el_count_label $el_count");
        if ($el_count > 0) { // One of the elements that were affected by an answer's weighting
            $el_score_label = $el.'_survey_score';
            $el_score = $dh[$el_score_label];
            $cols = $cols.', '.$el_count_label.', '.$el_score_label;  // Add the new column names
            $vals = $vals.', '.$el_count.', '.$el_score;              // Add the new column values
            // error_log("INSERT: cols: $cols");
        };
    };

    $insert_into = "INSERT INTO health($cols) VALUES($vals)"; // Issue the database insert
    error_log("insert: $insert_into");
    $insert_result = mysqli_query($con, $insert_into);
    // error_log("INSERT result: $insert_result");
    return $insert_result;
};

function update($con, $old_h, $new_h, $cid, $day, $elements) { // Insert a new record into 'health'
    // Which fields are affected?
    $sets = '';
    foreach($elements as $el) {
        $el_count_label = $el.'_survey_count'; // e.g. c1_count
        $el_old_count = $old_h[$el_count_label]; $el_new_count = $new_h[$el_count_label]; 
        if ($el_new_count > $el_old_count) { // One of the elements that were affected by an answer's weighting
            $el_score_label = $el.'_survey_score';
            $el_new_score = $new_h[$el_score_label];
            if ($sets == '') {
                $sets = 'SET '.$el_count_label.'='.$el_new_score;  // Add the first set=xyz's
            } else {
                $sets = $sets.', '.$el_count_label.'='.$el_new_score;  // Add the new set=xyz's
            };
        };
    };
    $update = "UPDATE health $sets WHERE day='$day' AND company_id='$cid'"; // Issue the database update
    error_log("update: $update");
    $update_result = mysqli_query($con, $update);
    return $update_result;
};

function weight($cat, $effect, $ans, $olddh, $el) {
    $value = $ans['value100'];      // Answer value (0-100)
    
    if ($ans['cat'] == $cat) {      // Are we the correct category
        // error_log("weight 1: $cat, $effect, $value, $el");

        global $new_health;
        $new_value = $effect/100 * $value;

        $score_label = $el.'_survey_score';    // Score field label in the 'health' table
        $count_label = $el.'_survey_count';    // Count field label in the 'health' table
        $el_count = $olddh[$count_label];  // Current count
        if ($el_count == 0) { // Ignore current score if this is a new record
            $new_health[$score_label] = $new_value;
            $new_health[$count_label] = 1;
            // error_log("weight 2: $new_value 1");
            // error_log(var_dump($day_health));
        } else { // We have a non-zero count to calculate the combined average
            $el_score = $olddh[$score_label];  // Current score 
            $new_health[$score_label] = (($el_score * $el_count) + $new_value) / $el_count + 1;
            $new_health[$count_label] = $el_count + 1;
            // $one = $day_health[$score_label]; $two = $day_health[$count_label];
            // error_log("weight 3: $one $two");
        };
        // error_log("weight 4: $value, $day_health[$score_label], $new_value");
    }; 
};

function weightSurvey($ans, $old_health) {
    // Table of weighting effects:
    // e.g. 'c1_survey_score' has a 10% effect on C1 (Commitment) given a 'Vital Base' answer
    $id = $ans['id'];
    // error_log("weightSurvey: $id");

    weight('Commitment', 100, $ans, $old_health, 'c1');     // C1: Commitment
    weight('Commitment', 10, $ans, $old_health, 'c2');      // C2: Communication
    weight('Commitment', 0, $ans, $old_health, 'c3');       // C3: Care
    weight('Commitment', 10, $ans, $old_health, 'e1');      // E1: Environment
    weight('Commitment', 0, $ans, $old_health, 'v1');       // V1: Vision
    weight('Commitment', 0, $ans, $old_health, 'v2');       // V2: Values
    weight('Commitment', 10, $ans, $old_health, 'v3');      // V3: Value
    weight('Commitment', 10, $ans, $old_health, 'v4');      // V4: Vulnerability
    weight('Commitment', 20, $ans, $old_health, 'v5');      // V5: Victory
    weight('Commitment', 20, $ans, $old_health, 'v6');      // V6: Vitality
    weight('Commitment', 0, $ans, $old_health, 'v7');       // V7: Vital Base

    weight('Communication', 20, $ans, $old_health, 'c1');   // C1: Commitment
    weight('Communication', 100, $ans, $old_health, 'c2');  // C2: Communication
    weight('Communication', 0, $ans, $old_health, 'c3');    // C3: Care
    weight('Communication', 10, $ans, $old_health, 'e1');   // E1: Environment
    weight('Communication', 10, $ans, $old_health, 'v1');   // V1: Vision
    weight('Communication', 25, $ans, $old_health, 'v2');   // V2: Values
    weight('Communication', 20, $ans, $old_health, 'v3');   // V3: Value
    weight('Communication', 20, $ans, $old_health, 'v4');   // V4: Vulnerability
    weight('Communication', 10, $ans, $old_health, 'v5');   // V5: Victory
    weight('Communication', 10, $ans, $old_health, 'v6');   // V6: Vitality
    weight('Communication', 25, $ans, $old_health, 'v7');   // V7: Vital Base

    weight('Care', 10, $ans, $old_health, 'c1');            // C1: Commitment
    weight('Care', 20, $ans, $old_health, 'c2');            // C2: Communication
    weight('Care', 100, $ans, $old_health, 'c3');           // C3: Care
    weight('Care', 20, $ans, $old_health, 'e1');            // E1: Environment
    weight('Care', 0, $ans, $old_health, 'v1');             // V1: Vision
    weight('Care', 20, $ans, $old_health, 'v2');            // V2: Values
    weight('Care', 20, $ans, $old_health, 'v3');            // V3: Value
    weight('Care', 20, $ans, $old_health, 'v4');            // V4: Vulnerability
    weight('Care', 10, $ans, $old_health, 'v5');            // V5: Victory
    weight('Care', 10, $ans, $old_health, 'v6');            // V6: Vitality
    weight('Care', 25, $ans, $old_health, 'v7');            // V7: Vital Base

    weight('Environment', 10, $ans, $old_health, 'c1');     // C1: Commitment
    weight('Environment', 10, $ans, $old_health, 'c2');     // C2: Communication
    weight('Environment', 10, $ans, $old_health, 'c3');     // C3: Care
    weight('Environment', 100, $ans, $old_health, 'e1');    // E1: Environment
    weight('Environment', 0, $ans, $old_health, 'v1');      // V1: Vision
    weight('Environment', 10, $ans, $old_health, 'v2');     // V2: Values
    weight('Environment', 20, $ans, $old_health, 'v3');     // V3: Value
    weight('Environment', 10, $ans, $old_health, 'v4');     // V4: Vulnerability
    weight('Environment', 10, $ans, $old_health, 'v5');     // V5: Victory
    weight('Environment', 10, $ans, $old_health, 'v6');     // V6: Vitality
    weight('Environment', 20, $ans, $old_health, 'v7');     // V7: Vital Base

    weight('Vision', 10, $ans, $old_health, 'c1');          // C1: Commitment
    weight('Vision', 20, $ans, $old_health, 'c2');          // C2: Communication
    weight('Vision', 20, $ans, $old_health, 'c3');          // C3: Care
    weight('Vision', 10, $ans, $old_health, 'e1');          // E1: Environment
    weight('Vision', 100, $ans, $old_health, 'v1');         // V1: Vision
    weight('Vision', 25, $ans, $old_health, 'v2');          // V2: Values
    weight('Vision', 20, $ans, $old_health, 'v3');          // V3: Value
    weight('Vision', 10, $ans, $old_health, 'v4');          // V4: Vulnerability
    weight('Vision', 0, $ans, $old_health, 'v5');           // V5: Victory
    weight('Vision', 20, $ans, $old_health, 'v6');          // V6: Vitality
    weight('Vision', 20, $ans, $old_health, 'v7');          // V7: Vital Base

    weight('Values', 10, $ans, $old_health, 'c1');          // C1: Commitment
    weight('Values', 20, $ans, $old_health, 'c2');          // C2: Communication
    weight('Values', 20, $ans, $old_health, 'c3');          // C3: Care
    weight('Values', 10, $ans, $old_health, 'e1');          // E1: Environment
    weight('Values', 25, $ans, $old_health, 'v1');          // V1: Vision
    weight('Values', 100, $ans, $old_health, 'v2');         // V2: Values
    weight('Values', 0, $ans, $old_health, 'v3');           // V3: Value
    weight('Values', 20, $ans, $old_health, 'v4');          // V4: Vulnerability
    weight('Values', 0, $ans, $old_health, 'v5');           // V5: Victory
    weight('Values', 20, $ans, $old_health, 'v6');          // V6: Vitality
    weight('Values', 20, $ans, $old_health, 'v7');          // V7: Vital Base

    weight('Value', 10, $ans, $old_health, 'c1');           // C1: Commitment
    weight('Value', 0, $ans, $old_health, 'c2');            // C2: Communication
    weight('Value', 20, $ans, $old_health, 'c3');           // C3: Care
    weight('Value', 20, $ans, $old_health, 'e1');           // E1: Environment
    weight('Value', 20, $ans, $old_health, 'v1');           // V1: Vision
    weight('Value', 20, $ans, $old_health, 'v2');           // V2: Values
    weight('Value', 100, $ans, $old_health, 'v3');          // V3: Value
    weight('Value', 40, $ans, $old_health, 'v4');           // V4: Vulnerability
    weight('Value', 20, $ans, $old_health, 'v5');           // V5: Victory
    weight('Value', 20, $ans, $old_health, 'v6');           // V6: Vitality
    weight('Value', 20, $ans, $old_health, 'v7');           // V7: Vital Base

    weight('Vulnerability', 10, $ans, $old_health, 'c1');   // C1: Commitment
    weight('Vulnerability', 0, $ans, $old_health, 'c2');    // C2: Communication
    weight('Vulnerability', 20, $ans, $old_health, 'c3');   // C3: Care
    weight('Vulnerability', 10, $ans, $old_health, 'e1');   // E1: Environment
    weight('Vulnerability', 0, $ans, $old_health, 'v1');    // V1: Vision
    weight('Vulnerability', 0, $ans, $old_health, 'v2');    // V2: Values
    weight('Vulnerability', 40, $ans, $old_health, 'v3');   // V3: Value
    weight('Vulnerability', 100, $ans, $old_health, 'v4');  // V4: Vulnerability
    weight('Vulnerability', 10, $ans, $old_health, 'v5');   // V5: Victory
    weight('Vulnerability', 10, $ans, $old_health, 'v6');   // V6: Vitality
    weight('Vulnerability', 20, $ans, $old_health, 'v7');   // V7: Vital Base

    weight('Victory', 10, $ans, $old_health, 'c1');         // C1: Commitment
    weight('Victory', 0, $ans, $old_health, 'c2');          // C2: Communication
    weight('Victory', 0, $ans, $old_health, 'c3');          // C3: Care
    weight('Victory', 0, $ans, $old_health, 'e1');          // E1: Environment
    weight('Victory', 0, $ans, $old_health, 'v1');          // V1: Vision
    weight('Victory', 0, $ans, $old_health, 'v2');          // V2: Values
    weight('Victory', 0, $ans, $old_health, 'v3');          // V3: Value
    weight('Victory', 0, $ans, $old_health, 'v4');          // V4: Vulnerability
    weight('Victory', 100, $ans, $old_health, 'v5');        // V5: Victory
    weight('Victory', 10, $ans, $old_health, 'v6');         // V6: Vitality
    weight('Victory', 20, $ans, $old_health, 'v7');         // V7: Vital Base

    weight('Vitality', 20, $ans, $old_health, 'c1');        // C1: Commitment
    weight('Vitality', 10, $ans, $old_health, 'c2');        // C2: Communication
    weight('Vitality', 0, $ans, $old_health, 'c3');         // C3: Care
    weight('Vitality', 0, $ans, $old_health, 'e1');         // E1: Environment
    weight('Vitality', 10, $ans, $old_health, 'v1');        // V1: Vision
    weight('Vitality', 0, $ans, $old_health, 'v2');         // V2: Values
    weight('Vitality', 0, $ans, $old_health, 'v3');         // V3: Value
    weight('Vitality', 0, $ans, $old_health, 'v4');         // V4: Vulnerability
    weight('Vitality', 20, $ans, $old_health, 'v5');        // V5: Victory
    weight('Vitality', 100, $ans, $old_health, 'v6');       // V6: Vitality
    weight('Vitality', 0, $ans, $old_health, 'v7');         // V7: Vital Base

    weight('Vital Base', 10, $ans, $old_health, 'c1');      // C1: Commitment
    weight('Vital Base', 20, $ans, $old_health, 'c2');      // C2: Communication
    weight('Vital Base', 20, $ans, $old_health, 'c3');      // C3: Care
    weight('Vital Base', 20, $ans, $old_health, 'e1');      // E1: Environment
    weight('Vital Base', 20, $ans, $old_health, 'v1');      // V1: Vision
    weight('Vital Base', 20, $ans, $old_health, 'v2');      // V2: Values
    weight('Vital Base', 20, $ans, $old_health, 'v3');      // V3: Value
    weight('Vital Base', 20, $ans, $old_health, 'v4');      // V4: Vulnerability
    weight('Vital Base', 10, $ans, $old_health, 'v5');      // V5: Victory
    weight('Vital Base', 20, $ans, $old_health, 'v6');      // V6: Vitality
    weight('Vital Base', 100, $ans, $old_health, 'v7');     // V7: Vital Base
};

$response = array(); // Array for JSON response
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8");
    $_POST = json_decode(file_get_contents('php://input'), true);

    // Escape the values to ensure no injection vunerability
    $answers = $_POST['answers'];
    $day = escape($con, 'day', '');
    $company_id = got_int('company_id', 0);
    
    $elements = array('c1','c2','c3','e1','v1','v2','v3','v4','v5','v6','v7');

    // $result = getHealth($con, $day, $company_id); // Get the current day score
    $select = "SELECT * FROM health WHERE day='$day' AND company_id=$company_id";
    $result = mysqli_query($con, $select);
    if ($result === false || mysqli_num_rows($result) == 0) { // No record so create one
        $tinsert = true;
        foreach($elements as $e) {
            $label = $e.'_survey_count';
            $old_health[$label] = 0;
        };
    } else { // We found at least 1 record
        $tinsert = false;
        $old_health = mysqli_fetch_assoc($result); // Just take the first
    };
    
    $new_health = array(); 
    foreach ($answers as $answer) {
        weightSurvey($answer, $old_health); // Adjust for an individual answer
    };
    
    if ($tinsert) {
        $db_result = insert($con, $new_health, $company_id, $day, $elements);
    } else {
        $db_result = update($con, $old_health, $new_health, $company_id, $day, $elements);
    };

    if ($db_result) {
        // Success
        http_response_code(200);
        $response["status"] = 200;
        $response["message"] = "Success";
        $response["sqlerror"] = "";
    } else {
        // Failure
        http_response_code(403);
        $response["status"] = 403;
        $response["message"] = "Failed to create/update record";
        $response["sqlerror"] = "";
    };
}; 

echo json_encode($response); // Echo JSON response

/* 
Useful stuff:
    SSH for mac:
        chmod 400 otwkey.pem to encrypt key. Do this in the otwkey directory
        ssh -i otwkey.pem ec2-user@52.38.155.255 to start ssh for the server
        cd ~/../../var/log/httpd to get to the log files on opsworks stack
        sudo cat getwhistlesphp-error.log | more to show the error log
 */
?>