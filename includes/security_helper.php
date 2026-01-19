<?php
/**
 * Security Helper Functions
 * ELMS - Electronic Learning Management System
 * Handles login security, rate limiting, and account protection
 */

require_once __DIR__ . '/email_helper.php';

/**
 * Record a login attempt
 */
function record_login_attempt($email, $success = false) {
    global $conn;
    
    $ip_address = get_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address, user_agent, success) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $email, $ip_address, $user_agent, $success);
    $stmt->execute();
}

// Note: get_client_ip() is defined in config/db.php

/**
 * Check if account is locked out
 */
function is_account_locked($email) {
    global $conn;
    
    $max_attempts = (int)get_security_setting('max_login_attempts', 5);
    $lockout_duration = (int)get_security_setting('lockout_duration', 15);
    
    // Count recent failed attempts
    $stmt = $conn->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE email = ? 
        AND success = 0 
        AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->bind_param("si", $email, $lockout_duration);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['attempts'] >= $max_attempts;
}

/**
 * Get remaining lockout time in minutes
 */
function get_lockout_remaining($email) {
    global $conn;
    
    $lockout_duration = (int)get_security_setting('lockout_duration', 15);
    
    $stmt = $conn->prepare("
        SELECT attempted_at 
        FROM login_attempts 
        WHERE email = ? AND success = 0 
        ORDER BY attempted_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $last_attempt = strtotime($result['attempted_at']);
        $lockout_end = $last_attempt + ($lockout_duration * 60);
        $remaining = ceil(($lockout_end - time()) / 60);
        return max(0, $remaining);
    }
    
    return 0;
}

/**
 * Clear failed login attempts after successful login
 */
function clear_login_attempts($email) {
    global $conn;
    
    // We don't delete, just mark as successful to maintain audit trail
    $stmt = $conn->prepare("
        UPDATE login_attempts 
        SET success = 1 
        WHERE email = ? AND success = 0
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
}

/**
 * Check session timeout
 */
function check_session_timeout() {
    $timeout_minutes = (int)get_security_setting('session_timeout', 60);
    $timeout_seconds = $timeout_minutes * 60;
    
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout_seconds) {
            // Session expired
            session_unset();
            session_destroy();
            return true;
        }
    }
    
    $_SESSION['last_activity'] = time();
    return false;
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get Google OAuth URL
 */
function get_google_oauth_url() {
    $client_id = get_security_setting('google_client_id', '');
    if (empty($client_id)) {
        return null;
    }
    
    $redirect_uri = BASE_URL . 'auth/google_callback.php';
    $scope = 'email profile';
    
    $params = [
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'scope' => $scope,
        'response_type' => 'code',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];
    
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Exchange Google auth code for user info
 */
function get_google_user_info($code) {
    $client_id = get_security_setting('google_client_id', '');
    $client_secret = get_security_setting('google_client_secret', '');
    $redirect_uri = BASE_URL . 'auth/google_callback.php';
    
    if (empty($client_id) || empty($client_secret)) {
        return null;
    }
    
    // Exchange code for token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'code' => $code,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token_info = json_decode($response, true);
    
    if (empty($token_info['access_token'])) {
        return null;
    }
    
    // Get user info with access token
    $userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $userinfo_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_info['access_token']]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Link Google account to existing user
 */
function link_google_account($user_id, $google_user_id, $access_token = null) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO oauth_tokens (user_id, provider, provider_user_id, access_token) 
        VALUES (?, 'google', ?, ?)
        ON DUPLICATE KEY UPDATE provider_user_id = ?, access_token = ?
    ");
    $stmt->bind_param("issss", $user_id, $google_user_id, $access_token, $google_user_id, $access_token);
    return $stmt->execute();
}

/**
 * Find user by Google account
 */
