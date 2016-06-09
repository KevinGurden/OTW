<?php
/*
    Functions to calculate and write the day's health score
    Pass the days' results back in the response object as 'day'
 */

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
            return 100 - $res; // It's a negative effect...
        } else {
            return $res; // Positive effect
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
    error_log('arg0: ' . $scores[0]);
    error_log('arg0: ' . $scores[1]);
    $count = 0; $total = 0;
    foreach ($scores as $score) {
        if (is_null($score)) {
            error_log('null');
        } else {
            error_log($score);
            $count = $count + 1;
            $total = $total + $score;
            error_log('so... count: $count, total: $total');
        };
    };
    if ($count == 0) {
        error_log('count 0 so return null');
        return null;
    } else {
        error_log('count !0 so return ' . $total/$count);
        return $total/$count;
    };
};

function score_component($comp, $score, $events) {
    // $set_v5 = score_component('v5', $score, array($gr_closed_met, $grow_closed_not_met));
    // Create an average but remove nulls first
    error_log('score_component ev0: ' . $events[0]);
    $count = 0; $total = 0;
    foreach ($events as $event) {
        if (is_null($event)) {
            error_log('null');
        } else {
            error_log($event);
            $count = $count + 1;
            $total = $total + $event;
        };
    };

    $survey_count = $score[$comp.'_survey_count'];
    $survey_total = $score[$comp.'_survey_score'];

    if (is_null($survey_count) || $survey_count == 0 || is_null($survey_count) || $survey_count == 0) { 
        // No survey result so ignore this
    } else { // Got survey result}
        $survey_score = $survey_total/$survey_count;
        if ($count > 0) { // Also got events
            $event_score = $total/$count;
            $comp_score = round(($event_score + $survey_score)/2);
        } else {
            $comp_score = $survey_score;
        };
    };
    return $comp."_result=".$comp_score;
};

function send_day($con, $cid, $day) { // Send back the days results
    error_log("score_health...");
    $select = "SELECT * FROM health WHERE company_id=$cid AND day='$day'";
    error_log("select: $select");
    
    $select_result = mysqli_query($con, $select);
    if ($select_result === false) { // Will either be false or an array
        // select failed to run
        error_log("select day failed");
    } else {
        error_log("select day success");
        
        // Check for empty result
        if (mysqli_num_rows($select_result) > 0) {
            // Assume only one day
            return mysqli_fetch_assoc($select_result);
        } else {
            return null;
        };
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

            // Events
            $wh_open_3m = score_event($score['whistle_open_3m'], 0, 20);
            $gr_closed_met = score_event($score['grow_closed_met'], 15, 0);
            $gr_closed_not_met = score_event($score['grow_closed_not_met'], 0, 15);

            // Components
            $set_c1 = score_component('c1', $score, array());
            $set_v4 = score_component('v4', $score, array($wh_open_3m));
            $set_v5 = score_component('v5', $score, array($gr_closed_met, $grow_closed_not_met));
            
            // error_log("set_v4: $set_v4");
            // $v4_wh_open_3m = score_event($score['whistle_open_3m'], 0, 20);
            // $v4_survey = score_survey('v4', $score);
            // error_log('score_total v4...');
            // $v4_arr = array($v4_survey, $v4_wh_open_3m);
            // $v4_score = score_total($v4_arr);
            // if (is_null($v4_score)) {
            //     $v4_score = 0;
            // };

            // $v5_gr_closed_met = score_event($score['grow_closed_met'], 15, 0);
            // $v5_survey = score_survey('v5', $score);
            // $v5_score = $v5_survey + $v5_gr_closed_met; // /2!
            // if (is_null($v5_score)) {
            //     $v4_score = 0;
            // };

            // $c1_survey = score_survey('c1', $score);
            // error_log("c1_survey $c1_survey");
            // $c1_score = $c1_survey;
            // if (is_null($c1_score)) {
            //     $c1_score = 0;
            // };

            /* 
            INSERT INTO health  
                SET day='$day',lookup=$cid:$day,company_id=$cid,
                    c1_score=$c1_score,v4_score=$v4_score,v5_score=$v5_score
            ON DUPLICATE KEY 
                UPDATE 
                    c1_score=$c1_score,v4_score=$v4_score,v5_score=$v5_score
            */
            $lookup = $cid . ':' . $day;
            $c123 = $set_c1;
            $v1234567 = $set_v4.', '.$set_v5;
            $on_dup = "ON DUPLICATE KEY UPDATE $c123, $v1234567";
            $insert = "INSERT INTO health SET day='$day', lookup='$lookup', company_id=$cid, $c123, $v1234567 $on_dup";
            error_log("insert: $insert");
            $insert_result = mysqli_query($con, $insert);

            if ($insert_result) {
                $send_result = send_day($con, $cid, $day);
                return $send_result;
            } else {
                error_log('fn_score_health: insert day failed');
                return null;
            };
        } else {
            error_log('fn_score_health: Nothing returned from health for $cid $day');
            return null;
        };
    };
};
?>