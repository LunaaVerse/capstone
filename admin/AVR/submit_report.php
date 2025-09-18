<?php
// submit_report.php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = "You must be logged in to submit a report.";
        header("Location: index.php");
        exit();
    }
    
    try {
        $pdo = getDBConnection('ttm_ttm');
        
        // Handle image upload
        $imageData = null;
        if (isset($_FILES['report_image']) && $_FILES['report_image']['error'] === UPLOAD_ERR_OK) {
            $imageData = file_get_contents($_FILES['report_image']['tmp_name']);
        }
        
        // Insert report
        $stmt = $pdo->prepare("INSERT INTO reports 
            (user_id, report_type, priority_level, location, report_date, report_time, description, image, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['report_type'],
            $_POST['priority_level'],
            $_POST['location'],
            $_POST['report_date'],
            $_POST['report_time'],
            $_POST['description'],
            $imageData
        ]);
        
        $_SESSION['success_message'] = "Report submitted successfully!";
        header("Location: index.php");
        exit();
        
    } catch(Exception $e) {
        $_SESSION['error_message'] = "Error submitting report: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>