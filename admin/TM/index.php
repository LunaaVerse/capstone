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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_traffic_log'])) {
        $location = $_POST['location'];
        $log_date = $_POST['log_date'];
        $log_time = $_POST['log_time'];
        $status_id = $_POST['status_id'];
        $average_speed = isset($_POST['average_speed']) ? $_POST['average_speed'] : null;
        $vehicle_count = isset($_POST['vehicle_count']) ? $_POST['vehicle_count'] : null;
        $notes = $_POST['notes'];
        $reported_by = $_SESSION['user_id'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO traffic_logs (location, log_date, log_time, status_id, average_speed_kmh, vehicle_count, notes, reported_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$location, $log_date, $log_time, $status_id, $average_speed, $vehicle_count, $notes, $reported_by]);
            
            $_SESSION['success_message'] = "Traffic log created successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error creating traffic log: " . $e->getMessage();
        }
    }
    
    // Handle log deletion
    if (isset($_POST['delete_log'])) {
        $log_id = $_POST['log_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM traffic_logs WHERE log_id = ?");
            $stmt->execute([$log_id]);
            
            $_SESSION['success_message'] = "Traffic log deleted successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting traffic log: " . $e->getMessage();
        }
    }
}

// Get traffic status types
$status_types = [];
try {
    $stmt = $pdo->query("SELECT * FROM traffic_status_types WHERE is_active = 1 ORDER BY severity_level");
    $status_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching status types: " . $e->getMessage();
}

