<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $track_id = (int)$_POST['track_id'];
    $track_name = clean_input($_POST['track_name']);
    $description = clean_input($_POST['description'] ?? '');
    $is_active = (int)$_POST['is_active'];

    $stmt = $conn->prepare("
        UPDATE shs_tracks
        SET track_name = ?, description = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssii", $track_name, $description, $is_active, $track_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Track updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update track']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
