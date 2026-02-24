<?php
require_once 'config.php';

$email = 'reviewer@veeru.com';
$stmt = $conn->prepare("SELECT id, name, class_id FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "SUCCESS: Reviewer account found.\n";
    print_r($result->fetch_assoc());
} else {
    echo "ERROR: Reviewer account NOT found in database.\n";
}
?>
