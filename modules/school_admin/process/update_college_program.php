<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $program_id = (int)$_POST['program_id'];
    $program_code = clean_input($_POST['program_code']);
    $program_name = clean_input($_POST['program_name']);
    $degree_level = clean_input($_POST['degree_level']);
    $is_active = (int)($_POST['is_active'] ?? 1);

    $stmt = $conn->prepare("
        UPDATE programs
        SET program_code = ?, program_name = ?, degree_level = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssii", $program_code, $program_name, $degree_level, $is_active, $program_id);
    
    if ($stmt->execute()) {
        $ip = get_client_ip();
        $action = "Updated program: $program_code - $program_name";
        $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
        $audit->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Program updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update program']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
