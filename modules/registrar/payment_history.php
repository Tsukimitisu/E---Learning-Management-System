<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Payment History";
$registrar_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;
$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$payment_type = $_GET['payment_type'] ?? 'all';
$search = clean_input($_GET['search'] ?? '');

$where = "p.branch_id = $branch_id";
if ($start_date && $end_date) { $where .= " AND DATE(p.created_at) BETWEEN '$start_date' AND '$end_date'"; }
if ($payment_type != 'all') { $where .= " AND p.payment_type = '$payment_type'"; }
if (!empty($search)) { $where .= " AND (s.student_no LIKE '%$search%' OR up.first_name LIKE '%$search%' OR up.last_name LIKE '%$search%' OR p.or_number LIKE '%$search%')"; }

$summary = $conn->query("
    SELECT 
        COUNT(*) as total_count,
        SUM(amount) as total_amount,
        SUM(CASE WHEN payment_type = 'tuition' THEN amount ELSE 0 END) as tuition_total,
        SUM(CASE WHEN payment_type != 'tuition' THEN amount ELSE 0 END) as other_total
    FROM payments p
    WHERE $where
")->fetch_assoc();

$payments = $conn->query("
    SELECT p.*, s.student_no, 
           CONCAT(up.first_name, ' ', up.last_name) as student_name,
           CONCAT(rec.first_name, ' ', rec.last_name) as recorded_by_name,
           ay.year_name
    FROM payments p
    INNER JOIN students s ON p.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN user_profiles rec ON p.recorded_by = rec.user_id
    LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
    WHERE $where
    ORDER BY p.created_at DESC
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

    /* --- FANTASTIC FINANCE UI --- */
    .finance-stat-card {
        background: white; border-radius: 15px; padding: 20px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; transition: 0.3s;
    }
    .finance-stat-card:hover { transform: translateY(-5px); }

    .filter-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px; }
    .modern-input { border-radius: 50px; border: 1px solid #ddd; font-size: 0.85rem; font-weight: 600; padding-left: 15px; }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }

    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 12px 15px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    .btn-maroon-action {
        background-color: var(--maroon); color: white; border: none; border-radius: 50px;
        font-weight: 700; padding: 8px 20px; transition: 0.3s; font-size: 0.85rem;
    }
    .btn-maroon-action:hover { background-color: #600000; transform: translateY(-2px); color: white; }

    .btn-excel { background-color: #28a745; color: white; border-radius: 50px; border: none; font-weight: 700; padding: 8px 20px; font-size: 0.85rem; transition: 0.3s; }
    .btn-excel:hover { background-color: #1e7e34; color: white; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-clock-history me-2 text-maroon"></i>Financial Ledger</h4>
            <p class="text-muted small mb-0">Reviewing: <?php echo htmlspecialchars($branch['name'] ?? 'General Branch'); ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="record_payment.php" class="btn btn-maroon-action shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> New Payment
            </a>
            <button class="btn btn-excel shadow-sm" onclick="exportToExcel()">
                <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
            </button>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Stats Row -->
    <div class="row g-3 mb-4 animate__animated animate__fadeIn">
        <div class="col-md-3">
            <div class="finance-stat-card border-start border-primary border-5">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded"><i class="bi bi-wallet2 fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold">₱<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></h4><small class="text-muted text-uppercase fw-bold" style="font-size:0.6rem;">Total Collections</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="finance-stat-card border-start border-success border-5">
                <div class="p-2 bg-success bg-opacity-10 text-success rounded"><i class="bi bi-mortarboard fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold">₱<?php echo number_format($summary['tuition_total'] ?? 0, 2); ?></h4><small class="text-muted text-uppercase fw-bold" style="font-size:0.6rem;">Tuition Fees</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="finance-stat-card border-start border-info border-5">
                <div class="p-2 bg-info bg-opacity-10 text-info rounded"><i class="bi bi-box-seam fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold">₱<?php echo number_format($summary['other_total'] ?? 0, 2); ?></h4><small class="text-muted text-uppercase fw-bold" style="font-size:0.6rem;">Other Collections</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="finance-stat-card border-start border-warning border-5">
                <div class="p-2 bg-warning bg-opacity-10 text-warning rounded"><i class="bi bi-receipt-cutoff fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($summary['total_count'] ?? 0); ?></h4><small class="text-muted text-uppercase fw-bold" style="font-size:0.6rem;">Transaction Count</small></div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="filter-card animate__animated animate__fadeIn">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label small fw-bold">START DATE</label>
                <input type="date" class="form-control modern-input" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">END DATE</label>
                <input type="date" class="form-control modern-input" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">PAYMENT TYPE</label>
                <select class="form-select modern-input" name="payment_type">
                    <option value="all">All Types</option>
                    <?php 
                    $types = ['tuition', 'miscellaneous', 'laboratory', 'library', 'registration', 'id_card', 'diploma', 'transcript', 'clearance', 'other'];
                    foreach($types as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo $payment_type == $t ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $t)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">SEARCH KEYWORD</label>
                <input type="text" class="form-control modern-input" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Student ID, Name, or OR#">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold" style="background-color: var(--blue);">
                    <i class="bi bi-filter me-1"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="main-card-modern animate__animated animate__fadeInUp">
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0" id="paymentsTable">
                <thead>
                    <tr>
                        <th class="ps-4">Official Receipt</th>
                        <th>Student Account</th>
                        <th>Category</th>
                        <th>Method</th>
                        <th class="text-end">Amount</th>
                        <th>Recorded By</th>
                        <th class="text-center">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments->num_rows == 0): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No matching transaction records found.</td></tr>
                    <?php else: while ($p = $payments->fetch_assoc()): 
                        $type_colors = ['tuition' => 'primary', 'miscellaneous' => 'secondary', 'laboratory' => 'info', 'library' => 'warning', 'registration' => 'success', 'id_card' => 'dark', 'diploma' => 'danger', 'transcript' => 'primary', 'clearance' => 'success', 'other' => 'secondary'];
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['or_number'] ?? '-'); ?></div>
                            <small class="text-muted" style="font-size:0.7rem;"><?php echo $p['reference_no']; ?></small>
                        </td>
                        <td>
                            <div class="fw-bold text-maroon small"><?php echo htmlspecialchars($p['student_no']); ?></div>
                            <small class="text-dark fw-semibold"><?php echo htmlspecialchars($p['student_name']); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $type_colors[$p['payment_type']] ?? 'secondary'; ?> bg-opacity-10 text-<?php echo $type_colors[$p['payment_type']] ?? 'secondary'; ?> border border-<?php echo $type_colors[$p['payment_type']] ?? 'secondary'; ?> border-opacity-25 px-3">
                                <?php echo strtoupper(str_replace('_', ' ', $p['payment_type'])); ?>
                            </span>
                        </td>
                        <td><small class="text-muted fw-bold"><?php echo strtoupper($p['payment_method']); ?></small></td>
                        <td class="text-end fw-bold text-blue">₱<?php echo number_format($p['amount'], 2); ?></td>
                        <td>
                            <div class="small fw-bold text-dark"><?php echo htmlspecialchars($p['recorded_by_name'] ?? '-'); ?></div>
                            <small class="text-muted" style="font-size:0.7rem;"><?php echo date('M d, Y • h:i A', strtotime($p['created_at'])); ?></small>
                        </td>
                        <td class="text-center">
                            <button class="action-btn-circle text-primary border" onclick="printReceipt(<?php echo $p['id']; ?>)">
                                <i class="bi bi-printer-fill"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- SCRIPTS --- -->
<script>
function exportToExcel() {
    const table = document.getElementById('paymentsTable');
    if (!table) return alert('No data to export');
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach((col, index) => {
            if (index < 6) { // Don't export the action column
                rowData.push('"' + col.innerText.replace(/"/g, '""').replace(/\n/g, ' ') + '"');
            }
        });
        csv.push(rowData.join(','));
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'ELMS_Payment_History_<?php echo date('Y-m-d'); ?>.csv';
    link.click();
}

function printReceipt(paymentId) {
    window.open('process/print_receipt.php?id=' + paymentId, '_blank', 'width=450,height=700');
}
</script>
</body>
</html>