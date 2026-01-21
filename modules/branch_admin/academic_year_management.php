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
    
    if ($action === 'promote_students') {
        $from_ay_id = (int)$_POST['from_academic_year'];
        $to_ay_id = (int)$_POST['to_academic_year'];
        $program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        $from_year_level = (int)$_POST['from_year_level'];
        
        $sql = "SELECT DISTINCT ss.student_id, sec.program_id, sec.year_level_id, sec.shs_strand_id, sec.shs_grade_level_id
                FROM section_students ss
                INNER JOIN sections sec ON ss.section_id = sec.id
                WHERE sec.academic_year_id = ? AND sec.branch_id = ? AND ss.status = 'active'";
        $params = [$from_ay_id, $branch_id];
        $types = "ii";
        if ($program_id) {
            $sql .= " AND sec.program_id = ? AND sec.year_level_id = ?";
            $params[] = $program_id; $params[] = $from_year_level; $types .= "ii";
        }
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $promoted_count = 0;
        foreach ($students as $student) {
            if ($student['program_id']) {
                $next_yl = $conn->prepare("SELECT id FROM program_year_levels WHERE program_id = ? AND year_level = (SELECT year_level + 1 FROM program_year_levels WHERE id = ?)");
                $next_yl->bind_param("ii", $student['program_id'], $student['year_level_id']);
                $next_yl->execute();
                $next = $next_yl->get_result()->fetch_assoc();
                $to_year_level_id = $next['id'] ?? null;
                $promotion_type = $to_year_level_id ? 'promoted' : 'graduated';
            } else {
                $current_grade = $conn->query("SELECT grade_level FROM shs_grade_levels WHERE id = " . $student['shs_grade_level_id'])->fetch_assoc();
                if ($current_grade && $current_grade['grade_level'] == 11) {
                    $next = $conn->query("SELECT id FROM shs_grade_levels WHERE grade_level = 12")->fetch_assoc();
                    $to_year_level_id = null; $to_shs_grade = $next['id'] ?? null; $promotion_type = 'promoted';
                } else { $promotion_type = 'graduated'; $to_shs_grade = null; }
            }
            $log = $conn->prepare("INSERT INTO student_promotions (student_id, from_academic_year_id, to_academic_year_id, from_year_level_id, to_year_level_id, from_shs_grade_level_id, to_shs_grade_level_id, program_id, shs_strand_id, branch_id, promotion_type, promoted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $to_yl = $to_year_level_id ?? null; $to_shs = $to_shs_grade ?? null; $promoted_by = $_SESSION['user_id'];
            $log->bind_param("iiiiiiiiiisi", $student['student_id'], $from_ay_id, $to_ay_id, $student['year_level_id'], $to_yl, $student['shs_grade_level_id'], $to_shs, $student['program_id'], $student['shs_strand_id'], $branch_id, $promotion_type, $promoted_by);
            $log->execute();
            $promoted_count++;
        }
        $message = "$promoted_count students processed for promotion!";
    }
}

$programs = $conn->query("SELECT DISTINCT p.* FROM programs p INNER JOIN sections s ON s.program_id = p.id WHERE s.branch_id = $branch_id")->fetch_all(MYSQLI_ASSOC);

