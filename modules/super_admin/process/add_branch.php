<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$school_id = (int)($_POST['school_id'] ?? 0);
$branch_name = clean_input($_POST['branch_name'] ?? '');
$address = clean_input($_POST['address'] ?? '');

if ($school_id == 0 || empty($branch_name)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

try {
    $stmt = $conn->prepare("INSERT INTO branches (school_id, name, address) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $school_id, $branch_name, $address);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Created branch: $branch_name";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Branch created successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create branch']);
}
?>