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
$branch = $conn->query("SELECT name FROM branches WHERE id = $branch_id")->fetch_assoc();

// Build branch condition for queries
$branch_condition = $branch_id > 0 ? "up.branch_id = $branch_id" : "1=1";
$payment_branch_condition = $branch_id > 0 ? "(p.branch_id = $branch_id OR p.branch_id IS NULL)" : "1=1";

// Initialize stats
$stats = [
    'total_students' => 0,
    'active_students' => 0,
    'new_students_today' => 0,
    'new_students_month' => 0,
    'total_fees_assessed' => 0,
    'total_payments' => 0,
    'total_balance' => 0,
    'today_collections' => 0,
    'month_collections' => 0,
    'tuition_configs' => 0
];

// Total students in this branch
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM students s 
    INNER JOIN user_profiles up ON s.user_id = up.user_id 
    WHERE $branch_condition
");
if ($row = $result->fetch_assoc()) { $stats['total_students'] = $row['count']; }

// Active students
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM students s 
    INNER JOIN users u ON s.user_id = u.id
    INNER JOIN user_profiles up ON s.user_id = up.user_id 
    WHERE $branch_condition AND u.status = 'active'
");
if ($row = $result->fetch_assoc()) { $stats['active_students'] = $row['count']; }

// New students today
$today = date('Y-m-d');
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM students s 
    INNER JOIN users u ON s.user_id = u.id
    INNER JOIN user_profiles up ON s.user_id = up.user_id 
    WHERE $branch_condition AND DATE(u.created_at) = '$today'
");
if ($row = $result->fetch_assoc()) { $stats['new_students_today'] = $row['count']; }

// New students this month
$month_start = date('Y-m-01');
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM students s 
    INNER JOIN users u ON s.user_id = u.id
    INNER JOIN user_profiles up ON s.user_id = up.user_id 
    WHERE $branch_condition AND DATE(u.created_at) >= '$month_start'
");
if ($row = $result->fetch_assoc()) { $stats['new_students_month'] = $row['count']; }

