-- Forever Young Tours - Production Database Schema
-- Additional tables and optimizations for production environment

-- =============================================
-- PERFORMANCE OPTIMIZATIONS
-- =============================================

-- Add indexes for better query performance
CREATE INDEX idx_bookings_status_date ON bookings(status, created_at);
CREATE INDEX idx_bookings_tour_date ON bookings(tour_date, status);
CREATE INDEX idx_bookings_user_status ON bookings(user_id, status);
CREATE INDEX idx_payments_method_status ON payments(payment_method, status);
CREATE INDEX idx_payments_created_date ON payments(DATE(created_at));
CREATE INDEX idx_tours_featured_status ON tours(featured, status);
CREATE INDEX idx_tours_country_category ON tours(country_id, category_id, status);
CREATE INDEX idx_users_role_status ON users(role_id, status);
CREATE INDEX idx_user_activity_user_date ON user_activity_logs(user_id, DATE(created_at));

-- Full-text search indexes
ALTER TABLE tours ADD FULLTEXT(title, short_description, full_description);
ALTER TABLE blog_posts ADD FULLTEXT(title, excerpt, content);
ALTER TABLE store_products ADD FULLTEXT(name, short_description, description);

-- =============================================
-- PAYMENT PROCESSING ENHANCEMENTS
-- =============================================

-- Payment methods table for dynamic configuration
CREATE TABLE payment_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    provider VARCHAR(50) NOT NULL,
    configuration JSON,
    supported_currencies JSON,
    min_amount DECIMAL(10,2) DEFAULT 0.01,
    max_amount DECIMAL(10,2) DEFAULT 999999.99,
    processing_fee_type ENUM('fixed', 'percentage') DEFAULT 'percentage',
    processing_fee_value DECIMAL(5,4) DEFAULT 0.0290,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Payment gateway transactions log
CREATE TABLE payment_gateway_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    transaction_type ENUM('charge', 'refund', 'capture', 'void') NOT NULL,
    request_data JSON,
    response_data JSON,
    status_code VARCHAR(10),
    error_message TEXT,
    processing_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    INDEX idx_payment_gateway (payment_id, gateway),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_created_at (created_at)
);

-- Payment webhooks for real-time updates
CREATE TABLE payment_webhooks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_id VARCHAR(255) NOT NULL UNIQUE,
    payload JSON NOT NULL,
    signature VARCHAR(500),
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    error_message TEXT,
    retry_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider_event (provider, event_type),
    INDEX idx_processed (processed),
    INDEX idx_event_id (event_id)
);

-- Mobile money transactions
CREATE TABLE mobile_money_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    provider ENUM('mtn', 'airtel', 'tigo') NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    external_reference VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'expired') DEFAULT 'pending',
    provider_response JSON,
    callback_received BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    INDEX idx_provider_phone (provider, phone_number),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);

-- =============================================
-- ADVANCED ANALYTICS TABLES
-- =============================================

-- Daily analytics summary
CREATE TABLE analytics_daily_summary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL UNIQUE,
    total_bookings INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0,
    total_visitors INT DEFAULT 0,
    total_page_views INT DEFAULT 0,
    conversion_rate DECIMAL(5,4) DEFAULT 0,
    average_booking_value DECIMAL(10,2) DEFAULT 0,
    top_tour_id INT,
    top_country_id INT,
    bounce_rate DECIMAL(5,4) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date)
);

-- User behavior tracking
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    country VARCHAR(2),
    city VARCHAR(100),
    device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
    browser VARCHAR(50),
    os VARCHAR(50),
    referrer_domain VARCHAR(255),
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP NULL,
    page_views INT DEFAULT 0,
    duration_seconds INT DEFAULT 0,
    bounced BOOLEAN DEFAULT TRUE,
    converted BOOLEAN DEFAULT FALSE,
    conversion_value DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_session_start (session_start),
    INDEX idx_converted (converted),
    INDEX idx_utm_campaign (utm_campaign)
);

