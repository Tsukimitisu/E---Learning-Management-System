<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Branch Reports";
$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Attendance Report Data
$attendance_report = $conn->query("
    SELECT cl.id as class_id, cl.section_name, s.subject_code, s.subject_title, COUNT(DISTINCT e.student_id) as total_enrolled, COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.student_id END) as total_present, ROUND((COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.student_id END) * 100.0) / NULLIF(COUNT(DISTINCT e.student_id), 0), 2) as attendance_rate
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    LEFT JOIN attendance a ON cl.id = a.class_id AND a.attendance_date BETWEEN '$start_date' AND '$end_date'
    WHERE cl.branch_id = $branch_id
    GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title
    ORDER BY s.subject_code, cl.section_name
");

// Academic Performance Report
$performance_report = $conn->query("
    SELECT cl.id as class_id, cl.section_name, s.subject_code, s.subject_title, COUNT(DISTINCT e.student_id) as total_students, AVG(g.final_grade) as avg_final_grade, COUNT(CASE WHEN g.remarks = 'PASSED' THEN 1 END) as passed_count, ROUND((COUNT(CASE WHEN g.remarks = 'PASSED' THEN 1 END) * 100.0) / NULLIF(COUNT(g.student_id), 0), 2) as pass_rate
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    LEFT JOIN grades g ON cl.id = g.class_id
    WHERE cl.branch_id = $branch_id
    GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title
    ORDER BY s.subject_code, cl.section_name
");

// Enrollment Statistics
$enrollment_stats = $conn->query("
    SELECT COUNT(DISTINCT cl.id) as total_classes, SUM(cl.max_capacity) as total_capacity, SUM(cl.current_enrolled) as total_enrolled, ROUND((SUM(cl.current_enrolled) * 100.0) / NULLIF(SUM(cl.max_capacity), 0), 2) as utilization_rate, COUNT(DISTINCT CASE WHEN cl.current_enrolled >= cl.max_capacity THEN cl.id END) as full_classes, COUNT(DISTINCT CASE WHEN cl.current_enrolled >= cl.max_capacity * 0.8 THEN cl.id END) as almost_full_classes
    FROM classes cl
    WHERE cl.branch_id = $branch_id
")->fetch_assoc();

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SHARED UI DESIGN SYSTEM --- */
    .page-header {
        background: white; padding: 20px; border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
    }

    .stat-card-modern {
        background: white; border-radius: 15px; padding: 20px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center;
        gap: 15px; transition: 0.3s; height: 100%; border-left: 5px solid transparent;
    }
    
    .content-card { background: white; border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; }
    
    .card-header-modern {
        padding: 15px 20px; border-bottom: 1px solid #eee;
        font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;
    }

    /* Table Styling */
    .table-modern thead th { 
        background: #f8f9fa; font-size: 0.7rem; text-transform: uppercase; 
        color: #888; padding: 15px 20px; border-bottom: 1px solid #eee;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; font-size: 0.85rem; }

    .btn-maroon { background-color: var(--maroon); color: white; font-weight: 700; border: none; }
    .btn-maroon:hover { background-color: #600000; color: white; transform: translateY(-1px); }

    .date-filter-group {
        background: #f1f3f5; border-radius: 10px; padding: 5px 15px; display: flex; align-items: center; gap: 10px;
    }
    .date-filter-group input { background: transparent; border: none; outline: none; font-size: 0.85rem; font-weight: 600; color: var(--blue); }

    .report-status { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER & FILTERS -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-file-earmark-bar-graph-fill me-2 text-maroon"></i>Branch Reports & Analytics
            </h4>
            <p class="text-muted small mb-0">Summary of academic performance and institutional occupancy.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <div class="date-filter-group">
                <i class="bi bi-calendar-range text-muted"></i>
                <input type="date" id="start_date" value="<?php echo $start_date; ?>">
                <span class="text-muted small fw-bold">TO</span>
                <input type="date" id="end_date" value="<?php echo $end_date; ?>">
            </div>
            <button class="btn btn-maroon btn-sm px-4 rounded-pill fw-bold shadow-sm" onclick="updateReports()">
                <i class="bi bi-arrow-clockwise me-1"></i> GENERATE
            </button>
        </div>
    </div>

    <div id="alertContainer"></div>

    <!-- 2. ENROLLMENT OVERVIEW (Balanced Cards) -->
    <div class="row g-4 mb-5">
        <div class="col-lg-2 col-md-4">
            <div class="stat-card-modern" style="border-left-color: var(--blue);">
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($enrollment_stats['total_classes'] ?? 0); ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Total Classes</small></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4">
            <div class="stat-card-modern" style="border-left-color: #28a745;">
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($enrollment_stats['total_enrolled'] ?? 0); ?> / <?php echo number_format($enrollment_stats['total_capacity'] ?? 0); ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Enrolled vs Capacity</small></div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4">
            <div class="stat-card-modern" style="border-left-color: #6f42c1;">
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($enrollment_stats['utilization_rate'] ?? 0, 1); ?>%</h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Utilization</small></div>
            </div>
        </div>
        <div class="col-lg-2 col-md-6">
            <div class="stat-card-modern" style="border-left-color: #dc3545;">
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($enrollment_stats['full_classes'] ?? 0); ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Full Classes</small></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <button class="btn btn-outline-primary w-100 h-100 rounded-4 fw-bold" onclick="exportReport('enrollment')">
                <i class="bi bi-file-earmark-arrow-down-fill me-2"></i>EXPORT STATS
            </button>
        </div>
    </div>

    <!-- 3. ATTENDANCE REPORT TABLE -->
    <div class="content-card animate__animated animate__fadeInUp">
        <div class="card-header-modern bg-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-calendar-check me-2 text-maroon"></i>Attendance Report Summary</span>
            <button class="btn btn-sm btn-light border px-3 fw-bold" style="font-size: 0.65rem;" onclick="exportReport('attendance')">EXPORT CSV</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern mb-0">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Subject Title</th>
                        <th class="text-center">Enrolled</th>
                        <th class="text-center">Present</th>
                        <th class="text-center">Attendance Rate</th>
                        <th class="text-end">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $attendance_report->fetch_assoc()): 
                        $rate = $row['attendance_rate'] ?? 0;
                        $color = ($rate >= 80) ? 'success' : (($rate >= 60) ? 'warning' : 'danger');
                    ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['section_name'] ?? 'N/A'); ?></td>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($row['subject_code']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($row['subject_title']); ?></small>
                        </td>
                        <td class="text-center"><?php echo number_format($row['total_enrolled']); ?></td>
                        <td class="text-center"><?php echo number_format($row['total_present']); ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> px-3 py-2 fw-bold">
                                <?php echo number_format($rate, 1); ?>%
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="report-status text-<?php echo $color; ?>">
                                <?php echo ($rate >= 80) ? 'Excellent' : (($rate >= 60) ? 'Good' : 'Critical'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. ACADEMIC PERFORMANCE TABLE -->
    <div class="content-card animate__animated animate__fadeInUp">
        <div class="card-header-modern bg-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-award me-2 text-blue"></i>Academic Performance Analytics</span>
            <button class="btn btn-sm btn-light border px-3 fw-bold" style="font-size: 0.65rem;" onclick="exportReport('performance')">EXPORT CSV</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern mb-0">
                <thead>
                    <tr>
                        <th>Class Section</th>
                        <th>Subject</th>
                        <th class="text-center">Population</th>
                        <th class="text-center">Avg Grade</th>
                        <th class="text-center">Passed</th>
                        <th class="text-center">Pass Rate</th>
                        <th class="text-end">Assessment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $performance_report->fetch_assoc()): 
                        $pass_rate = $row['pass_rate'] ?? 0;
                        $color = ($pass_rate >= 75) ? 'success' : (($pass_rate >= 50) ? 'warning' : 'danger');
                    ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars($row['section_name']); ?></td>
                        <td><small class="text-muted"><?php echo htmlspecialchars($row['subject_title']); ?></small></td>
                        <td class="text-center"><?php echo number_format($row['total_students']); ?></td>
                        <td class="text-center fw-bold text-blue"><?php echo $row['avg_final_grade'] ? number_format($row['avg_final_grade'], 2) : '-'; ?></td>
                        <td class="text-center text-success fw-bold"><?php echo number_format($row['passed_count']); ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> px-3 py-2 fw-bold">
                                <?php echo number_format($pass_rate, 1); ?>%
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="report-status text-<?php echo $color; ?>">
                                <?php echo ($pass_rate >= 75) ? 'Excellent' : (($pass_rate >= 50) ? 'Average' : 'Low Pass Rate'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Logic preserved exactly as requested
function updateReports() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    if (startDate && endDate) {
        window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
    }
}

function exportReport(type) {
    Swal.fire({
        title: 'Export Data',
        text: `The ${type} report is being prepared for download.`,
        icon: 'info',
        confirmButtonColor: '#800000'
    });
}

function showAlert(message, type) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>