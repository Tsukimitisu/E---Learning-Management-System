<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $subject_id = (int)($_POST['id'] ?? $_POST['course_id'] ?? 0);
    $subject_code = clean_input($_POST['course_code']);
    $subject_title = clean_input($_POST['course_title']);
    $units = (float)($_POST['units'] ?? 3);
    $lecture_hours = (int)($_POST['lecture_hours'] ?? 0);
    $lab_hours = (int)($_POST['lab_hours'] ?? 0);
    $prerequisites = clean_input($_POST['prerequisites'] ?? '');
    $program_id = (int)($_POST['program_id'] ?? 0) ?: null;
    $year_level_id = (int)($_POST['year_level_id'] ?? 0) ?: null;
    $semester = (int)($_POST['semester'] ?? 1);
    $is_active = (int)($_POST['is_active'] ?? 1);

    $stmt = $conn->prepare("
        UPDATE curriculum_subjects
        SET subject_code = ?, subject_title = ?, units = ?, lecture_hours = ?, lab_hours = ?, 
            prerequisites = ?, program_id = ?, year_level_id = ?, semester = ?, is_active = ?
        WHERE id = ? AND subject_type = 'college'
    ");
    $stmt->bind_param("ssdiiiiiiiii", $subject_code, $subject_title, $units, $lecture_hours, $lab_hours, $prerequisites, $program_id, $year_level_id, $semester, $is_active, $subject_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'College course updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update college course']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
