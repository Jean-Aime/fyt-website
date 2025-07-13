-- Forever Young Tours - Updated MCA and Advisor System Database Schema
-- Based on the detailed specifications provided

-- Drop existing tables to rebuild with correct structure
DROP TABLE IF EXISTS advisor_training_progress;
DROP TABLE IF EXISTS advisor_commissions;
DROP TABLE IF EXISTS mca_commissions;
DROP TABLE IF EXISTS certified_advisors;
DROP TABLE IF EXISTS mca_agents;
DROP TABLE IF EXISTS advisor_training_modules;

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
('Australia', 'AU', 'AUD'),
('South Africa', 'ZA', 'ZAR'),
('Nigeria', 'NG', 'NGN'),
('Ghana', 'GH', 'GHS'),
('India', 'IN', 'INR'),
('China', 'CN', 'CNY');

-- Create MCA Agents table (Marketing & Client Advisors - Country Level Leaders)
CREATE TABLE mca_agents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    mca_code VARCHAR(20) UNIQUE NOT NULL,
    tracking_code VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    country_id INT,
    country_code VARCHAR(3),
    region_city VARCHAR(100),
    date_of_birth DATE,
    place_of_birth VARCHAR(100),
    mailing_address TEXT,
    
    -- KYC Information
    id_type VARCHAR(50),
    id_number VARCHAR(100),
    issuing_authority VARCHAR(100),
    id_expiration_date DATE,
    taxpayer_id VARCHAR(100),
    proof_of_address_provided BOOLEAN DEFAULT FALSE,
    
    -- Professional Information
    previous_experience TEXT,
    how_heard_about_fyt TEXT,
    
    -- Status and Performance
    status ENUM('pending', 'active', 'inactive', 'suspended') DEFAULT 'pending',
    certification_date DATE,
    certification_expiry DATE,
    
    -- Goals and Targets
    advisor_recruitment_target INT DEFAULT 100,
    current_advisor_count INT DEFAULT 0,
    monthly_sales_target DECIMAL(15,2) DEFAULT 0.00,
    
    -- Performance Metrics
    total_sales DECIMAL(15,2) DEFAULT 0.00,
    total_commission DECIMAL(15,2) DEFAULT 0.00,
    performance_rating DECIMAL(3,2) DEFAULT 0.00,
    
    -- Registration and Payment
    registration_fee_paid BOOLEAN DEFAULT FALSE,
    registration_fee_amount DECIMAL(10,2) DEFAULT 59.00,
    registration_payment_date DATE,
    annual_renewal_date DATE,
    
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL,
    INDEX idx_mca_code (mca_code),
    INDEX idx_tracking_code (tracking_code),
    INDEX idx_status (status),
    INDEX idx_country (country_id)
);

