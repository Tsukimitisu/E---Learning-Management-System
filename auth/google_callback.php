<?php
/**
 * Google OAuth Callback Handler
 * ELMS - Electronic Learning Management System
 */
require_once '../config/init.php';
require_once '../includes/security_helper.php';

$error = '';

// Check if Google OAuth is enabled
$google_enabled = get_security_setting('enable_google_login', '0') === '1';
if (!$google_enabled) {
    header('Location: ../index.php?error=google_disabled');
    exit();
}

// Check for error from Google
if (isset($_GET['error'])) {
    header('Location: ../index.php?error=google_denied');
    exit();
}

// Check for authorization code
$code = $_GET['code'] ?? '';
if (empty($code)) {
    header('Location: ../index.php?error=no_code');
    exit();
}

// Exchange code for user info
$google_user = get_google_user_info($code);

if (!$google_user || empty($google_user['email'])) {
    header('Location: ../index.php?error=google_failed');
    exit();
}

$google_email = $google_user['email'];
$google_id = $google_user['id'];

// Find user by email
$user = find_user_by_google($google_email);

if ($user) {
    // User exists - log them in
    record_login_attempt($google_email, true);
    clear_login_attempts($google_email);
    
    // Link Google account if not already linked
    link_google_account($user['id'], $google_id);
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['branch_id'] = $user['branch_id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    $_SESSION['login_method'] = 'google';
    
    // Set session fingerprint for security
    $_SESSION['fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    
    // Generate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
    
    // Update last login
    $conn->query("UPDATE users SET last_login = NOW() WHERE id = {$user['id']}");
    
    // Log audit
    $ip = get_client_ip();
    $action = "User logged in via Google - " . $user['role_name'];
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user['id'], $action, $ip);
    $stmt->execute();
    
    // Redirect based on role
    $redirect = '../dashboard.php';
    switch ($user['role_id']) {
        case ROLE_SUPER_ADMIN:
            $redirect = '../modules/super_admin/dashboard.php';
            break;
        case ROLE_SCHOOL_ADMIN:
            $redirect = '../modules/school_admin/dashboard.php';
            break;
        case ROLE_BRANCH_ADMIN:
            $redirect = '../modules/branch_admin/dashboard.php';
            break;
        case ROLE_REGISTRAR:
            $redirect = '../modules/registrar/dashboard.php';
            break;
        case ROLE_TEACHER:
            $redirect = '../modules/teacher/dashboard.php';
            break;
        case ROLE_STUDENT:
            $redirect = '../modules/student/dashboard.php';
            break;
    }
    
    header('Location: ' . $redirect);
    exit();
} else {
    // User doesn't exist - show error
    record_login_attempt($google_email, false);
    header('Location: ../index.php?error=no_account&email=' . urlencode($google_email));
    exit();
}
?>
