<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff Member - Library Management System</title>
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
                <h1>Edit Staff Member</h1>
                <div class="actions">
                    <a href="staff.php" class="btn btn-secondary">Back to Staff List</a>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <div class="card">
                    <div class="card-header">
                        <h2>Staff Information</h2>
                    </div>
                    <div class="card-content">
                        <form action="staff.php?action=edit&id=<?php echo $staff['staff_id']; ?>" method="post">
                            <div class="form-group">
                                <label for="name">Full Name:</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($staff['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address:</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number:</label>
                                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($staff['phone']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="position">Position:</label>
                                <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($staff['position']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="hire_date">Hire Date:</label>
                                <input type="date" id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($staff['hire_date']); ?>" required>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="enable_login" name="enable_login" <?php echo (!empty($hasLogin)) ? 'checked' : ''; ?> onclick="toggleLoginFields()">
                                <label for="enable_login">Enable Login for this Staff Member</label>
                            </div>
                            
                            <div id="login-fields" class="<?php echo (empty($hasLogin)) ? 'hidden' : ''; ?>">
                                <div class="form-group">
                                    <label for="username">Username:</label>
                                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($loginInfo['username'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">New Password:</label>
                                    <input type="password" id="password" name="password">
                                    <small>Leave blank to keep current password</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password:</label>
                                    <input type="password" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Update Staff Member</button>
                                <a href="staff.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleLoginFields() {
            const loginFields = document.getElementById('login-fields');
            const enableLogin = document.getElementById('enable_login');
            
            if (enableLogin.checked) {
                loginFields.classList.remove('hidden');
            } else {
                loginFields.classList.add('hidden');
            }
        }
    </script>
</body>
</html> 