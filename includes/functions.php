<?php
/**
 * Sanitize user input
 */
if (!function_exists('clean_input')) {
    function clean_input($data) {
        if (is_array($data)) {
            return array_map('clean_input', $data);
        }
        if ($data === null) return '';
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

/**
 * Redirect helper
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        if (!headers_sent()) {
            header("Location: " . $url);
            exit();
        } else {
            echo '<script>window.location.href="' . $url . '";</script>';
            exit();
        }
    }
}

/**
 * Check if user is authenticated
 */
if (!function_exists('check_auth')) {
    function check_auth() {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            redirect(BASE_URL . 'index.php');
            exit();
        }
        return true;
    }
}

/**
 * Check if user has specific role
 */
if (!function_exists('check_role')) {
    function check_role($allowed_roles = []) {
        check_auth();
        
        $user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;

        if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles)) {
            redirect(BASE_URL . 'dashboard.php');
            exit();
        }
        return true;
    }
}

/**
 * Check if user is School Admin (curriculum management authority)
 */
if (!function_exists('check_school_admin')) {
    function check_school_admin() {
        check_auth();
        
        $user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;

        if ($user_role != ROLE_SCHOOL_ADMIN && $user_role != ROLE_SUPER_ADMIN) {
            redirect(BASE_URL . 'dashboard.php');
            exit();
        }
        return true;
    }
}

/**
 * Check if user can access curriculum management (School Admin only)
 */
if (!function_exists('require_curriculum_access')) {
    function require_curriculum_access() {
        $user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
        
        if (!isset($_SESSION['user_id']) || ($user_role != ROLE_SCHOOL_ADMIN && $user_role != ROLE_SUPER_ADMIN)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access denied. Curriculum management requires School Administrator privileges.']);
            exit();
        }
        return true;
    }
}

/**
 * Get current timestamp
 */
if (!function_exists('get_timestamp')) {
    function get_timestamp() {
        return date('Y-m-d H:i:s');
    }
}

/**
 * Get Client IP Address
 */
if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        return $ip;
    }
}

/**
 * Log audit trail
 */
if (!function_exists('log_audit')) {
    function log_audit($conn, $user_id, $action, $details = null, $ip_address = null) {
        if ($ip_address === null) {
            $ip_address = get_client_ip();
        }
        
        $sql = "INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $user_id, $action, $details, $ip_address);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
}
?>