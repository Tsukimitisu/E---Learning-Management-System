<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Dashboard";
$student_id = $_SESSION['user_id'];

// Backend logic 
$student_info = $conn->query("SELECT s.student_no, c.course_code, c.title as course_title FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.user_id = $student_id")->fetch_assoc();
$enrolled_classes = $conn->query("SELECT e.id as enrollment_id, e.status, cl.id as class_id, cl.room, c.course_code, c.title as course_title, CONCAT(up.first_name, ' ', up.last_name) as teacher_name, b.name as branch_name, (SELECT COUNT(*) FROM learning_materials WHERE class_id = cl.id) as materials_count, g.final_grade, g.remarks FROM enrollments e INNER JOIN classes cl ON e.class_id = cl.id INNER JOIN courses c ON cl.course_id = c.id INNER JOIN branches b ON c.branch_id = b.id LEFT JOIN users u ON cl.teacher_id = u.id LEFT JOIN user_profiles up ON u.id = up.user_id LEFT JOIN grades g ON g.student_id = $student_id AND g.class_id = cl.id WHERE e.student_id = $student_id ORDER BY e.created_at DESC");

// Stats logic 
$stats = ['enrolled_classes' => 0, 'pending_enrollments' => 0, 'completed_classes' => 0, 'average_grade' => 0];
$result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE student_id = $student_id AND status = 'approved'");
if ($row = $result->fetch_assoc()) { $stats['enrolled_classes'] = $row['count']; }
$result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE student_id = $student_id AND status = 'pending'");
if ($row = $result->fetch_assoc()) { $stats['pending_enrollments'] = $row['count']; }
$result = $conn->query("SELECT COUNT(*) as count FROM grades WHERE student_id = $student_id AND remarks = 'PASSED'");
if ($row = $result->fetch_assoc()) { $stats['completed_classes'] = $row['count']; }
$result = $conn->query("SELECT AVG(final_grade) as avg FROM grades WHERE student_id = $student_id AND final_grade > 0");
if ($row = $result->fetch_assoc()) { $stats['average_grade'] = $row['avg'] ? round($row['avg'], 2) : 0; }

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<div class="animate__animated animate__fadeIn">
    <!-- Welcome Banner -->
    <div class="p-4 rounded-4 mb-4 text-white shadow-lg animate__animated animate__slideInDown" style="background: linear-gradient(135deg, var(--maroon) 0%, #500000 100%);">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-1">Hello, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h2>
                <p class="mb-0 opacity-75"><i class="bi bi-mortarboard me-2"></i><?php echo htmlspecialchars($student_info['course_title'] ?? 'N/A'); ?></p>
                <small class="opacity-50">Student ID: <?php echo htmlspecialchars($student_info['student_no'] ?? 'N/A'); ?></small>
            </div>
            <div class="col-md-4 text-end d-none d-md-block">
                <i class="bi bi-person-workspace display-2 opacity-25"></i>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-5">
        <div class="col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <div class="bg-white p-3 rounded-3 shadow-sm d-flex align-items-center gap-3">
                <div class="avatar-sm bg-light text-primary rounded-2 p-2 fs-3"><i class="bi bi-book"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $stats['enrolled_classes']; ?></h4><small class="text-muted">Classes</small></div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="bg-white p-3 rounded-3 shadow-sm d-flex align-items-center gap-3">
                <div class="avatar-sm bg-light text-warning rounded-2 p-2 fs-3"><i class="bi bi-clock"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $stats['pending_enrollments']; ?></h4><small class="text-muted">Pending</small></div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <div class="bg-white p-3 rounded-3 shadow-sm d-flex align-items-center gap-3">
                <div class="avatar-sm bg-light text-success rounded-2 p-2 fs-3"><i class="bi bi-check2-circle"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $stats['completed_classes']; ?></h4><small class="text-muted">Completed</small></div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
            <div class="bg-white p-3 rounded-3 shadow-sm d-flex align-items-center gap-3">
                <div class="avatar-sm bg-light text-info rounded-2 p-2 fs-3"><i class="bi bi-graph-up"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $stats['average_grade'] ?: '0.00'; ?></h4><small class="text-muted">Avg Grade</small></div>
            </div>
        </div>
    </div>

    <!-- My Classes Grid -->
    <h5 class="fw-bold mb-3"><i class="bi bi-collection-play-fill me-2 text-maroon"></i>My Current Classes</h5>
    <div class="row g-4 mb-5">
        <?php 
        $enrolled_classes->data_seek(0);
        while ($class = $enrolled_classes->fetch_assoc()): 
            if ($class['status'] == 'approved'): ?>
            <div class="col-lg-6 animate__animated animate__zoomIn">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden" style="border-left: 5px solid var(--maroon) !important;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <span class="badge bg-light text-maroon mb-2"><?php echo htmlspecialchars($class['course_code']); ?></span>
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($class['course_title']); ?></h5>
                            </div>
                            <?php if ($class['final_grade']): ?>
                                <div class="avatar-sm rounded-circle border border-maroon text-maroon d-flex align-items-center justify-content-center fw-bold" style="width:50px; height:50px; font-size:0.85rem;">
                                    <?php echo number_format($class['final_grade'], 2); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small mb-3"><i class="bi bi-person-badge me-2"></i>Prof. <?php echo htmlspecialchars($class['teacher_name']); ?></p>
                        <div class="row g-2 mb-4">
                            <div class="col-6 small text-muted"><i class="bi bi-door-closed me-2"></i><?php echo htmlspecialchars($class['room']); ?></div>
                            <div class="col-6 small text-muted text-end"><i class="bi bi-building me-2"></i><?php echo htmlspecialchars($class['branch_name']); ?></div>
                        </div>
                        <a href="view_class.php?id=<?php echo $class['class_id']; ?>" class="btn btn-maroon w-100 py-2 fw-bold shadow-sm">Enter Classroom</a>
                    </div>
                </div>
            </div>
        <?php endif; endwhile; ?>
    </div>

    <!-- Grade Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="fw-bold mb-0 text-blue">Academic Grade Summary</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Code</th><th>Subject Title</th><th class="text-center">Grade</th><th class="text-center">Remarks</th></tr>
                </thead>
                <tbody>
                    <?php 
                    $enrolled_classes->data_seek(0);
                    while ($class = $enrolled_classes->fetch_assoc()): 
                        if ($class['final_grade']): ?>
                    <tr>
                        <td class="fw-bold text-maroon"><?php echo htmlspecialchars($class['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($class['course_title']); ?></td>
                        <td class="text-center fw-bold"><?php echo number_format($class['final_grade'], 2); ?></td>
                        <td class="text-center"><span class="badge rounded-pill <?php echo $class['remarks'] == 'PASSED' ? 'bg-success' : 'bg-danger'; ?>"><?php echo htmlspecialchars($class['remarks']); ?></span></td>
                    </tr>
                    <?php endif; endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>