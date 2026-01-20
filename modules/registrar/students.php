<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Management";

// ==========================================
// BACKEND LOGIC - ABSOLUTELY UNTOUCHED
// ==========================================

// Statistics
$stats = [
    'total_students' => 0,
    'pending_accounts' => 0,
    'active_students' => 0,
    'new_today' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM students");
if ($row = $result->fetch_assoc()) {
    $stats['total_students'] = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM users u INNER JOIN students s ON u.id = s.user_id WHERE u.status = 'inactive'");
if ($row = $result->fetch_assoc()) {
    $stats['pending_accounts'] = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM users u INNER JOIN students s ON u.id = s.user_id WHERE u.status = 'active'");
if ($row = $result->fetch_assoc()) {
    $stats['active_students'] = $row['count'] ?? 0;
}

$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM users u INNER JOIN students s ON u.id = s.user_id WHERE DATE(u.created_at) = '$today'");
if ($row = $result->fetch_assoc()) {
    $stats['new_today'] = $row['count'] ?? 0;
}

// Get registrar's branch
$registrar_id = $_SESSION['user_id'];
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;

// Build branch condition
$branch_condition = $branch_id > 0 ? "up.branch_id = $branch_id" : "1=1";

// Student List with balance calculation
$students_query = "
    SELECT 
        s.user_id, s.student_no, s.course_id,
        CONCAT(up.first_name, ' ', up.last_name) as full_name,
        u.email, u.status,
        COALESCE(p.program_code, ss.strand_code) as course_code, 
        COALESCE(p.program_name, ss.strand_name) as course_title,
        COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.user_id AND status = 'verified'), 0) as total_paid,
        COALESCE((SELECT SUM(amount) FROM student_fees WHERE student_id = s.user_id), 0) as total_fees,
        COALESCE((SELECT SUM(amount) FROM student_fees WHERE student_id = s.user_id), 0) - 
        COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.user_id AND status = 'verified'), 0) as balance
    FROM students s
    INNER JOIN users u ON s.user_id = u.id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id AND p.id IS NULL
    WHERE $branch_condition
    ORDER BY s.student_no DESC
";
$students_result = $conn->query($students_query);

$student_no_preview = generate_student_number($conn);

// Fetch College programs
$programs_result = $conn->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1 ORDER BY program_code");

// Fetch SHS strands
$strands_result = $conn->query("SELECT id, strand_code, strand_name FROM shs_strands WHERE is_active = 1 ORDER BY strand_name");

$program_year_levels_result = $conn->query("SELECT id, program_id, year_name FROM program_year_levels WHERE is_active = 1 ORDER BY program_id, year_level");
$program_year_levels = [];
while ($row = $program_year_levels_result->fetch_assoc()) {
    $program_year_levels[$row['program_id']][] = $row;
}

