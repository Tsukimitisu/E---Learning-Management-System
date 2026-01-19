<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Academic Year Management";
$branch_id = get_user_branch_id();

// Get all academic years
$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$current_ay = array_filter($academic_years, fn($ay) => $ay['is_active'] == 1);
$current_ay = reset($current_ay) ?: null;

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_academic_year') {
        $year_name = trim($_POST['year_name']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        // Check if year already exists
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
                // Refresh academic years list
                $academic_years = $conn->query("SELECT * FROM academic_years ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
            } else {
                $error = "Failed to create academic year: " . $conn->error;
            }
        }
    }
    
    if ($action === 'set_active_year') {
        $new_ay_id = (int)$_POST['academic_year_id'];
        
        // Deactivate all years first
        $conn->query("UPDATE academic_years SET is_active = 0, status = 'completed' WHERE is_active = 1");
        
        // Activate selected year
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
        
        // Get students to promote
        $sql = "SELECT DISTINCT ss.student_id, sec.program_id, sec.year_level_id, sec.shs_strand_id, sec.shs_grade_level_id
                FROM section_students ss
                INNER JOIN sections sec ON ss.section_id = sec.id
                WHERE sec.academic_year_id = ? 
                AND sec.branch_id = ?
                AND ss.status = 'active'";
        
        $params = [$from_ay_id, $branch_id];
        $types = "ii";
        
        if ($program_id) {
            $sql .= " AND sec.program_id = ? AND sec.year_level_id = ?";
            $params[] = $program_id;
            $params[] = $from_year_level;
            $types .= "ii";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $promoted_count = 0;
        foreach ($students as $student) {
            // Get next year level
            if ($student['program_id']) {
                $next_yl = $conn->prepare("SELECT id FROM program_year_levels WHERE program_id = ? AND year_level = (SELECT year_level + 1 FROM program_year_levels WHERE id = ?)");
                $next_yl->bind_param("ii", $student['program_id'], $student['year_level_id']);
                $next_yl->execute();
                $next = $next_yl->get_result()->fetch_assoc();
                $to_year_level_id = $next['id'] ?? null;
                
                // If no next year level, student has graduated
                $promotion_type = $to_year_level_id ? 'promoted' : 'graduated';
            } else {
                // SHS: Grade 11 -> Grade 12, Grade 12 -> Graduated
                $current_grade = $conn->query("SELECT grade_level FROM shs_grade_levels WHERE id = " . $student['shs_grade_level_id'])->fetch_assoc();
                if ($current_grade && $current_grade['grade_level'] == 11) {
                    $next = $conn->query("SELECT id FROM shs_grade_levels WHERE grade_level = 12")->fetch_assoc();
                    $to_year_level_id = null;
                    $to_shs_grade = $next['id'] ?? null;
                    $promotion_type = 'promoted';
                } else {
                    $promotion_type = 'graduated';
                    $to_shs_grade = null;
                }
            }
            
            // Log promotion
            $log = $conn->prepare("INSERT INTO student_promotions 
                (student_id, from_academic_year_id, to_academic_year_id, from_year_level_id, to_year_level_id, 
                 from_shs_grade_level_id, to_shs_grade_level_id, program_id, shs_strand_id, branch_id, promotion_type, promoted_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $to_yl = $to_year_level_id ?? null;
            $to_shs = $to_shs_grade ?? null;
            $promoted_by = $_SESSION['user_id'];
            
            $log->bind_param("iiiiiiiiiisi", 
                $student['student_id'], $from_ay_id, $to_ay_id, 
                $student['year_level_id'], $to_yl,
                $student['shs_grade_level_id'], $to_shs,
                $student['program_id'], $student['shs_strand_id'], $branch_id,
                $promotion_type, $promoted_by
            );
            $log->execute();
            $promoted_count++;
        }
        
        $message = "$promoted_count students processed for promotion!";
    }
}

// Get programs for this branch
$programs = $conn->query("
    SELECT DISTINCT p.* FROM programs p
    INNER JOIN sections s ON s.program_id = p.id
    WHERE s.branch_id = $branch_id
")->fetch_all(MYSQLI_ASSOC);

// Get student counts by year level for current AY
$student_counts = [];
if ($current_ay) {
    $counts = $conn->query("
        SELECT 
            COALESCE(pyl.year_name, sgl.grade_name) as year_level,
            COALESCE(p.program_name, ss.strand_name) as program,
            COUNT(DISTINCT sstu.student_id) as count
        FROM section_students sstu
        INNER JOIN sections sec ON sstu.section_id = sec.id
        LEFT JOIN programs p ON sec.program_id = p.id
        LEFT JOIN shs_strands ss ON sec.shs_strand_id = ss.id
        LEFT JOIN program_year_levels pyl ON sec.year_level_id = pyl.id
        LEFT JOIN shs_grade_levels sgl ON sec.shs_grade_level_id = sgl.id
        WHERE sec.academic_year_id = {$current_ay['id']}
        AND sec.branch_id = $branch_id
        AND sstu.status = 'active'
        GROUP BY sec.program_id, sec.year_level_id, sec.shs_strand_id, sec.shs_grade_level_id
        ORDER BY program, year_level
    ");
    $student_counts = $counts->fetch_all(MYSQLI_ASSOC);
}

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px; 
        background-color: #f8f9fa;
    }

    .management-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .management-card .card-header {
        background: linear-gradient(135deg, var(--blue) 0%, #004080 100%);
        color: white;
        padding: 15px 25px;
        font-weight: 600;
    }

    .management-card .card-body {
        padding: 25px;
    }

    .year-badge {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 25px;
        font-weight: 600;
        margin-right: 10px;
        margin-bottom: 10px;
    }

    .year-badge.active {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
    }

    .year-badge.upcoming {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .year-badge.completed {
        background: #e9ecef;
        color: #6c757d;
    }

    .stat-box {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-box:hover {
        background: white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .stat-box h3 {
        color: var(--maroon);
        margin-bottom: 5px;
    }

    .promotion-flow {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .promotion-flow .arrow {
        font-size: 2rem;
        color: var(--maroon);
    }

    .form-section {
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .form-section-title {
        font-weight: 600;
        color: var(--blue);
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--blue);
    }
</style>

<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-calendar3 me-2"></i>Academic Year Management
            </h4>
            <p class="text-muted small mb-0">Manage academic years, student promotions, and year transitions</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary px-4">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="body-scroll-part">
    <?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Current Academic Year Status -->
        <div class="col-lg-8">
            <div class="management-card">
                <div class="card-header">
                    <i class="bi bi-calendar-check me-2"></i>Academic Years Overview
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">All Academic Years:</h6>
                        <?php foreach ($academic_years as $ay): ?>
                        <span class="year-badge <?php echo $ay['is_active'] ? 'active' : ($ay['status'] ?? 'completed'); ?>">
                            <?php echo htmlspecialchars($ay['year_name']); ?>
                            <?php if ($ay['is_active']): ?>
                                <i class="bi bi-check-circle-fill ms-1"></i> Current
                            <?php endif; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-plus-circle me-2"></i>Create New Academic Year
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_academic_year">
                                    <div class="mb-3">
                                        <label class="form-label">Year Name</label>
                                        <input type="text" name="year_name" class="form-control" placeholder="e.g., 2026-2027" required pattern="\d{4}-\d{4}">
                                        <small class="text-muted">Format: YYYY-YYYY</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" name="start_date" class="form-control">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" name="end_date" class="form-control">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-plus me-1"></i> Create Academic Year
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="bi bi-arrow-repeat me-2"></i>Set Active Academic Year
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="set_active_year">
                                    <div class="mb-3">
                                        <label class="form-label">Select Academic Year</label>
                                        <select name="academic_year_id" class="form-select" required>
                                            <?php foreach ($academic_years as $ay): ?>
                                            <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_active'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($ay['year_name']); ?>
                                                <?php echo $ay['is_active'] ? '(Current)' : ''; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="alert alert-warning small">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Warning: Changing active year will affect all modules across the system.
                                    </div>
                                    <button type="submit" class="btn btn-warning w-100">
                                        <i class="bi bi-check2-circle me-1"></i> Set as Active
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Student Statistics -->
        <div class="col-lg-4">
            <div class="management-card">
                <div class="card-header">
                    <i class="bi bi-people me-2"></i>Current Enrollment Stats
                </div>
                <div class="card-body">
                    <?php if (empty($student_counts)): ?>
                    <p class="text-muted text-center">No enrollment data available.</p>
                    <?php else: ?>
                    <?php foreach ($student_counts as $stat): ?>
                    <div class="stat-box mb-3">
                        <h3 class="mb-0"><?php echo $stat['count']; ?></h3>
                        <small class="text-muted"><?php echo htmlspecialchars($stat['program']); ?> - <?php echo htmlspecialchars($stat['year_level']); ?></small>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Promotion Section -->
    <div class="management-card">
        <div class="card-header" style="background: linear-gradient(135deg, var(--maroon) 0%, #a00000 100%);">
            <i class="bi bi-arrow-up-circle me-2"></i>Student Year Level Promotion
        </div>
        <div class="card-body">
            <div class="promotion-flow">
                <div class="text-center">
                    <i class="bi bi-mortarboard display-4 text-primary"></i>
                    <p class="mb-0 fw-bold">1st Year</p>
                </div>
                <span class="arrow"><i class="bi bi-arrow-right"></i></span>
                <div class="text-center">
                    <i class="bi bi-mortarboard display-4 text-primary"></i>
                    <p class="mb-0 fw-bold">2nd Year</p>
                </div>
                <span class="arrow"><i class="bi bi-arrow-right"></i></span>
                <div class="text-center">
                    <i class="bi bi-mortarboard display-4 text-primary"></i>
                    <p class="mb-0 fw-bold">3rd Year</p>
                </div>
                <span class="arrow"><i class="bi bi-arrow-right"></i></span>
                <div class="text-center">
                    <i class="bi bi-mortarboard display-4 text-primary"></i>
                    <p class="mb-0 fw-bold">4th Year</p>
                </div>
                <span class="arrow"><i class="bi bi-arrow-right"></i></span>
                <div class="text-center">
                    <i class="bi bi-award display-4 text-success"></i>
                    <p class="mb-0 fw-bold text-success">Graduate</p>
                </div>
            </div>
            
            <form method="POST" id="promotionForm">
                <input type="hidden" name="action" value="promote_students">
                
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">From Academic Year</label>
                        <select name="from_academic_year" class="form-select" required>
                            <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_active'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ay['year_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">To Academic Year</label>
                        <select name="to_academic_year" class="form-select" required>
                            <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>">
                                <?php echo htmlspecialchars($ay['year_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Program (Optional)</label>
                        <select name="program_id" class="form-select" id="programSelect">
                            <option value="">All Programs</option>
                            <?php foreach ($programs as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">From Year Level</label>
                        <select name="from_year_level" class="form-select" id="yearLevelSelect">
                            <option value="">Select Program First</option>
                        </select>
                    </div>
                </div>
                
                <div class="alert alert-info mt-4">
                    <h6><i class="bi bi-info-circle me-2"></i>How Student Promotion Works:</h6>
                    <ul class="mb-0">
                        <li><strong>1st → 2nd Year:</strong> Students who passed all subjects advance to 2nd year</li>
                        <li><strong>2nd → 3rd Year:</strong> Students progress to junior year</li>
                        <li><strong>3rd → 4th Year:</strong> Students advance to senior year</li>
                        <li><strong>4th Year → Graduate:</strong> Completed students are marked as graduates</li>
                        <li><strong>SHS Grade 11 → 12:</strong> Senior High School progression</li>
                    </ul>
                </div>
                
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-outline-primary me-2" onclick="previewPromotion()">
                        <i class="bi bi-eye me-1"></i> Preview Students
                    </button>
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to process student promotions? This action will be logged.')">
                        <i class="bi bi-arrow-up-circle me-1"></i> Process Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.getElementById('programSelect').addEventListener('change', function() {
    const programId = this.value;
    const yearLevelSelect = document.getElementById('yearLevelSelect');
    
    if (!programId) {
        yearLevelSelect.innerHTML = '<option value="">Select Program First</option>';
        return;
    }
    
    // Fetch year levels for selected program
    fetch(`api/get_year_levels.php?program_id=${programId}`)
        .then(response => response.json())
        .then(data => {
            yearLevelSelect.innerHTML = '<option value="">All Year Levels</option>';
            data.forEach(yl => {
                yearLevelSelect.innerHTML += `<option value="${yl.id}">${yl.year_name}</option>`;
            });
        });
});

function previewPromotion() {
    alert('Preview feature coming soon! This will show a list of students to be promoted.');
}
</script>
</body>
</html>
