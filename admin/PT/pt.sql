-- Public Transport Sync Database
-- File: pts.sql
-- Created for LGU4 Traffic and Transport Management System

-- Vehicle types table
CREATE TABLE IF NOT EXISTS vehicle_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    capacity INT NOT NULL,
    avg_speed_kmh DECIMAL(5,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transport routes table
CREATE TABLE IF NOT EXISTS routes (
    route_id INT AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    start_location VARCHAR(100) NOT NULL,
    end_location VARCHAR(100) NOT NULL,
    total_distance_km DECIMAL(6,2) NOT NULL,
    estimated_duration_min INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Route stops table (intermediate stops along a route)
CREATE TABLE IF NOT EXISTS route_stops (
    stop_id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    stop_name VARCHAR(100) NOT NULL,
    stop_sequence INT NOT NULL,
    distance_from_start_km DECIMAL(6,2) NOT NULL,
    estimated_time_from_start_min INT NOT NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(route_id),
    UNIQUE KEY unique_route_stop_sequence (route_id, stop_sequence)
);

-- Vehicles table (physical transport units)
CREATE TABLE IF NOT EXISTS vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_number VARCHAR(20) NOT NULL UNIQUE,
    type_id INT NOT NULL,
    route_id INT NOT NULL,
    capacity INT NOT NULL,
    license_plate VARCHAR(15) NOT NULL UNIQUE,
    gps_device_id VARCHAR(50) UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    last_maintenance_date DATE,
    next_maintenance_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES vehicle_types(type_id),
    FOREIGN KEY (route_id) REFERENCES routes(route_id)
);

-- Route schedules table
CREATE TABLE IF NOT EXISTS route_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    frequency_min INT NOT NULL,
    operating_days SET('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    fare_amount DECIMAL(8,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(route_id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id)
);

-- Vehicle locations table (for real-time tracking)
CREATE TABLE IF NOT EXISTS vehicle_locations (
    location_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    current_speed_kmh DECIMAL(5,2),
    heading_degrees DECIMAL(5,2),
    nearest_stop_id INT,
    occupancy_percentage INT,
    traffic_condition ENUM('light', 'moderate', 'heavy', 'severe') DEFAULT 'moderate',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
    FOREIGN KEY (nearest_stop_id) REFERENCES route_stops(stop_id)
);

-- Estimated arrival times table
CREATE TABLE IF NOT EXISTS estimated_arrivals (
    arrival_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    stop_id INT NOT NULL,
    estimated_arrival_time TIMESTAMP NOT NULL,
    estimated_minutes INT NOT NULL,
    confidence_level ENUM('high', 'medium', 'low') DEFAULT 'medium',
    calculation_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
    FOREIGN KEY (stop_id) REFERENCES route_stops(stop_id)
);

-- Service announcements table
CREATE TABLE IF NOT EXISTS service_announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    announcement_type ENUM('info', 'warning', 'alert', 'update') DEFAULT 'info',
    affected_routes JSON, -- JSON array of route_ids
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Passenger statistics table
CREATE TABLE IF NOT EXISTS passenger_statistics (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    passenger_count INT NOT NULL,
    recorded_date DATE NOT NULL,
    recorded_time TIME NOT NULL,
    stop_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(route_id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
    FOREIGN KEY (stop_id) REFERENCES route_stops(stop_id)
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
CREATE INDEX idx_vehicle_locations_timestamp ON vehicle_locations(timestamp);
CREATE INDEX idx_vehicle_locations_vehicle ON vehicle_locations(vehicle_id, timestamp);
CREATE INDEX idx_estimated_arrivals_vehicle ON estimated_arrivals(vehicle_id, estimated_arrival_time);
CREATE INDEX idx_estimated_arrivals_stop ON estimated_arrivals(stop_id, estimated_arrival_time);
CREATE INDEX idx_route_schedules_route ON route_schedules(route_id, start_time);
CREATE INDEX idx_route_stops_route ON route_stops(route_id, stop_sequence);
