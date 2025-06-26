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
define('SECRET_KEY', 'your-secret-key-here');
define('CSRF_TOKEN_EXPIRE', 3600); // 1 hour

// Session configuration
session_start();

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Include authentication functions
require_once 'auth.php';

// Autoload classes
spl_autoload_register(function($class) {
    require_once __DIR__ . '/../classes/' . $class . '.php';
});

// Include common functions
require_once 'functions.php';
?>