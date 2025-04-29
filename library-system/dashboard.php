<?php
// Disable caching to ensure fresh data
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'config.php';
require_once 'Logger.php';
require_once 'utils.php';

// Require login to access this page
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['staff_id'])) {
    header('Location: index.php');
    exit;
}

$logger = Logger::getInstance();
$stats = [
    'total_books' => 0,
    'books_borrowed' => 0,
    'total_members' => 0,
    'active_members' => 0,
    'overdue_loans' => 0,
    'unpaid_fines' => 0
];

$conn = getDbConnection();
if ($conn) {
    try {
        // Force re-fetch real-time data from database
        
        // Get total books - matches database: 219
        $stmt = $conn->query("SELECT SUM(total_copies) as total FROM books");
        $row = $stmt->fetch_assoc();
        $stats['total_books'] = (int)($row['total'] ?? 0);
        
        // Get books borrowed - matches database: 2
        $stmt = $conn->query("SELECT SUM(total_copies - available_copies) as borrowed FROM books");
        $row = $stmt->fetch_assoc();
        $stats['books_borrowed'] = (int)($row['borrowed'] ?? 0);
        
        // Get total members - matches database: 8
        $stmt = $conn->query("SELECT COUNT(*) as total FROM members");
        $row = $stmt->fetch_assoc();
        $stats['total_members'] = (int)($row['total'] ?? 0);
        
        // Get active members - matches database: 8 (all members are active)
        $stmt = $conn->query("SELECT COUNT(*) as active FROM members WHERE membership_status = 'active'");
        $row = $stmt->fetch_assoc();
        $stats['active_members'] = (int)($row['active'] ?? 0);
        
        // Get overdue loans - matches database: 0
        $stmt = $conn->query("SELECT COUNT(*) as overdue FROM transactions WHERE status = 'overdue'");
        $row = $stmt->fetch_assoc();
        $stats['overdue_loans'] = (int)($row['overdue'] ?? 0);
        
        // Get unpaid fines - matches database: 1
        $stmt = $conn->query("SELECT COUNT(*) as unpaid FROM fines WHERE payment_status = 'unpaid'");
        $row = $stmt->fetch_assoc();
        $stats['unpaid_fines'] = (int)($row['unpaid'] ?? 0);
        
    } catch (Exception $e) {
        $logger->error("Dashboard statistics error: " . $e->getMessage(), $e);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Dashboard - Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Library System</h2>
            </div>
            <ul class="nav-links">
                <li class="active"><a href="dashboard.php">Dashboard</a></li>
                <li><a href="books.php">Books</a></li>
                <li><a href="members.php">Members</a></li>
                <li><a href="transactions.php">Transactions</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li><a href="staff.php">Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'User'); ?></span>
                </div>
            </div>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Total Books</h3>
                    <p class="stat-number"><?php echo $stats['total_books']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Books Borrowed</h3>
                    <p class="stat-number"><?php echo $stats['books_borrowed']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Members</h3>
                    <p class="stat-number"><?php echo $stats['total_members']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Active Members</h3>
                    <p class="stat-number"><?php echo $stats['active_members']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Overdue Loans</h3>
                    <p class="stat-number"><?php echo $stats['overdue_loans']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Unpaid Fines</h3>
                    <p class="stat-number"><?php echo $stats['unpaid_fines']; ?></p>
                </div>
            </div>
            
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <a href="books_add.php" class="btn btn-primary">Add New Book</a>
                    <a href="members_add.php" class="btn btn-primary">Add New Member</a>
                    <a href="transactions_add.php" class="btn btn-primary">Issue Book</a>
                    <a href="transactions.php?filter=overdue" class="btn btn-warning">View Overdue Books</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Force refresh of data by preventing caching
    document.addEventListener('DOMContentLoaded', function() {
        // Add timestamp to prevent browser caching
        const timestamp = new Date().getTime();
        
        // Function to refresh the page every 30 seconds
        function refreshData() {
            location.reload(true);
        }
        
        // Set to refresh every 30 seconds
        setTimeout(refreshData, 30000);
    });
    </script>
</body>
</html> 