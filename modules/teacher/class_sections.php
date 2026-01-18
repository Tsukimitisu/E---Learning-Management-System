<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$subject_code = $_GET['subject'] ?? '';
$teacher_id = $_SESSION['user_id'];

if (empty($subject_code)) {
    header('Location: my_classes.php');
    exit();
}

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$subject_code = urldecode($subject_code);
$page_title = "Sections - " . $subject_code;

$subject_info_query = "
    SELECT DISTINCT
        COALESCE(s.subject_code, c.course_code, CONCAT('CLASS-', cl.id)) as subject_code,
        COALESCE(s.subject_title, c.title, 'Untitled Class') as subject_title
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN courses c ON cl.course_id = c.id
    WHERE cl.teacher_id = $teacher_id 
    AND COALESCE(s.subject_code, c.course_code, CONCAT('CLASS-', cl.id)) = ?
    LIMIT 1
";

$stmt = $conn->prepare($subject_info_query);
$stmt->bind_param("s", $subject_code);
$stmt->execute();
$subject_info = $stmt->get_result()->fetch_assoc();

if (!$subject_info) {
    header('Location: my_classes.php');
    exit();
}

$sections_query = "
    SELECT 
        cl.id, cl.section_name, cl.room, cl.schedule, cl.max_capacity, cl.current_enrolled,
        b.name as branch_name, COUNT(DISTINCT e.student_id) as enrolled_count
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN courses c ON cl.course_id = c.id
    LEFT JOIN branches b ON cl.branch_id = b.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    WHERE cl.teacher_id = ? 
    AND COALESCE(s.subject_code, c.course_code, CONCAT('CLASS-', cl.id)) = ?
    GROUP BY cl.id
    ORDER BY cl.section_name
";

