<?php
require_once __DIR__ . '/../backend/config/aws-config.php';
if (file_exists(__DIR__ . '/../backend/config/secrets.php')) {
    require_once __DIR__ . '/../backend/config/secrets.php';
}

echo "🚀 Starting Full Migration of Old PDFs -> Cloudflare R2...\n\n";

// Fetch list of notes from production API
$ch = curl_init("https://api.veeruapp.in/api/migrate_old_pdfs_to_r2.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);

echo "Response from Railway server migration endpoint:\n";
echo $res . "\n\n";

// Backup migration loop from local environment directly
$notesToMigrate = [
    42 => 'uploads/notes/1771612157_The_First_Game_Changers__1__compressed__1_.pdf',
    43 => 'uploads/notes/1771612184_Indus_Valley_Civilisation_Class_Notes_compressed__1_.pdf',
    44 => 'uploads/notes/1771612201_Our_Government_Ultimate_Revision_Notes_compressed__1_.pdf',
    45 => 'uploads/notes/1771612222_India_National_Symbols_Notes_compressed__1_.pdf',
    46 => 'uploads/notes/1771612247_India_s_Great_Leaders_compressed__1_.pdf',
    47 => 'uploads/notes/1771612284_Travel_and_Talk_Revision_Notes_compressed__2_.pdf',
    48 => 'uploads/notes/1771612304_Different_Occupations_compressed__1_.pdf',
    49 => 'uploads/notes/1771612328_Environment_Protection_Basics_compressed__1_.pdf',
    50 => 'uploads/notes/1771612373_Air_Water_and_Weather_compressed__1_.pdf',
    51 => 'uploads/notes/1771612391_Housing_and_Clothing_Notes_compressed__1_.pdf',
    52 => 'uploads/notes/1771612418_Mawlynnong_Chapter_Review_compressed__1_.pdf',
    53 => 'uploads/notes/1771612437_Boond_The_Water_Cycle_Story_compressed.pdf',
    57 => 'uploads/notes/1771668339_My_Brother_s_Wheelchair_Notes_compressed.pdf'
];

$sqlUpdates = [];

foreach ($notesToMigrate as $note_id => $file_path) {
    echo "Processing Note #$note_id ($file_path)... ";
    $url = "http://api.veeruapp.in/api/serve_pdf.php?file=" . urlencode($file_path);
    $tmpFile = sys_get_temp_dir() . '/pdf_' . $note_id . '.pdf';
    
    $fp = fopen($tmpFile, 'w+');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($httpCode === 200 && filesize($tmpFile) > 1000) {
        $s3Key = "notes/" . basename($file_path);
        $r2Url = uploadToS3($tmpFile, $s3Key);
        if ($r2Url) {
            echo "✅ Uploaded to R2: $r2Url\n";
            $sqlUpdates[] = "UPDATE notes SET file_path = '" . addslashes($r2Url) . "' WHERE note_id = $note_id;";
        } else {
            echo "❌ R2 Upload Failed\n";
        }
    } else {
        echo "❌ Download Failed (HTTP $httpCode)\n";
    }
    @unlink($tmpFile);
}

echo "\n=========================================\n";
echo "GENERATED SQL QUERIES TO UPDATE RAILWAY DB:\n";
echo "=========================================\n";
echo implode("\n", $sqlUpdates) . "\n";
file_put_contents(__DIR__ . '/update_notes_r2.sql', implode("\n", $sqlUpdates));
echo "\nSaved SQL queries to scratch/update_notes_r2.sql\n";
