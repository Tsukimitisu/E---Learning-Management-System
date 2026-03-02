<?php
// Buffer ALL output so stray warnings/notices don't corrupt JSON
ob_start();

require_once '../../../config/init.php';

// Discard any output produced by init (session tracking, warnings, etc.)
ob_end_clean();

header('Content-Type: application/json');

// Suppress display_errors for JSON API responses
ini_set('display_errors', 0);

// Catch fatal errors and return JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $error['message'],
            'debug_file' => $error['file'],
            'debug_line' => $error['line']
        ]);
    }
});

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
    case 'preview_advance':
        previewAdvance();
        break;
    case 'get_student_balance':
        getStudentBalance();
        break;
    case 'record_downpayment':
        recordDownPayment();
        break;
    case 'check_graduation_eligibility':
        checkGraduationEligibility();
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

        // Notify student about enrollment
        create_notification(
            $student_id,
            'Enrollment Confirmed',
            "You have been enrolled in {$result['enrolled_count']} subject(s) for this term.",
            'enrollment',
            null,
            (int)$_SESSION['user_id']
        );

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
    $all_semesters = ($_GET['all_semesters'] ?? '0') === '1';

    if (!$program_type || !$program_id || !$year_level_id) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }

    // For transferee/irregular validation, load ALL subjects across all semesters
    $subjects = $all_semesters
        ? getCurriculumSubjectsForLevel($program_type, $program_id, $year_level_id, 'summer')
        : getCurriculumSubjectsForLevel($program_type, $program_id, $year_level_id, $semester);
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
    $downpayment_amount = (float)($_POST['downpayment_amount'] ?? 0);
    $payment_method = clean_input($_POST['payment_method'] ?? 'cash');

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

    // Validate mandatory downpayment
    if ($downpayment_amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'A down payment is required to advance the student.']);
        return;
    }

    $valid_methods = ['cash', 'bank_transfer', 'online', 'check'];
    if (!in_array($payment_method, $valid_methods)) {
        $payment_method = 'cash';
    }

    // Get current enrollment
    $current = getLatestTermEnrollment($student_id);
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Student has no current enrollment to advance from.']);
        return;
    }

    // Block advancement if student has ANY outstanding balance
    $total_outstanding = getAllOutstandingBalance($student_id);
    if ($total_outstanding > 0.009) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot advance year level. The student has an outstanding balance of ₱' . number_format($total_outstanding, 2) . '. All fees must be fully paid before advancement.'
        ]);
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

    // Look up tuition fee and apply discounts/penalties to validate minimum downpayment (25%)
    $tuition_lookup = lookupTuitionFee($program_type, $program_id, $new_year_level_id, $semester, $current_ay_id);
    if ($tuition_lookup > 0) {
        $adj = previewActiveAdjustments($tuition_lookup, $current_ay_id, $program_type);
        $adjusted_tuition = max(0, $tuition_lookup - $adj['total_discount'] + $adj['total_penalty']);
        $effective_fee = $adjusted_tuition > 0 ? $adjusted_tuition : $tuition_lookup;
        $min_downpayment = ceil($effective_fee * 0.25);
        if ($downpayment_amount < $min_downpayment) {
            echo json_encode(['success' => false, 'message' => 'Minimum down payment is ₱' . number_format($min_downpayment, 2) . ' (25% of adjusted tuition fee).']);
            return;
        }
        if ($downpayment_amount > $effective_fee) {
            $downpayment_amount = $effective_fee;
        }
    }

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

        // Record mandatory downpayment within the same transaction
        $recorded_by = (int)$_SESSION['user_id'];
        $reference_no = 'DP-ADV-' . date('Ymd') . '-' . str_pad($student_id, 4, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -4);
        $dp_description = 'Down payment upon year advancement to ' . $next_yl['year_name'];

        // Flush any pending results from prior queries
        while ($conn->more_results() && $conn->next_result()) {
            $r = $conn->store_result();
            if ($r) $r->free();
        }

        $dp_stmt = $conn->prepare("
            INSERT INTO payments (student_id, amount, payment_method, reference_no, academic_year_id, semester, description, status, verified_by, verified_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', ?, NOW(), NOW())
        ");
        if ($dp_stmt === false) {
            throw new Exception('Failed to prepare down payment query: ' . $conn->error);
        }
        $dp_stmt->bind_param("idssissi", $student_id, $downpayment_amount, $payment_method, $reference_no, $current_ay_id, $semester, $dp_description, $recorded_by);
        if (!$dp_stmt->execute()) {
            throw new Exception('Failed to record down payment: ' . $conn->error);
        }

        // Notify student
        create_notification(
            $student_id,
            'Year Level Advanced',
            'You have been advanced to ' . $next_yl['year_name'] . '. Down payment of ₱' . number_format($downpayment_amount, 2) . ' recorded.',
            'enrollment',
            null,
            $recorded_by
        );

        logAuditSimple($conn, "Year advancement: student {$student_id}, type {$student_type}, from level {$current['year_level_id']} to {$new_year_level_id}, semester {$semester}, downpayment ₱" . number_format($downpayment_amount, 2));

        $conn->commit();

        // Recalculate balance after downpayment
        $new_semester_balance = getSemesterOutstandingBalance($student_id, $current_ay_id, $semester);
        $new_total_balance = getAllOutstandingBalance($student_id);

        echo json_encode([
            'success' => true,
            'message' => "Student advanced to {$next_yl['year_name']}. {$result['enrolled_count']} subject(s) enrolled for {$semester} semester. Down payment of ₱" . number_format($downpayment_amount, 2) . ' recorded.',
            'meta' => array_merge($result, [
                'new_year_level_id' => $new_year_level_id,
                'new_year_level_name' => $next_yl['year_name'],
                'downpayment_amount' => $downpayment_amount,
                'downpayment_reference' => $reference_no,
                'semester_balance' => $new_semester_balance,
                'total_balance' => $new_total_balance
            ])
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function previewAdvance() {
    global $conn, $branch_id, $current_ay_id;

    $student_id = (int)($_GET['student_id'] ?? 0);
    $semester = normalizeSemester($_GET['semester'] ?? '1st');

    if (!$student_id || !verifyStudentInRegistrarBranch($student_id, $branch_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student']);
        return;
    }

    $current = getLatestTermEnrollment($student_id);
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'No current enrollment found.']);
        return;
    }

    $program_type = $current['program_type'];
    $program_id = (int)$current['program_id'];

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
        echo json_encode(['success' => false, 'message' => 'At highest year level.']);
        return;
    }

    $tuition_fee = lookupTuitionFee($program_type, $program_id, (int)$next_yl['id'], $semester, $current_ay_id);
    $adjustments = previewActiveAdjustments($tuition_fee, $current_ay_id, $program_type);
    $adjusted_fee = max(0, $tuition_fee - $adjustments['total_discount'] + $adjustments['total_penalty']);
    $min_downpayment = $adjusted_fee > 0 ? ceil($adjusted_fee * 0.25) : 0;

    echo json_encode([
        'success' => true,
        'current_year' => $current['year_level_name'] ?? '',
        'next_year' => $next_yl['year_name'],
        'next_year_level_id' => $next_yl['id'],
        'semester' => $semester,
        'tuition_fee' => $tuition_fee,
        'adjusted_fee' => $adjusted_fee,
        'min_downpayment' => $min_downpayment,
        'discounts' => $adjustments['discounts'],
        'penalties' => $adjustments['penalties'],
        'total_discount' => $adjustments['total_discount'],
        'total_penalty' => $adjustments['total_penalty'],
        'program_code' => $current['program_code'] ?? ''
    ]);
}

/**
 * Preview what discounts/penalties would apply to a tuition fee without inserting anything.
 * Used by previewAdvance and enrollNextYear validation.
 */
function previewActiveAdjustments($base_tuition_fee, $academic_year_id, $program_type = 'college') {
    global $conn;

    $today = date('Y-m-d');
    $academic_year_id = (int)$academic_year_id;
    $discounts = [];
    $penalties = [];
    $total_discount = 0;
    $total_penalty = 0;

    // Active discounts
    $disc_stmt = $conn->prepare("
        SELECT id, name, discount_type, value FROM tuition_discounts
        WHERE is_active = 1 AND start_date <= ? AND end_date >= ?
          AND (academic_year_id = ? OR academic_year_id IS NULL)
        ORDER BY id ASC
    ");
    $disc_stmt->bind_param("ssi", $today, $today, $academic_year_id);
    $disc_stmt->execute();
    $disc_result = $disc_stmt->get_result();
    while ($row = $disc_result->fetch_assoc()) {
        $amt = 0;
        if ($row['discount_type'] === 'percentage') {
            $amt = round($base_tuition_fee * ($row['value'] / 100), 2);
        } else {
            $amt = round((float)$row['value'], 2);
        }
        if ($amt > 0) {
            $desc = $row['name'] . ' (' . ($row['discount_type'] === 'percentage' ? $row['value'] . '%' : '₱' . number_format($row['value'], 2)) . ')';
            $discounts[] = ['description' => $desc, 'amount' => $amt];
            $total_discount += $amt;
        }
    }

    // Active penalties (college only — SHS does not have prelim/midterm/prefinals/finals penalty terms)
    if ($program_type !== 'shs') {
        $pen_stmt = $conn->prepare("
            SELECT id, name, penalty_type, value, applicable_term FROM tuition_penalties
            WHERE is_active = 1 AND start_date <= ?
              AND (academic_year_id = ? OR academic_year_id IS NULL)
            ORDER BY id ASC
        ");
        $pen_stmt->bind_param("si", $today, $academic_year_id);
        $pen_stmt->execute();
        $pen_result = $pen_stmt->get_result();
        while ($row = $pen_result->fetch_assoc()) {
            $amt = 0;
            if ($row['penalty_type'] === 'percentage') {
                $amt = round($base_tuition_fee * ($row['value'] / 100), 2);
            } else {
                $amt = round((float)$row['value'], 2);
            }
            if ($amt > 0) {
                $term_label = ($row['applicable_term'] && $row['applicable_term'] !== 'all') ? ' [' . ucfirst($row['applicable_term']) . ']' : '';
                $desc = $row['name'] . ' (' . ($row['penalty_type'] === 'percentage' ? $row['value'] . '%' : '₱' . number_format($row['value'], 2)) . ')' . $term_label;
                $penalties[] = ['description' => $desc, 'amount' => $amt, 'applicable_term' => $row['applicable_term'] ?? 'all'];
                $total_penalty += $amt;
            }
        }
    }

    return [
        'discounts' => $discounts,
        'penalties' => $penalties,
        'total_discount' => round($total_discount, 2),
        'total_penalty' => round($total_penalty, 2),
    ];
}

function lookupTuitionFee($program_type, $program_id, $year_level_id, $semester, $academic_year_id) {
    global $conn;

    $program_type = $program_type === 'shs' ? 'shs' : 'college';
    $semester = normalizeSemester($semester);

    $stmt = $conn->prepare("
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
    $stmt->bind_param("issiiii", $program_id, $semester, $program_type, $year_level_id, $academic_year_id, $year_level_id, $academic_year_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        // Fallback without program_type
        $fallback = $conn->prepare("
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
        $fallback->bind_param("isiiii", $program_id, $semester, $year_level_id, $academic_year_id, $year_level_id, $academic_year_id);
        $fallback->execute();
        $row = $fallback->get_result()->fetch_assoc();
    }

    return (float)($row['tuition_fee'] ?? 0);
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

function recordDownPayment() {
    global $conn, $branch_id, $current_ay_id;

    $student_id = (int)($_POST['student_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = clean_input($_POST['payment_method'] ?? 'cash');

    if (!$student_id || !verifyStudentInRegistrarBranch($student_id, $branch_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student']);
        return;
    }

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
        return;
    }

    // Get the latest term enrollment to determine semester
    $latest = getLatestTermEnrollment($student_id);
    $semester = $latest ? normalizeSemester($latest['semester']) : '1st';

    // Calculate minimum down payment (25% of current semester fee)
    $semester_fee = getSemesterTuitionFee($student_id, $current_ay_id, $semester);
    $min_downpayment = ceil($semester_fee * 0.25);

    if ($amount < $min_downpayment && $min_downpayment > 0) {
        echo json_encode(['success' => false, 'message' => 'Minimum down payment is ₱' . number_format($min_downpayment, 2)]);
        return;
    }

    $valid_methods = ['cash', 'bank_transfer', 'online', 'check'];
    if (!in_array($payment_method, $valid_methods)) {
        $payment_method = 'cash';
    }

    $recorded_by = (int)$_SESSION['user_id'];
    $description = 'Down payment upon enrollment';
    $reference_no = 'DP-' . date('Ymd') . '-' . str_pad($student_id, 4, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -4);

    // Flush any pending results from prior queries
    while ($conn->more_results() && $conn->next_result()) {
        $r = $conn->store_result();
        if ($r) $r->free();
    }

    $stmt = $conn->prepare("
        INSERT INTO payments (student_id, amount, payment_method, reference_no, academic_year_id, semester, description, status, verified_by, verified_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', ?, NOW(), NOW())
    ");
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    $stmt->bind_param("idssissi", $student_id, $amount, $payment_method, $reference_no, $current_ay_id, $semester, $description, $recorded_by);
    
    if ($stmt->execute()) {
        logAuditSimple($conn, "Down payment recorded: student {$student_id}, amount ₱" . number_format($amount, 2) . ", method {$payment_method}, ref {$reference_no}");

        // Notify student about down payment
        create_notification(
            $student_id,
            'Down Payment Recorded',
            'Your down payment of ₱' . number_format($amount, 2) . ' has been recorded successfully.',
            'payment',
            null,
            $recorded_by
        );
        
        $new_balance = getSemesterOutstandingBalance($student_id, $current_ay_id, $semester);
        $new_total_balance = getAllOutstandingBalance($student_id);
        echo json_encode([
            'success' => true,
            'message' => 'Down payment of ₱' . number_format($amount, 2) . ' recorded successfully. Ref: ' . $reference_no,
            'new_balance' => $new_balance,
            'new_total_balance' => $new_total_balance,
            'reference_number' => $reference_no
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record payment: ' . $conn->error]);
    }
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
        // If no subjects for specific semester, try loading all semesters
        $subjects = getCurriculumSubjectsForLevel($program_type, $program_id, $year_level_id, 'summer');
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

    // Build target enrollment list (non-credited subjects)
    $target_subject_ids = array_values(array_filter($subject_ids, static function ($sid) use ($completed_map) {
        return !isset($completed_map[$sid]);
    }));

    // Credited subjects to enroll with 'credited' status
    $credited_subject_ids = array_values(array_filter($subject_ids, static function ($sid) use ($completed_map) {
        return isset($completed_map[$sid]);
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

    // Mark credited subjects as 'credited' (upsert — converts 'enrolled' to 'credited' if re-processed)
    if (!empty($credited_subject_ids)) {
        $credit_enroll = $conn->prepare("
            INSERT INTO student_subject_enrollments
                (student_id, subject_id, section_id, academic_year_id, status, enrollment_type, recorded_by)
            VALUES (?, ?, NULL, ?, 'credited', ?, ?)
            ON DUPLICATE KEY UPDATE
                status = 'credited',
                enrollment_type = VALUES(enrollment_type),
                recorded_by = VALUES(recorded_by),
                updated_at = NOW()
        ");
        foreach ($credited_subject_ids as $sid) {
            $credit_enroll->bind_param("iiisi", $student_id, $sid, $current_ay_id, $student_type, $recorded_by);
            $credit_enroll->execute();
        }
    }

    // Insert regular subject enrollments
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

    // Get discount/penalty adjustments applied to this term
    $adjustments = getTermFeeAdjustments($student_id, $current_ay_id, $semester);

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
        'discounts_applied' => $adjustments['discounts'],
        'penalties_applied' => $adjustments['penalties'],
        'total_discount' => $adjustments['total_discount'],
        'total_penalty' => $adjustments['total_penalty'],
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

    $role_student = ROLE_STUDENT;
    $check = $conn->prepare("
        SELECT u.id
        FROM users u
        INNER JOIN user_profiles up ON u.id = up.user_id
        INNER JOIN user_roles ur ON u.id = ur.user_id
        WHERE u.id = ? AND ur.role_id = ? AND up.branch_id = ?
    ");
    $check->bind_param("iii", $student_id, $role_student, $branch_id);
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

function getSemesterTuitionFee($student_id, $academic_year_id, $semester) {
    global $conn;
    $semester = normalizeSemester($semester);
    $student_id = (int)$student_id;
    $academic_year_id = (int)$academic_year_id;

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM student_fees WHERE student_id = ? AND academic_year_id = ? AND semester = ?");
    $stmt->bind_param("iis", $student_id, $academic_year_id, $semester);
    $stmt->execute();
    return (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
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

function getTermFeeAdjustments($student_id, $academic_year_id, $semester) {
    global $conn;
    $student_id = (int)$student_id;
    $academic_year_id = (int)$academic_year_id;
    $semester = normalizeSemester($semester);

    $discounts = [];
    $penalties = [];
    $total_discount = 0;
    $total_penalty = 0;

    // Get discount entries
    $disc_stmt = $conn->prepare("
        SELECT fee_type, amount, description FROM student_fees
        WHERE student_id = ? AND academic_year_id = ? AND semester = ? AND fee_type = 'Discount'
        ORDER BY id ASC
    ");
    $disc_stmt->bind_param("iis", $student_id, $academic_year_id, $semester);
    $disc_stmt->execute();
    $disc_result = $disc_stmt->get_result();
    while ($row = $disc_result->fetch_assoc()) {
        $amt = abs((float)$row['amount']);
        $discounts[] = ['description' => $row['description'], 'amount' => $amt];
        $total_discount += $amt;
    }

    // Get penalty entries
    $pen_stmt = $conn->prepare("
        SELECT fee_type, amount, description FROM student_fees
        WHERE student_id = ? AND academic_year_id = ? AND semester = ? AND fee_type = 'Penalty'
        ORDER BY id ASC
    ");
    $pen_stmt->bind_param("iis", $student_id, $academic_year_id, $semester);
    $pen_stmt->execute();
    $pen_result = $pen_stmt->get_result();
    while ($row = $pen_result->fetch_assoc()) {
        $amt = (float)$row['amount'];
        $penalties[] = ['description' => $row['description'], 'amount' => $amt];
        $total_penalty += $amt;
    }

    return [
        'discounts' => $discounts,
        'penalties' => $penalties,
        'total_discount' => round($total_discount, 2),
        'total_penalty' => round($total_penalty, 2),
    ];
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

    // Check if fee already exists for this term AND year level
    $existing_stmt = $conn->prepare("
        SELECT id, amount FROM student_fees
        WHERE student_id = ?
          AND academic_year_id = ?
          AND semester = ?
          AND (year_level_id = ? OR (year_level_id IS NULL AND ? = 0))
          AND (fee_type IN ('Tuition Fee', 'Tuition') OR description = 'Tuition Fee')
        LIMIT 1
    ");
    $existing_stmt->bind_param("iisii", $student_id, $academic_year_id, $semester, $year_level_id, $year_level_id);
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

    // Insert base tuition fee
    $description = "Auto-assessed tuition for {$semester} semester enrollment";
    $insert_fee = $conn->prepare("
        INSERT INTO student_fees (student_id, fee_type, amount, academic_year_id, semester, year_level_id, description, created_by, created_at)
        VALUES (?, 'Tuition Fee', ?, ?, ?, ?, ?, ?, NOW())
    ");
    $insert_fee->bind_param("idisisi", $student_id, $tuition_fee, $academic_year_id, $semester, $year_level_id, $description, $recorded_by);
    $insert_fee->execute();

    // Apply active discounts (today between start_date and end_date)
    $today = date('Y-m-d');
    $discount_stmt = $conn->prepare("
        SELECT id, name, discount_type, value FROM tuition_discounts
        WHERE is_active = 1 AND start_date <= ? AND end_date >= ?
          AND (academic_year_id = ? OR academic_year_id IS NULL)
        ORDER BY id ASC
    ");
    $discount_stmt->bind_param("ssi", $today, $today, $academic_year_id);
    $discount_stmt->execute();
    $discounts = $discount_stmt->get_result();

    while ($disc = $discounts->fetch_assoc()) {
        $discount_amount = 0;
        if ($disc['discount_type'] === 'percentage') {
            $discount_amount = round($tuition_fee * ($disc['value'] / 100), 2);
        } else {
            $discount_amount = round((float)$disc['value'], 2);
        }
        if ($discount_amount > 0) {
            // Insert as negative amount (reduces total fees)
            $neg_amount = -$discount_amount;
            $disc_desc = "Discount: " . $disc['name'] . " (" . ($disc['discount_type'] === 'percentage' ? $disc['value'] . '%' : '₱' . number_format($disc['value'], 2)) . ")";
            $disc_insert = $conn->prepare("
                INSERT INTO student_fees (student_id, fee_type, amount, academic_year_id, semester, year_level_id, description, created_by, created_at)
                VALUES (?, 'Discount', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $disc_insert->bind_param("idisisi", $student_id, $neg_amount, $academic_year_id, $semester, $year_level_id, $disc_desc, $recorded_by);
            $disc_insert->execute();
        }
    }

    // Apply active penalties (college only — SHS does not use prelim/midterm/prefinals/finals payment terms)
    if ($program_type !== 'shs') {
        $penalty_stmt = $conn->prepare("
            SELECT id, name, penalty_type, value, applicable_term FROM tuition_penalties
            WHERE is_active = 1 AND start_date <= ?
              AND (academic_year_id = ? OR academic_year_id IS NULL)
            ORDER BY id ASC
        ");
        $penalty_stmt->bind_param("si", $today, $academic_year_id);
        $penalty_stmt->execute();
        $penalties = $penalty_stmt->get_result();

        while ($pen = $penalties->fetch_assoc()) {
            $penalty_amount = 0;
            if ($pen['penalty_type'] === 'percentage') {
                $penalty_amount = round($tuition_fee * ($pen['value'] / 100), 2);
            } else {
                $penalty_amount = round((float)$pen['value'], 2);
            }
            if ($penalty_amount > 0) {
                $term_label = ($pen['applicable_term'] && $pen['applicable_term'] !== 'all') ? ' [' . ucfirst($pen['applicable_term']) . ']' : '';
                $pen_desc = "Penalty: " . $pen['name'] . " (" . ($pen['penalty_type'] === 'percentage' ? $pen['value'] . '%' : '₱' . number_format($pen['value'], 2)) . ")" . $term_label;
                $pen_insert = $conn->prepare("
                    INSERT INTO student_fees (student_id, fee_type, amount, academic_year_id, semester, year_level_id, description, created_by, created_at)
                    VALUES (?, 'Penalty', ?, ?, ?, ?, ?, ?, NOW())
                ");
                $pen_insert->bind_param("idisisi", $student_id, $penalty_amount, $academic_year_id, $semester, $year_level_id, $pen_desc, $recorded_by);
                $pen_insert->execute();
            }
        }
    }

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

    // Determine voucher and enrollment status for SHS
    $voucher_status = 'not_applicable';
    $enrollment_status = 'enrolled';
    if ($program_type === 'shs') {
        $voucher_status = 'pending'; // SHS students start with pending voucher
    }

    $stmt = $conn->prepare("
        INSERT INTO student_term_enrollments
            (student_id, program_type, program_id, year_level_id, academic_year_id, semester, student_type, previous_school, status, voucher_status, enrollment_status, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'enrolled', ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            program_type = VALUES(program_type),
            program_id = VALUES(program_id),
            year_level_id = VALUES(year_level_id),
            student_type = VALUES(student_type),
            previous_school = VALUES(previous_school),
            status = 'enrolled',
            voucher_status = IF(VALUES(voucher_status) != 'not_applicable', VALUES(voucher_status), voucher_status),
            enrollment_status = VALUES(enrollment_status),
            recorded_by = VALUES(recorded_by),
            updated_at = NOW()
    ");
    $stmt->bind_param("isiiisssssi", $student_id, $program_type, $program_id, $year_level_id, $academic_year_id, $semester, $student_type, $previous_school, $voucher_status, $enrollment_status, $recorded_by);
    $stmt->execute();
}

/**
 * Check SHS graduation eligibility for a student.
 * Verifies all required curriculum subjects are completed with passing grades.
 */
function checkGraduationEligibility() {
    global $conn, $branch_id;

    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID required']);
        return;
    }

    if (!verifyStudentInRegistrarBranch($student_id, $branch_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student']);
        return;
    }

    // Get student's strand
    $student = $conn->query("SELECT s.course_id, s.lrn, s.student_type, 
        CONCAT(up.first_name, ' ', up.last_name) as full_name
        FROM students s 
        INNER JOIN user_profiles up ON s.user_id = up.user_id
        WHERE s.user_id = $student_id")->fetch_assoc();
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    // Check if it's SHS
    $strand = $conn->query("SELECT id, strand_code, strand_name FROM shs_strands WHERE id = " . (int)$student['course_id'])->fetch_assoc();
    if (!$strand) {
        echo json_encode(['success' => false, 'message' => 'Student is not enrolled in an SHS strand']);
        return;
    }

    // LRN check
    $has_lrn = !empty($student['lrn']);

    // Get all curriculum subjects for this strand (all grade levels)
    $grade_levels = $conn->query("SELECT id, grade_level, grade_name FROM shs_grade_levels WHERE strand_id = {$strand['id']} AND is_active = 1 ORDER BY grade_level");
    $all_grade_level_ids = [];
    while ($gl = $grade_levels->fetch_assoc()) {
        $all_grade_level_ids[] = (int)$gl['id'];
    }

    if (empty($all_grade_level_ids)) {
        echo json_encode(['success' => false, 'message' => 'No grade levels found for this strand']);
        return;
    }

    $gl_ids = implode(',', $all_grade_level_ids);
    
    // Get all required subjects
    $required_subjects = $conn->query("
        SELECT cs.id, cs.subject_code, cs.subject_title, cs.subject_type, cs.semester,
               sgl.grade_name
        FROM curriculum_subjects cs
        INNER JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
        WHERE cs.shs_strand_id = {$strand['id']}
        AND cs.shs_grade_level_id IN ($gl_ids)
        AND cs.is_active = 1
        ORDER BY sgl.grade_level, cs.semester, cs.subject_code
    ");

    $subjects = [];
    $total_required = 0;
    $completed_count = 0;
    $failed_subjects = [];
    $missing_subjects = [];
    $with_remedial = [];

    while ($sub = $required_subjects->fetch_assoc()) {
        $sid = (int)$sub['id'];
        $total_required++;

        // Check if student has a passing SHS grade
        $grade = $conn->query("
            SELECT final_grade, remarks FROM shs_grades 
            WHERE student_id = $student_id AND subject_id = $sid
            ORDER BY id DESC LIMIT 1
        ")->fetch_assoc();

        // Also check student_completed_subjects (credited from previous school)
        $credited = $conn->query("
            SELECT id FROM student_completed_subjects 
            WHERE student_id = $student_id AND subject_id = $sid
        ")->fetch_assoc();

        $status = 'missing';
        $final_grade = null;
        $remarks = null;

        if ($grade && $grade['final_grade'] !== null) {
            $final_grade = (int)$grade['final_grade'];
            $remarks = $grade['remarks'];
            if ($remarks === 'passed') {
                $status = 'passed';
                $completed_count++;
            } elseif ($remarks === 'with_remedial') {
                $status = 'with_remedial';
                $with_remedial[] = $sub['subject_code'] . ' - ' . $sub['subject_title'];
            } elseif ($remarks === 'failed') {
                $status = 'failed';
                $failed_subjects[] = $sub['subject_code'] . ' - ' . $sub['subject_title'];
            }
        } elseif ($credited) {
            $status = 'credited';
            $completed_count++;
        } else {
            $missing_subjects[] = $sub['subject_code'] . ' - ' . $sub['subject_title'];
        }

        $subjects[] = [
            'id' => $sid,
            'subject_code' => $sub['subject_code'],
            'subject_title' => $sub['subject_title'],
            'subject_type' => $sub['subject_type'],
            'grade_name' => $sub['grade_name'],
            'status' => $status,
            'final_grade' => $final_grade,
            'remarks' => $remarks
        ];
    }

    $eligible = ($completed_count >= $total_required) && empty($failed_subjects) && empty($with_remedial) && $has_lrn;

    $blockers = [];
    if (!$has_lrn) $blockers[] = 'Missing LRN (Learner Reference Number)';
    if (!empty($missing_subjects)) $blockers[] = count($missing_subjects) . ' subject(s) not yet graded';
    if (!empty($failed_subjects)) $blockers[] = count($failed_subjects) . ' subject(s) with FAILED status';
    if (!empty($with_remedial)) $blockers[] = count($with_remedial) . ' subject(s) requiring remedial';

    echo json_encode([
        'success' => true,
        'eligible' => $eligible,
        'student_name' => $student['full_name'],
        'strand' => $strand['strand_code'] . ' - ' . $strand['strand_name'],
        'lrn' => $student['lrn'] ?? '',
        'has_lrn' => $has_lrn,
        'total_required' => $total_required,
        'completed' => $completed_count,
        'completion_percentage' => $total_required > 0 ? round(($completed_count / $total_required) * 100, 1) : 0,
        'subjects' => $subjects,
        'blockers' => $blockers,
        'missing_subjects' => $missing_subjects,
        'failed_subjects' => $failed_subjects,
        'with_remedial' => $with_remedial
    ]);
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
        INSERT INTO audit_logs (user_id, action, ip_address)
        VALUES (?, ?, ?)
    ");
    if (!$log) {
        // Silently skip audit logging if table schema doesn't match
        return;
    }
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
    // Suppress warnings/errors to prevent output corruption in JSON APIs
    @$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular' AFTER course_id");
    @$conn->query("ALTER TABLE students MODIFY COLUMN student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular'");
    @$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS previous_school VARCHAR(255) DEFAULT NULL AFTER student_type");

    @$conn->query("
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
    @$conn->query("ALTER TABLE student_completed_subjects ADD COLUMN IF NOT EXISTS previous_subject_name VARCHAR(255) DEFAULT NULL AFTER completion_source");
    @$conn->query("ALTER TABLE student_completed_subjects ADD COLUMN IF NOT EXISTS previous_grade VARCHAR(50) DEFAULT NULL AFTER previous_subject_name");

    @$conn->query("
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
    @$conn->query("ALTER TABLE student_subject_enrollments MODIFY COLUMN enrollment_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular'");

    @$conn->query("
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
    @$conn->query("ALTER TABLE student_term_enrollments MODIFY COLUMN student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular'");

    // Extend tuition fee table to support both college and SHS programs
    @$conn->query("ALTER TABLE program_tuition_fees ADD COLUMN IF NOT EXISTS program_type ENUM('college','shs') NOT NULL DEFAULT 'college' AFTER program_id");
}

