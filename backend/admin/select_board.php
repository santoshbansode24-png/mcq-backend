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
    $valid_boards = ['CBSE', 'STATE_MARATHI', 'STATE_SEMI'];
    
    if (in_array($board, $valid_boards)) {
        $_SESSION['admin_selected_board'] = $board;
        // Set Human Readable Name
        switch($board) {
            case 'CBSE': $_SESSION['board_name'] = 'CBSE Board'; break;
            case 'STATE_MARATHI': $_SESSION['board_name'] = 'State Board (Marathi)'; break;
            case 'STATE_SEMI': $_SESSION['board_name'] = 'State Board (Semi)'; break;
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 800px;
            width: 90%;
        }
        h1 { color: #2d3748; margin-bottom: 10px; }
        p { color: #718096; margin-bottom: 30px; }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .card {
            background: #f8f9fa;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .card:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        
        .icon { font-size: 40px; margin-bottom: 15px; display: block; }
        .title { font-weight: 700; font-size: 18px; color: #4a5568; display: block; }
        
        .card.cbse:hover .title { color: #1976d2; }
        .card.marathi:hover .title { color: #388e3c; }
        .card.semi:hover .title { color: #f57c00; }
        
        .logout {
            margin-top: 30px;
            display: inline-block;
            color: #718096;
            text-decoration: none;
            font-size: 14px;
        }
        .logout:hover { color: #e53e3e; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome Admin! 👋</h1>
        <p>Select the Educational Board you want to manage today.</p>
        
        <div class="grid">
            <a href="?board=CBSE" class="card cbse">
                <span class="icon">🏫</span>
                <span class="title">CBSE Board</span>
            </a>
            
            <a href="?board=STATE_MARATHI" class="card marathi">
                <span class="icon">🚩</span>
                <span class="title">State Board<br>(Marathi Medium)</span>
            </a>
            
            <a href="?board=STATE_SEMI" class="card semi">
                <span class="icon">🇬🇧</span>
                <span class="title">State Board<br>(Semi English)</span>
            </a>
        </div>
        
        <a href="logout.php" class="logout">Logout</a>
    </div>
</body>
</html>