-- Create Certified Advisors table with proper rank structure
CREATE TABLE certified_advisors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    mca_id INT,
    advisor_code VARCHAR(20) UNIQUE NOT NULL,
    tracking_number VARCHAR(50) UNIQUE NOT NULL,
    
    -- Personal Information
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(50),
    country_code VARCHAR(3),
    region_city VARCHAR(100),
    date_of_birth DATE,
    place_of_birth VARCHAR(100),
    mailing_address TEXT,
    
    -- KYC Information
    id_type VARCHAR(50),
    id_number VARCHAR(100),
    issuing_authority VARCHAR(100),
    id_expiration_date DATE,
    taxpayer_id VARCHAR(100),
    proof_of_address_provided BOOLEAN DEFAULT FALSE,
    
    -- Professional Information
    previous_experience TEXT,
    how_heard_about_fyt TEXT,
    
    -- Advisor Rank and Level
    advisor_rank ENUM('certified_advisor', 'senior_advisor', 'executive_advisor') DEFAULT 'certified_advisor',
    certification_status ENUM('pending', 'certified', 'expired') DEFAULT 'pending',
    certification_date DATE,
    certification_expiry DATE,
    
    -- Commission Rates based on rank
    commission_rate DECIMAL(5,2) DEFAULT 30.00, -- 30% for Certified Advisor
    level_2_override_rate DECIMAL(5,2) DEFAULT 10.00,
    level_3_override_rate DECIMAL(5,2) DEFAULT 5.00,
    
    -- Status and Performance
    status ENUM('pending', 'active', 'inactive', 'suspended', 'training') DEFAULT 'pending',
    total_sales DECIMAL(15,2) DEFAULT 0.00,
    total_commission DECIMAL(15,2) DEFAULT 0.00,
    performance_rating DECIMAL(3,2) DEFAULT 0.00,
    
    -- Training and Certification
    training_completed BOOLEAN DEFAULT FALSE,
    training_score DECIMAL(5,2) DEFAULT 0.00,
    fyt_academy_completed BOOLEAN DEFAULT FALSE,
    jitsi_sessions_attended INT DEFAULT 0,
    
    -- Registration and Payment
    registration_fee_paid BOOLEAN DEFAULT FALSE,
    registration_fee_amount DECIMAL(10,2) DEFAULT 59.00,
    registration_payment_date DATE,
    annual_renewal_date DATE,
    
    -- Network Building
    level_2_advisors_count INT DEFAULT 0,
    level_3_advisors_count INT DEFAULT 0,
    
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mca_id) REFERENCES mca_agents(id) ON DELETE SET NULL,
    INDEX idx_advisor_code (advisor_code),
    INDEX idx_tracking_number (tracking_number),
    INDEX idx_status (status),
    INDEX idx_mca_id (mca_id),
    INDEX idx_advisor_rank (advisor_rank)
);

-- Create Training Modules table for FYT Academy
CREATE TABLE advisor_training_modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content LONGTEXT,
    module_type ENUM('onboarding', 'product_knowledge', 'sales_training', 'cultural_training', 'advanced') DEFAULT 'onboarding',
    topics TEXT,
    order_sequence INT DEFAULT 0,
    duration_minutes INT DEFAULT 30,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    passing_score DECIMAL(5,2) DEFAULT 80.00,
    required BOOLEAN DEFAULT TRUE,
    prerequisites TEXT,
    
    -- FYT Specific Content
    tour_categories TEXT, -- Agro-Tourism, Cultural Tours, Adventure Travel, etc.
    brand_guidelines TEXT,
    service_standards TEXT,
    
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_order (order_sequence),
    INDEX idx_status (status),
    INDEX idx_required (required),
    INDEX idx_module_type (module_type)
);

-- Create Training Progress table
CREATE TABLE advisor_training_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    advisor_id INT NOT NULL,
    module_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed', 'failed') DEFAULT 'not_started',
    score DECIMAL(5,2) DEFAULT 0.00,
    attempts INT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    jitsi_session_attended BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (advisor_id) REFERENCES certified_advisors(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES advisor_training_modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_advisor_module (advisor_id, module_id),
    INDEX idx_advisor_id (advisor_id),
    INDEX idx_module_id (module_id),
    INDEX idx_status (status)
);

-- Create Commission Transactions table (unified for both MCAs and Advisors)
CREATE TABLE commission_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    transaction_type ENUM('mca_override', 'mca_performance_bonus', 'advisor_direct_sales', 
                         'advisor_level2_override', 'advisor_level3_override', 
                         'advisor_performance_bonus', 'ambassador_bonus') NOT NULL,
    
    -- Recipient Information
    mca_id INT NULL,
    advisor_id INT NULL,
    
    -- Commission Calculation
    gross_commission DECIMAL(15,2) NOT NULL,
    net_commission_fund DECIMAL(15,2) NOT NULL, -- After deductions
    commission_percentage DECIMAL(5,2) NOT NULL,
    commission_amount DECIMAL(15,2) NOT NULL,
    
    -- Booking Details
    booking_amount DECIMAL(15,2) NOT NULL,
    tour_id INT,
    tour_date DATE,
    
    -- Status and Processing
    status ENUM('pending', 'approved', 'paid', 'cancelled', 'on_hold') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),
    processed_by INT,
    processed_at TIMESTAMP NULL,
    payment_date DATE NULL,
    
    -- Monthly Processing
    commission_month VARCHAR(7), -- YYYY-MM format
    payment_due_date DATE, -- 20th of following month
    
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (mca_id) REFERENCES mca_agents(id) ON DELETE SET NULL,
    FOREIGN KEY (advisor_id) REFERENCES certified_advisors(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_booking_id (booking_id),
    INDEX idx_mca_id (mca_id),
    INDEX idx_advisor_id (advisor_id),
    INDEX idx_status (status),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_commission_month (commission_month)
);

