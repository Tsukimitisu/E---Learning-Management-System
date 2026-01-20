<?php
require_once '../../config/init.php';
require_once '../../includes/security_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Security & Email Settings";
$user_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_security') {
        $settings_update = [
            'max_login_attempts' => (int)$_POST['max_login_attempts'],
            'lockout_duration' => (int)$_POST['lockout_duration'],
            'password_min_length' => (int)$_POST['password_min_length'],
            'password_require_uppercase' => isset($_POST['password_require_uppercase']) ? '1' : '0',
            'password_require_lowercase' => isset($_POST['password_require_lowercase']) ? '1' : '0',
            'password_require_number' => isset($_POST['password_require_number']) ? '1' : '0',
            'password_require_special' => isset($_POST['password_require_special']) ? '1' : '0',
            'session_timeout' => (int)$_POST['session_timeout'],
            'password_reset_expiry' => (int)$_POST['password_reset_expiry']
        ];
        foreach ($settings_update as $key => $value) { update_security_setting($key, $value, $user_id); }
        $message = "Security settings updated successfully!";
    }
    
    if ($action === 'update_email') {
        $settings_update = [
            'smtp_host' => trim($_POST['smtp_host']),
            'smtp_port' => (int)$_POST['smtp_port'],
            'smtp_username' => trim($_POST['smtp_username']),
            'smtp_from_email' => trim($_POST['smtp_from_email']),
            'smtp_from_name' => trim($_POST['smtp_from_name'])
        ];
        if (!empty($_POST['smtp_password'])) { $settings_update['smtp_password'] = $_POST['smtp_password']; }
        foreach ($settings_update as $key => $value) { update_security_setting($key, $value, $user_id); }
        $message = "Email settings updated successfully!";
    }
    
    if ($action === 'update_google') {
        $settings_update = [
            'enable_google_login' => isset($_POST['enable_google_login']) ? '1' : '0',
            'google_client_id' => trim($_POST['google_client_id'])
        ];
        if (!empty($_POST['google_client_secret'])) { $settings_update['google_client_secret'] = $_POST['google_client_secret']; }
        foreach ($settings_update as $key => $value) { update_security_setting($key, $value, $user_id); }
        $message = "Google OAuth settings updated successfully!";
    }
    
    if ($action === 'test_email') {
        $test_email = trim($_POST['test_email']);
        $result = send_email($test_email, 'ELMS Test Email', '<h2>Test Email</h2><p>This is a test email from your ELMS system. If you received this, your email configuration is working correctly!</p>', 'test', $user_id);
        if ($result['success']) { $message = "Test email sent successfully to {$test_email}!"; } 
        else { $error = "Failed to send test email: " . $result['message']; }
    }
}

