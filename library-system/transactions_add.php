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

// Get pre-selected book or member from query parameters
$preSelectedBookId = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
$preSelectedMemberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

// Define variables and set to empty values
$bookId = $preSelectedBookId;
$memberId = $preSelectedMemberId;
$loanDate = date('Y-m-d');
$dueDate = date('Y-m-d', strtotime('+14 days')); // Default due date is 2 weeks from today

// Get available books and members
$books = [];
$members = [];
$selectedBook = null;
$selectedMember = null;

$conn = getDbConnection();
if ($conn) {
    try {
        // Get available books
        $result = $conn->query("
            SELECT book_id, title, author, isbn, available_copies 
            FROM books 
            WHERE available_copies > 0
            ORDER BY title
        ");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $books[] = $row;
            }
        }
        
        // Get active members
        $result = $conn->query("
            SELECT member_id, first_name, last_name, email, membership_status 
            FROM members 
            WHERE membership_status = 'active'
            ORDER BY last_name, first_name
        ");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
            }
        }
        
        // If pre-selected book, get its details
        if ($preSelectedBookId > 0) {
            $stmt = $conn->prepare("
                SELECT book_id, title, author, isbn, available_copies 
                FROM books 
                WHERE book_id = ? AND available_copies > 0
            ");
            $stmt->bind_param("i", $preSelectedBookId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $selectedBook = $result->fetch_assoc();
            } else {
                $error = "Selected book is not available for loan";
                $bookId = 0;
            }
            
            $stmt->close();
        }
        
        // If pre-selected member, get details
        if ($preSelectedMemberId > 0) {
            $stmt = $conn->prepare("
                SELECT member_id, first_name, last_name, email, membership_status 
                FROM members 
                WHERE member_id = ? AND membership_status = 'active'
            ");
            $stmt->bind_param("i", $preSelectedMemberId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $selectedMember = $result->fetch_assoc();
            } else {
                $error = "Selected member is not active";
                $memberId = 0;
            }
            
            $stmt->close();
        }
        
        $conn->close();
    } catch (Exception $e) {
        $error = "Error loading data. Please try again.";
        $logger->error("Error loading data for transaction: " . $e->getMessage(), $e);
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize inputs
    $bookId = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
    $memberId = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
    $loanDate = sanitizeInput($_POST['loan_date'] ?? '');
    $dueDate = sanitizeInput($_POST['due_date'] ?? '');
    
    // Validate inputs
    if ($bookId <= 0) {
        $error = "Please select a book";
    } elseif ($memberId <= 0) {
        $error = "Please select a member";
    } elseif (empty($loanDate)) {
        $error = "Loan date is required";
    } elseif (empty($dueDate)) {
        $error = "Due date is required";
    } elseif (strtotime($dueDate) < strtotime($loanDate)) {
        $error = "Due date must be after loan date";
    } else {
        $conn = getDbConnection();
        
        if ($conn) {
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Check if book is available
                $stmt = $conn->prepare("
                    SELECT available_copies 
                    FROM books 
                    WHERE book_id = ? FOR UPDATE
                ");
                $stmt->bind_param("i", $bookId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    throw new Exception("Book not found");
                }
                
                $book = $result->fetch_assoc();
                if ($book['available_copies'] <= 0) {
                    throw new Exception("No available copies of this book");
                }
                
                $stmt->close();
                
                // Check if member is active
                $stmt = $conn->prepare("
                    SELECT membership_status 
                    FROM members 
                    WHERE member_id = ?
                ");
                $stmt->bind_param("i", $memberId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    throw new Exception("Member not found");
                }
                
                $member = $result->fetch_assoc();
                if ($member['membership_status'] !== 'active') {
                    throw new Exception("Member is inactive and cannot borrow books");
                }
                
                $stmt->close();
                
                // Create transaction
                $stmt = $conn->prepare("
                    INSERT INTO transactions (book_id, member_id, issue_date, due_date, status)
                    VALUES (?, ?, ?, ?, 'Borrowed')
                ");
                $stmt->bind_param("iiss", $bookId, $memberId, $loanDate, $dueDate);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error creating transaction: " . $conn->error);
                }
                
                $loanId = $conn->insert_id;
                $stmt->close();
                
                // Update book available copies
                $stmt = $conn->prepare("
                    UPDATE books 
                    SET available_copies = available_copies - 1
                    WHERE book_id = ?
                ");
                $stmt->bind_param("i", $bookId);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error updating book inventory: " . $conn->error);
                }
                
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $success = "Book issued successfully. Loan ID: $loanId";
                $logger->info("Book (ID: $bookId) issued to Member (ID: $memberId) by Staff ID {$_SESSION['staff_id']}. Loan ID: $loanId");
                
                // Reset form for a new transaction
                $bookId = 0;
                $memberId = 0;
                $loanDate = date('Y-m-d');
                $dueDate = date('Y-m-d', strtotime('+14 days'));
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = $e->getMessage();
                $logger->error("Transaction add error: " . $e->getMessage(), $e);
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
    <title>Issue Book - Library Management System</title>
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
                <h1>Issue Book</h1>
                <div class="actions">
                    <a href="transactions.php" class="btn btn-secondary">Back to Transactions</a>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <form method="POST" action="" class="form-container">
                    <div class="form-group">
                        <label for="book_id">Book <span class="required">*</span></label>
                        <select id="book_id" name="book_id" required>
                            <option value="">-- Select Book --</option>
                            <?php foreach ($books as $book): ?>
                                <option value="<?php echo $book['book_id']; ?>" <?php echo $book['book_id'] == $bookId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($book['title']); ?> by <?php echo htmlspecialchars($book['author']); ?> 
                                    (<?php echo htmlspecialchars($book['available_copies']); ?> available)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($books)): ?>
                            <div class="form-text text-warning">No books available for loan</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="member_id">Member <span class="required">*</span></label>
                        <select id="member_id" name="member_id" required>
                            <option value="">-- Select Member --</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>" <?php echo $member['member_id'] == $memberId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['first_name']) . ' ' . htmlspecialchars($member['last_name']); ?> 
                                    <?php if (!empty($member['email'])): ?>
                                        (<?php echo htmlspecialchars($member['email']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($members)): ?>
                            <div class="form-text text-warning">No active members found</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="loan_date">Loan Date <span class="required">*</span></label>
                        <input type="date" id="loan_date" name="loan_date" value="<?php echo htmlspecialchars($loanDate); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date <span class="required">*</span></label>
                        <input type="date" id="due_date" name="due_date" value="<?php echo htmlspecialchars($dueDate); ?>" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" <?php echo (empty($books) || empty($members)) ? 'disabled' : ''; ?>>Issue Book</button>
                        <a href="transactions.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 
