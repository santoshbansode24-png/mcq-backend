<?php
/**
 * AI Global Helpers - Fixed for XAMPP Windows & Railway Linux
 */

if (!function_exists('triggerAIWorker')) {
    /**
     * Trigger AI worker - works on both Windows XAMPP and Railway Linux.
     * Uses cURL fire-and-forget with a 1-second timeout.
     */
    function triggerAIWorker($workerScript = 'pdf_worker_ai.php') {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $currentDir = dirname($_SERVER['PHP_SELF']);
        $url = "$protocol://$host$currentDir/$workerScript";

        // Method 1: cURL fire-and-forget (works on Windows and Linux)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500); // only wait 500ms then abandon
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        @curl_exec($ch);
        curl_close($ch);
    }
}
?>
