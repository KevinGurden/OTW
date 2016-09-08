<?php
function send_email($email, $name, $subject, $body_html, $body_text, $from_email, $from_name) {
	// Send a new user note

	date_default_timezone_set('Etc/UTC');

	require 'PHPMailerAutoload.php';

	// Create a new PHPMailer instance
	$mail = new PHPMailer;

	// Set who the message is to be sent from
	$mail->setFrom($from_email, $from_name);

	// Set an alternative reply-to address
	// $mail->addReplyTo('register@rxh.not', 'Kevin Gurden');

	// Set who the message is to be sent to
	$mail->addAddress($email, $name);

	//Set the subject line
	$mail->Subject = $subject;

	// Read an HTML message body from an external file, convert referenced images to embedded,
	// convert HTML into a basic plain-text alternative body
	// $mail->msgHTML(file_get_contents('contents.html'), dirname(__FILE__));
	
	$mail->msgHTML($body_html);

	// Replace the plain text body with one created manually
	$mail->AltBody = $body_text;

	// Attach an image file
	// $mail->addAttachment('images/phpmailer_mini.png');

	// Send the message, check for errors
	if (!$mail->send()) {
	    debug("Mailer Error: " . $mail->ErrorInfo);
	    return mail->ErrorInfo;
	} else {
	    debug("Message sent!");
	    return true;
	};
};
?>