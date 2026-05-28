<?php
for($i=0; $i<5; $i++) {
    $ch = curl_init('https://api.veeruapp.in/api/get_mcqs.php?chapter_ids=15,77,78,79');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    echo curl_getinfo($ch, CURLINFO_HTTP_CODE) . " ";
    curl_close($ch);
}
echo "\n";
?>
