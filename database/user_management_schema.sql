-- User Management Schema Extensions

-- Add additional columns to users table if not exists
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS phone VARCHAR(20),
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(255),
ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255),
ADD COLUMN IF NOT EXISTS password_reset_expires DATETIME,
ADD COLUMN IF NOT EXISTS last_login DATETIME,
ADD COLUMN IF NOT EXISTS login_attempts INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS locked_until DATETIME,
ADD COLUMN IF NOT EXISTS created_by INT,
ADD COLUMN IF NOT EXISTS updated_by INT;

-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create role_permissions junction table
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
);

-- Create user_activity_logs table
CREATE TABLE IF NOT EXISTS user_activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_activity (user_id, created_at),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Insert default roles
INSERT IGNORE INTO roles (name, display_name, description) VALUES
('super_admin', 'Super Administrator', 'Full system access with all permissions'),
('content_manager', 'Content Manager', 'Manage tours, destinations, and content'),
('booking_agent', 'Booking Agent', 'Handle bookings and customer inquiries'),
('mca_agent', 'MCA Agent', 'MCA portal access for tour management'),
('client', 'Client', 'Regular client with booking capabilities');

-- Insert default permissions
INSERT IGNORE INTO permissions (name, display_name, category) VALUES
-- User Management
('users.view', 'View Users', 'users'),
('users.create', 'Create Users', 'users'),
('users.edit', 'Edit Users', 'users'),
('users.delete', 'Delete Users', 'users'),
('users.suspend', 'Suspend Users', 'users'),

-- Role Management
('roles.view', 'View Roles', 'roles'),
('roles.create', 'Create Roles', 'roles'),
('roles.edit', 'Edit Roles', 'roles'),
('roles.delete', 'Delete Roles', 'roles'),

-- Tour Management
('tours.view', 'View Tours', 'tours'),
('tours.create', 'Create Tours', 'tours'),
('tours.edit', 'Edit Tours', 'tours'),
('tours.delete', 'Delete Tours', 'tours'),
('tours.publish', 'Publish Tours', 'tours'),

-- Booking Management
('bookings.view', 'View Bookings', 'bookings'),
('bookings.create', 'Create Bookings', 'bookings'),
('bookings.edit', 'Edit Bookings', 'bookings'),
('bookings.delete', 'Delete Bookings', 'bookings'),
('bookings.confirm', 'Confirm Bookings', 'bookings'),
('bookings.cancel', 'Cancel Bookings', 'bookings'),

-- Destination Management
('destinations.view', 'View Destinations', 'destinations'),
('destinations.create', 'Create Destinations', 'destinations'),
('destinations.edit', 'Edit Destinations', 'destinations'),
('destinations.delete', 'Delete Destinations', 'destinations'),

-- Content Management
('content.view', 'View Content', 'content'),
('content.create', 'Create Content', 'content'),
('content.edit', 'Edit Content', 'content'),
('content.delete', 'Delete Content', 'content'),
('content.publish', 'Publish Content', 'content'),

-- Analytics & Reports
('analytics.view', 'View Analytics', 'analytics'),
('reports.view', 'View Reports', 'reports'),
('reports.export', 'Export Reports', 'reports'),

-- System Settings
('settings.view', 'View Settings', 'settings'),
('settings.edit', 'Edit Settings', 'settings'),

-- Financial
('payments.view', 'View Payments', 'payments'),
('payments.process', 'Process Payments', 'payments'),
('payments.refund', 'Process Refunds', 'payments');

-- Assign permissions to Super Admin role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.name = 'super_admin';

-- Assign basic permissions to Content Manager
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.name = 'content_manager' 
AND p.name IN (
    'tours.view', 'tours.create', 'tours.edit', 'tours.publish',
    'destinations.view', 'destinations.create', 'destinations.edit',
    'content.view', 'content.create', 'content.edit', 'content.publish',
    'bookings.view', 'analytics.view'
);

-- Assign basic permissions to Booking Agent
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.name = 'booking_agent' 
AND p.name IN (
    'bookings.view', 'bookings.create', 'bookings.edit', 'bookings.confirm', 'bookings.cancel',
    'tours.view', 'destinations.view', 'payments.view', 'payments.process'
);

-- Assign basic permissions to MCA Agent
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.name = 'mca_agent' 
AND p.name IN (
    'tours.view', 'bookings.view', 'destinations.view', 'analytics.view'
);

-- Assign basic permissions to Client
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.name = 'client' 
AND p.name IN (
    'tours.view', 'destinations.view', 'bookings.create'
);

-- Add foreign key constraints for user roles
ALTER TABLE users 
ADD CONSTRAINT fk_users_role 
FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;

ALTER TABLE users 
ADD CONSTRAINT fk_users_created_by 
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE users 
ADD CONSTRAINT fk_users_updated_by 
FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- Create indexes for better performance
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_email_verified ON users(email_verified);
CREATE INDEX idx_users_last_login ON users(last_login);
CREATE INDEX idx_roles_status ON roles(status);
CREATE INDEX idx_permissions_category ON permissions(category);
