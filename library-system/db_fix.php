<?php
// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration
require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Library Database Fix Tool</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        h1 { color: #333; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; }
        .container { max-width: 800px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Library Database Fix Tool</h1>";

try {
    // Get database connection
    $conn = getDbConnection();
    
    if (!$conn) {
        echo "<div class='error'>Database connection failed. Check your credentials in config.php.</div>";
        exit;
    }
    
    echo "<div class='success'>✓ Database connection successful!</div>";
    
    // 1. Check if the database exists
    echo "<h2>Checking Database:</h2>";
    $dbExists = false;
    try {
        $stmt = $conn->query("SELECT DATABASE()");
        $dbName = $stmt->fetchColumn();
        echo "<div>Current database: <strong>{$dbName}</strong></div>";
        $dbExists = true;
    } catch (PDOException $e) {
        echo "<div class='error'>⚠ Database not selected: " . $e->getMessage() . "</div>";
    }
    
    if (!$dbExists) {
        echo "<div class='error'>Please create the database and import the database_setup.sql file first.</div>";
        exit;
    }
    
    // 2. Check and fix required tables
    echo "<h2>Checking Tables:</h2>";
    $requiredTables = ['books', 'members', 'staff', 'staff_login', 'transactions', 'fines'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() === 0) {
            $missingTables[] = $table;
        }
    }
    
    if (count($missingTables) > 0) {
        echo "<div class='error'>⚠ Missing tables: " . implode(', ', $missingTables) . "</div>";
        echo "<div>Please run the 'database_setup.sql' script to create these tables.</div>";
    } else {
        echo "<div class='success'>✓ All required tables exist.</div>";
    }
    
    // 3. Fix staff table structure
    echo "<h2>Checking Staff Table Structure:</h2>";
    
    // Get all columns from staff table
    $stmt = $conn->query("DESCRIBE staff");
    $staffColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $staffColumns[$row['Field']] = $row;
    }
    
    echo "<div>Staff table columns: " . implode(', ', array_keys($staffColumns)) . "</div>";
    
    // Check if we need to fix naming inconsistencies
    $needsFirstLastNameFix = isset($staffColumns['name']) && !isset($staffColumns['first_name']);
    
    if ($needsFirstLastNameFix) {
        echo "<div class='warning'>⚠ Staff table has 'name' column but missing 'first_name' and 'last_name' columns.</div>";
        
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Add the new columns
            $conn->exec("ALTER TABLE staff ADD COLUMN first_name VARCHAR(100) AFTER staff_id");
            $conn->exec("ALTER TABLE staff ADD COLUMN last_name VARCHAR(100) AFTER first_name");
            
            // Split the name into first and last
            $conn->exec("UPDATE staff SET 
                first_name = SUBSTRING_INDEX(name, ' ', 1),
                last_name = SUBSTRING_INDEX(name, ' ', -1)");
                
            $conn->commit();
            echo "<div class='success'>✓ Added first_name and last_name columns and migrated data.</div>";
        } catch (PDOException $e) {
            $conn->rollBack();
            echo "<div class='error'>Failed to update staff table: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='success'>✓ Staff table structure is correct.</div>";
    }
    
    // 4. Fix members table structure
    echo "<h2>Checking Members Table Structure:</h2>";
    
    // Get all columns from members table
    $stmt = $conn->query("DESCRIBE members");
    $memberColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $memberColumns[$row['Field']] = $row;
    }
    
    echo "<div>Members table columns: " . implode(', ', array_keys($memberColumns)) . "</div>";
    
    // Check if status field needs to be renamed to membership_status
    $needsStatusRename = isset($memberColumns['status']) && !isset($memberColumns['membership_status']);
    
    if ($needsStatusRename) {
        echo "<div class='warning'>⚠ Members table has 'status' column but needs 'membership_status'.</div>";
        
        try {
            // Get current status field type
            $statusType = $memberColumns['status']['Type'];
            
            // Start transaction
            $conn->beginTransaction();
            
            // Rename the column preserving the type
            $conn->exec("ALTER TABLE members CHANGE status membership_status {$statusType}");
                
            $conn->commit();
            echo "<div class='success'>✓ Renamed status column to membership_status.</div>";
        } catch (PDOException $e) {
            $conn->rollBack();
            echo "<div class='error'>Failed to update members table: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='success'>✓ Members table structure is correct.</div>";
    }
    
    // 5. Verify admin user exists
    echo "<h2>Checking Admin User:</h2>";
    
    $stmt = $conn->query("SELECT * FROM staff_login WHERE username = 'admin'");
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminUser) {
        echo "<div class='success'>✓ Admin user exists with ID: {$adminUser['staff_id']}</div>";
    } else {
        echo "<div class='error'>⚠ Admin user does not exist!</div>";
        
        try {
            // Check if staff table has any records
            $stmt = $conn->query("SELECT staff_id FROM staff LIMIT 1");
            $staffExists = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($staffExists) {
                $staffId = $staffExists['staff_id'];
                
                // Start transaction
                $conn->beginTransaction();
                
                // Add admin user to staff_login
                $stmt = $conn->prepare("INSERT INTO staff_login (staff_id, username, password, role) VALUES (?, 'admin', ?, 'admin')");
                $stmt->execute([$staffId, password_hash('password123', PASSWORD_DEFAULT)]);
                
                $conn->commit();
                echo "<div class='success'>✓ Added admin user with username 'admin' and password 'password123'.</div>";
            } else {
                echo "<div class='error'>No staff records exist. Please add staff members first.</div>";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            echo "<div class='error'>Failed to add admin user: " . $e->getMessage() . "</div>";
        }
    }
    
    echo "<h2>Testing Login Query:</h2>";
    
    try {
        $query = "
            SELECT s.staff_id, 
                   " . (isset($staffColumns['first_name']) ? "CONCAT(s.first_name, ' ', s.last_name)" : "s.name") . " AS name, 
                   sl.password, 
                   sl.role
            FROM staff_login sl
            JOIN staff s ON sl.staff_id = s.staff_id
            WHERE sl.username = 'admin'
        ";
        
        echo "<div class='code'>" . htmlspecialchars($query) . "</div>";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<div class='success'>✓ Login query successful for admin user.</div>";
            echo "<div>Name: " . htmlspecialchars($user['name']) . "</div>";
            echo "<div>Role: " . htmlspecialchars($user['role']) . "</div>";
        } else {
            echo "<div class='error'>⚠ Login query returned no results.</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='error'>Login query error: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>Next Steps:</h2>";
    echo "<ol>
        <li>Return to the <a href='index.php'>login page</a> and try logging in with username: <strong>admin</strong> and password: <strong>password123</strong></li>
        <li>If you still encounter errors, check your web server's error logs</li>
        <li>Make sure all PHP extensions are enabled in php.ini (especially pdo_mysql)</li>
    </ol>";
    
} catch (PDOException $e) {
    echo "<div class='error'>Database error: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?> 