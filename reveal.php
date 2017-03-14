<?php
/*
Reveal an anonymous user by updating whistle/flag and setting activities.

Security: Requires JWT "Bearer <token>" 

Data passed:
    cat: The category; 'whistle' or 'flag': String
    id: The id of the target whistle/flag. Integer
    user: The username of the user. String
    nick: The nickname of the user. String
	company_id: The id of the company in the company table
    date: The date of the reveal
    debug: Turn on debug statements. Boolean

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
*/
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_post_escape.php';
include 'fn_jwt.php';
include 'fn_debug.php';

$_POST = json_decode(file_get_contents('php://input'), true);
announce(__FILE__, $_POST); // Announce us in the log
$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK

    // Connect to db
    $con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
    if (connected($con, $response)) {
        mysqli_set_charset($con, "utf8"); // Set the character set to use

        // Get the parameters
        $id = escape($con, 'id', -1);
        $cat = escape($con, 'cat', '');
        $company_id = got_int('company_id', -1); 
        $nick = escape($con, 'nick', '');
        debug('nick: '.$nick);  
        $user = escape($con, 'user', ''); 
        debug('user: '.$user);  
        $date = escape($con, 'date', '');
        
        // We have to have all fields except nickname
        if ($id >= 0 && $cat != '' && $company_id >= 0 && $user != '' && $date != '') { 
            // Update the whistle/flag by issuing a database update
            // UPDATE '$cat.S'   
            //     SET user='$user.', user_nick='$nick.', anon=0, revealed=1
            //     WHERE id=$id. AND company_id=$company_id.
            
            $sets = "SET user='$user', user_nick='$nick', anon=0, revealed=1";
            $update = "UPDATE ".$cat."s $sets WHERE id=$id AND company_id=$company_id";
            debug('update: '.$update);  
            $resultUpdate = mysqli_query($con, $update);

            if ($resultUpdate) { // Success
                // Adjust the activity lines before the reveal date (and after but with no revealed value)
                // UPDATE activity 
                //      SET anon=0, fromuser='$user', fromnick='$nick',
                //          IF(date < '2017-03-14 16:40:25', 1, 0)
                //      WHERE cat='$cat' AND catid=$id AND company_id = $company_id;
                //
                $sets = "SET anon=0, fromuser='$user', fromnick='$nick'";
                $rev = ", revealed=IF(date<'$date',1,0)";
                $where = "WHERE cat='$cat' AND catid=$id AND company_id=$company_id";
                $adjust = "UPDATE activity $sets $rev $where; ";
                debug('adjust: '.$adjust);

                // Issue the activity create
                $cols = "cat, catid, type, content, fromuser, fromnick, date, anon, revealed, company_id";
                $vals = "'$cat', $id, 'reveal', '', '$user', '$nick', '$date', 0, 0, $company_id";
                $insert = "INSERT INTO activity($cols) VALUES($vals)";
                debug('adjust/insert: '.$adjust.$insert);  
                debug('insert: '.$insert);  
                
                $resultAdjIns = mysqli_query($con, $adjust.$insert);

                if ($resultAdjIns) { // Success
                    http_response_code(200);
                    $response["message"] = "Reveal complete";
                    $response["sqlerror"] = "";
                } else { // Failure
                    debug(mysqli_error($con));
                    http_response_code(403);
                    $response["message"] = "Reveal insert failed";
                    $response["sqlerror"] = mysqli_error($con);
                };
            } else { // Failure
                debug(mysqli_error($con));
                http_response_code(402);
                $response["message"] = "Reveal update failed";
                $response["sqlerror"] = mysqli_error($con);
            };

        } else { // Failure
            debug("Need user/nick name, company id, category and id");
            http_response_code(401);
            $response["message"] = "Need user/nick name, company id, category and id";
            $response["sqlerror"] = "";
        };
    };
} else {
    http_response_code($claims['status']); // Token Failure
    $response["message"] = $claims['message'];
};

header('Content-Type: application/json');
echo json_encode($response);

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