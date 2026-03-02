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
    
    // SHS uses Hours per Week instead of college units - accept both field names
    $units = (float)($_POST['hours_per_week'] ?? $_POST['units'] ?? 0);
    
    $lecture_hours = (int)($_POST['hours'] ?? $_POST['lecture_hours'] ?? $_POST['college_lecture_hours'] ?? 0);
    $lab_hours = (int)($_POST['lab_hours'] ?? $_POST['college_lab_hours'] ?? 0);
    $subject_type = clean_input($_POST['category'] ?? $_POST['subject_type'] ?? 'shs_core');
    $prerequisites = clean_input($_POST['prerequisites'] ?? '');
    $is_active = (int)$_POST['is_active'];
    $program_id = isset($_POST['program_id']) && $_POST['program_id'] !== '' ? (int)$_POST['program_id'] : null;
    $year_level_id = isset($_POST['year_level_id']) && $_POST['year_level_id'] !== '' ? (int)$_POST['year_level_id'] : null;
    $semester = isset($_POST['semester']) && $_POST['semester'] !== '' ? (int)$_POST['semester'] : (isset($_POST['college_semester']) ? (int)$_POST['college_semester'] : 1);
    
    // SHS-specific fields
    $shs_strand_id = isset($_POST['shs_strand_id']) && $_POST['shs_strand_id'] !== '' ? (int)$_POST['shs_strand_id'] : null;
    $shs_grade_level_id = isset($_POST['shs_grade_level_id']) && $_POST['shs_grade_level_id'] !== '' ? (int)$_POST['shs_grade_level_id'] : null;

    // Validate SHS-specific rules
    if (in_array($subject_type, ['shs_core', 'shs_applied', 'shs_specialized'])) {
        if ($units <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Hours per Week is required and must be greater than 0']);
            exit();
        }
        if ($lecture_hours < 0 || $lab_hours < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Lecture/Lab hours cannot be negative']);
            exit();
        }
        if (!$shs_grade_level_id) {
            echo json_encode(['status' => 'error', 'message' => 'Grade Level is required (Grade 11 or 12)']);
            exit();
        }
        if (!in_array($semester, [1, 2])) {
            echo json_encode(['status' => 'error', 'message' => 'Semester is required (1st or 2nd)']);
            exit();
        }
        if ($subject_type === 'shs_specialized' && !$shs_strand_id) {
            echo json_encode(['status' => 'error', 'message' => 'Strand is required for Specialized subjects']);
            exit();
        }
    }

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
