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
    case 'get_available_sections':
        getAvailableSections();
        break;
    case 'get_student_enrollments':
        getStudentEnrollments();
        break;
    case 'enroll':
        enrollStudent();
        break;
    case 'unenroll':
        unenrollStudent();
        break;
    case 'unenroll_all':
        unenrollAll();
        break;
    case 'get_all_sections_for_bulk':
        getAllSectionsForBulk();
        break;
    case 'get_unenrolled_students':
        getUnenrolledStudents();
        break;
    case 'get_bulk_unenrolled_students':
        getBulkUnenrolledStudents();
        break;
    case 'bulk_enroll':
        bulkEnroll();
        break;
    case 'bulk_assign_to_section':
        bulkAssignToSection();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getAvailableSections() {
    global $conn, $branch_id, $current_ay_id;
    
    $student_id = (int)($_GET['student_id'] ?? 0);
    
    // Get all sections in this branch for the current academic year
    $query = "
        SELECT 
            s.id,
            s.section_name,
            s.max_capacity,
            s.room,
            s.semester,
            s.program_id,
            s.shs_strand_id as strand_id,
            s.year_level_id,
            s.shs_grade_level_id as grade_level_id,
            p.program_code,
            p.program_name,
            ss.strand_code,
            ss.strand_name,
            pyl.year_name,
            sgl.grade_name,
            CONCAT(up.first_name, ' ', up.last_name) as adviser_name,
            (SELECT COUNT(*) FROM section_students WHERE section_id = s.id AND status = 'active') as current_enrolled,
            (SELECT COUNT(*) FROM section_students WHERE section_id = s.id AND student_id = ? AND status = 'active') as is_enrolled
        FROM sections s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
        LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
        LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
        LEFT JOIN users u ON s.adviser_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
        ORDER BY COALESCE(p.program_code, ss.strand_code), s.section_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $student_id, $branch_id, $current_ay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_enrolled'] = (bool)$row['is_enrolled'];
        $row['is_full'] = $row['current_enrolled'] >= $row['max_capacity'];
        $sections[] = $row;
    }
    
    echo json_encode(['success' => true, 'sections' => $sections]);
}

function getStudentEnrollments() {
    global $conn, $branch_id, $current_ay_id;
    
    $student_id = (int)($_GET['student_id'] ?? 0);
    
    $query = "
        SELECT 
            ss.id as enrollment_id,
            ss.section_id,
            s.section_name,
            s.room,
            s.semester,
            COALESCE(p.program_code, st.strand_code) as program_code,
            COALESCE(p.program_name, st.strand_name) as program_name,
            COALESCE(pyl.year_name, sgl.grade_name) as year_level,
            CONCAT(up.first_name, ' ', up.last_name) as adviser_name
        FROM section_students ss
        INNER JOIN sections s ON ss.section_id = s.id
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN shs_strands st ON s.shs_strand_id = st.id
        LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
        LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
        LEFT JOIN users u ON s.adviser_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE ss.student_id = ? AND s.branch_id = ? AND s.academic_year_id = ? AND ss.status = 'active'
        ORDER BY s.section_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $student_id, $branch_id, $current_ay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $enrollments = [];
    while ($row = $result->fetch_assoc()) {
        $enrollments[] = $row;
    }
    
    echo json_encode(['success' => true, 'enrollments' => $enrollments]);
}

