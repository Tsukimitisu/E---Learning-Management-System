<?php
require_once '../../../config/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in as registrar
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$student_id) {
    echo json_encode(['status' => 'error', 'message' => 'Student ID is required']);
    exit;
}

// Get current academic year
$ay_query = "SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1";
$ay_result = $conn->query($ay_query);
$current_ay = $ay_result->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get student enrollments for current academic year
$query = "SELECT 
            e.id as enrollment_id,
            e.class_id,
            e.status as enrollment_status,
            cl.section_name,
            cl.schedule,
            cl.room,
            COALESCE(cs.subject_code, c.course_code, 'N/A') as subject_code,
            COALESCE(cs.subject_title, c.course_title, 'N/A') as subject_title,
            CONCAT(tp.first_name, ' ', tp.last_name) as teacher_name
          FROM enrollments e
          JOIN classes cl ON e.class_id = cl.id
          LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
          LEFT JOIN courses c ON cl.course_id = c.id
          LEFT JOIN users t ON cl.teacher_id = t.id
          LEFT JOIN user_profiles tp ON t.id = tp.user_id
          WHERE e.student_id = ? 
          AND cl.academic_year_id = ?
          AND e.status IN ('enrolled', 'pending')
          ORDER BY subject_code";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student_id, $current_ay_id);
$stmt->execute();
$result = $stmt->get_result();

$enrollments = [];
while ($row = $result->fetch_assoc()) {
    $enrollments[] = $row;
}

echo json_encode([
    'status' => 'success',
    'enrollments' => $enrollments
]);
