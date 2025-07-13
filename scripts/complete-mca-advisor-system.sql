-- Forever Young Tours - Complete MCA and Advisor System Database Schema
-- This script creates all necessary tables and data for the MCA/Advisor system

-- First, let's update the existing tables to ensure compatibility

-- Add MCA and Advisor roles if they don't exist
INSERT IGNORE INTO roles (name, display_name, description) VALUES
('mca', 'Master Certified Advisor', 'Master Certified Advisor with recruitment capabilities'),
('certified_advisor', 'Certified Advisor', 'Certified Travel Advisor');

-- Create countries table for location management
CREATE TABLE IF NOT EXISTS countries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(3) NOT NULL UNIQUE,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample countries
INSERT IGNORE INTO countries (name, code, currency) VALUES
('Rwanda', 'RW', 'RWF'),
('Uganda', 'UG', 'UGX'),
('Kenya', 'KE', 'KES'),
('Tanzania', 'TZ', 'TZS'),
('United States', 'US', 'USD'),
('Canada', 'CA', 'CAD'),
('United Kingdom', 'GB', 'GBP'),
('Germany', 'DE', 'EUR'),
('France', 'FR', 'EUR'),
('Australia', 'AU', 'AUD');

-- Create MCA Agents table
CREATE TABLE IF NOT EXISTS mca_agents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    agent_code VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    country_id INT,
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
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL,
    INDEX idx_agent_code (agent_code),
    INDEX idx_status (status),
    INDEX idx_country (country_id)
);

-- Create Certified Advisors table
CREATE TABLE IF NOT EXISTS certified_advisors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    mca_id INT,
    advisor_code VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(50),
    city VARCHAR(50),
    certification_level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    certification_status ENUM('pending', 'certified', 'expired') DEFAULT 'pending',
    certification_date DATE,
    certification_expiry DATE,
    status ENUM('active', 'inactive', 'suspended', 'training', 'pending') DEFAULT 'pending',
    commission_rate DECIMAL(5,2) DEFAULT 15.00,
    total_sales DECIMAL(15,2) DEFAULT 0.00,
    total_commission DECIMAL(15,2) DEFAULT 0.00,
    training_completed BOOLEAN DEFAULT FALSE,
    training_score DECIMAL(5,2) DEFAULT 0.00,
    performance_rating DECIMAL(3,2) DEFAULT 0.00,
    experience_level ENUM('beginner', 'intermediate', 'experienced', 'expert') DEFAULT 'beginner',
    motivation TEXT,
    referral_source VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mca_id) REFERENCES mca_agents(id) ON DELETE SET NULL,
    INDEX idx_advisor_code (advisor_code),
    INDEX idx_status (status),
    INDEX idx_mca_id (mca_id),
    INDEX idx_certification_status (certification_status)
);

-- Create Training Modules table
CREATE TABLE IF NOT EXISTS advisor_training_modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content LONGTEXT,
    topics TEXT,
    order_sequence INT DEFAULT 0,
    duration_minutes INT DEFAULT 30,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    passing_score DECIMAL(5,2) DEFAULT 80.00,
    required BOOLEAN DEFAULT TRUE,
    prerequisites TEXT,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (order_sequence),
    INDEX idx_status (status),
    INDEX idx_required (required)
);

-- Create Training Progress table
CREATE TABLE IF NOT EXISTS advisor_training_progress (
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
    FOREIGN KEY (module_id) REFERENCES advisor_training_modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_advisor_module (advisor_id, module_id),
    INDEX idx_advisor_id (advisor_id),
    INDEX idx_module_id (module_id),
    INDEX idx_status (status)
);

