<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Branch Admin Dashboard";
$admin_id = $_SESSION['user_id'];

// Get branch admin's branch (assume stored in user profile or separate table)
// For now, we'll use branch_id = 1 as default
$branch_id = 1; // In production, fetch from user's assigned branch

// Fetch Statistics
$stats = [
    'total_students' => 0,
    'total_classes' => 0,
    'active_teachers' => 0,
    'today_attendance' => 0
];

// Total Students in this branch
$result = $conn->query("
    SELECT COUNT(DISTINCT s.user_id) as count 
    FROM students s
    INNER JOIN courses c ON s.course_id = c.id
    WHERE c.branch_id = $branch_id
");
if ($row = $result->fetch_assoc()) {
    $stats['total_students'] = $row['count'];
}

// Total Classes in this branch
$result = $conn->query("SELECT COUNT(*) as count FROM classes WHERE branch_id = $branch_id");
if ($row = $result->fetch_assoc()) {
    $stats['total_classes'] = $row['count'];
}

// Active Teachers assigned to classes in this branch
$result = $conn->query("
    SELECT COUNT(DISTINCT teacher_id) as count 
    FROM classes 
    WHERE branch_id = $branch_id AND teacher_id IS NOT NULL
");
if ($row = $result->fetch_assoc()) {
    $stats['active_teachers'] = $row['count'];
}

// Today's Attendance (students who attended today)
$today = date('Y-m-d');
$result = $conn->query("
    SELECT COUNT(DISTINCT student_id) as count 
    FROM attendance a
    INNER JOIN classes cl ON a.class_id = cl.id
    WHERE cl.branch_id = $branch_id 
    AND a.attendance_date = '$today' 
    AND a.status = 'present'
");
if ($row = $result->fetch_assoc()) {
    $stats['today_attendance'] = $row['count'];
}

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-speedometer2"></i> Branch Administrator Dashboard
            </h4>
            <div>
                <span class="badge bg-success me-2">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
                </span>
                <span class="text-muted"><?php echo date('F d, Y'); ?></span>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <p><i class="bi bi-people"></i> Total Students</p>
                    <h3><?php echo number_format($stats['total_students']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <p><i class="bi bi-door-open"></i> Total Classes</p>
                    <h3><?php echo number_format($stats['total_classes']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <p><i class="bi bi-person-badge"></i> Active Teachers</p>
                    <h3><?php echo number_format($stats['active_teachers']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <p><i class="bi bi-calendar-check"></i> Today's Attendance</p>
                    <h3><?php echo number_format($stats['today_attendance']); ?></h3>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #800000; color: white;">
                        <i class="bi bi-list-check"></i> Recent Classes
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Section</th>
                                    <th>Teacher</th>
                                    <th>Schedule</th>
                                    <th>Room</th>
                                    <th>Enrolled</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_classes = $conn->query("
                                    SELECT 
                                        cl.id,
                                        cl.section_name,
                                        cl.schedule,
                                        cl.room,
                                        cl.current_enrolled,
                                        cl.max_capacity,
                                        s.subject_code,
                                        s.subject_title,
                                        CONCAT(up.first_name, ' ', up.last_name) as teacher_name
                                    FROM classes cl
                                    LEFT JOIN subjects s ON cl.subject_id = s.id
                                    LEFT JOIN users u ON cl.teacher_id = u.id
                                    LEFT JOIN user_profiles up ON u.id = up.user_id
                                    WHERE cl.branch_id = $branch_id
                                    ORDER BY cl.id DESC
                                    LIMIT 10
                                ");
                                
                                while ($class = $recent_classes->fetch_assoc()):
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($class['subject_code'] ?? 'N/A'); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($class['subject_title'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($class['section_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Not Assigned'); ?></td>
                                    <td><small><?php echo htmlspecialchars($class['schedule'] ?? '-'); ?></small></td>
                                    <td><?php echo htmlspecialchars($class['room'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $class['current_enrolled']; ?> / <?php echo $class['max_capacity']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <i class="bi bi-list-check"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="scheduling.php" class="btn btn-outline-primary">
                                <i class="bi bi-calendar-plus"></i> Schedule Classes
                            </a>
                            <a href="sectioning.php" class="btn btn-outline-dark">
                                <i class="bi bi-diagram-3"></i> Manage Sections
                            </a>
                            <a href="teachers.php" class="btn btn-outline-info">
                                <i class="bi bi-person-badge"></i> Manage Teachers
                            </a>
                            <a href="students.php" class="btn btn-outline-secondary">
                                <i class="bi bi-people"></i> Manage Students
                            </a>
                            <a href="announcements.php" class="btn btn-outline-success">
                                <i class="bi bi-megaphone"></i> Branch Announcements
                            </a>
                            <a href="monitoring.php" class="btn btn-outline-danger">
                                <i class="bi bi-eye"></i> Monitor & Comply
                            </a>
                            <a href="reports.php" class="btn btn-outline-warning">
                                <i class="bi bi-file-earmark-text"></i> Generate Reports
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mt-3">
                    <div class="card-header" style="background-color: #800000; color: white;">
                        <i class="bi bi-shield-check"></i> Branch Administrator Scope
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Scope:</strong> Single branch / campus</p>

                        <h6 class="mt-3 mb-2">Academic Implementation</h6>
                        <ul class="mb-3">
                            <li>Create and manage classes, sections, and class schedules</li>
                            <li>Assign subjects to teachers</li>
                            <li>Assign students to sections</li>
                            <li>Create and manage teacher accounts</li>
                        </ul>

                        <h6 class="mt-3 mb-2">Academic Monitoring</h6>
                        <ul class="mb-3">
                            <li>Monitor student attendance and academic standing</li>
                            <li>Track teacher compliance</li>
                            <li>Lock/unlock class records (as permitted)</li>
                        </ul>

                        <h6 class="mt-3 mb-2">Communication & Reports</h6>
                        <ul class="mb-3">
                            <li>Publish branch-level announcements</li>
                            <li>Generate attendance reports</li>
                            <li>Generate academic performance summaries</li>
                            <li>Generate enrollment statistics</li>
                            <li>Ensure compliance with school policies</li>
                        </ul>

                        <div class="alert alert-info py-2 mb-0">
                            <i class="bi bi-info-circle"></i>
                            Implements approved curriculum at branch level.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>