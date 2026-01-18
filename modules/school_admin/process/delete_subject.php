<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $subject_id = (int)$data['subject_id'];

    $stmt = $conn->prepare("DELETE FROM curriculum_subjects WHERE id = ?");
    $stmt->bind_param("i", $subject_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Subject deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete subject']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
