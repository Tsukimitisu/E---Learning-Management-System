<?php
/**
 * Reset Password Page
 * ELMS - Electronic Learning Management System
 */
require_once 'config/init.php';
require_once 'includes/email_helper.php';

$message = '';
$error = '';
$valid_token = false;
$token_data = null;

$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!empty($token)) {
    $token_data = verify_password_reset_token($token);
    if ($token_data) {
        $valid_token = true;
    } else {
        $error = "This password reset link is invalid or has expired.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Validate password strength
        $validation = validate_password($password);
        if (!$validation['valid']) {
            $error = implode('<br>', $validation['errors']);
        } else {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $token_data['user_id']);
            
            if ($stmt->execute()) {
                // Mark token as used
                use_password_reset_token($token);
                $message = "Your password has been reset successfully. You can now login with your new password.";
                $valid_token = false; // Hide the form
            } else {
                $error = "Failed to reset password. Please try again.";
            }
        }
    }
}

// Get password requirements for display
$password_requirements = [];
$min_length = get_security_setting('password_min_length', 8);
$password_requirements[] = "At least {$min_length} characters";
if (get_security_setting('password_require_uppercase', '1') === '1') {
    $password_requirements[] = "One uppercase letter (A-Z)";
}
if (get_security_setting('password_require_lowercase', '1') === '1') {
    $password_requirements[] = "One lowercase letter (a-z)";
}
if (get_security_setting('password_require_number', '1') === '1') {
    $password_requirements[] = "One number (0-9)";
}
if (get_security_setting('password_require_special', '0') === '1') {
    $password_requirements[] = "One special character (!@#$%)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        .reset-container {
            max-width: 450px;
            margin: 0 auto;
        }
        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .password-requirements ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        .password-requirements li {
            font-size: 0.875rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container reset-container">
            <div class="login-header text-center mb-4">
                <img src="assets/image/favicon.png" alt="Logo" class="login-logo mb-3" style="max-width: 80px;">
                <h3 class="fw-bold text-maroon">Reset Password</h3>
                <p class="text-muted">Create a new password for your account</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
                </div>
                <div class="text-center">
                    <a href="index.php" class="btn btn-maroon">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($valid_token): ?>
                <div class="password-requirements">
                    <strong><i class="bi bi-shield-check me-2"></i>Password Requirements:</strong>
                    <ul class="mt-2">
                        <?php foreach ($password_requirements as $req): ?>
                            <li><?php echo $req; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <form method="POST" class="login-form">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Enter new password" required minlength="<?php echo $min_length; ?>">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this, 'password')">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this, 'confirm_password')">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-maroon w-100">
                        <i class="bi bi-check-lg me-2"></i>Reset Password
                    </button>
                </form>
            <?php elseif (!$message): ?>
                <div class="text-center">
                    <p class="text-muted mb-4">Need a new reset link?</p>
                    <a href="forgot_password.php" class="btn btn-outline-primary">
                        <i class="bi bi-envelope me-2"></i>Request New Link
                    </a>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="index.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left me-2"></i>Back to Login
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(btn, inputName) {
            const input = document.querySelector(`input[name="${inputName}"]`);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>
