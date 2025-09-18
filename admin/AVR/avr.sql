-- Accident & Violation Reports Database
-- File: avr.sql


-- Reports table (main table for all reports)
CREATE TABLE IF NOT EXISTS reports (
    report_id VARCHAR(20) PRIMARY KEY, -- Format: RPT + timestamp
    user_id INT NOT NULL,
    report_type ENUM('accident', 'violation', 'hazard', 'other') NOT NULL,
    priority_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    location VARCHAR(255) NOT NULL,
    report_date DATE NOT NULL,
    report_time TIME NOT NULL,
    description TEXT NOT NULL,
    image LONGBLOB,
    status ENUM('pending', 'verified', 'invalid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
);

-- Report status history table (to track changes)
CREATE TABLE IF NOT EXISTS report_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    report_id VARCHAR(20) NOT NULL,
    old_status ENUM('pending', 'verified', 'invalid') NULL,
    new_status ENUM('pending', 'verified', 'invalid') NOT NULL,
    changed_by INT NOT NULL,
    change_reason TEXT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(report_id),
    FOREIGN KEY (changed_by) REFERENCES users(user_id)
);

-- Admin actions log table
CREATE TABLE IF NOT EXISTS admin_actions (
    action_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    report_id VARCHAR(20) NOT NULL,
    action_type ENUM('status_change', 'note_added', 'image_verified') NOT NULL,
    action_details TEXT,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id),
    FOREIGN KEY (report_id) REFERENCES reports(report_id)
);

-- Indexes for better performance
CREATE INDEX idx_report_status ON reports(status);
CREATE INDEX idx_report_type ON reports(report_type);
CREATE INDEX idx_report_date ON reports(report_date);
CREATE INDEX idx_report_priority ON reports(priority_level);
CREATE INDEX idx_user_reports ON reports(user_id);

