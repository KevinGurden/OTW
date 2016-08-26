<?php
/*
Login and get any general info specific to the user's company from the encol database. Return a JWT token for use to access other endpoints

Parameters:
    username: the username e.g. 'test1'. String
    password: the identifier within the category. String
    force: (test only) force the return of a specific company information. Integer
    events: if set will return events information as well. Integer boolean; 0 or 1

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error
    init: a json object of company specific information
    token: a JWT token
    debug: Turn on debug statements. Boolean

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_post_escape.php';
include 'fn_jwt.php';
include 'fn_debug.php';

function collect_events($query) { // Mop up the eents query into an array
    if (mysqli_num_rows($query) > 0) {
        $events = array();
        
        while ($event = mysqli_fetch_assoc($query)) { // Loop through all results
            $events[] = $event;
        };
        return $events; 
    } else {
        return array();
    };
};

function checkbrute($given_username, $c_id, $con) {
    // Get timestamp of current time 
    $now = time();
 
    // All login attempts are counted from the past 2 hours. 
    $valid_attempts = $now - (2 * 60 * 60);

    $select = "SELECT time FROM logins WHERE given_username=? AND company_id=? AND time > '$valid_attempts'";
    debug('select: '.$select);
    if ($stmt = mysqli_prepare($con, $select)) { 
        mysqli_stmt_bind_param($stmt, 'si', $given_username, $c_id);
        mysqli_stmt_execute($stmt); // Execute the prepared query
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 5) { // More than 5 failed logins?
            return true;
        } else {
            return false;
        };
    }
};

function pw_verify($given, $stored) { // Replace with password_verify() when PHP 5.5 is available
    return $given == $stored;
};

function pw_in_date($expireSQL) { // Is the temporary use-once password in date
    $expirePHP = strtotime($expireSQL);
    //debug('now: '.now().', expires: '.$expirePHP);
    debug('expires: '.$expirePHP);
    return 0 <= $expirePHP;
};

function login($given_username, $given_password, $con) {
    $login_result = array();

    // Prepare statement to avoid SQL injection
    $query = "SELECT * FROM users WHERE username='$given_username' LIMIT 1";
    debug("query: SELECT * FROM users WHERE username='".$given_username."' LIMIT 1");
    
    // if ($stmt = mysqli_prepare($con, $query)) { 
    // mysqli_stmt_bind_param($stmt, 's', $given_username);
    //     mysqli_stmt_execute($stmt); // Execute the prepared query 
    //     if (mysqli_stmt_affected_rows($stmt)>0) {
    //          $user_row = mysqli_fetch_assoc($stmt);

    $result = mysqli_query($con, $query);
    if ($result != false) { 
        if (mysqli_num_rows($result) > 0) {
            debug('got rows');
            $user_row = mysqli_fetch_assoc($result);
            
            $c_id = $user_row['company_id'];
            $password = $user_row['password'];
            $once = $user_row['one_time_use'] == 1;
            $expire = $user_row['one_time_expire'];

            if (false && checkbrute($given_username, $c_id, $con) == true) { // Check if the account is locked from too many login attempts 
                // Account is locked. Send an email to user saying their account is locked
                debug('brute is true');
                $login_result['result'] = false;
                return $login_result;
            } else {
                // Check if the password in the database matches the password the user gave
                // We are using the password_verify function to avoid timing attacks.
                debug('verifying password');
                if (pw_verify($given_password, $password)) {
                    debug('correct');
                    $login_result['result'] = true; // Password is correct!
                    $login_result['username'] = $given_username;
                    if (($once && pw_in_date($expire)) || !$once {
                        $claims = array('iss'=>'encol');
                        $time = time();
                        $login_result['jwt'] = generate_token($claims, $time, $once, 'HS256', 'secret');
                        $login_result['company_id'] = $c_id;
                        return $login_result;
                    } else {
                        $login_result = array('result' => false, 'message' => 'Temporary password has expired');
                        return $login_result;
                    };
                } else {
                    debug('not correct');
                    // Password is not correct so record this attempt in the database
                    $now = time();
                    $insert = "INSERT INTO logins(username, company_id, time) VALUES('$given_username', $c_id, '$now')";
                    $init_result = mysqli_query($con, $insert);
                    debug('insert: '.$insert);

                    $login_result = array('result' => false, 'message' => 'Incorrect password');
                    return $login_result;
                };
            }
        } else {
            debug('no row found for '.$given_username);
            $login_result = array('result' => false, 'message' => 'Username not recognised');
            return $login_result; // No row in users.
        }
    } else {
        debug('sql failed');
        $login_result = array('result' => false, 'message' => 'Login failed');
        return $login_result; // SQL failed
    };
};

$_POST = json_decode(file_get_contents('php://input'), true);
announce('login', $_POST); // Announce us in the log

$response = array();

// Connect to db
$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    mysqli_set_charset($con, "utf8"); // Set the character set to use
    date_default_timezone_set('Europe/London');
    
    // Escape the values to ensure no injection vunerability
    $username = escape($con, 'username', '');
    $password = escape($con, 'password', '');
    $events_needed = got_int('events', 0) == 1;

    $login_result = login($username, $password, $con);
    if ($login_result['result'] == true) {
        $response["jwt"] = $login_result["jwt"];
        $c_id = $login_result["company_id"];

        $query = "SELECT * FROM company JOIN licences ON company_id=".$c_id." WHERE id=$c_id";
        if ($events_needed) {
            $query = $query."; SELECT * FROM events";
        };
        debug('query: '.$query);

        $init_result = mysqli_multi_query($con, $query);

        // Check for bad or empty result
        if ($init_result == false) { // no init found
            http_response_code(204);
            $response["query"] = $query;
            $response["message"] = "No initialisation match for company $c_id";
        } else { // Init success
            $init_store = mysqli_store_result($con);
            $init = mysqli_fetch_assoc($init_store);
            
            if ($events_needed) {
                
                $events_result = mysqli_next_result($con);
                if ($events_result == false) { // no events found
                    http_response_code(204);
                    $response["query"] = $query;
                    $response["message"] = "No initialisation match for company $company";
                    $response["init"] = $init;
                } else { // Init & events success
                    $events_store = mysqli_store_result($con);
                    $events = collect_events($events_store); 

                    http_response_code(200);
                    $response["message"] = "Success";
                    $response["init"] = $init;
                    $response["events"] = $events;
                    $response["sqlerror"] = "";
                };
            } else {
                http_response_code(200);
                $response["message"] = "Success";
                $response["init"] = $init;
                $response["sqlerror"] = "";
            };
        };
    } else {
        debug("Login failed");
        http_response_code(401);
        $response["message"] = "Login failed";
        $response["sqlerror"] = "";
    };
};
echo json_encode($response);

/* 
Useful stuff:
    SSH for mac:
        chmod 400 otwkey.pem to encrypt key. Do this in the otwkey directory
        ssh -i otwkey.pem ec2-user@52.38.155.255 to start ssh for the server
        cd ~/../../var/log/httpd to get to the log files on opsworks stack
        sudo cat getwhistlesphp-error.log | more to show the error log
 */
?>
