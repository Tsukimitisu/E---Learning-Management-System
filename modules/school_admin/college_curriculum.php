<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "College Curriculum Management";

// Fetch college data
$programs = [];
$programs_result = $conn->query("SELECT id, program_code AS code, program_name AS name, degree_level, school_id, is_active FROM programs ORDER BY program_code");
if ($programs_result) {
    while ($row = $programs_result->fetch_assoc()) {
        $programs[] = $row;
    }
}

$year_levels = [];
$year_levels_result = $conn->query("
    SELECT yl.*, p.program_name
    FROM program_year_levels yl
    LEFT JOIN programs p ON yl.program_id = p.id
    ORDER BY yl.program_id, yl.year_level
");
if ($year_levels_result) {
    while ($row = $year_levels_result->fetch_assoc()) {
        $year_levels[] = $row;
    }
}

// Fetch college subjects
$college_subjects = [];
$college_subjects_result = $conn->query("
    SELECT cs.*,
           p.program_name,
           yl.year_name
    FROM curriculum_subjects cs
    LEFT JOIN programs p ON cs.program_id = p.id
    LEFT JOIN program_year_levels yl ON cs.year_level_id = yl.id
    WHERE cs.subject_type = 'college'
    ORDER BY cs.subject_code
");
if ($college_subjects_result) {
    while ($row = $college_subjects_result->fetch_assoc()) {
        $college_subjects[] = $row;
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
                        <i class="bi bi-building"></i> College Curriculum Management
                    </h4>
                    <br><small class="text-muted">Design and control College curriculum - CHED Compliant</small>
                </span>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- College Curriculum Tabs -->
        <div class="card shadow-sm">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="collegeTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="programs-tab" data-bs-toggle="tab" data-bs-target="#programs" type="button">
                            <i class="bi bi-mortarboard-fill"></i> Programs (<?php echo count($programs); ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="college-yearlevels-tab" data-bs-toggle="tab" data-bs-target="#college-yearlevels" type="button">
                            <i class="bi bi-calendar-range"></i> Year Levels
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="college-subjects-tab" data-bs-toggle="tab" data-bs-target="#college-subjects" type="button">
                            <i class="bi bi-book-half"></i> Subjects (<?php echo count($college_subjects); ?>)
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content" id="collegeTabContent">

                    <!-- Programs Tab -->
                    <div class="tab-pane fade show active" id="programs" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">College Programs</h5>
                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                                <i class="bi bi-plus-circle"></i> Add Program
                            </button>
                        </div>
                        <div class="row">
                            <?php foreach ($programs as $program): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 border-secondary">
                                    <div class="card-header bg-secondary text-white d-flex justify-content-between">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($program['code']); ?></h6>
                                        <small><?php echo htmlspecialchars($program['degree_level']); ?></small>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($program['name']); ?></h6>
                                        <p class="card-text">
                                            <strong>Status:</strong>
                                            <span class="badge bg-<?php echo $program['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $program['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </p>
                                        <div class="d-flex justify-content-between gap-2">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editProgram(<?php echo $program['id']; ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCollegeProgram(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['code']); ?>')">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Year Levels Tab -->
                    <div class="tab-pane fade" id="college-yearlevels" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">College Year Levels</h5>
                            <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#addCollegeYearModal">
                                <i class="bi bi-plus-circle"></i> Add Year Level
                            </button>
                        </div>
                        <div class="row">
                            <?php 
                            // Group year levels by program and include all programs
                            $grouped_year_levels = [];
                            
                            // First, initialize all programs
                            foreach ($programs as $program) {
                                $program_id = $program['id'];
                                if (!isset($grouped_year_levels[$program_id])) {
                                    $grouped_year_levels[$program_id] = [
                                        'program_name' => $program['name'],
                                        'levels' => []
                                    ];
                                }
                            }
                            
                            // Then, add year levels to their programs
                            foreach ($year_levels as $year) {
                                $program_id = $year['program_id'] ?? 0;
                                $program_name = $year['program_name'] ?? 'General';
                                if (!isset($grouped_year_levels[$program_id])) {
                                    $grouped_year_levels[$program_id] = [
                                        'program_name' => $program_name,
                                        'levels' => []
                                    ];
                                }
                                $grouped_year_levels[$program_id]['levels'][] = $year;
                            }
                            ?>
                            <?php foreach ($grouped_year_levels as $program_id => $group): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-dark h-100">
                                    <div class="card-header bg-dark text-white">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($group['program_name']); ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($group['levels'])): ?>
                                        <div class="alert alert-info mb-0">
                                            <small>No year levels added yet. Click "Add Year Level" to create one.</small>
                                        </div>
                                        <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($group['levels'] as $year): ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="card border-secondary">
                                                    <div class="card-header bg-secondary text-white text-center">
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($year['year_name']); ?></h6>
                                                    </div>
                                                    <div class="card-body text-center">
                                                        <p class="mb-2"><strong>Year:</strong> <?php echo $year['year_level']; ?></p>
                                                        <p class="mb-2"><strong>Semesters:</strong> <?php echo $year['semesters_count']; ?></p>
                                                        <div class="d-flex justify-content-center gap-1 mb-2">
                                                            <?php for ($i = 1; $i <= $year['semesters_count']; $i++): ?>
                                                            <span class="badge bg-dark"><?php echo $i; ?></span>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <div>
                                                            <button class="btn btn-sm btn-outline-warning" onclick="editCollegeYear(<?php echo $year['id']; ?>)" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCollegeYear(<?php echo $year['id']; ?>, '<?php echo htmlspecialchars($year['year_name']); ?>')" title="Delete">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
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

                    <!-- College Subjects Tab -->
                    <div class="tab-pane fade" id="college-subjects" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">College Subjects</h5>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCollegeSubjectModal">
                                <i class="bi bi-plus-circle"></i> Add Subject
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Code</th>
                                        <th>Title</th>
                                        <th>Units</th>
                                        <th>Lec/Lab</th>
                                        <th>Program</th>
                                        <th>Year/Sem</th>
                                        <th>Prerequisites</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($college_subjects as $subject): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($subject['subject_title']); ?></td>
                                        <td><?php echo $subject['units']; ?></td>
                                        <td><?php echo $subject['lecture_hours']; ?>/<?php echo $subject['lab_hours']; ?></td>
                                        <td><?php echo htmlspecialchars($subject['program_name'] ?? 'Unassigned'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($subject['year_name'] ?? 'N/A'); ?>
                                            / <?php echo $subject['semester'] ?? 'N/A'; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($subject['prerequisites'] ?? 'None'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $subject['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $subject['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1" onclick="editCollegeSubject(<?php echo $subject['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteCollegeSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_code']); ?>')">
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
const collegePrograms = <?php echo json_encode($programs); ?>;
const programsData = <?php echo json_encode($programs); ?>;
const collegeYearLevels = <?php echo json_encode($year_levels); ?>;
const yearLevelsData = <?php echo json_encode($year_levels); ?>;

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) {
        window.history.back();
    } else {
        window.location.href = 'curriculum.php';
    }
}
</script>

<?php include '../../includes/footer.php'; ?>