$shs_grade_levels_result = $conn->query("SELECT id, strand_id, grade_name FROM shs_grade_levels WHERE is_active = 1 ORDER BY strand_id, grade_level");
$shs_grade_levels = [];
while ($row = $shs_grade_levels_result->fetch_assoc()) {
    $shs_grade_levels[$row['strand_id']][] = $row;
}

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- UI COMPONENTS --- */
    .stat-card-modern {
        background: white; border-radius: 15px; padding: 20px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; transition: 0.3s;
    }
    .stat-card-modern:hover { transform: translateY(-5px); }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }

    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 12px 15px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    .btn-maroon-pill { background-color: var(--maroon); color: white; border: none; border-radius: 50px; font-weight: 700; padding: 8px 25px; transition: 0.3s; }
    .btn-maroon-pill:hover { background-color: #600000; color: white; transform: translateY(-2px); }

    .action-btn-circle { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s; border: 1px solid #eee; background: white; }
    .action-btn-circle:hover { transform: scale(1.1); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

    .search-filter-box { background: white; border-radius: 15px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 20px; }
    .modern-input { border-radius: 50px; border: 1px solid #ddd; padding-left: 15px; font-size: 0.85rem; }

    .info-label { font-size: 0.6rem; font-weight: 800; color: #888; text-transform: uppercase; letter-spacing: 1px; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-people-fill me-2 text-maroon"></i>Student Management</h4>
            <p class="text-muted small mb-0">Manage student registry, academic assignments, and financial records</p>
        </div>
        <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="bi bi-person-plus me-1"></i> Add New Student
        </button>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Stats Row -->
    <div class="row g-3 mb-4 animate__animated animate__fadeIn">
        <div class="col-md-3">
            <div class="stat-card-modern border-start border-primary border-5">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded"><i class="bi bi-people fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['total_students']); ?></h4><small class="text-muted">Total Students</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-modern border-start border-warning border-5">
                <div class="p-2 bg-warning bg-opacity-10 text-warning rounded"><i class="bi bi-hourglass-split fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['pending_accounts']); ?></h4><small class="text-muted">Inactive Accounts</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-modern border-start border-success border-5">
                <div class="p-2 bg-success bg-opacity-10 text-success rounded"><i class="bi bi-check-circle fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['active_students']); ?></h4><small class="text-muted">Active Students</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-modern border-start border-info border-5">
                <div class="p-2 bg-info bg-opacity-10 text-info rounded"><i class="bi bi-calendar-plus fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['new_today']); ?></h4><small class="text-muted">Registered Today</small></div>
            </div>
        </div>
    </div>

    <div id="alertContainer"></div>

    <!-- Filter Card -->
    <div class="search-filter-box animate__animated animate__fadeIn">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control modern-input shadow-sm" placeholder="Search by name or student number...">
            </div>
            <div class="col-md-4">
                <select id="programFilter" class="form-select modern-input shadow-sm">
                    <option value="">All Programs/Strands</option>
                    <?php $programs_result->data_seek(0); while ($p = $programs_result->fetch_assoc()): ?>
                        <option value="program-<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['program_code'].' - '.$p['program_name']); ?></option>
                    <?php endwhile; ?>
                    <?php $strands_result->data_seek(0); while ($s = $strands_result->fetch_assoc()): ?>
                        <option value="strand-<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['strand_code'].' - '.$s['strand_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select id="statusFilter" class="form-select modern-input shadow-sm">
                    <option value="">Filter by Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Main Student Registry Table -->
    <div class="main-card-modern animate__animated animate__fadeInUp">
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0" id="studentsTable">
                <thead>
                    <tr>
                        <th class="ps-4">Student ID</th>
                        <th>Full Name</th>
                        <th>Program/Course</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Verified Paid</th>
                        <th class="text-end">Balance</th>
                        <th class="text-center pe-4">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students_result->fetch_assoc()):
                        $balance = (float)$student['balance'];
                        $b_clr = $balance > 0 ? 'danger' : ($balance < 0 ? 'warning' : 'success');
                    ?>
                    <tr data-name="<?php echo htmlspecialchars(strtolower($student['full_name'])); ?>"
                        data-student-no="<?php echo htmlspecialchars(strtolower($student['student_no'])); ?>"
                        data-program="<?php echo htmlspecialchars($student['course_id'] ? 'program-'.$student['course_id'] : 'strand-'.$student['course_id']); ?>"
                        data-status="<?php echo htmlspecialchars($student['status']); ?>">
                        <td class="ps-4 fw-bold text-maroon small"><?php echo htmlspecialchars($student['student_no']); ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['full_name']); ?></div>
                            <small class="text-muted small"><?php echo htmlspecialchars($student['email']); ?></small>
                        </td>
                        <td><small class="text-muted fw-bold"><?php echo htmlspecialchars($student['course_code'] ? ($student['course_code'].' - '.$student['course_title']) : 'Unassigned'); ?></small></td>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?> px-3">
                                <?php echo strtoupper($student['status']); ?>
                            </span>
                        </td>
                        <td class="text-end text-success fw-bold">₱<?php echo number_format($student['total_paid'], 2); ?></td>
                        <td class="text-end">
                            <span class="badge bg-<?php echo $b_clr; ?> bg-opacity-10 text-<?php echo $b_clr; ?> border border-<?php echo $b_clr; ?> border-opacity-25 px-3">
                                ₱<?php echo number_format(abs($balance), 2); ?><?php echo $balance < 0 ? ' (Credit)' : ''; ?>
                            </span>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-flex justify-content-center gap-1">
                                <button class="action-btn-circle text-primary" onclick="viewPaymentHistory(<?php echo $student['user_id']; ?>, '<?php echo $student['student_no']; ?>', '<?php echo addslashes($student['full_name']); ?>')" title="History"><i class="bi bi-clock-history"></i></button>
                                <button class="action-btn-circle text-success" onclick="openPaymentModal(<?php echo $student['user_id']; ?>, '<?php echo $student['student_no']; ?>', '<?php echo addslashes($student['full_name']); ?>', <?php echo $student['total_fees']; ?>, <?php echo $student['total_paid']; ?>)" title="Payment"><i class="bi bi-cash-coin"></i></button>
                                <button class="action-btn-circle text-warning" onclick="openEditStudent(this)" 
                                    data-student-id="<?php echo $student['user_id']; ?>" data-student-no="<?php echo $student['student_no']; ?>"
                                    data-full-name="<?php echo $student['full_name']; ?>" data-email="<?php echo $student['email']; ?>"
                                    data-status="<?php echo $student['status']; ?>"><i class="bi bi-pencil-square"></i></button>
                                <button class="action-btn-circle text-info" onclick="openAssessFeeModal(<?php echo $student['user_id']; ?>, '<?php echo $student['student_no']; ?>', '<?php echo addslashes($student['full_name']); ?>')" title="Assess Fees"><i class="bi bi-calculator"></i></button>
                                <button class="action-btn-circle <?php echo $student['status'] === 'active' ? 'text-secondary' : 'text-success'; ?>" onclick="toggleStudentStatus(<?php echo $student['user_id']; ?>, '<?php echo $student['status']; ?>')">
                                    <i class="bi bi-<?php echo $student['status'] === 'active' ? 'pause-circle' : 'play-circle'; ?>"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================
     MODALS - UI UPDATED / LOGIC UNTOUCHED
     ========================================== -->

<!-- Modal: Add Student -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--maroon); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>New Student Enrollment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStudentForm">
                <div class="modal-body p-4 bg-light">
                    <h6 class="text-blue fw-bold mb-3 text-uppercase small">Personal Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><label class="form-label small fw-bold">First Name *</label><input type="text" class="form-control border-light shadow-sm" name="first_name" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Last Name *</label><input type="text" class="form-control border-light shadow-sm" name="last_name" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Email Address *</label><input type="email" class="form-control border-light shadow-sm" name="email" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Contact Number</label><input type="text" class="form-control border-light shadow-sm" name="contact_no"></div>
                        <div class="col-12"><label class="form-label small fw-bold">Current Address</label><textarea class="form-control border-light shadow-sm" name="address" rows="2"></textarea></div>
                    </div>

                    <h6 class="text-blue fw-bold mb-3 text-uppercase small">Academic Assignment</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><label class="form-label small fw-bold">Level Type *</label><select class="form-select border-light shadow-sm" name="program_type" id="program_type" required><option value="college">College</option><option value="shs">SHS</option></select></div>
                        <div class="col-md-4" id="collegeProgramCol"><label class="form-label small fw-bold">Program *</label><select class="form-select border-light shadow-sm" name="course_id" id="course_id">
                            <option value="">-- Choose Program --</option>
                            <?php $programs_result->data_seek(0); while ($p = $programs_result->fetch_assoc()): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['program_code'].' - '.$p['program_name']); ?></option><?php endwhile; ?>
                        </select></div>
                        <div class="col-md-4" id="shsStrandCol" style="display:none;"><label class="form-label small fw-bold">SHS Strand *</label><select class="form-select border-light shadow-sm" name="shs_strand_id" id="shs_strand_id">
                            <option value="">-- Choose Strand --</option>
                            <?php $strands_result->data_seek(0); while ($s = $strands_result->fetch_assoc()): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['strand_code'].' - '.$s['strand_name']); ?></option><?php endwhile; ?>
                        </select></div>
                        <div class="col-md-4"><label class="form-label small fw-bold">Year Level</label><select class="form-select border-light shadow-sm" name="year_level_id" id="year_level_id">
                            <option value="">-- Select Level --</option>
                            <?php foreach ($program_year_levels as $pid => $levels): foreach ($levels as $l): ?><option value="<?php echo $l['id']; ?>" data-program-id="<?php echo $pid; ?>"><?php echo htmlspecialchars($l['year_name']); ?></option><?php endforeach; endforeach; ?>
                            <?php foreach ($shs_grade_levels as $sid => $levels): foreach ($levels as $l): ?><option value="<?php echo $l['id']; ?>" data-strand-id="<?php echo $sid; ?>"><?php echo htmlspecialchars($l['grade_name']); ?></option><?php endforeach; endforeach; ?>
                        </select></div>
                    </div>

                    <h6 class="text-blue fw-bold mb-3 text-uppercase small">Account Setup</h6>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label small fw-bold">Student No. (Preview)</label><input type="text" class="form-control bg-light" value="<?php echo $student_no_preview; ?>" readonly></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Temporary Password</label><input type="text" class="form-control border-light shadow-sm" name="password" value="student123" required></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon-pill px-4">Register Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Student -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue);">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Student Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStudentForm">
                <input type="hidden" name="student_id" id="edit_student_id">
                <div class="modal-body p-4 bg-light">
                    <div class="mb-3"><label class="form-label small fw-bold text-muted">FULL NAME</label><input type="text" class="form-control bg-white" id="edit_full_name" readonly></div>
                    <div class="mb-3"><label class="form-label small fw-bold">UPDATE EMAIL</label><input type="email" class="form-control border-light" name="email" id="edit_email" required></div>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label small fw-bold text-muted">STUDENT NO.</label><input type="text" class="form-control bg-white" id="edit_student_no" readonly></div>
                        <div class="col-6"><label class="form-label small fw-bold">ACCOUNT STATUS</label><select class="form-select border-light" name="status" id="edit_status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4"><button type="submit" class="btn btn-primary w-100 fw-bold">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Payment -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: #28a745; border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i>Record New Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPaymentForm">
                <input type="hidden" name="student_id" id="payment_student_id">
                <div class="modal-body p-4 bg-light">
                    <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-25 small mb-4" id="payment_student_info"></div>
                    <div class="row g-4 text-center mb-4 border-bottom pb-3">
                        <div class="col-4"><label class="info-label">Assessed</label><div class="fw-bold text-primary" id="payment_total_fees">₱0.00</div></div>
                        <div class="col-4"><label class="info-label">Paid</label><div class="fw-bold text-success" id="payment_total_paid">₱0.00</div></div>
                        <div class="col-4"><label class="info-label">Balance</label><div class="fw-bold text-danger" id="payment_balance">₱0.00</div></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label small fw-bold">OR Number *</label><input type="text" class="form-control" name="or_number" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Amount (₱) *</label><input type="number" class="form-control" name="amount" step="0.01" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label small fw-bold">Payment Type *</label><input type="text" class="form-control" name="payment_type" required placeholder="Tuition, Misc, Laboratory, etc."></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label small fw-bold">Method *</label><select class="form-select" name="payment_method" required><option value="cash">Cash</option><option value="check">Check</option><option value="bank_transfer">Bank Transfer</option><option value="online">Online</option></select></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Term *</label><select class="form-select" name="semester" required><option value="1st">1st Semester</option><option value="2nd">2nd Semester</option><option value="summer">Summer</option></select></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4"><button type="submit" class="btn btn-success w-100 fw-bold">Confirm Transaction</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Payment History -->
