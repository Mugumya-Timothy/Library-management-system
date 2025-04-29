<?php
require_once 'config.php';
require_once 'utils/logger.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: index.php');
    exit;
}

// Initialize logger
$logger = new Logger();

// Get action from URL
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$staffId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle different actions
switch ($action) {
    case 'view':
        handleViewStaff($staffId);
        break;
    case 'add':
        handleAddStaff();
        break;
    case 'edit':
        handleEditStaff($staffId);
        break;
    case 'delete':
        handleDeleteStaff($staffId);
        break;
    default:
        handleListStaff();
        break;
}

/**
 * Function to handle listing all staff members
 */
function handleListStaff() {
    global $logger;
    
    $error = '';
    $success = '';
    $staff = [];
    
    // Check for success messages
    if (isset($_SESSION['success_message'])) {
        $success = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }
    
    // Set up search and sorting
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'first_name';
    $dir = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'DESC' : 'ASC';
    
    // Validate sort column
    $validColumns = ['staff_id', 'first_name', 'email', 'phone', 'position', 'hire_date'];
    if (!in_array($sort, $validColumns)) {
        $sort = 'first_name';
    }
    
    try {
        // Connect to the database
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Build query with search
        $query = "SELECT s.*, CONCAT(s.first_name, ' ', s.last_name) AS name, CASE WHEN sl.username IS NOT NULL THEN 1 ELSE 0 END as has_login
                 FROM staff s
                 LEFT JOIN staff_login sl ON s.staff_id = sl.staff_id";
                 
        $params = [];
        
        if (!empty($search)) {
            $query .= " WHERE CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.email LIKE ? OR s.position LIKE ?";
            $searchParam = "%$search%";
            $params = [$searchParam, $searchParam, $searchParam];
        }
        
        $query .= " ORDER BY s.$sort $dir";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $logger->log("Error in staff list: " . $e->getMessage(), "ERROR");
    }
    
    // Helper function to create sort URLs
    function getSortUrl($column, $currentSort, $currentDir) {
        global $search;
        $dir = 'asc';
        if ($column === $currentSort && strtolower($currentDir) === 'asc') {
            $dir = 'desc';
        }
        
        $params = ['sort' => $column, 'dir' => $dir];
        if (!empty($search)) {
            $params['search'] = $search;
        }
        
        return 'staff.php?' . http_build_query($params);
    }
    
    // Helper function to display sort icons
    function getSortIcon($column, $currentSort, $currentDir) {
        if ($column !== $currentSort) {
            return '<span class="sort-icon">⇵</span>';
        }
        
        return (strtolower($currentDir) === 'asc') 
            ? '<span class="sort-icon asc">↑</span>' 
            : '<span class="sort-icon desc">↓</span>';
    }
    
    // Include the template
    include 'templates/staff_list_template.php';
}

/**
 * Function to handle viewing a staff member
 */
