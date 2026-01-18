<?php
require_once '../config/init.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_STUDENT) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'];
$last_check = (int)($_POST['last_check'] ?? 0);

if ($last_check == 0) {
    $last_check = time() - 3600; // Default to 1 hour ago
}

$last_check_datetime = date('Y-m-d H:i:s', $last_check);

try {
    // Check for new grades (grades that exist and have values)
    $new_grades_query = "
        SELECT COUNT(DISTINCT g.class_id) as count
        FROM grades g
        INNER JOIN enrollments e ON g.class_id = e.class_id AND g.student_id = e.student_id
        WHERE g.student_id = ?
        AND e.status = 'approved'
        AND g.final_grade > 0
    ";
    
    $stmt = $conn->prepare($new_grades_query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $new_grades = $stmt->get_result()->fetch_assoc()['count'];
    
    // Check for new learning materials uploaded after last check
    $new_materials_query = "
        SELECT COUNT(*) as count
        FROM learning_materials lm
        INNER JOIN enrollments e ON lm.class_id = e.class_id
        WHERE e.student_id = ?
        AND e.status = 'approved'
        AND lm.uploaded_at > ?
    ";
    
    $stmt = $conn->prepare($new_materials_query);
    $stmt->bind_param("is", $student_id, $last_check_datetime);
    $stmt->execute();
    $new_materials = $stmt->get_result()->fetch_assoc()['count'];
    
    echo json_encode([
        'status' => 'success',
        'new_grades' => (int)$new_grades,
        'new_materials' => (int)$new_materials,
        'current_time' => time(),
        'last_check' => $last_check
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to check updates',
        'new_grades' => 0,
        'new_materials' => 0,
        'current_time' => time()
    ]);
}
?>