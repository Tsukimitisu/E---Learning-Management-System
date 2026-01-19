<?php
require_once '../../../config/init.php';
require_once '../../../includes/email_helper.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SUPER_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

// Sanitize inputs
$user_id = (int)($_POST['user_id'] ?? 0);
$first_name = clean_input($_POST['first_name'] ?? '');
$last_name = clean_input($_POST['last_name'] ?? '');
$email = clean_input($_POST['email'] ?? '');
$contact_no = clean_input($_POST['contact_no'] ?? '');
$address = clean_input($_POST['address'] ?? '');
$role_id = (int)($_POST['role_id'] ?? 0);
$status = clean_input($_POST['status'] ?? 'active');
$password = $_POST['password'] ?? '';

// Validate required fields
if ($user_id == 0 || empty($first_name) || empty($last_name) || empty($email) || $role_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
    exit();
}

// Validate status
if (!in_array($status, ['active', 'inactive'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status value']);
    exit();
}

// Validate password if provided
if (!empty($password)) {
    $password_validation = validate_password($password);
    if (!$password_validation['valid']) {
        echo json_encode(['status' => 'error', 'message' => implode(', ', $password_validation['errors'])]);
        exit();
    }
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if email already exists for another user
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkStmt->bind_param("si", $email, $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Email already exists for another user']);
        exit();
    }
    
    // Update users table
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $userStmt = $conn->prepare("UPDATE users SET email = ?, password = ?, status = ? WHERE id = ?");
        $userStmt->bind_param("sssi", $email, $hashed_password, $status, $user_id);
    } else {
        $userStmt = $conn->prepare("UPDATE users SET email = ?, status = ? WHERE id = ?");
        $userStmt->bind_param("ssi", $email, $status, $user_id);
    }
    
    if (!$userStmt->execute()) {
        throw new Exception("Failed to update user account");
    }
    
    // Check if profile exists
    $checkProfileStmt = $conn->prepare("SELECT user_id FROM user_profiles WHERE user_id = ?");
    $checkProfileStmt->bind_param("i", $user_id);
    $checkProfileStmt->execute();
    $profileExists = $checkProfileStmt->get_result()->num_rows > 0;
    
    if ($profileExists) {
        // Update user_profiles table
        $profileStmt = $conn->prepare("UPDATE user_profiles SET first_name = ?, last_name = ?, contact_no = ?, address = ? WHERE user_id = ?");
        $profileStmt->bind_param("ssssi", $first_name, $last_name, $contact_no, $address, $user_id);
    } else {
        // Insert into user_profiles table
        $profileStmt = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, contact_no, address) VALUES (?, ?, ?, ?, ?)");
        $profileStmt->bind_param("issss", $user_id, $first_name, $last_name, $contact_no, $address);
    }
    
    if (!$profileStmt->execute()) {
        throw new Exception("Failed to update user profile");
    }
    
    // Check if user_roles exists
    $checkRoleStmt = $conn->prepare("SELECT user_id FROM user_roles WHERE user_id = ?");
    $checkRoleStmt->bind_param("i", $user_id);
    $checkRoleStmt->execute();
    $roleExists = $checkRoleStmt->get_result()->num_rows > 0;
    
    if ($roleExists) {
        // Update user_roles table
        $roleStmt = $conn->prepare("UPDATE user_roles SET role_id = ? WHERE user_id = ?");
        $roleStmt->bind_param("ii", $role_id, $user_id);
    } else {
        // Insert into user_roles table
        $roleStmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        $roleStmt->bind_param("ii", $user_id, $role_id);
    }
    
    if (!$roleStmt->execute()) {
        throw new Exception("Failed to update user role");
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User updated successfully!'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
