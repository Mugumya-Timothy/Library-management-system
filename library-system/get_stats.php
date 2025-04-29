<?php
require_once 'config.php';

header('Content-Type: application/json');

// Connect to database
$conn = getDbConnection();
if (!$conn) {
    die(json_encode(['error' => 'Connection failed']));
}

$stats = [];

// Get total books
$result = $conn->query("SELECT SUM(total_copies) as total FROM books");
$row = $result->fetch_assoc();
$stats['total_books'] = (int)($row['total'] ?? 0);

// Get books borrowed
$result = $conn->query("SELECT SUM(total_copies - available_copies) as borrowed FROM books");
$row = $result->fetch_assoc();
$stats['books_borrowed'] = (int)($row['borrowed'] ?? 0);

// Get total members
$result = $conn->query("SELECT COUNT(*) as total FROM members");
$row = $result->fetch_assoc();
$stats['total_members'] = (int)($row['total'] ?? 0);

// Get active members
$result = $conn->query("SELECT COUNT(*) as active FROM members WHERE membership_status = 'active'");
$row = $result->fetch_assoc();
$stats['active_members'] = (int)($row['active'] ?? 0);

// Members by status
$members_by_status = [];
$result = $conn->query("SELECT membership_status, COUNT(*) as count FROM members GROUP BY membership_status");
while ($row = $result->fetch_assoc()) {
    $members_by_status[$row['membership_status']] = (int)$row['count'];
}
$stats['members_by_status'] = $members_by_status;

// Get overdue loans
$result = $conn->query("SELECT COUNT(*) as overdue FROM transactions WHERE status = 'overdue'");
$row = $result->fetch_assoc();
$stats['overdue_loans'] = (int)($row['overdue'] ?? 0);

// Transactions by status
$transactions_by_status = [];
$result = $conn->query("SELECT status, COUNT(*) as count FROM transactions GROUP BY status");
while ($row = $result->fetch_assoc()) {
    $transactions_by_status[$row['status']] = (int)$row['count'];
}
$stats['transactions_by_status'] = $transactions_by_status;

// Get unpaid fines
$result = $conn->query("SELECT COUNT(*) as unpaid FROM fines WHERE payment_status = 'unpaid'");
$row = $result->fetch_assoc();
$stats['unpaid_fines'] = (int)($row['unpaid'] ?? 0);

// Fines by status
$fines_by_status = [];
$result = $conn->query("SELECT payment_status, COUNT(*) as count FROM fines GROUP BY payment_status");
while ($row = $result->fetch_assoc()) {
    $fines_by_status[$row['payment_status']] = (int)$row['count'];
}
$stats['fines_by_status'] = $fines_by_status;

echo json_encode($stats, JSON_PRETTY_PRINT);

$conn->close();
?> 