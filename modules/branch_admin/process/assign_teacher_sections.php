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
$class_ids = $data['class_ids'] ?? [];

if ($teacher_id <= 0 || !is_array($class_ids) || count($class_ids) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    exit();
}

$class_ids = array_values(array_filter(array_map('intval', $class_ids)));
if (count($class_ids) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'No valid sections selected']);
    exit();
}

$branch_id = 1; // In production, fetch from user's assigned branch

try {
    // Validate teacher
    $teacher_check = $conn->prepare("SELECT u.id FROM users u INNER JOIN user_roles ur ON u.id = ur.user_id WHERE u.id = ? AND ur.role_id = ? AND u.status = 'active'");
    $teacher_check->bind_param("ii", $teacher_id, ROLE_TEACHER);
    $teacher_check->execute();
    if ($teacher_check->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Teacher not found or inactive']);
        exit();
    }

    // Validate class ownership
    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
    $types = str_repeat('i', count($class_ids) + 1);
    $query = "SELECT id FROM classes WHERE branch_id = ? AND id IN ($placeholders)";
    $stmt = $conn->prepare($query);
    $params = array_merge([$branch_id], $class_ids);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $valid_ids = [];
    while ($row = $result->fetch_assoc()) {
        $valid_ids[] = (int)$row['id'];
    }

    if (count($valid_ids) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No valid sections found for this branch']);
        exit();
    }

    $conn->begin_transaction();

    $update_placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
    $update_types = 'i' . str_repeat('i', count($valid_ids));
    $update_query = "UPDATE classes SET teacher_id = ? WHERE id IN ($update_placeholders)";
    $update_stmt = $conn->prepare($update_query);
    $update_params = array_merge([$teacher_id], $valid_ids);
    $update_stmt->bind_param($update_types, ...$update_params);
    $update_stmt->execute();

    // Audit log
    $ip = get_client_ip();
    $action = "Assigned teacher ID $teacher_id to sections: " . implode(',', $valid_ids);
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'Teacher assigned to selected sections successfully']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>