<?php
function escape($con, $field, $default) {
    if (isset($_POST[$field])) {
    	return mysqli_real_escape_string($con, $_POST[$field]);
    } else {
    	return $default;
    };
};

function got_int($field, $default) {
    if (isset($_POST[$field])) {
        return $_POST[$field];
    } else {
        return $default;
    };
};
?>
