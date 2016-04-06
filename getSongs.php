<?php
/*
 * Get a list of songs
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if(isset($_GET['limit'])) {
    $lim = $_GET['limit'];
} else {
    $lim = "25";
};
    
// Array for JSON response
$response = array();

require_once __DIR__ . '/db_config.php';
// connecting to db
$con = mysqli_connect(DB_SERVER, DB_USER, DB_PASSWORD, DB_DATABASE);
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
};

// Get a list of songs
$result = mysqli_query($con,
    "SELECT songid,songtitle,artist FROM songs ORDER BY RAND() LIMIT $lim") or die(mysqli_error());

// check for empty result
if (mysqli_num_rows($result) > 0) {
    // looping through all results
    $response["songs"] = array();
    
    while ($row = mysqli_fetch_array($result)) {
        // temp user array
        $product = array();
        $product["songid"] = $row["songid"];
        $product["songtitle"] = $row["songtitle"];
        $product["artist"] = $row["artist"];

        // push single link into final response array
        array_push($response["songs"], $product);
    }
    // success
    $response["success"] = 1;

    // echoing JSON response
    echo json_encode($response);
} else {
    // no songs found
    $response["success"] = 0;
    $response["message"] = "No products found";

    // echo no users JSON
    echo json_encode($response);
}
?>
