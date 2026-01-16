<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Dashboard";
$student_id = $_SESSION['user_id'];

// Fetch student info
$student_info = $conn->query("
    SELECT s.student_no, c.course_code, c.title as course_title
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    WHERE s.user_id = $student_id
")->fetch_assoc();

// Fetch enrolled classes
$enrolled_classes = $conn->query("
    SELECT 
        e.id as enrollment_id,
        e.status,
        cl.id as class_id,
        cl.room,
        c.course_code,
        c.title as course_title,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
        b.name as branch_name,
        (SELECT COUNT(*) FROM learning_materials WHERE class_id = cl.id) as materials_count,
        g.final_grade,
        g.remarks
    FROM enrollments e
    INNER JOIN classes cl ON e.class_id = cl.id
    INNER JOIN courses c ON cl.course_id = c.id
    INNER JOIN branches b ON c.branch_id = b.id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN grades g ON g.student_id = $student_id AND g.class_id = cl.id
    WHERE e.student_id = $student_id
    ORDER BY e.created_at DESC
");

// Fetch statistics
$stats = [
    'enrolled_classes' => 0,
    'pending_enrollments' => 0,
    'completed_classes' => 0,
    'average_grade' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE student_id = $student_id AND status = 'approved'");
if ($row = $result->fetch_assoc()) {
    $stats['enrolled_classes'] = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE student_id = $student_id AND status = 'pending'");
if ($row = $result->fetch_assoc()) {
    $stats['pending_enrollments'] = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM grades WHERE student_id = $student_id AND remarks = 'PASSED'");
if ($row = $result->fetch_assoc()) {
    $stats['completed_classes'] = $row['count'];
}

$result = $conn->query("SELECT AVG(final_grade) as avg FROM grades WHERE student_id = $student_id AND final_grade > 0");
if ($row = $result->fetch_assoc()) {
    $stats['average_grade'] = $row['avg'] ? round($row['avg'], 2) : 0;
}

include '../../includes/header.php';
?>

<style>
.class-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid #800000;
}
.class-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
}
.grade-badge {
    font-size: 1.5rem;
    padding: 10px 20px;
}
</style>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0" style="color: #003366;">
                    <i class="bi bi-speedometer2"></i> Student Portal
                </h4>
                <small class="text-muted">
                    <?php echo htmlspecialchars($student_info['student_no'] ?? 'N/A'); ?> | 
                    <?php echo htmlspecialchars($student_info['course_title'] ?? 'No Course'); ?>
                </small>
            </div>
            <div>
                <span class="badge bg-success me-2 position-relative">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                          id="notificationBadge" style="display:none;">
                        <span id="notificationCount">0</span>
                    </span>
                </span>
                <span class="text-muted"><?php echo date('F d, Y'); ?></span>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <p><i class="bi bi-book"></i> Enrolled Classes</p>
                    <h3><?php echo $stats['enrolled_classes']; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <p><i class="bi bi-clock-history"></i> Pending</p>
                    <h3><?php echo $stats['pending_enrollments']; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <p><i class="bi bi-check-circle"></i> Completed</p>
                    <h3><?php echo $stats['completed_classes']; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <p><i class="bi bi-bar-chart"></i> Average Grade</p>
                    <h3><?php echo $stats['average_grade'] > 0 ? number_format($stats['average_grade'], 2) : '-'; ?></h3>
                </div>
            </div>
        </div>

        <!-- Enrolled Classes -->
        <div class="card shadow-sm mb-4">
            <div class="card-header" style="background-color: #800000; color: white;">
                <h5 class="mb-0"><i class="bi bi-book"></i> My Classes</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php 
                    $enrolled_classes->data_seek(0);
                    $has_classes = false;
                    while ($class = $enrolled_classes->fetch_assoc()): 
                        if ($class['status'] == 'approved') {
                            $has_classes = true;
                    ?>
                    <div class="col-md-6 mb-3">
                        <div class="card class-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="card-title mb-1" style="color: #800000;">
                                            <?php echo htmlspecialchars($class['course_code']); ?>
                                        </h5>
                                        <p class="card-text text-muted mb-2">
                                            <?php echo htmlspecialchars($class['course_title']); ?>
                                        </p>
                                    </div>
                                    <?php if ($class['final_grade']): ?>
                                    <span class="grade-badge badge <?php echo $class['remarks'] == 'PASSED' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo number_format($class['final_grade'], 2); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($class['teacher_name']); ?><br>
                                        <i class="bi bi-door-closed"></i> <?php echo htmlspecialchars($class['room']); ?><br>
                                        <i class="bi bi-building"></i> <?php echo htmlspecialchars($class['branch_name']); ?><br>
                                        <i class="bi bi-file-earmark-pdf"></i> <?php echo $class['materials_count']; ?> Learning Materials
                                    </small>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="view_class.php?id=<?php echo $class['class_id']; ?>" class="btn btn-primary">
                                        <i class="bi bi-box-arrow-in-right"></i> View Classroom
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php 
                        }
                    endwhile; 
                    
                    if (!$has_classes):
                    ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> You are not enrolled in any classes yet.
                            <a href="enroll.php" class="alert-link">Click here to enroll</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Grades Summary -->
        <div class="card shadow-sm">
            <div class="card-header" style="background-color: #003366; color: white;">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> My Grades</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Midterm</th>
                                <th>Final</th>
                                <th>Final Grade</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $enrolled_classes->data_seek(0);
                            $has_grades = false;
                            while ($class = $enrolled_classes->fetch_assoc()): 
                                if ($class['final_grade']):
                                    $has_grades = true;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($class['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($class['course_title']); ?></td>
                                <td>
                                    <?php 
                                    $grade_info = $conn->query("SELECT midterm FROM grades WHERE student_id = $student_id AND class_id = {$class['class_id']}")->fetch_assoc();
                                    echo $grade_info['midterm'] ? number_format($grade_info['midterm'], 2) : '-';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $grade_info = $conn->query("SELECT final FROM grades WHERE student_id = $student_id AND class_id = {$class['class_id']}")->fetch_assoc();
                                    echo $grade_info['final'] ? number_format($grade_info['final'], 2) : '-';
                                    ?>
                                </td>
                                <td><strong><?php echo number_format($class['final_grade'], 2); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $class['remarks'] == 'PASSED' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo htmlspecialchars($class['remarks']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php 
                                endif;
                            endwhile; 
                            
                            if (!$has_grades):
                            ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No grades available yet</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/notifications.js"></script>
</body>
</html>