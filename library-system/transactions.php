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
$transactions = [];
$error = '';
$success = '';

// Get filter status from query parameter
$statusFilter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';

// Check for flash messages
if (isset($_SESSION['flash_message'])) {
    $success = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Mark overdue books
$conn = getDbConnection();
if ($conn) {
    try {
        // Update status of overdue books
        $conn->query("
            UPDATE transactions 
            SET status = 'overdue' 
            WHERE due_date < CURDATE() AND status = 'borrowed'
        ");
        
        // Fetch transactions
        $query = "
            SELECT t.transaction_id, t.issue_date, t.due_date, t.return_date, t.status,
                   b.book_id, b.title, b.author,
                   m.member_id, CONCAT(m.first_name, ' ', m.last_name) as member_name
            FROM transactions t
            JOIN books b ON t.book_id = b.book_id
            JOIN members m ON t.member_id = m.member_id
        ";
        
        // Add status filter if not 'all'
        if ($statusFilter !== 'all') {
            $query .= " WHERE t.status = '" . $conn->real_escape_string($statusFilter) . "'";
        }
        
        $query .= " ORDER BY t.issue_date DESC";
        
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $transactions[] = $row;
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        $error = "Error fetching transactions. Please try again.";
        $logger->error("Error fetching transactions: " . $e->getMessage(), $e);
    }
}

// Process return action
if (isset($_GET['action']) && $_GET['action'] === 'return' && isset($_GET['id'])) {
    $transactionId = (int)$_GET['id'];
    $conn = getDbConnection();
    
    if ($conn) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Get book_id from transaction
            $stmt = $conn->prepare("
                SELECT book_id FROM transactions 
                WHERE transaction_id = ? AND status != 'returned'
            ");
            $stmt->bind_param("i", $transactionId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = "Transaction not found or already returned.";
            } else {
                $row = $result->fetch_assoc();
                $bookId = $row['book_id'];
                
                // Update transaction
                $stmt = $conn->prepare("
                    UPDATE transactions 
                    SET status = 'returned', return_date = CURDATE()
                    WHERE transaction_id = ?
                ");
                $stmt->bind_param("i", $transactionId);
                
                if ($stmt->execute()) {
                    // Update book available copies
                    $stmt = $conn->prepare("
                        UPDATE books 
                        SET available_copies = available_copies + 1
                        WHERE book_id = ?
                    ");
                    $stmt->bind_param("i", $bookId);
                    
                    if ($stmt->execute()) {
                        $conn->commit();
                        $success = "Book returned successfully.";
                        $logger->info("Book returned for transaction ID $transactionId by Staff ID {$_SESSION['staff_id']}");
                    } else {
                        $conn->rollback();
                        $error = "Error updating book inventory: " . $conn->error;
                        $logger->error("Error updating book inventory for transaction ID $transactionId: " . $conn->error);
                    }
                } else {
                    $conn->rollback();
                    $error = "Error updating transaction: " . $conn->error;
                    $logger->error("Error updating transaction ID $transactionId: " . $conn->error);
                }
            }
            
            $stmt->close();
        } catch (Exception $e) {
            if ($conn) {
                $conn->rollback();
            }
            $error = "An error occurred. Please try again.";
            $logger->error("Transaction return error: " . $e->getMessage(), $e);
        }
        
        $conn->close();
    } else {
        $error = "Database connection error. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/transactions.css">
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
                <h1>Transactions</h1>
                <div class="actions">
                    <a href="transactions_add.php" class="btn btn-primary">Issue Book</a>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <div class="filters">
                    <div class="filter-label">Filter by Status:</div>
                    <div class="filter-options">
                        <a href="transactions.php?filter=all" class="filter-option <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
                        <a href="transactions.php?filter=borrowed" class="filter-option <?php echo $statusFilter === 'borrowed' ? 'active' : ''; ?>">Borrowed</a>
                        <a href="transactions.php?filter=returned" class="filter-option <?php echo $statusFilter === 'returned' ? 'active' : ''; ?>">Returned</a>
                        <a href="transactions.php?filter=overdue" class="filter-option <?php echo $statusFilter === 'overdue' ? 'active' : ''; ?>">Overdue</a>
                    </div>
                </div>
                
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search transactions...">
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Book</th>
                                <th>Member</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No transactions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
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
                                        <td><?php echo htmlspecialchars($transaction['issue_date']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['due_date']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['return_date'] ?? 'Not returned'); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($transaction['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($transaction['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <?php if ($transaction['status'] === 'borrowed' || $transaction['status'] === 'overdue'): ?>
                                                <a href="#" class="btn btn-icon btn-return" title="Return Book" 
                                                   onclick="confirmReturn(<?php echo $transaction['transaction_id']; ?>, '<?php echo htmlspecialchars(addslashes($transaction['title'])); ?>')">
                                                    Return
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        .btn-return {
            background-color: transparent;
            color: #22c55e;
            padding: 6px 12px;
            border: 1px solid #22c55e;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-return:hover {
            background-color: #22c55e;
            color: #ffffff;
        }

        td.actions {
            white-space: nowrap;
        }
    </style>

    <script>
        // Simple client-side search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
        
        // Confirm return
        function confirmReturn(id, title) {
            if (confirm(`Are you sure you want to return the book "${title}"?`)) {
                window.location.href = `transactions.php?action=return&id=${id}`;
            }
        }
    </script>
</body>
</html> 