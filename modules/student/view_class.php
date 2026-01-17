<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$class_id = (int)($_GET['id'] ?? 0);
$student_id = $_SESSION['user_id'];

// Backend Logic:
$verify = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND class_id = ? AND status = 'approved'");
$verify->bind_param("ii", $student_id, $class_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) { header('Location: dashboard.php'); exit(); }

$class_info = $conn->query("SELECT cl.room, c.course_code, c.title as course_title, CONCAT(up.first_name, ' ', up.last_name) as teacher_name, b.name as branch_name FROM classes cl INNER JOIN courses c ON cl.course_id = c.id INNER JOIN branches b ON c.branch_id = b.id LEFT JOIN users u ON cl.teacher_id = u.id LEFT JOIN user_profiles up ON u.id = up.user_id WHERE cl.id = $class_id")->fetch_assoc();
$materials = $conn->query("SELECT id, file_path, uploaded_at FROM learning_materials WHERE class_id = $class_id ORDER BY uploaded_at DESC");
$grade = $conn->query("SELECT midterm, final, final_grade, remarks FROM grades WHERE student_id = $student_id AND class_id = $class_id")->fetch_assoc();

$page_title = "Classroom - " . $class_info['course_code'];

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<div class="animate__animated animate__fadeIn">
    <!-- Blue Classroom Header  -->
    <div class="p-4 rounded-4 mb-4 text-white shadow-lg d-flex flex-column flex-md-row justify-content-between align-items-center text-center text-md-start" style="background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%);">
        <div class="mb-3 mb-md-0">
            <!-- Placeholder Visibility -->
            <span class="badge bg-white text-maroon mb-2 px-3 py-2 fw-bold animate__animated animate__bounceIn"><?php echo htmlspecialchars($class_info['course_code']); ?></span>
            <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($class_info['course_title']); ?></h2>
            <p class="mb-0 opacity-75 small"><i class="bi bi-building me-2"></i><?php echo htmlspecialchars($class_info['branch_name']); ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-light btn-sm fw-bold px-4 py-2 shadow-sm rounded-pill">
            <i class="bi bi-arrow-left me-2"></i>Back to Portal
        </a>
    </div>

    <div class="row g-4">
        <!-- Sidebar Info -->
        <div class="col-lg-4">
            <?php if ($grade): ?>
            <div class="bg-white rounded-4 shadow-sm p-4 text-center mb-4 border-top border-maroon border-5 animate__animated animate__fadeInLeft">
                <p class="text-muted small fw-bold text-uppercase mb-3">Academic Performance</p>
                <div class="rounded-circle border border-maroon border-4 d-flex align-items-center justify-content-center mx-auto mb-3 animate__animated animate__zoomIn" style="width:100px; height:100px; color:var(--maroon); font-size:1.8rem; font-weight:800;">
                    <?php echo number_format($grade['final_grade'], 2); ?>
                </div>
                <span class="badge rounded-pill px-4 py-2 <?php echo $grade['remarks'] == 'PASSED' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $grade['remarks']; ?></span>
                <div class="row mt-4 pt-3 border-top g-0">
                    <div class="col-6 border-end text-center">
                        <small class="text-muted d-block">Midterm</small>
                        <span class="fw-bold text-dark"><?php echo number_format($grade['midterm'], 2); ?></span>
                    </div>
                    <div class="col-6 text-center">
                        <small class="text-muted d-block">Final</small>
                        <span class="fw-bold text-dark"><?php echo number_format($grade['final'], 2); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-4 shadow-sm p-4 animate__animated animate__fadeInLeft" style="animation-delay: 0.2s;">
                <h6 class="fw-bold mb-4 text-blue border-bottom pb-2"><i class="bi bi-info-circle-fill me-2"></i>Class Information</h6>
                <div class="mb-3">
                    <label class="text-muted small d-block mb-1 fw-bold">INSTRUCTOR</label>
                    <span class="fw-bold text-dark">Prof. <?php echo htmlspecialchars($class_info['teacher_name']); ?></span>
                </div>
                <div class="mb-0">
                    <label class="text-muted small d-block mb-1 fw-bold">ROOM ASSIGNMENT</label>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($class_info['room']); ?></span>
                </div>
            </div>
        </div>

        <!-- Materials List -->
        <div class="col-lg-8">
            <div class="bg-white rounded-4 shadow-sm p-4 animate__animated animate__fadeInUp">
                <h5 class="fw-bold mb-4 text-blue border-bottom pb-2"><i class="bi bi-file-earmark-arrow-down-fill me-2"></i>Learning Materials</h5>
                <?php if ($materials->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                    <?php while ($material = $materials->fetch_assoc()): 
                        $filename = basename($material['file_path']);
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $icon = 'bi-file-earmark'; $color = 'text-secondary';
                        if ($ext == 'pdf') { $icon = 'bi-file-earmark-pdf-fill'; $color = 'text-danger'; }
                        elseif ($ext == 'pptx' || $ext == 'ppt') { $icon = 'bi-file-earmark-ppt-fill'; $color = 'text-warning'; }
                    ?>
                        <a href="../../uploads/materials/<?php echo htmlspecialchars($filename); ?>" target="_blank" class="list-group-item list-group-item-action border rounded-3 mb-2 p-3 transition shadow-sm-hover animate__animated animate__fadeInRight">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="bi <?php echo $icon; ?> <?php echo $color; ?> fs-3 me-3"></i>
                                    <div>
                                        <div class="fw-bold text-dark text-break" style="font-size:0.9rem;"><?php echo htmlspecialchars($filename); ?></div>
                                        <small class="text-muted">Uploaded on <?php echo date('M d, Y', strtotime($material['uploaded_at'])); ?></small>
                                    </div>
                                </div>
                                <i class="bi bi-download text-muted"></i>
                            </div>
                        </a>
                    <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 opacity-50">
                        <i class="bi bi-folder-x display-1 d-block mb-2"></i>
                        <p>No learning materials available for this class.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>