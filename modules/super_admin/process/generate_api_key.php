<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$key_name = clean_input($_POST['key_name'] ?? '');
$service_name = clean_input($_POST['service_name'] ?? '');
$expires_at = !empty($_POST['expires_at']) ? clean_input($_POST['expires_at']) : NULL;

if (empty($key_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Key name is required']);
    exit();
}

try {
    // Generate API key and secret
    $api_key = bin2hex(random_bytes(32));
    $api_secret = bin2hex(random_bytes(64));
    
    $stmt = $conn->prepare("
        INSERT INTO api_keys (key_name, api_key, api_secret, service_name, expires_at, created_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssi", $key_name, $api_key, $api_secret, $service_name, $expires_at, $_SESSION['user_id']);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Generated API key: $key_name";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'API key generated successfully',
        'api_key' => $api_key
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate API key']);
}
?>