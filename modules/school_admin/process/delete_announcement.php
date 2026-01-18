<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$announcement_id = (int)($data['announcement_id'] ?? 0);

if ($announcement_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid announcement ID']);
    exit();
}

try {
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $announcement_id);
    $stmt->execute();

    // Log action
    $ip = get_client_ip();
    $action = "Deleted announcement ID: $announcement_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    echo json_encode(['status' => 'success', 'message' => 'Announcement deleted successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error deleting announcement']);
}
?>
