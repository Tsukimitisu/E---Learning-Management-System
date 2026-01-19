<?php
/**
 * Account Settings - For all users to manage their account
 * Includes: Profile info, password change, Google account linking
 */
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../includes/email_helper.php';
require_once __DIR__ . '/../../includes/security_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /elms_system/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'] ?? $_SESSION['role'] ?? 0;

// Get user information
$stmt = $pdo->prepare("SELECT u.*, 
    COALESCE(
        (SELECT CONCAT(up.first_name, ' ', up.last_name) FROM user_profiles up WHERE up.user_id = u.id),
        u.email
    ) as full_name,
    (SELECT ur.role_id FROM user_roles ur WHERE ur.user_id = u.id LIMIT 1) as role_id,
    (SELECT ot.provider_user_id FROM oauth_tokens ot WHERE ot.user_id = u.id AND ot.provider = 'google' LIMIT 1) as google_email
    FROM users u WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /elms_system/index.php');
    exit;
}

// Get role name
$role_names = [
    1 => 'Super Admin',
    2 => 'School Admin',
    3 => 'Branch Admin',
    4 => 'Registrar',
    5 => 'Teacher',
    6 => 'Student'
];
$role_name = $role_names[$role_id] ?? 'User';

// Get security settings for password requirements
$password_settings = [];
$settings_to_get = ['password_min_length', 'password_require_uppercase', 'password_require_lowercase', 
                    'password_require_number', 'password_require_special', 'enable_google_login'];
foreach ($settings_to_get as $key) {
    $password_settings[$key] = get_security_setting($key);
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $error_message = 'Current password is incorrect.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } else {
            // Validate new password
            $validation = validate_password($new_password);
            if ($validation !== true) {
                $error_message = implode('<br>', $validation);
            } else {
                // Update password
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed, $user_id])) {
                    $success_message = 'Password updated successfully!';
                    
                    // Log the activity
                    try {
                        $ip = get_client_ip();
                        $conn->query("INSERT INTO audit_logs (user_id, action, ip_address) VALUES ({$user_id}, 'Password changed', '{$ip}')");
                    } catch (Exception $e) {
                        // Ignore logging errors
                    }
                } else {
                    $error_message = 'Failed to update password. Please try again.';
                }
            }
        }
    } elseif ($action === 'unlink_google') {
        // Remove Google OAuth link
        $stmt = $pdo->prepare("DELETE FROM oauth_tokens WHERE user_id = ? AND provider = 'google'");
        if ($stmt->execute([$user_id])) {
            $success_message = 'Google account unlinked successfully!';
            $user['google_email'] = null;
            
            // Log the activity
            try {
                $ip = get_client_ip();
                $conn->query("INSERT INTO audit_logs (user_id, action, ip_address) VALUES ({$user_id}, 'Google account unlinked', '{$ip}')");
            } catch (Exception $e) {
                // Ignore logging errors
            }
        } else {
            $error_message = 'Failed to unlink Google account.';
        }
    }
}

// Get login history for current user (by email)
$login_history = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE email = ? ORDER BY attempted_at DESC LIMIT 10");
    $stmt->execute([$user['email']]);
    $login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist or have different structure
}

