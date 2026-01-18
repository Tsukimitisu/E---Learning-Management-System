<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$registrar_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? null;

// Get form data
$student_id = (int)($_POST['student_id'] ?? 0);
$fee_type = trim($_POST['fee_type'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);
$semester = trim($_POST['semester'] ?? '1st');
$description = trim($_POST['description'] ?? '');

// Validation
if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student selected']);
    exit;
}

if (empty($fee_type)) {
    echo json_encode(['success' => false, 'message' => 'Fee type is required']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid amount greater than 0']);
    exit;
}

// Verify student exists
$student_check = $conn->prepare("SELECT user_id FROM students WHERE user_id = ?");
$student_check->bind_param("i", $student_id);
$student_check->execute();
if ($student_check->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit;
}

// Insert fee
$stmt = $conn->prepare("
    INSERT INTO student_fees (student_id, fee_type, amount, academic_year_id, semester, description, created_by) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("isdissi", $student_id, $fee_type, $amount, $current_ay_id, $semester, $description, $registrar_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => "Fee assessed successfully: $fee_type - ₱" . number_format($amount, 2)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to assess fee: ' . $stmt->error]);
}
?>
