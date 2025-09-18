<?php
// Database configuration for all modules
define('DB_HOST', 'localhost:3307');
define('DB_NAME', 'ttm_ttm');  // Changed to match your SQL file
define('DB_USER', 'root');
define('DB_PASS', '');

// Function to get database connection
function getDBConnection($dbName = 'ttm') {
    $host = DB_HOST;
    $user = DB_USER;
    $pass = DB_PASS;
    
    // Use the main database by default
    $databaseName = ($dbName === 'ttm') ? DB_NAME : $dbName;
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$databaseName;charset=utf8mb4", 
            $user, 
            $pass
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("ERROR: Could not connect to database '$databaseName'. " . $e->getMessage());
    }
}

// Authentication helper function
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
?>