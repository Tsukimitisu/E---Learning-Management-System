<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$payment_id = (int)($data['payment_id'] ?? 0);
$action = clean_input($data['action'] ?? '');
$reason = clean_input($data['reason'] ?? '');

if ($payment_id <= 0 || !in_array($action, ['verify', 'reject'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    exit();
}

try {
    $conn->begin_transaction();

    $lock = $conn->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
    $lock->bind_param("i", $payment_id);
    $lock->execute();
    $payment = $lock->get_result()->fetch_assoc();

    if (!$payment) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Payment not found']);
        exit();
    }

    if ($action === 'verify') {
        $update = $conn->prepare("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = NOW(), rejection_reason = NULL WHERE id = ?");
        $update->bind_param("ii", $_SESSION['user_id'], $payment_id);
        $update->execute();
    } else {
        $update = $conn->prepare("UPDATE payments SET status = 'rejected', verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE id = ?");
        $update->bind_param("isi", $_SESSION['user_id'], $reason, $payment_id);
        $update->execute();
    }

    $eligibility = null;
    if ($action === 'verify') {
        $pendingEnroll = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND status = 'pending'");
        $pendingEnroll->bind_param("i", $payment['student_id']);
        $pendingEnroll->execute();
        $eligibility = $pendingEnroll->get_result()->fetch_assoc()['count'] ?? 0;
    }

    $studentInfo = $conn->query("SELECT CONCAT(first_name, ' ', last_name) as name FROM user_profiles WHERE user_id = {$payment['student_id']}")->fetch_assoc();
    $studentName = $studentInfo['name'] ?? 'Student';

    $log_action = $action === 'verify'
        ? "Verified payment ID {$payment_id} for student {$studentName}"
        : "Rejected payment ID {$payment_id} for student {$studentName}";

    log_audit($conn, $_SESSION['user_id'], $log_action);

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => $action === 'verify' ? 'Payment verified successfully' : 'Payment rejected successfully',
        'payment_status' => $action === 'verify' ? 'verified' : 'rejected',
        'pending_enrollments' => $eligibility
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>