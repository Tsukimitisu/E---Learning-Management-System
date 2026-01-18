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
    $subject_id = (int)($data['id'] ?? $data['course_id'] ?? 0);

    if ($subject_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid course ID']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM curriculum_subjects WHERE id = ? AND subject_type = 'college'");
    $stmt->bind_param("i", $subject_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'College course deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete college course']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
