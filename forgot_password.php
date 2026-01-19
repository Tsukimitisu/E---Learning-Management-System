<?php
/**
 * Forgot Password Page
 * ELMS - Electronic Learning Management System
 */
require_once 'config/init.php';
require_once 'includes/email_helper.php';

$message = '';
$error = '';
$show_form = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        // Check if user exists
        $stmt = $conn->prepare("
            SELECT u.id, up.first_name 
            FROM users u 
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE u.email = ? AND u.status = 'active'
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user) {
            // Create reset token
            $token = create_password_reset_token($user['id']);
            
            if ($token) {
                // Send reset email
                $reset_link = BASE_URL . "reset_password.php?token=" . $token;
                $result = send_password_reset($email, $user['first_name'] ?: 'User', $reset_link);
                
                if ($result['success']) {
                    $message = "Password reset instructions have been sent to your email address.";
                    $show_form = false;
                } else {
                    $error = "Failed to send reset email. Please try again later.";
                }
            } else {
                $error = "Failed to generate reset token. Please try again.";
            }
        } else {
            // Don't reveal if email exists or not for security
            $message = "If an account exists with that email, you will receive password reset instructions.";
            $show_form = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        .forgot-container {
            max-width: 450px;
            margin: 0 auto;
        }
        .back-link {
            color: #003366;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container forgot-container">
            <div class="login-header text-center mb-4">
                <img src="assets/image/favicon.png" alt="Logo" class="login-logo mb-3" style="max-width: 80px;">
                <h3 class="fw-bold text-maroon">Forgot Password</h3>
                <p class="text-muted">Enter your email to receive reset instructions</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($show_form): ?>
                <form method="POST" class="login-form">
                    <div class="mb-4">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="Enter your email" required autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-maroon w-100 mb-3">
                        <i class="bi bi-send me-2"></i>Send Reset Link
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="index.php" class="back-link">
                    <i class="bi bi-arrow-left me-2"></i>Back to Login
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
