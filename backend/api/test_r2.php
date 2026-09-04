<?php
// TEMPORARY TEST FILE - Remove after verification
if (file_exists(__DIR__ . '/../config/aws-config.php')) {
    require_once __DIR__ . '/../config/aws-config.php';
} elseif (file_exists(__DIR__ . '/config/aws-config.php')) {
    require_once __DIR__ . '/config/aws-config.php';
}

header('Content-Type: application/json');

$results = [
    'r2_configured' => !empty(R2_ACCESS_KEY_ID) && !empty(R2_SECRET_ACCESS_KEY),
    'endpoint'      => R2_ENDPOINT,
    'bucket'        => R2_BUCKET_NAME,
    'public_url'    => R2_PUBLIC_URL,
    'key_preview'   => !empty(R2_ACCESS_KEY_ID) ? substr(R2_ACCESS_KEY_ID, 0, 8) . '...' : 'NOT SET',
];

// Try uploading a tiny test file to R2
$testResult = 'not_tested';
if ($results['r2_configured']) {
    try {
        $tmpFile = tempnam(sys_get_temp_dir(), 'r2test_');
        file_put_contents($tmpFile, 'Veeru R2 Test - ' . date('Y-m-d H:i:s'));
        $testKey  = 'test/connection_test_' . time() . '.txt';
        $url      = uploadToS3($tmpFile, $testKey);
        unlink($tmpFile);
        $testResult = $url ? 'success: ' . $url : 'upload_failed';
    } catch (Exception $e) {
        $testResult = 'error: ' . $e->getMessage();
    }
} else {
    $testResult = 'skipped - env vars not set';
}

$results['upload_test'] = $testResult;
echo json_encode($results, JSON_PRETTY_PRINT);
?>
