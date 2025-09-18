<?php
// Database configuration for TTM
define('DB_HOST', 'localhost');
define('DB_USER', 'ttm_ttm'); // Changed from DB_USERNAME to DB_USER
define('DB_PASS', 'Admin123'); // Changed from DB_PASSWORD to DB_PASS
define('DB_NAME', 'ttm_ttm'); 
define('DB_SERVER', 'localhost'); // For backward compatibility

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Set charset to utf8mb4 for proper encoding
mysqli_set_charset($conn, "utf8mb4");

// Create PDO connection for other scripts that need it
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("ERROR: Could not connect to database. " . $e->getMessage());
}
?>