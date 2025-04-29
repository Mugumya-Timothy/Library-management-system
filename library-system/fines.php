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
$fines = [];
$error = '';
$success = '';

// Get filter status from query parameter
$statusFilter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';

// Check for flash messages
if (isset($_SESSION['flash_message'])) {
    $success = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Process adding a new fine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id']) && isset($_POST['amount'])) {
    $transactionId = (int)$_POST['transaction_id'];
    $amount = (float)$_POST['amount'];
    $paymentStatus = sanitizeInput($_POST['payment_status']);
    $paymentDate = null;
    
    // Set payment date if status is paid
    if ($paymentStatus === 'paid' && !empty($_POST['payment_date'])) {
        $paymentDate = $_POST['payment_date'];
    }
    
    $conn = getDbConnection();
    
    if ($conn) {
        try {
            // Verify transaction exists
            $stmt = $conn->prepare("
                SELECT transaction_id, member_id 
                FROM transactions 
                WHERE transaction_id = ?
            ");
            $stmt->bind_param("i", $transactionId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = "Transaction ID not found. Please verify and try again.";
            } else {
                // Insert fine record - using exact database structure fields
                $stmt = $conn->prepare("
                    INSERT INTO fines (transaction_id, amount, payment_status, payment_date, staff_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $staffId = $_SESSION['staff_id'];
                $stmt->bind_param("idssi", $transactionId, $amount, $paymentStatus, $paymentDate, $staffId);
            
                if ($stmt->execute()) {
                    $fineId = $stmt->insert_id;
                    $success = "Fine has been added successfully.";
                    $logger->info("Fine ID $fineId created by Staff ID {$_SESSION['staff_id']} for Transaction ID $transactionId");
                } else {
                    $error = "Error adding fine: " . $conn->error;
                    $logger->error("Error adding fine for Transaction ID $transactionId: " . $conn->error);
                }
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $error = "An error occurred. Please try again.";
            $logger->error("Fine creation error: " . $e->getMessage(), $e);
        }
        
        $conn->close();
    } else {
        $error = "Database connection error. Please try again later.";
    }
}

// Process fine payment status change
if (isset($_GET['action']) && isset($_GET['id'])) {
    $fineId = (int)$_GET['id'];
    $newStatus = '';
    
    if ($_GET['action'] === 'pay') {
        $newStatus = 'paid';
    } elseif ($_GET['action'] === 'unpay') {
        $newStatus = 'unpaid';
    }
    
    if (!empty($newStatus)) {
        $conn = getDbConnection();
        
        if ($conn) {
            try {
                $stmt = $conn->prepare("
                    UPDATE fines
                    SET payment_status = ?
                    WHERE fine_id = ?
                ");
                $stmt->bind_param("si", $newStatus, $fineId);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success = "Fine marked as " . ucfirst($newStatus) . " successfully.";
                        $logger->info("Fine ID $fineId marked as $newStatus by Staff ID {$_SESSION['staff_id']}");
                    } else {
                        $error = "Fine not found.";
                    }
                } else {
                    $error = "Error updating fine payment status: " . $conn->error;
                    $logger->error("Error updating fine payment status for Fine ID $fineId: " . $conn->error);
                }
                
                $stmt->close();
            } catch (Exception $e) {
                $error = "An error occurred. Please try again.";
                $logger->error("Fine payment status change error: " . $e->getMessage(), $e);
            }
            
            $conn->close();
        } else {
            $error = "Database connection error. Please try again later.";
        }
    }
}

// Fetch fines
$conn = getDbConnection();
if ($conn) {
    try {
        $query = "
            SELECT f.fine_id, f.transaction_id, f.amount, f.payment_date, f.payment_status,
                   t.issue_date, t.due_date, t.return_date, t.status as transaction_status,
                   b.book_id, b.title as book_title,
                   m.member_id, CONCAT(m.first_name, ' ', m.last_name) as member_name
            FROM fines f
            JOIN transactions t ON f.transaction_id = t.transaction_id
            JOIN books b ON t.book_id = b.book_id
            JOIN members m ON t.member_id = m.member_id
        ";
        
        // Add status filter if not 'all'
        if ($statusFilter !== 'all') {
            $query .= " WHERE f.payment_status = '" . $conn->real_escape_string($statusFilter) . "'";
        }
        
        $query .= " ORDER BY f.payment_date DESC, f.fine_id DESC";
        
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $fines[] = $row;
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        $error = "Error fetching fines. Please try again.";
        $logger->error("Error fetching fines: " . $e->getMessage(), $e);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fines - Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/fines.css">
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
                <li class="active"><a href="fines.php">Fines</a></li>
                <li><a href="staff.php">Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <div class="header-left">
                    <h1>Fines</h1>
                </div>
                <div class="actions">
                    <a href="#" class="btn btn-primary add-btn" onclick="openAddFineModal()">Add Fine</a>
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
                        <a href="fines.php?filter=all" class="filter-option <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
                        <a href="fines.php?filter=paid" class="filter-option <?php echo $statusFilter === 'paid' ? 'active' : ''; ?>">Paid</a>
                        <a href="fines.php?filter=unpaid" class="filter-option <?php echo $statusFilter === 'unpaid' ? 'active' : ''; ?>">Unpaid</a>
                        <a href="fines.php?filter=waived" class="filter-option <?php echo $statusFilter === 'waived' ? 'active' : ''; ?>">Waived</a>
                    </div>
                </div>
                
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search fines...">
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fine ID</th>
                                <th>Member</th>
                                <th>Book</th>
                                <th>Amount</th>
                                <th>Issue Date</th>
                                <th>Loan Status</th>
                                <th>Payment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fines)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No fines found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($fines as $fine): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fine['fine_id']); ?></td>
                                        <td>
                                            <a href="members_view.php?id=<?php echo $fine['member_id']; ?>">
                                                <?php echo htmlspecialchars($fine['member_name']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="books_view.php?id=<?php echo $fine['book_id']; ?>">
                                                <?php echo htmlspecialchars($fine['book_title']); ?>
                                            </a>
                                        </td>
                                        <td>UGX<?php echo htmlspecialchars(number_format($fine['amount'], 2)); ?></td>
                                        <td><?php echo htmlspecialchars($fine['payment_date'] ?? 'Not paid'); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($fine['transaction_status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($fine['transaction_status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($fine['payment_status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($fine['payment_status'])); ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <div class="action-buttons">
                                                <a href="fines.php?action=pay&id=<?php echo $fine['fine_id']; ?>" class="btn btn-paid" title="Mark as Paid">Paid</a>
                                                <a href="fines.php?action=unpay&id=<?php echo $fine['fine_id']; ?>" class="btn btn-unpaid" title="Mark as Unpaid">Unpaid</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="summary-section">
                    <div class="summary-card">
                        <h3>Summary</h3>
                        <?php
                            $totalFines = 0;
                            $totalPaid = 0;
                            $totalUnpaid = 0;
                            
                            foreach ($fines as $fine) {
                                $totalFines += $fine['amount'];
                                if ($fine['payment_status'] === 'paid') {
                                    $totalPaid += $fine['amount'];
                                } else {
                                    $totalUnpaid += $fine['amount'];
                                }
                            }
                        ?>
                        <div class="summary-row">
                            <div class="summary-label">Total Fines:</div>
                            <div class="summary-value">UGX<?php echo htmlspecialchars(number_format($totalFines, 2)); ?></div>
                        </div>
                        <div class="summary-row">
                            <div class="summary-label">Total Paid:</div>
                            <div class="summary-value">UGX<?php echo htmlspecialchars(number_format($totalPaid, 2)); ?></div>
                        </div>
                        <div class="summary-row">
                            <div class="summary-label">Total Unpaid:</div>
                            <div class="summary-value">UGX<?php echo htmlspecialchars(number_format($totalUnpaid, 2)); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Fine Modal -->
    <div id="addFineModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddFineModal()">&times;</span>
            <h2>Add New Fine</h2>
            <form action="fines.php" method="post" id="addFineForm">
                <div class="form-group">
                    <label for="transaction_id">Transaction ID:</label>
                    <input type="number" id="transaction_id" name="transaction_id" required>
                </div>
                
                <div class="form-group">
                    <label for="amount">Fine Amount (UGX):</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="payment_status">Payment Status:</label>
                    <select id="payment_status" name="payment_status" required>
                        <option value="unpaid">Unpaid</option>
                        <option value="paid">Paid</option>
                        <option value="waived">Waived</option>
                    </select>
                </div>
                
                <div class="form-group payment-date-group" style="display: none;">
                    <label for="payment_date">Payment Date:</label>
                    <input type="datetime-local" id="payment_date" name="payment_date">
                </div>
                
                <!-- Hidden field for staff_id -->
                <input type="hidden" name="staff_id" value="<?php echo $_SESSION['staff_id']; ?>">
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Fine</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddFineModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .add-btn {
            padding: 8px 15px;
            font-weight: 500;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: #3b82f6;
            color: white;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .add-btn:hover {
            background-color: #2563eb;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .btn-paid, .btn-unpaid {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid;
        }
        
        .btn-paid {
            background-color: #e6f7ef;
            color: #047857;
            border-color: #10b981;
        }
        
        .btn-paid:hover {
            background-color: #d1fae5;
        }
        
        .btn-unpaid {
            background-color: #fef2f2;
            color: #b91c1c;
            border-color: #ef4444;
        }
        
        .btn-unpaid:hover {
            background-color: #fee2e2;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 25px;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
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
        
        // Get the modal
        var addFineModal = document.getElementById("addFineModal");

        // Function to open the modal
        function openAddFineModal() {
            addFineModal.style.display = "block";
        }

        // When the user clicks on <span> (x), close the modal
        function closeAddFineModal() {
            addFineModal.style.display = "none";
            document.getElementById("addFineForm").reset();
        }

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == addFineModal) {
                closeAddFineModal();
            }
        }

        // Show/hide payment date field based on payment status
        document.getElementById("payment_status").addEventListener("change", function() {
            var paymentDateGroup = document.querySelector(".payment-date-group");
            if (this.value === "paid") {
                paymentDateGroup.style.display = "block";
                // Set default date to current date and time
                var now = new Date();
                var year = now.getFullYear();
                var month = (now.getMonth() + 1).toString().padStart(2, '0');
                var day = now.getDate().toString().padStart(2, '0');
                var hours = now.getHours().toString().padStart(2, '0');
                var minutes = now.getMinutes().toString().padStart(2, '0');
                document.getElementById("payment_date").value = `${year}-${month}-${day}T${hours}:${minutes}`;
            } else {
                paymentDateGroup.style.display = "none";
                document.getElementById("payment_date").value = "";
            }
        });
    </script>
</body>
</html> 