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
    header('Location: books.php');
    exit;
}

$bookId = (int)$_GET['id'];

// Define variables and set to empty values
$title = '';
$author = '';
$isbn = '';
$genre = '';
$shelfLocation = '';
$status = '';
$totalCopies = 0;
$availableCopies = 0;

// Fetch book details
$conn = getDbConnection();
if ($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT title, author, isbn, genre, shelf_location, status, total_copies, available_copies 
            FROM books 
            WHERE book_id = ?
        ");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Book not found";
        } else {
            $book = $result->fetch_assoc();
            $title = $book['title'];
            $author = $book['author'];
            $isbn = $book['isbn'];
            $genre = $book['genre'];
            $shelfLocation = $book['shelf_location'];
            $status = $book['status'];
            $totalCopies = $book['total_copies'];
            $availableCopies = $book['available_copies'];
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error fetching book details. Please try again.";
        $logger->error("Error fetching book ID $bookId: " . $e->getMessage(), $e);
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize inputs
    $title = sanitizeInput($_POST['title'] ?? '');
    $author = sanitizeInput($_POST['author'] ?? '');
    $isbn = sanitizeInput($_POST['isbn'] ?? '');
    $genre = sanitizeInput($_POST['genre'] ?? '');
    $shelfLocation = sanitizeInput($_POST['shelf_location'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? '');
    $newTotalCopies = !empty($_POST['total_copies']) ? (int)$_POST['total_copies'] : 1;
    
    // Basic validation
    if (empty($title)) {
        $error = "Title is required";
    } elseif (empty($author)) {
        $error = "Author is required";
    } elseif (empty($isbn)) {
        $error = "ISBN is required";
    } elseif ($newTotalCopies < 1) {
        $error = "Total copies must be at least 1";
    } else {
        $conn = getDbConnection();
        
        if ($conn) {
            try {
                // Check if ISBN already exists for another book
                $stmt = $conn->prepare("
                    SELECT book_id FROM books 
                    WHERE isbn = ? AND book_id != ?
                ");
                $stmt->bind_param("si", $isbn, $bookId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "ISBN already exists for another book";
                    $stmt->close();
                } else {
                    $stmt->close();
                    
                    // Calculate new available copies
                    $borrowedCopies = $totalCopies - $availableCopies;
                    $newAvailableCopies = max(0, $newTotalCopies - $borrowedCopies);
                    
                    // Make sure we don't set available copies higher than total copies
                    $newAvailableCopies = min($newTotalCopies, $newAvailableCopies);
                    
                    // Update book
                    $stmt = $conn->prepare("
                        UPDATE books 
                        SET title = ?, author = ?, isbn = ?, genre = ?, shelf_location = ?, status = ?, 
                            total_copies = ?, available_copies = ?
                        WHERE book_id = ?
                    ");
                    $stmt->bind_param(
                        "ssssssssi", 
                        $title, $author, $isbn, $genre, $shelfLocation, $status, 
                        $newTotalCopies, $newAvailableCopies, $bookId
                    );
                    
                    if ($stmt->execute()) {
                        $success = "Book updated successfully";
                        $logger->info("Book ID $bookId updated by Staff ID {$_SESSION['staff_id']}");
                        
                        // Update local variables to reflect the updated values
                        $totalCopies = $newTotalCopies;
                        $availableCopies = $newAvailableCopies;
                    } else {
                        $error = "Error updating book: " . $conn->error;
                        $logger->error("Error updating book ID $bookId: " . $conn->error);
                    }
                    
                    $stmt->close();
                }
            } catch (Exception $e) {
                $error = "An error occurred. Please try again.";
                $logger->error("Book update error: " . $e->getMessage(), $e);
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
    <title>Edit Book - Library Management System</title>
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
                <h1>Edit Book</h1>
                <div class="actions">
                    <a href="books.php" class="btn btn-secondary">Back to Books</a>
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
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="author">Author <span class="required">*</span></label>
                        <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($author); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="isbn">ISBN <span class="required">*</span></label>
                        <input type="text" id="isbn" name="isbn" value="<?php echo htmlspecialchars($isbn); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="genre">Genre</label>
                        <input type="text" id="genre" name="genre" value="<?php echo htmlspecialchars($genre); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="shelf_location">Shelf Location</label>
                        <input type="text" id="shelf_location" name="shelf_location" value="<?php echo htmlspecialchars($shelfLocation); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="available" <?php echo ($status === 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="borrowed" <?php echo ($status === 'borrowed') ? 'selected' : ''; ?>>Borrowed</option>
                            <option value="reserved" <?php echo ($status === 'reserved') ? 'selected' : ''; ?>>Reserved</option>
                            <option value="maintenance" <?php echo ($status === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="total_copies">Total Copies <span class="required">*</span></label>
                        <input type="number" id="total_copies" name="total_copies" min="1" value="<?php echo htmlspecialchars($totalCopies); ?>" required>
                        <small class="form-text text-muted">
                            Currently available: <?php echo htmlspecialchars($availableCopies); ?> copies out of <?php echo htmlspecialchars($totalCopies); ?> total copies.
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update Book</button>
                        <a href="books.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 
