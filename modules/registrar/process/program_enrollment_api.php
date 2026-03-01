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
    case 'enroll_next_year':
        enrollNextYear();
        break;
    case 'get_student_balance':
        getStudentBalance();
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
    $semester = normalizeSemester($_POST['semester'] ?? '1st');
    $student_type = normalizeStudentType($_POST['student_type'] ?? 'regular');
    $previous_school = clean_input($_POST['previous_school'] ?? '');
    $completed_subject_ids = parseIdList($_POST['completed_subject_ids'] ?? '[]');
    $completed_subject_details = parseCompletedSubjectDetails($_POST['completed_subject_details'] ?? '{}');

    if (!$student_id || !$program_type || !$program_id || !$year_level_id) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    if ($current_ay_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'No active academic year. Please activate one before enrolling students.']);
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
            $semester,
            $student_type,
            $previous_school,
            $completed_subject_ids,
            $completed_subject_details,
            (int)$_SESSION['user_id'],
            $current_ay_id
        );

        $action_text = "Program enrollment: student {$student_id}, type {$student_type}, program {$program_id}, level {$year_level_id}, semester {$semester}, enrolled_subjects {$result['enrolled_count']}";
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
    $semester = normalizeSemester($_POST['semester'] ?? '1st');
    $student_type = normalizeStudentType($_POST['student_type'] ?? 'irregular');
    $previous_school = clean_input($_POST['previous_school'] ?? '');
    $completed_subject_ids = parseIdList($_POST['completed_subject_ids'] ?? '[]');
    $completed_subject_details = parseCompletedSubjectDetails($_POST['completed_subject_details'] ?? '{}');

    if (!in_array($student_type, ['irregular', 'transferee'], true)) {
        $student_type = 'irregular';
    }

    if (!$student_id || !$program_type || !$program_id || !$year_level_id) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    if ($current_ay_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'No active academic year. Please activate one before enrolling students.']);
        return;
    }

    if ($previous_school === '') {
        echo json_encode(['success' => false, 'message' => 'Previous school is required for non-regular enrollment']);
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
            $semester,
            $student_type,
            $previous_school,
            $completed_subject_ids,
            $completed_subject_details,
            (int)$_SESSION['user_id'],
            $current_ay_id
        );

        $action_text = "Non-regular enrollment: student {$student_id}, type {$student_type}, program {$program_id}, level {$year_level_id}, semester {$semester}, enrolled_subjects {$result['enrolled_count']}, completed {$result['completed_count']}";
        logAuditSimple($conn, $action_text);

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => ucfirst($student_type) . " enrollment saved. {$result['enrolled_count']} subject(s) queued for current enrollment.",
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
    $semester = normalizeSemester($_POST['semester'] ?? '1st');
    $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);
    $student_type = normalizeStudentType($_POST['student_type'] ?? 'regular');

    if (!$program_type || !$program_id || !$year_level_id || empty($student_ids)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    if ($current_ay_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'No active academic year. Please activate one before enrolling students.']);
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
                $semester,
                $student_type,
                '',
                [],
                [],
                (int)$_SESSION['user_id'],
                $current_ay_id
            );
            $enrolled_count++;
        }

        logAuditSimple($conn, "Bulk program enrollment: {$enrolled_count} student(s), program {$program_id}, level {$year_level_id}, semester {$semester}, type {$student_type}");

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
            CASE
                WHEN COALESCE(st.student_type, 'regular') = 'regular' THEN 'regular'
                WHEN st.student_type = 'transferee' THEN 'transferee'
                ELSE 'irregular'
            END as student_type,
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
    $semester = normalizeSemester($_GET['semester'] ?? '1st');
    $student_id = (int)($_GET['student_id'] ?? 0);

    if (!$program_type || !$program_id || !$year_level_id) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }

    $subjects = getCurriculumSubjectsForLevel($program_type, $program_id, $year_level_id, $semester);
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
            SELECT subject_id, previous_subject_name, previous_grade
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
            $completed_map[(int)$row['subject_id']] = [
                'previous_subject_name' => (string)($row['previous_subject_name'] ?? ''),
                'previous_grade' => (string)($row['previous_grade'] ?? '')
            ];
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
        $subject['previous_subject_name'] = $completed_map[$sid]['previous_subject_name'] ?? '';
        $subject['previous_grade'] = $completed_map[$sid]['previous_grade'] ?? '';
        $subject['already_enrolled'] = isset($enrolled_map[$sid]);
        $payload[] = $subject;
    }

    echo json_encode(['success' => true, 'subjects' => $payload]);
}

