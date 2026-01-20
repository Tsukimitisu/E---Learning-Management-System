<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Record Payment";
$registrar_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;
$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_payment'])) {
    $student_id = (int)$_POST['student_id'];
    $or_number = clean_input($_POST['or_number']);
    $amount = (float)$_POST['amount'];
    $payment_type = clean_input($_POST['payment_type']);
    $payment_method = clean_input($_POST['payment_method']);
    $description = clean_input($_POST['description'] ?? '');
    $semester = clean_input($_POST['semester']);
    
    if ($student_id <= 0 || empty($or_number) || $amount <= 0) {
        $error = "Please fill in all required fields.";
    } else {
        $check = $conn->prepare("SELECT id FROM payments WHERE or_number = ?");
        $check->bind_param("s", $or_number);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "OR Number already exists. Please use a unique OR number.";
        } else {
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
            } else { $error = "Failed to record payment: " . $stmt->error; }
        }
    }
}

$students = $conn->query("
    SELECT s.user_id, s.student_no, CONCAT(up.first_name, ' ', up.last_name) as full_name, COALESCE(p.program_code, ss.strand_code) as program_code
    FROM students s INNER JOIN user_profiles up ON s.user_id = up.user_id LEFT JOIN programs p ON s.course_id = p.id LEFT JOIN shs_strands ss ON s.course_id = ss.id
    WHERE up.branch_id = $branch_id ORDER BY up.last_name, up.first_name
");

$recent_payments = $conn->query("
    SELECT p.*, s.student_no, CONCAT(up.first_name, ' ', up.last_name) as student_name
    FROM payments p INNER JOIN students s ON p.student_id = s.user_id INNER JOIN user_profiles up ON s.user_id = up.user_id
    WHERE p.recorded_by = $registrar_id AND p.branch_id = $branch_id ORDER BY p.created_at DESC LIMIT 20
");

$today_total = $conn->query("
    SELECT SUM(amount) as total, COUNT(*) as count FROM payments WHERE recorded_by = $registrar_id AND DATE(created_at) = CURDATE()
")->fetch_assoc();

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- ICON VISIBILITY FIXES --- */
    .icon-circle-bg {
        width: 50px; height: 50px; border-radius: 50%;
        background-color: var(--white); /* White background */
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    /* Fixed icon color inside the white circle */
    .icon-circle-bg i { color: var(--blue) !important; }

    .payment-form-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }
    .card-header-maroon { background: var(--maroon); color: white !important; padding: 15px 25px; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }
    .card-header-maroon i { color: white !important; } /* Force header icon white */

    .stat-bar {
        background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%);
        border-radius: 15px; padding: 25px; color: white; margin-bottom: 25px;
        display: flex; align-items: center; justify-content: space-between;
    }

    .table-modern thead th { 
        background: #fcfcfc; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #888; 
        padding: 12px 15px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 12px 15px; vertical-align: middle; font-size: 0.85rem; border-bottom: 1px solid #f1f1f1; }

    .btn-maroon-save {
        background-color: var(--maroon); color: white !important; border: none; border-radius: 10px;
        font-weight: 700; padding: 14px; transition: 0.3s; width: 100%;
    }
    .btn-maroon-save i { color: white !important; }
    .btn-maroon-save:hover { background-color: #600000; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(128,0,0,0.2); }

    .form-label { font-size: 0.75rem; font-weight: 800; color: #555; text-transform: uppercase; letter-spacing: 0.5px; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } .row > div { width: 100%; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-cash-coin me-2 text-maroon"></i>Payment Collection</h4>
            <p class="text-muted small mb-0"><?php echo htmlspecialchars($branch['name'] ?? 'Main Campus'); ?> • A.Y. <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
                <i class="bi bi-clock-history me-1 text-maroon"></i> Cashier Session Active
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Today's Collection Summary (Icon Fixed) -->
    <div class="stat-bar animate__animated animate__fadeIn">
        <div class="d-flex align-items-center">
            <div class="icon-circle-bg me-3">
                <i class="bi bi-graph-up-arrow fs-4"></i> <!-- Icon is now Blue inside white circle -->
            </div>
            <div>
                <h6 class="mb-0 small fw-bold opacity-75 text-uppercase">Today's Collected Total</h6>
                <h3 class="mb-0 fw-bold">₱<?php echo number_format($today_total['total'] ?? 0, 2); ?></h3>
            </div>
        </div>
        <div class="text-end d-none d-md-block">
            <span class="badge bg-dark text-blue fw-bold px-3 py-2 rounded-pill shadow-sm"><?php echo $today_total['count'] ?? 0; ?> Transaction(s) recorded</span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 animate__animated animate__headShake">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4 animate__animated animate__shakeX">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Entry Form -->
        <div class="col-lg-5 animate__animated animate__fadeInLeft">
            <div class="payment-form-card">
                <div class="card-header-maroon"><i class="bi bi-plus-circle me-2"></i>New Transaction Entry</div>
                <div class="p-4">
                    <form method="POST" id="paymentForm">
                        <div class="mb-4">
                            <label class="form-label">Search Student *</label>
                            <select class="form-select shadow-sm border-light" name="student_id" id="studentSelect" required>
                                <option value="">-- Select Student Account --</option>
                                <?php while ($s = $students->fetch_assoc()): ?>
                                <option value="<?php echo $s['user_id']; ?>">
                                    <?php echo htmlspecialchars($s['student_no'] . ' - ' . $s['full_name'] . ' (' . ($s['program_code'] ?? 'N/A') . ')'); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">OR Number *</label>
                                <input type="text" class="form-control shadow-sm border-light" name="or_number" required placeholder="2024-00XXX">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Amount (₱) *</label>
                                <input type="number" class="form-control shadow-sm border-light fw-bold text-success" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Payment Type *</label>
                                <input type="text" class="form-control shadow-sm border-light" name="payment_type" required placeholder="Tuition, Misc, etc.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Method *</label>
                                <select class="form-select shadow-sm border-light" name="payment_method" required>
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="online">Online Payment</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Academic Term *</label>
                            <select class="form-select shadow-sm border-light" name="semester" required>
                                <option value="1st">1st Semester</option>
                                <option value="2nd">2nd Semester</option>
                                <option value="summer">Summer</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control shadow-sm border-light" name="description" rows="2" placeholder="Details about this payment..."></textarea>
                        </div>
                        
                        <button type="submit" name="record_payment" class="btn-maroon-save shadow-sm">
                            <i class="bi bi-check2-circle me-2"></i> Complete Transaction
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History Sidebar -->
        <div class="col-lg-7 animate__animated animate__fadeInRight">
            <div class="main-card-modern">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history me-2 text-blue"></i>My Recent Recordings</span>
                    <a href="payment_history.php" class="btn btn-sm btn-light border fw-bold text-blue px-3" style="font-size:0.7rem;">VIEW ALL</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>OR & REF</th>
                                <th>Student</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_payments->num_rows == 0): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted small">No transactions processed in this session.</td></tr>
                            <?php else: while ($p = $recent_payments->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['or_number'] ?? '-'); ?></div>
                                    <small class="text-muted" style="font-size:0.7rem;"><?php echo $p['reference_no']; ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold text-blue small"><?php echo htmlspecialchars($p['student_name']); ?></div>
                                    <small class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($p['student_no']); ?></small>
                                </td>
                                <td class="text-end fw-bold text-success small">₱<?php echo number_format($p['amount'], 2); ?></td>
                                <td class="text-center">
                                    <small class="text-muted d-block"><?php echo date('M d', strtotime($p['created_at'])); ?></small>
                                    <small class="text-muted opacity-75" style="font-size:0.7rem;"><?php echo date('h:i A', strtotime($p['created_at'])); ?></small>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- SCRIPTS --- -->
<script>
$(document).ready(function() {
    $('#studentSelect').select2({ width: '100%', placeholder: "Search Student Name or ID" });

    $('#paymentForm').on('submit', function(e) {
        const amount = parseFloat($('input[name="amount"]').val());
        if (amount <= 0 || isNaN(amount)) {
            e.preventDefault();
            Swal.fire({
                title: 'Invalid Entry',
                text: 'Transaction amount must be greater than ₱0.00',
                icon: 'error',
                confirmButtonColor: '#800000'
            });
        }
    });
});
</script>
</body>
</html>