<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "College Curriculum Management";

// Fetch college data
$programs_result = $conn->query("SELECT * FROM programs ORDER BY program_code");
$college_programs = $programs_result->fetch_all(MYSQLI_ASSOC);

$year_levels_result = $conn->query("
    SELECT yl.*, p.program_name
    FROM program_year_levels yl
    LEFT JOIN programs p ON yl.program_id = p.id
    ORDER BY yl.program_id, yl.year_level
");
$college_year_levels = $year_levels_result->fetch_all(MYSQLI_ASSOC);

// Fetch college subjects
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
$college_subjects = $college_subjects_result->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-building"></i> College Curriculum Management
            </h4>
            <small class="text-muted">Design and control College curriculum</small>
        </div>

        <div id="alertContainer"></div>

        <!-- College Curriculum Tabs -->
        <div class="card shadow-sm">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="collegeTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="programs-tab" data-bs-toggle="tab" data-bs-target="#programs" type="button">
                            <i class="bi bi-mortarboard-fill"></i> Programs
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="college-yearlevels-tab" data-bs-toggle="tab" data-bs-target="#college-yearlevels" type="button">
                            <i class="bi bi-calendar-range"></i> Year Levels
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="college-subjects-tab" data-bs-toggle="tab" data-bs-target="#college-subjects" type="button">
                            <i class="bi bi-book-half"></i> College Subjects
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="course-assignments-tab" data-bs-toggle="tab" data-bs-target="#course-assignments" type="button">
                            <i class="bi bi-link-45deg"></i> Course Assignments
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
                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                                <i class="bi bi-plus-circle"></i> Add Program
                            </button>
                        </div>
                        <div class="row">
                            <?php foreach ($college_programs as $program): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']); ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text small">
                                            <strong>Degree:</strong> <?php echo htmlspecialchars($program['degree_level']); ?><br>
                                            <strong>School:</strong> <?php
                                                $school_result = $conn->query("SELECT name FROM schools WHERE id = " . $program['school_id']);
                                                $school = $school_result->fetch_assoc();
                                                echo htmlspecialchars($school['name'] ?? 'Unknown');
                                            ?>
                                        </p>
                                        <div class="d-flex justify-content-between">
                                            <span class="badge bg-<?php echo $program['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $program['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                            <div>
                                                <button class="btn btn-sm btn-outline-warning me-1" onclick="editProgram(<?php echo $program['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteProgram(<?php echo $program['id']; ?>)">
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

                    <!-- Year Levels Tab -->
                    <div class="tab-pane fade" id="college-yearlevels" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">College Year Levels</h5>
                            <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#addCollegeYearModal">
                                <i class="bi bi-plus-circle"></i> Add Year Level
                            </button>
                        </div>
                        <div class="row">
                            <?php foreach ($college_year_levels as $year): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card border-dark">
                                    <div class="card-header bg-dark text-white text-center">
                                        <h4 class="mb-0"><?php echo htmlspecialchars($year['year_name'] ?? 'Unknown Year'); ?></h4>
                                    </div>
                                    <div class="card-body text-center">
                                        <p class="mb-2"><strong>Program:</strong> <?php echo htmlspecialchars($year['program_name'] ?? 'Unknown'); ?></p>
                                        <p class="mb-2"><strong>Year Level:</strong> <?php echo $year['year_level'] ?? 'N/A'; ?></p>
                                        <p class="mb-2"><strong>Semesters:</strong> <?php echo $year['semesters_count'] ?? 2; ?></p>
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editCollegeYear(<?php echo $year['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
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
                                <i class="bi bi-plus-circle"></i> Add College Subject
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Title</th>
                                        <th>Units</th>
                                        <th>Lecture Hrs</th>
                                        <th>Lab Hrs</th>
                                        <th>Program</th>
                                        <th>Year Level</th>
                                        <th>Semester</th>
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
                                        <td><?php echo $subject['lecture_hours']; ?></td>
                                        <td><?php echo $subject['lab_hours']; ?></td>
                                        <td><?php echo htmlspecialchars($subject['program_name'] ?? 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars($subject['year_name'] ?? 'Unassigned'); ?></td>
                                        <td><?php echo $subject['semester']; ?></td>
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
                                            <button class="btn btn-sm btn-info me-1" onclick="assignCollegeSubject(<?php echo $subject['id']; ?>)">
                                                <i class="bi bi-link"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteCollegeSubject(<?php echo $subject['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Course Assignments Tab -->
                    <div class="tab-pane fade" id="course-assignments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">College Course Assignments</h5>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignCollegeCourseModal">
                                <i class="bi bi-link-45deg"></i> Assign Course
                            </button>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Assign college courses to specific programs, year levels, and semesters.
                        </div>
                        <!-- Assignment interface will be implemented here -->
                        <div class="text-center text-muted mt-5">
                            <i class="bi bi-cone-striped display-4"></i>
                            <p class="mt-3">Course assignment interface coming soon...</p>
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