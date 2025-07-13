<?php
function getCurrentUrl()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    return $protocol . $host . $uri;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Forever Young Tours - Main Configuration File
// Database and system configuration

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Africa/Kigali');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'database_fyt');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');



// Paths
define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . '/webtour');
// URLs should be:
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/webtour');
define('ADMIN_PATH', ROOT_PATH . '/admin');

define('ADMIN_URL', SITE_URL . '/admin');
define('ASSETS_URL', SITE_URL . '/assets');
define('UPLOADS_URL', SITE_URL . '/uploads');

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 28800); // 8 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls');
define('FROM_EMAIL', 'noreply@foreveryoungtours.com');
define('FROM_NAME', 'Forever Young Tours');

// Payment Gateway Configuration
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_your_stripe_publishable_key');
define('STRIPE_SECRET_KEY', 'sk_test_your_stripe_secret_key');
define('PAYPAL_CLIENT_ID', 'your_paypal_client_id');
define('PAYPAL_CLIENT_SECRET', 'your_paypal_client_secret');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' or 'live'

// File Upload Configuration
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Currency
define('DEFAULT_CURRENCY', 'USD');
define('CURRENCY_SYMBOL', '$');

// Language Configuration
$available_languages = ['en' => 'English', 'fr' => 'French', 'rw' => 'Kinyarwanda'];
$default_language = 'en';
$current_language = $_SESSION['language'] ?? $_COOKIE['language'] ?? $default_language;

if (!array_key_exists($current_language, $available_languages)) {
    $current_language = $default_language;
}

setcookie('language', $current_language, time() + (365 * 24 * 60 * 60), '/');
require_once __DIR__ . '/../languages/' . $current_language . '.php';


// Database Connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];

    $db = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

// Helper Functions
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateRandomString($length = 10)
{
    return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}

function formatCurrency($amount, $currency = DEFAULT_CURRENCY)
{
    // Fallback to 0 if amount is null or not numeric
    $amount = is_numeric($amount) ? (float) $amount : 0;

    switch ($currency) {
        case 'USD':
            return '$' . number_format($amount, 2);
        case 'EUR':
            return '€' . number_format($amount, 2);
        case 'GBP':
            return '£' . number_format($amount, 2);
        case 'RWF':
            return '₣' . number_format($amount, 0);
        default:
            return $currency . ' ' . number_format($amount, 2);
    }
}


if (!function_exists('timeAgo')) {
    function timeAgo($datetime)
    {
        $time = time() - strtotime($datetime);

        if ($time < 60)
            return 'just now';
        if ($time < 3600)
            return floor($time / 60) . ' minutes ago';
        if ($time < 86400)
            return floor($time / 3600) . ' hours ago';
        if ($time < 2592000)
            return floor($time / 86400) . ' days ago';
        if ($time < 31536000)
            return floor($time / 2592000) . ' months ago';

        return floor($time / 31536000) . ' years ago';
    }
}


function sendEmail($to, $subject, $message, $headers = [])
{
    // Email sending implementation
    return true;
}

function uploadFile($file, $destination, $allowedTypes = null)
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }

    $fileSize = $file['size'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Check file size
    if ($fileSize > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size too large'];
    }

    // Check file type
    if ($allowedTypes && !in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }

    // Generate unique filename
    $newFileName = uniqid() . '.' . $fileType;
    $uploadPath = $destination . '/' . $newFileName;

    // Create directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    // Move uploaded file
    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        return [
            'success' => true,
            'filename' => $newFileName,
            'path' => $uploadPath
        ];
    }

    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

// Auto-load classes
spl_autoload_register(function ($className) {
    $paths = [
        ROOT_PATH . '/classes/',
        ADMIN_PATH . '/includes/',
        ROOT_PATH . '/includes/'
    ];

    foreach ($paths as $path) {
        $file = $path . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            break;
        }
    }
});


// Initialize system settings

try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($settings as $key => $value) {
        if (!defined(strtoupper($key))) {
            define(strtoupper($key), $value);
        }
    }
} catch (Exception $e) {
    error_log("Failed to load system settings: " . $e->getMessage());
}

?>
