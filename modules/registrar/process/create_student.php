<?php
require_once '../../../config/init.php';

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
    $password = $_POST['password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
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

    $insert_profile = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, contact_no, address) VALUES (?, ?, ?, ?, ?)");
    $insert_profile->bind_param("issss", $user_id, $first_name, $last_name, $contact_no, $address);

    if (!$insert_profile->execute()) {
        throw new Exception('Failed to create user profile');
    }

    $student_course_id = $program_type === 'college' ? $course_id : null;
    $insert_student = $conn->prepare("INSERT INTO students (user_id, student_no, course_id) VALUES (?, ?, ?)");
    $insert_student->bind_param("isi", $user_id, $student_no, $student_course_id);

    if (!$insert_student->execute()) {
        throw new Exception('Failed to create student record');
    }

    $insert_role = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $role_id = ROLE_STUDENT;
    $insert_role->bind_param("ii", $user_id, $role_id);

    if (!$insert_role->execute()) {
        throw new Exception('Failed to assign student role');
    }

    log_audit($conn, $_SESSION['user_id'], "Created student account for {$first_name} {$last_name} ({$student_no})");

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Student account created successfully',
        'student_id' => $user_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>