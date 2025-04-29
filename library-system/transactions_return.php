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

// Check if id parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: transactions.php');
    exit;
}

$loanId = (int)$_GET['id'];
$transaction = null;
$book = null;
$member = null;
$fine = null;

// Fetch transaction details
$conn = getDbConnection();
if ($conn) {
    try {
        // Get transaction data
        $stmt = $conn->prepare("
            SELECT t.loan_id, t.book_id, t.member_id, t.loan_date, t.due_date, t.return_date, t.status,
                   b.title as book_title, b.author as book_author,
                   m.name as member_name
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
            
            // Check if book is already returned
            if ($transaction['status'] === 'Returned') {
                redirectWithMessage('transactions_view.php?id=' . $loanId, "This book has already been returned.", 'info');
                exit;
            }
            
            // Check if there's an existing fine
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
    } catch (Exception $e) {
        $error = "Error fetching transaction details. Please try again.";
        $logger->error("Error fetching transaction ID $loanId: " . $e->getMessage(), $e);
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $transaction) {
    // Get and sanitize inputs
    $returnDate = sanitizeInput($_POST['return_date'] ?? '');
    $issueFineBool = isset($_POST['issue_fine']) && $_POST['issue_fine'] === 'yes';
    $fineAmount = isset($_POST['fine_amount']) ? (float)$_POST['fine_amount'] : 0;
    
    // Validate inputs
    if (empty($returnDate)) {
        $error = "Return date is required";
    } else {
        $conn = getDbConnection();
        
        if ($conn) {
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Update transaction
                $stmt = $conn->prepare("
                    UPDATE transactions 
                    SET return_date = ?, status = 'Returned'
                    WHERE loan_id = ?
                ");
                $stmt->bind_param("si", $returnDate, $loanId);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error updating transaction: " . $conn->error);
                }
                
                $stmt->close();
                
                // Update book available copies
                $stmt = $conn->prepare("
                    UPDATE books 
                    SET available_copies = available_copies + 1
                    WHERE book_id = ?
                ");
                $stmt->bind_param("i", $transaction['book_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error updating book inventory: " . $conn->error);
                }
                
                $stmt->close();
                
                // Create fine if needed
                if ($issueFineBool && $fineAmount > 0) {
                    // Check if fine already exists
                    if ($fine) {
                        // Update existing fine
                        $stmt = $conn->prepare("
                            UPDATE fines 
                            SET amount = ?, issue_date = CURRENT_DATE
                            WHERE fine_id = ?
                        ");
                        $stmt->bind_param("di", $fineAmount, $fine['fine_id']);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error updating fine: " . $conn->error);
                        }
                        
                        $fineId = $fine['fine_id'];
                    } else {
                        // Create new fine
                        $stmt = $conn->prepare("
                            INSERT INTO fines (loan_id, amount, issue_date, payment_status)
                            VALUES (?, ?, CURRENT_DATE, 'Unpaid')
                        ");
                        $stmt->bind_param("id", $loanId, $fineAmount);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error creating fine: " . $conn->error);
                        }
                        
                        $fineId = $conn->insert_id;
                    }
                    
                    $stmt->close();
                    $fineMessage = " A fine of $" . htmlspecialchars(number_format($fineAmount, 2)) . " has been issued.";
                } else {
                    $fineMessage = "";
                }
                
                // Commit transaction
                $conn->commit();
                
                $successMessage = "Book returned successfully." . $fineMessage;
                $logger->info("Book (ID: {$transaction['book_id']}) returned by Member (ID: {$transaction['member_id']}). Loan ID: $loanId. Staff ID: {$_SESSION['staff_id']}");
                
                redirectWithMessage('transactions.php', $successMessage, 'success');
                exit;
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = $e->getMessage();
                $logger->error("Transaction return error: " . $e->getMessage(), $e);
            }
            
            $conn->close();
        } else {
            $error = "Database connection error. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book - Library Management System</title>
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
                <h1>Return Book</h1>
                <div class="actions">
                    <a href="transactions.php" class="btn btn-secondary">Back to Transactions</a>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($transaction): ?>
                <div class="content-wrapper">
                    <div class="transaction-details">
                        <div class="detail-section">
                            <h3>Book Information</h3>
                            <div class="detail-row">
                                <div class="detail-label">Title:</div>
                                <div class="detail-value">
                                    <a href="books_view.php?id=<?php echo $transaction['book_id']; ?>">
                                        <?php echo htmlspecialchars($transaction['book_title']); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Author:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($transaction['book_author']); ?></div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h3>Member Information</h3>
                            <div class="detail-row">
                                <div class="detail-label">Name:</div>
                                <div class="detail-value">
                                    <a href="members_view.php?id=<?php echo $transaction['member_id']; ?>">
                                        <?php echo htmlspecialchars($transaction['member_name']); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h3>Loan Information</h3>
                            <div class="detail-row">
                                <div class="detail-label">Loan ID:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($transaction['loan_id']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Loan Date:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($transaction['loan_date']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Due Date:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($transaction['due_date']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Status:</div>
                                <div class="detail-value">
                                    <span class="status-badge <?php echo strtolower($transaction['status']); ?>">
                                        <?php echo htmlspecialchars($transaction['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="" class="form-container">
                        <div class="form-group">
                            <label for="return_date">Return Date <span class="required">*</span></label>
                            <input type="date" id="return_date" name="return_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <?php
                        // Check if book is overdue
                        $isOverdue = strtotime($transaction['due_date']) < time();
                        ?>
                        
                        <div class="form-group">
                            <label>Issue Fine</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="issue_fine" value="yes" <?php echo ($isOverdue || $fine) ? 'checked' : ''; ?> onchange="toggleFineAmount()">
                                    Yes
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="issue_fine" value="no" <?php echo (!$isOverdue && !$fine) ? 'checked' : ''; ?> onchange="toggleFineAmount()">
                                    No
                                </label>
                            </div>
                        </div>
                        
                        <div id="fineAmountGroup" class="form-group" <?php echo (!$isOverdue && !$fine) ? 'style="display:none"' : ''; ?>>
                            <label for="fine_amount">Fine Amount ($)</label>
                            <input type="number" id="fine_amount" name="fine_amount" min="0" step="0.01" value="<?php echo $fine ? $fine['amount'] : ($isOverdue ? '5.00' : '0.00'); ?>">
                            <?php if ($isOverdue): ?>
                                <div class="form-text text-warning">
                                    This book is overdue by <?php echo floor((time() - strtotime($transaction['due_date'])) / 86400); ?> days.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Return Book</button>
                            <a href="transactions.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleFineAmount() {
            const issueFine = document.querySelector('input[name="issue_fine"]:checked').value === 'yes';
            const fineAmountGroup = document.getElementById('fineAmountGroup');
            
            fineAmountGroup.style.display = issueFine ? 'block' : 'none';
            
            if (!issueFine) {
                document.getElementById('fine_amount').value = '0.00';
            }
        }
    </script>
</body>
</html> 
