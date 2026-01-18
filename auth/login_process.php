<?php
require_once '../config/init.php';

header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

// Get and sanitize inputs
$email = clean_input($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all fields']);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Prepare statement to fetch user
    $stmt = $conn->prepare("
         SELECT u.id, u.email, u.password, u.status, 
             up.first_name, up.last_name, up.branch_id,
             r.id as role_id, r.name as role_name
        FROM users u
        INNER JOIN user_profiles up ON u.id = up.user_id
        INNER JOIN user_roles ur ON u.id = ur.user_id
        INNER JOIN roles r ON ur.role_id = r.id
        WHERE u.email = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
        exit();
    }
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Your account is inactive. Please contact administrator.']);
        exit();
    }
    
    // Update last login
    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $user['id']);
    $updateStmt->execute();
    
    // Log audit
    $ip_address = get_client_ip();
    $action = "User logged in - " . $user['role_name'];
    $auditStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $auditStmt->bind_param("iss", $user['id'], $action, $ip_address);
    $auditStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['branch_id'] = $user['branch_id'] ?? null;
    $_SESSION['logged_in'] = true;
    
    // Determine redirect based on role
    $redirect = 'dashboard.php';
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful! Redirecting...',
        'redirect' => $redirect
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'System error. Please try again later.']);
    error_log("Login Error: " . $e->getMessage());
}

$conn->close();
?>