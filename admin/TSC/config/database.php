<?php
// Database configuration for all modules
$databases = [
    'ttm' => [
        'host' => 'localhost:3307',
        'name' => 'ttm',
        'user' => 'root',
        'pass' => ''
    ],
    'avr' => [
        'host' => 'localhost:3307',
        'name' => 'avr',
        'user' => 'root',
        'pass' => ''
    ],
    'pts' => [
        'host' => 'localhost:3307',
        'name' => 'pats',
        'user' => 'root',
        'pass' => ''
    ],
    'pt' => [
        'host' => 'localhost:3307',
        'name' => 'pts',
        'user' => 'root',
        'pass' => ''
    ],
    'rtr' => [
        'host' => 'localhost:3307',
        'name' => 'rtr',
        'user' => 'root',
        'pass' => ''
    ],
    'tm' => [
        'host' => 'localhost:3307',
        'name' => 'tm',
        'user' => 'root',
        'pass' => ''
    ],
    'tsc' => [
        'host' => 'localhost:3307',
        'name' => 'tsc',
        'user' => 'root',
        'pass' => ''
    ],
    'vrd' => [
        'host' => 'localhost:3307',
        'name' => 'vrd',
        'user' => 'root',
        'pass' => ''
    ]
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
define('DB_NAME', 'ttm');
define('DB_USER', 'root');
define('DB_PASS', '');
?>