function handleViewStaff($staffId) {
    global $logger;
    
    $error = '';
    $staff = null;
    $transactions = [];
    
    if ($staffId <= 0) {
        header('Location: staff.php');
        exit;
    }
    
    try {
        // Connect to the database
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get staff information
        $stmt = $pdo->prepare("SELECT s.*, sl.username, CASE WHEN sl.username IS NOT NULL THEN 1 ELSE 0 END as has_login
                              FROM staff s
                              LEFT JOIN staff_login sl ON s.staff_id = sl.staff_id
                              WHERE s.staff_id = ?");
        $stmt->execute([$staffId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) {
            $error = "Staff member not found";
        } else {
            // Get recent transactions
            $stmt = $pdo->prepare("SELECT t.*, b.title as book_title, m.name as member_name
                                  FROM transactions t
                                  JOIN books b ON t.book_id = b.book_id
                                  JOIN members m ON t.member_id = m.member_id
                                  WHERE t.staff_id = ?
                                  ORDER BY t.loan_date DESC
                                  LIMIT 50");
            $stmt->execute([$staffId]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $logger->log("Error viewing staff: " . $e->getMessage(), "ERROR");
    }
    
    // Include the template
    include 'templates/staff_view_template.php';
}

/**
 * Function to handle adding a new staff member
 */
function handleAddStaff() {
    global $logger;
    
    $error = '';
    $success = '';
    
    // Initialize form values
    $name = '';
    $email = '';
    $phone = '';
    $address = '';
    $position = '';
    $hire_date = date('Y-m-d');
    $status = 'active';
    $create_login = false;
    $username = '';
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get form data
        $name = trim($_POST['name'] ?? '');
        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $hire_date = trim($_POST['hire_date'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $create_login = isset($_POST['create_login']);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($firstName)) {
            $error = "First name is required";
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Valid email is required";
        } elseif ($create_login) {
            if (empty($username)) {
                $error = "Username is required for login";
            } elseif (empty($password)) {
                $error = "Password is required for login";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match";
            }
        }
        
        // If no errors, add staff to database
        if (empty($error)) {
            try {
                // Connect to the database
                $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->beginTransaction();
                
                // Add staff record
                $stmt = $pdo->prepare("INSERT INTO staff (first_name, last_name, email, phone, address, position, hire_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$firstName, $lastName, $email, $phone, $address, $position, $hire_date, $status]);
                $staffId = $pdo->lastInsertId();
                
                // Create login if requested
                if ($create_login) {
                    // Check if username exists
                    $stmt = $pdo->prepare("SELECT staff_id FROM staff_login WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->rowCount() > 0) {
                        throw new Exception("Username already exists. Please choose another.");
                    }
                    
                    // Add login credentials
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO staff_login (staff_id, username, password) VALUES (?, ?, ?)");
                    $stmt->execute([$staffId, $username, $hashedPassword]);
                }
                
                $pdo->commit();
                $logger->log("Staff added: ID $staffId, Name: $name", "INFO");
                
                $_SESSION['success_message'] = "Staff member added successfully";
                header('Location: staff.php');
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error adding staff: " . $e->getMessage();
                $logger->log("Error adding staff: " . $e->getMessage(), "ERROR");
                
                // Reset form values on error
                $name = '';
                $email = '';
                $phone = '';
                $address = '';
                $position = '';
                $hire_date = date('Y-m-d');
                $status = 'active';
                $username = '';
                $create_login = false;
            }
        }
    }
    
    // Include the template
    include 'templates/staff_add_template.php';
}

/**
 * Function to handle editing a staff member
 */
function handleEditStaff($staffId) {
    global $logger;
    
    $error = '';
    $success = '';
    $staff = null;
    
    if ($staffId <= 0) {
        header('Location: staff.php');
        exit;
    }
    
    try {
        // Connect to the database
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Get form data
            $name = trim($_POST['name'] ?? '');
            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $hire_date = trim($_POST['hire_date'] ?? '');
            $enableLogin = isset($_POST['enable_login']);
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validate input
            if (empty($firstName)) {
                $error = "First name is required";
            } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Valid email is required";
            } elseif ($enableLogin && empty($username)) {
                $error = "Username is required for login";
            } elseif (!empty($password) && $password !== $confirmPassword) {
                $error = "Passwords do not match";
            }
            
            if (empty($error)) {
                $pdo->beginTransaction();
                
                try {
                    // Update staff record
                    $stmt = $pdo->prepare("UPDATE staff SET first_name = ?, last_name = ?, email = ?, phone = ?, position = ?, hire_date = ? WHERE staff_id = ?");
                    $stmt->execute([$firstName, $lastName, $email, $phone, $position, $hire_date, $staffId]);
                    
                    // Check current login status
                    $stmt = $pdo->prepare("SELECT username FROM staff_login WHERE staff_id = ?");
                    $stmt->execute([$staffId]);
                    $hasLogin = $stmt->rowCount() > 0;
                    $currentLogin = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($enableLogin) {
                        if (!$hasLogin) {
                            // Create new login
                            if (empty($password)) {
                                throw new Exception("Password is required when creating a new login");
                            }
                            
                            // Check if username exists
                            $stmt = $pdo->prepare("SELECT staff_id FROM staff_login WHERE username = ? AND staff_id != ?");
                            $stmt->execute([$username, $staffId]);
                            if ($stmt->rowCount() > 0) {
                                throw new Exception("Username already exists. Please choose another.");
                            }
                            
                            // Add login
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("INSERT INTO staff_login (staff_id, username, password) VALUES (?, ?, ?)");
                            $stmt->execute([$staffId, $username, $hashedPassword]);
                        } else {
                            // Update existing login
                            if (!empty($password)) {
                                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("UPDATE staff_login SET username = ?, password = ? WHERE staff_id = ?");
                                $stmt->execute([$username, $hashedPassword, $staffId]);
                            } else {
                                $stmt = $pdo->prepare("UPDATE staff_login SET username = ? WHERE staff_id = ?");
                                $stmt->execute([$username, $staffId]);
                            }
                        }
                    } elseif ($hasLogin) {
                        // Remove login
                        $stmt = $pdo->prepare("DELETE FROM staff_login WHERE staff_id = ?");
                        $stmt->execute([$staffId]);
                    }
                    
                    $pdo->commit();
                    $success = "Staff member updated successfully";
                    $logger->log("Staff updated: ID $staffId, Name: $name", "INFO");
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error updating staff: " . $e->getMessage();
                    $logger->log("Error updating staff: " . $e->getMessage(), "ERROR");
                }
            }
        }
        
        // Get staff information for form
        $stmt = $pdo->prepare("SELECT s.*, sl.username, sl.staff_id AS login_id
                              FROM staff s
                              LEFT JOIN staff_login sl ON s.staff_id = sl.staff_id
                              WHERE s.staff_id = ?");
        $stmt->execute([$staffId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) {
            $error = "Staff member not found";
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $logger->log("Error in staff edit: " . $e->getMessage(), "ERROR");
    }
    
    // Include the template
    include 'templates/staff_edit_template.php';
}

/**
 * Function to handle deleting a staff member
 */
function handleDeleteStaff($staffId) {
    global $logger;
    
    $error = '';
    $staff = null;
    $hasActiveTransactions = false;
    $activeTransactionCount = 0;
    
    if ($staffId <= 0) {
        header('Location: staff.php');
        exit;
    }
    
    // Prevent self-deletion
    if ($staffId == $_SESSION['staff_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
        header('Location: staff.php');
        exit;
    }
    
    try {
        // Connect to the database
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get staff information
        $stmt = $pdo->prepare("SELECT s.*, sl.username, CASE WHEN sl.username IS NOT NULL THEN 1 ELSE 0 END as has_login
                              FROM staff s
                              LEFT JOIN staff_login sl ON s.staff_id = sl.staff_id
                              WHERE s.staff_id = ?");
        $stmt->execute([$staffId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) {
            $error = "Staff member not found";
        } else {
            // Check for active transactions
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions 
                                  WHERE staff_id = ? AND return_date IS NULL");
            $stmt->execute([$staffId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $activeTransactionCount = $result['count'];
            $hasActiveTransactions = $activeTransactionCount > 0;
            
            // Process deletion if form submitted
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasActiveTransactions) {
                $pdo->beginTransaction();
                
                try {
                    // Delete from staff_login first (if exists)
                    if ($staff['has_login']) {
                        $stmt = $pdo->prepare("DELETE FROM staff_login WHERE staff_id = ?");
                        $stmt->execute([$staffId]);
                    }
                    
                    // Delete from staff
                    $stmt = $pdo->prepare("DELETE FROM staff WHERE staff_id = ?");
                    $stmt->execute([$staffId]);
                    
                    $pdo->commit();
                    
                    $logger->log("Staff deleted: ID $staffId, Name: {$staff['first_name']} {$staff['last_name']}", "INFO");
                    
                    $_SESSION['success_message'] = "Staff member deleted successfully";
                    header('Location: staff.php');
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error deleting staff: " . $e->getMessage();
                    $logger->log("Error deleting staff: " . $e->getMessage(), "ERROR");
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $logger->log("Error in staff delete: " . $e->getMessage(), "ERROR");
    }
    
    // Include the template
    include 'templates/staff_delete_template.php';
} 