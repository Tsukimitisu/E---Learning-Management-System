<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Classes";
$teacher_id = $_SESSION['user_id'];

// Fetch unique subjects/courses assigned to this teacher
// FIXED: Handle NULL values properly
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
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">My Classes</h4>
                    <small class="text-muted">Select a subject to view sections</small>
                </div>
                <a href="dashboard.php" class="btn btn-minimal">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div class="row">
            <?php if ($subjects_result->num_rows == 0): ?>
            <div class="col-12">
                <div class="minimal-card">
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> You have no classes assigned yet.
                    </div>
                </div>
            </div>
            <?php else: ?>
            
            <?php while ($subject = $subjects_result->fetch_assoc()): 
                $subject_code = $subject['subject_code'];
                $subject_title = $subject['subject_title'];
                $section_count = $subject['section_count'];
                $total_students = $subject['total_students'] ?? 0;
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm" style="border-left: 5px solid var(--maroon); cursor: pointer;" 
                     onclick="viewSections('<?php echo urlencode($subject_code); ?>')">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title mb-1" style="color: var(--navy); font-weight: 600;">
                                    <?php echo htmlspecialchars($subject_code); ?>
                                </h5>
                                <p class="text-muted mb-0" style="font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($subject_title); ?>
                                </p>
                            </div>
                            <i class="bi bi-chevron-right fs-4" style="color: var(--maroon);"></i>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-6">
                                <div class="text-center p-3" style="background-color: #f8f9fa; border-radius: 8px;">
                                    <h3 class="mb-1" style="color: var(--maroon);"><?php echo $section_count; ?></h3>
                                    <small class="text-muted">Sections</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3" style="background-color: #f8f9fa; border-radius: 8px;">
                                    <h3 class="mb-1" style="color: var(--navy);"><?php echo $total_students; ?></h3>
                                    <small class="text-muted">Students</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent text-center" style="border-top: 1px solid #e0e0e0;">
                        <small class="text-muted">
                            <i class="bi bi-box-arrow-in-right"></i> Click to view sections
                        </small>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewSections(subjectCode) {
    window.location.href = 'class_sections.php?subject=' + encodeURIComponent(subjectCode);
}
</script>
</body>
</html>