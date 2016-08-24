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
    if (!isset($algorithms[$algorithm])) return false;
    $hmac = $algorithms[$algorithm];
    
    $token = explode('.',$token);
    if (count($token)<3) return false;
    
    $header = json_decode(base64_decode(strtr($token[0],'-_','+/')),true);
    if (!$secret) return false;
    if ($header['typ']!='JWT') return false;
    if ($header['alg']!=$algorithm) return false;
    
    $signature = bin2hex(base64_decode(strtr($token[2],'-_','+/')));
    if ($signature!=hash_hmac($hmac,"$token[0].$token[1]",$secret)) return false;
    
    $claims = json_decode(base64_decode(strtr($token[1],'-_','+/')),true);
    if (!$claims) return false;
    if (isset($claims['nbf']) && $time+$leeway<$claims['nbf']) return false;
    if (isset($claims['iat']) && $time+$leeway<$claims['iat']) return false;
    if (isset($claims['exp']) && $time-$leeway>$claims['exp']) return false;

    return $claims;
};

function token() {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        if (strlen($auth) >= 8) { // It's long enough to have a Bearer JWT token
            $token = substr($auth, 7);
            $claims = token_valid($token);
            
            if ($claims == false) {
                return array('result'=>false, 'status'=>401, 'message'=>"Not authorised (1)");
            } else {
                $claims['result'] = true;
                return $claims;
            };
        } else {
            return array('result'=>false, 'status'=>401, 'message'=>"Invalid authorisation (2)");
        };
    } else {
        return array('result'=>false, 'status'=>401, 'message'=>"Invalid authorisation (3)");
    };
};

?>