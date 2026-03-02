<?php
/**
 * SHS Grade Update API
 * Handles quarter-based grading with whole-number enforcement
 */
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$student_id = (int)($_POST['student_id'] ?? 0);
$section_id = (int)($_POST['section_id'] ?? 0);
$subject_id = (int)($_POST['subject_id'] ?? 0);
$grade_id = (int)($_POST['grade_id'] ?? 0);
$current_version = (int)($_POST['version'] ?? 0);
$semester = (int)($_POST['semester'] ?? 1);
$quarter = $_POST['quarter'] ?? 'q1';
$notes = clean_input($_POST['notes'] ?? '');
$teacher_id = $_SESSION['user_id'];

// Parse quarter grades - enforce whole numbers
$q1 = $_POST['q1_grade'] !== '' ? (int)round(floatval($_POST['q1_grade'] ?? '')) : null;
$q2 = $_POST['q2_grade'] !== '' ? (int)round(floatval($_POST['q2_grade'] ?? '')) : null;
$q3 = $_POST['q3_grade'] !== '' ? (int)round(floatval($_POST['q3_grade'] ?? '')) : null;
$q4 = $_POST['q4_grade'] !== '' ? (int)round(floatval($_POST['q4_grade'] ?? '')) : null;

// Validate ranges (0-100)
foreach (['q1' => $q1, 'q2' => $q2, 'q3' => $q3, 'q4' => $q4] as $label => $val) {
    if ($val !== null && ($val < 0 || $val > 100)) {
        echo json_encode(['status' => 'error', 'message' => "Invalid grade for $label: must be 0-100"]);
        exit();
    }
}

// Get current academic year
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = (int)($current_ay['id'] ?? 0);

if ($student_id == 0 || $section_id == 0 || $subject_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit();
}

// Block credited subjects
$check_credited = $conn->prepare("SELECT status FROM student_subject_enrollments WHERE student_id = ? AND subject_id = ? AND section_id = ? AND status = 'credited' LIMIT 1");
if ($check_credited) {
    $check_credited->bind_param("iii", $student_id, $subject_id, $section_id);
    $check_credited->execute();
    if ($check_credited->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Cannot enter grades for a credited subject.']);
        exit();
    }
}

// Verify teacher assignment
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: not assigned to this subject']);
    exit();
}

// Compute semester finals (rounded whole numbers)
$sem1_final = ($q1 !== null && $q2 !== null) ? (int)round(($q1 + $q2) / 2) : null;
$sem2_final = ($q3 !== null && $q4 !== null) ? (int)round(($q3 + $q4) / 2) : null;

// Compute subject final grade
$final_grade = null;
if ($sem1_final !== null && $sem2_final !== null) {
    $final_grade = (int)round(($sem1_final + $sem2_final) / 2);
} elseif ($sem1_final !== null) {
    $final_grade = $sem1_final;
} elseif ($sem2_final !== null) {
    $final_grade = $sem2_final;
}

// Determine remarks
$remarks = '';
if ($final_grade !== null) {
    if ($final_grade >= 75) {
        $remarks = 'passed';
    } elseif ($final_grade >= 70) {
        $remarks = 'with_remedial';
    } else {
        $remarks = 'failed';
    }
}