$stmt = $conn->prepare($sections_query);
$stmt->bind_param("is", $teacher_id, $subject_code);
$stmt->execute();
$sections_result = $stmt->get_result();

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- SECTION CARD UI --- */
    .section-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        overflow: hidden; height: 100%; display: flex; flex-direction: column;
    }
    .section-card:hover { transform: translateY(-8px); box-shadow: 0 15px 30px rgba(0, 51, 102, 0.1); }

    /* --- ACCURATE PROGRESS BAR --- */
    .progress-container {
        background-color: #e9ecef; border-radius: 10px; height: 12px;
        width: 100%; overflow: hidden; margin: 10px 0; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
    }
    .progress-fill { height: 100%; border-radius: 10px; transition: width 1s ease-in-out; }

    .btn-enter-section { 
        background-color: var(--blue); color: white; border-radius: 10px; 
        font-weight: 700; padding: 12px; transition: 0.3s; border: none; 
        text-align: center; text-decoration: none; 
    }
    .btn-enter-section:hover { background-color: #002244; color: white; transform: scale(1.02); }

    /* Breadcrumbs */
    .breadcrumb-modern { background: transparent; padding: 0; margin-bottom: 5px; }
    .breadcrumb-item { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    .breadcrumb-item a { color: var(--maroon); text-decoration: none; }
    .breadcrumb-item + .breadcrumb-item::before { content: "›"; color: #ccc; font-size: 1.2rem; vertical-align: middle; }

    @media (max-width: 576px) { .header-fixed-part { padding: 15px; } .body-scroll-part { padding: 15px; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-modern">
                    <li class="breadcrumb-item"><a href="my_classes.php">My Classes</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($subject_info['subject_code']); ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <?php echo htmlspecialchars($subject_info['subject_title']); ?>
            </h4>
        </div>
        <a href="my_classes.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<!-- Part 2: Scrollable Content Area -->
<div class="body-scroll-part">
    
    <?php if ($sections_result->num_rows == 0): ?>
    <div class="alert bg-white border-0 shadow-sm p-4 text-center animate__animated animate__fadeIn">
        <i class="bi bi-exclamation-circle text-warning fs-1"></i>
        <h5 class="mt-3">No active sections found for this subject.</h5>
    </div>
    <?php else: ?>

    <div class="row g-4">
        <?php 
        $counter = 1;
        while ($section = $sections_result->fetch_assoc()): 
            $max = (int)$section['max_capacity'];
            $current = (int)$section['enrolled_count'];
            $percentage = ($max > 0) ? ($current / $max) * 100 : 0;
            
            if ($percentage >= 100) {
                $bar_color = '#dc3545'; $badge_bg = 'bg-danger'; $status_text = 'Full';
            } elseif ($percentage >= 85) {
                $bar_color = '#ffc107'; $badge_bg = 'bg-warning text-dark'; $status_text = 'Near Limit';
            } else {
                $bar_color = '#28a745'; $badge_bg = 'bg-success'; $status_text = 'Available';
            }
        ?>
        <div class="col-md-6 col-lg-4 animate__animated animate__zoomIn">
            <div class="section-card" style="border-top: 5px solid <?php echo $bar_color; ?>;">
                <div class="p-4 flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($section['section_name'] ?: 'Section'); ?></h5>
                            <span class="small text-muted"><i class="bi bi-building me-1"></i><?php echo htmlspecialchars($section['branch_name'] ?: 'N/A'); ?></span>
                        </div>
                        <span class="badge <?php echo $badge_bg; ?> rounded-pill small px-3"><?php echo $status_text; ?></span>
                    </div>

                    <div class="mb-2 small text-muted"><i class="bi bi-door-closed me-2 text-maroon"></i><strong>Room:</strong> <?php echo htmlspecialchars($section['room'] ?: 'TBD'); ?></div>
                    <div class="mb-4 small text-muted"><i class="bi bi-calendar3 me-2 text-maroon"></i><strong>Schedule:</strong> <?php echo htmlspecialchars($section['schedule'] ?: 'TBD'); ?></div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="fw-bold text-muted small text-uppercase" style="font-size: 0.65rem;">Enrollment Progress</small>
                            <small class="fw-bold" style="color: var(--blue);"><?php echo $current; ?> / <?php echo $max; ?></small>
                        </div>
                        <div class="progress-container">
                            <div class="progress-fill" style="width: <?php echo min($percentage, 100); ?>%; background-color: <?php echo $bar_color; ?>;"></div>
                        </div>
                        <div class="text-end"><small class="text-muted" style="font-size: 0.7rem;"><?php echo round($percentage); ?>% Capacity Used</small></div>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="classroom.php?id=<?php echo $section['id']; ?>" class="btn-enter-section shadow-sm">
                            <i class="bi bi-door-open me-2"></i> Enter Classroom
                        </a>
                    </div>
                </div>
                <div class="bg-light p-2 text-center border-top"><small class="text-muted fw-bold">ID: <?php echo $section['id']; ?></small></div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- BROUGHT BACK: ANALYTICS OVERVIEW -->
    <div class="bg-white rounded-4 shadow-sm p-4 mt-5 animate__animated animate__fadeInUp">
        <h6 class="fw-bold text-uppercase small opacity-50 mb-4" style="letter-spacing: 1.5px;">Analytics Overview</h6>
        <?php
        $sections_result->data_seek(0);
        $t_sec = 0; $t_stu = 0; $t_cap = 0;
        while ($s = $sections_result->fetch_assoc()) { 
            $t_sec++; 
            $t_stu += (int)$s['enrolled_count']; 
            $t_cap += (int)$s['max_capacity']; 
        }
        $util = ($t_cap > 0) ? round(($t_stu / $t_cap) * 100) : 0;
        ?>
        <div class="row text-center g-4">
            <div class="col-md-3 border-end"><h3><?php echo $t_sec; ?></h3><small class="text-muted fw-bold">Sections</small></div>
            <div class="col-md-3 border-end"><h3><?php echo $t_stu; ?></h3><small class="text-muted fw-bold">Students</small></div>
            <div class="col-md-3 border-end"><h3><?php echo $t_cap; ?></h3><small class="text-muted fw-bold">Capacity</small></div>
            <div class="col-md-3"><h3><?php echo $util; ?>%</h3><small class="text-muted fw-bold">Utilization</small></div>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
</body>
</html>