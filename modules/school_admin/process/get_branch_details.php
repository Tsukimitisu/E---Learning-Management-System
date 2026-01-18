<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$branch_id = (int)($_GET['branch_id'] ?? 0);

if ($branch_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid branch ID']);
    exit();
}

try {
    // Get branch info
    $branch_query = $conn->prepare("SELECT * FROM branches WHERE id = ?");
    $branch_query->bind_param("i", $branch_id);
    $branch_query->execute();
    $branch = $branch_query->get_result()->fetch_assoc();

    if (!$branch) {
        echo json_encode(['status' => 'error', 'message' => 'Branch not found']);
        exit();
    }

    // Get current academic year
    $current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
    $current_ay_id = $current_ay['id'] ?? 0;

    // Get students list (from section_students in this branch)
    $students_query = $conn->prepare("
        SELECT DISTINCT u.id, CONCAT(up.first_name, ' ', up.last_name) as name, u.email
        FROM section_students ss
        INNER JOIN sections s ON ss.section_id = s.id
        INNER JOIN users u ON ss.student_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        WHERE s.branch_id = ? AND s.academic_year_id = ? AND ss.status = 'active'
        ORDER BY up.last_name, up.first_name
        LIMIT 50
    ");
    $students_query->bind_param("ii", $branch_id, $current_ay_id);
    $students_query->execute();
    $students_result = $students_query->get_result();
    $students = [];
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
    $branch['students'] = $students;
    $branch['student_count'] = count($students);

    // Get teachers list (from teacher_subject_assignments in this branch)
    $teachers_query = $conn->prepare("
        SELECT DISTINCT u.id, CONCAT(up.first_name, ' ', up.last_name) as name, u.email
        FROM teacher_subject_assignments tsa
        INNER JOIN users u ON tsa.teacher_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        WHERE tsa.branch_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
        ORDER BY up.last_name, up.first_name
    ");
    $teachers_query->bind_param("ii", $branch_id, $current_ay_id);
    $teachers_query->execute();
    $teachers_result = $teachers_query->get_result();
    $teachers = [];
    while ($row = $teachers_result->fetch_assoc()) {
        $teachers[] = $row;
    }
    $branch['teachers'] = $teachers;
    $branch['teacher_count'] = count($teachers);

    // Get sections list
    $sections_query = $conn->prepare("
        SELECT s.id, s.section_name, 
               COALESCE(p.program_code, ss.strand_code) as program_code,
               (SELECT COUNT(*) FROM section_students WHERE section_id = s.id AND status = 'active') as student_count
        FROM sections s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
        WHERE s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
        ORDER BY s.section_name
    ");
    $sections_query->bind_param("ii", $branch_id, $current_ay_id);
    $sections_query->execute();
    $sections_result = $sections_query->get_result();
    $sections = [];
    while ($row = $sections_result->fetch_assoc()) {
        $sections[] = $row;
    }
    $branch['sections'] = $sections;
    $branch['section_count'] = count($sections);

    // Count subjects assigned in this branch
    $subject_count = $conn->prepare("
        SELECT COUNT(DISTINCT curriculum_subject_id) as count
        FROM teacher_subject_assignments
        WHERE branch_id = ? AND academic_year_id = ? AND is_active = 1
    ");
    $subject_count->bind_param("ii", $branch_id, $current_ay_id);
    $subject_count->execute();
    $branch['subject_count'] = $subject_count->get_result()->fetch_assoc()['count'] ?? 0;

    echo json_encode(['status' => 'success', 'branch' => $branch]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
