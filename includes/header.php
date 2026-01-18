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

        /* ===== MINIMALIST DESIGN - MAROON, NAVY BLUE & WHITE ===== */
        
        body { 
            background-color: #f5f5f5; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1a1a1a;
        }
        
        /* Cards - Clean & Minimal */
        .card { 
            border: none; 
            border-radius: 4px; 
            background: #fff; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        .card-header { 
            border: none !important; 
            background-color: #fff !important; 
            padding: 20px 25px !important; 
            font-weight: 600 !important;
            border-bottom: 1px solid #e8e8e8 !important;
            color: var(--blue);
        }
        .card-body { padding: 25px !important; }
        
        /* Tables - Minimalist */
        .table { 
            margin-bottom: 0; 
            border-collapse: separate;
            border-spacing: 0;
        }
        .table thead th {
            background-color: var(--blue);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 1px;
            padding: 12px 15px;
            vertical-align: middle;
        }
        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
            color: #1a1a1a;
            font-size: 0.9rem;
        }
        .table tbody tr { 
            transition: background-color 0.15s ease;
        }
        .table tbody tr:hover { 
            background-color: #f8f9fb; 
        }
        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Buttons - Minimalist with Maroon & Navy */
        .btn { 
            font-weight: 500; 
            border-radius: 4px;
            padding: 0.5rem 1.25rem;
            border: none;
            transition: all 0.2s ease;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        .btn-sm { 
            padding: 0.35rem 0.9rem; 
            font-size: 0.75rem;
        }
        
        /* Maroon Primary Buttons */
        .btn-primary,
        .btn[style*="background-color: #800000"],
        .btn[style*="background-color:#800000"] { 
            background-color: var(--maroon) !important; 
            border-color: var(--maroon) !important;
            color: white !important;
        }
        .btn-primary:hover { 
            background-color: #660000 !important; 
            border-color: #660000 !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(128,0,0,0.3);
        }
        
        /* Navy Blue Secondary */
        .btn-secondary,
        .btn-info { 
            background-color: var(--blue); 
            border-color: var(--blue);
            color: white;
        }
        .btn-secondary:hover,
        .btn-info:hover { 
            background-color: #002145; 
            border-color: #002145;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,51,102,0.3);
        }
        
        /* Action Buttons */
        .btn-success { 
            background-color: #2ecc71; 
            border-color: #2ecc71;
        }
        .btn-success:hover { 
            background-color: #27ae60; 
            transform: translateY(-1px);
        }
        .btn-warning { 
            background-color: #f39c12; 
            border-color: #f39c12; 
            color: white;
        }
        .btn-warning:hover { 
            background-color: #e67e22; 
            transform: translateY(-1px);
        }
        .btn-danger { 
            background-color: #e74c3c; 
            border-color: #e74c3c;
        }
        .btn-danger:hover { 
            background-color: #c0392b;
            transform: translateY(-1px);
        }

        .curriculum-management-tracks {
            display: none !important;
        }

        /* Outline Buttons */
        .btn-outline-secondary { 
            border: 1px solid #ddd;
            color: #666; 
            background: white;
            font-weight: 500;
        }
        .btn-outline-secondary:hover { 
            background-color: var(--blue);
            border-color: var(--blue);
            color: white;
        }

        /* Badges - Clean & Minimal */
        .badge { 
            padding: 0.4rem 0.8rem; 
            font-weight: 500; 
            border-radius: 3px; 
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge.bg-success { background-color: #2ecc71 !important; }
        .badge.bg-danger { background-color: #e74c3c !important; }
        .badge.bg-warning { background-color: #f39c12 !important; color: white !important; }
        .badge.bg-info { background-color: var(--blue) !important; }
        .badge.bg-secondary { background-color: #95a5a6 !important; }

        /* Alerts - Minimal */
        #alertContainer { 
            position: relative; 
            margin: 20px 0; 
            z-index: 100; 
        }
        .alert {
            border-radius: 4px;
            border-left: 4px solid;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 15px 20px;
        }
        .alert-info {
            border-left-color: var(--blue);
            color: var(--blue);
        }
        .alert-success {
            border-left-color: #2ecc71;
            color: #27ae60;
        }
        .alert-warning {
            border-left-color: #f39c12;
            color: #e67e22;
        }
        .alert-danger {
            border-left-color: #e74c3c;
            color: #c0392b;
        }

        /* Modals - Minimal with Navy Header */
        .modal-content {
            border: none;
            border-radius: 4px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .modal-header { 
            border-bottom: 1px solid #e8e8e8; 
            background-color: var(--blue);
            color: white;
            padding: 20px 25px;
        }
        .modal-header.bg-primary { background-color: var(--maroon) !important; }
        .modal-header.bg-success { background-color: #2ecc71 !important; }
        .modal-header.bg-warning { background-color: #f39c12 !important; }
        .modal-header.bg-info { background-color: var(--blue) !important; }
        .modal-header.bg-secondary { background-color: #7f8c8d !important; }
        .modal-header.bg-dark { background-color: #2c3e50 !important; }
        .modal-title { font-weight: 600; font-size: 1.1rem; }
        .modal-body { padding: 25px; }
        .modal-footer { 
            border-top: 1px solid #e8e8e8; 
            padding: 15px 25px;
            background-color: #fafafa;
        }

        /* Form Controls - Clean & Minimal */
        .form-control, .form-select {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 0.6rem 1rem;
            transition: all 0.2s ease;
            background-color: white;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.08);
            outline: none;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        /* Navbar Custom - Minimal */
        .navbar-custom { 
            background: white;
            padding: 20px 25px;
            border-radius: 4px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .navbar-custom h4 {
            margin-bottom: 5px;
            color: var(--blue);
            font-weight: 600;
            font-size: 1.5rem;
        }
        .navbar-custom small {
            color: #666;
            font-size: 0.9rem;
        }

        /* Stats Cards - Minimal with Accent Colors */
        .stat-card {
            background: white;
            border-radius: 4px;
            padding: 25px;
            border-left: 4px solid var(--maroon);
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            transition: all 0.2s ease;
        }
        .stat-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-card h3 { 
            font-weight: 600; 
            color: var(--blue); 
            margin: 0; 
            font-size: 2.2rem; 
        }
        .stat-card p { 
            color: #555; 
            font-size: 0.9rem; 
            margin-top: 8px; 
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        /* Tabs - Minimal Navy Style */
        .nav-tabs {
            border-bottom: 1px solid #e8e8e8;
            background: white;
        }
        .nav-tabs .nav-link {
            color: #555;
            font-weight: 500;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 20px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .nav-tabs .nav-link:hover {
            border-bottom-color: #ddd;
            color: var(--blue);
            background: transparent;
        }
        .nav-tabs .nav-link.active {
            color: var(--blue);
            background-color: transparent;
            border-bottom-color: var(--maroon);
            font-weight: 600;
        }

        /* Progress Bar */
        .progress {
            height: 8px;
            border-radius: 10px;
            background-color: #e8e8e8;
        }
        .progress-bar {
            background-color: var(--maroon);
            border-radius: 10px;
        }

        /* Content Wrapper */
        #content {
            padding: 25px;
        }
        
        /* Remove excessive shadows and borders */
        .shadow-sm {
            box-shadow: 0 1px 3px rgba(0,0,0,0.06) !important;
        }
        
        /* Text Colors */
        .text-primary { color: var(--blue) !important; }
        .text-secondary { color: #7f8c8d !important; }
        .text-muted { color: #95a5a6 !important; }
        
        /* Icon Styling */
        i.bi {
            margin-right: 5px;
        }
        
        /* Border Colors */
        .border-primary { border-color: var(--blue) !important; }
        .border-secondary { border-color: #e8e8e8 !important; }

        /* Responsive Improvements */
        @media (max-width: 768px) {
            .navbar-custom {
                flex-direction: column;
                gap: 10px;
            }
            .stat-card {
                padding: 15px;
            }
            .table {
                font-size: 0.85rem;
            }
            .table thead th,
            .table tbody td {
                padding: 10px;
            }
        }
        
    </style>
</head>
<body>
    <div class="overlay" id="sidebarOverlay"></div>
    <div class="wrapper">