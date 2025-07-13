#!/bin/bash

# Forever Young Tours - Production Deployment Script
# Automated deployment with rollback capability

set -e  # Exit on any error

# Configuration
PROJECT_NAME="forever-young-tours"
DEPLOY_USER="deploy"
DEPLOY_PATH="/var/www/foreveryoungtours.com"
BACKUP_PATH="/var/backups/foreveryoungtours"
GIT_REPO="https://github.com/your-username/forever-young-tours.git"
GIT_BRANCH="main"
PHP_VERSION="8.1"
NODE_VERSION="18"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running as correct user
check_user() {
    if [ "$USER" != "$DEPLOY_USER" ]; then
        error "This script must be run as $DEPLOY_USER user"
        exit 1
    fi
}

# Pre-deployment checks
pre_deployment_checks() {
    log "Running pre-deployment checks..."
    
    # Check PHP version
    PHP_CURRENT=$(php -r "echo PHP_VERSION;" 2>/dev/null || echo "0")
    if ! php -v | grep -q "PHP $PHP_VERSION"; then
        error "PHP $PHP_VERSION is required, found: $PHP_CURRENT"
        exit 1
    fi
    
    # Check Node.js version
    if ! node --version | grep -q "v$NODE_VERSION"; then
        warning "Node.js $NODE_VERSION recommended"
    fi
    
    # Check disk space (require at least 2GB free)
    AVAILABLE_SPACE=$(df $DEPLOY_PATH | awk 'NR==2 {print $4}')
    if [ $AVAILABLE_SPACE -lt 2097152 ]; then
        error "Insufficient disk space. At least 2GB required."
        exit 1
    fi
    
    # Check if required commands exist
    for cmd in git composer npm mysql; do
        if ! command -v $cmd &> /dev/null; then
            error "$cmd is required but not installed"
            exit 1
        fi
    done
    
    # Check database connectivity
    if ! mysql -u root -p"$DB_PASSWORD" -e "SELECT 1;" &> /dev/null; then
        error "Cannot connect to database"
        exit 1
    fi
    
    success "Pre-deployment checks passed"
}

# Create backup
create_backup() {
    log "Creating backup..."
    
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_DIR="$BACKUP_PATH/$TIMESTAMP"
    
    mkdir -p $BACKUP_DIR
    
    # Backup files
    if [ -d "$DEPLOY_PATH" ]; then
        log "Backing up application files..."
        tar -czf "$BACKUP_DIR/files.tar.gz" -C "$DEPLOY_PATH" . 2>/dev/null || true
    fi
    
    # Backup database
    log "Backing up database..."
    mysqldump -u root -p"$DB_PASSWORD" forever_young_tours_prod > "$BACKUP_DIR/database.sql"
    
    # Backup uploads
    if [ -d "$DEPLOY_PATH/uploads" ]; then
        log "Backing up uploads..."
        tar -czf "$BACKUP_DIR/uploads.tar.gz" -C "$DEPLOY_PATH" uploads 2>/dev/null || true
    fi
    
    # Keep only last 10 backups
    ls -t $BACKUP_PATH | tail -n +11 | xargs -r -I {} rm -rf "$BACKUP_PATH/{}"
    
    echo $TIMESTAMP > /tmp/last_backup
    success "Backup created: $BACKUP_DIR"
}

# Deploy code
deploy_code() {
    log "Deploying code..."
    
    TEMP_DIR="/tmp/${PROJECT_NAME}_deploy_$$"
    
    # Clone repository
    log "Cloning repository..."
    git clone --branch $GIT_BRANCH --depth 1 $GIT_REPO $TEMP_DIR
    
    # Install PHP dependencies
    log "Installing PHP dependencies..."
    cd $TEMP_DIR
    composer install --no-dev --optimize-autoloader --no-interaction
    
    # Install Node.js dependencies and build assets
    if [ -f "package.json" ]; then
        log "Installing Node.js dependencies..."
        npm ci --production
        
        log "Building assets..."
        npm run build
    fi
    
    # Copy files to deployment directory
    log "Copying files..."
    rsync -av --delete \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='.env' \
        --exclude='uploads' \
        --exclude='storage/logs' \
        $TEMP_DIR/ $DEPLOY_PATH/
    
    # Set permissions
    log "Setting permissions..."
    chown -R www-data:www-data $DEPLOY_PATH
    chmod -R 755 $DEPLOY_PATH
    chmod -R 775 $DEPLOY_PATH/uploads
    chmod -R 775 $DEPLOY_PATH/storage
    
    # Clean up
    rm -rf $TEMP_DIR
    
    success "Code deployed successfully"
}

