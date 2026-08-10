<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// Load local secrets if they exist (local development fallback)
if (file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// =====================================================
// CLOUDFLARE R2 CONFIGURATION (S3-Compatible Storage)
// =====================================================
// All secrets are stored as Railway environment variables — NOT in code.

if (!defined('R2_ACCESS_KEY_ID')) {
    define('R2_ACCESS_KEY_ID',     getenv('R2_ACCESS_KEY_ID')     ?: '');
}
if (!defined('R2_SECRET_ACCESS_KEY')) {
    define('R2_SECRET_ACCESS_KEY', getenv('R2_SECRET_ACCESS_KEY') ?: '');
}
if (!defined('R2_ENDPOINT')) {
    define('R2_ENDPOINT',          getenv('R2_ENDPOINT')          ?: 'https://df57a4dcdaa565e80969e7b3b7ca183f.r2.cloudflarestorage.com');
}
if (!defined('R2_BUCKET_NAME')) {
    define('R2_BUCKET_NAME',       getenv('R2_BUCKET_NAME')       ?: 'veeru-storage');
}

// Fallback to the known public URL if the env var is missing
if (!defined('R2_PUBLIC_URL')) {
    $envPublicUrl = getenv('R2_PUBLIC_URL');
    define('R2_PUBLIC_URL',        $envPublicUrl ? $envPublicUrl : 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev');
}

// Legacy aliases for backward compatibility
if (!defined('AWS_ACCESS_KEY_ID')) {
    define('AWS_ACCESS_KEY_ID',     R2_ACCESS_KEY_ID);
}
if (!defined('AWS_SECRET_ACCESS_KEY')) {
    define('AWS_SECRET_ACCESS_KEY', R2_SECRET_ACCESS_KEY);
}
if (!defined('AWS_DEFAULT_REGION')) {
    define('AWS_DEFAULT_REGION',    'auto');
}
if (!defined('AWS_BUCKET_NAME')) {
    define('AWS_BUCKET_NAME',       R2_BUCKET_NAME);
}

/**
 * Check if Cloudflare R2 credentials are fully configured
 * @return bool
 */
function isR2Configured() {
    return !empty(R2_ACCESS_KEY_ID) && !empty(R2_SECRET_ACCESS_KEY);
}


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
