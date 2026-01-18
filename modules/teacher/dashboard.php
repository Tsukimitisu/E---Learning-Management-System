<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Teacher Dashboard";
$teacher_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

/** 
 * BACKEND LOGIC - Using new section/subject structure
 */
$stats = [
    'my_subjects' => 0,
    'total_sections' => 0,
    'total_students' => 0,
    'grading_progress' => 0
];

// Count subjects assigned to this teacher
$subjects_count = $conn->prepare("
    SELECT COUNT(DISTINCT tsa.curriculum_subject_id) as count 
    FROM teacher_subject_assignments tsa
    WHERE tsa.teacher_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
");
$subjects_count->bind_param("ii", $teacher_id, $current_ay_id);
$subjects_count->execute();
if ($row = $subjects_count->get_result()->fetch_assoc()) { 
    $stats['my_subjects'] = $row['count']; 
}

// Get all subject assignments for this teacher to calculate sections and students
$subjects_query = $conn->prepare("
    SELECT cs.id, cs.semester, cs.program_id, cs.year_level_id, cs.shs_strand_id, cs.shs_grade_level_id, tsa.branch_id
    FROM teacher_subject_assignments tsa
    INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
    WHERE tsa.teacher_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
");
$subjects_query->bind_param("ii", $teacher_id, $current_ay_id);
$subjects_query->execute();
$subjects_result = $subjects_query->get_result();

$total_sections = 0;
$total_students = 0;
$semester_map = [1 => '1st', 2 => '2nd', 3 => 'summer'];

while ($subject = $subjects_result->fetch_assoc()) {
    $semester_str = $semester_map[$subject['semester']] ?? '1st';
    
    // Count sections and students for this subject
    if (!empty($subject['program_id'])) {
        $count_query = $conn->prepare("
            SELECT COUNT(DISTINCT s.id) as section_count,
                   COUNT(DISTINCT ss.student_id) as student_count
            FROM sections s
            LEFT JOIN section_students ss ON s.id = ss.section_id AND ss.status = 'active'
            WHERE s.program_id = ? AND s.year_level_id = ? AND s.semester = ?
            AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
        ");
        $count_query->bind_param("iisii", $subject['program_id'], $subject['year_level_id'], 
            $semester_str, $subject['branch_id'], $current_ay_id);
    } else {
        $count_query = $conn->prepare("
            SELECT COUNT(DISTINCT s.id) as section_count,
                   COUNT(DISTINCT ss.student_id) as student_count
            FROM sections s
            LEFT JOIN section_students ss ON s.id = ss.section_id AND ss.status = 'active'
            WHERE s.shs_strand_id = ? AND s.shs_grade_level_id = ? AND s.semester = ?
            AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
        ");
        $count_query->bind_param("iisii", $subject['shs_strand_id'], $subject['shs_grade_level_id'], 
            $semester_str, $subject['branch_id'], $current_ay_id);
    }
    $count_query->execute();
    $counts = $count_query->get_result()->fetch_assoc();
    $total_sections += $counts['section_count'] ?? 0;
    $total_students += $counts['student_count'] ?? 0;
}

$stats['total_sections'] = $total_sections;
$stats['total_students'] = $total_students;

// Calculate grading progress based on teacher's assigned subjects and sections
$grading_progress = 0;
if (!empty($teacher_subjects)) {
    $graded_count = 0;
    $total_count = 0;
    
    foreach ($teacher_subjects as $subject) {
        // Get sections for this subject assignment
        $semester_str = $semester_map[$subject['semester']] ?? '1st';
        
        if (!empty($subject['program_id'])) {
            // College
            $section_query = $conn->prepare("
                SELECT s.id FROM sections s 
                WHERE s.program_id = ? AND s.year_level_id = ? AND s.semester = ?
                AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
            ");
            $section_query->bind_param("iisii", $subject['program_id'], $subject['year_level_id'], 
                $semester_str, $subject['branch_id'], $current_ay_id);
        } else {
            // SHS
            $section_query = $conn->prepare("
                SELECT s.id FROM sections s 
                WHERE s.shs_strand_id = ? AND s.shs_grade_level_id = ? AND s.semester = ?
                AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
            ");
            $section_query->bind_param("iisii", $subject['shs_strand_id'], $subject['shs_grade_level_id'], 
                $semester_str, $subject['branch_id'], $current_ay_id);
        }
        $section_query->execute();
        $sections_result = $section_query->get_result();
        
        while ($sec = $sections_result->fetch_assoc()) {
            // Count students in this section
            $student_count_query = $conn->prepare("SELECT COUNT(*) as cnt FROM section_students WHERE section_id = ? AND status = 'active'");
            $student_count_query->bind_param("i", $sec['id']);
            $student_count_query->execute();
            $cnt = $student_count_query->get_result()->fetch_assoc()['cnt'] ?? 0;
            $total_count += $cnt;
            
            // Count graded students for this section and subject
            $graded_query = $conn->prepare("SELECT COUNT(*) as cnt FROM grades WHERE section_id = ? AND subject_id = ? AND final_grade IS NOT NULL AND final_grade > 0");
            $graded_query->bind_param("ii", $sec['id'], $subject['subject_id']);
            $graded_query->execute();
            $graded_cnt = $graded_query->get_result()->fetch_assoc()['cnt'] ?? 0;
            $graded_count += $graded_cnt;
        }
    }
    
    $grading_progress = $total_count > 0 ? round(($graded_count / $total_count) * 100) : 0;
}
$stats['grading_progress'] = $grading_progress;

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<style>
    /* --- LAYOUT ENGINE: LOCKED SIDEBAR --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px; /* Padding for bottom visibility */
        background-color: #f8f9fa;
    }

    /* --- FANTASTIC TEACHER UI --- */
    .teacher-stat-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }
    .teacher-stat-card:hover { transform: translateY(-8px); box-shadow: 0 12px 20px rgba(0,0,0,0.1); }
    
    .stat-icon-circle {
        width: 60px; height: 60px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem;
    }

    .action-card-btn {
        background: white;
        border: 1px solid #eee;
        padding: 25px;
        border-radius: 20px;
        text-align: center;
        text-decoration: none;
        color: #555;
        transition: all 0.3s ease;
        display: block;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    }
    .action-card-btn:hover {
        background: var(--blue);
        color: white !important;
        border-color: var(--blue);
        transform: scale(1.05);
    }
    .action-card-btn i { font-size: 2rem; display: block; margin-bottom: 10px; }

    .table-modern {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    }
    .table-modern thead th {
        background: #fcfcfc;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #888;
        padding: 15px 20px;
        border-bottom: 2px solid #eee;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; }

    /* Progress bar styling */
    .progress-custom { height: 8px; border-radius: 10px; background: #eee; overflow: hidden; margin-top: 10px; }
    .progress-bar-maroon { background: var(--maroon); }

    /* Mobile Responsive Fixes */
    @media (max-width: 576px) {
        .header-fixed-part { flex-direction: column; gap: 15px; text-align: center; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>

<!-- Part 1: Fixed Top Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);">Faculty Dashboard</h4>
        <p class="text-muted small mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
    </div>
    <span class="badge rounded-pill bg-light text-dark border px-3 py-2 shadow-sm">
        <i class="bi bi-calendar3 me-2 text-maroon"></i><?php echo date('F d, Y'); ?>
    </span>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    
    <!-- Stats Grid (Animated) -->
    <div class="row g-4 mb-5">
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.1s;">
            <div class="teacher-stat-card border-start border-primary border-5">
                <div class="stat-icon-circle bg-light text-primary"><i class="bi bi-journal-bookmark"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['my_subjects']); ?></h3>
                    <p class="text-muted small text-uppercase fw-bold mb-0">My Subjects</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.2s;">
            <div class="teacher-stat-card border-start border-info border-5">
                <div class="stat-icon-circle bg-light text-info"><i class="bi bi-collection"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_sections']); ?></h3>
                    <p class="text-muted small text-uppercase fw-bold mb-0">Total Sections</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.3s;">
            <div class="teacher-stat-card border-start border-warning border-5">
                <div class="stat-icon-circle bg-light text-warning"><i class="bi bi-people"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_students']); ?></h3>
                    <p class="text-muted small text-uppercase fw-bold mb-0">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.4s;">
            <div class="teacher-stat-card border-start border-success border-5">
                <div class="stat-icon-circle bg-light text-success"><i class="bi bi-graph-up"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $stats['grading_progress']; ?>%</h3>
                    <p class="text-muted small text-uppercase fw-bold mb-0">Grading Progress</p>
                    <div class="progress-custom"><div class="progress-bar progress-bar-maroon" style="width: <?php echo $stats['grading_progress']; ?>%"></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h6 class="fw-bold text-muted mb-3 text-uppercase small" style="letter-spacing: 1px;">Management Hub</h6>
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <a href="subjects.php" class="action-card-btn shadow-sm">
                <i class="bi bi-journal-bookmark-fill"></i> My Subjects
            </a>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <a href="grading.php" class="action-card-btn shadow-sm">
                <i class="bi bi-calculator-fill"></i> Grading
            </a>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <a href="attendance.php" class="action-card-btn shadow-sm">
                <i class="bi bi-calendar-check-fill"></i> Attendance
            </a>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
            <a href="materials.php" class="action-card-btn shadow-sm">
                <i class="bi bi-folder-fill"></i> Materials
            </a>
        </div>
    </div>

    <!-- Subjects List Table -->
    <div class="table-modern animate__animated animate__fadeInUp" style="animation-delay: 0.5s;">
        <div class="bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-collection-play-fill me-2"></i>Your Assigned Subjects</h6>
            <a href="subjects.php" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Subject Details</th>
                        <th>Program</th>
                        <th>Semester</th>
                        <th>Branch</th>
                        <th class="text-center">Sections</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Get teacher's subjects for display
                    $display_subjects = $conn->prepare("
                        SELECT cs.id as subject_id, cs.subject_code, cs.subject_title, cs.semester,
                               cs.program_id, cs.year_level_id, cs.shs_strand_id, cs.shs_grade_level_id,
                               COALESCE(p.program_name, ss.strand_name) as program_name,
                               b.name as branch_name, tsa.branch_id
                        FROM teacher_subject_assignments tsa
                        INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
                        INNER JOIN branches b ON tsa.branch_id = b.id
                        LEFT JOIN programs p ON cs.program_id = p.id
                        LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
                        WHERE tsa.teacher_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
                        ORDER BY cs.subject_code
                        LIMIT 5
                    ");
                    $display_subjects->bind_param("ii", $teacher_id, $current_ay_id);
                    $display_subjects->execute();
                    $subjects_list = $display_subjects->get_result();
                    
                    if ($subjects_list->num_rows > 0):
                        while ($subj = $subjects_list->fetch_assoc()):
                            // Count sections for this subject
                            $sem_str = $semester_map[$subj['semester']] ?? '1st';
                            if (!empty($subj['program_id'])) {
                                $sec_count = $conn->prepare("SELECT COUNT(*) as cnt FROM sections WHERE program_id = ? AND year_level_id = ? AND semester = ? AND branch_id = ? AND academic_year_id = ? AND is_active = 1");
                                $sec_count->bind_param("iisii", $subj['program_id'], $subj['year_level_id'], $sem_str, $subj['branch_id'], $current_ay_id);
                            } else {
                                $sec_count = $conn->prepare("SELECT COUNT(*) as cnt FROM sections WHERE shs_strand_id = ? AND shs_grade_level_id = ? AND semester = ? AND branch_id = ? AND academic_year_id = ? AND is_active = 1");
                                $sec_count->bind_param("iisii", $subj['shs_strand_id'], $subj['shs_grade_level_id'], $sem_str, $subj['branch_id'], $current_ay_id);
                            }
                            $sec_count->execute();
                            $section_count = $sec_count->get_result()->fetch_assoc()['cnt'] ?? 0;
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($subj['subject_code']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($subj['subject_title']); ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($subj['program_name'] ?? 'N/A'); ?></span></td>
                        <td><small class="text-muted"><i class="bi bi-calendar me-1"></i><?php echo $subj['semester']; ?> Sem</small></td>
                        <td><small class="fw-bold text-primary"><?php echo htmlspecialchars($subj['branch_name']); ?></small></td>
                        <td class="text-center"><span class="badge bg-blue rounded-pill px-3"><?php echo $section_count; ?></span></td>
                        <td class="text-end">
                            <a href="subject_sections.php?subject_id=<?php echo $subj['subject_id']; ?>" class="btn btn-maroon btn-sm px-3 fw-bold rounded-pill shadow-sm">
                                View Sections
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No subjects assigned to you yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>