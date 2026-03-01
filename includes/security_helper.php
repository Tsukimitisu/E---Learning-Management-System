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
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address, user_agent, success, attempted_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssi", $email, $ip_address, $user_agent, $success);
    $stmt->execute();

    // Always log to security_logs for dashboard and audit
    $event_type = $success ? 'login_success' : 'login_failed';
    $severity = $success ? 'info' : 'medium';
    $details = $success ? 'User login successful' : 'User login failed';
    $user_id = null;
    $get_id = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $get_id->bind_param("s", $email);
    $get_id->execute();
    $res = $get_id->get_result();
    if ($row = $res->fetch_assoc()) {
        $user_id = $row['id'];
    }
    $sec_stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, details, ip_address, user_agent, severity, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $sec_stmt->bind_param("isssss", $user_id, $event_type, $details, $ip_address, $user_agent, $severity);
    $sec_stmt->execute();

    // Also log to audit_logs for failed logins and account locks
    if (!$success) {
        $action = 'Failed login attempt';
        if ($user_id) {
            $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $audit->bind_param("iss", $user_id, $action, $ip_address);
            $audit->execute();
        }
    }
}

// Note: get_client_ip() is defined in config/db.php

/**
 * Get lockout analysis info for an email.
 * Counts all failed attempts since the last successful login.
 * Returns cycle info, remaining attempts, and lockout timestamps.
 */
function get_lockout_info($email) {
    global $conn;

    $max_attempts  = (int)get_security_setting('max_login_attempts', 5);
    $lockout_dur   = (int)get_security_setting('lockout_duration', 1);   // initial minutes
    $lockout_cycles = (int)get_security_setting('lockout_cycles', 3);

    // Last successful login timestamp
    $stmt = $conn->prepare("SELECT MAX(attempted_at) as last_success FROM login_attempts WHERE email = ? AND success = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $last_success = $stmt->get_result()->fetch_assoc()['last_success'];

    // All failed attempts since last success (ordered chronologically)
    if ($last_success) {
        $stmt = $conn->prepare("SELECT attempted_at FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > ? ORDER BY attempted_at ASC");
        $stmt->bind_param("ss", $email, $last_success);
    } else {
        $stmt = $conn->prepare("SELECT attempted_at FROM login_attempts WHERE email = ? AND success = 0 ORDER BY attempted_at ASC");
        $stmt->bind_param("s", $email);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $failures = [];
    while ($row = $result->fetch_assoc()) {
        $failures[] = $row['attempted_at'];
    }

    $total          = count($failures);
    $completed      = ($max_attempts > 0) ? intdiv($total, $max_attempts) : 0;
    $in_cycle       = ($max_attempts > 0) ? ($total % $max_attempts) : 0;

    return [
        'total_failures'     => $total,
        'completed_cycles'   => $completed,
        'failures_in_cycle'  => $in_cycle,
        'remaining_attempts' => $max_attempts - $in_cycle,
        'max_attempts'       => $max_attempts,
        'lockout_duration'   => $lockout_dur,
        'lockout_cycles'     => $lockout_cycles,
        'failure_times'      => $failures,
    ];
}

/**
 * Check if account is locked out.
 *
 * Logic (cycle-based with escalating lockout):
 *  - Failures are counted since the last successful login (reset only on success).
 *  - Every  `max_attempts`  consecutive failures = 1 completed cycle.
 *  - Cycle 1 lockout = lockout_duration minutes, cycle 2 = 2×, … cycle N = N×.
 *  - When completed_cycles >= lockout_cycles  →  permanent lock (account deactivated).
 */
function is_account_locked($email) {
    global $conn;

    $info = get_lockout_info($email);

    // Not enough failures for any lockout
    if ($info['total_failures'] < $info['max_attempts']) {
        return false;
    }

    $completed = $info['completed_cycles'];

    // ── Permanent lock ──────────────────────────────────────────
    if ($completed >= $info['lockout_cycles']) {
        // Deactivate the user account
        $update = $conn->prepare("UPDATE users SET status = 'inactive' WHERE email = ? AND status = 'active'");
        $update->bind_param("s", $email);
        $update->execute();

        if ($update->affected_rows > 0) {
            // Log once
            $user_id = null;
            $get_id = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $get_id->bind_param("s", $email);
            $get_id->execute();
            $res = $get_id->get_result();
            if ($row = $res->fetch_assoc()) $user_id = $row['id'];

            $ip = get_client_ip();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($user_id) {
                $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'Account permanently locked - max lockout cycles exceeded', ?)");
                $audit->bind_param("is", $user_id, $ip);
                $audit->execute();
                $sec = $conn->prepare("INSERT INTO security_logs (user_id, event_type, details, ip_address, user_agent, severity, created_at) VALUES (?, 'account_locked', 'Permanently locked after exhausting all lockout cycles', ?, ?, 'critical', NOW())");
                $sec->bind_param("iss", $user_id, $ip, $ua);
                $sec->execute();
            }
        }
        return true;
    }

    // ── Temporary lockout ───────────────────────────────────────
    // The lockout was triggered at the cycle-boundary failure
    // (failure #completed_cycles × max_attempts, 0-based index = that − 1)
    $boundary_idx       = ($completed * $info['max_attempts']) - 1;
    $lockout_trigger     = strtotime($info['failure_times'][$boundary_idx]);
    $lockout_minutes     = $completed * $info['lockout_duration'];
    $lockout_end         = $lockout_trigger + ($lockout_minutes * 60);

    if (time() < $lockout_end) {
        return true;   // still within lockout window
    }

    // Lockout expired — user may attempt the next cycle
    return false;
}

/**
 * Get remaining lockout time in minutes.
 * Returns  0 if not locked, -1 if permanently locked.
 */
function get_lockout_remaining($email) {
    $info = get_lockout_info($email);

    if ($info['total_failures'] < $info['max_attempts']) {
        return 0;
    }

    $completed = $info['completed_cycles'];

    if ($completed >= $info['lockout_cycles']) {
        return -1;   // permanent
    }

    $boundary_idx    = ($completed * $info['max_attempts']) - 1;
    $lockout_trigger = strtotime($info['failure_times'][$boundary_idx]);
    $lockout_minutes = $completed * $info['lockout_duration'];
    $lockout_end     = $lockout_trigger + ($lockout_minutes * 60);
    $remaining_sec   = $lockout_end - time();

    return ($remaining_sec > 0) ? (int)ceil($remaining_sec / 60) : 0;
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
