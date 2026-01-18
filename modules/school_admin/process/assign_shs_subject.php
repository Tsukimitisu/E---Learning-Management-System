<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $subject_id = (int)$_POST['subject_id'];
    $shs_strand_id = (int)$_POST['shs_strand_id'];
    $shs_grade_level_id = (int)$_POST['shs_grade_level_id'];
    $semester = (int)$_POST['semester'];
    $subject_type = clean_input($_POST['subject_type']);

    // Validate the assignment
    $validate = $conn->prepare("
        SELECT id FROM shs_grade_levels
        WHERE id = ? AND strand_id = ? AND is_active = 1
    ");
    $validate->bind_param("ii", $shs_grade_level_id, $shs_strand_id);
    $validate->execute();

    if ($validate->get_result()->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid strand or grade level']);
        exit();
    }

    // Update subject assignment
    $stmt = $conn->prepare("
        UPDATE curriculum_subjects
        SET shs_strand_id = ?, shs_grade_level_id = ?, semester = ?, subject_type = ?, program_id = NULL, year_level_id = NULL
        WHERE id = ?
    ");
    $stmt->bind_param("iissi", $shs_strand_id, $shs_grade_level_id, $semester, $subject_type, $subject_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Subject assigned to SHS curriculum successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to assign subject']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
