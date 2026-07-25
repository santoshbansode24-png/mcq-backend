<?php
/**
 * Run R2 Notes SQL Migration on Railway DB
 */
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$queries = [
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612157_The_First_Game_Changers__1__compressed__1_.pdf' WHERE note_id = 42",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612184_Indus_Valley_Civilisation_Class_Notes_compressed__1_.pdf' WHERE note_id = 43",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612201_Our_Government_Ultimate_Revision_Notes_compressed__1_.pdf' WHERE note_id = 44",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612222_India_National_Symbols_Notes_compressed__1_.pdf' WHERE note_id = 45",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612247_India_s_Great_Leaders_compressed__1_.pdf' WHERE note_id = 46",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612284_Travel_and_Talk_Revision_Notes_compressed__2_.pdf' WHERE note_id = 47",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612304_Different_Occupations_compressed__1_.pdf' WHERE note_id = 48",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612328_Environment_Protection_Basics_compressed__1_.pdf' WHERE note_id = 49",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612373_Air_Water_and_Weather_compressed__1_.pdf' WHERE note_id = 50",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612391_Housing_and_Clothing_Notes_compressed__1_.pdf' WHERE note_id = 51",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612418_Mawlynnong_Chapter_Review_compressed__1_.pdf' WHERE note_id = 52",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771612437_Boond_The_Water_Cycle_Story_compressed.pdf' WHERE note_id = 53",
    "UPDATE notes SET file_path = 'https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/1771668339_My_Brother_s_Wheelchair_Notes_compressed.pdf' WHERE note_id = 57"
];

$count = 0;
foreach ($queries as $q) {
    try {
        $pdo->exec($q);
        $count++;
    } catch (Exception $e) {
        // Continue
    }
}

echo json_encode([
    'status' => 'success',
    'updated_count' => $count,
    'message' => "Successfully updated $count notes to Cloudflare R2 links"
]);
?>
