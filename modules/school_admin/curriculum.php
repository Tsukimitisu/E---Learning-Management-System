<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Curriculum Management";

// Fetch data for stats
$track_count = $conn->query("SELECT COUNT(*) as count FROM shs_tracks WHERE is_active = 1")->fetch_assoc()['count'];
$program_count = $conn->query("SELECT COUNT(*) as count FROM programs WHERE is_active = 1")->fetch_assoc()['count'];
$shs_subject_count = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type IN ('shs_core', 'shs_applied', 'shs_specialized') AND is_active = 1")->fetch_assoc()['count'];
$college_subject_count = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type = 'college' AND is_active = 1")->fetch_assoc()['count'];

// Fetch actual data arrays for display and JavaScript
$tracks = [];
$tracks_result = $conn->query("SELECT id, track_name AS name, track_code, description, is_active FROM shs_tracks ORDER BY track_name");
if ($tracks_result) {
    while ($row = $tracks_result->fetch_assoc()) {
        $tracks[] = $row;
    }
}

$strands = [];
$strands_result = $conn->query("
    SELECT st.id, st.strand_name AS name, st.strand_code, st.description, st.is_active, st.track_id,
           t.track_name AS track_name
    FROM shs_strands st
    LEFT JOIN shs_tracks t ON st.track_id = t.id
    ORDER BY st.strand_name
");
if ($strands_result) {
    while ($row = $strands_result->fetch_assoc()) {
        $strands[] = $row;
    }
}

$grade_levels = [];
$grade_levels_result = $conn->query("SELECT id, grade_name AS name, semesters_count AS semesters, is_active FROM shs_grade_levels ORDER BY grade_name");
if ($grade_levels_result) {
    while ($row = $grade_levels_result->fetch_assoc()) {
        $grade_levels[] = $row;
    }
}

$college_programs = [];
$college_programs_result = $conn->query("
    SELECT id, program_code AS code, program_name AS name, degree_level, school_id, is_active
    FROM programs
    ORDER BY program_code
");
if ($college_programs_result) {
    while ($row = $college_programs_result->fetch_assoc()) {
        $college_programs[] = $row;
    }
}

$college_year_levels = [];
$college_year_levels_result = $conn->query("
    SELECT id, program_id, year_level as year_number, year_name as name, semesters_count as semesters, is_active
    FROM program_year_levels
    ORDER BY program_id, year_level
");
if ($college_year_levels_result) {
    while ($row = $college_year_levels_result->fetch_assoc()) {
        $college_year_levels[] = $row;
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
                        <i class="bi bi-book"></i> Curriculum Management
                    </h4>
                    <br><small class="text-muted">Select SHS or College to manage subjects</small>
                </span>
            </div>
        </div>

        <div id="alertContainer" class="mt-3"></div>

        <div class="row mt-4">
            <!-- SHS Curriculum Card -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-mortarboard"></i> Senior High School (SHS) Curriculum
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Manage SHS strands, grade levels, and subject assignments.</p>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-check-circle text-success"></i> Specialized Strands</li>
                            <li><i class="bi bi-check-circle text-success"></i> Grade 11 & 12 Levels</li>
                            <li><i class="bi bi-check-circle text-success"></i> Subject Assignments</li>
                        </ul>
                    </div>
                    <div class="card-footer">
                        <a href="shs_curriculum.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-arrow-right"></i> Manage SHS Curriculum
                        </a>
                    </div>
                </div>
            </div>

            <!-- College Curriculum Card -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-building"></i> College Curriculum
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Manage college programs, year levels, subjects, and course assignments.</p>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-check-circle text-success"></i> Degree Programs (BSIT, BSCS, etc.)</li>
                            <li><i class="bi bi-check-circle text-success"></i> Year Level Structure</li>
                            <li><i class="bi bi-check-circle text-success"></i> Subject Prerequisites</li>
                            <li><i class="bi bi-check-circle text-success"></i> Course Assignments</li>
                        </ul>
                    </div>
                    <div class="card-footer">
                        <a href="college_curriculum.php" class="btn btn-info btn-sm">
                            <i class="bi bi-arrow-right"></i> Manage College Curriculum
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary">
                            <?php
                            $track_count = $conn->query("SELECT COUNT(*) as count FROM shs_tracks WHERE is_active = 1")->fetch_assoc()['count'];
                            echo $track_count;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">SHS Tracks</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success">
                            <?php
                            $program_count = $conn->query("SELECT COUNT(*) as count FROM programs WHERE is_active = 1")->fetch_assoc()['count'];
                            echo $program_count;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">College Programs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning">
                            <?php
                            $shs_subject_count = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type IN ('shs_core', 'shs_applied', 'shs_specialized') AND is_active = 1")->fetch_assoc()['count'];
                            echo $shs_subject_count;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">SHS Subjects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-danger">
                            <?php
                            $college_subject_count = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type = 'college' AND is_active = 1")->fetch_assoc()['count'];
                            echo $college_subject_count;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">College Subjects</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-activity"></i> Recent Curriculum Activity</h6>
            </div>
            <div class="card-body">
                <?php
                $recent_activity = $conn->query("
                    SELECT al.action,
                           al.timestamp AS created_at,
                           COALESCE(up.first_name, '') AS first_name,
                           COALESCE(up.last_name, '') AS last_name,
                           u.email
                    FROM audit_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    LEFT JOIN user_profiles up ON up.user_id = u.id
                    WHERE al.action LIKE '%curriculum%' OR al.action LIKE '%subject%' OR al.action LIKE '%program%' OR al.action LIKE '%track%'
                    ORDER BY al.timestamp DESC
                    LIMIT 5
                ");

                if ($recent_activity->num_rows > 0) {
                    echo '<div class="list-group list-group-flush">';
                    while ($activity = $recent_activity->fetch_assoc()) {
                        echo '<div class="list-group-item px-0">';
                        echo '<small class="text-muted">' . date('M d, Y H:i', strtotime($activity['created_at'])) . '</small><br>';
                        echo '<span>' . htmlspecialchars($activity['action']) . '</span>';
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<p class="text-muted mb-0">No recent curriculum activity</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

        <div class="card-body curriculum-management-tracks">
                <div class="tab-content" id="curriculumTabContent">

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
                    <div class="tab-pane fade" id="grades" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Grade Levels & Semesters</h5>
                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                                <i class="bi bi-plus-circle"></i> Add Grade Level
                            </button>
                        </div>
                        <div class="row">
                            <?php foreach ($grade_levels as $grade): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white d-flex justify-content-between">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($grade['name']); ?></h5>
                                        <span class="badge bg-light text-dark"><?php echo $grade['semesters']; ?> Semesters</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6>Semesters:</h6>
                                                <?php for ($i = 1; $i <= $grade['semesters']; $i++): ?>
                                                <span class="badge bg-secondary me-1"><?php echo $i; ?><?php echo $i == 1 ? 'st' : ($i == 2 ? 'nd' : 'rd'); ?> Semester</span>
                                                <?php endfor; ?>
                                            </div>
                                            <div>
                                                <button class="btn btn-sm btn-outline-warning me-1" onclick="editGrade(<?php echo $grade['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteGrade(<?php echo $grade['id']; ?>)">
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

                    <!-- SHS Subjects Tab -->
                    <div class="tab-pane fade" id="subjects" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">SHS Subjects</h5>
                            <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                                <i class="bi bi-plus-circle"></i> Add SHS Subject
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Code</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Units</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($subject = $subjects_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($subject['subject_title']); ?></td>
                                        <td><span class="badge bg-primary">Core</span></td>
                                        <td><?php echo $subject['units']; ?></td>
                                        <td><?php echo $subject['units'] * 1.5; ?> hrs</td>
                                        <td>
                                            <span class="badge bg-<?php echo $subject['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $subject['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1" onclick="editSubject(<?php echo $subject['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="assignSubject(<?php echo $subject['id']; ?>)">
                                                <i class="bi bi-link"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Subject Assignments Tab -->
                    <div class="tab-pane fade" id="assignments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Subject-Track-Strand Assignments</h5>
                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#assignSubjectModal">
                                <i class="bi bi-link"></i> Assign Subject
                            </button>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> This section manages which subjects are offered in which tracks, strands, grade levels, and semesters.
                        </div>
                        <div class="row">
                            <?php foreach ($tracks as $track): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($track['name']); ?> Track</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $track_strands = array_filter($strands, function($s) use ($track) { return $s['track_id'] == $track['id']; });
                                        foreach ($track_strands as $strand):
                                        ?>
                                        <div class="mb-3">
                                            <h6 class="text-success"><?php echo htmlspecialchars($strand['name']); ?> Strand</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Grade 11</h6>
                                                    <small class="text-muted">Subjects will be listed here</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Grade 12</h6>
                                                    <small class="text-muted">Subjects will be listed here</small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- College Programs Tab -->
                    <div class="tab-pane fade" id="programs" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">College Programs</h5>
                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                                <i class="bi bi-plus-circle"></i> Add Program
                            </button>
                        </div>
                        <div class="row">
                            <?php foreach ($college_programs as $program): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 border-secondary">
                                    <div class="card-header bg-secondary text-white d-flex justify-content-between">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($program['code']); ?></h6>
                                        <small><?php echo htmlspecialchars($program['degree_level']); ?></small>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($program['name']); ?></h6>
                                        <p class="card-text">
                                            <strong>Duration:</strong> <?php echo $program['duration_years']; ?> years<br>
                                            <strong>Status:</strong>
                                            <span class="badge bg-<?php echo $program['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $program['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </p>
                                        <div class="d-flex justify-content-between gap-2">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editProgram(<?php echo $program['id']; ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewProgramCurriculum(<?php echo $program['id']; ?>)">
                                                <i class="bi bi-eye"></i> Curriculum
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

                    <!-- College Subjects Tab -->
                    <div class="tab-pane fade" id="college-subjects" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">College Subjects</h5>
                            <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addCollegeSubjectModal">
                                <i class="bi bi-plus-circle"></i> Add College Subject
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Code</th>
                                        <th>Title</th>
                                        <th>Units</th>
                                        <th>Lecture Hours</th>
                                        <th>Lab Hours</th>
                                        <th>Program</th>
                                        <th>Year Level</th>
                                        <th>Semester</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $college_subjects_result = $conn->query("
                                        SELECT cs.id, cs.subject_code, cs.subject_title, cs.units, cs.lecture_hours, cs.lab_hours, 
                                               cs.semester, cs.is_active, p.program_code, p.program_name, pyl.year_name
                                        FROM curriculum_subjects cs
                                        LEFT JOIN programs p ON cs.program_id = p.id
                                        LEFT JOIN program_year_levels pyl ON cs.year_level_id = pyl.id
                                        WHERE cs.subject_type = 'college'
                                        ORDER BY cs.subject_code
                                    ");
                                    while ($subject = $college_subjects_result->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($subject['subject_title']); ?></td>
                                        <td><?php echo $subject['units']; ?></td>
                                        <td><?php echo $subject['lecture_hours'] ?? 0; ?></td>
                                        <td><?php echo $subject['lab_hours'] ?? 0; ?></td>
                                        <td><?php echo htmlspecialchars($subject['program_code'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($subject['year_name'] ?? '-'); ?></td>
                                        <td><?php echo $subject['semester']; ?></td>
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
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- College Courses Tab -->
                    <div class="tab-pane fade" id="college-courses" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">College Courses</h5>
                            <button class="btn btn-sm text-white" style="background-color: #6c757d;" data-bs-toggle="modal" data-bs-target="#addCollegeCourseModal">
                                <i class="bi bi-plus-circle"></i> Add Course
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Title</th>
                                        <th>Units</th>
                                        <th>Hours</th>
                                        <th>Prerequisites</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Mock college courses data
                                    $college_courses = [
                                        ['code' => 'CS101', 'title' => 'Introduction to Computing', 'units' => 3, 'hours' => 3, 'prerequisites' => 'None', 'active' => 1],
                                        ['code' => 'CS102', 'title' => 'Computer Programming 1', 'units' => 3, 'hours' => 6, 'prerequisites' => 'CS101', 'active' => 1],
                                        ['code' => 'MATH101', 'title' => 'College Algebra', 'units' => 3, 'hours' => 3, 'prerequisites' => 'None', 'active' => 1],
                                        ['code' => 'ENG101', 'title' => 'Communication Skills 1', 'units' => 3, 'hours' => 3, 'prerequisites' => 'None', 'active' => 1]
                                    ];
                                    foreach ($college_courses as $course):
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                                        <td><?php echo $course['units']; ?></td>
                                        <td><?php echo $course['hours']; ?> hrs</td>
                                        <td><?php echo htmlspecialchars($course['prerequisites']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $course['active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $course['active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1" onclick="editCollegeCourse('<?php echo $course['code']; ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="assignCollegeCourse('<?php echo $course['code']; ?>')">
                                                <i class="bi bi-link"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- College Year Levels Tab -->
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
                            foreach ($college_programs as $program) {
                                $program_id = $program['id'];
                                if (!isset($grouped_year_levels[$program_id])) {
                                    $grouped_year_levels[$program_id] = [
                                        'program_name' => $program['name'],
                                        'levels' => []
                                    ];
                                }
                            }
                            
                            // Then, add year levels to their programs
                            foreach ($college_year_levels as $year) {
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
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($year['name']); ?></h6>
                                                    </div>
                                                    <div class="card-body text-center">
                                                        <p class="mb-2"><strong>Year:</strong> <?php echo $year['year_number']; ?></p>
                                                        <p class="mb-2"><strong>Semesters:</strong> <?php echo $year['semesters']; ?></p>
                                                        <div class="d-flex justify-content-center gap-1 mb-2">
                                                            <?php for ($i = 1; $i <= $year['semesters']; $i++): ?>
                                                            <span class="badge bg-dark"><?php echo $i; ?></span>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <div>
                                                            <button class="btn btn-sm btn-outline-warning" onclick="editCollegeYear(<?php echo $year['id']; ?>)" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCollegeYear(<?php echo $year['id']; ?>, '<?php echo htmlspecialchars($year['name']); ?>')" title="Delete">
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

                    <!-- Grading Rules Tab -->
                    <div class="tab-pane fade" id="grading" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Grading Rules & Weights</h5>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#gradingRulesModal">
                                <i class="bi bi-gear"></i> Configure Rules
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning">
                                        <h6 class="mb-0">SHS Grading Weights</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-2"><strong>Core Subjects:</strong> 60%</p>
                                        <p class="mb-2"><strong>Applied Subjects:</strong> 20%</p>
                                        <p class="mb-0"><strong>Specialized Subjects:</strong> 20%</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-dark">
                                    <div class="card-header bg-dark text-white">
                                        <h6 class="mb-0">College Grading System</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-2"><strong>Major Courses:</strong> 70%</p>
                                        <p class="mb-2"><strong>General Education:</strong> 20%</p>
                                        <p class="mb-0"><strong>Electives:</strong> 10%</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0">Subject/Course Category Rules</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>SHS Categories:</h6>
                                                <p class="mb-2"><strong>Core:</strong> Required for all strands</p>
                                                <p class="mb-2"><strong>Applied:</strong> Strand-specific requirements</p>
                                                <p class="mb-0"><strong>Specialized:</strong> Career-focused electives</p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>College Categories:</h6>
                                                <p class="mb-2"><strong>Major:</strong> Program-specific core courses</p>
                                                <p class="mb-2"><strong>General Education:</strong> Liberal education requirements</p>
                                                <p class="mb-0"><strong>Electives:</strong> Free choice courses</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Edit Track Modal -->
<div class="modal fade" id="editTrackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Academic Track</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTrackForm">
                <input type="hidden" name="track_id" id="editTrackId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Track Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="track_name" id="editTrackName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editTrackDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editTrackStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Update Track
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Strand Modal -->
<div class="modal fade" id="editStrandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Academic Strand</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStrandForm">
                <input type="hidden" name="strand_id" id="editStrandId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Track <span class="text-danger">*</span></label>
                        <select class="form-select" name="track_id" id="editStrandTrack" required>
                            <option value="">-- Select Track --</option>
                            <?php foreach ($tracks as $track): ?>
                            <option value="<?php echo $track['id']; ?>"><?php echo htmlspecialchars($track['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Strand Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="strand_name" id="editStrandName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editStrandDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editStrandStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Update Strand
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Grade Level Modal -->
<div class="modal fade" id="editGradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Grade Level</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editGradeForm">
                <input type="hidden" name="grade_id" id="editGradeId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Grade Level Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="grade_name" id="editGradeName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Semesters <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="semesters" id="editGradeSemesters" required min="1" max="4">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editGradeStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-save"></i> Update Grade Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit SHS Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSubjectForm">
                <input type="hidden" name="subject_id" id="editSubjectId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject_code" id="editSubjectCode" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" id="editSubjectCategory" required>
                                <option value="core">Core</option>
                                <option value="applied">Applied</option>
                                <option value="specialized">Specialized</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject_title" id="editSubjectTitle" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="units" id="editSubjectUnits" required min="0.5" max="5" step="0.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hours per Week</label>
                            <input type="number" class="form-control" name="hours" id="editSubjectHours" min="1" max="10">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Prerequisites</label>
                        <input type="text" class="form-control" name="prerequisites" id="editSubjectPrerequisites" placeholder="e.g. None, Grade 11 subjects">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editSubjectStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <!-- Edit Track Modal -->
                        <i class="bi bi-save"></i> Update Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Program Modal -->
<div class="modal fade" id="editProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit College Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProgramForm">
                <input type="hidden" name="program_id" id="editProgramId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Program Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="program_code" id="editProgramCode" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Degree Level <span class="text-danger">*</span></label>
                            <select class="form-select" name="degree_level" id="editProgramLevel" required>
                                <option value="Associate">Associate Degree</option>
                                <option value="Bachelor">Bachelor's Degree</option>
                                <option value="Master">Master's Degree</option>
                                <option value="Doctorate">Doctorate</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Program Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="program_name" id="editProgramName" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="duration_years" id="editProgramDuration" required min="1" max="6">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Units Required</label>
                            <input type="number" class="form-control" name="total_units" id="editProgramUnits" min="1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Program Description</label>
                        <textarea class="form-control" name="description" id="editProgramDescription" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editProgramStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-save"></i> Update Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit College Course Modal -->
<div class="modal fade" id="editCollegeCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit College Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCollegeCourseForm">
                <input type="hidden" name="course_code" id="editCourseCode">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="course_code" id="editCourseCodeInput" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" id="editCourseCategory" required>
                                <option value="major">Major Course</option>
                                <option value="general">General Education</option>
                                <option value="elective">Elective</option>
                                <option value="prerequisite">Prerequisite</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Course Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="course_title" id="editCourseTitle" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Credit Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="units" id="editCourseUnits" required min="0.5" max="6" step="0.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Hours per Week</label>
                            <input type="number" class="form-control" name="hours" id="editCourseHours" min="1" max="10">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lecture Hours</label>
                            <input type="number" class="form-control" name="lecture_hours" id="editCourseLectureHours" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Laboratory Hours</label>
                            <input type="number" class="form-control" name="lab_hours" id="editCourseLabHours" min="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Prerequisites</label>
                        <input type="text" class="form-control" name="prerequisites" id="editCoursePrerequisites" placeholder="e.g. CS101, MATH101 or None">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Course Description</label>
                        <textarea class="form-control" name="description" id="editCourseDescription" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="active" id="editCourseStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-save"></i> Update Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit College Year Level Modal -->
<div class="modal fade" id="editCollegeYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit College Year Level</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCollegeYearForm">
                <input type="hidden" name="year_id" id="editYearId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Year Level Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="year_name" id="editYearName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year Number <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="year_number" id="editYearNumber" required min="1" max="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semesters in this Year <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="semesters" id="editYearSemesters" required min="1" max="4">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editYearStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-save"></i> Update Year Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Grading Rules Modal -->
<div class="modal fade" id="gradingRulesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-calculator"></i> Configure Grading Rules</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="gradingRulesForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>SHS Grading Weights</h6>
                            <div class="mb-3">
                                <label class="form-label">Core Subjects (%)</label>
                                <input type="number" class="form-control" name="shs_core_weight" min="0" max="100" value="60">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Applied Subjects (%)</label>
                                <input type="number" class="form-control" name="shs_applied_weight" min="0" max="100" value="20">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Specialized Subjects (%)</label>
                                <input type="number" class="form-control" name="shs_specialized_weight" min="0" max="100" value="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>College Grading Weights</h6>
                            <div class="mb-3">
                                <label class="form-label">Major Courses (%)</label>
                                <input type="number" class="form-control" name="college_major_weight" min="0" max="100" value="70">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">General Education (%)</label>
                                <input type="number" class="form-control" name="college_general_weight" min="0" max="100" value="20">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Electives (%)</label>
                                <input type="number" class="form-control" name="college_elective_weight" min="0" max="100" value="10">
                            </div>
                        </div>
                    </div>

                    <h6 class="mt-4">Category Rules</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>SHS Rules</h6>
                            <div class="mb-3">
                                <label class="form-label">Core Subject Requirements</label>
                                <textarea class="form-control" name="shs_core_rules" rows="2">Required for all strands. Covers essential competencies.</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Applied Subject Requirements</label>
                                <textarea class="form-control" name="shs_applied_rules" rows="2">Strand-specific subjects that develop specialized skills.</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Specialized Subject Requirements</label>
                                <textarea class="form-control" name="shs_specialized_rules" rows="2">Career-focused electives chosen by students.</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>College Rules</h6>
                            <div class="mb-3">
                                <label class="form-label">Major Course Requirements</label>
                                <textarea class="form-control" name="college_major_rules" rows="2">Program-specific core courses essential for the degree.</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">General Education Requirements</label>
                                <textarea class="form-control" name="college_general_rules" rows="2">Liberal education courses required for well-rounded education.</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Elective Course Requirements</label>
                                <textarea class="form-control" name="college_elective_rules" rows="2">Free choice courses for specialization or interest.</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save"></i> Save Rules
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'curriculum_modals.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Track data for JavaScript
const tracksData = <?php echo json_encode($tracks); ?>;
const strandsData = <?php echo json_encode($strands); ?>;

// Form handlers
document.getElementById('addTrackForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_track.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addStrandForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_strand.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addGradeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_grade_level.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_shs_subject.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('assignSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('/elms_system/modules/school_admin/process/assign_shs_subject.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addProgramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_college_program.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addCollegeCourseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_college_course.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editCollegeSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('/elms_system/modules/school_admin/process/update_subject.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert('Subject updated successfully!', 'success');
            $('#editCollegeSubjectModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('addCollegeYearForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/add_college_year_level.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('assignCollegeCourseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('/elms_system/modules/school_admin/process/assign_college_course.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editTrackForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_track.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editStrandForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_strand.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editGradeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_grade_level.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editSubjectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_subject.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editProgramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_college_program.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editCollegeCourseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_college_course.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('editCollegeYearForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_college_year_level.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

document.getElementById('gradingRulesForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/update_grading_rules.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

// Utility functions
function loadStrands(trackId) {
    const strandSelect = document.getElementById('strandSelect');
    strandSelect.innerHTML = '<option value="">-- Select Strand --</option>';

    if (trackId) {
        const trackStrands = strandsData.filter(strand => strand.track_id == trackId);
        trackStrands.forEach(strand => {
            const option = document.createElement('option');
            option.value = strand.id;
            option.textContent = strand.name;
            strandSelect.appendChild(option);
        });
    }
}

function editTrack(id) {
    // Find track data and populate edit modal
    const tracksData = <?php echo json_encode($tracks); ?>;
    const track = tracksData.find(t => t.id == id);
    if (track) {
        document.getElementById('editTrackId').value = track.id;
        document.getElementById('editTrackName').value = track.name;
        document.getElementById('editTrackDescription').value = track.description;
        document.getElementById('editTrackStatus').value = track.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editTrackModal')).show();
    }
}

function deleteTrack(id) {
    if (confirm('Are you sure you want to delete this track? This will also delete all associated strands and may affect subject assignments.')) {
        fetch('process/delete_track.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ track_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
    }
}

function editStrand(id) {
    const strandsData = <?php echo json_encode($strands); ?>;
    const strand = strandsData.find(s => s.id == id);
    if (strand) {
        document.getElementById('editStrandId').value = strand.id;
        document.getElementById('editStrandName').value = strand.name;
        document.getElementById('editStrandTrack').value = strand.track_id;
        document.getElementById('editStrandDescription').value = strand.description;
        document.getElementById('editStrandStatus').value = strand.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editStrandModal')).show();
    }
}

function deleteStrand(id) {
    if (confirm('Are you sure you want to delete this strand? This may affect subject assignments.')) {
        fetch('process/delete_strand.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ strand_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
    }
}

function editGrade(id) {
    const gradeLevels = <?php echo json_encode($grade_levels); ?>;
    const grade = gradeLevels.find(g => g.id == id);
    if (grade) {
        document.getElementById('editGradeId').value = grade.id;
        document.getElementById('editGradeName').value = grade.name;
        document.getElementById('editGradeSemesters').value = grade.semesters;
        document.getElementById('editGradeStatus').value = grade.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editGradeModal')).show();
    }
}

function deleteGrade(id) {
    if (confirm('Are you sure you want to delete this grade level? This may affect subject assignments.')) {
        fetch('process/delete_grade_level.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ grade_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
    }
}

function editSubject(id) {
    // Fetch subject data and populate edit modal
    fetch(`process/get_subject.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const subject = data.subject;
                document.getElementById('editSubjectId').value = subject.id;
                document.getElementById('editSubjectCode').value = subject.subject_code;
                document.getElementById('editSubjectTitle').value = subject.subject_title;
                document.getElementById('editSubjectCategory').value = subject.category || 'core';
                document.getElementById('editSubjectUnits').value = subject.units;
                document.getElementById('editSubjectHours').value = subject.hours || 3;
                document.getElementById('editSubjectPrerequisites').value = subject.prerequisites || '';
                document.getElementById('editSubjectStatus').value = subject.is_active ? '1' : '0';
                new bootstrap.Modal(document.getElementById('editSubjectModal')).show();
            } else {
                showAlert('Failed to load subject data', 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
}

function editProgram(id) {
    const programs = <?php echo json_encode($college_programs); ?>;
    const program = programs.find(p => p.id == id);
    if (program) {
        document.getElementById('editProgramId').value = program.id;
        document.getElementById('editProgramCode').value = program.code;
        document.getElementById('editProgramName').value = program.name;
        document.getElementById('editProgramLevel').value = program.degree_level;
        document.getElementById('editProgramDuration').value = program.duration_years;
        document.getElementById('editProgramUnits').value = program.total_units || '';
        document.getElementById('editProgramDescription').value = program.description || '';
        document.getElementById('editProgramStatus').value = program.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editProgramModal')).show();
    }
}

function viewProgramCurriculum(id) {
    // Redirect to program-specific curriculum view
    window.location.href = `program_curriculum.php?program_id=${id}`;
}

function editCollegeCourse(code) {
    // Fetch course data and populate edit modal
    fetch(`process/get_college_course.php?code=${code}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const course = data.course;
                document.getElementById('editCourseCode').value = course.code;
                document.getElementById('editCourseTitle').value = course.title;
                document.getElementById('editCourseCategory').value = course.category;
                document.getElementById('editCourseUnits').value = course.units;
                document.getElementById('editCourseHours').value = course.hours;
                document.getElementById('editCourseLectureHours').value = course.lecture_hours || '';
                document.getElementById('editCourseLabHours').value = course.lab_hours || '';
                document.getElementById('editCoursePrerequisites').value = course.prerequisites || '';
                document.getElementById('editCourseDescription').value = course.description || '';
                document.getElementById('editCourseStatus').value = course.active ? '1' : '0';
                new bootstrap.Modal(document.getElementById('editCollegeCourseModal')).show();
            } else {
                showAlert('Failed to load course data', 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
}

function assignCollegeCourse(code) {
   
    document.querySelector('select[name="course_id"]').value = code;
    new bootstrap.Modal(document.getElementById('assignCollegeCourseModal')).show();
}

function editCollegeYear(id) {
    const yearLevels = <?php echo json_encode($college_year_levels); ?>;
    const year = yearLevels.find(y => y.id == id);
    if (year) {
        document.getElementById('editYearId').value = year.id;
        document.getElementById('editYearName').value = year.name;
        document.getElementById('editYearNumber').value = year.year_number;
        document.getElementById('editYearSemesters').value = year.semesters;
        document.getElementById('editYearStatus').value = year.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editCollegeYearModal')).show();
    }
}

function assignSubject(id) {
    const target = document.querySelector('select[name="subject_id"]') || document.querySelector('input[name="subject_id"]');
    if (target) target.value = id;
    new bootstrap.Modal(document.getElementById('assignSubjectModal')).show();
}

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) {
        window.history.back();
    } else {
        window.location.href = 'index.php';
    }
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

</body>
</html>