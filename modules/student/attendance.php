<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Attendance";
$student_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$section = $conn->query("
    SELECT sst.section_id, sec.section_name
    FROM section_students sst
    INNER JOIN sections sec ON sst.section_id = sec.id
    WHERE sst.student_id = $student_id AND sec.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();
$section_id = $section['section_id'] ?? 0;

$filter_month = $_GET['month'] ?? date('Y-m');

$attendance_query = $conn->query("
    SELECT a.*, 
           COALESCE(cs.subject_code, c.course_code) as subject_code, 
           COALESCE(cs.subject_title, c.title) as subject_title,
           CONCAT(up.first_name, ' ', up.last_name) as recorded_by_name
    FROM attendance a
    LEFT JOIN curriculum_subjects cs ON a.subject_id = cs.id
    LEFT JOIN classes cl ON a.class_id = cl.id AND a.class_id > 0
    LEFT JOIN courses c ON cl.course_id = c.id
    LEFT JOIN user_profiles up ON a.recorded_by = up.user_id
    WHERE a.student_id = $student_id
    AND DATE_FORMAT(a.attendance_date, '%Y-%m') = '$filter_month'
    ORDER BY a.attendance_date DESC, subject_code
");

$attendance_records = [];
while ($row = $attendance_query->fetch_assoc()) { $attendance_records[] = $row; }

$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
    FROM attendance
    WHERE student_id = $student_id
");
$stats = $stats_query->fetch_assoc();

$attendance_rate = $stats['total_days'] > 0 
    ? round((($stats['present'] + $stats['late'] + $stats['excused']) / $stats['total_days']) * 100, 1) 
    : 0;

$by_subject = $conn->query("
    SELECT 
        COALESCE(cs.subject_code, c.course_code) as course_code, 
        COALESCE(cs.subject_title, c.title) as title,
        COUNT(*) as total,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late
    FROM attendance a
    LEFT JOIN curriculum_subjects cs ON a.subject_id = cs.id
    LEFT JOIN classes cl ON a.class_id = cl.id AND a.class_id > 0
    LEFT JOIN courses c ON cl.course_id = c.id
    WHERE a.student_id = $student_id
    GROUP BY COALESCE(a.subject_id, cl.course_id)
    ORDER BY course_code
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

    /* --- FANTASTIC ATTENDANCE UI --- */
    .att-stat-card {
        background: white; border-radius: 15px; padding: 20px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s;
        text-align: center; height: 100%;
    }
    .att-stat-card:hover { transform: translateY(-5px); }
    .att-icon-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.3rem; }

    .main-card { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .card-header-modern { background: #fcfcfc; padding: 15px 25px; border-bottom: 1px solid #eee; font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

    .table-modern thead th { background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; padding: 15px 20px; position: sticky; top: -1px; z-index: 5; }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; font-size: 0.9rem; }

    .progress-tiny { height: 6px; border-radius: 10px; background: #eee; overflow: hidden; margin: 8px 0; }
    .month-picker { border-radius: 50px; border: 1px solid #ddd; font-weight: 600; color: var(--blue); padding-left: 15px; width: 220px; }

    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
        .month-picker { width: 100%; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-calendar-check-fill me-2 text-success"></i>My Attendance</h4>
            <p class="text-muted small mb-0"><?php echo htmlspecialchars($current_ay['year_name'] ?? 'Academic Year'); ?></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <input type="month" class="form-control form-control-sm month-picker shadow-sm" value="<?php echo $filter_month; ?>" onchange="window.location.href='attendance.php?month='+this.value">
            <button class="btn btn-light btn-sm border rounded-pill px-3 shadow-sm" onclick="window.print()"><i class="bi bi-printer"></i></button>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Stats Row -->
    <div class="row g-4 mb-4 animate__animated animate__fadeIn">
        <div class="col-6 col-md-3">
            <div class="att-stat-card border-bottom border-success border-4">
                <div class="att-icon-circle bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                <h3 class="fw-bold mb-0"><?php echo $stats['present'] ?? 0; ?></h3>
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Days Present</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="att-stat-card border-bottom border-danger border-4">
                <div class="att-icon-circle bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle"></i></div>
                <h3 class="fw-bold mb-0"><?php echo $stats['absent'] ?? 0; ?></h3>
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Days Absent</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="att-stat-card border-bottom border-warning border-4">
                <div class="att-icon-circle bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock-history"></i></div>
                <h3 class="fw-bold mb-0"><?php echo $stats['late'] ?? 0; ?></h3>
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Tardy Count</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="att-stat-card border-bottom border-info border-4">
                <div class="att-icon-circle bg-info bg-opacity-10 text-info"><i class="bi bi-percent"></i></div>
                <h3 class="fw-bold mb-0 text-info"><?php echo $attendance_rate; ?>%</h3>
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Compliance Rate</small>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Attendance by Subject (Left) -->
        <div class="col-lg-4 animate__animated animate__fadeInLeft">
            <div class="main-card h-100">
                <div class="card-header-modern"><i class="bi bi-pie-chart-fill me-2"></i>Subject Statistics</div>
                <div class="p-0">
                    <?php if ($by_subject->num_rows == 0): ?>
                        <div class="text-center py-5 text-muted small">No subject data found.</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php while ($subj = $by_subject->fetch_assoc()): 
                            $subj_rate = $subj['total'] > 0 ? round(($subj['present'] / $subj['total']) * 100) : 0;
                        ?>
                        <li class="list-group-item p-4 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-dark text-maroon border border-maroon"><?php echo htmlspecialchars($subj['course_code']); ?></span>
                                <span class="fw-bold text-blue"><?php echo $subj_rate; ?>%</span>
                            </div>
                            <div class="small fw-bold text-dark text-truncate mb-2"><?php echo htmlspecialchars($subj['title']); ?></div>
                            <div class="progress-tiny">
                                <div class="progress-bar bg-success" style="width: <?php echo $subj_rate; ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-2" style="font-size: 0.7rem;">
                                <span class="text-muted">Present: <strong><?php echo $subj['present']; ?></strong></span>
                                <span class="text-muted">Absent: <strong><?php echo $subj['absent']; ?></strong></span>
                                <span class="text-muted">Late: <strong><?php echo $subj['late']; ?></strong></span>
                            </div>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Attendance Records Table (Right) -->
        <div class="col-lg-8 animate__animated animate__fadeInRight">
            <div class="main-card">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul me-2"></i>Daily Logs: <?php echo date('F Y', strtotime($filter_month . '-01')); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Subject</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Time In</th>
                                <th>Instructor Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendance_records)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No attendance logs found for this period.</td></tr>
                            <?php else: foreach ($attendance_records as $record): 
                                $status_colors = ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'excused' => 'info'];
                                $row_clr = $status_colors[$record['status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo date('M d', strtotime($record['attendance_date'])); ?></div>
                                    <small class="text-muted text-uppercase" style="font-size:0.65rem;"><?php echo date('l', strtotime($record['attendance_date'])); ?></small>
                                </td>
                                <td>
                                    <div class="small fw-bold text-blue"><?php echo htmlspecialchars($record['subject_code']); ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($record['subject_title']); ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-<?php echo $row_clr; ?> px-3 py-2">
                                        <?php echo strtoupper($record['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center fw-bold text-dark small">
                                    <?php echo $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '--:--'; ?>
                                </td>
                                <td>
                                    <small class="text-muted fst-italic"><?php echo htmlspecialchars($record['remarks'] ?? 'No remarks'); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Legend -->
    <div class="main-card mt-4 animate__animated animate__fadeInUp">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3 text-muted small text-uppercase" style="letter-spacing: 1px;">Definitions</h6>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3">
                <div class="col"><span class="badge bg-success me-2">PRESENT</span><small class="text-muted">Full attendance recorded.</small></div>
                <div class="col"><span class="badge bg-danger me-2">ABSENT</span><small class="text-muted">Unexcused missed class.</small></div>
                <div class="col"><span class="badge bg-warning text-dark me-2">LATE</span><small class="text-muted">Arrival after grace period.</small></div>
                <div class="col"><span class="badge bg-info me-2">EXCUSED</span><small class="text-muted">Approved valid absence.</small></div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>