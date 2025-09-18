<?php
session_start();
require_once '../config/database.php'; // Database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submission for new report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_report'])) {
        $report_type = $_POST['report_type'];
        $location = $_POST['location'];
        $report_date = $_POST['report_date'];
        $report_time = $_POST['report_time'];
        $description = $_POST['description'];
        $priority_level = $_POST['priority_level'];
        $user_id = $_SESSION['user_id'];
        
        // Generate a unique report ID
        $report_id = 'RPT' . date('YmdHis') . rand(100, 999);
        
        try {
            // Handle file upload
            $image_data = null;
            $file_name = null;
            
            if (isset($_FILES['report_image']) && $_FILES['report_image']['error'] == UPLOAD_ERR_OK) {
                $image_tmp_name = $_FILES['report_image']['tmp_name'];
                $image_name = $_FILES['report_image']['name'];
                $image_size = $_FILES['report_image']['size'];
                $image_type = $_FILES['report_image']['type'];
                
                // Check if the file is an image
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                if (in_array($image_type, $allowed_types)) {
                    // Read the file content
                    $image_data = file_get_contents($image_tmp_name);
                    
                    // Also save file info for attachments table
                    $upload_dir = '../uploads/reports/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($image_name, PATHINFO_EXTENSION);
                    $file_name = $report_id . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    
                    move_uploaded_file($image_tmp_name, $file_path);
                }
            }
            
            // Insert report into database
            $stmt = $pdo->prepare("INSERT INTO reports (report_id, user_id, report_type, priority_level, location, report_date, report_time, description, image, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$report_id, $user_id, $report_type, $priority_level, $location, $report_date, $report_time, $description, $image_data]);
            
            // If file was uploaded, add to attachments table
            if ($file_name) {
                $stmt = $pdo->prepare("INSERT INTO report_attachments (report_id, file_name, file_path, file_type, file_size, uploaded_by) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$report_id, $file_name, $file_path, $image_type, $image_size, $user_id]);
            }
            
            $_SESSION['success_message'] = "Report submitted successfully! Your report ID is: " . $report_id;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error submitting report: " . $e->getMessage();
        }
    }
    
    // Handle admin status update
    if (isset($_POST['update_status']) && $_SESSION['user_role'] === 'admin') {
        $report_id = $_POST['report_id'];
        $new_status = $_POST['status'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        $admin_id = $_SESSION['user_id'];
        
        try {
            // Get current status for history
            $stmt = $pdo->prepare("SELECT status FROM reports WHERE report_id = ?");
            $stmt->execute([$report_id]);
            $current_status = $stmt->fetchColumn();
            
            // Update report status
            $stmt = $pdo->prepare("UPDATE reports SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE report_id = ?");
            $stmt->execute([$new_status, $admin_id, $report_id]);
            
            // Add to status history
            $stmt = $pdo->prepare("INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, change_reason) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$report_id, $current_status, $new_status, $admin_id, $admin_notes]);
            
            // Add to admin actions
            $stmt = $pdo->prepare("INSERT INTO admin_actions (admin_id, report_id, action_type, action_details) 
                                  VALUES (?, ?, 'status_change', ?)");
            $stmt->execute([$admin_id, $report_id, "Status changed from $current_status to $new_status. Notes: $admin_notes"]);
            
            $_SESSION['success_message'] = "Report status updated successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating report status: " . $e->getMessage();
        }
    }
}

// Get user role for conditional rendering
$user_role = $_SESSION['user_role'] ?? 'citizen';

// Get violation categories
$violation_categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM violation_categories WHERE is_active = 1 ORDER BY name");
    $violation_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching violation categories: " . $e->getMessage();
}

// Get reports based on user role
$reports = [];
try {
    if ($user_role === 'admin') {
        // Admins can see all reports
        $stmt = $pdo->prepare("SELECT r.*, u.username, u.full_name, u.contact_number, 
                              (SELECT COUNT(*) FROM report_attachments ra WHERE ra.report_id = r.report_id) as attachment_count
                              FROM reports r 
                              JOIN users u ON r.user_id = u.user_id 
                              ORDER BY r.created_at DESC");
        $stmt->execute();
    } else {
        // Regular users can only see their own reports
        $stmt = $pdo->prepare("SELECT r.*, u.username, u.full_name, u.contact_number,
                              (SELECT COUNT(*) FROM report_attachments ra WHERE ra.report_id = r.report_id) as attachment_count
                              FROM reports r 
                              JOIN users u ON r.user_id = u.user_id 
                              WHERE r.user_id = ? 
                              ORDER BY r.created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching reports: " . $e->getMessage();
}

// Get status history for reports
$status_history = [];
if (!empty($reports)) {
    $report_ids = array_column($reports, 'report_id');
    $placeholders = implode(',', array_fill(0, count($report_ids), '?'));
    
    try {
        $stmt = $pdo->prepare("SELECT rsh.*, u.username, u.full_name 
                              FROM report_status_history rsh 
                              JOIN users u ON rsh.changed_by = u.user_id 
                              WHERE rsh.report_id IN ($placeholders) 
                              ORDER BY rsh.changed_at DESC");
        $stmt->execute($report_ids);
        $history_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize by report_id
        foreach ($history_records as $record) {
            $status_history[$record['report_id']][] = $record;
        }
    } catch (PDOException $e) {
        $error = "Error fetching status history: " . $e->getMessage();
    }
}

// Get dashboard statistics
$stats = [
    'total_reports' => 0,
    'pending_reports' => 0,
    'verified_reports' => 0,
    'invalid_reports' => 0
];

try {
    // Total reports
    if ($user_role === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reports WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_reports'] = $result['count'];
    
    // Reports by status
    if ($user_role === 'admin') {
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
    } else {
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM reports WHERE user_id = ? GROUP BY status");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($status_counts as $count) {
        if ($count['status'] === 'pending') $stats['pending_reports'] = $count['count'];
        if ($count['status'] === 'verified') $stats['verified_reports'] = $count['count'];
        if ($count['status'] === 'invalid') $stats['invalid_reports'] = $count['count'];
    }
    
} catch (PDOException $e) {
    $error = "Error fetching statistics: " . $e->getMessage();
}

// Get report distribution for chart
$report_distribution = [];
try {
    if ($user_role === 'admin') {
        $stmt = $pdo->query("SELECT report_type, COUNT(*) as count FROM reports GROUP BY report_type");
    } else {
        $stmt = $pdo->prepare("SELECT report_type, COUNT(*) as count FROM reports WHERE user_id = ? GROUP BY report_type");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $report_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching report distribution: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Traffic and Transport Management - Accident & Violation Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../css/style1.css" />
    <link rel="stylesheet" href="../css/viewprofile.css" />
    <style>
        /* Accident & Violation Reports Specific Styles */
        .report-card {
            border-left: 4px solid;
            transition: all 0.3s;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .status-pending {
            border-left-color: #ffc107;
            background: linear-gradient(to right, rgba(255, 193, 7, 0.05), white);
        }
        
        .status-verified {
            border-left-color: #198754;
            background: linear-gradient(to right, rgba(25, 135, 84, 0.05), white);
        }
        
        .status-invalid {
            border-left-color: #dc3545;
            background: linear-gradient(to right, rgba(220, 53, 69, 0.05), white);
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-pending-indicator {
            background-color: #ffc107;
        }
        
        .status-verified-indicator {
            background-color: #198754;
        }
        
        .status-invalid-indicator {
            background-color: #dc3545;
        }
        
        .report-form {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 3px solid #1d3557;
        }
        
        .report-table {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .tab-content {
            padding: 20px;
            background: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-button {
            padding: 10px 20px;
            background: #f8f9fa;
            border: none;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .tab-button:hover {
            background: #e9ecef;
        }
        
        .tab-button.active {
            background: #1d3557;
            color: white;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 3px solid #1d3557;
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-card p {
            font-size: 24px;
            font-weight: bold;
            color: #1d3557;
            margin: 0;
        }

        .chart-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* Alert Styles */
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }
        
        .history-timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }
        
        .history-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #e9ecef;
        }
        
        .history-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .history-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #1d3557;
            border: 2px solid white;
        }
        
        .history-content {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .tab-buttons {
                flex-direction: column;
            }
            
            .tab-button {
                width: 100%;
                text-align: center;
            }
            
            .chart-container {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Alert Container -->
    <div class="alert-container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>

    <section id="sidebar">
        <a href="#" class="brand">
            <img
                src="../img/ttm.png"
                alt="Profile Photo"
                class="profile-avatar"
                id="profileAvatar"
                style="cursor: pointer; transition: transform 0.2s"
            />
            <span class="text">Traffic And Transport Management</span>
        </a>
        <ul class="side-menu top">
            <li>
                <a href="../index.php">
                    <i class="bx bxs-dashboard"></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <span class="separator">Main</span>
            
            <li>
                <a href="../TM/index.php">
                    <i class="bx bx-car"></i>
                    <span class="text">Traffic Monitoring</span>
                </a>
            </li>
            <li>
                <a href="../RTR/index.php">
                    <i class="bx bx-directions routing"></i>
                    <span class="text">Real-Time Road Updates</span>
                </a>
            </li>
            <li class="active">
                <a href="index.php">
                    <i class="bx bx-bell"></i>
                    <span class="text">Accident & Violation Reports</span>
                </a>
            </li>
            <li>
                <a href="../VRD/index.php">
                    <i class="bx bx-map"></i>
                    <span class="text">Vehicle Routing & Diversion</span>
                </a>
            </li>
            <li>
                <a href="../TSC/index.php">
                    <i class="bx bx-toggle-left"></i>
                    <span class="text">Traffic Signal Control</span>
                </a>
            </li>
            <li>
                <a href="../PT/index.php">
                    <i class="bx bx-train"></i>
                    <span class="text">Public Transport Sync</span>
                </a>
            </li>
            <li>
                <a href="../PTS/index.php">
                    <i class="bx bx-receipt"></i>
                    <span class="text">Permit & Ticketing System</span>
                </a>
            </li>
        </ul>
        <ul class="side-menu">
            <span class="separator">SETTINGS</span>
            <li>
                <a href="../Profile.php">
                    <i class="bx bxs-user-pin"></i>
                    <span class="text">Profile</span>
                </a>
            </li>
            <li>
                <a href="../Settings.php">
                    <i class="bx bxs-cog"></i>
                    <span class="text">Settings</span>
                </a>
            </li>
            <li>
                <a href="../logout.php" class="logout">
                    <i class="bx bxs-log-out-circle"></i>
                    <span class="text">Logout</span>
                </a>
            </li>
        </ul>
    </section>

    <section id="content">
        <nav>
            <a href="#" class="nav-link">Categories</a>
            <form action="#">
                <div class="form-input">
                    <input type="search" placeholder="Search..." />
                    <button type="submit" class="search-btn">
                        <i class="bx bx-search"></i>
                    </button>
                </div>
            </form>
            <a href="#" class="notification">
                <i class="bx bxs-bell"></i>
                <span class="num">9+</span>
            </a>
            <div class="profile-dropdown">
                <a href="#" class="profile" id="profile-btn">
                    <img src="/placeholder.svg?height=40&width=40" />
                </a>
                <div class="dropdown-menu" id="dropdown-menu">
                    <a href="../Profile.php">Profile</a>
                    <a href="../Settings.php">Settings</a>
                </div>
            </div>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Accident & Violation Reports</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="../index.php">Dashboard</a>
                        </li>
                        <li><i class="bx bx-chevron-right"></i></li>
                        <li>
                            <a class="active" href="#">Accident & Violation Reports</a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab(event, 'dashboard')">
                    <i class="bx bxs-dashboard"></i> Dashboard
                </button>
                <button class="tab-button" onclick="openTab(event, 'submit_report')">
                    <i class="bx bx-edit"></i> Submit Report
                </button>
                <button class="tab-button" onclick="openTab(event, 'report_history')">
                    <i class="bx bx-history"></i> Report History
                </button>
                <?php if ($user_role === 'admin'): ?>
                <button class="tab-button" onclick="openTab(event, 'admin_review')">
                    <i class="bx bx-check-shield"></i> Admin Review
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content" style="display: block;">
                <div class="dashboard-overview">
                    <h2>Accident & Violation Reports Dashboard</h2>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <h3>Total Reports</h3>
                            <p id="totalReports"><?php echo $stats['total_reports']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Pending Review</h3>
                            <p id="pendingReports"><?php echo $stats['pending_reports']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Verified Reports</h3>
                            <p id="verifiedReports"><?php echo $stats['verified_reports']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Invalid Reports</h3>
                            <p id="invalidReports"><?php echo $stats['invalid_reports']; ?></p>
                        </div>
                    </div>

                    <div class="chart-container">
                        <div class="chart">
                            <h3>Report Type Distribution</h3>
                            <canvas id="reportTypeChart"></canvas>
                        </div>
                        <div class="chart">
                            <h3>Status Distribution</h3>
                            <canvas id="statusDistributionChart"></canvas>
                        </div>
                    </div>

                    <div class="response-time">
                        <h3>Recent Activity</h3>
                        <p>Your reporting activity over time</p>
                        <div style="width: 80%; margin: 0 auto">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submit Report Tab -->
            <div id="submit_report" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="report-form">
                            <h4><i class="bx bx-edit"></i> Submit New Report</h4>
                            <form id="reportForm" method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Report Type *</label>
                                    <select class="form-select" name="report_type" required>
                                        <option value="">Select report type</option>
                                        <?php foreach ($violation_categories as $category): ?>
                                            <option value="<?php echo $category['name']; ?>"><?php echo $category['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Location *</label>
                                    <input type="text" class="form-control" name="location" placeholder="Enter location or intersection" required>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Date *</label>
                                        <input type="date" class="form-control" name="report_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Time *</label>
                                        <input type="time" class="form-control" name="report_time" value="<?php echo date('H:i'); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Priority Level *</label>
                                    <select class="form-select" name="priority_level" required>
                                        <option value="">Select priority level</option>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description *</label>
                                    <textarea class="form-control" name="description" rows="4" placeholder="Please provide a detailed description of the incident or violation" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Upload Image (Optional)</label>
                                    <input type="file" class="form-control" name="report_image" id="reportImage" accept="image/*">
                                    <div class="form-text">Supported formats: JPG, PNG, GIF. Max size: 5MB</div>
                                    <img id="imagePreview" class="image-preview" src="#" alt="Image preview">
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle"></i> Your report will be reviewed by our team. You can track its status in the Report History tab.
                                </div>
                                
                                <button type="submit" name="submit_report" class="btn btn-primary">
                                    <i class="bx bx-paper-plane"></i> Submit Report
                                </button>
                                <button type="reset" class="btn btn-outline-secondary ms-2">
                                    <i class="bx bx-reset"></i> Reset Form
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Report History Tab -->
            <div id="report_history" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="report-form">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                <h4><i class="bx bx-history"></i> Your Report History</h4>
                                <div class="mt-2 mt-md-0">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Search reports..." id="searchReports">
                                        <button class="btn btn-outline-secondary" type="button" onclick="filterReports()">
                                            <i class="bx bx-filter"></i> Filter
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (empty($reports)): ?>
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle"></i> You haven't submitted any reports yet.
                                </div>
                            <?php else: ?>
                                <div class="report-table">
                                    <?php foreach ($reports as $report): ?>
                                        <div class="report-card p-3 status-<?php echo $report['status']; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h5><?php echo htmlspecialchars($report['report_type']); ?> 
                                                        <span class="badge bg-<?php 
                                                            if ($report['status'] === 'pending') echo 'warning';
                                                            elseif ($report['status'] === 'verified') echo 'success';
                                                            else echo 'danger';
                                                        ?> ms-2">
                                                            <?php echo ucfirst($report['status']); ?>
                                                        </span>
                                                    </h5>
                                                    <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($report['location']); ?></p>
                                                    <p class="mb-1"><strong>Date/Time:</strong> <?php echo $report['report_date'] . ' ' . $report['report_time']; ?></p>
                                                    <p class="mb-1"><strong>Priority:</strong> 
                                                        <span class="badge bg-<?php 
                                                            if ($report['priority_level'] === 'low') echo 'secondary';
                                                            elseif ($report['priority_level'] === 'medium') echo 'info';
                                                            elseif ($report['priority_level'] === 'high') echo 'warning';
                                                            else echo 'danger';
                                                        ?>">
                                                            <?php echo ucfirst($report['priority_level']); ?>
                                                        </span>
                                                    </p>
                                                    <p class="mb-2"><strong>Description:</strong> <?php echo htmlspecialchars($report['description']); ?></p>
                                                    
                                                    <?php if ($report['attachment_count'] > 0): ?>
                                                        <p class="mb-2">
                                                            <i class="bx bx-paperclip"></i> 
                                                            <a href="#" onclick="viewAttachments('<?php echo $report['report_id']; ?>')">View Attachments (<?php echo $report['attachment_count']; ?>)</a>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <small class="text-muted">Report ID: <?php echo $report['report_id']; ?> | Submitted: <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></small>
                                                </div>
                                                <div class="ms-3">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewReportDetails('<?php echo $report['report_id']; ?>')">
                                                        <i class="bx bx-show"></i> Details
                                                    </button>
                                                    <?php if ($report['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-secondary mt-1" onclick="editReport('<?php echo $report['report_id']; ?>')">
                                                            <i class="bx bx-edit"></i> Edit
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Status History -->
                                            <?php if (isset($status_history[$report['report_id']])): ?>
                                            <div class="history-timeline mt-3">
                                                <?php foreach ($status_history[$report['report_id']] as $history): ?>
                                                <div class="history-item">
                                                    <div class="history-content">
                                                        <strong><?php echo $history['full_name']; ?></strong> changed status from 
                                                        <span class="badge bg-<?php 
                                                            if ($history['old_status'] === 'pending') echo 'warning';
                                                            elseif ($history['old_status'] === 'verified') echo 'success';
                                                            else echo 'danger';
                                                        ?>"><?php echo ucfirst($history['old_status']); ?></span> to 
                                                        <span class="badge bg-<?php 
                                                            if ($history['new_status'] === 'pending') echo 'warning';
                                                            elseif ($history['new_status'] === 'verified') echo 'success';
                                                            else echo 'danger';
                                                        ?>"><?php echo ucfirst($history['new_status']); ?></span>
                                                        <br>
                                                        <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($history['changed_at'])); ?></small>
                                                        <?php if (!empty($history['change_reason'])): ?>
                                                            <p class="mt-1 mb-0"><strong>Reason:</strong> <?php echo htmlspecialchars($history['change_reason']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Admin Review Tab -->
            <?php if ($user_role === 'admin'): ?>
            <div id="admin_review" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="report-form">
                            <h4><i class="bx bx-check-shield"></i> Admin Review Panel</h4>
                            
                            <ul class="nav nav-tabs" id="adminTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                                        Pending Review <span class="badge bg-warning"><?php echo $stats['pending_reports']; ?></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="verified-tab" data-bs-toggle="tab" data-bs-target="#verified" type="button" role="tab" aria-controls="verified" aria-selected="false">
                                        Verified Reports <span class="badge bg-success"><?php echo $stats['verified_reports']; ?></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="invalid-tab" data-bs-toggle="tab" data-bs-target="#invalid" type="button" role="tab" aria-controls="invalid" aria-selected="false">
                                        Invalid Reports <span class="badge bg-danger"><?php echo $stats['invalid_reports']; ?></span>
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content p-3 border border-top-0" id="adminTabContent">
                                <!-- Pending Reports Tab -->
                                <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                                    <?php 
                                    $pending_reports = array_filter($reports, function($report) {
                                        return $report['status'] === 'pending';
                                    });
                                    ?>
                                    
                                    <?php if (empty($pending_reports)): ?>
                                        <div class="alert alert-info">
                                            <i class="bx bx-info-circle"></i> No pending reports to review.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($pending_reports as $report): ?>
                                            <div class="report-card p-3 status-pending mb-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h5><?php echo htmlspecialchars($report['report_type']); ?></h5>
                                                        <p class="mb-1"><strong>Reported by:</strong> <?php echo htmlspecialchars($report['full_name']); ?> (<?php echo htmlspecialchars($report['username']); ?>)</p>
                                                        <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($report['contact_number']); ?></p>
                                                        <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($report['location']); ?></p>
                                                        <p class="mb-1"><strong>Date/Time:</strong> <?php echo $report['report_date'] . ' ' . $report['report_time']; ?></p>
                                                        <p class="mb-1"><strong>Priority:</strong> 
                                                            <span class="badge bg-<?php 
                                                                if ($report['priority_level'] === 'low') echo 'secondary';
                                                                elseif ($report['priority_level'] === 'medium') echo 'info';
                                                                elseif ($report['priority_level'] === 'high') echo 'warning';
                                                                else echo 'danger';
                                                            ?>">
                                                                <?php echo ucfirst($report['priority_level']); ?>
                                                            </span>
                                                        </p>
                                                        <p class="mb-2"><strong>Description:</strong> <?php echo htmlspecialchars($report['description']); ?></p>
                                                        
                                                        <?php if ($report['attachment_count'] > 0): ?>
                                                            <p class="mb-2">
                                                                <i class="bx bx-paperclip"></i> 
                                                                <a href="#" onclick="viewAttachments('<?php echo $report['report_id']; ?>')">View Attachments (<?php echo $report['attachment_count']; ?>)</a>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <small class="text-muted">Report ID: <?php echo $report['report_id']; ?> | Submitted: <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></small>
                                                    </div>
                                                    <div class="ms-3">
                                                        <button class="btn btn-sm btn-success" onclick="updateReportStatus('<?php echo $report['report_id']; ?>', 'verified')">
                                                            <i class="bx bx-check"></i> Verify
                                                        </button>
                                                        <button class="btn btn-sm btn-danger mt-1" onclick="updateReportStatus('<?php echo $report['report_id']; ?>', 'invalid')">
                                                            <i class="bx bx-x"></i> Reject
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-primary mt-1" onclick="viewReportDetails('<?php echo $report['report_id']; ?>')">
                                                            <i class="bx bx-show"></i> Details
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Verified Reports Tab -->
                                <div class="tab-pane fade" id="verified" role="tabpanel" aria-labelledby="verified-tab">
                                    <?php 
                                    $verified_reports = array_filter($reports, function($report) {
                                        return $report['status'] === 'verified';
                                    });
                                    ?>
                                    
                                    <?php if (empty($verified_reports)): ?>
                                        <div class="alert alert-info">
                                            <i class="bx bx-info-circle"></i> No verified reports.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($verified_reports as $report): ?>
                                            <div class="report-card p-3 status-verified mb-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h5><?php echo htmlspecialchars($report['report_type']); ?></h5>
                                                        <p class="mb-1"><strong>Reported by:</strong> <?php echo htmlspecialchars($report['full_name']); ?> (<?php echo htmlspecialchars($report['username']); ?>)</p>
                                                        <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($report['contact_number']); ?></p>
                                                        <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($report['location']); ?></p>
                                                        <p class="mb-1"><strong>Date/Time:</strong> <?php echo $report['report_date'] . ' ' . $report['report_time']; ?></p>
                                                        <p class="mb-1"><strong>Priority:</strong> 
                                                            <span class="badge bg-<?php 
                                                                if ($report['priority_level'] === 'low') echo 'secondary';
                                                                elseif ($report['priority_level'] === 'medium') echo 'info';
                                                                elseif ($report['priority_level'] === 'high') echo 'warning';
                                                                else echo 'danger';
                                                            ?>">
                                                                <?php echo ucfirst($report['priority_level']); ?>
                                                            </span>
                                                        </p>
                                                        <p class="mb-2"><strong>Description:</strong> <?php echo htmlspecialchars($report['description']); ?></p>
                                                        
                                                        <?php if ($report['attachment_count'] > 0): ?>
                                                            <p class="mb-2">
                                                                <i class="bx bx-paperclip"></i> 
                                                                <a href="#" onclick="viewAttachments('<?php echo $report['report_id']; ?>')">View Attachments (<?php echo $report['attachment_count']; ?>)</a>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <small class="text-muted">Report ID: <?php echo $report['report_id']; ?> | Submitted: <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></small>
                                                    </div>
                                                    <div class="ms-3">
                                                        <button class="btn btn-sm btn-danger" onclick="updateReportStatus('<?php echo $report['report_id']; ?>', 'invalid')">
                                                            <i class="bx bx-x"></i> Mark Invalid
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-primary mt-1" onclick="viewReportDetails('<?php echo $report['report_id']; ?>')">
                                                            <i class="bx bx-show"></i> Details
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Status History -->
                                                <?php if (isset($status_history[$report['report_id']])): ?>
                                                <div class="history-timeline mt-3">
                                                    <?php foreach ($status_history[$report['report_id']] as $history): ?>
                                                    <div class="history-item">
                                                        <div class="history-content">
                                                            <strong><?php echo $history['full_name']; ?></strong> changed status from 
                                                            <span class="badge bg-<?php 
                                                                if ($history['old_status'] === 'pending') echo 'warning';
                                                                elseif ($history['old_status'] === 'verified') echo 'success';
                                                                else echo 'danger';
                                                            ?>"><?php echo ucfirst($history['old_status']); ?></span> to 
                                                            <span class="badge bg-<?php 
                                                                if ($history['new_status'] === 'pending') echo 'warning';
                                                                elseif ($history['new_status'] === 'verified') echo 'success';
                                                                else echo 'danger';
                                                            ?>"><?php echo ucfirst($history['new_status']); ?></span>
                                                            <br>
                                                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($history['changed_at'])); ?></small>
                                                            <?php if (!empty($history['change_reason'])): ?>
                                                                <p class="mt-1 mb-0"><strong>Reason:</strong> <?php echo htmlspecialchars($history['change_reason']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Invalid Reports Tab -->
                                <div class="tab-pane fade" id="invalid" role="tabpanel" aria-labelledby="invalid-tab">
                                    <?php 
                                    $invalid_reports = array_filter($reports, function($report) {
                                        return $report['status'] === 'invalid';
                                    });
                                    ?>
                                    
                                    <?php if (empty($invalid_reports)): ?>
                                        <div class="alert alert-info">
                                            <i class="bx bx-info-circle"></i> No invalid reports.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($invalid_reports as $report): ?>
                                            <div class="report-card p-3 status-invalid mb-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h5><?php echo htmlspecialchars($report['report_type']); ?></h5>
                                                        <p class="mb-1"><strong>Reported by:</strong> <?php echo htmlspecialchars($report['full_name']); ?> (<?php echo htmlspecialchars($report['username']); ?>)</p>
                                                        <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($report['contact_number']); ?></p>
                                                        <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($report['location']); ?></p>
                                                        <p class="mb-1"><strong>Date/Time:</strong> <?php echo $report['report_date'] . ' ' . $report['report_time']; ?></p>
                                                        <p class="mb-1"><strong>Priority:</strong> 
                                                            <span class="badge bg-<?php 
                                                                if ($report['priority_level'] === 'low') echo 'secondary';
                                                                elseif ($report['priority_level'] === 'medium') echo 'info';
                                                                elseif ($report['priority_level'] === 'high') echo 'warning';
                                                                else echo 'danger';
                                                            ?>">
                                                                <?php echo ucfirst($report['priority_level']); ?>
                                                            </span>
                                                        </p>
                                                        <p class="mb-2"><strong>Description:</strong> <?php echo htmlspecialchars($report['description']); ?></p>
                                                        
                                                        <?php if ($report['attachment_count'] > 0): ?>
                                                            <p class="mb-2">
                                                                <i class="bx bx-paperclip"></i> 
                                                                <a href="#" onclick="viewAttachments('<?php echo $report['report_id']; ?>')">View Attachments (<?php echo $report['attachment_count']; ?>)</a>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <small class="text-muted">Report ID: <?php echo $report['report_id']; ?> | Submitted: <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></small>
                                                    </div>
                                                    <div class="ms-3">
                                                        <button class="btn btn-sm btn-success" onclick="updateReportStatus('<?php echo $report['report_id']; ?>', 'verified')">
                                                            <i class="bx bx-check"></i> Mark Verified
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-primary mt-1" onclick="viewReportDetails('<?php echo $report['report_id']; ?>')">
                                                            <i class="bx bx-show"></i> Details
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Status History -->
                                                <?php if (isset($status_history[$report['report_id']])): ?>
                                                <div class="history-timeline mt-3">
                                                    <?php foreach ($status_history[$report['report_id']] as $history): ?>
                                                    <div class="history-item">
                                                        <div class="history-content">
                                                            <strong><?php echo $history['full_name']; ?></strong> changed status from 
                                                            <span class="badge bg-<?php 
                                                                if ($history['old_status'] === 'pending') echo 'warning';
                                                                elseif ($history['old_status'] === 'verified') echo 'success';
                                                                else echo 'danger';
                                                            ?>"><?php echo ucfirst($history['old_status']); ?></span> to 
                                                            <span class="badge bg-<?php 
                                                                if ($history['new_status'] === 'pending') echo 'warning';
                                                                elseif ($history['new_status'] === 'verified') echo 'success';
                                                                else echo 'danger';
                                                            ?>"><?php echo ucfirst($history['new_status']); ?></span>
                                                            <br>
                                                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($history['changed_at'])); ?></small>
                                                            <?php if (!empty($history['change_reason'])): ?>
                                                                <p class="mt-1 mb-0"><strong>Reason:</strong> <?php echo htmlspecialchars($history['change_reason']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </section>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Update Report Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="statusForm">
                    <div class="modal-body">
                        <input type="hidden" name="report_id" id="reportId">
                        <input type="hidden" name="update_status" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="statusSelect" required>
                                <option value="pending">Pending</option>
                                <option value="verified">Verified</option>
                                <option value="invalid">Invalid</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="admin_notes" rows="3" placeholder="Add any notes about this status change"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Report Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="reportDetails">
                    <!-- Details will be loaded here via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Attachments Modal -->
    <div class="modal fade" id="attachmentsModal" tabindex="-1" aria-labelledby="attachmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attachmentsModalLabel">Report Attachments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="attachmentsContent">
                    <!-- Attachments will be loaded here via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Tab navigation
        function openTab(evt, tabName) {
            var i, tabcontent, tabbuttons;
            
            // Hide all tab content
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            
            // Remove active class from all buttons
            tabbuttons = document.getElementsByClassName("tab-button");
            for (i = 0; i < tabbuttons.length; i++) {
                tabbuttons[i].className = tabbuttons[i].className.replace(" active", "");
            }
            
            // Show the specific tab content and add active class to the button
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
            
            // If dashboard tab is opened, refresh charts
            if (tabName === 'dashboard') {
                renderCharts();
            }
        }
        
        // Image preview for file upload
        document.getElementById('reportImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Update report status (admin function)
        function updateReportStatus(reportId, status) {
            document.getElementById('reportId').value = reportId;
            document.getElementById('statusSelect').value = status;
            
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }
        
        // View report details
        function viewReportDetails(reportId) {
            // In a real application, this would fetch data via AJAX
            // For this example, we'll just show a placeholder
            document.getElementById('reportDetails').innerHTML = `
                <div class="text-center">
                    <p>Loading report details for ID: ${reportId}</p>
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            modal.show();
            
            // Simulate AJAX call
            setTimeout(() => {
                document.getElementById('reportDetails').innerHTML = `
                    <h4>Report Details</h4>
                    <p><strong>Report ID:</strong> ${reportId}</p>
                    <p><strong>Type:</strong> Traffic Violation</p>
                    <p><strong>Location:</strong> Main Street & 1st Avenue</p>
                    <p><strong>Date/Time:</strong> ${new Date().toLocaleString()}</p>
                    <p><strong>Status:</strong> <span class="badge bg-warning">Pending</span></p>
                    <p><strong>Priority:</strong> <span class="badge bg-danger">Critical</span></p>
                    <p><strong>Description:</strong> This is a detailed description of the incident or violation that was reported.</p>
                    <hr>
                    <h5>Status History</h5>
                    <ul class="list-group">
                        <li class="list-group-item">
                            <strong>Submitted</strong> - ${new Date(Date.now() - 86400000).toLocaleString()} <br>
                            <small class="text-muted">Report was submitted by user</small>
                        </li>
                    </ul>
                `;
            }, 1000);
        }
        
        // View attachments
        function viewAttachments(reportId) {
            document.getElementById('attachmentsContent').innerHTML = `
                <div class="text-center">
                    <p>Loading attachments for report ID: ${reportId}</p>
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('attachmentsModal'));
            modal.show();
            
            // Simulate AJAX call
            setTimeout(() => {
                document.getElementById('attachmentsContent').innerHTML = `
                    <h5>Attachments for Report: ${reportId}</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <img src="https://via.placeholder.com/300x200?text=Violation+Image" class="card-img-top" alt="Report Image">
                                <div class="card-body">
                                    <h6 class="card-title">violation_image.jpg</h6>
                                    <p class="card-text"><small class="text-muted">Uploaded: ${new Date().toLocaleDateString()}</small></p>
                                    <a href="#" class="btn btn-sm btn-primary">Download</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bx bx-file" style="font-size: 3rem;"></i>
                                    <h6 class="card-title mt-2">additional_evidence.pdf</h6>
                                    <p class="card-text"><small class="text-muted">Uploaded: ${new Date().toLocaleDateString()}</small></p>
                                    <a href="#" class="btn btn-sm btn-primary">Download</a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }, 1000);
        }
        
        // Filter reports
        function filterReports() {
            const searchTerm = document.getElementById('searchReports').value.toLowerCase();
            const reportCards = document.querySelectorAll('.report-card');
            
            reportCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Render charts for dashboard
        function renderCharts() {
            // Report Type Distribution Chart
            const typeCtx = document.getElementById('reportTypeChart').getContext('2d');
            const typeChart = new Chart(typeCtx, {
                type: 'pie',
                data: {
                    labels: ['Speeding', 'Red Light', 'Illegal Parking', 'Accident', 'Other'],
                    datasets: [{
                        data: [35, 25, 20, 15, 5],
                        backgroundColor: [
                            '#1d3557',
                            '#457b9d',
                            '#a8dadc',
                            '#e63946',
                            '#f1faee'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Status Distribution Chart
            const statusCtx = document.getElementById('statusDistributionChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Verified', 'Invalid'],
                    datasets: [{
                        data: [<?php echo $stats['pending_reports']; ?>, <?php echo $stats['verified_reports']; ?>, <?php echo $stats['invalid_reports']; ?>],
                        backgroundColor: [
                            '#ffc107',
                            '#198754',
                            '#dc3545'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Activity Chart
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            const activityChart = new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Reports Submitted',
                        data: [12, 19, 8, 15, 10, 17],
                        borderColor: '#1d3557',
                        tension: 0.1,
                        fill: true,
                        backgroundColor: 'rgba(29, 53, 87, 0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize charts if on dashboard
            if (document.getElementById('dashboard').style.display === 'block') {
                renderCharts();
            }
            
            // Initialize Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>