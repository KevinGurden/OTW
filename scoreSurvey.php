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
include 'fn_scoring.php';

function update($con, $old_h, $new_h, $cid, $day, $elements) { // Insert a new record into 'health'
    // If new_h is [] then this is an event update only (probably a refusal to answer a survey)
    // error_log("update: ".count($old_h).",".count($new_h));
    /* 
    INSERT INTO health  
        SET day='$day', lookup=$cid:$day, company_id=$cid,
            c1_survey_count=(), c2_survey_score=(),
            etc
            survey_anon_3m=(SELECT COUNT(*) FROM answers WHERE company_id=$cid AND anon=1 AND DATEDIFF(CURDATE(),subdate)<90), 
            survey_refuse_3m=()
    ON DUPLICATE KEY 
        UPDATE 
            c1_survey_count=(), c2_survey_score=(),
            etc
            survey_anon_3m = (
                SELECT 
                    COUNT(*) FROM whistles
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'whistle' AND datediff(curdate(),subdate)<90
            ),
            whistle_refuse_3m = (
                SELECT 
                    COUNT(*) FROM whistles
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'cat' AND datediff(curdate(),subdate)<90
            );
    */
    // Which survey fields are affected?
    $sets = '';
    if (count($new_h)>0) { // We have some answers. Do answers and events
        foreach($elements as $el) {
            $el_count_label = $el.'_survey_count'; // e.g. c1_survey_count
            $el_old_count = $old_h[$el_count_label]; 
            if (isset($new_h[$el.'_count'])) {
                $el_new_count = $new_h[$el.'_count'];
            } else {
                $el_new_count = null;
            };
            if ($el_new_count > $el_old_count) { // One of the elements that were affected by an answer's weighting
                $el_new_score = $new_h[$el.'_score'];
                $el_score_label = $el.'_survey_score';
                $sets = $sets.$el_count_label.'='.$el_new_count.','.$el_score_label.'='.$el_new_score.', ';  // Add the new field=xyz
            };
        };
        $survey_scores = $sets;
    } else {
        $survey_scores = ""; // Only do events so blank this out
    };

    // Add any events
    $lookup = $cid . ':' . $day;
    $days_90 = "DATEDIFF(CURDATE(),subdate)<90";

    $survey_anon_3m = "survey_anon_3m=(SELECT COUNT(*) FROM answers WHERE company_id=$cid AND anon=1 AND $days_90)";
    $survey_refuse_3m = "survey_refuse_3m=(SELECT COUNT(*) FROM answers WHERE company_id=$cid AND refused=1 AND $days_90)";
    $survey_events = "$survey_anon_3m, $survey_refuse_3m";

    $on_dup = "ON DUPLICATE KEY UPDATE $survey_scores $survey_events";
    $insert = "INSERT INTO health SET day='$day', lookup='$lookup', company_id=$cid, $survey_scores $survey_events $on_dup";
    // error_log("insert2: $insert");
    $insert_result = mysqli_query($con, $insert);
    return $insert_result;
};

function weight($cat, $effect, $ans, $olddh, $el) {
    $value = $ans['value100'];      // Answer value (0-100)
    
    if ($ans['cat'] == $cat && $effect > 0) {      // Are we the correct category
        global $new_health;
        $effect100 = $effect/100;
        $new_value = $effect100 * $value;

        $score_label = $el.'_survey_score';    // Score field label in the 'health' table
        $count_label = $el.'_survey_count';    // Count field label in the 'health' table
        $el_count = $olddh[$count_label];  // Current count
        
        if ($el_count == 0) { // Ignore current score if this is a new record
            $new_health[$score_label] = $new_value;
            $new_health[$count_label] = $effect100;
        } else { // We have a non-zero count to calculate the combined average
            $el_score = $olddh[$score_label];  // Current score 
            // $new_health[$score_label] = (($el_score * $el_count) + $new_value) / $el_count + 1;
            $new_health[$score_label] = $el_score + ($new_value/$effect100);
            $new_health[$count_label] = $el_count + $effect100;
        };
    }; 
};

function weight_score($old_score, $old_count, $cat, $effect, $ans, $el) {
    if ($ans['cat'] == $cat && $effect > 0) {      // Are we the correct category
        $value = $ans['value100'];      // Answer value (0-100)
        // global $new_health;
        $effect100 = $effect/100;
        $new_value = $effect100 * $value;

        if ($old_count == 0) { // Ignore current score if this is a new record
            $new_score = $new_value;
        } else { // We have a non-zero count to calculate the combined average
            $new_score = $old_score + $new_value;
        };
        return $new_score;
    } else {
        return $old_score;
    };
};

