<?php
require_once 'config.php';
require_once 'utils/logger.php';

// Initialize the logger
$logger = new Logger();

echo "<h1>Database Connection Test</h1>";

try {
    // Get database connection
    $conn = getDbConnection();
    
    echo "<div style='color: green; font-weight: bold;'>Connection successful!</div>";
    
    // Test query to verify tables exist
    $tables = ['books', 'members', 'staff', 'staff_login', 'transactions', 'fines'];
    echo "<h2>Checking Database Tables:</h2>";
    echo "<ul>";
    
    foreach ($tables as $table) {
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                echo "<li style='color: green;'>Table '{$table}' exists</li>";
                
                // Count records in the table
                $countStmt = $conn->prepare("SELECT COUNT(*) FROM {$table}");
                $countStmt->execute();
                $count = $countStmt->fetchColumn();
                echo " - Contains {$count} record(s)";
            } else {
                echo "<li style='color: red;'>Table '{$table}' does not exist</li>";
            }
        } catch (PDOException $e) {
            echo "<li style='color: red;'>Error checking table '{$table}': " . $e->getMessage() . "</li>";
        }
    }
    
    echo "</ul>";
    
    // Check if admin user exists
    try {
        $stmt = $conn->prepare("SELECT * FROM staff_login WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "<div style='color: green;'>Admin user exists</div>";
        } else {
            echo "<div style='color: red;'>Admin user does not exist</div>";
        }
    } catch (PDOException $e) {
        echo "<div style='color: red;'>Error checking admin user: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>Database Configuration:</h2>";
    echo "<ul>";
    echo "<li>Host: " . DB_HOST . "</li>";
    echo "<li>Database: " . DB_NAME . "</li>";
    echo "<li>User: " . DB_USER . "</li>";
    echo "<li>Port: " . DB_PORT . "</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; font-weight: bold;'>Connection failed: " . $e->getMessage() . "</div>";
    echo "<h3>Troubleshooting Tips:</h3>";
    echo "<ol>";
    echo "<li>Make sure WAMP server is running (green icon)</li>";
    echo "<li>Verify database credentials in config.php</li>";
    echo "<li>Check if database 'library_system' exists</li>";
    echo "<li>Make sure MySQL service is running</li>";
    echo "</ol>";
    
    $logger->log("Database connection test failed: " . $e->getMessage(), "ERROR");
}

echo "<p><a href='index.php'>Return to Login</a></p>";
?> 