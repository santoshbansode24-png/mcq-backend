<?php
for ($i = 1; $i <= 140; $i++) {
    $ch = curl_init("https://api.veeruapp.in/api/get_notes.php?chapter_id=$i");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    if (!empty($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as $note) {
            if ($note['note_type'] === 'pdf') {
                echo "Chapter $i | Note ID: {$note['note_id']} | Title: {$note['title']}\n";
                echo "  file_path: {$note['file_path']}\n";
                echo "  file_url:  {$note['file_url']}\n\n";
            }
        }
    }
}
