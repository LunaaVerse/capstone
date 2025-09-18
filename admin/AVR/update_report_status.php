<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    // Generate a unique report ID
    $report_id = 'RPT' . date('YmdHis') . rand(100, 999);
    
    // Get form data
    $user_id = $_SESSION['user_id'];
    $report_type = $_POST['report_type'] ?? '';
    $priority_level = $_POST['priority_level'] ?? '';
    $location = $_POST['location'] ?? '';
    $report_date = $_POST['report_date'] ?? '';
    $report_time = $_POST['report_time'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Handle image upload
    $image = null;
    if (isset($_FILES['report_image']) && $_FILES['report_image']['error'] === UPLOAD_ERR_OK) {
        $image = file_get_contents($_FILES['report_image']['tmp_name']);
    }
    
    try {
        // Insert report into database
        $stmt = $pdo->prepare("INSERT INTO reports 
            (report_id, user_id, report_type, priority_level, location, report_date, report_time, description, image, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        $stmt->execute([
            $report_id, 
            $user_id, 
            $report_type, 
            $priority_level, 
            $location, 
            $report_date, 
            $report_time, 
            $description, 
            $image
        ]);
        
        $_SESSION['success_message'] = "Report submitted successfully! Your report ID is: $report_id";
        header("Location: index.php");
        exit();
        
    } catch(PDOException $e) {
        $error = "Error submitting report: " . $e->getMessage();
        $_SESSION['error_message'] = $error;
        header("Location: index.php");
        exit();
    }
}
?>