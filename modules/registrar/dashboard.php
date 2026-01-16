<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Registrar Dashboard";

// Fetch Statistics
$stats = [
    'total_students' => 0,
    'pending_enrollments' => 0,
    'active_classes' => 0,
    'approved_today' => 0
];

// Total Students
$result = $conn->query("SELECT COUNT(*) as count FROM students");
if ($row = $result->fetch_assoc()) {
    $stats['total_students'] = $row['count'];
}

// Pending Enrollments
$result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'pending'");
if ($row = $result->fetch_assoc()) {
    $stats['pending_enrollments'] = $row['count'];
}

// Active Classes
$result = $conn->query("SELECT COUNT(*) as count FROM classes");
if ($row = $result->fetch_assoc()) {
    $stats['active_classes'] = $row['count'];
}

// Approved Today
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'approved' AND DATE(created_at) = '$today'");
if ($row = $result->fetch_assoc()) {
    $stats['approved_today'] = $row['count'];
}

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-speedometer2"></i> Registrar Dashboard
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
                    <p><i class="bi bi-clock-history"></i> Pending Enrollments</p>
                    <h3><?php echo number_format($stats['pending_enrollments']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <p><i class="bi bi-door-open"></i> Active Classes</p>
                    <h3><?php echo number_format($stats['active_classes']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <p><i class="bi bi-check-circle"></i> Approved Today</p>
                    <h3><?php echo number_format($stats['approved_today']); ?></h3>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #800000; color: white;">
                        <i class="bi bi-list-check"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <a href="enroll.php" class="btn btn-lg btn-primary w-100">
                                    <i class="bi bi-pencil-square"></i><br>
                                    Enroll Student
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="classes.php" class="btn btn-lg btn-success w-100">
                                    <i class="bi bi-door-open"></i><br>
                                    Manage Classes
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="students.php" class="btn btn-lg btn-info w-100">
                                    <i class="bi bi-people"></i><br>
                                    View Students
                                </a>
                            </div>
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