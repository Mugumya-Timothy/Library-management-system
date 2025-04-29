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
$members = [];
$error = '';
$success = '';

// Check for flash messages
if (isset($_SESSION['flash_message'])) {
    $success = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Handle delete request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $memberId = (int)$_GET['id'];
    $conn = getDbConnection();
    
    if ($conn) {
        try {
            // Check if member has active loans
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count FROM transactions 
                WHERE member_id = ? AND status != 'Returned'
            ");
            $stmt->bind_param("i", $memberId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $error = "Cannot delete member with active loans. Please return all books first.";
            } else {
                // Delete member
                $stmt = $conn->prepare("DELETE FROM members WHERE member_id = ?");
                $stmt->bind_param("i", $memberId);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success = "Member deleted successfully.";
                        $logger->info("Member ID $memberId deleted by Staff ID {$_SESSION['staff_id']}");
                    } else {
                        $error = "Member not found.";
                    }
                } else {
                    $error = "Error deleting member: " . $conn->error;
                    $logger->error("Error deleting member ID $memberId: " . $conn->error);
                }
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $error = "An error occurred. Please try again.";
            $logger->error("Member deletion error: " . $e->getMessage(), $e);
        }
        
        $conn->close();
    } else {
        $error = "Database connection error. Please try again later.";
    }
}

// Fetch members
$conn = getDbConnection();
if ($conn) {
    try {
        $result = $conn->query("
            SELECT member_id, first_name, last_name, email, phone, address, 
                   registration_date, membership_status, membership_expiry 
            FROM members 
            ORDER BY last_name, first_name
        ");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        $error = "Error fetching members. Please try again.";
        $logger->error("Error fetching members: " . $e->getMessage(), $e);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/members.css">
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
                <h1>Members</h1>
                <div class="actions">
                    <a href="members_add.php" class="btn btn-primary">Add New Member</a>
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
                    <input type="text" id="searchInput" placeholder="Search members...">
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Registration Date</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($members)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No members found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['member_id']); ?></td>
                                        <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($member['registration_date']); ?></td>
                                        <td><?php echo htmlspecialchars($member['membership_expiry']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($member['membership_status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($member['membership_status'])); ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <a href="members_edit.php?id=<?php echo $member['member_id']; ?>" class="btn btn-icon btn-edit" title="Edit">Edit</a>
                                            <a href="#" class="btn btn-icon btn-delete" title="Delete" 
                                               onclick="confirmDelete(<?php echo $member['member_id']; ?>, '<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>')">Delete</a>
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
        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete member "${name}"?`)) {
                window.location.href = `members.php?action=delete&id=${id}`;
            }
        }
    </script>
</body>
</html> 