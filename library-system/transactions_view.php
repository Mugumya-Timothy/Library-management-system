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
$success = '';

// Check for flash messages
if (isset($_SESSION['flash_message'])) {
    $success = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Check if id parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: transactions.php');
    exit;
}

$loanId = (int)$_GET['id'];
$transaction = null;
$fine = null;

// Fetch transaction details
$conn = getDbConnection();
if ($conn) {
    try {
        // Get transaction data
        $stmt = $conn->prepare("
            SELECT t.loan_id, t.book_id, t.member_id, t.loan_date, t.due_date, t.return_date, t.status,
                   b.title as book_title, b.author as book_author, b.isbn as book_isbn,
                   m.name as member_name, m.email as member_email, m.phone as member_phone
            FROM transactions t
            JOIN books b ON t.book_id = b.book_id
            JOIN members m ON t.member_id = m.member_id
            WHERE t.loan_id = ?
        ");
        $stmt->bind_param("i", $loanId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Transaction not found";
        } else {
            $transaction = $result->fetch_assoc();
            
            // Check if there's a fine associated with this transaction
            $stmt = $conn->prepare("
                SELECT fine_id, amount, issue_date, payment_status
                FROM fines
                WHERE loan_id = ?
            ");
            $stmt->bind_param("i", $loanId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $fine = $result->fetch_assoc();
            }
        }
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $error = "Error fetching transaction details. Please try again.";
        $logger->error("Error fetching transaction ID $loanId: " . $e->getMessage(), $e);
    }
}

// Process fine payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_fine' && $fine) {
    $conn = getDbConnection();
    
    if ($conn) {
        try {
            $stmt = $conn->prepare("
                UPDATE fines
                SET payment_status = 'Paid'
                WHERE fine_id = ?
            ");
            $stmt->bind_param("i", $fine['fine_id']);
            
            if ($stmt->execute()) {
                $fine['payment_status'] = 'Paid';
                $success = "Fine marked as paid successfully.";
                $logger->info("Fine ID {$fine['fine_id']} marked as paid by Staff ID {$_SESSION['staff_id']}");
            } else {
                $error = "Error updating fine payment status: " . $conn->error;
                $logger->error("Error updating fine payment status for Fine ID {$fine['fine_id']}: " . $conn->error);
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $error = "An error occurred. Please try again.";
            $logger->error("Fine payment error: " . $e->getMessage(), $e);
        }
        
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Details - Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/books.css">
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
                <li class="active"><a href="transactions.php">Transactions</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li><a href="staff.php">Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Transaction Details</h1>
                <div class="actions">
                    <a href="transactions.php" class="btn btn-secondary">Back to Transactions</a>
                    <?php if ($transaction && $transaction['status'] !== 'Returned'): ?>
                        <a href="transactions_return.php?id=<?php echo $loanId; ?>" class="btn btn-primary">Return Book</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($transaction): ?>
                <div class="content-wrapper">
                    <div class="book-details">
                        <h2>Transaction #<?php echo htmlspecialchars($transaction['loan_id']); ?></h2>
                            <div class="status-badge <?php echo strtolower($transaction['status']); ?>">
                                <?php echo htmlspecialchars($transaction['status']); ?>
                            </div>
                            
                            <div class="detail-section">
                            <div class="detail-item">
                                <div class="detail-label">Book:</div>
                                    <div class="detail-value">
                                        <a href="books_view.php?id=<?php echo $transaction['book_id']; ?>">
                                            <?php echo htmlspecialchars($transaction['book_title']); ?>
                                        </a>
                                    </div>
                                </div>
                            
                            <div class="detail-item">
                                    <div class="detail-label">Author:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($transaction['book_author']); ?></div>
                                </div>
                            
                            <div class="detail-item">
                                    <div class="detail-label">ISBN:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($transaction['book_isbn']); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Member:</div>
                                    <div class="detail-value">
                                        <a href="members_view.php?id=<?php echo $transaction['member_id']; ?>">
                                            <?php echo htmlspecialchars($transaction['member_name']); ?>
                                        </a>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                    <div class="detail-label">Loan Date:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($transaction['loan_date']); ?></div>
                                </div>
                            
                            <div class="detail-item">
                                    <div class="detail-label">Due Date:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($transaction['due_date']); ?></div>
                                </div>
                            
                            <div class="detail-item">
                                    <div class="detail-label">Return Date:</div>
                                    <div class="detail-value">
                                        <?php echo $transaction['return_date'] ? htmlspecialchars($transaction['return_date']) : 'Not returned yet'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($fine): ?>
                            <div class="detail-section">
                                    <h3>Fine Information</h3>
                                <div class="detail-item">
                                        <div class="detail-label">Amount:</div>
                                        <div class="detail-value">$<?php echo htmlspecialchars(number_format($fine['amount'], 2)); ?></div>
                                    </div>
                                
                                <div class="detail-item">
                                        <div class="detail-label">Issue Date:</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($fine['issue_date']); ?></div>
                                    </div>
                                
                                <div class="detail-item">
                                        <div class="detail-label">Payment Status:</div>
                                        <div class="detail-value">
                                            <span class="status-badge <?php echo strtolower($fine['payment_status']); ?>">
                                                <?php echo htmlspecialchars($fine['payment_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($fine['payment_status'] === 'Unpaid'): ?>
                                    <form method="POST" action="" class="mt-4">
                                                <input type="hidden" name="action" value="pay_fine">
                                        <button type="submit" class="btn btn-primary">Mark as Paid</button>
                                            </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 
