<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$setting_key = clean_input($_POST['setting_key'] ?? '');
$setting_value = clean_input($_POST['setting_value'] ?? '');

if (empty($setting_key)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid setting key']);
    exit();
}

try {
    $stmt = $conn->prepare("
        UPDATE system_settings 
        SET setting_value = ?, updated_by = ? 
        WHERE setting_key = ?
    ");
    $stmt->bind_param("sis", $setting_value, $_SESSION['user_id'], $setting_key);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Updated system setting: $setting_key = $setting_value";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Setting updated successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update setting']);
}
?>