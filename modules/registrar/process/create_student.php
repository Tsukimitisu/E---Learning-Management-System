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
    $first_name = clean_input($_POST['first_name'] ?? '');
    $last_name = clean_input($_POST['last_name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $contact_no = clean_input($_POST['contact_no'] ?? '');
    $address = clean_input($_POST['address'] ?? '');
    $program_type = clean_input($_POST['program_type'] ?? 'college');
    $course_id = (int)($_POST['course_id'] ?? 0);
    $shs_strand_id = (int)($_POST['shs_strand_id'] ?? 0);
    $year_level_id = (int)($_POST['year_level_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    
    // Tuition fee fields
    $tuition_fee_id = (int)($_POST['tuition_fee_id'] ?? 0);
    $total_tuition = (float)($_POST['total_tuition'] ?? 0);
    $payment_option = clean_input($_POST['payment_option'] ?? '');
    $downpayment_amount = (float)($_POST['downpayment_amount'] ?? 0);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled']);
        exit();
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
    
    $insert_student = $conn->prepare("INSERT INTO students (user_id, student_no, course_id) VALUES (?, ?, ?)");
    $insert_student->bind_param("isi", $user_id, $student_no, $final_course_id);

    if (!$insert_student->execute()) {
        throw new Exception('Failed to create student record');
    }

    $insert_role = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $role_id = ROLE_STUDENT;
    $insert_role->bind_param("ii", $user_id, $role_id);

    if (!$insert_role->execute()) {
        throw new Exception('Failed to assign student role');
    }

    // Get current academic year for fee assessment
    $current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
    $current_ay_id = $current_ay['id'] ?? null;
    
    // If tuition fee is set and payment option is selected, create fee assessments and record payment
    if ($tuition_fee_id > 0 && $total_tuition > 0 && !empty($payment_option)) {
        // Create student fee assessment for the full tuition amount
        $insert_fee = $conn->prepare("INSERT INTO student_fees (student_id, fee_type, amount, semester, academic_year_id, description, created_by, created_at) VALUES (?, 'Tuition', ?, '1st', ?, 'Tuition Fee', ?, NOW())");
        $insert_fee->bind_param("idii", $user_id, $total_tuition, $current_ay_id, $_SESSION['user_id']);
        $insert_fee->execute();
        
        // Generate reference number for payment
        $reference_no = 'PAY-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        if ($payment_option === 'full') {
            // Record full payment
            $insert_payment = $conn->prepare("INSERT INTO payments (reference_no, student_id, amount, payment_type, description, academic_year_id, semester, branch_id, recorded_by, payment_method, status, created_at) VALUES (?, ?, ?, 'Tuition', 'Full tuition payment upon enrollment', ?, '1st', ?, ?, 'cash', 'verified', NOW())");
            $insert_payment->bind_param("sidiii", $reference_no, $user_id, $total_tuition, $current_ay_id, $registrar_branch_id, $_SESSION['user_id']);
            $insert_payment->execute();
            
        } else if ($payment_option === 'downpayment' && $downpayment_amount > 0) {
            // Record down payment
            $insert_payment = $conn->prepare("INSERT INTO payments (reference_no, student_id, amount, payment_type, description, academic_year_id, semester, branch_id, recorded_by, payment_method, status, created_at) VALUES (?, ?, ?, 'Tuition', 'Down payment upon enrollment', ?, '1st', ?, ?, 'cash', 'verified', NOW())");
            $insert_payment->bind_param("sidiii", $reference_no, $user_id, $downpayment_amount, $current_ay_id, $registrar_branch_id, $_SESSION['user_id']);
            $insert_payment->execute();
        }
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