<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$enable = (int)($_POST['enable'] ?? 0);

try {
    $stmt = $conn->prepare("
        UPDATE system_settings 
        SET setting_value = ?, updated_by = ? 
        WHERE setting_key = 'maintenance_mode'
    ");
    $value = $enable ? '1' : '0';
    $stmt->bind_param("si", $value, $_SESSION['user_id']);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = $enable ? "Enabled maintenance mode" : "Disabled maintenance mode";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    $message = $enable ? 'Maintenance mode enabled' : 'Maintenance mode disabled';
    echo json_encode(['status' => 'success', 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to toggle maintenance mode']);
}
?>