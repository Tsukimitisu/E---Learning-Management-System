<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Attendance Management";
$teacher_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$classes = $conn->query("
    SELECT
        cl.id,
        cl.section_name,
        s.subject_code,
        s.subject_title,
        COUNT(DISTINCT e.student_id) as student_count
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    WHERE cl.teacher_id = $teacher_id
    GROUP BY cl.id
    ORDER BY s.subject_code
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; // Opens wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
        z-index: 10;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px; /* Space at bottom for visibility */
        background-color: #f8f9fa;
    }

    /* --- FANTASTIC ATTENDANCE UI --- */
    .attendance-card {
        background: white;
        border-radius: 20px;
        border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        border-top: 6px solid var(--maroon);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .attendance-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px rgba(128, 0, 0, 0.1);
    }

    .attendance-card .card-body { padding: 25px; }

    .class-icon-box {
        width: 45px;
        height: 45px;
        background: rgba(0, 51, 102, 0.05);
        color: var(--blue);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
    }

    .student-badge {
        background: #e7f5ff;
        color: #1971c2;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.8rem;
    }

    .btn-take-attendance {
        background-color: var(--maroon);
        color: white;
        border-radius: 10px;
        font-weight: 700;
        padding: 12px;
        transition: 0.3s;
        border: none;
        width: 100%;
        display: inline-block;
        text-align: center;
        text-decoration: none;
    }
    .btn-take-attendance:hover {
        background-color: #600000;
        color: white;
        transform: scale(1.02);
    }

    /* Staggered Animations */
    <?php for($i=1; $i<=10; $i++): ?>
    .delay-<?php echo $i; ?> { animation-delay: <?php echo $i * 0.1; ?>s; }
    <?php endfor; ?>

    /* Mobile Logic */
    @media (max-width: 576px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-calendar-check-fill me-2"></i>Attendance Tracking</h4>
        <p class="text-muted small mb-0">Select a class to manage daily student attendance</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4 shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    <div id="alertContainer"></div>

    <div class="row g-4">
        <?php 
        $counter = 1;
        while ($class = $classes->fetch_assoc()): 
        ?>
        <div class="col-md-6 col-lg-4 animate__animated animate__fadeInUp delay-<?php echo $counter; ?>">
            <div class="attendance-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="class-icon-box">
                            <i class="bi bi-person-check"></i>
                        </div>
                        <span class="student-badge">
                            <i class="bi bi-people-fill me-1"></i> <?php echo $class['student_count']; ?> Students
                        </span>
                    </div>

                    <div class="mb-3">
                        <span class="badge bg-light text-maroon border border-maroon px-3 mb-2">
                            <?php echo htmlspecialchars($class['subject_code'] ?: 'N/A'); ?>
                        </span>
                        <h5 class="fw-bold mb-1 text-dark">
                            <?php echo htmlspecialchars($class['section_name'] ?: 'N/A'); ?>
                        </h5>
                        <p class="text-muted small mb-0">
                            <?php echo htmlspecialchars($class['subject_title'] ?: 'N/A'); ?>
                        </p>
                    </div>

                    <div class="mt-auto pt-3">
                        <a href="attendance_sheet.php?class_id=<?php echo $class['id']; ?>" class="btn btn-take-attendance shadow-sm">
                            <i class="bi bi-clipboard2-check me-2"></i> Take Attendance
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php 
            $counter++; 
            endwhile; 
            
            if ($counter == 1): // No classes found
        ?>
        <div class="col-12 text-center py-5 animate__animated animate__fadeIn">
            <i class="bi bi-calendar-x display-1 text-muted opacity-25"></i>
            <h5 class="mt-3 text-muted">No active classes assigned for attendance.</h5>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
</body>
</html>