// Get recent logs
$recent_logs = [];
try {
    $stmt = $pdo->prepare("SELECT tl.*, tst.status_name, tst.color_code 
                          FROM traffic_logs tl 
                          JOIN traffic_status_types tst ON tl.status_id = tst.status_id 
                          ORDER BY tl.created_at DESC 
                          LIMIT 5");
    $stmt->execute();
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching recent logs: " . $e->getMessage();
}

// Get all logs for report
$all_logs = [];
try {
    $stmt = $pdo->prepare("SELECT tl.*, tst.status_name, tst.color_code 
                          FROM traffic_logs tl 
                          JOIN traffic_status_types tst ON tl.status_id = tst.status_id 
                          ORDER BY tl.log_date DESC, tl.log_time DESC");
    $stmt->execute();
    $all_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching all logs: " . $e->getMessage();
}

// Get dashboard statistics
$stats = [
    'total_logs_today' => 0,
    'active_incidents' => 0,
    'congestion_points' => 0,
    'average_speed' => 0
];

try {
    $today = date('Y-m-d');
    
    // Total logs today
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM traffic_logs WHERE log_date = ?");
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_logs_today'] = $result['count'];
    
    // Active incidents (high traffic)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM traffic_logs tl 
                          JOIN traffic_status_types tst ON tl.status_id = tst.status_id 
                          WHERE tl.log_date = ? AND tst.severity_level = 3");
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['active_incidents'] = $result['count'];
    
    // Congestion points (medium traffic)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM traffic_logs tl 
                          JOIN traffic_status_types tst ON tl.status_id = tst.status_id 
                          WHERE tl.log_date = ? AND tst.severity_level = 2");
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['congestion_points'] = $result['count'];
    
    // Average speed
    $stmt = $pdo->prepare("SELECT AVG(average_speed_kmh) as avg_speed FROM traffic_logs 
                          WHERE log_date = ? AND average_speed_kmh IS NOT NULL");
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['average_speed'] = round($result['avg_speed'] ?? 0, 1);
    
} catch (PDOException $e) {
    $error = "Error fetching statistics: " . $e->getMessage();
}

// Get traffic status distribution for chart
$status_distribution = [];
try {
    $stmt = $pdo->query("SELECT tst.status_name, COUNT(tl.log_id) as count, tst.color_code 
                        FROM traffic_status_types tst 
                        LEFT JOIN traffic_logs tl ON tst.status_id = tl.status_id 
                        WHERE tst.is_active = 1 
                        GROUP BY tst.status_id, tst.status_name, tst.color_code");
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
    <title>Traffic and Transport Management - Traffic Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/style1.css" />
    <link rel="stylesheet" href="css/viewprofile.css" />
    <style>
        /* Traffic Monitoring Specific Styles */
        .traffic-status-card {
            border-left: 4px solid;
            transition: all 0.3s;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .traffic-status-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .traffic-low {
            border-left-color: #06d6a0;
            background: linear-gradient(to right, rgba(6, 214, 160, 0.05), white);
        }
        
        .traffic-medium {
            border-left-color: #ffd166;
            background: linear-gradient(to right, rgba(255, 209, 102, 0.05), white);
        }
        
        .traffic-high {
            border-left-color: #e63946;
            background: linear-gradient(to right, rgba(230, 57, 70, 0.05), white);
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-low {
            background-color: #06d6a0;
        }
        
        .status-medium {
            background-color: #ffd166;
        }
        
        .status-high {
            background-color: #e63946;
        }
        
        .traffic-form {
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
        
        .traffic-signal {
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

        .response-time {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
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
            
            <li class="active">
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
                    <h1>Traffic Monitoring</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Dashboard</a>
                        </li>
                        <li><i class="bx bx-chevron-right"></i></li>
                        <li>
                            <a class="active" href="#">Traffic Monitoring</a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab(event, 'dashboard')">
                    <i class="bx bxs-dashboard"></i> Dashboard
                </button>
                <button class="tab-button" onclick="openTab(event, 'manual_traffic_log')">
                    <i class="bx bx-edit"></i> Manual Traffic Log
                </button>
                <button class="tab-button" onclick="openTab(event, 'traffic_volume_status')">
                    <i class="bx bx-traffic-cone"></i> Traffic Volume Status
                </button>
                <button class="tab-button" onclick="openTab(event, 'daily_monitoring_report')">
                    <i class="bx bx-file"></i> Daily Monitoring Report
                </button>
            </div>
            
            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content" style="display: block;">
                <div class="dashboard-overview">
                    <h2>Traffic Monitoring Dashboard Overview</h2>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <h3>Active Traffic Incidents</h3>
                            <p id="activeIncidents"><?php echo $stats['active_incidents']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Congestion Points</h3>
                            <p id="congestionPoints"><?php echo $stats['congestion_points']; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Average Speed</h3>
                            <p id="averageSpeed"><?php echo $stats['average_speed']; ?> km/h</p>
                        </div>
                        <div class="stat-card">
                            <h3>Total Logs Today</h3>
                            <p id="totalLogs"><?php echo $stats['total_logs_today']; ?></p>
                        </div>
                    </div>

                    <div class="chart-container">
                        <div class="chart">
                            <h3>Traffic Volume Trends (Last 24 Hours)</h3>
                            <canvas id="trafficTrendChart"></canvas>
                        </div>
                        <div class="chart">
                            <h3>Traffic Status Distribution</h3>
                            <canvas id="statusDistributionChart"></canvas>
                        </div>
                    </div>

                    <div class="response-time">
                        <h3>Peak Traffic Hours</h3>
                        <p>7:30-9:30 AM | 4:30-7:00 PM</p>
                        <div style="width: 80%; margin: 0 auto">
                            <canvas id="peakHoursChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Manual Traffic Log Tab -->
            <div id="manual_traffic_log" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="traffic-form">
                            <h4><i class="bx bx-edit"></i> Create Traffic Log</h4>
                            <form id="trafficLogForm" method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Location *</label>
                                    <input type="text" class="form-control" name="location" placeholder="Enter location or intersection" required>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Date *</label>
                                        <input type="date" class="form-control" name="log_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Time *</label>
                                        <input type="time" class="form-control" name="log_time" value="<?php echo date('H:i'); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Traffic Status *</label>
                                    <select class="form-select" name="status_id" required>
                                        <option value="">Select traffic status</option>
                                        <?php foreach ($status_types as $status): ?>
                                            <option value="<?php echo $status['status_id']; ?>"><?php echo $status['status_name']; ?> - <?php echo $status['description']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Average Speed (km/h)</label>
                                        <input type="number" class="form-control" name="average_speed" placeholder="Optional">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Vehicle Count</label>
                                        <input type="number" class="form-control" name="vehicle_count" placeholder="Optional">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Additional Notes</label>
                                    <textarea class="form-control" name="notes" rows="3" placeholder="Any additional details about traffic conditions"></textarea>
                                </div>
                                
                                <button type="submit" name="create_traffic_log" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Save Log
                                </button>
                                <button type="reset" class="btn btn-outline-secondary ms-2">
                                    <i class="bx bx-reset"></i> Reset
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="traffic-form">
                            <h4><i class="bx bx-map"></i> Recent Traffic Logs</h4>
                            <div class="report-table">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Location</th>
                                            <th>Date/Time</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentLogsTable">
                                        <?php if (empty($recent_logs)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No recent logs found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_logs as $log): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($log['location']); ?></td>
                                                    <td><?php echo $log['log_date'] . ' ' . $log['log_time']; ?></td>
                                                    <td>
                                                        <?php 
                                                        $badge_class = '';
                                                        if ($log['status_name'] == 'Low') $badge_class = 'bg-success';
                                                        elseif ($log['status_name'] == 'Medium') $badge_class = 'bg-warning';
                                                        else $badge_class = 'bg-danger';
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $log['status_name']; ?></span>
                                                    </td>
                                                    <td>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="log_id" value="<?php echo $log['log_id']; ?>">
                                                            <button type="submit" name="delete_log" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this log?')">
                                                                <i class="bx bx-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="bx bx-info-circle"></i> Logs are saved to the database.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Traffic Volume Status Tab -->
            <div id="traffic_volume_status" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-4">
                        <div class="traffic-form">
                            <h4><i class="bx bx-traffic-cone"></i> Traffic Status Overview</h4>
                            
                            <?php foreach ($status_types as $status): ?>
                                <div class="traffic-status-card p-3 mb-3" style="border-left-color: <?php echo $status['color_code']; ?>; background: linear-gradient(to right, <?php echo hex2rgba($status['color_code'], 0.05); ?>, white);">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5><?php echo $status['status_name']; ?> Traffic</h5>
                                            <p class="mb-0"><?php echo $status['description']; ?></p>
                                            <small class="text-muted">
                                                Speed: 
                                                <?php 
                                                if ($status['min_speed_kmh'] !== null && $status['max_speed_kmh'] !== null) {
                                                    echo $status['min_speed_kmh'] . '-' . $status['max_speed_kmh'] . ' km/h';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </small>
                                        </div>
                                        <div class="traffic-signal" style="background-color: <?php echo $status['color_code']; ?>;">
                                            <?php echo strtoupper($status['status_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="mt-3">
                                <h6>Traffic Level Definitions:</h6>
                                <ul class="list-unstyled">
                                    <?php foreach ($status_types as $status): ?>
                                        <li>
                                            <span class="status-indicator" style="background-color: <?php echo $status['color_code']; ?>"></span> 
                                            <strong><?php echo $status['status_name']; ?>:</strong> <?php echo $status['description']; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="traffic-form">
                            <h4><i class="bx bx-map-alt"></i> Traffic Status Map</h4>
                            <div style="height: 400px; background-color: #f5f5f5; border-radius: 10px; display: flex; align-items: center; justify-content: center; position: relative;">
                                <div style="text-align: center;">
                                    <i class="bx bx-map" style="font-size: 3rem; color: #ccc;"></i>
                                    <p>Interactive traffic status map would appear here</p>
                                    <small class="text-muted">Real-time traffic monitoring visualization</small>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button class="btn btn-outline-primary" onclick="refreshTrafficStatus()">
                                    <i class="bx bx-refresh"></i> Refresh Status
                                </button>
                                <button class="btn btn-outline-secondary ms-2" onclick="filterLocations()">
                                    <i class="bx bx-filter"></i> Filter Locations
                                </button>
                                <button class="btn btn-outline-info ms-2" onclick="toggleMapView()">
                                    <i class="bx bx-layer"></i> Toggle View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Daily Monitoring Report Tab -->
            <div id="daily_monitoring_report" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="traffic-form">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                <h4><i class="bx bx-file"></i> Daily Traffic Monitoring Report</h4>
                                <div class="mt-2 mt-md-0">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="filterReports()">
                                        <i class="bx bx-filter"></i> Filter
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="exportToPDF()">
                                        <i class="bx bx-download"></i> Export PDF
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary ms-2" onclick="printReport()">
                                        <i class="bx bx-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                            
                            <div class="report-table">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Location</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Speed (km/h)</th>
                                            <th>Vehicle Count</th>
                                            <th>Notes</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reportTable">
                                        <?php if (empty($all_logs)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No reports available</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($all_logs as $log): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($log['location']); ?></td>
                                                    <td><?php echo $log['log_date']; ?></td>
                                                    <td><?php echo $log['log_time']; ?></td>
                                                    <td>
                                                        <span class="status-indicator" style="background-color: <?php echo $log['color_code']; ?>"></span>
                                                        <?php echo $log['status_name']; ?>
                                                    </td>
                                                    <td><?php echo $log['average_speed_kmh'] ?? '-'; ?></td>
                                                    <td><?php echo $log['vehicle_count'] ?? '-'; ?></td>
                                                    <td><?php echo !empty($log['notes']) ? htmlspecialchars(substr($log['notes'], 0, 50)) . (strlen($log['notes']) > 50 ? '...' : '') : '-'; ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editLog(<?php echo $log['log_id']; ?>)">
                                                            <i class="bx bx-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="log_id" value="<?php echo $log['log_id']; ?>">
                                                            <button type="submit" name="delete_log" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this log?')">
                                                                <i class="bx bx-trash"></i>
                                                            </button>
                                                        </form>
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
            // Traffic Trend Chart
            const trafficTrendCtx = document.getElementById('trafficTrendChart').getContext('2d');
            const trafficTrendChart = new Chart(trafficTrendCtx, {
                type: 'line',
                data: {
                    labels: ['00:00', '03:00', '06:00', '09:00', '12:00', '15:00', '18:00', '21:00'],
                    datasets: [{
                        label: 'Average Speed (km/h)',
                        data: [65, 60, 45, 35, 55, 40, 30, 50],
                        borderColor: '#1d3557',
                        backgroundColor: 'rgba(29, 53, 87, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Traffic Speed Trends'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: 0,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Speed (km/h)'
                            }
                        }
                    }
                }
            });
            
            // Status Distribution Chart
            const statusDistributionCtx = document.getElementById('statusDistributionChart').getContext('2d');
            const statusDistributionChart = new Chart(statusDistributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Low Traffic', 'Medium Traffic', 'High Traffic'],
                    datasets: [{
                        data: [45, 35, 20],
                        backgroundColor: ['#06d6a0', '#ffd166', '#e63946'],
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
                            text: 'Traffic Status Distribution'
                        }
                    }
                }
            });
            
            // Peak Hours Chart
            const peakHoursCtx = document.getElementById('peakHoursChart').getContext('2d');
            const peakHoursChart = new Chart(peakHoursCtx, {
                type: 'bar',
                data: {
                    labels: ['5-6 AM', '6-7 AM', '7-8 AM', '8-9 AM', '9-10 AM', '10-11 AM', '11-12 PM', 
                            '12-1 PM', '1-2 PM', '2-3 PM', '3-4 PM', '4-5 PM', '5-6 PM', '6-7 PM', '7-8 PM'],
                    datasets: [{
                        label: 'Traffic Volume',
                        data: [20, 45, 85, 95, 70, 60, 55, 50, 45, 40, 45, 80, 90, 75, 50],
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
                                text: 'Traffic Volume (%)'
                            }
                        }
                    }
                }
            });
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        // Helper functions for traffic monitoring
        function refreshTrafficStatus() {
            alert('Traffic status refreshed!');
            // In a real application, this would fetch updated data from the server
        }
        
        function filterLocations() {
            alert('Filtering locations...');
            // In a real application, this would show a filter modal
        }
        
        function toggleMapView() {
            alert('Toggling map view...');
            // In a real application, this would switch between map and list views
        }
        
        function filterReports() {
            alert('Filtering reports...');
            // In a real application, this would show a filter modal
        }
        
        function exportToPDF() {
            alert('Exporting to PDF...');
            // In a real application, this would generate and download a PDF report
        }
        
        function printReport() {
            alert('Printing report...');
            // In a real application, this would open the print dialog
        }
        
        function editLog(logId) {
            alert('Editing log ID: ' + logId);
            // In a real application, this would open an edit modal
        }
    </script>
</body>
</html>

<?php
// Helper function to convert hex color to rgba
function hex2rgba($color, $opacity = false) {
    $default = 'rgb(0,0,0)';
    
    // Return default if no color provided
    if(empty($color))
        return $default; 
    
    // Sanitize $color if "#" is provided 
    if ($color[0] == '#' ) {
        $color = substr( $color, 1 );
    }
    
    // Check if color has 6 or 3 characters and get values
    if (strlen($color) == 6) {
        $hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
    } elseif ( strlen( $color ) == 3 ) {
        $hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
    } else {
        return $default;
    }
    
    // Convert hexadec to rgb
    $rgb =  array_map('hexdec', $hex);
    
    // Check if opacity is set(rgba or rgb)
    if($opacity){
        if(abs($opacity) > 1)
            $opacity = 1.0;
        $output = 'rgba('.implode(",",$rgb).','.$opacity.')';
    } else {
        $output = 'rgb('.implode(",",$rgb).')';
    }
    
    // Return rgb(a) color string
    return $output;
}
?>