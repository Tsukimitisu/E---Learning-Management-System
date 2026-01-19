<?php
require_once '../../config/init.php';
require_once '../../includes/security_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Security & Email Settings";
$user_id = $_SESSION['user_id'];

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_security') {
        $settings = [
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
        
        foreach ($settings as $key => $value) {
            update_security_setting($key, $value, $user_id);
        }
        $message = "Security settings updated successfully!";
    }
    
    if ($action === 'update_email') {
        $settings = [
            'smtp_host' => trim($_POST['smtp_host']),
            'smtp_port' => (int)$_POST['smtp_port'],
            'smtp_username' => trim($_POST['smtp_username']),
            'smtp_from_email' => trim($_POST['smtp_from_email']),
            'smtp_from_name' => trim($_POST['smtp_from_name'])
        ];
        
        // Only update password if provided
        if (!empty($_POST['smtp_password'])) {
            $settings['smtp_password'] = $_POST['smtp_password'];
        }
        
        foreach ($settings as $key => $value) {
            update_security_setting($key, $value, $user_id);
        }
        $message = "Email settings updated successfully!";
    }
    
    if ($action === 'update_google') {
        $settings = [
            'enable_google_login' => isset($_POST['enable_google_login']) ? '1' : '0',
            'google_client_id' => trim($_POST['google_client_id'])
        ];
        
        // Only update secret if provided
        if (!empty($_POST['google_client_secret'])) {
            $settings['google_client_secret'] = $_POST['google_client_secret'];
        }
        
        foreach ($settings as $key => $value) {
            update_security_setting($key, $value, $user_id);
        }
        $message = "Google OAuth settings updated successfully!";
    }
    
    if ($action === 'test_email') {
        $test_email = trim($_POST['test_email']);
        $result = send_email($test_email, 'ELMS Test Email', '<h2>Test Email</h2><p>This is a test email from your ELMS system. If you received this, your email configuration is working correctly!</p>', 'test', $user_id);
        
        if ($result['success']) {
            $message = "Test email sent successfully to {$test_email}!";
        } else {
            $error = "Failed to send test email: " . $result['message'];
        }
    }
}

// Get current settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM security_settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get login stats
$login_stats = get_login_stats(7);