<div class="modal fade" id="paymentHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue);">
                <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>Financial Transcript</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-light p-4 border-bottom text-center">
                    <span class="badge bg-white text-dark border p-2 px-3 fw-bold mb-3" id="history_student_info"></span>
                    <div class="row g-4"><div class="col-4"><label class="info-label">Fees</label><div id="history_total_fees" class="fw-bold">₱0.00</div></div><div class="col-4"><label class="info-label">Verified</label><div id="history_total_paid" class="fw-bold text-success">₱0.00</div></div><div class="col-4"><label class="info-label">Statement</label><div id="history_balance" class="fw-bold">₱0.00</div></div></div>
                </div>
                <div class="p-4">
                    <ul class="nav nav-pills mb-3"><li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#pTab">Payments Ledger</a></li><li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#fTab">Assessed Fees</a></li></ul>
                    <div class="tab-content"><div class="tab-pane fade show active" id="pTab"><table class="table table-sm small table-modern"><thead><tr><th>Date</th><th>OR#</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead><tbody id="payments_list"></tbody></table></div><div class="tab-pane fade" id="fTab"><table class="table table-sm small table-modern"><thead><tr><th>Date</th><th>Fee Type</th><th>Term</th><th>Amount</th></tr></thead><tbody id="fees_list"></tbody></table></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Assess Fee -->
