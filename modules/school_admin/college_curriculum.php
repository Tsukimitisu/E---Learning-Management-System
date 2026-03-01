<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "College Curriculum Management";

// Determine active tab from URL parameter
$active_tab = $_GET['tab'] ?? 'programs';
$restore_program_id = (int)($_GET['pid'] ?? 0);

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
    SELECT cs.*, p.program_name, p.program_code, yl.year_name, yl.year_level
    FROM curriculum_subjects cs
    LEFT JOIN programs p ON cs.program_id = p.id
    LEFT JOIN program_year_levels yl ON cs.year_level_id = yl.id
    WHERE cs.subject_type = 'college'
    ORDER BY cs.program_id, yl.year_level, cs.semester, cs.subject_code
");
if ($college_subjects_result) {
    while ($row = $college_subjects_result->fetch_assoc()) { $college_subjects[] = $row; }
}

// Group subjects by program → year_level → semester
$subjects_by_program = [];
foreach ($college_subjects as $sub) {
    $pid = $sub['program_id'] ?? 0;
    $ylid = $sub['year_level_id'] ?? 0;
    $sem = $sub['semester'] ?? 1;
    if (!isset($subjects_by_program[$pid])) $subjects_by_program[$pid] = [];
    if (!isset($subjects_by_program[$pid][$ylid])) $subjects_by_program[$pid][$ylid] = [];
    if (!isset($subjects_by_program[$pid][$ylid][$sem])) $subjects_by_program[$pid][$ylid][$sem] = [];
    $subjects_by_program[$pid][$ylid][$sem][] = $sub;
}

// Count subjects per program
$subject_counts = [];
foreach ($college_subjects as $sub) {
    $pid = $sub['program_id'] ?? 0;
    $subject_counts[$pid] = ($subject_counts[$pid] ?? 0) + 1;
}