// Get recent email logs
$email_logs = $conn->query("
    SELECT * FROM email_logs 
    ORDER BY sent_at DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div id="content">
    <div class="navbar-custom">
        <button type="button" id="sidebarCollapse" class="burger-btn">
            <i class="bi bi-list"></i>
        </button>
        <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i><?php echo $page_title; ?></h5>
    </div>

    <div class="main-content-body p-4">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-box-arrow-in-right me-2"></i>Login Attempts (7 days)</h6>
                        <h2 class="mb-0"><?php echo $login_stats['total_attempts']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-check-circle me-2"></i>Successful Logins</h6>
                        <h2 class="mb-0"><?php echo $login_stats['successful_logins']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-x-circle me-2"></i>Failed Attempts</h6>
                        <h2 class="mb-0"><?php echo $login_stats['failed_logins']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-exclamation-triangle me-2"></i>Suspicious IPs</h6>
                        <h2 class="mb-0"><?php echo $login_stats['suspicious_ips']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tabs -->
        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
                    <i class="bi bi-shield-lock me-2"></i>Login Security
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button">
                    <i class="bi bi-envelope me-2"></i>Email (SMTP)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="google-tab" data-bs-toggle="tab" data-bs-target="#google" type="button">
                    <i class="bi bi-google me-2"></i>Google OAuth
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button">
                    <i class="bi bi-journal-text me-2"></i>Email Logs
                </button>
            </li>
        </ul>

        <div class="tab-content" id="settingsTabContent">
            <!-- Security Settings -->
            <div class="tab-pane fade show active" id="security" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_security">
                            
                            <h5 class="mb-4"><i class="bi bi-lock me-2"></i>Account Lockout Settings</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Maximum Failed Login Attempts</label>
                                    <input type="number" class="form-control" name="max_login_attempts" 
                                           value="<?php echo $settings['max_login_attempts'] ?? 5; ?>" min="1" max="20">
                                    <small class="text-muted">Account will be locked after this many failed attempts</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Lockout Duration (minutes)</label>
                                    <input type="number" class="form-control" name="lockout_duration" 
                                           value="<?php echo $settings['lockout_duration'] ?? 15; ?>" min="1" max="1440">
                                    <small class="text-muted">How long the account stays locked</small>
                                </div>
                            </div>
                            
                            <h5 class="mb-4"><i class="bi bi-key me-2"></i>Password Requirements</h5>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Minimum Password Length</label>
                                    <input type="number" class="form-control" name="password_min_length" 
                                           value="<?php echo $settings['password_min_length'] ?? 8; ?>" min="6" max="32">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Required Characters</label>
                                    <div class="d-flex flex-wrap gap-3 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="password_require_uppercase" 
                                                   <?php echo ($settings['password_require_uppercase'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Uppercase (A-Z)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="password_require_lowercase" 
                                                   <?php echo ($settings['password_require_lowercase'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Lowercase (a-z)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="password_require_number" 
                                                   <?php echo ($settings['password_require_number'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Number (0-9)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="password_require_special" 
                                                   <?php echo ($settings['password_require_special'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Special (!@#$%)</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="mb-4"><i class="bi bi-clock me-2"></i>Session & Timeout</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Session Timeout (minutes)</label>
                                    <input type="number" class="form-control" name="session_timeout" 
                                           value="<?php echo $settings['session_timeout'] ?? 60; ?>" min="5" max="1440">
                                    <small class="text-muted">Users will be logged out after this period of inactivity</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password Reset Link Expiry (minutes)</label>
                                    <input type="number" class="form-control" name="password_reset_expiry" 
                                           value="<?php echo $settings['password_reset_expiry'] ?? 60; ?>" min="5" max="1440">
                                    <small class="text-muted">How long password reset links remain valid</small>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Save Security Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="tab-pane fade" id="email" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Gmail SMTP Setup:</strong> To use Gmail, you need to create an <strong>App Password</strong> in your Google Account settings 
                            (Security → 2-Step Verification → App passwords). Use that password instead of your regular Gmail password.
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_email">
                            
                            <h5 class="mb-4"><i class="bi bi-server me-2"></i>SMTP Server Settings</h5>
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" name="smtp_host" 
                                           value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" name="smtp_port" 
                                           value="<?php echo $settings['smtp_port'] ?? 587; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">SMTP Username (Email)</label>
                                    <input type="email" class="form-control" name="smtp_username" 
                                           value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                                           placeholder="your-email@gmail.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">SMTP Password (App Password)</label>
                                    <input type="password" class="form-control" name="smtp_password" 
                                           placeholder="<?php echo empty($settings['smtp_password']) ? 'Not set' : '••••••••••••'; ?>">
                                    <small class="text-muted">Leave blank to keep current password</small>
                                </div>
                            </div>
                            
                            <h5 class="mb-4 mt-4"><i class="bi bi-person-badge me-2"></i>Sender Information</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">From Email Address</label>
                                    <input type="email" class="form-control" name="smtp_from_email" 
                                           value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>"
                                           placeholder="noreply@yourdomain.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">From Name</label>
                                    <input type="text" class="form-control" name="smtp_from_name" 
                                           value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'ELMS System'); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Save Email Settings
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <!-- Test Email -->
                        <h5 class="mb-3"><i class="bi bi-send me-2"></i>Send Test Email</h5>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="test_email">
                            <div class="col-auto">
                                <input type="email" class="form-control" name="test_email" placeholder="test@example.com" required>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-send me-2"></i>Send Test
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Google OAuth -->
            <div class="tab-pane fade" id="google" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Google OAuth Setup:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                                <li>Create a new project or select existing one</li>
                                <li>Enable "Google+ API" or "Google Identity" API</li>
                                <li>Go to Credentials → Create Credentials → OAuth Client ID</li>
                                <li>Set Authorized redirect URI: <code><?php echo BASE_URL; ?>auth/google_callback.php</code></li>
                                <li>Copy the Client ID and Client Secret below</li>
                            </ol>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_google">
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enable_google_login" id="enableGoogle"
                                           <?php echo ($settings['enable_google_login'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enableGoogle">
                                        <strong>Enable "Sign in with Google"</strong>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Google Client ID</label>
                                    <input type="text" class="form-control" name="google_client_id" 
                                           value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>"
                                           placeholder="xxxxx.apps.googleusercontent.com">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Google Client Secret</label>
                                    <input type="password" class="form-control" name="google_client_secret" 
                                           placeholder="<?php echo empty($settings['google_client_secret']) ? 'Not set' : '••••••••••••'; ?>">
                                    <small class="text-muted">Leave blank to keep current secret</small>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Save Google Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Email Logs -->
            <div class="tab-pane fade" id="logs" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-journal-text me-2"></i>Recent Email Logs</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Recipient</th>
                                        <th>Subject</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($email_logs)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No email logs yet</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($email_logs as $log): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y H:i', strtotime($log['sent_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($log['recipient_email']); ?></td>
                                                <td><?php echo htmlspecialchars($log['subject']); ?></td>
                                                <td><span class="badge bg-secondary"><?php echo $log['template_type']; ?></span></td>
                                                <td>
                                                    <?php if ($log['status'] === 'sent'): ?>
                                                        <span class="badge bg-success"><i class="bi bi-check me-1"></i>Sent</span>
                                                    <?php elseif ($log['status'] === 'failed'): ?>
                                                        <span class="badge bg-danger" title="<?php echo htmlspecialchars($log['error_message']); ?>">
                                                            <i class="bi bi-x me-1"></i>Failed
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
