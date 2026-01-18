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

// Get registrar's branch
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;

// Get current academic year
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get form data
$student_id = (int)($_POST['student_id'] ?? 0);
$or_number = trim($_POST['or_number'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);
$payment_type = trim($_POST['payment_type'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? 'cash');
$semester = trim($_POST['semester'] ?? '1st');
$description = trim($_POST['description'] ?? '');

// Validation
if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student selected']);
    exit;
}

if (empty($or_number)) {
    echo json_encode(['success' => false, 'message' => 'OR Number is required']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid amount greater than 0']);
    exit;
}

if (empty($payment_type)) {
    echo json_encode(['success' => false, 'message' => 'Payment type is required']);
    exit;
}

// Check if OR number already exists
$check = $conn->prepare("SELECT id FROM payments WHERE or_number = ?");
$check->bind_param("s", $or_number);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'OR Number already exists. Please use a unique OR number.']);
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

// Generate reference number
$reference_no = 'PAY-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

// Insert payment - set as verified since recorded by registrar
$stmt = $conn->prepare("
    INSERT INTO payments (reference_no, or_number, student_id, amount, payment_type, description, 
                          academic_year_id, semester, branch_id, recorded_by, payment_method, status, verified_by, verified_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified', ?, NOW())
");
$stmt->bind_param("ssidssisisis", 
    $reference_no, $or_number, $student_id, $amount, $payment_type, $description,
    $current_ay_id, $semester, $branch_id, $registrar_id, $payment_method, $registrar_id
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => "Payment recorded successfully! Reference: $reference_no, OR: $or_number",
        'reference_no' => $reference_no,
        'or_number' => $or_number
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record payment: ' . $stmt->error]);
}
?>
