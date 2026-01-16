<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$material_id = (int)($_POST['material_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

if ($material_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid material ID']);
    exit();
}

try {
    // Verify material belongs to teacher's class
    $verify = $conn->prepare("
        SELECT lm.file_path, lm.class_id
        FROM learning_materials lm
        INNER JOIN classes cl ON lm.class_id = cl.id
        WHERE lm.id = ? AND cl.teacher_id = ?
    ");
    $verify->bind_param("ii", $material_id, $teacher_id);
    $verify->execute();
    $result = $verify->get_result();
    
    if ($result->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Material not found or unauthorized']);
        exit();
    }
    
    $material = $result->fetch_assoc();
    $file_path = UPLOAD_DIR . $material['file_path'];
    
    // Delete from database
    $delete_stmt = $conn->prepare("DELETE FROM learning_materials WHERE id = ?");
    $delete_stmt->bind_param("i", $material_id);
    $delete_stmt->execute();
    
    // Delete physical file
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Log audit
    $ip = get_client_ip();
    $action = "Deleted material ID: $material_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $teacher_id, $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Material deleted successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete material']);
}
?>