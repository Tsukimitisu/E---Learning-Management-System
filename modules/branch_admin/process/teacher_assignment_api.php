<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo json_encode(['success' => false, 'message' => 'No branch assigned']);
    exit();
}

// Get current academic year
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_year_levels':
        getYearLevels();
        break;
    case 'get_subjects':
        getSubjects();
        break;
    case 'assign_teacher':
        assignTeacher();
        break;
    case 'unassign_teacher':
        unassignTeacher();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getYearLevels() {
    global $conn;
    
    $type = $_GET['type'] ?? 'college';
    $program_id = (int)($_GET['program_id'] ?? 0);
    
    $levels = [];
    
    if ($type === 'college') {
        $query = "SELECT id, year_name as name FROM program_year_levels WHERE program_id = ? AND is_active = 1 ORDER BY year_level";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
            return;
        }
        $stmt->bind_param("i", $program_id);
    } else {
        $query = "SELECT id, grade_name as name FROM shs_grade_levels WHERE strand_id = ? AND is_active = 1 ORDER BY grade_level";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
            return;
        }
        $stmt->bind_param("i", $program_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $levels[] = $row;
    }
    
    echo json_encode(['success' => true, 'levels' => $levels]);
}

function getSubjects() {
    global $conn, $branch_id, $current_ay_id;
    
    $type = $_GET['type'] ?? 'college';
    $program_id = (int)($_GET['program_id'] ?? 0);
    $year_level_id = (int)($_GET['year_level_id'] ?? 0);
    $semester = $_GET['semester'] ?? '1st';
    
    // Convert semester format: '1st' -> 1, '2nd' -> 2, 'summer' -> 3
    $semester_num = 1;
    if ($semester === '2nd') {
        $semester_num = 2;
    } else if ($semester === 'summer') {
        $semester_num = 3;
    }
    
    // Debug info
    $debug = [
        'branch_id' => $branch_id,
        'academic_year_id' => $current_ay_id,
        'type' => $type,
        'program_id' => $program_id,
        'year_level_id' => $year_level_id,
        'semester' => $semester,
        'semester_num' => $semester_num
    ];
    
    if ($type === 'college') {
        $query = "
            SELECT cs.id, cs.subject_code, cs.subject_title, cs.units,
                   tsa.teacher_id, CONCAT(up.first_name, ' ', up.last_name) as teacher_name
            FROM curriculum_subjects cs
            LEFT JOIN teacher_subject_assignments tsa ON cs.id = tsa.curriculum_subject_id 
                AND tsa.branch_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
            LEFT JOIN users u ON tsa.teacher_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE cs.program_id = ? AND cs.year_level_id = ? AND cs.semester = ? AND cs.is_active = 1
            ORDER BY cs.subject_code
        ";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Query prepare error: ' . $conn->error, 'debug' => $debug]);
            return;
        }
        $stmt->bind_param("iiiii", $branch_id, $current_ay_id, $program_id, $year_level_id, $semester_num);
    } else {
        // For SHS, get subjects that match the grade level (shs_grade_level_id)
        // Include subjects where shs_strand_id matches OR is NULL (core subjects for all strands)
        $query = "
            SELECT cs.id, cs.subject_code, cs.subject_title, cs.units,
                   tsa.teacher_id, CONCAT(up.first_name, ' ', up.last_name) as teacher_name
            FROM curriculum_subjects cs
            LEFT JOIN teacher_subject_assignments tsa ON cs.id = tsa.curriculum_subject_id 
                AND tsa.branch_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
            LEFT JOIN users u ON tsa.teacher_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE (cs.shs_strand_id = ? OR cs.shs_strand_id IS NULL) 
                AND cs.shs_grade_level_id = ? 
                AND cs.semester = ? 
                AND cs.is_active = 1
                AND cs.subject_type IN ('shs_core', 'shs_applied', 'shs_specialized')
            ORDER BY cs.subject_code
        ";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Query prepare error: ' . $conn->error, 'debug' => $debug]);
            return;
        }
        $stmt->bind_param("iiiii", $branch_id, $current_ay_id, $program_id, $year_level_id, $semester_num);
    }
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Query execute error: ' . $stmt->error, 'debug' => $debug]);
        return;
    }
    
    $result = $stmt->get_result();
    
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    
    echo json_encode(['success' => true, 'subjects' => $subjects, 'debug' => $debug]);
}

function assignTeacher() {
    global $conn, $branch_id, $current_ay_id;
    
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    
    if (!$subject_id || !$teacher_id) {
        echo json_encode(['success' => false, 'message' => 'Subject and teacher are required']);
        return;
    }
    
    // Check if assignment already exists
    $check = $conn->prepare("
        SELECT id FROM teacher_subject_assignments 
        WHERE curriculum_subject_id = ? AND branch_id = ? AND academic_year_id = ?
    ");
    $check->bind_param("iii", $subject_id, $branch_id, $current_ay_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    
    if ($existing) {
        // Update existing assignment
        $stmt = $conn->prepare("
            UPDATE teacher_subject_assignments 
            SET teacher_id = ?, is_active = 1
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $teacher_id, $existing['id']);
    } else {
        // Create new assignment
        $stmt = $conn->prepare("
            INSERT INTO teacher_subject_assignments (teacher_id, curriculum_subject_id, branch_id, academic_year_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiii", $teacher_id, $subject_id, $branch_id, $current_ay_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Teacher assigned successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error assigning teacher: ' . $conn->error]);
    }
}

function unassignTeacher() {
    global $conn, $branch_id, $current_ay_id;
    
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    
    $stmt = $conn->prepare("
        UPDATE teacher_subject_assignments 
        SET is_active = 0
        WHERE curriculum_subject_id = ? AND branch_id = ? AND academic_year_id = ?
    ");
    $stmt->bind_param("iii", $subject_id, $branch_id, $current_ay_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Teacher unassigned successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error unassigning teacher']);
    }
}
