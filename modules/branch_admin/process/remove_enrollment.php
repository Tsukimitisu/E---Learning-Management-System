<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$enrollment_id = (int)($data['enrollment_id'] ?? 0);

if (empty($enrollment_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Enrollment ID required']);
    exit();
}

try {
    // Get enrollment details
    $get_enrollment = $conn->prepare("SELECT class_id, status FROM enrollments WHERE id = ?");
    $get_enrollment->bind_param("i", $enrollment_id);
    $get_enrollment->execute();
    $enrollment_result = $get_enrollment->get_result()->fetch_assoc();

    if (!$enrollment_result) {
        echo json_encode(['status' => 'error', 'message' => 'Enrollment not found']);
        exit();
    }

    $class_id = $enrollment_result['class_id'];
    $status = $enrollment_result['status'];

    // Start transaction
    $conn->begin_transaction();

    // Delete enrollment
    $delete_enrollment = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
    $delete_enrollment->bind_param("i", $enrollment_id);

    if (!$delete_enrollment->execute()) {
        throw new Exception('Failed to remove enrollment');
    }

    // If enrollment was approved, decrement class count
    if ($status === 'approved') {
        $update_class = $conn->prepare("
            UPDATE classes
            SET current_enrolled = GREATEST(current_enrolled - 1, 0), updated_at = NOW()
            WHERE id = ?
        ");
        $update_class->bind_param("i", $class_id);

        if (!$update_class->execute()) {
            throw new Exception('Failed to update class capacity');
        }
    }

    // Log the action
    $ip = get_client_ip();
    $action = "Removed enrollment ID $enrollment_id from class ID $class_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Enrollment removed successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>