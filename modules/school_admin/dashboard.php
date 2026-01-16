<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "School Admin Dashboard";

// Fetch Statistics
$stats = [
    'total_programs' => 0,
    'total_subjects' => 0,
    'active_courses' => 0,
    'total_announcements' => 0
];

// Total Programs
$result = $conn->query("SELECT COUNT(*) as count FROM programs WHERE is_active = 1");
if ($row = $result->fetch_assoc()) {
    $stats['total_programs'] = $row['count'];
}

// Total Subjects
$result = $conn->query("SELECT COUNT(*) as count FROM subjects WHERE is_active = 1");
if ($row = $result->fetch_assoc()) {
    $stats['total_subjects'] = $row['count'];
}

// Active Courses (Classes)
$result = $conn->query("SELECT COUNT(*) as count FROM courses");
if ($row = $result->fetch_assoc()) {
    $stats['active_courses'] = $row['count'];
}

// Total Announcements
$result = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
if ($row = $result->fetch_assoc()) {
    $stats['total_announcements'] = $row['count'];
}

// Recent activity
$recent_activity = $conn->query("
    SELECT al.action, al.timestamp, 
           CONCAT(up.first_name, ' ', up.last_name) as user_name
    FROM audit_logs al
    LEFT JOIN user_profiles up ON al.user_id = up.user_id
    ORDER BY al.timestamp DESC
    LIMIT 10
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-speedometer2"></i> School Administrator Dashboard
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
                    <p><i class="bi bi-mortarboard"></i> Total Programs</p>
                    <h3><?php echo number_format($stats['total_programs']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <p><i class="bi bi-book"></i> Total Subjects</p>
                    <h3><?php echo number_format($stats['total_subjects']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <p><i class="bi bi-list-check"></i> Active Courses</p>
                    <h3><?php echo number_format($stats['active_courses']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <p><i class="bi bi-megaphone"></i> Announcements</p>
                    <h3><?php echo number_format($stats['total_announcements']); ?></h3>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #800000; color: white;">
                        <i class="bi bi-activity"></i> Recent Activity
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = $recent_activity->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($log['timestamp'])); ?></small></td>
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
                            <a href="programs.php" class="btn btn-outline-primary">
                                <i class="bi bi-mortarboard"></i> Manage Programs
                            </a>
                            <a href="curriculum.php" class="btn btn-outline-success">
                                <i class="bi bi-book"></i> Subject Catalog
                            </a>
                            <a href="announcements.php" class="btn btn-outline-warning">
                                <i class="bi bi-megaphone"></i> Announcements
                            </a>
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