-- Create Advisor Network Relationships table (for level 2 and 3 overrides)
CREATE TABLE advisor_network (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_advisor_id INT NOT NULL,
    child_advisor_id INT NOT NULL,
    relationship_level INT NOT NULL, -- 1 = direct recruit, 2 = level 2, 3 = level 3
    recruitment_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (parent_advisor_id) REFERENCES certified_advisors(id) ON DELETE CASCADE,
    FOREIGN KEY (child_advisor_id) REFERENCES certified_advisors(id) ON DELETE CASCADE,
    UNIQUE KEY unique_relationship (parent_advisor_id, child_advisor_id),
    INDEX idx_parent_advisor (parent_advisor_id),
    INDEX idx_child_advisor (child_advisor_id),
    INDEX idx_level (relationship_level)
);

-- Update bookings table to include MCA and Advisor references
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS mca_id INT,
ADD COLUMN IF NOT EXISTS advisor_id INT,
ADD COLUMN IF NOT EXISTS referral_source VARCHAR(100),
ADD COLUMN IF NOT EXISTS booking_type ENUM('direct', 'mca_referral', 'advisor_referral') DEFAULT 'direct',
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

-- Insert comprehensive FYT Academy training modules
INSERT INTO advisor_training_modules (title, description, content, module_type, topics, order_sequence, duration_minutes, difficulty_level, passing_score, required, tour_categories, brand_guidelines, service_standards) VALUES

('Welcome to Forever Young Tours - Company Overview', 
 'Introduction to FYT mission, vision, and core values', 
 'Welcome to Forever Young Tours Ltd! Learn about our company history, mission to provide exceptional travel experiences, and our commitment to agro-tourism, cultural exchange, and sustainable travel practices. Understand our position in the East African tourism market and our expansion goals.',
 'onboarding', 
 'Company History,Mission & Vision,Core Values,Market Position,Expansion Strategy',
 1, 60, 'beginner', 80.00, TRUE,
 'Agro-Tourism,Cultural Tours,Adventure Travel,Sports Tours,Business Development Tours',
 'Brand identity guidelines, logo usage, communication standards, professional presentation requirements',
 'Customer service excellence, quality assurance, safety standards, cultural sensitivity'),

('FYT Tour Categories and Products', 
 'Comprehensive overview of all FYT tour offerings', 
 'Deep dive into our five main tour categories: Agro-Tourism experiences showcasing Rwanda\'s agricultural heritage, Cultural Tours highlighting East African traditions, Adventure Travel for thrill-seekers, Sports Tours for athletic groups, and Business Development Tours for professional networking and trade missions.',
 'product_knowledge', 
 'Agro-Tourism,Cultural Tours,Adventure Travel,Sports Tours,Business Development,Pricing Structure,Seasonal Variations',
 2, 90, 'beginner', 85.00, TRUE,
 'Agro-Tourism,Cultural Tours,Adventure Travel,Sports Tours,Business Development Tours',
 'Product presentation standards, marketing material usage, pricing communication',
 'Tour quality standards, safety protocols, customer satisfaction metrics'),

('Sales Techniques and Client Engagement', 
 'Master the art of consultative selling for group tours', 
 'Learn proven sales techniques specifically for group tour bookings. Understand different client types (churches, associations, diaspora groups), how to identify their needs, handle objections, and close sales. Focus on building long-term relationships and repeat business.',
 'sales_training', 
 'Consultative Selling,Group Sales,Client Psychology,Objection Handling,Closing Techniques,Relationship Building',
 3, 120, 'intermediate', 80.00, TRUE,
 'All Tour Categories',
 'Sales presentation standards, ethical selling practices, brand representation',
 'Professional communication, follow-up procedures, customer service excellence'),

