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
    header('Location: staff.php');
    exit;
}

$staffId = (int)$_GET['id'];

// Initialize variables
$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$position = '';
$hire_date = '';
$username = '';
$current_username = '';
$has_login = false;
$create_login = false;
$change_password = false;
$password = '';

// Get staff information
$conn = getDbConnection();

if ($conn) {
    try {
        // Fetch staff data
        $stmt = $conn->prepare("
            SELECT s.first_name, s.last_name, s.email, s.phone, s.position, s.hire_date,
                   sl.username, CASE WHEN sl.username IS NOT NULL THEN 1 ELSE 0 END AS has_login
            FROM staff s
            LEFT JOIN staff_login sl ON s.staff_id = sl.staff_id
            WHERE s.staff_id = ?
        ");
        $stmt->bind_param("i", $staffId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Staff member not found";
            $stmt->close();
            $conn->close();
            header('Location: staff.php');
            exit;
        }
        
        $staffData = $result->fetch_assoc();
        $firstName = $staffData['first_name'];
        $lastName = $staffData['last_name'];
        $name = $firstName . ' ' . $lastName; // For display purposes
        $email = $staffData['email'];
        $phone = $staffData['phone'];
        $position = $staffData['position'];
        $hire_date = $staffData['hire_date'];
        $has_login = $staffData['has_login'] == 1;
        $current_username = $staffData['username'];
        $username = $current_username;
        $stmt->close();
        
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
            $create_login = isset($_POST['create_login']) && !$has_login;
            $change_password = isset($_POST['change_password']) && $has_login;
            $username = $has_login ? $current_username : trim($_POST['username'] ?? '');
            
            if ($create_login) {
                $username = trim($_POST['username'] ?? '');
                $password = trim($_POST['password'] ?? '');
            }
            
            if ($change_password) {
                $password = trim($_POST['new_password'] ?? '');
            }
            
            // Validate input
            if (empty($firstName)) {
                $error = "First name is required";
            } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Valid email address is required";
            } elseif (empty($position)) {
                $error = "Position is required";
            } elseif (empty($hire_date) || !validateDate($hire_date)) {
                $error = "Valid hire date is required";
            } elseif ($create_login) {
                if (empty($username)) {
                    $error = "Username is required for login account";
                } elseif (empty($password) || strlen($password) < 6) {
                    $error = "Password must be at least 6 characters";
                }
            } elseif ($change_password) {
                if (empty($password) || strlen($password) < 6) {
                    $error = "New password must be at least 6 characters";
                }
            }
            
            // If no errors, proceed with database operations
            if (empty($error)) {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Update staff table
                    $stmt = $conn->prepare("
                        UPDATE staff 
                        SET first_name = ?, last_name = ?, email = ?, phone = ?, position = ?, hire_date = ? 
                        WHERE staff_id = ?
                    ");
                    $stmt->bind_param("ssssssi", $firstName, $lastName, $email, $phone, $position, $hire_date, $staffId);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update staff information: " . $conn->error);
                    }
                    
                    $stmt->close();
                    
                    // Handle login account
                    if ($create_login) {
                        // Check if username already exists
                        $stmt = $conn->prepare("SELECT staff_id FROM staff_login WHERE username = ?");
                        $stmt->bind_param("s", $username);
                        $stmt->execute();
                        $usernameResult = $stmt->get_result();
                        
                        if ($usernameResult->num_rows > 0) {
                            throw new Exception("Username already exists. Please choose a different username.");
                        }
                        
                        $stmt->close();
                        
                        // Create login account
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO staff_login (staff_id, username, password) VALUES (?, ?, ?)");
                        $stmt->bind_param("iss", $staffId, $username, $hashed_password);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to create login account: " . $conn->error);
                        }
                        
                        $stmt->close();
                        $has_login = true;
                        $current_username = $username;
                    } elseif ($change_password) {
                        // Update password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE staff_login SET password = ? WHERE staff_id = ?");
                        $stmt->bind_param("si", $hashed_password, $staffId);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update password: " . $conn->error);
                        }
                        
                        $stmt->close();
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success = "Staff information updated successfully";
                    $logger->info("Staff ID $staffId updated by Staff ID {$_SESSION['staff_id']}");
                    
                    // Reset password fields
                    $password = '';
                    $change_password = false;
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                    $logger->error("Staff edit error: " . $e->getMessage(), $e);
                }
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        $error = "Error fetching staff information. Please try again.";
        $logger->error("Error fetching staff ID $staffId: " . $e->getMessage(), $e);
        
        if (isset($conn) && $conn) {
            $conn->close();
        }
    }
} else {
    $error = "Database connection error. Please try again later.";
}

// Helper function to validate date
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff - Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
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
                <li><a href="fines.php">Fines</a></li>
                <li class="active"><a href="staff.php">Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Edit Staff</h1>
                <div class="actions">
                    <a href="staff.php" class="btn btn-secondary">Back to Staff List</a>
                    <a href="staff_view.php?id=<?php echo $staffId; ?>" class="btn btn-secondary">View Staff Details</a>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <div class="form-container">
                    <form action="" method="POST" class="form">
                        <div class="form-group">
                            <label for="name">Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="position">Position <span class="required">*</span></label>
                            <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($position); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="hire_date">Hire Date <span class="required">*</span></label>
                            <input type="date" id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($hire_date); ?>" required>
                        </div>
                        
                        <div class="form-divider"></div>
                        
                        <?php if ($has_login): ?>
                            <div class="form-group">
                                <label>Login Information</label>
                                <div class="detail-row">
                                    <div class="detail-label">Username:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($current_username); ?></div>
                                </div>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="change_password" name="change_password" <?php echo $change_password ? 'checked' : ''; ?>>
                                <label for="change_password">Change password</label>
                            </div>
                            
                            <div id="password-fields" class="<?php echo $change_password ? '' : 'hidden'; ?>">
                                <div class="form-group">
                                    <label for="new_password">New Password <span class="required">*</span></label>
                                    <input type="password" id="new_password" name="new_password" minlength="6">
                                    <small>Password must be at least 6 characters long</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="create_login" name="create_login" <?php echo $create_login ? 'checked' : ''; ?>>
                                <label for="create_login">Create login account for this staff member</label>
                            </div>
                            
                            <div id="login-details" class="<?php echo $create_login ? '' : 'hidden'; ?>">
                                <div class="form-group">
                                    <label for="username">Username <span class="required">*</span></label>
                                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">Password <span class="required">*</span></label>
                                    <input type="password" id="password" name="password" minlength="6">
                                    <small>Password must be at least 6 characters long</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Staff</button>
                            <a href="staff.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        <?php if ($has_login): ?>
        // Toggle password fields visibility based on checkbox
        document.getElementById('change_password').addEventListener('change', function() {
            document.getElementById('password-fields').classList.toggle('hidden', !this.checked);
            
            // Reset password when unchecked
            if (!this.checked) {
                document.getElementById('new_password').value = '';
            }
        });
        <?php else: ?>
        // Toggle login details visibility based on checkbox
        document.getElementById('create_login').addEventListener('change', function() {
            document.getElementById('login-details').classList.toggle('hidden', !this.checked);
            
            // Reset fields when unchecked
            if (!this.checked) {
                document.getElementById('username').value = '';
                document.getElementById('password').value = '';
            }
        });
        <?php endif; ?>
    </script>
</body>
</html> 
