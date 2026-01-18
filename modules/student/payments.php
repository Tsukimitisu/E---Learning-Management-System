<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Payments";
$student_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get total assessed fees
$total_fees = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total FROM student_fees WHERE student_id = $student_id
")->fetch_assoc()['total'];

// Get payment summary
$summary = $conn->query("
    SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(amount), 0) as total_paid,
        COALESCE(SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END), 0) as verified_total,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_total
    FROM payments 
    WHERE student_id = $student_id
")->fetch_assoc();

$balance = $total_fees - $summary['verified_total'];

// Get all payments
$payments = $conn->query("
    SELECT p.*, ay.year_name,
           CONCAT(rec.first_name, ' ', rec.last_name) as recorded_by_name
    FROM payments p
    LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
    LEFT JOIN user_profiles rec ON p.recorded_by = rec.user_id
    WHERE p.student_id = $student_id
    ORDER BY p.created_at DESC
");

// Get assessed fees
$fees = $conn->query("
    SELECT sf.*, ay.year_name
    FROM student_fees sf
    LEFT JOIN academic_years ay ON sf.academic_year_id = ay.id
    WHERE sf.student_id = $student_id
    ORDER BY sf.created_at DESC
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-receipt me-2"></i>My Payments</h4>
                <small class="text-muted">Academic Year: <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></small>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-primary text-white h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-calculator display-6 opacity-75"></i>
                        <h3 class="fw-bold mb-0 mt-2">₱<?php echo number_format($total_fees, 2); ?></h3>
                        <small class="opacity-75">Total Fees</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-success text-white h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-6 opacity-75"></i>
                        <h3 class="fw-bold mb-0 mt-2">₱<?php echo number_format($summary['verified_total'], 2); ?></h3>
                        <small class="opacity-75">Total Paid</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm <?php echo $balance > 0 ? 'bg-danger' : 'bg-info'; ?> text-white h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-wallet2 display-6 opacity-75"></i>
                        <h3 class="fw-bold mb-0 mt-2">₱<?php echo number_format(abs($balance), 2); ?></h3>
                        <small class="opacity-75"><?php echo $balance > 0 ? 'Balance Due' : ($balance < 0 ? 'Overpaid' : 'Fully Paid'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-warning text-dark h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-clock display-6 opacity-75"></i>
                        <h3 class="fw-bold mb-0 mt-2">₱<?php echo number_format($summary['pending_total'], 2); ?></h3>
                        <small class="opacity-75">Pending</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Payment History -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Payment History</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($payments->num_rows == 0): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-receipt display-4"></i>
                            <p class="mt-2 mb-0">No payment records found</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>OR Number</th>
                                        <th>Type</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($p = $payments->fetch_assoc()): 
                                        $status_colors = ['verified' => 'success', 'pending' => 'warning', 'rejected' => 'danger'];
                                    ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($p['created_at'])); ?></small>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($p['or_number'] ?? '-'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($p['payment_type'] ?? 'Other'); ?></td>
                                        <td class="text-end fw-bold">₱<?php echo number_format($p['amount'], 2); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $status_colors[$p['status']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst($p['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Assessed Fees -->
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Assessed Fees</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($fees->num_rows == 0): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-clipboard display-4"></i>
                            <p class="mt-2 mb-0">No fees assessed yet</p>
                        </div>
                        <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php while ($f = $fees->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($f['fee_type']); ?></strong>
                                    <br><small class="text-muted"><?php echo $f['semester']; ?> Semester</small>
                                </div>
                                <span class="badge bg-primary rounded-pill">₱<?php echo number_format($f['amount'], 2); ?></span>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Info Note -->
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <small>For payment inquiries, please visit the Registrar's Office.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
