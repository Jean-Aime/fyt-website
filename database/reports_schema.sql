-- Reports table for storing generated analytics reports
CREATE TABLE IF NOT EXISTS reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    report_type ENUM('revenue_summary', 'booking_analysis', 'customer_insights', 'tour_performance', 'geographic_analysis', 'payment_analysis') NOT NULL,
    parameters JSON,
    file_path VARCHAR(500),
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_report_type (report_type),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at)
);

-- Add view_count column to tours table if it doesn't exist
ALTER TABLE tours ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0;

-- Add indexes for better analytics performance
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_created_at (created_at);
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_status (status);
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_tour_date (tour_date);
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_user_id (user_id);

ALTER TABLE tours ADD INDEX IF NOT EXISTS idx_country_id (country_id);
ALTER TABLE tours ADD INDEX IF NOT EXISTS idx_category_id (category_id);
ALTER TABLE tours ADD INDEX IF NOT EXISTS idx_status (status);
ALTER TABLE tours ADD INDEX IF NOT EXISTS idx_featured (featured);

-- Create payments table if it doesn't exist
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    payment_method ENUM('credit_card', 'debit_card', 'paypal', 'bank_transfer', 'mobile_money') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    gateway_response JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_payment_method (payment_method),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_transaction_id (transaction_id)
);

-- Create tour_categories table if it doesn't exist
CREATE TABLE IF NOT EXISTS tour_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50),
    color VARCHAR(7),
    status ENUM('active', 'inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_sort_order (sort_order)
);

-- Insert default tour categories
INSERT IGNORE INTO tour_categories (name, slug, description, icon, color) VALUES
('Adventure Tours', 'adventure-tours', 'Thrilling outdoor adventures and extreme sports', 'fas fa-mountain', '#e74c3c'),
('Cultural Tours', 'cultural-tours', 'Explore local culture, traditions, and heritage', 'fas fa-landmark', '#9b59b6'),
('Wildlife Safari', 'wildlife-safari', 'Amazing wildlife viewing and safari experiences', 'fas fa-paw', '#27ae60'),
('City Tours', 'city-tours', 'Urban exploration and city sightseeing', 'fas fa-city', '#3498db'),
('Beach & Island', 'beach-island', 'Relaxing beach holidays and island getaways', 'fas fa-umbrella-beach', '#1abc9c'),
('Mountain Trekking', 'mountain-trekking', 'Hiking and trekking in mountain regions', 'fas fa-hiking', '#795548'),
('Food & Wine', 'food-wine', 'Culinary experiences and wine tasting tours', 'fas fa-wine-glass-alt', '#ff9800'),
('Photography Tours', 'photography-tours', 'Specialized tours for photography enthusiasts', 'fas fa-camera', '#607d8b');

-- Update tours table to ensure it has all necessary columns
ALTER TABLE tours 
ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS booking_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS review_count INT DEFAULT 0;

-- Create tour_reviews table for ratings and reviews
CREATE TABLE IF NOT EXISTS tour_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT NOT NULL,
    user_id INT NOT NULL,
    booking_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    review TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_tour_review (user_id, tour_id),
    INDEX idx_tour_id (tour_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_rating (rating)
);

-- Create tour_itinerary table for detailed day-by-day itinerary
CREATE TABLE IF NOT EXISTS tour_itinerary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT NOT NULL,
    day_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    activities JSON,
    meals_included JSON,
    accommodation VARCHAR(255),
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE,
    INDEX idx_tour_id (tour_id),
    INDEX idx_day_number (day_number)
);

-- Create tour_addons table for optional tour add-ons
CREATE TABLE IF NOT EXISTS tour_addons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    max_quantity INT DEFAULT 1,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE,
    INDEX idx_tour_id (tour_id),
    INDEX idx_status (status)
);

-- Create booking_addons table for tracking selected add-ons
CREATE TABLE IF NOT EXISTS booking_addons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    addon_id INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (addon_id) REFERENCES tour_addons(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_addon_id (addon_id)
);

-- Create activity_log table for tracking user activities
CREATE TABLE IF NOT EXISTS activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    data JSON,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_read_at (read_at),
    INDEX idx_created_at (created_at)
);

-- Update existing data with calculated values
UPDATE tours t SET 
    booking_count = (
        SELECT COUNT(*) FROM bookings b 
        WHERE b.tour_id = t.id AND b.status IN ('confirmed', 'completed')
    ),
    average_rating = (
        SELECT COALESCE(AVG(rating), 0) FROM tour_reviews r 
        WHERE r.tour_id = t.id AND r.status = 'approved'
    ),
    review_count = (
        SELECT COUNT(*) FROM tour_reviews r 
        WHERE r.tour_id = t.id AND r.status = 'approved'
    );
