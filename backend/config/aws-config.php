<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// =====================================================
// CLOUDFLARE R2 CONFIGURATION (S3-Compatible Storage)
// =====================================================
// All secrets are stored as Railway environment variables — NOT in code.

define('R2_ACCESS_KEY_ID',     getenv('R2_ACCESS_KEY_ID')     ?: '');
define('R2_SECRET_ACCESS_KEY', getenv('R2_SECRET_ACCESS_KEY') ?: '');
define('R2_ENDPOINT',          getenv('R2_ENDPOINT')          ?: 'https://df57a4dcdaa565e80969e7b3b7ca183f.r2.cloudflarestorage.com');
define('R2_BUCKET_NAME',       getenv('R2_BUCKET_NAME')       ?: 'veeru-storage');
define('R2_PUBLIC_URL',        getenv('R2_PUBLIC_URL')        ?: ''); // e.g. https://pub-xxxx.r2.dev

// Legacy aliases for backward compatibility
define('AWS_ACCESS_KEY_ID',     R2_ACCESS_KEY_ID);
define('AWS_SECRET_ACCESS_KEY', R2_SECRET_ACCESS_KEY);
define('AWS_DEFAULT_REGION',    'auto');
define('AWS_BUCKET_NAME',       R2_BUCKET_NAME);

/**
 * Get configured R2/S3 Client
 * @return S3Client
 */
function getS3Client() {
    return new S3Client([
        'version'                 => 'latest',
        'region'                  => 'auto',
        'endpoint'                => R2_ENDPOINT,
        'use_path_style_endpoint' => true, // Required for R2
        'credentials' => [
            'key'    => R2_ACCESS_KEY_ID,
            'secret' => R2_SECRET_ACCESS_KEY,
        ],
    ]);
}

/**
 * Upload file to Cloudflare R2 (S3-compatible)
 * @param string $sourceFile  Path to local temp file
 * @param string $s3Key       Desired object key/path in bucket (e.g. class_materials/file.pdf)
 * @return string|false       Public URL of the uploaded file, or false on failure
 */
function uploadToS3($sourceFile, $s3Key) {
    // Check R2 is configured
    if (empty(R2_ACCESS_KEY_ID) || empty(R2_SECRET_ACCESS_KEY)) {
        error_log("R2 not configured: missing R2_ACCESS_KEY_ID or R2_SECRET_ACCESS_KEY env vars.");
        return false;
    }

    $s3 = getS3Client();

    try {
        // Detect content type
        $mimeType = mime_content_type($sourceFile) ?: 'application/octet-stream';

        $s3->putObject([
            'Bucket'      => R2_BUCKET_NAME,
            'Key'         => $s3Key,
            'SourceFile'  => $sourceFile,
            'ContentType' => $mimeType,
        ]);

        // Build public URL from Public Dev URL
        $publicBase = rtrim(R2_PUBLIC_URL, '/');
        if (!empty($publicBase)) {
            return $publicBase . '/' . ltrim($s3Key, '/');
        }

        // Fallback: endpoint-based URL
        return rtrim(R2_ENDPOINT, '/') . '/' . R2_BUCKET_NAME . '/' . ltrim($s3Key, '/');

    } catch (AwsException $e) {
        error_log("R2 Upload Error: " . $e->getMessage());
        return false;
    }
}
?>
