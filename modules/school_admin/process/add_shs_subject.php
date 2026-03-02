<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $subject_code = clean_input($_POST['subject_code']);
    $subject_title = clean_input($_POST['subject_title']);
    
    // SHS uses Hours per Week instead of college-style units
    $hours_per_week = (float)($_POST['hours_per_week'] ?? $_POST['units'] ?? 0);
    if ($hours_per_week <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Hours per Week is required and must be greater than 0']);
        exit();
    }
    $units = $hours_per_week; // Store in units column for DB compatibility
    
    // Accept both modal field names (shs_*) and generic names
    $lecture_hours = (int)($_POST['lecture_hours'] ?? $_POST['shs_lecture_hours'] ?? ($_POST['hours'] ?? 0));
    $lab_hours = (int)($_POST['lab_hours'] ?? $_POST['shs_lab_hours'] ?? 0);
    if ($lecture_hours < 0 || $lab_hours < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Lecture/Lab hours cannot be negative']);
        exit();
    }
    
    $prerequisites = clean_input($_POST['prerequisites'] ?? '');
    $shs_strand_id = (int)($_POST['shs_strand_id'] ?? 0) ?: null;
    $shs_grade_level_id = (int)($_POST['shs_grade_level_id'] ?? 0) ?: null;
    $semester = (int)($_POST['semester'] ?? $_POST['shs_semester'] ?? 0);
    $subject_type = clean_input($_POST['subject_type'] ?? '');
    
    // Validate subject_type is selected and is SHS type
    if (empty($subject_type) || !in_array($subject_type, ['shs_core', 'shs_applied', 'shs_specialized'])) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a valid Subject Type (SHS Core, SHS Applied, or SHS Specialized)']);
        exit();
    }
    
    // Validate Grade Level is required (11 or 12)
    if (!$shs_grade_level_id) {
        echo json_encode(['status' => 'error', 'message' => 'Grade Level is required (Grade 11 or 12)']);
        exit();
    }
    
    // Validate Semester is required (1 or 2)
    if (!in_array($semester, [1, 2])) {
        echo json_encode(['status' => 'error', 'message' => 'Semester is required (1st or 2nd)']);
        exit();
    }
    
    // Strand is required for Specialized subjects
    if ($subject_type === 'shs_specialized' && !$shs_strand_id) {
        echo json_encode(['status' => 'error', 'message' => 'Strand is required for Specialized subjects']);
        exit();
    }
    
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
            shs_strand_id, shs_grade_level_id, semester, prerequisites, is_active, created_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $stmt->bind_param("ssdiisiiisi", $subject_code, $subject_title, $units, $lecture_hours, $lab_hours, $subject_type, $shs_strand_id, $shs_grade_level_id, $semester, $prerequisites, $created_by);
    
    if ($stmt->execute()) {
        $subject_id = $conn->insert_id;
        echo json_encode([
            'status' => 'success',
            'message' => 'SHS subject added successfully',
            'subject_id' => $subject_id
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add SHS subject: ' . $stmt->error]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
