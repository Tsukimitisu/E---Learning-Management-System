<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $subject_code = clean_input($_POST['course_code']);
    $subject_title = clean_input($_POST['course_title']);
    $units = (float)($_POST['units'] ?? 3);
    $lecture_hours = (int)($_POST['lecture_hours'] ?? 0);
    $lab_hours = (int)($_POST['lab_hours'] ?? 0);
    $prerequisites = clean_input($_POST['prerequisites'] ?? '');
    $program_id = (int)($_POST['program_id'] ?? 0) ?: null;
    $year_level_id = (int)($_POST['year_level_id'] ?? 0) ?: null;
    $semester = (int)($_POST['semester'] ?? 1);
    $created_by = (int)$_SESSION['user_id'];

    // Check for duplicate subject code
    $check_duplicate = $conn->prepare("SELECT id FROM curriculum_subjects WHERE subject_code = ?");
    $check_duplicate->bind_param("s", $subject_code);
    $check_duplicate->execute();
    
    if ($check_duplicate->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Subject code already exists']);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO curriculum_subjects (
            subject_code, subject_title, units, lecture_hours, lab_hours, 
            subject_type, program_id, year_level_id, semester, 
            prerequisites, is_active, created_by
        )
        VALUES (?, ?, ?, ?, ?, 'college', ?, ?, ?, ?, 1, ?)
    ");
    $stmt->bind_param("ssdiiiiisi", $subject_code, $subject_title, $units, $lecture_hours, $lab_hours, $program_id, $year_level_id, $semester, $prerequisites, $created_by);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'College course added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add college course']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
