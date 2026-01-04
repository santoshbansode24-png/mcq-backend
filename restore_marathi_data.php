<?php
include_once __DIR__ . '/config/db.php';
global $pdo;

echo "Restoring Marathi words (Data Only)...\n";

try {
    // 1. Delete first 20 words
    echo "Deleting IDs 1-20...\n";
    $pdo->exec("DELETE FROM vocab_words WHERE word_id <= 20");

    // 2. Insert words
    echo "Inserting Marathi words...\n";
    $sql = "INSERT INTO vocab_words (word_id, word, definition, definition_marathi, example_sentence, difficulty_level, set_number, level_name, word_type, category_id) VALUES
    (1, 'Happy', 'Feeling or showing pleasure', 'आनंदी, खुश', 'She was happy to see her friends', 'Easy', 1, 'Beginner', 'adjective', 1),
    (2, 'Big', 'Large in size', 'मोठा, विशाल', 'The elephant is a big animal', 'Easy', 1, 'Beginner', 'adjective', 1),
    (3, 'Small', 'Little in size', 'लहान, छोटा', 'The mouse is a small animal', 'Easy', 1, 'Beginner', 'adjective', 1),
    (4, 'Good', 'Of high quality', 'चांगला, उत्तम', 'He is a good student', 'Easy', 1, 'Beginner', 'adjective', 1),
    (5, 'Bad', 'Of poor quality', 'वाईट, खराब', 'Smoking is a bad habit', 'Easy', 1, 'Beginner', 'adjective', 1),
    (6, 'Fast', 'Moving quickly', 'वेगवान, जलद', 'The cheetah is a fast runner', 'Easy', 1, 'Beginner', 'adjective', 1),
    (7, 'Slow', 'Moving at low speed', 'मंद, हळू', 'The tortoise is slow but steady', 'Easy', 1, 'Beginner', 'adjective', 1),
    (8, 'Hot', 'Having high temperature', 'गरम, उष्ण', 'The tea is too hot to drink', 'Easy', 1, 'Beginner', 'adjective', 1),
    (9, 'Cold', 'Having low temperature', 'थंड, शीत', 'Ice cream is cold and sweet', 'Easy', 1, 'Beginner', 'adjective', 1),
    (10, 'New', 'Recently made or created', 'नवीन, ताजा', 'I bought a new phone', 'Easy', 1, 'Beginner', 'adjective', 1),
    (11, 'Old', 'Having existed for a long time', 'जुना, प्राचीन', 'This is an old building', 'Easy', 1, 'Beginner', 'adjective', 1),
    (12, 'Clean', 'Free from dirt', 'स्वच्छ, शुद्ध', 'Keep your room clean', 'Easy', 1, 'Beginner', 'adjective', 1),
    (13, 'Dirty', 'Covered with dirt', 'घाणेरडा, मळकट', 'Wash your dirty clothes', 'Easy', 1, 'Beginner', 'adjective', 1),
    (14, 'Easy', 'Not difficult', 'सोपा, सुलभ', 'This question is very easy', 'Easy', 1, 'Beginner', 'adjective', 1),
    (15, 'Hard', 'Difficult to do', 'कठीण, अवघड', 'Math can be hard sometimes', 'Easy', 1, 'Beginner', 'adjective', 1),
    (16, 'Bright', 'Giving out light', 'तेजस्वी, चमकदार', 'The sun is very bright', 'Easy', 1, 'Beginner', 'adjective', 1),
    (17, 'Dark', 'With little or no light', 'अंधारमय, काळोख', 'The room is too dark', 'Easy', 1, 'Beginner', 'adjective', 1),
    (18, 'Strong', 'Having power', 'बलवान, मजबूत', 'He is very strong', 'Easy', 1, 'Beginner', 'adjective', 1),
    (19, 'Weak', 'Lacking strength', 'कमकुवत, दुर्बल', 'He felt weak after illness', 'Easy', 1, 'Beginner', 'adjective', 1),
    (20, 'Beautiful', 'Pleasing to look at', 'सुंदर, मनोहर', 'The flowers are beautiful', 'Easy', 1, 'Beginner', 'adjective', 1);";
    
    $pdo->exec($sql);
    echo "Success!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
