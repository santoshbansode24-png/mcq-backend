<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if (!defined('RAZORPAY_KEY_SECRET')) {
    define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: '');
}

echo json_encode([
    "secret_length" => strlen(RAZORPAY_KEY_SECRET),
    "secret_raw" => RAZORPAY_KEY_SECRET // I'll delete this immediately after
]);
?>
