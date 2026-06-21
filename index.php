<?php
/**
 * Veeru App - Main Entry Point
 */

// Redirect /teacher to the teacher portal subdirectory
$request_uri = $_SERVER['REQUEST_URI'];
if ($request_uri == '/teacher' || $request_uri == '/teacher/') {
    header("Location: /teacher/index.php");
    exit();
}

// Redirect old local notes requests (direct URLs) to Cloudflare R2 public bucket
if (preg_match('/uploads\/notes\/([^\/]+\.pdf)/i', $request_uri, $matches)) {
    $filename = $matches[1];
    $r2_url = "https://pub-30dbe31bca9f4e8d8f406dba53b733c3.r2.dev/notes/" . $filename;
    header("Location: " . $r2_url, true, 302);
    exit();
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
