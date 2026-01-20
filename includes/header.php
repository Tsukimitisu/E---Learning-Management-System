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

        /* --- LAYOUT ENGINE: FIXED VIEWPORT --- */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden; /* Prevents white background glitch on sidebar when scrolling */
            font-family: 'Public Sans', sans-serif;
            background-color: #f4f7f6;
        }

        .wrapper {
            display: flex;
            width: 100%;
            height: 100vh;
            align-items: stretch;
        }

        /* --- SIDEBAR: CORPORATE BLUE --- */
        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            background: var(--blue);
            color: var(--white);
            transition: var(--transition);
            display: flex;
            flex-direction: column; /* For pinning footer to bottom */
            z-index: 1050;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }

        /* Desktop Toggle Logic */
        #sidebar.active {
            margin-left: calc(-1 * var(--sidebar-width));
        }

        #sidebar .sidebar-header {
            padding: 30px 20px;
            background: var(--maroon);
            text-align: center;
        }

        .sidebar-logo {
            width: 55px;
            margin-bottom: 10px;
            filter: drop-shadow(0 4px 4px rgba(0,0,0,0.2));
        }

        #sidebar ul.components {
            padding: 15px 10px;
            flex-grow: 1;
            overflow-y: auto; /* Menu items scroll if they exceed height */
        }

        #sidebar ul li a {
            padding: 12px 15px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 4px;
            transition: 0.2s;
        }

        #sidebar ul li a i {
            margin-right: 12px;
            font-size: 1.1rem;
        }

        #sidebar ul li a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
        }

        #sidebar ul li a.active {
            background: var(--maroon) !important;
            color: var(--white) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            font-weight: 600;
        }

        .sidebar-footer {
    padding: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.15); /* Slightly darker for contrast */
}

.footer-link {
    padding: 12px 15px; /* Increased vertical padding */
    font-size: 0.85rem;
    display: flex; /* Enables flexbox for icon/text alignment */
    align-items: center;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    margin-bottom: 2px;
}

.footer-link i {
    font-size: 1.1rem;
    margin-right: 15px; /* FIX: Added margin to prevent overlap */
    min-width: 20px;    /* FIX: Ensures text is aligned perfectly */
    display: flex;
    justify-content: center;
}

.footer-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff !important;
}

/* Specific Logout Style */
.logout-link {
    color: #ffbaba !important; /* Soft red for logout visibility */
    font-weight: 600;
}

.logout-link i {
    color: #ffbaba;
}
        /* --- CONTENT AREA & TOP NAVBAR --- */
        #content {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            transition: var(--transition);
        }

        .navbar-custom {
            background: var(--white);
            height: 70px;
            padding: 0 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            z-index: 1000;
        }

        /* BURGER BUTTON - ABSOLUTELY NO BORDER */
        .burger-btn {
            background: transparent !important;
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

        /* USER PROFILE STYLING */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-info-text {
            text-align: right;
            line-height: 1.2;
        }

        .user-info-text .name {
            display: block;
            font-weight: 700;
            font-size: 0.85rem;
            color: #333;
        }

        .user-info-text .role {
            font-size: 0.75rem;
            color: var(--blue);
            text-transform: uppercase;
            font-weight: 800;
        }

        .avatar-circle {
            width: 40px;
            height: 40px;
            background: var(--maroon);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border: 2px solid #fff;
        }

        /* Main Content Scrollable Area */
        .main-content-body {
            flex: 1;
            overflow-y: auto; /* Only this part scrolls */
            padding: 30px;
            background-color: #f8f9fa;
        }

        /* --- MOBILE OVERLAY & RESPONSIVE FIXES --- */
        .overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            display: none;
        }

        @media (max-width: 992px) {
            #sidebar {
                position: fixed;
                left: -260px; /* Stay hidden on mobile by default */
                margin-left: 0 !important;
                height: 100%;
                z-index: 10000;
            }
            #sidebar.active {
                left: 0 !important; /* Slide in when active */
            }
            #content {
                margin-left: 0 !important;
            }
            .overlay.active {
                display: block;
            }
            .main-content-body {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Responsive Mobile Overlay -->
    <div class="overlay animate__animated animate__fadeIn" id="sidebarOverlay"></div>
    
    <div class="wrapper">