<div class="modal fade" id="assessFeeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue);">
                <h5 class="modal-title fw-bold"><i class="bi bi-calculator me-2"></i>Assess Student Fee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assessFeeForm">
                <input type="hidden" name="student_id" id="assess_student_id">
                <div class="modal-body p-4 bg-light">
                    <div class="alert bg-white border border-light small mb-4" id="assess_student_info"></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Fee Description *</label><input type="text" class="form-control" name="fee_type" required placeholder="Tuition, Laboratory, Entrance, etc."></div>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label small fw-bold">Amount (₱) *</label><input type="number" class="form-control" name="amount" step="0.01" required></div>
                        <div class="col-6"><label class="form-label small fw-bold">Term *</label><select class="form-select" name="semester" required><option value="1st">1st Semester</option><option value="2nd">2nd Semester</option><option value="summer">Summer</option></select></div>
                    </div>
                    <div class="mt-3"><label class="form-label small fw-bold">Optional Details</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                </div>
                <div class="modal-footer border-0 p-4"><button type="submit" class="btn btn-primary w-100 fw-bold">Add Assessment</button></div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- ==========================================
     JAVASCRIPT - UNTOUCHED LOGIC & RE-WIRED
     ========================================== -->
<script>
/** 1. CREATE STUDENT */
document.getElementById('addStudentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
    try {
        const response = await fetch('process/create_student.php', { method: 'POST', body: new FormData(e.target) });
        const data = await response.json();
        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('addStudentModal')).hide();
            showAlert('✅ ' + data.message + `<div class="card mt-3 border-primary"><div class="card-header bg-primary text-white small">CREDENTIALS GENERATED</div><div class="card-body bg-light small"><strong>No:</strong> <code id="c_no">${data.student_no}</code><br><strong>Email:</strong> <code id="c_em">${data.credentials.email}</code><br><strong>Pass:</strong> <code id="c_pw">${data.credentials.password}</code><div class="mt-2"><button onclick="copyGenerated()" class="btn btn-sm btn-outline-primary">Copy All</button> <button onclick="location.reload()" class="btn btn-sm btn-success">Done</button></div></div></div>`, 'success');
        } else { showAlert(data.message, 'danger'); btn.disabled = false; btn.innerHTML = original; }
    } catch (error) { showAlert('Critical failure during creation.', 'danger'); btn.disabled = false; btn.innerHTML = original; }
});

