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

	// Set who the message is to be sent from
	$mail->setFrom('register@rxh.not', 'Kevin Gurden');

	// Set an alternative reply-to address
	$mail->addReplyTo('register@rxh.not', 'Kevin Gurden');

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