<?php
require_once 'config/db.php';

try {
    $stmt = $pdo->query("SELECT key_points FROM quick_revision LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo "Raw JSON:\n" . $row['key_points'] . "\n\n";
        $decoded = json_decode($row['key_points'], true);
        echo "Decoded first item keys:\n";
        if (is_array($decoded) && count($decoded) > 0) {
            print_r(array_keys($decoded[0]));
        } else {
            echo "Decoded is empty or not array";
        }
    } else {
        echo "No data in quick_revision";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
