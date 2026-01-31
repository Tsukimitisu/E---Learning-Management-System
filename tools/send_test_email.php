<?php
/**
 * SMTP test script for ELMS
 * Usage (browser): http://localhost/elms_system/tools/send_test_email.php?to=you@example.com
 * Usage (CLI): php tools/send_test_email.php you@example.com
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/email_helper.php';

header('Content-Type: application/json');

// Allow CLI usage
$to = '';
if (PHP_SAPI === 'cli') {
    $to = $argv[1] ?? '';
} else {
    $to = $_GET['to'] ?? ($_POST['to'] ?? '');
}

if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid recipient email via ?to=you@example.com']);
    exit;
}

$subject = SITE_NAME . ' - SMTP Test Message';
$body = '<p>This is a test message from ELMS sent at ' . date('Y-m-d H:i:s') . '.</p>';

// Call send_email which also logs to email_logs
$result = send_email($to, $subject, $body, 'smtp_test', isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);

// Return full diagnostic info including SMTP setting snapshot (without secrets)
$diagnostic = [
    'result' => $result,
    'smtp_snapshot' => [
        'host' => get_security_setting('smtp_host', null),
        'port' => get_security_setting('smtp_port', null),
        'username_set' => !empty(get_security_setting('smtp_username', '')) ? true : false,
        'from_email' => get_security_setting('smtp_from_email', null)
    ]
];

echo json_encode($diagnostic);

?>