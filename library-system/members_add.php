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
$registrationDate = date('Y-m-d'); // Default to today
$membershipStatus = 'active';
$membershipExpiry = date('Y-m-d', strtotime('+1 year')); // Default to 1 year from today

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize inputs
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $registrationDate = sanitizeInput($_POST['registration_date'] ?? '');
    $membershipStatus = sanitizeInput($_POST['membership_status'] ?? 'active');
    $membershipExpiry = sanitizeInput($_POST['membership_expiry'] ?? '');
    
    // Validate inputs
    if (empty($firstName)) {
        $error = "First name is required";
    } elseif (empty($lastName)) {
        $error = "Last name is required";
    } elseif (!empty($email) && !isValidEmail($email)) {
        $error = "Invalid email format";
    } elseif (!empty($phone) && !isValidPhone($phone)) {
        $error = "Invalid phone number format";
    } elseif (empty($registrationDate)) {
        $error = "Registration date is required";
    } elseif (empty($membershipExpiry)) {
        $error = "Membership expiry date is required";
    } else {
        $conn = getDbConnection();
        
        if ($conn) {
            try {
                // Check if email already exists
                if (!empty($email)) {
                    $stmt = $conn->prepare("SELECT member_id FROM members WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "Email already exists";
                        $stmt->close();
                    } else {
                        $stmt->close();
                        
                        // Insert new member
                        $stmt = $conn->prepare("
                            INSERT INTO members (first_name, last_name, email, phone, address, 
                                             registration_date, membership_status, membership_expiry)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->bind_param("ssssssss", $firstName, $lastName, $email, $phone, $address, 
                                          $registrationDate, $membershipStatus, $membershipExpiry);
                        
                        if ($stmt->execute()) {
                            $memberId = $conn->insert_id;
                            $success = "Member added successfully";
                            $logger->info("New member (ID: $memberId, Name: $firstName $lastName) added by Staff ID {$_SESSION['staff_id']}");
                            
                            // Reset form
                            $firstName = '';
                            $lastName = '';
                            $email = '';
                            $phone = '';
                            $address = '';
                            $registrationDate = date('Y-m-d');
                            $membershipStatus = 'active';
                            $membershipExpiry = date('Y-m-d', strtotime('+1 year'));
                        } else {
                            $error = "Error adding member: " . $conn->error;
                            $logger->error("Error adding member: " . $conn->error);
                        }
                        
                        $stmt->close();
                    }
                } else {
                    $error = "Email is required";
                }
            } catch (Exception $e) {
                $error = "An error occurred. Please try again.";
                $logger->error("Member add error: " . $e->getMessage(), $e);
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
    <title>Add Member - Library Management System</title>
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
                <li class="active"><a href="members.php">Members</a></li>
                <li><a href="transactions.php">Transactions</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li><a href="staff.php">Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Add Member</h1>
                <div class="actions">
                    <a href="members.php" class="btn btn-secondary">Back to Members</a>
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
                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="registration_date">Registration Date <span class="required">*</span></label>
                        <input type="date" id="registration_date" name="registration_date" value="<?php echo htmlspecialchars($registrationDate); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="membership_status">Membership Status</label>
                        <select id="membership_status" name="membership_status">
                            <option value="active" <?php echo $membershipStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?php echo $membershipStatus === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="suspended" <?php echo $membershipStatus === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="membership_expiry">Membership Expiry Date <span class="required">*</span></label>
                        <input type="date" id="membership_expiry" name="membership_expiry" value="<?php echo htmlspecialchars($membershipExpiry); ?>" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Add Member</button>
                        <a href="members.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 
