<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Payments";
$student_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$total_fees = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total FROM student_fees WHERE student_id = $student_id
")->fetch_assoc()['total'];

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

$payments = $conn->query("
    SELECT p.*, ay.year_name,
           CONCAT(rec.first_name, ' ', rec.last_name) as recorded_by_name
    FROM payments p
    LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
    LEFT JOIN user_profiles rec ON p.recorded_by = rec.user_id
    WHERE p.student_id = $student_id
    ORDER BY p.created_at DESC
");

$fees = $conn->query("
    SELECT sf.*, ay.year_name
    FROM student_fees sf
    LEFT JOIN academic_years ay ON sf.academic_year_id = ay.id
    WHERE sf.student_id = $student_id
    ORDER BY sf.created_at DESC
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC PAYMENT UI --- */
    .finance-card {
        border-radius: 15px; padding: 25px; border: none; color: white;
        transition: 0.3s; height: 100%; display: flex; align-items: center; gap: 20px;
    }
    .finance-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    
    .finance-icon {
        width: 55px; height: 55px; border-radius: 12px; background: rgba(255,255,255,0.2);
        display: flex; align-items: center; justify-content: center; font-size: 1.8rem;
    }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }

    .card-header-modern { background: #fcfcfc; padding: 15px 25px; border-bottom: 1px solid #eee; font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

    .table-modern thead th { background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; padding: 15px 20px; position: sticky; top: -1px; z-index: 5; }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; font-size: 0.9rem; }

    .currency-symbol { font-size: 0.8rem; opacity: 0.7; margin-right: 2px; }

    .fee-item { border-left: 4px solid var(--maroon); padding: 12px 15px; background: #fff; margin-bottom: 10px; border-radius: 0 8px 8px 0; transition: 0.2s; }
    .fee-item:hover { background: #fafafa; }

    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-wallet2 me-2 text-maroon"></i>Account Balance & Payments</h4>
            <p class="text-muted small mb-0">Financial statement for <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></p>
        </div>
        <div class="text-end">
             <button class="btn btn-light btn-sm border rounded-pill px-3 shadow-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print Statement
            </button>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Summary Row Staggered Animation -->
    <div class="row g-4 mb-5">
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.1s;">
            <div class="finance-card shadow-sm" style="background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%);">
                <div class="finance-icon"><i class="bi bi-calculator"></i></div>
                <div>
                    <h3 class="fw-bold mb-0">₱<?php echo number_format($total_fees, 2); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Total Assessment</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.2s;">
            <div class="finance-card shadow-sm" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                <div class="finance-icon"><i class="bi bi-check-circle"></i></div>
                <div>
                    <h3 class="fw-bold mb-0">₱<?php echo number_format($summary['verified_total'], 2); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Verified Paid</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.3s;">
            <div class="finance-card shadow-sm shadow-lg" style="background: linear-gradient(135deg, <?php echo $balance > 0 ? 'var(--maroon)' : '#17a2b8'; ?> 0%, #4a0000 100%);">
                <div class="finance-icon"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <h3 class="fw-bold mb-0">₱<?php echo number_format(abs($balance), 2); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">
                        <?php echo $balance > 0 ? 'Outstanding Balance' : ($balance < 0 ? 'Overpayment' : 'Fully Paid'); ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.4s;">
            <div class="finance-card shadow-sm" style="background: linear-gradient(135deg, #ffc107 0%, #d39e00 100%); color: #333;">
                <div class="finance-icon" style="background: rgba(0,0,0,0.1);"><i class="bi bi-clock-history"></i></div>
                <div>
                    <h3 class="fw-bold mb-0">₱<?php echo number_format($summary['pending_total'], 2); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">In Verification</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Payment History (Left) -->
        <div class="col-lg-8 animate__animated animate__fadeInLeft">
            <div class="main-card-modern">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-stars me-2"></i>Transaction Ledger</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Transaction Date</th>
                                <th>Official Receipt</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($payments->num_rows == 0): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No transactions found on this account.</td></tr>
                            <?php else: while ($p = $payments->fetch_assoc()): 
                                $s_clr = ['verified' => 'success', 'pending' => 'warning', 'rejected' => 'danger'][$p['status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($p['created_at'])); ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border fw-bold"><?php echo htmlspecialchars($p['or_number'] ?? 'NO-OR'); ?></span></td>
                                <td><small class="text-muted text-uppercase fw-bold"><?php echo htmlspecialchars($p['payment_type']); ?></small></td>
                                <td class="text-end fw-bold text-blue">₱<?php echo number_format($p['amount'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-<?php echo $s_clr; ?> px-3 py-2">
                                        <?php echo strtoupper($p['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Assessed Fees (Right) -->
        <div class="col-lg-4 animate__animated animate__fadeInRight">
            <div class="main-card-modern">
                <div class="card-header-modern bg-white"><i class="bi bi-card-list me-2"></i>Breakdown of Fees</div>
                <div class="p-4">
                    <?php if ($fees->num_rows == 0): ?>
                        <div class="text-center py-4 text-muted small">No assessed fees found.</div>
                    <?php else: while ($f = $fees->fetch_assoc()): ?>
                    <div class="fee-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($f['fee_type']); ?></div>
                            <small class="text-muted" style="font-size:0.65rem;"><?php echo $f['semester']; ?> Semester • <?php echo $f['year_name']; ?></small>
                        </div>
                        <span class="fw-bold text-maroon">₱<?php echo number_format($f['amount'], 2); ?></span>
                    </div>
                    <?php endwhile; endif; ?>

                    <div class="alert bg-light border-0 mt-4 small text-muted">
                        <i class="bi bi-info-circle-fill text-blue me-2"></i>
                        For payment validation or discrepancies, please present your physical receipt to the Cashier's Office.
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>