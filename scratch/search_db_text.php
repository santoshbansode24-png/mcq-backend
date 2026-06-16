<?php
$host = 'yamanote.proxy.rlwy.net';
$port = '24540';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$dbname = 'railway';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    
    echo "Connected successfully to live database.\n";
    
    // Get list of tables
    $tablesStmt = $pdo->query("SHOW TABLES");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_NUM);
    
    $searchTerms = ['%exam portal%', '%connecting to%'];
    
    foreach ($tables as $tableRow) {
        $table = $tableRow[0];
        
        // Get columns of the table
        $colsStmt = $pdo->query("DESCRIBE `$table`");
        $cols = $colsStmt->fetchAll();
        
        $textCols = [];
        foreach ($cols as $col) {
            $type = strtolower($col['Type']);
            if (strpos($type, 'char') !== false || strpos($type, 'text') !== false) {
                $textCols[] = $col['Field'];
            }
        }
        
        if (empty($textCols)) continue;
        
        // Build query to search all text columns
        $conditions = [];
        $params = [];
        foreach ($textCols as $col) {
            foreach ($searchTerms as $term) {
                $conditions[] = "`$col` LIKE ?";
                $params[] = $term;
            }
        }
        
        $query = "SELECT * FROM `$table` WHERE " . implode(" OR ", $conditions);
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        if (!empty($results)) {
            echo "\nFound matches in table '$table':\n";
            print_r($results);
        }
    }
    
    echo "\nDatabase search complete.\n";

} catch (PDOException $e) {
    echo "Search failed: " . $e->getMessage() . "\n";
}
?>
