-- Forever Young Tours - Complete Database Schema with Payment Integration
-- Updated: 2024
-- Description: Comprehensive database structure with payment gateways and booking management

CREATE DATABASE IF NOT EXISTS database_fyt
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE database_fyt;

-- =============================================
-- CORE AUTHENTICATION & USERS
-- =============================================

-- Roles table
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Permissions table
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Role permissions junction table
CREATE TABLE role_permissions (
    role_id INT,
    permission_id INT,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    nationality VARCHAR(50),
    passport_number VARCHAR(50),
    passport_expiry DATE,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relationship VARCHAR(50),
    role_id INT NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    password_reset_token VARCHAR(255),
    password_reset_expires TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    profile_image VARCHAR(255),
    preferences JSON,
    billing_address JSON,
    shipping_address JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_status (status)
);

-- User sessions table
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
);


-- User activity logs
CREATE TABLE user_activity_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- =============================================
-- COUNTRIES & DESTINATIONS
-- =============================================

-- Countries table
CREATE TABLE countries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(3) NOT NULL UNIQUE,
    continent VARCHAR(50),
    currency VARCHAR(10),
    language VARCHAR(100),
    timezone VARCHAR(50),
    visa_required BOOLEAN DEFAULT FALSE,
    description TEXT,
    travel_advisory TEXT,
    best_time_to_visit TEXT,
    flag_image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_continent (continent)
);

-- Regions table
CREATE TABLE regions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    country_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    climate VARCHAR(100),
    attractions TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
    INDEX idx_country_id (country_id),
    INDEX idx_status (status)
);

-- =============================================
-- TOUR CATEGORIES & TOURS
-- =============================================

-- Tour categories table
CREATE TABLE tour_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50),
    color VARCHAR(7),
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tours table
CREATE TABLE tours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    short_description TEXT,
    full_description LONGTEXT,
    country_id INT NOT NULL,
    region_id INT,
    category_id INT NOT NULL,
    duration_days INT NOT NULL,
    duration_nights INT DEFAULT 0,
    min_group_size INT DEFAULT 1,
    max_group_size INT DEFAULT 20,
    difficulty_level ENUM('easy', 'moderate', 'challenging', 'extreme') DEFAULT 'moderate',
    price_adult DECIMAL(10,2) NOT NULL,
    price_child DECIMAL(10,2),
    price_infant DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    includes TEXT,
    excludes TEXT,
    requirements TEXT,
    cancellation_policy TEXT,
    featured_image VARCHAR(255),
    gallery JSON,
    video_url VARCHAR(255),
    brochure_pdf VARCHAR(255),
    status ENUM('draft', 'active', 'inactive') DEFAULT 'draft',
    featured BOOLEAN DEFAULT FALSE,
    rating_average DECIMAL(3,2) DEFAULT 0,
    rating_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    booking_count INT DEFAULT 0,
    seo_title VARCHAR(200),
    seo_description TEXT,
    seo_keywords TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (country_id) REFERENCES countries(id),
    FOREIGN KEY (region_id) REFERENCES regions(id),
    FOREIGN KEY (category_id) REFERENCES tour_categories(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_featured (featured),
    INDEX idx_country_id (country_id),
    INDEX idx_category_id (category_id),
    INDEX idx_price_adult (price_adult),
    FULLTEXT idx_search (title, short_description, full_description)
);

-- Tour add-ons table
CREATE TABLE tour_addons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    max_quantity INT DEFAULT 1,
    required BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE
);

-- Itineraries table
CREATE TABLE itineraries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT NOT NULL,
    day_number INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    activities TEXT,
    meals_included VARCHAR(100),
    accommodation VARCHAR(200),
    transportation VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE,
    INDEX idx_tour_day (tour_id, day_number)
);

-- Tour availability table
CREATE TABLE tour_availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT NOT NULL,
    date DATE NOT NULL,
    available_spots INT NOT NULL,
    booked_spots INT DEFAULT 0,
    price_adult DECIMAL(10,2),
    price_child DECIMAL(10,2),
    price_infant DECIMAL(10,2),
    status ENUM('available', 'limited', 'sold_out', 'cancelled') DEFAULT 'available',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tour_date (tour_id, date),
    INDEX idx_date (date),
    INDEX idx_status (status)
);

