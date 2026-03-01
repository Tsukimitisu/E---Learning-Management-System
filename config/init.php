<?php
/**
 * Initialization Configuration
 * ELMS - Electronic Learning Management System
 */

// Start Session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
    
    // Secure cookie flag — only send over HTTPS
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    ini_set('session.cookie_secure', $is_https ? 1 : 0);
    
    session_start();
    
    // Session timeout enforcement — expire after 1 hour of inactivity
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

// Security Headers
// HSTS Header (Forces HTTPS)
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Best effort to hide Server disclosure (Note: May be overridden by OpenResty/Nginx)
header_remove("X-Powered-By");
header("Server: Website"); // Obfuscate server header if possible

if (!function_exists('elms_env')) {
    function elms_env($key, $default = null) {
        $value = getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('elms_env_bool')) {
    function elms_env_bool($key, $default = false) {
        $value = elms_env($key, null);
        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

// Define System Constants
define('SITE_NAME', 'ELMS - Datamex');
$base_url = rtrim((string)elms_env('ELMS_BASE_URL', 'http://localhost/elms_system/'), '/') . '/';
define('BASE_URL', $base_url);
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/elms_system/uploads/');

$realtime_server_url = trim((string)elms_env('ELMS_REALTIME_SERVER_URL', ''));
$realtime_broadcast_url = trim((string)elms_env('ELMS_REALTIME_BROADCAST_URL', ''));
if ($realtime_broadcast_url === '' && $realtime_server_url !== '') {
    $realtime_broadcast_url = rtrim($realtime_server_url, '/') . '/api/broadcast';
}
if ($realtime_broadcast_url === '') {
    $realtime_broadcast_url = 'http://127.0.0.1:3000/api/broadcast';
}

define('ELMS_REALTIME_ENABLED', elms_env_bool('ELMS_REALTIME_ENABLED', true));
define('ELMS_REALTIME_SERVER_URL', $realtime_server_url);
define('ELMS_REALTIME_SERVER_PORT', (int)elms_env('ELMS_REALTIME_SERVER_PORT', '3000'));
define('ELMS_REALTIME_SOCKET_PATH', (string)elms_env('ELMS_REALTIME_SOCKET_PATH', '/socket.io'));
define('ELMS_REALTIME_BROADCAST_URL', $realtime_broadcast_url);

// Define Role Constants
define('ROLE_SUPER_ADMIN', 1);
define('ROLE_SCHOOL_ADMIN', 2);
define('ROLE_BRANCH_ADMIN', 3);
define('ROLE_REGISTRAR', 4);
define('ROLE_TEACHER', 5);
define('ROLE_STUDENT', 6);

// Timezone
date_default_timezone_set('Asia/Manila');

// Error Reporting — suppress in production, enable in development
$elms_env_mode = strtolower(trim((string)elms_env('ELMS_ENV', 'production')));
if ($elms_env_mode === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Include Database Configuration
require_once __DIR__ . '/db.php';

// Run automatic schema migrations
if (isset($conn) && $conn && !$conn->connect_error) {
    // Check and add branch_id column to user_profiles if missing
    $check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='" . DB_NAME . "' AND TABLE_NAME='user_profiles' AND COLUMN_NAME='branch_id'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE user_profiles ADD COLUMN branch_id INT(10) UNSIGNED DEFAULT NULL AFTER address");
    }

    // Backfill session branch_id for branch admins if missing
    if (!empty($_SESSION['user_id']) && ($_SESSION['role_id'] ?? null) == ROLE_BRANCH_ADMIN && empty($_SESSION['branch_id'])) {
        $stmt = $conn->prepare("SELECT branch_id FROM user_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $_SESSION['branch_id'] = $row['branch_id'] ?? null;
        }
        $stmt->close();
    }
}

// Include Helper Functions
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/realtime_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

// Include RBAC System
require_once __DIR__ . '/../includes/rbac.php';

// ============================
//  CSRF Auto-Enforcement
// ============================
// Automatically validate CSRF token on POST/PUT/DELETE requests
// for authenticated users, unless the endpoint explicitly opts out.
if (!empty($_SESSION['user_id']) && in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'PUT', 'DELETE'])) {
    // Skip CSRF for the login endpoint itself (no session when first logging in)
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $csrf_skip_paths = [
        '/auth/login_process.php',
        '/auth/google_callback.php',
    ];
    $skip_csrf = false;
    foreach ($csrf_skip_paths as $path) {
        if (strpos($request_uri, $path) !== false) {
            $skip_csrf = true;
            break;
        }
    }
    if (!$skip_csrf) {
        // Check token from POST body, GET param, or X-CSRF-Token header
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf($token)) {
            http_response_code(403);
            $accepts_json = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
            $is_xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
            if ($accepts_json || $is_xhr || strpos($content_type, 'application/json') !== false) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Security token expired. Please refresh the page and try again.']);
            } else {
                echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Session Expired</h2><p>Your security token has expired. Please <a href="javascript:location.reload()">refresh the page</a> and try again.</p></body></html>';
            }
            exit();
        }
    }
}

// Track active session for concurrent user support
if (!empty($_SESSION['user_id']) && isset($conn) && $conn && !$conn->connect_error) {
    $session_id = session_id();
    $user_id = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $now = date('Y-m-d H:i:s');
    
    // Update or insert active session
    $stmt = $conn->prepare("
        INSERT INTO active_sessions (session_id, user_id, ip_address, user_agent, last_activity)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity), ip_address = VALUES(ip_address)
    ");
    $stmt->bind_param("sisss", $session_id, $user_id, $ip, $user_agent, $now);
    $stmt->execute();
    $stmt->close();
    
    // Clean old sessions (inactive for more than 2 hours)
    $conn->query("DELETE FROM active_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
}
?>
