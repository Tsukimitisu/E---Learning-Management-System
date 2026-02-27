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
ensureIrregularSupportSchema($conn);

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
    case 'get_sections_by_program':
        getSectionsByProgram();
        break;
    case 'get_students_by_program':
        getStudentsByProgram();
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
            CASE WHEN s.program_id IS NOT NULL THEN 'college' ELSE 'shs' END as subject_type,
            p.program_code,
            p.program_name,
            ss.strand_code,
            ss.strand_name,
            pyl.year_name,
            sgl.grade_name,
            CONCAT(up.first_name, ' ', up.last_name) as adviser_name,
            (SELECT COUNT(*) FROM section_students WHERE section_id = s.id AND status = 'active') as current_enrolled,
            (SELECT COUNT(*) FROM section_students WHERE section_id = s.id AND student_id = ? AND status = 'active') as is_enrolled,
            (
                SELECT COUNT(*)
                FROM student_subject_enrollments sse
                INNER JOIN curriculum_subjects cs ON sse.subject_id = cs.id
                WHERE sse.student_id = ? 
                  AND sse.academic_year_id = ?
                  AND sse.status = 'enrolled'
                  AND (
                      (s.program_id IS NOT NULL AND cs.program_id = s.program_id AND cs.year_level_id = s.year_level_id)
                      OR
                      (s.shs_strand_id IS NOT NULL AND cs.shs_strand_id = s.shs_strand_id AND cs.shs_grade_level_id = s.shs_grade_level_id)
                  )
                  AND (
                      s.semester = 'summer'
                      OR (s.semester = '1st' AND cs.semester = 1)
                      OR (s.semester = '2nd' AND cs.semester = 2)
                  )
            ) as eligible_subject_count
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
    $stmt->bind_param("iiiii", $student_id, $student_id, $current_ay_id, $branch_id, $current_ay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_enrolled'] = (bool)$row['is_enrolled'];
        $row['is_full'] = $row['current_enrolled'] >= $row['max_capacity'];
        $row['is_eligible'] = ((int)($row['eligible_subject_count'] ?? 0)) > 0;
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

    if (!hasAssignableSubjectsForSection($student_id, $section_id, $current_ay_id)) {
        echo json_encode(['success' => false, 'message' => 'Student has no enrolled subjects for this section. Assign only to sections with pending enrolled subjects.']);
        return;
    }
    
    // Check if already enrolled in this section
    $dup_check = $conn->prepare("
        SELECT id FROM section_students WHERE student_id = ? AND section_id = ? AND status = 'active'
    ");
    $dup_check->bind_param("ii", $student_id, $section_id);
    $dup_check->execute();
    if ($dup_check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Student is already in this section']);
        return;
    }
    
    // Check if student is already enrolled in any other section for this academic year (only 1 section per student)
    $existing_section_check = $conn->prepare("
        SELECT ss.id, s.section_name 
        FROM section_students ss
        INNER JOIN sections s ON ss.section_id = s.id
        WHERE ss.student_id = ? AND ss.status = 'active' 
        AND s.academic_year_id = ? AND s.branch_id = ?
    ");
    $existing_section_check->bind_param("iii", $student_id, $current_ay_id, $branch_id);
    $existing_section_check->execute();
    $existing_result = $existing_section_check->get_result();
    if ($existing_result->num_rows > 0) {
        $existing = $existing_result->fetch_assoc();
        echo json_encode(['success' => false, 'message' => 'Student is already enrolled in section "' . $existing['section_name'] . '". A student can only be assigned to 1 section.']);
        return;
    }
    
    // Add student to section
    $stmt = $conn->prepare("INSERT INTO section_students (section_id, student_id, status, enrolled_at) VALUES (?, ?, 'active', NOW())");
    $stmt->bind_param("ii", $section_id, $student_id);
    
    if ($stmt->execute()) {
        syncStudentSubjectEnrollmentsForSection($student_id, $section_id, $current_ay_id, $_SESSION['user_id']);
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
    $skipped_students = [];
    
    // Prepare statements
    $stmt = $conn->prepare("INSERT IGNORE INTO section_students (section_id, student_id, status, enrolled_at) VALUES (?, ?, 'active', NOW())");
    $existing_check = $conn->prepare("
        SELECT ss.id, s.section_name 
        FROM section_students ss
        INNER JOIN sections s ON ss.section_id = s.id
        WHERE ss.student_id = ? AND ss.status = 'active' 
        AND s.academic_year_id = ? AND s.branch_id = ?
    ");
    
    foreach ($student_ids as $student_id) {
        $student_id = (int)$student_id;
        
        // Check if student is already in any section for this academic year
        $existing_check->bind_param("iii", $student_id, $current_ay_id, $branch_id);
        $existing_check->execute();
        if ($existing_check->get_result()->num_rows > 0) {
            $skipped_students[] = $student_id;
            continue;
        }

        if (!hasAssignableSubjectsForSection($student_id, $section_id, $current_ay_id)) {
            $skipped_students[] = $student_id;
            continue;
        }
        
        $stmt->bind_param("ii", $section_id, $student_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            syncStudentSubjectEnrollmentsForSection($student_id, $section_id, $current_ay_id, $_SESSION['user_id']);
            $enrolled_count++;
        }
    }
    
    $message = "$enrolled_count students added to section";
    if (count($skipped_students) > 0) {
        $message .= ". " . count($skipped_students) . " student(s) skipped (already assigned or no eligible subjects for selected section).";
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
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
        $skipped_students = [];
        
        $stmt = $conn->prepare("INSERT IGNORE INTO section_students (section_id, student_id, status, enrolled_at) VALUES (?, ?, 'active', NOW())");
        $existing_check = $conn->prepare("
            SELECT ss.id, s.section_name 
            FROM section_students ss
            INNER JOIN sections s ON ss.section_id = s.id
            WHERE ss.student_id = ? AND ss.status = 'active' 
            AND s.academic_year_id = ? AND s.branch_id = ?
        ");
        
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
            
            // Check if student is already in any section for this academic year (only 1 section allowed)
            $existing_check->bind_param("iii", $student_id, $current_ay_id, $branch_id);
            $existing_check->execute();
            if ($existing_check->get_result()->num_rows > 0) {
                $skipped_students[] = $student_id;
                continue;
            }

            if (!hasAssignableSubjectsForSection($student_id, $section_id, $current_ay_id)) {
                $skipped_students[] = $student_id;
                continue;
            }
            
            $stmt->bind_param("ii", $section_id, $student_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                syncStudentSubjectEnrollmentsForSection($student_id, $section_id, $current_ay_id, $_SESSION['user_id']);
                $assigned_count++;
            }
        }
        
        $conn->commit();
        
        $message = "$assigned_count student(s) assigned to section successfully";
        if (count($skipped_students) > 0) {
            $message .= ". " . count($skipped_students) . " student(s) skipped (already assigned or no eligible subjects for selected section).";
        }
        
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error assigning students: ' . $e->getMessage()]);
    }
}

// Get sections filtered by program/strand and year level
function getSectionsByProgram() {
    global $conn, $branch_id, $current_ay_id;
    
    $program_type = $_GET['program_type'] ?? '';
    $program_id = (int)($_GET['program_id'] ?? 0);
    $year_level_id = (int)($_GET['year_level_id'] ?? 0);
    
    if (!$program_type || !$program_id) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    $sections = [];
    
    if ($program_type === 'college') {
        $query = "
            SELECT 
                s.id,
                s.section_name,
                s.max_capacity,
                p.program_code as subject_code,
                CONCAT(p.program_code, ' - ', s.section_name) as display_name,
                (SELECT COUNT(*) FROM section_students WHERE section_id = s.id AND status = 'active') as current_enrolled
            FROM sections s
            INNER JOIN programs p ON s.program_id = p.id
            WHERE s.branch_id = ? 
            AND s.academic_year_id = ? 
            AND s.is_active = 1
            AND s.program_id = ?
            " . ($year_level_id ? "AND s.year_level_id = ?" : "") . "
            ORDER BY s.section_name
        ";
        
        if ($year_level_id) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiii", $branch_id, $current_ay_id, $program_id, $year_level_id);
        } else {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iii", $branch_id, $current_ay_id, $program_id);
        }
    } else {
        // SHS
        $query = "
            SELECT 
                s.id,
                s.section_name,
                s.max_capacity,
                ss.strand_code as subject_code,
                CONCAT(ss.strand_code, ' - ', s.section_name) as display_name,
                (SELECT COUNT(*) FROM section_students WHERE section_id = s.id AND status = 'active') as current_enrolled
            FROM sections s
            INNER JOIN shs_strands ss ON s.shs_strand_id = ss.id
            WHERE s.branch_id = ? 
            AND s.academic_year_id = ? 
            AND s.is_active = 1
            AND s.shs_strand_id = ?
            " . ($year_level_id ? "AND s.shs_grade_level_id = ?" : "") . "
            ORDER BY s.section_name
        ";
        
        if ($year_level_id) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiii", $branch_id, $current_ay_id, $program_id, $year_level_id);
        } else {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iii", $branch_id, $current_ay_id, $program_id);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
    
    echo json_encode(['success' => true, 'sections' => $sections]);
}

// Get students filtered by program/strand (enrolled students in that program)
function getStudentsByProgram() {
    global $conn, $branch_id, $current_ay_id;
    
    $program_type = $_GET['program_type'] ?? '';
    $program_id = (int)($_GET['program_id'] ?? 0);
    $year_level_id = (int)($_GET['year_level_id'] ?? 0);
    
    if (!$program_type || !$program_id) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    $students = [];

    if ($program_type === 'college') {
        $eligibility_clause = "
            AND EXISTS (
                SELECT 1
                FROM student_subject_enrollments sse
                INNER JOIN curriculum_subjects cs ON cs.id = sse.subject_id
                WHERE sse.student_id = u.id
                  AND sse.academic_year_id = ?
                  AND sse.status = 'enrolled'
                  AND cs.program_id = ?
                  " . ($year_level_id ? "AND cs.year_level_id = ?" : "") . "
            )
        ";
    } else {
        $eligibility_clause = "
            AND EXISTS (
                SELECT 1
                FROM student_subject_enrollments sse
                INNER JOIN curriculum_subjects cs ON cs.id = sse.subject_id
                WHERE sse.student_id = u.id
                  AND sse.academic_year_id = ?
                  AND sse.status = 'enrolled'
                  AND cs.shs_strand_id = ?
                  " . ($year_level_id ? "AND cs.shs_grade_level_id = ?" : "") . "
            )
        ";
    }

    // Get students enrolled in the specified program/strand and with at least one pending subject.
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
        INNER JOIN students st ON u.id = st.user_id
        LEFT JOIN programs p ON st.course_id = p.id
        LEFT JOIN shs_strands ss ON st.course_id = ss.id
        WHERE ur.role_id = " . ROLE_STUDENT . "
        AND u.status = 'active'
        AND up.branch_id = ?
        AND st.course_id = ?
        $eligibility_clause
        ORDER BY up.last_name, up.first_name
    ";

    $stmt = $conn->prepare($query);
    if ($year_level_id) {
        $stmt->bind_param("iiiii", $branch_id, $program_id, $current_ay_id, $program_id, $year_level_id);
    } else {
        $stmt->bind_param("iiii", $branch_id, $program_id, $current_ay_id, $program_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
}

function syncStudentSubjectEnrollmentsForSection($student_id, $section_id, $current_ay_id, $recorded_by) {
    global $conn;

    $section_stmt = $conn->prepare("
        SELECT id, program_id, year_level_id, shs_strand_id, shs_grade_level_id, semester
        FROM sections
        WHERE id = ?
        LIMIT 1
    ");
    $section_stmt->bind_param("i", $section_id);
    $section_stmt->execute();
    $section = $section_stmt->get_result()->fetch_assoc();
    if (!$section) {
        return;
    }

    $semester = $section['semester'] ?? '1st';
    $semester_num = 1;
    if ($semester === '2nd') {
        $semester_num = 2;
    } elseif ($semester === 'summer') {
        $semester_num = 3;
    }

    if (!empty($section['program_id'])) {
        $subjects_stmt = $conn->prepare("
            SELECT id
            FROM curriculum_subjects
            WHERE program_id = ? AND year_level_id = ? AND is_active = 1
              AND (semester = ? OR ? = 3)
        ");
        $subjects_stmt->bind_param("iiii", $section['program_id'], $section['year_level_id'], $semester_num, $semester_num);
    } else {
        $subjects_stmt = $conn->prepare("
            SELECT id
            FROM curriculum_subjects
            WHERE shs_strand_id = ? AND shs_grade_level_id = ? AND is_active = 1
              AND (semester = ? OR ? = 3)
        ");
        $subjects_stmt->bind_param("iiii", $section['shs_strand_id'], $section['shs_grade_level_id'], $semester_num, $semester_num);
    }
    $subjects_stmt->execute();
    $subjects_result = $subjects_stmt->get_result();

    $subject_ids = [];
    while ($row = $subjects_result->fetch_assoc()) {
        $subject_ids[] = (int)$row['id'];
    }
    if (empty($subject_ids)) {
        return;
    }

    $completed_map = [];
    $completed_sql = "
        SELECT subject_id FROM student_completed_subjects
        WHERE student_id = ? AND subject_id IN (" . implode(',', array_fill(0, count($subject_ids), '?')) . ")
    ";
    $completed_stmt = $conn->prepare($completed_sql);
    $completed_types = 'i' . str_repeat('i', count($subject_ids));
    $completed_params = array_merge([$student_id], $subject_ids);
    $completed_stmt->bind_param($completed_types, ...$completed_params);
    $completed_stmt->execute();
    $completed_result = $completed_stmt->get_result();
    while ($row = $completed_result->fetch_assoc()) {
        $completed_map[(int)$row['subject_id']] = true;
    }

    $student_type = 'regular';
    $type_stmt = $conn->prepare("SELECT COALESCE(student_type, 'regular') as student_type FROM students WHERE user_id = ?");
    $type_stmt->bind_param("i", $student_id);
    $type_stmt->execute();
    $type_row = $type_stmt->get_result()->fetch_assoc();
    if (!empty($type_row['student_type'])) {
        $student_type = normalizeStudentType($type_row['student_type']);
    }

    $upsert = $conn->prepare("
        INSERT INTO student_subject_enrollments
            (student_id, subject_id, section_id, academic_year_id, status, enrollment_type, recorded_by)
        VALUES (?, ?, ?, ?, 'enrolled', ?, ?)
        ON DUPLICATE KEY UPDATE
            section_id = VALUES(section_id),
            status = 'enrolled',
            enrollment_type = VALUES(enrollment_type),
            recorded_by = VALUES(recorded_by),
            updated_at = NOW()
    ");

    foreach ($subject_ids as $subject_id) {
        if (isset($completed_map[$subject_id])) {
            continue;
        }
        $upsert->bind_param("iiiisi", $student_id, $subject_id, $section_id, $current_ay_id, $student_type, $recorded_by);
        $upsert->execute();
    }
}

function hasAssignableSubjectsForSection($student_id, $section_id, $current_ay_id) {
    global $conn;

    $section_stmt = $conn->prepare("
        SELECT id, program_id, year_level_id, shs_strand_id, shs_grade_level_id, semester
        FROM sections
        WHERE id = ?
        LIMIT 1
    ");
    $section_stmt->bind_param("i", $section_id);
    $section_stmt->execute();
    $section = $section_stmt->get_result()->fetch_assoc();
    if (!$section) {
        return false;
    }

    $semester = $section['semester'] ?? '1st';
    $semester_num = 1;
    if ($semester === '2nd') {
        $semester_num = 2;
    } elseif ($semester === 'summer') {
        $semester_num = 3;
    }

    if (!empty($section['program_id'])) {
        $subjects_stmt = $conn->prepare("
            SELECT id
            FROM curriculum_subjects
            WHERE program_id = ? AND year_level_id = ? AND is_active = 1
              AND (semester = ? OR ? = 3)
        ");
        $subjects_stmt->bind_param("iiii", $section['program_id'], $section['year_level_id'], $semester_num, $semester_num);
    } else {
        $subjects_stmt = $conn->prepare("
            SELECT id
            FROM curriculum_subjects
            WHERE shs_strand_id = ? AND shs_grade_level_id = ? AND is_active = 1
              AND (semester = ? OR ? = 3)
        ");
        $subjects_stmt->bind_param("iiii", $section['shs_strand_id'], $section['shs_grade_level_id'], $semester_num, $semester_num);
    }
    $subjects_stmt->execute();
    $subjects_result = $subjects_stmt->get_result();

    $subject_ids = [];
    while ($row = $subjects_result->fetch_assoc()) {
        $subject_ids[] = (int)$row['id'];
    }
    if (empty($subject_ids)) {
        return false;
    }

    $eligibility_sql = "
        SELECT COUNT(*) as cnt
        FROM student_subject_enrollments
        WHERE student_id = ? AND academic_year_id = ? AND status = 'enrolled'
          AND subject_id IN (" . implode(',', array_fill(0, count($subject_ids), '?')) . ")
    ";
    $eligibility_stmt = $conn->prepare($eligibility_sql);
    $eligibility_types = 'ii' . str_repeat('i', count($subject_ids));
    $eligibility_params = array_merge([$student_id, $current_ay_id], $subject_ids);
    $eligibility_stmt->bind_param($eligibility_types, ...$eligibility_params);
    $eligibility_stmt->execute();
    $eligibility_row = $eligibility_stmt->get_result()->fetch_assoc();

    return ((int)($eligibility_row['cnt'] ?? 0)) > 0;
}

function ensureIrregularSupportSchema($conn) {
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular' AFTER course_id");
    $conn->query("ALTER TABLE students MODIFY COLUMN student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular'");
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS previous_school VARCHAR(255) DEFAULT NULL AFTER student_type");

    $conn->query("
        CREATE TABLE IF NOT EXISTS student_completed_subjects (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT(10) UNSIGNED NOT NULL,
            subject_id INT(10) UNSIGNED NOT NULL,
            completion_source VARCHAR(255) DEFAULT NULL,
            previous_subject_name VARCHAR(255) DEFAULT NULL,
            previous_grade VARCHAR(50) DEFAULT NULL,
            remarks TEXT DEFAULT NULL,
            recorded_by INT(10) UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_student_subject (student_id, subject_id),
            KEY idx_student (student_id),
            KEY idx_subject (subject_id),
            KEY idx_recorded_by (recorded_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->query("ALTER TABLE student_completed_subjects ADD COLUMN IF NOT EXISTS previous_subject_name VARCHAR(255) DEFAULT NULL AFTER completion_source");
    $conn->query("ALTER TABLE student_completed_subjects ADD COLUMN IF NOT EXISTS previous_grade VARCHAR(50) DEFAULT NULL AFTER previous_subject_name");

    $conn->query("
        CREATE TABLE IF NOT EXISTS student_subject_enrollments (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT(10) UNSIGNED NOT NULL,
            subject_id INT(10) UNSIGNED NOT NULL,
            section_id INT(11) DEFAULT NULL,
            academic_year_id INT(10) UNSIGNED NOT NULL,
            status ENUM('enrolled','completed','dropped') NOT NULL DEFAULT 'enrolled',
            enrollment_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular',
            recorded_by INT(10) UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_student_subject_ay (student_id, subject_id, academic_year_id),
            KEY idx_student_status (student_id, status),
            KEY idx_subject_status (subject_id, status),
            KEY idx_section_subject_status (section_id, subject_id, status),
            KEY idx_academic_year (academic_year_id),
            KEY idx_recorded_by (recorded_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->query("ALTER TABLE student_subject_enrollments MODIFY COLUMN enrollment_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular'");
}

function normalizeStudentType($student_type) {
    $student_type = strtolower(trim((string)$student_type));
    if (!in_array($student_type, ['regular', 'irregular', 'transferee'], true)) {
        return 'regular';
    }
    return $student_type;
}
