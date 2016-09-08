<?php
function escape($con, $field, $default) {
    if (isset($_GET[$field])) {
        if ($con == false) {
            return $_GET[$field];
        } else {
    	   return mysqli_real_escape_string($con, $_GET[$field]);
        };
    } else {
    	return $default;
    };
};

function got_int($field, $default) {
    if (isset($_GET[$field])) {
        return $_GET[$field];
    } else {
        return $default;
    };
};

function got_array($field, $default) {
    if (isset($_GET[$field])) {
        return $_GET[$field];
    } else {
        return $default;
    };
};
?>