function enrollNextYear() {
    global $conn, $branch_id, $current_ay_id;

    $student_id = (int)($_POST['student_id'] ?? 0);
    $semester = normalizeSemester($_POST['semester'] ?? '1st');
    $student_type = normalizeStudentType($_POST['student_type'] ?? '');
    $previous_school = clean_input($_POST['previous_school'] ?? '');
    $completed_subject_ids = parseIdList($_POST['completed_subject_ids'] ?? '[]');
    $completed_subject_details = parseCompletedSubjectDetails($_POST['completed_subject_details'] ?? '{}');

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    if ($current_ay_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'No active academic year.']);
        return;
    }
    if (!verifyStudentInRegistrarBranch($student_id, $branch_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student']);
        return;
    }

    // Get current enrollment
    $current = getLatestTermEnrollment($student_id);
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Student has no current enrollment to advance from.']);
        return;
    }

    $program_type = $current['program_type'];
    $program_id = (int)$current['program_id'];

    // Keep existing student type if not specified
    if (empty($student_type) || $student_type === 'regular') {
        $student_type = normalizeStudentType($current['student_type'] ?? 'regular');
    }
    if (in_array($student_type, ['irregular', 'transferee'], true) && empty($previous_school)) {
        $previous_school = $current['previous_school'] ?? '';
    }

    // Find next year level
    if ($program_type === 'college') {
        $next_yl_stmt = $conn->prepare("
            SELECT pyl.id, pyl.year_level, pyl.year_name
            FROM program_year_levels pyl
            INNER JOIN program_year_levels current_pyl ON current_pyl.id = ?
            WHERE pyl.program_id = ? AND pyl.year_level = current_pyl.year_level + 1 AND pyl.is_active = 1
            LIMIT 1
        ");
        $next_yl_stmt->bind_param("ii", $current['year_level_id'], $program_id);
    } else {
        $next_yl_stmt = $conn->prepare("
            SELECT sgl.id, sgl.grade_level as year_level, sgl.grade_name as year_name
            FROM shs_grade_levels sgl
            INNER JOIN shs_grade_levels current_sgl ON current_sgl.id = ?
            WHERE sgl.strand_id = ? AND sgl.grade_level = current_sgl.grade_level + 1 AND sgl.is_active = 1
            LIMIT 1
        ");
        $next_yl_stmt->bind_param("ii", $current['year_level_id'], $program_id);
    }

    $next_yl_stmt->execute();
    $next_yl = $next_yl_stmt->get_result()->fetch_assoc();

    if (!$next_yl) {
        echo json_encode(['success' => false, 'message' => 'Student is at the highest year level — no further advancement available (may be ready for graduation).']);
        return;
    }

    $new_year_level_id = (int)$next_yl['id'];

    $conn->begin_transaction();
    try {
        // Mark previous term enrollment as completed
        $complete_stmt = $conn->prepare("UPDATE student_term_enrollments SET status = 'completed', updated_at = NOW() WHERE student_id = ? AND id = ?");
        $complete_stmt->bind_param("ii", $student_id, $current['id']);
        $complete_stmt->execute();

        $result = applyProgramEnrollment(
            $student_id, $program_type, $program_id, $new_year_level_id,
            $semester, $student_type, $previous_school,
            $completed_subject_ids, $completed_subject_details,
            (int)$_SESSION['user_id'], $current_ay_id
        );

        logAuditSimple($conn, "Year advancement: student {$student_id}, type {$student_type}, from level {$current['year_level_id']} to {$new_year_level_id}, semester {$semester}");

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => "Student advanced to {$next_yl['year_name']}. {$result['enrolled_count']} subject(s) enrolled for {$semester} semester.",
            'meta' => array_merge($result, [
                'new_year_level_id' => $new_year_level_id,
                'new_year_level_name' => $next_yl['year_name']
            ])
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getStudentBalance() {
    global $conn, $branch_id;

    $student_id = (int)($_GET['student_id'] ?? 0);

    if (!$student_id || !verifyStudentInRegistrarBranch($student_id, $branch_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student']);
        return;
    }

    $total_outstanding = getAllOutstandingBalance($student_id);
    $latest_enrollment = getLatestTermEnrollment($student_id);

    echo json_encode([
        'success' => true,
        'balance' => $total_outstanding,
        'latest_enrollment' => $latest_enrollment
    ]);
}

function applyProgramEnrollment($student_id, $program_type, $program_id, $year_level_id, $semester, $student_type, $previous_school, $completed_subject_ids, $completed_subject_details, $recorded_by, $current_ay_id) {
    global $conn;

    $semester = normalizeSemester($semester);
    $student_type = normalizeStudentType($student_type);
    $is_non_regular = in_array($student_type, ['irregular', 'transferee'], true);
    if (!$is_non_regular) {
        $previous_school = '';
    }

    if ($semester === '2nd') {
        // For transferees/irregulars enrolling for the first time in 2nd semester, skip 1st sem check
        $has_first_sem = hasTermEnrollment($student_id, $current_ay_id, '1st');
        if ($has_first_sem) {
            ensureTermTuitionFee($student_id, $program_type, $program_id, $year_level_id, '1st', $current_ay_id, $recorded_by);
            $first_sem_outstanding = getSemesterOutstandingBalance($student_id, $current_ay_id, '1st');
            if ($first_sem_outstanding > 0.009) {
                throw new Exception('Cannot enroll to 2nd semester. First semester balance must be fully paid first. Outstanding: ₱' . number_format($first_sem_outstanding, 2));
            }
        }
    }

    // When enrolling at a different year level, enforce all previous balances are paid
    $latest_enrollment = getLatestTermEnrollment($student_id);
    if ($latest_enrollment && (int)$latest_enrollment['year_level_id'] !== $year_level_id) {
        $total_outstanding = getAllOutstandingBalance($student_id);
        if ($total_outstanding > 0.009) {
            throw new Exception('Cannot enroll to new year level. All previous balances must be fully paid first. Outstanding: ₱' . number_format($total_outstanding, 2));
        }
    }

    $subjects = getCurriculumSubjectsForLevel($program_type, $program_id, $year_level_id, $semester);
    if (empty($subjects)) {
        throw new Exception('No curriculum subjects found for selected level and semester.');
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

    $assessed_fee = ensureTermTuitionFee($student_id, $program_type, $program_id, $year_level_id, $semester, $current_ay_id, $recorded_by);
    upsertStudentTermEnrollment($student_id, $program_type, $program_id, $year_level_id, $semester, $current_ay_id, $student_type, $previous_school, $recorded_by);

    // Normalize completed-subject list to selected curriculum only
    $valid_subject_set = array_flip($subject_ids);
    $completed_subject_ids = array_values(array_unique(array_filter(array_map('intval', $completed_subject_ids), static function ($id) use ($valid_subject_set) {
        return isset($valid_subject_set[$id]);
    })));
    $completed_subject_lookup = array_flip($completed_subject_ids);

    $normalized_details = [];
    foreach ($completed_subject_details as $sid => $detail) {
        $subject_id = (int)$sid;
        if (!isset($valid_subject_set[$subject_id]) || !isset($completed_subject_lookup[$subject_id])) {
            continue;
        }
        $previous_subject_name = trim((string)($detail['previous_subject_name'] ?? ''));
        $previous_grade = trim((string)($detail['grade'] ?? ''));
        $normalized_details[$subject_id] = [
            'previous_subject_name' => substr($previous_subject_name, 0, 255),
            'previous_grade' => substr($previous_grade, 0, 50)
        ];
    }

    if ($is_non_regular) {
        foreach ($completed_subject_ids as $sid) {
            if (empty($normalized_details[$sid]['previous_grade'])) {
                throw new Exception('Grade is required for each credited subject.');
            }
        }
    }

    // Save completed-subject declarations for non-regular students.
    if ($is_non_regular) {
        if (!empty($subject_ids)) {
            if (!empty($completed_subject_ids)) {
                $delete_unchecked_sql = "
                    DELETE FROM student_completed_subjects
                    WHERE student_id = ?
                      AND subject_id IN (" . placeholders(count($subject_ids)) . ")
                      AND subject_id NOT IN (" . placeholders(count($completed_subject_ids)) . ")
                ";
                $delete_unchecked_stmt = $conn->prepare($delete_unchecked_sql);
                $delete_unchecked_types = 'i' . str_repeat('i', count($subject_ids)) . str_repeat('i', count($completed_subject_ids));
                $delete_unchecked_params = array_merge([$student_id], $subject_ids, $completed_subject_ids);
                $delete_unchecked_stmt->bind_param($delete_unchecked_types, ...$delete_unchecked_params);
                $delete_unchecked_stmt->execute();
            } else {
                $delete_all_sql = "
                    DELETE FROM student_completed_subjects
                    WHERE student_id = ?
                      AND subject_id IN (" . placeholders(count($subject_ids)) . ")
                ";
                $delete_all_stmt = $conn->prepare($delete_all_sql);
                $delete_all_types = 'i' . str_repeat('i', count($subject_ids));
                $delete_all_params = array_merge([$student_id], $subject_ids);
                $delete_all_stmt->bind_param($delete_all_types, ...$delete_all_params);
                $delete_all_stmt->execute();
            }
        }
    }

    if (!$is_non_regular && !empty($subject_ids)) {
        $delete_all_sql = "
            DELETE FROM student_completed_subjects
            WHERE student_id = ?
              AND subject_id IN (" . placeholders(count($subject_ids)) . ")
        ";
        $delete_all_stmt = $conn->prepare($delete_all_sql);
        $delete_all_types = 'i' . str_repeat('i', count($subject_ids));
        $delete_all_params = array_merge([$student_id], $subject_ids);
        $delete_all_stmt->bind_param($delete_all_types, ...$delete_all_params);
        $delete_all_stmt->execute();
    }

    $completed_count = 0;
    if ($is_non_regular && !empty($completed_subject_ids)) {
        $insert_completed = $conn->prepare("
            INSERT INTO student_completed_subjects
                (student_id, subject_id, completion_source, previous_subject_name, previous_grade, remarks, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                completion_source = VALUES(completion_source),
                previous_subject_name = VALUES(previous_subject_name),
                previous_grade = VALUES(previous_grade),
                remarks = VALUES(remarks),
                recorded_by = VALUES(recorded_by)
        ");
        $source = $previous_school ?: 'Previous school';
        $remarks = 'Completed in previous school';
        foreach ($completed_subject_ids as $sid) {
            $detail = $normalized_details[$sid] ?? ['previous_subject_name' => '', 'previous_grade' => ''];
            $previous_subject_name = $detail['previous_subject_name'];
            $previous_grade = $detail['previous_grade'];
            $insert_completed->bind_param("iissssi", $student_id, $sid, $source, $previous_subject_name, $previous_grade, $remarks, $recorded_by);
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

    // Get total outstanding balance after fee assessment
    $total_outstanding = getAllOutstandingBalance($student_id);
    $semester_balance = getSemesterOutstandingBalance($student_id, $current_ay_id, $semester);

    return [
        'semester' => $semester,
        'student_type' => $student_type,
        'total_subjects' => count($subject_ids),
        'completed_count' => count($completed_map),
        'enrolled_count' => $enrolled_count,
        'skipped_duplicate_count' => count($existing_map),
        'tuition_fee_assessed' => $assessed_fee,
        'semester_balance' => $semester_balance,
        'total_balance' => $total_outstanding,
    ];
}

function getCurriculumSubjectsForLevel($program_type, $program_id, $year_level_id, $semester = '1st') {
    global $conn;

    $semester = normalizeSemester($semester);
    $semester_num = ($semester === '2nd') ? 2 : (($semester === 'summer') ? 3 : 1);

    if ($program_type === 'college') {
        $stmt = $conn->prepare("
            SELECT id, subject_code, subject_title, units, semester
            FROM curriculum_subjects
            WHERE program_id = ? AND year_level_id = ? AND is_active = 1
              AND (semester = ? OR ? = 3)
            ORDER BY semester, subject_code
        ");
        $stmt->bind_param("iiii", $program_id, $year_level_id, $semester_num, $semester_num);
    } else {
        $stmt = $conn->prepare("
            SELECT id, subject_code, subject_title, units, semester
            FROM curriculum_subjects
            WHERE shs_strand_id = ? AND shs_grade_level_id = ? AND is_active = 1
              AND (semester = ? OR ? = 3)
            ORDER BY semester, subject_code
        ");
        $stmt->bind_param("iiii", $program_id, $year_level_id, $semester_num, $semester_num);
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

function parseCompletedSubjectDetails($raw) {
    $details = json_decode((string)$raw, true);
    if (!is_array($details)) {
        return [];
    }

    $normalized = [];
    foreach ($details as $subject_id => $payload) {
        $sid = (int)$subject_id;
        if ($sid <= 0 || !is_array($payload)) {
            continue;
        }
        $normalized[$sid] = [
            'previous_subject_name' => trim((string)($payload['previous_subject_name'] ?? '')),
            'grade' => trim((string)($payload['grade'] ?? ''))
        ];
    }

    return $normalized;
}

function normalizeSemester($semester) {
    $semester = strtolower(trim((string)$semester));
    if (!in_array($semester, ['1st', '2nd', 'summer'], true)) {
        return '1st';
    }
    return $semester;
}

function getSemesterOutstandingBalance($student_id, $academic_year_id, $semester) {
    global $conn;

    $semester = normalizeSemester($semester);
    $student_id = (int)$student_id;
    $academic_year_id = (int)$academic_year_id;

    $fees_stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_fees
        FROM student_fees
        WHERE student_id = ? AND academic_year_id = ? AND semester = ?
    ");
    $fees_stmt->bind_param("iis", $student_id, $academic_year_id, $semester);
    $fees_stmt->execute();
    $fees_total = (float)($fees_stmt->get_result()->fetch_assoc()['total_fees'] ?? 0);

    $paid_stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_paid
        FROM payments
        WHERE student_id = ? AND academic_year_id = ? AND semester = ? AND status = 'verified'
    ");
    $paid_stmt->bind_param("iis", $student_id, $academic_year_id, $semester);
    $paid_stmt->execute();
    $paid_total = (float)($paid_stmt->get_result()->fetch_assoc()['total_paid'] ?? 0);

    return max(0, round($fees_total - $paid_total, 2));
}

function hasTermEnrollment($student_id, $academic_year_id, $semester) {
    global $conn;
    $student_id = (int)$student_id;
    $academic_year_id = (int)$academic_year_id;
    $semester = normalizeSemester($semester);

    // Check term enrollments
    $stmt = $conn->prepare("SELECT id FROM student_term_enrollments WHERE student_id = ? AND academic_year_id = ? AND semester = ? LIMIT 1");
    $stmt->bind_param("iis", $student_id, $academic_year_id, $semester);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) return true;

    // Fallback: check student_fees for legacy data
    $fees_stmt = $conn->prepare("SELECT id FROM student_fees WHERE student_id = ? AND academic_year_id = ? AND semester = ? LIMIT 1");
    $fees_stmt->bind_param("iis", $student_id, $academic_year_id, $semester);
    $fees_stmt->execute();
    return $fees_stmt->get_result()->num_rows > 0;
}

function getAllOutstandingBalance($student_id) {
    global $conn;
    $student_id = (int)$student_id;

    $fees_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM student_fees WHERE student_id = ?");
    $fees_stmt->bind_param("i", $student_id);
    $fees_stmt->execute();
    $total_fees = (float)($fees_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $paid_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE student_id = ? AND status = 'verified'");
    $paid_stmt->bind_param("i", $student_id);
    $paid_stmt->execute();
    $total_paid = (float)($paid_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    return max(0, round($total_fees - $total_paid, 2));
}

function getLatestTermEnrollment($student_id) {
    global $conn;
    $student_id = (int)$student_id;

    $stmt = $conn->prepare("
        SELECT ste.*,
               CASE WHEN ste.program_type = 'college' THEN pyl.year_name ELSE sgl.grade_name END as year_level_name,
               CASE WHEN ste.program_type = 'college' THEN pyl.year_level ELSE sgl.grade_level END as year_level_num,
               CASE WHEN ste.program_type = 'college' THEN p.program_code ELSE ss.strand_code END as program_code,
               CASE WHEN ste.program_type = 'college' THEN p.program_name ELSE ss.strand_name END as program_name
        FROM student_term_enrollments ste
        LEFT JOIN program_year_levels pyl ON ste.year_level_id = pyl.id
        LEFT JOIN shs_grade_levels sgl ON ste.year_level_id = sgl.id
        LEFT JOIN programs p ON ste.program_id = p.id AND ste.program_type = 'college'
        LEFT JOIN shs_strands ss ON ste.program_id = ss.id AND ste.program_type = 'shs'
        WHERE ste.student_id = ?
        ORDER BY ste.academic_year_id DESC, FIELD(ste.semester, 'summer', '2nd', '1st') DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function ensureTermTuitionFee($student_id, $program_type, $program_id, $year_level_id, $semester, $academic_year_id, $recorded_by) {
    global $conn;

    $student_id = (int)$student_id;
    $program_type = $program_type === 'shs' ? 'shs' : 'college';
    $program_id = (int)$program_id;
    $year_level_id = (int)$year_level_id;
    $academic_year_id = (int)$academic_year_id;
    $recorded_by = (int)$recorded_by;
    $semester = normalizeSemester($semester);

    // Check if fee already exists for this term
    $existing_stmt = $conn->prepare("
        SELECT id, amount FROM student_fees
        WHERE student_id = ?
          AND academic_year_id = ?
          AND semester = ?
          AND (fee_type IN ('Tuition Fee', 'Tuition') OR description = 'Tuition Fee')
        LIMIT 1
    ");
    $existing_stmt->bind_param("iis", $student_id, $academic_year_id, $semester);
    $existing_stmt->execute();
    $existing_result = $existing_stmt->get_result();
    if ($existing_result->num_rows > 0) {
        $existing_row = $existing_result->fetch_assoc();
        return (float)($existing_row['amount'] ?? 0);
    }

    // Look up configured tuition fee (supports both college and SHS)
    $tuition_stmt = $conn->prepare("
        SELECT tuition_fee
        FROM program_tuition_fees
        WHERE program_id = ? AND is_active = 1
          AND semester = ?
          AND program_type = ?
          AND (year_level_id = ? OR year_level_id IS NULL)
          AND (academic_year_id = ? OR academic_year_id IS NULL)
        ORDER BY
            CASE WHEN year_level_id = ? THEN 0 ELSE 1 END,
            CASE WHEN academic_year_id = ? THEN 0 ELSE 1 END,
            id DESC
        LIMIT 1
    ");
    $tuition_stmt->bind_param("issiiii", $program_id, $semester, $program_type, $year_level_id, $academic_year_id, $year_level_id, $academic_year_id);
    $tuition_stmt->execute();
    $tuition_row = $tuition_stmt->get_result()->fetch_assoc();

    // Fallback: try without program_type filter for legacy data
    if (!$tuition_row) {
        $fallback_stmt = $conn->prepare("
            SELECT tuition_fee
            FROM program_tuition_fees
            WHERE program_id = ? AND is_active = 1
              AND semester = ?
              AND (year_level_id = ? OR year_level_id IS NULL)
              AND (academic_year_id = ? OR academic_year_id IS NULL)
            ORDER BY
                CASE WHEN year_level_id = ? THEN 0 ELSE 1 END,
                CASE WHEN academic_year_id = ? THEN 0 ELSE 1 END,
                id DESC
            LIMIT 1
        ");
        $fallback_stmt->bind_param("isiiii", $program_id, $semester, $year_level_id, $academic_year_id, $year_level_id, $academic_year_id);
        $fallback_stmt->execute();
        $tuition_row = $fallback_stmt->get_result()->fetch_assoc();
    }

    if (!$tuition_row) {
        return 0;
    }

    $tuition_fee = (float)($tuition_row['tuition_fee'] ?? 0);
    if ($tuition_fee <= 0) {
        return 0;
    }

    $description = "Auto-assessed tuition for {$semester} semester enrollment";
    $insert_fee = $conn->prepare("
        INSERT INTO student_fees (student_id, fee_type, amount, academic_year_id, semester, description, created_by, created_at)
        VALUES (?, 'Tuition Fee', ?, ?, ?, ?, ?, NOW())
    ");
    $insert_fee->bind_param("idissi", $student_id, $tuition_fee, $academic_year_id, $semester, $description, $recorded_by);
    $insert_fee->execute();

    return $tuition_fee;
}

function upsertStudentTermEnrollment($student_id, $program_type, $program_id, $year_level_id, $semester, $academic_year_id, $student_type, $previous_school, $recorded_by) {
    global $conn;

    $student_id = (int)$student_id;
    $program_id = (int)$program_id;
    $year_level_id = (int)$year_level_id;
    $academic_year_id = (int)$academic_year_id;
    $recorded_by = (int)$recorded_by;
    $semester = normalizeSemester($semester);
    $student_type = normalizeStudentType($student_type);
    $program_type = $program_type === 'shs' ? 'shs' : 'college';
    $previous_school = trim((string)$previous_school);
    if ($student_type === 'regular') {
        $previous_school = '';
    }

    $stmt = $conn->prepare("
        INSERT INTO student_term_enrollments
            (student_id, program_type, program_id, year_level_id, academic_year_id, semester, student_type, previous_school, status, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'enrolled', ?)
        ON DUPLICATE KEY UPDATE
            program_type = VALUES(program_type),
            program_id = VALUES(program_id),
            year_level_id = VALUES(year_level_id),
            student_type = VALUES(student_type),
            previous_school = VALUES(previous_school),
            status = 'enrolled',
            recorded_by = VALUES(recorded_by),
            updated_at = NOW()
    ");
    $stmt->bind_param("isiiisssi", $student_id, $program_type, $program_id, $year_level_id, $academic_year_id, $semester, $student_type, $previous_school, $recorded_by);
    $stmt->execute();
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

    $conn->query("
        CREATE TABLE IF NOT EXISTS student_term_enrollments (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT(10) UNSIGNED NOT NULL,
            program_type ENUM('college','shs') NOT NULL DEFAULT 'college',
            program_id INT(10) UNSIGNED NOT NULL,
            year_level_id INT(10) UNSIGNED NOT NULL,
            academic_year_id INT(10) UNSIGNED NOT NULL,
            semester ENUM('1st','2nd','summer') NOT NULL DEFAULT '1st',
            student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular',
            previous_school VARCHAR(255) DEFAULT NULL,
            status ENUM('enrolled','completed','cancelled') NOT NULL DEFAULT 'enrolled',
            recorded_by INT(10) UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_student_term (student_id, academic_year_id, semester),
            KEY idx_student_ay (student_id, academic_year_id),
            KEY idx_program_level (program_id, year_level_id),
            KEY idx_recorded_by (recorded_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->query("ALTER TABLE student_term_enrollments MODIFY COLUMN student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular'");

    // Extend tuition fee table to support both college and SHS programs
    $conn->query("ALTER TABLE program_tuition_fees ADD COLUMN IF NOT EXISTS program_type ENUM('college','shs') NOT NULL DEFAULT 'college' AFTER program_id");
}

