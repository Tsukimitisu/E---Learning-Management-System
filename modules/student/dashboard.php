<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Dashboard";
$student_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get student profile info
$student_profile = $conn->query("
    SELECT up.*, b.name as branch_name, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(p.program_code, ss.strand_code) as program_code,
           st.student_no
    FROM user_profiles up
    LEFT JOIN branches b ON up.branch_id = b.id
    LEFT JOIN students st ON up.user_id = st.user_id
    LEFT JOIN programs p ON st.course_id = p.id
    LEFT JOIN shs_strands ss ON st.course_id = ss.id
    WHERE up.user_id = $student_id
")->fetch_assoc();

// Get enrolled section
$section_info = $conn->query("
    SELECT s.*, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(p.program_code, ss.strand_code) as program_code,
           COALESCE(pyl.year_name, sgl.grade_name) as year_level,
           b.name as branch_name
    FROM section_students stu
    INNER JOIN sections s ON stu.section_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

$section_id = $section_info['id'] ?? 0;

// Get subjects for current section via teacher_subject_assignments
$subjects = [];
if ($section_id > 0) {
    $subjects_query = $conn->query("
        SELECT cs.*, 
               tsa.teacher_id,
               CONCAT(up.first_name, ' ', up.last_name) as teacher_name
        FROM teacher_subject_assignments tsa
        INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
        LEFT JOIN user_profiles up ON tsa.teacher_id = up.user_id
        WHERE tsa.branch_id = " . ($section_info['branch_id'] ?? 0) . "
        AND tsa.academic_year_id = $current_ay_id
        AND tsa.is_active = 1
        AND cs.is_active = 1
        AND (
            (cs.program_id = " . ($section_info['program_id'] ?? 0) . " AND cs.year_level_id = " . ($section_info['year_level_id'] ?? 0) . ")
            OR (cs.shs_strand_id = " . ($section_info['shs_strand_id'] ?? 0) . " AND cs.shs_grade_level_id = " . ($section_info['shs_grade_level_id'] ?? 0) . ")
        )
        ORDER BY cs.subject_code
    ");
    while ($row = $subjects_query->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// Count stats
$stats = [
    'total_subjects' => count($subjects),
    'total_attendance' => 0,
    'attendance_rate' => 0,
    'average_grade' => 0,
    'pending_assessments' => 0
];

// Get attendance stats
$attendance_stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count
    FROM attendance
    WHERE student_id = $student_id
")->fetch_assoc();

if ($attendance_stats['total'] > 0) {
    $stats['total_attendance'] = $attendance_stats['total'];
    $stats['attendance_rate'] = round(($attendance_stats['present_count'] / $attendance_stats['total']) * 100, 1);
}

// Get average grade
$grade_avg = $conn->query("
    SELECT AVG(final_grade) as avg_grade
    FROM grades
    WHERE student_id = $student_id AND final_grade > 0
")->fetch_assoc();
$stats['average_grade'] = $grade_avg['avg_grade'] ? round($grade_avg['avg_grade'], 2) : 0;

// Get pending assessments
$pending = $conn->query("
    SELECT COUNT(*) as count
    FROM assessment_scores ascore
    INNER JOIN assessments a ON ascore.assessment_id = a.id
    WHERE ascore.student_id = $student_id AND ascore.status = 'pending'
")->fetch_assoc();
$stats['pending_assessments'] = $pending['count'] ?? 0;

// Get recent announcements
$announcements = $conn->query("
    SELECT a.*, CONCAT(up.first_name, ' ', up.last_name) as author_name
    FROM announcements a
    LEFT JOIN user_profiles up ON a.created_by = up.user_id
    WHERE a.is_active = 1 
    AND (a.target_audience = 'all' OR a.target_audience = 'students')
    AND (a.expires_at IS NULL OR a.expires_at > NOW())
    ORDER BY a.priority DESC, a.created_at DESC
    LIMIT 5
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Welcome Banner -->
        <div class="p-4 rounded-4 mb-4 text-white shadow-lg" style="background: linear-gradient(135deg, #800000 0%, #500000 100%);">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h2>
                    <?php if ($section_info): ?>
                    <p class="mb-0 opacity-75">
                        <i class="bi bi-mortarboard me-2"></i>
                        <?php echo htmlspecialchars($section_info['program_code'] . ' - ' . $section_info['section_name']); ?>
                    </p>
                    <small class="opacity-50">
                        <?php echo htmlspecialchars($section_info['year_level'] ?? ''); ?> | 
                        <?php echo htmlspecialchars($section_info['branch_name'] ?? ''); ?> |
                        <?php echo htmlspecialchars($current_ay['year_name'] ?? ''); ?>
                    </small>
                    <?php else: ?>
                    <p class="mb-0 opacity-75"><i class="bi bi-info-circle me-2"></i>Not enrolled in any section yet</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                    <i class="bi bi-person-workspace display-2 opacity-25"></i>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="bi bi-book text-primary fs-4"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold"><?php echo $stats['total_subjects']; ?></h3>
                            <small class="text-muted">Subjects</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="bi bi-calendar-check text-success fs-4"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold"><?php echo $stats['attendance_rate']; ?>%</h3>
                            <small class="text-muted">Attendance</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                            <i class="bi bi-graph-up text-info fs-4"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold"><?php echo $stats['average_grade'] ?: 'N/A'; ?></h3>
                            <small class="text-muted">Avg Grade</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                            <i class="bi bi-clipboard-check text-warning fs-4"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold"><?php echo $stats['pending_assessments']; ?></h3>
                            <small class="text-muted">Pending Tasks</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- My Subjects -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-book-half text-primary me-2"></i>My Subjects</h5>
                        <a href="my_classes.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($subjects)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-2">No subjects assigned yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Subject Code</th>
                                        <th>Subject Title</th>
                                        <th>Units</th>
                                        <th>Teacher</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($subject['subject_code']); ?></span></td>
                                        <td><?php echo htmlspecialchars($subject['subject_title']); ?></td>
                                        <td><?php echo $subject['units']; ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="bi bi-person me-1"></i>
                                                <?php echo htmlspecialchars($subject['teacher_name'] ?? 'TBA'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <a href="subject_view.php?id=<?php echo $subject['id']; ?>&teacher=<?php echo $subject['teacher_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
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

            <!-- Announcements & Quick Links -->
            <div class="col-lg-4 mb-4">
                <!-- Announcements -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-megaphone text-danger me-2"></i>Announcements</h5>
                        <a href="announcements.php" class="btn btn-sm btn-outline-danger">View All</a>
                    </div>
                    <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                        <?php if ($announcements->num_rows == 0): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-bell-slash"></i>
                            <p class="mb-0 small">No announcements</p>
                        </div>
                        <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php while ($ann = $announcements->fetch_assoc()): 
                                $priority_colors = ['low' => 'secondary', 'normal' => 'info', 'high' => 'warning', 'urgent' => 'danger'];
                            ?>
                            <li class="list-group-item px-3 py-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge bg-<?php echo $priority_colors[$ann['priority']]; ?> me-1"><?php echo ucfirst($ann['priority']); ?></span>
                                        <strong class="small"><?php echo htmlspecialchars($ann['title']); ?></strong>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <?php echo date('M d, Y', strtotime($ann['created_at'])); ?>
                                </small>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-lightning text-warning me-2"></i>Quick Links</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="grades.php" class="btn btn-outline-primary text-start">
                                <i class="bi bi-bar-chart-fill me-2"></i> View My Grades
                            </a>
                            <a href="attendance.php" class="btn btn-outline-success text-start">
                                <i class="bi bi-calendar-check me-2"></i> View Attendance
                            </a>
                            <a href="assessments.php" class="btn btn-outline-warning text-start">
                                <i class="bi bi-clipboard-check me-2"></i> View Assessments
                            </a>
                            <a href="materials.php" class="btn btn-outline-info text-start">
                                <i class="bi bi-file-earmark-pdf me-2"></i> Learning Materials
                            </a>
                            <a href="profile.php" class="btn btn-outline-secondary text-start">
                                <i class="bi bi-person-circle me-2"></i> My Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
