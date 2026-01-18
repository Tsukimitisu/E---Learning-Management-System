<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$admin_id = (int)($data['admin_id'] ?? 0);

if ($admin_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid administrator ID']);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();

    // Log action
    $ip = get_client_ip();
    $action = "Deactivated branch administrator ID: $admin_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    echo json_encode(['status' => 'success', 'message' => 'Administrator deactivated successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error deactivating administrator']);
}
?>
