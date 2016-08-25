<?php

function debug($text) {
    global $debug_on, $debug_name;

    if ($debug_on) {
        error_log($debug_name.": ".$text);
    };
};

function error($text) {
    global $debug_name;

    error_log($debug_name.": ".$text);
};

function announce($name, $params) {
    global $debug_on, $debug_name;

    $debug_on = (isset($params['debug']) && $params['debug']==true);

    $debug_name = $name;
    error_log("----- ".$debug_name.".php --------------- php:".phpversion()); // Announce us in the log
};

?>