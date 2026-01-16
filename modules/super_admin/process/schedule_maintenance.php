<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$title = clean_input($_POST['title'] ?? '');
$description = clean_input($_POST['description'] ?? '');
$start_time = clean_input($_POST['start_time'] ?? '');
$end_time = clean_input($_POST['end_time'] ?? '');

if (empty($title) || empty($start_time) || empty($end_time)) {
    echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO system_maintenance (title, description, start_time, end_time, created_by) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssi", $title, $description, $start_time, $end_time, $_SESSION['user_id']);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Scheduled maintenance: $title";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Maintenance scheduled successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to schedule maintenance']);
}
?>