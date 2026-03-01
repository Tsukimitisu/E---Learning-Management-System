<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Academic Year Management";
$branch_id = get_user_branch_id();

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$current_ay = array_filter($academic_years, fn($ay) => $ay['is_active'] == 1);
$current_ay = reset($current_ay) ?: null;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_academic_year') {
        $year_name = trim($_POST['year_name']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        $check = $conn->prepare("SELECT id FROM academic_years WHERE year_name = ?");
        $check->bind_param("s", $year_name);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = "Academic year '$year_name' already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO academic_years (year_name, start_date, end_date, is_active, status) VALUES (?, ?, ?, 0, 'upcoming')");
            $stmt->bind_param("sss", $year_name, $start_date, $end_date);
            if ($stmt->execute()) {
                $message = "Academic year '$year_name' created successfully!";
                $academic_years = $conn->query("SELECT * FROM academic_years ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
            } else { $error = "Failed to create academic year: " . $conn->error; }
        }
    }
    
    if ($action === 'set_active_year') {
        $new_ay_id = (int)$_POST['academic_year_id'];
        $conn->query("UPDATE academic_years SET is_active = 0, status = 'completed' WHERE is_active = 1");
        $stmt = $conn->prepare("UPDATE academic_years SET is_active = 1, status = 'current' WHERE id = ?");
        $stmt->bind_param("i", $new_ay_id);
        if ($stmt->execute()) {
            $message = "Academic year updated successfully!";
            $academic_years = $conn->query("SELECT * FROM academic_years ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
            $current_ay = array_filter($academic_years, fn($ay) => $ay['is_active'] == 1);
            $current_ay = reset($current_ay);
        }
    }
}

$programs = $conn->query("SELECT DISTINCT p.* FROM programs p INNER JOIN sections s ON s.program_id = p.id WHERE s.branch_id = $branch_id")->fetch_all(MYSQLI_ASSOC);

$student_counts = [];
if ($current_ay) {
    $counts = $conn->query("SELECT COALESCE(pyl.year_name, sgl.grade_name) as year_level, COALESCE(p.program_name, ss.strand_name) as program, COUNT(DISTINCT sstu.student_id) as count FROM section_students sstu INNER JOIN sections sec ON sstu.section_id = sec.id LEFT JOIN programs p ON sec.program_id = p.id LEFT JOIN shs_strands ss ON sec.shs_strand_id = ss.id LEFT JOIN program_year_levels pyl ON sec.year_level_id = pyl.id LEFT JOIN shs_grade_levels sgl ON sec.shs_grade_level_id = sgl.id WHERE sec.academic_year_id = {$current_ay['id']} AND sec.branch_id = $branch_id AND sstu.status = 'active' GROUP BY sec.program_id, sec.year_level_id, sec.shs_strand_id, sec.shs_grade_level_id ORDER BY program, year_level");
    $student_counts = $counts->fetch_all(MYSQLI_ASSOC);
}

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/academic_year_management.css">

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. HEADER SECTION -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-calendar3-range me-2 text-maroon"></i>Academic Year Management
            </h4>
            <p class="text-muted small mb-0">Control school cycles and timeline status.</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 bg-transparent p-0">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Academic Year</li>
            </ol>
        </nav>
    </div>

    <!-- 2. ALERTS -->
    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm animate__animated animate__headShake">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm animate__animated animate__shakeX">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- 3. TOP STATS ROW (Balanced 4/8) -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-md-12">
            <div class="stat-card-modern">
                <div class="bg-primary bg-opacity-10 p-3 rounded-3 text-primary">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
                <div>
                    <p class="text-muted small fw-bold mb-0">AVAILABLE PERIODS</p>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php foreach ($academic_years as $ay): ?>
                            <span class="year-badge <?php echo $ay['is_active'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($ay['year_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8 col-md-12">
            <div class="stat-card-modern" style="border-left-color: var(--maroon);">
                <div class="bg-danger bg-opacity-10 p-3 rounded-3 text-danger">
                    <i class="bi bi-people fs-4"></i>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <p class="text-muted small fw-bold mb-0">POPULATION BREAKDOWN (CURRENT)</p>
                    <div class="scroll-container mt-2">
                        <?php if (empty($student_counts)): ?>
                            <small class="text-muted">No active student data found.</small>
                        <?php else: foreach ($student_counts as $stat): ?>
                            <div class="border-end pe-3 flex-shrink-0">
                                <span class="fw-bold d-block" style="font-size: 0.9rem;"><?php echo $stat['count']; ?> Students</span>
                                <small class="text-muted" style="font-size: 0.65rem;"><?php echo htmlspecialchars($stat['program']); ?> | <?php echo htmlspecialchars($stat['year_level']); ?></small>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. CONFIGURATION FORMS ROW (Balanced 6/6) -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6 col-md-12">
            <div class="content-card">
                <div class="card-header-modern"><i class="bi bi-plus-circle me-2"></i>Initialization</div>
                <div class="card-body p-4">
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="create_academic_year">
                        <div class="mb-3">
                            <label class="form-label-custom">Year Designation (YYYY-YYYY)</label>
                            <input type="text" name="year_name" class="form-control" placeholder="e.g., 2026-2027" required pattern="\d{4}-\d{4}">
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label-custom">Start Date</label>
                                <input type="date" name="start_date" class="form-control">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label-custom">End Date</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2">CREATE NEW PERIOD</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-md-12">
            <div class="content-card">
                <div class="card-header-modern"><i class="bi bi-lightning-charge me-2"></i>Active Deployment</div>
                <div class="card-body p-4">
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="set_active_year">
                        <div class="mb-3">
                            <label class="form-label-custom">Select Target Year</label>
                            <select name="academic_year_id" class="form-select" required>
                                <?php foreach ($academic_years as $ay): ?>
                                <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_active'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ay['year_name']); ?> <?php echo $ay['is_active'] ? '(Currently Live)' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-info border-0 small mb-4 py-2">
                            <i class="bi bi-info-circle me-1"></i> Changing the live year affects all system grading and enrollment modules.
                        </div>
                        <button type="submit" class="btn btn-maroon w-100 py-2">ACTIVATE SELECTED PERIOD</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>