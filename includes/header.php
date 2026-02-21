

<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Real-time Socket.IO Loader -->
    <script src="/elms_system/assets/js/realtime_loader.js"></script>
    <script src="/elms_system/assets/js/realtime_client.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Modern Corporate Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Frameworks: Bootstrap 5, Icons, Animations, SweetAlert -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --maroon: #800000;
            --blue: #003366;
            --white: #FFFFFF;
            --sidebar-width: 260px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden; 
            font-family: 'Public Sans', sans-serif;
            background-color: #f4f7f6;
        }

        .wrapper {
            display: flex;
            width: 100%;
            height: 100vh;
            align-items: stretch;
        }

        /* SIDEBAR STYLES */
        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            background: var(--blue);
            color: var(--white);
            transition: var(--transition);
            display: flex;
            flex-direction: column; 
            z-index: 1050;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }

        #sidebar.active { margin-left: calc(-1 * var(--sidebar-width)); }
        #sidebar .sidebar-header { padding: 30px 20px; background: var(--maroon); text-align: center; }
        .sidebar-logo { width: 55px; margin-bottom: 10px; filter: drop-shadow(0 4px 4px rgba(0,0,0,0.2)); }
        #sidebar ul.components { padding: 15px 10px; flex-grow: 1; overflow-y: auto; }
        
        #sidebar ul li a {
            padding: 12px 15px; font-size: 0.85rem; display: flex; align-items: center;
            color: rgba(255, 255, 255, 0.7); text-decoration: none; border-radius: 10px;
            margin-bottom: 4px; transition: 0.2s;
        }
        #sidebar ul li a i { margin-right: 12px; font-size: 1.1rem; }
        #sidebar ul li a:hover { background: rgba(255, 255, 255, 0.1); color: var(--white); }
        #sidebar ul li a.active {
            background: var(--maroon) !important; color: var(--white) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); font-weight: 600;
        }

        /* CONTENT & HEADER */
        #content {
            flex: 1; display: flex; flex-direction: column;
            height: 100vh; overflow: hidden; transition: var(--transition);
        }

        .navbar-custom {
            background: var(--white); height: 70px; padding: 0 25px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 1000;
        }

        .burger-btn {
            background: transparent !important; border: none !important;
            color: var(--maroon); font-size: 1.8rem; cursor: pointer; padding: 0;
        }

        /* DROPDOWN STYLES */
        .user-profile {
            display: flex; align-items: center; gap: 12px;
            cursor: pointer; padding: 5px 10px; border-radius: 30px;
            transition: background 0.2s;
        }
        .user-profile:hover { background-color: #f8f9fa; }
        
        .user-info-text { text-align: right; line-height: 1.2; }
        .user-info-text .name { display: block; font-weight: 700; font-size: 0.85rem; color: #333; }
        .user-info-text .role { font-size: 0.75rem; color: var(--blue); text-transform: uppercase; font-weight: 800; }
        
        .avatar-circle {
            width: 40px; height: 40px; background: var(--maroon); color: white;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: 2px solid #fff;
        }

        /* Dropdown Menu Adjustment */
        .profile-dropdown-menu {
            border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 10px; padding: 8px 0; min-width: 200px;
            margin-top: 10px !important;
        }
        .profile-dropdown-menu .dropdown-item { padding: 10px 20px; font-size: 0.9rem; }
        .profile-dropdown-menu .dropdown-item:hover { background-color: #f0f2f5; color: var(--blue); }
        .profile-dropdown-menu .dropdown-item.text-danger:hover { background-color: #fff5f5; color: #dc3545; }

        .main-content-body {
            flex: 1; overflow-y: auto; padding: 30px; background-color: #f8f9fa;
        }

        /* MOBILE FIXES */
        .overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5); z-index: 1040; display: none;
        }

        @media (max-width: 992px) {
            #sidebar { position: fixed; left: -260px; margin-left: 0 !important; height: 100%; z-index: 10000; }
            #sidebar.active { left: 0 !important; }
            #content { margin-left: 0 !important; }
            .overlay.active { display: block; }
            .main-content-body { padding: 15px; }
        }
    </style>
</head>
<body>
    <div class="overlay animate__animated animate__fadeIn" id="sidebarOverlay"></div>
    
    <div class="wrapper">
        <!-- INCLUDE SIDEBAR AUTOMATICALLY -->
        <?php include 'sidebar.php'; ?>

        <div id="content">
            <!-- NAVBAR (HEADER) -->
            <nav class="navbar-custom animate__animated animate__fadeInDown">
                <div class="d-flex align-items-center">
                    <button type="button" id="sidebarCollapse" class="burger-btn me-3">
                        <i class="bi bi-list"></i>
                    </button>
                    
                    <div class="position-relative ms-2" style="cursor:pointer;">
                        <i class="bi bi-bell fs-5 text-muted"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge" style="display:none; font-size: 0.5rem;">
                            <span id="notificationCount">0</span>
                        </span>
                    </div>
                </div>
                
                <!-- DROPDOWN FOR ACCOUNT & LOGOUT -->
                <div class="dropdown">
                    <div class="user-profile" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-info-text d-none d-sm-block">
                            <span class="role">USER <?php echo strtoupper(htmlspecialchars($_SESSION['role'])); ?></span>
                            <span class="name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User Account'); ?></span>
                        </div>
                        <div class="avatar-circle">
                            <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
                        </div>
                    </div>

                    <ul class="dropdown-menu dropdown-menu-end profile-dropdown-menu">
                        <li><span class="dropdown-header d-sm-none">Signed in as <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong></span></li>
                        <li><a class="dropdown-item" href="../../modules/common/account_settings.php"><i class="bi bi-person-gear me-2"></i> Account Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger logout-trigger" href="javascript:void(0);"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </nav>
            
            <!-- START OF CONTENT BODY -->
            <div class="main-content-body">

