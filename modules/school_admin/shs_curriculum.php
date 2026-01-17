<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "SHS Curriculum Management";

// Fetch SHS data
$tracks_result = $conn->query("SELECT id, track_name as name, track_code, written_work_weight, performance_task_weight, quarterly_exam_weight, description, is_active FROM shs_tracks ORDER BY track_name");
$tracks = $tracks_result->fetch_all(MYSQLI_ASSOC);

$strands_result = $conn->query("
    SELECT s.id, s.strand_name as name, s.strand_code, s.description, s.is_active, s.track_id, t.track_name
    FROM shs_strands s
    LEFT JOIN shs_tracks t ON s.track_id = t.id
    ORDER BY s.strand_name
");
$strands = $strands_result->fetch_all(MYSQLI_ASSOC);

$grade_levels_result = $conn->query("SELECT id, grade_name as name, grade_level, semesters_count as semesters, is_active FROM shs_grade_levels ORDER BY grade_level");
$grade_levels = $grade_levels_result->fetch_all(MYSQLI_ASSOC);

// Fetch SHS subjects
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
$shs_subjects = $shs_subjects_result->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-mortarboard"></i> SHS Curriculum Management
            </h4>
            <small class="text-muted">Design and control Senior High School curriculum</small>
        </div>

        <div id="alertContainer"></div>

        <!-- SHS Curriculum Tabs -->
        <div class="card shadow-sm">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="shsTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="tracks-tab" data-bs-toggle="tab" data-bs-target="#tracks" type="button">
                            <i class="bi bi-grid"></i> Tracks
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="strands-tab" data-bs-toggle="tab" data-bs-target="#strands" type="button">
                            <i class="bi bi-diagram-3"></i> Strands
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="shs-grades-tab" data-bs-toggle="tab" data-bs-target="#shs-grades" type="button">
                            <i class="bi bi-calendar"></i> Grade Levels
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="shs-subjects-tab" data-bs-toggle="tab" data-bs-target="#shs-subjects" type="button">
                            <i class="bi bi-book"></i> SHS Subjects
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="shs-assignments-tab" data-bs-toggle="tab" data-bs-target="#shs-assignments" type="button">
                            <i class="bi bi-link"></i> Subject Assignments
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content" id="shsTabContent">

                    <!-- Tracks Tab -->
                    <div class="tab-pane fade show active" id="tracks" role="tabpanel">
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
                                        <h6 class="mb-0"><?php echo htmlspecialchars($track['name'] ?? 'Unknown Track'); ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text small"><?php echo htmlspecialchars($track['description'] ?? 'No description available'); ?></p>
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
                    <div class="tab-pane fade" id="strands" role="tabpanel">
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
                                        <h6 class="mb-0"><?php echo htmlspecialchars($strand['name'] ?? 'Unknown Strand'); ?></h6>
                                        <small><?php echo htmlspecialchars($strand['track_name'] ?? 'Unknown Track'); ?></small>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text small"><?php echo htmlspecialchars($strand['description'] ?? 'No description available'); ?></p>
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
                        <div class="row">
                            <?php foreach ($grade_levels as $grade): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-info">
                                    <div class="card-header bg-info text-white text-center">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($grade['name'] ?? 'Unknown Grade'); ?></h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <p class="mb-2"><strong>Level:</strong> <?php echo $grade['grade_level'] ?? 'N/A'; ?></p>
                                        <p class="mb-2"><strong>Semesters:</strong> <?php echo $grade['semesters'] ?? 2; ?></p>
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editGradeLevel(<?php echo $grade['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
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
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Units</th>
                                        <th>Strand</th>
                                        <th>Grade</th>
                                        <th>Semester</th>
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
                                            <button class="btn btn-sm btn-info me-1" onclick="assignSubject(<?php echo $subject['id']; ?>)">
                                                <i class="bi bi-link"></i>
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

                    <!-- SHS Assignments Tab -->
                    <div class="tab-pane fade" id="shs-assignments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">SHS Subject Assignments</h5>
                            <button class="btn btn-sm" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#assignSubjectModal">
                                <i class="bi bi-link"></i> Assign Subject
                            </button>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Assign SHS subjects to specific strands, grade levels, and semesters.
                        </div>
                        <!-- Assignment interface will be implemented here -->
                        <div class="text-center text-muted mt-5">
                            <i class="bi bi-cone-striped display-4"></i>
                            <p class="mt-3">Subject assignment interface coming soon...</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include modals -->
<?php include 'curriculum_modals.php'; ?>

<script src="../../assets/js/curriculum.js"></script>

<?php include '../../includes/footer.php'; ?>