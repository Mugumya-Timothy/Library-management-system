<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Staff - Library Management System</title>
    <link rel="stylesheet" href="css/staff.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="brand">
                <h2>Library System</h2>
            </div>
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <span class="menu-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="books.php" class="menu-item">
                    <span class="menu-icon">📚</span>
                    <span>Books</span>
                </a>
                <a href="members.php" class="menu-item">
                    <span class="menu-icon">👥</span>
                    <span>Members</span>
                </a>
                <a href="transactions.php" class="menu-item">
                    <span class="menu-icon">🔄</span>
                    <span>Transactions</span>
                </a>
                <a href="staff.php" class="menu-item active">
                    <span class="menu-icon">👨‍💼</span>
                    <span>Staff</span>
                </a>
                <a href="reports.php" class="menu-item">
                    <span class="menu-icon">📝</span>
                    <span>Reports</span>
                </a>
                <a href="logout.php" class="menu-item">
                    <span class="menu-icon">🚪</span>
                    <span>Logout</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <header class="dashboard-header">
                <h1>Delete Staff</h1>
                <div class="user-info">
                    <?php if(isset($_SESSION['username'])): ?>
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="content-wrapper">
                <?php if($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <a href="staff.php" class="btn">Back to Staff List</a>
                <?php elseif($staff): ?>
                    <div class="warning-box">
                        <strong>Warning:</strong> This action cannot be undone. Deleting a staff member will permanently remove all their information from the system.
                    </div>

                    <div class="staff-info">
                        <h2>Staff Information</h2>
                        <p><strong>ID:</strong> <?php echo htmlspecialchars($staff['staff_id']); ?></p>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($staff['name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($staff['phone']); ?></p>
                        <p><strong>Position:</strong> <?php echo htmlspecialchars($staff['position']); ?></p>
                        <p><strong>Hire Date:</strong> <?php echo htmlspecialchars($staff['hire_date']); ?></p>
                        <p><strong>Login Account:</strong> <?php echo $staff['has_login'] ? 'Yes (Username: ' . htmlspecialchars($staff['username']) . ')' : 'No'; ?></p>
                    </div>

                    <?php if($hasActiveTransactions): ?>
                        <div class="error-message">
                            <strong>Cannot delete:</strong> This staff member has <?php echo $activeTransactionCount; ?> active transaction(s). 
                            Please reassign or complete these transactions before deleting this staff member.
                        </div>
                        <a href="staff.php" class="btn">Back to Staff List</a>
                    <?php else: ?>
                        <form action="staff.php?action=delete&id=<?php echo $staffId; ?>" method="post" class="delete-form">
                            <p><strong>Are you sure you want to delete this staff member?</strong></p>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-danger">Yes, Delete Staff</button>
                                <a href="staff.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="error-message">No staff member found.</div>
                    <a href="staff.php" class="btn">Back to Staff List</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 