<?php

function generate_token($claims, $time, $once, $algorithm) {
    $algorithms = array('HS256'=>'sha256','HS384'=>'sha384','HS512'=>'sha512');
    $secret = 'ABeKGnSScKWol';
    $header = array();
    $header['typ'] = 'JWT';
    $header['alg'] = $algorithm;
    $token = array();
    $token[0] = rtrim(strtr(base64_encode(json_encode((object)$header)),'+/','-_'),'=');
    
    $claims['iat'] = $time;
    if ($once) {
        $claims['ex1'] = true; // Expire after 1st use
        $claims['exp'] = $time + 60 * 60 * 4; // 4 hours for password reset
    } else {
        $claims['iat'] = $time;
        $claims['exp'] = $time + 60 * 60 * 24 * 7; // 7 days for normal login token
    };
    $token[1] = rtrim(strtr(base64_encode(json_encode((object)$claims)),'+/','-_'),'=');
    
    if (!isset($algorithms[$algorithm])) return false;
    $hmac = $algorithms[$algorithm];

    $signature = hash_hmac($hmac, "$token[0].$token[1]", $secret, true);
    $token[2] = rtrim(strtr(base64_encode($signature),'+/','-_'),'=');
    return implode('.', $token);
};

function token_valid($token) {
    $time = time();
    $leeway = 60*60;
    $algorithm = "HS256";
    $secret = 'ABeKGnSScKWol';

    $algorithms = array('HS256'=>'sha256','HS384'=>'sha384','HS512'=>'sha512');
    if (!isset($algorithms[$algorithm])) {
        debug('algarithm not found');
        return 1;
    };
    $hmac = $algorithms[$algorithm];
    
    $token = explode('.',$token);
    if (count($token)<3) {
        debug('token not in 3 parts');
        return 2;
    };
    
    $header = json_decode(base64_decode(strtr($token[0],'-_','+/')),true);
    if (!$secret) {
        debug('missing secret');
        return 3;
    };
    if ($header['typ']!='JWT') {
        debug('typ not JWT');
        return 4;
    };
    if ($header['alg']!=$algorithm) {
        debug('alg not ok');
        return 5;
    };
    
    $signature = bin2hex(base64_decode(strtr($token[2],'-_','+/')));
    if ($signature!=hash_hmac($hmac,"$token[0].$token[1]",$secret)) {
        debug('secret different');
        return 6;
    };
    
    $claims = json_decode(base64_decode(strtr($token[1],'-_','+/')),true);
    if (!$claims) return false;
    if (isset($claims['nbf']) && $time+$leeway<$claims['nbf']) {
        debug('nbf bad');
        return 7;
    };
    if (isset($claims['iat']) && $time+$leeway<$claims['iat']) {
        debug('iat bad');
        return 8;
    };
    if (isset($claims['exp']) && $time-$leeway>$claims['exp']) {
        debug('exp bad');
        return 9;
    };

    return $claims;
};

function token() {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        if (strlen($auth) >= 8 && substr($auth, 0, 6) == 'Bearer') { // It's long enough to have a Bearer JWT token
            $token = substr($auth, 7);
            $claims = token_valid($token);
            
            if (is_int($claims)) {
                if ($claims == 9) {
                    debug('Token has expired');
                    return array('result'=>false, 'status'=>401, 'message'=>"Token expired");
                } else {
                    error('Invalid Token provided. Error '.$claims['status']);
                    return array('result'=>false, 'status'=>401, 'message'=>"Not authorised (".$claims.")");
                };
            } else {
                $claims['result'] = true;
                return $claims;
            };
        } else {
            error('JWT Token not provided');
            return array('result'=>false, 'status'=>401, 'message'=>"Invalid authorisation (2)");
        };
    } else {
        error('Authorisation header & JWT Token not provided');
        return array('result'=>false, 'status'=>401, 'message'=>"Invalid authorisation (3)");
    };
};

?>