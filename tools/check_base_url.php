<?php
/**
 * Diagnostic tool to verify BASE_URL detection
 */

// Mock SERVER variables for testing
function test_detection($host, $uri, $https = 'on', $port = 443) {
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['HTTPS'] = $https;
    $_SERVER['SERVER_PORT'] = $port;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    
    // Simulate the logic from init.php
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? "https://" : "http://";
    $host_detected = $_SERVER['HTTP_HOST'] ?? 'localhost';

    if (strpos($host_detected, 'sql') === 0 && strpos($host_detected, 'infinityfree.com') !== false) {
        $host_detected = $_SERVER['SERVER_NAME'] ?? $host_detected;
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $base_path = '/';

    if (strpos($request_uri, '/elms_system/') !== false) {
        $base_path = '/elms_system/';
    }

    $detected_base_url = $protocol . $host_detected . $base_path;
    
    echo "Testing Host: $host | URI: $uri\n";
    echo "Detected BASE_URL: $detected_base_url\n";
    echo "-----------------------------------\n";
}

header('Content-Type: text/plain');
echo "ELMS BASE_URL Detection Diagnostic\n";
echo "===================================\n\n";

// Case 1: Local XAMPP
test_detection('localhost', '/elms_system/dashboard.php', 'off', 80);

// Case 2: InfinityFree Root (Correct Host)
test_detection('elms-test.infinityfreeapp.com', '/dashboard.php');

// Case 3: InfinityFree Root (Incorrect Host Header - DB Host)
test_detection('sql300.infinityfree.com', '/dashboard.php');

// Case 4: Custom Domain
test_detection('my-school.edu', '/login.php');
