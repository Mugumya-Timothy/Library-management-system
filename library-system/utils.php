<?php
require_once 'config.php';
require_once 'Logger.php';

/**
 * Sanitize input data
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    if ($data === null || $data === '') {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number format
 * @param string $phone Phone number to validate
 * @return bool True if valid, false otherwise
 */
function isValidPhone($phone) {
    // Basic validation: only digits, spaces, dashes, parentheses, and plus sign
    return preg_match('/^[0-9\s\-\(\)\+]+$/', $phone) === 1;
}

/**
 * Validate date format
 * @param string $date Date to validate
 * @param string $format Expected date format (default: Y-m-d)
 * @return bool True if valid, false otherwise
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']);
}

/**
 * Require login to access page
 * Redirects to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Generate JSON response
 * @param bool $success Whether the operation was successful
 * @param string $message Message to include in response
 * @param array $data Additional data to include in response
 * @return string JSON response
 */
function jsonResponse($success, $message, $data = []) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response['data'] = $data;
    }
    
    header('Content-Type: application/json');
    return json_encode($response);
}

/**
 * Redirect with flash message
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function redirectWithMessage($url, $message, $type = 'info') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    
    header("Location: $url");
    exit;
}

/**
 * Display flash message if exists
 * @return string HTML for flash message or empty string
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'];
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return "<div class='alert alert-$type'>$message</div>";
    }
    
    return '';
}
?> 