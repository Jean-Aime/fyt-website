<?php
// Production Configuration for Forever Young Tours
// This file contains production-specific settings

// Production Database Configuration
define('PROD_DB_HOST', 'your-production-db-host.com');
define('PROD_DB_NAME', 'forever_young_tours_prod');
define('PROD_DB_USER', 'prod_db_user');
define('PROD_DB_PASS', 'your_secure_production_password');
define('PROD_DB_CHARSET', 'utf8mb4');

// Production Site Configuration
define('PROD_SITE_URL', 'https://foreveryoungtours.com');
define('PROD_ADMIN_URL', 'https://foreveryoungtours.com/admin');
define('PROD_ASSETS_URL', 'https://cdn.foreveryoungtours.com/assets');
define('PROD_UPLOADS_URL', 'https://cdn.foreveryoungtours.com/uploads');

// SSL Configuration
define('FORCE_SSL', true);
define('SSL_CERT_PATH', '/etc/ssl/certs/foreveryoungtours.crt');
define('SSL_KEY_PATH', '/etc/ssl/private/foreveryoungtours.key');

// Production Email Configuration (SendGrid)
define('PROD_SMTP_HOST', 'smtp.sendgrid.net');
define('PROD_SMTP_PORT', 587);
define('PROD_SMTP_USERNAME', 'apikey');
define('PROD_SMTP_PASSWORD', 'your_sendgrid_api_key');
define('PROD_SMTP_ENCRYPTION', 'tls');
define('PROD_FROM_EMAIL', 'noreply@foreveryoungtours.com');
define('PROD_FROM_NAME', 'Forever Young Tours');

// Production Payment Gateway Configuration
// Stripe Live Keys
define('PROD_STRIPE_PUBLISHABLE_KEY', 'pk_live_your_stripe_live_publishable_key');
define('PROD_STRIPE_SECRET_KEY', 'sk_live_your_stripe_live_secret_key');
define('PROD_STRIPE_WEBHOOK_SECRET', 'whsec_your_stripe_webhook_secret');

// PayPal Live Configuration
define('PROD_PAYPAL_CLIENT_ID', 'your_paypal_live_client_id');
define('PROD_PAYPAL_CLIENT_SECRET', 'your_paypal_live_client_secret');
define('PROD_PAYPAL_MODE', 'live');
define('PROD_PAYPAL_WEBHOOK_ID', 'your_paypal_webhook_id');

// Mobile Money Configuration (MTN, Airtel)
define('PROD_MTN_API_KEY', 'your_mtn_api_key');
define('PROD_MTN_API_SECRET', 'your_mtn_api_secret');
define('PROD_MTN_SUBSCRIPTION_KEY', 'your_mtn_subscription_key');
define('PROD_AIRTEL_CLIENT_ID', 'your_airtel_client_id');
define('PROD_AIRTEL_CLIENT_SECRET', 'your_airtel_client_secret');

// CDN Configuration
define('CDN_ENABLED', true);
define('CDN_URL', 'https://cdn.foreveryoungtours.com');
define('CDN_API_KEY', 'your_cdn_api_key');

// Cache Configuration
define('REDIS_HOST', 'your-redis-host.com');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', 'your_redis_password');
define('CACHE_TTL', 3600); // 1 hour

// Security Configuration
define('PROD_ENCRYPTION_KEY', 'your_32_character_encryption_key_here');
define('PROD_JWT_SECRET', 'your_jwt_secret_key_for_api_authentication');
define('RATE_LIMIT_ENABLED', true);
define('MAX_REQUESTS_PER_MINUTE', 60);

// Monitoring and Logging
define('ERROR_REPORTING_ENABLED', false); // Disable in production
define('LOG_LEVEL', 'ERROR');
define('LOG_FILE_PATH', '/var/log/foreveryoungtours/app.log');
define('SENTRY_DSN', 'your_sentry_dsn_for_error_tracking');

// Backup Configuration
define('BACKUP_ENABLED', true);
define('BACKUP_FREQUENCY', 'daily');
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_S3_BUCKET', 'foreveryoungtours-backups');
define('AWS_ACCESS_KEY_ID', 'your_aws_access_key');
define('AWS_SECRET_ACCESS_KEY', 'your_aws_secret_key');
define('AWS_REGION', 'us-east-1');

// Performance Settings
define('ENABLE_GZIP', true);
define('ENABLE_BROWSER_CACHE', true);
define('CACHE_EXPIRY_TIME', 86400); // 24 hours
define('MINIFY_CSS', true);
define('MINIFY_JS', true);

