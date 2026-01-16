<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Learning Materials";
$teacher_id = $_SESSION['user_id'];

// Fetch teacher's classes
$classes = $conn->query("
    SELECT 
        cl.id,
        cl.section_name,
        s.subject_code,
        s.subject_title
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE cl.teacher_id = $teacher_id
    ORDER BY s.subject_code
");

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">Learning Materials</h4>
                    <small class="text-muted">Upload and manage course materials</small>
                </div>
                <a href="dashboard.php" class="btn btn-minimal">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Class Selection -->
        <div class="minimal-card">
            <h5 class="section-title">Select Class</h5>
            <div class="row">
                <?php while ($class = $classes->fetch_assoc()): 
                    $subject_code = $class['subject_code'] ?? 'N/A';
                    $section_name = $class['section_name'] ?? 'N/A';
                    $subject_title = $class['subject_title'] ?? 'N/A';
                ?>
                <div class="col-md-6 mb-3">
                    <div class="card h-100" style="border-left: 4px solid var(--maroon);">
                        <div class="card-body">
                            <h6 class="card-title" style="color: var(--navy);">
                                <?php echo htmlspecialchars($subject_code); ?> - <?php echo htmlspecialchars($section_name); ?>
                            </h6>
                            <p class="card-text mb-2">
                                <small class="text-muted"><?php echo htmlspecialchars($subject_title); ?></small>
                            </p>
                            <div class="d-grid">
                                <a href="materials_list.php?class_id=<?php echo $class['id']; ?>" class="btn btn-primary-minimal">
                                    <i class="bi bi-folder-open"></i> Manage Materials
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>