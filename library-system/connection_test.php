<?php
// Include configuration file
require_once 'config.php';

// Set content type to HTML
header('Content-Type: text/html');

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Database Connection Test</h1>';

try {
    // Get database connection
    $conn = getDbConnection();
    
    echo '<div class="success">✓ Connection successful!</div>';
    
    // Database configuration
    echo '<h2>Database Configuration</h2>';
    echo '<table>
        <tr><th>Parameter</th><th>Value</th></tr>
        <tr><td>Host</td><td>' . DB_HOST . '</td></tr>
        <tr><td>Database</td><td>' . DB_NAME . '</td></tr>
        <tr><td>User</td><td>' . DB_USER . '</td></tr>
        <tr><td>Port</td><td>' . DB_PORT . '</td></tr>
    </table>';
    
    // Test query to verify tables exist
    $tables = ['books', 'members', 'staff', 'staff_login', 'transactions', 'fines'];
    echo '<h2>Database Tables</h2>';
    echo '<table>
        <tr>
            <th>Table Name</th>
            <th>Status</th>
            <th>Record Count</th>
        </tr>';
    
    foreach ($tables as $table) {
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            
            if ($stmt->rowCount() > 0) {
                // Table exists, count records
                $countStmt = $conn->prepare("SELECT COUNT(*) FROM {$table}");
                $countStmt->execute();
                $count = $countStmt->fetchColumn();
                
                echo '<tr>
                    <td>' . $table . '</td>
                    <td class="success">✓ Exists</td>
                    <td>' . $count . ' record(s)</td>
                </tr>';
            } else {
                echo '<tr>
                    <td>' . $table . '</td>
                    <td class="error">✗ Does not exist</td>
                    <td>-</td>
                </tr>';
            }
        } catch (PDOException $e) {
            echo '<tr>
                <td>' . $table . '</td>
                <td class="error">✗ Error: ' . $e->getMessage() . '</td>
                <td>-</td>
            </tr>';
        }
    }
    
    echo '</table>';
    
    // Check for specific data
    echo '<h2>Data Verification</h2>';
    
    // Verify admin user
    try {
        $stmt = $conn->prepare("SELECT * FROM staff_login WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo '<div class="success">✓ Admin user exists</div>';
        } else {
            echo '<div class="error">✗ Admin user does not exist</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="error">✗ Error checking admin user: ' . $e->getMessage() . '</div>';
    }
    
    // Check for books with Ugandan authors
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE author LIKE '%Isegawa%' OR author LIKE '%Kyomuhendo%' OR author LIKE '%Museveni%'");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            echo '<div class="success">✓ Found ' . $count . ' books with Ugandan authors</div>';
        } else {
            echo '<div class="error">✗ No books with Ugandan authors found</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="error">✗ Error checking Ugandan books: ' . $e->getMessage() . '</div>';
    }
    
    // Check for Ugandan members
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE first_name LIKE 'N%' OR first_name LIKE 'M%' OR first_name LIKE 'S%'");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            echo '<div class="success">✓ Found ' . $count . ' members with typical Ugandan names</div>';
        } else {
            echo '<div class="error">✗ No members with typical Ugandan names found</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="error">✗ Error checking Ugandan members: ' . $e->getMessage() . '</div>';
    }
    
} catch (PDOException $e) {
    echo '<div class="error">✗ Connection failed: ' . $e->getMessage() . '</div>';
    echo '<h3>Troubleshooting Tips:</h3>';
    echo '<ol>
        <li>Make sure WAMP server is running (green icon)</li>
        <li>Verify database credentials in config.php</li>
        <li>Check if database \'' . DB_NAME . '\' exists</li>
        <li>Make sure MySQL service is running</li>
    </ol>';
}

echo '<p><a href="index.php">Go to Login Page</a></p>';
echo '</body></html>';
?> 