<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$student_id = (int)($_POST['student_id'] ?? 0);
$section_id = (int)($_POST['section_id'] ?? 0);
$subject_id = (int)($_POST['subject_id'] ?? 0);
$midterm = floatval($_POST['midterm'] ?? 0);
$final = floatval($_POST['final'] ?? 0);
$final_grade = floatval($_POST['final_grade'] ?? 0);
$remarks = clean_input($_POST['remarks'] ?? '');
$grade_id = (int)($_POST['grade_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];
$current_version = (int)($_POST['version'] ?? 1);

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($student_id == 0 || $section_id == 0 || $subject_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit();
}

// Verify teacher is assigned to this subject
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Check if grading periods are locked
$grading_periods = [];
if ($midterm > 0) $grading_periods[] = 'midterm';
if ($final > 0) $grading_periods[] = 'final';

if (!empty($grading_periods)) {
    $placeholders = str_repeat('?,', count($grading_periods) - 1) . '?';
    $check_lock = $conn->prepare("
        SELECT grading_period, is_locked, locked_by
        FROM grade_locks
        WHERE section_id = ? AND subject_id = ? AND grading_period IN ($placeholders) AND is_locked = 1
    ");
    $types = "ii" . str_repeat('s', count($grading_periods));
    $params = array_merge([$section_id, $subject_id], $grading_periods);
    $check_lock->bind_param($types, ...$params);
    $check_lock->execute();
    $locked_periods = $check_lock->get_result();

    if ($locked_periods->num_rows > 0) {
        $locked_list = [];
        while ($row = $locked_periods->fetch_assoc()) {
            $locked_list[] = ucfirst($row['grading_period']);
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot update grades. The following grading periods are locked: ' . implode(', ', $locked_list)
        ]);
        exit();
    }
}

try {
    // Start transaction for pessimistic locking
    $conn->begin_transaction();

    if ($grade_id > 0) {
        // Pessimistic locking: Select for update to prevent concurrent modifications
        $lock_stmt = $conn->prepare("
            SELECT id, version FROM grades
            WHERE id = ? AND student_id = ? AND section_id = ? AND subject_id = ?
            FOR UPDATE
        ");
        $lock_stmt->bind_param("iiii", $grade_id, $student_id, $section_id, $subject_id);
        $lock_stmt->execute();
        $result = $lock_stmt->get_result();

        if ($result->num_rows == 0) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Grade record not found']);
            exit();
        }

        $current_grade = $result->fetch_assoc();

        // Optimistic locking: Check version
        if ($current_grade['version'] != $current_version) {
            $conn->rollback();
            echo json_encode([
                'status' => 'error',
                'message' => 'Grade was modified by another user. Please refresh and try again.',
                'conflict' => true
            ]);
            exit();
        }

        // Update existing grade with version increment
        $stmt = $conn->prepare("
            UPDATE grades
            SET midterm = ?, final = ?, final_grade = ?, remarks = ?, version = version + 1
            WHERE id = ? AND student_id = ? AND section_id = ? AND subject_id = ?
        ");
        $stmt->bind_param("dddsiiii", $midterm, $final, $final_grade, $remarks, $grade_id, $student_id, $section_id, $subject_id);
        $stmt->execute();

        $return_grade_id = $grade_id;
    } else {
        // Check if grade exists already
        $check_existing = $conn->prepare("SELECT id FROM grades WHERE student_id = ? AND section_id = ? AND subject_id = ?");
        $check_existing->bind_param("iii", $student_id, $section_id, $subject_id);
        $check_existing->execute();
        $existing = $check_existing->get_result()->fetch_assoc();
        
        if ($existing) {
            // Update existing
            $stmt = $conn->prepare("
                UPDATE grades 
                SET midterm = ?, final = ?, final_grade = ?, remarks = ?, version = version + 1
                WHERE id = ?
            ");
            $stmt->bind_param("dddsi", $midterm, $final, $final_grade, $remarks, $existing['id']);
            $stmt->execute();
            $return_grade_id = $existing['id'];
        } else {
            // Insert new grade
            $stmt = $conn->prepare("
                INSERT INTO grades (student_id, section_id, subject_id, midterm, final, final_grade, remarks, version)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("iiiddds", $student_id, $section_id, $subject_id, $midterm, $final, $final_grade, $remarks);
            $stmt->execute();
            $return_grade_id = $conn->insert_id;
        }
    }

    $conn->commit();
    
    // Log audit
    $ip = get_client_ip();
    $action = "Updated grade for student ID $student_id in section ID $section_id, subject ID $subject_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $teacher_id, $action, $ip);
    $audit->execute();

    // Get the updated version for optimistic locking
    $version_stmt = $conn->prepare("SELECT version FROM grades WHERE id = ?");
    $version_stmt->bind_param("i", $return_grade_id);
    $version_stmt->execute();
    $version_result = $version_stmt->get_result()->fetch_assoc();

    echo json_encode([
        'status' => 'success',
        'message' => 'Grade saved successfully',
        'grade_id' => $return_grade_id,
        'version' => $version_result['version']
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Failed to save grade: ' . $e->getMessage()]);
}
?>