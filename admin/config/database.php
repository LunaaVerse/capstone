<?php
// Database configuration
define('DB_HOST', 'localhost:3307');
define('DB_NAME', 'ttm');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");
?>