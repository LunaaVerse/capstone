<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "traffic_system_db";

$conn = new mysqli($host, $user, $pass, $db);

$result = $conn->query("SELECT * FROM traffic_logs ORDER BY created_at DESC LIMIT 10");

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

echo json_encode($logs);
$conn->close();
?>