-- Create MCA Commissions table
CREATE TABLE IF NOT EXISTS mca_commissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mca_id INT NOT NULL,
    booking_id INT,
    advisor_id INT,
    commission_amount DECIMAL(15,2) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL,
    booking_amount DECIMAL(15,2) NOT NULL,
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    description TEXT,
    processed_by INT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mca_id) REFERENCES mca_agents(id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES certified_advisors(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_mca_id (mca_id),
    INDEX idx_booking_id (booking_id),
    INDEX idx_status (status)
);

-- Create Advisor Commissions table
CREATE TABLE IF NOT EXISTS advisor_commissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    advisor_id INT NOT NULL,
    booking_id INT,
    commission_amount DECIMAL(15,2) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL,
    booking_amount DECIMAL(15,2) NOT NULL,
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    description TEXT,
    processed_by INT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (advisor_id) REFERENCES certified_advisors(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_advisor_id (advisor_id),
    INDEX idx_booking_id (booking_id),
    INDEX idx_status (status)
);

-- Update bookings table to include MCA and Advisor references
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS mca_id INT,
ADD COLUMN IF NOT EXISTS advisor_id INT,
ADD INDEX IF NOT EXISTS idx_mca_id (mca_id),
ADD INDEX IF NOT EXISTS idx_advisor_id (advisor_id);

-- Add foreign key constraints if they don't exist
SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
               WHERE TABLE_NAME = 'bookings' AND CONSTRAINT_NAME = 'fk_bookings_mca') = 0,
              'ALTER TABLE bookings ADD CONSTRAINT fk_bookings_mca FOREIGN KEY (mca_id) REFERENCES mca_agents(id) ON DELETE SET NULL',
              'SELECT "FK already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
               WHERE TABLE_NAME = 'bookings' AND CONSTRAINT_NAME = 'fk_bookings_advisor') = 0,
              'ALTER TABLE bookings ADD CONSTRAINT fk_bookings_advisor FOREIGN KEY (advisor_id) REFERENCES certified_advisors(id) ON DELETE SET NULL',
              'SELECT "FK already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Insert comprehensive training modules
INSERT IGNORE INTO advisor_training_modules (title, description, content, topics, order_sequence, duration_minutes, difficulty_level, passing_score, required) VALUES
('Welcome to Forever Young Tours', 
 'Introduction to our company, mission, and values', 
 'Welcome to Forever Young Tours! In this module, you will learn about our company history, mission statement, core values, and what makes us unique in the travel industry. We focus on agro-tourism, cultural experiences, and sustainable travel practices.',
 'Company History,Mission & Vision,Core Values,Agro-Tourism Focus,Sustainability',
 1, 45, 'beginner', 80.00, TRUE),

('Understanding Our Tour Products', 
 'Comprehensive overview of all tour packages and destinations', 
 'Learn about our complete range of tour packages including agro-tourism experiences, cultural tours, adventure travel, and luxury packages. Understand pricing structures, seasonal variations, and special offerings.',
 'Tour Categories,Agro-Tourism,Cultural Tours,Adventure Travel,Pricing Structure,Seasonal Packages',
 2, 60, 'beginner', 85.00, TRUE),

('Sales Techniques and Customer Engagement', 
 'Master the art of consultative selling and customer service', 
 'Develop your sales skills with proven techniques for engaging customers, understanding their needs, handling objections, and closing sales. Learn about different customer types and how to tailor your approach.',
 'Consultative Selling,Customer Psychology,Objection Handling,Closing Techniques,Customer Types',
 3, 90, 'intermediate', 80.00, TRUE),

('Booking System and Procedures', 
 'Complete guide to our booking platform and processes', 
 'Step-by-step training on using our booking system, processing payments, handling modifications, managing customer records, and following proper procedures for different types of bookings.',
 'Booking Platform,Payment Processing,Modifications,Customer Records,Procedures',
 4, 75, 'intermediate', 90.00, TRUE),

('Commission Structure and Earnings', 
 'Understanding how you earn and get paid', 
 'Learn about our commission structure, how earnings are calculated, payment schedules, bonus opportunities, and performance incentives. Understand the difference between base commissions and performance bonuses.',
 'Commission Rates,Payment Schedule,Bonus Structure,Performance Incentives,Earnings Calculation',
 5, 30, 'beginner', 75.00, TRUE),

