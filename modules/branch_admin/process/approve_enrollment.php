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
    $get_enrollment = $conn->prepare("SELECT class_id FROM enrollments WHERE id = ?");
    $get_enrollment->bind_param("i", $enrollment_id);
    $get_enrollment->execute();
    $enrollment_result = $get_enrollment->get_result()->fetch_assoc();

    if (!$enrollment_result) {
        echo json_encode(['status' => 'error', 'message' => 'Enrollment not found']);
        exit();
    }

    $class_id = $enrollment_result['class_id'];

    // Check class capacity
    $check_capacity = $conn->prepare("SELECT current_enrolled, max_capacity FROM classes WHERE id = ?");
    $check_capacity->bind_param("i", $class_id);
    $check_capacity->execute();
    $capacity_result = $check_capacity->get_result()->fetch_assoc();

    if ($capacity_result['current_enrolled'] >= $capacity_result['max_capacity']) {
        echo json_encode(['status' => 'error', 'message' => 'Class is at full capacity']);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    // Update enrollment status
    $update_enrollment = $conn->prepare("
        UPDATE enrollments
        SET status = 'approved', approved_by = ?, approved_at = NOW()
        WHERE id = ?
    ");
    $update_enrollment->bind_param("ii", $_SESSION['user_id'], $enrollment_id);

    if (!$update_enrollment->execute()) {
        throw new Exception('Failed to approve enrollment');
    }

    // Update class enrollment count
    $update_class = $conn->prepare("
        UPDATE classes
        SET current_enrolled = current_enrolled + 1, updated_at = NOW()
        WHERE id = ?
    ");
    $update_class->bind_param("i", $class_id);

    if (!$update_class->execute()) {
        throw new Exception('Failed to update class capacity');
    }

    // Log the action
    $ip = get_client_ip();
    $action = "Approved enrollment ID $enrollment_id for class ID $class_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Enrollment approved successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>