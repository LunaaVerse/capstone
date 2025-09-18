<?php
// Database configuration for all modules
$databases = [
    'ttm_ttm' => [
        'host' => 'localhost:3307',
        'name' => 'ttm_ttm',
        'user' => 'root',
        'pass' => ''
    ],
    
];

// Function to get database connection
function getDBConnection($dbName) {
    global $databases;
    
    if (!isset($databases[$dbName])) {
        throw new Exception("Database configuration for '$dbName' not found");
    }
    
    $dbConfig = $databases[$dbName];
    
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4", 
            $dbConfig['user'], 
            $dbConfig['pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("ERROR: Could not connect to database '$dbName'. " . $e->getMessage());
    }
}

// For backward compatibility
define('DB_HOST', 'localhost:3307');
define('DB_NAME', 'ttm_ttm');
define('DB_USER', 'root');
define('DB_PASS', '');
?>