('Marketing and Promotion Strategies', 
 'Effective ways to promote tours and build your client base', 
 'Discover proven marketing strategies, social media best practices, referral programs, and how to build a sustainable client base. Learn to use our marketing materials effectively.',
 'Marketing Strategies,Social Media,Referral Programs,Client Building,Marketing Materials',
 6, 60, 'intermediate', 80.00, FALSE),

('Customer Service Excellence', 
 'Providing exceptional service throughout the customer journey', 
 'Learn how to provide outstanding customer service from initial inquiry to post-trip follow-up. Handle complaints, manage expectations, and create memorable experiences.',
 'Customer Service,Complaint Handling,Expectation Management,Follow-up,Experience Creation',
 7, 45, 'intermediate', 85.00, FALSE),

('Advanced Sales Techniques', 
 'Advanced strategies for experienced advisors', 
 'Advanced selling techniques including upselling, cross-selling, package customization, and handling high-value clients. Learn about luxury market dynamics and premium service delivery.',
 'Upselling,Cross-selling,Package Customization,Luxury Market,Premium Service',
 8, 75, 'advanced', 85.00, FALSE);

-- Insert permissions for MCA and Advisor management
INSERT IGNORE INTO permissions (name, display_name, category, description) VALUES
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

-- Assign permissions to admin role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'admin' 
AND p.name IN ('mcas.view', 'mcas.create', 'mcas.edit', 'mcas.delete', 
               'advisors.view', 'advisors.create', 'advisors.edit', 'advisors.delete',
               'training.view', 'training.create', 'training.edit', 'training.delete',
               'commissions.view', 'commissions.create', 'commissions.edit', 'commissions.delete');

-- Assign permissions to MCA role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'mca' 
AND p.name IN ('dashboard.view', 'bookings.view', 'bookings.create', 'bookings.edit',
               'advisors.view', 'advisors.create', 'commissions.view', 'training.view');

-- Assign permissions to advisor role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'certified_advisor' 
AND p.name IN ('dashboard.view', 'bookings.view', 'bookings.create', 
               'training.view', 'commissions.view');

-- Create sample MCA for testing
INSERT IGNORE INTO users (first_name, last_name, email, username, password_hash, role_id, status) 
SELECT 'John', 'Smith', 'mca@foreveryoungtours.com', 'mca_john', 
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
       r.id, 'active'
FROM roles r WHERE r.name = 'mca' LIMIT 1;

-- Create MCA agent record for the sample user
INSERT IGNORE INTO mca_agents (user_id, agent_code, first_name, last_name, email, phone, country_id, status, commission_rate)
SELECT u.id, 'MCA001', 'John', 'Smith', 'mca@foreveryoungtours.com', '+250788123456', c.id, 'active', 10.00
FROM users u, countries c
WHERE u.email = 'mca@foreveryoungtours.com' AND c.code = 'RW' LIMIT 1;

-- Create sample advisor for testing
INSERT IGNORE INTO users (first_name, first_name,last_name, email, username, password_hash, role_id, status) 
SELECT 'Jane', 'Doe', 'advisor@foreveryoungtours.com', 'advisor_jane', 
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
       r.id, 'active'
FROM roles r WHERE r.name = 'certified_advisor' LIMIT 1;

-- Create advisor record for the sample user
INSERT IGNORE INTO certified_advisors (user_id, mca_id, advisor_code, first_name, last_name, email, phone, country, city, status, commission_rate)
SELECT u.id, ma.id, 'ADV001', 'Jane', 'Doe', 'advisor@foreveryoungtours.com', '+250788654321', 'Rwanda', 'Kigali', 'active', 15.00
FROM users u, mca_agents ma
WHERE u.email = 'advisor@foreveryoungtours.com' AND ma.agent_code = 'MCA001' LIMIT 1;
