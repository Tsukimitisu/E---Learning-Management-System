<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_REGISTRAR) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

ensureIrregularSupportSchema($conn);

// Get registrar's branch
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = " . (int)$_SESSION['user_id'])->fetch_assoc();
$branch_id = (int)($registrar_profile['branch_id'] ?? 0);

// Get current academic year
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = (int)($current_ay['id'] ?? 0);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'enroll_program':
        enrollInProgram();
        break;
    case 'enroll_program_irregular':
        enrollIrregularProgram();
        break;
    case 'bulk_enroll_program':
        bulkEnrollProgram();
        break;
    case 'get_student_info':
        getStudentInfo();
        break;
    case 'get_subjects_for_enrollment':
        getSubjectsForEnrollment();
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
    $student_type = normalizeStudentType($_POST['student_type'] ?? 'regular');
    $previous_school = clean_input($_POST['previous_school'] ?? '');
    $completed_subject_ids = parseIdList($_POST['completed_subject_ids'] ?? '[]');

    if (!$student_id || !$program_type || !$program_id || !$year_level_id) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    if (!verifyStudentInRegistrarBranch($student_id, $branch_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student']);
        return;
    }

    if (!verifyProgram($program_type, $program_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid program']);
        return;
    }

    $conn->begin_transaction();

    try {
        $result = applyProgramEnrollment(
            $student_id,
            $program_type,
            $program_id,
            $year_level_id,
            $student_type,
            $previous_school,
            $completed_subject_ids,
            (int)$_SESSION['user_id'],
            $current_ay_id
        );

        $action_text = "Program enrollment: student {$student_id}, type {$student_type}, program {$program_id}, level {$year_level_id}, enrolled_subjects {$result['enrolled_count']}";
        logAuditSimple($conn, $action_text);

        $conn->commit();

        $message = "Student enrolled successfully. {$result['enrolled_count']} subject(s) enrolled";
        if ($result['completed_count'] > 0) {
            $message .= ", {$result['completed_count']} marked completed from previous school";
        }
        $message .= '.';

        echo json_encode([
            'success' => true,
            'message' => $message,
            'meta' => $result
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function enrollIrregularProgram() {
    global $conn, $current_ay_id, $branch_id;

    $student_id = (int)($_POST['student_id'] ?? 0);
    $program_type = $_POST['program_type'] ?? '';
    $program_id = (int)($_POST['program_id'] ?? 0);
    $year_level_id = (int)($_POST['year_level_id'] ?? 0);
    $student_type = normalizeStudentType($_POST['student_type'] ?? 'irregular');
    $previous_school = clean_input($_POST['previous_school'] ?? '');
    $completed_subject_ids = parseIdList($_POST['completed_subject_ids'] ?? '[]');

    if (!in_array($student_type, ['irregular', 'transferee'], true)) {
        $student_type = 'irregular';
    }

    if (!$student_id || !$program_type || !$program_id || !$year_level_id) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    if (!verifyStudentInRegistrarBranch($student_id, $branch_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student']);
        return;
    }

    if (!verifyProgram($program_type, $program_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid program']);
        return;
    }

    $conn->begin_transaction();
    try {
        $result = applyProgramEnrollment(
            $student_id,
            $program_type,
            $program_id,
            $year_level_id,
            $student_type,
            $previous_school,
            $completed_subject_ids,
            (int)$_SESSION['user_id'],
            $current_ay_id
        );

        $action_text = "Irregular enrollment: student {$student_id}, type {$student_type}, program {$program_id}, level {$year_level_id}, enrolled_subjects {$result['enrolled_count']}, completed {$result['completed_count']}";
        logAuditSimple($conn, $action_text);

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "Irregular enrollment saved. {$result['enrolled_count']} subject(s) queued for current enrollment.",
            'meta' => $result
        ]);
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
    $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);
    $student_type = normalizeStudentType($_POST['student_type'] ?? 'regular');

    if (!$program_type || !$program_id || !$year_level_id || empty($student_ids)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    if (!verifyProgram($program_type, $program_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid program']);
        return;
    }

    $conn->begin_transaction();

    try {
        $enrolled_count = 0;
        $skipped = 0;

        foreach ($student_ids as $student_id) {
            $student_id = (int)$student_id;
            if ($student_id <= 0) {
                $skipped++;
                continue;
            }

            if (!verifyStudentInRegistrarBranch($student_id, $branch_id)) {
                $skipped++;
                continue;
            }

            applyProgramEnrollment(
                $student_id,
                $program_type,
                $program_id,
                $year_level_id,
                $student_type,
                '',
                [],
                (int)$_SESSION['user_id'],
                $current_ay_id
            );
            $enrolled_count++;
        }

        logAuditSimple($conn, "Bulk program enrollment: {$enrolled_count} student(s), program {$program_id}, level {$year_level_id}, type {$student_type}");

        $conn->commit();
        $message = "{$enrolled_count} student(s) enrolled successfully";
        if ($skipped > 0) {
            $message .= "; {$skipped} skipped";
        }
        $message .= '.';
        echo json_encode(['success' => true, 'message' => $message]);
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
            st.student_type,
            st.previous_school,
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

function getSubjectsForEnrollment() {
    global $conn, $branch_id, $current_ay_id;

    $program_type = $_GET['program_type'] ?? '';
    $program_id = (int)($_GET['program_id'] ?? 0);
    $year_level_id = (int)($_GET['year_level_id'] ?? 0);
    $student_id = (int)($_GET['student_id'] ?? 0);

    if (!$program_type || !$program_id || !$year_level_id) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }

    $subjects = getCurriculumSubjectsForLevel($program_type, $program_id, $year_level_id);
    if (empty($subjects)) {
        echo json_encode(['success' => true, 'subjects' => []]);
        return;
    }

    $subject_ids = array_map(static function ($row) {
        return (int)$row['id'];
    }, $subjects);

    $completed_map = [];
    $enrolled_map = [];

    if ($student_id > 0 && verifyStudentInRegistrarBranch($student_id, $branch_id)) {
        $completed_sql = "
            SELECT subject_id 
            FROM student_completed_subjects 
            WHERE student_id = ? AND subject_id IN (" . placeholders(count($subject_ids)) . ")
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

        $enrolled_sql = "
            SELECT subject_id 
            FROM student_subject_enrollments 
            WHERE student_id = ? AND academic_year_id = ? 
              AND subject_id IN (" . placeholders(count($subject_ids)) . ")
              AND status IN ('enrolled','completed')
        ";
        $enrolled_stmt = $conn->prepare($enrolled_sql);
        $enrolled_types = 'ii' . str_repeat('i', count($subject_ids));
        $enrolled_params = array_merge([$student_id, $current_ay_id], $subject_ids);
        $enrolled_stmt->bind_param($enrolled_types, ...$enrolled_params);
        $enrolled_stmt->execute();
        $enrolled_result = $enrolled_stmt->get_result();
        while ($row = $enrolled_result->fetch_assoc()) {
            $enrolled_map[(int)$row['subject_id']] = true;
        }
    }

    $payload = [];
    foreach ($subjects as $subject) {
        $sid = (int)$subject['id'];
        $subject['already_completed'] = isset($completed_map[$sid]);
        $subject['already_enrolled'] = isset($enrolled_map[$sid]);
        $payload[] = $subject;
    }

    echo json_encode(['success' => true, 'subjects' => $payload]);
}

function applyProgramEnrollment($student_id, $program_type, $program_id, $year_level_id, $student_type, $previous_school, $completed_subject_ids, $recorded_by, $current_ay_id) {
    global $conn;

    $subjects = getCurriculumSubjectsForLevel($program_type, $program_id, $year_level_id);
    if (empty($subjects)) {
        throw new Exception('No curriculum subjects found for selected level.');
    }

    $subject_ids = array_map(static function ($row) {
        return (int)$row['id'];
    }, $subjects);

    // Upsert student core record
    $student_check = $conn->prepare("SELECT user_id FROM students WHERE user_id = ?");
    $student_check->bind_param("i", $student_id);
    $student_check->execute();
    $exists = $student_check->get_result()->num_rows > 0;

    if ($exists) {
        $stmt = $conn->prepare("
            UPDATE students 
            SET course_id = ?, student_type = ?, previous_school = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param("issi", $program_id, $student_type, $previous_school, $student_id);
    } else {
        $student_no = generateStudentNumber($conn);
        $stmt = $conn->prepare("
            INSERT INTO students (user_id, student_no, course_id, student_type, previous_school)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isiss", $student_id, $student_no, $program_id, $student_type, $previous_school);
    }

    if (!$stmt->execute()) {
        throw new Exception('Failed to update student enrollment profile.');
    }

    // Normalize completed-subject list to selected curriculum only
    $valid_subject_set = array_flip($subject_ids);
    $completed_subject_ids = array_values(array_unique(array_filter(array_map('intval', $completed_subject_ids), static function ($id) use ($valid_subject_set) {
        return isset($valid_subject_set[$id]);
    })));

    // Save completed-subject declarations for irregular/transferee
    $completed_count = 0;
    if (in_array($student_type, ['irregular', 'transferee'], true) && !empty($completed_subject_ids)) {
        $insert_completed = $conn->prepare("
            INSERT INTO student_completed_subjects (student_id, subject_id, completion_source, recorded_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                completion_source = VALUES(completion_source),
                recorded_by = VALUES(recorded_by)
        ");
        $source = $previous_school ?: 'Previous school';
        foreach ($completed_subject_ids as $sid) {
            $insert_completed->bind_param("iisi", $student_id, $sid, $source, $recorded_by);
            $insert_completed->execute();
            $completed_count++;
        }
    }

    // Get full completed-subject map for selected set
    $completed_map = [];
    if (!empty($subject_ids)) {
        $completed_sql = "
            SELECT subject_id FROM student_completed_subjects
            WHERE student_id = ? AND subject_id IN (" . placeholders(count($subject_ids)) . ")
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
    }

    // Build target enrollment list (exclude completed subjects)
    $target_subject_ids = array_values(array_filter($subject_ids, static function ($sid) use ($completed_map) {
        return !isset($completed_map[$sid]);
    }));

    // Exclude already enrolled/completed for this AY to prevent duplicates
    $existing_map = [];
    if (!empty($target_subject_ids)) {
        $existing_sql = "
            SELECT subject_id FROM student_subject_enrollments
            WHERE student_id = ? AND academic_year_id = ?
              AND subject_id IN (" . placeholders(count($target_subject_ids)) . ")
              AND status IN ('enrolled','completed')
        ";
        $existing_stmt = $conn->prepare($existing_sql);
        $existing_types = 'ii' . str_repeat('i', count($target_subject_ids));
        $existing_params = array_merge([$student_id, $current_ay_id], $target_subject_ids);
        $existing_stmt->bind_param($existing_types, ...$existing_params);
        $existing_stmt->execute();
        $existing_result = $existing_stmt->get_result();
        while ($row = $existing_result->fetch_assoc()) {
            $existing_map[(int)$row['subject_id']] = true;
        }
    }

    $to_enroll = array_values(array_filter($target_subject_ids, static function ($sid) use ($existing_map) {
        return !isset($existing_map[$sid]);
    }));

    // Drop currently-enrolled rows for newly marked-completed subjects in this AY
    if (!empty($completed_map)) {
        $completed_ids = array_keys($completed_map);
        $drop_sql = "
            UPDATE student_subject_enrollments
            SET status = 'dropped', updated_at = NOW()
            WHERE student_id = ? AND academic_year_id = ?
              AND status = 'enrolled'
              AND subject_id IN (" . placeholders(count($completed_ids)) . ")
        ";
        $drop_stmt = $conn->prepare($drop_sql);
        $drop_types = 'ii' . str_repeat('i', count($completed_ids));
        $drop_params = array_merge([$student_id, $current_ay_id], $completed_ids);
        $drop_stmt->bind_param($drop_types, ...$drop_params);
        $drop_stmt->execute();
    }

    // Insert subject enrollments
    $enrolled_count = 0;
    if (!empty($to_enroll)) {
        $insert_enroll = $conn->prepare("
            INSERT INTO student_subject_enrollments
                (student_id, subject_id, section_id, academic_year_id, status, enrollment_type, recorded_by)
            VALUES (?, ?, NULL, ?, 'enrolled', ?, ?)
            ON DUPLICATE KEY UPDATE
                status = 'enrolled',
                enrollment_type = VALUES(enrollment_type),
                recorded_by = VALUES(recorded_by),
                updated_at = NOW()
        ");
        foreach ($to_enroll as $sid) {
            $insert_enroll->bind_param("iiisi", $student_id, $sid, $current_ay_id, $student_type, $recorded_by);
            $insert_enroll->execute();
            $enrolled_count++;
        }
    }

    return [
        'student_type' => $student_type,
        'total_subjects' => count($subject_ids),
        'completed_count' => count($completed_map),
        'enrolled_count' => $enrolled_count,
        'skipped_duplicate_count' => count($existing_map),
    ];
}

function getCurriculumSubjectsForLevel($program_type, $program_id, $year_level_id) {
    global $conn;

    if ($program_type === 'college') {
        $stmt = $conn->prepare("
            SELECT id, subject_code, subject_title, units, semester
            FROM curriculum_subjects
            WHERE program_id = ? AND year_level_id = ? AND is_active = 1
            ORDER BY semester, subject_code
        ");
        $stmt->bind_param("ii", $program_id, $year_level_id);
    } else {
        $stmt = $conn->prepare("
            SELECT id, subject_code, subject_title, units, semester
            FROM curriculum_subjects
            WHERE shs_strand_id = ? AND shs_grade_level_id = ? AND is_active = 1
            ORDER BY semester, subject_code
        ");
        $stmt->bind_param("ii", $program_id, $year_level_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function verifyStudentInRegistrarBranch($student_id, $branch_id) {
    global $conn;

    $check = $conn->prepare("
        SELECT u.id
        FROM users u
        INNER JOIN user_profiles up ON u.id = up.user_id
        INNER JOIN user_roles ur ON u.id = ur.user_id
        WHERE u.id = ? AND ur.role_id = ? AND up.branch_id = ?
    ");
    $check->bind_param("iii", $student_id, ROLE_STUDENT, $branch_id);
    $check->execute();
    return $check->get_result()->num_rows > 0;
}

function verifyProgram($program_type, $program_id) {
    global $conn;

    if ($program_type === 'college') {
        $stmt = $conn->prepare("SELECT id FROM programs WHERE id = ? AND is_active = 1");
    } else {
        $stmt = $conn->prepare("SELECT id FROM shs_strands WHERE id = ? AND is_active = 1");
    }
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function parseIdList($raw) {
    $ids = json_decode($raw, true);
    if (!is_array($ids)) {
        return [];
    }
    return array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
        return $id > 0;
    })));
}

function normalizeStudentType($student_type) {
    $student_type = strtolower(trim((string)$student_type));
    if (!in_array($student_type, ['regular', 'irregular', 'transferee'], true)) {
        return 'regular';
    }
    return $student_type;
}

function placeholders($count) {
    return implode(',', array_fill(0, max(1, (int)$count), '?'));
}

function logAuditSimple($conn, $action_text) {
    $log = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, ip_address, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uid = (int)$_SESSION['user_id'];
    $log->bind_param("iss", $uid, $action_text, $ip);
    $log->execute();
}

function generateStudentNumber($conn) {
    $year = date('Y');
    $prefix = "STU-$year-";

    // students table has no numeric id column, so order by user_id
    $result = $conn->query("SELECT student_no FROM students WHERE student_no LIKE '$prefix%' ORDER BY user_id DESC LIMIT 1");

    if ($row = $result->fetch_assoc()) {
        $last_num = (int)str_replace($prefix, '', $row['student_no']);
        $new_num = $last_num + 1;
    } else {
        $new_num = 1;
    }

    return $prefix . str_pad($new_num, 5, '0', STR_PAD_LEFT);
}

function ensureIrregularSupportSchema($conn) {
    // Keep idempotent and lightweight for compatibility in environments
    // where migrations haven't been applied yet.
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular' AFTER course_id");
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS previous_school VARCHAR(255) DEFAULT NULL AFTER student_type");

    $conn->query("
        CREATE TABLE IF NOT EXISTS student_completed_subjects (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT(10) UNSIGNED NOT NULL,
            subject_id INT(10) UNSIGNED NOT NULL,
            completion_source VARCHAR(255) DEFAULT NULL,
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
}

