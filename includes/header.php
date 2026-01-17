<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo SITE_NAME; ?></title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        :root { --maroon: #800000; --blue: #003366; --sidebar-width: 260px; }
        
        html, body { height: 100%; margin: 0; overflow: hidden; font-family: 'Public Sans', sans-serif; background-color: #f4f7f6; }
        .wrapper { display: flex; width: 100%; height: 100vh; align-items: stretch; }

        /* --- SIDEBAR --- */
        #sidebar {
            min-width: var(--sidebar-width); max-width: var(--sidebar-width);
            background: var(--blue); color: #fff; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column; z-index: 1050; box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }
        #sidebar.active { margin-left: calc(-1 * var(--sidebar-width)); }
        #sidebar .sidebar-header { padding: 25px 20px; background: var(--maroon); text-align: center; }
        .sidebar-logo { width: 50px; margin-bottom: 8px; }
        #sidebar ul.components { padding: 15px 10px; flex-grow: 1; overflow-y: auto; }
        #sidebar ul li a {
            padding: 12px 15px; font-size: 0.9rem; display: flex; align-items: center;
            color: rgba(255, 255, 255, 0.7); text-decoration: none; border-radius: 8px; margin-bottom: 4px; transition: 0.2s;
        }
        #sidebar ul li a i { margin-right: 12px; font-size: 1.1rem; }
        #sidebar ul li a:hover, #sidebar ul li a.active { background: var(--maroon); color: #fff; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .logout-link { color: #ffbaba !important; text-decoration: none; font-weight: 600; display: flex; align-items: center; }

        /* --- CONTENT --- */
        #content { flex: 1; display: flex; flex-direction: column; overflow: hidden; width: 100%; }
        .text-maroon {color: var(--maroon) !important;}
        .navbar-custom { background: #fff; height: 70px; padding: 0 25px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 1000; }
        
        /* BURGER BUTTON - NO BORDER */
        .burger-btn { 
            background: transparent; 
            border: none !important; 
            outline: none !important; 
            box-shadow: none !important;
            color: var(--maroon); 
            font-size: 1.8rem; 
            cursor: pointer; 
            padding: 0;
            display: flex;
            align-items: center;
        }

        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-info-text { text-align: right; line-height: 1.2; }
        .user-info-text .name { display: block; font-weight: 700; font-size: 0.85rem; color: #333; }
        .user-info-text .role { font-size: 0.7rem; color: var(--blue); text-transform: uppercase; font-weight: 800; }
        .avatar-circle { width: 40px; height: 40px; background: var(--maroon); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        .main-content-body { flex: 1; overflow-y: auto; padding: 30px; }

        /* --- MOBILE OVERLAY & RESPONSIVE --- */
        .overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1040; display: none; }
        
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
    <div class="overlay" id="sidebarOverlay"></div>
    <div class="wrapper">