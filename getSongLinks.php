<?php
/*
 * Retrun all links from a particular song 
 * 1.2 Limit the returned records if LIMIT is passed
 */
header('Access-Control-Allow-Origin: *');
$songid = isset( $_GET['songid'] )? $_GET['songid']: false; 
$artist = isset( $_GET['artist'] )? $_GET['artist']: false; 
$difflow = isset( $_GET['difflow'] )? $_GET['difflow']: false; 
$diffhigh = isset( $_GET['diffhigh'] )? $_GET['diffhigh']: false; 
$avoidLTs = isset( $_GET['avoidLTs'] )? $_GET['avoidLTs']: false; 
$avoidSongs = isset( $_GET['avoidSongs'] )? $_GET['avoidSongs']: false;
if (isset($_GET['purch'])) {
    $purchased = $_GET['purch'];
} else {
    $purchased = "";
};
// 1.2 Limit the returned records if this is set
if (isset($_GET['limit'])) {
    $limit = $_GET['limit']; $limit = "LIMIT $limit"; $order = "ORDER BY RAND()";
} else {
    $limit = ""; $order = "";
};

    
header('Content-Type: text/html');
//error_log("getSongLinks: songid=$songid, artist=$artist, difflow=$difflow, diffhigh=$diffhigh, avoidLTs=$avoidLTs, avoidSongs=$avoidSongs, purchased=$purchased, limit=$limit");

// Array for JSON response
$response = array();

require_once __DIR__ . '/db_config.php';
// connecting to db
$con = mysqli_connect(DB_SERVER, DB_USER, DB_PASSWORD, DB_DATABASE);
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
};

//error_log("getSongLinks query");

// Get link from the links table
if ($avoidLTs!="") {$avoidLTs = "AND LOCATE(linktype, '$avoidLTs')=0";};
if ($avoidSongs!="") {$avoidSongs = "AND LOCATE(songidB, '$avoidSongs')=0";};
if ($purchased=="") {
    $types = "AND purchA='G' AND purchB='G'";
} else {
    $types = "AND (purchA='' OR purchB='' OR (purchA IN($purchased) AND purchB IN($purchased)))";
};
$artist = mysqli_real_escape_string($con, $artist);  // Get rid of any single quotes first
$select = "SELECT * FROM links WHERE songidA=$songid AND artistA='$artist' AND (difficulty BETWEEN $difflow AND $diffhigh) $avoidLTs $avoidSongs $types $order $limit";
error_log("getSongLinks done query: $select");
$result = mysqli_query($con, $select) or die(mysqli_error($con));
    
// check for empty result
if (mysqli_num_rows($result) > 0) {
    header('Content-Type: application/json');
    // looping through all results
    $response["links"] = array();
    
    while ($row = mysqli_fetch_array($result)) {
        // temp user array
        $product = array();
        $product["songidA"] = $row["songidA"];
        $product["songtitleA"] = $row["songtitleA"]; $product["artistA"] = $row["artistA"];
        $product["itemA"] = $row["itemA"]; $product["arttypeA"] = $row["arttypeA"];
        $product["songidB"] = $row["songidB"];
        $product["songtitleB"] = $row["songtitleB"]; $product["artistB"] = $row["artistB"];
        $product["itemB"] = $row["itemB"]; $product["arttypeB"] = $row["arttypeB"];
        $product["linktype"] = $row["linktype"]; $product["extra"] = $row["extra"];
        $product["difficulty"] = $row["difficulty"]; $product["anstype"] = $row["anstype"];
        $product["need"] = $row["need"]; $product["incorrect"] = $row["incorrect"];

        // push single link into final response array
        array_push($response["links"], $product);
    }
    // success
    $response["success"] = 1;

    // echoing JSON response
    echo json_encode($response);
    // error_log("getSongLinks sent");
} else {
    header('Content-Type: application/json');
    $response["success"] = 0;
    $response["message"] = "No links found ($select)";
    header('Content-Type: text/html');
    error_log($response["message"]);

    // echo no links
    echo json_encode($response);
}
?>
