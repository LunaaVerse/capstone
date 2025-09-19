<?php
session_start();
require_once 'config/database.php'; // Your database connection file

// Create database connection
try {
    $pdo = getDBConnection('ttm_ttm');
} catch(Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Initialize error array
$errors = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_schedule'])) {
        // Validate required fields
        $required_fields = ['route_name', 'vehicle_type', 'fare', 'start_location', 'end_location', 'first_trip', 'last_trip', 'frequency'];
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field]))) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required.";
            }
        }
        
        // Validate operating days
        if (!isset($_POST['operating_days']) || empty($_POST['operating_days'])) {
            $errors[] = "At least one operating day must be selected.";
        }
        
        // Validate fare is numeric
        if (!empty($_POST['fare']) && !is_numeric($_POST['fare'])) {
            $errors[] = "Fare must be a valid number.";
        }
        
        // Validate frequency is numeric
        if (!empty($_POST['frequency']) && !is_numeric($_POST['frequency'])) {
            $errors[] = "Frequency must be a valid number.";
        }
        
        // If no validation errors, proceed with saving
        if (empty($errors)) {
            try {
                // Generate a unique route ID
                $route_id = 'ROUTE_' . time() . '_' . rand(1000, 9999);
                $operating_days = implode(',', $_POST['operating_days']);
                
                $stmt = $pdo->prepare("INSERT INTO transport_routes (route_id, route_name, vehicle_type, fare, start_location, end_location, first_trip, last_trip, frequency, operating_days, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([
                    $route_id,
                    $_POST['route_name'],
                    $_POST['vehicle_type'],
                    $_POST['fare'],
                    $_POST['start_location'],
                    $_POST['end_location'],
                    $_POST['first_trip'],
                    $_POST['last_trip'],
                    $_POST['frequency'],
                    $operating_days,
                    $user_id
                ]);
                
                if ($result) {
                    $_SESSION['success_message'] = "Route schedule saved successfully!";
                    // Clear form data after successful submission
                    unset($_POST);
                } else {
                    $_SESSION['error_message'] = "Failed to save route schedule. Please try again.";
                }
            } catch (PDOException $e) {
                // Check if it's a duplicate entry error
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $_SESSION['error_message'] = "A route with this name already exists.";
                } else {
                    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
                }
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
        
        // Redirect to avoid form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } 
    elseif (isset($_POST['calculate_eta'])) {
        // Calculate ETA
        $route_id = $_POST['eta_route'];
        $distance = floatval($_POST['distance']);
        $traffic_condition = $_POST['traffic_condition'];
        $additional_stops = intval($_POST['additional_stops']);
        
        // Calculate ETA based on traffic conditions
        $base_speed = 25; // km/h
        if ($traffic_condition === 'light') $base_speed = 35;
        elseif ($traffic_condition === 'moderate') $base_speed = 25;
        elseif ($traffic_condition === 'heavy') $base_speed = 15;
        
        // Add time for additional stops (2 minutes per stop)
        $stop_time = $additional_stops * 2;
        
        // Calculate travel time
        $travel_time_hours = $distance / $base_speed;
        $estimated_minutes = round($travel_time_hours * 60) + $stop_time;
        
        // Calculate arrival time
        $arrival_time = date('Y-m-d H:i:s', strtotime("+$estimated_minutes minutes"));
        
        // Save to database
        try {
            $stmt = $pdo->prepare("INSERT INTO vehicle_locations (route_id, vehicle_id, current_location, target_stop, distance, traffic_condition, additional_stops, estimated_minutes, arrival_time, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $route_id,
                $_POST['vehicle_id'],
                $_POST['current_location'],
                $_POST['target_stop'],
                $distance,
                $traffic_condition,
                $additional_stops,
                $estimated_minutes,
                $arrival_time,
                $user_id
            ]);
            
            if ($result) {
                $_SESSION['eta_data'] = [
                    'vehicle_id' => $_POST['vehicle_id'],
                    'route' => $_POST['eta_route'],
                    'current_location' => $_POST['current_location'],
                    'target_stop' => $_POST['target_stop'],
                    'estimated_minutes' => $estimated_minutes,
                    'arrival_time' => date('H:i:s', strtotime($arrival_time)),
                    'timestamp' => date('H:i:s')
                ];
            } else {
                $_SESSION['error_message'] = "Failed to calculate ETA. Please try again.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
        
        // Redirect to avoid form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get all routes for dropdown
$routes = $pdo->query("SELECT * FROM transport_routes ORDER BY route_name")->fetchAll();

// Get schedules for display
$schedules = $pdo->query("SELECT * FROM transport_routes ORDER BY created_at DESC")->fetchAll();

// Get recent ETAs
$etas = $pdo->query("SELECT vl.*, tr.route_name 
                    FROM vehicle_locations vl 
                    JOIN transport_routes tr ON vl.route_id = tr.route_id 
                    ORDER BY vl.created_at DESC LIMIT 10")->fetchAll();

// Get service announcements
$announcements = $pdo->query("SELECT * FROM service_announcements WHERE end_date IS NULL OR end_date >= CURDATE() ORDER BY created_at DESC")->fetchAll();

// Get stats for dashboard
$active_routes = $pdo->query("SELECT COUNT(*) as count FROM transport_routes WHERE status = 'active'")->fetch()['count'];
$total_vehicles = $pdo->query("SELECT COUNT(DISTINCT vehicle_id) as count FROM vehicle_locations")->fetch()['count'];
$daily_passengers = rand(2000, 2500); // This would come from actual data in a real system

// Calculate on-time performance (simplified)
$on_time_performance = rand(80, 95);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link
      href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="css/style1.css" />
    <link rel="stylesheet" href="css/viewprofile.css" />
    <title>LGU4 - Public Transport Sync</title>
    <style>
      /* Public Transport Sync Specific Styles */
      .transport-card {
        border-left: 4px solid;
        transition: all 0.3s;
        border-radius: 8px;
        background: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      }
      
      .transport-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      }
      
      .route-active {
        border-left-color: #06d6a0;
        background: linear-gradient(to right, rgba(6, 214, 160, 0.05), white);
      }
      
      .route-delayed {
        border-left-color: #ffd166;
        background: linear-gradient(to right, rgba(255, 209, 102, 0.05), white);
      }
      
      .route-inactive {
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
      
      .status-active {
        background-color: #06d6a0;
      }
      
      .status-delayed {
        background-color: #ffd166;
      }
      
      .status-inactive {
        background-color: #e63946;
      }
      
      .transport-form {
        background: white;
        border-radius: 10px;
        padding: 2rem;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        border-top: 3px solid #1d3557;
      }
      
      .schedule-table {
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
      
      .vehicle-status {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        color: white;
        font-weight: bold;
        font-size: 12px;
      }
      
      .eta-display {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-radius: 10px;
        text-align: center;
        margin-bottom: 15px;
      }
      
      .route-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
      }
      
      .commuter-display {
        background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
        color: white;
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 20px;
      }
      
      @media (max-width: 768px) {
        .tab-buttons {
          flex-direction: column;
        }
        
        .tab-button {
          border-radius: 5px;
          margin-bottom: 5px;
        }
        
        .transport-form {
          padding: 1rem;
        }
      }
    </style>
  </head>
  <body>
    <div id="avatarModal" class="modal">
      <span class="close">&times;</span>
      <img class="modal-content" id="modalImage" />
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
            <li >
                <a href="../TSC/index.php">
                    <i class="bx bx-toggle-left"></i>
                    <span class="text">Traffic Signal Control</span>
                </a>
            </li>
            <li class="active">
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
            <input type="search" placeholder="Search routes..." />
            <button type="submit" class="search-btn">
              <i class="bx bx-search"></i>
            </button>
          </div>
        </form>
        <a href="#" class="notification">
          <i class="bx bxs-bell"></i>
          <span class="num">5+</span>
        </a>
        <div class="profile-dropdown">
          <a href="#" class="profile" id="profile-btn">
            <img src="img/aiah.jpg" />
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
            <h1>Public Transport Sync</h1>
            <ul class="breadcrumb">
              <li>
                <a href="#">Dashboard</a>
              </li>
              <li><i class="bx bx-chevron-right"></i></li>
              <li>
                <a class="active" href="#">Public Transport Sync</a>
              </li>
            </ul>
          </div>
        </div>
        
        <!-- Display success/error messages -->
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
        
        <div class="tab-buttons">
          <button class="tab-button active" onclick="openTab(event, 'dashboard')">
            <i class="bx bxs-dashboard"></i> Dashboard
          </button>
          <button class="tab-button" onclick="openTab(event, 'vehicle_timetable')">
            <i class="bx bx-time"></i> Vehicle Timetable
          </button>
          <button class="tab-button" onclick="openTab(event, 'arrival_estimator')">
            <i class="bx bx-map-pin"></i> Arrival Estimator
          </button>
          <button class="tab-button" onclick="openTab(event, 'commuter_info')">
            <i class="bx bx-info-circle"></i> Commuter Info
          </button>
        </div>
        
        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-content" style="display: block;">
          <div class="dashboard-overview">
            <h2>Public Transport Dashboard Overview</h2>
            
            <div class="stats-container">
              <div class="stat-card">
                <h3>Active Routes</h3>
                <p id="activeRoutes"><?php echo $active_routes; ?></p>
              </div>
              <div class="stat-card">
                <h3>Total Vehicles</h3>
                <p id="totalVehicles"><?php echo $total_vehicles; ?></p>
              </div>
              <div class="stat-card">
                <h3>On-Time Performance</h3>
                <p id="onTimePerf"><?php echo $on_time_performance; ?>%</p>
              </div>
              <div class="stat-card">
                <h3>Daily Passengers</h3>
                <p id="dailyPassengers"><?php echo number_format($daily_passengers); ?></p>
              </div>
            </div>
            
            <div class="row mt-4">
              <div class="col-md-8">
                <div class="transport-form">
                  <h4><i class="bx bx-line-chart"></i> Route Performance</h4>
                  <canvas id="routePerformanceChart"></canvas>
                </div>
              </div>
              <div class="col-md-4">
                <div class="transport-form">
                  <h4><i class="bx bx-bus"></i> Vehicle Status</h4>
                  <?php foreach ($schedules as $schedule): ?>
                  <div class="transport-card route-active p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <h6><?php echo $schedule['route_name']; ?></h6>
                        <small>
                          <?php 
                          $vehicle_count = $pdo->prepare("SELECT COUNT(DISTINCT vehicle_id) as count FROM vehicle_locations WHERE route_id = ?");
                          $vehicle_count->execute([$schedule['route_id']]);
                          $count = $vehicle_count->fetch()['count'];
                          echo $count . " vehicles active";
                          ?>
                        </small>
                      </div>
                      <div class="vehicle-status" style="background-color: #06d6a0;">
                        Active
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Vehicle Timetable Tab -->
        <div id="vehicle_timetable" class="tab-content" style="display: none;">
          <div class="row">
            <div class="col-md-5">
              <div class="transport-form">
                <h4><i class="bx bx-time"></i> Add/Edit Route Schedule</h4>
                <form id="timetableForm" method="POST">
                  <input type="hidden" name="save_schedule" value="1">
                  <div class="mb-3">
                    <label class="form-label">Route Name</label>
                    <input type="text" class="form-control" name="route_name" placeholder="e.g., Route A - City Center" value="<?php echo isset($_POST['route_name']) ? htmlspecialchars($_POST['route_name']) : ''; ?>" required>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label">Vehicle Type</label>
                      <select class="form-select" name="vehicle_type" required>
                        <option value="">Select vehicle type</option>
                        <option value="bus" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'bus') ? 'selected' : ''; ?>>Bus</option>
                        <option value="jeepney" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'jeepney') ? 'selected' : ''; ?>>Jeepney</option>
                        <option value="van" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'van') ? 'selected' : ''; ?>>Van</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Fare (PHP)</label>
                      <input type="number" class="form-control" name="fare" placeholder="15.00" step="0.50" min="0" value="<?php echo isset($_POST['fare']) ? htmlspecialchars($_POST['fare']) : ''; ?>" required>
                    </div>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">Start Location</label>
                    <input type="text" class="form-control" name="start_location" placeholder="Terminal/Station name" value="<?php echo isset($_POST['start_location']) ? htmlspecialchars($_POST['start_location']) : ''; ?>" required>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">End Location</label>
                    <input type="text" class="form-control" name="end_location" placeholder="Destination name" value="<?php echo isset($_POST['end_location']) ? htmlspecialchars($_POST['end_location']) : ''; ?>" required>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label">First Trip</label>
                      <input type="time" class="form-control" name="first_trip" value="<?php echo isset($_POST['first_trip']) ? htmlspecialchars($_POST['first_trip']) : ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Last Trip</label>
                      <input type="time" class="form-control" name="last_trip" value="<?php echo isset($_POST['last_trip']) ? htmlspecialchars($_POST['last_trip']) : ''; ?>" required>
                    </div>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">Frequency (minutes)</label>
                    <input type="number" class="form-control" name="frequency" placeholder="15" min="5" max="60" value="<?php echo isset($_POST['frequency']) ? htmlspecialchars($_POST['frequency']) : ''; ?>" required>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">Operating Days</label>
                    <div class="form-check-group">
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="operating_days[]" value="monday" id="monday" <?php echo (isset($_POST['operating_days']) && in_array('monday', $_POST['operating_days'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="monday">Mon</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="operating_days[]" value="tuesday" id="tuesday" <?php echo (isset($_POST['operating_days']) && in_array('tuesday', $_POST['operating_days'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="tuesday">Tue</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="operating_days[]" value="wednesday" id="wednesday" <?php echo (isset($_POST['operating_days']) && in_array('wednesday', $_POST['operating_days'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="wednesday">Wed</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="operating_days[]" value="thursday" id="thursday" <?php echo (isset($_POST['operating_days']) && in_array('thursday', $_POST['operating_days'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="thursday">Thu</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="operating_days[]" value="friday" id="friday" <?php echo (isset($_POST['operating_days']) && in_array('friday', $_POST['operating_days'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="friday">Fri</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="operating_days[]" value="saturday" id="saturday" <?php echo (isset($_POST['operating_days']) && in_array('saturday', $_POST['operating_days'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="saturday">Sat</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="operating_days[]" value="sunday" id="sunday" <?php echo (isset($_POST['operating_days']) && in_array('sunday', $_POST['operating_days'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="sunday">Sun</label>
                      </div>
                    </div>
                  </div>
                  
                  <button type="submit" class="btn btn-primary">
                    <i class="bx bx-save"></i> Save Schedule
                  </button>
                  <button type="reset" class="btn btn-outline-secondary ms-2">
                    <i class="bx bx-reset"></i> Reset
                  </button>
                </form>
              </div>
            </div>
            
            <div class="col-md-7">
              <div class="transport-form">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h4><i class="bx bx-list-ul"></i> Route Schedules</h4>
                  <button class="btn btn-sm btn-outline-primary" onclick="exportSchedules()">
                    <i class="bx bx-download"></i> Export
                  </button>
                </div>
                <div class="schedule-table">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Route</th>
                        <th>Type</th>
                        <th>Schedule</th>
                        <th>Frequency</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody id="scheduleTable">
                      <?php foreach ($schedules as $schedule): ?>
                      <tr>
                        <td>
                          <strong><?php echo $schedule['route_name']; ?></strong><br>
                          <small><?php echo $schedule['start_location']; ?> → <?php echo $schedule['end_location']; ?></small>
                        </td>
                        <td>
                          <span class="badge bg-primary"><?php echo strtoupper($schedule['vehicle_type']); ?></span><br>
                          <small>₱<?php echo $schedule['fare']; ?></small>
                        </td>
                        <td>
                          <?php echo $schedule['first_trip']; ?> - <?php echo $schedule['last_trip']; ?><br>
                          <small><?php echo strtoupper(str_replace(',', ', ', $schedule['operating_days'])); ?></small>
                        </td>
                        <td><?php echo $schedule['frequency']; ?> min</td>
                        <td>
                          <button class="btn btn-sm btn-outline-primary" onclick="editSchedule('<?php echo $schedule['route_id']; ?>')">
                            <i class="bx bx-edit"></i>
                          </button>
                          <button class="btn btn-sm btn-outline-danger ms-1" onclick="deleteSchedule('<?php echo $schedule['route_id']; ?>')">
                            <i class="bx bx-trash"></i>
                          </button>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                      <?php if (empty($schedules)): ?>
                      <tr><td colspan="5" class="text-center">No schedules found</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
                <div class="alert alert-info mt-3">
                  <i class="bx bx-info-circle"></i> Schedules are saved to the database and displayed to commuters.
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Arrival Estimator Tab -->
        <div id="arrival_estimator" class="tab-content" style="display: none;">
          <div class="row">
            <div class="col-md-6">
              <div class="transport-form">
                <h4><i class="bx bx-map-pin"></i> Vehicle Location Input</h4>
                <form id="etaForm" method="POST">
                  <input type="hidden" name="calculate_eta" value="1">
                  <div class="mb-3">
                    <label class="form-label">Select Route</label>
                    <select class="form-select" name="eta_route" required>
                      <option value="">Choose a route</option>
                      <?php foreach ($routes as $route): ?>
                      <option value="<?php echo $route['route_id']; ?>"><?php echo $route['route_name']; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">Vehicle ID/Number</label>
                    <input type="text" class="form-control" name="vehicle_id" placeholder="e.g., Bus-001, JP-25" required>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">Current Location</label>
                    <input type="text" class="form-control" name="current_location" placeholder="Current stop or landmark" required>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">Target Stop/Station</label>
                    <input type="text" class="form-control" name="target_stop" placeholder="Destination stop" required>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label">Distance (km)</label>
                      <input type="number" class="form-control" name="distance" placeholder="5.2" step="0.1" min="0" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Traffic Condition</label>
                      <select class="form-select" name="traffic_condition" required>
                        <option value="">Select condition</option>
                        <option value="light">Light Traffic</option>
                        <option value="moderate">Moderate Traffic</option>
                        <option value="heavy">Heavy Traffic</option>
                      </select>
                    </div>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">Additional Stops</label>
                    <input type="number" class="form-control" name="additional_stops" placeholder="3" min="0" max="20">
                    <small class="form-text text-muted">Number of stops before reaching destination</small>
                  </div>
                  
                  <button type="submit" class="btn btn-primary">
                    <i class="bx bx-calculator"></i> Calculate ETA
                  </button>
                  <button type="button" class="btn btn-outline-success ms-2" onclick="updateETA()">
                    <i class="bx bx-refresh"></i> Update ETA
                  </button>
                </form>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="transport-form">
                <h4><i class="bx bx-time-five"></i> Current ETAs</h4>
                <div id="etaDisplay">
                  <?php if (isset($_SESSION['eta_data'])): ?>
                  <div class="eta-display">
                    <h5><i class="bx bx-bus"></i> <?php echo $_SESSION['eta_data']['vehicle_id']; ?></h5>
                    <p><strong>Route:</strong> <?php 
                      $route_name = $pdo->prepare("SELECT route_name FROM transport_routes WHERE route_id = ?");
                      $route_name->execute([$_SESSION['eta_data']['route']]);
                      echo $route_name->fetch()['route_name'];
                    ?></p>
                    <p><strong>Current Location:</strong> <?php echo $_SESSION['eta_data']['current_location']; ?></p>
                    <p><strong>Target Stop:</strong> <?php echo $_SESSION['eta_data']['target_stop']; ?></p>
                    <p><strong>Estimated Arrival:</strong> <?php echo $_SESSION['eta_data']['arrival_time']; ?></p>
                    <p><strong>Estimated Time:</strong> <?php echo $_SESSION['eta_data']['estimated_minutes']; ?> minutes</p>
                    <p><small>Calculated at: <?php echo $_SESSION['eta_data']['timestamp']; ?></small></p>
                  </div>
                  <?php unset($_SESSION['eta_data']); ?>
                  <?php else: ?>
                  <div class="alert alert-info">
                    <i class="bx bx-info-circle"></i> No ETA data available. Calculate an ETA to display it here.
                  </div>
                  <?php endif; ?>
                </div>
                
                <h5 class="mt-4">Recent ETA Calculations</h5>
                <div class="schedule-table">
                  <table class="table table-sm">
                    <thead>
                      <tr>
                        <th>Vehicle</th>
                        <th>Route</th>
                        <th>ETA (min)</th>
                        <th>Time</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($etas as $eta): ?>
                      <tr>
                        <td><?php echo $eta['vehicle_id']; ?></td>
                        <td><?php echo $eta['route_name']; ?></td>
                        <td><?php echo $eta['estimated_minutes']; ?></td>
                        <td><?php echo date('H:i', strtotime($eta['created_at'])); ?></td>
                      </tr>
                      <?php endforeach; ?>
                      <?php if (empty($etas)): ?>
                      <tr><td colspan="4" class="text-center">No ETA records found</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Commuter Info Tab -->
        <div id="commuter_info" class="tab-content" style="display: none;">
          <div class="commuter-display">
            <h3><i class="bx bx-group"></i> Commuter Information Portal</h3>
            <p>Real-time information for public transport users</p>
          </div>
          
          <div class="row mt-4">
            <div class="col-md-8">
              <div class="transport-form">
                <h4><i class="bx bx-bell"></i> Service Announcements</h4>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#announcementModal">
                    <i class="bx bx-plus"></i> Add Announcement
                  </button>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="liveUpdates" checked>
                    <label class="form-check-label" for="liveUpdates">Live Updates</label>
                  </div>
                </div>
                
                <div class="announcement-list">
                  <?php foreach ($announcements as $announcement): ?>
                  <div class="alert alert-info">
                    <div class="d-flex justify-content-between">
                      <h6><?php echo $announcement['title']; ?></h6>
                      <small><?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></small>
                    </div>
                    <p><?php echo $announcement['message']; ?></p>
                    <?php if ($announcement['end_date']): ?>
                    <small>Valid until: <?php echo date('M j, Y', strtotime($announcement['end_date'])); ?></small>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                  <?php if (empty($announcements)): ?>
                  <div class="alert alert-secondary">
                    <i class="bx bx-info-circle"></i> No active service announcements.
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="transport-form">
                <h4><i class="bx bx-stats"></i> Commuter Stats</h4>
                <div class="stats-container">
                  <div class="stat-card">
                    <h5>Peak Hours</h5>
                    <p>7:00-9:00 AM<br>5:00-7:00 PM</p>
                  </div>
                  <div class="stat-card">
                    <h5>Popular Routes</h5>
                    <p>City Center - Suburbs<br>Market - Terminal</p>
                  </div>
                </div>
                
                <h5 class="mt-4">Route Popularity</h5>
                <canvas id="routePopularityChart"></canvas>
              </div>
            </div>
          </div>
          
          <div class="transport-form mt-4">
            <h4><i class="bx bx-map-alt"></i> Interactive Route Map</h4>
            <div id="routeMap" style="height: 300px; background-color: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
              <p class="text-muted">Interactive map visualization would appear here</p>
            </div>
          </div>
        </div>
      </main>
    </section>
    
    <!-- Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add Service Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="announcementForm">
              <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" class="form-control" name="title" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea class="form-control" name="message" rows="3" required></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Valid Until (optional)</label>
                <input type="date" class="form-control" name="end_date">
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveAnnouncement()">Save Announcement</button>
          </div>
        </div>
      </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      // Tab switching function
      function openTab(evt, tabName) {
        var i, tabcontent, tabbuttons;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
          tabcontent[i].style.display = "none";
        }
        tabbuttons = document.getElementsByClassName("tab-button");
        for (i = 0; i < tabbuttons.length; i++) {
          tabbuttons[i].className = tabbuttons[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
      }
      
      // Initialize charts
      function initCharts() {
        // Route Performance Chart
        const perfCtx = document.getElementById('routePerformanceChart').getContext('2d');
        new Chart(perfCtx, {
          type: 'bar',
          data: {
            labels: ['Route A', 'Route B', 'Route C', 'Route D', 'Route E'],
            datasets: [{
              label: 'On-Time Performance (%)',
              data: [92, 85, 78, 88, 95],
              backgroundColor: 'rgba(54, 162, 235, 0.5)',
              borderColor: 'rgba(54, 162, 235, 1)',
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            scales: {
              y: {
                beginAtZero: true,
                max: 100
              }
            }
          }
        });
        
        // Route Popularity Chart
        const popCtx = document.getElementById('routePopularityChart').getContext('2d');
        new Chart(popCtx, {
          type: 'doughnut',
          data: {
            labels: ['Route A', 'Route B', 'Route C', 'Route D'],
            datasets: [{
              data: [35, 25, 20, 20],
              backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)'
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
      }
      
      // Initialize on page load
      document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        
        // Profile dropdown
        const profileBtn = document.getElementById('profile-btn');
        const dropdownMenu = document.getElementById('dropdown-menu');
        
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
        
        // Avatar modal
        const avatar = document.getElementById('profileAvatar');
        const modal = document.getElementById('avatarModal');
        const modalImg = document.getElementById('modalImage');
        const closeBtn = document.querySelector('.close');
        
        avatar.addEventListener('click', function() {
          modal.style.display = 'block';
          modalImg.src = this.src;
        });
        
        closeBtn.addEventListener('click', function() {
          modal.style.display = 'none';
        });
        
        window.addEventListener('click', function(e) {
          if (e.target === modal) {
            modal.style.display = 'none';
          }
        });
      });
      
      // Form validation
      function validateTimetableForm() {
        const form = document.getElementById('timetableForm');
        const operatingDays = form.querySelectorAll('input[name="operating_days[]"]:checked');
        
        if (operatingDays.length === 0) {
          alert('Please select at least one operating day.');
          return false;
        }
        
        return true;
      }
      
      // Export schedules
      function exportSchedules() {
        alert('Export functionality would be implemented here.');
      }
      
      // Edit schedule
      function editSchedule(routeId) {
        alert('Edit functionality for route ' + routeId + ' would be implemented here.');
      }
      
      // Delete schedule
      function deleteSchedule(routeId) {
        if (confirm('Are you sure you want to delete this schedule?')) {
          alert('Delete functionality for route ' + routeId + ' would be implemented here.');
        }
      }
      
      // Update ETA
      function updateETA() {
        alert('Update ETA functionality would be implemented here.');
      }
      
      // Save announcement
      function saveAnnouncement() {
        alert('Save announcement functionality would be implemented here.');
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('announcementModal'));
        modal.hide();
      }
    </script>
  </body>
</html>