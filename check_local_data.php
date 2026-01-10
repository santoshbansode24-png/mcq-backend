<?php
require_once 'backend/config/db.php';
echo "<h1>Recent Quick Revisions (Local DB)</h1>";
$stm = $pdo->query("SELECT * FROM quick_revision ORDER BY revision_id DESC LIMIT 5");
$rows = $stm->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) {
    echo "ID: " . $r['revision_id'] . " | Title: " . $r['title'] . "<br>";
    $points = json_decode($r['key_points'], true);
    echo "Points Count: " . count($points) . "<br>";
    if (count($points) > 0) {
        echo "First Point: " . print_r($points[0], true) . "<br>";
    }
    echo "<hr>";
}
?>
