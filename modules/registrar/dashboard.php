<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Registrar Dashboard";
$registrar_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;
$branch = $conn->query("SELECT name FROM branches WHERE id = $branch_id")->fetch_assoc();

$stats = [
    'total_students' => 0, 'pending_enrollments' => 0, 'active_classes' => 0, 
    'approved_today' => 0, 'total_payments' => 0, 'pending_payments' => 0, 'today_collections' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM students s INNER JOIN user_profiles up ON s.user_id = up.user_id WHERE up.branch_id = $branch_id");
if ($row = $result->fetch_assoc()) { $stats['total_students'] = $row['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'pending'");
if ($row = $result->fetch_assoc()) { $stats['pending_enrollments'] = $row['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM classes WHERE branch_id = $branch_id");
if ($row = $result->fetch_assoc()) { $stats['active_classes'] = $row['count']; }

$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'approved' AND DATE(created_at) = '$today'");
if ($row = $result->fetch_assoc()) { $stats['approved_today'] = $row['count']; }

$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'verified' AND branch_id = $branch_id");
if ($row = $result->fetch_assoc()) { $stats['total_payments'] = $row['total'] ?? 0; }

$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
if ($row = $result->fetch_assoc()) { $stats['pending_payments'] = $row['count'] ?? 0; }

$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'verified' AND branch_id = $branch_id AND DATE(created_at) = CURDATE()");
if ($row = $result->fetch_assoc()) { $stats['today_collections'] = $row['total'] ?? 0; }

$recent_enrollments = $conn->query("SELECT s.student_no, CONCAT(up.first_name, ' ', up.last_name) as student_name, e.created_at FROM enrollments e INNER JOIN students s ON e.student_id = s.user_id INNER JOIN user_profiles up ON s.user_id = up.user_id ORDER BY e.created_at DESC LIMIT 5");
$recent_payments = $conn->query("SELECT s.student_no, CONCAT(up.first_name, ' ', up.last_name) as student_name, p.amount, p.status, p.or_number, p.created_at FROM payments p INNER JOIN students s ON p.student_id = s.user_id INNER JOIN user_profiles up ON s.user_id = up.user_id WHERE p.branch_id = $branch_id ORDER BY p.created_at DESC LIMIT 5");

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC REGISTRAR UI --- */
    .reg-stat-card {
        background: white; border-radius: 15px; padding: 25px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s;
        height: 100%; display: flex; align-items: center; gap: 20px;
    }
    .reg-stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    
    .stat-icon-square {
        width: 55px; height: 55px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
    }

    .small text-dark:hover {
        color: white !important;
    }

    .action-card {
        background: white; border-radius: 20px; border: 1px solid #eee;
        padding: 20px; text-align: center; text-decoration: none; transition: 0.3s;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .action-card:hover { background: var(--blue); color: white !important; transform: scale(1.05); box-shadow: 0 5px 15px rgba(0,51,102,0.2); }
    .action-card i { font-size: 2rem; margin-bottom: 10px; transition: 0.3s; }
    .action-card:hover i { transform: rotate(-10deg); color: white !important; }
    .action-card:hover .text-dark {color: white !important;}

    .activity-card { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .activity-header { background: #fcfcfc; padding: 15px 25px; border-bottom: 1px solid #eee; font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; }

    /* Staggered Delays */
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }

    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-speedometer2 me-2 text-maroon"></i>Registrar Hub</h4>
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
    
    <!-- Stats Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-4 col-xl-3 animate__animated animate__zoomIn delay-1">
            <div class="reg-stat-card border-start border-primary border-5">
                <div class="stat-icon-square bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_students']); ?></h3>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Total Students</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-3 animate__animated animate__zoomIn delay-2">
            <div class="reg-stat-card border-start border-warning border-5">
                <div class="stat-icon-square bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock-history"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['pending_enrollments']); ?></h3>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Pending Reg</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-3 animate__animated animate__zoomIn delay-3">
            <div class="reg-stat-card border-start border-success border-5">
                <div class="stat-icon-square bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['approved_today']); ?></h3>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Approved Today</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-3 animate__animated animate__zoomIn delay-4">
            <div class="reg-stat-card border-start border-danger border-5">
                <div class="stat-icon-square bg-danger bg-opacity-10 text-danger"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <h3 class="fw-bold mb-0">₱<?php echo number_format($stats['today_collections'], 0); ?></h3>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Collections Today</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Grid -->
   <h6 class="fw-bold mb-3 text-uppercase small opacity-75" style="letter-spacing: 1px;">
        <i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Administrative Operations
    </h6>
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-4 col-lg-3 col-xl-2 animate__animated animate__fadeInUp delay-1">
            <a href="program_enrollment.php" class="action-card shadow-sm h-100">
                <i class="bi bi-mortarboard-fill text-primary"></i>
                <!-- The text below will now turn white on hover because of the CSS fix above -->
                <div class="fw-bold small text-dark">Program Enrollment</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3 col-xl-2 animate__animated animate__fadeInUp delay-2">
            <a href="enroll.php" class="action-card shadow-sm h-100">
                <i class="bi bi-pencil-square text-info"></i>
                <div class="fw-bold small text-dark">Class Enrollment</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3 col-xl-2 animate__animated animate__fadeInUp delay-3">
            <a href="record_payment.php" class="action-card shadow-sm h-100">
                <i class="bi bi-receipt text-success"></i>
                <div class="fw-bold small text-dark">Record Payment</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3 col-xl-2 animate__animated animate__fadeInUp delay-4">
            <a href="payment_history.php" class="action-card shadow-sm h-100">
                <i class="bi bi-clock-history text-warning"></i>
                <div class="fw-bold small text-dark">Payment History</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3 col-xl-2 animate__animated animate__fadeInUp delay-1">
            <a href="students.php" class="action-card shadow-sm h-100">
                <i class="bi bi-people-fill text-secondary"></i>
                <div class="fw-bold small text-dark">All Students</div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3 col-xl-2 animate__animated animate__fadeInUp delay-2">
            <a href="records.php" class="action-card shadow-sm h-100">
                <i class="bi bi-file-earmark-text text-info"></i>
                <div class="fw-bold small text-dark">Academic Records</div>
            </a>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row g-4">
        <!-- Recent Enrollments -->
        <div class="col-lg-6 animate__animated animate__fadeInLeft">
            <div class="activity-card">
                <div class="activity-header"><i class="bi bi-person-plus-fill me-2 text-maroon"></i>New Registrations</div>
                <div class="p-0">
                    <ul class="list-group list-group-flush">
                        <?php if ($recent_enrollments->num_rows == 0): ?>
                            <li class="list-group-item py-4 text-center text-muted">No recent enrollments found.</li>
                        <?php else: while ($row = $recent_enrollments->fetch_assoc()): ?>
                            <li class="list-group-item px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['student_no']); ?></small>
                                </div>
                                <span class="badge bg-light text-muted border"><?php echo date('M d', strtotime($row['created_at'])); ?></span>
                            </li>
                        <?php endwhile; endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="col-lg-6 animate__animated animate__fadeInRight">
            <div class="activity-card">
                <div class="activity-header"><i class="bi bi-cash-coin me-2 text-success"></i>Finance Log</div>
                <div class="p-0">
                    <ul class="list-group list-group-flush">
                        <?php if ($recent_payments->num_rows == 0): ?>
                            <li class="list-group-item py-4 text-center text-muted">No recent payments recorded.</li>
                        <?php else: while ($row = $recent_payments->fetch_assoc()): 
                             $s_clr = ($row['status'] == 'verified') ? 'success' : (($row['status'] == 'rejected') ? 'danger' : 'warning');
                        ?>
                            <li class="list-group-item px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark">₱<?php echo number_format($row['amount'], 2); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['student_name']); ?> • OR: <?php echo htmlspecialchars($row['or_number'] ?? 'N/A'); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $s_clr; ?> rounded-pill mb-1 d-inline-block"><?php echo strtoupper($row['status']); ?></span>
                                    <br><small class="text-muted" style="font-size:0.65rem;"><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></small>
                                </div>
                            </li>
                        <?php endwhile; endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>