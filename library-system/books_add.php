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

// Define variables and set to empty values
$title = '';
$author = '';
$isbn = '';
$genre = '';
$shelfLocation = '';
$status = 'available';
$totalCopies = 1;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize inputs
    $title = sanitizeInput($_POST['title'] ?? '');
    $author = sanitizeInput($_POST['author'] ?? '');
    $isbn = sanitizeInput($_POST['isbn'] ?? '');
    $genre = sanitizeInput($_POST['genre'] ?? '');
    $shelfLocation = sanitizeInput($_POST['shelf_location'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'available');
    $totalCopies = !empty($_POST['total_copies']) ? (int)$_POST['total_copies'] : 1;
    
    // Basic validation
    if (empty($title)) {
        $error = "Title is required";
    } elseif (empty($author)) {
        $error = "Author is required";
    } elseif (empty($isbn)) {
        $error = "ISBN is required";
    } elseif ($totalCopies < 1) {
        $error = "Total copies must be at least 1";
    } else {
        $conn = getDbConnection();
        
        if ($conn) {
            try {
                // Check if ISBN already exists
                $stmt = $conn->prepare("SELECT book_id FROM books WHERE isbn = ?");
                $stmt->bind_param("s", $isbn);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "ISBN already exists";
                    $stmt->close();
                } else {
                    $stmt->close();
                    
                    // Insert new book
                    $stmt = $conn->prepare("
                        INSERT INTO books (title, author, isbn, genre, shelf_location, status, 
                                           total_copies, available_copies)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("ssssssii", $title, $author, $isbn, $genre, $shelfLocation, 
                                      $status, $totalCopies, $totalCopies);
                    
                    if ($stmt->execute()) {
                        $bookId = $conn->insert_id;
                        $success = "Book added successfully";
                        $logger->info("New book (ID: $bookId, Title: $title) added by Staff ID {$_SESSION['staff_id']}");
                        
                        // Reset form
                        $title = '';
                        $author = '';
                        $isbn = '';
                        $genre = '';
                        $shelfLocation = '';
                        $status = 'available';
                        $totalCopies = 1;
                    } else {
                        $error = "Error adding book: " . $conn->error;
                        $logger->error("Error adding book: " . $conn->error);
                    }
                    
                    $stmt->close();
                }
            } catch (Exception $e) {
                $error = "An error occurred. Please try again.";
                $logger->error("Book add error: " . $e->getMessage(), $e);
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
    <title>Add Book - Library Management System</title>
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
                <h1>Add Book</h1>
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
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Add Book</button>
                        <a href="books.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 
