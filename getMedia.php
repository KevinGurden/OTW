<?php
/*
Get a media file from the encol database.

Parameters:
    id: company identifier. Integer
    user: userid. String
    mediaId: Integer
    type: type of return media; 'thumbnail', 'full', 'stock'. String

Return:
    status: 200 for success, 300+ for error
    message: High level error message
    sqlerror: detailed sql error
    media: an array of media objects

See bottom for useful commands
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Origin: *');

include 'fn_connected.php';
include 'fn_http_response.php';
include 'fn_get_escape.php';

error_log("----- getMedia.php ---------------------------"); // Announce us in the log

$response = array(); // Array for JSON response

$con = mysqli_connect("otw.cvgjunrhiqdt.us-west-2.rds.amazonaws.com", "techkevin", "whistleotw", "encol");
if (connected($con, $response)) {
    // mysqli_set_charset($con, "utf8"); // Set the character set to use

    $id = escape($con, 'id', 0); // Escape to avoid injection vunerability
    $user = escape($con, 'user', '');
    $mediaId = escape($con, 'mediaId', '');
    $type = escape($con, 'type', 'full');
    error_log("getMedia: ".$type);

    // Get 1 record only
    $select = "SELECT * FROM media WHERE company_id=$id AND user='$user' AND id=$mediaId LIMIT 1";
    error_log("getMedia: ".$select);
    $result = mysqli_query($con, $select);
    $response["query"] = "$select";

    if ($result === false) { // Will either be false or an array
        // query failed to run
        http_response_code(400);
        $response["message"] = "Query failed";
        $response["sqlerror"] = mysqli_error($con);
    } else {
        $media = array();

        // Check for empty result
        if (mysqli_num_rows($result) > 0) {
            $media = mysqli_fetch_assoc($result); // Should only be 1 result
            
            if ($type=='full') { // Only convert file/file64 if we want the full transfer
                $b64 = base64_encode($media["file"]);
                $media["valid"] = !($b64 === false);
                if ($media["valid"]) { // Valid conversion
                    // $b64 = mysqli_real_escape_string($con, $b64);
                    $media["file64"] = $b64;
                } else {
                    $media["file64"] = null;
                };
                $media["thumbnail"] = base64_encode($media["thumbnail"]);
            } elseif ($type=='thumbnail') {
                $media["file"] = null;
                $media["thumbnail"] = base64_encode($media["thumbnail"]);
                $media["thumbnail"] = base64_encode($media["thumbnail"]);
            };

            $response["media"] = $media;

            http_response_code(200); // Success
            $response["message"] = "Success";
            $response["sqlerror"] = "";
        } else {
            http_response_code(200); // Success but null return
            $response["message"] = "No media found";
            $response["sqlerror"] = "";
        };
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
