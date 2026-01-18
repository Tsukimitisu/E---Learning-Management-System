<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $subject_id = (int)($_GET['id'] ?? $_GET['code'] ?? 0);

    $stmt = $conn->prepare("
        SELECT id, subject_code as course_code, subject_title as course_title, units, 
               lecture_hours, lab_hours, prerequisites, program_id, year_level_id, 
               semester, is_active
        FROM curriculum_subjects
        WHERE id = ? AND subject_type = 'college' AND is_active = 1
    ");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Course not found']);
        exit();
    }

    $course = $result->fetch_assoc();
    echo json_encode(['status' => 'success', 'course' => $course]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
