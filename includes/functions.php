<?php
/**
 * Sanitize user input
 */
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirect helper
 */
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        echo '<script>window.location.href="' . $url . '";</script>';
        exit();
    }
}

/**
 * Check if user is authenticated
 */
function check_auth() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        redirect(BASE_URL . 'index.php');
        exit();
    }
    return true;
}

/**
 * Check if user has specific role
 */
function check_role($allowed_roles = []) {
    check_auth();

    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        redirect(BASE_URL . 'dashboard.php');
        exit();
    }
    return true;
}

/**
 * Check if user is School Admin (curriculum management authority)
 */
function check_school_admin() {
    check_auth();

    if ($_SESSION['role'] != ROLE_SCHOOL_ADMIN && $_SESSION['role'] != ROLE_SUPER_ADMIN) {
        redirect(BASE_URL . 'dashboard.php');
        exit();
    }
    return true;
}

/**
 * Check if user can access curriculum management (School Admin only)
 */
function require_curriculum_access() {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != ROLE_SCHOOL_ADMIN && $_SESSION['role'] != ROLE_SUPER_ADMIN)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Curriculum management requires School Administrator privileges.']);
        exit();
    }
    return true;
}

/**
 * Get current timestamp
 */
function get_timestamp() {
    return date('Y-m-d H:i:s');
}

/**
 * Log audit trail
 */
function log_audit($conn, $user_id, $action, $details = null, $ip_address = null) {
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    $sql = "INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $user_id, $action, $details, $ip_address);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}
?>