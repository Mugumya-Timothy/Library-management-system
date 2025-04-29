<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Library Management System</title>
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
                <h1>Staff Management</h1>
                <div class="actions">
                    <a href="staff.php?action=add" class="btn btn-primary">Add New Staff</a>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <div class="search-container">
                    <form action="" method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Search by name, email or position..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="staff.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="<?php echo getSortUrl('staff_id', $sort, $dir); ?>">
                                        ID <?php echo getSortIcon('staff_id', $sort, $dir); ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo getSortUrl('name', $sort, $dir); ?>">
                                        Name <?php echo getSortIcon('name', $sort, $dir); ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo getSortUrl('email', $sort, $dir); ?>">
                                        Email <?php echo getSortIcon('email', $sort, $dir); ?>
                                    </a>
                                </th>
                                <th>Phone</th>
                                <th>
                                    <a href="<?php echo getSortUrl('position', $sort, $dir); ?>">
                                        Position <?php echo getSortIcon('position', $sort, $dir); ?>
                                    </a>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($staff) > 0): ?>
                                <?php foreach ($staff as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['staff_id']); ?></td>
                                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td><?php echo htmlspecialchars($member['phone'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($member['position'] ?: 'N/A'); ?></td>
                                        <td class="actions">
                                            <a href="staff.php?action=edit&id=<?php echo $member['staff_id']; ?>" class="btn btn-icon btn-edit" title="Edit">Edit</a>
                                            <a href="#" class="btn btn-icon btn-delete" title="Delete" 
                                               onclick="confirmDelete(<?php echo $member['staff_id']; ?>, '<?php echo htmlspecialchars(addslashes($member['name'])); ?>')">
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-results">No staff members found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Simple client-side search functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality can be added here if needed
        });
        
        // Confirm delete
        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete staff member "${name}"?`)) {
                window.location.href = `staff.php?action=delete&id=${id}`;
            }
        }
    </script>
</body>
</html> 