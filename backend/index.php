<?php
/**
 * Veeru App - Main Entry Point & Smart API Route Dispatcher
 */

$request_uri = $_SERVER['REQUEST_URI'] ?? '';

// Redirect /teacher to the teacher portal subdirectory
if ($request_uri == '/teacher' || $request_uri == '/teacher/') {
    header("Location: /teacher/index.php");
    exit();
}

// Redirect old local uploads requests (direct URLs) to Cloudflare R2 public bucket
if (preg_match('/uploads\/notes\/([^\/]+)/i', $request_uri, $matches)) {
    header("Location: https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/" . $matches[1], true, 302);
    exit();
}
if (preg_match('/uploads\/class_materials\/([^\/]+)/i', $request_uri, $matches)) {
    header("Location: https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/class_materials/" . $matches[1], true, 302);
    exit();
}
if (preg_match('/uploads\/class_documents\/([^\/]+)/i', $request_uri, $matches)) {
    header("Location: https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/class_documents/" . $matches[1], true, 302);
    exit();
}

// Smart API Route Dispatcher for Railway Nginx / Apache / PHP Built-in Server
$path = parse_url($request_uri, PHP_URL_PATH);
if (!empty($path) && $path !== '/' && $path !== '/index.php') {
    if (preg_match('/([a-zA-Z0-9_\-]+\.php)$/i', $path, $m)) {
        $file = $m[1];
        if ($file !== 'index.php') {
            if (isset($_GET['debug_route'])) {
                header('Content-Type: text/plain');
                echo "DIR: " . __DIR__ . "\n";
                echo "CWD: " . getcwd() . "\n";
                echo "REQ: " . $request_uri . "\n";
                echo "FILES IN /app/api:\n";
                print_r(glob('/app/api/*'));
                echo "FILES IN /app/backend/api:\n";
                print_r(glob('/app/backend/api/*'));
                exit();
            }
            $locations = [
                __DIR__ . '/backend/api/' . $file,
                __DIR__ . '/api/' . $file,
                __DIR__ . '/' . $file,
                '/app/backend/api/' . $file,
                '/app/api/' . $file,
                '/app/' . $file
            ];
            foreach ($locations as $loc) {
                if (file_exists($loc) && is_file($loc)) {
                    require $loc;
                    exit();
                }
            }
            try {
                $baseSearch = is_dir('/app') ? '/app' : __DIR__;
                $dirIter = new RecursiveDirectoryIterator($baseSearch, RecursiveDirectoryIterator::SKIP_DOTS);
                $iterator = new RecursiveIteratorIterator($dirIter);
                foreach ($iterator as $f) {
                    if ($f->isFile() && strcasecmp($f->getFilename(), $file) === 0) {
                        require $f->getPathname();
                        exit();
                    }
                }
            } catch (Throwable $t) {}
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veeru - Educational Platform</title>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #064E3B 0%, #059669 100%);
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            margin: 0;
            text-align: center;
        }
        .container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 3rem;
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 500px;
            width: 90%;
        }
        h1 { font-size: 3rem; margin-bottom: 1rem; font-weight: 800; letter-spacing: -0.05em; }
        p { font-size: 1.2rem; opacity: 0.9; margin-bottom: 2rem; }
        .btn {
            display: inline-block;
            background: white;
            color: #059669;
            padding: 1rem 2rem;
            border-radius: 1rem;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            background: #f0fdf4;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Veeru</h1>
        <p>Empowering teachers and students with smart AI-driven educational tools.</p>
        <a href="/teacher/" class="btn">Go to Teacher Portal</a>
    </div>
</body>
</html>
