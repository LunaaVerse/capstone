-- Vehicle Routing & Diversion System Database
-- File: tcs.sql


-- Table for common destinations
CREATE TABLE destinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    category ENUM('government', 'commercial', 'healthcare', 'industrial', 'educational', 'residential') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for route information
CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    start_point VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    start_latitude DECIMAL(10, 8),
    start_longitude DECIMAL(11, 8),
    end_latitude DECIMAL(10, 8),
    end_longitude DECIMAL(11, 8),
    route_type ENUM('primary', 'alternate', 'emergency', 'seasonal') DEFAULT 'primary',
    estimated_time_minutes INT NOT NULL,
    distance_km DECIMAL(5, 2) NOT NULL,
    status ENUM('active', 'inactive', 'maintenance', 'closed') DEFAULT 'active',
    description TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Table for diversion notices
CREATE TABLE diversion_notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affected_road VARCHAR(255) NOT NULL,
    reason ENUM('roadwork', 'accident', 'event', 'weather', 'emergency', 'other') NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    alternative_route TEXT,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    additional_details TEXT,
    created_by INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Table for route waypoints
CREATE TABLE route_waypoints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    sequence INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_route_sequence (route_id, sequence)
);

-- Table for route traffic conditions
CREATE TABLE route_traffic_conditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    traffic_level ENUM('low', 'moderate', 'heavy', 'severe') DEFAULT 'moderate',
    average_speed_kmh DECIMAL(4, 1),
    delay_minutes INT DEFAULT 0,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
);

-- Table for user favorites
CREATE TABLE user_favorite_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    route_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_route (user_id, route_id)
);

-- Table for route history
CREATE TABLE route_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    start_point VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    route_id INT,
    travel_time_minutes INT,
    distance_km DECIMAL(5, 2),
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL
);

-- Table for system settings
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('map_refresh_interval', '300', 'Map refresh interval in seconds'),
('traffic_update_interval', '180', 'Traffic update interval in seconds'),
('default_route_type', 'primary', 'Default route type for suggestions'),
('max_alternate_routes', '3', 'Maximum number of alternate routes to show'),
('diversion_notice_duration', '24', 'Default duration for diversion notices in hours');

-- Create indexes for better performance
CREATE INDEX idx_destinations_category ON destinations(category);
CREATE INDEX idx_destinations_active ON destinations(is_active);
CREATE INDEX idx_routes_status ON routes(status);
CREATE INDEX idx_routes_type ON routes(route_type);
CREATE INDEX idx_diversion_notices_active ON diversion_notices(is_active);
CREATE INDEX idx_diversion_notices_dates ON diversion_notices(start_datetime, end_datetime);
CREATE INDEX idx_diversion_notices_priority ON diversion_notices(priority);
CREATE INDEX idx_traffic_conditions_reported ON route_traffic_conditions(reported_at);
CREATE INDEX idx_route_history_accessed ON route_history(accessed_at);