-- Conversion funnel tracking
CREATE TABLE conversion_funnel (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(128) NOT NULL,
    user_id INT NULL,
    step_name VARCHAR(100) NOT NULL,
    step_order INT NOT NULL,
    tour_id INT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON,
    FOREIGN KEY (session_id) REFERENCES user_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE SET NULL,
    INDEX idx_session_step (session_id, step_order),
    INDEX idx_step_name (step_name),
    INDEX idx_timestamp (timestamp)
);

-- =============================================
-- CONTENT MANAGEMENT ENHANCEMENTS
-- =============================================

-- SEO metadata table
CREATE TABLE seo_metadata (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entity_type ENUM('tour', 'blog_post', 'page', 'category') NOT NULL,
    entity_id INT NOT NULL,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    og_title VARCHAR(255),
    og_description TEXT,
    og_image VARCHAR(500),
    twitter_title VARCHAR(255),
    twitter_description TEXT,
    twitter_image VARCHAR(500),
    canonical_url VARCHAR(500),
    robots VARCHAR(100) DEFAULT 'index,follow',
    schema_markup JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entity (entity_type, entity_id),
    INDEX idx_entity_type (entity_type)
);

-- Content versioning
CREATE TABLE content_versions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    entity_type ENUM('tour', 'blog_post', 'page') NOT NULL,
    entity_id INT NOT NULL,
    version_number INT NOT NULL,
    title VARCHAR(255),
    content LONGTEXT,
    metadata JSON,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_published BOOLEAN DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_entity_version (entity_type, entity_id, version_number),
    INDEX idx_published (is_published, published_at)
);

-- =============================================
-- ADVANCED BOOKING FEATURES
-- =============================================

-- Booking modifications/changes
CREATE TABLE booking_modifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    modification_type ENUM('date_change', 'tour_change', 'traveler_change', 'cancellation') NOT NULL,
    original_data JSON NOT NULL,
    new_data JSON NOT NULL,
    reason TEXT,
    fee_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_by INT NOT NULL,
    processed_by INT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking_id (booking_id),
    INDEX idx_status (status),
    INDEX idx_modification_type (modification_type)
);

-- Group bookings
CREATE TABLE group_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_name VARCHAR(255) NOT NULL,
    group_leader_id INT NOT NULL,
    tour_id INT NOT NULL,
    tour_date DATE NOT NULL,
    total_travelers INT NOT NULL,
    group_discount_percentage DECIMAL(5,2) DEFAULT 0,
    special_requirements TEXT,
    status ENUM('draft', 'confirmed', 'cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_leader_id) REFERENCES users(id),
    FOREIGN KEY (tour_id) REFERENCES tours(id),
    INDEX idx_tour_date (tour_id, tour_date),
    INDEX idx_status (status)
);

-- Individual bookings within group
CREATE TABLE group_booking_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_booking_id INT NOT NULL,
    booking_id INT NOT NULL,
    member_role ENUM('leader', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_booking_id) REFERENCES group_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking_group (group_booking_id, booking_id),
    INDEX idx_group_booking (group_booking_id)
);

-- Waitlist for fully booked tours
CREATE TABLE tour_waitlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT NOT NULL,
    tour_date DATE NOT NULL,
    user_id INT NOT NULL,
    travelers INT DEFAULT 1,
    priority_score INT DEFAULT 0,
    notification_sent BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    status ENUM('active', 'notified', 'converted', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tour_date_status (tour_id, tour_date, status),
    INDEX idx_user_status (user_id, status),
    INDEX idx_priority (priority_score DESC)
);

-- =============================================
-- CUSTOMER RELATIONSHIP MANAGEMENT
-- =============================================

-- Customer segments for targeted marketing
CREATE TABLE customer_segments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    criteria JSON NOT NULL,
    auto_update BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_auto_update (auto_update)
);

-- Customer segment membership
CREATE TABLE customer_segment_members (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    segment_id INT NOT NULL,
    user_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    removed_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (segment_id) REFERENCES customer_segments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_segment_user (segment_id, user_id),
    INDEX idx_segment_active (segment_id, is_active),
    INDEX idx_user_active (user_id, is_active)
);

