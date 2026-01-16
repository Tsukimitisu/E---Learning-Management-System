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

$subject_code = urldecode($subject_code);
$page_title = "Sections - " . $subject_code;

// Get subject info - FIXED to handle all cases
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

// Get all sections for this subject - FIXED
$sections_query = "
    SELECT 
        cl.id,
        cl.section_name,
        cl.room,
        cl.schedule,
        cl.max_capacity,
        cl.current_enrolled,
        b.name as branch_name,
        COUNT(DISTINCT e.student_id) as enrolled_count
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
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">
                        <?php echo htmlspecialchars($subject_info['subject_code']); ?> - Sections
                    </h4>
                    <small class="text-muted"><?php echo htmlspecialchars($subject_info['subject_title']); ?></small>
                </div>
                <a href="my_classes.php" class="btn btn-minimal">
                    <i class="bi bi-arrow-left"></i> Back to Classes
                </a>
            </div>
        </div>

        <?php if ($sections_result->num_rows == 0): ?>
        <div class="minimal-card">
            <div class="alert alert-warning mb-0">
                <i class="bi bi-exclamation-triangle"></i> No sections found for this subject.
            </div>
        </div>
        <?php else: ?>

        <div class="row">
            <?php while ($section = $sections_result->fetch_assoc()): 
                $percentage = ($section['max_capacity'] > 0) ? 
                    ($section['enrolled_count'] / $section['max_capacity']) * 100 : 0;
                
                if ($percentage >= 100) {
                    $border_color = '#dc3545';
                    $status_badge = 'danger';
                    $status_text = 'Full';
                } elseif ($percentage >= 90) {
                    $border_color = '#ffc107';
                    $status_badge = 'warning';
                    $status_text = 'Almost Full';
                } else {
                    $border_color = '#28a745';
                    $status_badge = 'success';
                    $status_text = 'Available';
                }
                
                $section_name = $section['section_name'] ?: 'Unnamed Section';
                $room = $section['room'] ?: 'Not Set';
                $schedule = $section['schedule'] ?: 'Not Set';
                $branch_name = $section['branch_name'] ?: 'N/A';
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm" style="border-left: 5px solid <?php echo $border_color; ?>;">
                    <div class="card-body">
                        <!-- Section Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title mb-1" style="color: var(--maroon); font-weight: 600;">
                                    <?php echo htmlspecialchars($section_name); ?>
                                </h5>
                                <small class="text-muted">
                                    <i class="bi bi-building"></i> <?php echo htmlspecialchars($branch_name); ?>
                                </small>
                            </div>
                            <span class="badge bg-<?php echo $status_badge; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </div>

                        <!-- Section Details -->
                        <div class="mb-3">
                            <div class="row g-2">
                                <div class="col-12">
                                    <small class="text-muted">
                                        <i class="bi bi-door-closed"></i> 
                                        <strong>Room:</strong> <?php echo htmlspecialchars($room); ?>
                                    </small>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> 
                                        <strong>Schedule:</strong> <?php echo htmlspecialchars($schedule); ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Student Count -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">
                                    <i class="bi bi-people"></i> Students Enrolled
                                </small>
                                <strong style="color: var(--navy);">
                                    <?php echo $section['enrolled_count']; ?> / <?php echo $section['max_capacity']; ?>
                                </strong>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" 
                                     style="width: <?php echo min($percentage, 100); ?>%; background-color: <?php echo $border_color; ?>;"
                                     role="progressbar">
                                </div>
                            </div>
                            <small class="text-muted"><?php echo round($percentage); ?>% capacity</small>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <a href="classroom.php?id=<?php echo $section['id']; ?>" class="btn btn-primary-minimal">
                                <i class="bi bi-box-arrow-in-right"></i> Enter Section
                            </a>
                            <div class="btn-group">
                                <a href="gradebook.php?class_id=<?php echo $section['id']; ?>" 
                                   class="btn btn-minimal btn-sm">
                                    <i class="bi bi-journal-text"></i> Grades
                                </a>
                                <a href="attendance_sheet.php?class_id=<?php echo $section['id']; ?>" 
                                   class="btn btn-minimal btn-sm">
                                    <i class="bi bi-calendar-check"></i> Attendance
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-transparent" style="border-top: 1px solid #e0e0e0;">
                        <small class="text-muted">
                            <i class="bi bi-hash"></i> Class ID: <?php echo $section['id']; ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Section Summary -->
        <div class="minimal-card mt-4">
            <h5 class="section-title">Section Summary</h5>
            <div class="row text-center">
                <?php
                // Calculate summary
                $sections_result->data_seek(0);
                $total_sections = 0;
                $total_students = 0;
                $total_capacity = 0;
                
                while ($section = $sections_result->fetch_assoc()) {
                    $total_sections++;
                    $total_students += $section['enrolled_count'];
                    $total_capacity += $section['max_capacity'];
                }
                
                $avg_utilization = $total_capacity > 0 ? round(($total_students / $total_capacity) * 100) : 0;
                ?>
                
                <div class="col-md-3">
                    <h3 style="color: var(--maroon);"><?php echo $total_sections; ?></h3>
                    <p class="text-muted mb-0">Total Sections</p>
                </div>
                <div class="col-md-3">
                    <h3 style="color: var(--navy);"><?php echo $total_students; ?></h3>
                    <p class="text-muted mb-0">Total Students</p>
                </div>
                <div class="col-md-3">
                    <h3 style="color: #17a2b8;"><?php echo $total_capacity; ?></h3>
                    <p class="text-muted mb-0">Total Capacity</p>
                </div>
                <div class="col-md-3">
                    <h3 style="color: #28a745;"><?php echo $avg_utilization; ?>%</h3>
                    <p class="text-muted mb-0">Utilization</p>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>