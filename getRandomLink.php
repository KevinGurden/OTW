<?php
/*
 * Return 1 random link
 */
header('Access-Control-Allow-Origin: *');

if (isset($_GET['v'])) {$version = $_GET['v'];} else {$version = 0;};
$diff = $_GET['diff'];
$diffhigh = $diff + 0.2;  // Set higher limit on difficulty
$difflow = $diff - 0.2;   // .. and for lower
error_log("v=$version");
    
// Array for JSON response
$response = array();

// Include db connect class
require_once __DIR__ . '/db_config.php';

// connecting to db
$con = mysqli_connect(DB_SERVER, DB_USER, DB_PASSWORD, DB_DATABASE);
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
};
    
// Get a random link from the links table
$select = "SELECT * FROM links WHERE anstype='T' AND difficulty BETWEEN $difflow AND $diffhigh ORDER BY RAND() LIMIT 1";
$result = mysqli_query($con, $select) or die(mysqli_error($con));

// check for empty result
if (mysqli_num_rows($result) > 0) {
    header('Content-Type: application/json');
    // looping through all results
    $response["links"] = array();
    
    while ($row = mysqli_fetch_array($result)) {
        // temp user array
        $product = array();
        $product["songtitleA"] = $row["songtitleA"];
        $product["artistA"] = $row["artistA"];
        $product["itemA"] = $row["itemA"];
        $product["songtitleB"] = $row["songtitleB"];
        $product["artistB"] = $row["artistB"];
        $product["itemB"] = $row["itemB"];
        $product["linktype"] = $row["linktype"];
        $product["extra"] = $row["extra"];

        // push single link into final response array
        array_push($response["links"], $product);
    }
    // success
    $response["success"] = 1;

    // echoing JSON response
    echo json_encode($response);
} else {
    header('Content-Type: application/json');
    $response["success"] = 0;
    $response["message"] = "No links found ($select)";
    header('Content-Type: text/html');
    error_log($response["message"]);

    // echo no users JSON
    echo json_encode($response);
}
?>