('Cultural Exchange and Trade Facilitation', 
 'Supporting cultural delegations and business groups', 
 'Learn how to facilitate cultural exchange programs, business delegations, and conference groups. Understand the unique needs of these specialized tours and how to coordinate with embassies, government tourism boards, and business chambers.',
 'cultural_training', 
 'Cultural Exchange,Business Delegations,Conference Groups,Embassy Relations,Government Partnerships',
 4, 75, 'intermediate', 85.00, TRUE,
 'Cultural Tours,Business Development Tours',
 'Cultural sensitivity guidelines, professional protocol, diplomatic etiquette',
 'Cultural competency standards, professional service delivery, protocol adherence'),

('Digital Marketing and Social Media Promotion', 
 'Leverage digital platforms for tour promotion', 
 'Master the use of social media platforms, digital marketing tools, and online promotion strategies. Learn to create engaging content, use FYT marketing materials effectively, and build your online presence to attract group bookings.',
 'sales_training', 
 'Social Media Marketing,Digital Platforms,Content Creation,Online Promotion,Lead Generation',
 5, 90, 'intermediate', 80.00, FALSE,
 'All Tour Categories',
 'Social media guidelines, content standards, brand consistency, approved messaging',
 'Digital marketing standards, content quality requirements, engagement metrics'),

('Commission Structure and Compensation Plan', 
 'Understanding your earnings and advancement opportunities', 
 'Detailed explanation of the FYT compensation plan, commission rates for different advisor levels, override structures, bonus pools, and advancement criteria. Learn how to maximize your earnings and build a successful advisor network.',
 'onboarding', 
 'Commission Rates,Override Structure,Bonus Pools,Advancement Criteria,Payment Schedule',
 6, 45, 'beginner', 90.00, TRUE,
 'All Tour Categories',
 'Compensation transparency, ethical practices, performance standards',
 'Professional conduct, performance metrics, advancement requirements'),

('CRM and Technology Tools', 
 'Master FYT\'s technology platform', 
 'Learn to use EspoCRM for client management, Baserow dashboard for tracking performance, automated email and WhatsApp templates, and other digital tools provided by FYT for efficient business operations.',
 'onboarding', 
 'EspoCRM,Baserow Dashboard,Email Templates,WhatsApp Automation,Digital Tools',
 7, 60, 'intermediate', 85.00, TRUE,
 'All Tour Categories',
 'Technology usage guidelines, data privacy, system security',
 'Data management standards, system usage protocols, privacy compliance'),

('Advanced Group Tour Coordination', 
 'Managing large group bookings and logistics', 
 'Advanced training for handling large group bookings, coordinating with multiple stakeholders, managing special requests, and ensuring smooth tour execution. Learn about VIP services, airport coordination, and premium service delivery.',
 'advanced', 
 'Group Coordination,Logistics Management,VIP Services,Stakeholder Management,Premium Service',
 8, 105, 'advanced', 85.00, FALSE,
 'All Tour Categories',
 'Premium service standards, VIP protocol, professional presentation',
 'Excellence in service delivery, attention to detail, customer satisfaction');

-- Insert sample MCA for testing
INSERT IGNORE INTO users (first_name, last_name, email, username, password_hash, role_id, status) 
SELECT 'John', 'Smith', 'mca@foreveryoungtours.com', 'mca_john', 
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
       r.id, 'active'
FROM roles r WHERE r.name = 'mca' LIMIT 1;

-- Create MCA agent record
INSERT IGNORE INTO mca_agents (
    user_id, mca_code, tracking_code, first_name, last_name, email, phone, 
    country_id, country_code, region_city, status, registration_fee_paid, 
    advisor_recruitment_target, monthly_sales_target
)
SELECT u.id, 'MCA001', 'MCA-RW-001', 'John', 'Smith', 'mca@foreveryoungtours.com', 
       '+250788123456', c.id, 'RW', 'Kigali', 'active', TRUE, 100, 50000.00
