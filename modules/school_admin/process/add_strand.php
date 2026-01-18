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
    $strand_name = clean_input($_POST['strand_name']);
    $strand_code = clean_input($_POST['strand_code'] ?? '');
    $description = clean_input($_POST['description'] ?? '');

    $stmt = $conn->prepare("
        INSERT INTO shs_strands (track_id, strand_name, strand_code, description, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("isss", $track_id, $strand_name, $strand_code, $description);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Strand added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add strand']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
