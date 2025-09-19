<?php
session_start();
require_once 'config/database.php'; // Database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Database connection
try {
    $pdo = getDBConnection();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'citizen';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apply_permit'])) {
        $full_name = $_POST['full_name'];
        $event_purpose = $_POST['event_purpose'];
        $location = $_POST['location'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $notes = $_POST['notes'] ?? '';
        
        // Generate permit ID
        $permit_id = 'PER' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO permits (permit_id, user_id, full_name, event_purpose, location, start_date, end_date, notes, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$permit_id, $user_id, $full_name, $event_purpose, $location, $start_date, $end_date, $notes]);
            
            $_SESSION['success_message'] = "Permit application submitted successfully! Your permit ID: $permit_id";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error submitting permit application: " . $e->getMessage();
        }
    }
    
    // Admin actions
    if (isset($_POST['update_status']) && in_array($user_role, ['admin', 'officer'])) {
        $permit_id = $_POST['permit_id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE permits SET status = ?, updated_at = NOW() WHERE permit_id = ?");
            $stmt->execute([$status, $permit_id]);
            
            $_SESSION['success_message'] = "Permit status updated successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating permit status: " . $e->getMessage();
        }
    }
}

// Get user permits
$user_permits = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM permits WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $user_permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching user permits: " . $e->getMessage();
}

