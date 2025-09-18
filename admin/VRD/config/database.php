<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ttm_ttm');  // Changed from 'ttm' to 'ttm_ttm'
define('DB_USER', 'ttm_ttm');
define('DB_PASS', 'Admin123');

// Establish database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>