FROM users u, countries c
WHERE u.email = 'mca@foreveryoungtours.com' AND c.code = 'RW' LIMIT 1;

-- Create sample advisor for testing
INSERT IGNORE INTO users (first_name, last_name, email, username, password_hash, role_id, status) 
SELECT 'Jane', 'Doe', 'advisor@foreveryoungtours.com', 'advisor_jane', 
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
       r.id, 'active'
FROM roles r WHERE r.name = 'certified_advisor' LIMIT 1;

-- Create advisor record
INSERT IGNORE INTO certified_advisors (
    user_id, mca_id, advisor_code, tracking_number, first_name, last_name, 
    email, phone, country, country_code, region_city, status, 
    registration_fee_paid, advisor_rank, commission_rate
)
SELECT u.id, ma.id, 'ADV001', 'ADV-RW-001', 'Jane', 'Doe', 
       'advisor@foreveryoungtours.com', '+250788654321', 'Rwanda', 'RW', 
       'Kigali', 'active', TRUE, 'certified_advisor', 30.00
FROM users u, mca_agents ma
WHERE u.email = 'advisor@foreveryoungtours.com' AND ma.mca_code = 'MCA001' LIMIT 1;

-- Update permissions for the new system
INSERT IGNORE INTO permissions (name, display_name, category, description) VALUES
('mcas.view', 'View MCAs', 'MCA Management', 'View Marketing & Client Advisors'),
('mcas.create', 'Create MCAs', 'MCA Management', 'Create new Marketing & Client Advisors'),
('mcas.edit', 'Edit MCAs', 'MCA Management', 'Edit Marketing & Client Advisor details'),
('mcas.delete', 'Delete MCAs', 'MCA Management', 'Delete Marketing & Client Advisors'),
('advisors.view', 'View Advisors', 'Advisor Management', 'View Certified Advisors'),
('advisors.create', 'Create Advisors', 'Advisor Management', 'Create new Certified Advisors'),
('advisors.edit', 'Edit Advisors', 'Advisor Management', 'Edit Certified Advisor details'),
('advisors.delete', 'Delete Advisors', 'Advisor Management', 'Delete Certified Advisors'),
('training.view', 'View Training', 'Training Management', 'View FYT Academy modules'),
('training.create', 'Create Training', 'Training Management', 'Create training modules'),
('training.edit', 'Edit Training', 'Training Management', 'Edit training modules'),
('training.delete', 'Delete Training', 'Training Management', 'Delete training modules'),
('commissions.view', 'View Commissions', 'Commission Management', 'View commission transactions'),
('commissions.create', 'Create Commissions', 'Commission Management', 'Create commission transactions'),
('commissions.edit', 'Edit Commissions', 'Commission Management', 'Edit commission transactions'),
('commissions.delete', 'Delete Commissions', 'Commission Management', 'Delete commission transactions'),
('network.view', 'View Network', 'Network Management', 'View advisor network relationships'),
('network.manage', 'Manage Network', 'Network Management', 'Manage advisor network relationships');

-- Assign permissions to admin role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'admin' 
AND p.name IN ('mcas.view', 'mcas.create', 'mcas.edit', 'mcas.delete', 
               'advisors.view', 'advisors.create', 'advisors.edit', 'advisors.delete',
               'training.view', 'training.create', 'training.edit', 'training.delete',
               'commissions.view', 'commissions.create', 'commissions.edit', 'commissions.delete',
               'network.view', 'network.manage');

-- Assign permissions to MCA role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'mca' 
AND p.name IN ('dashboard.view', 'bookings.view', 'bookings.create', 'bookings.edit',
               'advisors.view', 'advisors.create', 'commissions.view', 'training.view',
               'network.view', 'network.manage');

-- Assign permissions to advisor role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'certified_advisor' 
AND p.name IN ('dashboard.view', 'bookings.view', 'bookings.create', 
               'training.view', 'commissions.view', 'network.view');