# Run database migrations
run_migrations() {
    log "Running database migrations..."
    
    cd $DEPLOY_PATH
    
    # Check if migration files exist
    if [ -d "database/migrations" ]; then
        for migration in database/migrations/*.sql; do
            if [ -f "$migration" ]; then
                log "Running migration: $(basename $migration)"
                mysql -u root -p"$DB_PASSWORD" forever_young_tours_prod < "$migration"
            fi
        done
    fi
    
    success "Database migrations completed"
}

# Update configuration
update_config() {
    log "Updating configuration..."
    
    cd $DEPLOY_PATH
    
    # Copy production config if it doesn't exist
    if [ ! -f "config/production.php" ]; then
        cp "deploy/production-config.php" "config/production.php"
    fi
    
    # Update .htaccess for production
    cat > .htaccess << 'EOF'
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Cache static assets
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 year"
    Header set Cache-Control "public, immutable"
</FilesMatch>

# Compress output
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
EOF
    
    success "Configuration updated"
}

# Clear and warm cache
manage_cache() {
    log "Managing cache..."
    
    cd $DEPLOY_PATH
    
    # Clear OPcache
    if command -v php &> /dev/null; then
        php -r "if (function_exists('opcache_reset')) opcache_reset();"
    fi
    
    # Clear application cache
    if [ -d "storage/cache" ]; then
        rm -rf storage/cache/*
    fi
    
    # Warm up cache by hitting key pages
    curl -s http://localhost/health-check > /dev/null || true
    
    success "Cache management completed"
}

# Restart services
restart_services() {
    log "Restarting services..."
    
    # Restart PHP-FPM
    if systemctl is-active --quiet php${PHP_VERSION}-fpm; then
        systemctl restart php${PHP_VERSION}-fpm
        log "PHP-FPM restarted"
    fi
    
    # Restart Apache/Nginx
    if systemctl is-active --quiet apache2; then
        systemctl reload apache2
        log "Apache reloaded"
    elif systemctl is-active --quiet nginx; then
        systemctl reload nginx
        log "Nginx reloaded"
    fi
    
    # Restart Redis if available
    if systemctl is-active --quiet redis; then
        systemctl restart redis
        log "Redis restarted"
    fi
    
    success "Services restarted"
}

# Post-deployment tests
post_deployment_tests() {
    log "Running post-deployment tests..."
    
    # Test database connection
    if ! mysql -u root -p"$DB_PASSWORD" -e "SELECT 1 FROM users LIMIT 1;" forever_young_tours_prod &> /dev/null; then
        error "Database connection test failed"
        return 1
    fi
    
    # Test web server response
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/)
    if [ "$HTTP_CODE" != "200" ]; then
        error "Web server test failed. HTTP code: $HTTP_CODE"
        return 1
    fi
    
    # Test admin panel
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/admin/)
    if [ "$HTTP_CODE" != "200" ] && [ "$HTTP_CODE" != "302" ]; then
        error "Admin panel test failed. HTTP code: $HTTP_CODE"
        return 1
    fi
    
    # Test API endpoints
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health)
    if [ "$HTTP_CODE" != "200" ]; then
        warning "API health check failed. HTTP code: $HTTP_CODE"
    fi
    
    success "Post-deployment tests passed"
}

# Rollback function
rollback() {
    error "Deployment failed. Starting rollback..."
    
    if [ -f "/tmp/last_backup" ]; then
        BACKUP_TIMESTAMP=$(cat /tmp/last_backup)
        BACKUP_DIR="$BACKUP_PATH/$BACKUP_TIMESTAMP"
        
        if [ -d "$BACKUP_DIR" ]; then
            log "Restoring from backup: $BACKUP_TIMESTAMP"
            
            # Restore files
            if [ -f "$BACKUP_DIR/files.tar.gz" ]; then
                tar -xzf "$BACKUP_DIR/files.tar.gz" -C "$DEPLOY_PATH"
            fi
            
            # Restore database
            if [ -f "$BACKUP_DIR/database.sql" ]; then
                mysql -u root -p"$DB_PASSWORD" forever_young_tours_prod < "$BACKUP_DIR/database.sql"
            fi
            
            # Restore uploads
            if [ -f "$BACKUP_DIR/uploads.tar.gz" ]; then
                tar -xzf "$BACKUP_DIR/uploads.tar.gz" -C "$DEPLOY_PATH"
            fi
            
            restart_services
            success "Rollback completed"
        else
            error "Backup directory not found: $BACKUP_DIR"
        fi
    else
        error "No backup information found"
    fi
}

# Send notification
send_notification() {
    local status=$1
    local message=$2
    
    # Send email notification (configure with your email settings)
    if command -v mail &> /dev/null; then
        echo "$message" | mail -s "[$PROJECT_NAME] Deployment $status" admin@foreveryoungtours.com
    fi
    
    # Send Slack notification (configure webhook URL)
    if [ ! -z "$SLACK_WEBHOOK_URL" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"[$PROJECT_NAME] Deployment $status: $message\"}" \
            $SLACK_WEBHOOK_URL
    fi
}

# Main deployment function
main() {
    log "Starting deployment of $PROJECT_NAME..."
    
    # Trap errors for rollback
    trap 'rollback; send_notification "FAILED" "Deployment failed and rollback was attempted"; exit 1' ERR
    
    check_user
    pre_deployment_checks
    create_backup
    deploy_code
    run_migrations
    update_config
    manage_cache
    restart_services
    
    if post_deployment_tests; then
        success "Deployment completed successfully!"
        send_notification "SUCCESS" "Deployment completed successfully at $(date)"
    else
        error "Post-deployment tests failed"
        exit 1
    fi
}

# Script options
case "${1:-deploy}" in
    "deploy")
        main
        ;;
    "rollback")
        rollback
        ;;
    "test")
        post_deployment_tests
        ;;
    "backup")
        create_backup
        ;;
    *)
        echo "Usage: $0 {deploy|rollback|test|backup}"
        exit 1
        ;;
esac
