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
    case 'get_sections':
        getSections();
        break;
    case 'get_subjects':
        getSubjects();
        break;
    case 'add_section':
        addSection();
        break;
    case 'get_section':
        getSection();
        break;
    case 'update_section':
        updateSection();
        break;
    case 'delete_section':
        deleteSection();
        break;
    case 'get_section_students':
        getSectionStudents();
        break;
    case 'get_available_students':
        getAvailableStudents();
        break;
    case 'add_student_to_section':
        addStudentToSection();
        break;
    case 'remove_student_from_section':
        removeStudentFromSection();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getSections() {
    global $conn, $branch_id, $current_ay_id;
    
    $program_type = $_GET['program_type'] ?? '';
    $program_id = (int)($_GET['program_id'] ?? 0);
    $year_level_id = (int)($_GET['year_level_id'] ?? 0);
    $semester = $_GET['semester'] ?? '1st';
    
    $where_clause = "s.branch_id = ? AND s.academic_year_id = ? AND s.semester = ? AND s.is_active = 1";
    $params = [$branch_id, $current_ay_id, $semester];
    $types = "iis";
    
    if ($program_type === 'college') {
        $where_clause .= " AND s.program_id = ? AND s.year_level_id = ?";
        $params[] = $program_id;
        $params[] = $year_level_id;
        $types .= "ii";
    } else if ($program_type === 'shs') {
        $where_clause .= " AND s.shs_strand_id = ? AND s.shs_grade_level_id = ?";
        $params[] = $program_id;
        $params[] = $year_level_id;
        $types .= "ii";
    }
    
    $query = "
        SELECT 
            s.id,
            s.section_name,
            s.max_capacity,
            s.room,
            s.adviser_id,
            CONCAT(up.first_name, ' ', up.last_name) as adviser_name,
            (SELECT COUNT(*) FROM section_students ss WHERE ss.section_id = s.id AND ss.status = 'active') as student_count
        FROM sections s
        LEFT JOIN users u ON s.adviser_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE $where_clause
        ORDER BY s.section_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
    
    echo json_encode(['success' => true, 'sections' => $sections]);
}