-- Customer lifetime value tracking
CREATE TABLE customer_ltv (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    total_bookings INT DEFAULT 0,
    total_spent DECIMAL(12,2) DEFAULT 0,
    average_booking_value DECIMAL(10,2) DEFAULT 0,
    first_booking_date DATE NULL,
    last_booking_date DATE NULL,
    predicted_ltv DECIMAL(12,2) DEFAULT 0,
    customer_tier ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_customer_tier (customer_tier),
    INDEX idx_total_spent (total_spent DESC),
    INDEX idx_last_calculated (last_calculated)
);

-- =============================================
-- INVENTORY AND AVAILABILITY MANAGEMENT
-- =============================================

-- Tour availability calendar
CREATE TABLE tour_availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT NOT NULL,
    date DATE NOT NULL,
    max_capacity INT NOT NULL,
    available_spots INT NOT NULL,
    price_adult DECIMAL(10,2),
    price_child DECIMAL(10,2),
    price_infant DECIMAL(10,2),
    seasonal_markup DECIMAL(5,2) DEFAULT 0,
    status ENUM('available', 'limited', 'sold_out', 'blocked', 'cancelled') DEFAULT 'available',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tour_date (tour_id, date),
    INDEX idx_tour_status_date (tour_id, status, date),
    INDEX idx_date_status (date, status)
);

-- Seasonal pricing rules
CREATE TABLE seasonal_pricing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    markup_percentage DECIMAL(5,2) NOT NULL,
    applies_to ENUM('all_tours', 'specific_tours', 'categories') DEFAULT 'all_tours',
    tour_ids JSON NULL,
    category_ids JSON NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date_range (start_date, end_date),
    INDEX idx_status (status)
);

-- =============================================
-- COMMUNICATION AND NOTIFICATIONS
-- =============================================

-- Advanced notification system
CREATE TABLE notification_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    type ENUM('email', 'sms', 'push', 'in_app') NOT NULL,
    trigger_event VARCHAR(100) NOT NULL,
    subject VARCHAR(255),
    content LONGTEXT NOT NULL,
    variables JSON,
    send_delay_minutes INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_trigger_event (trigger_event),
    INDEX idx_type_status (type, status)
);

-- Notification queue with retry logic
CREATE TABLE notification_queue (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    template_id INT NOT NULL,
    recipient_type ENUM('user', 'admin', 'agent') NOT NULL,
    recipient_id INT NOT NULL,
    recipient_email VARCHAR(255),
    recipient_phone VARCHAR(20),
    subject VARCHAR(255),
    content LONGTEXT NOT NULL,
    variables JSON,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    FOREIGN KEY (template_id) REFERENCES notification_templates(id),
    INDEX idx_status_scheduled (status, scheduled_at),
    INDEX idx_recipient (recipient_type, recipient_id),
    INDEX idx_priority (priority DESC)
);

-- SMS integration
CREATE TABLE sms_messages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    notification_id BIGINT,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_message_id VARCHAR(100),
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    cost DECIMAL(6,4) DEFAULT 0,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    error_message TEXT,
    FOREIGN KEY (notification_id) REFERENCES notification_queue(id) ON DELETE SET NULL,
    INDEX idx_phone_status (phone_number, status),
    INDEX idx_provider_message_id (provider_message_id),
    INDEX idx_sent_at (sent_at)
);

-- =============================================
-- SECURITY AND AUDIT ENHANCEMENTS
-- =============================================

-- Enhanced security logs
CREATE TABLE security_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    event_type ENUM('login_success', 'login_failed', 'password_change', 'permission_change', 'data_access', 'suspicious_activity') NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    details JSON,
    risk_score INT DEFAULT 0,
    blocked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_risk_score (risk_score DESC),
    INDEX idx_created_at (created_at)
);

-- API access tokens
CREATE TABLE api_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_name VARCHAR(100) NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    permissions JSON,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    status ENUM('active', 'revoked', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_status (user_id, status),
    INDEX idx_expires_at (expires_at)
);

