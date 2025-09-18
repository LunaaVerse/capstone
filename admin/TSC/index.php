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

// Get all intersections
$intersections = [];
try {
    $stmt = $pdo->query("SELECT * FROM intersections ORDER BY name");
    $intersections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching intersections: " . $e->getMessage();
}

// Get signal schedules
$schedules = [];
try {
    $stmt = $pdo->query("SELECT ss.*, i.name as intersection_name 
                        FROM signal_schedules ss 
                        JOIN intersections i ON ss.intersection_id = i.intersection_id 
                        ORDER BY i.name, ss.start_time");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching schedules: " . $e->getMessage();
}

// Get active signals
$active_signals = [];
try {
    $stmt = $pdo->query("SELECT asig.*, i.name as intersection_name 
                        FROM active_signals asig 
                        JOIN intersections i ON asig.intersection_id = i.intersection_id");
    $active_signals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching active signals: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Manual signal change
    if (isset($_POST['change_signal'])) {
        $intersection_id = $_POST['intersection_id'];
        $signal = $_POST['signal'];
        $timer = $_POST['timer'];
        $is_auto = isset($_POST['is_auto']) ? 1 : 0;
        
        try {
            // Check if record exists
            $stmt = $pdo->prepare("SELECT * FROM active_signals WHERE intersection_id = ?");
            $stmt->execute([$intersection_id]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE active_signals SET current_signal = ?, timer_value = ?, is_auto_mode = ?, last_change = NOW() WHERE intersection_id = ?");
                $stmt->execute([$signal, $timer, $is_auto, $intersection_id]);
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO active_signals (intersection_id, current_signal, timer_value, is_auto_mode, last_change) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$intersection_id, $signal, $timer, $is_auto]);
            }
            
            // Log the change
            $stmt = $pdo->prepare("INSERT INTO timing_changes_log (intersection_id, changed_by, change_description, red_duration_before, red_duration_after, yellow_duration_before, yellow_duration_after, green_duration_before, green_duration_after) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$intersection_id, $_SESSION['user_id'], "Manual signal change to $signal", 0, 0, 0, 0, 0, 0]);
            
            $_SESSION['success_message'] = "Signal changed successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error changing signal: " . $e->getMessage();
        }
    }
    
    // Update timing
    if (isset($_POST['update_timing'])) {
        $intersection_id = $_POST['intersection_id'];
        $red_duration = $_POST['red_duration'];
        $yellow_duration = $_POST['yellow_duration'];
        $green_duration = $_POST['green_duration'];
        $time_period = $_POST['time_period'];
        
        try {
            // Check if record exists
            $stmt = $pdo->prepare("SELECT * FROM signal_timings WHERE intersection_id = ? AND time_period = ?");
            $stmt->execute([$intersection_id, $time_period]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE signal_timings SET red_duration = ?, yellow_duration = ?, green_duration = ? WHERE intersection_id = ? AND time_period = ?");
                $stmt->execute([$red_duration, $yellow_duration, $green_duration, $intersection_id, $time_period]);
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO signal_timings (intersection_id, red_duration, yellow_duration, green_duration, time_period, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$intersection_id, $red_duration, $yellow_duration, $green_duration, $time_period]);
            }
            
            $_SESSION['success_message'] = "Timing updated successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating timing: " . $e->getMessage();
        }
    }
    
    // Add/update schedule
    if (isset($_POST['save_schedule'])) {
        $schedule_id = $_POST['schedule_id'] ?? null;
        $intersection_id = $_POST['intersection_id'];
        $time_period = $_POST['time_period'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $red_duration = $_POST['red_duration'];
        $yellow_duration = $_POST['yellow_duration'];
        $green_duration = $_POST['green_duration'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            if ($schedule_id) {
                // Update existing schedule
                $stmt = $pdo->prepare("UPDATE signal_schedules SET intersection_id = ?, time_period = ?, start_time = ?, end_time = ?, red_duration = ?, yellow_duration = ?, green_duration = ?, is_active = ? WHERE schedule_id = ?");
                $stmt->execute([$intersection_id, $time_period, $start_time, $end_time, $red_duration, $yellow_duration, $green_duration, $is_active, $schedule_id]);
            } else {
                // Insert new schedule
                $stmt = $pdo->prepare("INSERT INTO signal_schedules (intersection_id, time_period, start_time, end_time, red_duration, yellow_duration, green_duration, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$intersection_id, $time_period, $start_time, $end_time, $red_duration, $yellow_duration, $green_duration, $is_active]);
            }
            
            $_SESSION['success_message'] = "Schedule saved successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error saving schedule: " . $e->getMessage();
        }
    }
    
    // Delete schedule
    if (isset($_POST['delete_schedule'])) {
        $schedule_id = $_POST['schedule_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM signal_schedules WHERE schedule_id = ?");
            $stmt->execute([$schedule_id]);
            
            $_SESSION['success_message'] = "Schedule deleted successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting schedule: " . $e->getMessage();
        }
    }
}

// Get timing changes log
$timing_changes = [];
try {
    $stmt = $pdo->query("SELECT tcl.*, i.name as intersection_name 
                        FROM timing_changes_log tcl 
                        JOIN intersections i ON tcl.intersection_id = i.intersection_id 
                        ORDER BY tcl.created_at DESC 
                        LIMIT 10");
    $timing_changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching timing changes: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Traffic and Transport Management - Traffic Signal Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/style1.css" />
    <link rel="stylesheet" href="css/viewprofile.css" />
    <style>
        /* Traffic Signal Control Specific Styles */
        .signal-control-card {
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            border-top: 3px solid #1d3557;
        }
        
        .signal-display {
            width: 100px;
            height: 250px;
            background: #333;
            border-radius: 15px;
            margin: 0 auto 20px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }
        
        .signal-light {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #444;
            margin: 5px 0;
            transition: all 0.3s;
        }
        
        .signal-light.active-red {
            background: #e63946;
            box-shadow: 0 0 20px rgba(230, 57, 70, 0.7);
        }
        
        .signal-light.active-yellow {
            background: #ffd166;
            box-shadow: 0 0 20px rgba(255, 209, 102, 0.7);
        }
        
        .signal-light.active-green {
            background: #06d6a0;
            box-shadow: 0 0 20px rgba(6, 214, 160, 0.7);
        }
        
        .signal-controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .signal-btn {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            border: none;
            font-weight: bold;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: all 0.3s;
        }
        
        .signal-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .signal-btn.red {
            background: #e63946;
        }
        
        .signal-btn.yellow {
            background: #ffd166;
            color: #333;
        }
        
        .signal-btn.green {
            background: #06d6a0;
        }
        
        .timer-display {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            color: #1d3557;
        }
        
        .timing-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .schedule-table th,
        .schedule-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .schedule-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .schedule-table tr:hover {
            background: #f8f9fa;
        }
        
        .active-schedule {
            background: rgba(6, 214, 160, 0.1) !important;
            border-left: 3px solid #06d6a0;
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
            
            .signal-controls {
                flex-direction: column;
                align-items: center;
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
            <li class="active">
                <a href="../TSC/index.php">
                    <i class="bx bx-toggle-left"></i>
                    <span class="text">Traffic Signal Control</span>
                </a>
            </li>
            <li>
                <a href="../PTS/index.php">
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
                    <h1>Traffic Signal Control</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Dashboard</a>
                        </li>
                        <li><i class="bx bx-chevron-right"></i></li>
                        <li>
                            <a class="active" href="#">Traffic Signal Control</a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab(event, 'dashboard')">
                    <i class="bx bxs-dashboard"></i> Dashboard
                </button>
                <button class="tab-button" onclick="openTab(event, 'signal_control')">
                    <i class="bx bx-toggle-left"></i> Signal Control
                </button>
                <button class="tab-button" onclick="openTab(event, 'timing_adjuster')">
                    <i class="bx bx-time"></i> Timing Adjuster
                </button>
                <button class="tab-button" onclick="openTab(event, 'schedule_viewer')">
                    <i class="bx bx-calendar"></i> Schedule Viewer
                </button>
            </div>
            
            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content" style="display: block;">
                <div class="dashboard-overview">
                    <h2>Traffic Signal Control Dashboard</h2>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <h3>Total Intersections</h3>
                            <p id="totalIntersections"><?php echo count($intersections); ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Active Signals</h3>
                            <p id="activeSignals"><?php echo count($active_signals); ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Scheduled Changes</h3>
                            <p id="scheduledChanges"><?php echo count($schedules); ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Auto Mode Signals</h3>
                            <p id="autoModeSignals"><?php 
                                $auto_count = 0;
                                foreach ($active_signals as $signal) {
                                    if ($signal['is_auto_mode']) $auto_count++;
                                }
                                echo $auto_count;
                            ?></p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="signal-control-card">
                                <h4>Recent Signal Changes</h4>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Intersection</th>
                                                <th>Change</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($timing_changes)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No recent changes</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($timing_changes as $change): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($change['intersection_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($change['change_description']); ?></td>
                                                        <td><?php echo date('H:i', strtotime($change['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="signal-control-card">
                                <h4>Current Signal Status</h4>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Intersection</th>
                                                <th>Signal</th>
                                                <th>Timer</th>
                                                <th>Mode</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($active_signals)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No active signals</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($active_signals as $signal): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($signal['intersection_name']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $badge_class = '';
                                                            if ($signal['current_signal'] == 'red') $badge_class = 'bg-danger';
                                                            elseif ($signal['current_signal'] == 'yellow') $badge_class = 'bg-warning';
                                                            else $badge_class = 'bg-success';
                                                            ?>
                                                            <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($signal['current_signal']); ?></span>
                                                        </td>
                                                        <td><?php echo $signal['timer_value']; ?>s</td>
                                                        <td>
                                                            <span class="badge <?php echo $signal['is_auto_mode'] ? 'bg-info' : 'bg-secondary'; ?>">
                                                                <?php echo $signal['is_auto_mode'] ? 'Auto' : 'Manual'; ?>
                                                            </span>
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
            </div>
            
            <!-- Signal Control Tab -->
            <div id="signal_control" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-4">
                        <div class="signal-control-card">
                            <h4>Select Intersection</h4>
                            <select class="form-select mb-3" id="intersectionSelect" onchange="updateSignalDisplay()">
                                <option value="">Select an intersection</option>
                                <?php foreach ($intersections as $intersection): ?>
                                    <option value="<?php echo $intersection['intersection_id']; ?>"><?php echo htmlspecialchars($intersection['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div id="signalDisplay">
                                <div class="signal-display">
                                    <div class="signal-light" id="redLight"></div>
                                    <div class="signal-light" id="yellowLight"></div>
                                    <div class="signal-light" id="greenLight"></div>
                                </div>
                                
                                <div class="timer-display" id="timerDisplay">--</div>
                                
                                <div class="form-group mb-3">
                                    <label class="form-label">Timer Duration (seconds)</label>
                                    <input type="number" class="form-control" id="timerInput" name="timer" value="30" min="5" max="120">
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="autoModeCheck" name="is_auto" checked>
                                    <label class="form-check-label" for="autoModeCheck">Auto Mode</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="signal-control-card">
                            <h4>Manual Signal Control</h4>
                            
                            <div class="signal-controls">
                                <button class="signal-btn red" onclick="setSignal('red')">
                                    <i class="bx bx-circle" style="font-size: 24px;"></i>
                                    <span>Red</span>
                                </button>
                                <button class="signal-btn yellow" onclick="setSignal('yellow')">
                                    <i class="bx bx-circle" style="font-size: 24px;"></i>
                                    <span>Yellow</span>
                                </button>
                                <button class="signal-btn green" onclick="setSignal('green')">
                                    <i class="bx bx-circle" style="font-size: 24px;"></i>
                                    <span>Green</span>
                                </button>
                            </div>
                            
                            <form id="signalForm" method="POST">
                                <input type="hidden" id="formIntersectionId" name="intersection_id" value="">
                                <input type="hidden" id="formSignal" name="signal" value="">
                                <input type="hidden" id="formTimer" name="timer" value="30">
                                <input type="hidden" id="formIsAuto" name="is_auto" value="1">
                                
                                <button type="submit" name="change_signal" class="btn btn-primary" id="submitSignalBtn" disabled>
                                    <i class="bx bx-check"></i> Apply Signal Change
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetSignalForm()">
                                    <i class="bx bx-reset"></i> Reset
                                </button>
                            </form>
                            
                            <div class="alert alert-info mt-3">
                                <i class="bx bx-info-circle"></i> Select an intersection and signal to control. Changes will be applied immediately.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Timing Adjuster Tab -->
            <div id="timing_adjuster" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="signal-control-card">
                            <h4>Timing Configuration</h4>
                            
                            <form id="timingForm" method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Intersection</label>
                                    <select class="form-select" name="intersection_id" required>
                                        <option value="">Select an intersection</option>
                                        <?php foreach ($intersections as $intersection): ?>
                                            <option value="<?php echo $intersection['intersection_id']; ?>"><?php echo htmlspecialchars($intersection['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Time Period</label>
                                    <select class="form-select" name="time_period" required>
                                        <option value="">Select time period</option>
                                        <option value="peak_morning">Peak Morning</option>
                                        <option value="off_peak_morning">Off-Peak Morning</option>
                                        <option value="midday">Midday</option>
                                        <option value="off_peak_afternoon">Off-Peak Afternoon</option>
                                        <option value="peak_evening">Peak Evening</option>
                                        <option value="night">Night</option>
                                    </select>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Red Duration (s)</label>
                                        <input type="number" class="form-control" name="red_duration" value="30" min="5" max="120" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Yellow Duration (s)</label>
                                        <input type="number" class="form-control" name="yellow_duration" value="5" min="3" max="10" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Green Duration (s)</label>
                                        <input type="number" class="form-control" name="green_duration" value="45" min="10" max="180" required>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_timing" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Save Timing
                                </button>
                                <button type="reset" class="btn btn-outline-secondary ms-2">
                                    <i class="bx bx-reset"></i> Reset
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="signal-control-card">
                            <h4>Current Timings</h4>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Intersection</th>
                                            <th>Period</th>
                                            <th>Red (s)</th>
                                            <th>Yellow (s)</th>
                                            <th>Green (s)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $stmt = $pdo->query("SELECT st.*, i.name as intersection_name 
                                                                FROM signal_timings st 
                                                                JOIN intersections i ON st.intersection_id = i.intersection_id 
                                                                ORDER BY i.name, st.time_period");
                                            $timings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (empty($timings)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No timing configurations found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($timings as $timing): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($timing['intersection_name']); ?></td>
                                                        <td><?php echo ucfirst(str_replace('_', ' ', $timing['time_period'])); ?></td>
                                                        <td><?php echo $timing['red_duration']; ?></td>
                                                        <td><?php echo $timing['yellow_duration']; ?></td>
                                                        <td><?php echo $timing['green_duration']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif;
                                        } catch (PDOException $e) {
                                            echo '<tr><td colspan="5" class="text-center">Error loading timings</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Schedule Viewer Tab -->
            <div id="schedule_viewer" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="signal-control-card">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                <h4>Signal Schedules</h4>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                    <i class="bx bx-plus"></i> Add Schedule
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Intersection</th>
                                            <th>Time Period</th>
                                            <th>Start Time</th>
                                            <th>End Time</th>
                                            <th>Red (s)</th>
                                            <th>Yellow (s)</th>
                                            <th>Green (s)</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($schedules)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center">No schedules found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($schedules as $schedule): ?>
                                                <tr class="<?php echo $schedule['is_active'] ? 'active-schedule' : ''; ?>">
                                                    <td><?php echo htmlspecialchars($schedule['intersection_name']); ?></td>
                                                    <td><?php echo ucfirst(str_replace('_', ' ', $schedule['time_period'])); ?></td>
                                                    <td><?php echo date('H:i', strtotime($schedule['start_time'])); ?></td>
                                                    <td><?php echo date('H:i', strtotime($schedule['end_time'])); ?></td>
                                                    <td><?php echo $schedule['red_duration']; ?></td>
                                                    <td><?php echo $schedule['yellow_duration']; ?></td>
                                                    <td><?php echo $schedule['green_duration']; ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $schedule['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo $schedule['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="editSchedule(<?php echo $schedule['schedule_id']; ?>)">
                                                            <i class="bx bx-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline-block;">
                                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
                                                            <button type="submit" name="delete_schedule" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this schedule?')">
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

    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="scheduleForm">
                    <div class="modal-body">
                        <input type="hidden" name="schedule_id" id="scheduleId">
                        
                        <div class="mb-3">
                            <label class="form-label">Intersection</label>
                            <select class="form-select" name="intersection_id" id="scheduleIntersection" required>
                                <option value="">Select an intersection</option>
                                <?php foreach ($intersections as $intersection): ?>
                                    <option value="<?php echo $intersection['intersection_id']; ?>"><?php echo htmlspecialchars($intersection['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Time Period</label>
                            <select class="form-select" name="time_period" id="schedulePeriod" required>
                                <option value="">Select time period</option>
                                <option value="peak_morning">Peak Morning</option>
                                <option value="off_peak_morning">Off-Peak Morning</option>
                                <option value="midday">Midday</option>
                                <option value="off_peak_afternoon">Off-Peak Afternoon</option>
                                <option value="peak_evening">Peak Evening</option>
                                <option value="night">Night</option>
                            </select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" id="scheduleStart" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" id="scheduleEnd" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Red Duration (s)</label>
                                <input type="number" class="form-control" name="red_duration" id="scheduleRed" value="30" min="5" max="120" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Yellow Duration (s)</label>
                                <input type="number" class="form-control" name="yellow_duration" id="scheduleYellow" value="5" min="3" max="10" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Green Duration (s)</label>
                                <input type="number" class="form-control" name="green_duration" id="scheduleGreen" value="45" min="10" max="180" required>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="scheduleActive" checked>
                            <label class="form-check-label" for="scheduleActive">Active Schedule</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_schedule" class="btn btn-primary">Save Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
                tabbuttons[i].className = tabbuttons[i].className.replace(" active", "");
            }
            
            // Show the specific tab content
            document.getElementById(tabName).style.display = "block";
            
            // Add active class to the button that opened the tab
            evt.currentTarget.className += " active";
        }
        
        // Signal control functions
        let currentSignal = null;
        let currentIntersection = null;
        
        function updateSignalDisplay() {
            const intersectionId = document.getElementById('intersectionSelect').value;
            currentIntersection = intersectionId;
            
            // Enable/disable form based on selection
            document.getElementById('submitSignalBtn').disabled = !intersectionId || !currentSignal;
            document.getElementById('formIntersectionId').value = intersectionId;
            
            // TODO: Fetch current signal status for the selected intersection
            // For now, we'll just reset the display
            resetLights();
        }
        
        function setSignal(signal) {
            currentSignal = signal;
            
            // Update visual display
            resetLights();
            document.getElementById(signal + 'Light').classList.add('active-' + signal);
            
            // Update form values
            document.getElementById('formSignal').value = signal;
            document.getElementById('formTimer').value = document.getElementById('timerInput').value;
            document.getElementById('formIsAuto').value = document.getElementById('autoModeCheck').checked ? '1' : '0';
            
            // Enable submit button if intersection is selected
            if (currentIntersection) {
                document.getElementById('submitSignalBtn').disabled = false;
            }
        }
        
        function resetLights() {
            document.getElementById('redLight').className = 'signal-light';
            document.getElementById('yellowLight').className = 'signal-light';
            document.getElementById('greenLight').className = 'signal-light';
        }
        
        function resetSignalForm() {
            document.getElementById('intersectionSelect').value = '';
            document.getElementById('timerInput').value = '30';
            document.getElementById('autoModeCheck').checked = true;
            resetLights();
            currentSignal = null;
            currentIntersection = null;
            document.getElementById('submitSignalBtn').disabled = true;
            document.getElementById('timerDisplay').textContent = '--';
        }
        
        // Schedule functions
        function editSchedule(scheduleId) {
            // TODO: Fetch schedule data via AJAX and populate the form
            // For now, we'll just show the modal
            var myModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
            myModal.show();
            
            // Reset form
            document.getElementById('scheduleForm').reset();
            document.getElementById('scheduleId').value = scheduleId;
            
            // TODO: Populate form with schedule data
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize timer display
            document.getElementById('timerDisplay').textContent = document.getElementById('timerInput').value + 's';
            
            // Update timer display when input changes
            document.getElementById('timerInput').addEventListener('input', function() {
                document.getElementById('timerDisplay').textContent = this.value + 's';
                if (currentSignal) {
                    document.getElementById('formTimer').value = this.value;
                }
            });
            
            // Update auto mode when checkbox changes
            document.getElementById('autoModeCheck').addEventListener('change', function() {
                if (currentSignal) {
                    document.getElementById('formIsAuto').value = this.checked ? '1' : '0';
                }
            });
        });
    </script>
</body>
</html>