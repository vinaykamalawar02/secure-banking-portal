<?php
// Database configuration for SQLite
$db_path = __DIR__ . '/../database.sqlite';

// Create database file if it doesn't exist
if (!file_exists($db_path)) {
    touch($db_path);
}

try {
    $conn = new PDO('sqlite:' . $db_path);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Enable foreign keys
    $conn->exec('PRAGMA foreign_keys = ON');
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Security configuration
define('SECRET_KEY', 'bfd1436722e72f80cd8ba409383f272297a117f13840262b4067a55756a7b263');
define('CSRF_TOKEN_EXPIRE', 3600); // 1 hour

// Enhanced session configuration with security
// Use @ to suppress warnings for settings that might not be available
@ini_set('session.cookie_httponly', 1);
@ini_set('session.cookie_secure', 1);
@ini_set('session.use_strict_mode', 1);
@ini_set('session.cookie_samesite', 'Strict');
@ini_set('session.gc_maxlifetime', 1800); // 30 minutes

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Include security functions first
require_once 'security.php';

// Initialize secure session management
init_secure_session();

// Include authentication functions
require_once 'auth.php';

// Autoload classes
spl_autoload_register(function($class) {
    $class_file = __DIR__ . '/../classes/' . $class . '.php';
    if (file_exists($class_file)) {
        require_once $class_file;
    }
});

// Include common functions
require_once 'functions.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Check for HTTPS in production
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    // In production, you might want to redirect to HTTPS
    // header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    // exit();
}
?>