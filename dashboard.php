<?php
require_once 'config/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
    header('Location: index.php');
    exit();
}

// Route user to appropriate dashboard based on role
switch ($_SESSION['role_id']) {
    case ROLE_SUPER_ADMIN:
        header('Location: modules/super_admin/dashboard.php');
        break;
    
    case ROLE_SCHOOL_ADMIN:
        header('Location: modules/school_admin/dashboard.php');
        break;
    
    case ROLE_BRANCH_ADMIN:
        header('Location: modules/branch_admin/dashboard.php');
        break;
    
    case ROLE_REGISTRAR:
        header('Location: modules/registrar/dashboard.php');
        break;
    
    case ROLE_TEACHER:
        header('Location: modules/teacher/dashboard.php');
        break;
    
    case ROLE_STUDENT:
        header('Location: modules/student/dashboard.php');
        break;
    
    default:
        // Invalid role - logout
        session_destroy();
        header('Location: index.php');
        break;
}
exit();
?>