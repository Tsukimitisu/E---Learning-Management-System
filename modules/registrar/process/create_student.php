<?php
require_once '../../../config/init.php';
require_once '../../../includes/email_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

try {
    // Compatibility guard for environments where migrations are not yet applied.
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular' AFTER course_id");
    $conn->query("ALTER TABLE students MODIFY COLUMN student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular'");
    $conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS previous_school VARCHAR(255) DEFAULT NULL AFTER student_type");

    $first_name = clean_input($_POST['first_name'] ?? '');
    $last_name = clean_input($_POST['last_name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $contact_no = clean_input($_POST['contact_no'] ?? '');
    $address = clean_input($_POST['address'] ?? '');
    $program_type = clean_input($_POST['program_type'] ?? 'college');
    $course_id = (int)($_POST['course_id'] ?? 0);
    $shs_strand_id = (int)($_POST['shs_strand_id'] ?? 0);
    // For college, year_level_id comes from 'year_level_id'; for SHS from 'shs_year_level_id'
    $year_level_id = (int)($_POST['year_level_id'] ?? 0);
    if ($program_type === 'shs') {
        $year_level_id = (int)($_POST['shs_year_level_id'] ?? 0);
    }
    $password = $_POST['password'] ?? '';
    $lrn = clean_input($_POST['lrn'] ?? '');
    // Student type can come from either field depending on level type
    $student_type = clean_input($_POST['student_type'] ?? $_POST['student_type_shs'] ?? 'regular');

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled']);
        exit();
    }

    // Validate level type selection
    if (!in_array($program_type, ['college', 'shs'])) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a Level Type (College or SHS) first']);
        exit();
    }

    // Validate LRN format (must be exactly 12 digits if provided)
    if (!empty($lrn)) {
        if (!preg_match('/^\d{12}$/', $lrn)) {
            echo json_encode(['status' => 'error', 'message' => 'LRN must be exactly 12 digits']);
            exit();
        }
        // Check LRN uniqueness
        $check_lrn = $conn->prepare("SELECT user_id FROM students WHERE lrn = ?");
        $check_lrn->bind_param("s", $lrn);
        $check_lrn->execute();
        if ($check_lrn->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'This LRN is already assigned to another student']);
            exit();
        }
    }

    // LRN is required for SHS students
    if ($program_type === 'shs' && empty($lrn)) {
        echo json_encode(['status' => 'error', 'message' => 'LRN (Learner Reference Number) is required for SHS students']);
        exit();
    }

    // Validate student_type
    if (!in_array($student_type, ['regular', 'irregular', 'transferee'])) {
        $student_type = 'regular';
    }

    // Get the registrar's branch_id
    $registrar_result = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = " . $_SESSION['user_id']);
    $registrar_profile = $registrar_result->fetch_assoc();
    $registrar_branch_id = $registrar_profile['branch_id'] ?? null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit();
    }

    // Always validate email exists (MX record check) before creating account
    $email_validation = validate_email_exists($email);
    if (!$email_validation['valid']) {
        echo json_encode(['status' => 'error', 'message' => $email_validation['message']]);
        exit();
    }

    // Validate password using security settings
    $password_validation = validate_password($password);
    if (!$password_validation['valid']) {
        echo json_encode(['status' => 'error', 'message' => implode(', ', $password_validation['errors'])]);
        exit();
    }

    // Validate program selection
    if ($program_type === 'college' && $course_id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a program for college students']);
        exit();
    }
    if ($program_type === 'shs' && $shs_strand_id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a strand for SHS students']);
        exit();
    }

    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    if ($check_email->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        exit();
    }

    $student_no = generate_student_number($conn);

    $conn->begin_transaction();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $insert_user = $conn->prepare("INSERT INTO users (email, password, status, created_at) VALUES (?, ?, 'active', NOW())");
    $insert_user->bind_param("ss", $email, $hashed_password);

    if (!$insert_user->execute()) {
        throw new Exception('Failed to create user account');
    }

    $user_id = $conn->insert_id;

    // Insert user profile with the registrar's branch_id
    $insert_profile = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, contact_no, address, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
    $insert_profile->bind_param("issssi", $user_id, $first_name, $last_name, $contact_no, $address, $registrar_branch_id);

    if (!$insert_profile->execute()) {
        throw new Exception('Failed to create user profile');
    }

    // Determine the course_id to store in students table
    // For college: use the program id from programs table
    // For SHS: use the strand id from shs_strands table
    $final_course_id = $program_type === 'college' ? $course_id : $shs_strand_id;
    
    $lrn_value = !empty($lrn) ? $lrn : null;
    $insert_student = $conn->prepare("INSERT INTO students (user_id, student_no, course_id, student_type, lrn) VALUES (?, ?, ?, ?, ?)");
    $insert_student->bind_param("isiss", $user_id, $student_no, $final_course_id, $student_type, $lrn_value);

    if (!$insert_student->execute()) {
        throw new Exception('Failed to create student record');
    }

    $insert_role = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $role_id = ROLE_STUDENT;
    $insert_role->bind_param("ii", $user_id, $role_id);

    if (!$insert_role->execute()) {
        throw new Exception('Failed to assign student role');
    }

    // Log the action
    $program_name = $program_type === 'college' ? 'Program ID: ' . $course_id : 'Strand ID: ' . $shs_strand_id;
    log_audit($conn, $_SESSION['user_id'], "Created student account for {$first_name} {$last_name} ({$student_no}) - $program_name");

    $conn->commit();

    // Try to send email with credentials to the student (optional - won't fail if SMTP not configured)
    $email_sent = false;
    $email_error = '';
    try {
        $email_result = send_account_credentials($email, $first_name, $last_name, $password, 'Student', $_SESSION['user_id']);
        $email_sent = $email_result['success'] ?? false;
        if (!$email_sent) {
            $email_error = $email_result['message'] ?? 'Email service not configured';
        }
    } catch (Exception $e) {
        $email_error = 'Email service error: ' . $e->getMessage();
    }

    $response = [
        'status' => 'success',
        'message' => 'Student account created successfully!',
        'student_id' => $user_id,
        'student_no' => $student_no,
        'credentials' => [
            'email' => $email,
            'password' => $password
        ],
        'email_sent' => $email_sent
    ];
    
    if ($email_sent) {
        $response['message'] = 'Student account created! Login credentials have been emailed to ' . $email;
    }

    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