// API Configuration
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 1000); // requests per hour
define('API_KEY_REQUIRED', true);

// Third-party Integrations
define('GOOGLE_ANALYTICS_ID', 'GA-XXXXXXXXX');
define('FACEBOOK_PIXEL_ID', 'your_facebook_pixel_id');
define('GOOGLE_MAPS_API_KEY', 'your_google_maps_api_key');
define('WEATHER_API_KEY', 'your_weather_api_key');

// Mobile App Configuration
define('MOBILE_APP_VERSION', '1.0.0');
define('FORCE_UPDATE_VERSION', '1.0.0');
define('PUSH_NOTIFICATION_KEY', 'your_firebase_server_key');

// Maintenance Mode
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'We are currently performing scheduled maintenance. Please check back soon.');
define('MAINTENANCE_ALLOWED_IPS', ['your.admin.ip.address']);

// Environment Detection
function isProduction() {
    return $_SERVER['HTTP_HOST'] === 'foreveryoungtours.com' || 
           $_SERVER['HTTP_HOST'] === 'www.foreveryoungtours.com';
}

// Auto-detect environment and set appropriate configurations
if (isProduction()) {
    // Use production settings
    define('DB_HOST', PROD_DB_HOST);
    define('DB_NAME', PROD_DB_NAME);
    define('DB_USER', PROD_DB_USER);
    define('DB_PASS', PROD_DB_PASS);
    define('SITE_URL', PROD_SITE_URL);
    define('STRIPE_PUBLISHABLE_KEY', PROD_STRIPE_PUBLISHABLE_KEY);
    define('STRIPE_SECRET_KEY', PROD_STRIPE_SECRET_KEY);
    define('PAYPAL_CLIENT_ID', PROD_PAYPAL_CLIENT_ID);
    define('PAYPAL_CLIENT_SECRET', PROD_PAYPAL_CLIENT_SECRET);
    define('PAYPAL_MODE', PROD_PAYPAL_MODE);
    
    // Disable error reporting in production
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_FILE_PATH);
} else {
    // Use development settings (from main config.php)
    // This allows seamless switching between environments
}

// Production Security Headers
function setProductionHeaders() {
    if (isProduction()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://js.stripe.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com; img-src \'self\' data: https:; connect-src \'self\' https://api.stripe.com;');
        
        if (FORCE_SSL && !isset($_SERVER['HTTPS'])) {
            $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirectURL");
            exit();
        }
    }
}

// Call security headers function
setProductionHeaders();

// Production Database Connection with Connection Pooling
function getProductionDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . PROD_DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // Enable persistent connections
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . PROD_DB_CHARSET,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Production database connection failed: " . $e->getMessage());
            
            // Show maintenance page instead of exposing error
            if (isProduction()) {
                http_response_code(503);
                include 'maintenance.html';
                exit();
            } else {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    }
    
    return $pdo;
}

// Production Logging Function
function logError($message, $context = []) {
    if (isProduction()) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'server' => $_SERVER['HTTP_HOST'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        error_log(json_encode($logEntry), 3, LOG_FILE_PATH);
        
        // Send to Sentry if configured
        if (defined('SENTRY_DSN') && SENTRY_DSN) {
            // Sentry integration would go here
        }
    }
}

// Health Check Endpoint
function healthCheck() {
    $health = [
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => '1.0.0',
        'checks' => []
    ];
    
    // Database check
    try {
        $pdo = getProductionDBConnection();
        $pdo->query('SELECT 1');
        $health['checks']['database'] = 'healthy';
    } catch (Exception $e) {
        $health['checks']['database'] = 'unhealthy';
        $health['status'] = 'unhealthy';
    }
    
    // Redis check (if enabled)
    if (defined('REDIS_HOST')) {
        try {
            $redis = new Redis();
            $redis->connect(REDIS_HOST, REDIS_PORT);
            if (defined('REDIS_PASSWORD')) {
                $redis->auth(REDIS_PASSWORD);
            }
            $redis->ping();
            $health['checks']['redis'] = 'healthy';
            $redis->close();
        } catch (Exception $e) {
            $health['checks']['redis'] = 'unhealthy';
        }
    }
    
    // File system check
    $health['checks']['filesystem'] = is_writable(UPLOADS_PATH) ? 'healthy' : 'unhealthy';
    
    return $health;
}

// Handle health check requests
if (isset($_GET['health-check'])) {
    header('Content-Type: application/json');
    echo json_encode(healthCheck());
    exit();
}
?>
