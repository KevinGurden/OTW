<?php
function send_email($to_email, $to_name) {
	/**
	 * Send via Google's Gmail servers.
	 */

	// SMTP needs accurate times, and the PHP time zone MUST be set
	// This should be done in your php.ini, but this is how to do it if you don't have access to that
	date_default_timezone_set('Etc/UTC');

	require 'PHPMailerAutoload.php';

	// Create a new PHPMailer instance
	$mail = new PHPMailer;

	// Tell PHPMailer to use SMTP
	// $mail->isSMTP();

	// Enable SMTP debugging
	// 0 = off (for production use)
	// 1 = client messages
	// 2 = client and server messages
	// $mail->SMTPDebug = 2;

	// Ask for HTML-friendly debug output
	// $mail->Debugoutput = 'html';

	// Set the hostname of the mail server
	// $mail->Host = 'smtp.gmail.com';
	// use
	// $mail->Host = gethostbyname('smtp.gmail.com');
	// if your network does not support SMTP over IPv6

	// Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
	// $mail->Port = 587;

	// Set the encryption system to use - ssl (deprecated) or tls
	// $mail->SMTPSecure = false; // Was tls

	// Whether to use SMTP authentication
	// $mail->SMTPAuth = false;

	// Username to use for SMTP authentication - use full email address for gmail
	// $mail->Username = "kevin.gurden@googlemail.com";

	// Password to use for SMTP authentication
	// $mail->Password = "logs1Dgo";

	// Set who the message is to be sent from
	$mail->setFrom('register@rxh.not.com', 'Kevin Gurden');

	// Set an alternative reply-to address
	$mail->addReplyTo('kevin.gurden@googlemail.com', 'Kevin Gurden');

	// Set who the message is to be sent to
	$mail->addAddress($to_email, $to_name);

	//Set the subject line
	$mail->Subject = 'Test GMail';

	// Read an HTML message body from an external file, convert referenced images to embedded,
	// convert HTML into a basic plain-text alternative body
	// $mail->msgHTML(file_get_contents('contents.html'), dirname(__FILE__));
	
	$mail->msgHTML('<p>Hello</p>');

	// Replace the plain text body with one created manually
	$mail->AltBody = 'Plain hello';

	// Attach an image file
	// $mail->addAttachment('images/phpmailer_mini.png');

	// Send the message, check for errors
	if (!$mail->send()) {
	    debug("Mailer Error: " . $mail->ErrorInfo);
	} else {
	    debug("Message sent!");
	};
};
?>