$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM security_settings");
while ($row = $result->fetch_assoc()) { $settings[$row['setting_key']] = $row['setting_value']; }
$login_stats = get_login_stats(7);
$email_logs = $conn->query("SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC UI COMPONENTS --- */
    .sec-stat-card {
        background: white; border-radius: 12px; padding: 20px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s;
    }
    .sec-stat-card:hover { transform: translateY(-5px); }

    .nav-pills-modern .nav-link {
        color: #666; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;
        padding: 12px 20px; border-radius: 10px; transition: 0.3s;
    }
    .nav-pills-modern .nav-link.active {
        background-color: var(--blue); color: white; box-shadow: 0 4px 12px rgba(0,51,102,0.2);
    }

    .settings-card { background: white; border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .section-label { color: var(--blue); font-weight: 800; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px; margin-bottom: 20px; }

    .btn-save-maroon {
        background-color: var(--maroon); color: white; border: none; border-radius: 8px; font-weight: 700; padding: 10px 25px; transition: 0.3s;
    }
    .btn-save-maroon:hover { background-color: #600000; transform: scale(1.02); color: white; }
    
    code { background: #eee; padding: 2px 6px; border-radius: 4px; color: var(--maroon); }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-shield-lock-fill me-2"></i>Security & Email Control</h4>
            <p class="text-muted small mb-0">Manage authentication policies and SMTP communication</p>
        </div>
        <div class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm">
            <i class="bi bi-clock-history me-1"></i> Policy Version: 2026.01
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 animate__animated animate__headShake">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4 animate__animated animate__shakeX">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Login Statistics Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="sec-stat-card border-start border-primary border-5">
                <p class="text-muted small fw-bold mb-1">TOTAL ATTEMPTS</p>
                <h3 class="fw-bold mb-0"><?php echo $login_stats['total_attempts']; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="sec-stat-card border-start border-success border-5">
                <p class="text-muted small fw-bold mb-1">SUCCESSFUL</p>
                <h3 class="fw-bold mb-0 text-success"><?php echo $login_stats['successful_logins']; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="sec-stat-card border-start border-danger border-5">
                <p class="text-muted small fw-bold mb-1">FAILED ATTEMPTS</p>
                <h3 class="fw-bold mb-0 text-danger"><?php echo $login_stats['failed_logins']; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="sec-stat-card border-start border-warning border-5">
                <p class="text-muted small fw-bold mb-1">SUSPICIOUS IPS</p>
                <h3 class="fw-bold mb-0 text-warning"><?php echo $login_stats['suspicious_ips']; ?></h3>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-pills nav-pills-modern mb-4" id="settingsTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#security" type="button"><i class="bi bi-shield-check me-2"></i>Login Security</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#email" type="button"><i class="bi bi-envelope-at me-2"></i>Email (SMTP)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#google" type="button"><i class="bi bi-google me-2"></i>Google OAuth</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#logs" type="button"><i class="bi bi-list-columns-reverse me-2"></i>Email Logs</button></li>
    </ul>

    <div class="tab-content" id="settingsTabContent">
        <!-- TAB 1: LOGIN SECURITY -->
        <div class="tab-pane fade show active" id="security" role="tabpanel">
            <div class="settings-card p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="update_security">
                    
                    <h6 class="section-label">Account Lockout Policies</h6>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Max Failed Attempts</label>
                            <input type="number" class="form-control border-light shadow-sm" name="max_login_attempts" value="<?php echo $settings['max_login_attempts'] ?? 5; ?>" min="1" max="20">
                            <small class="text-muted">Attempts before account is temporarily disabled.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Lockout Duration (Mins)</label>
                            <input type="number" class="form-control border-light shadow-sm" name="lockout_duration" value="<?php echo $settings['lockout_duration'] ?? 15; ?>" min="1" max="1440">
                        </div>
                    </div>
                    
                    <h6 class="section-label">Complexity Requirements</h6>
                    <div class="row g-4 mb-5">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Min Password Length</label>
                            <input type="number" class="form-control border-light shadow-sm" name="password_min_length" value="<?php echo $settings['password_min_length'] ?? 8; ?>" min="6" max="32">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Enforce Characters</label>
                            <div class="d-flex flex-wrap gap-4 mt-2">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="password_require_uppercase" <?php echo ($settings['password_require_uppercase'] ?? '1') === '1' ? 'checked' : ''; ?>><label class="form-check-label small">Uppercase</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="password_require_lowercase" <?php echo ($settings['password_require_lowercase'] ?? '1') === '1' ? 'checked' : ''; ?>><label class="form-check-label small">Lowercase</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="password_require_number" <?php echo ($settings['password_require_number'] ?? '1') === '1' ? 'checked' : ''; ?>><label class="form-check-label small">Numbers</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="password_require_special" <?php echo ($settings['password_require_special'] ?? '0') === '1' ? 'checked' : ''; ?>><label class="form-check-label small">Symbols</label></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="section-label">Session Lifecycle</h6>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Session Timeout (Mins)</label>
                            <input type="number" class="form-control border-light shadow-sm" name="session_timeout" value="<?php echo $settings['session_timeout'] ?? 60; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Reset Link Expiry (Mins)</label>
                            <input type="number" class="form-control border-light shadow-sm" name="password_reset_expiry" value="<?php echo $settings['password_reset_expiry'] ?? 60; ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-save-maroon shadow-sm mt-2"><i class="bi bi-check2-circle me-2"></i>Save Security Policies</button>
                </form>
            </div>
        </div>

        <!-- TAB 2: EMAIL (SMTP) -->
        <div class="tab-pane fade" id="email" role="tabpanel">
            <div class="settings-card p-4">
                <div class="alert bg-light border-start border-blue border-4 mb-4 small">
                    <i class="bi bi-info-circle-fill text-blue me-2"></i><strong>Configuration Hint:</strong> When using Gmail, utilize a Google "App Password" to bypass legacy login blocks.
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_email">
                    <h6 class="section-label">SMTP Infrastructure</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-9"><label class="form-label fw-bold">SMTP Host</label><input type="text" class="form-control border-light shadow-sm" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>"></div>
                        <div class="col-md-3"><label class="form-label fw-bold">Port</label><input type="number" class="form-control border-light shadow-sm" name="smtp_port" value="<?php echo $settings['smtp_port'] ?? 587; ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-bold">Username / Account</label><input type="email" class="form-control border-light shadow-sm" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-bold">App Password</label><input type="password" class="form-control border-light shadow-sm" name="smtp_password" placeholder="••••••••••••"></div>
                    </div>
                    <h6 class="section-label">Sender Identity</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><label class="form-label fw-bold">Display Email</label><input type="email" class="form-control border-light shadow-sm" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-bold">Display Name</label><input type="text" class="form-control border-light shadow-sm" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'ELMS System'); ?>"></div>
                    </div>
                    <button type="submit" class="btn btn-save-maroon shadow-sm"><i class="bi bi-send-check me-2"></i>Update Mail Config</button>
                </form>
                <hr class="my-5">
                <h6 class="fw-bold mb-3"><i class="bi bi-bug me-2"></i>Diagnostics</h6>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="test_email">
                    <div class="col-md-4"><input type="email" class="form-control border-light shadow-sm" name="test_email" placeholder="Recipient address..." required></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100 fw-bold">Test Mail</button></div>
                </form>
            </div>
        </div>

        <!-- TAB 3: GOOGLE OAUTH -->
        <div class="tab-pane fade" id="google" role="tabpanel">
            <div class="settings-card p-4">
                <div class="alert bg-light border-0 shadow-sm mb-4 small">
                    <strong>Redirect URI:</strong> <code><?php echo BASE_URL; ?>auth/google_callback.php</code>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_google">
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="enable_google_login" id="enGoogle" <?php echo ($settings['enable_google_login'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="enGoogle">Authorize "Sign in with Google" Module</label>
                    </div>
                    <div class="mb-3"><label class="form-label fw-bold">Client ID</label><input type="text" class="form-control border-light shadow-sm" name="google_client_id" value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>"></div>
                    <div class="mb-4"><label class="form-label fw-bold">Client Secret</label><input type="password" class="form-control border-light shadow-sm" name="google_client_secret" placeholder="••••••••••••"></div>
                    <button type="submit" class="btn btn-save-maroon shadow-sm"><i class="bi bi-google me-2"></i>Save OAuth Details</button>
                </form>
            </div>
        </div>

        <!-- TAB 4: EMAIL LOGS -->
        <div class="tab-pane fade" id="logs" role="tabpanel">
            <div class="settings-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Date/Time</th><th>Recipient</th><th>Subject</th><th>Type</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (empty($email_logs)): ?><tr><td colspan="5" class="text-center py-5 text-muted">No audit logs found.</td></tr>
                            <?php else: foreach ($email_logs as $log): ?>
                                <tr>
                                    <td><small class="text-muted"><?php echo date('M d, H:i', strtotime($log['sent_at'])); ?></small></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($log['recipient_email']); ?></td>
                                    <td><small><?php echo htmlspecialchars($log['subject']); ?></small></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $log['template_type']; ?></span></td>
                                    <td>
                                        <?php if ($log['status'] === 'sent'): ?><span class="badge bg-success">Success</span>
                                        <?php elseif ($log['status'] === 'failed'): ?><span class="badge bg-danger" title="<?php echo htmlspecialchars($log['error_message']); ?>">Failed</span>
                                        <?php else: ?><span class="badge bg-warning text-dark">Pending</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>