<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Payment Tracking";

$today = date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$status_filter = $_GET['status'] ?? 'all';
$search = clean_input($_GET['search'] ?? '');

// Summary cards
$summary = [
    'total_collected' => 0,
    'pending_count' => 0,
    'rejected_count' => 0,
    'today_count' => 0
];

$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'verified'");
if ($row = $result->fetch_assoc()) {
    $summary['total_collected'] = $row['total'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
if ($row = $result->fetch_assoc()) {
    $summary['pending_count'] = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'rejected'");
if ($row = $result->fetch_assoc()) {
    $summary['rejected_count'] = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE DATE(created_at) = '$today'");
if ($row = $result->fetch_assoc()) {
    $summary['today_count'] = $row['count'] ?? 0;
}

// Payment records
$query = "
    SELECT 
        p.id, p.amount, p.status, p.proof_file, p.created_at,
        s.user_id as student_id,
        s.student_no,
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
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ssss";
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payments_result = $stmt->get_result();

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-cash-coin"></i> Payment Tracking
            </h4>
        </div>

        <div id="alertContainer"></div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <p><i class="bi bi-cash"></i> Total Collected</p>
                    <h3><?php echo format_currency($summary['total_collected']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <p><i class="bi bi-clock-history"></i> Pending</p>
                    <h3><?php echo number_format($summary['pending_count']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <p><i class="bi bi-x-circle"></i> Rejected</p>
                    <h3><?php echo number_format($summary['rejected_count']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <p><i class="bi bi-calendar-check"></i> Today</p>
                    <h3><?php echo number_format($summary['today_count']); ?></h3>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student or email">
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-filter"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payment Records Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Proof</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments_result->fetch_assoc()):
                                $status = $payment['status'];
                                $badge_class = $status === 'verified' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning');
                                $proof_link = $payment['proof_file'] ? '../../uploads/payments/' . $payment['proof_file'] : '';
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($payment['student_no']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($payment['student_name']); ?></small>
                                </td>
                                <td><?php echo format_currency($payment['amount']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                <td><span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span></td>
                                <td>
                                    <?php if ($payment['proof_file']): ?>
                                        <a href="<?php echo htmlspecialchars($proof_link); ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info me-1" onclick='openPaymentDetails(<?php echo json_encode($payment); ?>)'>
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-success me-1" onclick="verifyPayment(<?php echo $payment['id']; ?>)" <?php echo $status === 'verified' ? 'disabled' : ''; ?>>
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="rejectPayment(<?php echo $payment['id']; ?>)" <?php echo $status === 'rejected' ? 'disabled' : ''; ?>>
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #003366; color: white;">
                <h5 class="modal-title"><i class="bi bi-receipt"></i> Payment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="paymentDetailsContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary" id="clearanceBtn">Generate Clearance</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedPayment = null;

function openPaymentDetails(payment) {
    selectedPayment = payment;
    const proof = payment.proof_file ? `<img src="../../uploads/payments/${payment.proof_file}" class="img-fluid border rounded">` : '<span class="text-muted">No proof uploaded</span>';

    document.getElementById('paymentDetailsContent').innerHTML = `
        <p><strong>Student:</strong> ${payment.student_name} (${payment.student_no})</p>
        <p><strong>Email:</strong> ${payment.email}</p>
        <p><strong>Amount:</strong> ${new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(payment.amount)}</p>
        <p><strong>Status:</strong> ${payment.status}</p>
        <p><strong>Date:</strong> ${payment.created_at}</p>
        <hr>
        <h6>Proof of Payment</h6>
        ${proof}
    `;

    document.getElementById('clearanceBtn').onclick = () => {
        window.open(`process/generate_clearance.php?student_id=${payment.student_id}`, '_blank');
    };

    new bootstrap.Modal(document.getElementById('paymentDetailsModal')).show();
}

async function verifyPayment(paymentId) {
    if (!confirm('Verify this payment?')) return;

    const response = await fetch('process/verify_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ payment_id: paymentId, action: 'verify' })
    });
    const data = await response.json();

    if (data.status === 'success') {
        showAlert(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showAlert(data.message, 'danger');
    }
}

async function rejectPayment(paymentId) {
    const reason = prompt('Enter rejection reason:');
    if (!reason) return;

    const response = await fetch('process/verify_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ payment_id: paymentId, action: 'reject', reason })
    });
    const data = await response.json();

    if (data.status === 'success') {
        showAlert(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showAlert(data.message, 'danger');
    }
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>
