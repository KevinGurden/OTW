<?php
/*
    Functions to calculate and write the day's health score
    Pass the days' results back in the response object as 'day'
 */
function weights() { // Provide survey contributions
    return array(
        "Commitment" =>     array(100,  10,   0,  10,   0,   0,  10,  10,  20,  20,   0), // Cm (was C1)
        "Communication" =>  array( 20, 100,   0,  10,  10,  25,  20,  20,  10,  10,  25), // Co (C2)
        "Care" =>           array( 10,  20, 100,  20,   0,  20,  20,  20,  10,  10,  25), // Ca (C3)
        "Workplace" =>      array( 10,  10,  10, 100,   0,  10,  20,  10,  10,  10,  20), // Wo (E1)
        "Vision" =>         array( 10,  20,  20,  10, 100,  25,  20,  10,   0,  20,  20), // Vi (V1)
        "Values" =>         array( 10,  20,  20,  10,  25, 100,   0,  20,   0,  20,  20), // Va (V2)
        "Recognistion" =>   array( 10,   0,  20,  20,  20,  20, 100,  40,  20,  20,  20), // Re (V3)
        "Risk" =>           array( 10,   0,  20,  10,   0,   0,  40, 100,  10,  10,  20), // Ri (V4)
        "Success" =>        array( 10,   0,   0,   0,   0,   0,   0,   0, 100,  10,  20), // Su (V5)
        "Vitality" =>       array( 20,  10,   0,   0,  10,   0,   0,   0,  20, 100,   0), // Vt (V6)
        "Vital Base" =>     array( 10,  20,  20,  20,  20,  20,  20,  20,  10,  20, 100)  // Vb (V7)
    );
};

function score_health($con, $cid, $day) { // Update the C1..E1 scores and then the overall health
    // Get the days' health row, calculate new values and write it back
    error_log("score_health...");
    $select = "SELECT * FROM health WHERE company_id=$cid AND day<='$day' AND datediff('$day',day)<=5 ORDER BY -day";
    error_log("select: $select");
    
    $select_result = mysqli_query($con, $select);
    if ($select_result === false) { // Will either be false or an array
        // select failed to run
        error_log("select failed");
    } else {
        error_log("select success");
        // Check for empty result
        if (mysqli_num_rows($select_result) > 0) {
            error_log("results > 0 success");
            // Loop through all results. The first should be $day (usually today)
            while ($score = mysqli_fetch_assoc($select_result)) {
                $scores[] = $score;
            };
            
            $s_day = $scores[0];
            if ($s_day['day'] == $day) { // We've got the correct day
                error_log("correct day");
                // Events
                $wh_open_3m = score_event($s_day['whistle_open_3m'], 0, 20);
                $wh_quick_3m = score_event($s_day['whistle_quick_3m'], 0, 20);
                $wh_open = score_event($s_day['whistle_open'], 5, 50);
                $wh_open_anon = score_event_div($s_day['whistle_anon'], $score['whistle_open'], 0, 1);
                $fl_open_3m = score_event($s_day['flag_open_3m'], 0, 20);
                $fl_quick_3m = score_event($s_day['flag_quick_3m'], 0, 20);
                $fl_open = score_event($s_day['flag_open'], 5, 100);
                $fl_open_anon = score_event_div($s_day['flag_anon'], $score['flag_open'], 0, 1);
                $gr_open_3m = score_event($s_day['grow_open_3m'], 0, 20);
                $gr_closed_met = score_event($s_day['grow_closed_met'], 15, 0);
                $gr_closed_not_met = score_event($s_day['grow_closed_not_met'], 0, 15);
                $su_anon_3m = score_event($s_day['survey_anon_3m'], 0, 10);
                $su_refuse_3m = score_event($s_day['survey_refuse_3m'], 0, 50);
                $su_none_5d = score_event($s_day['survey_5d'], 0, 1);

                // Contributions
                $cm_grow = score_contribution('cm', 'grow', array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));
                // $set_v3_whistle = score_contribution('v3', 'whistle', array());
                $ri_whistle = score_contribution('ri', 'whistle', array($wh_open_3m, $wh_open, $wh_quick_3m, $wh_open_anon));
                $ri_flag = score_contribution('ri', 'flag', array($fl_open_3m, $fl_open, $fl_quick_3m, $fl_open_anon));
                $su_grow = score_contribution('su', 'grow', array($gr_closed_met, $gr_closed_not_met));
                $vt_grow = score_contribution('vt', 'grow', array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));
                $vb_grow = score_contribution('vb', 'grow', array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));

                // Components
                $cm = score_component('cm', $s_day, array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));
                error_log("components: cm = ".$cm['value']);
                $co = score_component('co', $s_day, array());
                $ca = score_component('ca', $s_day, array());
                $wo = score_component('wo', $s_day, array());
                $vi = score_component('vi', $s_day, array());
                $va = score_component('va', $s_day, array());
                $re = score_component('re', $s_day, array());
                $ri = score_component('ri', $s_day, array($wh_open_3m, $wh_open, $wh_quick_3m, $wh_open_anon,
                                                          $fl_open_3m, $fl_open, $fl_quick_3m, $fl_open_anon));
                $su = score_component('su', $s_day, array($gr_closed_met, $gr_closed_not_met));
                $vt = score_component('vt', $s_day, array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));
                $vb = score_component('vb', $s_day, array($gr_open_3m, $gr_closed_met, $gr_closed_not_met));

                // Rolling Averages
                $cm_5 = score_rolling('cm', $cm, $scores);
                $co_5 = score_rolling('co', $co, $scores);
                $ca_5 = score_rolling('ca', $ca, $scores);
                $wo_5 = score_rolling('wo', $wo, $scores);
                $vi_5 = score_rolling('vi', $vi, $scores);
                $va_5 = score_rolling('va', $va, $scores);
                $re_5 = score_rolling('re', $re, $scores);
                $ri_5 = score_rolling('ri', $ri, $scores);
                $su_5 = score_rolling('su', $su, $scores);
                $vt_5 = score_rolling('vt', $vt, $scores);
                $vb_5 = score_rolling('vb', $vb, $scores);
                $health_5 = overall_health(array($cm_5, $co_5, $ca_5, $wo_5, $vi_5, $va_5, $re_5, $ri_5, $su_5, $vt_5, $vb_5));  

                // Health
                $health = overall_health(
                    array($cm['value'], $co['value'], $ca['value'], $wo['value'],
                        $vi['value'], $va['value'], $re['value'], $ri['value'], $su['value'], $vt['value'], $vb['value'])
                );  

                /* 
                INSERT INTO health  
                    SET day='$day',lookup=$cid:$day,company_id=$cid,
                        health=.., health_avg_recent=.., cm_score=.., cm_avg_recent=.., cm_grow_score=... etc
                ON DUPLICATE KEY 
                    UPDATE 
                        health=.., health_avg_recent=.., cm_score=.., cm_avg_recent=.., cm_grow_score=... etc
                */
                $lookup = $cid . ':' . $day;
                $cm_set = $cm['set'].','.$cm_5.','.$cm_grow['set'];
                $co_set = $co['set'].','.$co_5;
                $ca_set = $ca['set'].','.$ca_5;
                $wo_set = $wo['set'].','.$wo_5;
                $vi_set = $vi['set'].','.$vi_5;
                $va_set = $va['set'].','.$va_5;
                $re_set = $re['set'].','.$re_5;
                $ri_set = $ri['set'].','.$ri_5.','.$ri_flag['set'].','.$ri_whistle['set'];
                $su_set = $su['set'].','.$su_5.','.$su_grow['set'];
                $vt_set = $vt['set'].','.$vt_5.','.$vt_grow['set'];
                $vb_set = $vb['set'].','.$vb_5.','.$vb_grow['set'];
                $healths = "health=$health, health_avg_recent=$health_5";
                $sets = "$healths, $cm_set, $co_set, $ca_set, $wo_set, $vi_set, $va_set, $re_set, $ri_set, $su_set, $vt_set, $vb_set";
                
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
                error_log('fn_score_health: Nothing returned from health for ' . $cid . ' & ' . $day);
                return null;
            };
        } else {
            error_log('fn_score_health: Nothing returned from health for ' . $cid . ' & ' . $day . ' (5 days)');
            return null;
        };
    };
};

