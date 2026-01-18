<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Attendance Sections";
$teacher_id = $_SESSION['user_id'];
$subject_id = (int)($_GET['subject_id'] ?? 0);

if (!$subject_id) {
    header('Location: attendance.php');
    exit();
}

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Verify teacher is assigned to this subject and get subject info
$subject_query = $conn->prepare("
    SELECT cs.*, tsa.branch_id, b.name as branch_name,
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(pyl.year_name, sgl.grade_name) as year_level
    FROM teacher_subject_assignments tsa
    INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
    INNER JOIN branches b ON tsa.branch_id = b.id
    LEFT JOIN programs p ON cs.program_id = p.id
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON cs.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    WHERE tsa.teacher_id = ? AND cs.id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
");
$subject_query->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$subject_query->execute();
$subject = $subject_query->get_result()->fetch_assoc();

if (!$subject) {
    header('Location: attendance.php');
    exit();
}

// Convert semester number to string format
$semester_map = [1 => '1st', 2 => '2nd', 3 => 'summer'];
$semester_str = $semester_map[$subject['semester']] ?? '1st';

// Get sections for this subject's year level and semester
$sections_sql = "
    SELECT s.*, 
           CONCAT(up.first_name, ' ', up.last_name) as adviser_name,
           (SELECT COUNT(*) FROM section_students ss WHERE ss.section_id = s.id AND ss.status = 'active') as student_count
    FROM sections s
    LEFT JOIN users u ON s.adviser_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE s.branch_id = ?
    AND s.academic_year_id = ?
    AND s.semester = ?
    AND s.is_active = 1
";

// Add program/strand filter based on subject type
if (!empty($subject['program_id'])) {
    $sections_sql .= " AND s.program_id = ? AND s.year_level_id = ?";
    $sections_sql .= " ORDER BY s.section_name";
    $sections_query = $conn->prepare($sections_sql);
    $sections_query->bind_param("iisii", 
        $subject['branch_id'], 
        $current_ay_id, 
        $semester_str,
        $subject['program_id'],
        $subject['year_level_id']
    );
} else {
    $sections_sql .= " AND s.shs_strand_id = ? AND s.shs_grade_level_id = ?";
    $sections_sql .= " ORDER BY s.section_name";
    $sections_query = $conn->prepare($sections_sql);
    $sections_query->bind_param("iisii", 
        $subject['branch_id'], 
        $current_ay_id, 
        $semester_str,
        $subject['shs_strand_id'],
        $subject['shs_grade_level_id']
    );
}

$sections_query->execute();
$sections_result = $sections_query->get_result();

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
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

    .subject-info-card {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        color: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 25px;
    }

    .section-card {
        background: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        cursor: pointer;
        overflow: hidden;
        height: 100%;
    }

    .section-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(40, 167, 69, 0.15);
        border: 2px solid #28a745;
    }

    .section-card .card-body { padding: 25px; }

    .section-icon-box {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        color: white;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .stat-pill {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 10px;
        text-align: center;
        flex: 1;
    }

    .card-footer-custom {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        padding: 12px;
        text-align: center;
        color: white;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .info-badge {
        background: rgba(255,255,255,0.2);
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        margin-right: 10px;
    }

    <?php for($i=1; $i<=12; $i++): ?>
        .delay-<?php echo $i; ?> { animation-delay: <?php echo $i * 0.1; ?>s; }
    <?php endfor; ?>

    @media (max-width: 576px) {
        .header-fixed-part { flex-direction: column; gap: 15px; text-align: center; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>


<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);">
            <i class="bi bi-calendar-check me-2"></i><?php echo htmlspecialchars($subject['subject_code']); ?> - Attendance
        </h4>
        <p class="text-muted small mb-0"><?php echo htmlspecialchars($subject['subject_title']); ?></p>
    </div>
    <a href="attendance.php" class="btn btn-outline-secondary btn-sm px-4 shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Attendance
    </a>
</div>

<div class="body-scroll-part">
    <!-- Subject Info Card -->
    <div class="subject-info-card animate__animated animate__fadeIn">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="fw-bold mb-2"><i class="bi bi-calendar-check me-2"></i><?php echo htmlspecialchars($subject['subject_code']); ?> - <?php echo htmlspecialchars($subject['subject_title']); ?></h5>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <span class="info-badge"><i class="bi bi-mortarboard me-1"></i> <?php echo htmlspecialchars($subject['program_name']); ?></span>
                    <span class="info-badge"><i class="bi bi-layers me-1"></i> <?php echo htmlspecialchars($subject['year_level']); ?></span>
                    <span class="info-badge"><i class="bi bi-calendar me-1"></i> <?php echo htmlspecialchars($subject['semester']); ?> Semester</span>
                    <span class="info-badge"><i class="bi bi-building me-1"></i> <?php echo htmlspecialchars($subject['branch_name']); ?></span>
                    <span class="info-badge"><i class="bi bi-star me-1"></i> <?php echo $subject['units']; ?> Units</span>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <h2 class="mb-0 fw-bold"><?php echo $sections_result->num_rows; ?></h2>
                <small class="opacity-75">Sections to Track</small>
            </div>
        </div>
    </div>

    <h5 class="mb-3"><i class="bi bi-collection text-primary me-2"></i>Select a Section to Take Attendance</h5>

    <div class="row">
        <?php if ($sections_result->num_rows == 0): ?>
        <div class="col-12 animate__animated animate__fadeIn">
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                <i class="bi bi-collection display-1 text-muted opacity-25"></i>
                <h5 class="mt-3 text-muted">No sections available.</h5>
                <p class="small text-muted">Sections will appear here once they are created by the Branch Admin.</p>
            </div>
        </div>
        <?php else: ?>
        
        <?php 
        $counter = 1;
        while ($section = $sections_result->fetch_assoc()): 
        ?>
        <div class="col-md-6 col-lg-4 mb-4 animate__animated animate__zoomIn delay-<?php echo $counter; ?>">
            <div class="section-card" onclick="openAttendance(<?php echo $section['id']; ?>, <?php echo $subject_id; ?>)">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="section-icon-box me-3">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0" style="color: var(--blue);"><?php echo htmlspecialchars($section['section_name']); ?></h5>
                            <?php if ($section['room']): ?>
                            <small class="text-muted"><i class="bi bi-door-open me-1"></i><?php echo htmlspecialchars($section['room']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($section['adviser_name']): ?>
                    <p class="mb-3 text-muted small">
                        <i class="bi bi-person-badge me-1"></i> Adviser: <?php echo htmlspecialchars($section['adviser_name']); ?>
                    </p>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <div class="stat-pill">
                            <h4 class="mb-0 fw-bold" style="color: var(--maroon);"><?php echo $section['student_count']; ?></h4>
                            <small class="text-muted" style="font-size: 0.7rem;">Students</small>
                        </div>
                        <div class="stat-pill">
                            <h4 class="mb-0 fw-bold" style="color: var(--blue);"><?php echo $section['max_capacity']; ?></h4>
                            <small class="text-muted" style="font-size: 0.7rem;">Capacity</small>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="progress" style="height: 6px;">
                            <?php 
                            $percentage = $section['max_capacity'] > 0 ? ($section['student_count'] / $section['max_capacity']) * 100 : 0;
                            $color = $percentage > 80 ? 'danger' : ($percentage > 50 ? 'warning' : 'success');
                            ?>
                            <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <small class="text-muted" style="font-size: 0.7rem;"><?php echo round($percentage); ?>% filled</small>
                    </div>
                </div>
                <div class="card-footer-custom">
                    <i class="bi bi-clipboard-check me-2"></i> Take Attendance
                </div>
            </div>
        </div>
        <?php 
            $counter++;
            endwhile; 
        ?>
        
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function openAttendance(sectionId, subjectId) {
    window.location.href = 'attendance_sheet.php?section_id=' + sectionId + '&subject_id=' + subjectId;
}
</script>
</body>
</html>