-- =============================================
-- PAYMENT GATEWAYS & METHODS
-- =============================================

-- Payment gateways table
CREATE TABLE payment_gateways (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    provider VARCHAR(50) NOT NULL,
    configuration JSON,
    supported_currencies JSON,
    supported_countries JSON,
    min_amount DECIMAL(10,2) DEFAULT 0.01,
    max_amount DECIMAL(10,2) DEFAULT 999999.99,
    processing_fee_type ENUM('fixed', 'percentage') DEFAULT 'percentage',
    processing_fee_value DECIMAL(5,4) DEFAULT 0.0290,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    logo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Payment methods table
CREATE TABLE payment_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gateway_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    requires_redirect BOOLEAN DEFAULT FALSE,
    requires_verification BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id) ON DELETE CASCADE,
    INDEX idx_gateway_status (gateway_id, status)
);

-- =============================================
-- BOOKINGS & PAYMENTS
-- =============================================

-- Bookings table
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_reference VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    tour_id INT NOT NULL,
    agent_id INT,
    tour_date DATE NOT NULL,
    adults INT NOT NULL DEFAULT 1,
    children INT DEFAULT 0,
    infants INT DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'refunded') DEFAULT 'pending',
    payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    special_requests TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relationship VARCHAR(50),
    dietary_requirements TEXT,
    medical_conditions TEXT,
    travel_insurance BOOLEAN DEFAULT FALSE,
    insurance_provider VARCHAR(100),
    insurance_policy_number VARCHAR(100),
    confirmed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    cancellation_reason TEXT,
    refund_amount DECIMAL(10,2) DEFAULT 0,
    refund_reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (tour_id) REFERENCES tours(id),
    FOREIGN KEY (agent_id) REFERENCES users(id),
    INDEX idx_booking_reference (booking_reference),
    INDEX idx_user_id (user_id),
    INDEX idx_tour_id (tour_id),
    INDEX idx_tour_date (tour_date),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Booking travelers table
CREATE TABLE booking_travelers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    type ENUM('adult', 'child', 'infant') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    nationality VARCHAR(50),
    passport_number VARCHAR(50),
    passport_expiry DATE,
    dietary_requirements TEXT,
    medical_conditions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id)
);

-- Booking add-ons table
CREATE TABLE booking_addons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    addon_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (addon_id) REFERENCES tour_addons(id)
);

-- Payments table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    payment_reference VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method_id INT NOT NULL,
    gateway_transaction_id VARCHAR(100),
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    gateway_response JSON,
    failure_reason TEXT,
    processed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    INDEX idx_booking_id (booking_id),
    INDEX idx_payment_reference (payment_reference),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Payment transactions table (for detailed transaction logs)
CREATE TABLE payment_transactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    transaction_type ENUM('charge', 'refund', 'capture', 'void', 'authorize') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    gateway_transaction_id VARCHAR(100),
    gateway_response JSON,
    status ENUM('pending', 'success', 'failed') NOT NULL,
    error_code VARCHAR(50),
    error_message TEXT,
    processing_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    INDEX idx_payment_id (payment_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Refunds table
CREATE TABLE refunds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    refund_reference VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    reason TEXT,
    gateway_refund_id VARCHAR(100),
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    gateway_response JSON,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_refund_reference (refund_reference),
    INDEX idx_status (status)
);

-- Payment webhooks table
CREATE TABLE payment_webhooks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    gateway_name VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_id VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    signature VARCHAR(500),
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    error_message TEXT,
    retry_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gateway_event (gateway_name, event_type),
    INDEX idx_processed (processed),
    INDEX idx_event_id (event_id)
);

-- =============================================
-- MCA AGENTS
-- =============================================

