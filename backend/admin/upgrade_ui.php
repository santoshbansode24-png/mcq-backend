<?php
$dir = __DIR__;
$files = glob($dir . '/*.php');
$commonCss = '<!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">';

foreach ($files as $file) {
    if (basename($file) == 'upgrade_ui.php') continue;
    $content = file_get_contents($file);
    if (strpos($content, '<style>') !== false) {
        // Strip out the entire <style> block and replace with standard links
        $content = preg_replace('/<style>.*?<\/style>/s', $commonCss, $content);
        file_put_contents($file, $content);
        echo 'Updated: ' . basename($file) . "\n";
    }
}
echo "Done.\n";
?>
