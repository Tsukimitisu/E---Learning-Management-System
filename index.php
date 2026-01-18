<?php
require_once 'config/init.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
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
    <link rel="stylesheet" href="assets/css/login.css">
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
                <div class="text-center mb-5">
                    <img src="assets/image/datamexlogo.png" alt="Datamex Logo" class="main-form-logo mb-3">
                    <h4 class="fw-bold">Welcome Back</h4>
                    <p class="text-muted small">Please sign in to your account</p>
                </div>

                <div id="alertMessage" class="alert d-none animate__animated animate__shakeX" role="alert"></div>

                <form id="loginForm">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" class="form-control custom-input" id="email" name="email" placeholder="email@elms.com" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Password</label>
                        <input type="password" class="form-control custom-input" id="password" name="password" placeholder="••••••••" required>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember">
                            <label class="form-check-label text-muted small" for="remember">Remember me</label>
                        </div>
                        <!-- Forgot Password Restored -->
                        <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-signin text-white">
                            <span id="btnText">Sign In</span>
                            <span id="btnLoader" class="spinner-border spinner-border-sm d-none" role="status"></span>
                        </button>
                    </div>
                </form>
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
<script src="assets/js/login.js"></script>
</body>
</html>