<?php
function connected($con, $response) {
    if (mysqli_connect_errno()) {
        error_log("Failed to connect to MySQL: " . mysqli_connect_error());
        $response["message"] = "Failed to connect to DB";
        $response["sqlerror"] = mysqli_connect_error();
        return false;
    } else {
        return true;
    };
};
?>