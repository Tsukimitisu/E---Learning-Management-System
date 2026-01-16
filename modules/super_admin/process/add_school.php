<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$school_name = clean_input($_POST['school_name'] ?? '');

if (empty($school_name)) {
    echo json_encode(['status' => 'error', 'message' => 'School name is required']);
    exit();
}

try {
    $stmt = $conn->prepare("INSERT INTO schools (name) VALUES (?)");
    $stmt->bind_param("s", $school_name);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Created school: $school_name";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'School created successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create school']);
}
?>