<?php
/**
 * Initialization Configuration
 * ELMS - Electronic Learning Management System
 */

// Start Session
if (session_status() === PHP_SESSION_NONE) {
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
}

// Include Helper Functions
require_once __DIR__ . '/../includes/functions.php';
?>