<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "College Curriculum Management";

/** 
 * ==========================================
 * BACKEND LOGIC - ABSOLUTELY UNTOUCHED
 * ==========================================
 */
$programs = [];
$programs_result = $conn->query("SELECT id, program_code AS code, program_name AS name, degree_level, school_id, is_active FROM programs ORDER BY program_code");
if ($programs_result) {
    while ($row = $programs_result->fetch_assoc()) { $programs[] = $row; }
}

$year_levels = [];
$year_levels_result = $conn->query("
    SELECT yl.*, p.program_name
    FROM program_year_levels yl
    LEFT JOIN programs p ON yl.program_id = p.id
    ORDER BY yl.program_id, yl.year_level
");
if ($year_levels_result) {
    while ($row = $year_levels_result->fetch_assoc()) { $year_levels[] = $row; }
}

$college_subjects = [];
$college_subjects_result = $conn->query("
    SELECT cs.*, p.program_name, yl.year_name
    FROM curriculum_subjects cs
    LEFT JOIN programs p ON cs.program_id = p.id
    LEFT JOIN program_year_levels yl ON cs.year_level_id = yl.id
    WHERE cs.subject_type = 'college'
    ORDER BY cs.subject_code
");
if ($college_subjects_result) {
    while ($row = $college_subjects_result->fetch_assoc()) { $college_subjects[] = $row; }
}

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC UI COMPONENTS --- */
    .nav-pills-modern .nav-link {
        color: #666; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;
        padding: 12px 25px; border-radius: 10px; transition: 0.3s; margin-right: 10px;
        background: #fff; border: 1.5px solid #eee;
    }
    .nav-pills-modern .nav-link.active {
        background-color: var(--blue); color: white; border-color: var(--blue); box-shadow: 0 4px 12px rgba(0,51,102,0.2);
    }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px;
    }

    .prog-card {
        border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: 0.3s; background: white; overflow: hidden; height: 100%;
    }
    .prog-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .prog-header { background: var(--blue); color: white; padding: 15px; }

    .year-level-card {
        border-radius: 12px; border: 1.5px solid #f1f1f1; background: #fcfcfc;
        padding: 15px; text-align: center; transition: 0.3s;
    }
    .year-level-card:hover { border-color: var(--maroon); background: white; }

    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    .btn-maroon-pill { background-color: var(--maroon); color: white !important; border: none; border-radius: 50px; font-weight: 700; padding: 8px 20px; transition: 0.3s; font-size: 0.8rem; }
    .btn-maroon-pill:hover { background-color: #600000; transform: translateY(-2px); }

    .breadcrumb-item { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    .breadcrumb-item a { color: var(--maroon); text-decoration: none; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } .nav-pills-modern { flex-direction: column; } .nav-pills-modern .nav-link { margin-right: 0; margin-bottom: 5px; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-building-fill me-2 text-maroon"></i>College Curriculum</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="curriculum.php">Curriculum</a></li>
                    <li class="breadcrumb-item active">College</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-outline-secondary btn-sm px-4 rounded-pill shadow-sm" onclick="goBack()">
            <i class="bi bi-arrow-left me-1"></i> Back
        </button>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <!-- Modern Navigation Pills -->
    <ul class="nav nav-pills nav-pills-modern mb-4 animate__animated animate__fadeIn" id="collegeTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="programs-tab" data-bs-toggle="pill" data-bs-target="#programs" type="button">
                <i class="bi bi-mortarboard-fill me-2"></i>Programs (<?php echo count($programs); ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="college-yearlevels-tab" data-bs-toggle="pill" data-bs-target="#college-yearlevels" type="button">
                <i class="bi bi-calendar-range me-2"></i>Year Levels
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="college-subjects-tab" data-bs-toggle="pill" data-bs-target="#college-subjects" type="button">
                <i class="bi bi-book-half me-2"></i>Subjects (<?php echo count($college_subjects); ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="collegeTabContent">

        <!-- TAB 1: PROGRAMS -->
        <div class="tab-pane fade show active" id="programs" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold text-muted text-uppercase small mb-0" style="letter-spacing: 1px;">Degree Programs</h6>
                <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Program
                </button>
            </div>
            <div class="row g-4">
                <?php foreach ($programs as $program): ?>
                <div class="col-md-6 col-lg-4 animate__animated animate__zoomIn">
                    <div class="prog-card">
                        <div class="prog-header d-flex justify-content-between">
                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($program['code']); ?></h6>
                            <span class="badge bg-white text-blue rounded-pill small"><?php echo htmlspecialchars($program['degree_level']); ?></span>
                        </div>
                        <div class="p-4">
                            <h6 class="fw-bold text-dark mb-3"><?php echo htmlspecialchars($program['name']); ?></h6>
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="badge rounded-pill bg-<?php echo $program['is_active'] ? 'success' : 'secondary'; ?> px-3">
                                    <?php echo $program['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-warning w-100 fw-bold" onclick="editProgram(<?php echo $program['id']; ?>)">EDIT</button>
                                <button class="btn btn-sm btn-outline-danger w-100 fw-bold" onclick="deleteCollegeProgram(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['code']); ?>')">DELETE</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 2: YEAR LEVELS -->
        <div class="tab-pane fade" id="college-yearlevels" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold text-muted text-uppercase small mb-0" style="letter-spacing: 1px;">Year Level Structure</h6>
                <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addCollegeYearModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Year Level
                </button>
            </div>
            <div class="row g-4">
                <?php 
                $grouped_year_levels = [];
                foreach ($programs as $p) { $grouped_year_levels[$p['id']] = ['name' => $p['name'], 'levels' => []]; }
                foreach ($year_levels as $y) { if (isset($grouped_year_levels[$y['program_id']])) $grouped_year_levels[$y['program_id']]['levels'][] = $y; }
                
                foreach ($grouped_year_levels as $pid => $group): ?>
                <div class="col-md-6 animate__animated animate__fadeIn">
                    <div class="main-card-modern h-100">
                        <div class="card-header-modern bg-light border-bottom">
                            <i class="bi bi-mortarboard me-2"></i> <?php echo htmlspecialchars($group['program_name']); ?>
                        </div>
                        <div class="p-4">
                            <?php if (empty($group['levels'])): ?>
                                <p class="text-muted small italic">No levels defined for this program.</p>
                            <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($group['levels'] as $year): ?>
                                <div class="col-6">
                                    <div class="year-level-card">
                                        <h6 class="fw-bold text-blue mb-1"><?php echo htmlspecialchars($year['year_name']); ?></h6>
                                        <small class="text-muted d-block mb-3">Semesters: <?php echo $year['semesters_count']; ?></small>
                                        <div class="d-flex justify-content-center gap-1">
                                            <button class="btn btn-xs btn-outline-warning border-0 p-1" onclick="editCollegeYear(<?php echo $year['id']; ?>)"><i class="bi bi-pencil"></i></button>
                                            <button class="btn btn-xs btn-outline-danger border-0 p-1" onclick="deleteCollegeYear(<?php echo $year['id']; ?>, '<?php echo htmlspecialchars($year['year_name']); ?>')"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 3: SUBJECTS -->
        <div class="tab-pane fade" id="college-subjects" role="tabpanel">
            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addCollegeSubjectModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Subject
                </button>
            </div>
            <div class="main-card-modern">
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Code & Title</th>
                                <th class="text-center">Units</th>
                                <th class="text-center">Lec/Lab</th>
                                <th>Academic Program</th>
                                <th>Year/Sem</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($college_subjects as $subject): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                                    <small class="text-muted text-truncate d-block" style="max-width: 200px;"><?php echo htmlspecialchars($subject['subject_title']); ?></small>
                                </td>
                                <td class="text-center fw-bold text-maroon"><?php echo $subject['units']; ?></td>
                                <td class="text-center small text-muted"><?php echo $subject['lecture_hours']; ?> / <?php echo $subject['lab_hours']; ?></td>
                                <td><span class="badge bg-light text-blue border border-blue px-3"><?php echo htmlspecialchars($subject['program_name'] ?? 'Unassigned'); ?></span></td>
                                <td><small class="fw-bold"><?php echo htmlspecialchars($subject['year_name'] ?? 'N/A'); ?></small><br><small class="text-muted"><?php echo $subject['semester'] ?? 'N/A'; ?></small></td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-<?php echo $subject['is_active'] ? 'success' : 'secondary'; ?> px-3">
                                        <?php echo $subject['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button class="btn btn-sm btn-white border shadow-sm text-warning" onclick="editCollegeSubject(<?php echo $subject['id']; ?>)"><i class="bi bi-pencil-fill"></i></button>
                                        <button class="btn btn-sm btn-white border shadow-sm text-danger" onclick="deleteCollegeSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_code']); ?>')"><i class="bi bi-trash-fill"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Inclusion -->
<?php include 'curriculum_modals.php'; ?>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
const collegePrograms = <?php echo json_encode($programs); ?>;
const collegeYearLevels = <?php echo json_encode($year_levels); ?>;

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) { window.history.back(); } 
    else { window.location.href = 'curriculum.php'; }
}

function filterYearLevelsByProgram() {
    const programId = document.getElementById('collegeSubjectProgram').value;
    const yearLevelSelect = document.getElementById('collegeSubjectYearLevel');
    yearLevelSelect.innerHTML = '<option value="">-- Select Year Level --</option>';
    if (!programId) { yearLevelSelect.innerHTML = '<option value="">-- Select Program First --</option>'; return; }
    const filtered = collegeYearLevels.filter(yl => yl.program_id == programId);
    if (filtered.length === 0) { yearLevelSelect.innerHTML = '<option value="">-- No Year Levels Found --</option>'; return; }
    filtered.forEach(yl => {
        const option = document.createElement('option');
        option.value = yl.id;
        option.textContent = yl.year_name;
        yearLevelSelect.appendChild(option);
    });
}
</script>
</body>
</html>