function overall_health($els) {
    $all_non_null = array_filter($els, "not_null");
    return round(array_sum($all_non_null) / count($all_non_null));
};

function score_rolling($comp, $day_score, $other_scores) {
    // Create an average from the last 5 days of scores
    $roll_total = $day_score['value']; $count = 1;
    error_log('score_rolling: '.$comp.': roll_total initially '.$roll_total);
    foreach ($other_scores as $other_score) {
        
        if (!is_null($other_score[$comp.'_score'])) {
            error_log('score_rolling: '.$comp.': '.$roll_total.' + '.$other_score[$comp.'_score'].' = '.$roll_total + $other_score[$comp.'_score']);
            $roll_total = $roll_total + $other_score[$comp.'_score'];
            $count = $count + 1;
            error_log('score_rolling: '.$comp.': count now '.$count);
        } else {
            error_log('score_rolling: '.$comp.'_score is null');
        };
    };

    $roll_score = round($roll_total/$count);
    error_log('score_rolling: '.$comp.': '.$roll_total.' / '.$count.' = '.$roll_score);
    return $comp."_avg_recent=".$roll_score;
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
            return 100 - $res; // It's a negative effect...
        } else {
            return $res; // Positive effect
        };
    };
};

function score_event_div($value1, $value2, $good, $bad) {
    // Calculate value1/value2 and return a percentage score between $good (100%) and bad (0%).
    if (!is_null($value2) && $value2>0) {
        if (!isset($value1)) {
            $value1=0;
        };
        return score_event($value1/$value2, $good, $bad);
    } else {
        return null;
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
            error_log("score: $score");
            $count = $count + 1;
            $total = $total + $score;
            // error_log('so... count: $count, total: $total');
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
        if (!is_null($event)) {
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
    error_log("component: set: ".$comp."_score=".$comp_score);
    return array('set' => $comp."_score=".$comp_score, 'value' => $comp_score);
};

function score_contribution($comp, $cont, $events) {
    // Create an average but remove nulls first
    $count = 0; $total = 0;
    foreach ($events as $event) {
        if (!is_null($event)) {
            $count = $count + 1;
            $total = $total + $event;
        };
    };
    
    if ($count > 0) { // Also got events
        $cont_score = round($total/$count);
    } else {
        $cont_score = 'NULL';
    };
    $cont_set = array('set' => $comp."_".$cont."_score=".$cont_score, 'value' => $cont_score);
    //error_log("contribution: $cont_set['set']");
    return $cont_set;
};


function send_day($con, $cid, $day) { // Send back the days results
    // error_log("score_health...");
    $select = "SELECT * FROM health WHERE company_id=$cid AND day='$day'";
    // error_log("select: $select");
    
    $select_result = mysqli_query($con, $select);
    if ($select_result === false) { // Will either be false or an array
        // select failed to run
        // error_log("select day failed");
    } else {
        // error_log("select day success");
        
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
};

?>