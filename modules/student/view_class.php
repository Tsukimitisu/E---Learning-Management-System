<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$class_id = (int)($_GET['id'] ?? 0);
$student_id = $_SESSION['user_id'];

if ($class_id == 0) {
    header('Location: dashboard.php');
    exit();
}

// Verify student is enrolled in this class
$verify = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND class_id = ? AND status = 'approved'");
$verify->bind_param("ii", $student_id, $class_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) {
    header('Location: dashboard.php');
    exit();
}

// Get class info
$class_info = $conn->query("
    SELECT 
        cl.room,
        c.course_code,
        c.title as course_title,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
        b.name as branch_name
    FROM classes cl
    INNER JOIN courses c ON cl.course_id = c.id
    INNER JOIN branches b ON c.branch_id = b.id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE cl.id = $class_id
")->fetch_assoc();

// Get learning materials
$materials = $conn->query("
    SELECT id, file_path, uploaded_at
    FROM learning_materials
    WHERE class_id = $class_id
    ORDER BY uploaded_at DESC
");

// Get grade
$grade = $conn->query("
    SELECT midterm, final, final_grade, remarks
    FROM grades
    WHERE student_id = $student_id AND class_id = $class_id
")->fetch_assoc();

$page_title = "Classroom - " . $class_info['course_code'];
include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0" style="color: #003366;">
                    <i class="bi bi-door-open"></i> <?php echo htmlspecialchars($class_info['course_code']); ?>
                </h4>
                <small class="text-muted"><?php echo htmlspecialchars($class_info['course_title']); ?></small>
            </div>
            <a href="dashboard.php" class="btn btn-sm btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="row">
            <!-- Class Info -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #800000; color: white;">
                        <i class="bi bi-info-circle"></i> Class Information
                    </div>
                    <div class="card-body">
                        <p><strong>Teacher:</strong><br><?php echo htmlspecialchars($class_info['teacher_name']); ?></p>
                        <p><strong>Room:</strong><br><?php echo htmlspecialchars($class_info['room']); ?></p>
                        <p><strong>Branch:</strong><br><?php echo htmlspecialchars($class_info['branch_name']); ?></p>
                    </div>
                </div>

                <?php if ($grade): ?>
                <div class="card shadow-sm mt-3">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <i class="bi bi-bar-chart"></i> My Grade
                    </div>
                    <div class="card-body text-center">
                        <h2 class="display-4" style="color: #800000;">
                            <?php echo number_format($grade['final_grade'], 2); ?>
                        </h2>
                        <p class="mb-2">
                            <strong>Midterm:</strong> <?php echo number_format($grade['midterm'], 2); ?><br>
                            <strong>Final:</strong> <?php echo number_format($grade['final'], 2); ?>
                        </p>
                        <span class="badge <?php echo $grade['remarks'] == 'PASSED' ? 'bg-success' : 'bg-danger'; ?> fs-5">
                            <?php echo htmlspecialchars($grade['remarks']); ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Learning Materials -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <i class="bi bi-file-earmark-pdf"></i> Learning Materials
                    </div>
                    <div class="card-body">
                        <?php if ($materials->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while ($material = $materials->fetch_assoc()): 
                                $filename = basename($material['file_path']);
                                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                
                                $icon_class = 'bi-file-earmark';
                                $icon_color = 'text-secondary';
                                
                                if ($extension == 'pdf') {
                                    $icon_class = 'bi-file-earmark-pdf';
                                    $icon_color = 'text-danger';
                                } elseif (in_array($extension, ['doc', 'docx'])) {
                                    $icon_class = 'bi-file-earmark-word';
                                    $icon_color = 'text-primary';
                                } elseif (in_array($extension, ['ppt', 'pptx'])) {
                                    $icon_class = 'bi-file-earmark-ppt';
                                    $icon_color = 'text-warning';
                                }
                            ?>
                            <a href="../../uploads/materials/<?php echo htmlspecialchars($filename); ?>" 
                               target="_blank" 
                               class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <i class="bi <?php echo $icon_class; ?> <?php echo $icon_color; ?> fs-4 me-2"></i>
                                        <span><?php echo htmlspecialchars($filename); ?></span>
                                    </div>
                                    <div>
                                        <small class="text-muted me-3">
                                            <?php echo date('M d, Y', strtotime($material['uploaded_at'])); ?>
                                        </small>
                                        <i class="bi bi-download"></i>
                                    </div>
                                </div>
                            </a>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No learning materials uploaded yet.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/notifications.js"></script>
</body>
</html>