<!-- Sidebar Overlay for Mobile -->
<div class="overlay" id="sidebarOverlay"></div>

<nav id="sidebar">
    <!-- Top Branding Section (Maroon) -->
    <div class="sidebar-header shadow-sm">
        <img src="../../assets/image/datamexlogo.png" alt="ELMS Logo" class="sidebar-logo">
        <h3 class="mb-0 fw-bold" style="font-size: 1.1rem; letter-spacing: 1px;">ELMS</h3>
        <p class="mb-0 small opacity-75" style="font-size: 0.65rem; letter-spacing: 2px;">DATAMEX COLLEGE OF SAINT ADELINE</p>
    </div>

    <?php if (($_SESSION['role_id'] ?? null) == ROLE_BRANCH_ADMIN): ?>
        <?php
            $branch_label = 'Unassigned';
            $sidebar_branch_id = get_user_branch_id();
            if (!empty($sidebar_branch_id)) {
                $branch_stmt = $conn->prepare("SELECT name FROM branches WHERE id = ?");
                $branch_stmt->bind_param("i", $sidebar_branch_id);
                $branch_stmt->execute();
                $branch_result = $branch_stmt->get_result();
                if ($branch_row = $branch_result->fetch_assoc()) {
                    $branch_label = $branch_row['name'] ?? $branch_label;
                }
                $branch_stmt->close();
            }
        ?>
        <div class="px-3 py-2" style="background-color: #e7f3ff; border-bottom: 1px solid #d0e4ff;">
            <span class="badge bg-info text-dark">
                <i class="bi bi-building"></i> <?php echo htmlspecialchars($branch_label); ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- Scrollable Navigation Menu (Blue) -->
    <ul class="list-unstyled components">
        <?php
        $current_page = basename($_SERVER['PHP_SELF']);
        
        // --- 1. SUPER ADMIN MENU ---
        if ($_SESSION['role_id'] == ROLE_SUPER_ADMIN) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-grid-fill"></i> <span>Dashboard</span></a></li>
            <li><a href="users.php" class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>"><i class="bi bi-people-fill"></i> <span>Users</span></a></li>
            <li><a href="system_settings.php" class="<?php echo ($current_page == 'system_settings.php') ? 'active' : ''; ?>"><i class="bi bi-gear-wide-connected"></i> <span>Settings</span></a></li>
            <li><a href="security.php" class="<?php echo ($current_page == 'security.php') ? 'active' : ''; ?>"><i class="bi bi-shield-lock-fill"></i> <span>Security & Audit</span></a></li>
            <li><a href="maintenance.php" class="<?php echo ($current_page == 'maintenance.php') ? 'active' : ''; ?>"><i class="bi bi-tools"></i> <span>Maintenance</span></a></li>
            <li><a href="api_management.php" class="<?php echo ($current_page == 'api_management.php') ? 'active' : ''; ?>"><i class="bi bi-cpu-fill"></i> <span>API Management</span></a></li>
            <li><a href="branches.php" class="<?php echo ($current_page == 'branches.php') ? 'active' : ''; ?>"><i class="bi bi-building-fill"></i> <span>Branches</span></a></li>
        <?php }

        // --- 2. SCHOOL ADMIN MENU ---
        if ($_SESSION['role_id'] == ROLE_SCHOOL_ADMIN) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-grid-fill"></i> <span>Dashboard</span></a></li>
            <li><a href="administrative_control.php" class="<?php echo ($current_page == 'administrative_control.php') ? 'active' : ''; ?>"><i class="bi bi-shield-check"></i> <span>Admin Control</span></a></li>
            <li><a href="programs.php" class="<?php echo ($current_page == 'programs.php') ? 'active' : ''; ?>"><i class="bi bi-mortarboard-fill"></i> <span>Programs</span></a></li>
            <li><a href="curriculum.php" class="<?php echo ($current_page == 'curriculum.php') ? 'active' : ''; ?>"><i class="bi bi-book-half"></i> <span>Subject Catalog</span></a></li>
            <li><a href="announcements.php" class="<?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>"><i class="bi bi-megaphone-fill"></i> <span>Announcements</span></a></li>
        <?php }

        // --- 3. BRANCH ADMIN MENU ---
        if ($_SESSION['role_id'] == ROLE_BRANCH_ADMIN) { ?>
             <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-grid-fill"></i> <span>Dashboard</span></a></li>
             <li><a href="programs_sections.php" class="<?php echo ($current_page == 'programs_sections.php') ? 'active' : ''; ?>"><i class="bi bi-grid-3x3-gap"></i> <span>Programs & Sections</span></a></li>
             <li><a href="teacher_assignment.php" class="<?php echo ($current_page == 'teacher_assignment.php') ? 'active' : ''; ?>"><i class="bi bi-person-badge"></i> <span>Teacher Assignment</span></a></li>
             <li><a href="student_assignment.php" class="<?php echo ($current_page == 'student_assignment.php') ? 'active' : ''; ?>"><i class="bi bi-person-plus"></i> <span>Student Assignment</span></a></li>
             <li><a href="teachers.php" class="<?php echo ($current_page == 'teachers.php') ? 'active' : ''; ?>"><i class="bi bi-person-workspace"></i> <span>Teachers</span></a></li>
             <li><a href="students.php" class="<?php echo ($current_page == 'students.php') ? 'active' : ''; ?>"><i class="bi bi-people-fill"></i> <span>Students</span></a></li>
             <li><a href="registrars.php" class="<?php echo ($current_page == 'registrars.php') ? 'active' : ''; ?>"><i class="bi bi-person-badge-fill"></i> <span>Registrars</span></a></li>
             <li><a href="announcements.php" class="<?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>"><i class="bi bi-megaphone-fill"></i> <span>Announcements</span></a></li>
             <li><a href="monitoring.php" class="<?php echo ($current_page == 'monitoring.php') ? 'active' : ''; ?>"><i class="bi bi-eye-fill"></i> <span>Monitoring</span></a></li>
             <li><a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>"><i class="bi bi-file-earmark-bar-graph"></i> <span>Reports</span></a></li>
        <?php }

        // --- 4. REGISTRAR MENU ---
        if ($_SESSION['role_id'] == ROLE_REGISTRAR) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-grid-fill"></i> <span>Dashboard</span></a></li>
            <li><a href="enroll.php" class="<?php echo ($current_page == 'enroll.php') ? 'active' : ''; ?>"><i class="bi bi-pencil-square"></i> <span>Enrollment</span></a></li>
            <li><a href="classes.php" class="<?php echo ($current_page == 'classes.php') ? 'active' : ''; ?>"><i class="bi bi-door-open-fill"></i> <span>Classes</span></a></li>
            <li><a href="students.php" class="<?php echo ($current_page == 'students.php') ? 'active' : ''; ?>"><i class="bi bi-person-badge-fill"></i> <span>Students</span></a></li>
            <li><a href="records.php" class="<?php echo ($current_page == 'records.php') ? 'active' : ''; ?>"><i class="bi bi-file-earmark-text"></i> <span>Academic Records</span></a></li>
            <li><a href="certificates.php" class="<?php echo ($current_page == 'certificates.php') ? 'active' : ''; ?>"><i class="bi bi-award"></i> <span>Certificates</span></a></li>
            <li><a href="record_payment.php" class="<?php echo ($current_page == 'record_payment.php') ? 'active' : ''; ?>"><i class="bi bi-receipt"></i> <span>Record Payment</span></a></li>
            <li><a href="payment_history.php" class="<?php echo ($current_page == 'payment_history.php') ? 'active' : ''; ?>"><i class="bi bi-clock-history"></i> <span>Payment History</span></a></li>
            <li><a href="payments.php" class="<?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>"><i class="bi bi-cash-coin"></i> <span>Verify Payments</span></a></li>
            <li><a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>"><i class="bi bi-file-earmark-bar-graph"></i> <span>Reports</span></a></li>
        <?php }

        // --- 5. TEACHER MENU ---
        if ($_SESSION['role_id'] == ROLE_TEACHER) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-grid-fill"></i> <span>Dashboard</span></a></li>
            <li><a href="subjects.php" class="<?php echo (in_array($current_page, ['subjects.php', 'subject_sections.php', 'section_students.php'])) ? 'active' : ''; ?>"><i class="bi bi-journal-bookmark"></i> <span>My Subjects</span></a></li>
            <li><a href="grading.php" class="<?php echo (in_array($current_page, ['grading.php', 'gradebook.php'])) ? 'active' : ''; ?>"><i class="bi bi-calculator-fill"></i> <span>Grades</span></a></li>
            <li><a href="attendance.php" class="<?php echo (in_array($current_page, ['attendance.php', 'attendance_sheet.php'])) ? 'active' : ''; ?>"><i class="bi bi-calendar-check-fill"></i> <span>Attendance</span></a></li>
            <li><a href="assessments.php" class="<?php echo ($current_page == 'assessments.php') ? 'active' : ''; ?>"><i class="bi bi-clipboard-check-fill"></i> <span>Assessments</span></a></li>
            <li><a href="materials.php" class="<?php echo (in_array($current_page, ['materials.php', 'materials_list.php'])) ? 'active' : ''; ?>"><i class="bi bi-file-earmark-pdf-fill"></i> <span>Materials</span></a></li>
            <li><a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>"><i class="bi bi-file-earmark-text"></i> <span>Reports</span></a></li>
        <?php }

        // --- 6. STUDENT MENU ---
        if ($_SESSION['role_id'] == ROLE_STUDENT) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-grid-fill"></i> <span>Dashboard</span></a></li>
            <li><a href="my_classes.php" class="<?php echo (in_array($current_page, ['my_classes.php', 'subject_view.php'])) ? 'active' : ''; ?>"><i class="bi bi-book-fill"></i> <span>My Classes</span></a></li>
            <li><a href="schedule.php" class="<?php echo ($current_page == 'schedule.php') ? 'active' : ''; ?>"><i class="bi bi-calendar-week-fill"></i> <span>Schedule</span></a></li>
            <li><a href="grades.php" class="<?php echo ($current_page == 'grades.php') ? 'active' : ''; ?>"><i class="bi bi-bar-chart-fill"></i> <span>My Grades</span></a></li>
            <li><a href="attendance.php" class="<?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>"><i class="bi bi-calendar-check-fill"></i> <span>Attendance</span></a></li>
            <li><a href="assessments.php" class="<?php echo ($current_page == 'assessments.php') ? 'active' : ''; ?>"><i class="bi bi-clipboard-check-fill"></i> <span>Assessments</span></a></li>
            <li><a href="materials.php" class="<?php echo ($current_page == 'materials.php') ? 'active' : ''; ?>"><i class="bi bi-file-earmark-pdf-fill"></i> <span>Materials</span></a></li>
            <li><a href="payments.php" class="<?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>"><i class="bi bi-credit-card-fill"></i> <span>Payments</span></a></li>
            <li><a href="announcements.php" class="<?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>"><i class="bi bi-megaphone-fill"></i> <span>Announcements</span></a></li>
            <li><a href="enrollment.php" class="<?php echo ($current_page == 'enrollment.php') ? 'active' : ''; ?>"><i class="bi bi-clipboard-data-fill"></i> <span>Enrollment</span></a></li>
            <li><a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>"><i class="bi bi-person-fill"></i> <span>My Profile</span></a></li>
        <?php } ?>
    </ul>

    <!-- Logout Section: Pinned to bottom of Blue area -->
    <div class="sidebar-footer">
        <a href="../../logout.php" class="logout-link">
            <i class="bi bi-box-arrow-right me-3"></i> Logout Account
        </a>
    </div>
