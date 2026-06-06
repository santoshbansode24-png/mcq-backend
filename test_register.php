<?php
$urls = [
    'local_backend_register' => 'http://localhost/veeru/backend/api/register.php',
    'prod_backend_register' => 'https://api.veeruapp.in/backend/api/register.php',
    'prod_api_register' => 'https://api.veeruapp.in/api/register.php'
];

foreach ($urls as $name => $url) {
    echo "Testing $name ($url):\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    // Send a blank payload to trigger field validation
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    
    echo "HTTP CODE: " . $info['http_code'] . "\n";
    if ($err) {
        echo "CURL ERROR: $err\n";
    } else {
        echo "RESPONSE: $res\n";
    }
    echo "---------------------------------------------------\n";
    curl_close($ch);
}
?>
