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
    <title>Fix Admin Password</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        h1 { color: #333; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; }
        .container { max-width: 800px; margin: 0 auto; }
        pre { background: #f9f9f9; padding: 10px; border-radius: 4px; overflow: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Fix Admin Password</h1>";

// Password to set
$newPassword = 'password123';
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

echo "<div>Attempting to fix the admin password to: <strong>{$newPassword}</strong></div>";
echo "<div>Generated hash: <code>{$hashedPassword}</code></div>";

try {
    // Get database connection
    $conn = getDbConnection();
    
    if (!$conn) {
        echo "<div class='error'>Database connection failed. Check your credentials in config.php.</div>";
        exit;
    }
    
    echo "<div class='success'>Database connection successful!</div>";
    
    // Check if admin user exists
    $stmt = $conn->query("SELECT * FROM staff_login WHERE username = 'admin'");
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminUser) {
        echo "<div class='error'>Admin user does not exist in the database!</div>";
        
        // Try to create admin user if we have staff
        $staffStmt = $conn->query("SELECT staff_id FROM staff LIMIT 1");
        $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($staff) {
            $staffId = $staff['staff_id'];
            echo "<div>Found staff ID: {$staffId}. Will create admin user.</div>";
            
            try {
                $insertStmt = $conn->prepare("INSERT INTO staff_login (staff_id, username, password, role) VALUES (?, 'admin', ?, 'admin')");
                $insertStmt->execute([$staffId, $hashedPassword]);
                
                echo "<div class='success'>Created new admin user with username 'admin' and password '{$newPassword}'</div>";
            } catch (PDOException $e) {
                echo "<div class='error'>Failed to create admin user: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<div class='error'>No staff members found in the database. Cannot create admin user.</div>";
        }
    } else {
        echo "<div>Admin user exists with ID: {$adminUser['staff_id']}</div>";
        echo "<div>Current password hash: <code>" . htmlspecialchars($adminUser['password']) . "</code></div>";
        
        // Update admin password
        try {
            $updateStmt = $conn->prepare("UPDATE staff_login SET password = ? WHERE username = 'admin'");
            $updateStmt->execute([$hashedPassword]);
            
            if ($updateStmt->rowCount() > 0) {
                echo "<div class='success'>Successfully updated admin password to '{$newPassword}'</div>";
            } else {
                echo "<div class='warning'>No changes made. Password might already be set correctly.</div>";
            }
        } catch (PDOException $e) {
            echo "<div class='error'>Failed to update admin password: " . $e->getMessage() . "</div>";
        }
    }
    
    // Check for any other users we could update
    $allUsersStmt = $conn->query("SELECT * FROM staff_login");
    $users = $allUsersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<h2>All Users in Database:</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Username</th><th>Role</th><th>Action</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role'] ?? 'N/A') . "</td>";
            echo "<td><a href='?reset_user=" . htmlspecialchars($user['username']) . "'>Reset Password</a></td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Handle password reset request
    if (isset($_GET['reset_user'])) {
        $username = $_GET['reset_user'];
        
        try {
            $resetStmt = $conn->prepare("UPDATE staff_login SET password = ? WHERE username = ?");
            $resetStmt->execute([$hashedPassword, $username]);
            
            if ($resetStmt->rowCount() > 0) {
                echo "<div class='success'>Successfully reset password for user '{$username}' to '{$newPassword}'</div>";
            } else {
                echo "<div class='error'>User '{$username}' not found or no changes made.</div>";
            }
        } catch (PDOException $e) {
            echo "<div class='error'>Failed to reset password: " . $e->getMessage() . "</div>";
        }
    }
    
    echo "<h2>Next Steps:</h2>";
    echo "<ol>
        <li>Return to the <a href='index.php'>login page</a></li>
        <li>Log in using username <strong>admin</strong> and password <strong>{$newPassword}</strong></li>
    </ol>";
    
} catch (PDOException $e) {
    echo "<div class='error'>Database error: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?> 