/** 2. RECORD PAYMENT */
document.getElementById('addPaymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    try {
        const response = await fetch('process/record_payment.php', { method: 'POST', body: new FormData(e.target) });
        const data = await response.json();
        if (data.success) { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); } 
        else { showAlert(data.message, 'danger'); }
    } catch (e) { showAlert('Financial sync failed.', 'danger'); }
});

/** 3. ASSESS FEE */
document.getElementById('assessFeeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    try {
        const r = await fetch('process/assess_fee.php', { method: 'POST', body: new FormData(e.target) });
        const d = await r.json();
        if (d.success) { showAlert(d.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(d.message, 'danger'); }
    } catch (e) { showAlert('Logic error.', 'danger'); }
});

/** 4. FETCH HISTORY */
async function viewPaymentHistory(studentId, studentNo, fullName) {
    document.getElementById('history_student_info').textContent = studentNo + ' - ' + fullName;
    document.getElementById('payments_list').innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    new bootstrap.Modal(document.getElementById('paymentHistoryModal')).show();
    try {
        const r = await fetch(`process/get_payment_history.php?student_id=${studentId}`);
        const d = await r.json();
        if (d.success) {
            document.getElementById('history_total_fees').textContent = '₱' + parseFloat(d.total_fees).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('history_total_paid').textContent = '₱' + parseFloat(d.total_paid).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('history_balance').textContent = '₱' + Math.abs(d.total_fees - d.total_paid).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('payments_list').innerHTML = d.payments.map(p => `<tr><td>${new Date(p.created_at).toLocaleDateString()}</td><td>${p.or_number || '-'}</td><td>${p.payment_type}</td><td class="fw-bold">₱${parseFloat(p.amount).toLocaleString()}</td><td><span class="badge bg-${p.status === 'verified' ? 'success' : 'warning'}">${p.status}</span></td></tr>`).join('');
            document.getElementById('fees_list').innerHTML = d.fees.map(f => `<tr><td>${new Date(f.created_at).toLocaleDateString()}</td><td>${f.fee_type}</td><td>${f.semester}</td><td class="fw-bold">₱${parseFloat(f.amount).toLocaleString()}</td></tr>`).join('');
        }
    } catch (e) { document.getElementById('payments_list').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading ledger.</td></tr>'; }
}

/** 5. FILTERS */
function applyFilters() {
    const s = document.getElementById('searchInput').value.toLowerCase();
    const p = document.getElementById('programFilter').value;
    const st = document.getElementById('statusFilter').value;
    document.querySelectorAll('#studentsTable tbody tr').forEach(row => {
        const matchesS = row.dataset.name.includes(s) || row.dataset.studentNo.includes(s);
        const matchesP = !p || row.dataset.program === p;
        const matchesSt = !st || row.dataset.status === st;
        row.style.display = (matchesS && matchesP && matchesSt) ? '' : 'none';
    });
}
['searchInput', 'programFilter', 'statusFilter'].forEach(id => document.getElementById(id).addEventListener('input', applyFilters));

/** 6. UI HELPERS */
function openPaymentModal(sid, sno, name, fees, paid) {
    document.getElementById('payment_student_id').value = sid;
    document.getElementById('payment_student_info').textContent = sno + ' - ' + name;
    document.getElementById('payment_total_fees').textContent = '₱' + fees.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('payment_total_paid').textContent = '₱' + paid.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('payment_balance').textContent = '₱' + (fees - paid).toLocaleString('en-US', {minimumFractionDigits: 2});
    new bootstrap.Modal(document.getElementById('addPaymentModal')).show();
}

function openAssessFeeModal(sid, sno, name) {
    document.getElementById('assess_student_id').value = sid;
    document.getElementById('assess_student_info').textContent = sno + ' - ' + name;
    new bootstrap.Modal(document.getElementById('assessFeeModal')).show();
}

function openEditStudent(button) {
    document.getElementById('edit_student_id').value = button.dataset.studentId;
    document.getElementById('edit_full_name').value = button.dataset.fullName;
    document.getElementById('edit_email').value = button.dataset.email;
    document.getElementById('edit_student_no').value = button.dataset.studentNo;
    document.getElementById('edit_status').value = button.dataset.status;
    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
}

function copyGenerated() {
    const t = `ID: ${document.getElementById('c_no').innerText}\nEmail: ${document.getElementById('c_em').innerText}\nPass: ${document.getElementById('c_pw').innerText}`;
    navigator.clipboard.writeText(t).then(() => alert('Credentials copied!'));
}

function showAlert(m, t) {
    document.getElementById('alertContainer').innerHTML = `<div class="alert alert-${t} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert">${m}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}

document.getElementById('program_type').addEventListener('change', function() {
    const isCol = this.value === 'college';
    document.getElementById('collegeProgramCol').style.display = isCol ? 'block' : 'none';
    document.getElementById('shsStrandCol').style.display = isCol ? 'none' : 'block';
});
</script>
</body>
</html>