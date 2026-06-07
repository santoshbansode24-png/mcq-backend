<?php
/**
 * Expo Push Notifications Utility (Optimized with self-healing token cleanup)
 * Veeru Nested Backend
 */

/**
 * Sends a push notification to all students in a specific class.
 *
 * @param PDO    $pdo      Database connection
 * @param int    $class_id Classroom ID
 * @param string $title    Notification Title
 * @param string $body     Notification Message
 * @param array  $data     Optional deep-link custom payload
 * @return int             Number of successful push notifications queued
 */
function sendClassPushNotifications($pdo, $class_id, $title, $body, $data = []) {
    try {
        // 1. Fetch all student push tokens in the target class
        $stmt = $pdo->prepare("
            SELECT u.push_token 
            FROM users u
            JOIN student_class_mapping scm ON u.user_id = scm.student_id
            WHERE scm.class_id = ? AND u.push_token IS NOT NULL AND u.push_token != ''
        ");
        $stmt->execute([intval($class_id)]);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tokens)) {
            error_log("[PushService] No push tokens found for class_id: " . $class_id);
            return 0;
        }

        // Filter and clean unique tokens
        $tokens = array_unique(array_filter($tokens));
        
        // 2. Prepare Expo Push API batch requests (Max 100 per chunk as per Expo rules)
        $chunks = array_chunk($tokens, 100);
        $totalSent = 0;

        foreach ($chunks as $chunk) {
            $messages = [];
            foreach ($chunk as $token) {
                // Quick format check (Expo push tokens start with ExponentPushToken or ExpoPushToken)
                if (strpos($token, 'ExponentPushToken') !== false || strpos($token, 'ExpoPushToken') !== false) {
                    $messages[] = [
                        'to' => $token,
                        'title' => $title,
                        'body' => $body,
                        'data' => $data,
                        'sound' => 'default',
                        'priority' => 'high'
                    ];
                }
            }

            if (empty($messages)) {
                continue;
            }

            // 3. Dispatch to Expo
            $ch = curl_init('https://exp.host/--/api/v2/push/send');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Accept-Encoding: gzip, deflate'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messages));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8); // Fail fast so teacher request doesn't hang

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $resData = json_decode($response, true);
                if (isset($resData['data']) && is_array($resData['data'])) {
                    foreach ($resData['data'] as $index => $status) {
                        if (isset($status['status']) && $status['status'] === 'ok') {
                            $totalSent++;
                        } else {
                            error_log("[PushService] Individual push failed: " . json_encode($status));
                            
                            // Auto-cleanup unregistered tokens
                            if (isset($status['details']['error']) && $status['details']['error'] === 'DeviceNotRegistered') {
                                if (isset($messages[$index]['to'])) {
                                    $deadToken = $messages[$index]['to'];
                                    try {
                                        $cleanupStmt = $pdo->prepare("UPDATE users SET push_token = NULL WHERE push_token = ?");
                                        $cleanupStmt->execute([$deadToken]);
                                        error_log("[PushService] Cleaned dead token from database: " . $deadToken);
                                    } catch (PDOException $ex) {
                                        error_log("[PushService] DB error cleaning dead token: " . $ex->getMessage());
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                error_log("[PushService] Expo request failed. HTTP: " . $httpCode . ", Error: " . $curlError . ", Response: " . $response);
            }
        }

        return $totalSent;
    } catch (Exception $e) {
        error_log("[PushService] Exception: " . $e->getMessage());
        return 0;
    }
}
?>
