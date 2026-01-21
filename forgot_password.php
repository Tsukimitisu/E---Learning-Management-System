<?php
/**
 * Forgot Password Page
 * ELMS - Electronic Learning Management System
 */
require_once 'config/init.php';
require_once 'includes/email_helper.php';

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$message = '';
$error = '';
$show_form = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
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
            $token = create_password_reset_token($user['id']);
            if ($token) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    
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

        .input-group-text {
            background-color: transparent;
            border-right: none;
            color: #adb5bd;
            padding-left: 15px;
        }

        .form-control {
            border-left: none;
            padding: 12px 15px;
            font-size: 0.95rem;
            border-radius: 0 10px 10px 0;
            background-color: #fdfdfd;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #dee2e6;
            background-color: #fff;
        }

        .input-group:focus-within .input-group-text {
            border-color: #dee2e6;
            color: var(--maroon);
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

        .btn-maroon:active {
            transform: translateY(0);
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
                <h3 class="title-text mb-1">Forgot Password</h3>
                <p class="subtitle-text">Enter your credentials to recover access</p>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success animate__animated animate__fadeIn">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger animate__animated animate__shakeX">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <?php if ($show_form): ?>
                <form method="POST" class="mt-2">
                    <div class="mb-4">
                        <label class="form-label">Registered Email</label>
                        <div class="input-group shadow-sm" style="border-radius: 10px; overflow: hidden; border: 1px solid #dee2e6;">
                            <span class="input-group-text"><i class="bi bi-envelope-at"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="example@email.com" required autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-maroon w-100 mb-3">
                        <i class="bi bi-send-fill me-2"></i> Send Reset Link
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center py-3">
                    <p class="small text-muted">Please check your spam folder if you don't see the email within a few minutes.</p>
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
</body>
</html>