try {
    $conn->begin_transaction();

    if ($grade_id > 0) {
        // Pessimistic lock + optimistic version check
        $lock = $conn->prepare("SELECT id, version FROM shs_grades WHERE id = ? AND student_id = ? AND section_id = ? AND subject_id = ? FOR UPDATE");
        $lock->bind_param("iiii", $grade_id, $student_id, $section_id, $subject_id);
        $lock->execute();
        $existing = $lock->get_result()->fetch_assoc();

        if (!$existing) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Grade record not found']);
            exit();
        }

        if ($current_version > 0 && $existing['version'] != $current_version) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Grade was modified by another user. Please refresh.', 'conflict' => true]);
            exit();
        }

        $stmt = $conn->prepare("
            UPDATE shs_grades SET 
                q1_grade = ?, q2_grade = ?, q3_grade = ?, q4_grade = ?,
                sem1_final_grade = ?, sem2_final_grade = ?, final_grade = ?,
                semester = ?, remarks = ?, notes = ?, version = version + 1
            WHERE id = ?
        ");
        $q1_db = $q1; $q2_db = $q2; $q3_db = $q3; $q4_db = $q4;
        $sem1_db = $sem1_final; $sem2_db = $sem2_final; $final_db = $final_grade;
        
        // Use bind with proper null handling
        $stmt->bind_param("iiiiiiiiissi",
            $q1_db, $q2_db, $q3_db, $q4_db,
            $sem1_db, $sem2_db, $final_db,
            $semester, $remarks, $notes, $grade_id
        );
        // Handle NULLs by setting params after bind
        $params = [$q1_db, $q2_db, $q3_db, $q4_db, $sem1_db, $sem2_db, $final_db, $semester, $remarks, $notes, $grade_id];
        
        $stmt->execute();
        $return_grade_id = $grade_id;
    } else {
        // Check if record already exists
        $check = $conn->prepare("SELECT id FROM shs_grades WHERE student_id = ? AND section_id = ? AND subject_id = ? AND academic_year_id = ?");
        $check->bind_param("iiii", $student_id, $section_id, $subject_id, $current_ay_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            $stmt = $conn->prepare("
                UPDATE shs_grades SET 
                    q1_grade = ?, q2_grade = ?, q3_grade = ?, q4_grade = ?,
                    sem1_final_grade = ?, sem2_final_grade = ?, final_grade = ?,
                    semester = ?, remarks = ?, notes = ?, version = version + 1
                WHERE id = ?
            ");
            $stmt->bind_param("iiiiiiiiissi",
                $q1, $q2, $q3, $q4,
                $sem1_final, $sem2_final, $final_grade,
                $semester, $remarks, $notes, $existing['id']
            );
            $stmt->execute();
            $return_grade_id = $existing['id'];
        } else {
            $stmt = $conn->prepare("
                INSERT INTO shs_grades (student_id, section_id, subject_id, academic_year_id, semester, q1_grade, q2_grade, q3_grade, q4_grade, sem1_final_grade, sem2_final_grade, final_grade, remarks, notes, version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("iiiiiiiiiiiiss",
                $student_id, $section_id, $subject_id, $current_ay_id, $semester,
                $q1, $q2, $q3, $q4,
                $sem1_final, $sem2_final, $final_grade,
                $remarks, $notes
            );
            $stmt->execute();
            $return_grade_id = $conn->insert_id;
        }
    }

    $conn->commit();

    // Get final version
    $v_stmt = $conn->prepare("SELECT version, q1_grade, q2_grade, q3_grade, q4_grade, sem1_final_grade, sem2_final_grade, final_grade FROM shs_grades WHERE id = ?");
    $v_stmt->bind_param("i", $return_grade_id);
    $v_stmt->execute();
    $final_data = $v_stmt->get_result()->fetch_assoc();

    // Audit log
    $ip = get_client_ip();
    $action = "Updated SHS grade for student $student_id, section $section_id, subject $subject_id, quarter $quarter";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $teacher_id, $action, $ip);
    $audit->execute();

    // Notification
    create_notification($student_id, 'Grade Updated', 'A new SHS grade has been posted for one of your subjects.', 'grade', null, $teacher_id);

    echo json_encode([
        'status' => 'success',
        'message' => 'SHS grade saved successfully',
        'grade_id' => $return_grade_id,
        'version' => $final_data['version'],
        'q1' => $final_data['q1_grade'],
        'q2' => $final_data['q2_grade'],
        'q3' => $final_data['q3_grade'],
        'q4' => $final_data['q4_grade'],
        'sem1_final' => $final_data['sem1_final_grade'],
        'sem2_final' => $final_data['sem2_final_grade'],
        'final_grade' => $final_data['final_grade']
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Failed to save: ' . $e->getMessage()]);
}
