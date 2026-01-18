<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Attendance Management";
$teacher_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

/** 
 * BACKEND LOGIC - Show subjects first, then sections (like My Subjects)
 */
// Get all subjects assigned to this teacher
$subjects_query = $conn->prepare("
    SELECT DISTINCT
        cs.id as subject_id,
        cs.subject_code,
        cs.subject_title,
        cs.units,
        cs.semester,
        cs.program_id,
        cs.year_level_id,
        cs.shs_strand_id,
        cs.shs_grade_level_id,
        tsa.branch_id,
        COALESCE(p.program_name, ss.strand_name) as program_name,
        b.name as branch_name
    FROM teacher_subject_assignments tsa
    INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
    INNER JOIN branches b ON tsa.branch_id = b.id
    LEFT JOIN programs p ON cs.program_id = p.id
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    WHERE tsa.teacher_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
    ORDER BY cs.subject_code
");
$subjects_query->bind_param("ii", $teacher_id, $current_ay_id);
$subjects_query->execute();
$subjects_result = $subjects_query->get_result();

// Build array of subjects with section counts
$teacher_subjects = [];
while ($subject = $subjects_result->fetch_assoc()) {
    // Convert semester number to string format
    $semester_map = [1 => '1st', 2 => '2nd', 3 => 'summer'];
    $semester_str = $semester_map[$subject['semester']] ?? '1st';
    
    // Count sections for this subject based on program type
    if (!empty($subject['program_id'])) {
        $count_sql = "SELECT COUNT(DISTINCT s.id) as section_count,
            (SELECT COUNT(DISTINCT ss.student_id) FROM section_students ss 
             INNER JOIN sections sec ON ss.section_id = sec.id 
             WHERE sec.program_id = ? AND sec.year_level_id = ? AND sec.semester = ? 
             AND sec.branch_id = ? AND sec.academic_year_id = ? AND ss.status = 'active') as total_students
            FROM sections s 
            WHERE s.program_id = ? AND s.year_level_id = ? AND s.semester = ? 
            AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1";
        $count_query = $conn->prepare($count_sql);
        $count_query->bind_param("iisiiiisii", 
            $subject['program_id'], $subject['year_level_id'], $semester_str, $subject['branch_id'], $current_ay_id,
            $subject['program_id'], $subject['year_level_id'], $semester_str, $subject['branch_id'], $current_ay_id);
    } else {
        $count_sql = "SELECT COUNT(DISTINCT s.id) as section_count,
            (SELECT COUNT(DISTINCT ss.student_id) FROM section_students ss 
             INNER JOIN sections sec ON ss.section_id = sec.id 
             WHERE sec.shs_strand_id = ? AND sec.shs_grade_level_id = ? AND sec.semester = ? 
             AND sec.branch_id = ? AND sec.academic_year_id = ? AND ss.status = 'active') as total_students
            FROM sections s 
            WHERE s.shs_strand_id = ? AND s.shs_grade_level_id = ? AND s.semester = ? 
            AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1";
        $count_query = $conn->prepare($count_sql);
        $count_query->bind_param("iisiiiisii", 
            $subject['shs_strand_id'], $subject['shs_grade_level_id'], $semester_str, $subject['branch_id'], $current_ay_id,
            $subject['shs_strand_id'], $subject['shs_grade_level_id'], $semester_str, $subject['branch_id'], $current_ay_id);
    }
    $count_query->execute();
    $counts = $count_query->get_result()->fetch_assoc();
    
    $subject['section_count'] = $counts['section_count'] ?? 0;
    $subject['total_students'] = $counts['total_students'] ?? 0;
    $subject['semester_str'] = $semester_str;
    $teacher_subjects[] = $subject;
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
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
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px;
        background-color: #f8f9fa;
    }

    /* --- SUBJECT CARD STYLES --- */
    .subject-card {
        background: white;
        border-radius: 20px;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        cursor: pointer;
        overflow: hidden;
        border-top: 6px solid var(--maroon);
        height: 100%;
    }

    .subject-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(128, 0, 0, 0.1);
    }

    .subject-card .card-body { padding: 30px; }

    .subject-icon-box {
        width: 50px;
        height: 50px;
        background: rgba(128, 0, 0, 0.05);
        color: var(--maroon);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .stat-badge-light {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        transition: 0.3s;
    }
    .subject-card:hover .stat-badge-light { background: #fff; border: 1px solid #eee; }

    .card-footer-custom {
        background: #fcfcfc;
        padding: 15px;
        text-align: center;
        border-top: 1px solid #f1f1f1;
        font-weight: 600;
        font-size: 0.8rem;
        color: #888;
    }

    .program-badge {
        background: linear-gradient(135deg, #003366 0%, #004080 100%);
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
    }

    .semester-badge {
        background: #f0f0f0;
        color: #666;
        padding: 5px 10px;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    /* Staggered Animations */
    <?php for($i=1; $i<=12; $i++): ?>
    .delay-<?php echo $i; ?> { animation-delay: <?php echo $i * 0.1; ?>s; }
    <?php endfor; ?>

    /* Mobile Logic */
    @media (max-width: 576px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-calendar-check-fill me-2"></i>Attendance Tracking</h4>
        <p class="text-muted small mb-0">Select a subject to manage daily student attendance</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4 shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    <div id="alertContainer"></div>

    <div class="row">
        <?php if (count($teacher_subjects) == 0): ?>
        <div class="col-12 animate__animated animate__fadeIn">
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                <i class="bi bi-calendar-x display-1 text-muted opacity-25"></i>
                <h5 class="mt-3 text-muted">No subjects assigned for attendance.</h5>
                <p class="small text-muted">Contact your Branch Admin to get assigned to subjects.</p>
            </div>
        </div>
        <?php else: ?>
        
        <?php 
        $counter = 1;
        foreach ($teacher_subjects as $subject): 
            $subject_id = $subject['subject_id'];
            $subject_code = $subject['subject_code'];
            $subject_title = $subject['subject_title'];
            $units = $subject['units'];
            $semester = $subject['semester'];
            $section_count = $subject['section_count'] ?? 0;
            $total_students = $subject['total_students'] ?? 0;
            $program = $subject['program_name'] ?? 'General';
            $branch = $subject['branch_name'] ?? 'General';
        ?>
        <div class="col-md-6 col-lg-4 mb-4 animate__animated animate__zoomIn delay-<?php echo $counter; ?>">
            <div class="subject-card" onclick="viewAttendanceSections(<?php echo $subject_id; ?>)">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="subject-icon-box">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-muted border px-3 py-2 small fw-bold d-block mb-1">
                                <i class="bi bi-building me-1"></i> <?php echo htmlspecialchars($branch); ?>
                            </span>
                            <span class="semester-badge">
                                <?php echo htmlspecialchars($semester); ?> Sem
                            </span>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-1" style="color: var(--blue);">
                        <?php echo htmlspecialchars($subject_code); ?>
                    </h5>
                    <p class="text-muted mb-2" style="font-size: 0.9rem; min-height: 40px; line-height: 1.4;">
                        <?php echo htmlspecialchars($subject_title); ?>
                    </p>
                    
                    <div class="mb-3">
                        <span class="program-badge">
                            <i class="bi bi-mortarboard me-1"></i> <?php echo htmlspecialchars($program); ?>
                        </span>
                        <span class="badge bg-warning text-dark ms-1"><?php echo $units; ?> units</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stat-badge-light">
                                <h4 class="mb-0 fw-bold" style="color: var(--maroon);"><?php echo $section_count; ?></h4>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.6rem; letter-spacing: 0.5px;">Sections</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-badge-light">
                                <h4 class="mb-0 fw-bold" style="color: var(--blue);"><?php echo $total_students; ?></h4>
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.6rem; letter-spacing: 0.5px;">Students</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer-custom">
                    <i class="bi bi-arrow-right-circle me-2 text-maroon"></i> Take Attendance
                </div>
            </div>
        </div>
        <?php 
            $counter++;
            endforeach; 
        ?>
        
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function viewAttendanceSections(subjectId) {
    window.location.href = 'attendance_sections.php?subject_id=' + subjectId;
}
</script>
</body>
</html>