<?php
/**
 * Select Board Gateway
 * Veeru Admin
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

// Handle Selection
if (isset($_GET['board'])) {
    $board = $_GET['board'];
    $valid_boards = ['CBSE', 'STATE_MARATHI', 'STATE_SEMI', 'SCHOLARSHIP'];
    
    if (in_array($board, $valid_boards)) {
        $_SESSION['admin_selected_board'] = $board;
        // Set Human Readable Name
        switch($board) {
            case 'CBSE': $_SESSION['board_name'] = 'CBSE Board'; break;
            case 'STATE_MARATHI': $_SESSION['board_name'] = 'State Board (Marathi)'; break;
            case 'STATE_SEMI': $_SESSION['board_name'] = 'State Board (Semi)'; break;
            case 'SCHOLARSHIP': $_SESSION['board_name'] = 'Scholarship & Olympiad'; break;
        }
        header('Location: dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Board - Veeru Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            padding: 50px;
            width: 100%;
            max-width: 900px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            text-align: center;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h1 { font-size: 36px; font-weight: 800; color: #1e293b; margin-bottom: 10px; letter-spacing: -1px; }
        p { color: #64748b; margin-bottom: 40px; font-size: 16px; }
        .grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 40px;
            width: 100%;
        }
        .card {
            text-decoration: none;
            padding: 30px 20px;
            border-radius: 24px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            flex: 1 1 200px;
            max-width: 250px;
            min-width: 200px;
        }
        .card i { font-size: 40px; transition: all 0.3s; }
        .card .title { font-weight: 700; color: #334155; font-size: 16px; line-height: 1.3; }
        .cbse i { color: #3b82f6; }
        .marathi i { color: #f97316; }
        .semi i { color: #8b5cf6; }
        .scholarship i { color: #eab308; }
        .card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: #667eea;
            background: white;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .logout {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #ef4444;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: opacity 0.3s;
        }
        .logout:hover { opacity: 0.7; }
        @media (max-width: 640px) {
            .container { padding: 30px 20px; }
            h1 { font-size: 28px; }
            .grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome Admin! 👋</h1>
        <p>Which Educational Board are you managing today?</p>
        <div class="grid">
            <a href="?board=CBSE" class="card cbse">
                <i class="fa-solid fa-school"></i>
                <span class="title">CBSE Board</span>
            </a>
            <a href="?board=STATE_MARATHI" class="card marathi">
                <i class="fa-solid fa-flag"></i>
                <span class="title">State Board<br>(Marathi)</span>
            </a>
            <a href="?board=STATE_SEMI" class="card semi">
                <i class="fa-solid fa-language"></i>
                <span class="title">State Board<br>(Semi English)</span>
            </a>
            <a href="?board=SCHOLARSHIP" class="card scholarship">
                <i class="fa-solid fa-award"></i>
                <span class="title">Scholarship &<br>Olympiad</span>
            </a>
        </div>
        <a href="logout.php" class="logout">
            <i class="fa-solid fa-right-from-bracket"></i> Logout Securely
        </a>
    </div>
</body>
</html>