-- Rate limiting
CREATE TABLE rate_limits (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    identifier_type ENUM('ip', 'user', 'api_key') NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    requests_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    INDEX idx_identifier_endpoint (identifier, endpoint),
    INDEX idx_window_start (window_start),
    INDEX idx_blocked_until (blocked_until)
);

-- =============================================
-- REPORTING AND BUSINESS INTELLIGENCE
-- =============================================

-- Saved reports
CREATE TABLE saved_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    report_type VARCHAR(100) NOT NULL,
    parameters JSON,
    sql_query LONGTEXT,
    schedule_frequency ENUM('none', 'daily', 'weekly', 'monthly') DEFAULT 'none',
    schedule_time TIME NULL,
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    shared_with JSON,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_report_type (report_type),
    INDEX idx_schedule (schedule_frequency, next_run_at),
    INDEX idx_created_by (created_by)
);

-- Report executions
CREATE TABLE report_executions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    report_id INT NOT NULL,
    executed_by INT NULL,
    execution_time_ms INT,
    row_count INT,
    file_path VARCHAR(500),
    status ENUM('running', 'completed', 'failed') DEFAULT 'running',
    error_message TEXT,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES saved_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_report_status (report_id, status),
    INDEX idx_executed_at (executed_at)
);

-- =============================================
-- INSERT DEFAULT DATA FOR PRODUCTION
-- =============================================

-- Insert default payment methods
INSERT INTO payment_methods (name, display_name, provider, configuration, supported_currencies, processing_fee_value, status) VALUES
('stripe', 'Credit/Debit Card', 'stripe', '{"publishable_key": "", "secret_key": ""}', '["USD", "EUR", "GBP", "RWF"]', 0.0290, 'active'),
('paypal', 'PayPal', 'paypal', '{"client_id": "", "client_secret": "", "mode": "sandbox"}', '["USD", "EUR", "GBP"]', 0.0349, 'active'),
('mtn_mobile_money', 'MTN Mobile Money', 'mtn', '{"api_key": "", "subscription_key": ""}', '["RWF", "UGX"]', 0.0200, 'active'),
('airtel_money', 'Airtel Money', 'airtel', '{"client_id": "", "client_secret": ""}', '["RWF", "UGX", "KES", "TZS"]', 0.0200, 'active'),
('bank_transfer', 'Bank Transfer', 'manual', '{"account_details": {}}', '["USD", "RWF"]', 0.0000, 'active');

-- Insert default customer segments
INSERT INTO customer_segments (name, description, criteria, created_by) VALUES
('VIP Customers', 'High-value customers with multiple bookings', '{"total_spent": {"min": 5000}, "booking_count": {"min": 3}}', 1),
('First-time Visitors', 'Customers who haven\'t made any bookings yet', '{"booking_count": {"max": 0}, "registration_days": {"min": 7}}', 1),
('Repeat Customers', 'Customers with 2+ bookings', '{"booking_count": {"min": 2}}', 1),
('Adventure Seekers', 'Customers who prefer adventure tours', '{"preferred_categories": ["adventure-tours", "mountain-trekking"]}', 1);

-- Insert default notification templates
INSERT INTO notification_templates (name, type, trigger_event, subject, content, variables) VALUES
('booking_confirmation', 'email', 'booking_created', 'Booking Confirmation - {{booking_reference}}', 
'Dear {{customer_name}}, your booking has been confirmed...', '["customer_name", "booking_reference", "tour_name", "tour_date"]'),

('payment_received', 'email', 'payment_completed', 'Payment Received - {{booking_reference}}', 
'Thank you for your payment...', '["customer_name", "booking_reference", "amount", "payment_method"]'),

('tour_reminder', 'email', 'tour_reminder_7_days', 'Your tour is coming up!', 
'Your tour {{tour_name}} is scheduled for {{tour_date}}...', '["customer_name", "tour_name", "tour_date"]'),

('booking_sms', 'sms', 'booking_created', '', 
'Your Forever Young Tours booking {{booking_reference}} is confirmed for {{tour_date}}. Thank you!', '["booking_reference", "tour_date"]');