function enrollStudent() {
    global $conn, $branch_id, $current_ay_id;
    
    $student_id = (int)($_POST['student_id'] ?? 0);
    $section_id = (int)($_POST['section_id'] ?? 0);
    
    if (!$student_id || !$section_id) {
        echo json_encode(['success' => false, 'message' => 'Student and section are required']);
        return;
    }
    
    // Verify section belongs to branch and has capacity
    $section_check = $conn->prepare("
        SELECT id, max_capacity,
               (SELECT COUNT(*) FROM section_students WHERE section_id = id AND status = 'active') as current_enrolled
        FROM sections 
        WHERE id = ? AND branch_id = ? AND academic_year_id = ? AND is_active = 1
    ");
    $section_check->bind_param("iii", $section_id, $branch_id, $current_ay_id);
    $section_check->execute();
    $section = $section_check->get_result()->fetch_assoc();
    
    if (!$section) {
        echo json_encode(['success' => false, 'message' => 'Invalid section']);
        return;
    }
    
    if ($section['current_enrolled'] >= $section['max_capacity']) {
        echo json_encode(['success' => false, 'message' => 'Section is full']);
        return;
    }
    
    // Check if already enrolled
    $dup_check = $conn->prepare("
        SELECT id FROM section_students WHERE student_id = ? AND section_id = ? AND status = 'active'
    ");
    $dup_check->bind_param("ii", $student_id, $section_id);
    $dup_check->execute();
    if ($dup_check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Student is already in this section']);
        return;
    }
    
    // Add student to section
    $stmt = $conn->prepare("INSERT INTO section_students (section_id, student_id, status, enrolled_at) VALUES (?, ?, 'active', NOW())");
    $stmt->bind_param("ii", $section_id, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student added to section successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding student to section']);
    }
}

function unenrollStudent() {
    global $conn, $branch_id;
    
    $student_id = (int)($_POST['student_id'] ?? 0);
    $section_id = (int)($_POST['section_id'] ?? 0);
    
    if (!$student_id || !$section_id) {
        echo json_encode(['success' => false, 'message' => 'Student and section are required']);
        return;
    }
    
    // Verify section belongs to branch
    $section_check = $conn->prepare("SELECT id FROM sections WHERE id = ? AND branch_id = ?");
    $section_check->bind_param("ii", $section_id, $branch_id);
    $section_check->execute();
    if ($section_check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid section']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE section_students SET status = 'removed' WHERE student_id = ? AND section_id = ?");
    $stmt->bind_param("ii", $student_id, $section_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student removed from section']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing student']);
    }
}

function unenrollAll() {
    global $conn, $branch_id, $current_ay_id;
    
    $student_id = (int)($_POST['student_id'] ?? 0);
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID required']);
        return;
    }
    
    // Remove from all sections in this branch for current academic year
    $stmt = $conn->prepare("
        UPDATE section_students ss
        INNER JOIN sections s ON ss.section_id = s.id
        SET ss.status = 'removed'
        WHERE ss.student_id = ? AND s.branch_id = ? AND s.academic_year_id = ?
    ");
    $stmt->bind_param("iii", $student_id, $branch_id, $current_ay_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student removed from all sections']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing student from sections']);
    }
}

function getAllSectionsForBulk() {
    global $conn, $branch_id, $current_ay_id;
    
    $query = "
        SELECT 
            s.id,
            s.section_name,
            s.max_capacity,
            s.semester,
            COALESCE(p.program_code, st.strand_code) as program_code,
            COALESCE(pyl.year_name, sgl.grade_name) as year_level,
            (SELECT COUNT(*) FROM section_students WHERE section_id = s.id AND status = 'active') as current_enrolled
        FROM sections s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN shs_strands st ON s.shs_strand_id = st.id
        LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
        LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
        WHERE s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
        HAVING current_enrolled < s.max_capacity
        ORDER BY program_code, s.section_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $branch_id, $current_ay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
    
    echo json_encode(['success' => true, 'sections' => $sections]);
}

