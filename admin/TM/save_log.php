<?php
// DB connection
$host = "localhost";
$user = "root";  // default XAMPP
$pass = "";
$db   = "traffic_system_db"; // database name

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get data from POST
$location = $_POST['location'];
$logDate  = $_POST['date'];
$logTime  = $_POST['time'];
$status   = $_POST['status'];
$notes    = $_POST['notes'];

// Insert into DB
$sql = "INSERT INTO traffic_logs (location, log_date, log_time, status, notes) 
        VALUES ('$location', '$logDate', '$logTime', '$status', '$notes')";

if ($conn->query($sql) === TRUE) {
    echo "success";
} else {
    echo "error: " . $conn->error;
}

$conn->close();
?>