-- Insert seasonal pricing rules
INSERT INTO seasonal_pricing (name, description, start_date, end_date, markup_percentage, status) VALUES
('Peak Season', 'High season pricing for December-January', '2024-12-15', '2025-01-15', 25.00, 'active'),
('Easter Holiday', 'Easter holiday premium', '2024-03-29', '2024-04-08', 15.00, 'active'),
('Summer Premium', 'Summer season markup', '2024-06-01', '2024-08-31', 20.00, 'active');

-- Create stored procedures for common operations
DELIMITER //

-- Procedure to update customer LTV
CREATE PROCEDURE UpdateCustomerLTV(IN customer_id INT)
BEGIN
    DECLARE total_bookings INT DEFAULT 0;
    DECLARE total_spent DECIMAL(12,2) DEFAULT 0;
    DECLARE avg_booking_value DECIMAL(10,2) DEFAULT 0;
    DECLARE first_booking DATE DEFAULT NULL;
    DECLARE last_booking DATE DEFAULT NULL;
    DECLARE predicted_ltv DECIMAL(12,2) DEFAULT 0;
    DECLARE customer_tier VARCHAR(20) DEFAULT 'bronze';
    
    -- Calculate metrics
    SELECT 
        COUNT(*),
        COALESCE(SUM(total_amount), 0),
        COALESCE(AVG(total_amount), 0),
        MIN(DATE(created_at)),
        MAX(DATE(created_at))
    INTO total_bookings, total_spent, avg_booking_value, first_booking, last_booking
    FROM bookings 
    WHERE user_id = customer_id AND status IN ('confirmed', 'completed');
    
    -- Calculate predicted LTV (simple model)
    SET predicted_ltv = total_spent * 1.5;
    
    -- Determine customer tier
    IF total_spent >= 10000 THEN
        SET customer_tier = 'platinum';
    ELSEIF total_spent >= 5000 THEN
        SET customer_tier = 'gold';
    ELSEIF total_spent >= 2000 THEN
        SET customer_tier = 'silver';
    ELSE
        SET customer_tier = 'bronze';
    END IF;
    
    -- Update or insert LTV record
    INSERT INTO customer_ltv (
        user_id, total_bookings, total_spent, average_booking_value,
        first_booking_date, last_booking_date, predicted_ltv, customer_tier
    ) VALUES (
        customer_id, total_bookings, total_spent, avg_booking_value,
        first_booking, last_booking, predicted_ltv, customer_tier
    ) ON DUPLICATE KEY UPDATE
        total_bookings = VALUES(total_bookings),
        total_spent = VALUES(total_spent),
        average_booking_value = VALUES(average_booking_value),
        first_booking_date = VALUES(first_booking_date),
        last_booking_date = VALUES(last_booking_date),
        predicted_ltv = VALUES(predicted_ltv),
        customer_tier = VALUES(customer_tier),
        last_calculated = NOW();
END //

-- Procedure to update tour availability
CREATE PROCEDURE UpdateTourAvailability(IN tour_id INT, IN tour_date DATE)
BEGIN
    DECLARE booked_spots INT DEFAULT 0;
    DECLARE max_capacity INT DEFAULT 0;
    DECLARE available_spots INT DEFAULT 0;
    DECLARE availability_status VARCHAR(20) DEFAULT 'available';
    
    -- Get current bookings for this tour and date
    SELECT COALESCE(SUM(adults + children), 0)
    INTO booked_spots
    FROM bookings 
    WHERE tour_id = tour_id 
    AND tour_date = tour_date 
    AND status IN ('confirmed', 'completed');
    
    -- Get tour capacity
    SELECT max_group_size INTO max_capacity
    FROM tours WHERE id = tour_id;
    
    -- Calculate available spots
    SET available_spots = max_capacity - booked_spots;
    
    -- Determine status
    IF available_spots <= 0 THEN
        SET availability_status = 'sold_out';
    ELSEIF available_spots <= 3 THEN
        SET availability_status = 'limited';
    ELSE
        SET availability_status = 'available';
    END IF;
    
    -- Update availability
    INSERT INTO tour_availability (
        tour_id, date, max_capacity, available_spots, status
    ) VALUES (
        tour_id, tour_date, max_capacity, available_spots, availability_status
    ) ON DUPLICATE KEY UPDATE
        available_spots = VALUES(available_spots),
        status = VALUES(status),
        updated_at = NOW();
