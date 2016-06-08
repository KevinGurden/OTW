<?php
/*
Update the the whistle health metrics in the health table for a particular day.
This should be executed after a whistle has been submitted to the database.

Parameters:
    day: The day to apply the scores. Date stamp
    company_id: company that this applies to. Integer

Return:
    status: 200 for success, 300+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';
// include 'scoreHealth.php';

function insert($con, $cid, $day) { // Insert a new record into 'health' or update if it already exists
    /* 
    INSERT INTO health  
        SET day='$day',
            lookup=$cid:$day,
            company_id=$cid,
            whistle_open = (
                SELECT 
                    COUNT(*) FROM whistles
                    WHERE company_id = $cid AND status != 'closed' AND cat = 'whistle'
            )
    ON DUPLICATE KEY 
        UPDATE whistle_open = (
            SELECT 
                COUNT(*) FROM whistles
                WHERE company_id = 1 AND status != 'closed' AND cat = 'whistle'
        );
    */
    $lookup = $cid . ':' . $day;
    $whistles_open = "SELECT COUNT(*) FROM whistles WHERE company_id=$cid AND status != 'closed' AND cat = 'whistle'";
    $on_dup = "ON DUPLICATE KEY UPDATE whistle_open = ($whistles_open)";
    $insert = "INSERT INTO health SET day='$day', lookup='$lookup', company_id=$cid, whistle_open = ($whistles_open) $on_dup";
    error_log("insert: $insert");
    $insert_result = mysqli_query($con, $insert);
    return $insert_result;
};

function score_event($value, $good, $bad) {
    // Return a percentage score between $good (100%) and bad (0%).
    if (is_null($value)) {
        return $value;
    } else {

        if ($good < $bad) {
            $low = $good; $high = $bad; // It's a negative effect...
        } else {
            $low = $bad; $high = $good;
        };

        if ($value < $low) {
            $value = $low;
        };
        if ($value > $high) {
            $value = $high;
        };
        $res = $value/($high - $low) * 100; 

        if ($good < $bad) {
            return 100 - res; // It's a negative effect...
        } else {
            return res; // Positive effect
        };
    };
};

function score_survey($comp, $score) {
    // Return the calculated survey score for component (C1, C2, etc) $comp
    $survey_score = $comp.'_survey_score';
    $survey_count = $comp.'_survey_count';
    if (is_null($score[$survey_score]) || is_null($score[$survey_count])) {
        return null;
    } else {
        return $score[$survey_score]/$score[$survey_count]*100;
    };
};

function score_total($scores) {
    // Create an average but remove nulls first
    $count = 0; $total = 0;
    foreach ($scores as $score) {
        if (!is_null($score)) {
            $count = $count + 1;
            $total = $total + $score;
        };
    };
    if ($count == 0) {
        return null;
    } else {
        return $total/$count;
    };
};

function score_health($con, $cid, $day) { // Update the C1..E1 scores and then the overall health
    // Get the days' health row, calculate new values and write it back
    error_log("score_health...");
    $select = "SELECT * FROM health WHERE company_id=$cid AND day='$day'";
    error_log("select: $select");
    
    $select_result = mysqli_query($con, $select);
    if ($select_result === false) { // Will either be false or an array
        // select failed to run
        error_log("select failed");
    } else {
        error_log("select success");
        // Check for empty result
        if (mysqli_num_rows($select_result) > 0) {
            // Just assume only 1 row for that day
            $score = mysqli_fetch_assoc($select_result);

            $v4_wh_open_3m = score_event($score['whistle_open_3m'], 0, 20);
            $v4_survey = score_survey('v4', $score);
            $v4_score = score_total(array($v4_survey, $v4_wh_open_3m)); // /2!

            $v5_gr_closed_met = score_event($score['grow_closed_met'], 15, 0);
            $v5_survey = score_survey('v5', $score);
            $v5_score = $v5_survey + $v5_wh_open_3m; // /2!

            $c1_survey = score_survey('c1', $score);
            error_log("c1_survey $c1_survey");
            $c1_score = $c1_survey;

            /* 
            INSERT INTO health  
                SET day='$day',lookup=$cid:$day,company_id=$cid,
                    c1_score=$c1_score,v4_score=$v4_score,v5_score=$v5_score
            ON DUPLICATE KEY 
                UPDATE 
                    c1_score=$c1_score,v4_score=$v4_score,v5_score=$v5_score
            */
            $lookup = $cid . ':' . $day;
            $c123 = "c1_score=$c1_score";
            $v1234567 = "v4_score=$v4_score,v5_score=$v5_score";
            $on_dup = "ON DUPLICATE KEY UPDATE $c123,$v1234567";
            $insert = "INSERT INTO health SET day='$day', lookup='$lookup', company_id=$cid, $c123, $v1234567 $on_dup";
            error_log("insert: $insert");
            $insert_result = mysqli_query($con, $insert);
        } else {
            error_log('scoreWhistles: Nothing returned from health for $cid $day');
        };
    };
};

$response = array(); // Array for JSON response
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use

    // Escape the values to ensure no injection vunerability
    $day = escape($con, 'day', '');
    $company_id = got_int('company_id', 0);
    
    $db_result = insert($con, $company_id, $day);
    error_log('db_result: ' . $db_result);

    if ($db_result) {
        // Success
        http_response_code(200);
        // $response["status"] = 200;
        $response["message"] = "Success";
        $response["sqlerror"] = "";
        error_log('success');

        // Finally update the overall health scores
        $health_result = score_health($con, $company_id, $day);
    } else {
        // Failure
        http_response_code(304);
        // $response["status"] = 304;
        $response["message"] = "Failed to create/update record";
        $response["sqlerror"] = mysqli_connect_error();
        error_log('failure');
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
