<?php
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
$error = '';

// Check if id parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: staff.php');
    exit;
}

$staffId = (int)$_GET['id'];
$staff = null;
$transactions = [];
$has_login = false;

// Fetch staff details
$conn = getDbConnection();
if ($conn) {
    try {
        // Get staff information
        $stmt = $conn->prepare("
            SELECT s.staff_id, s.first_name, s.last_name, s.email, s.phone, s.position, s.hire_date,
                   CASE WHEN sl.username IS NOT NULL THEN 1 ELSE 0 END AS has_login,
                   sl.username
            FROM staff s
            LEFT JOIN staff_login sl ON s.staff_id = sl.staff_id
            WHERE s.staff_id = ?
        ");
        $stmt->bind_param("i", $staffId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Staff member not found";
        } else {
            $staff = $result->fetch_assoc();
            $has_login = $staff['has_login'] == 1;
            
            // Get staff's transaction history
            $stmt = $conn->prepare("
                SELECT t.loan_id, t.loan_date, t.due_date, t.return_date, t.status,
                       b.book_id, b.title, b.author,
                       m.member_id, m.name as member_name
                FROM transactions t
                JOIN books b ON t.book_id = b.book_id
                JOIN members m ON t.member_id = m.member_id
                WHERE t.staff_id = ?
                ORDER BY t.loan_date DESC
                LIMIT 100
            ");
            $stmt->bind_param("i", $staffId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $transactions[] = $row;
            }
        }
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $error = "Error fetching staff details. Please try again.";
        $logger->error("Error fetching staff ID $staffId: " . $e->getMessage(), $e);
    }
} else {
    $error = "Database connection error. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Details - Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Library System</h2>
            </div>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="books.php">Books</a></li>
                <li><a href="members.php">Members</a></li>
                <li><a href="transactions.php">Transactions</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li class="active"><a href="staff.php">Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Staff Details</h1>
                <div class="actions">
                    <a href="staff.php" class="btn btn-secondary">Back to Staff</a>
                    <?php if ($staff): ?>
                        <a href="staff_edit.php?id=<?php echo $staffId; ?>" class="btn btn-primary">Edit Staff</a>
                        <?php if ($staffId != $_SESSION['staff_id']): ?>
                            <a href="staff_delete.php?id=<?php echo $staffId; ?>" class="btn btn-danger">Delete Staff</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($staff): ?>
                <div class="content-wrapper">
                    <div class="staff-details">
                        <div class="detail-card">
                            <h2><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h2>
                            <div class="position"><?php echo htmlspecialchars($staff['position'] ?: 'N/A'); ?></div>
                            
                            <?php if ($staffId == $_SESSION['staff_id']): ?>
                                <div class="status-badge current-user">Current User</div>
                            <?php endif; ?>
                            
                            <div class="detail-section">
                                <h3>Contact Information</h3>
                                <div class="detail-row">
                                    <div class="detail-label">Email:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($staff['email'] ?: 'N/A'); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Phone:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($staff['phone'] ?: 'N/A'); ?></div>
                                </div>
                            </div>
                            
                            <div class="detail-section">
                                <h3>Employment Information</h3>
                                <div class="detail-row">
                                    <div class="detail-label">Staff ID:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($staff['staff_id']); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Hire Date:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($staff['hire_date'] ? date('Y-m-d', strtotime($staff['hire_date'])) : 'N/A'); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Login Status:</div>
                                    <div class="detail-value">
                                        <?php if ($has_login): ?>
                                            <span class="status-active">Has Login (<?php echo htmlspecialchars($staff['username']); ?>)</span>
                                        <?php else: ?>
                                            <span class="status-inactive">No Login Account</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="staff-transactions">
                        <h3>Transaction History</h3>
                        
                        <?php if (empty($transactions)): ?>
                            <p>No transaction history found.</p>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Loan ID</th>
                                            <th>Book</th>
                                            <th>Member</th>
                                            <th>Loan Date</th>
                                            <th>Due Date</th>
                                            <th>Return Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($transaction['loan_id']); ?></td>
                                                <td>
                                                    <a href="books_view.php?id=<?php echo $transaction['book_id']; ?>">
                                                        <?php echo htmlspecialchars($transaction['title']); ?>
                                                    </a>
                                                    <div class="text-muted"><?php echo htmlspecialchars($transaction['author']); ?></div>
                                                </td>
                                                <td>
                                                    <a href="members_view.php?id=<?php echo $transaction['member_id']; ?>">
                                                        <?php echo htmlspecialchars($transaction['member_name']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($transaction['loan_date']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['due_date']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['return_date'] ?? 'Not returned'); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo strtolower($transaction['status']); ?>">
                                                        <?php echo htmlspecialchars($transaction['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($transaction['status'] !== 'Returned'): ?>
                                                        <a href="transactions_return.php?id=<?php echo $transaction['loan_id']; ?>" class="btn btn-sm btn-primary">Return</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 
