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
$first_name = clean_input($_POST['first_name'] ?? '');
$last_name = clean_input($_POST['last_name'] ?? '');
$email = clean_input($_POST['email'] ?? '');
$contact_no = clean_input($_POST['contact_no'] ?? '');
$address = clean_input($_POST['address'] ?? '');
$role_id = (int)($_POST['role_id'] ?? 0);
$password = $_POST['password'] ?? '';
$send_email = isset($_POST['send_email']) && $_POST['send_email'] === 'true';

// Validate required fields
if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || $role_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
    exit();
}

// Validate email exists (MX record check) if we're going to send email
if ($send_email) {
    $email_validation = validate_email_exists($email);
    if (!$email_validation['valid']) {
        echo json_encode(['status' => 'error', 'message' => $email_validation['message']]);
        exit();
    }
}

// Validate password using security settings
$password_validation = validate_password($password);
if (!$password_validation['valid']) {
    echo json_encode(['status' => 'error', 'message' => implode(', ', $password_validation['errors'])]);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        exit();
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert into users table
    $userStmt = $conn->prepare("INSERT INTO users (email, password, status) VALUES (?, ?, 'active')");
    $userStmt->bind_param("ss", $email, $hashed_password);
    
    if (!$userStmt->execute()) {
        throw new Exception("Failed to create user account");
    }
    
    $user_id = $conn->insert_id;
    
    // Insert into user_profiles table
    $profileStmt = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, contact_no, address) VALUES (?, ?, ?, ?, ?)");
    $profileStmt->bind_param("issss", $user_id, $first_name, $last_name, $contact_no, $address);
    
    if (!$profileStmt->execute()) {
        throw new Exception("Failed to create user profile");
    }
    
    // Insert into user_roles table
    $roleStmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $roleStmt->bind_param("ii", $user_id, $role_id);
    
    if (!$roleStmt->execute()) {
        throw new Exception("Failed to assign user role");
    }
    
    // Log audit
    $ip_address = get_client_ip();
    $action = "Created new user: $email with role ID: $role_id";
    $auditStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $auditStmt->bind_param("iss", $_SESSION['user_id'], $action, $ip_address);
    $auditStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Send email notification if requested
    $email_sent = false;
    $email_error = '';
    if ($send_email) {
        $role_names = [
            ROLE_SUPER_ADMIN => 'Super Admin',
            ROLE_SCHOOL_ADMIN => 'School Admin', 
            ROLE_BRANCH_ADMIN => 'Branch Admin',
            ROLE_REGISTRAR => 'Registrar',
            ROLE_TEACHER => 'Teacher',
            ROLE_STUDENT => 'Student'
        ];
        $role_name = $role_names[$role_id] ?? 'User';
        
        $email_result = send_account_credentials($email, $first_name, $last_name, $password, $role_name, $_SESSION['user_id']);
        $email_sent = $email_result['success'];
        if (!$email_sent) {
            $email_error = $email_result['error'] ?? 'Unknown error';
        }
    }
    
    $response = [
        'status' => 'success',
        'message' => 'User created successfully!',
        'user_id' => $user_id
    ];
    
    if ($send_email) {
        if ($email_sent) {
            $response['message'] .= ' Email notification sent.';
            $response['email_sent'] = true;
        } else {
            $response['message'] .= ' However, email notification failed: ' . $email_error;
            $response['email_sent'] = false;
            $response['email_error'] = $email_error;
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'System error: ' . $e->getMessage()]);
    error_log("Add User Error: " . $e->getMessage());
}

$conn->close();
?>