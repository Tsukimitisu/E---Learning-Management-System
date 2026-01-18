<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_REGISTRAR) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get registrar's branch
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = " . $_SESSION['user_id'])->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;

// Get current academic year
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'enroll_program':
        enrollInProgram();
        break;
    case 'bulk_enroll_program':
        bulkEnrollProgram();
        break;
    case 'get_student_info':
        getStudentInfo();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function enrollInProgram() {
    global $conn, $branch_id, $current_ay_id;
    
    $student_id = (int)($_POST['student_id'] ?? 0);
    $program_type = $_POST['program_type'] ?? '';
    $program_id = (int)($_POST['program_id'] ?? 0);
    $year_level_id = (int)($_POST['year_level_id'] ?? 0);
    
    if (!$student_id || !$program_type || !$program_id || !$year_level_id) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Verify student exists and belongs to this branch
    $check = $conn->prepare("
        SELECT u.id FROM users u
        INNER JOIN user_profiles up ON u.id = up.user_id
        INNER JOIN user_roles ur ON u.id = ur.user_id
        WHERE u.id = ? AND ur.role_id = ? AND up.branch_id = ?
    ");
    $check->bind_param("iii", $student_id, ROLE_STUDENT, $branch_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid student']);
        return;
    }
    
    // Verify program exists
    if ($program_type === 'college') {
        $prog_check = $conn->query("SELECT id FROM programs WHERE id = $program_id AND is_active = 1");
    } else {
        $prog_check = $conn->query("SELECT id FROM shs_strands WHERE id = $program_id AND is_active = 1");
    }
    
    if ($prog_check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid program']);
        return;
    }
    
    // Check if student record exists
    $student_check = $conn->query("SELECT user_id FROM students WHERE user_id = $student_id");
    
    $conn->begin_transaction();
    
    try {
        if ($student_check->num_rows > 0) {
            // Update existing student record
            $stmt = $conn->prepare("
                UPDATE students 
                SET course_id = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param("ii", $program_id, $student_id);
        } else {
            // Create new student record
            $student_no = generateStudentNumber($conn);
            $stmt = $conn->prepare("
                INSERT INTO students (user_id, student_no, course_id)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("isi", $student_id, $student_no, $program_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update student enrollment");
        }
        
        // Log the action
        $log = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, ip_address, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $action_text = "Enrolled student ID $student_id in program $program_id";
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log->bind_param("iss", $_SESSION['user_id'], $action_text, $ip);
        $log->execute();
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Student enrolled in program successfully. Branch Admin can now assign to sections.']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function bulkEnrollProgram() {
    global $conn, $branch_id, $current_ay_id;
    
    $program_type = $_POST['program_type'] ?? '';
    $program_id = (int)($_POST['program_id'] ?? 0);
    $year_level_id = (int)($_POST['year_level_id'] ?? 0);
    $year_level = (int)($_POST['year_level'] ?? 1);
    $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);
    
    if (!$program_type || !$program_id || !$year_level_id || empty($student_ids)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Verify program exists
    if ($program_type === 'college') {
        $prog_check = $conn->query("SELECT id FROM programs WHERE id = $program_id AND is_active = 1");
    } else {
        $prog_check = $conn->query("SELECT id FROM shs_strands WHERE id = $program_id AND is_active = 1");
    }
    
    if ($prog_check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid program']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $enrolled_count = 0;
        
        foreach ($student_ids as $student_id) {
            $student_id = (int)$student_id;
            
            // Verify student belongs to branch
            $check = $conn->prepare("
                SELECT u.id FROM users u
                INNER JOIN user_profiles up ON u.id = up.user_id
                INNER JOIN user_roles ur ON u.id = ur.user_id
                WHERE u.id = ? AND ur.role_id = ? AND up.branch_id = ?
            ");
            $check->bind_param("iii", $student_id, ROLE_STUDENT, $branch_id);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                continue; // Skip invalid students
            }
            
            // Check if student record exists
            $student_check = $conn->query("SELECT user_id FROM students WHERE user_id = $student_id");
            
            if ($student_check->num_rows > 0) {
                // Update existing
                $stmt = $conn->prepare("
                    UPDATE students 
                    SET course_id = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param("ii", $program_id, $student_id);
            } else {
                // Create new
                $student_no = generateStudentNumber($conn);
                $stmt = $conn->prepare("
                    INSERT INTO students (user_id, student_no, course_id)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("isi", $student_id, $student_no, $program_id);
            }
            
            if ($stmt->execute()) {
                $enrolled_count++;
            }
        }
        
        // Log the action
        $log = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, ip_address, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $action_text = "Bulk enrolled $enrolled_count students in program $program_id";
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log->bind_param("iss", $_SESSION['user_id'], $action_text, $ip);
        $log->execute();
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => "$enrolled_count student(s) enrolled successfully. Branch Admin can now assign them to sections."]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getStudentInfo() {
    global $conn, $branch_id;
    
    $student_id = (int)($_GET['student_id'] ?? 0);
    
    $query = "
        SELECT 
            u.id,
            up.first_name,
            up.last_name,
            COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
            st.course_id,
            COALESCE(p.program_code, ss.strand_code) as program_code,
            COALESCE(p.program_name, ss.strand_name) as program_name
        FROM users u
        INNER JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN students st ON u.id = st.user_id
        LEFT JOIN programs p ON st.course_id = p.id
        LEFT JOIN shs_strands ss ON st.course_id = ss.id
        WHERE u.id = ? AND up.branch_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $student_id, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'student' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
}

function generateStudentNumber($conn) {
    $year = date('Y');
    $prefix = "STU-$year-";
    
    // Get the last student number for this year
    $result = $conn->query("SELECT student_no FROM students WHERE student_no LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
    
    if ($row = $result->fetch_assoc()) {
        $last_num = (int)str_replace($prefix, '', $row['student_no']);
        $new_num = $last_num + 1;
    } else {
        $new_num = 1;
    }
    
    return $prefix . str_pad($new_num, 5, '0', STR_PAD_LEFT);
}
