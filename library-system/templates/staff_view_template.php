<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Staff Member - Library Management System</title>
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
                <li><a href="members.php">Members</a></li>
                <li><a href="transactions.php">Transactions</a></li>
                <li><a href="fines.php">Fines</a></li>
                <li class="active"><a href="staff.php">Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Staff Member Details</h1>
                <div class="actions">
                    <a href="staff.php?action=edit&id=<?php echo $staff['staff_id']; ?>" class="btn btn-icon btn-edit" title="Edit">Edit</a>
                    <a href="#" class="btn btn-icon btn-delete" title="Delete" 
                       onclick="confirmDelete(<?php echo $staff['staff_id']; ?>, '<?php echo htmlspecialchars(addslashes($staff['name'])); ?>')">Delete</a>
                    <a href="staff.php" class="btn btn-secondary">Back to Staff List</a>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <div class="staff-info card">
                    <div class="card-header">
                        <h2><?php echo htmlspecialchars($staff['name']); ?></h2>
                        <div class="position"><?php echo htmlspecialchars($staff['position'] ?: 'Not specified'); ?></div>
                    </div>
                    <div class="card-content">
                        <div class="info-group">
                            <div class="info-item">
                                <span class="label">Staff ID:</span>
                                <span class="value"><?php echo $staff['staff_id']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Email:</span>
                                <span class="value"><?php echo htmlspecialchars($staff['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Phone:</span>
                                <span class="value"><?php echo htmlspecialchars($staff['phone'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Hire Date:</span>
                                <span class="value"><?php echo htmlspecialchars($staff['hire_date'] ? date('F j, Y', strtotime($staff['hire_date'])) : 'Not specified'); ?></span>
                            </div>
                        </div>
                        
                        <div class="login-status">
                            <h3>Login Credentials</h3>
                            <?php if (isset($staff['login_id']) && $staff['login_id']): ?>
                                <div class="status-badge success">Login Enabled</div>
                                <div class="info-item">
                                    <span class="label">Username:</span>
                                    <span class="value"><?php echo htmlspecialchars($staff['username']); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="status-badge neutral">No Login Credentials</div>
                                <p>This staff member doesn't have login credentials. You can add them by editing their profile.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($transactions)): ?>
                <div class="recent-activity card">
                    <div class="card-header">
                        <h2>Recent Activity</h2>
                    </div>
                    <div class="card-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Book Title</th>
                                    <th>Member</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo $transaction['transaction_id']; ?></td>
                                    <td><?php echo htmlspecialchars($transaction['book_title']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['member_name']); ?></td>
                                    <td><?php echo $transaction['type']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="recent-activity card">
                    <div class="card-header">
                        <h2>Recent Activity</h2>
                    </div>
                    <div class="card-content">
                        <p class="empty-state">No recent transactions found for this staff member.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
    // Confirm delete function
    function confirmDelete(id, name) {
        if (confirm(`Are you sure you want to delete staff member "${name}"?`)) {
            window.location.href = `staff.php?action=delete&id=${id}`;
        }
    }
</script>
</body>
</html> 