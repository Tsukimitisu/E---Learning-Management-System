<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

try {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_date = clean_input($_POST['payment_date'] ?? date('Y-m-d'));

    if ($student_id <= 0 || $amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid payment data']);
        exit();
    }

    $check = $conn->prepare("SELECT user_id FROM students WHERE user_id = ?");
    $check->bind_param("i", $student_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Student not found']);
        exit();
    }

    $proof_file = null;
    if (!empty($_FILES['proof_file']['name'])) {
        $upload_dir = '../../../uploads/payments/';
        $ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
        $filename = 'payment_' . $student_id . '_' . time() . '.' . $ext;
        $target = $upload_dir . $filename;

        if (!move_uploaded_file($_FILES['proof_file']['tmp_name'], $target)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload proof file']);
            exit();
        }
        $proof_file = $filename;
    }

    $stmt = $conn->prepare("INSERT INTO payments (student_id, amount, status, proof_file, created_at) VALUES (?, ?, 'verified', ?, ?)");
    $stmt->bind_param("idss", $student_id, $amount, $proof_file, $payment_date);
    $stmt->execute();

    log_audit($conn, $_SESSION['user_id'], "Added manual payment for student ID {$student_id}");

    echo json_encode(['status' => 'success', 'message' => 'Payment recorded successfully', 'payment_id' => $conn->insert_id]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>