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
$tracks_result = $conn->query("SELECT id, track_name as name, track_code, written_work_weight, performance_task_weight, quarterly_exam_weight, description, is_active FROM shs_tracks ORDER BY track_name");
if ($tracks_result) {
    while ($row = $tracks_result->fetch_assoc()) { $tracks[] = $row; }
}

$strands = [];
$strands_result = $conn->query("
    SELECT s.id, s.strand_name as name, s.strand_code, s.description, s.is_active, s.track_id, t.track_name
    FROM shs_strands s
    LEFT JOIN shs_tracks t ON s.track_id = t.id
    ORDER BY s.strand_name
");
if ($strands_result) {
    while ($row = $strands_result->fetch_assoc()) { $strands[] = $row; }
}

$grade_levels = [];
$grade_levels_result = $conn->query("SELECT id, grade_name as name, grade_level, semesters_count as semesters, is_active FROM shs_grade_levels ORDER BY grade_level");
if ($grade_levels_result) {
    while ($row = $grade_levels_result->fetch_assoc()) { $grade_levels[] = $row; }
}

$shs_subjects = [];
$shs_subjects_result = $conn->query("
    SELECT cs.*, ss.strand_name, sgl.grade_name
    FROM curriculum_subjects cs
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    WHERE cs.subject_type IN ('shs_core', 'shs_applied', 'shs_specialized')
    ORDER BY cs.subject_code
");
if ($shs_subjects_result) {
    while ($row = $shs_subjects_result->fetch_assoc()) { $shs_subjects[] = $row; }
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

    .strand-card {
        border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: 0.3s; background: white; overflow: hidden; height: 100%;
    }
    .strand-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .strand-header { background: var(--maroon); color: white; padding: 15px; }

    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    .btn-maroon-pill { background-color: var(--maroon); color: white !important; border: none; border-radius: 50px; font-weight: 700; padding: 8px 25px; transition: 0.3s; font-size: 0.8rem; }
    .btn-maroon-pill:hover { background-color: #600000; transform: translateY(-2px); }

    .action-btn-circle { 
        width: 34px; height: 34px; border-radius: 50%; display: inline-flex; 
        align-items: center; justify-content: center; transition: 0.2s; border: 1px solid #eee; background: white;
    }
    .action-btn-circle:hover { transform: scale(1.1); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }

    .breadcrumb-item { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    .breadcrumb-item a { color: var(--maroon); text-decoration: none; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } .nav-pills-modern { flex-direction: column; } .nav-pills-modern .nav-link { margin-right: 0; margin-bottom: 5px; } }
</style>

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
            <button class="nav-link" id="shs-grades-tab" data-bs-toggle="pill" data-bs-target="#shs-grades" type="button">
                <i class="bi bi-calendar-range me-2"></i>Grade Levels
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
                            <span class="badge bg-dark text-maroon rounded-pill small"><?php echo htmlspecialchars($strand['track_name']); ?></span>
                        </div>
                        <div class="p-4">
                            <h6 class="fw-bold text-dark mb-3"><?php echo htmlspecialchars($strand['name']); ?></h6>
                            <p class="small text-muted mb-4 line-clamp-2"><?php echo htmlspecialchars($strand['description'] ?: 'No description available.'); ?></p>
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

        <!-- TAB 2: GRADE LEVELS -->
        <div class="tab-pane fade" id="shs-grades" role="tabpanel">
            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Grade Level
                </button>
            </div>
            <div class="main-card-modern animate__animated animate__fadeInUp">
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Grade Identity</th>
                                <th class="text-center">Sequence Level</th>
                                <th class="text-center">Academic Terms</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grade_levels as $grade): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($grade['name']); ?></div>
                                    <small class="text-muted">Standard SHS Level</small>
                                </td>
                                <td class="text-center fw-bold text-blue"><?php echo $grade['grade_level']; ?></td>
                                <td class="text-center"><span class="badge bg-light text-primary border border-primary px-3 rounded-pill"><?php echo $grade['semesters']; ?> Semesters</span></td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-<?php echo $grade['is_active'] ? 'success' : 'secondary'; ?> px-3">
                                        <?php echo $grade['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="action-btn-circle text-warning border shadow-sm" onclick="editGradeLevel(<?php echo $grade['id']; ?>)">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: SUBJECTS -->
        <div class="tab-pane fade" id="shs-subjects" role="tabpanel">
            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="bi bi-plus-circle me-1"></i> Add SHS Subject
                </button>
            </div>
            <div class="main-card-modern animate__animated animate__fadeInUp">
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Code & Title</th>
                                <th>Category</th>
                                <th class="text-center">Units</th>
                                <th>Assignment (Strand/Grade)</th>
                                <th class="text-center">Term</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shs_subjects as $subject): 
                                $type = ucfirst(str_replace('shs_', '', $subject['subject_type']));
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                                    <small class="text-muted text-truncate d-block" style="max-width: 200px;"><?php echo htmlspecialchars($subject['subject_title']); ?></small>
                                </td>
                                <td><span class="badge bg-dark text-blue border border-blue px-3"><?php echo $type; ?></span></td>
                                <td class="text-center fw-bold text-maroon"><?php echo $subject['units']; ?></td>
                                <td>
                                    <div class="small fw-bold text-dark"><?php echo htmlspecialchars($subject['strand_name'] ?? 'Core / All'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($subject['grade_name'] ?? 'TBD'); ?></small>
                                </td>
                                <td class="text-center small fw-bold"><?php echo $subject['semester'] == 1 ? '1st Sem' : '2nd Sem'; ?></td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-<?php echo $subject['is_active'] ? 'success' : 'secondary'; ?> px-3">
                                        <?php echo $subject['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button class="btn btn-sm btn-white border shadow-sm text-warning" onclick="editSubject(<?php echo $subject['id']; ?>)"><i class="bi bi-pencil-fill"></i></button>
                                        <button class="btn btn-sm btn-white border shadow-sm text-danger" onclick="deleteSubject(<?php echo $subject['id']; ?>)"><i class="bi bi-trash-fill"></i></button>
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
const tracksData = <?php echo json_encode($tracks); ?>;
const strandsData = <?php echo json_encode($strands); ?>;
const gradeLevelsData = <?php echo json_encode($grade_levels); ?>;

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) { window.history.back(); } 
    else { window.location.href = 'curriculum.php'; }
}
</script>
</body>
</html>