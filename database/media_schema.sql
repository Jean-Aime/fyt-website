-- Media Library Schema
CREATE TABLE IF NOT EXISTS media_folders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    parent_id INT NULL,
    path VARCHAR(500) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES media_folders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_path (path(255))
);

CREATE TABLE IF NOT EXISTS media_files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_type ENUM('image', 'video', 'audio', 'document', 'other') NOT NULL DEFAULT 'other',
    dimensions VARCHAR(20) NULL, -- For images: "1920x1080"
    alt_text TEXT NULL,
    caption TEXT NULL,
    description TEXT NULL,
    folder_id INT NULL,
    uploaded_by INT NOT NULL,
    status ENUM('active', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (folder_id) REFERENCES media_folders(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_file_type (file_type),
    INDEX idx_folder_id (folder_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Email System Schema
CREATE TABLE IF NOT EXISTS email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text LONGTEXT NULL,
    template_type ENUM('system', 'marketing', 'transactional') DEFAULT 'marketing',
    variables JSON NULL, -- Available template variables
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_template_type (template_type),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS email_campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    template_id INT NULL,
    recipient_type ENUM('all_users', 'newsletter_subscribers', 'customers', 'custom') NOT NULL,
    recipient_list JSON NULL, -- For custom recipient lists
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    status ENUM('draft', 'scheduled', 'sending', 'sent', 'failed') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_recipient_type (recipient_type)
);

CREATE TABLE IF NOT EXISTS email_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NULL,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255) NULL,
    subject VARCHAR(255) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text LONGTEXT NULL,
    status ENUM('pending', 'sending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    opened_at TIMESTAMP NULL,
    clicked_at TIMESTAMP NULL,
    bounced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_to_email (to_email),
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    status ENUM('active', 'unsubscribed', 'bounced') DEFAULT 'active',
    source VARCHAR(100) NULL, -- Where they subscribed from
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_subscribed_at (subscribed_at)
);

-- Analytics Schema
CREATE TABLE IF NOT EXISTS analytics_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    session_id VARCHAR(100) NULL,
    event_name VARCHAR(100) NOT NULL,
    event_category VARCHAR(100) NULL,
    properties JSON NULL,
    page_url VARCHAR(500) NULL,
    referrer VARCHAR(500) NULL,
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event_name (event_name),
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at),
    INDEX idx_event_category (event_category)
);

CREATE TABLE IF NOT EXISTS analytics_page_views (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    session_id VARCHAR(100) NULL,
    page_url VARCHAR(500) NOT NULL,
    page_title VARCHAR(255) NULL,
    referrer VARCHAR(500) NULL,
    time_on_page INT NULL, -- seconds
    bounce BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_page_url (page_url(255)),
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at)
);

-- Payment Processing Schema Updates
ALTER TABLE payments ADD COLUMN IF NOT EXISTS payment_intent_id VARCHAR(255) NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS refunded_amount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS refund_reason TEXT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS processor_fee DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS net_amount DECIMAL(10,2) GENERATED ALWAYS AS (amount - refunded_amount - processor_fee) STORED;

CREATE TABLE IF NOT EXISTS payment_refunds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    refund_reason TEXT NOT NULL,
    refund_reference VARCHAR(255) NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    processed_by INT NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS payment_disputes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    dispute_id VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('warning_needs_response', 'warning_under_review', 'warning_closed', 'needs_response', 'under_review', 'charge_refunded', 'won', 'lost') NOT NULL,
    evidence_due_by TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status),
    INDEX idx_dispute_id (dispute_id)
);

-- Insert default email templates
INSERT IGNORE INTO email_templates (name, subject, body_html, template_type, created_by) VALUES
('Booking Confirmation', 'Your booking confirmation - {{booking_reference}}', 
'<h2>Booking Confirmed!</h2>
<p>Dear {{customer_name}},</p>
<p>Thank you for booking with Forever Young Tours. Your booking has been confirmed.</p>
<p><strong>Booking Details:</strong></p>
<ul>
<li>Booking Reference: {{booking_reference}}</li>
<li>Tour: {{tour_name}}</li>
<li>Date: {{tour_date}}</li>
<li>Travelers: {{traveler_count}}</li>
<li>Total Amount: ${{total_amount}}</li>
</ul>
<p>We look forward to providing you with an amazing experience!</p>
<p>Best regards,<br>Forever Young Tours Team</p>', 
'transactional', 1),

('Welcome Email', 'Welcome to Forever Young Tours!', 
'<h2>Welcome to Forever Young Tours!</h2>
<p>Dear {{first_name}},</p>
<p>Thank you for joining our community of adventure seekers!</p>
<p>We specialize in creating unforgettable travel experiences across beautiful destinations.</p>
<p>Stay tuned for exclusive offers, travel tips, and amazing tour packages.</p>
<p>Happy travels!<br>Forever Young Tours Team</p>', 
'marketing', 1),

('Payment Receipt', 'Payment Receipt - {{payment_reference}}', 
'<h2>Payment Receipt</h2>
<p>Dear {{customer_name}},</p>
<p>We have successfully received your payment.</p>
<p><strong>Payment Details:</strong></p>
<ul>
<li>Payment Reference: {{payment_reference}}</li>
<li>Amount: ${{amount}}</li>
<li>Payment Method: {{payment_method}}</li>
<li>Date: {{payment_date}}</li>
</ul>
<p>Thank you for your business!</p>
<p>Forever Young Tours Team</p>', 
'transactional', 1);

-- Insert sample analytics events for demonstration
INSERT IGNORE INTO analytics_events (event_name, event_category, properties, created_at) VALUES
('page_view', 'navigation', '{"page": "/", "title": "Home"}', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('tour_view', 'engagement', '{"tour_id": 1, "tour_name": "Gorilla Trekking"}', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('booking_started', 'conversion', '{"tour_id": 1}', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('session_start', 'session', '{"source": "google", "medium": "organic"}', DATE_SUB(NOW(), INTERVAL 1 DAY));
