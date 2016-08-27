<?php
/*
Update the the whistle health metrics in the health table for a particular day.
This should be executed after a whistle has been submitted to the database.
Catch metrics for V2 of the scoring

Security: Requires JWT "Bearer <token>" 

Parameters:
    day: The day to apply the scores. Date stamp
    company_id: company that this applies to. Integer
    types: list of comma separated whistle type names
    events: An array of event objects
    scores: An array of v2 scores objects
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

function insert($con, $cid, $day, $scores) { // Insert a new record into 'health' or update if it already exists
    // Get current health and build V2 element scores
    $select = "SELECT * FROM health WHERE day='$day' AND company_id=.$cid.";
    $result = mysqli_query($con, $select);
    if (mysqli_num_rows($result) == 0) { // Day doesn't exist so create zero record
        $current = array(
            'v2_ca_score'=>null, 'v2_cm_score'=>null, 'v2_co_score'=>null, 'v2_ra_score'=>null, 'v2_re_score'=>null, 'v2_ri_score'=>null, 
            'v2_su_score'=>null, 'v2_va_score'=>null, 'v2_vb_score'=>null, 'v2_vt_score'=>null, 'v2_wo_score'=>null
        );
    } else {
        $current = mysqli_fetch_assoc($result);
    };
    $els = array('ca', 'cm', 'co', 're', 'ri', 'su', 'va', 'vb', 'vi', 'vt', 'wo');
    $v2s = array();
    foreach ($els as $el) {
        $sc = "v2_".$el."_score"; 
        if (is_null($current[$sc])) {
            $v2s[] = $sc."=".$scores[$sc];
        } else {
            $tot = $current[$sc] + $scores[$sc];
            $v2s[] = $sc."=".$tot;
        };
    };
    $v2sets = implode(",", $v2s);

    /* 
    INSERT INTO health  
        SET day='$day',
            lookup=$cid:$day,
            company_id=$cid,
            v2_ca_score = etc
            whistle_open = (
                as UPDATE below
            )
    ON DUPLICATE KEY 
        UPDATE 
            v2_ca_score = etc,
            whistle_open = ( // Count whistles that were submitted before the day and are have been open for 90 days
                SELECT 
                    COUNT(*) FROM whistles
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'whistle' AND subdate<='$day'
            ),
            whistle_open_3m = ( // Count whistles that were submitted before the day and are have been open for 90 days
                SELECT 
                    COUNT(*) FROM whistles
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'whistle' AND subdate<='$day' AND datediff(curdate(),subdate)<90
            ),
            whistle_quick_3m = ( // Count quick whistles that were submitted before the day and are have been open for 90 days
                SELECT 
                    COUNT(*) FROM whistles
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'quick' AND subdate<='$day' AND datediff(curdate(),subdate)<90
            ),
            whistle_anon = ( // Count whistles that were submitted before the day and are raised anononously
                SELECT 
                    COUNT(*) FROM whistles
                    WHERE company_id = 1 AND status != 'closed' AND cat = 'whistle' AND subdate<='$day' AND anon=1
            )
    */
    $lookup = $cid . ':' . $day;
    $comp = "company_id=$cid";
    $days_90 = "DATEDIFF(CURDATE(),subdate)<90";
    $not_closed = "status!='closed'";
    $before = "subdate<='$day'";
    $cat_whistle = "cat='whistle'"; $cat_quick = "cat='quick'";

    $whistles_open = "whistle_open = (SELECT COUNT(*) FROM whistles WHERE $comp AND $not_closed AND $cat_whistle AND $before)";
    $whistles_open_3m = "whistle_open_3m = (SELECT COUNT(*) FROM whistles WHERE $comp AND $not_closed AND $cat_whistle AND $before AND $days_90)";
    $whistles_quick_3m = "whistle_quick_3m = (SELECT COUNT(*) FROM whistles WHERE $comp AND $not_closed AND $cat_quick AND $before AND $days_90)";
    $whistles_open_anon = "whistle_anon = (SELECT COUNT(*) FROM whistles WHERE $comp AND $not_closed AND $cat_whistle AND $before AND anon=1)";
    $whistle_events = "$whistles_open, $whistles_open_3m, $whistles_quick_3m, $whistles_open_anon";
    
    $on_dup = "ON DUPLICATE KEY UPDATE $v2sets, $whistle_events";
    $insert = "INSERT INTO health SET day='$day', lookup='$lookup', $comp, $v2sets, $whistle_events $on_dup";
    $insert_result = mysqli_query($con, $insert);
    debug($insert, $insert_result);
    return $insert_result;
};

function insert_counts($con, $cid, $day, $types) { // Update category type counts into 'health'
    /* 
    UPDATE health
        SET whistle_open_1=( // Count cat 1 whistles that were submitted before the day and are still open
            SELECT COUNT(*) FROM whistles
                WHERE company_id = $cid AND status != 'closed' AND cat = 'whistle' AND subdate<='$day' AND type_selected='$type' 
        ),
        etc
        WHERE day='$day',
            lookup=$cid:$day,
            company_id=$cid
    */
    // Build up the set= statements
    $sets = '';
    $set_where = "WHERE company_id=".$cid." AND status!='closed' AND cat='whistle' AND subdate<='$day'";
    $select = "SELECT COUNT(*) FROM whistles $set_where";
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
    $update_result = mysqli_query($con, $update);
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
        $scores = $_POST['scores'];
        $response['events'] = $events;
        $response['types'] = $types;
        
        $db_result1 = insert($con, $company_id, $day, $scores); // Update any whistle events first e.g. Open > 3 months
        if ($db_result1) { // Completed
            
            $db_result2 = insert_counts($con, $company_id, $day, $types); // Now add category counts e.g. Bribery

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
            $response["day"] = score_health($con, $company_id, $day, $events, "scoreWhistle");
        
        } else { // Failure
            http_response_code(304);
            $response["message"] = "Failed to create/update record";
            $response["sqlerror"] = mysqli_error($con);
            error_log('failure');
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
