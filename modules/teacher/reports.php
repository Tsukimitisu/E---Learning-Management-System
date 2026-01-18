<?php
require_once '../../config/init.php';

// Fix: Check both role_id and role for compatibility
$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Reports & Analytics";
$teacher_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - Updated to use new section/subject structure
 */
// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Semester mapping
$semester_map = [1 => '1st', 2 => '2nd', 3 => 'summer'];

// Get teacher's assigned subjects
$subjects_query = $conn->prepare("
    SELECT 
        tsa.id as assignment_id,
        tsa.curriculum_subject_id as subject_id,
        cs.subject_code,
        cs.subject_title,
        cs.semester,
        cs.program_id,
        cs.year_level_id,
        cs.shs_strand_id,
        cs.shs_grade_level_id,
        tsa.branch_id
    FROM teacher_subject_assignments tsa
    INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
    WHERE tsa.teacher_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
    ORDER BY cs.subject_code
");
$subjects_query->bind_param("ii", $teacher_id, $current_ay_id);
$subjects_query->execute();
$teacher_subjects = $subjects_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Build sections list with subject info for dropdowns
$section_options = [];
foreach ($teacher_subjects as $subject) {
    $semester_str = $semester_map[$subject['semester']] ?? '1st';
    
    if (!empty($subject['program_id'])) {
        // College
        $sections_query = $conn->prepare("
            SELECT s.id, s.section_name, p.program_code
            FROM sections s
            LEFT JOIN programs p ON s.program_id = p.id
            WHERE s.program_id = ? AND s.year_level_id = ? AND s.semester = ?
            AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
        ");
        $sections_query->bind_param("iisii", $subject['program_id'], $subject['year_level_id'], 
            $semester_str, $subject['branch_id'], $current_ay_id);
    } else {
        // SHS
        $sections_query = $conn->prepare("
            SELECT s.id, s.section_name, ss.strand_code as program_code
            FROM sections s
            LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
            WHERE s.shs_strand_id = ? AND s.shs_grade_level_id = ? AND s.semester = ?
            AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
        ");
        $sections_query->bind_param("iisii", $subject['shs_strand_id'], $subject['shs_grade_level_id'], 
            $semester_str, $subject['branch_id'], $current_ay_id);
    }
    $sections_query->execute();
    $sections_result = $sections_query->get_result();
    
    while ($section = $sections_result->fetch_assoc()) {
        $section_options[] = [
            'section_id' => $section['id'],
            'subject_id' => $subject['subject_id'],
            'label' => $subject['subject_code'] . ' - ' . $section['section_name']
        ];
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php'; // Opens wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
        z-index: 10;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px;
        background-color: #f8f9fa;
    }

    /* --- FANTASTIC REPORT UI --- */
    .report-card {
        background: white;
        border-radius: 20px;
        border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        padding: 30px;
    }

    .report-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px rgba(0, 51, 102, 0.1);
    }

    .report-icon-bg {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 20px;
    }

    .stats-summary-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        border: none;
        box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    }

    .stat-divider {
        width: 1px;
        background: #eee;
        height: 50px;
    }

    .btn-generate {
        background-color: var(--maroon);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        padding: 12px;
        transition: 0.3s;
    }
    .btn-generate:hover {
        background-color: #600000;
        color: white;
        transform: scale(1.02);
    }

    /* Mobile Logic */
    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
        .stat-divider { display: none; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-bar-chart-line-fill me-2"></i>Reports & Analytics</h4>
        <p class="text-muted small mb-0">Generate academic insights and documentation</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4 shadow-sm rounded-pill">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <div class="row g-4 mb-5">
        <!-- Grade Summary -->
        <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <div class="report-card">
                <div class="report-icon-bg bg-light text-maroon"><i class="bi bi-file-earmark-pdf"></i></div>
                <h5 class="fw-bold text-dark mb-2">Grade Summary</h5>
                <p class="text-muted small mb-4">Export a comprehensive PDF of student final marks for a specific class.</p>
                <form id="gradeSummaryForm" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">TARGET CLASS</label>
                        <select class="form-select border-light shadow-sm" name="section_subject" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($section_options as $opt): ?>
                                <option value="<?php echo $opt['section_id'] . '_' . $opt['subject_id']; ?>">
                                    <?php echo htmlspecialchars($opt['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-generate w-100 shadow-sm">
                        <i class="bi bi-file-pdf me-2"></i> Generate PDF
                    </button>
                </form>
            </div>
        </div>

        <!-- Attendance -->
        <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="report-card">
                <div class="report-icon-bg bg-light text-primary"><i class="bi bi-calendar-check"></i></div>
                <h5 class="fw-bold text-dark mb-2">Attendance Sheet</h5>
                <p class="text-muted small mb-4">Export attendance logs in Excel format for local records or auditing.</p>
                <form id="attendanceReportForm" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">TARGET CLASS</label>
                        <select class="form-select border-light shadow-sm" name="section_subject" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($section_options as $opt): ?>
                                <option value="<?php echo $opt['section_id'] . '_' . $opt['subject_id']; ?>">
                                    <?php echo htmlspecialchars($opt['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">FROM</label>
                            <input type="date" class="form-control form-control-sm border-light shadow-sm" name="date_from" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">TO</label>
                            <input type="date" class="form-control form-control-sm border-light shadow-sm" name="date_to" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-generate w-100 shadow-sm" style="background-color: var(--blue);">
                        <i class="bi bi-file-excel me-2"></i> Generate Excel
                    </button>
                </form>
            </div>
        </div>

        <!-- Class Performance -->
        <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <div class="report-card">
                <div class="report-icon-bg bg-light text-success"><i class="bi bi-graph-up-arrow"></i></div>
                <h5 class="fw-bold text-dark mb-2">Class Analytics</h5>
                <p class="text-muted small mb-4">Visualize grade distributions and performance trends for your sections.</p>
                <form id="performanceReportForm" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">TARGET CLASS</label>
                        <select class="form-select border-light shadow-sm" name="section_subject" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($section_options as $opt): ?>
                                <option value="<?php echo $opt['section_id'] . '_' . $opt['subject_id']; ?>">
                                    <?php echo htmlspecialchars($opt['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-generate w-100 shadow-sm" style="background-color: #28a745;">
                        <i class="bi bi-pie-chart me-2"></i> View Analytics
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Stats Summary -->
    <div class="stats-summary-card animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
        <h6 class="fw-bold mb-4 text-uppercase small opacity-75" style="letter-spacing: 1px;">Overall Teaching Summary</h6>
        <?php
        /** BACKEND STATS - Updated for new structure */
        $total_subjects = count($teacher_subjects);
        
        // Count total sections and students
        $total_sections = 0;
        $total_students = 0;
        $all_section_ids = [];
        
        foreach ($teacher_subjects as $subject) {
            $semester_str = $semester_map[$subject['semester']] ?? '1st';
            
            if (!empty($subject['program_id'])) {
                $count_query = $conn->prepare("
                    SELECT s.id, COUNT(DISTINCT ss.student_id) as student_count
                    FROM sections s
                    LEFT JOIN section_students ss ON s.id = ss.section_id AND ss.status = 'active'
                    WHERE s.program_id = ? AND s.year_level_id = ? AND s.semester = ?
                    AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
                    GROUP BY s.id
                ");
                $count_query->bind_param("iisii", $subject['program_id'], $subject['year_level_id'], 
                    $semester_str, $subject['branch_id'], $current_ay_id);
            } else {
                $count_query = $conn->prepare("
                    SELECT s.id, COUNT(DISTINCT ss.student_id) as student_count
                    FROM sections s
                    LEFT JOIN section_students ss ON s.id = ss.section_id AND ss.status = 'active'
                    WHERE s.shs_strand_id = ? AND s.shs_grade_level_id = ? AND s.semester = ?
                    AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
                    GROUP BY s.id
                ");
                $count_query->bind_param("iisii", $subject['shs_strand_id'], $subject['shs_grade_level_id'], 
                    $semester_str, $subject['branch_id'], $current_ay_id);
            }
            $count_query->execute();
            $sections_result = $count_query->get_result();
            
            while ($sec = $sections_result->fetch_assoc()) {
                if (!in_array($sec['id'], $all_section_ids)) {
                    $all_section_ids[] = $sec['id'];
                    $total_sections++;
                    $total_students += $sec['student_count'] ?? 0;
                }
            }
        }
        
        // Calculate average grade and pass rate from grades table
        $avg_grade = 0;
        $pass_rate = 0;
        if (!empty($all_section_ids)) {
            $section_ids_str = implode(',', $all_section_ids);
            $grade_stats = $conn->query("
                SELECT 
                    AVG(final_grade) as avg_grade,
                    COUNT(CASE WHEN remarks = 'PASSED' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as pass_rate
                FROM grades 
                WHERE section_id IN ($section_ids_str) AND final_grade > 0
            ")->fetch_assoc();
            $avg_grade = $grade_stats['avg_grade'] ?? 0;
            $pass_rate = $grade_stats['pass_rate'] ?? 0;
        }
        ?>
        <div class="row align-items-center text-center g-4">
            <div class="col-md-3">
                <h3 class="fw-bold mb-0" style="color: var(--maroon);"><?php echo $total_subjects; ?></h3>
                <small class="text-muted fw-bold">My Subjects</small>
            </div>
            <div class="col-md-3 d-flex align-items-center justify-content-center">
                <div class="stat-divider d-none d-md-block"></div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-0" style="color: var(--blue);"><?php echo $total_students; ?></h3>
                    <small class="text-muted fw-bold">Total Students</small>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-center justify-content-center">
                <div class="stat-divider d-none d-md-block"></div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-0 text-success"><?php echo $avg_grade ? number_format($avg_grade, 2) : '0.00'; ?></h3>
                    <small class="text-muted fw-bold">Mean Grade</small>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-center justify-content-center">
                <div class="stat-divider d-none d-md-block"></div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-0 text-info"><?php echo $pass_rate ? number_format($pass_rate, 1) : '0'; ?>%</h3>
                    <small class="text-muted fw-bold">Success Rate</small>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - Updated for new structure --- -->
<script>
document.getElementById('gradeSummaryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const value = e.target.querySelector('[name="section_subject"]').value;
    const [sectionId, subjectId] = value.split('_');
    window.open('process/generate_grade_report.php?section_id=' + sectionId + '&subject_id=' + subjectId, '_blank');
});

document.getElementById('attendanceReportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const value = e.target.querySelector('[name="section_subject"]').value;
    const [sectionId, subjectId] = value.split('_');
    const dateFrom = e.target.querySelector('[name="date_from"]').value;
    const dateTo = e.target.querySelector('[name="date_to"]').value;
    window.open('process/generate_attendance_report.php?section_id=' + sectionId + '&subject_id=' + subjectId + '&date_from=' + dateFrom + '&date_to=' + dateTo, '_blank');
});

document.getElementById('performanceReportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const value = e.target.querySelector('[name="section_subject"]').value;
    const [sectionId, subjectId] = value.split('_');
    window.location.href = 'performance_analytics.php?section_id=' + sectionId + '&subject_id=' + subjectId;
});
</script>
</body>
</html>