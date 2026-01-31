<?php
/**
 * Fix SMTP `from` settings by copying `smtp_username` -> `smtp_from_email`
 * and setting `smtp_from_name` -> SITE_NAME if empty.
 * Usage (browser): http://localhost/elms_system/tools/fix_smtp_from.php
 * Usage (CLI): php tools/fix_smtp_from.php
 */
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/email_helper.php';

header('Content-Type: application/json');

global $conn;

function get_setting($key) {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM security_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    return $row['setting_value'] ?? null;
}

function upsert_setting($key, $value, $updated_by = null) {
    global $conn;
    // Try update first
    $stmt = $conn->prepare("UPDATE security_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
    $stmt->bind_param("sis", $value, $updated_by, $key);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        return true;
    }
    // Insert if not exists
    $stmt = $conn->prepare("INSERT INTO security_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $key, $value, $updated_by);
    return $stmt->execute();
}

$smtp_username = get_setting('smtp_username');
$smtp_from_email = get_setting('smtp_from_email');
$smtp_from_name = get_setting('smtp_from_name');

$before = [
    'smtp_username' => $smtp_username,
    'smtp_from_email' => $smtp_from_email,
    'smtp_from_name' => $smtp_from_name
];

$changes = [];

if (empty($smtp_from_email) && !empty($smtp_username) && filter_var($smtp_username, FILTER_VALIDATE_EMAIL)) {
    $ok = upsert_setting('smtp_from_email', $smtp_username, isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
    $changes['smtp_from_email'] = $ok ? $smtp_username : false;
}

if (empty($smtp_from_name)) {
    $site_name = defined('SITE_NAME') ? SITE_NAME : 'ELMS System';
    $ok = upsert_setting('smtp_from_name', $site_name, isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
    $changes['smtp_from_name'] = $ok ? $site_name : false;
}

$after = [
    'smtp_username' => get_setting('smtp_username'),
    'smtp_from_email' => get_setting('smtp_from_email'),
    'smtp_from_name' => get_setting('smtp_from_name')
];

echo json_encode(['before' => $before, 'changes' => $changes, 'after' => $after], JSON_PRETTY_PRINT);

?>