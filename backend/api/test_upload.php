<!DOCTYPE html>
<html>
<head>
    <title>Test PDF Upload</title>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        input[type="file"] { margin: 10px 0; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>Test PDF Upload</h1>
    <p>This is a simple test to verify PDF uploads work correctly.</p>
    
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="test_pdf" accept=".pdf" required>
        <br>
        <button type="submit">Upload Test PDF</button>
    </form>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_pdf'])) {
        $upload_dir = __DIR__ . '/../uploads/notes/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file = $_FILES['test_pdf'];
        $filename = time() . '_test.pdf';
        $destination = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $file_url = "https://api.veeruapp.in/backend/api/serve_pdf.php?file=uploads/notes/" . $filename;
            echo "<div class='success'>";
            echo "✅ Upload successful!<br>";
            echo "File saved to: $destination<br>";
            echo "File size: " . filesize($destination) . " bytes<br>";
            echo "Test URL: <a href='$file_url' target='_blank'>$file_url</a><br>";
            echo "</div>";
        } else {
            echo "<div class='error'>❌ Upload failed!</div>";
        }
    }
    
    // List existing files
    $upload_dir = __DIR__ . '/../uploads/notes/';
    if (is_dir($upload_dir)) {
        $files = array_diff(scandir($upload_dir), ['.', '..']);
        if (count($files) > 0) {
            echo "<h2>Files in upload directory:</h2><ul>";
            foreach ($files as $file) {
                $size = filesize($upload_dir . $file);
                echo "<li>$file (" . number_format($size) . " bytes)</li>";
            }
            echo "</ul>";
        } else {
            echo "<p><em>No files in upload directory yet.</em></p>";
        }
    }
    ?>
</body>
</html>
