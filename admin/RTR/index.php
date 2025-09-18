<?php
session_start();
require_once 'config/database.php'; // Database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submission for creating/editing road updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_road_update'])) {
        $location = $_POST['location'];
        $status = $_POST['status'];
        $date = $_POST['date'];
        $time = $_POST['time'];
        $details = $_POST['details'];
        $duration = $_POST['duration'];
        $created_by = $_SESSION['user_id'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO road_updates (location, status, date, time, details, duration, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$location, $status, $date, $time, $details, $duration, $created_by]);
            
            $_SESSION['success_message'] = "Road update posted successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error posting road update: " . $e->getMessage();
        }
    }
    
    // Handle update deletion
    if (isset($_POST['delete_update'])) {
        $update_id = $_POST['update_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM road_updates WHERE id = ?");
            $stmt->execute([$update_id]);
            
            $_SESSION['success_message'] = "Road update deleted successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting road update: " . $e->getMessage();
        }
    }
    
    // Handle update editing
    if (isset($_POST['edit_road_update'])) {
        $update_id = $_POST['update_id'];
        $location = $_POST['location'];
        $status = $_POST['status'];
        $date = $_POST['date'];
        $time = $_POST['time'];
        $details = $_POST['details'];
        $duration = $_POST['duration'];
        
        try {
            $stmt = $pdo->prepare("UPDATE road_updates SET location = ?, status = ?, date = ?, time = ?, details = ?, duration = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$location, $status, $date, $time, $details, $duration, $update_id]);
            
            $_SESSION['success_message'] = "Road update updated successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating road update: " . $e->getMessage();
        }
    }
}