-- MCA agents table
CREATE TABLE mca_agents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    agent_code VARCHAR(20) NOT NULL UNIQUE,
    commission_rate DECIMAL(5,2) DEFAULT 10.00,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    territory VARCHAR(100),
    specialization TEXT,
    training_completed BOOLEAN DEFAULT FALSE,
    certification_date DATE,
    performance_rating DECIMAL(3,2) DEFAULT 0,
    total_sales DECIMAL(12,2) DEFAULT 0,
    total_commission DECIMAL(12,2) DEFAULT 0,
    bank_account_name VARCHAR(100),
    bank_account_number VARCHAR(50),
    bank_name VARCHAR(100),
    bank_swift_code VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_agent_code (agent_code),
    INDEX idx_status (status),
    INDEX idx_performance_rating (performance_rating)
);

-- MCA commissions table
CREATE TABLE mca_commissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    agent_id INT NOT NULL,
    booking_id INT NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL,
    booking_amount DECIMAL(10,2) NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    approved_at TIMESTAMP NULL,
    approved_by INT,
    paid_at TIMESTAMP NULL,
    payment_reference VARCHAR(50),
    payment_method VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES mca_agents(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_agent_id (agent_id),
    INDEX idx_booking_id (booking_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- =============================================
-- SYSTEM SETTINGS
-- =============================================

-- System settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value LONGTEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json', 'text') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_is_public (is_public)
);

-- =============================================
-- EMAIL TEMPLATES & NOTIFICATIONS
-- =============================================

-- Email templates table
CREATE TABLE email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(200) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text LONGTEXT,
    variables JSON,
    category VARCHAR(50),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Email queue table
CREATE TABLE email_queue (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    to_email VARCHAR(100) NOT NULL,
    to_name VARCHAR(100),
    from_email VARCHAR(100),
    from_name VARCHAR(100),
    subject VARCHAR(200) NOT NULL,
    body_html LONGTEXT,
    body_text LONGTEXT,
    template_id INT,
    template_data JSON,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES email_templates(id),
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_priority (priority)
);

-- Notifications table
CREATE TABLE notifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_read_at (read_at),
    INDEX idx_created_at (created_at)
);

-- =============================================
-- INSERT DEFAULT DATA
-- =============================================

-- Insert default roles
INSERT INTO roles (name, display_name, description) VALUES
('super_admin', 'Super Administrator', 'Full system access with all permissions'),
('admin', 'Administrator', 'Administrative access to most system features'),
('content_manager', 'Content Manager', 'Manage tours, blog posts, and media content'),
('booking_agent', 'Booking Agent', 'Handle customer bookings and reservations'),
('mca_agent', 'MCA Agent', 'Multi-Country Agent with commission tracking'),
('client', 'Client', 'Regular customer with booking capabilities');

-- Insert default permissions
INSERT INTO permissions (name, display_name, category) VALUES
-- Dashboard
('dashboard.view', 'View Dashboard', 'dashboard'),

-- Users & Roles
('users.view', 'View Users', 'users'),
('users.create', 'Create Users', 'users'),
('users.edit', 'Edit Users', 'users'),
('users.delete', 'Delete Users', 'users'),
('roles.view', 'View Roles', 'users'),
('roles.create', 'Create Roles', 'users'),
('roles.edit', 'Edit Roles', 'users'),
('roles.delete', 'Delete Roles', 'users'),

-- Tours
('tours.view', 'View Tours', 'tours'),
('tours.create', 'Create Tours', 'tours'),
('tours.edit', 'Edit Tours', 'tours'),
('tours.delete', 'Delete Tours', 'tours'),
('tours.manage', 'Manage Tour Categories', 'tours'),

-- Bookings
('bookings.view', 'View Bookings', 'bookings'),
('bookings.create', 'Create Bookings', 'bookings'),
('bookings.edit', 'Edit Bookings', 'bookings'),
('bookings.delete', 'Delete Bookings', 'bookings'),
('bookings.approve', 'Approve Bookings', 'bookings'),

-- Payments
('payments.view', 'View Payments', 'payments'),
('payments.process', 'Process Payments', 'payments'),
('payments.refund', 'Process Refunds', 'payments'),

