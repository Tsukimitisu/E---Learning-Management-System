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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    
    <!-- Modern Corporate Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --maroon: #800000;
            --blue: #003366;
            --white: #FFFFFF;
            --soft-gray: #f4f7f6;
        }

        body, html {
            height: 100%;
            font-family: 'Public Sans', sans-serif;
            background-color: var(--soft-gray);
            overflow: hidden;
        }

        .auth-wrapper {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: radial-gradient(circle at top right, rgba(0, 51, 102, 0.05), transparent),
                        radial-gradient(circle at bottom left, rgba(128, 0, 0, 0.05), transparent);
        }

        .auth-card {
            background: var(--white);
            width: 100%;
            max-width: 450px;
            border-radius: 25px;
            padding: 45px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
        }

        .auth-logo {
            width: 85px;
            height: 85px;
            background: white;
            border-radius: 50%;
            padding: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            margin: -90px auto 25px auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-logo img {
            width: 100%;
            object-fit: contain;
        }

        .title-text {
            color: var(--blue);
            font-weight: 800;
            letter-spacing: -0.5px;
            font-size: 1.6rem;
        }

        .subtitle-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }

        /* Form Styling */
        .form-label {
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--blue);
        }

        .input-group {
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            box-shadow: 0 5px 15px rgba(128, 0, 0, 0.1);
            border-color: var(--maroon) !important;
        }

        .input-group-text {
            background-color: #fdfdfd;
            border: none;
            color: #adb5bd;
            padding-left: 15px;
        }

        .form-control {
            border: none;
            padding: 12px 15px;
            font-size: 0.95rem;
            background-color: #fdfdfd;
        }

        .form-control:focus {
            box-shadow: none;
            background-color: #fff;
        }

        .input-group:focus-within .input-group-text {
            color: var(--maroon);
            background-color: #fff;
        }

        /* Password Toggle Button Styling */
        .btn-toggle-password {
            background-color: #fdfdfd;
            border: none;
            color: #adb5bd;
            z-index: 5;
            padding-right: 15px;
        }
        
        .btn-toggle-password:hover {
            color: var(--blue);
            background-color: #fdfdfd;
        }

        .input-group:focus-within .btn-toggle-password {
            background-color: #fff;
        }

        /* Button Styling */
        .btn-maroon {
            background: linear-gradient(135deg, var(--maroon) 0%, #a00000 100%);
            border: none;
            color: white;
            font-weight: 700;
            padding: 14px;
            border-radius: 12px;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.85rem;
            box-shadow: 0 10px 20px rgba(128, 0, 0, 0.2);
        }

        .btn-maroon:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(128, 0, 0, 0.3);
            color: white;
        }

        .btn-outline-primary {
            color: var(--blue);
            border-color: var(--blue);
            font-weight: 600;
            border-radius: 12px;
            padding: 12px;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--blue);
            color: white;
        }

        .back-link {
            color: var(--blue);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
        }

        .back-link:hover {
            color: var(--maroon);
        }

        .alert {
            border-radius: 15px;
            border: none;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 15px 20px;
        }

        .alert-success { background-color: #e6f4ea; color: #1e7e34; }
        .alert-danger { background-color: #fceaea; color: #dc3545; }

        .req-box {
            background-color: #f8f9fa;
            border-left: 3px solid var(--maroon);
            padding: 15px;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #555;
            margin-bottom: 20px;
        }
        .req-box ul { padding-left: 20px; margin-bottom: 0; }

        @media (max-width: 576px) {
            .auth-card { padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card animate__animated animate__zoomIn">
            
            <div class="auth-logo animate__animated animate__bounceInDown animate__delay-1s">
                <img src="assets/image/datamexlogo.png" alt="ELMS Logo">
            </div>

            <div class="text-center">
                <h3 class="title-text mb-1">Reset Password</h3>
                <p class="subtitle-text">Securely update your account credentials</p>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success animate__animated animate__fadeIn">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $message; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-maroon w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Go to Login
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger animate__animated animate__shakeX">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Valid Token: Show Form -->
            <?php if ($valid_token): ?>
                <div class="req-box animate__animated animate__fadeIn">
                    <strong class="text-primary"><i class="bi bi-shield-lock me-1"></i> Requirements:</strong>
                    <ul class="mt-2">
                        <?php foreach ($password_requirements as $req): ?>
                            <li><?php echo $req; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <form method="POST" class="mt-2 animate__animated animate__fadeInUp">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Enter new password" required minlength="<?php echo $min_length; ?>">
                            <button class="btn btn-toggle-password" type="button" onclick="togglePassword(this, 'password')">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                            <button class="btn btn-toggle-password" type="button" onclick="togglePassword(this, 'confirm_password')">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-maroon w-100 mb-3">
                        <i class="bi bi-check-circle-fill me-2"></i> Reset Password
                    </button>
                </form>

            <!-- Invalid Token/Message Logic -->
            <?php elseif (!$message): ?>
                <div class="text-center py-3 animate__animated animate__fadeIn">
                    <p class="small text-muted mb-3">Link expired or invalid?</p>
                    <a href="forgot_password.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-envelope me-2"></i> Request New Link
                    </a>
                </div>
            <?php endif; ?>

            <!-- Footer Link -->
            <div class="text-center mt-4 border-top pt-4">
                <a href="index.php" class="back-link">
                    <i class="bi bi-arrow-left-circle me-2 fs-5"></i> Back to login portal
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