-- Forever Young Tours - MCA and Advisor Management Tables
-- Run this script to create the necessary tables for MCA and Advisor functionality

-- Create MCA (Master Certified Advisor) table
CREATE TABLE IF NOT EXISTS mcas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    mca_code VARCHAR(20) UNIQUE NOT NULL,
    license_number VARCHAR(50),
    certification_date DATE,
    certification_expiry DATE,
    status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'pending',
    commission_rate DECIMAL(5,2) DEFAULT 10.00,
    total_sales DECIMAL(15,2) DEFAULT 0.00,
    total_commission DECIMAL(15,2) DEFAULT 0.00,
    recruits_count INT DEFAULT 0,
    performance_rating DECIMAL(3,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_mca_code (mca_code),
    INDEX idx_mca_status (status)
);

-- Create Certified Advisors table
CREATE TABLE IF NOT EXISTS certified_advisors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    mca_id INT,
    advisor_code VARCHAR(20) UNIQUE NOT NULL,
    certification_level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    certification_date DATE,
    certification_expiry DATE,
    status ENUM('active', 'inactive', 'suspended', 'training', 'pending') DEFAULT 'pending',
    commission_rate DECIMAL(5,2) DEFAULT 5.00,
    total_sales DECIMAL(15,2) DEFAULT 0.00,
    total_commission DECIMAL(15,2) DEFAULT 0.00,
    training_completed BOOLEAN DEFAULT FALSE,
    training_score DECIMAL(5,2) DEFAULT 0.00,
    performance_rating DECIMAL(3,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mca_id) REFERENCES mcas(id) ON DELETE SET NULL,
    INDEX idx_advisor_code (advisor_code),
    INDEX idx_advisor_status (status),
    INDEX idx_mca_id (mca_id)
);

-- Create Training Modules table
CREATE TABLE IF NOT EXISTS training_modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content LONGTEXT,
    module_order INT DEFAULT 0,
    duration_minutes INT DEFAULT 30,
    passing_score DECIMAL(5,2) DEFAULT 80.00,
    is_required BOOLEAN DEFAULT TRUE,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_module_order (module_order),
    INDEX idx_status (status)
);

-- Create Training Progress table
CREATE TABLE IF NOT EXISTS training_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    advisor_id INT NOT NULL,
    module_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed', 'failed') DEFAULT 'not_started',
    score DECIMAL(5,2) DEFAULT 0.00,
    attempts INT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (advisor_id) REFERENCES certified_advisors(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES training_modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_advisor_module (advisor_id, module_id),
    INDEX idx_advisor_id (advisor_id),
    INDEX idx_module_id (module_id)
);

-- Create Commission Transactions table
CREATE TABLE IF NOT EXISTS commission_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT,
    mca_id INT,
    advisor_id INT,
    transaction_type ENUM('sale', 'bonus', 'penalty', 'adjustment') DEFAULT 'sale',
    amount DECIMAL(15,2) NOT NULL,
    commission_rate DECIMAL(5,2),
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    description TEXT,
    processed_by INT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mca_id) REFERENCES mcas(id) ON DELETE SET NULL,
    FOREIGN KEY (advisor_id) REFERENCES certified_advisors(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking_id (booking_id),
    INDEX idx_mca_id (mca_id),
    INDEX idx_advisor_id (advisor_id),
    INDEX idx_status (status)
);

-- Create MCA Recruits table (tracks which advisors were recruited by which MCA)
CREATE TABLE IF NOT EXISTS mca_recruits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mca_id INT NOT NULL,
    advisor_id INT NOT NULL,
    recruited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'transferred') DEFAULT 'active',
    notes TEXT,
    FOREIGN KEY (mca_id) REFERENCES mcas(id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES certified_advisors(id) ON DELETE CASCADE,
    UNIQUE KEY unique_mca_advisor (mca_id, advisor_id),
    INDEX idx_mca_id (mca_id),
    INDEX idx_advisor_id (advisor_id)
);

-- Insert default training modules
INSERT INTO training_modules (title, description, content, module_order, duration_minutes, passing_score, is_required) VALUES
('Introduction to Forever Young Tours', 'Overview of company history, mission, and values', 'Welcome to Forever Young Tours! This module covers our company background, mission statement, core values, and what makes us unique in the travel industry.', 1, 45, 80.00, TRUE),
('Tour Products and Services', 'Comprehensive overview of all tour packages and services', 'Learn about our complete range of tour packages, destinations, pricing structures, and special services we offer to our clients.', 2, 60, 85.00, TRUE),
('Sales Techniques and Customer Service', 'Best practices for selling tours and providing excellent customer service', 'Master the art of consultative selling, handling objections, and providing exceptional customer service throughout the customer journey.', 3, 90, 80.00, TRUE),
('Booking Systems and Procedures', 'How to use our booking system and follow proper procedures', 'Step-by-step guide to using our booking platform, processing payments, handling modifications, and managing customer records.', 4, 75, 90.00, TRUE),
('Commission Structure and Payments', 'Understanding how commissions work and payment schedules', 'Learn about our commission structure, how earnings are calculated, payment schedules, and bonus opportunities.', 5, 30, 75.00, TRUE);

-- Update roles table to include MCA and Advisor roles
INSERT INTO roles (name, display_name, description, permissions) VALUES
('mca', 'Master Certified Advisor', 'Master Certified Advisor with recruitment and management capabilities', '["dashboard.view", "bookings.view", "bookings.create", "bookings.edit", "advisors.view", "advisors.create", "commissions.view", "training.view"]'),
('advisor', 'Certified Advisor', 'Certified Travel Advisor', '["dashboard.view", "bookings.view", "bookings.create", "training.view", "commissions.view"]');

-- Add permissions for MCA and Advisor management
INSERT INTO permissions (name, display_name, category, description) VALUES
('mcas.view', 'View MCAs', 'MCA Management', 'View Master Certified Advisors'),
('mcas.create', 'Create MCAs', 'MCA Management', 'Create new Master Certified Advisors'),
('mcas.edit', 'Edit MCAs', 'MCA Management', 'Edit Master Certified Advisor details'),
('mcas.delete', 'Delete MCAs', 'MCA Management', 'Delete Master Certified Advisors'),
('advisors.view', 'View Advisors', 'Advisor Management', 'View Certified Advisors'),
('advisors.create', 'Create Advisors', 'Advisor Management', 'Create new Certified Advisors'),
('advisors.edit', 'Edit Advisors', 'Advisor Management', 'Edit Certified Advisor details'),
('advisors.delete', 'Delete Advisors', 'Advisor Management', 'Delete Certified Advisors'),
('training.view', 'View Training', 'Training Management', 'View training modules and progress'),
('training.create', 'Create Training', 'Training Management', 'Create training modules'),
('training.edit', 'Edit Training', 'Training Management', 'Edit training modules'),
('training.delete', 'Delete Training', 'Training Management', 'Delete training modules'),
('commissions.view', 'View Commissions', 'Commission Management', 'View commission transactions'),
('commissions.create', 'Create Commissions', 'Commission Management', 'Create commission transactions'),
('commissions.edit', 'Edit Commissions', 'Commission Management', 'Edit commission transactions'),
('commissions.delete', 'Delete Commissions', 'Commission Management', 'Delete commission transactions');
