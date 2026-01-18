<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

// Check if user is branch admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$contact_no = trim($_POST['contact_no'] ?? '');

// Validate required fields
if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Get branch admin's branch
$admin_branch_query = $conn->prepare("
    SELECT branch_id FROM user_profiles WHERE user_id = ?
");
$admin_branch_query->bind_param("i", $_SESSION['user_id']);
$admin_branch_query->execute();
$admin_branch_result = $admin_branch_query->get_result();
$admin_data = $admin_branch_result->fetch_assoc();

if (!$admin_data || !$admin_data['branch_id']) {
    echo json_encode(['success' => false, 'message' => 'Branch not found for admin']);
    exit;
}

$branch_id = $admin_data['branch_id'];

// Check if email already exists
$email_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$email_check->bind_param("s", $email);
$email_check->execute();
if ($email_check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    exit;
}

$conn->begin_transaction();

try {
    // Create user account
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (email, password, status, created_at) 
        VALUES (?, ?, 'active', NOW())
    ");
    $stmt->bind_param("ss", $email, $hashed_password);
    $stmt->execute();
    $user_id = $conn->insert_id;
    
    // Assign role
    $role_id = ROLE_REGISTRAR;
    $role_stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $role_stmt->bind_param("ii", $user_id, $role_id);
    $role_stmt->execute();
    
    // Create user profile
    $profile_stmt = $conn->prepare("
        INSERT INTO user_profiles (user_id, first_name, last_name, contact_no, branch_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $profile_stmt->bind_param("isssi", $user_id, $first_name, $last_name, $contact_no, $branch_id);
    $profile_stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Registrar account created successfully',
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error creating account: ' . $e->getMessage()]);
}
?>
