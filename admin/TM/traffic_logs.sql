-- Traffic Monitoring Database
-- File: tm.sql
-- Created for LGU4 Traffic and Transport Management System

-- Camera locations table
CREATE TABLE IF NOT EXISTS camera_locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    address TEXT,
    zone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    installation_date DATE,
    last_maintenance_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- CCTV cameras table
CREATE TABLE IF NOT EXISTS cctv_cameras (
    camera_id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    camera_name VARCHAR(100) NOT NULL,
    camera_type ENUM('fixed', 'ptz', 'thermal', 'license_plate') DEFAULT 'fixed',
    ip_address VARCHAR(45),
    rtsp_url VARCHAR(500),
    resolution VARCHAR(20),
    status ENUM('online', 'offline', 'maintenance') DEFAULT 'online',
    is_recording BOOLEAN DEFAULT TRUE,
    storage_days INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES camera_locations(location_id)
);

-- Traffic status types table
CREATE TABLE IF NOT EXISTS traffic_status_types (
    status_id INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    color_code VARCHAR(7) DEFAULT '#6c757d',
    min_speed_kmh INT,
    max_speed_kmh INT,
    severity_level INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Traffic logs table (main table for traffic monitoring data)
CREATE TABLE IF NOT EXISTS traffic_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    camera_id INT NOT NULL,
    status_id INT NOT NULL,
    reported_by INT NOT NULL,
    average_speed_kmh DECIMAL(5,2),
    vehicle_count INT,
    congestion_level ENUM('low', 'medium', 'high') DEFAULT 'low',
    incident_detected BOOLEAN DEFAULT FALSE,
    incident_type VARCHAR(100),
    weather_conditions VARCHAR(100),
    notes TEXT,
    log_date DATE NOT NULL,
    log_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES camera_locations(location_id),
    FOREIGN KEY (camera_id) REFERENCES cctv_cameras(camera_id),
    FOREIGN KEY (status_id) REFERENCES traffic_status_types(status_id),
    FOREIGN KEY (reported_by) REFERENCES users(user_id)
);

-- Incident reports table (for detailed incident tracking)
CREATE TABLE IF NOT EXISTS incident_reports (
    incident_id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    incident_type VARCHAR(100) NOT NULL,
    severity ENUM('minor', 'moderate', 'severe', 'critical') DEFAULT 'minor',
    description TEXT,
    vehicles_involved INT DEFAULT 0,
    injuries INT DEFAULT 0,
    fatalities INT DEFAULT 0,
    response_time_minutes INT,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP NULL,
    resolved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (log_id) REFERENCES traffic_logs(log_id),
    FOREIGN KEY (resolved_by) REFERENCES users(user_id)
);

-- Traffic statistics table (for aggregated data)
CREATE TABLE IF NOT EXISTS traffic_statistics (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    stat_date DATE NOT NULL,
    stat_hour INT NOT NULL,
    avg_speed_kmh DECIMAL(5,2),
    total_vehicles INT,
    peak_hour BOOLEAN DEFAULT FALSE,
    congestion_percentage DECIMAL(5,2),
    incident_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES camera_locations(location_id),
    UNIQUE KEY unique_location_date_hour (location_id, stat_date, stat_hour)
);

-- System alerts table
CREATE TABLE IF NOT EXISTS system_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('congestion', 'incident', 'camera_offline', 'maintenance', 'system') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    location_id INT,
    camera_id INT,
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by INT,
    acknowledged_at TIMESTAMP NULL,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES camera_locations(location_id),
    FOREIGN KEY (camera_id) REFERENCES cctv_cameras(camera_id),
    FOREIGN KEY (acknowledged_by) REFERENCES users(user_id),
    FOREIGN KEY (resolved_by) REFERENCES users(user_id)
);

-- Maintenance logs table
CREATE TABLE IF NOT EXISTS maintenance_logs (
    maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
    camera_id INT NOT NULL,
    maintenance_type ENUM('routine', 'repair', 'upgrade', 'cleaning') DEFAULT 'routine',
    description TEXT,
    performed_by INT NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    duration_minutes INT,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (camera_id) REFERENCES cctv_cameras(camera_id),
    FOREIGN KEY (performed_by) REFERENCES users(user_id)
);

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Indexes for better performance
CREATE INDEX idx_traffic_logs_date ON traffic_logs(log_date, log_time);
CREATE INDEX idx_traffic_logs_location ON traffic_logs(location_id, created_at);
CREATE INDEX idx_traffic_logs_status ON traffic_logs(status_id, created_at);
CREATE INDEX idx_incident_reports_severity ON incident_reports(severity, created_at);
CREATE INDEX idx_traffic_stats_date ON traffic_statistics(stat_date, stat_hour);
CREATE INDEX idx_system_alerts_type ON system_alerts(alert_type, created_at);
CREATE INDEX idx_camera_status ON cctv_cameras(status, is_active);

-- Insert default system settings
