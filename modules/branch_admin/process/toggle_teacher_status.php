<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$teacher_id = (int)($data['teacher_id'] ?? 0);
$status = clean_input($data['status'] ?? '');

if (empty($teacher_id) || !in_array($status, ['active', 'inactive'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    exit();
}

try {
    // Update teacher status
    $update = $conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
    $update->bind_param("si", $status, $teacher_id);

    if (!$update->execute()) {
        throw new Exception('Failed to update teacher status');
    }

    // Log the action
    $ip = get_client_ip();
    $action = "Changed teacher ID $teacher_id status to $status";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Teacher status updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>