// Get all permits for admin view
$all_permits = [];
if (in_array($user_role, ['admin', 'officer'])) {
    try {
        $stmt = $pdo->query("SELECT p.*, u.username, u.email 
                            FROM permits p 
                            JOIN users u ON p.user_id = u.user_id 
                            ORDER BY p.created_at DESC");
        $all_permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching all permits: " . $e->getMessage();
    }
}

// Get statistics for dashboard
$stats = [
    'total_permits' => 0,
    'pending_permits' => 0,
    'approved_permits' => 0,
    'rejected_permits' => 0
];

try {
    // Total permits
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM permits WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_permits'] = $result['count'];
    
    // Pending permits
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM permits WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_permits'] = $result['count'];
    
    // Approved permits
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM permits WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['approved_permits'] = $result['count'];
    
    // Rejected permits
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM permits WHERE user_id = ? AND status = 'rejected'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['rejected_permits'] = $result['count'];
    
} catch (PDOException $e) {
    $error = "Error fetching statistics: " . $e->getMessage();
}

// Get status distribution for chart
$status_distribution = [];
if (in_array($user_role, ['admin', 'officer'])) {
    try {
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM permits GROUP BY status");
        $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching status distribution: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Traffic and Transport Management - Permit & Ticketing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../css/style1.css" />
    <link rel="stylesheet" href="../../css/viewprofile.css" />
    <style>
        /* Permit & Ticketing System Specific Styles */
        .permit-card {
            border-left: 4px solid;
            transition: all 0.3s;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .permit-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .status-pending {
            border-left-color: #ffc107;
            background: linear-gradient(to right, rgba(255, 193, 7, 0.05), white);
        }
        
        .status-approved {
            border-left-color: #198754;
            background: linear-gradient(to right, rgba(25, 135, 84, 0.05), white);
        }
        
        .status-rejected {
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
        
        .status-approved-indicator {
            background-color: #198754;
        }
        
        .status-rejected-indicator {
            background-color: #dc3545;
        }
        
        .permit-form {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 3px solid #1d3557;
        }
        
        .permit-table {
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
        
        .permit-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: white;
            font-weight: bold;
            font-size: 12px;
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
                src="../../img/ttm.png"
                alt="Profile Photo"
                class="profile-avatar"
                id="profileAvatar"
                style="cursor: pointer; transition: transform 0.2s"
            />
            <span class="text">Traffic And Transport Management</span>
        </a>
        <ul class="side-menu top">
            <li>
                <a href="../../index.php">
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
            <li>
                <a href="../AVR/index.php">
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
            <li class="active">
                <a href="../PTS/index.php">
                    <i class="bx bx-receipt"></i>
                    <span class="text">Permit & Ticketing System</span>
                </a>
            </li>
        </ul>
        <ul class="side-menu">
            <span class="separator">SETTINGS</span>
            <li>
                <a href="../../Profile.php">
                    <i class="bx bxs-user-pin"></i>
                    <span class="text">Profile</span>
                </a>
            </li>
            <li>
                <a href="../../Settings.php">
                    <i class="bx bxs-cog"></i>
                    <span class="text">Settings</span>
                </a>
            </li>
            <li>
                <a href="../../logout.php" class="logout">
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
                    <a href="../../Profile.php">Profile</a>
                    <a href="../../Settings.php">Settings</a>
                </div>
            </div>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Permit & Ticketing System</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Dashboard</a>
                        </li>
                        <li><i class="bx bx-chevron-right"></i></li>
                        <li>
                            <a class="active" href="#">Permit & Ticketing System</a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab(event, 'dashboard')">
                    <i class="bx bxs-dashboard"></i> Dashboard
                </button>
                <button class="tab-button" onclick="openTab(event, 'apply_permit')">
                    <i class="bx bx-edit"></i> Apply for Permit
                </button>
                <button class="tab-button" onclick="openTab(event, 'permits')">
                    <i class="bx bx-file"></i> Permits
                </button>
                <?php if (in_array($user_role, ['admin', 'officer'])): ?>
                <button class="tab-button" onclick="openTab(event, 'manage_permits')">
                    <i class="bx bx-cog"></i> Manage Permits
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content" style="display: block;">
                <div class="dashboard-overview">
                    <h2>Permit & Ticketing System Dashboard</h2>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <h3>Total Permits</h3>
                            <p id="totalPermits"><?php echo $stats['total_permits']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Pending Permits</h3>
                            <p id="pendingPermits"><?php echo $stats['pending_permits']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Approved Permits</h3>
                            <p id="approvedPermits"><?php echo $stats['approved_permits']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Rejected Permits</h3>
                            <p id="rejectedPermits"><?php echo $stats['rejected_permits']; ?></p>
                        </div>
                    </div>

                    <?php if (in_array($user_role, ['admin', 'officer'])): ?>
                    <div class="chart-container">
                        <div class="chart">
                            <h3>Permit Status Distribution</h3>
                            <canvas id="statusDistributionChart"></canvas>
                        </div>
                        <div class="chart">
                            <h3>Monthly Permit Applications</h3>
                            <canvas id="monthlyApplicationsChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="response-time">
                        <h3>Quick Actions</h3>
                        <div class="d-flex justify-content-center gap-3 mt-4 flex-wrap">
                            <button class="btn btn-primary" onclick="openTab(event, 'apply_permit')">
                                <i class="bx bx-plus"></i> Apply for New Permit
                            </button>
                            <button class="btn btn-outline-primary" onclick="openTab(event, 'permits')">
                                <i class="bx bx-list-ul"></i> View Permits
                            </button>
                            <?php if (in_array($user_role, ['admin', 'officer'])): ?>
                            <button class="btn btn-outline-info" onclick="openTab(event, 'manage_permits')">
                                <i class="bx bx-cog"></i> Manage All Permits
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Apply for Permit Tab -->
            <div id="apply_permit" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="permit-form">
                            <h4><i class="bx bx-edit"></i> Apply for New Permit</h4>
                            <form id="permitApplicationForm" method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" placeholder="Enter your full name" required>
                                </div>
                                
                                <div class mb-3">
                                    <label class="form-label">Event Purpose *</label>
                                    <input type="text" class="form-control" name="event_purpose" placeholder="Describe the purpose of your event" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Location *</label>
                                    <input type="text" class="form-control" name="location" placeholder="Enter the event location" required>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Start Date *</label>
                                        <input type="date" class="form-control" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">End Date *</label>
                                        <input type="date" class="form-control" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Additional Notes</label>
                                    <textarea class="form-control" name="notes" rows="3" placeholder="Any additional details about your permit application"></textarea>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle"></i> Your application will be reviewed within 3-5 business days. You'll be notified via email once a decision is made.
                                </div>
                                
                                <button type="submit" name="apply_permit" class="btn btn-primary">
                                    <i class="bx bx-send"></i> Submit Application
                                </button>
                                <button type="reset" class="btn btn-outline-secondary ms-2">
                                    <i class="bx bx-reset"></i> Reset Form
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- My Permits Tab -->
            <div id="permits" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="permit-form">
                            <h4><i class="bx bx-file"></i> Permit Applications</h4>
                            
                            <?php if (empty($user_permits)): ?>
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle"></i> You haven't applied for any permits yet.
                                </div>
                            <?php else: ?>
                                <div class="permit-table">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Permit ID</th>
                                                <th>Purpose</th>
                                                <th>Location</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                                <th>Applied On</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($user_permits as $permit): ?>
                                                <tr>
                                                    <td><?php echo $permit['permit_id']; ?></td>
                                                    <td><?php echo htmlspecialchars($permit['event_purpose']); ?></td>
                                                    <td><?php echo htmlspecialchars($permit['location']); ?></td>
                                                    <td><?php echo $permit['start_date']; ?></td>
                                                    <td><?php echo $permit['end_date']; ?></td>
                                                    <td>
                                                        <?php 
                                                        $badge_class = '';
                                                        if ($permit['status'] == 'pending') $badge_class = 'bg-warning';
                                                        elseif ($permit['status'] == 'approved') $badge_class = 'bg-success';
                                                        else $badge_class = 'bg-danger';
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($permit['status']); ?></span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($permit['created_at'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewPermitDetails('<?php echo $permit['permit_id']; ?>')">
                                                            <i class="bx bx-show"></i> View
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Manage Permits Tab (Admin/Officer only) -->
            <?php if (in_array($user_role, ['admin', 'officer'])): ?>
            <div id="manage_permits" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="permit-form">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                <h4><i class="bx bx-cog"></i> Manage Permit Applications</h4>
                                <div class="mt-2 mt-md-0">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="filterPermits()">
                                        <i class="bx bx-filter"></i> Filter
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="exportToPDF()">
                                        <i class="bx bx-download"></i> Export PDF
                                    </button>
                                </div>
                            </div>
                            
                            <div class="permit-table">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Permit ID</th>
                                            <th>Applicant</th>
                                            <th>Purpose</th>
                                            <th>Location</th>
                                            <th>Date Range</th>
                                            <th>Status</th>
                                            <th>Applied On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($all_permits)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No permit applications found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($all_permits as $permit): ?>
                                                <tr>
                                                    <td><?php echo $permit['permit_id']; ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($permit['full_name']); ?><br>
                                                        <small class="text-muted"><?php echo $permit['username']; ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($permit['event_purpose']); ?></td>
                                                    <td><?php echo htmlspecialchars($permit['location']); ?></td>
                                                    <td>
                                                        <?php echo $permit['start_date']; ?> to <?php echo $permit['end_date']; ?>
                                                    </td>
                                                    <td>
                                                        <span class="status-indicator 
                                                            <?php 
                                                            if ($permit['status'] == 'pending') echo 'status-pending-indicator';
                                                            elseif ($permit['status'] == 'approved') echo 'status-approved-indicator';
                                                            else echo 'status-rejected-indicator';
                                                            ?>"></span>
                                                        <?php echo ucfirst($permit['status']); ?>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($permit['created_at'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="viewPermitDetails('<?php echo $permit['permit_id']; ?>')">
                                                            <i class="bx bx-show"></i>
                                                        </button>
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                                                                <i class="bx bx-cog"></i>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="permit_id" value="<?php echo $permit['permit_id']; ?>">
                                                                        <input type="hidden" name="status" value="approved">
                                                                        <button type="submit" name="update_status" class="dropdown-item">Approve</button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="permit_id" value="<?php echo $permit['permit_id']; ?>">
                                                                        <input type="hidden" name="status" value="rejected">
                                                                        <button type="submit" name="update_status" class="dropdown-item">Reject</button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="permit_id" value="<?php echo $permit['permit_id']; ?>">
                                                                        <input type="hidden" name="status" value="pending">
                                                                        <button type="submit" name="update_status" class="dropdown-item">Set as Pending</button>
                                                                    </form>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Tab navigation
        function openTab(evt, tabName) {
            // Hide all tab content
            var tabcontent = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            
            // Remove active class from all buttons
            var tabbuttons = document.getElementsByClassName("tab-button");
            for (var i = 0; i < tabbuttons.length; i++) {
                tabbuttons[i].classList.remove("active");
            }
            
            // Show the specific tab content
            document.getElementById(tabName).style.display = "block";
            
            // Add active class to the button that opened the tab
            evt.currentTarget.classList.add("active");
        }
        
        // Initialize charts
        function initCharts() {
            <?php if (in_array($user_role, ['admin', 'officer']) && !empty($status_distribution)): ?>
            // Status Distribution Chart
            const statusDistributionCtx = document.getElementById('statusDistributionChart').getContext('2d');
            const statusDistributionChart = new Chart(statusDistributionCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php 
                        foreach ($status_distribution as $status) {
                            echo "'" . ucfirst($status['status']) . "',";
                        }
                        ?>
                    ],
                    datasets: [{
                        data: [
                            <?php 
                            foreach ($status_distribution as $status) {
                                echo $status['count'] . ",";
                            }
                            ?>
                        ],
                        backgroundColor: ['#ffc107', '#198754', '#dc3545'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Permit Status Distribution'
                        }
                    }
                }
            });
            
            // Monthly Applications Chart
            const monthlyApplicationsCtx = document.getElementById('monthlyApplicationsChart').getContext('2d');
            const monthlyApplicationsChart = new Chart(monthlyApplicationsCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Permit Applications',
                        data: [12, 19, 15, 17, 22, 18, 25, 28, 32, 30, 27, 35],
                        backgroundColor: 'rgba(29, 53, 87, 0.7)',
                        borderColor: 'rgba(29, 53, 87, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Applications'
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        }
        
        // View permit details
        function viewPermitDetails(permitId) {
            alert('Viewing details for permit: ' + permitId);
            // In a real application, this would open a modal or redirect to a details page
        }
        
        // Filter permits (for admin view)
        function filterPermits() {
            alert('Filter functionality would be implemented here');
        }
        
        // Export to PDF
        function exportToPDF() {
            alert('PDF export functionality would be implemented here');
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            
            // Profile dropdown functionality
            const profileBtn = document.getElementById('profile-btn');
            const dropdownMenu = document.getElementById('dropdown-menu');
            
            if (profileBtn && dropdownMenu) {
                profileBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!profileBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.style.display = 'none';
                    }
                });
            }
            
            // Profile avatar animation
            const profileAvatar = document.getElementById('profileAvatar');
            if (profileAvatar) {
                profileAvatar.addEventListener('mouseover', function() {
                    this.style.transform = 'scale(1.1)';
                });
                
                profileAvatar.addEventListener('mouseout', function() {
                    this.style.transform = 'scale(1)';
                });
                
                profileAvatar.addEventListener('click', function() {
                    window.location.href = '../../Profile.php';
                });
            }
        });
    </script>
</body>
</html>