<?php
require_once 'config.php';

// Connect to database
$conn = getDbConnection();
if (!$conn) {
    die("Connection failed");
}

echo "<h1>Database Stats</h1>";

// Get total books
$result = $conn->query("SELECT SUM(total_copies) as total FROM books");
$row = $result->fetch_assoc();
echo "Total Books: " . ($row['total'] ?? 0) . "<br>";

// Get books borrowed
$result = $conn->query("SELECT SUM(total_copies - available_copies) as borrowed FROM books");
$row = $result->fetch_assoc();
echo "Books Borrowed: " . ($row['borrowed'] ?? 0) . "<br>";

// Get total members
$result = $conn->query("SELECT COUNT(*) as total FROM members");
$row = $result->fetch_assoc();
echo "Total Members: " . ($row['total'] ?? 0) . "<br>";

// Get active members
$result = $conn->query("SELECT COUNT(*) as active FROM members WHERE membership_status = 'active'");
$row = $result->fetch_assoc();
echo "Active Members: " . ($row['active'] ?? 0) . "<br>";
$result = $conn->query("SELECT membership_status, COUNT(*) as count FROM members GROUP BY membership_status");
echo "Membership status breakdown:<br>";
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['membership_status'] . ": " . $row['count'] . "<br>";
}

// Get overdue loans
$result = $conn->query("SELECT COUNT(*) as overdue FROM transactions WHERE status = 'overdue'");
$row = $result->fetch_assoc();
echo "Overdue Loans: " . ($row['overdue'] ?? 0) . "<br>";
$result = $conn->query("SELECT status, COUNT(*) as count FROM transactions GROUP BY status");
echo "Transaction status breakdown:<br>";
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['status'] . ": " . $row['count'] . "<br>";
}

// Get unpaid fines
$result = $conn->query("SELECT COUNT(*) as unpaid FROM fines WHERE payment_status = 'unpaid'");
$row = $result->fetch_assoc();
echo "Unpaid Fines: " . ($row['unpaid'] ?? 0) . "<br>";
$result = $conn->query("SELECT payment_status, COUNT(*) as count FROM fines GROUP BY payment_status");
echo "Fine payment status breakdown:<br>";
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['payment_status'] . ": " . $row['count'] . "<br>";
}

$conn->close();
?> 