function find_user_by_google($google_email) {
    global $conn;
    
    // First check if email exists in users table
    $stmt = $conn->prepare("
        SELECT u.*, up.first_name, up.last_name, up.branch_id, ur.role_id, r.name as role_name
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.email = ? AND u.status = 'active'
    ");
    $stmt->bind_param("s", $google_email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get login statistics for dashboard
 */
function get_login_stats($days = 7) {
    global $conn;
    
    $stats = [];
    
    // Total login attempts
    $result = $conn->query("
        SELECT COUNT(*) as total, SUM(success) as successful 
        FROM login_attempts 
        WHERE attempted_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)
    ")->fetch_assoc();
    
    $stats['total_attempts'] = $result['total'] ?? 0;
    $stats['successful_logins'] = $result['successful'] ?? 0;
    $stats['failed_logins'] = $stats['total_attempts'] - $stats['successful_logins'];
    
    // Unique IPs with failed attempts
    $result = $conn->query("
        SELECT COUNT(DISTINCT ip_address) as count 
        FROM login_attempts 
        WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)
    ")->fetch_assoc();
    $stats['suspicious_ips'] = $result['count'] ?? 0;
    
    return $stats;
}

/**
 * Create account with role hierarchy validation
 * Super Admin -> School Admin
 * School Admin -> Branch Admin  
 * Branch Admin -> Teacher, Registrar
 * Registrar -> Student
 */
function can_create_role($creator_role_id, $target_role_id) {
    $allowed = [
        ROLE_SUPER_ADMIN => [ROLE_SCHOOL_ADMIN, ROLE_SUPER_ADMIN],
        ROLE_SCHOOL_ADMIN => [ROLE_BRANCH_ADMIN],
        ROLE_BRANCH_ADMIN => [ROLE_TEACHER, ROLE_REGISTRAR],
        ROLE_REGISTRAR => [ROLE_STUDENT]
    ];
    
    return isset($allowed[$creator_role_id]) && in_array($target_role_id, $allowed[$creator_role_id]);
}

/**
 * Create new user account with email notification
 */
function create_user_account($email, $first_name, $last_name, $role_id, $branch_id = null, $created_by = null, $send_email = true) {
    global $conn;
    
    // Validate email
    $email_check = validate_email_exists($email);
    if (!$email_check['valid']) {
        return ['success' => false, 'message' => $email_check['message']];
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    // Generate password
    $plain_password = generate_secure_password();
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    
    // Get role name
    $role_result = $conn->query("SELECT name FROM roles WHERE id = {$role_id}")->fetch_assoc();
    $role_name = $role_result['name'] ?? 'User';
    
    $conn->begin_transaction();
    
    try {
        // Create user
        $stmt = $conn->prepare("INSERT INTO users (email, password, status) VALUES (?, ?, 'active')");
        $stmt->bind_param("ss", $email, $hashed_password);
        $stmt->execute();
        $user_id = $conn->insert_id;
        
        // Create profile
        $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, branch_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $user_id, $first_name, $last_name, $branch_id);
        $stmt->execute();
        
        // Assign role
        $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $role_id);
        $stmt->execute();
        
        // If student, create student record
        if ($role_id == ROLE_STUDENT) {
            $student_no = 'STU-' . date('Y') . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare("INSERT INTO students (user_id, student_no) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $student_no);
            $stmt->execute();
        }
        
        $conn->commit();
        
        // Send email with credentials
        if ($send_email) {
            $email_result = send_account_credentials($email, $first_name, $last_name, $plain_password, $role_name, $created_by);
            if (!$email_result['success']) {
                // Account created but email failed - return warning
                return [
                    'success' => true, 
                    'user_id' => $user_id,
                    'password' => $plain_password,
                    'warning' => 'Account created but email failed to send: ' . $email_result['message']
                ];
            }
        }
        
        return [
            'success' => true, 
            'user_id' => $user_id,
            'password' => $plain_password,
            'message' => 'Account created successfully' . ($send_email ? ' and credentials sent to email' : '')
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Failed to create account: ' . $e->getMessage()];
    }
}
?>
