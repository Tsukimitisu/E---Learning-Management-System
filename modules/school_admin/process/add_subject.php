<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

try {
    $subject_code = clean_input($_POST['subject_code']);
    $subject_title = clean_input($_POST['subject_title']);
    $units = (float)($_POST['units'] ?? 0);
    $subject_type = clean_input($_POST['subject_type'] ?? 'college');
    
    // Consolidate lecture and lab hours from SHS or College fields
    $lecture_hours = (int)($_POST['shs_lecture_hours'] ?? $_POST['college_lecture_hours'] ?? 0);
    $lab_hours = (int)($_POST['shs_lab_hours'] ?? $_POST['college_lab_hours'] ?? 0);
    
    // Consolidate semester field
    $semester = (int)($_POST['shs_semester'] ?? $_POST['college_semester'] ?? 1);
    
    $prerequisites = clean_input($_POST['prerequisites'] ?? '');
    $program_id = (int)($_POST['program_id'] ?? 0) ?: null;
    $year_level_id = (int)($_POST['year_level_id'] ?? 0) ?: null;
    $shs_strand_id = (int)($_POST['shs_strand_id'] ?? 0) ?: null;
    $shs_grade_level_id = (int)($_POST['shs_grade_level_id'] ?? 0) ?: null;
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
            subject_code, subject_title, units, lecture_hours, lab_hours, subject_type,
            program_id, year_level_id, shs_strand_id, shs_grade_level_id, semester,
            prerequisites, is_active, created_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $stmt->bind_param(
        "ssdiisiiiiisi",
        $subject_code,
        $subject_title,
        $units,
        $lecture_hours,
        $lab_hours,
        $subject_type,
        $program_id,
        $year_level_id,
        $shs_strand_id,
        $shs_grade_level_id,
        $semester,
        $prerequisites,
        $created_by
    );
    
    if ($stmt->execute()) {
        $subject_id = $conn->insert_id;
        echo json_encode([
            'status' => 'success',
            'message' => 'Subject added successfully',
            'subject_id' => $subject_id
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add subject: ' . $stmt->error]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>