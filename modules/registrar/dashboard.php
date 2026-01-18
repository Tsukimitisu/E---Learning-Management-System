<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Registrar Dashboard";
$registrar_id = $_SESSION['user_id'];

// Get registrar's branch
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;

// Get branch info
$branch = $conn->query("SELECT name FROM branches WHERE id = $branch_id")->fetch_assoc();

// Fetch Statistics for this branch
$stats = [
    'total_students' => 0,
    'pending_enrollments' => 0,
    'active_classes' => 0,
    'approved_today' => 0,
    'total_payments' => 0,
    'pending_payments' => 0,
    'today_collections' => 0
];

// Total Students in this branch
$result = $conn->query("SELECT COUNT(*) as count FROM students s INNER JOIN user_profiles up ON s.user_id = up.user_id WHERE up.branch_id = $branch_id");
if ($row = $result->fetch_assoc()) {
    $stats['total_students'] = $row['count'];
}

// Pending Enrollments
$result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'pending'");
if ($row = $result->fetch_assoc()) {
    $stats['pending_enrollments'] = $row['count'];
}

// Active Classes for this branch
$result = $conn->query("SELECT COUNT(*) as count FROM classes WHERE branch_id = $branch_id");
if ($row = $result->fetch_assoc()) {
    $stats['active_classes'] = $row['count'];
}

// Approved Today
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'approved' AND DATE(created_at) = '$today'");
if ($row = $result->fetch_assoc()) {
    $stats['approved_today'] = $row['count'];
}

// Total Payments Collected for this branch
$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'verified' AND branch_id = $branch_id");
if ($row = $result->fetch_assoc()) {
    $stats['total_payments'] = $row['total'] ?? 0;
}

// Pending Payment Verifications
$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
if ($row = $result->fetch_assoc()) {
    $stats['pending_payments'] = $row['count'] ?? 0;
}

// Today's collections for this branch
$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'verified' AND branch_id = $branch_id AND DATE(created_at) = CURDATE()");
if ($row = $result->fetch_assoc()) {
    $stats['today_collections'] = $row['total'] ?? 0;
}

// Recent activity
$recent_enrollments = $conn->query("SELECT s.student_no, CONCAT(up.first_name, ' ', up.last_name) as student_name, e.created_at
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    ORDER BY e.created_at DESC
    LIMIT 5
");

$recent_payments = $conn->query("SELECT s.student_no, CONCAT(up.first_name, ' ', up.last_name) as student_name, p.amount, p.status, p.or_number, p.created_at
    FROM payments p
    INNER JOIN students s ON p.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    WHERE p.branch_id = $branch_id
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
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <p><i class="bi bi-receipt"></i> Today's Collections</p>
                    <h3>₱<?php echo number_format($stats['today_collections'], 2); ?></h3>
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
                            <div class="col-md-3 mb-3">
                                <a href="program_enrollment.php" class="btn btn-lg btn-primary w-100">
                                    <i class="bi bi-mortarboard-fill"></i><br>
                                    Program Enrollment
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="enroll.php" class="btn btn-lg btn-info w-100">
                                    <i class="bi bi-pencil-square"></i><br>
                                    Class Enrollment
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="record_payment.php" class="btn btn-lg btn-success w-100">
                                    <i class="bi bi-receipt"></i><br>
                                    Record Payment
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="payment_history.php" class="btn btn-lg btn-warning w-100">
                                    <i class="bi bi-clock-history"></i><br>
                                    Payment History
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="students.php" class="btn btn-lg btn-secondary w-100">
                                    <i class="bi bi-people"></i><br>
                                    All Students
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="records.php" class="btn btn-lg btn-info w-100">
                                    <i class="bi bi-file-earmark-text"></i><br>
                                    Academic Records
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="certificates.php" class="btn btn-lg btn-danger w-100">
                                    <i class="bi bi-award"></i><br>
                                    Generate Certificates
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
                                    <div>
                                        <span><?php echo htmlspecialchars($row['student_name']); ?></span>
                                        <br><small class="text-muted">OR: <?php echo htmlspecialchars($row['or_number'] ?? '-'); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $row['status'] === 'verified' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                            ₱<?php echo number_format($row['amount'], 2); ?>
                                        </span>
                                        <br><small class="text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></small>
                                    </div>
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