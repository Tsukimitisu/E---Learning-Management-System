<?php
/**
 * Update Global Security Settings - affects all users
 * Settings: registration_enabled, max_login_attempts, password_min_length, session_timeout, maintenance_mode
 * Concurrency: Uses transactions and proper timestamp handling
 */

require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$setting_key = clean_input($_POST['setting_key'] ?? '');
$setting_value = clean_input($_POST['setting_value'] ?? '');

// Validate setting key
$allowed_settings = [
    'registration_enabled',
    'max_login_attempts',
    'lockout_duration',
    'password_min_length',
    'password_require_uppercase',
    'password_require_lowercase',
    'password_require_number',
    'password_require_special',
    'session_timeout',
    'maintenance_mode'
];

if (!in_array($setting_key, $allowed_settings)) {
    echo json_encode(['success' => false, 'message' => 'Invalid setting key']);
    exit();
}

// Validate values based on type
switch ($setting_key) {
    case 'registration_enabled':
    case 'password_require_uppercase':
    case 'password_require_lowercase':
    case 'password_require_number':
    case 'password_require_special':
    case 'maintenance_mode':
        if (!in_array($setting_value, ['0', '1'])) {
            echo json_encode(['success' => false, 'message' => 'Boolean setting must be 0 or 1']);
            exit();
        }
        break;
    case 'max_login_attempts':
    case 'lockout_duration':
    case 'password_min_length':
    case 'session_timeout':
        if (!is_numeric($setting_value) || $setting_value < 1) {
            echo json_encode(['success' => false, 'message' => 'Value must be a positive number']);
            exit();
        }
        break;
}

try {
    $conn->begin_transaction();
    
    // Update security_settings table (affects new logins/sessions)
    $stmt = $conn->prepare("
        UPDATE security_settings 
        SET setting_value = ?, updated_at = NOW(), updated_by = ? 
        WHERE setting_key = ?
    ");
    $stmt->bind_param("sis", $setting_value, $_SESSION['user_id'], $setting_key);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        // Insert if doesn't exist
        $insert_stmt = $conn->prepare("
            INSERT INTO security_settings (setting_key, setting_value, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $insert_stmt->bind_param("ssi", $setting_key, $setting_value, $_SESSION['user_id']);
        $insert_stmt->execute();
    }
    
    // Log audit trail
    $ip = get_client_ip();
    $action = "Updated global security setting: $setting_key = " . (strlen($setting_value) > 50 ? substr($setting_value, 0, 50) . '...' : $setting_value);
    $audit_stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, ip_address, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $audit_stmt->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit_stmt->execute();
    
    // If maintenance mode is being toggled, log security event
    if ($setting_key === 'maintenance_mode') {
        $event_type = 'maintenance_mode_' . ($setting_value == '1' ? 'enabled' : 'disabled');
        $event_details = "Maintenance mode " . ($setting_value == '1' ? 'enabled' : 'disabled') . " by Super Admin";
        $sec_stmt = $conn->prepare("
            INSERT INTO security_logs (user_id, event_type, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $sec_stmt->bind_param("isss", $_SESSION['user_id'], $event_type, $event_details, $ip);
        $sec_stmt->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Setting updated and applied to all users',
        'setting_key' => $setting_key,
        'setting_value' => $setting_value
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to update setting: ' . $e->getMessage()]);
}

?>
