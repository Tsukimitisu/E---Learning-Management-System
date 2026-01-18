<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $strand_id = (int)$_POST['strand_id'];
    $strand_name = clean_input($_POST['strand_name']);
    $description = clean_input($_POST['description'] ?? '');
    $is_active = (int)$_POST['is_active'];

    $stmt = $conn->prepare("
        UPDATE shs_strands
        SET strand_name = ?, description = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssii", $strand_name, $description, $is_active, $strand_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Strand updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update strand']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
