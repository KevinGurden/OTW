<?php
/*
 * Update a leader's scores
 */
header('Access-Control-Allow-Origin: *');

$uuid = $_GET['uuid'];
$score7 = $_GET['score7']; $date7 = $_GET['date7'];
    
// Include db connect class
require_once __DIR__ . '/db_config.php';

// connecting to db
$con = mysqli_connect(DB_SERVER, DB_USER, DB_PASSWORD, DB_DATABASE);
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
};
    
// Escape the values to ensure no injection
$uuid = mysqli_real_escape_string($con, $uuid);
$score7 = mysqli_real_escape_string($con, $score7); $date7 = mysqli_real_escape_string($con, $date7);
if(isset($_GET['scoreall']) && isset($_GET['dateall'])) {
    $scoreall = $_GET['scoreall']; $dateall = $_GET['dateall'];
    $scoreall = mysqli_real_escape_string($con, $scoreall); $dateall = mysqli_real_escape_string($con, $dateall);
    $plus = ",scoreall=$scoreall,dateall='$dateall'";
} else {
    $plus = "";
};
if(isset($_GET['gamesplayed']) && isset($_GET['level7']) && isset($_GET['levelall'])) {
    $gamesplayed = $_GET['gamesplayed']; $level7 = $_GET['level7']; $levelall = $_GET['levelall'];
    $gamesplayed = mysqli_real_escape_string($con, $gamesplayed);
    $level7 = mysqli_real_escape_string($con, $level7); $levelall = mysqli_real_escape_string($con, $levelall);
    $played = ",gamesplayed=$gamesplayed,level7='$level7',levelall='$levelall'";
} else {
    $played = "";
};
    
// Update a leader entry into the table
$scores = "score7=$score7,date7='$date7'$plus$played";
$insert = "UPDATE leaders SET $scores WHERE uuid='$uuid'";
error_log("$insert");
$result = mysqli_query($con, $insert) or die(mysqli_error($con));
?>