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
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=1777135263">
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
            
            <a href="?board=SCHOLARSHIP" class="card scholarship">
                <span class="icon">🏆</span>
                <span class="title">Scholarship & Olympiad</span>
            </a>
        </div>
        
        <a href="logout.php" class="logout">Logout</a>
    </div>
</body>
</html>
