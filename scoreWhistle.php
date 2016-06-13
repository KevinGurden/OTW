<?php
/*
Update the the whistle health metrics in the health table for a particular day.
This should be executed after a whistle has been submitted to the database.

Parameters:
    day: The day to apply the scores. Date stamp
    company_id: company that this applies to. Integer
    types: list of comma separated whistle type names

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
include 'fn_score_health.php';

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

function insert_counts($con, $cid, $day, $types) { // Update type counts into 'health'
    /* 
    UPDATE health
        SET whistle_open_1=(
            SELECT COUNT(*) FROM whistles
                WHERE company_id = $cid AND status != 'closed' AND cat = 'whistle' AND type_selected='$type'
        ),
        etc
        WHERE day='$day',
            lookup=$cid:$day,
            company_id=$cid
    */
    // Build up the set= statements
    $sets = '';
    $set_where = "WHERE company_id=".$cid." AND status != 'closed' AND cat = 'whistle'";
    $select = "SELECT COUNT(*) FROM whistles $set_where";
    error_log("types[0]: ".$types[0]);
    foreach($types as $ix=>$type) {
        $type_count_label = 'whistle_open_'.$ix; // e.g. whistle_open_1
        $type_count = "($select AND type_selected='$type')";
        if ($sets == '') {
            $sets = 'SET '.$type_count_label.'='.$type_count;
        } else {
            $sets = $sets.','.$type_count_label.'='.$type_count;
        };
    };

    
    $update = "UPDATE health $sets WHERE day='$day' AND company_id=$cid";
    error_log("update: $update");
    $update_result = mysqli_query($con, $update);
    return $update_result;
};

$response = array(); // Array for JSON response
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use

    // Escape the values to ensure no injection vunerability
    $day = escape($con, 'day', '');
    $company_id = got_int('company_id', 0);
    $types = escape($con, 'types', '');
    
    $db_result1 = insert($con, $company_id, $day);
    error_log('db_result1: ' . $db_result1);
    if ($db_result1) { // Completed
        
        if ($types!='') { // Old apps didn't pass types so check first
            $types_array = explode(',',$types);
            $db_result2 = insert_counts($con, $company_id, $day, $types_array);
            error_log('db_result2: ' . $db_result2);

            if ($db_result2) { // Success
                http_response_code(200);
                // $response["status"] = 200;
                $response["message"] = "Success";
                $response["sqlerror"] = "";
                error_log('success');
            } else { // Partial failure
                http_response_code(304);
                $response["message"] = "Partially failed to create/update record";
                $response["sqlerror"] = mysqli_connect_error();
                error_log('partial failure');
            };
        } else {
            http_response_code(200);
            $response["message"] = "Success although types not passed";
            $response["sqlerror"] = "";
            error_log('success');
        };
            
        // Finally update the overall health scores. This does not use insert_counts
        $response["day"] = score_health($con, $company_id, $day);
    } else {
        // Failure
        http_response_code(304);
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
