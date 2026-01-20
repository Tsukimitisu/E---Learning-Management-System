<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Payment Tracking";

/** 
 * ==========================================
 * BACKEND LOGIC - ABSOLUTELY UNTOUCHED
 * ==========================================
 */
$today = date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$status_filter = $_GET['status'] ?? 'all';
$search = clean_input($_GET['search'] ?? '');

// Summary cards logic
$summary = ['total_collected' => 0, 'pending_count' => 0, 'rejected_count' => 0, 'today_count' => 0];
$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'verified'");
if ($row = $result->fetch_assoc()) { $summary['total_collected'] = $row['total'] ?? 0; }
$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
if ($row = $result->fetch_assoc()) { $summary['pending_count'] = $row['count'] ?? 0; }
$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'rejected'");
if ($row = $result->fetch_assoc()) { $summary['rejected_count'] = $row['count'] ?? 0; }
$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE DATE(created_at) = '$today'");
if ($row = $result->fetch_assoc()) { $summary['today_count'] = $row['count'] ?? 0; }

// Payment records query
$query = "
    SELECT 
        p.id, p.amount, p.status, p.proof_file, p.created_at,
        s.user_id as student_id, s.student_no,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        u.email
    FROM payments p
    INNER JOIN students s ON p.student_id = s.user_id
    INNER JOIN users u ON s.user_id = u.id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    WHERE (? = 'all' OR p.status = ?)
      AND DATE(p.created_at) BETWEEN ? AND ?
";

