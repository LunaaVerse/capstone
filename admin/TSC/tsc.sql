-- Traffic Signal Control System Database
-- File: tcs.sql

CREATE DATABASE IF NOT EXISTS traffic_signal_control;
USE traffic_signal_control;

-- Table for storing intersection information
CREATE TABLE intersections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    status ENUM('online', 'offline', 'maintenance') DEFAULT 'online',
    current_signal ENUM('red', 'yellow', 'green') DEFAULT 'red',
    next_change_time INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for signal timing configurations
CREATE TABLE signal_timings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intersection_id INT NOT NULL,
    red_duration INT NOT NULL DEFAULT 30,
    yellow_duration INT NOT NULL DEFAULT 5,
    green_duration INT NOT NULL DEFAULT 45,
    time_period ENUM(
        'peak_morning', 
        'off_peak_morning', 
        'midday', 
        'off_peak_afternoon', 
        'peak_evening', 
        'night'
    ) NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (intersection_id) REFERENCES intersections(id) ON DELETE CASCADE,
    UNIQUE KEY unique_intersection_period (intersection_id, time_period)
);

-- Table for signal schedules
CREATE TABLE signal_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intersection_id INT NOT NULL,
    time_period ENUM(
        'peak_morning', 
        'off_peak_morning', 
        'midday', 
        'off_peak_afternoon', 
        'peak_evening', 
        'night'
    ) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    red_duration INT NOT NULL,
    yellow_duration INT NOT NULL,
    green_duration INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (intersection_id) REFERENCES intersections(id) ON DELETE CASCADE
);

-- Table for logging timing changes
CREATE TABLE timing_changes_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intersection_id INT NOT NULL,
    changed_by VARCHAR(100) NOT NULL,
    change_description TEXT NOT NULL,
    red_duration_before INT,
    red_duration_after INT,
    yellow_duration_before INT,
    yellow_duration_after INT,
    green_duration_before INT,
    green_duration_after INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (intersection_id) REFERENCES intersections(id) ON DELETE CASCADE
);

-- Table for system users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'operator') DEFAULT 'operator',
    avatar_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for system settings
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for active signal states
CREATE TABLE active_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intersection_id INT NOT NULL UNIQUE,
    current_signal ENUM('red', 'yellow', 'green') DEFAULT 'red',
    timer_value INT DEFAULT 30,
    is_auto_mode BOOLEAN DEFAULT TRUE,
    last_change TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (intersection_id) REFERENCES intersections(id) ON DELETE CASCADE
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('default_red_duration', '30', 'Default duration for red signal in seconds'),
('default_yellow_duration', '5', 'Default duration for yellow signal in seconds'),
('default_green_duration', '45', 'Default duration for green signal in seconds'),
('auto_mode_default', '1', 'Default mode for signals (1=auto, 0=manual)');

-- Create indexes for better performance
CREATE INDEX idx_intersections_status ON intersections(status);
CREATE INDEX idx_signal_timings_period ON signal_timings(time_period);
CREATE INDEX idx_signal_schedules_active ON signal_schedules(is_active);
CREATE INDEX idx_timing_changes_time ON timing_changes_log(created_at);
CREATE INDEX idx_active_signals_mode ON active_signals(is_auto_mode);