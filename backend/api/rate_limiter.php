<?php
/**
 * Simple File-Based Rate Limiter
 * Protects AI endpoints from being spammed and draining API quotas.
 */
function checkRateLimit($limit = 15, $windowSeconds = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Use uploads directory or sys_get_temp_dir for cache
    $cacheDir = __DIR__ . '/../../uploads/ratelimit/';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    if (!is_writable($cacheDir)) {
        $cacheDir = sys_get_temp_dir() . '/ratelimit/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
    }
    
    $ipHash = md5($ip);
    $cacheFile = $cacheDir . $ipHash . '.json';
    
    $now = time();
    $requests = [];
    
    if (file_exists($cacheFile)) {
        $content = @file_get_contents($cacheFile);
        if ($content) {
            $data = json_decode($content, true);
            if (is_array($data)) {
                // Filter out requests older than the window
                foreach ($data as $timestamp) {
                    if ($now - $timestamp <= $windowSeconds) {
                        $requests[] = $timestamp;
                    }
                }
            }
        }
    }
    
    if (count($requests) >= $limit) {
        return false; // Rate limit exceeded
    }
    
    $requests[] = $now;
    @file_put_contents($cacheFile, json_encode($requests));
    
    return true;
}
?>
