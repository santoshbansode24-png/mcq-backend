<?php
// Debug script to simulate vocab_submit_rating.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'services/SRSService.php';

$userId = 1; // Assuming user ID 1 exists
$wordId = 5; // As per screenshot
$rating = 2; // As per screenshot
$timeTaken = 5;

echo "<h3>Testing Rating Submission</h3>";
echo "User: $userId, Word: $wordId, Rating: $rating<br>";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    echo "Transaction started.<br>";

    $srsService = new SRSService($pdo);
    
    echo "Calling updateProgress...<br>";
    $result = $srsService->updateProgress($userId, $wordId, $rating, $timeTaken);
    
    if (!$result['success']) {
        throw new Exception("SRSService Error: " . $result['message']);
    }

    echo "SRSService success. Result: <pre>" . print_r($result, true) . "</pre>";
    
    // Simulate the rest of the file logic
    $sql = "SELECT vw.word, vw.definition, vw.set_number, vc.category_name
            FROM vocab_words vw
            JOIN vocab_categories vc ON vw.category_id = vc.category_id
            WHERE vw.word_id = :word_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':word_id' => $wordId]);
    $wordDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$wordDetails) {
        throw new Exception("Word details not found for word_id: $wordId");
    }
    echo "Word details found: " . $wordDetails['word'] . "<br>";

    // Calc stats
    $setNumber = (int)$wordDetails['set_number'];
    echo "Set Number: $setNumber<br>";

    // Update stats table directly to test that query
    $nextSet = $setNumber + 1;
    $sql = "UPDATE user_vocab_stats 
            SET highest_set_unlocked = GREATEST(highest_set_unlocked, :next_set),
                sets_completed = GREATEST(sets_completed, :completed_set)
            WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':next_set' => $nextSet,
        ':completed_set' => $setNumber
    ]);
    echo "User stats updated.<br>";

    $pdo->commit();
    echo "<h3>SUCCESS: Transaction committed.</h3>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "Transaction rolled back.<br>";
    }
    $errmsg = "ERROR: " . $e->getMessage();
    echo "<h2 style='color:red'>" . $errmsg . "</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    file_put_contents('error_log.txt', $errmsg); 
}
?>
