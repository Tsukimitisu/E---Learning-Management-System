<?php
require_once 'config/init.php';

// Redirect if already logged in
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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <!-- Left Panel - Maroon Background -->
            <div class="col-md-6 d-flex align-items-center justify-content-center" style="background-color: #800000;">
                <div class="text-center text-white">
                    <img src="assets/img/logo.png" alt="ELMS Logo" class="img-fluid mb-4" style="max-width: 200px;">
                    <h1 class="display-4 fw-bold">ELMS</h1>
                    <p class="lead">Electronic Learning Management System</p>
                    <p class="text-white-50">Datamex Educational Solutions</p>
                </div>
            </div>

            <!-- Right Panel - Login Form -->
            <div class="col-md-6 d-flex align-items-center justify-content-center bg-light">
                <div class="w-100" style="max-width: 400px; padding: 2rem;">
                    <div class="card shadow-lg border-0">
                        <div class="card-body p-5">
                            <h3 class="card-title text-center mb-4" style="color: #003366;">Welcome Back</h3>
                            
                            <!-- Alert Messages -->
                            <div id="alertMessage" class="alert d-none" role="alert"></div>

                            <!-- Login Form -->
                            <form id="loginForm">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-lg text-white" style="background-color: #800000;">
                                        <span id="btnText">Sign In</span>
                                        <span id="btnLoader" class="spinner-border spinner-border-sm d-none" role="status"></span>
                                    </button>
                                </div>
                            </form>

                            <div class="text-center mt-4">
                                <small class="text-muted">© 2025 Datamex. All rights reserved.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/login.js"></script>
</body>
</html>