<?php
/**
 * Diagnostic: Check CBSE Class 3 English Chapters
 * Verifies if chapter data exists in database
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

echo "=== CBSE CLASS 3 ENGLISH CHAPTERS DIAGNOSTIC ===\n\n";

try {
    // 1. Find CBSE Class 3
    echo "Step 1: Finding CBSE Class 3...\n";
    $stmt = $pdo->query("
        SELECT class_id, class_name, board_id 
        FROM classes 
        WHERE class_name LIKE '%Class 3%' OR class_name LIKE '%3%'
        LIMIT 10
    ");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($classes)) {
        echo "❌ No Class 3 found in database!\n";
        exit;
    }
    
    echo "Found classes:\n";
    foreach ($classes as $class) {
        echo "  - ID: {$class['class_id']}, Name: {$class['class_name']}, Board ID: {$class['board_id']}\n";
    }
    
    // 2. Find CBSE Board
    echo "\nStep 2: Finding CBSE Board...\n";
    $stmt = $pdo->query("SELECT board_id, board_name FROM boards WHERE board_name LIKE '%CBSE%'");
    $cbseBoard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cbseBoard) {
        echo "❌ CBSE Board not found!\n";
        exit;
    }
    
    echo "CBSE Board: ID={$cbseBoard['board_id']}, Name={$cbseBoard['board_name']}\n";
    
    // 3. Find CBSE Class 3
    $stmt = $pdo->prepare("
        SELECT class_id, class_name 
        FROM classes 
        WHERE board_id = :board_id AND (class_name LIKE '%3%' OR class_name LIKE '%Class 3%')
    ");
    $stmt->execute([':board_id' => $cbseBoard['board_id']]);
    $cbseClass3 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cbseClass3) {
        echo "❌ CBSE Class 3 not found!\n";
        exit;
    }
    
    echo "\n✓ CBSE Class 3: ID={$cbseClass3['class_id']}, Name={$cbseClass3['class_name']}\n";
    $classId = $cbseClass3['class_id'];
    
    // 4. Find English Subject
    echo "\nStep 3: Finding English Subject...\n";
    $stmt = $pdo->prepare("
        SELECT subject_id, subject_name 
        FROM subjects 
        WHERE class_id = :class_id AND subject_name LIKE '%English%'
    ");
    $stmt->execute([':class_id' => $classId]);
    $englishSubject = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$englishSubject) {
        echo "❌ English subject not found for Class 3!\n";
        echo "\nAvailable subjects for this class:\n";
        $stmt = $pdo->prepare("SELECT subject_id, subject_name FROM subjects WHERE class_id = :class_id");
        $stmt->execute([':class_id' => $classId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $subj) {
            echo "  - {$subj['subject_name']} (ID: {$subj['subject_id']})\n";
        }
        exit;
    }
    
    echo "✓ English Subject: ID={$englishSubject['subject_id']}, Name={$englishSubject['subject_name']}\n";
    $subjectId = $englishSubject['subject_id'];
    
    // 5. Check Chapters
    echo "\nStep 4: Checking Chapters...\n";
    $stmt = $pdo->prepare("
        SELECT chapter_id, chapter_name, chapter_number, created_at, updated_at
        FROM chapters 
        WHERE subject_id = :subject_id
        ORDER BY chapter_number
    ");
    $stmt->execute([':subject_id' => $subjectId]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total chapters found: " . count($chapters) . "\n\n";
    
    if (count($chapters) > 0) {
        echo "Chapters:\n";
        foreach ($chapters as $ch) {
            echo "  Chapter {$ch['chapter_number']}: {$ch['chapter_name']}\n";
            echo "    ID: {$ch['chapter_id']}\n";
            echo "    Created: {$ch['created_at']}\n";
            echo "    Updated: {$ch['updated_at']}\n";
            
            // Check content for each chapter
            $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM mcqs WHERE chapter_id = :cid");
            $stmt2->execute([':cid' => $ch['chapter_id']]);
            $mcqCount = $stmt2->fetch()['count'];
            
            $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM flashcards WHERE chapter_id = :cid");
            $stmt2->execute([':cid' => $ch['chapter_id']]);
            $flashcardCount = $stmt2->fetch()['count'];
            
            echo "    MCQs: $mcqCount, Flashcards: $flashcardCount\n";
            echo "    ---\n";
        }
    } else {
        echo "❌ No chapters found!\n";
    }
    
    // 6. Test API Endpoint
    echo "\nStep 5: Testing API Endpoint...\n";
    $url = "http://localhost/veeru/backend/api/get_chapters.php?subject_id=$subjectId";
    echo "URL: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['status'])) {
            echo "API Status: {$data['status']}\n";
            if ($data['status'] === 'success' && isset($data['data'])) {
                echo "Chapters returned by API: " . count($data['data']) . "\n";
            }
        }
    }
    
    echo "\n✅ DIAGNOSTIC COMPLETE\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
