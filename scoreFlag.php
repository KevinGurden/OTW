<?php
/*
Update the the flag health metrics in the health table for a particular day.
This should be executed after a flag has been submitted to the database.

Parameters:
    day: The day to apply the scores. Date stamp
    company_id: company that this applies to. Integer
    types: list of comma separated flag type names

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
include 'fn_scoring.php';

function insert($con, $cid, $day) { // Insert a new record into 'health' or update if it already exists
    /* 
    INSERT INTO health  
        SET day='$day',
            lookup=$cid:$day,
            company_id=$cid,
            flag_open = (
                as UPDATE below
            )
    ON DUPLICATE KEY 
        UPDATE 
            flag_open = (
                SELECT 
                    COUNT(*) FROM flags
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'flag'
            ),
            flag_open_3m = (
                SELECT 
                    COUNT(*) FROM flags
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'flag' AND datediff(curdate(),subdate)<90
            ),
            flag_quick_3m = (
                SELECT 
                    COUNT(*) FROM flags
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'quick' AND datediff(curdate(),subdate)<90
            ),
            flag_anon = (
                SELECT 
                    COUNT(*) FROM flags
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'flag' AND anon=1
            )
    */
    $lookup = $cid . ':' . $day;
    $days_90 = "DATEDIFF(CURDATE(),subdate)<90";
    $not_closed = "status!='closed'";
    $cat_flag = "cat='flag'"; $cat_quick = "cat='quick'";

    $flags_open = "flag_open = (SELECT COUNT(*) FROM flags WHERE company_id=$cid AND $not_closed AND $cat_flag)";
    $flags_open_3m = "flag_open_3m = (SELECT COUNT(*) FROM flags WHERE company_id=$cid AND $not_closed AND $cat_flag AND $days_90)";
    $flags_quick_3m = "flag_quick_3m = (SELECT COUNT(*) FROM flags WHERE company_id=$cid AND $not_closed AND $cat_quick AND $days_90)";
    $flags_open_anon = "flag_anon = (SELECT COUNT(*) FROM flags WHERE company_id=$cid AND $not_closed AND $cat_flag AND anon=1)";
    $flag_events = "$flags_open, $flags_open_3m, $flags_quick_3m, $flags_open_anon";
    
    $on_dup = "ON DUPLICATE KEY UPDATE $flag_events";
    $insert = "INSERT INTO health SET day='$day', lookup='$lookup', company_id=$cid, $flag_events $on_dup";
    $insert_result = mysqli_query($con, $insert);
    error_log($insert, $insert_result);
    return $insert_result;
};

function insert_counts($con, $cid, $day, $types) { // Update category type counts into 'health'
    /* 
    UPDATE health
        SET flag_open_1=(
            SELECT COUNT(*) FROM flags
                WHERE company_id = $cid AND status != 'closed' AND cat = 'flag' AND type_selected='$type'
        ),
        etc
        WHERE day='$day',
            lookup=$cid:$day,
            company_id=$cid
    */
    // Build up the set= statements
    $sets = '';
    $set_where = "WHERE company_id=".$cid." AND status != 'closed' AND cat = 'flag'";
    $select = "SELECT COUNT(*) FROM flags $set_where";
    foreach($types as $ix=>$type) {
        $type_count_label = 'flag_open_'.$ix; // e.g. flag_open_1
        $type_count = "($select AND type_selected='$type')";
        if ($sets == '') {
            $sets = 'SET '.$type_count_label.'='.$type_count;
        } else {
            $sets = $sets.','.$type_count_label.'='.$type_count;
        };
    };

    
    $update = "UPDATE health $sets WHERE day='$day' AND company_id=$cid";
    error_log('update: '.$update);
    $update_result = mysqli_query($con, $update);
    return $update_result;
};

error_log("----- scoreFlag.php ---------------------------"); // Announce us in the log

$response = array(); // Array for JSON response
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use

    // Escape the values to ensure no injection vunerability
    $day = escape($con, 'day', '');
    $company_id = got_int('company_id', 0);
    $types = escape($con, 'types', '');
    
    $db_result1 = insert($con, $company_id, $day); // Update any flag events first e.g. Open > 3 months
    if ($db_result1) { // Completed
        
        if ($types!='') { // Old apps didn't pass types so check first
            error_log('Flag: types: '.$types);
            $types_array = explode(',',$types);
            $db_result2 = insert_counts($con, $company_id, $day, $types_array); // Now add category counts e.g. Sexism

            if ($db_result2) { // Success
                http_response_code(200);
                // $response["status"] = 200;
                $response["message"] = "Success";
                $response["sqlerror"] = "";
                error_log('success');
            } else { // Partial failure
                http_response_code(304);
                $response["message"] = "Partially failed to create/update record";
                $response["sqlerror"] = mysqli_error($con);
                error_log('partial failure');
            };
        } else {
            http_response_code(200);
            $response["message"] = "Success although types not passed";
            $response["sqlerror"] = "";
        };
            
        // Finally update the overall health scores. This does not use insert_counts
        $response["day"] = score_health($con, $company_id, $day);
    } else {
        // Failure
        http_response_code(304);
        $response["message"] = "Failed to create/update record";
        $response["sqlerror"] = mysqli_error($con);
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
