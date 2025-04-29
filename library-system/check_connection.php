<?php
// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration
require_once 'config.php';

echo "<h1>Database Connection Check</h1>";

try {
    // Get database connection
    $conn = getDbConnection();
    
    if (!$conn) {
        echo "<div style='color: red;'>Database connection failed. Check your credentials in config.php.</div>";
        exit;
    }
    
    echo "<div style='color: green;'>✓ Database connection successful!</div>";
    
    // Get PDO driver info
    echo "<h2>PDO Driver Information:</h2>";
    echo "<ul>";
    echo "<li>PDO Driver Name: " . $conn->getAttribute(PDO::ATTR_DRIVER_NAME) . "</li>";
    echo "<li>Server Version: " . $conn->getAttribute(PDO::ATTR_SERVER_VERSION) . "</li>";
    echo "<li>Client Version: " . $conn->getAttribute(PDO::ATTR_CLIENT_VERSION) . "</li>";
    echo "</ul>";
    
    echo "<h2>Database Tables:</h2>";
    $tables = array('books', 'members', 'staff', 'staff_login', 'transactions', 'fines');
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Table Name</th><th>Exists</th><th>Row Count</th></tr>";
    
    foreach ($tables as $table) {
        try {
            // Check if table exists
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            
            // Get row count if table exists
            $count = 0;
            if ($exists) {
                $countStmt = $conn->query("SELECT COUNT(*) FROM $table");
                $count = $countStmt->fetchColumn();
            }
            
            echo "<tr>";
            echo "<td>$table</td>";
            echo "<td style='color: " . ($exists ? "green" : "red") . "'>" . ($exists ? "✓" : "✗") . "</td>";
            echo "<td>$count rows</td>";
            echo "</tr>";
        } catch (PDOException $e) {
            echo "<tr>";
            echo "<td>$table</td>";
            echo "<td colspan='2' style='color: red'>Error: " . $e->getMessage() . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    echo "<h2>Next Steps:</h2>";
    echo "<ol>";
    echo "<li><a href='fix_database.php'>Run the database fix script</a> to update column names and structure</li>";
    echo "<li>Then <a href='index.php'>try to login</a> with username: admin and password: password123</li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
}
?> 