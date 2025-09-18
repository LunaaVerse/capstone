<?php
session_start();
require_once 'config/database.php'; // Database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get all intersections (using the existing tables from your schema)
$intersections = [];
try {
    $stmt = $pdo->query("SELECT signal_id as intersection_id, intersection_name as name, location 
                        FROM traffic_signals 
                        ORDER BY intersection_name");
    $intersections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching intersections: " . $e->getMessage();
}

// Get signal schedules (using your existing table structure)
$schedules = [];
try {
    $stmt = $pdo->query("SELECT ss.*, ts.intersection_name  
                        FROM signal_schedules ss 
                        JOIN traffic_signals ts ON ss.signal_id = ts.signal_id 
                        ORDER BY ts.intersection_name, ss.start_time");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching schedules: " . $e->getMessage();
}

// Get active signals (simulating this with your traffic_signals table)
$active_signals = [];
try {
    $stmt = $pdo->query("SELECT ts.signal_id, ts.intersection_name, ts.current_state as current_signal, 
                        30 as timer_value, '1' as is_auto_mode, ts.status
                        FROM traffic_signals ts 
                        WHERE ts.status = 'online'");
    $active_signals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching active signals: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Manual signal change
    if (isset($_POST['change_signal'])) {
        $signal_id = $_POST['signal_id'];
        $signal = $_POST['signal'];
        
        try {
            // Update the signal state
            $stmt = $pdo->prepare("UPDATE traffic_signals SET current_state = ? WHERE signal_id = ?");
            $stmt->execute([$signal, $signal_id]);
            
            // Log the change (using your signal_logs table)
            $stmt = $pdo->prepare("INSERT INTO signal_logs (signal_id, user_id, action, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([$signal_id, $_SESSION['user_id'], 'state_change', "Manual signal change to $signal"]);
            
            $_SESSION['success_message'] = "Signal changed successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error changing signal: " . $e->getMessage();
        }
    }
    
    // Update timing
    if (isset($_POST['update_timing'])) {
        $signal_id = $_POST['signal_id'];
        $red_duration = $_POST['red_duration'];
        $yellow_duration = $_POST['yellow_duration'];
        $green_duration = $_POST['green_duration'];
        $time_period = $_POST['time_period'];
        
        try {
            // Check if record exists
            $stmt = $pdo->prepare("SELECT * FROM signal_timings WHERE signal_id = ? AND time_period = ?");
            $stmt->execute([$signal_id, $time_period]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE signal_timings SET red_duration = ?, yellow_duration = ?, green_duration = ? WHERE signal_id = ? AND time_period = ?");
                $stmt->execute([$red_duration, $yellow_duration, $green_duration, $signal_id, $time_period]);
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO signal_timings (signal_id, red_duration, yellow_duration, green_duration, time_period, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$signal_id, $red_duration, $yellow_duration, $green_duration, $time_period]);
            }
            
            // Log the change
            $stmt = $pdo->prepare("INSERT INTO signal_logs (signal_id, user_id, action, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([$signal_id, $_SESSION['user_id'], 'timing_change', "Updated timing for $time_period: R:$red_duration Y:$yellow_duration G:$green_duration"]);
            
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
        $signal_id = $_POST['signal_id'];
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
                $stmt = $pdo->prepare("UPDATE signal_schedules SET signal_id = ?, time_period = ?, start_time = ?, end_time = ?, red_duration = ?, yellow_duration = ?, green_duration = ?, is_active = ? WHERE schedule_id = ?");
                $stmt->execute([$signal_id, $time_period, $start_time, $end_time, $red_duration, $yellow_duration, $green_duration, $is_active, $schedule_id]);
            } else {
                // Insert new schedule
                $stmt = $pdo->prepare("INSERT INTO signal_schedules (signal_id, time_period, start_time, end_time, red_duration, yellow_duration, green_duration, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$signal_id, $time_period, $start_time, $end_time, $red_duration, $yellow_duration, $green_duration, $is_active]);
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

// Get signal logs for dashboard
$signal_logs = [];
try {
    $stmt = $pdo->query("SELECT sl.*, ts.intersection_name  
                        FROM signal_logs sl 
                        JOIN traffic_signals ts ON sl.signal_id = ts.signal_id 
                        ORDER BY sl.created_at DESC 
                        LIMIT 10");
    $signal_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching signal logs: " . $e->getMessage();
}

// Get signal timings
$signal_timings = [];
try {
    $stmt = $pdo->query("SELECT st.*, ts.intersection_name  
                        FROM signal_timings st 
                        JOIN traffic_signals ts ON st.signal_id = ts.signal_id 
                        ORDER BY ts.intersection_name, st.time_period");
    $signal_timings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching signal timings: " . $e->getMessage();
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
            <li>
                <a href="../AVR/index.php">
                    <i class="bx bx-bell"></i>
                    <span class="text">Accident & Violation Reports</span>
                </a>
            </li>
            <li >
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
                                    if (isset($signal['is_auto_mode']) && $signal['is_auto_mode']) $auto_count++;
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
                                            <?php if (empty($signal_logs)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No recent changes</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($signal_logs as $log): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($log['intersection_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                                                        <td><?php echo date('H:i', strtotime($log['created_at'])); ?></td>
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
                                                <th>Status</th>
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
                                                        <td>
                                                            <span class="badge <?php echo $signal['status'] == 'online' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo ucfirst($signal['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo isset($signal['is_auto_mode']) && $signal['is_auto_mode'] ? 'bg-info' : 'bg-secondary'; ?>">
                                                                <?php echo isset($signal['is_auto_mode']) && $signal['is_auto_mode'] ? 'Auto' : 'Manual'; ?>
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
                                <input type="hidden" id="formSignalId" name="signal_id" value="">
                                <input type="hidden" id="formSignal" name="signal" value="">
                                <input type="hidden" name="change_signal" value="1">
                                
                                <button type="submit" class="btn btn-primary" id="submitSignalBtn" disabled>
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
                                    <select class="form-select" name="signal_id" required>
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
                                        <?php if (empty($signal_timings)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No timing configurations found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($signal_timings as $timing): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($timing['intersection_name']); ?></td>
                                                    <td><?php echo ucfirst(str_replace('_', ' ', $timing['time_period'])); ?></td>
                                                    <td><?php echo $timing['red_duration']; ?></td>
                                                    <td><?php echo $timing['yellow_duration']; ?></td>
                                                    <td><?php echo $timing['green_duration']; ?></td>
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
            
            <!-- Schedule Viewer Tab -->
            <div id="schedule_viewer" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-12">
                        <div class="signal-control-card">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                <h4>Signal Schedules</h4>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                    <i class="bx bx-plus"></i> Add New Schedule
                                </button>
                            </div>
                            
                            <div style="max-height: 500px; overflow-y: auto;">
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
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#scheduleModal"
                                                                    onclick="editSchedule(<?php echo htmlspecialchars(json_encode($schedule)); ?>)">
                                                                <i class="bx bx-edit"></i>
                                                            </button>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
                                                                <button type="submit" name="delete_schedule" class="btn btn-outline-danger" 
                                                                        onclick="return confirm('Are you sure you want to delete this schedule?')">
                                                                    <i class="bx bx-trash"></i>
                                                                </button>
                                                            </form>
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
        </main>
    </section>
    
    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalLabel">Add/Edit Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="scheduleForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="scheduleId" name="schedule_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Intersection</label>
                            <select class="form-select" name="signal_id" id="modalSignalId" required>
                                <option value="">Select an intersection</option>
                                <?php foreach ($intersections as $intersection): ?>
                                    <option value="<?php echo $intersection['intersection_id']; ?>"><?php echo htmlspecialchars($intersection['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Time Period</label>
                            <select class="form-select" name="time_period" id="modalTimePeriod" required>
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
                                <input type="time" class="form-control" name="start_time" id="modalStartTime" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" id="modalEndTime" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Red Duration (s)</label>
                                <input type="number" class="form-control" name="red_duration" id="modalRedDuration" value="30" min="5" max="120" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Yellow Duration (s)</label>
                                <input type="number" class="form-control" name="yellow_duration" id="modalYellowDuration" value="5" min="3" max="10" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Green Duration (s)</label>
                                <input type="number" class="form-control" name="green_duration" id="modalGreenDuration" value="45" min="10" max="180" required>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="modalIsActive" name="is_active" checked>
                            <label class="form-check-label" for="modalIsActive">Active Schedule</label>
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
                tabbuttons[i].className = tabbuttons[i].className.replace(" active", "");
            }
            
            // Show the specific tab content and add active class to the button
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
        
        // Signal control functions
        let currentSignal = null;
        let currentSignalId = null;
        
        function updateSignalDisplay() {
            const select = document.getElementById('intersectionSelect');
            const signalId = select.value;
            const signalName = select.options[select.selectedIndex].text;
            
            if (signalId) {
                // In a real application, you would fetch the current signal state from the server
                // For now, we'll simulate with default values
                document.getElementById('formSignalId').value = signalId;
                currentSignalId = signalId;
                
                // Enable the submit button
                document.getElementById('submitSignalBtn').disabled = false;
                
                // Update the display to show the selected intersection
                document.getElementById('timerDisplay').textContent = "30s";
                
                // Reset lights
                document.getElementById('redLight').className = 'signal-light';
                document.getElementById('yellowLight').className = 'signal-light';
                document.getElementById('greenLight').className = 'signal-light';
            } else {
                // Reset if no intersection selected
                resetSignalForm();
            }
        }
        
        function setSignal(signal) {
            currentSignal = signal;
            document.getElementById('formSignal').value = signal;
            
            // Update the visual display
            document.getElementById('redLight').className = 'signal-light' + (signal === 'red' ? ' active-red' : '');
            document.getElementById('yellowLight').className = 'signal-light' + (signal === 'yellow' ? ' active-yellow' : '');
            document.getElementById('greenLight').className = 'signal-light' + (signal === 'green' ? ' active-green' : '');
        }
        
        function resetSignalForm() {
            document.getElementById('intersectionSelect').value = '';
            document.getElementById('formSignalId').value = '';
            document.getElementById('formSignal').value = '';
            document.getElementById('submitSignalBtn').disabled = true;
            document.getElementById('timerDisplay').textContent = '--';
            
            // Reset lights
            document.getElementById('redLight').className = 'signal-light';
            document.getElementById('yellowLight').className = 'signal-light';
            document.getElementById('greenLight').className = 'signal-light';
            
            currentSignal = null;
            currentSignalId = null;
        }
        
        // Schedule modal functions
        function editSchedule(schedule) {
            document.getElementById('scheduleId').value = schedule.schedule_id;
            document.getElementById('modalSignalId').value = schedule.signal_id;
            document.getElementById('modalTimePeriod').value = schedule.time_period;
            document.getElementById('modalStartTime').value = schedule.start_time.substring(0, 5);
            document.getElementById('modalEndTime').value = schedule.end_time.substring(0, 5);
            document.getElementById('modalRedDuration').value = schedule.red_duration;
            document.getElementById('modalYellowDuration').value = schedule.yellow_duration;
            document.getElementById('modalGreenDuration').value = schedule.green_duration;
            document.getElementById('modalIsActive').checked = schedule.is_active == 1;
        }
        
        // Reset modal when closed
        document.getElementById('scheduleModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('scheduleForm').reset();
            document.getElementById('scheduleId').value = '';
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Profile dropdown toggle
        document.getElementById('profile-btn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('dropdown-menu').classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        window.addEventListener('click', function(e) {
            if (!e.target.matches('#profile-btn') && !e.target.closest('#dropdown-menu')) {
                var dropdown = document.getElementById('dropdown-menu');
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>