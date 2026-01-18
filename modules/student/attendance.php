<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Attendance";
$student_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get student's section
$section = $conn->query("
    SELECT sst.section_id, sec.section_name
    FROM section_students sst
    INNER JOIN sections sec ON sst.section_id = sec.id
    WHERE sst.student_id = $student_id AND sec.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();
$section_id = $section['section_id'] ?? 0;

// Filter by month
$filter_month = $_GET['month'] ?? date('Y-m');

// Get attendance records - support both section_id/subject_id and class_id methods
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
while ($row = $attendance_query->fetch_assoc()) {
    $attendance_records[] = $row;
}

// Calculate attendance statistics
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

// Get attendance by subject - support both methods
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
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-calendar-check text-success me-2"></i>My Attendance</h4>
                <small class="text-muted"><?php echo htmlspecialchars($current_ay['year_name'] ?? ''); ?></small>
            </div>
            <div>
                <input type="month" class="form-control" id="monthFilter" value="<?php echo $filter_month; ?>" 
                       onchange="window.location.href='attendance.php?month='+this.value">
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body">
                        <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex p-3 mb-2">
                            <i class="bi bi-check-circle text-success fs-3"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-success"><?php echo $stats['present'] ?? 0; ?></h3>
                        <small class="text-muted">Present</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body">
                        <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex p-3 mb-2">
                            <i class="bi bi-x-circle text-danger fs-3"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-danger"><?php echo $stats['absent'] ?? 0; ?></h3>
                        <small class="text-muted">Absent</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body">
                        <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex p-3 mb-2">
                            <i class="bi bi-clock text-warning fs-3"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-warning"><?php echo $stats['late'] ?? 0; ?></h3>
                        <small class="text-muted">Late</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body">
                        <div class="rounded-circle bg-info bg-opacity-10 d-inline-flex p-3 mb-2">
                            <i class="bi bi-percent text-info fs-3"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-info"><?php echo $attendance_rate; ?>%</h3>
                        <small class="text-muted">Attendance Rate</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Attendance by Subject -->
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-pie-chart text-primary me-2"></i>By Subject</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($by_subject->num_rows == 0): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox"></i>
                            <p class="small mb-0">No data available</p>
                        </div>
                        <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php while ($subj = $by_subject->fetch_assoc()): 
                                $subj_rate = $subj['total'] > 0 ? round(($subj['present'] / $subj['total']) * 100) : 0;
                            ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($subj['course_code']); ?></span>
                                    <small class="text-muted"><?php echo $subj_rate; ?>%</small>
                                </div>
                                <small class="d-block text-truncate" title="<?php echo htmlspecialchars($subj['title']); ?>">
                                    <?php echo htmlspecialchars($subj['title']); ?>
                                </small>
                                <div class="progress mt-1" style="height: 5px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $subj_rate; ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    P: <?php echo $subj['present']; ?> | 
                                    A: <?php echo $subj['absent']; ?> | 
                                    L: <?php echo $subj['late']; ?>
                                </small>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Attendance Records -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-list-ul text-info me-2"></i>
                            Attendance Records - <?php echo date('F Y', strtotime($filter_month . '-01')); ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($attendance_records)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x display-4"></i>
                            <p class="mt-2">No attendance records for this month</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th class="text-center">Status</th>
                                        <th>Time In</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): 
                                        $status_colors = [
                                            'present' => 'success',
                                            'absent' => 'danger',
                                            'late' => 'warning',
                                            'excused' => 'info'
                                        ];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('M d', strtotime($record['attendance_date'])); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo date('l', strtotime($record['attendance_date'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($record['subject_code']); ?></span>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['subject_title']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $status_colors[$record['status']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($record['time_in']): ?>
                                            <?php echo date('h:i A', strtotime($record['time_in'])); ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($record['remarks'] ?? '-'); ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Status Legend</h6>
                <div class="d-flex flex-wrap gap-3">
                    <span><span class="badge bg-success">Present</span> - Attended the class</span>
                    <span><span class="badge bg-danger">Absent</span> - Did not attend</span>
                    <span><span class="badge bg-warning">Late</span> - Arrived after class started</span>
                    <span><span class="badge bg-info">Excused</span> - Absence with valid reason</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
