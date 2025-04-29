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
    header('Location: members.php');
    exit;
}

$memberId = (int)$_GET['id'];

// Initialize variables
$firstName = '';
$lastName = '';
$name = ''; // For display purposes
$email = '';
$phone = '';
$address = '';
$membershipDate = '';
$status = '';
$membershipExpiry = '';

// Fetch member details
$conn = getDbConnection();
if ($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT first_name, last_name, email, phone, address, registration_date, membership_status, membership_expiry 
            FROM members 
            WHERE member_id = ?
        ");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Member not found";
        } else {
            $member = $result->fetch_assoc();
            $firstName = $member['first_name'];
            $lastName = $member['last_name'];
            $name = $firstName . ' ' . $lastName; // For display purposes
            $email = $member['email'] ?? '';
            $phone = $member['phone'] ?? '';
            $address = $member['address'] ?? '';
            $membershipDate = $member['registration_date'];
            $status = $member['membership_status'];
            $membershipExpiry = $member['membership_expiry'];
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error fetching member details. Please try again.";
        $logger->error("Error fetching member ID $memberId: " . $e->getMessage(), $e);
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize inputs
    $name = sanitizeInput($_POST['name'] ?? '');
    $nameParts = explode(' ', $name, 2);
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $membershipDate = sanitizeInput($_POST['registration_date'] ?? '');
    $status = sanitizeInput($_POST['membership_status'] ?? 'active');
    $membershipExpiry = sanitizeInput($_POST['membership_expiry'] ?? '');
    
    // Validate inputs
    if (empty($firstName)) {
        $error = "Name is required";
    } elseif (!empty($email) && !isValidEmail($email)) {
        $error = "Invalid email format";
    } elseif (!empty($phone) && !isValidPhone($phone)) {
        $error = "Invalid phone number format";
    } elseif (empty($membershipDate)) {
        $error = "Membership date is required";
    } else {
        $conn = getDbConnection();
        
        if ($conn) {
            try {
                // Check if email already exists for another member
                if (!empty($email)) {
                    $stmt = $conn->prepare("
                        SELECT member_id FROM members 
                        WHERE email = ? AND member_id != ?
                    ");
                    $stmt->bind_param("si", $email, $memberId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "Email already exists for another member";
                        $stmt->close();
                    } else {
                        $stmt->close();
                        
                        // Update member
                        $stmt = $conn->prepare("
                            UPDATE members 
                            SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, 
                                registration_date = ?, membership_status = ?, membership_expiry = ? 
                            WHERE member_id = ?
                        ");
                        $stmt->bind_param("ssssssssi", $firstName, $lastName, $email, $phone, $address, 
                                          $membershipDate, $status, $membershipExpiry, $memberId);
                        
                        if ($stmt->execute()) {
                            $success = "Member details updated successfully";
                            $logger->info("Member ID $memberId updated by Staff ID {$_SESSION['staff_id']}");
                        } else {
                            $error = "Error updating member: " . $conn->error;
                            $logger->error("Error updating member ID $memberId: " . $conn->error);
                        }
                        
                        $stmt->close();
                    }
                } else {
                    // Update member without email
                    $stmt = $conn->prepare("
                        UPDATE members 
                        SET first_name = ?, last_name = ?, email = NULL, phone = ?, address = ?, 
                            registration_date = ?, membership_status = ?, membership_expiry = ?
                        WHERE member_id = ?
                    ");
                    $stmt->bind_param("ssssssssi", $firstName, $lastName, $phone, $address, 
                                    $membershipDate, $status, $membershipExpiry, $memberId);
                    
                    if ($stmt->execute()) {
                        $success = "Member updated successfully";
                        $logger->info("Member ID $memberId updated by Staff ID {$_SESSION['staff_id']}");
                    } else {
                        $error = "Error updating member: " . $conn->error;
                        $logger->error("Error updating member ID $memberId: " . $conn->error);
                    }
                    
                    $stmt->close();
                }
            } catch (Exception $e) {
                $error = "An error occurred. Please try again.";
                $logger->error("Member update error: " . $e->getMessage(), $e);
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
    <title>Edit Member - Library Management System</title>
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
                <h1>Edit Member</h1>
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
                        <label for="name">Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
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
                        <input type="date" id="registration_date" name="registration_date" value="<?php echo htmlspecialchars($membershipDate); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="membership_status">Membership Status</label>
                        <select id="membership_status" name="membership_status">
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="membership_expiry">Membership Expiry Date <span class="required">*</span></label>
                        <input type="date" id="membership_expiry" name="membership_expiry" value="<?php echo htmlspecialchars($membershipExpiry); ?>" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update Member</button>
                        <a href="members.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 