</nav>

<!-- Page Content Area Starts -->
<div id="content">
    <!-- Navbar with Profile Alignment & Notification -->
    <nav class="navbar-custom animate__animated animate__fadeInDown">
        <div class="d-flex align-items-center">
            <button type="button" id="sidebarCollapse" class="burger-btn me-3 position-relative">
                <i class="bi bi-list"></i>
                <!-- Mobile Notification Badge on Burger -->
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-lg-none" id="notificationBadge" style="display:none; font-size: 0.5rem;">
                    <span id="notificationCount">0</span>
                </span>
            </button>
            
            <!-- Desktop Notification Bell -->
            <div class="position-relative d-none d-lg-block me-3" style="cursor: pointer;">
                <i class="bi bi-bell text-muted fs-5"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadgeDesktop" style="display:none; font-size: 0.6rem;">
                    <span id="notificationCountDesktop">0</span>
                </span>
            </div>
        </div>
        
        <div class="user-profile">
            <div class="user-info-text d-none d-sm-block">
                <!-- Role on top (Blue bold), Name below (Muted) -->
                <span class="role" style="color: var(--blue); font-weight: 800; font-size: 0.8rem;">USER <?php echo strtoupper(htmlspecialchars($_SESSION['role'])); ?></span>
                <span class="name" style="color: #666; font-size: 0.75rem; display: block;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User Account'); ?></span>
            </div>
            <div class="avatar-circle shadow-sm">
                <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
            </div>
        </div>
    </nav>
    
    <!-- Main Scrollable Container -->
    <div class="main-content-body">