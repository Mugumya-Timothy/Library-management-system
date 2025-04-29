<?php
// Direct admin password reset script

// Enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'config.php';

echo "<h1>Direct Admin Password Reset</h1>";

// Connect to database
try {
    // Use existing connection function
    $conn = getDbConnection();
    
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    echo "<div style='color:green'>Connected to database successfully!</div>";
    
    // Set the new password - hardcoded for simplicity
    $newPassword = 'password123';
    
    // Create password hash
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    echo "<div>Generated hash for '$newPassword': " . $hashedPassword . "</div>";
    
    // Update admin password directly
    $query = "UPDATE staff_login SET password = ? WHERE username = 'admin'";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $hashedPassword);
    $stmt->execute();
    
    $rowCount = $stmt->affected_rows;
    
    if ($rowCount > 0) {
        echo "<div style='color:green'>Admin password successfully reset to '$newPassword'</div>";
    } else {
        echo "<div style='color:orange'>No admin user found to update. Attempting to create one...</div>";
        
        // Try to find at least one staff member
        $staffQuery = "SELECT staff_id FROM staff LIMIT 1";
        $staffResult = $conn->query($staffQuery);
        
        if ($staffResult && $staffResult->num_rows > 0) {
            $staff = $staffResult->fetch_assoc();
            $staffId = $staff['staff_id'];
            
            // Try to insert admin user
            $insertQuery = "INSERT INTO staff_login (staff_id, username, password, role) 
                           VALUES (?, 'admin', ?, 'admin')";
            $insertStmt = $conn->prepare($insertQuery);
            if (!$insertStmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $insertStmt->bind_param("is", $staffId, $hashedPassword);
            $insertStmt->execute();
            
            echo "<div style='color:green'>Created new admin user with password '$newPassword'</div>";
        } else {
            echo "<div style='color:red'>No staff members found in database. Cannot create admin user.</div>";
        }
    }
    
    // Display all users for reference
    echo "<h2>All Users in Database:</h2>";
    $usersQuery = "SELECT * FROM staff_login";
    $usersResult = $conn->query($usersQuery);
    
    if ($usersResult && $usersResult->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Username</th><th>Staff ID</th><th>Role</th></tr>";
        
        while ($user = $usersResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['staff_id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<div style='color:red'>No users found in database.</div>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<div style='color:red'>Database Error: " . $e->getMessage() . "</div>";
}

echo "<p><a href='index.php'>Return to login page</a> and try logging in with username 'admin' and password '$newPassword'</p>";
?> 