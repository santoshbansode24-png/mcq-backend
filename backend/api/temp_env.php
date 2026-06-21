<?php
if (($_GET['secret'] ?? '') !== 'my_temporary_secret_key_123') {
    die('Unauthorized');
}
header('Content-Type: application/json');
echo json_encode([
    'R2_ACCESS_KEY_ID' => getenv('R2_ACCESS_KEY_ID'),
    'R2_SECRET_ACCESS_KEY' => getenv('R2_SECRET_ACCESS_KEY'),
    'R2_ENDPOINT' => getenv('R2_ENDPOINT'),
    'R2_BUCKET_NAME' => getenv('R2_BUCKET_NAME'),
    'R2_PUBLIC_URL' => getenv('R2_PUBLIC_URL'),
]);
?>