// Get login statistics for current user
$login_stats = [
    'total_logins' => 0,
    'last_login' => null
];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, MAX(attempted_at) as last_login FROM login_attempts WHERE email = ? AND success = 1");
    $stmt->execute([$user['email']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $login_stats['total_logins'] = $stats['total'] ?? 0;
    $login_stats['last_login'] = $stats['last_login'];
} catch (Exception $e) {
    // Ignore errors
}

// Generate Google OAuth URL for linking
$google_oauth_url = '';
if ($password_settings['enable_google_login'] && !$user['google_email']) {
    $google_oauth_url = get_google_oauth_url();
}

// Determine redirect path based on role
$dashboard_paths = [
    1 => '/elms_system/modules/super_admin/dashboard.php',
    2 => '/elms_system/modules/school_admin/dashboard.php',
    3 => '/elms_system/modules/branch_admin/dashboard.php',
    4 => '/elms_system/modules/registrar/dashboard.php',
    5 => '/elms_system/modules/teacher/dashboard.php',
    6 => '/elms_system/modules/student/dashboard.php'
];
$back_url = $dashboard_paths[$role_id] ?? '/elms_system/dashboard.php';

$page_title = 'Account Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - ELMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
        }
        body {
            background-color: #f8f9fc;
        }
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        .google-btn {
            background-color: #fff;
            border: 1px solid #ddd;
            color: #333;
        }
        .google-btn:hover {
            background-color: #f8f9fa;
        }
        .google-btn img {
            width: 18px;
            margin-right: 10px;
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #666;
        }
        .password-requirements li {
            margin-bottom: 3px;
        }
        .password-requirements li.valid {
            color: #28a745;
        }
        .password-requirements li.invalid {
            color: #dc3545;
        }
        .login-history-table {
            font-size: 0.9rem;
        }
        .status-badge {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Overview -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="profile-img">
                            <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
                        </div>
                        <h4><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></h4>
                        <p class="text-muted mb-2"><?= htmlspecialchars($user['email']) ?></p>
                        <span class="badge bg-primary"><?= htmlspecialchars($role_name) ?></span>
                        
                        <hr>
                        
                        <div class="text-start">
                            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? '') ?></p>
                            <p class="mb-1"><strong>Account Status:</strong> 
                                <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($user['status'] ?? 'Active') ?>
                                </span>
                            </p>
                            <p class="mb-1"><strong>Member Since:</strong> <?= date('M d, Y', strtotime($user['created_at'] ?? 'now')) ?></p>
                            <?php if ($login_stats['last_login']): ?>
                            <p class="mb-0"><strong>Last Login:</strong> <?= date('M d, Y H:i', strtotime($login_stats['last_login'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Google Account Linking -->
                <?php if ($password_settings['enable_google_login']): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-google me-2"></i>Google Account</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($user['google_email']): ?>
                            <div class="d-flex align-items-center mb-3">
                                <img src="https://www.google.com/favicon.ico" alt="Google" style="width: 24px;" class="me-2">
                                <div>
                                    <p class="mb-0"><strong>Linked Account</strong></p>
                                    <small class="text-muted"><?= htmlspecialchars($user['google_email']) ?></small>
                                </div>
                            </div>
                            <form method="post" onsubmit="return confirm('Are you sure you want to unlink your Google account?');">
                                <input type="hidden" name="action" value="unlink_google">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-x-circle me-1"></i>Unlink Account
                                </button>
                            </form>
                        <?php elseif ($google_oauth_url): ?>
                            <p class="text-muted mb-3">Link your Google account to enable quick sign-in.</p>
                            <a href="<?= htmlspecialchars($google_oauth_url) ?>" class="btn google-btn w-100">
                                <img src="https://www.google.com/favicon.ico" alt="Google">
                                Link Google Account
                            </a>
                        <?php else: ?>
                            <p class="text-muted mb-0">Google authentication is not fully configured yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Password & Security -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="passwordForm">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Password Requirements -->
                            <div class="password-requirements mb-3">
                                <p class="mb-2"><strong>Password Requirements:</strong></p>
                                <ul class="list-unstyled ms-2" id="passwordRequirements">
                                    <li id="req-length">
                                        <i class="bi bi-circle"></i> At least <?= $password_settings['password_min_length'] ?> characters
                                    </li>
                                    <?php if ($password_settings['password_require_uppercase']): ?>
                                    <li id="req-upper">
                                        <i class="bi bi-circle"></i> At least one uppercase letter
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($password_settings['password_require_lowercase']): ?>
                                    <li id="req-lower">
                                        <i class="bi bi-circle"></i> At least one lowercase letter
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($password_settings['password_require_number']): ?>
                                    <li id="req-number">
                                        <i class="bi bi-circle"></i> At least one number
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($password_settings['password_require_special']): ?>
                                    <li id="req-special">
                                        <i class="bi bi-circle"></i> At least one special character (!@#$%^&*...)
                                    </li>
                                    <?php endif; ?>
                                    <li id="req-match">
                                        <i class="bi bi-circle"></i> Passwords match
                                    </li>
                                </ul>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" id="changePasswordBtn" disabled>
                                <i class="bi bi-key me-1"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Login History -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Login Activity</h5>
                        <?php if ($login_stats['total_logins'] > 0): ?>
                        <span class="badge bg-info"><?= $login_stats['total_logins'] ?> total logins</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($login_history)): ?>
                            <p class="text-muted mb-0">No login history available.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm login-history-table">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>IP Address</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($login_history as $attempt): ?>
                                        <tr>
                                            <td><?= date('M d, Y H:i:s', strtotime($attempt['attempted_at'])) ?></td>
                                            <td><code><?= htmlspecialchars($attempt['ip_address'] ?? '') ?></code></td>
                                            <td>
                                                <?php if (!empty($attempt['success'])): ?>
                                                    <span class="badge bg-success status-badge">Success</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger status-badge">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
        });

        // Password validation
        const minLength = <?= $password_settings['password_min_length'] ?>;
        const requireUpper = <?= $password_settings['password_require_uppercase'] ? 'true' : 'false' ?>;
        const requireLower = <?= $password_settings['password_require_lowercase'] ? 'true' : 'false' ?>;
        const requireNumber = <?= $password_settings['password_require_number'] ? 'true' : 'false' ?>;
        const requireSpecial = <?= $password_settings['password_require_special'] ? 'true' : 'false' ?>;

        function validatePassword() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            let valid = true;

            // Check length
            const lengthEl = document.getElementById('req-length');
            if (password.length >= minLength) {
                lengthEl.classList.add('valid');
                lengthEl.classList.remove('invalid');
                lengthEl.querySelector('i').className = 'bi bi-check-circle-fill';
            } else {
                lengthEl.classList.remove('valid');
                lengthEl.classList.add('invalid');
                lengthEl.querySelector('i').className = 'bi bi-x-circle-fill';
                valid = false;
            }

            // Check uppercase
            if (requireUpper) {
                const upperEl = document.getElementById('req-upper');
                if (/[A-Z]/.test(password)) {
                    upperEl.classList.add('valid');
                    upperEl.classList.remove('invalid');
                    upperEl.querySelector('i').className = 'bi bi-check-circle-fill';
                } else {
                    upperEl.classList.remove('valid');
                    upperEl.classList.add('invalid');
                    upperEl.querySelector('i').className = 'bi bi-x-circle-fill';
                    valid = false;
                }
            }

            // Check lowercase
            if (requireLower) {
                const lowerEl = document.getElementById('req-lower');
                if (/[a-z]/.test(password)) {
                    lowerEl.classList.add('valid');
                    lowerEl.classList.remove('invalid');
                    lowerEl.querySelector('i').className = 'bi bi-check-circle-fill';
                } else {
                    lowerEl.classList.remove('valid');
                    lowerEl.classList.add('invalid');
                    lowerEl.querySelector('i').className = 'bi bi-x-circle-fill';
                    valid = false;
                }
            }

            // Check number
            if (requireNumber) {
                const numberEl = document.getElementById('req-number');
                if (/[0-9]/.test(password)) {
                    numberEl.classList.add('valid');
                    numberEl.classList.remove('invalid');
                    numberEl.querySelector('i').className = 'bi bi-check-circle-fill';
                } else {
                    numberEl.classList.remove('valid');
                    numberEl.classList.add('invalid');
                    numberEl.querySelector('i').className = 'bi bi-x-circle-fill';
                    valid = false;
                }
            }

            // Check special character
            if (requireSpecial) {
                const specialEl = document.getElementById('req-special');
                if (/[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\\/`~]/.test(password)) {
                    specialEl.classList.add('valid');
                    specialEl.classList.remove('invalid');
                    specialEl.querySelector('i').className = 'bi bi-check-circle-fill';
                } else {
                    specialEl.classList.remove('valid');
                    specialEl.classList.add('invalid');
                    specialEl.querySelector('i').className = 'bi bi-x-circle-fill';
                    valid = false;
                }
            }

            // Check match
            const matchEl = document.getElementById('req-match');
            if (password && confirm && password === confirm) {
                matchEl.classList.add('valid');
                matchEl.classList.remove('invalid');
                matchEl.querySelector('i').className = 'bi bi-check-circle-fill';
            } else {
                matchEl.classList.remove('valid');
                matchEl.classList.add('invalid');
                matchEl.querySelector('i').className = 'bi bi-x-circle-fill';
                valid = false;
            }

            // Enable/disable submit button
            document.getElementById('changePasswordBtn').disabled = !valid || !document.getElementById('current_password').value;
        }

        document.getElementById('new_password').addEventListener('input', validatePassword);
        document.getElementById('confirm_password').addEventListener('input', validatePassword);
        document.getElementById('current_password').addEventListener('input', validatePassword);
    </script>
</body>
</html>
