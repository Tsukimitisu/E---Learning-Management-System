<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $course_id = clean_input($_POST['course_id'] ?? '');
    $program_id = (int)($_POST['program_id'] ?? 0);
    $year_level_id = (int)($_POST['year_level_id'] ?? 0);
    $semester = (int)($_POST['semester'] ?? 0);

    if (!$course_id || !$program_id || !$year_level_id || !$semester) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit();
    }

    // Validate program/year level pairing exists and active
    $validate = $conn->prepare("SELECT id FROM program_year_levels WHERE id = ? AND program_id = ? AND is_active = 1");
    $validate->bind_param('ii', $year_level_id, $program_id);
    $validate->execute();
    if ($validate->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid program/year level']);
        exit();
    }

    // Persist assignment in program_courses mapping table
    $stmt = $conn->prepare(""
        . "INSERT INTO program_courses (program_id, year_level_id, semester, course_code, is_active) "
        . "VALUES (?, ?, ?, ?, 1) "
        . "ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)"
    );
    $stmt->bind_param('iiis', $program_id, $year_level_id, $semester, $course_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Course assigned successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to assign course']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