function weight_count($old_count, $cat, $effect, $ans, $el) {    
    if ($ans['cat'] == $cat && $effect > 0) {      // Are we the correct category
        $effect100 = $effect/100;
        if (!isset($old_count) || $old_count == 0) { // Ignore current score if this is a new record
            $new_count = $effect100;
        } else { // We have a non-zero count to calculate the combined average
            $new_count = $old_count + $effect100;
        };
        return $new_count;
    } else {
        return $old_count;
    };
};

function weight_survey($ans, $old_health, $new_health, $els) {
    // Table of weighting effects:
    // e.g. 'c1_survey_score' has a 10% effect on C1 (Commitment) given a 'Vital Base' answer
    // weight('Vital Base', 10, $ans, $old_health, 'c1');      // C1: Commitment
    $weights = weights(); // Get the weighting from fn_scoring.php
    $categories = array_keys($weights);

    foreach($categories as $cat) {
        foreach($els as $i => $el) {
            $wt = $weights[$cat][$i];
            $new_health[$el."_score"] = weight_score($new_health[$el."_score"], $new_health[$el."_count"],  $cat, $wt, $ans, $el);
            $new_health[$el."_count"] = weight_count($new_health[$el."_count"], $cat, $wt, $ans, $el);
        };
    };

    // $cat = 'Commitment';
    // $new_health["c1_score"] = weight_score($new_health["c1_score"], $new_health["c1_count"],  $cat, 100, $ans, 'c1');   // C1: Commitment
    //     $new_health["c1_count"] = weight_count($new_health["c1_count"], $cat,                       100, $ans, 'c1');                                 
    return $new_health; 
};

error_log("----- scoreSurvey.php ---------------------------"); // Announce us in the log

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

    $select = "SELECT * FROM health WHERE day='$day' AND company_id=$company_id";
    $result = mysqli_query($con, $select);
    if ($result === false || mysqli_num_rows($result) == 0) { // No record so create one
        $tinsert = true;
        foreach($elements as $e) {
            $old_health[$e.'_survey_score'] = null;
            $old_health[$e.'_survey_count'] = 0;
        };
    } else { // We found at least 1 record
        $tinsert = false;
        $old_health = mysqli_fetch_assoc($result); // Just take the first
    };
    
    // Set the new health scores initially to those returned from the current day's health
    $new_health = array(
        "c1_score" => $old_health['c1_survey_score'], "c1_count" => $old_health['c1_survey_count'],
        "c2_score" => $old_health['c2_survey_score'], "c2_count" => $old_health['c2_survey_count'],
        "c3_score" => $old_health['c3_survey_score'], "c3_count" => $old_health['c3_survey_count'],
        "e1_score" => $old_health['e1_survey_score'], "e1_count" => $old_health['e1_survey_count'],
        "v1_score" => $old_health['v1_survey_score'], "v1_count" => $old_health['v1_survey_count'],
        "v2_score" => $old_health['v2_survey_score'], "v2_count" => $old_health['v2_survey_count'],
        "v3_score" => $old_health['v3_survey_score'], "v3_count" => $old_health['v3_survey_count'],
        "v4_score" => $old_health['v4_survey_score'], "v4_count" => $old_health['v4_survey_count'],
        "v5_score" => $old_health['v5_survey_score'], "v5_count" => $old_health['v5_survey_count'],
        "v6_score" => $old_health['v6_survey_score'], "v6_count" => $old_health['v6_survey_count'],
        "v7_score" => $old_health['v7_survey_score'], "v7_count" => $old_health['v7_survey_count'],
    );
    // Cycle through each question to build a running score/count for each element
    foreach ($answers as $answer) {
        // Weight the score according to the contributions in fn_scoring
        $new_health = weight_survey($answer, $old_health, $new_health, $elements); // Adjust for an individual answer
        // error_log("after  question ".$answer["id"]." new_health[v4] is ".$new_health["v4_score"].",".$new_health["v4_count"]);
    };

    return 

    $db_result = update($con, $old_health, $new_health, $company_id, $day, $elements);

    if ($db_result) { // Success... finally update the overall health scores. This does not use insert_counts
        $response["day"] = score_health($con, $company_id, $day);
        
        http_response_code(200);
        $response["message"] = "Success";
        $response["sqlerror"] = "";
    } else { // Failure
        $response["sqlerror"] = mysqli_error($con);
        error_log('scoreSurvey: Failed to update survey ('.$response['sqlerror'].')');
        http_response_code(403);
        $response["message"] = "Failed to create/update record";
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
    GIT commands
        'git status' then 'git add <file>.php' then 'git commit -m 'message'' then 'git push origin master'
 */
?>