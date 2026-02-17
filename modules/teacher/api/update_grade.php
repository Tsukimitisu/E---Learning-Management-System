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
$prelim = floatval($_POST['prelim'] ?? 0);
$midterm = floatval($_POST['midterm'] ?? 0);
$prefinal = floatval($_POST['prefinal'] ?? 0);
$final = floatval($_POST['final'] ?? 0);
$final_grade = floatval($_POST['final_grade'] ?? 0);
$remarks = clean_input($_POST['remarks'] ?? '');
$notes = clean_input($_POST['notes'] ?? '');
$grade_id = (int)($_POST['grade_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];
$current_version = (int)($_POST['version'] ?? 0);

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
if ($prelim > 0) $grading_periods[] = 'prelim';
if ($midterm > 0) $grading_periods[] = 'midterm';
if ($prefinal > 0) $grading_periods[] = 'prefinal';
if ($final > 0) $grading_periods[] = 'final';

if (!empty($grading_periods)) {
    // Support both schemas:
    // 1) New lock format: section_id + subject_id
    // 2) Legacy lock format: class_id
    $has_section_id = false;
    $has_subject_id = false;
    $has_class_id = false;

    if ($col = $conn->query("SHOW COLUMNS FROM grade_locks LIKE 'section_id'")) {
        $has_section_id = $col->num_rows > 0;
    }
    if ($col = $conn->query("SHOW COLUMNS FROM grade_locks LIKE 'subject_id'")) {
        $has_subject_id = $col->num_rows > 0;
    }
    if ($col = $conn->query("SHOW COLUMNS FROM grade_locks LIKE 'class_id'")) {
        $has_class_id = $col->num_rows > 0;
    }

    $locked_list = [];
    foreach ($grading_periods as $period) {
        $check_lock = null;

        if ($has_section_id && $has_subject_id) {
            $check_lock = $conn->prepare("
                SELECT 1 FROM grade_locks
                WHERE section_id = ? AND subject_id = ? AND grading_period = ? AND is_locked = 1
                LIMIT 1
            ");
            if ($check_lock) {
                $check_lock->bind_param("iis", $section_id, $subject_id, $period);
            }
        } elseif ($has_class_id) {
            // Fallback for legacy schema where class_id is used for section context
            $check_lock = $conn->prepare("
                SELECT 1 FROM grade_locks
                WHERE class_id = ? AND grading_period = ? AND is_locked = 1
                LIMIT 1
            ");
            if ($check_lock) {
                $check_lock->bind_param("is", $section_id, $period);
            }
        }

        if ($check_lock) {
            $check_lock->execute();
            if ($check_lock->get_result()->num_rows > 0) {
                $locked_list[] = ucfirst($period);
            }
        }
    }

    if (!empty($locked_list)) {
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

        // Optimistic locking: only enforce when client sends a version (>0)
        if ($current_version > 0 && $current_grade['version'] != $current_version) {
            $conn->rollback();
            echo json_encode([
                'status' => 'error',
                'message' => 'Grade was modified by another user. Please refresh and try again.',
                'conflict' => true
            ]);
            exit();
        }

        // Update existing grade with version increment (including all 4 terms)
        $stmt = $conn->prepare("
            UPDATE grades
            SET prelim = ?, midterm = ?, prefinal = ?, final = ?, final_grade = ?, remarks = ?, notes = ?, academic_year_id = ?, version = version + 1
            WHERE id = ? AND student_id = ? AND section_id = ? AND subject_id = ?
        ");
        $stmt->bind_param("dddddssiiiii", $prelim, $midterm, $prefinal, $final, $final_grade, $remarks, $notes, $current_ay_id, $grade_id, $student_id, $section_id, $subject_id);
        $stmt->execute();

        $return_grade_id = $grade_id;
    } else {
        // Check if grade exists already
        $check_existing = $conn->prepare("SELECT id FROM grades WHERE student_id = ? AND section_id = ? AND subject_id = ?");
        $check_existing->bind_param("iii", $student_id, $section_id, $subject_id);
        $check_existing->execute();
        $existing = $check_existing->get_result()->fetch_assoc();
        
        if ($existing) {
            // Update existing (including all 4 terms)
            $stmt = $conn->prepare("
                UPDATE grades
                SET prelim = ?, midterm = ?, prefinal = ?, final = ?, final_grade = ?, remarks = ?, notes = ?, academic_year_id = ?, version = version + 1
                WHERE id = ?
            ");
            $stmt->bind_param("dddddssii", $prelim, $midterm, $prefinal, $final, $final_grade, $remarks, $notes, $current_ay_id, $existing['id']);
            $stmt->execute();
            $return_grade_id = $existing['id'];
        } else {
            // Insert new grade (including all 4 terms)
            $stmt = $conn->prepare("
                INSERT INTO grades (student_id, section_id, subject_id, academic_year_id, prelim, midterm, prefinal, final, final_grade, remarks, notes, version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("iiiidddddss", $student_id, $section_id, $subject_id, $current_ay_id, $prelim, $midterm, $prefinal, $final, $final_grade, $remarks, $notes);
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
