
-- Road categories table
CREATE TABLE IF NOT EXISTS road_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    priority_level INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Road segments table (individual road sections)
CREATE TABLE IF NOT EXISTS road_segments (
    segment_id INT AUTO_INCREMENT PRIMARY KEY,
    road_name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    start_location VARCHAR(100) NOT NULL,
    end_location VARCHAR(100) NOT NULL,
    length_km DECIMAL(6,2),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    is_monitored BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES road_categories(category_id)
);

-- Road status types table
CREATE TABLE IF NOT EXISTS road_status_types (
    status_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    severity_level INT DEFAULT 1,
    color_code VARCHAR(7) DEFAULT '#6c757d',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Road updates table (main table for status updates)
CREATE TABLE IF NOT EXISTS road_updates (
    update_id INT AUTO_INCREMENT PRIMARY KEY,
    segment_id INT NOT NULL,
    status_id INT NOT NULL,
    reported_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    reported_date DATE NOT NULL,
    reported_time TIME NOT NULL,
    estimated_duration VARCHAR(50),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    affected_areas TEXT,
    alternate_routes TEXT,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (segment_id) REFERENCES road_segments(segment_id),
    FOREIGN KEY (status_id) REFERENCES road_status_types(status_id),
    FOREIGN KEY (reported_by) REFERENCES users(user_id),
    FOREIGN KEY (verified_by) REFERENCES users(user_id)
);

-- Update attachments table (for images, documents, etc.)
CREATE TABLE IF NOT EXISTS update_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    update_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (update_id) REFERENCES road_updates(update_id),
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
);

-- Update history table (track changes to updates)
CREATE TABLE IF NOT EXISTS update_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    update_id INT NOT NULL,
    changed_by INT NOT NULL,
    change_description TEXT NOT NULL,
    old_values JSON,
    new_values JSON,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (update_id) REFERENCES road_updates(update_id),
    FOREIGN KEY (changed_by) REFERENCES users(user_id)
);

-- User notifications table
CREATE TABLE IF NOT EXISTS user_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    update_id INT NOT NULL,
    notification_type ENUM('new_update', 'status_change', 'verification', 'alert') DEFAULT 'new_update',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (update_id) REFERENCES road_updates(update_id)
);

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Statistics table (for dashboard metrics)
CREATE TABLE IF NOT EXISTS statistics_daily (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL,
    total_updates INT DEFAULT 0,
    open_roads INT DEFAULT 0,
    blocked_roads INT DEFAULT 0,
    maintenance_roads INT DEFAULT 0,
    verified_updates INT DEFAULT 0,
    unverified_updates INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_stat_date (stat_date)
);

-- Indexes for better performance
CREATE INDEX idx_road_updates_segment ON road_updates(segment_id, created_at);
CREATE INDEX idx_road_updates_status ON road_updates(status_id, created_at);
CREATE INDEX idx_road_updates_date ON road_updates(reported_date, reported_time);
CREATE INDEX idx_road_updates_verified ON road_updates(is_verified, created_at);
CREATE INDEX idx_notifications_user ON user_notifications(user_id, is_read, created_at);
CREATE INDEX idx_updates_active ON road_updates(is_active, created_at);
