<?php
/**
 * Database Configuration
 * 
 * This file contains the database connection parameters and other
 * global configuration settings for the Library Management System.
 */

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library_system');
define('DB_PORT', 3306);

// Application paths
define('APP_ROOT', dirname(__FILE__));
define('URL_ROOT', 'http://localhost/library-system');

// Logging configuration
define('LOG_DIR', APP_ROOT . '/logs');
define('LOG_FILE', LOG_DIR . '/app.log');   
define('ERROR_LOG', LOG_DIR . '/error.log');

// Session lifetime (in seconds)
define('SESSION_LIFETIME', 1800); // 30 minutes

// Ensure log directory exists
if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

/**
 * Function to get database connection
 * 
 * @return mysqli|null Database connection or null on failure
 */
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log('Database Connection Error: ' . $conn->connect_error, 0);
        return null;
    }
    return $conn;
}

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters for better security
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
}

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', 1); // Change from 0 to 1 to show errors to users
ini_set('log_errors', 1);

// Set error log path
ini_set('error_log', __DIR__ . '/logs/error.log');

/**
 * Log error message to file
 * @param string $message Error message
 * @param Exception|null $exception Optional exception for stack trace
 */
function logError($message, $exception = null) {
    $logEntry = date('[Y-m-d H:i:s]') . ' ' . $message;
    
    if ($exception !== null) {
        $logEntry .= "\nStack trace: " . $exception->getTraceAsString();
    }
    
    error_log($logEntry . "\n", 3, __DIR__ . '/logs/error.log');
}

/**
 * Display user-friendly error message and log technical details
 * @param string $userMessage Message to display to user
 * @param string $technicalMessage Technical details to log
 * @param Exception|null $exception Optional exception for stack trace
 */
function handleError($userMessage, $technicalMessage, $exception = null) {
    logError($technicalMessage, $exception);
    echo json_encode(['success' => false, 'message' => $userMessage]);
    exit;
}
?> 