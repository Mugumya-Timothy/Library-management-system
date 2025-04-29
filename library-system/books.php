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
$books = [];
$error = '';
$success = '';

// Check for flash messages
if (isset($_SESSION['flash_message'])) {
    $success = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Handle delete request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $bookId = (int)$_GET['id'];
    $conn = getDbConnection();
    
    if ($conn) {
        try {
            // Check if book has active loans
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count FROM transactions 
                WHERE book_id = ? AND status != 'Returned'
            ");
            $stmt->bind_param("i", $bookId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $error = "Cannot delete book with active loans. Please return all copies first.";
            } else {
                // Delete book
                $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
                $stmt->bind_param("i", $bookId);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success = "Book deleted successfully.";
                        $logger->info("Book ID $bookId deleted by Staff ID {$_SESSION['staff_id']}");
                    } else {
                        $error = "Book not found.";
                    }
                } else {
                    $error = "Error deleting book: " . $conn->error;
                    $logger->error("Error deleting book ID $bookId: " . $conn->error);
                }
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $error = "An error occurred. Please try again.";
            $logger->error("Book deletion error: " . $e->getMessage(), $e);
        }
        
        $conn->close();
    } else {
        $error = "Database connection error. Please try again later.";
    }
}

// Fetch books
$conn = getDbConnection();
if ($conn) {
    try {
        $result = $conn->query("
            SELECT book_id, title, author, isbn, genre, added_date, shelf_location, 
                   status, total_copies, available_copies 
            FROM books 
            ORDER BY title
        ");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $books[] = $row;
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        $error = "Error fetching books. Please try again.";
        $logger->error("Error fetching books: " . $e->getMessage(), $e);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books - Library Management System</title>
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
                <h1>Books</h1>
                <div class="actions">
                    <a href="books_add.php" class="btn btn-primary">Add New Book</a>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search books...">
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Genre</th>
                                <th>Added Date</th>
                                <th>Shelf Location</th>
                                <th>Status</th>
                                <th>Copies</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($books)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No books found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($books as $book): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($book['book_id']); ?></td>
                                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                                        <td><?php echo htmlspecialchars($book['author']); ?></td>
                                        <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                        <td><?php echo htmlspecialchars($book['genre'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($book['added_date']))); ?></td>
                                        <td><?php echo htmlspecialchars($book['shelf_location'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($book['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($book['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($book['available_copies']); ?> / 
                                            <?php echo htmlspecialchars($book['total_copies']); ?>
                                        </td>
                                        <td class="actions">
                                            <a href="books_edit.php?id=<?php echo $book['book_id']; ?>" class="btn btn-icon btn-edit" title="Edit">Edit</a>
                                            <a href="#" class="btn btn-icon btn-delete" title="Delete" 
                                               onclick="confirmDelete(<?php echo $book['book_id']; ?>, '<?php echo htmlspecialchars(addslashes($book['title'])); ?>')">
                                                Delete
                                            </a>
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
        
        // Confirm delete
        function confirmDelete(id, title) {
            if (confirm(`Are you sure you want to delete the book "${title}"?`)) {
                window.location.href = `books.php?action=delete&id=${id}`;
            }
        }
    </script>
</body>
</html> 