<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff Member - Library Management System</title>
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
                <h1>Add New Staff Member</h1>
                <div class="actions">
                    <a href="staff.php" class="btn btn-secondary">Cancel</a>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <form action="staff.php?action=add" method="post" class="form">
                    <div class="form-group">
                        <label for="name">Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="position">Position <span class="required">*</span></label>
                        <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($position ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="hire_date">Hire Date <span class="required">*</span></label>
                        <input type="date" id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($hire_date ?? date('Y-m-d')); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo (isset($status) && $status === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($status) && $status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-divider"></div>
                    
                    <div class="form-section">
                        <h3>Login Credentials</h3>
                        
                        <div class="form-check">
                            <input type="checkbox" id="create_login" name="create_login" value="1" <?php echo !empty($create_login) ? 'checked' : ''; ?>>
                            <label for="create_login">Create login credentials for this staff member</label>
                        </div>
                        
                        <div id="login-fields" <?php echo empty($create_login) ? 'style="display: none;"' : ''; ?>>
                            <div class="form-group">
                                <label for="username">Username <span class="required">*</span></label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                                <input type="password" id="password" name="password">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Add Staff Member</button>
                        <a href="staff.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const createLoginCheckbox = document.getElementById('create_login');
            const loginFieldsContainer = document.getElementById('login-fields');
            
            createLoginCheckbox.addEventListener('change', function() {
                loginFieldsContainer.style.display = this.checked ? 'block' : 'none';
            });
        });
    </script>
</body>
</html> 