<?php
$buildGradle = 'student_app/android/app/build.gradle';
if (file_exists($buildGradle)) {
    $content = file_get_contents($buildGradle);
    if (preg_match('/applicationId\s+"([^"]+)"/', $content, $matches)) {
        echo "applicationId in build.gradle: " . $matches[1] . "\n";
    }
}

$manifest = 'student_app/android/app/src/main/AndroidManifest.xml';
if (file_exists($manifest)) {
    $content = file_get_contents($manifest);
    if (preg_match('/package="([^"]+)"/', $content, $matches)) {
        echo "package in AndroidManifest.xml: " . $matches[1] . "\n";
    }
}
?>
