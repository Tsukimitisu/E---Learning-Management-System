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
    
    // If row didn't exist, insert it
    if ($stmt->affected_rows === 0) {
        $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value, updated_by) VALUES ('maintenance_mode', '$value', {$_SESSION['user_id']})");
    }
    
    $ip = get_client_ip();
    $action = $enable ? "Enabled maintenance mode" : "Disabled maintenance mode";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    // Broadcast maintenance mode via realtime to ALL users
    if ($enable) {
        send_realtime_update('maintenance_mode', [
            'enabled' => true,
            'message' => 'The system is entering maintenance mode. All users will be logged out.',
            'timestamp' => time()
        ]);
        
        // Force destroy all non-super-admin sessions from active_sessions
        $conn->query("DELETE FROM active_sessions WHERE user_id IN (
            SELECT id FROM users WHERE role_id != " . ROLE_SUPER_ADMIN . "
        )");
    } else {
        send_realtime_update('maintenance_mode', [
            'enabled' => false,
            'message' => 'System is back online.',
            'timestamp' => time()
        ]);
    }
    
    $message = $enable ? 'Maintenance mode enabled. All non-admin users have been notified and will be logged out.' : 'Maintenance mode disabled. System is now live.';
    echo json_encode(['status' => 'success', 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to toggle maintenance mode']);
}
?>