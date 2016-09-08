<?php
/*
Send an email.

Security: Requires JWT "Bearer <token>" 

Parameters:
	toEmail: User's email address. String
	toName: User's name. String
	subject: Email's subject heading. String
	html: Email HTML content: String
	text: Email text content: String
	fromEmail: From email address: String
	fromName: From email name: String
	company_id: Integer
	debug: Turn on debug statements. Boolean

Return:
    status: 200 for success, 400+ for error
    message: High level error message
    sqlerror: detailed sql error

See bottom for useful commands
*/
header('Access-Control-Allow-Methods: POST OPTIONS');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_post_escape.php';
include 'fn_jwt.php';
include 'fn_email.php';
include 'fn_debug.php';

$_POST = json_decode(file_get_contents('php://input'), true);
announce(__FILE__, $_POST);

$response = array();

$claims = token();
if ($claims['result'] == true) { // Token was OK

	// Get the parameters. No SQL is done so no need to escape the values
	$to_email = escape(false, 'toEmail', ''); 
	$to_name = escape(false, 'toName', ''); 
	$subject = escape(false, 'subject', ''); 
	$body_html = escape(false, 'html', ''); 
	$body_text = escape(false, 'text', ''); 
	$from_email = escape(false, 'fromEmail', ''); 
	$from_name = escape(false, 'fromName', ''); 
	debug('from_name: '.$from_name);
	debug('from_email: '.$from_email);
	$company_id = escape(false, 'company_id', 0); // Default to 0-Unknown
		    
	$sent = send_email($to_email, $to_name, $subject, $body_html, $body_text, $from_email, $from_name);
	if ($sent != false) {
        http_response_code(200);
        $response["message"] = "Email sent";
        $response["sqlerror"] = "";
	} else { // Failure
        http_response_code(412);
  		debug('not sent '.$sent['ErrorInfo']);
        $response["message"] = "Email failed to send: ".$sent['ErrorInfo'];
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
