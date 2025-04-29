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
    header('Location: members.php');
    exit;
}

$memberId = (int)$_GET['id'];
$member = null;
$transactions = [];

// Fetch member details
$conn = getDbConnection();
if ($conn) {
    try {
        // Get member information
        $stmt = $conn->prepare("
            SELECT member_id, name, email, phone, address, membership_date, status 
            FROM members 
            WHERE member_id = ?
        ");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Member not found";
        } else {
            $member = $result->fetch_assoc();
            
            // Get member's transaction history
            $stmt = $conn->prepare("
                SELECT t.loan_id, t.loan_date, t.due_date, t.return_date, t.status,
                       b.book_id, b.title, b.author
                FROM transactions t
                JOIN books b ON t.book_id = b.book_id
                WHERE t.member_id = ?
                ORDER BY t.loan_date DESC
            ");
            $stmt->bind_param("i", $memberId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $transactions[] = $row;
            }
        }
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $error = "Error fetching member details. Please try again.";
        $logger->error("Error fetching member ID $memberId: " . $e->getMessage(), $e);
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
    <title>Member Details - Library Management System</title>
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
                <li class="active"><a href="members.php">Members</a></li>
                <li><a href="transactions.php">Transactions</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li><a href="staff.php">Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Member Details</h1>
                <div class="actions">
                    <a href="members.php" class="btn btn-secondary">Back to Members</a>
                    <?php if ($member): ?>
                        <a href="members_edit.php?id=<?php echo $memberId; ?>" class="btn btn-primary">Edit Member</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($member): ?>
                <div class="content-wrapper">
                    <div class="member-details">
                        <div class="detail-card">
                            <h2><?php echo htmlspecialchars($member['name']); ?></h2>
                            <div class="status-badge <?php echo strtolower($member['status']); ?>">
                                <?php echo htmlspecialchars($member['status']); ?>
                            </div>
                            
                            <div class="detail-section">
                                <h3>Contact Information</h3>
                                <div class="detail-row">
                                    <div class="detail-label">Email:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Phone:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Address:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($member['address'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                            
                            <div class="detail-section">
                                <h3>Membership Information</h3>
                                <div class="detail-row">
                                    <div class="detail-label">Member ID:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($member['member_id']); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Membership Date:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($member['membership_date']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="member-transactions">
                        <h3>Transaction History</h3>
                        
                        <?php if (empty($transactions)): ?>
                            <p>No transaction history found.</p>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Loan ID</th>
                                            <th>Book Title</th>
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
                                                        <a href="transactions_return.php?id=<?php echo $transaction['loan_id']; ?>" class="btn btn-icon btn-return" title="Return Book">Return</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <div class="action-buttons">
                            <a href="transactions_add.php?member_id=<?php echo $memberId; ?>" class="btn btn-primary">Issue New Book</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 
