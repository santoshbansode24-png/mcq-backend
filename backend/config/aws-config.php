<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// AWS Configuration
define('AWS_ACCESS_KEY_ID', getenv('AWS_ACCESS_KEY_ID') ?: 'YOUR_AWS_ACCESS_KEY_ID');
define('AWS_SECRET_ACCESS_KEY', getenv('AWS_SECRET_ACCESS_KEY') ?: 'YOUR_AWS_SECRET_ACCESS_KEY');
define('AWS_DEFAULT_REGION', getenv('AWS_DEFAULT_REGION') ?: 'ap-south-1');
define('AWS_BUCKET_NAME', getenv('AWS_BUCKET_NAME') ?: 'veeru-notes-storage-2026');

/**
 * Get configured S3 Client
 * @return S3Client
 */
function getS3Client() {
    return new S3Client([
        'version' => 'latest',
        'region'  => AWS_DEFAULT_REGION,
        'credentials' => [
            'key'    => AWS_ACCESS_KEY_ID,
            'secret' => AWS_SECRET_ACCESS_KEY,
        ]
    ]);
}

/**
 * Upload file to S3
 * @param string $sourceFile Path to local file
 * @param string $s3Key Desired path/key in S3 bucket (e.g. notes/file.pdf)
 * @return string|false Public URL of the uploaded file or false on failure
 */
function uploadToS3($sourceFile, $s3Key) {
    $s3 = getS3Client();
    
    try {
        $result = $s3->putObject([
            'Bucket' => AWS_BUCKET_NAME,
            'Key'    => $s3Key,
            'SourceFile' => $sourceFile,
            // 'ACL'    => 'public-read', // Removed: Bucket owner enforced / ACLs disabled
            // 'ContentType' => mime_content_type($sourceFile) // Optional: explicit content type
        ]);
        
        return $result['ObjectURL'];
    } catch (AwsException $e) {
        // Log error
        error_log("AWS S3 Upload Error: " . $e->getMessage());
        return false;
    }
}
?>
