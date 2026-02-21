<?php
require_once '../config/aws-config.php';

header('Content-Type: application/json');

$results = [
    'aws_key_set' => defined('AWS_ACCESS_KEY_ID') && AWS_ACCESS_KEY_ID !== 'YOUR_AWS_ACCESS_KEY_ID' ? 'YES' : 'NO',
    'aws_secret_set' => defined('AWS_SECRET_ACCESS_KEY') && AWS_SECRET_ACCESS_KEY !== 'YOUR_AWS_SECRET_ACCESS_KEY' ? 'YES' : 'NO',
    'bucket' => defined('AWS_BUCKET_NAME') ? AWS_BUCKET_NAME : 'NOT DEFINED',
    'region' => defined('AWS_DEFAULT_REGION') ? AWS_DEFAULT_REGION : 'NOT DEFINED',
];

if ($results['aws_key_set'] === 'YES') {
    try {
        $s3 = getS3Client();
        // Test connectivity by listing objects (with limit 1)
        $s3->listObjectsV2([
            'Bucket' => AWS_BUCKET_NAME,
            'MaxKeys' => 1
        ]);
        $results['connection'] = 'SUCCESS';
    } catch (Exception $e) {
        $results['connection'] = 'FAILED';
        $results['error'] = $e->getMessage();
    }
} else {
    $results['connection'] = 'SKIPPED (Keys not set)';
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>
