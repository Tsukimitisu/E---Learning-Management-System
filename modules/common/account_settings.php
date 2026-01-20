<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../includes/email_helper.php';
require_once __DIR__ . '/../../includes/security_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /elms_system/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'] ?? $_SESSION['role'] ?? 0;

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
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

$role_names = [1 => 'Super Admin', 2 => 'School Admin', 3 => 'Branch Admin', 4 => 'Registrar', 5 => 'Teacher', 6 => 'Student'];
$role_name = $role_names[$role_id] ?? 'User';

$password_settings = [];
$settings_to_get = ['password_min_length', 'password_require_uppercase', 'password_require_lowercase', 'password_require_number', 'password_require_special', 'enable_google_login'];
foreach ($settings_to_get as $key) { $password_settings[$key] = get_security_setting($key); }

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        if (!password_verify($current_password, $user['password'])) { $error_message = 'Current password is incorrect.'; } 
        elseif ($new_password !== $confirm_password) { $error_message = 'New passwords do not match.'; } 
        else {
            $validation = validate_password($new_password);
            if ($validation !== true) { $error_message = implode('<br>', $validation); } 
            else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed, $user_id])) {
                    $success_message = 'Password updated successfully!';
                    try { $ip = get_client_ip(); $conn->query("INSERT INTO audit_logs (user_id, action, ip_address) VALUES ({$user_id}, 'Password changed', '{$ip}')"); } catch (Exception $e) {}
                } else { $error_message = 'Failed to update password.'; }
            }
        }
    } elseif ($action === 'unlink_google') {
        $stmt = $pdo->prepare("DELETE FROM oauth_tokens WHERE user_id = ? AND provider = 'google'");
        if ($stmt->execute([$user_id])) {
            $success_message = 'Google account unlinked successfully!';
            $user['google_email'] = null;
            try { $ip = get_client_ip(); $conn->query("INSERT INTO audit_logs (user_id, action, ip_address) VALUES ({$user_id}, 'Google account unlinked', '{$ip}')"); } catch (Exception $e) {}
        } else { $error_message = 'Failed to unlink Google account.'; }
    }
}

