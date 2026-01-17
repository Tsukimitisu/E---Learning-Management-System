<nav id="sidebar">
    <div class="sidebar-header">
        <h3><?php echo SITE_NAME; ?></h3>
        <small><?php echo htmlspecialchars($_SESSION['role']); ?></small>
    </div>

    <ul class="list-unstyled components">
        <?php
        $current_page = basename($_SERVER['PHP_SELF']);
        
        // Super Admin Menu
       // Super Admin Menu
if ($_SESSION['role_id'] == ROLE_SUPER_ADMIN) {
?>
    <li>
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li>
        <a href="users.php" class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
            <i class="bi bi-people"></i> User Management
        </a>
    </li>
    <li>
        <a href="system_settings.php" class="<?php echo ($current_page == 'system_settings.php') ? 'active' : ''; ?>">
            <i class="bi bi-gear"></i> System Settings
        </a>
    </li>
    <li>
        <a href="security.php" class="<?php echo ($current_page == 'security.php') ? 'active' : ''; ?>">
            <i class="bi bi-shield-check"></i> Security & Audit
        </a>
    </li>
    <li>
        <a href="maintenance.php" class="<?php echo ($current_page == 'maintenance.php') ? 'active' : ''; ?>">
            <i class="bi bi-tools"></i> Maintenance
        </a>
    </li>
    <li>
        <a href="api_management.php" class="<?php echo ($current_page == 'api_management.php') ? 'active' : ''; ?>">
            <i class="bi bi-plugin"></i> API Management
        </a>
    </li>
    <li>
        <a href="branches.php" class="<?php echo ($current_page == 'branches.php') ? 'active' : ''; ?>">
            <i class="bi bi-building"></i> School & Branches
        </a>
    </li>
 
<?php
}
        
        // School Admin Menu
       if ($_SESSION['role_id'] == ROLE_SCHOOL_ADMIN) {
?>
    <li>
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li>
        <a href="programs.php" class="<?php echo ($current_page == 'programs.php') ? 'active' : ''; ?>">
            <i class="bi bi-mortarboard"></i> Programs
        </a>
    </li>
    <li>
        <a href="curriculum.php" class="<?php echo ($current_page == 'curriculum.php') ? 'active' : ''; ?>">
            <i class="bi bi-book"></i> Subject Catalog
        </a>
    </li>
    <li>
        <a href="announcements.php" class="<?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>">
            <i class="bi bi-megaphone"></i> Announcements
        </a>
    </li>
<?php
}
        
        // Branch Admin Menu
        // Branch Admin Menu
 if ($_SESSION['role_id'] == ROLE_BRANCH_ADMIN) {
?>
     <li>
         <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
             <i class="bi bi-speedometer2"></i> Dashboard
         </a>
     </li>
     <li>
         <a href="scheduling.php" class="<?php echo ($current_page == 'scheduling.php') ? 'active' : ''; ?>">
             <i class="bi bi-calendar-plus"></i> Class Scheduling
         </a>
     </li>
     <li>
         <a href="sectioning.php" class="<?php echo ($current_page == 'sectioning.php') ? 'active' : ''; ?>">
             <i class="bi bi-diagram-3"></i> Section Management
         </a>
     </li>
     <li>
         <a href="teachers.php" class="<?php echo ($current_page == 'teachers.php') ? 'active' : ''; ?>">
             <i class="bi bi-person-badge"></i> Teacher Management
         </a>
     </li>
     <li>
         <a href="students.php" class="<?php echo ($current_page == 'students.php') ? 'active' : ''; ?>">
             <i class="bi bi-people"></i> Student Management
         </a>
     </li>
     <li>
         <a href="announcements.php" class="<?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>">
             <i class="bi bi-megaphone"></i> Announcements
         </a>
     </li>
     <li>
         <a href="monitoring.php" class="<?php echo ($current_page == 'monitoring.php') ? 'active' : ''; ?>">
             <i class="bi bi-eye"></i> Monitoring & Compliance
         </a>
     </li>
     <li>
         <a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
             <i class="bi bi-file-earmark-text"></i> Reports
         </a>
     </li>
<?php
 }
        
        // Registrar Menu
              if ($_SESSION['role_id'] == ROLE_REGISTRAR) {
?>
    <li>
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li>
        <a href="enroll.php" class="<?php echo ($current_page == 'enroll.php') ? 'active' : ''; ?>">
            <i class="bi bi-pencil-square"></i> Enroll Student
        </a>
    </li>
    <li>
        <a href="classes.php" class="<?php echo ($current_page == 'classes.php') ? 'active' : ''; ?>">
            <i class="bi bi-door-open"></i> Manage Classes
        </a>
    </li>
    <li>
        <a href="students.php" class="<?php echo ($current_page == 'students.php') ? 'active' : ''; ?>">
            <i class="bi bi-person-badge"></i> Students
        </a>
    </li>
<?php
}
        
        // Teacher Menu
  // Teacher Menu
if ($_SESSION['role_id'] == ROLE_TEACHER) {
?>
    <li>
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li>
        <a href="my_classes.php" class="<?php echo ($current_page == 'my_classes.php') ? 'active' : ''; ?>">
            <i class="bi bi-door-open"></i> My Classes
        </a>
    </li>
    <li>
        <a href="grading.php" class="<?php echo ($current_page == 'grading.php') ? 'active' : ''; ?>">
            <i class="bi bi-calculator"></i> Grade Management
        </a>
    </li>
    <li>
        <a href="attendance.php" class="<?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
            <i class="bi bi-calendar-check"></i> Attendance
        </a>
    </li>
    <li>
        <a href="assessments.php" class="<?php echo ($current_page == 'assessments.php') ? 'active' : ''; ?>">
            <i class="bi bi-clipboard-check"></i> Assessments
        </a>
    </li>
    <li>
        <a href="materials.php" class="<?php echo ($current_page == 'materials.php') ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-pdf"></i> Materials
        </a>
    </li>
    <li>
        <a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-text"></i> Reports
        </a>
    </li>
<?php
}
        
        // Student Menu
      if ($_SESSION['role_id'] == ROLE_STUDENT) {
?>
    <li>
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li>
        <a href="my_classes.php" class="<?php echo ($current_page == 'my_classes.php') ? 'active' : ''; ?>">
            <i class="bi bi-book"></i> My Classes
        </a>
    </li>
    <li>
        <a href="grades.php" class="<?php echo ($current_page == 'grades.php') ? 'active' : ''; ?>">
            <i class="bi bi-bar-chart"></i> My Grades
        </a>
    </li>
<?php
}
        ?>
    </ul>

    <ul class="list-unstyled" style="position: absolute; bottom: 20px; width: 100%;">
        <li>
            <a href="../../logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </li>
    </ul>
</nav>