// Total fees assessed
$result = $conn->query("
    SELECT COALESCE(SUM(sf.amount), 0) as total 
    FROM student_fees sf
    INNER JOIN students s ON sf.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id 
    WHERE $branch_condition
");
if ($row = $result->fetch_assoc()) { $stats['total_fees_assessed'] = $row['total']; }

// Total payments received (verified)
$result = $conn->query("
    SELECT COALESCE(SUM(p.amount), 0) as total 
    FROM payments p
    INNER JOIN students s ON p.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id 
    WHERE $payment_branch_condition AND p.status = 'verified'
");
if ($row = $result->fetch_assoc()) { $stats['total_payments'] = $row['total']; }

// Calculate total balance
$stats['total_balance'] = $stats['total_fees_assessed'] - $stats['total_payments'];

// Today's collections
$result = $conn->query("
    SELECT COALESCE(SUM(p.amount), 0) as total 
    FROM payments p
    INNER JOIN students s ON p.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id 
    WHERE $payment_branch_condition AND p.status = 'verified' AND DATE(p.created_at) = CURDATE()
");
if ($row = $result->fetch_assoc()) { $stats['today_collections'] = $row['total']; }

// This month's collections
$result = $conn->query("
    SELECT COALESCE(SUM(p.amount), 0) as total 
    FROM payments p
    INNER JOIN students s ON p.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id 
    WHERE $payment_branch_condition AND p.status = 'verified' AND DATE(p.created_at) >= '$month_start'
");
if ($row = $result->fetch_assoc()) { $stats['month_collections'] = $row['total']; }

// Tuition fee configurations
$result = $conn->query("SELECT COUNT(*) as count FROM program_tuition_fees");
if ($row = $result->fetch_assoc()) { $stats['tuition_configs'] = $row['count']; }

// Recent students (newly created)
$recent_students = $conn->query("
    SELECT s.student_no, s.user_id,
           CONCAT(up.first_name, ' ', up.last_name) as student_name,
           COALESCE(p.program_code, ss.strand_code) as program_code,
           u.created_at, u.status
    FROM students s 
    INNER JOIN users u ON s.user_id = u.id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id AND p.id IS NULL
    WHERE $branch_condition
    ORDER BY u.created_at DESC 
    LIMIT 5
");

// Recent payments
$recent_payments = $conn->query("
    SELECT s.student_no, 
           CONCAT(up.first_name, ' ', up.last_name) as student_name, 
           p.amount, p.status, p.or_number, p.reference_no, p.payment_type, p.description, p.created_at 
    FROM payments p 
    INNER JOIN students s ON p.student_id = s.user_id 
    INNER JOIN user_profiles up ON s.user_id = up.user_id 
    WHERE $payment_branch_condition
    ORDER BY p.created_at DESC 
    LIMIT 5
");

// Students with balance (top 5)
$students_with_balance = $conn->query("
    SELECT s.student_no, s.user_id,
           CONCAT(up.first_name, ' ', up.last_name) as student_name,
           COALESCE(p.program_code, ss.strand_code) as program_code,
           COALESCE((SELECT SUM(amount) FROM student_fees WHERE student_id = s.user_id), 0) as total_fees,
           COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.user_id AND status = 'verified'), 0) as total_paid
    FROM students s 
    INNER JOIN users u ON s.user_id = u.id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id AND p.id IS NULL
    WHERE $branch_condition
    HAVING (total_fees - total_paid) > 0
    ORDER BY (total_fees - total_paid) DESC 
    LIMIT 5
");

include '../../includes/header.php';
?>

<style>
.reg-stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: all 0.3s ease;
}
.reg-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.stat-icon-square {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.action-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem 1rem;
    text-align: center;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
}
.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    background: var(--maroon);
}
.action-card:hover i,
.action-card:hover div {
    color: white !important;
}
.action-card i {
    font-size: 2rem;
}
.activity-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.activity-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    padding: 1rem 1.25rem;
    font-weight: bold;
    border-bottom: 1px solid #eee;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.finance-summary-card {
    background: linear-gradient(135deg, var(--maroon) 0%, #6a1b3d 100%);
    color: white;
    border-radius: 20px;
    padding: 1.5rem;
}
</style>

<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-speedometer2 me-2 text-maroon"></i>Registrar Dashboard</h4>
            <p class="text-muted small mb-0">Branch: <span class="fw-bold text-dark"><?php echo htmlspecialchars($branch['name'] ?? 'Not Assigned'); ?></span></p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
                <i class="bi bi-calendar3 me-1 text-maroon"></i> <?php echo date('F d, Y'); ?>
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Stats Row 1: Students -->
    <h6 class="fw-bold mb-3 text-uppercase small opacity-75" style="letter-spacing: 1px;">
        <i class="bi bi-people-fill me-2 text-primary"></i>Student Statistics
    </h6>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="reg-stat-card border-start border-primary border-4">
                <div class="stat-icon-square bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_students']); ?></h3>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Total Students</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="reg-stat-card border-start border-success border-4">
                <div class="stat-icon-square bg-success bg-opacity-10 text-success"><i class="bi bi-person-check"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['active_students']); ?></h3>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Active Students</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="reg-stat-card border-start border-info border-4">
                <div class="stat-icon-square bg-info bg-opacity-10 text-info"><i class="bi bi-person-plus"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['new_students_today']); ?></h3>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">New Today</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="reg-stat-card border-start border-warning border-4">
                <div class="stat-icon-square bg-warning bg-opacity-10 text-warning"><i class="bi bi-calendar-plus"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['new_students_month']); ?></h3>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">This Month</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row 2: Finance -->
    <h6 class="fw-bold mb-3 text-uppercase small opacity-75" style="letter-spacing: 1px;">
        <i class="bi bi-cash-coin me-2 text-success"></i>Financial Overview
    </h6>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="reg-stat-card border-start border-primary border-4">
                <div class="stat-icon-square bg-primary bg-opacity-10 text-primary"><i class="bi bi-receipt"></i></div>
                <div>
                    <h4 class="fw-bold mb-0">₱<?php echo number_format($stats['total_fees_assessed'], 0); ?></h4>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Fees Assessed</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="reg-stat-card border-start border-success border-4">
                <div class="stat-icon-square bg-success bg-opacity-10 text-success"><i class="bi bi-wallet2"></i></div>
                <div>
                    <h4 class="fw-bold mb-0">₱<?php echo number_format($stats['total_payments'], 0); ?></h4>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Total Collected</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="reg-stat-card border-start border-danger border-4">
                <div class="stat-icon-square bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <h4 class="fw-bold mb-0">₱<?php echo number_format($stats['total_balance'], 0); ?></h4>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Outstanding Balance</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="reg-stat-card border-start border-info border-4">
                <div class="stat-icon-square bg-info bg-opacity-10 text-info"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <h4 class="fw-bold mb-0">₱<?php echo number_format($stats['today_collections'], 0); ?></h4>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Today's Collection</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Grid -->
    <h6 class="fw-bold mb-3 text-uppercase small opacity-75" style="letter-spacing: 1px;">
        <i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick Actions
    </h6>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <a href="students.php" class="action-card shadow-sm h-100">
                <i class="bi bi-person-plus-fill text-primary"></i>
                <div class="fw-bold small text-dark">Add Student</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="tuition_fees.php" class="action-card shadow-sm h-100">
                <i class="bi bi-currency-dollar text-success"></i>
                <div class="fw-bold small text-dark">Tuition Fees</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="payment_history.php" class="action-card shadow-sm h-100">
                <i class="bi bi-clock-history text-info"></i>
                <div class="fw-bold small text-dark">Payment History</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="certificates.php" class="action-card shadow-sm h-100">
                <i class="bi bi-award text-warning"></i>
                <div class="fw-bold small text-dark">Certificates</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="reports.php" class="action-card shadow-sm h-100">
                <i class="bi bi-bar-chart-line text-secondary"></i>
                <div class="fw-bold small text-dark">Reports</div>
            </a>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row g-4">
        <!-- Recent Students -->
        <div class="col-lg-4">
            <div class="activity-card h-100">
                <div class="activity-header"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Recent Students</div>
                <div class="p-0">
                    <ul class="list-group list-group-flush">
                        <?php if ($recent_students->num_rows == 0): ?>
                            <li class="list-group-item py-4 text-center text-muted small">No students found.</li>
                        <?php else: while ($row = $recent_students->fetch_assoc()): ?>
                            <li class="list-group-item px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark small"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                    <small class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($row['student_no']); ?> • <?php echo htmlspecialchars($row['program_code'] ?? 'N/A'); ?></small>
                                </div>
                                <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'secondary'; ?> rounded-pill" style="font-size: 0.65rem;"><?php echo strtoupper($row['status']); ?></span>
                            </li>
                        <?php endwhile; endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="col-lg-4">
            <div class="activity-card h-100">
                <div class="activity-header"><i class="bi bi-cash-coin me-2 text-success"></i>Recent Payments</div>
                <div class="p-0">
                    <ul class="list-group list-group-flush">
                        <?php if ($recent_payments->num_rows == 0): ?>
                            <li class="list-group-item py-4 text-center text-muted small">No payments recorded.</li>
                        <?php else: while ($row = $recent_payments->fetch_assoc()): 
                             $s_clr = ($row['status'] == 'verified') ? 'success' : (($row['status'] == 'rejected') ? 'danger' : 'warning');
                        ?>
                            <li class="list-group-item px-3 py-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold text-success small">₱<?php echo number_format($row['amount'], 2); ?></div>
                                        <small class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($row['student_name']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $s_clr; ?> bg-opacity-10 text-<?php echo $s_clr; ?>" style="font-size: 0.6rem;"><?php echo strtoupper($row['payment_type']); ?></span>
                                        <br><small class="text-muted" style="font-size: 0.6rem;"><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></small>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Students with Balance -->
        <div class="col-lg-4">
            <div class="activity-card h-100">
                <div class="activity-header"><i class="bi bi-exclamation-circle me-2 text-danger"></i>Outstanding Balances</div>
                <div class="p-0">
                    <ul class="list-group list-group-flush">
                        <?php if ($students_with_balance->num_rows == 0): ?>
                            <li class="list-group-item py-4 text-center text-muted small">All accounts settled!</li>
                        <?php else: while ($row = $students_with_balance->fetch_assoc()): 
                            $balance = $row['total_fees'] - $row['total_paid'];
                        ?>
                            <li class="list-group-item px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark small"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                    <small class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($row['student_no']); ?></small>
                                </div>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2">₱<?php echo number_format($balance, 0); ?></span>
                            </li>
                        <?php endwhile; endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Summary Card -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="finance-summary-card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-2"><i class="bi bi-graph-up-arrow me-2"></i>Monthly Summary - <?php echo date('F Y'); ?></h5>
                        <p class="mb-0 opacity-75 small">Overview of this month's collections and new registrations</p>
                    </div>
                    <div class="col-md-6">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end border-light border-opacity-25">
                                    <h3 class="fw-bold mb-0">₱<?php echo number_format($stats['month_collections'], 0); ?></h3>
                                    <small class="opacity-75">Collections</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <h3 class="fw-bold mb-0"><?php echo number_format($stats['new_students_month']); ?></h3>
                                <small class="opacity-75">New Students</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>
