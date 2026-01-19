<?php
require_once '../config/init.php';
require_once '../includes/security_helper.php';

header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and sanitize inputs
$email = clean_input($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
    exit();
}

// Check if account is locked
if (is_account_locked($email)) {
    $remaining = get_lockout_remaining($email);
    echo json_encode([
        'success' => false, 
        'locked' => true,
        'lockout_remaining' => $remaining,
        'message' => "Account is temporarily locked. Please try again in {$remaining} minutes."
    ]);
    exit();
}

// Get max attempts for calculating remaining
$max_attempts = (int)get_security_setting('max_login_attempts', 5);

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
        record_login_attempt($email, false);
        
        // Calculate remaining attempts
        $lockout_duration = (int)get_security_setting('lockout_duration', 15);
        $recent_attempts = $conn->query("SELECT COUNT(*) as cnt FROM login_attempts WHERE email = '$email' AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL $lockout_duration MINUTE)")->fetch_assoc()['cnt'];
        $remaining = max(0, $max_attempts - $recent_attempts);
        
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid email or password',
            'attempts_remaining' => $remaining
        ]);
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        $conn->rollback();
        record_login_attempt($email, false);
        
        // Calculate remaining attempts
        $lockout_duration = (int)get_security_setting('lockout_duration', 15);
        $recent_attempts = $conn->query("SELECT COUNT(*) as cnt FROM login_attempts WHERE email = '$email' AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL $lockout_duration MINUTE)")->fetch_assoc()['cnt'];
        $remaining = max(0, $max_attempts - $recent_attempts);
        
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid email or password',
            'attempts_remaining' => $remaining
        ]);
        exit();
    }
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        $conn->rollback();
        record_login_attempt($email, false);
        echo json_encode(['success' => false, 'message' => 'Your account is inactive. Please contact administrator.']);
        exit();
    }
    
    // Successful login - record and clear previous attempts
    record_login_attempt($email, true);
    clear_login_attempts($email);
    
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
    
    // Regenerate session ID for security (prevent session fixation)
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['branch_id'] = $user['branch_id'] ?? null;
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    $_SESSION['login_method'] = 'password';
    
    // Set session fingerprint for security
    $_SESSION['fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    
    // Generate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
    
    // Determine redirect based on role
    $redirect = 'dashboard.php';
    
    echo json_encode([
        'success' => true,
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