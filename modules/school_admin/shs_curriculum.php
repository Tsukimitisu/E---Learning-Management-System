<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "SHS Curriculum Management";

/** 
 * ==========================================
 * BACKEND LOGIC - ABSOLUTELY UNTOUCHED
 * ==========================================
 */
$tracks = [];
$tracks_result = $conn->query("SELECT id, track_name as name, track_code, written_work_weight, performance_task_weight, quarterly_exam_weight, description, is_active FROM shs_tracks WHERE is_active = 1 ORDER BY track_name");
if ($tracks_result) {
    while ($row = $tracks_result->fetch_assoc()) { $tracks[] = $row; }
}

$strands = [];
$strands_result = $conn->query("
    SELECT s.id, s.strand_name as name, s.strand_code, s.description, s.is_active, s.track_id, t.track_name
    FROM shs_strands s
    INNER JOIN shs_tracks t ON s.track_id = t.id AND t.is_active = 1
    ORDER BY s.strand_name
");
if ($strands_result) {
    while ($row = $strands_result->fetch_assoc()) { $strands[] = $row; }
}

$grade_levels = [];
$grade_levels_result = $conn->query("SELECT id, grade_name as name, grade_level, semesters_count as semesters, is_active FROM shs_grade_levels WHERE grade_level IN (11,12) ORDER BY grade_level");
if ($grade_levels_result) {
    while ($row = $grade_levels_result->fetch_assoc()) { $grade_levels[] = $row; }
}

$shs_subjects = [];
$shs_subjects_result = $conn->query("
    SELECT cs.*, ss.strand_name, ss.strand_code, ss.track_id, t.track_name, sgl.grade_name, sgl.grade_level
    FROM curriculum_subjects cs
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    LEFT JOIN shs_tracks t ON ss.track_id = t.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    WHERE cs.subject_type IN ('shs_core', 'shs_applied', 'shs_specialized')
    ORDER BY ss.strand_name, sgl.grade_level, cs.semester, cs.subject_code
");
if ($shs_subjects_result) {
    while ($row = $shs_subjects_result->fetch_assoc()) { $shs_subjects[] = $row; }
}

// Organize subjects by strand -> grade_level -> semester
$subjects_by_strand = [];
foreach ($shs_subjects as $subj) {
    $sid = $subj['shs_strand_id'] ?: 'core';
    $glevel = (int)($subj['grade_level'] ?? 0);
    $sem = (int)($subj['semester'] ?? 1);
    $subjects_by_strand[$sid][$glevel][$sem][] = $subj;
}
$core_subjects = $subjects_by_strand['core'] ?? [];
unset($subjects_by_strand['core']);

// Count subjects per strand
$subject_counts = [];
foreach ($shs_subjects as $sub) {
    $sid = $sub['shs_strand_id'] ?? 0;
    $subject_counts[$sid] = ($subject_counts[$sid] ?? 0) + 1;
}
$core_count = $subject_counts[0] ?? 0;

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/shs_curriculum.css">

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-mortarboard-fill me-2 text-maroon"></i>SHS Curriculum</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="curriculum.php">Curriculum</a></li>
                    <li class="breadcrumb-item active">Senior High School</li>
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
    <ul class="nav nav-pills nav-pills-modern mb-4 animate__animated animate__fadeIn" id="shsTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="strands-tab" data-bs-toggle="pill" data-bs-target="#strands" type="button">
                <i class="bi bi-diagram-3 me-2"></i>Academic Strands (<?php echo count($strands); ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="shs-subjects-tab" data-bs-toggle="pill" data-bs-target="#shs-subjects" type="button">
                <i class="bi bi-book-half me-2"></i>Curriculum Subjects
            </button>
        </li>
    </ul>

    <div class="tab-content" id="shsTabContent">

        <!-- TAB 1: STRANDS -->
        <div class="tab-pane fade show active" id="strands" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold text-muted text-uppercase small mb-0" style="letter-spacing: 1px;">SHS Academic Strands</h6>
                <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addStrandModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Strand
                </button>
            </div>
            <div class="row g-4">
                <?php foreach ($strands as $strand): ?>
                <div class="col-md-6 col-lg-4 animate__animated animate__zoomIn">
                    <div class="strand-card">
                        <div class="strand-header d-flex justify-content-between">
                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($strand['strand_code']); ?></h6>
                            <span class="badge bg-white text-dark rounded-pill small"><?php echo htmlspecialchars($strand['track_name']); ?></span>
                        </div>
                        <div class="p-4">
                            <h6 class="fw-bold text-dark mb-3"><?php echo htmlspecialchars($strand['name']); ?></h6>
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="badge rounded-pill bg-<?php echo $strand['is_active'] ? 'success' : 'secondary'; ?> px-3">
                                    <?php echo $strand['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-warning w-100 fw-bold" onclick="editStrand(<?php echo $strand['id']; ?>)">EDIT</button>
                                <button class="btn btn-sm btn-outline-danger w-100 fw-bold" onclick="deleteStrand(<?php echo $strand['id']; ?>)">DELETE</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>


        <!-- TAB 2: SUBJECTS (Strand Cards View - like College) -->
        <div class="tab-pane fade" id="shs-subjects" role="tabpanel">

            <!-- VIEW 1: Strand Cards Grid -->
            <div id="subjectStrandCards">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold text-muted text-uppercase small mb-0" style="letter-spacing: 1px;">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Select a Strand to Manage Subjects
                    </h6>
                    <span class="badge bg-light text-dark border px-3 py-2">
                        <i class="bi bi-book me-1"></i> Total Subjects: <?php echo count($shs_subjects); ?>
                    </span>
                </div>
                <div class="row g-4">
                    <?php if ($core_count > 0): ?>
                    <div class="col-md-6 col-lg-4 animate__animated animate__zoomIn">
                        <div class="subject-strand-card" onclick="showStrandSubjects('core', 'CORE', 'Core / Applied Subjects')" role="button">
                            <div class="subject-strand-header" style="background: linear-gradient(135deg, var(--maroon) 0%, #600000 100%);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 fw-bold">CORE</h6>
                                    <span class="badge bg-white text-dark rounded-pill small">All Strands</span>
                                </div>
                            </div>
                            <div class="p-4">
                                <h6 class="fw-bold text-dark mb-3">Core / Applied Subjects</h6>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <span class="badge rounded-pill bg-primary px-3 me-1">
                                            <i class="bi bi-book me-1"></i><?php echo $core_count; ?> Subject<?php echo $core_count !== 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                    <span class="badge rounded-pill bg-success px-3">ACTIVE</span>
                                </div>
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Grade Levels:</small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">Grade 11</span>
                                        <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">Grade 12</span>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <small class="text-primary fw-bold"><i class="bi bi-arrow-right-circle me-1"></i>Click to manage subjects</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($strands as $strand): 
                        $sCount = $subject_counts[$strand['id']] ?? 0;
                    ?>
                    <div class="col-md-6 col-lg-4 animate__animated animate__zoomIn">
                        <div class="subject-strand-card" onclick="showStrandSubjects(<?php echo $strand['id']; ?>, '<?php echo htmlspecialchars(addslashes($strand['strand_code'])); ?>', '<?php echo htmlspecialchars(addslashes($strand['name'])); ?>')" role="button">
                            <div class="subject-strand-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($strand['strand_code']); ?></h6>
                                    <span class="badge bg-white text-dark rounded-pill small"><?php echo htmlspecialchars($strand['track_name']); ?></span>
                                </div>
                            </div>
                            <div class="p-4">
                                <h6 class="fw-bold text-dark mb-3"><?php echo htmlspecialchars($strand['name']); ?></h6>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <span class="badge rounded-pill bg-primary px-3 me-1">
                                            <i class="bi bi-book me-1"></i><?php echo $sCount; ?> Subject<?php echo $sCount !== 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                    <span class="badge rounded-pill bg-<?php echo $strand['is_active'] ? 'success' : 'secondary'; ?> px-3">
                                        <?php echo $strand['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                    </span>
                                </div>
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Grade Levels:</small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">Grade 11</span>
                                        <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">Grade 12</span>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <small class="text-primary fw-bold"><i class="bi bi-arrow-right-circle me-1"></i>Click to manage subjects</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($strands) && $core_count === 0): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-50"></i>
                    <h6 class="fw-bold">No strands or subjects found</h6>
                    <p>Create academic strands first, then add subjects to them.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- VIEW 2: Strand Subject Detail (Hidden by default) -->
            <div id="subjectStrandDetail" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm" onclick="backToStrandCards()">
                            <i class="bi bi-arrow-left me-1"></i> All Strands
                        </button>
                        <div>
                            <h5 class="fw-bold mb-0" style="color: var(--blue);" id="detailStrandName"></h5>
                            <small class="text-muted" id="detailStrandCode"></small>
                        </div>
                    </div>
                    <button class="btn btn-maroon-pill shadow-sm" onclick="openAddShsSubjectModal()">
                        <i class="bi bi-plus-circle me-1"></i> Add Subject
                    </button>
                </div>

                <!-- Grade Level / Semester Accordion -->
                <div id="gradeSemAccordion">
                    <!-- Core Subjects Section -->
                    <div class="strand-subjects-section" id="strandSection_core" style="display: none;">
                        <?php 
                        $has_any_core = false;
                        foreach ([11,12] as $g) { foreach ([1,2] as $s) { if (!empty($core_subjects[$g][$s])) $has_any_core = true; } }
                        ?>
                        <?php if (!$has_any_core): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                <h6 class="mt-3 text-muted">No Core Subjects Yet</h6>
                                <p class="text-muted small">Click "Add Subject" to add core/applied subjects.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ([11, 12] as $grade): 
                                $gradeSubjects = $core_subjects[$grade] ?? [];
                                $totalGradeSubjects = 0;
                                foreach ($gradeSubjects as $semSubs) $totalGradeSubjects += count($semSubs);
                                if ($totalGradeSubjects === 0) continue;
                            ?>
                            <div class="grade-level-section mb-4">
                                <div class="grade-level-header-card" onclick="toggleGradeLevel(this)">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="grade-icon-box">
                                                <i class="bi bi-calendar-range"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold">Grade <?php echo $grade; ?></h6>
                                                <small class="text-muted">2 Semesters &bull; <?php echo $totalGradeSubjects; ?> Subject<?php echo $totalGradeSubjects !== 1 ? 's' : ''; ?></small>
                                            </div>
                                        </div>
                                        <i class="bi bi-chevron-down toggle-icon"></i>
                                    </div>
                                </div>
                                <div class="grade-level-body" style="display: none;">
                                    <?php for ($sem = 1; $sem <= 2; $sem++): 
                                        $semSubjects = $gradeSubjects[$sem] ?? [];
                                        $totalHours = 0;
                                        foreach ($semSubjects as $s) $totalHours += (float)$s['units'];
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
                                                    <span class="badge bg-dark rounded-pill"><?php echo $totalHours; ?> hrs/wk</span>
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
                                                        <th>Type</th>
                                                        <th class="text-center">Hrs/Wk</th>
                                                        <th class="text-center">Lec/Lab</th>
                                                        <th class="text-center">Status</th>
                                                        <th class="text-end pe-4">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($semSubjects as $subj): ?>
                                                    <tr>
                                                        <td class="ps-4">
                                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($subj['subject_code']); ?></div>
                                                            <small class="text-muted text-truncate d-block" style="max-width: 250px;"><?php echo htmlspecialchars($subj['subject_title']); ?></small>
                                                        </td>
                                                        <td><span class="badge bg-dark text-info border border-info px-2" style="font-size:0.7rem;"><?php echo ucfirst(str_replace('shs_', '', $subj['subject_type'])); ?></span></td>
                                                        <td class="text-center fw-bold text-maroon"><?php echo $subj['units']; ?></td>
                                                        <td class="text-center small text-muted"><?php echo $subj['lecture_hours']; ?> / <?php echo $subj['lab_hours']; ?></td>
                                                        <td class="text-center">
                                                            <span class="badge rounded-pill bg-<?php echo $subj['is_active'] ? 'success' : 'secondary'; ?> px-3">
                                                                <?php echo $subj['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end pe-4">
                                                            <div class="d-flex justify-content-end gap-1">
                                                                <button class="btn btn-sm btn-white border shadow-sm text-warning" onclick="editSubject(<?php echo $subj['id']; ?>)"><i class="bi bi-pencil-fill"></i></button>
                                                                <button class="btn btn-sm btn-white border shadow-sm text-danger" onclick="deleteSubject(<?php echo $subj['id']; ?>)"><i class="bi bi-trash-fill"></i></button>
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

                    <!-- Per-Strand Subject Sections -->
                    <?php foreach ($strands as $strand): 
                        $strand_subjs = $subjects_by_strand[$strand['id']] ?? [];
                    ?>
                    <div class="strand-subjects-section" id="strandSection_<?php echo $strand['id']; ?>" style="display: none;">
                        <?php 
                        $has_any = false;
                        foreach ([11,12] as $g) { foreach ([1,2] as $s) { if (!empty($strand_subjs[$g][$s])) $has_any = true; } }
                        ?>
                        <?php if (!$has_any): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                <h6 class="mt-3 text-muted">No Subjects Assigned</h6>
                                <p class="text-muted small">Click "Add Subject" to assign subjects to this strand.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ([11, 12] as $grade): 
                                $gradeSubjects = $strand_subjs[$grade] ?? [];
                                $totalGradeSubjects = 0;
                                foreach ($gradeSubjects as $semSubs) $totalGradeSubjects += count($semSubs);
                                if ($totalGradeSubjects === 0) continue;
                            ?>
                            <div class="grade-level-section mb-4">
                                <div class="grade-level-header-card" onclick="toggleGradeLevel(this)">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="grade-icon-box">
                                                <i class="bi bi-calendar-range"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold">Grade <?php echo $grade; ?></h6>
                                                <small class="text-muted">2 Semesters &bull; <?php echo $totalGradeSubjects; ?> Subject<?php echo $totalGradeSubjects !== 1 ? 's' : ''; ?></small>
                                            </div>
                                        </div>
                                        <i class="bi bi-chevron-down toggle-icon"></i>
                                    </div>
                                </div>
                                <div class="grade-level-body" style="display: none;">
                                    <?php for ($sem = 1; $sem <= 2; $sem++): 
                                        $semSubjects = $gradeSubjects[$sem] ?? [];
                                        $totalHours = 0;
                                        foreach ($semSubjects as $s) $totalHours += (float)$s['units'];
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
                                                    <span class="badge bg-dark rounded-pill"><?php echo $totalHours; ?> hrs/wk</span>
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
                                                        <th>Type</th>
                                                        <th class="text-center">Hrs/Wk</th>
                                                        <th class="text-center">Lec/Lab</th>
                                                        <th class="text-center">Status</th>
                                                        <th class="text-end pe-4">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($semSubjects as $subj): ?>
                                                    <tr>
                                                        <td class="ps-4">
                                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($subj['subject_code']); ?></div>
                                                            <small class="text-muted text-truncate d-block" style="max-width: 250px;"><?php echo htmlspecialchars($subj['subject_title']); ?></small>
                                                        </td>
                                                        <td><span class="badge bg-dark text-info border border-info px-2" style="font-size:0.7rem;"><?php echo ucfirst(str_replace('shs_', '', $subj['subject_type'])); ?></span></td>
                                                        <td class="text-center fw-bold text-maroon"><?php echo $subj['units']; ?></td>
                                                        <td class="text-center small text-muted"><?php echo $subj['lecture_hours']; ?> / <?php echo $subj['lab_hours']; ?></td>
                                                        <td class="text-center">
                                                            <span class="badge rounded-pill bg-<?php echo $subj['is_active'] ? 'success' : 'secondary'; ?> px-3">
                                                                <?php echo $subj['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end pe-4">
                                                            <div class="d-flex justify-content-end gap-1">
                                                                <button class="btn btn-sm btn-white border shadow-sm text-warning" onclick="editSubject(<?php echo $subj['id']; ?>)"><i class="bi bi-pencil-fill"></i></button>
                                                                <button class="btn btn-sm btn-white border shadow-sm text-danger" onclick="deleteSubject(<?php echo $subj['id']; ?>)"><i class="bi bi-trash-fill"></i></button>
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

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
const tracksData = <?php echo json_encode($tracks); ?>;
const strandsData = <?php echo json_encode($strands); ?>;
const gradeLevelsData = <?php echo json_encode($grade_levels); ?>;

window.currentStrandId = null;
window.currentStrandCode = '';
window.currentStrandName = '';

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) { window.history.back(); } 
    else { window.location.href = 'curriculum.php'; }
}

// === STRAND CARD NAVIGATION (like College) ===
function showStrandSubjects(strandId, strandCode, strandName) {
    window.currentStrandId = strandId;
    window.currentStrandCode = strandCode;
    window.currentStrandName = strandName;

    document.getElementById('subjectStrandCards').style.display = 'none';
    document.getElementById('subjectStrandDetail').style.display = 'block';
    document.getElementById('detailStrandName').textContent = strandName;
    document.getElementById('detailStrandCode').textContent = strandCode;

    // Hide all strand sections, show the selected one
    document.querySelectorAll('.strand-subjects-section').forEach(el => el.style.display = 'none');
    const section = document.getElementById('strandSection_' + strandId);
    if (section) {
        section.style.display = 'block';
        // Auto-expand first grade level
        const firstHeader = section.querySelector('.grade-level-header-card');
        if (firstHeader) {
            const body = firstHeader.nextElementSibling;
            if (body && body.style.display === 'none') {
                body.style.display = 'block';
                firstHeader.querySelector('.toggle-icon')?.classList.add('rotated');
            }
        }
    }
}

function backToStrandCards() {
    document.getElementById('subjectStrandDetail').style.display = 'none';
    document.getElementById('subjectStrandCards').style.display = 'block';
    window.currentStrandId = null;
    // Collapse all grade levels
    document.querySelectorAll('.grade-level-body').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.toggle-icon').forEach(el => el.classList.remove('rotated'));
}

function toggleGradeLevel(header) {
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

// Open Add Subject Modal with SHS fields pre-shown
function openAddShsSubjectModal() {
    // Reset the form
    const form = document.getElementById('addSubjectForm');
    if (form) form.reset();
    
    // Set default to SHS Core and show SHS fields
    const subjectTypeSelect = document.getElementById('subjectTypeSelect');
    if (subjectTypeSelect) {
        subjectTypeSelect.value = 'shs_core';
    }
    
    // Set default hours per week
    const hoursField = document.getElementById('hoursPerWeek');
    if (hoursField) hoursField.value = 4;
    
    // Show SHS fields, hide college fields
    const shsFields = document.getElementById('shsFields');
    const collegeFields = document.getElementById('collegeFields');
    if (shsFields) shsFields.style.display = 'block';
    if (collegeFields) collegeFields.style.display = 'none';

    // Pre-select strand if viewing a specific strand
    if (window.currentStrandId && window.currentStrandId !== 'core') {
        const strandSelect = document.getElementById('shsStrandSelect');
        if (strandSelect) strandSelect.value = window.currentStrandId;
    }
    
    // Update strand required indicator
    updateAddSubjectType();
    
    // Open the modal
    new bootstrap.Modal(document.getElementById('addSubjectModal')).show();
}
window.openAddShsSubjectModal = openAddShsSubjectModal;

// Toggle strand required star in Add modal based on subject type
function updateAddSubjectType() {
    const typeSelect = document.getElementById('subjectTypeSelect');
    const star = document.getElementById('strandRequiredStar');
    const strandSelect = document.getElementById('shsStrandSelect');
    const helpEl = document.getElementById('subjectTypeHelp');
    if (!typeSelect) return;
    const val = typeSelect.value;
    const isSpecialized = val === 'shs_specialized';
    if (star) star.style.display = isSpecialized ? 'inline' : 'none';
    if (strandSelect) strandSelect.required = isSpecialized;
    if (helpEl) {
        if (val === 'shs_core') {
            helpEl.style.display = 'block';
            helpEl.textContent = 'Core subjects are shared across all strands';
        } else if (val === 'shs_applied') {
            helpEl.style.display = 'block';
            helpEl.textContent = 'Applied subjects are common across strands within a track';
        } else if (val === 'shs_specialized') {
            helpEl.style.display = 'block';
            helpEl.textContent = 'Specialized subjects are specific to a strand';
        } else {
            helpEl.style.display = 'none';
        }
    }
}
window.updateAddSubjectType = updateAddSubjectType;

// --- SHS STRAND AJAX LOGIC ---
async function addStrand(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    try {
        const res = await fetch('process/add_strand.php', { method: 'POST', body: formData });
        const data = await res.json();
        showAlert(data.message, data.status === 'success' ? 'success' : 'danger');
        if (data.status === 'success') {
            form.reset();
            bootstrap.Modal.getInstance(document.getElementById('addStrandModal')).hide();
            setTimeout(() => location.reload(), 1000);
        }
    } catch (err) { showAlert('Error: ' + err.message, 'danger'); }
}
document.getElementById('addStrandForm').addEventListener('submit', addStrand);

async function editStrand(id) {
    const strand = strandsData.find(s => s.id == id);
    if (!strand) return showAlert('Strand not found', 'danger');
    document.getElementById('editStrandId').value = strand.id;
    document.getElementById('editStrandTrack').value = strand.track_id;
    document.getElementById('editStrandName').value = strand.name;
    document.getElementById('editStrandDescription').value = strand.description;
    document.getElementById('editStrandStatus').value = strand.is_active;
    new bootstrap.Modal(document.getElementById('editStrandModal')).show();
}
window.editStrand = editStrand;

document.getElementById('editStrandForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const res = await fetch('process/update_strand.php', { method: 'POST', body: formData });
        const data = await res.json();
        showAlert(data.message, data.status === 'success' ? 'success' : 'danger');
        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('editStrandModal')).hide();
            setTimeout(() => location.reload(), 1000);
        }
    } catch (err) { showAlert('Error: ' + err.message, 'danger'); }
});

async function deleteStrand(id) {
    if (!confirm('Are you sure you want to delete this strand?')) return;
    try {
        const res = await fetch('process/delete_strand.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ strand_id: id })
        });
        const data = await res.json();
        showAlert(data.message, data.status === 'success' ? 'success' : 'danger');
        if (data.status === 'success') setTimeout(() => location.reload(), 1000);
    } catch (err) { showAlert('Error: ' + err.message, 'danger'); }
}
window.deleteStrand = deleteStrand;

// --- SHS SUBJECT AJAX LOGIC ---
// (Add Subject handler is defined at end of script with full validation)

async function editSubject(id) {
    try {
        const res = await fetch('process/get_subject.php?id=' + id);
        const data = await res.json();
        if (data.status !== 'success') return showAlert(data.message, 'danger');
        const subj = data.subject;
        document.querySelector('#editSubjectForm [name=subject_id]').value = subj.id;
        document.querySelector('#editSubjectForm [name=subject_code]').value = subj.subject_code;
        document.querySelector('#editSubjectForm [name=subject_title]').value = subj.subject_title;
        // Map DB units column to Hours per Week field
        document.querySelector('#editSubjectForm [name=hours_per_week]').value = subj.units || 4;
        document.querySelector('#editSubjectForm [name=lecture_hours]').value = subj.lecture_hours;
        document.querySelector('#editSubjectForm [name=lab_hours]').value = subj.lab_hours;
        document.querySelector('#editSubjectForm [name=subject_type]').value = subj.subject_type;
        document.querySelector('#editSubjectForm [name=prerequisites]').value = subj.prerequisites;
        document.querySelector('#editSubjectForm [name=is_active]').value = subj.is_active;
        // Set strand and grade if present
        if (subj.shs_strand_id) document.querySelector('#editSubjectForm [name=shs_strand_id]').value = subj.shs_strand_id;
        if (subj.shs_grade_level_id) document.querySelector('#editSubjectForm [name=shs_grade_level_id]').value = subj.shs_grade_level_id;
        if (subj.semester) document.querySelector('#editSubjectForm [name=semester]').value = subj.semester;
        // Update strand required indicator
        updateEditSubjectType();
        new bootstrap.Modal(document.getElementById('editSubjectModal')).show();
    } catch (err) { showAlert('Error: ' + err.message, 'danger'); }
}
window.editSubject = editSubject;

document.getElementById('editSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const res = await fetch('process/update_subject.php', { method: 'POST', body: formData });
        const data = await res.json();
        showAlert(data.message, data.status === 'success' ? 'success' : 'danger');
        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('editSubjectModal')).hide();
            setTimeout(() => location.reload(), 1000);
        }
    } catch (err) { showAlert('Error: ' + err.message, 'danger'); }
});

