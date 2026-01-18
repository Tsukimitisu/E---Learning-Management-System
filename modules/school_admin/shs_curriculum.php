<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "SHS Curriculum Management";

// Fetch all SHS data
$tracks = [];
$tracks_result = $conn->query("SELECT id, track_name as name, track_code, written_work_weight, performance_task_weight, quarterly_exam_weight, description, is_active FROM shs_tracks ORDER BY track_name");
if ($tracks_result) {
    while ($row = $tracks_result->fetch_assoc()) {
        $tracks[] = $row;
    }
}

$strands = [];
$strands_result = $conn->query("
    SELECT s.id, s.strand_name as name, s.strand_code, s.description, s.is_active, s.track_id, t.track_name
    FROM shs_strands s
    LEFT JOIN shs_tracks t ON s.track_id = t.id
    ORDER BY s.strand_name
");
if ($strands_result) {
    while ($row = $strands_result->fetch_assoc()) {
        $strands[] = $row;
    }
}

$grade_levels = [];
$grade_levels_result = $conn->query("SELECT id, grade_name as name, grade_level, semesters_count as semesters, is_active FROM shs_grade_levels ORDER BY grade_level");
if ($grade_levels_result) {
    while ($row = $grade_levels_result->fetch_assoc()) {
        $grade_levels[] = $row;
    }
}

$shs_subjects = [];
$shs_subjects_result = $conn->query("
    SELECT cs.*,
           ss.strand_name,
           sgl.grade_name
    FROM curriculum_subjects cs
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    WHERE cs.subject_type IN ('shs_core', 'shs_applied', 'shs_specialized')
    ORDER BY cs.subject_code
");
if ($shs_subjects_result) {
    while ($row = $shs_subjects_result->fetch_assoc()) {
        $shs_subjects[] = $row;
    }
}

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <div>
                <a href="javascript:void(0)" onclick="goBack()" class="btn btn-sm btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <span style="display: inline-block;">
                    <h4 class="mb-0 d-inline-block" style="color: #003366;">
                        <i class="bi bi-mortarboard"></i> SHS Curriculum Management
                    </h4>
                    <br><small class="text-muted">Design and control Senior High School curriculum</small>
                </span>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- SHS Curriculum Tabs -->
        <div class="card shadow-sm">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="shsTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="strands-tab" data-bs-toggle="tab" data-bs-target="#strands" type="button">
                            <i class="bi bi-diagram-3"></i> Strands (<?php echo count($strands); ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="shs-grades-tab" data-bs-toggle="tab" data-bs-target="#shs-grades" type="button">
                            <i class="bi bi-calendar"></i> Grade Levels
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="shs-subjects-tab" data-bs-toggle="tab" data-bs-target="#shs-subjects" type="button">
                            <i class="bi bi-book"></i> Subjects (<?php echo count($shs_subjects); ?>)
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content" id="shsTabContent">

                    <!-- Tracks Tab (Hidden completely) -->
                    <div class="tab-pane fade" id="tracks" role="tabpanel" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Academic Tracks</h5>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTrackModal">
                                <i class="bi bi-plus-circle"></i> Add Track
                            </button>
                        </div>
                        <div class="row">
                            <?php foreach ($tracks as $track): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($track['name']); ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text small">
                                            <strong>Code:</strong> <?php echo htmlspecialchars($track['track_code']); ?><br>
                                            <strong>Written Work:</strong> <?php echo $track['written_work_weight']; ?>%<br>
                                            <strong>Performance:</strong> <?php echo $track['performance_task_weight']; ?>%<br>
                                            <strong>Exam:</strong> <?php echo $track['quarterly_exam_weight']; ?>%
                                        </p>
                                        <p class="small text-muted"><?php echo htmlspecialchars($track['description'] ?? ''); ?></p>
                                        <div class="d-flex justify-content-between">
                                            <span class="badge bg-<?php echo $track['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $track['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                            <div>
                                                <button class="btn btn-sm btn-outline-warning me-1" onclick="editTrack(<?php echo $track['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTrack(<?php echo $track['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Strands Tab -->
                    <div class="tab-pane fade show active" id="strands" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Academic Strands</h5>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addStrandModal">
                                <i class="bi bi-plus-circle"></i> Add Strand
                            </button>
                        </div>
                        <div class="row">
                            <?php foreach ($strands as $strand): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 border-success">
                                    <div class="card-header bg-success text-white d-flex justify-content-between">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($strand['name']); ?></h6>
                                        <small><?php echo htmlspecialchars($strand['track_name']); ?></small>
                                    </div>
                                    <div class="card-body">
                                        <p class="small text-muted"><?php echo htmlspecialchars($strand['description'] ?? ''); ?></p>
                                        <div class="d-flex justify-content-between">
                                            <span class="badge bg-<?php echo $strand['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $strand['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                            <div>
                                                <button class="btn btn-sm btn-outline-warning me-1" onclick="editStrand(<?php echo $strand['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteStrand(<?php echo $strand['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Grade Levels Tab -->
                    <div class="tab-pane fade" id="shs-grades" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Grade Levels</h5>
                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                                <i class="bi bi-plus-circle"></i> Add Grade Level
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Grade</th>
                                        <th>Level</th>
                                        <th>Semesters</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grade_levels as $grade): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($grade['name']); ?></strong></td>
                                        <td><?php echo $grade['grade_level']; ?></td>
                                        <td><?php echo $grade['semesters']; ?> Semesters</td>
                                        <td>
                                            <span class="badge bg-<?php echo $grade['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $grade['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-warning" onclick="editGradeLevel(<?php echo $grade['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SHS Subjects Tab -->
                    <div class="tab-pane fade" id="shs-subjects" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">SHS Subjects</h5>
                            <button class="btn btn-sm" style="background-color: #800000; color: white;" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                                <i class="bi bi-plus-circle"></i> Add SHS Subject
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Code</th>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Units</th>
                                        <th>Strand</th>
                                        <th>Grade</th>
                                        <th>Sem</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shs_subjects as $subject): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($subject['subject_title']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst(str_replace('shs_', '', $subject['subject_type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $subject['units']; ?></td>
                                        <td><?php echo htmlspecialchars($subject['strand_name'] ?? 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars($subject['grade_name'] ?? 'Unassigned'); ?></td>
                                        <td><?php echo $subject['semester']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $subject['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $subject['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1" onclick="editSubject(<?php echo $subject['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteSubject(<?php echo $subject['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
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
    </div>
</div>

<!-- ...include modals... -->
<?php include 'curriculum_modals.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/curriculum.js"></script>

<script>
const tracksData = <?php echo json_encode($tracks); ?>;
const strandsData = <?php echo json_encode($strands); ?>;
const gradeLevelsData = <?php echo json_encode($grade_levels); ?>;

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) {
        window.history.back();
    } else {
        window.location.href = 'curriculum.php';
    }
}
</script>

<?php include '../../includes/footer.php'; ?>