-- MCA
('mca.view', 'View MCA Agents', 'mca'),
('mca.create', 'Create MCA Agents', 'mca'),
('mca.edit', 'Edit MCA Agents', 'mca'),
('mca.delete', 'Delete MCA Agents', 'mca'),
('mca.manage', 'Manage MCA System', 'mca'),

-- Settings
('settings.view', 'View Settings', 'settings'),
('settings.edit', 'Edit Settings', 'settings');

-- Assign permissions to roles
-- Super Admin gets all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Admin gets most permissions except super admin functions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE name NOT LIKE 'settings.%';

-- Content Manager permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE category IN ('dashboard', 'tours');

-- Booking Agent permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE category IN ('dashboard', 'bookings', 'payments', 'tours') AND name NOT LIKE '%.delete';

-- MCA Agent permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE category IN ('dashboard', 'bookings', 'tours', 'mca') AND name IN ('dashboard.view', 'bookings.view', 'bookings.create', 'tours.view', 'mca.view');

-- Client permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE name IN ('dashboard.view', 'bookings.view', 'bookings.create', 'tours.view');

-- Insert default admin user
INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, status, email_verified) VALUES
('admin', 'admin@foreveryoungtours.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 1, 'active', TRUE);

-- Insert default tour categories
INSERT INTO tour_categories (name, slug, description, icon, color) VALUES
('Adventure Tours', 'adventure-tours', 'Thrilling outdoor adventures and extreme sports', 'fas fa-mountain', '#E67E22'),
('Cultural Tours', 'cultural-tours', 'Explore local culture, traditions, and heritage', 'fas fa-landmark', '#9B59B6'),
('Wildlife Safari', 'wildlife-safari', 'Amazing wildlife viewing and safari experiences', 'fas fa-paw', '#27AE60'),
('City Tours', 'city-tours', 'Urban exploration and city sightseeing', 'fas fa-city', '#3498DB'),
('Beach & Islands', 'beach-islands', 'Tropical beaches and island getaways', 'fas fa-umbrella-beach', '#1ABC9C'),
('Mountain Trekking', 'mountain-trekking', 'Hiking and trekking in mountain regions', 'fas fa-hiking', '#8E44AD'),
('Photography Tours', 'photography-tours', 'Specialized tours for photography enthusiasts', 'fas fa-camera', '#F39C12'),
('Family Tours', 'family-tours', 'Family-friendly tours and activities', 'fas fa-users', '#E74C3C');

-- Insert default countries
INSERT INTO countries (name, code, continent, currency, language, timezone, description) VALUES
('Rwanda', 'RWA', 'Africa', 'RWF', 'Kinyarwanda, French, English', 'Africa/Kigali', 'Land of a thousand hills with incredible wildlife and culture'),
('Kenya', 'KEN', 'Africa', 'KES', 'Swahili, English', 'Africa/Nairobi', 'Home to the Great Migration and diverse wildlife'),
('Tanzania', 'TZA', 'Africa', 'TZS', 'Swahili, English', 'Africa/Dar_es_Salaam', 'Mount Kilimanjaro and Serengeti National Park'),
('Uganda', 'UGA', 'Africa', 'UGX', 'English, Luganda', 'Africa/Kampala', 'Pearl of Africa with mountain gorillas'),
('South Africa', 'ZAF', 'Africa', 'ZAR', 'Afrikaans, English, Zulu', 'Africa/Johannesburg', 'Rainbow nation with diverse landscapes and wildlife');

-- Insert default payment gateways
INSERT INTO payment_gateways (name, display_name, provider, configuration, supported_currencies, status) VALUES
('stripe', 'Stripe', 'stripe', '{"publishable_key": "", "secret_key": "", "webhook_secret": ""}', '["USD", "EUR", "GBP", "RWF"]', 'active'),
('paypal', 'PayPal', 'paypal', '{"client_id": "", "client_secret": "", "mode": "sandbox"}', '["USD", "EUR", "GBP"]', 'active'),
('mtn_mobile_money', 'MTN Mobile Money', 'mtn', '{"api_key": "", "subscription_key": "", "environment": "sandbox"}', '["RWF", "UGX"]', 'active'),
('airtel_money', 'Airtel Money', 'airtel', '{"client_id": "", "client_secret": "", "environment": "sandbox"}', '["RWF", "UGX", "KES", "TZS"]', 'active'),
('bank_transfer', 'Bank Transfer', 'manual', '{"account_details": {}}', '["USD", "RWF", "EUR"]', 'active');

-- Insert default payment methods
INSERT INTO payment_methods (gateway_id, name, display_name, description, icon, requires_redirect, status) VALUES
(1, 'credit_card', 'Credit/Debit Card', 'Pay securely with your credit or debit card', 'fas fa-credit-card', FALSE, 'active'),
(2, 'paypal', 'PayPal', 'Pay with your PayPal account', 'fab fa-paypal', TRUE, 'active'),
(3, 'mtn_momo', 'MTN Mobile Money', 'Pay with MTN Mobile Money', 'fas fa-mobile-alt', FALSE, 'active'),
(4, 'airtel_money', 'Airtel Money', 'Pay with Airtel Money', 'fas fa-mobile-alt', FALSE, 'active'),
(5, 'bank_transfer', 'Bank Transfer', 'Direct bank transfer', 'fas fa-university', FALSE, 'active');

-- Insert default email templates
INSERT INTO email_templates (name, subject, body_html, category) VALUES
('booking_confirmation', 'Booking Confirmation - {{booking_reference}}', 
'<h2>Booking Confirmed!</h2><p>Dear {{customer_name}},</p><p>Your booking has been confirmed.</p><p><strong>Booking Reference:</strong> {{booking_reference}}</p><p><strong>Tour:</strong> {{tour_title}}</p><p><strong>Date:</strong> {{tour_date}}</p><p>Thank you for choosing Forever Young Tours!</p>', 
'booking'),

('payment_received', 'Payment Received - {{booking_reference}}', 
'<h2>Payment Received</h2><p>Dear {{customer_name}},</p><p>We have received your payment for booking {{booking_reference}}.</p><p><strong>Amount:</strong> {{amount}} {{currency}}</p><p>Thank you for your payment!</p>', 
'payment'),

('booking_cancelled', 'Booking Cancelled - {{booking_reference}}', 
'<h2>Booking Cancelled</h2><p>Dear {{customer_name}},</p><p>Your booking has been cancelled.</p><p><strong>Booking Reference:</strong> {{booking_reference}}</p><p>If you have any questions, please contact our support team.</p>', 
'booking');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description, is_public) VALUES
('site_name', 'Forever Young Tours', 'string', 'general', 'Website name', TRUE),
('site_description', 'Discover amazing tours and adventures', 'text', 'general', 'Website description', TRUE),
('contact_email', 'info@foreveryoungtours.com', 'string', 'general', 'Main contact email', TRUE),
('contact_phone', '+250 123 456 789', 'string', 'general', 'Main contact phone', TRUE),
('default_currency', 'USD', 'string', 'general', 'Default currency code', TRUE),
('booking_confirmation_required', 'true', 'boolean', 'booking', 'Require admin confirmation for bookings', FALSE),
('max_booking_days_advance', '365', 'number', 'booking', 'Maximum days in advance for bookings', FALSE),
('commission_rate_default', '10.00', 'number', 'mca', 'Default commission rate for MCA agents', FALSE),
('payment_timeout_minutes', '30', 'number', 'payment', 'Payment timeout in minutes', FALSE),
('stripe_publishable_key', '', 'string', 'payment', 'Stripe publishable key', FALSE),
('stripe_secret_key', '', 'string', 'payment', 'Stripe secret key', FALSE),
('paypal_client_id', '', 'string', 'payment', 'PayPal client ID', FALSE),
('paypal_client_secret', '', 'string', 'payment', 'PayPal client secret', FALSE);

SET FOREIGN_KEY_CHECKS = 1;
