<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$class_id = (int)($data['class_id'] ?? 0);
$grading_period = clean_input($data['grading_period'] ?? '');
$action = clean_input($data['action'] ?? '');

$allowed_periods = ['prelim', 'midterm', 'final', 'quarterly'];
$allowed_actions = ['lock', 'unlock'];

if ($class_id <= 0 || !in_array($grading_period, $allowed_periods, true) || !in_array($action, $allowed_actions, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    exit();
}

$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied: Branch assignment required']);
    exit();
}

try {
    // Ensure class belongs to this branch
    $check = $conn->prepare("SELECT id FROM classes WHERE id = ? AND branch_id = ?");
    $check->bind_param("ii", $class_id, $branch_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied: This resource belongs to a different branch']);
        exit();
    }

    if ($action === 'lock') {
        $update = $conn->prepare("UPDATE grade_locks SET is_locked = 1, locked_by = ?, locked_at = NOW(), unlock_request = 0, unlock_reason = NULL WHERE class_id = ? AND grading_period = ?");
        $update->bind_param("iis", $_SESSION['user_id'], $class_id, $grading_period);
        $update->execute();

        if ($update->affected_rows === 0) {
            $insert = $conn->prepare("INSERT INTO grade_locks (class_id, grading_period, is_locked, locked_by, locked_at, unlock_request, unlock_reason) VALUES (?, ?, 1, ?, NOW(), 0, NULL)");
            $insert->bind_param("isi", $class_id, $grading_period, $_SESSION['user_id']);
            $insert->execute();
        }
    } else {
        $update = $conn->prepare("UPDATE grade_locks SET is_locked = 0, locked_by = NULL, locked_at = NULL, unlock_request = 0, unlock_reason = NULL WHERE class_id = ? AND grading_period = ?");
        $update->bind_param("is", $class_id, $grading_period);
        $update->execute();

        if ($update->affected_rows === 0) {
            $insert = $conn->prepare("INSERT INTO grade_locks (class_id, grading_period, is_locked, locked_by, locked_at, unlock_request, unlock_reason) VALUES (?, ?, 0, NULL, NULL, 0, NULL)");
            $insert->bind_param("is", $class_id, $grading_period);
            $insert->execute();
        }
    }

    // Audit log
    $ip = get_client_ip();
    $action_text = ($action === 'lock') ? 'Locked' : 'Unlocked';
    $log_action = "$action_text $grading_period records for class ID $class_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $log_action, $ip);
    $audit->execute();

    echo json_encode(['status' => 'success', 'message' => "{$action_text} {$grading_period} records successfully"]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>