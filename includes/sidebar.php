<nav id="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <img src="../../assets/image/datamexlogo.png" alt="ELMS Logo" class="sidebar-logo">
        <div class="brand-text">
            <h3 class="fw-bold">ELMS</h3>
            <p class="text-uppercase">Datamex College of Saint Adeline</p>
        </div>
    </div>

    <!-- Branch Admin Context -->
    <?php if (($_SESSION['role_id'] ?? null) == ROLE_BRANCH_ADMIN): ?>
        <?php
            $branch_label = 'Unassigned';
            $sidebar_branch_id = get_user_branch_id();
            if (!empty($sidebar_branch_id)) {
                if(isset($conn)){
                    $branch_stmt = $conn->prepare("SELECT name FROM branches WHERE id = ?");
                    $branch_stmt->bind_param("i", $sidebar_branch_id);
                    $branch_stmt->execute();
                    $branch_result = $branch_stmt->get_result();
                    if ($branch_row = $branch_result->fetch_assoc()) {
                        $branch_label = $branch_row['name'] ?? $branch_label;
                    }
                    $branch_stmt->close();
                }
            }
        ?>
        <div class="branch-badge">
            <div class="small text-white-50 text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Current Branch</div>
            <div class="fw-bold text-white mt-1">
                <i class="bi bi-building-fill me-1 text-warning"></i> <?php echo htmlspecialchars($branch_label); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation Links -->
    <ul class="list-unstyled components">
        <?php
        $current_page = basename($_SERVER['PHP_SELF']);
        
        // --- 1. SUPER ADMIN MENU ---
        if ($_SESSION['role_id'] == ROLE_SUPER_ADMIN) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a></li>
            <li><a href="users.php" class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>"><i class="bi bi-people"></i> <span>Users Management</span></a></li>
            <li><a href="security_settings.php" class="<?php echo ($current_page == 'security_settings.php') ? 'active' : ''; ?>"><i class="bi bi-shield-check"></i> <span>Security & Email</span></a></li>
            <li><a href="security.php" class="<?php echo ($current_page == 'security.php') ? 'active' : ''; ?>"><i class="bi bi-activity"></i> <span>Audit Logs</span></a></li>
            <li><a href="maintenance.php" class="<?php echo ($current_page == 'maintenance.php') ? 'active' : ''; ?>"><i class="bi bi-tools"></i> <span>System Maintenance</span></a></li>
            <li><a href="api_management.php" class="<?php echo ($current_page == 'api_management.php') ? 'active' : ''; ?>"><i class="bi bi-hdd-network"></i> <span>API Management</span></a></li>
        <?php }

        // --- 2. SCHOOL ADMIN MENU ---
        if ($_SESSION['role_id'] == ROLE_SCHOOL_ADMIN) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-grid-fill"></i> <span>Dashboard</span></a></li>
            <li><a href="administrative_control.php" class="<?php echo ($current_page == 'administrative_control.php') ? 'active' : ''; ?>"><i class="bi bi-sliders"></i> <span>Admin Control</span></a></li>
            <li><a href="programs.php" class="<?php echo ($current_page == 'programs.php') ? 'active' : ''; ?>"><i class="bi bi-mortarboard"></i> <span>Programs</span></a></li>
            <li><a href="curriculum.php" class="<?php echo (in_array($current_page, ['curriculum.php', 'shs_curriculum.php', 'college_curriculum.php'])) ? 'active' : ''; ?>"><i class="bi bi-journal-richtext"></i> <span>Subject Catalog</span></a></li>
            <li><a href="branches.php" class="<?php echo ($current_page == 'branches.php') ? 'active' : ''; ?>"><i class="bi bi-buildings"></i> <span>Branches</span></a></li>
            <li><a href="announcements.php" class="<?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>"><i class="bi bi-megaphone"></i> <span>Announcements</span></a></li>
        <?php }

        // --- 3. BRANCH ADMIN MENU ---
        if ($_SESSION['role_id'] == ROLE_BRANCH_ADMIN) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-grid-1x2-fill"></i> <span>Dashboard</span></a></li>
            <li><a href="academic_year_management.php" class="<?php echo ($current_page == 'academic_year_management.php') ? 'active' : ''; ?>"><i class="bi bi-calendar-event"></i> <span>Academic Year</span></a></li>
            <li><a href="programs_sections.php" class="<?php echo ($current_page == 'programs_sections.php') ? 'active' : ''; ?>"><i class="bi bi-collection"></i> <span>Programs & Sections</span></a></li>
            <li><a href="teacher_assignment.php" class="<?php echo ($current_page == 'teacher_assignment.php') ? 'active' : ''; ?>"><i class="bi bi-person-fill-gear"></i> <span>Teacher Assignment</span></a></li>
            <li><a href="student_assignment.php" class="<?php echo ($current_page == 'student_assignment.php') ? 'active' : ''; ?>"><i class="bi bi-person-plus-fill"></i> <span>Student Assignment</span></a></li>
            <li><a href="teachers.php" class="<?php echo ($current_page == 'teachers.php') ? 'active' : ''; ?>"><i class="bi bi-person-video3"></i> <span>Teachers</span></a></li>
            <li><a href="registrars.php" class="<?php echo ($current_page == 'registrars.php') ? 'active' : ''; ?>"><i class="bi bi-person-badge"></i> <span>Registrars</span></a></li>
            <li><a href="announcements.php" class="<?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>"><i class="bi bi-broadcast"></i> <span>Announcements</span></a></li>
            <li><a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>"><i class="bi bi-graph-up-arrow"></i> <span>Reports</span></a></li>
        <?php }

        // --- 4. REGISTRAR MENU ---
        if ($_SESSION['role_id'] == ROLE_REGISTRAR) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-speedometer"></i> <span>Dashboard</span></a></li>
            <li><a href="students.php" class="<?php echo ($current_page == 'students.php') ? 'active' : ''; ?>"><i class="bi bi-people-fill"></i> <span>Students</span></a></li>
            <li><a href="program_enrollment.php" class="<?php echo in_array($current_page, ['program_enrollment.php', 'transferee_management.php']) ? 'active' : ''; ?>"><i class="bi bi-mortarboard"></i> <span>Program Enrollment</span></a></li>
            <li><a href="tuition_fees.php" class="<?php echo ($current_page == 'tuition_fees.php') ? 'active' : ''; ?>"><i class="bi bi-cash-coin"></i> <span>Tuition Fees</span></a></li>
            <li><a href="certificates.php" class="<?php echo ($current_page == 'certificates.php') ? 'active' : ''; ?>"><i class="bi bi-patch-check"></i> <span>Certificates</span></a></li>
            <li><a href="payment_history.php" class="<?php echo ($current_page == 'payment_history.php') ? 'active' : ''; ?>"><i class="bi bi-receipt"></i> <span>Payment History</span></a></li>
        <?php }

        // --- 5. TEACHER MENU ---
        if ($_SESSION['role_id'] == ROLE_TEACHER) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-grid"></i> <span>Dashboard</span></a></li>
            <li><a href="subjects.php" class="<?php echo (in_array($current_page, ['subjects.php', 'subject_sections.php', 'section_students.php', 'classroom.php', 'grading.php', 'grading_sections.php', 'gradebook.php'])) ? 'active' : ''; ?>"><i class="bi bi-easel"></i> <span>My Classes</span></a></li>
            <li><a href="assessments.php" class="<?php echo ($current_page == 'assessments.php') ? 'active' : ''; ?>"><i class="bi bi-check2-square"></i> <span>Assessments</span></a></li>
            <li><a href="materials.php" class="<?php echo (in_array($current_page, ['materials.php','materials_sections.php', 'materials_list.php'])) ? 'active' : ''; ?>"><i class="bi bi-folder2-open"></i> <span>Materials</span></a></li>
            <li><a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>"><i class="bi bi-file-earmark-text"></i> <span>Class Reports</span></a></li>
        <?php }

        // --- 6. STUDENT MENU ---
        if ($_SESSION['role_id'] == ROLE_STUDENT) { ?>
            <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="bi bi-house-door"></i> <span>Dashboard</span></a></li>
            <li><a href="my_classes.php" class="<?php echo (in_array($current_page, ['my_classes.php', 'subject_view.php'])) ? 'active' : ''; ?>"><i class="bi bi-book"></i> <span>My Classes</span></a></li>
            <li><a href="schedule.php" class="<?php echo ($current_page == 'schedule.php') ? 'active' : ''; ?>"><i class="bi bi-calendar3"></i> <span>Class Schedule</span></a></li>
            <li><a href="grades.php" class="<?php echo ($current_page == 'grades.php') ? 'active' : ''; ?>"><i class="bi bi-award"></i> <span>My Grades</span></a></li>
            <li><a href="assessments.php" class="<?php echo ($current_page == 'assessments.php') ? 'active' : ''; ?>"><i class="bi bi-pencil-square"></i> <span>Assessments</span></a></li>
            <li><a href="materials.php" class="<?php echo ($current_page == 'materials.php') ? 'active' : ''; ?>"><i class="bi bi-cloud-download"></i> <span>Learning Materials</span></a></li>
            <li><a href="payments.php" class="<?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>"><i class="bi bi-wallet2"></i> <span>Payments</span></a></li>
            <li><a href="announcements.php" class="<?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>"><i class="bi bi-megaphone-fill"></i> <span>Announcements</span></a></li>
            <li><a href="enrollment.php" class="<?php echo ($current_page == 'enrollment.php') ? 'active' : ''; ?>"><i class="bi bi-ui-checks"></i> <span>Enrollment</span></a></li>
            <li><a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>"><i class="bi bi-person-circle"></i> <span>My Profile</span></a></li>
        <?php } ?>
    </ul>
</nav>
