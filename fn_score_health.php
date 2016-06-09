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
    // Create an average but remove nulls first
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
        if ($count > 0) { // But got events
            $comp_score = round($total/$count);
        } else {
            $comp_score = 'NULL'; // Got nothing!
        };
    } else { // Got survey result}
        $survey_score = $survey_total/$survey_count;
        if ($count > 0) { // Also got events
            $event_score = $total/$count;
            $comp_score = round(($event_score + $survey_score)/2);
        } else {
            $comp_score = $survey_score;
        };
    };

    return array('set' => $comp."_score=".$comp_score, 'value' => $comp_score);
};

function score_contribution($comp, $cont, $events) {
    // Create an average but remove nulls first
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
    
    if ($count > 0) { // Also got events
        $cont_score = round($total/$count);
    } else {
        $cont_score = 'NULL';
    };

    return array('set' => $comp."_".$cont."_score=".$cont_score, 'value' => $cont_score);
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

function not_null($var) {
    // Returns whether the input var is null
    return !is_null($var);
}

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
            $wh_open = score_event($score['whistle_open'], 200, 0);
            $gr_open_3m = score_event($score['grow_open_3m'], 0, 20);
            $gr_closed_met = score_event($score['grow_closed_met'], 15, 0);
            $gr_closed_not_met = score_event($score['grow_closed_not_met'], 0, 15);

            // Contributions
            $c1_grow = score_contribution('c1', 'grow', array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));
            // $set_v3_whistle = score_contribution('v3', 'whistle', array());
            $v4_whistle = score_contribution('v4', 'whistle', array($wh_open_3m, $wh_open));
            $v5_grow = score_contribution('v5', 'grow', array($gr_closed_met, $gr_closed_not_met));
            $v6_grow = score_contribution('v6', 'grow', array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));
            $v7_grow = score_contribution('v7', 'grow', array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));

            // Components
            $c1 = score_component('c1', $score, array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));
            $c2 = score_component('c2', $score, array());
            $c3 = score_component('c3', $score, array());
            $e1 = score_component('e1', $score, array());
            $v1 = score_component('v1', $score, array());
            $v2 = score_component('v2', $score, array());
            $v3 = score_component('v3', $score, array());
            $v4 = score_component('v4', $score, array($wh_open_3m, $wh_open));
            $v5 = score_component('v5', $score, array($gr_closed_met, $gr_closed_not_met));
            $v6 = score_component('v6', $score, array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));
            $v7 = score_component('v7', $score, array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));

            // Health
            $all = array($c1['value'], $c2['value'], $c3['value'], $e1['value'],
                        $v1['value'], $v2['value'], $v3['value'], $v4['value'], $v5['value'], $v6['value'], $v7['value']
                    );  
            $all_non_null = array_filter($all, "not_null");
            $health = round(array_sum($all_non_null) / count($all_non_null));

            /* 
            INSERT INTO health  
                SET day='$day',lookup=$cid:$day,company_id=$cid,
                    health=.., c1_score=.. etc   , c1_grow_score=... etc
            ON DUPLICATE KEY 
                UPDATE 
                    health=.., c1_score=.. etc   , c1_grow_score=... etc
            */
            $lookup = $cid . ':' . $day;
            $c123e1 = "$c1['set'], $c2['set'], $c3['set'], $e1['set']";
            $c123e1 = "$c123e1, $c1_grow['set']";
            $v1234567 = "$v1['set'], $v2['set'], $v3['set'], $v4['set'], $v5['set'], $v6['set'], $v7['set']";
            $v1234567 = "$v1234567, $v4_whistle['set'], $v5_grow['set'], $v6_grow['set'], $v7_grow['set']";
            $sets = "health=$health, $c123e1, $v1234567";
            $on_dup = "ON DUPLICATE KEY UPDATE $sets";
            $insert = "INSERT INTO health SET day='$day', lookup='$lookup', company_id=$cid, $sets $on_dup";
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