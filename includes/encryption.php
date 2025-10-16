<?php
function encryptPassword($password, $user_id) {
    $cipher = "AES-256-CBC";
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = openssl_random_pseudo_bytes(16);
    
    $encrypted = openssl_encrypt($password, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    $combined = $iv . $encrypted;
    
    return base64_encode($combined);
}

function decryptPassword($encryptedPassword, $user_id) {
    $cipher = "AES-256-CBC";
    $key = hash('sha256', ENCRYPTION_KEY, true);
    
    $data = base64_decode($encryptedPassword);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    return openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
}
?>