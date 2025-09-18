-- Permit & Ticketing System Database
-- File: pats.sql
-- Created for LGU4 Traffic and Transport Management System

CREATE DATABASE IF NOT EXISTS permit_ticketing_db;
USE permit_ticketing_db;

-- Users table (for citizens and administrators)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    address TEXT,
    contact_number VARCHAR(20),
    role ENUM('admin', 'officer', 'citizen') DEFAULT 'citizen',
    profile_image LONGBLOB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Permit types table
CREATE TABLE IF NOT EXISTS permit_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    requirements TEXT,
    processing_fee DECIMAL(10, 2) DEFAULT 0.00,
    validity_days INT DEFAULT 365,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Permit applications table
CREATE TABLE IF NOT EXISTS permit_applications (
    application_id VARCHAR(20) PRIMARY KEY, -- Format: PER-XXXXXX
    user_id INT NOT NULL,
    permit_type_id INT NOT NULL,
    event_purpose VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    additional_info TEXT,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    rejection_reason TEXT,
    permit_fee DECIMAL(10, 2) DEFAULT 0.00,
    payment_status ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
    payment_date TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (permit_type_id) REFERENCES permit_types(type_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
);

-- Violation types table
CREATE TABLE IF NOT EXISTS violation_types (
    violation_id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    fine_amount DECIMAL(10, 2) NOT NULL,
    points INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tickets table
CREATE TABLE IF NOT EXISTS tickets (
    ticket_id VARCHAR(20) PRIMARY KEY, -- Format: TKT-XXXXXX
    violator_name VARCHAR(100) NOT NULL,
    violator_contact VARCHAR(20),
    violator_address TEXT,
    violation_type_id INT NOT NULL,
    location VARCHAR(255) NOT NULL,
    violation_date DATE NOT NULL,
    violation_time TIME NOT NULL,
    issued_by INT NOT NULL, -- Officer who issued the ticket
    fine_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('unpaid', 'paid', 'contested', 'dismissed', 'warrant') DEFAULT 'unpaid',
    notes TEXT,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    due_date DATE,
    contestation_details TEXT,
    hearing_date DATE,
    hearing_outcome ENUM('upheld', 'dismissed', 'reduced') NULL,
    FOREIGN KEY (violation_type_id) REFERENCES violation_types(violation_id),
    FOREIGN KEY (issued_by) REFERENCES users(user_id)
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    item_type ENUM('permit', 'ticket') NOT NULL,
    item_id VARCHAR(20) NOT NULL, -- application_id or ticket_id
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'debit_card', 'online_banking', 'mobile_payment') NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    receipt_data TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Audit log table
CREATE TABLE IF NOT EXISTS audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id VARCHAR(20) NOT NULL,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Indexes for better performance
CREATE INDEX idx_permit_status ON permit_applications(status);
CREATE INDEX idx_permit_user ON permit_applications(user_id);
CREATE INDEX idx_ticket_status ON tickets(status);
CREATE INDEX idx_ticket_violator ON tickets(violator_name);
CREATE INDEX idx_ticket_officer ON tickets(issued_by);
CREATE INDEX idx_payment_user ON payments(user_id);
CREATE INDEX idx_payment_item ON payments(item_type, item_id);

