<?php
/*
 * Rename a players name in the leader board
 */
header('Access-Control-Allow-Origin: *');

$newname = $_GET['name'];
$uuid = $_GET['uuid'];
    
// Array for JSON response
$response = array();

// Include db connect class
require_once __DIR__ . '/db_config.php';

// connecting to db
$con = mysqli_connect(DB_SERVER, DB_USER, DB_PASSWORD, DB_DATABASE);
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
};


// Get link from the links table
$newname = mysqli_real_escape_string($con, $newname);
$update = "UPDATE leaders SET name='$newname' WHERE uuid='$uuid'";
error_log("Rename ready. $update");
$result = mysqli_query($con, $update) or die(mysqli_error($con));
error_log("Rename finished($result). $update");
?>
