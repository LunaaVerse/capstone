<?php
// delete_log.php
$servername = "localhost";
$username = "root"; // default sa XAMPP
$password = "";     // default walang password
$dbname = "traffic_system"; // palitan mo kung iba pangalan ng DB mo

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['id'])) {
  $id = intval($_POST['id']);
  $sql = "DELETE FROM traffic_logs WHERE id = $id";

  if ($conn->query($sql) === TRUE) {
    echo "success";
  } else {
    echo "Error: " . $conn->error;
  }
} else {
  echo "No ID provided";
}

$conn->close();
?>