$student_counts = [];
if ($current_ay) {
    $counts = $conn->query("SELECT COALESCE(pyl.year_name, sgl.grade_name) as year_level, COALESCE(p.program_name, ss.strand_name) as program, COUNT(DISTINCT sstu.student_id) as count FROM section_students sstu INNER JOIN sections sec ON sstu.section_id = sec.id LEFT JOIN programs p ON sec.program_id = p.id LEFT JOIN shs_strands ss ON sec.shs_strand_id = ss.id LEFT JOIN program_year_levels pyl ON sec.year_level_id = pyl.id LEFT JOIN shs_grade_levels sgl ON sec.shs_grade_level_id = sgl.id WHERE sec.academic_year_id = {$current_ay['id']} AND sec.branch_id = $branch_id AND sstu.status = 'active' GROUP BY sec.program_id, sec.year_level_id, sec.shs_strand_id, sec.shs_grade_level_id ORDER BY program, year_level");
    $student_counts = $counts->fetch_all(MYSQLI_ASSOC);
}

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- ENHANCED UI ENGINE --- */
    .page-header {
        background: white; padding: 20px; border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
    }

    .stat-card-modern {
        background: white; border-radius: 15px; padding: 20px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center;
        gap: 15px; transition: 0.3s; height: 100%; border-left: 5px solid var(--blue);
    }

    .content-card {
        background: white; border-radius: 15px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); height: 100%;
        display: flex; flex-direction: column; overflow: hidden;
    }

    .card-header-modern {
        background: #fcfcfc; padding: 15px 20px; border-bottom: 1px solid #eee;
        font-weight: 700; color: var(--blue); text-transform: uppercase;
        font-size: 0.8rem; letter-spacing: 1px;
    }

    .year-badge {
        padding: 6px 15px; border-radius: 8px; font-weight: 700;
        font-size: 0.75rem; border: 1px solid #eee; background: #f8f9fa; color: #666;
    }
    .year-badge.active { background: #e6f4ea; color: #1e7e34; border-color: #c3e6cb; }

    /* Promotion Flow Styling */
    .promotion-step { text-align: center; flex: 1; position: relative; padding: 10px; }
    .step-circle {
        width: 50px; height: 50px; background: white; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;
        font-weight: 800; color: var(--blue); border: 3px solid #eee;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.3s;
    }
    .promotion-step.active .step-circle { border-color: var(--maroon); background: var(--maroon); color: white; }
    .promotion-step.graduate .step-circle { border-color: #28a745; color: #28a745; }
    .step-label { font-size: 0.75rem; font-weight: 700; color: #555; }

    .form-label-custom { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #888; margin-bottom: 5px; }
    
    .btn-maroon { background-color: var(--maroon); color: white; font-weight: 700; }
    .btn-maroon:hover { background-color: #600000; color: white; transform: translateY(-2px); }

    .scroll-container { display: flex; overflow-x: auto; gap: 15px; padding-bottom: 5px; }
    .scroll-container::-webkit-scrollbar { height: 4px; }
    .scroll-container::-webkit-scrollbar-thumb { background: #ddd; border-radius: 10px; }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. HEADER SECTION -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-calendar3-range me-2 text-maroon"></i>Academic Year Management
            </h4>
            <p class="text-muted small mb-0">Control school cycles, timeline status, and student promotions.</p>
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

    <!-- 5. PROMOTION SECTION (Balanced Full Width) -->
    <div class="content-card mb-5">
        <div class="card-header-modern bg-white text-maroon" style="border-top: 3px solid var(--maroon);">
            <i class="bi bi-arrow-up-circle me-2"></i>Student Progression & Promotion
        </div>
        <div class="card-body p-4">
            <!-- Progression Tracker (Desktop Only) -->
            <div class="d-none d-lg-flex align-items-center justify-content-between px-5 mb-5 mt-2">
                <?php $levels = ["1st Year", "2nd Year", "3rd Year", "4th Year"]; 
                foreach($levels as $index => $label): ?>
                    <div class="promotion-step <?php echo ($index == 0) ? 'active' : ''; ?>">
                        <div class="step-circle"><?php echo $index + 1; ?></div>
                        <span class="step-label"><?php echo $label; ?></span>
                    </div>
                    <?php if($index < 3): ?>
                        <div class="text-muted opacity-25"><i class="bi bi-chevron-right fs-4"></i></div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="text-muted opacity-25"><i class="bi bi-chevron-right fs-4"></i></div>
                <div class="promotion-step graduate">
                    <div class="step-circle"><i class="bi bi-mortarboard-fill"></i></div>
                    <span class="step-label text-success fw-bold">Graduate</span>
                </div>
            </div>

            <!-- Promotion Form -->
            <form method="POST" id="promotionForm" class="bg-light p-4 rounded-4 border">
                <input type="hidden" name="action" value="promote_students">
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label-custom">Source Year</label>
                        <select name="from_academic_year" class="form-select" required>
                            <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_active'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ay['year_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label-custom">Target Year</label>
                        <select name="to_academic_year" class="form-select" required>
                            <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>"><?php echo htmlspecialchars($ay['year_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label-custom">Academic Program</label>
                        <select name="program_id" class="form-select">
                            <option value="">All Programs</option>
                            <?php foreach ($programs as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label-custom">Promote From</label>
                        <select name="from_year_level" class="form-select">
                            <option value="">Select Level</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 p-3 border-start border-4 border-warning bg-white rounded shadow-sm">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-shield-lock-fill text-warning fs-4 me-3"></i>
                        <p class="mb-0 small text-muted">
                            <strong>Security Note:</strong> Only students with "Cleared" status in the selected Source Year will be moved. This action is permanently logged.
                        </p>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="button" class="btn btn-outline-secondary px-4 fw-bold me-2 mb-2 mb-sm-0" onclick="alert('Student preview functionality is being generated...')">
                        <i class="bi bi-eye me-1"></i> PREVIEW
                    </button>
                    <button type="submit" class="btn btn-maroon px-5" onclick="return confirm('Promote students to the next year level? This process moves database records to the target period.')">
                        <i class="bi bi-rocket-takeoff me-2"></i> PROCESS PROMOTION
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>