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
    $strand_id = (int)$data['strand_id'];

    // Check if strand has associated subjects
    $check_subjects = $conn->prepare("SELECT COUNT(*) as count FROM curriculum_subjects WHERE shs_strand_id = ?");
    $check_subjects->bind_param("i", $strand_id);
    $check_subjects->execute();
    $subject_count = $check_subjects->get_result()->fetch_assoc()['count'];

    if ($subject_count > 0) {
        echo json_encode(['status' => 'error', 'message' => "Cannot delete strand with $subject_count associated subjects. Delete subjects first."]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM shs_strands WHERE id = ?");
    $stmt->bind_param("i", $strand_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Strand deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete strand']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
