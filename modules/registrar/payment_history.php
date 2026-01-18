<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Payment History";
$registrar_id = $_SESSION['user_id'];

// Get registrar's branch
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;

// Get branch info
$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();

// Filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$payment_type = $_GET['payment_type'] ?? 'all';
$search = clean_input($_GET['search'] ?? '');

// Build query
$where = "p.branch_id = $branch_id";
$params = [];
$types = "";

if ($start_date && $end_date) {
    $where .= " AND DATE(p.created_at) BETWEEN '$start_date' AND '$end_date'";
}

if ($payment_type != 'all') {
    $where .= " AND p.payment_type = '$payment_type'";
}

if (!empty($search)) {
    $where .= " AND (s.student_no LIKE '%$search%' OR up.first_name LIKE '%$search%' OR up.last_name LIKE '%$search%' OR p.or_number LIKE '%$search%')";
}

// Get summary stats
$summary = $conn->query("
    SELECT 
        COUNT(*) as total_count,
        SUM(amount) as total_amount,
        SUM(CASE WHEN payment_type = 'tuition' THEN amount ELSE 0 END) as tuition_total,
        SUM(CASE WHEN payment_type != 'tuition' THEN amount ELSE 0 END) as other_total
    FROM payments p
    WHERE $where
")->fetch_assoc();

// Get payments
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
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-clock-history me-2"></i>Payment History</h4>
                <small class="text-muted">Branch: <?php echo htmlspecialchars($branch['name'] ?? 'Unknown'); ?></small>
            </div>
            <div>
                <a href="record_payment.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> Record Payment
                </a>
                <button class="btn btn-outline-success" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-primary text-white">
                    <div class="card-body text-center">
                        <h6 class="opacity-75">Total Collections</h6>
                        <h3 class="fw-bold mb-0">₱<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-success text-white">
                    <div class="card-body text-center">
                        <h6 class="opacity-75">Tuition Fees</h6>
                        <h3 class="fw-bold mb-0">₱<?php echo number_format($summary['tuition_total'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-info text-white">
                    <div class="card-body text-center">
                        <h6 class="opacity-75">Other Fees</h6>
                        <h3 class="fw-bold mb-0">₱<?php echo number_format($summary['other_total'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm bg-warning text-dark">
                    <div class="card-body text-center">
                        <h6 class="opacity-75">Transactions</h6>
                        <h3 class="fw-bold mb-0"><?php echo number_format($summary['total_count'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label small">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Payment Type</label>
                        <select class="form-select" name="payment_type">
                            <option value="all">All Types</option>
                            <option value="tuition" <?php echo $payment_type == 'tuition' ? 'selected' : ''; ?>>Tuition</option>
                            <option value="miscellaneous" <?php echo $payment_type == 'miscellaneous' ? 'selected' : ''; ?>>Miscellaneous</option>
                            <option value="laboratory" <?php echo $payment_type == 'laboratory' ? 'selected' : ''; ?>>Laboratory</option>
                            <option value="library" <?php echo $payment_type == 'library' ? 'selected' : ''; ?>>Library</option>
                            <option value="registration" <?php echo $payment_type == 'registration' ? 'selected' : ''; ?>>Registration</option>
                            <option value="id_card" <?php echo $payment_type == 'id_card' ? 'selected' : ''; ?>>ID Card</option>
                            <option value="diploma" <?php echo $payment_type == 'diploma' ? 'selected' : ''; ?>>Diploma</option>
                            <option value="transcript" <?php echo $payment_type == 'transcript' ? 'selected' : ''; ?>>Transcript</option>
                            <option value="clearance" <?php echo $payment_type == 'clearance' ? 'selected' : ''; ?>>Clearance</option>
                            <option value="other" <?php echo $payment_type == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Student No, Name, or OR#">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if ($payments->num_rows == 0): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-receipt display-4"></i>
                    <p class="mt-2 mb-0">No payment records found</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="paymentsTable">
                        <thead class="table-light">
                            <tr>
                                <th>OR Number</th>
                                <th>Reference</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                                <th>A.Y. / Sem</th>
                                <th>Recorded By</th>
                                <th>Date</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = $payments->fetch_assoc()): 
                                $type_colors = [
                                    'tuition' => 'primary', 'miscellaneous' => 'secondary', 'laboratory' => 'info',
                                    'library' => 'warning', 'registration' => 'success', 'id_card' => 'dark',
                                    'diploma' => 'danger', 'transcript' => 'primary', 'clearance' => 'success', 'other' => 'secondary'
                                ];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($p['or_number'] ?? '-'); ?></strong></td>
                                <td><small class="text-muted"><?php echo $p['reference_no']; ?></small></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($p['student_no']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($p['student_name']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $type_colors[$p['payment_type']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $p['payment_type'])); ?>
                                    </span>
                                </td>
                                <td><small><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></small></td>
                                <td class="text-end fw-bold">₱<?php echo number_format($p['amount'], 2); ?></td>
                                <td>
                                    <small><?php echo $p['year_name'] ?? '-'; ?></small>
                                    <br><small class="text-muted"><?php echo ucfirst($p['semester'] ?? '-'); ?></small>
                                </td>
                                <td><small><?php echo htmlspecialchars($p['recorded_by_name'] ?? '-'); ?></small></td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($p['created_at'])); ?></small>
                                    <br><small class="text-muted"><?php echo date('h:i A', strtotime($p['created_at'])); ?></small>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" onclick="printReceipt(<?php echo $p['id']; ?>)">
                                        <i class="bi bi-printer"></i>
                                    </button>
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
</div>

<script>
function exportToExcel() {
    const table = document.getElementById('paymentsTable');
    if (!table) {
        alert('No data to export');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'payment_history_<?php echo date('Y-m-d'); ?>.csv';
    link.click();
}

function printReceipt(paymentId) {
    window.open('process/print_receipt.php?id=' + paymentId, '_blank', 'width=400,height=600');
}
</script>

<?php include '../../includes/footer.php'; ?>
