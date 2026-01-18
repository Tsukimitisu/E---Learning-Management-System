<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Record Payment";
$registrar_id = $_SESSION['user_id'];

// Get registrar's branch
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;

// Get branch info
$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$message = '';
$error = '';

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_payment'])) {
    $student_id = (int)$_POST['student_id'];
    $or_number = clean_input($_POST['or_number']);
    $amount = (float)$_POST['amount'];
    $payment_type = clean_input($_POST['payment_type']);
    $payment_method = clean_input($_POST['payment_method']);
    $description = clean_input($_POST['description'] ?? '');
    $semester = clean_input($_POST['semester']);
    
    // Validation
    if ($student_id <= 0 || empty($or_number) || $amount <= 0) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if OR number already exists
        $check = $conn->prepare("SELECT id FROM payments WHERE or_number = ?");
        $check->bind_param("s", $or_number);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "OR Number already exists. Please use a unique OR number.";
        } else {
            // Generate reference number
            $reference_no = 'PAY-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                INSERT INTO payments (reference_no, or_number, student_id, amount, payment_type, description, 
                                      academic_year_id, semester, branch_id, recorded_by, payment_method, status, verified_by, verified_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified', ?, NOW())
            ");
            $stmt->bind_param("ssidssisisis", 
                $reference_no, $or_number, $student_id, $amount, $payment_type, $description,
                $current_ay_id, $semester, $branch_id, $registrar_id, $payment_method, $registrar_id
            );
            
            if ($stmt->execute()) {
                $message = "Payment recorded successfully! Reference: $reference_no, OR: $or_number";
            } else {
                $error = "Failed to record payment: " . $stmt->error;
            }
        }
    }
}

// Get students for this branch (for dropdown)
$students = $conn->query("
    SELECT s.user_id, s.student_no, 
           CONCAT(up.first_name, ' ', up.last_name) as full_name,
           COALESCE(p.program_code, ss.strand_code) as program_code
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id
    WHERE up.branch_id = $branch_id
    ORDER BY up.last_name, up.first_name
");

// Get recent payments recorded by this registrar
$recent_payments = $conn->query("
    SELECT p.*, s.student_no, 
           CONCAT(up.first_name, ' ', up.last_name) as student_name
    FROM payments p
    INNER JOIN students s ON p.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    WHERE p.recorded_by = $registrar_id AND p.branch_id = $branch_id
    ORDER BY p.created_at DESC
    LIMIT 20
");

// Get today's total
$today_total = $conn->query("
    SELECT SUM(amount) as total, COUNT(*) as count 
    FROM payments 
    WHERE recorded_by = $registrar_id AND DATE(created_at) = CURDATE()
")->fetch_assoc();

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-receipt me-2"></i>Record Payment</h4>
                <small class="text-muted">Branch: <?php echo htmlspecialchars($branch['name'] ?? 'Unknown'); ?> | A.Y. <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></small>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Payment Form -->
            <div class="col-lg-5 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>New Payment Transaction</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="paymentForm">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Student <span class="text-danger">*</span></label>
                                <select class="form-select" name="student_id" id="studentSelect" required>
                                    <option value="">-- Select Student --</option>
                                    <?php while ($s = $students->fetch_assoc()): ?>
                                    <option value="<?php echo $s['user_id']; ?>">
                                        <?php echo htmlspecialchars($s['student_no'] . ' - ' . $s['full_name'] . ' (' . ($s['program_code'] ?? 'N/A') . ')'); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">OR Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="or_number" required placeholder="e.g., 2024-00001">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Amount (₱) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Payment Type <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="payment_type" required placeholder="e.g., Tuition Fee, Registration, Misc Fee">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="cash">Cash</option>
                                        <option value="check">Check</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="online">Online Payment</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Semester <span class="text-danger">*</span></label>
                                <select class="form-select" name="semester" required>
                                    <option value="1st">1st Semester</option>
                                    <option value="2nd">2nd Semester</option>
                                    <option value="summer">Summer</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description/Notes</label>
                                <textarea class="form-control" name="description" rows="2" placeholder="Additional details (optional)"></textarea>
                            </div>
                            
                            <button type="submit" name="record_payment" class="btn btn-primary w-100">
                                <i class="bi bi-check-circle me-1"></i> Record Payment
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Today's Summary -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Today's Collection</h6>
                        <h3 class="text-success fw-bold mb-1">₱<?php echo number_format($today_total['total'] ?? 0, 2); ?></h3>
                        <small class="text-muted"><?php echo $today_total['count'] ?? 0; ?> transaction(s)</small>
                    </div>
                </div>
            </div>
            
            <!-- Recent Transactions -->
            <div class="col-lg-7 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Recent Transactions</h5>
                        <a href="payments.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($recent_payments->num_rows == 0): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-receipt display-4"></i>
                            <p class="mt-2 mb-0">No transactions yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>OR #</th>
                                        <th>Student</th>
                                        <th>Type</th>
                                        <th class="text-end">Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($p = $recent_payments->fetch_assoc()): 
                                        $type_colors = [
                                            'tuition' => 'primary',
                                            'miscellaneous' => 'secondary',
                                            'laboratory' => 'info',
                                            'library' => 'warning',
                                            'registration' => 'success',
                                            'id_card' => 'dark',
                                            'diploma' => 'danger',
                                            'transcript' => 'primary',
                                            'clearance' => 'success',
                                            'other' => 'secondary'
                                        ];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($p['or_number'] ?? '-'); ?></strong>
                                            <br><small class="text-muted"><?php echo $p['reference_no']; ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($p['student_no']); ?></small>
                                            <br><?php echo htmlspecialchars($p['student_name']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $type_colors[$p['payment_type']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $p['payment_type'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold">₱<?php echo number_format($p['amount'], 2); ?></td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($p['created_at'])); ?></small>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($p['created_at'])); ?></small>
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
    </div>
</div>

<script>
// Auto-focus on student select
document.getElementById('studentSelect').focus();

// Form validation
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const amount = parseFloat(document.querySelector('input[name="amount"]').value);
    if (amount <= 0) {
        e.preventDefault();
        alert('Please enter a valid amount greater than 0.');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
