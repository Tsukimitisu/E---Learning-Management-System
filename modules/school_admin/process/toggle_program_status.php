<?php
require_once '../../../config/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$program_id = (int)($payload['program_id'] ?? 0);
$new_status = isset($payload['is_active']) ? (int)$payload['is_active'] : null;

if ($program_id <= 0 || ($new_status !== 0 && $new_status !== 1)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit();
}

try {
    $conn->begin_transaction();

    // Lock the program row for update to avoid race conditions
    $sel = $conn->prepare('SELECT id, is_active, program_name FROM programs WHERE id = ? FOR UPDATE');
    $sel->bind_param('i', $program_id);
    $sel->execute();
    $res = $sel->get_result();
    if ($res->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Program not found']);
        exit();
    }
    $row = $res->fetch_assoc();

    $upd = $conn->prepare('UPDATE programs SET is_active = ?, updated_at = NOW() WHERE id = ?');
    $upd->bind_param('ii', $new_status, $program_id);
    $upd->execute();

    // Audit log (use helper)
    $ip = get_client_ip();
    $action = ($new_status ? 'Activated' : 'Deactivated') . " program: " . ($row['program_name'] ?? $program_id);
    try { log_audit($conn, $_SESSION['user_id'], $action, null, $ip); } catch (Exception $e) { }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Program status updated']);
} catch (Exception $e) {
    if ($conn->errno) $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
