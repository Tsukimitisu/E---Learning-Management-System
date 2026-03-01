<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    // DEBUG: Output incoming POST data in response for troubleshooting
    $debug_post = $_POST;
    $subject_id = (int)$_POST['subject_id'];
    $subject_code = clean_input($_POST['subject_code']);
    $subject_title = clean_input($_POST['subject_title']);
    $units = (float)$_POST['units'];
    $lecture_hours = (int)($_POST['hours'] ?? $_POST['lecture_hours'] ?? $_POST['college_lecture_hours'] ?? 0);
    $lab_hours = (int)($_POST['lab_hours'] ?? $_POST['college_lab_hours'] ?? 0);
    $subject_type = clean_input($_POST['category'] ?? $_POST['subject_type'] ?? 'college');
    $prerequisites = clean_input($_POST['prerequisites'] ?? '');
    $is_active = (int)$_POST['is_active'];
    $program_id = isset($_POST['program_id']) && $_POST['program_id'] !== '' ? (int)$_POST['program_id'] : null;
    $year_level_id = isset($_POST['year_level_id']) && $_POST['year_level_id'] !== '' ? (int)$_POST['year_level_id'] : null;
    $semester = isset($_POST['semester']) && $_POST['semester'] !== '' ? (int)$_POST['semester'] : (isset($_POST['college_semester']) ? (int)$_POST['college_semester'] : 1);
    
    // SHS-specific fields
    $shs_strand_id = isset($_POST['shs_strand_id']) && $_POST['shs_strand_id'] !== '' ? (int)$_POST['shs_strand_id'] : null;
    $shs_grade_level_id = isset($_POST['shs_grade_level_id']) && $_POST['shs_grade_level_id'] !== '' ? (int)$_POST['shs_grade_level_id'] : null;

    // Check if subject code conflicts with another subject
    $check_code = $conn->prepare("SELECT id FROM curriculum_subjects WHERE subject_code = ? AND id != ?");
    $check_code->bind_param("si", $subject_code, $subject_id);
    $check_code->execute();

    if ($check_code->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Subject code already exists']);
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE curriculum_subjects
        SET subject_code = ?, subject_title = ?, units = ?, lecture_hours = ?, lab_hours = ?, 
            subject_type = ?, prerequisites = ?, is_active = ?, program_id = ?, year_level_id = ?, 
            semester = ?, shs_strand_id = ?, shs_grade_level_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssdiissiiiiiii", 
        $subject_code, $subject_title, $units, $lecture_hours, $lab_hours, 
        $subject_type, $prerequisites, $is_active, $program_id, $year_level_id, 
        $semester, $shs_strand_id, $shs_grade_level_id, $subject_id
    );
    
    if ($stmt->execute()) {
        send_realtime_update('curriculum_updated', [
            'action' => 'subject_updated',
            'subject_id' => $subject_id,
            'subject_code' => $subject_code,
            'program_id' => $program_id,
            'updated_by' => $_SESSION['user_id']
        ], 'school_admin');
        echo json_encode(['status' => 'success', 'message' => 'Subject updated successfully', 'debug_post' => $debug_post]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update subject',
            'sql_error' => $stmt->error,
            'debug_post' => $debug_post
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