function getUnenrolledStudents() {
    global $conn, $branch_id;
    
    $section_id = (int)($_GET['section_id'] ?? 0);
    
    $query = "
        SELECT 
            u.id,
            up.first_name,
            up.last_name,
            COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no
        FROM users u
        INNER JOIN user_profiles up ON u.id = up.user_id
        INNER JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN students st ON u.id = st.user_id
        WHERE ur.role_id = " . ROLE_STUDENT . "
        AND u.status = 'active'
        AND u.id NOT IN (
            SELECT student_id FROM section_students WHERE section_id = ? AND status = 'active'
        )
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

function bulkEnroll() {
    global $conn, $branch_id, $current_ay_id;
    
    $section_id = (int)($_POST['section_id'] ?? 0);
    $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);
    
    if (!$section_id || empty($student_ids)) {
        echo json_encode(['success' => false, 'message' => 'Section and students are required']);
        return;
    }
    
    // Verify section
    $section_check = $conn->prepare("
        SELECT id, max_capacity,
               (SELECT COUNT(*) FROM section_students WHERE section_id = id AND status = 'active') as current_enrolled
        FROM sections 
        WHERE id = ? AND branch_id = ? AND academic_year_id = ? AND is_active = 1
    ");
    $section_check->bind_param("iii", $section_id, $branch_id, $current_ay_id);
    $section_check->execute();
    $section = $section_check->get_result()->fetch_assoc();
    
    if (!$section) {
        echo json_encode(['success' => false, 'message' => 'Invalid section']);
        return;
    }
    
    $available_slots = $section['max_capacity'] - $section['current_enrolled'];
    if (count($student_ids) > $available_slots) {
        echo json_encode(['success' => false, 'message' => "Only $available_slots slots available in this section"]);
        return;
    }
    
    $enrolled_count = 0;
    $stmt = $conn->prepare("INSERT IGNORE INTO section_students (section_id, student_id, status, enrolled_at) VALUES (?, ?, 'active', NOW())");
    
    foreach ($student_ids as $student_id) {
        $student_id = (int)$student_id;
        $stmt->bind_param("ii", $section_id, $student_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $enrolled_count++;
        }
    }
    
    echo json_encode(['success' => true, 'message' => "$enrolled_count students added to section"]);
}
function getBulkUnenrolledStudents() {
    global $conn, $branch_id, $current_ay_id;
    
    $section_id = (int)($_GET['section_id'] ?? 0);
    $filter = $_GET['filter'] ?? '';
    
    if (!$section_id) {
        echo json_encode(['success' => false, 'message' => 'Section ID required']);
        return;
    }
    
    $where_clause = "ur.role_id = " . ROLE_STUDENT . "
        AND u.status = 'active'
        AND u.id NOT IN (
            SELECT student_id FROM section_students WHERE section_id = ? AND status = 'active'
        )";
    
    if ($filter === 'no_program') {
        $where_clause .= " AND st.course_id IS NULL";
    } elseif ($filter === 'with_program') {
        $where_clause .= " AND st.course_id IS NOT NULL";
    }
    
    $query = "
        SELECT 
            u.id,
            up.first_name,
            up.last_name,
            COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
            COALESCE(p.program_code, ss.strand_code) as program_code
        FROM users u
        INNER JOIN user_profiles up ON u.id = up.user_id
        INNER JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN students st ON u.id = st.user_id
        LEFT JOIN programs p ON st.course_id = p.id
        LEFT JOIN shs_strands ss ON st.course_id = ss.id
        WHERE $where_clause
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

function bulkAssignToSection() {
    global $conn, $branch_id, $current_ay_id;
    
    $section_id = (int)($_POST['section_id'] ?? 0);
    $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);
    
    if (!$section_id || empty($student_ids)) {
        echo json_encode(['success' => false, 'message' => 'Section and students are required']);
        return;
    }
    
    // Verify section exists and belongs to branch
    $section_check = $conn->prepare("
        SELECT id, max_capacity,
               (SELECT COUNT(*) FROM section_students WHERE section_id = id AND status = 'active') as current_enrolled
        FROM sections 
        WHERE id = ? AND branch_id = ? AND academic_year_id = ? AND is_active = 1
    ");
    $section_check->bind_param("iii", $section_id, $branch_id, $current_ay_id);
    $section_check->execute();
    $section = $section_check->get_result()->fetch_assoc();
    
    if (!$section) {
        echo json_encode(['success' => false, 'message' => 'Invalid section']);
        return;
    }
    
    $available_slots = $section['max_capacity'] - $section['current_enrolled'];
    if (count($student_ids) > $available_slots) {
        echo json_encode(['success' => false, 'message' => "Only $available_slots slots available in this section"]);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $assigned_count = 0;
        $stmt = $conn->prepare("INSERT IGNORE INTO section_students (section_id, student_id, status, enrolled_at) VALUES (?, ?, 'active', NOW())");
        
        foreach ($student_ids as $student_id) {
            $student_id = (int)$student_id;
            
            // Verify student belongs to branch
            $check = $conn->prepare("
                SELECT u.id FROM users u
                INNER JOIN user_profiles up ON u.id = up.user_id
                WHERE u.id = ? AND up.branch_id = ?
            ");
            $check->bind_param("ii", $student_id, $branch_id);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                continue;
            }
            
            $stmt->bind_param("ii", $section_id, $student_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $assigned_count++;
            }
        }
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => "$assigned_count student(s) assigned to section successfully"]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error assigning students: ' . $e->getMessage()]);
    }
}