// Get all road updates for display
$road_updates = [];
try {
    $stmt = $pdo->prepare("SELECT ru.*, u.username 
                          FROM road_updates ru 
                          JOIN users u ON ru.created_by = u.user_id 
                          ORDER BY ru.created_at DESC");
    $stmt->execute();
    $road_updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching road updates: " . $e->getMessage();
}

// Get statistics for dashboard
$stats = [
    'total_updates' => 0,
    'open_roads' => 0,
    'blocked_roads' => 0,
    'maintenance_roads' => 0
];

try {
    // Total updates
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM road_updates");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_updates'] = $result['count'];
    
    // Open roads
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM road_updates WHERE status = 'open'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['open_roads'] = $result['count'];
    
    // Blocked roads
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM road_updates WHERE status = 'blocked'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['blocked_roads'] = $result['count'];
    
    // Maintenance roads
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM road_updates WHERE status = 'maintenance'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['maintenance_roads'] = $result['count'];
    
} catch (PDOException $e) {
    $error = "Error fetching statistics: " . $e->getMessage();
}

// Get status distribution for chart
$status_distribution = [];
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM road_updates GROUP BY status");
    $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching status distribution: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Traffic and Transport Management - Real-Time Road Updates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/style1.css" />
    <link rel="stylesheet" href="css/viewprofile.css" />
    <style>
        /* Real-Time Road Updates Specific Styles */
        .road-status-card {
            border-left: 4px solid;
            transition: all 0.3s;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 15px;
        }
        
        .road-status-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .status-open {
            border-left-color: #06d6a0;
            background: linear-gradient(to right, rgba(6, 214, 160, 0.05), white);
        }
        
        .status-blocked {
            border-left-color: #e63946;
            background: linear-gradient(to right, rgba(230, 57, 70, 0.05), white);
        }
        
        .status-maintenance {
            border-left-color: #ffd166;
            background: linear-gradient(to right, rgba(255, 209, 102, 0.05), white);
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-open-indicator {
            background-color: #06d6a0;
        }
        
        .status-blocked-indicator {
            background-color: #e63946;
        }
        
        .status-maintenance-indicator {
            background-color: #ffd166;
        }
        
        .road-form {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 3px solid #1d3557;
        }
        
        .notification-panel {
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

        /* Filter Styles */
        .filter-container {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
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
                src="img/ttm.png"
                alt="Profile Photo"
                class="profile-avatar"
                id="profileAvatar"
                style="cursor: pointer; transition: transform 0.2s"
            />
            <span class="text">Traffic And Transport Management</span>
        </a>
        <ul class="side-menu top">
            <li>
                <a href="index.php">
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
            <li class="active">
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
                <a href="Profile.php">
                    <i class="bx bxs-user-pin"></i>
                    <span class="text">Profile</span>
                </a>
            </li>
            <li>
                <a href="Settings.php">
                    <i class="bx bxs-cog"></i>
                    <span class="text">Settings</span>
                </a>
            </li>
            <li>
                <a href="logout.php" class="logout">
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
                    <a href="Profile.php">Profile</a>
                    <a href="Settings.php">Settings</a>
                </div>
            </div>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Real-Time Road Updates</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Dashboard</a>
                        </li>
                        <li><i class="bx bx-chevron-right"></i></li>
                        <li>
                            <a class="active" href="#">Real-Time Road Updates</a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab(event, 'dashboard')">
                    <i class="bx bxs-dashboard"></i> Dashboard
                </button>
                <button class="tab-button" onclick="openTab(event, 'admin_post')">
                    <i class="bx bx-edit"></i> Admin Post Dashboard
                </button>
                <button class="tab-button" onclick="openTab(event, 'notification_panel')">
                    <i class="bx bx-bell"></i> Notification Panel
                </button>
            </div>
            
            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content" style="display: block;">
                <div class="dashboard-overview">
                    <h2>Road Updates Dashboard Overview</h2>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <h3>Total Road Updates</h3>
                            <p id="totalUpdates"><?php echo $stats['total_updates']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Open Roads</h3>
                            <p id="openRoads" style="color: #06d6a0;"><?php echo $stats['open_roads']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Blocked Roads</h3>
                            <p id="blockedRoads" style="color: #e63946;"><?php echo $stats['blocked_roads']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Under Maintenance</h3>
                            <p id="maintenanceRoads" style="color: #ffd166;"><?php echo $stats['maintenance_roads']; ?></p>
                        </div>
                    </div>

                    <div class="chart-container">
                        <div class="chart">
                            <h3>Road Status Distribution</h3>
                            <canvas id="statusDistributionChart"></canvas>
                        </div>
                        <div class="chart">
                            <h3>Recent Updates Timeline</h3>
                            <canvas id="updatesTimelineChart"></canvas>
                        </div>
                    </div>

                    <div class="response-time">
                        <h3>Latest Road Updates</h3>
                        <div class="mt-3">
                            <?php if (empty($road_updates)): ?>
                                <div class="alert alert-info">No road updates available</div>
                            <?php else: ?>
                                <?php foreach (array_slice($road_updates, 0, 5) as $update): ?>
                                    <div class="road-status-card p-3 mb-2 <?php echo 'status-' . strtolower($update['status']); ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($update['location']); ?></h5>
                                                <p class="mb-1"><?php echo htmlspecialchars($update['details']); ?></p>
                                                <small class="text-muted">
                                                    <span class="status-indicator <?php echo 'status-' . strtolower($update['status']) . '-indicator'; ?>"></span>
                                                    <?php echo ucfirst($update['status']); ?> • 
                                                    Updated: <?php echo date('M j, Y g:i A', strtotime($update['updated_at'])); ?>
                                                </small>
                                            </div>
                                            <div>
                                                <span class="badge bg-<?php 
                                                    if ($update['status'] == 'open') echo 'success';
                                                    elseif ($update['status'] == 'blocked') echo 'danger';
                                                    else echo 'warning';
                                                ?>">
                                                    <?php echo ucfirst($update['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Admin Post Dashboard Tab -->
            <div id="admin_post" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="road-form">
                            <h4><i class="bx bx-edit"></i> Create Road Update</h4>
                            <form id="roadUpdateForm" method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Location *</label>
                                    <input type="text" class="form-control" name="location" placeholder="Enter location details" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Road Status *</label>
                                    <select class="form-select" name="status" required>
                                        <option value="">Select road status</option>
                                        <option value="open">Open</option>
                                        <option value="blocked">Blocked</option>
                                        <option value="maintenance">Under Maintenance</option>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Date *</label>
                                            <input type="date" class="form-control" name="date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Time *</label>
                                            <input type="time" class="form-control" name="time" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Duration</label>
                                    <input type="text" class="form-control" name="duration" placeholder="e.g., 2 hours, 5 days">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Details</label>
                                    <textarea class="form-control" name="details" rows="3" placeholder="Provide details about the road condition"></textarea>
                                </div>
                                
                                <button type="submit" name="create_road_update" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Post Update
                                </button>
                                <button type="reset" class="btn btn-outline-secondary ms-2">
                                    <i class="bx bx-reset"></i> Reset
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="road-form">
                            <h4><i class="bx bx-history"></i> Recent Road Updates</h4>
                            <div class="notification-panel">
                                <?php if (empty($road_updates)): ?>
                                    <div class="alert alert-info">No road updates available</div>
                                <?php else: ?>
                                    <?php foreach (array_slice($road_updates, 0, 10) as $update): ?>
                                        <div class="road-status-card p-3 mb-3 <?php echo 'status-' . strtolower($update['status']); ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($update['location']); ?></h5>
                                                    <p class="mb-1"><?php echo htmlspecialchars($update['details']); ?></p>
                                                    <p class="mb-1"><strong>Duration:</strong> <?php echo htmlspecialchars($update['duration']); ?></p>
                                                    <small class="text-muted">
                                                        <span class="status-indicator <?php echo 'status-' . strtolower($update['status']) . '-indicator'; ?>"></span>
                                                        <?php echo ucfirst($update['status']); ?> • 
                                                        Posted by: <?php echo htmlspecialchars($update['username']); ?> • 
                                                        <?php echo date('M j, Y g:i A', strtotime($update['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editUpdate(<?php echo $update['id']; ?>, '<?php echo htmlspecialchars($update['location']); ?>', '<?php echo $update['status']; ?>', '<?php echo $update['date']; ?>', '<?php echo $update['time']; ?>', `<?php echo htmlspecialchars($update['details']); ?>`, `<?php echo htmlspecialchars($update['duration']); ?>`)">
                                                        <i class="bx bx-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="update_id" value="<?php echo $update['id']; ?>">
                                                        <button type="submit" name="delete_update" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this update?')">
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Modal -->
                <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Road Update</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="update_id" id="edit_update_id">
                                    <div class="mb-3">
                                        <label class="form-label">Location *</label>
                                        <input type="text" class="form-control" name="location" id="edit_location" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Road Status *</label>
                                        <select class="form-select" name="status" id="edit_status" required>
                                            <option value="open">Open</option>
                                            <option value="blocked">Blocked</option>
                                            <option value="maintenance">Under Maintenance</option>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Date *</label>
                                                <input type="date" class="form-control" name="date" id="edit_date" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Time *</label>
                                                <input type="time" class="form-control" name="time" id="edit_time" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Duration</label>
                                        <input type="text" class="form-control" name="duration" id="edit_duration">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Details</label>
                                        <textarea class="form-control" name="details" id="edit_details" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="edit_road_update" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notification Panel Tab -->
            <div id="notification_panel" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="road-form">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                <h4><i class="bx bx-bell"></i> Road Updates Notification Panel</h4>
                                <div class="mt-2 mt-md-0">
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshNotifications()">
                                        <i class="bx bx-refresh"></i> Refresh
                                    </button>
                                    <div class="btn-group ms-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="bx bx-filter"></i> Filter by Status
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="filterByStatus('all')">All Statuses</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="filterByStatus('open')">Open</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="filterByStatus('blocked')">Blocked</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="filterByStatus('maintenance')">Under Maintenance</a></li>
                                        </ul>
                                    </div>
                                    <div class="btn-group ms-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="bx bx-sort"></i> Sort By
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="sortBy('newest')">Newest First</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="sortBy('oldest')">Oldest First</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="sortBy('location')">Location</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="sortBy('status')">Status</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="filter-container">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Search Location</label>
                                        <input type="text" class="form-control" id="searchLocation" placeholder="Enter location..." onkeyup="filterRoads()">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Filter by Status</label>
                                        <select class="form-select" id="statusFilter" onchange="filterRoads()">
                                            <option value="all">All Statuses</option>
                                            <option value="open">Open</option>
                                            <option value="blocked">Blocked</option>
                                            <option value="maintenance">Under Maintenance</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="notification-panel" id="notificationContainer">
                                <?php if (empty($road_updates)): ?>
                                    <div class="alert alert-info">No road updates available</div>
                                <?php else: ?>
                                    <?php foreach ($road_updates as $update): ?>
                                        <div class="road-status-card p-3 mb-3 <?php echo 'status-' . strtolower($update['status']); ?>" data-location="<?php echo htmlspecialchars(strtolower($update['location'])); ?>" data-status="<?php echo $update['status']; ?>" data-date="<?php echo strtotime($update['created_at']); ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($update['location']); ?></h5>
                                                    <p class="mb-1"><?php echo htmlspecialchars($update['details']); ?></p>
                                                    <p class="mb-1"><strong>Duration:</strong> <?php echo htmlspecialchars($update['duration']); ?></p>
                                                    <small class="text-muted">
                                                        <span class="status-indicator <?php echo 'status-' . strtolower($update['status']) . '-indicator'; ?>"></span>
                                                        <?php echo ucfirst($update['status']); ?> • 
                                                        Posted by: <?php echo htmlspecialchars($update['username']); ?> • 
                                                        <?php echo date('M j, Y g:i A', strtotime($update['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <span class="badge bg-<?php 
                                                        if ($update['status'] == 'open') echo 'success';
                                                        elseif ($update['status'] == 'blocked') echo 'danger';
                                                        else echo 'warning';
                                                    ?>">
                                                        <?php echo ucfirst($update['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
            
            // If opening dashboard, refresh charts
            if (tabName === 'dashboard') {
                initCharts();
            }
        }
        
        // Initialize charts
        function initCharts() {
            // Status Distribution Chart
            const statusDistributionCtx = document.getElementById('statusDistributionChart').getContext('2d');
            const statusDistributionChart = new Chart(statusDistributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Open Roads', 'Blocked Roads', 'Under Maintenance'],
                    datasets: [{
                        data: [
                            <?php echo $stats['open_roads']; ?>,
                            <?php echo $stats['blocked_roads']; ?>,
                            <?php echo $stats['maintenance_roads']; ?>
                        ],
                        backgroundColor: [
                            '#06d6a0',
                            '#e63946',
                            '#ffd166'
                        ],
                        borderWidth: 1
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
            
            // Updates Timeline Chart (mock data for now)
            const updatesTimelineCtx = document.getElementById('updatesTimelineChart').getContext('2d');
            const updatesTimelineChart = new Chart(updatesTimelineCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Road Updates',
                        data: [12, 19, 8, 15, 22, 18, 14],
                        borderColor: '#1d3557',
                        tension: 0.1,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Edit road update
        function editUpdate(id, location, status, date, time, details, duration) {
            document.getElementById('edit_update_id').value = id;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_time').value = time;
            document.getElementById('edit_details').value = details;
            document.getElementById('edit_duration').value = duration;
            
            // Show the modal
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        // Filter roads in notification panel
        function filterRoads() {
            const searchText = document.getElementById('searchLocation').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            
            const roadCards = document.querySelectorAll('#notificationContainer .road-status-card');
            
            roadCards.forEach(card => {
                const location = card.getAttribute('data-location');
                const status = card.getAttribute('data-status');
                
                const matchesSearch = location.includes(searchText);
                const matchesStatus = statusFilter === 'all' || status === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Sort roads in notification panel
        function sortBy(criteria) {
            const container = document.getElementById('notificationContainer');
            const roadCards = Array.from(container.querySelectorAll('.road-status-card'));
            
            roadCards.sort((a, b) => {
                if (criteria === 'newest') {
                    return parseInt(b.getAttribute('data-date')) - parseInt(a.getAttribute('data-date'));
                } else if (criteria === 'oldest') {
                    return parseInt(a.getAttribute('data-date')) - parseInt(b.getAttribute('data-date'));
                } else if (criteria === 'location') {
                    return a.getAttribute('data-location').localeCompare(b.getAttribute('data-location'));
                } else if (criteria === 'status') {
                    return a.getAttribute('data-status').localeCompare(b.getAttribute('data-status'));
                }
                return 0;
            });
            
            // Remove all cards and re-add in sorted order
            roadCards.forEach(card => container.appendChild(card));
        }
        
        // Filter by status from dropdown
        function filterByStatus(status) {
            document.getElementById('statusFilter').value = status;
            filterRoads();
        }
        
        // Refresh notifications
        function refreshNotifications() {
            location.reload();
        }
        
        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
            
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
        });
    </script>
</body>
</html>