$login_history = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE email = ? ORDER BY attempted_at DESC LIMIT 10");
    $stmt->execute([$user['email']]);
    $login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$login_stats = ['total_logins' => 0, 'last_login' => null];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, MAX(attempted_at) as last_login FROM login_attempts WHERE email = ? AND success = 1");
    $stmt->execute([$user['email']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $login_stats['total_logins'] = $stats['total'] ?? 0;
    $login_stats['last_login'] = $stats['last_login'];
} catch (Exception $e) {}

$google_oauth_url = ($password_settings['enable_google_login'] && !$user['google_email']) ? get_google_oauth_url() : '';
$dashboard_paths = [1 => '/elms_system/modules/super_admin/dashboard.php', 2 => '/elms_system/modules/school_admin/dashboard.php', 3 => '/elms_system/modules/branch_admin/dashboard.php', 4 => '/elms_system/modules/registrar/dashboard.php', 5 => '/elms_system/modules/teacher/dashboard.php', 6 => '/elms_system/modules/student/dashboard.php'];
$back_url = $dashboard_paths[$role_id] ?? '/elms_system/dashboard.php';

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
    .settings-hero-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }
    .settings-banner { background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%); height: 80px; }
    .settings-avatar-wrapper { margin-top: -45px; }

    .card-modern { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .card-header-modern { background: #fcfcfc; padding: 15px 25px; border-bottom: 1px solid #eee; font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

    .password-requirements li { font-size: 0.8rem; margin-bottom: 5px; color: #888; transition: 0.3s; }
    .password-requirements li i { margin-right: 8px; }
    .password-requirements li.valid { color: #28a745; font-weight: 600; }
    .password-requirements li.invalid { color: #dc3545; }

    .btn-maroon-save { background-color: var(--maroon); color: white; border: none; border-radius: 10px; font-weight: 700; padding: 10px 25px; transition: 0.3s; }
    .btn-maroon-save:hover:not(:disabled) { background-color: #600000; transform: translateY(-2px); color: white; }
    .btn-maroon-save:disabled { opacity: 0.6; cursor: not-allowed; }

    .google-link-card { background: #fff; border: 1px solid #e1e4e8; border-radius: 12px; padding: 15px; display: flex; align-items: center; justify-content: space-between; }
    
    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-person-gear me-2 text-maroon"></i>Account Security</h4>
            <p class="text-muted small mb-0">Manage password, login history and linked accounts</p>
        </div>
       
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <?php if ($success_message): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 animate__animated animate__headShake">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4 animate__animated animate__shakeX">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Profile Column -->
        <div class="col-lg-4 animate__animated animate__fadeInLeft">
            <div class="settings-hero-card mb-4">
                <div class="settings-banner"></div>
                <div class="card-body p-4 text-center">
                    <div class="settings-avatar-wrapper">
                        <div class="avatar-circle mx-auto border-4 border-white shadow" style="width: 90px; height: 90px; font-size: 2.5rem;">
                            <?php echo strtoupper(substr($user['full_name'] ?? 'U', 0, 1)); ?>
                        </div>
                    </div>
                    <h5 class="fw-bold mt-3 mb-1 text-dark"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                    <span class="badge bg-blue rounded-pill px-3 mb-4"><?php echo strtoupper($role_name); ?></span>
                    
                    <div class="text-start border-top pt-4">
                        <div class="mb-3">
                            <label class="text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Registered Email</label>
                            <div class="fw-bold text-dark small text-truncate"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Account Status</label>
                            <div><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3"><?php echo ucfirst($user['status']); ?></span></div>
                        </div>
                        <div class="mb-0">
                            <label class="text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Member Since</label>
                            <div class="small text-muted"><i class="bi bi-calendar3 me-2"></i><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Google OAuth Card -->
            <?php if ($password_settings['enable_google_login']): ?>
            <div class="card-modern">
                <div class="card-header-modern bg-white"><i class="bi bi-google me-2"></i>Connected Services</div>
                <div class="card-body p-4">
                    <?php if ($user['google_email']): ?>
                        <div class="google-link-card mb-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-google text-primary fs-4 me-3"></i>
                                <div>
                                    <div class="fw-bold small">Google Account</div>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['google_email']); ?></small>
                                </div>
                            </div>
                            <form method="post" onsubmit="return confirm('Are you sure you want to unlink Google?');">
                                <input type="hidden" name="action" value="unlink_google">
                                <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-x-circle fs-5"></i></button>
                            </form>
                        </div>
                    <?php elseif ($google_oauth_url): ?>
                        <a href="<?php echo htmlspecialchars($google_oauth_url); ?>" class="btn btn-light border w-100 fw-bold py-2 shadow-sm">
                            <i class="bi bi-google me-2"></i> Link Google Account
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Security Forms Column -->
        <div class="col-lg-8 animate__animated animate__fadeInRight">
            <div class="card-modern mb-4">
                <div class="card-header-modern"><i class="bi bi-key-fill me-2"></i>Update Password</div>
                <div class="card-body p-4">
                    <form method="post" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Current Password</label>
                                <div class="input-group shadow-sm">
                                    <input type="password" class="form-control border-light" id="current_password" name="current_password" required>
                                    <button class="btn btn-outline-secondary border-light toggle-password" type="button" data-target="current_password"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">New Password</label>
                                <div class="input-group shadow-sm">
                                    <input type="password" class="form-control border-light" id="new_password" name="new_password" required>
                                    <button class="btn btn-outline-secondary border-light toggle-password" type="button" data-target="new_password"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Confirm New Password</label>
                                <div class="input-group shadow-sm">
                                    <input type="password" class="form-control border-light" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary border-light toggle-password" type="button" data-target="confirm_password"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- Requirements Checklist -->
                        <div class="p-3 rounded-3 bg-light border mb-4">
                            <p class="small fw-bold text-muted text-uppercase mb-2" style="font-size: 0.65rem;">Password Requirements</p>
                            <ul class="list-unstyled mb-0 password-requirements" id="passwordRequirements">
                                <li id="req-length"><i class="bi bi-circle"></i> Minimum <?php echo $password_settings['password_min_length']; ?> characters</li>
                                <?php if ($password_settings['password_require_uppercase']): ?><li id="req-upper"><i class="bi bi-circle"></i> One uppercase letter</li><?php endif; ?>
                                <?php if ($password_settings['password_require_lowercase']): ?><li id="req-lower"><i class="bi bi-circle"></i> One lowercase letter</li><?php endif; ?>
                                <?php if ($password_settings['password_require_number']): ?><li id="req-number"><i class="bi bi-circle"></i> One numeric digit</li><?php endif; ?>
                                <?php if ($password_settings['password_require_special']): ?><li id="req-special"><i class="bi bi-circle"></i> One special character</li><?php endif; ?>
                                <li id="req-match"><i class="bi bi-circle"></i> Password confirmation match</li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-maroon-save shadow-sm" id="changePasswordBtn" disabled>
                            <i class="bi bi-shield-check me-2"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Login History Card -->
            <div class="card-modern">
                <div class="card-header-modern bg-white d-flex justify-content-between">
                    <span><i class="bi bi-clock-history me-2"></i>Recent Sign-ins</span>
                    <span class="badge bg-light text-muted border"><?php echo $login_stats['total_logins']; ?> Total Sessions</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-4">Timestamp</th><th>IP Address</th><th class="text-center">Status</th></tr></thead>
                        <tbody>
                            <?php if (empty($login_history)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted small">No activity found.</td></tr>
                            <?php else: foreach ($login_history as $attempt): ?>
                                <tr>
                                    <td class="ps-4 small fw-bold text-dark"><?php echo date('M d, Y • H:i:s', strtotime($attempt['attempted_at'])); ?></td>
                                    <td><code class="text-maroon"><?php echo htmlspecialchars($attempt['ip_address'] ?? '0.0.0.0'); ?></code></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-<?php echo !empty($attempt['success']) ? 'success' : 'danger'; ?> px-3">
                                            <?php echo !empty($attempt['success']) ? 'AUTHORIZED' : 'FAILED'; ?>
                                        </span>
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

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & WIRED --- -->
<script>
    // Visibility Toggle
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const target = document.getElementById(this.dataset.target);
            const icon = this.querySelector('i');
            if (target.type === 'password') { target.type = 'text'; icon.className = 'bi bi-eye-slash'; }
            else { target.type = 'password'; icon.className = 'bi bi-eye'; }
        });
    });

    // Password Real-time Validation (Logic strictly preserved)
    const minLength = <?php echo $password_settings['password_min_length']; ?>;
    const requireUpper = <?php echo $password_settings['password_require_uppercase'] ? 'true' : 'false'; ?>;
    const requireLower = <?php echo $password_settings['password_require_lowercase'] ? 'true' : 'false'; ?>;
    const requireNumber = <?php echo $password_settings['password_require_number'] ? 'true' : 'false'; ?>;
    const requireSpecial = <?php echo $password_settings['password_require_special'] ? 'true' : 'false'; ?>;

    function validatePassword() {
        const password = document.getElementById('new_password').value;
        const confirm = document.getElementById('confirm_password').value;
        let valid = true;

        const updateReq = (id, condition) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (condition) { el.classList.add('valid'); el.classList.remove('invalid'); el.querySelector('i').className = 'bi bi-check-circle-fill'; }
            else { el.classList.remove('valid'); el.classList.add('invalid'); el.querySelector('i').className = 'bi bi-circle'; valid = false; }
        };

        updateReq('req-length', password.length >= minLength);
        if (requireUpper) updateReq('req-upper', /[A-Z]/.test(password));
        if (requireLower) updateReq('req-lower', /[a-z]/.test(password));
        if (requireNumber) updateReq('req-number', /[0-9]/.test(password));
        if (requireSpecial) updateReq('req-special', /[!@#$%^&*(),.?":{}|<>]/.test(password));
        updateReq('req-match', password && confirm && password === confirm);

        document.getElementById('changePasswordBtn').disabled = !valid || !document.getElementById('current_password').value;
    }

    ['new_password', 'confirm_password', 'current_password'].forEach(id => {
        document.getElementById(id).addEventListener('input', validatePassword);
    });
</script>
</body>
</html>