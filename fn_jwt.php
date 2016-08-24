<?php

function token_valid($token) {
    $time = time();
    $leeway = 60*60;
    $algorithm = "HS256";
    $secret = 'secret';

    $algorithms = array('HS256'=>'sha256','HS384'=>'sha384','HS512'=>'sha512');
    if (!isset($algorithms[$algorithm])) return false;
    $hmac = $algorithms[$algorithm];
    debug('hmac: '.$hmac);
    
    $token = explode('.',$token);
    if (count($token)<3) return false;
    
    $header = json_decode(base64_decode(strtr($token[0],'-_','+/')),true);
    debug('header: '.$header);
    if (!$secret) return false;
    if ($header['typ']!='JWT') return false;
    if ($header['alg']!=$algorithm) return false;
    debug('alg: '.$header['alg']);
    
    $signature = bin2hex(base64_decode(strtr($token[2],'-_','+/')));
    if ($signature!=hash_hmac($hmac,"$token[0].$token[1]",$secret)) return false;
    debug('sig ok');
    
    $claims = json_decode(base64_decode(strtr($token[1],'-_','+/')),true);
    if (!$claims) return false;
    if (isset($claims['nbf']) && $time+$leeway<$claims['nbf']) return false;
    if (isset($claims['iat']) && $time+$leeway<$claims['iat']) return false;
    if (isset($claims['exp']) && $time-$leeway>$claims['exp']) return false;
    debug('exp: '.$claims['exp']);
    // if (isset($claims['iat']) && !isset($claims['exp'])) {
    //     if ($time-$leeway>$claims['iat']+$ttl) return false;
    // };
    debug('claims good');
    return $claims;
};

function token($response) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        debug('auth: '.$auth);
        if (strlen($auth) >= 8) { // It's long enough to have a Bearer JWT token
            $token = substr($auth, 7);
            $token_len = strlen($token);
            debug('token length:', $token_len);

            $claims = token_valid($token);
            debug('got claims: '.var_export($claims, true));
            if ($claims == false) {
                http_response_code(401);
                $response["message"] = "Not authorised (1)";
            } else {
                return $claims;
            };
        } else {
            http_response_code(401);
            $response["message"] = "Invalid authorisation (2)";
        };
    } else {
        http_response_code(401);
        $response["message"] = "Invalid authorisation (3)";
    };
};

?>