<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit;
}

// Get total fees
$fees_total = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM student_fees WHERE student_id = $student_id")->fetch_assoc();

// Get total paid (verified only)
$paid_total = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE student_id = $student_id AND status = 'verified'")->fetch_assoc();

// Get payment history
$payments_result = $conn->query("
    SELECT p.*, CONCAT(rec.first_name, ' ', rec.last_name) as recorded_by_name
    FROM payments p
    LEFT JOIN user_profiles rec ON p.recorded_by = rec.user_id
    WHERE p.student_id = $student_id
    ORDER BY p.created_at DESC
");

$payments = [];
while ($row = $payments_result->fetch_assoc()) {
    $payments[] = $row;
}

// Get assessed fees
$fees_result = $conn->query("
    SELECT sf.*, ay.year_name,
           CONCAT(cb.first_name, ' ', cb.last_name) as created_by_name
    FROM student_fees sf
    LEFT JOIN academic_years ay ON sf.academic_year_id = ay.id
    LEFT JOIN user_profiles cb ON sf.created_by = cb.user_id
    WHERE sf.student_id = $student_id
    ORDER BY sf.created_at DESC
");

$fees = [];
while ($row = $fees_result->fetch_assoc()) {
    $fees[] = $row;
}

echo json_encode([
    'success' => true,
    'total_fees' => (float)$fees_total['total'],
    'total_paid' => (float)$paid_total['total'],
    'payments' => $payments,
    'fees' => $fees
]);
?>
