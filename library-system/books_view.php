<?php
require_once 'config.php';
require_once 'Logger.php';
require_once 'utils.php';

// Start session and require login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['staff_id'])) {
    header('Location: index.php');
    exit;
}

$logger = Logger::getInstance();
$error = '';
$book = null;

// Check if id parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: books.php');
    exit;
}

$bookId = (int)$_GET['id'];

// Fetch specific book by ID
$conn = getDbConnection();
if ($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT book_id, title, author, isbn, genre, added_date, shelf_location,
                   status, total_copies, available_copies
            FROM books
            WHERE book_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $bookId);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $book = $result->fetch_assoc();
        } else {
            $error = "Book not found.";
            $logger->error("Book ID $bookId not found");
        }
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $error = 'Error fetching book details. Please try again later.';
        $logger->error('Error fetching book: ' . $e->getMessage(), $e);
    }
} else {
    $error = 'Database connection error. Please try again later.';
}

// Fetch loan history for this book
$loanHistory = [];
$conn = getDbConnection();
if ($conn && $book) {
    try {
        $stmt = $conn->prepare("
            SELECT t.transaction_id, t.issue_date, t.return_date, t.status,
                   m.member_id, m.first_name, m.last_name
            FROM transactions t
            JOIN members m ON t.member_id = m.member_id
            WHERE t.book_id = ?
            ORDER BY t.issue_date DESC
            LIMIT 10
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $bookId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $loanHistory[] = $row;
            }
            
            $stmt->close();
        }
        
        $conn->close();
    } catch (Exception $e) {
        $logger->error('Error fetching loan history: ' . $e->getMessage(), $e);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Details - Library Management System</title>
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
                <li class="active"><a href="books.php">Books</a></li>
                <li><a href="members.php">Members</a></li>
                <li><a href="transactions.php">Transactions</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li><a href="staff.php">Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Book Details</h1>
                <div class="actions">
                    <a href="books.php" class="btn btn-secondary">Back to Books</a>
                    <?php if ($book): ?>
                        <a href="books_edit.php?id=<?php echo $bookId; ?>" class="btn btn-primary">Edit Book</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($book): ?>
                <div class="content-wrapper">
                    <div class="book-details">
                        <div class="book-info">
                            <h2><?php echo htmlspecialchars($book['title']); ?></h2>
                            <p class="author">By <?php echo htmlspecialchars($book['author']); ?></p>
                            
                            <div class="book-meta">
                                <div class="meta-item">
                                    <span class="label">ISBN:</span>
                                    <span class="value"><?php echo htmlspecialchars($book['isbn']); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <span class="label">Genre:</span>
                                    <span class="value"><?php echo htmlspecialchars($book['genre'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <span class="label">Added Date:</span>
                                    <span class="value"><?php echo htmlspecialchars(date('F j, Y', strtotime($book['added_date']))); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <span class="label">Shelf Location:</span>
                                    <span class="value"><?php echo htmlspecialchars($book['shelf_location'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <span class="label">Status:</span>
                                    <span class="value">
                                        <?php echo htmlspecialchars($book['status']); ?>
                                        
                                        <?php if ($book['status'] == 'borrowed'): ?>
                                            <span class="status-badge unavailable">Borrowed</span>
                                        <?php elseif ($book['status'] == 'reserved'): ?>
                                            <span class="status-badge limited">Reserved</span>
                                        <?php elseif ($book['status'] == 'maintenance'): ?>
                                            <span class="status-badge unavailable">Maintenance</span>
                                        <?php else: ?>
                                            <span class="status-badge available">Available</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="meta-item">
                                    <span class="label">Copies:</span>
                                    <span class="value">
                                        <?php echo htmlspecialchars($book['available_copies']); ?> / 
                                        <?php echo htmlspecialchars($book['total_copies']); ?> available
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($loanHistory)): ?>
                        <h3 class="section-title">Loan History</h3>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Member</th>
                                        <th>Issue Date</th>
                                        <th>Return Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loanHistory as $loan): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($loan['transaction_id']); ?></td>
                                            <td>
                                                <a href="members_view.php?id=<?php echo $loan['member_id']; ?>">
                                                    <?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($loan['issue_date']); ?></td>
                                            <td><?php echo $loan['return_date'] ? htmlspecialchars($loan['return_date']) : 'Not returned'; ?></td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($loan['status']); ?>"><?php echo htmlspecialchars($loan['status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
