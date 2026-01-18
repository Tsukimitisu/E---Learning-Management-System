<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
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
    $password = $_POST['password'] ?? '';
    $address = clean_input($_POST['address'] ?? '');
    $branch_id = get_user_branch_id();
    if ($branch_id === null) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied: Branch assignment required']);
        exit();
    }

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit();
    }

    // Check if email already exists
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    if ($check_email->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $insert_user = $conn->prepare("
        INSERT INTO users (email, password, status, created_at)
        VALUES (?, ?, 'active', NOW())
    ");
    $insert_user->bind_param("ss", $email, $hashed_password);

    if (!$insert_user->execute()) {
        throw new Exception('Failed to create user account');
    }

    $user_id = $conn->insert_id;

    // Insert user profile
    $insert_profile = $conn->prepare("
        INSERT INTO user_profiles (user_id, first_name, last_name, address, branch_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert_profile->bind_param("isssi", $user_id, $first_name, $last_name, $address, $branch_id);

    if (!$insert_profile->execute()) {
        throw new Exception('Failed to create user profile');
    }

    // Assign teacher role
    $role_id = ROLE_TEACHER;
    $insert_role = $conn->prepare("
        INSERT INTO user_roles (user_id, role_id)
        VALUES (?, ?)
    ");
    $insert_role->bind_param("ii", $user_id, $role_id);

    if (!$insert_role->execute()) {
        throw new Exception('Failed to assign teacher role');
    }

    // Log the action
    $ip = get_client_ip();
    $action = "Created teacher account for $first_name $last_name ($email)";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Teacher account created successfully. Default password: teacher123'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>