<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Classes";
$teacher_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$subjects_query = "
    SELECT 
        COALESCE(s.subject_code, c.course_code, CONCAT('CLASS-', cl.id)) as subject_code,
        COALESCE(s.subject_title, c.title, 'Untitled Class') as subject_title,
        COALESCE(s.id, c.id, cl.id) as subject_id,
        COUNT(DISTINCT cl.id) as section_count,
        SUM(cl.current_enrolled) as total_students,
        MAX(b.name) as branch_name
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN courses c ON cl.course_id = c.id
    LEFT JOIN branches b ON cl.branch_id = b.id
    WHERE cl.teacher_id = ?
    GROUP BY 
        COALESCE(s.subject_code, c.course_code, CONCAT('CLASS-', cl.id)),
        COALESCE(s.subject_title, c.title, 'Untitled Class')
    ORDER BY subject_code
";

$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects_result = $stmt->get_result();

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

    /* Staggered Animation delays */
    <?php for($i=1; $i<=12; $i++): ?>
        .delay-<?php echo $i; ?> { animation-delay: <?php echo $i * 0.1; ?>s; }
    <?php endfor; ?>

    /* Mobile Logic */
    @media (max-width: 576px) {
        .header-fixed-part { flex-direction: column; gap: 15px; text-align: center; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>


<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);">My Classes</h4>
        <p class="text-muted small mb-0">Manage and oversee your assigned subjects and sections</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4 shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
</div>

<div class="body-scroll-part">
    
    <div class="row">
        <?php if ($subjects_result->num_rows == 0): ?>
        <div class="col-12 animate__animated animate__fadeIn">
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                <i class="bi bi-folder-x display-1 text-muted opacity-25"></i>
                <h5 class="mt-3 text-muted">No assignments found.</h5>
                <p class="small text-muted">Contact the registrar if you believe this is an error.</p>
            </div>
        </div>
        <?php else: ?>
        
        <?php 
        $counter = 1;
        while ($subject = $subjects_result->fetch_assoc()): 
            $subject_code = $subject['subject_code'];
            $subject_title = $subject['subject_title'];
            $section_count = $subject['section_count'];
            $total_students = $subject['total_students'] ?? 0;
            $branch = $subject['branch_name'] ?? 'General';
        ?>
        <div class="col-md-6 col-lg-4 mb-4 animate__animated animate__zoomIn delay-<?php echo $counter; ?>">
            <div class="subject-card" onclick="viewSections('<?php echo urlencode($subject_code); ?>')">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="subject-icon-box">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <span class="badge bg-light text-muted border px-3 py-2 small fw-bold">
                            <i class="bi bi-building me-1"></i> <?php echo htmlspecialchars($branch); ?>
                        </span>
                    </div>

                    <h5 class="fw-bold mb-1" style="color: var(--blue);">
                        <?php echo htmlspecialchars($subject_code); ?>
                    </h5>
                    <p class="text-muted mb-4" style="font-size: 0.9rem; min-height: 40px; line-height: 1.4;">
                        <?php echo htmlspecialchars($subject_title); ?>
                    </p>

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
                    <i class="bi bi-arrow-right-circle me-2 text-maroon"></i> View Section Details
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

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED --- -->
<script>
function viewSections(subjectCode) {
    // Original Function Logic
    window.location.href = 'class_sections.php?subject=' + encodeURIComponent(subjectCode);
}
</script>
</body>
</html>