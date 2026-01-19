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
    
    session_start();
}

// Define System Constants
define('SITE_NAME', 'ELMS - Datamex');
define('BASE_URL', 'http://localhost/elms_system/');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/elms_system/uploads/');

// Define Role Constants
define('ROLE_SUPER_ADMIN', 1);
define('ROLE_SCHOOL_ADMIN', 2);
define('ROLE_BRANCH_ADMIN', 3);
define('ROLE_REGISTRAR', 4);
define('ROLE_TEACHER', 5);
define('ROLE_STUDENT', 6);

// Timezone
date_default_timezone_set('Asia/Manila');

// Error Reporting (Development Mode)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Include RBAC System
require_once __DIR__ . '/../includes/rbac.php';

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