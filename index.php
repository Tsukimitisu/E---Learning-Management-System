<?php
require_once 'config/init.php';
require_once 'includes/security_helper.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Check for error messages
$error_message = '';
$info_message = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'google_disabled':
            $error_message = 'Google Sign-In is not enabled. Please contact your administrator.';
            break;
        case 'google_denied':
            $error_message = 'Google Sign-In was cancelled.';
            break;
        case 'google_failed':
            $error_message = 'Failed to sign in with Google. Please try again.';
            break;
        case 'no_account':
            $email = $_GET['email'] ?? '';
            $error_message = "No account found for this Google account" . ($email ? " ({$email})" : "") . ". Please contact your administrator.";
            break;
        case 'session_expired':
            $info_message = 'Your session has expired. Please sign in again.';
            break;
    }
}

// Get Google OAuth settings
$google_enabled = get_security_setting('enable_google_login', '0') === '1';
$google_url = $google_enabled ? get_google_oauth_url() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .divider span {
            padding: 0 15px;
            color: #6c757d;
            font-size: 0.85rem;
        }
        .btn-google {
            background: #ffffff;
            border: 2px solid #e0e0e0;
            color: #3c4043;
            font-weight: 500;
            font-size: 14px;
            padding: 12px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .btn-google:hover {
            background: #f8f9fa;
            border-color: #4285f4;
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.25);
            transform: translateY(-1px);
        }
        .btn-google:active {
            transform: translateY(0);
        }
        .btn-google svg {
            width: 20px;
            height: 20px;
        }
        .lockout-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeeba);
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">
        
        <!-- LEFT PANEL -->
        <div class="col-lg-7 d-none d-lg-block position-relative overflow-hidden">
            <div class="hero-image"></div>
            <div class="hero-overlay"></div>
            
            <div class="hero-content d-flex flex-column justify-content-between h-100 p-5">
                <div class="animate__animated animate__fadeInDown">
              
                    <div class="brand-name-top"></div>
                </div>

                <div class="animate__animated animate__fadeInUp">
                    <h1 class="display-3 fw-bold text-white mb-3">Datamex College of Saint Adeline</h1>
                    <p class="hero-description">
                        The Electronic Learning Management System transforming educational resources into digital insights to help you gain an edge in modern learning.
                    </p>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL (White Background) -->
        <div class="col-lg-5 d-flex flex-column justify-content-center align-items-center bg-white position-relative">
            
            <div class="login-form-container animate__animated animate__fadeIn">
                <div class="text-center mb-4">
                    <img src="assets/image/datamexlogo.png" alt="Datamex Logo" class="main-form-logo mb-3">
                    <h4 class="fw-bold">Welcome Back</h4>
                    <p class="text-muted small">Please sign in to your account</p>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger animate__animated animate__shakeX">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($info_message): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i><?php echo htmlspecialchars($info_message); ?>
                    </div>
                <?php endif; ?>

                <div id="alertMessage" class="alert d-none animate__animated" role="alert"></div>
                
                <div id="lockoutWarning" class="lockout-warning d-none">
                    <i class="bi bi-shield-exclamation me-2"></i>
                    <strong>Account Temporarily Locked</strong>
                    <p class="mb-0 mt-1 small">Too many failed attempts. Please try again in <span id="lockoutTime">0</span> minutes.</p>
                </div>

                <form id="loginForm">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" class="form-control custom-input" id="email" name="email" placeholder="email@elms.com" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control custom-input" id="password" name="password" placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label text-muted small" for="remember">Remember me</label>
                        </div>
                        <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-signin text-white" id="loginBtn">
                            <span id="btnText">Sign In</span>
                            <span id="btnLoader" class="spinner-border spinner-border-sm d-none ms-2" role="status"></span>
                        </button>
                    </div>
                </form>

                <?php if ($google_url): ?>
                    <div class="divider">
                        <span>or continue with</span>
                    </div>

                    <div class="d-grid">
                        <a href="<?php echo htmlspecialchars($google_url); ?>" class="btn btn-google">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                            </svg>
                            Sign in with Google
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="powered-by">
                <span>All Right Reserved</span>
                <img src="assets/image/datamexlogo.png" alt="Datamex Logo">
                <span class="fw-bold" style="color: var(--primary-maroon);">DCSA</span>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const alertMessage = document.getElementById('alertMessage');
    const lockoutWarning = document.getElementById('lockoutWarning');
    const lockoutTime = document.getElementById('lockoutTime');
    const btnText = document.getElementById('btnText');
    const btnLoader = document.getElementById('btnLoader');
    const loginBtn = document.getElementById('loginBtn');
    const togglePassword = document.getElementById('togglePassword');
    
    // Toggle password visibility
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    }

    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Show loading
        btnText.textContent = 'Signing in...';
        btnLoader.classList.remove('d-none');
        loginBtn.disabled = true;
        alertMessage.classList.add('d-none');
        lockoutWarning.classList.add('d-none');

        const formData = new FormData(loginForm);

        try {
            const response = await fetch('auth/login_process.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                alertMessage.classList.remove('d-none', 'alert-danger');
                alertMessage.classList.add('alert-success', 'animate__fadeIn');
                alertMessage.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + data.message;
                
                setTimeout(() => {
                    window.location.href = data.redirect || 'dashboard.php';
                }, 500);
            } else {
                // Check if account is locked
                if (data.locked) {
                    lockoutWarning.classList.remove('d-none');
                    lockoutTime.textContent = data.lockout_remaining || 15;
                    alertMessage.classList.add('d-none');
                } else {
                    alertMessage.classList.remove('d-none', 'alert-success');
                    alertMessage.classList.add('alert-danger', 'animate__shakeX');
                    alertMessage.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + data.message;
                    
                    // Show remaining attempts if provided
                    if (data.attempts_remaining !== undefined) {
                        alertMessage.innerHTML += '<br><small class="text-muted">' + data.attempts_remaining + ' attempts remaining before lockout</small>';
                    }
                }
                
                btnText.textContent = 'Sign In';
                btnLoader.classList.add('d-none');
                loginBtn.disabled = false;
            }
        } catch (error) {
            alertMessage.classList.remove('d-none', 'alert-success');
            alertMessage.classList.add('alert-danger', 'animate__shakeX');
            alertMessage.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Connection error. Please try again.';
            
            btnText.textContent = 'Sign In';
            btnLoader.classList.add('d-none');
            loginBtn.disabled = false;
        }
    });
});
</script>
</body>
</html>