$params = [$status_filter, $status_filter, $start_date, $end_date];
$types = "ssss";
if (!empty($search)) {
    $query .= " AND (s.student_no LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ? OR u.email LIKE ?)";
    $search_like = '%' . $search . '%';
    $params[] = $search_like; $params[] = $search_like; $params[] = $search_like; $params[] = $search_like;
    $types .= "ssss";
}
$query .= " ORDER BY p.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payments_result = $stmt->get_result();

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC UI COMPONENTS --- */
    .stat-card-finance {
        background: white; border-radius: 15px; padding: 20px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; transition: 0.3s;
    }
    .stat-card-finance:hover { transform: translateY(-5px); }

    .main-card-modern { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .filter-row-card { background: white; border-radius: 15px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 20px; }
    
    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    /* --- PERFECT CIRCLE BUTTON FIX --- */
    .action-btn-circle { 
        width: 36px !important; 
        height: 36px !important; 
        border-radius: 10% !important; 
        display: inline-flex !important; 
        align-items: center !important; 
        justify-content: center !important; 
        transition: 0.2s; 
        border: 1px solid #eee; 
        background: white;
        padding: 0 !important;
        aspect-ratio: 1 / 1;
    }
    .action-btn-circle:hover:not(:disabled) { transform: scale(1.1); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .action-btn-circle i { margin: 0 !important; font-size: 1rem; }

    .btn-filter-circle {
        width: 40px !important; height: 40px !important; border-radius: 50% !important;
        display: flex !important; align-items: center !important; justify-content: center !important;
        background-color: var(--blue) !important; color: white !important; padding: 0 !important; border: none !important;
    }

    .modern-input { border-radius: 50px; border: 1px solid #ddd; font-size: 0.8rem; font-weight: 600; padding-left: 15px; }
    .proof-thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; cursor: pointer; border: 1px solid #eee; transition: 0.2s; }
    .proof-thumb:hover { transform: scale(1.1); border-color: var(--maroon); }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-cash-stack me-2 text-maroon"></i>Verification Center</h4>
            <p class="text-muted small mb-0">Review student payment proofs and issue clearances</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
                <i class="bi bi-shield-check-fill me-1 text-success"></i> Secure Verification Active
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Summary Stats Row -->
    <div class="row g-3 mb-4 animate__animated animate__fadeIn">
        <div class="col-md-3">
            <div class="stat-card-finance border-start border-primary border-5">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded"><i class="bi bi-wallet2 fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo format_currency($summary['total_collected']); ?></h4><small class="text-muted fw-bold">Verified Collected</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-finance border-start border-warning border-5">
                <div class="p-2 bg-warning bg-opacity-10 text-warning rounded"><i class="bi bi-hourglass-split fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold text-warning"><?php echo number_format($summary['pending_count']); ?></h4><small class="text-muted fw-bold">Awaiting Review</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-finance border-start border-danger border-5">
                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded"><i class="bi bi-x-circle fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold text-danger"><?php echo number_format($summary['rejected_count']); ?></h4><small class="text-muted fw-bold">Total Rejected</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-finance border-start border-success border-5">
                <div class="p-2 bg-success bg-opacity-10 text-success rounded"><i class="bi bi-calendar-check fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold text-success"><?php echo number_format($summary['today_count']); ?></h4><small class="text-muted fw-bold">Entries Today</small></div>
            </div>
        </div>
    </div>

    <div id="alertContainer"></div>

    <!-- Modern Filter Form -->
    <div class="filter-row-card animate__animated animate__fadeIn">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.6rem;">Start Date</label>
                <input type="date" class="form-control modern-input shadow-sm" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.6rem;">End Date</label>
                <input type="date" class="form-control modern-input shadow-sm" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.6rem;">Payment Status</label>
                <select class="form-select modern-input shadow-sm" name="status">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.6rem;">Search Identity</label>
                <input type="text" class="form-control modern-input shadow-sm" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Student No or Name">
            </div>
            <div class="col-md-1 d-flex align-items-end justify-content-center">
                <button class="btn btn-filter-circle shadow-sm" type="submit" title="Filter Results"><i class="bi bi-funnel-fill"></i></button>
            </div>
        </form>
    </div>

    <!-- Main Data Ledger -->
    <div class="main-card-modern animate__animated animate__fadeInUp">
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Student & Identity</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Submission Date</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Proof</th>
                        <th class="text-center pe-4">Verification</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($payment = $payments_result->fetch_assoc()):
                        $status = $payment['status'];
                        $b_clr = $status === 'verified' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning');
                        $proof_url = $payment['proof_file'] ? '../../uploads/payments/' . $payment['proof_file'] : null;
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($payment['student_no']); ?></div>
                            <small class="text-muted fw-semibold"><?php echo htmlspecialchars($payment['student_name']); ?></small>
                        </td>
                        <td class="text-end fw-bold text-blue"><?php echo format_currency($payment['amount']); ?></td>
                        <td class="text-center small text-muted"><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-<?php echo $b_clr; ?> px-3 py-2" style="font-size: 0.65rem;">
                                <?php echo strtoupper($status); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($proof_url): ?>
                                <img src="<?php echo htmlspecialchars($proof_url); ?>" class="proof-thumb shadow-sm" onclick='openPaymentDetails(<?php echo json_encode($payment); ?>)' title="Preview Image">
                            <?php else: ?>
                                <span class="text-muted small italic">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-flex justify-content-center gap-2">
                                <button class="action-btn-circle text-info" onclick='openPaymentDetails(<?php echo json_encode($payment); ?>)' title="Details"><i class="bi bi-eye-fill"></i></button>
                                <button class="action-btn-circle text-success" onclick="verifyPayment(<?php echo $payment['id']; ?>)" <?php echo $status === 'verified' ? 'disabled' : ''; ?> title="Verify"><i class="bi bi-check-circle-fill"></i></button>
                                <button class="action-btn-circle text-danger" onclick="rejectPayment(<?php echo $payment['id']; ?>)" <?php echo $status === 'rejected' ? 'disabled' : ''; ?> title="Reject"><i class="bi bi-x-circle-fill"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($payments_result->num_rows == 0): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted small fst-italic">No payment transactions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt-cutoff me-2"></i>Transaction Dossier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-md-5 p-4 bg-light border-end">
                        <h6 class="fw-bold text-blue text-uppercase small mb-4">Metadata</h6>
                        <div id="paymentDetailsContent"></div>
                    </div>
                    <div class="col-md-7 p-4 bg-white text-center">
                        <h6 class="fw-bold text-muted text-uppercase small mb-3">Electronic Receipt / Proof</h6>
                        <div id="proofImageContainer" class="p-2 border rounded-3 bg-light" style="min-height: 200px; display: flex; align-items: center; justify-content: center;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light fw-bold rounded-pill px-4" data-bs-dismiss="modal">Dismiss</button>
                <button type="button" class="btn btn-primary shadow-sm px-4 rounded-pill" id="clearanceBtn" style="background-color: var(--maroon); border: none;">
                    <i class="bi bi-file-earmark-check me-1"></i> Issue Clearance
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
let selectedPayment = null;

function openPaymentDetails(payment) {
    selectedPayment = payment;
    const proofHtml = payment.proof_file 
        ? `<img src="../../uploads/payments/${payment.proof_file}" class="img-fluid rounded shadow-sm">` 
        : '<div class="text-muted small"><i class="bi bi-image display-4 d-block opacity-25"></i>No document provided</div>';

    document.getElementById('paymentDetailsContent').innerHTML = `
        <div class="mb-3">
            <label class="info-label">Student Name</label>
            <div class="fw-bold text-dark">${payment.student_name}</div>
            <small class="text-muted">${payment.student_no}</small>
        </div>
        <div class="mb-3">
            <label class="info-label">Account Email</label>
            <div class="small text-dark">${payment.email}</div>
        </div>
        <div class="mb-3">
            <label class="info-label">Amount Transferred</label>
            <div class="h4 fw-bold text-success">₱${parseFloat(payment.amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
        </div>
        <div class="mb-0">
            <label class="info-label">Registration Date</label>
            <div class="small text-dark fw-bold">${payment.created_at}</div>
        </div>
    `;
    document.getElementById('proofImageContainer').innerHTML = proofHtml;
    document.getElementById('clearanceBtn').onclick = () => {
        window.open(`process/generate_clearance.php?student_id=${payment.student_id}`, '_blank');
    };
    new bootstrap.Modal(document.getElementById('paymentDetailsModal')).show();
}

/** AJAX HANDLERS (RESTORED) */
async function verifyPayment(paymentId) {
    if (!confirm('Authorize this payment? This will update student balance.')) return;
    try {
        const response = await fetch('process/verify_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payment_id: paymentId, action: 'verify' })
        });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1000); } 
        else { showAlert(data.message, 'danger'); }
    } catch (e) { showAlert('Sync error with finance server.', 'danger'); }
}

async function rejectPayment(paymentId) {
    const reason = prompt('State the reason for transaction rejection:');
    if (!reason) return;
    try {
        const response = await fetch('process/verify_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payment_id: paymentId, action: 'reject', reason })
        });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1000); } 
        else { showAlert(data.message, 'danger'); }
    } catch (e) { showAlert('Error processing rejection.', 'danger'); }
}

function showAlert(message, type) {
    const html = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = html;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>