END //

DELIMITER ;

-- Create triggers for automatic updates
DELIMITER //

-- Trigger to update customer LTV when booking is created/updated
CREATE TRIGGER update_customer_ltv_after_booking
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    CALL UpdateCustomerLTV(NEW.user_id);
END //

CREATE TRIGGER update_customer_ltv_after_booking_update
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status OR OLD.total_amount != NEW.total_amount THEN
        CALL UpdateCustomerLTV(NEW.user_id);
    END IF;
END //

-- Trigger to update tour availability when booking changes
CREATE TRIGGER update_availability_after_booking
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    CALL UpdateTourAvailability(NEW.tour_id, NEW.tour_date);
END //

CREATE TRIGGER update_availability_after_booking_update
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status OR OLD.adults != NEW.adults OR OLD.children != NEW.children THEN
        CALL UpdateTourAvailability(NEW.tour_id, NEW.tour_date);
        IF OLD.tour_id != NEW.tour_id OR OLD.tour_date != NEW.tour_date THEN
            CALL UpdateTourAvailability(OLD.tour_id, OLD.tour_date);
        END IF;
    END IF;
END //

DELIMITER ;

-- Create views for common queries
CREATE VIEW booking_summary AS
SELECT 
    b.id,
    b.booking_reference,
    b.status,
    b.tour_date,
    b.adults + b.children + b.infants as total_travelers,
    b.total_amount,
    b.currency,
    b.created_at,
    u.first_name,
    u.last_name,
    u.email,
    t.title as tour_title,
    c.name as country_name,
    p.status as payment_status,
    p.payment_method
FROM bookings b
LEFT JOIN users u ON b.user_id = u.id
LEFT JOIN tours t ON b.tour_id = t.id
LEFT JOIN countries c ON t.country_id = c.id
LEFT JOIN payments p ON b.id = p.booking_id;

CREATE VIEW revenue_summary AS
SELECT 
    DATE(b.created_at) as booking_date,
    COUNT(*) as total_bookings,
    SUM(b.total_amount) as total_revenue,
    AVG(b.total_amount) as average_booking_value,
    COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) as confirmed_bookings,
    COUNT(CASE WHEN b.status = 'cancelled' THEN 1 END) as cancelled_bookings
FROM bookings b
GROUP BY DATE(b.created_at);

CREATE VIEW tour_performance AS
SELECT 
    t.id,
    t.title,
    t.price_adult,
    COUNT(b.id) as total_bookings,
    SUM(b.total_amount) as total_revenue,
    AVG(b.total_amount) as average_booking_value,
    COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) as confirmed_bookings,
    (COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) * 100.0 / NULLIF(COUNT(b.id), 0)) as conversion_rate
FROM tours t
LEFT JOIN bookings b ON t.id = b.tour_id
GROUP BY t.id, t.title, t.price_adult;

-- Optimize table storage engines and settings
ALTER TABLE bookings ENGINE=InnoDB ROW_FORMAT=COMPRESSED;
ALTER TABLE payments ENGINE=InnoDB ROW_FORMAT=COMPRESSED;
ALTER TABLE user_activity_logs ENGINE=InnoDB ROW_FORMAT=COMPRESSED;
ALTER TABLE analytics_events ENGINE=InnoDB ROW_FORMAT=COMPRESSED;
ALTER TABLE notification_queue ENGINE=InnoDB ROW_FORMAT=COMPRESSED;

-- Set up partitioning for large tables (optional, for high-volume sites)
-- ALTER TABLE user_activity_logs PARTITION BY RANGE (YEAR(created_at)) (
--     PARTITION p2024 VALUES LESS THAN (2025),
--     PARTITION p2025 VALUES LESS THAN (2026),
--     PARTITION p_future VALUES LESS THAN MAXVALUE
-- );
