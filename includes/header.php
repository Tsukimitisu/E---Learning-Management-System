<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$session_role_id = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
$session_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$session_role_raw = isset($_SESSION['role']) ? (string)$_SESSION['role'] : 'guest';
$session_role_key = strtolower(trim($session_role_raw));
$session_role_key = preg_replace('/[\s-]+/', '_', $session_role_key);
$session_role_key = preg_replace('/[^a-z0-9_]/', '', $session_role_key);
if ($session_role_key === '') {
    $session_role_key = 'guest';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Real-time Socket.IO Loader -->
    <script>
        // Realtime runtime settings (config/init.php)
        window.ELMS_REALTIME_ENABLED = <?php echo ELMS_REALTIME_ENABLED ? 'true' : 'false'; ?>;
        window.ELMS_REALTIME_SERVER_URL = <?php echo json_encode(ELMS_REALTIME_SERVER_URL); ?>;
        window.ELMS_REALTIME_SERVER_PORT = <?php echo json_encode((int)ELMS_REALTIME_SERVER_PORT); ?>;
        window.ELMS_REALTIME_SOCKET_PATH = <?php echo json_encode((string)ELMS_REALTIME_SOCKET_PATH); ?>;
        window.USER_ID = <?php echo json_encode($session_user_id); ?>;
        window.USER_ROLE = <?php echo json_encode($session_role_key); ?>;
        window.USER_ROLE_ID = <?php echo json_encode($session_role_id); ?>;
        window.CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
    </script>
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <script src="/elms_system/assets/js/realtime_loader.js"></script>
    <script src="/elms_system/assets/js/realtime_client.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Modern Corporate Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
        :root {
            /* Brand Colors */
            --maroon: #800000;
            --maroon-light: #9e1b1b;
            --blue: #003366;
            --blue-dark: #002244;
            --text-light: #ecf0f1;
            --text-muted: #aeb9cc;
            
            /* Dimensions & Transitions */
            --sidebar-width: 280px;
            --header-height: 70px;
            --transition-speed: 0.3s;
        }

        body {
            font-family: 'Public Sans', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            overflow-x: hidden; 
        }

        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            position: relative;
        }

        /* ============================
           SIDEBAR - DESKTOP BEHAVIOR
           ============================ */
        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--blue) 0%, var(--blue-dark) 100%);
            color: var(--white);
            transition: margin-left var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            
            /* STACKING ORDER: BACK */
            z-index: 90; /* Low z-index ensures it stays behind modals */
            
            display: flex;
            flex-direction: column;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.05);
            border-right: 1px solid rgba(255,255,255,0.05);
            
            /* DESKTOP SPECIFIC: Sticks to the layout */
            position: sticky;
            top: 0;
            height: 100vh;
        }

        /* Hides sidebar to the left on Desktop */
        #sidebar.active {
            margin-left: calc(var(--sidebar-width) * -1);
        }

        /* ============================
           CONTENT - DESKTOP BEHAVIOR
           ============================ */
        #content {
            width: 100%;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f4f6f9;
            transition: all var(--transition-speed);
            position: relative;
            z-index: 1; /* Lowest Base Level */
        }

        /* Sidebar Header Styles */
        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(5px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
        }
        .sidebar-logo { width: 60px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3)); margin-bottom: 10px; transition: transform 0.3s ease; }
        .sidebar-header:hover .sidebar-logo { transform: scale(1.05); }
        .brand-text h3 { color: #fff; font-size: 1.2rem; letter-spacing: 1.5px; margin-bottom: 2px; }
        .brand-text p { color: var(--text-muted); font-size: 0.65rem; letter-spacing: 1px; font-weight: 500; }
        #sidebar ul.components { padding: 20px 15px; flex-grow: 1; overflow-y: auto; }
        #sidebar ul.components::-webkit-scrollbar { width: 5px; }
        #sidebar ul.components::-webkit-scrollbar-track { background: transparent; }
        #sidebar ul.components::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
        #sidebar ul li a { padding: 14px 18px; font-size: 0.9rem; display: flex; align-items: center; color: var(--text-muted); text-decoration: none; border-radius: 12px; transition: all 0.2s ease-in-out; position: relative; overflow: hidden; font-weight: 500; margin-bottom: 8px; }
        #sidebar ul li a i { margin-right: 15px; font-size: 1.15rem; width: 25px; text-align: center; transition: transform 0.2s; }
        #sidebar ul li a:hover { color: #ffffff; background: rgba(255, 255, 255, 0.08); transform: translateX(4px); }
        #sidebar ul li a:hover i { transform: scale(1.1); }
        #sidebar ul li a.active { color: #fff; background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%); box-shadow: 0 4px 15px rgba(128, 0, 0, 0.4); font-weight: 600; }
        #sidebar ul li a.active::after { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 4px; background: white; border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        .branch-badge { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; margin: 15px; padding: 12px; text-align: center; }
        
        .navbar-custom { 
            height: var(--header-height); 
            background: #fff; 
            padding: 0 30px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.03); 
            
            /* STACKING ORDER: BACK */
            z-index: 95; /* Just above Sidebar, but WAY below Modals */
            position: sticky;
            top: 0;
        }

        .burger-btn { background: transparent; border: none; color: var(--blue); font-size: 1.6rem; cursor: pointer; padding: 5px; border-radius: 8px; transition: background 0.2s; }
        .burger-btn:hover { background: #f0f2f5; }
        .notification-wrapper { position: relative; cursor: pointer; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.2s; }
        .notification-wrapper:hover { background: #f0f2f5; }
        /* Notification Dropdown */
        .notification-dropdown { position: absolute; top: calc(100% + 10px); right: 0; width: 380px; max-height: 480px; background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.12); z-index: 1050; display: none; overflow: hidden; animation: fadeInDropdown 0.2s ease-out; }
        .notification-dropdown.show { display: block; }
        .notification-dropdown-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 18px 12px; border-bottom: 1px solid #f0f0f0; }
        .notification-dropdown-header h6 { margin: 0; font-size: 1rem; font-weight: 700; color: #2c3e50; }
        .notif-mark-all-btn { background: none; border: none; color: var(--maroon); font-size: 0.8rem; font-weight: 600; cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: background 0.15s; }
        .notif-mark-all-btn:hover { background: rgba(128,0,0,0.08); }
        .notification-dropdown-body { max-height: 380px; overflow-y: auto; }
        .notification-dropdown-body::-webkit-scrollbar { width: 5px; }
        .notification-dropdown-body::-webkit-scrollbar-thumb { background: #ddd; border-radius: 10px; }
        .notif-item { display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; cursor: pointer; transition: background 0.15s; border-bottom: 1px solid #f8f8f8; text-decoration: none; color: inherit; }
        .notif-item:hover { background: #f8f9ff; }
        .notif-item.unread { background: #f0f4ff; }
        .notif-item.unread:hover { background: #e8edff; }
        .notif-icon { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .notif-icon.info { background: #e3f2fd; color: #1976d2; }
        .notif-icon.enrollment { background: #e8f5e9; color: #388e3c; }
        .notif-icon.grade { background: #fff3e0; color: #f57c00; }
        .notif-icon.material { background: #f3e5f5; color: #7b1fa2; }
        .notif-icon.announcement { background: #fff8e1; color: #f9a825; }
        .notif-icon.payment { background: #e0f7fa; color: #00838f; }
        .notif-icon.system { background: #fce4ec; color: #c62828; }
        .notif-content { flex: 1; min-width: 0; }
        .notif-content .notif-title { font-weight: 600; font-size: 0.85rem; color: #2c3e50; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notif-content .notif-msg { font-size: 0.8rem; color: #666; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 2px; }
        .notif-content .notif-time { font-size: 0.7rem; color: #999; }
        .notif-unread-dot { width: 8px; height: 8px; background: var(--maroon); border-radius: 50%; flex-shrink: 0; margin-top: 6px; }
        .notif-empty { padding: 40px 20px; text-align: center; color: #999; }
        .notif-empty i { font-size: 2.5rem; margin-bottom: 10px; display: block; color: #ddd; }
        @media (max-width: 480px) { .notification-dropdown { width: 320px; right: -40px; } }
        .user-profile { display: flex; align-items: center; gap: 15px; padding: 6px 8px 6px 15px; border-radius: 35px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
        .user-profile:hover { background-color: #f8f9fa; border-color: #e9ecef; }
        .user-info-text { text-align: right; line-height: 1.3; }
        .user-info-text .name { display: block; font-weight: 700; font-size: 0.9rem; color: #2c3e50; }
        .user-info-text .role { font-size: 0.7rem; color: var(--maroon); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .avatar-circle { width: 42px; height: 42px; background: linear-gradient(135deg, var(--maroon) 0%, #a00000 100%); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(128, 0, 0, 0.2); border: 2px solid #fff; }
        .profile-dropdown-menu { border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.08); border-radius: 16px; padding: 10px; min-width: 240px; margin-top: 15px !important; animation: fadeInDropdown 0.2s ease-out; z-index: 1060; }
        @keyframes fadeInDropdown { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .profile-dropdown-menu .dropdown-item { padding: 12px 15px; font-size: 0.9rem; border-radius: 8px; margin-bottom: 2px; color: #555; }
        .profile-dropdown-menu .dropdown-item:hover { background-color: #f0f7ff; color: var(--blue); }
        .profile-dropdown-menu .dropdown-item.text-danger:hover { background-color: #fff1f1; color: #dc3545; }
        .main-content-body { flex: 1; overflow-y: auto; padding: 30px; }

        /* ============================
           CRITICAL Z-INDEX FIXES
           ============================ */
        
        /* Ensure modals always appear ABOVE sidebar and navbar */
        .modal-backdrop { z-index: 1050 !important; }
        .modal { z-index: 1060 !important; }
        
        /* Ensure SweetAlert is top-most */
        .swal2-container { z-index: 1200 !important; }
        
        /* Fixed Headers in Content */
        .header-fixed-part { z-index: 50 !important; }

        /* ============================
           OVERLAY & MOBILE BEHAVIOR
           ============================ */
        
        /* DEFAULT: Hide Overlay on Desktop */
        .overlay {
            display: none !important;
        }

        /* MOBILE ONLY STYLES (Max Width 992px) */
        @media (max-width: 992px) {
            #sidebar {
                position: fixed; /* Fixed only on mobile */
                left: calc(var(--sidebar-width) * -1);
                height: 100%;
                margin-left: 0 !important; /* Reset desktop logic */
                transition: left var(--transition-speed);
                
                /* On mobile, Sidebar must be higher than content, but lower than modal */
                z-index: 1045 !important; 
            }

            /* On Mobile, Active means "Show it" */
            #sidebar.active {
                left: 0;
            }

            /* Enable Overlay only on Mobile */
            .overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.4);
                backdrop-filter: blur(3px);
                z-index: 1040 !important; /* Just below mobile sidebar */
                display: none !important; /* Hidden by default */
                opacity: 0;
                transition: opacity 0.3s;
            }

            .overlay.active {
                display: block !important;
                opacity: 1;
            }

            .main-content-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body
    data-user-id="<?php echo $session_user_id; ?>"
    data-user-role="<?php echo htmlspecialchars($session_role_key, ENT_QUOTES, 'UTF-8'); ?>"
    data-user-role-id="<?php echo $session_role_id; ?>"
>
    <div class="overlay" id="sidebarOverlay"></div>
    
    <div class="wrapper">
        <!-- INCLUDE SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <div id="content">
            <!-- NAVBAR (HEADER) -->
            <nav class="navbar-custom">
                <div class="d-flex align-items-center">
                    <button type="button" id="sidebarCollapse" class="burger-btn me-3">
                        <i class="bi bi-list"></i>
                    </button>
                </div>
                
                <!-- RIGHT SIDE: Notification Bell + User Profile -->
                <div class="d-flex align-items-center gap-3">
                    <div class="notification-wrapper" id="notificationBell">
                        <i class="bi bi-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" id="notificationBadge" style="display:none; font-size: 0.6rem; padding: 0.35em 0.65em;">
                            <span id="notificationCount">0</span>
                        </span>
                        <!-- Notification Dropdown -->
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-dropdown-header">
                                <h6>Notifications</h6>
                                <button class="notif-mark-all-btn" id="markAllReadBtn" title="Mark all as read">
                                    <i class="bi bi-check2-all"></i> Mark all read
                                </button>
                            </div>
                            <div class="notification-dropdown-body" id="notificationList">
                                <div class="notif-empty">
                                    <i class="bi bi-bell-slash"></i>
                                    No notifications yet
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DROPDOWN FOR ACCOUNT & LOGOUT -->
                    <div class="dropdown">
                    <div class="user-profile" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-info-text d-none d-sm-block">
                            <span class="role"><?php echo strtoupper(htmlspecialchars($_SESSION['role'])); ?></span>
                            <span class="name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User Account'); ?></span>
                        </div>
                        <div class="avatar-circle">
                            <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
                        </div>
                    </div>

                    <ul class="dropdown-menu dropdown-menu-end profile-dropdown-menu">
                        <li><span class="dropdown-header d-sm-none">Signed in as <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong></span></li>
                        <li><a class="dropdown-item" href="../../modules/common/account_settings.php"><i class="bi bi-gear"></i> Account Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger logout-trigger" href="javascript:void(0);"><i class="bi bi-box-arrow-right"></i> Sign Out</a></li>
                    </ul>
                    </div>
                </div>
            </nav>
            
            <!-- START OF CONTENT BODY -->
            <div class="main-content-body animate__animated animate__fadeIn">