// Build year levels indexed by program
$year_levels_by_program = [];
foreach ($year_levels as $yl) {
    $pid = $yl['program_id'];
    if (!isset($year_levels_by_program[$pid])) $year_levels_by_program[$pid] = [];
    $year_levels_by_program[$pid][] = $yl;
}

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/college_curriculum.css">

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
            <button class="nav-link <?php echo $active_tab === 'programs' ? 'active' : ''; ?>" id="programs-tab" data-bs-toggle="pill" data-bs-target="#programs" type="button">
                <i class="bi bi-mortarboard-fill me-2"></i>Programs (<?php echo count($programs); ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?php echo $active_tab === 'yearlevels' ? 'active' : ''; ?>" id="college-yearlevels-tab" data-bs-toggle="pill" data-bs-target="#college-yearlevels" type="button">
                <i class="bi bi-calendar-range me-2"></i>Year Levels
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?php echo $active_tab === 'subjects' ? 'active' : ''; ?>" id="college-subjects-tab" data-bs-toggle="pill" data-bs-target="#college-subjects" type="button">
                <i class="bi bi-book-half me-2"></i>Subjects (<?php echo count($college_subjects); ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="collegeTabContent">

        <!-- TAB 1: PROGRAMS -->
        <div class="tab-pane fade <?php echo $active_tab === 'programs' ? 'show active' : ''; ?>" id="programs" role="tabpanel">
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
                            <span class="badge bg-white text-dark rounded-pill small"><?php echo htmlspecialchars($program['degree_level']); ?></span>
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
                                <button class="btn btn-sm btn-outline-danger w-100 fw-bold" onclick="deleteProgram(<?php echo $program['id']; ?>)">DELETE</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 2: YEAR LEVELS -->
        <div class="tab-pane fade <?php echo $active_tab === 'yearlevels' ? 'show active' : ''; ?>" id="college-yearlevels" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold text-muted text-uppercase small mb-0" style="letter-spacing: 1px;">Year Level Structure</h6>
                <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addCollegeYearModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Year Level
                </button>
            </div>
            <div class="row g-4">
                <?php 
                $grouped_year_levels = [];
                foreach ($programs as $p) { $grouped_year_levels[$p['id']] = ['program_name' => ($p['program_name'] ?? $p['name'] ?? ''), 'levels' => []]; }
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

        <!-- TAB 3: SUBJECTS (Program Cards View) -->
        <div class="tab-pane fade <?php echo $active_tab === 'subjects' ? 'show active' : ''; ?>" id="college-subjects" role="tabpanel">
            
            <!-- VIEW 1: Program Cards Grid -->
            <div id="subjectProgramCards">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold text-muted text-uppercase small mb-0" style="letter-spacing: 1px;">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Select a Program to Manage Subjects
                    </h6>
                    <span class="badge bg-light text-dark border px-3 py-2">
                        <i class="bi bi-book me-1"></i> Total Subjects: <?php echo count($college_subjects); ?>
                    </span>
                </div>
                <div class="row g-4">
                    <?php foreach ($programs as $program): 
                        $pCount = $subject_counts[$program['id']] ?? 0;
                        $pYearLevels = $year_levels_by_program[$program['id']] ?? [];
                    ?>
                    <div class="col-md-6 col-lg-4 animate__animated animate__zoomIn">
                        <div class="subject-prog-card" onclick="showProgramSubjects(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars(addslashes($program['code'])); ?>', '<?php echo htmlspecialchars(addslashes($program['name'])); ?>')" role="button">
                            <div class="subject-prog-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($program['code']); ?></h6>
                                    <span class="badge bg-white text-dark rounded-pill small"><?php echo htmlspecialchars($program['degree_level']); ?></span>
                                </div>
                            </div>
                            <div class="p-4">
                                <h6 class="fw-bold text-dark mb-3"><?php echo htmlspecialchars($program['name']); ?></h6>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <span class="badge rounded-pill bg-primary px-3 me-1">
                                            <i class="bi bi-book me-1"></i><?php echo $pCount; ?> Subject<?php echo $pCount !== 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                    <span class="badge rounded-pill bg-<?php echo $program['is_active'] ? 'success' : 'secondary'; ?> px-3">
                                        <?php echo $program['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                    </span>
                                </div>
                                <?php if (!empty($pYearLevels)): ?>
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Year Levels:</small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php foreach ($pYearLevels as $yl): ?>
                                        <span class="badge bg-light text-dark border" style="font-size: 0.65rem;"><?php echo htmlspecialchars($yl['year_name']); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <small class="text-primary fw-bold"><i class="bi bi-arrow-right-circle me-1"></i>Click to manage subjects</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- VIEW 2: Program Subject Detail (Hidden by default) -->
            <div id="subjectProgramDetail" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm" onclick="backToProgramCards()">
                            <i class="bi bi-arrow-left me-1"></i> All Programs
                        </button>
                        <div>
                            <h5 class="fw-bold mb-0" style="color: var(--blue);" id="detailProgramName"></h5>
                            <small class="text-muted" id="detailProgramCode"></small>
                        </div>
                    </div>
                    <button class="btn btn-maroon-pill shadow-sm" onclick="openAddSubjectForProgram()">
                        <i class="bi bi-plus-circle me-1"></i> Add Subject
                    </button>
                </div>

                <!-- Year Level / Semester Accordion -->
                <div id="yearSemAccordion">
                    <?php foreach ($programs as $program): 
                        $pYearLevels = $year_levels_by_program[$program['id']] ?? [];
                        $pSubjects = $subjects_by_program[$program['id']] ?? [];
                    ?>
                    <div class="program-subjects-section" id="programSection_<?php echo $program['id']; ?>" style="display: none;">
                        <?php if (empty($pYearLevels)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                                <h6 class="mt-3 text-muted">No Year Levels Defined</h6>
                                <p class="text-muted small">Please add year levels for this program first in the "Year Levels" tab.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pYearLevels as $yl): 
                                $ylSubjects = $pSubjects[$yl['id']] ?? [];
                                $semCount = $yl['semesters_count'] ?? 2;
                                $totalYlSubjects = 0;
                                foreach ($ylSubjects as $semSubs) $totalYlSubjects += count($semSubs);
                            ?>
                            <div class="year-level-section mb-4">
                                <div class="year-level-header-card" onclick="toggleYearLevel(this)">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="year-icon-box">
                                                <i class="bi bi-calendar-range"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($yl['year_name']); ?></h6>
                                                <small class="text-muted"><?php echo $semCount; ?> Semester<?php echo $semCount > 1 ? 's' : ''; ?> &bull; <?php echo $totalYlSubjects; ?> Subject<?php echo $totalYlSubjects !== 1 ? 's' : ''; ?></small>
                                            </div>
                                        </div>
                                        <i class="bi bi-chevron-down toggle-icon"></i>
                                    </div>
                                </div>
                                <div class="year-level-body" style="display: none;">
                                    <?php for ($sem = 1; $sem <= $semCount; $sem++): 
                                        $semSubjects = $ylSubjects[$sem] ?? [];
                                        $totalUnits = 0;
                                        foreach ($semSubjects as $s) $totalUnits += (float)$s['units'];
                                    ?>
                                    <div class="semester-section">
                                        <div class="semester-header">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold">
                                                    <i class="bi bi-mortarboard me-2"></i>
                                                    <?php echo $sem == 1 ? '1st' : '2nd'; ?> Semester
                                                </span>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-primary rounded-pill"><?php echo count($semSubjects); ?> subject<?php echo count($semSubjects) !== 1 ? 's' : ''; ?></span>
                                                    <span class="badge bg-dark rounded-pill"><?php echo $totalUnits; ?> units</span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (empty($semSubjects)): ?>
                                        <div class="text-center py-4">
                                            <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                                            <p class="text-muted small mt-2 mb-0">No subjects assigned for this semester</p>
                                        </div>
                                        <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-modern align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th class="ps-4">Code & Title</th>
                                                        <th class="text-center">Units</th>
                                                        <th class="text-center">Lec/Lab</th>
                                                        <th>Prerequisites</th>
                                                        <th class="text-center">Status</th>
                                                        <th class="text-end pe-4">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($semSubjects as $subject): ?>
                                                    <tr>
                                                        <td class="ps-4">
                                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                                                            <small class="text-muted text-truncate d-block" style="max-width: 250px;"><?php echo htmlspecialchars($subject['subject_title']); ?></small>
                                                        </td>
                                                        <td class="text-center fw-bold text-maroon"><?php echo $subject['units']; ?></td>
                                                        <td class="text-center small text-muted"><?php echo $subject['lecture_hours']; ?> / <?php echo $subject['lab_hours']; ?></td>
                                                        <td><small class="text-muted"><?php echo htmlspecialchars($subject['prerequisites'] ?: '—'); ?></small></td>
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
                                        <?php endif; ?>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Inclusion -->
<?php include 'curriculum_modals.php'; ?>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - CONCURRENT & RE-WIRED --- -->
<script src="../../assets/js/curriculum.js"></script>
<script>
window.collegePrograms = <?php echo json_encode($programs); ?>;
window.collegeYearLevels = <?php echo json_encode($year_levels); ?>;
window.currentProgramId = null;
window.currentProgramCode = '';
window.currentProgramName = '';

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) { window.history.back(); } 
    else { window.location.href = 'curriculum.php'; }
}

function filterYearLevelsByProgram() {
    const programId = document.getElementById('collegeSubjectProgram')?.value;
    const yearLevelSelect = document.getElementById('collegeSubjectYearLevel');
    if (!yearLevelSelect) return;
    yearLevelSelect.innerHTML = '<option value="">-- Select Year Level --</option>';
    if (!programId) { yearLevelSelect.innerHTML = '<option value="">-- Select Program First --</option>'; return; }
    const filtered = window.collegeYearLevels.filter(yl => yl.program_id == programId);
    if (filtered.length === 0) { yearLevelSelect.innerHTML = '<option value="">-- No Year Levels Found --</option>'; return; }
    filtered.forEach(yl => {
        const option = document.createElement('option');
        option.value = yl.id;
        option.textContent = yl.year_name;
        yearLevelSelect.appendChild(option);
    });
}

// Show subjects for a specific program
function showProgramSubjects(programId, programCode, programName) {
    window.currentProgramId = programId;
    window.currentProgramCode = programCode;
    window.currentProgramName = programName;

    document.getElementById('subjectProgramCards').style.display = 'none';
    document.getElementById('subjectProgramDetail').style.display = 'block';
    document.getElementById('detailProgramName').textContent = programName;
    document.getElementById('detailProgramCode').textContent = programCode;

    // Hide all program sections, show the selected one
    document.querySelectorAll('.program-subjects-section').forEach(el => el.style.display = 'none');
    const section = document.getElementById('programSection_' + programId);
    if (section) {
        section.style.display = 'block';
        // Auto-expand first year level
        const firstYearHeader = section.querySelector('.year-level-header-card');
        if (firstYearHeader) {
            const body = firstYearHeader.nextElementSibling;
            if (body && body.style.display === 'none') {
                body.style.display = 'block';
                firstYearHeader.querySelector('.toggle-icon')?.classList.add('rotated');
            }
        }
    }
}

// Back to program cards
function backToProgramCards() {
    document.getElementById('subjectProgramDetail').style.display = 'none';
    document.getElementById('subjectProgramCards').style.display = 'block';
    window.currentProgramId = null;
    // Collapse all year levels
    document.querySelectorAll('.year-level-body').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.toggle-icon').forEach(el => el.classList.remove('rotated'));
}

// Toggle year level accordion
function toggleYearLevel(header) {
    const body = header.nextElementSibling;
    const icon = header.querySelector('.toggle-icon');
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon?.classList.add('rotated');
    } else {
        body.style.display = 'none';
        icon?.classList.remove('rotated');
    }
}

// Open add subject modal with program pre-selected
function openAddSubjectForProgram() {
    const programSelect = document.getElementById('collegeSubjectProgram');
    if (programSelect && window.currentProgramId) {
        programSelect.value = window.currentProgramId;
        filterYearLevelsByProgram();
    }
    new bootstrap.Modal(document.getElementById('addCollegeSubjectModal')).show();
}

// Restore program detail view if pid is in URL
<?php if ($restore_program_id > 0 && $active_tab === 'subjects'): ?>
(function() {
    var pid = <?php echo $restore_program_id; ?>;
    var prog = window.collegePrograms.find(function(p) { return p.id == pid; });
    if (prog) {
        showProgramSubjects(pid, prog.code || '', prog.name || '');
    }
})();
<?php endif; ?>
</script>
</body>
</html>