function getSubjects() {
    global $conn, $branch_id, $current_ay_id;
    
    $program_type = $_GET['program_type'] ?? '';
    $program_id = (int)($_GET['program_id'] ?? 0);
    $year_level_id = (int)($_GET['year_level_id'] ?? 0);
    $semester = $_GET['semester'] ?? '1st';
    
    if ($program_type === 'college') {
        $query = "
            SELECT cs.id, cs.subject_code, cs.subject_title, cs.units,
                   CONCAT(up.first_name, ' ', up.last_name) as teacher_name
            FROM curriculum_subjects cs
            LEFT JOIN teacher_subject_assignments tsa ON cs.id = tsa.curriculum_subject_id 
                AND tsa.branch_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
            LEFT JOIN users u ON tsa.teacher_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE cs.program_id = ? AND cs.year_level_id = ? AND cs.semester = ? AND cs.is_active = 1
            ORDER BY cs.subject_code
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiis", $branch_id, $current_ay_id, $program_id, $year_level_id, $semester);
    } else {
        // SHS - get by strand and grade level
        $query = "
            SELECT cs.id, cs.subject_code, cs.subject_title, cs.units,
                   CONCAT(up.first_name, ' ', up.last_name) as teacher_name
            FROM curriculum_subjects cs
            LEFT JOIN teacher_subject_assignments tsa ON cs.id = tsa.curriculum_subject_id 
                AND tsa.branch_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
            LEFT JOIN users u ON tsa.teacher_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE cs.shs_strand_id = ? AND cs.shs_grade_level_id = ? AND cs.semester = ? AND cs.is_active = 1
            ORDER BY cs.subject_code
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiis", $branch_id, $current_ay_id, $program_id, $year_level_id, $semester);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $subjects = [];
    
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    
    echo json_encode(['success' => true, 'subjects' => $subjects]);
}

function addSection() {
    global $conn, $branch_id, $current_ay_id;
    
    $program_type = $_POST['program_type'] ?? '';
    $section_name = trim($_POST['section_name'] ?? '');
    $max_capacity = (int)($_POST['max_capacity'] ?? 40);
    $room = trim($_POST['room'] ?? '');
    $adviser_id = !empty($_POST['adviser_id']) ? (int)$_POST['adviser_id'] : null;
    $semester = $_POST['semester'] ?? '1st';
    
    // Get program/strand and year level IDs
    $program_id = null;
    $year_level_id = null;
    $shs_strand_id = null;
    $shs_grade_level_id = null;
    
    if ($program_type === 'college') {
        $program_id = (int)($_POST['program_id'] ?? 0);
        $year_level_id = (int)($_POST['year_level_id'] ?? 0);
    } else {
        $shs_strand_id = (int)($_POST['strand_id'] ?? 0);
        $shs_grade_level_id = (int)($_POST['grade_level_id'] ?? 0);
    }
    
    if (!$section_name) {
        echo json_encode(['success' => false, 'message' => 'Section name is required']);
        return;
    }
    
    // Check for duplicate section name
    $dup_check = $conn->prepare("
        SELECT id FROM sections 
        WHERE section_name = ? AND branch_id = ? AND academic_year_id = ? AND semester = ?
        AND (program_id = ? OR shs_strand_id = ?)
        AND (year_level_id = ? OR shs_grade_level_id = ?)
    ");
    $dup_check->bind_param("siisiiii", $section_name, $branch_id, $current_ay_id, $semester, 
                           $program_id, $shs_strand_id, $year_level_id, $shs_grade_level_id);
    $dup_check->execute();
    if ($dup_check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'A section with this name already exists']);
        return;
    }
    
    // Insert new section
    $stmt = $conn->prepare("
        INSERT INTO sections (section_name, program_id, year_level_id, shs_strand_id, shs_grade_level_id, 
                              semester, academic_year_id, branch_id, max_capacity, room, adviser_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("siiiisiissi", $section_name, $program_id, $year_level_id, $shs_strand_id, 
                      $shs_grade_level_id, $semester, $current_ay_id, $branch_id, $max_capacity, $room, $adviser_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Section created successfully', 'section_id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating section: ' . $conn->error]);
    }
}

function getSection() {
    global $conn, $branch_id;
    
    $section_id = (int)($_GET['section_id'] ?? 0);
    
    $stmt = $conn->prepare("
        SELECT id, section_name, max_capacity, room, adviser_id
        FROM sections
        WHERE id = ? AND branch_id = ?
    ");
    $stmt->bind_param("ii", $section_id, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'section' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Section not found']);
    }
}

function updateSection() {
    global $conn, $branch_id;
    
    $section_id = (int)($_POST['section_id'] ?? 0);
    $section_name = trim($_POST['section_name'] ?? '');
    $max_capacity = (int)($_POST['max_capacity'] ?? 40);
    $room = trim($_POST['room'] ?? '');
    $adviser_id = !empty($_POST['adviser_id']) ? (int)$_POST['adviser_id'] : null;
    
    if (!$section_name) {
        echo json_encode(['success' => false, 'message' => 'Section name is required']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE sections 
        SET section_name = ?, max_capacity = ?, room = ?, adviser_id = ?
        WHERE id = ? AND branch_id = ?
    ");
    $stmt->bind_param("sisiii", $section_name, $max_capacity, $room, $adviser_id, $section_id, $branch_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Section updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating section']);
    }
}

function deleteSection() {
    global $conn, $branch_id;
    
    $section_id = (int)($_POST['section_id'] ?? 0);
    
    // Delete section students first
    $conn->query("DELETE FROM section_students WHERE section_id = $section_id");
    
    // Delete section
    $stmt = $conn->prepare("DELETE FROM sections WHERE id = ? AND branch_id = ?");
    $stmt->bind_param("ii", $section_id, $branch_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Section deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting section']);
    }
}

function getSectionStudents() {
    global $conn, $branch_id;
    
    $section_id = (int)($_GET['section_id'] ?? 0);
    
    // Verify section belongs to branch
    $verify = $conn->prepare("SELECT id FROM sections WHERE id = ? AND branch_id = ?");
    $verify->bind_param("ii", $section_id, $branch_id);
    $verify->execute();
    if ($verify->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Section not found']);
        return;
    }
    
    $query = "
        SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name, up.student_id
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

function getAvailableStudents() {
    global $conn, $branch_id;
    
    $section_id = (int)($_GET['section_id'] ?? 0);
    $program_type = $_GET['program_type'] ?? '';
    $program_id = (int)($_GET['program_id'] ?? 0);
    $search = trim($_GET['search'] ?? '');
    
    // Get students enrolled in this branch who are not in this section
    $query = "
        SELECT DISTINCT u.id, CONCAT(up.first_name, ' ', up.last_name) as name, up.student_id
        FROM users u
        INNER JOIN user_profiles up ON u.id = up.user_id
        INNER JOIN user_roles ur ON u.id = ur.user_id
        INNER JOIN enrollments e ON u.id = e.student_id
        WHERE ur.role_id = " . ROLE_STUDENT . "
        AND u.status = 'active'
        AND e.branch_id = ?
        AND u.id NOT IN (
            SELECT student_id FROM section_students WHERE section_id = ? AND status = 'active'
        )
    ";
    
    $params = [$branch_id, $section_id];
    $types = "ii";
    
    if (!empty($search)) {
        $query .= " AND (up.first_name LIKE ? OR up.last_name LIKE ? OR up.student_id LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }
    
    $query .= " ORDER BY up.last_name, up.first_name LIMIT 50";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
}

function addStudentToSection() {
    global $conn, $branch_id;
    
    $section_id = (int)($_POST['section_id'] ?? 0);
    $student_id = (int)($_POST['student_id'] ?? 0);
    
    // Verify section belongs to branch
    $verify = $conn->prepare("SELECT max_capacity, (SELECT COUNT(*) FROM section_students WHERE section_id = ? AND status = 'active') as current_count FROM sections WHERE id = ? AND branch_id = ?");
    $verify->bind_param("iii", $section_id, $section_id, $branch_id);
    $verify->execute();
    $section = $verify->get_result()->fetch_assoc();
    
    if (!$section) {
        echo json_encode(['success' => false, 'message' => 'Section not found']);
        return;
    }
    
    if ($section['current_count'] >= $section['max_capacity']) {
        echo json_encode(['success' => false, 'message' => 'Section is at full capacity']);
        return;
    }
    
    // Check if already enrolled
    $check = $conn->prepare("SELECT id FROM section_students WHERE section_id = ? AND student_id = ?");
    $check->bind_param("ii", $section_id, $student_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        // Reactivate if exists
        $stmt = $conn->prepare("UPDATE section_students SET status = 'active' WHERE section_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $section_id, $student_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO section_students (section_id, student_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $section_id, $student_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student added to section']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding student']);
    }
}

function removeStudentFromSection() {
    global $conn, $branch_id;
    
    $section_id = (int)($_POST['section_id'] ?? 0);
    $student_id = (int)($_POST['student_id'] ?? 0);
    
    // Verify section belongs to branch
    $verify = $conn->prepare("SELECT id FROM sections WHERE id = ? AND branch_id = ?");
    $verify->bind_param("ii", $section_id, $branch_id);
    $verify->execute();
    if ($verify->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Section not found']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE section_students SET status = 'dropped' WHERE section_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $section_id, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student removed from section']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing student']);
    }
}
