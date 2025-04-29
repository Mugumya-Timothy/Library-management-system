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
$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$address = '';
$position = '';
$hire_date = date('Y-m-d'); // Default to today
$username = '';
$password = '';
$create_login = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize inputs
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $position = sanitizeInput($_POST['position'] ?? '');
    $hire_date = sanitizeInput($_POST['hire_date'] ?? '');
    $create_login = isset($_POST['create_login']);
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($firstName)) {
        $error = "First name is required";
    } elseif (empty($lastName)) {
        $error = "Last name is required";
    } elseif (empty($email)) {
        $error = "Email is required";
    } elseif (!isValidEmail($email)) {
        $error = "Invalid email format";
    } elseif (!empty($phone) && !isValidPhone($phone)) {
        $error = "Invalid phone number format";
    } elseif (empty($hire_date)) {
        $error = "Hire date is required";
    } elseif ($create_login && empty($username)) {
        $error = "Username is required for login";
    } elseif ($create_login && empty($password)) {
        $error = "Password is required for login";
    } elseif ($create_login && $password !== $confirmPassword) {
        $error = "Passwords do not match";
    } elseif ($create_login && strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        $conn = getDbConnection();
        
        if ($conn) {
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Check if email already exists
                $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "Email already exists";
                    $stmt->close();
                    $conn->rollback();
                } else {
                    $stmt->close();
                    
                    // If creating login, check if username already exists
                    if ($create_login) {
                        $stmt = $conn->prepare("SELECT staff_id FROM staff_login WHERE username = ?");
                        $stmt->bind_param("s", $username);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $error = "Username already exists";
                            $stmt->close();
                            $conn->rollback();
                            throw new Exception("Username already exists");
                        }
                        
                        $stmt->close();
                    }
                    
                    // Insert staff
                    $stmt = $conn->prepare("
                        INSERT INTO staff (first_name, last_name, email, phone, address, position, hire_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("sssssss", $firstName, $lastName, $email, $phone, $address, $position, $hire_date);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error adding staff: " . $conn->error);
                    }
                    
                    $staffId = $conn->insert_id;
                    $stmt->close();
                    
                    // Insert login if required
                    if ($create_login) {
                        // Hash password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $role = 'assistant'; // Default role
                        
                        $stmt = $conn->prepare("
                            INSERT INTO staff_login (staff_id, username, password, role)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->bind_param("isss", $staffId, $username, $hashed_password, $role);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error creating login: " . $conn->error);
                        }
                        
                        $stmt->close();
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success = "Staff member added successfully" . ($create_login ? " with login credentials" : "");
                    $logger->info("New staff (ID: $staffId, Name: $firstName $lastName) added by Staff ID {$_SESSION['staff_id']}");
                    
                    // Reset form
                    $firstName = '';
                    $lastName = '';
                    $email = '';
                    $phone = '';
                    $address = '';
                    $position = '';
                    $hire_date = date('Y-m-d');
                    $username = '';
                    $password = '';
                    $create_login = false;
                }
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                
                if (strpos($e->getMessage(), "Username already exists") === false) {
                    $error = "An error occurred. Please try again.";
                    $logger->error("Staff add error: " . $e->getMessage(), $e);
                }
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
    <title>Add Staff - Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/staff.css">
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
                <h1>Add Staff</h1>
                <div class="actions">
                    <a href="staff.php" class="btn btn-secondary">Back to Staff</a>
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
                    <div class="form-section">
                        <h3>Staff Information</h3>
                        
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($firstName); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>" required>
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
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="position">Position</label>
                            <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($position); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="hire_date">Hire Date <span class="required">*</span></label>
                            <input type="date" id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($hire_date); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Login Credentials</h3>
                        
                        <div class="form-group">
                            <label>Create Login Account</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="create_login" value="yes" <?php echo $create_login ? 'checked' : ''; ?> onchange="toggleLoginFields()">
                                    Yes
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="create_login" value="no" <?php echo !$create_login ? 'checked' : ''; ?> onchange="toggleLoginFields()">
                                    No
                                </label>
                            </div>
                        </div>
                        
                        <div id="loginFields" <?php echo !$create_login ? 'style="display:none"' : ''; ?>>
                            <div class="form-group">
                                <label for="username">Username <span class="required">*</span></label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                                <input type="password" id="password" name="password">
                                <small class="form-text">Password must be at least 8 characters long</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Add Staff</button>
                        <a href="staff.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleLoginFields() {
            const create_login = document.querySelector('input[name="create_login"]:checked').value === 'yes';
            document.getElementById('loginFields').style.display = create_login ? 'block' : 'none';
            
            // Toggle required attribute on login fields
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');
            
            usernameField.required = create_login;
            passwordField.required = create_login;
            confirmPasswordField.required = create_login;
        }
    </script>
</body>
</html> 
