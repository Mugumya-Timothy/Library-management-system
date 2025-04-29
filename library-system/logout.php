<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'Logger.php';

$logger = Logger::getInstance();

// Log the logout action if user was logged in
if (isset($_SESSION['staff_id'])) {
    $staffId = $_SESSION['staff_id'];
    $staffName = $_SESSION['staff_name'] ?? 'Unknown';
    $logger->info("Staff ID $staffId ($staffName) logged out");
}

// Destroy session
$_SESSION = array();
session_destroy();

// Redirect to login page
header('Location: index.php');
exit;
?> 