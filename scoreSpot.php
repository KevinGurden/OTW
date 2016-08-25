<?php
/*
Update the the spot health metrics in the health table for a particular day.
This should be executed after a spot has been submitted to the database.

Security: Requires JWT "Bearer <token>" 

Parameters:
    day: The day to apply the scores. Date stamp
    company_id: company that this applies to. Integer
    types: list of comma separated spot type names
    events: An array of event objects
    debug: Turn on debug statements. Boolean

Return:
    status: 200 for success, 300+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_post_escape.php';
include 'fn_scoring.php';
include 'fn_jwt.php';
include 'fn_debug.php';

function insert($con, $cid, $day) { // Insert a new record into 'health' or update if it already exists
    /* 
    INSERT INTO health  
        SET day='$day',
            lookup=$cid:$day,
            company_id=$cid,
            spot_open = (
                as UPDATE below
            )
    ON DUPLICATE KEY 
        UPDATE 
            spot_open = ( // Count spots that were submitted before the day and are still open
                SELECT 
                    COUNT(*) FROM spots
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'spot' AND sub_date<=$day
            ),
            flag_open_1w = ( // Count spots that were submitted before the day and are have been open for 7 days
                SELECT 
                    COUNT(*) FROM spots
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'spot' AND sub_date<=$day AND datediff(curdate(),sub_date)<=7
            )
    */
    $select_spots = "SELECT COUNT(*) FROM spots";
    $lookup = $cid . ':' . $day;
    $comp = "company_id=$cid";
    $days_7 = "DATEDIFF(CURDATE(),subdate)<7";
    $not_closed = "status!='closed'";
    $before = "sub_date<='$day'";
    $cat_spot = "cat='spot'";

    $spots_open = "spot_open = ($select_spots WHERE $comp AND $not_closed AND $cat_spot AND $before)";
    $spots_open_1w = "spot_open_1w = ($select_spots WHERE $comp AND $not_closed AND $cat_spot AND $before AND $days_7)";
    $spot_events = "$spots_open, $spots_open_1w";
    
    $on_dup = "ON DUPLICATE KEY UPDATE $spot_events";
    $insert = "INSERT INTO health SET day='$day', lookup='$lookup', $comp, $spot_events $on_dup";
    $insert_result = mysqli_query($con, $insert);
    debug('insert: '.$insert);
    debug('result: '.$insert_result);
    return $insert_result;
};

function insert_counts($con, $cid, $day, $types) { // Update category type counts into 'health'
    /* 
    UPDATE health
        SET spot_open_1=( // Count cat 1 spots that were submitted before the day and are still open
            SELECT COUNT(*) FROM spots
                WHERE company_id = $cid AND status != 'closed' AND cat = 'spot' AND sub_date<=$day AND type_selected='$type'
        ),
        etc
        WHERE day='$day',
            lookup=$cid:$day,
            company_id=$cid
    */
    // Build up the set= statements
    $sets = '';
    $set_where = "WHERE company_id=".$cid." AND status != 'closed' AND cat = 'spot' AND sub_date<='$day'";
    $select = "SELECT COUNT(*) FROM spots $set_where";
    foreach($types as $ix=>$type) {
        $type_count_label = 'spot_open_'.$ix; // e.g. spot_open_1
        $type_count = "($select AND type_selected='$type')";
        if ($sets == '') {
            $sets = 'SET '.$type_count_label.'='.$type_count;
        } else {
            $sets = $sets.','.$type_count_label.'='.$type_count;
        };
    };

    
    $update = "UPDATE health $sets WHERE day='$day' AND company_id=$cid";
    debug('update: '.$update);
    $update_result = mysqli_query($con, $update);
    debug('update_result: '.$update_result);
    return $update_result;
};

$_POST = json_decode(file_get_contents('php://input'), true);
announce('createComment', $_POST); // Announce us in the log

$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK

    $con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
    if (connected($con, $response)) {
        mysqli_set_charset($con, "utf8"); // Set the character set to use

        // Escape the values to ensure no injection vunerability
        $_POST = json_decode(file_get_contents('php://input'), true);
        $day = escape($con, 'day', '');
        $company_id = got_int('company_id', 0);
        $types = $_POST['types'];
        $events = $_POST['events'];
        
        $db_result1 = insert($con, $company_id, $day); // Update any spot events first e.g. Open > 1 week
        if ($db_result1) { // Completed
            
            $db_result2 = insert_counts($con, $company_id, $day, $types); // Now add category counts e.g. Hygiene

            if ($db_result2) { // Success
                http_response_code(200);
                $response["message"] = "Success";
                $response["sqlerror"] = "";
            } else { // Partial failure
                http_response_code(304);
                $response["message"] = "Partially failed to create/update record";
                $response["sqlerror"] = mysqli_error($con);
                error_log('partial failure');
            };
                
            // Finally update the overall health scores. This does not use insert_counts
            $response["day"] = score_health($con, $company_id, $day, $events, "scoreSpot");
        } else {
            // Failure
            http_response_code(304);
            $response["message"] = "Failed to create/update record";
            $response["sqlerror"] = mysqli_error($con);
            error_log('Failed to create/update record');
        };
    };
} else {
    http_response_code($claims['status']); // Token Failure
    $response["message"] = $claims['message'];
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
