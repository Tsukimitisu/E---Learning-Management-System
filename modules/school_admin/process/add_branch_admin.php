<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$full_name = clean_input($_POST['full_name'] ?? '');
$email = clean_input($_POST['email'] ?? '');
$branch_id = (int)($_POST['branch_id'] ?? 0);

if (empty($full_name) || empty($email) || $branch_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

try {
    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        exit();
    }

    // Create temporary password
    $temp_password = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
    $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (email, password, status) VALUES (?, ?, 'active')");
    $stmt->bind_param("ss", $email, $password_hash);
    $stmt->execute();
    $user_id = $conn->insert_id();

    // Insert user profile
    $name_parts = explode(' ', $full_name, 2);
    $first_name = $name_parts[0];
    $last_name = $name_parts[1] ?? '';

    $profile = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, branch_id) VALUES (?, ?, ?, ?)");
    $profile->bind_param("issi", $user_id, $first_name, $last_name, $branch_id);
    $profile->execute();

    // Assign role
    $role = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $role_id = ROLE_BRANCH_ADMIN;
    $role->bind_param("ii", $user_id, $role_id);
    $role->execute();

    // Log action
    $ip = get_client_ip();
    $action = "Created branch administrator: $full_name ($email)";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    echo json_encode(['status' => 'success', 'message' => 'Branch administrator created successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error creating administrator: ' . $e->getMessage()]);
}
?>
