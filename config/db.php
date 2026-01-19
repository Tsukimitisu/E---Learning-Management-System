<?php
/**
 * Database Configuration
 * ELMS - Electronic Learning Management System
 */

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'elms_data');

// Initialize connection variable
$conn = null;

// Establish Database Connection
try {
    // Create connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("CRITICAL ERROR: Could not connect to the database. Error: " . $conn->connect_error);
    }
    
    // Set charset to UTF-8
    if (!$conn->set_charset("utf8mb4")) {
        die("Error loading character set utf8mb4: " . $conn->error);
    }
    
} catch (Exception $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Create PDO connection for modules that require it
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // PDO connection failed - some features may not work
    error_log("PDO Connection Failed: " . $e->getMessage());
}

/**
 * Clean Input Helper Function (fallback if functions.php not loaded)
 */
if (!function_exists('clean_input')) {
    function clean_input($data) {
        if ($data === null) return '';
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
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

if (!function_exists('safe_html')) {
    function safe_html($string, $default = '') {
        if ($string === null || $string === '') {
            return $default;
        }
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

?>