async function deleteSubject(id) {
    if (!confirm('Are you sure you want to delete this subject?')) return;
    try {
        const res = await fetch('process/delete_subject.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ subject_id: id })
        });
        const data = await res.json();
        showAlert(data.message, data.status === 'success' ? 'success' : 'danger');
        if (data.status === 'success') setTimeout(() => location.reload(), 1000);
    } catch (err) { showAlert('Error: ' + err.message, 'danger'); }
}
window.deleteSubject = deleteSubject;

function showAlert(msg, type = 'info') {
    const html = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = html;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}

// Toggle strand required star in Edit modal based on subject type
function updateEditSubjectType() {
    const typeSelect = document.getElementById('editSubjectTypeSelect');
    const star = document.getElementById('editStrandRequiredStar');
    const strandSelect = document.getElementById('editShsStrandSelect');
    if (!typeSelect) return;
    const isSpecialized = typeSelect.value === 'shs_specialized';
    if (star) star.style.display = isSpecialized ? 'inline' : 'none';
    if (strandSelect) strandSelect.required = isSpecialized;
}
window.updateEditSubjectType = updateEditSubjectType;

// Add Subject form validation before submit
document.getElementById('addSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const subjectType = formData.get('subject_type');
    const hoursPerWeek = parseFloat(formData.get('hours_per_week'));
    
    // Client-side validation
    if (!subjectType) {
        return showAlert('Please select a Subject Type', 'danger');
    }
    if (!hoursPerWeek || hoursPerWeek <= 0) {
        return showAlert('Hours per Week is required and must be greater than 0', 'danger');
    }
    if (subjectType === 'shs_specialized' && !formData.get('shs_strand_id')) {
        return showAlert('Strand is required for Specialized subjects', 'danger');
    }
    
    try {
        const res = await fetch('process/add_shs_subject.php', { method: 'POST', body: formData });
        const data = await res.json();
        showAlert(data.message, data.status === 'success' ? 'success' : 'danger');
        if (data.status === 'success') {
            form.reset();
            bootstrap.Modal.getInstance(document.getElementById('addSubjectModal')).hide();
            setTimeout(() => location.reload(), 1000);
        }
    } catch (err) { showAlert('Error: ' + err.message, 'danger'); }
}, { capture: true });
</script>
</body>
</html>
