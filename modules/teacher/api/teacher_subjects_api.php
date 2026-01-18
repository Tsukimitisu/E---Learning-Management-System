<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_my_subjects':
        getMySubjects();
        break;
    case 'get_section_students':
        getSectionStudents();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getMySubjects() {
    global $conn, $teacher_id, $current_ay_id;
    
    $branch_id = (int)($_GET['branch_id'] ?? 0);
    $semester = $_GET['semester'] ?? '1st';
    
    // Get all subjects assigned to this teacher
    $query = "
        SELECT 
            cs.id,
            cs.subject_code,
            cs.subject_title,
            cs.units,
            cs.program_id,
            cs.year_level_id,
            cs.shs_strand_id,
            cs.shs_grade_level_id,
            p.program_name,
            pyl.year_name as year_level_name,
            ss.strand_name,
            sgl.grade_level,
            tsa.branch_id
        FROM teacher_subject_assignments tsa
        INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
        LEFT JOIN programs p ON cs.program_id = p.id
        LEFT JOIN program_year_levels pyl ON cs.year_level_id = pyl.id
        LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
        LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
        WHERE tsa.teacher_id = ? 
        AND tsa.branch_id = ? 
        AND tsa.academic_year_id = ?
        AND tsa.is_active = 1
        AND cs.semester = ?
        ORDER BY cs.subject_code
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiis", $teacher_id, $branch_id, $current_ay_id, $semester);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        // Get sections for this subject's year level
        $sections = getSectionsForSubject($row, $branch_id);
        $row['sections'] = $sections;
        
        // Calculate total students
        $total_students = 0;
        foreach ($sections as $section) {
            $total_students += $section['student_count'];
        }
        $row['total_students'] = $total_students;
        
        $subjects[] = $row;
    }
    
    echo json_encode(['success' => true, 'subjects' => $subjects]);
}

function getSectionsForSubject($subject, $branch_id) {
    global $conn, $current_ay_id;
    
    // Get sections based on whether it's college or SHS
    if ($subject['program_id']) {
        // College
        $query = "
            SELECT s.id, s.section_name, 
                   (SELECT COUNT(*) FROM section_students ss WHERE ss.section_id = s.id AND ss.status = 'active') as student_count
            FROM sections s
            WHERE s.program_id = ? 
            AND s.year_level_id = ? 
            AND s.semester = (SELECT semester FROM curriculum_subjects WHERE id = ?)
            AND s.branch_id = ?
            AND s.academic_year_id = ?
            AND s.is_active = 1
            ORDER BY s.section_name
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiii", $subject['program_id'], $subject['year_level_id'], $subject['id'], $branch_id, $current_ay_id);
    } else {
        // SHS
        $query = "
            SELECT s.id, s.section_name,
                   (SELECT COUNT(*) FROM section_students ss WHERE ss.section_id = s.id AND ss.status = 'active') as student_count
            FROM sections s
            WHERE s.shs_strand_id = ? 
            AND s.shs_grade_level_id = ? 
            AND s.semester = (SELECT semester FROM curriculum_subjects WHERE id = ?)
            AND s.branch_id = ?
            AND s.academic_year_id = ?
            AND s.is_active = 1
            ORDER BY s.section_name
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiii", $subject['shs_strand_id'], $subject['shs_grade_level_id'], $subject['id'], $branch_id, $current_ay_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
    
    return $sections;
}

function getSectionStudents() {
    global $conn, $teacher_id, $current_ay_id;
    
    $section_id = (int)($_GET['section_id'] ?? 0);
    $subject_id = (int)($_GET['subject_id'] ?? 0);
    
    // Verify teacher has access to this subject
    $verify = $conn->prepare("
        SELECT tsa.id FROM teacher_subject_assignments tsa
        WHERE tsa.teacher_id = ? AND tsa.curriculum_subject_id = ? AND tsa.is_active = 1
    ");
    $verify->bind_param("ii", $teacher_id, $subject_id);
    $verify->execute();
    if ($verify->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access to this subject']);
        return;
    }
    
    // Get students in section
    $query = "
        SELECT u.id, up.first_name, up.last_name, up.student_id,
               CONCAT(up.first_name, ' ', up.last_name) as name
        FROM section_students ss
        INNER JOIN users u ON ss.student_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        WHERE ss.section_id = ? AND ss.status = 'active'
        ORDER BY up.last_name, up.first_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
}
