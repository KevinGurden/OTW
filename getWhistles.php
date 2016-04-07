<?php
/*
 * Get a list of songs
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Array for JSON response
$response = array();

require_once __DIR__ . '/db_config.php';
// Connect to db
$con = mysqli_connect(DB_SERVER, DB_USER, DB_PASSWORD, DB_DATABASE);
if (mysqli_connect_errno()) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
};

// Get a list of songs
$result = mysqli_query(
    $con,
    "SELECT * FROM whistles"
) or die(mysqli_error());

// Check for empty result
if (mysqli_num_rows($result) > 0) {
    // Loop through all results
    $response["whistles"] = array();
    
    while ($row = mysqli_fetch_array($result)) {
        $whistle = array();
        $whistle["id"] = $row["id"];
        $whistle["title"] = $row["title"];

        // Push single link into final response array
        array_push($response["songs"], $whistle);
    }
    // Success
    $response["success"] = 1;

    // Echoing JSON response
    echo json_encode($response);
} else {
    // no songs found
    $response["success"] = 0;
    $response["message"] = "No whistles found";

    // echo no users JSON
    echo json_encode($response);
}
?>
