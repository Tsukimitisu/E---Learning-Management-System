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
    $program_id = (int)$_POST['program_id'];
    $year_level_id = (int)$_POST['year_level_id'];
    $semester = (int)$_POST['semester'];

    // Validate the assignment
    $validate = $conn->prepare("
        SELECT id FROM program_year_levels
        WHERE program_id = ? AND id = ? AND is_active = 1
    ");
    $validate->bind_param("ii", $program_id, $year_level_id);
    $validate->execute();

    if ($validate->get_result()->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid program or year level']);
        exit();
    }

    // Update subject assignment
    $stmt = $conn->prepare("
        UPDATE curriculum_subjects
        SET program_id = ?, year_level_id = ?, semester = ?, shs_strand_id = NULL, shs_grade_level_id = NULL
        WHERE id = ?
    ");
    $stmt->bind_param("iiii", $program_id, $year_level_id, $semester, $subject_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Subject assigned to curriculum successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to assign subject']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
