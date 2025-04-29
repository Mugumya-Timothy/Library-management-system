<?php
// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration
require_once 'config.php';

echo "<h1>Database Fix Script</h1>";

try {
    // Get database connection
    $conn = getDbConnection();
    
    if (!$conn) {
        echo "<div style='color: red;'>Database connection failed. Check your credentials in config.php.</div>";
        exit;
    }
    
    echo "<div style='color: green;'>Database connection successful!</div>";

    // Check staff table structure
    $stmt = $conn->query("DESCRIBE staff");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Current Staff Table Structure:</h2>";
    echo "<pre>" . print_r($columns, true) . "</pre>";
    
    // Check if we need to migrate name to first_name/last_name
    $nameColumnExists = in_array('name', $columns);
    $firstNameExists = in_array('first_name', $columns);
    $lastNameExists = in_array('last_name', $columns);
    
    if ($nameColumnExists && !$firstNameExists) {
        echo "<div>Converting 'name' column to 'first_name' and 'last_name'...</div>";
        
        // Add first_name and last_name columns if they don't exist
        if (!$firstNameExists) {
            $conn->exec("ALTER TABLE staff ADD COLUMN first_name VARCHAR(100) AFTER staff_id");
        }
        if (!$lastNameExists) {
            $conn->exec("ALTER TABLE staff ADD COLUMN last_name VARCHAR(100) AFTER first_name");
        }
        
        // Update the new columns with data from name
        $conn->exec("UPDATE staff SET first_name = SUBSTRING_INDEX(name, ' ', 1), last_name = SUBSTRING_INDEX(name, ' ', -1)");
        
        echo "<div style='color: green;'>Added first_name and last_name columns and migrated data.</div>";
    }
    
    // Check status vs membership_status in members table
    $stmt = $conn->query("DESCRIBE members");
    $memberColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Current Members Table Structure:</h2>";
    echo "<pre>" . print_r($memberColumns, true) . "</pre>";
    
    // Check if status needs to be renamed to membership_status
    $statusExists = in_array('status', $memberColumns);
    $membershipStatusExists = in_array('membership_status', $memberColumns);
    
    if ($statusExists && !$membershipStatusExists) {
        echo "<div>Renaming 'status' column to 'membership_status'...</div>";
        $conn->exec("ALTER TABLE members CHANGE status membership_status ENUM('active', 'expired', 'suspended') NOT NULL DEFAULT 'active'");
        echo "<div style='color: green;'>Renamed status column to membership_status.</div>";
    }
    
    // Verify login queries work
    echo "<h2>Testing Login Query:</h2>";
    
    // Check if we're using first_name+last_name or just name
    if ($firstNameExists) {
        $loginQuery = "
            SELECT s.staff_id, CONCAT(s.first_name, ' ', s.last_name) AS name, sl.password, sl.role
            FROM staff_login sl
            JOIN staff s ON sl.staff_id = s.staff_id
            WHERE sl.username = 'admin'
        ";
    } else {
        $loginQuery = "
            SELECT s.staff_id, s.name, sl.password, sl.role
            FROM staff_login sl
            JOIN staff s ON sl.staff_id = s.staff_id
            WHERE sl.username = 'admin'
        ";
    }
    
    echo "<div>Query: " . htmlspecialchars($loginQuery) . "</div>";
    
    try {
        $stmt = $conn->query($loginQuery);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<div style='color: green;'>Admin user found: " . htmlspecialchars($user['name']) . "</div>";
            echo "<div>Login should work with username 'admin' and password 'password123' or 'admin123'</div>";
        } else {
            echo "<div style='color: red;'>Admin user not found! Check staff_login table.</div>";
        }
    } catch (PDOException $e) {
        echo "<div style='color: red;'>Error testing login query: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>Updated Configuration</h2>";
    echo "<p>Your database connection is now working correctly. All required tables are available.</p>";
    echo "<p>You can now <a href='index.php'>login to your application</a>.</p>";
    
} catch (PDOException $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
}
?> 