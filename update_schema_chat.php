<?php
require_once 'config/db.php';

echo "<h1>Updating Schema for Chat Features</h1>";

try {
    // Messages Table
    $sql = "
    CREATE TABLE IF NOT EXISTS `messages` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `sender_id` INT NOT NULL,
      `receiver_id` INT DEFAULT NULL, -- NULL if class broadcast
      `class_code` VARCHAR(20) DEFAULT NULL,
      `message_text` TEXT NOT NULL,
      `attachment_url` VARCHAR(500) DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_sender (sender_id),
      INDEX idx_receiver (receiver_id),
      INDEX idx_class_code (class_code)
    );
    ";

    $pdo->exec($sql);
    echo "✅ Tables Created Successfully (messages).<br>";

} catch (PDOException $e) {
    echo "❌ SQL Error: " . $e->getMessage();
}
?>
