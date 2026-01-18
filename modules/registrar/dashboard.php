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
    'approved_today' => 0,
    'total_payments' => 0,
    'pending_payments' => 0
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

// Total Payments Collected
$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'verified'");
if ($row = $result->fetch_assoc()) {
    $stats['total_payments'] = $row['total'] ?? 0;
}

// Pending Payment Verifications
$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
if ($row = $result->fetch_assoc()) {
    $stats['pending_payments'] = $row['count'] ?? 0;
}

// Recent activity
$recent_enrollments = $conn->query("SELECT s.student_no, CONCAT(up.first_name, ' ', up.last_name) as student_name, e.created_at
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    ORDER BY e.created_at DESC
    LIMIT 5
");

$recent_payments = $conn->query("SELECT s.student_no, CONCAT(up.first_name, ' ', up.last_name) as student_name, p.amount, p.status, p.created_at
    FROM payments p
    INNER JOIN students s ON p.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    ORDER BY p.created_at DESC
    LIMIT 5
");

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
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <p><i class="bi bi-cash-coin"></i> Pending Payments</p>
                    <h3><?php echo number_format($stats['pending_payments']); ?></h3>
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
                            <div class="col-md-4 mb-3">
                                <a href="payments.php" class="btn btn-lg btn-warning w-100">
                                    <i class="bi bi-cash-coin"></i><br>
                                    Manage Payments
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="records.php" class="btn btn-lg btn-secondary w-100">
                                    <i class="bi bi-file-earmark-text"></i><br>
                                    Academic Records
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="certificates.php" class="btn btn-lg btn-danger w-100">
                                    <i class="bi bi-award"></i><br>
                                    Generate Certificates
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="reports.php" class="btn btn-lg btn-dark w-100">
                                    <i class="bi bi-file-earmark-bar-graph"></i><br>
                                    View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <i class="bi bi-clock-history"></i> Recent Enrollments
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php while ($row = $recent_enrollments->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($row['student_name']); ?> (<?php echo htmlspecialchars($row['student_no']); ?>)</span>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></small>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #800000; color: white;">
                        <i class="bi bi-receipt"></i> Recent Payments
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php while ($row = $recent_payments->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($row['student_name']); ?> (<?php echo htmlspecialchars($row['student_no']); ?>)</span>
                                    <span>
                                        <span class="badge bg-<?php echo $row['status'] === 'verified' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                        <small class="text-muted ms-2"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></small>
                                    </span>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>