<?php
session_start();
require_once 'config/database.php'; // Database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection is now established in database.php, so we don't need to create a new one

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle route creation
    if (isset($_POST['create_route'])) {
        $route_name = $_POST['route_name'];
        $start_point = $_POST['start_point'];
        $destination = $_POST['destination'];
        $route_type = $_POST['route_type'];
        $travel_time = $_POST['travel_time'];
        $distance = $_POST['distance'];
        $status = $_POST['status'];
        $description = $_POST['description'];
        $created_by = $_SESSION['user_id'];
        
        try {
            $stmt = $pdo_connection->prepare("INSERT INTO routes (route_name, start_point, destination, route_type, travel_time, distance, status, description, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$route_name, $start_point, $destination, $route_type, $travel_time, $distance, $status, $description, $created_by]);
            
            $_SESSION['success_message'] = "Route created successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error creating route: " . $e->getMessage();
        }
    }
    
    // Handle route deletion
    if (isset($_POST['delete_route'])) {
        $route_id = $_POST['route_id'];
        
        try {
            $stmt = $pdo_connection->prepare("DELETE FROM routes WHERE route_id = ?");
            $stmt->execute([$route_id]);
            
            $_SESSION['success_message'] = "Route deleted successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting route: " . $e->getMessage();
        }
    }
    
    // Handle diversion creation
    if (isset($_POST['create_diversion'])) {
        $affected_road = $_POST['affected_road'];
        $reason = $_POST['reason'];
        $start_date = $_POST['start_date'];
        $start_time = $_POST['start_time'];
        $end_date = $_POST['end_date'];
        $end_time = $_POST['end_time'];
        $alternative_route = $_POST['alternative_route'];
        $priority = $_POST['priority'];
        $details = $_POST['details'];
        $created_by = $_SESSION['user_id'];
        
        try {
            $stmt = $pdo_connection->prepare("INSERT INTO diversion_notices (affected_road, reason, start_date, start_time, end_date, end_time, alternative_route, priority, details, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$affected_road, $reason, $start_date, $start_time, $end_date, $end_time, $alternative_route, $priority, $details, $created_by]);
            
            $_SESSION['success_message'] = "Diversion notice created successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error creating diversion notice: " . $e->getMessage();
        }
    }
    
    // Handle diversion deletion
    if (isset($_POST['delete_diversion'])) {
        $diversion_id = $_POST['diversion_id'];
        
        try {
            $stmt = $pdo_connection->prepare("DELETE FROM diversion_notices WHERE diversion_id = ?");
            $stmt->execute([$diversion_id]);
            
            $_SESSION['success_message'] = "Diversion notice deleted successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting diversion notice: " . $e->getMessage();
        }
    }
    
    // Handle diversion status update
    if (isset($_POST['update_diversion_status'])) {
        $diversion_id = $_POST['diversion_id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo_connection->prepare("UPDATE diversion_notices SET status = ? WHERE diversion_id = ?");
            $stmt->execute([$status, $diversion_id]);
            
            $_SESSION['success_message'] = "Diversion status updated successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating diversion status: " . $e->getMessage();
        }
    }
}

// Get all routes
$routes = [];
try {
    $stmt = $pdo_connection->query("SELECT * FROM routes ORDER BY created_at DESC");
    $routes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching routes: " . $e->getMessage();
}

// Get all diversions
$diversions = [];
try {
    $stmt = $pdo_connection->query("SELECT * FROM diversion_notices ORDER BY created_at DESC");
    $diversions = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching diversions: " . $e->getMessage();
}

// Get active diversions count for dashboard
$active_diversions_count = 0;
try {
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    $stmt = $pdo_connection->query("SELECT COUNT(*) as count FROM diversion_notices 
                         WHERE status = 'active' 
                         AND (end_date > '$current_date' OR (end_date = '$current_date' AND end_time > '$current_time'))");
    $result = $stmt->fetch();
    $active_diversions_count = $result['count'];
} catch (PDOException $e) {
    $error = "Error fetching active diversions count: " . $e->getMessage();
}

// Get total routes count for dashboard
$total_routes_count = 0;
try {
    $stmt = $pdo_connection->query("SELECT COUNT(*) as count FROM routes");
    $result = $stmt->fetch();
    $total_routes_count = $result['count'];
} catch (PDOException $e) {
    $error = "Error fetching routes count: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Traffic and Transport Management - Vehicle Routing & Diversion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../css/style1.css" />
    <link rel="stylesheet" href="../css/viewprofile.css" />
    <style>
        /* Vehicle Routing & Diversion Specific Styles */
        .route-card {
            border-left: 4px solid;
            transition: all 0.3s;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .route-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .route-primary {
            border-left-color: #1d3557;
            background: linear-gradient(to right, rgba(29, 53, 87, 0.05), white);
        }
        
        .route-alternate {
            border-left-color: #457b9d;
            background: linear-gradient(to right, rgba(69, 123, 157, 0.05), white);
        }
        
        .route-emergency {
            border-left-color: #e63946;
            background: linear-gradient(to right, rgba(230, 57, 70, 0.05), white);
        }
        
        .route-scenic {
            border-left-color: #06d6a0;
            background: linear-gradient(to right, rgba(6, 214, 160, 0.05), white);
        }
        
        .diversion-card {
            border-left: 4px solid;
            transition: all 0.3s;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .diversion-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .priority-low {
            border-left-color: #06d6a0;
        }
        
        .priority-medium {
            border-left-color: #ffd166;
        }
        
        .priority-high {
            border-left-color: #f8961e;
        }
        
        .priority-urgent {
            border-left-color: #e63946;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active {
            background-color: #06d6a0;
            color: white;
        }
        
        .status-inactive {
            background-color: #6c757d;
            color: white;
        }
        
        .status-expired {
            background-color: #e63946;
            color: white;
        }
        
        .routing-form {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 3px solid #1d3557;
        }
        
        .map-container {
            height: 400px;
            background-color: #f5f5f5;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .map-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            flex-direction: column;
            color: #6c757d;
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
            <li class="active">
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
                    <h1>Vehicle Routing & Diversion</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="../index.php">Dashboard</a>
                        </li>
                        <li><i class="bx bx-chevron-right"></i></li>
                        <li>
                            <a class="active" href="#">Vehicle Routing & Diversion</a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab(event, 'dashboard')">
                    <i class="bx bxs-dashboard"></i> Dashboard
                </button>
                <button class="tab-button" onclick="openTab(event, 'route_suggestions')">
                    <i class="bx bx-directions"></i> Route Suggestions
                </button>
                <button class="tab-button" onclick="openTab(event, 'diversion_board')">
                    <i class="bx bx-notification"></i> Diversion Notice Board
                </button>
                <button class="tab-button" onclick="openTab(event, 'admin_routes')">
                    <i class="bx bx-cog"></i> Admin Route Management
                </button>
            </div>
            
            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content" style="display: block;">
                <div class="dashboard-overview">
                    <h2>Routing & Diversion Dashboard Overview</h2>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <h3>Total Routes</h3>
                            <p id="totalRoutes"><?php echo $total_routes_count; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Active Diversions</h3>
                            <p id="activeDiversions"><?php echo $active_diversions_count; ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Primary Routes</h3>
                            <p id="primaryRoutes"><?php 
                                $primary_count = 0;
                                foreach ($routes as $route) {
                                    if ($route['route_type'] == 'primary') $primary_count++;
                                }
                                echo $primary_count;
                            ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Alternative Routes</h3>
                            <p id="alternativeRoutes"><?php 
                                $alt_count = 0;
                                foreach ($routes as $route) {
                                    if ($route['route_type'] == 'alternate') $alt_count++;
                                }
                                echo $alt_count;
                            ?></p>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="routing-form">
                                <h4><i class="bx bx-map"></i> Quick Route Finder</h4>
                                <form id="quickRouteForm">
                                    <div class="mb-3">
                                        <label class="form-label">Starting Point</label>
                                        <input type="text" class="form-control" placeholder="Enter starting location" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Destination</label>
                                        <input type="text" class="form-control" placeholder="Enter destination" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Route Preference</label>
                                        <select class="form-select">
                                            <option value="fastest">Fastest Route</option>
                                            <option value="shortest">Shortest Distance</option>
                                            <option value="avoid_tolls">Avoid Tolls</option>
                                            <option value="scenic">Scenic Route</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-search"></i> Find Route
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="routing-form">
                                <h4><i class="bx bx-notification"></i> Active Diversions</h4>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php if (empty($diversions)): ?>
                                        <div class="alert alert-info">
                                            No active diversions at this time.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($diversions as $diversion): 
                                            $current_date = date('Y-m-d');
                                            $current_time = date('H:i:s');
                                            $is_active = ($diversion['status'] == 'active') && 
                                                        ($diversion['end_date'] > $current_date || 
                                                        ($diversion['end_date'] == $current_date && $diversion['end_time'] > $current_time));
                                            $is_expired = ($diversion['end_date'] < $current_date) || 
                                                         ($diversion['end_date'] == $current_date && $diversion['end_time'] < $current_time);
                                            $status_class = $is_active ? 'status-active' : ($is_expired ? 'status-expired' : 'status-inactive');
                                            $status_text = $is_active ? 'Active' : ($is_expired ? 'Expired' : 'Inactive');
                                        ?>
                                            <div class="diversion-card p-3 mb-2 priority-<?php echo $diversion['priority']; ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($diversion['affected_road']); ?></h6>
                                                        <small class="text-muted">Reason: <?php echo htmlspecialchars($diversion['reason']); ?></small>
                                                    </div>
                                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                </div>
                                                <div class="mt-2">
                                                    <small>Until: <?php echo date('M j, Y', strtotime($diversion['end_date'])); ?> at <?php echo date('g:i A', strtotime($diversion['end_time'])); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Route Suggestions Tab -->
            <div id="route_suggestions" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-5">
                        <div class="routing-form">
                            <h4><i class="bx bx-directions"></i> Route Finder</h4>
                            <form id="routeFinderForm">
                                <div class="mb-3">
                                    <label class="form-label">Starting Location *</label>
                                    <input type="text" class="form-control" id="startLocation" placeholder="Enter starting point" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Destination *</label>
                                    <input type="text" class="form-control" id="destination" placeholder="Enter destination" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Route Preference</label>
                                    <select class="form-select" id="routePreference">
                                        <option value="fastest">Fastest Route</option>
                                        <option value="shortest">Shortest Distance</option>
                                        <option value="avoid_tolls">Avoid Tolls</option>
                                        <option value="avoid_highways">Avoid Highways</option>
                                        <option value="scenic">Scenic Route</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="avoidDiversions">
                                    <label class="form-check-label" for="avoidDiversions">Avoid areas with active diversions</label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-search"></i> Find Route
                                </button>
                                <button type="button" class="btn btn-outline-secondary ms-2" onclick="clearRouteForm()">
                                    <i class="bx bx-reset"></i> Clear
                                </button>
                            </form>
                            
                            <div id="routeResults" class="mt-4" style="display: none;">
                                <h5>Suggested Routes</h5>
                                <div class="list-group" id="routeOptions">
                                    <!-- Route options will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="routing-form">
                            <h4><i class="bx bx-map"></i> Route Map</h4>
                            <div class="map-container">
                                <div class="map-placeholder">
                                    <i class="bx bx-map-alt" style="font-size: 3rem;"></i>
                                    <p class="mt-2">Enter locations to view route map</p>
                                    <small class="text-muted">Interactive map will appear here</small>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button class="btn btn-outline-primary" onclick="printRoute()" disabled id="printBtn">
                                    <i class="bx bx-printer"></i> Print Route
                                </button>
                                <button class="btn btn-outline-secondary ms-2" onclick="shareRoute()" disabled id="shareBtn">
                                    <i class="bx bx-share-alt"></i> Share Route
                                </button>
                                <button class="btn btn-outline-info ms-2" onclick="saveRoute()" disabled id="saveBtn">
                                    <i class="bx bx-save"></i> Save Route
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Diversion Notice Board Tab -->
            <div id="diversion_board" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-5">
                        <div class="routing-form">
                            <h4><i class="bx bx-notification"></i> Create Diversion Notice</h4>
                            <form id="diversionForm" method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Affected Road *</label>
                                    <input type="text" class="form-control" name="affected_road" placeholder="e.g., Main Street between 1st and 3rd" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Reason *</label>
                                    <select class="form-select" name="reason" required>
                                        <option value="">Select reason</option>
                                        <option value="roadwork">Roadwork</option>
                                        <option value="accident">Accident</option>
                                        <option value="event">Event</option>
                                        <option value="weather">Weather</option>
                                        <option value="emergery">Emergency</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Start Date *</label>
                                        <input type="date" class="form-control" name="start_date" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Start Time *</label>
                                        <input type="time" class="form-control" name="start_time" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">End Date *</label>
                                        <input type="date" class="form-control" name="end_date" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">End Time *</label>
                                        <input type="time" class="form-control" name="end_time" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Alternative Route *</label>
                                    <textarea class="form-control" name="alternative_route" rows="2" placeholder="Suggest alternative routes for drivers" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Priority Level</label>
                                    <select class="form-select" name="priority">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Additional Details</label>
                                    <textarea class="form-control" name="details" rows="3" placeholder="Any additional information about the diversion"></textarea>
                                </div>
                                
                                <button type="submit" name="create_diversion" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Create Notice
                                </button>
                                <button type="reset" class="btn btn-outline-secondary ms-2">
                                    <i class="bx bx-reset"></i> Reset
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="routing-form">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0"><i class="bx bx-list-ul"></i> Active Diversion Notices</h4>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="showExpiredToggle" onchange="toggleExpiredNotices()">
                                    <label class="form-check-label" for="showExpiredToggle">Show expired notices</label>
                                </div>
                            </div>
                            
                            <div id="diversionList">
                                <?php if (empty($diversions)): ?>
                                    <div class="alert alert-info">
                                        No diversion notices found. Create one using the form on the left.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($diversions as $diversion): 
                                        $current_date = date('Y-m-d');
                                        $current_time = date('H:i:s');
                                        $is_active = ($diversion['status'] == 'active') && 
                                                    ($diversion['end_date'] > $current_date || 
                                                    ($diversion['end_date'] == $current_date && $diversion['end_time'] > $current_time));
                                        $is_expired = ($diversion['end_date'] < $current_date) || 
                                                     ($diversion['end_date'] == $current_date && $diversion['end_time'] < $current_time);
                                        $status_class = $is_active ? 'status-active' : ($is_expired ? 'status-expired' : 'status-inactive');
                                        $status_text = $is_active ? 'Active' : ($is_expired ? 'Expired' : 'Inactive');
                                    ?>
                                        <div class="diversion-card p-3 mb-3 priority-<?php echo $diversion['priority']; ?> <?php if ($is_expired) echo 'expired-notice'; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h5><?php echo htmlspecialchars($diversion['affected_road']); ?></h5>
                                                    <p class="mb-1"><strong>Reason:</strong> <?php echo ucfirst($diversion['reason']); ?></p>
                                                    <p class="mb-1"><strong>Duration:</strong> <?php echo date('M j, Y', strtotime($diversion['start_date'])); ?> at <?php echo date('g:i A', strtotime($diversion['start_time'])); ?> to <?php echo date('M j, Y', strtotime($diversion['end_date'])); ?> at <?php echo date('g:i A', strtotime($diversion['end_time'])); ?></p>
                                                    <p class="mb-1"><strong>Alternative Route:</strong> <?php echo htmlspecialchars($diversion['alternative_route']); ?></p>
                                                    <?php if (!empty($diversion['details'])): ?>
                                                        <p class="mb-1"><strong>Details:</strong> <?php echo htmlspecialchars($diversion['details']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex flex-column align-items-end">
                                                    <span class="status-badge <?php echo $status_class; ?> mb-2"><?php echo $status_text; ?></span>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if (!$is_expired): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="diversion_id" value="<?php echo $diversion['diversion_id']; ?>">
                                                                <input type="hidden" name="status" value="<?php echo $diversion['status'] == 'active' ? 'inactive' : 'active'; ?>">
                                                                <button type="submit" name="update_diversion_status" class="btn btn-sm <?php echo $diversion['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                                                    <?php echo $diversion['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this diversion notice?');">
                                                            <input type="hidden" name="diversion_id" value="<?php echo $diversion['diversion_id']; ?>">
                                                            <button type="submit" name="delete_diversion" class="btn btn-sm btn-danger">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
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
            
            <!-- Admin Route Management Tab -->
            <div id="admin_routes" class="tab-content" style="display: none;">
                <div class="row">
                    <div class="col-md-5">
                        <div class="routing-form">
                            <h4><i class="bx bx-plus"></i> Create New Route</h4>
                            <form method="POST" id="routeForm">
                                <div class="mb-3">
                                    <label class="form-label">Route Name *</label>
                                    <input type="text" class="form-control" name="route_name" placeholder="e.g., Downtown Express Route" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Start Point *</label>
                                    <input type="text" class="form-control" name="start_point" placeholder="Starting location" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Destination *</label>
                                    <input type="text" class="form-control" name="destination" placeholder="End location" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Route Type *</label>
                                    <select class="form-select" name="route_type" required>
                                        <option value="">Select route type</option>
                                        <option value="primary">Primary Route</option>
                                        <option value="alternate">Alternate Route</option>
                                        <option value="emergency">Emergency Route</option>
                                        <option value="scenic">Scenic Route</option>
                                    </select>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Estimated Travel Time (minutes) *</label>
                                        <input type="number" class="form-control" name="travel_time" min="1" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Distance (km) *</label>
                                        <input type="number" step="0.1" class="form-control" name="distance" min="0.1" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="maintenance">Under Maintenance</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Route Description</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Describe key landmarks, turns, or other important details"></textarea>
                                </div>
                                
                                <button type="submit" name="create_route" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Create Route
                                </button>
                                <button type="reset" class="btn btn-outline-secondary ms-2">
                                    <i class="bx bx-reset"></i> Reset
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="routing-form">
                            <h4><i class="bx bx-list-ul"></i> Manage Routes</h4>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Route Name</th>
                                            <th>From → To</th>
                                            <th>Type</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($routes)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No routes found. Create one using the form on the left.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($routes as $route): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($route['route_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($route['start_point']); ?> → <?php echo htmlspecialchars($route['destination']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            switch($route['route_type']) {
                                                                case 'primary': echo 'primary'; break;
                                                                case 'alternate': echo 'info'; break;
                                                                case 'emergency': echo 'danger'; break;
                                                                case 'scenic': echo 'success'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                            <?php echo ucfirst($route['route_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $route['travel_time']; ?> min</td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            switch($route['status']) {
                                                                case 'active': echo 'success'; break;
                                                                case 'inactive': echo 'secondary'; break;
                                                                case 'maintenance': echo 'warning'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                            <?php echo ucfirst($route['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="viewRoute(<?php echo $route['route_id']; ?>)">
                                                                <i class="bx bx-show"></i>
                                                            </button>
                                                            <button class="btn btn-outline-info" onclick="editRoute(<?php echo $route['route_id']; ?>)">
                                                                <i class="bx bx-edit"></i>
                                                            </button>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="route_id" value="<?php echo $route['route_id']; ?>">
                                                                <button type="submit" name="delete_route" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this route?');">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab navigation
        function openTab(evt, tabName) {
            // Hide all tab contents
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].style.display = "none";
            }
            
            // Remove active class from all tab buttons
            var tabButtons = document.getElementsByClassName("tab-button");
            for (var i = 0; i < tabButtons.length; i++) {
                tabButtons[i].className = tabButtons[i].className.replace(" active", "");
            }
            
            // Show the specific tab content and add active class to the button
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
        
        // Toggle expired notices
        function toggleExpiredNotices() {
            var showExpired = document.getElementById('showExpiredToggle').checked;
            var expiredNotices = document.querySelectorAll('.expired-notice');
            
            expiredNotices.forEach(function(notice) {
                notice.style.display = showExpired ? 'block' : 'none';
            });
        }
        
        // Route form handling
        function clearRouteForm() {
            document.getElementById('routeFinderForm').reset();
            document.getElementById('routeResults').style.display = 'none';
            document.getElementById('printBtn').disabled = true;
            document.getElementById('shareBtn').disabled = true;
            document.getElementById('saveBtn').disabled = true;
        }
        
        // Simulate route finding
        document.getElementById('routeFinderForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            document.getElementById('routeResults').style.display = 'block';
            document.getElementById('routeOptions').innerHTML = `
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Finding the best routes...</p>
                </div>
            `;
            
            // Simulate API call delay
            setTimeout(function() {
                // Generate mock route options
                var startLocation = document.getElementById('startLocation').value;
                var destination = document.getElementById('destination').value;
                var preference = document.getElementById('routePreference').value;
                
                var routeOptions = [
                    {
                        name: 'Fastest Route',
                        time: '15 min',
                        distance: '5.2 km',
                        type: 'primary',
                        description: 'Uses main roads with minimal traffic lights'
                    },
                    {
                        name: 'Alternate Route',
                        time: '18 min',
                        distance: '4.8 km',
                        type: 'alternate',
                        description: 'Slightly longer but avoids known congestion areas'
                    },
                    {
                        name: 'Scenic Route',
                        time: '22 min',
                        distance: '6.1 km',
                        type: 'scenic',
                        description: 'Picturesque route through parks and waterfront'
                    }
                ];
                
                // Display route options
                var routeOptionsHTML = '';
                routeOptions.forEach(function(route, index) {
                    routeOptionsHTML += `
                        <div class="list-group-item route-card route-${route.type}">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">${route.name}</h5>
                                <small class="text-primary fw-bold">${route.time}</small>
                            </div>
                            <p class="mb-1">${route.description}</p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted">Distance: ${route.distance}</small>
                                <button class="btn btn-sm btn-outline-primary" onclick="selectRoute(${index})">
                                    Select Route
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                document.getElementById('routeOptions').innerHTML = routeOptionsHTML;
                
            }, 1500);
        });
        
        function selectRoute(index) {
            // Enable action buttons
            document.getElementById('printBtn').disabled = false;
            document.getElementById('shareBtn').disabled = false;
            document.getElementById('saveBtn').disabled = false;
            
            // Update map placeholder to show selected route
            document.querySelector('.map-placeholder').innerHTML = `
                <div class="text-center p-3">
                    <i class="bx bx-check-circle text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-2">Route Selected</h5>
                    <p>Route option ${index + 1} has been selected</p>
                    <small class="text-muted">Interactive map would display the selected route here</small>
                </div>
            `;
        }
        
        function printRoute() {
            alert('Print functionality would be implemented here');
        }
        
        function shareRoute() {
            alert('Share functionality would be implemented here');
        }
        
        function saveRoute() {
            alert('Save route functionality would be implemented here');
        }
        
        function viewRoute(routeId) {
            alert('View details for route ID: ' + routeId);
        }
        
        function editRoute(routeId) {
            alert('Edit route with ID: ' + routeId);
        }
        
        // Profile dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>