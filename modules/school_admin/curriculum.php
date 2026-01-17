<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "SHS Curriculum Management";

// Fetch data for different curriculum components
// For now, we'll use placeholder queries - in production these would be real tables

    // Mock data for tracks (in production, this would be from shs_tracks table)
$tracks = [
    ['id' => 1, 'name' => 'Academic', 'description' => 'Academic Track', 'is_active' => 1],
    ['id' => 2, 'name' => 'TVL', 'description' => 'Technical-Vocational-Livelihood Track', 'is_active' => 1],
    ['id' => 3, 'name' => 'Arts & Design', 'description' => 'Arts and Design Track', 'is_active' => 1],
    ['id' => 4, 'name' => 'Sports', 'description' => 'Sports Track', 'is_active' => 1]
];

// Mock data for strands (in production, this would be from shs_strands table)
$strands = [
    ['id' => 1, 'name' => 'STEM', 'track_id' => 1, 'description' => 'Science, Technology, Engineering, Mathematics', 'is_active' => 1],
    ['id' => 2, 'name' => 'ABM', 'track_id' => 1, 'description' => 'Accountancy, Business, Management', 'is_active' => 1],
    ['id' => 3, 'name' => 'HUMSS', 'track_id' => 1, 'description' => 'Humanities and Social Sciences', 'is_active' => 1],
    ['id' => 4, 'name' => 'GAS', 'track_id' => 1, 'description' => 'General Academic Strand', 'is_active' => 1],
    ['id' => 5, 'name' => 'TVL-ICT', 'track_id' => 2, 'description' => 'Technical-Vocational Livelihood - Information and Communications Technology', 'is_active' => 1]
];

// Mock data for grade levels (in production, this would be from shs_grade_levels table)
$grade_levels = [
    ['id' => 1, 'name' => 'Grade 11', 'semesters' => 2, 'is_active' => 1],
    ['id' => 2, 'name' => 'Grade 12', 'semesters' => 2, 'is_active' => 1]
];

// Mock data for college programs (in production, this would be from college_programs table)
$college_programs = [
    ['id' => 1, 'name' => 'Bachelor of Science in Computer Science', 'code' => 'BSCS', 'degree_level' => 'Bachelor', 'duration_years' => 4, 'is_active' => 1],
    ['id' => 2, 'name' => 'Bachelor of Science in Information Technology', 'code' => 'BSIT', 'degree_level' => 'Bachelor', 'duration_years' => 4, 'is_active' => 1],
    ['id' => 3, 'name' => 'Bachelor of Science in Business Administration', 'code' => 'BSBA', 'degree_level' => 'Bachelor', 'duration_years' => 4, 'is_active' => 1],
    ['id' => 4, 'name' => 'Associate in Computer Technology', 'code' => 'ACT', 'degree_level' => 'Associate', 'duration_years' => 2, 'is_active' => 1]
];

// Mock data for college year levels (in production, this would be from college_year_levels table)
$college_year_levels = [
    ['id' => 1, 'name' => '1st Year', 'year_number' => 1, 'semesters' => 2, 'is_active' => 1],
    ['id' => 2, 'name' => '2nd Year', 'year_number' => 2, 'semesters' => 2, 'is_active' => 1],
    ['id' => 3, 'name' => '3rd Year', 'year_number' => 3, 'semesters' => 2, 'is_active' => 1],
    ['id' => 4, 'name' => '4th Year', 'year_number' => 4, 'semesters' => 2, 'is_active' => 1]
];

// Fetch existing subjects (keeping existing functionality)
$subjects_result = $conn->query("
    SELECT
        s.id,
        s.subject_code,
        s.subject_title,
        s.units,
        s.year_level,
        s.semester,
        s.is_active,
        p.program_name
    FROM subjects s
    LEFT JOIN programs p ON s.program_id = p.id
    ORDER BY s.subject_code
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-book"></i> Curriculum Management
            </h4>
            <small class="text-muted">Design and control SHS & College curriculum</small>
        </div>

        <div id="alertContainer"></div>

        <!-- Curriculum Management Tabs -->
        <div class="card shadow-sm">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="curriculumTabs" role="tablist">
                    <!-- SHS Curriculum -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                            <i class="bi bi-mortarboard"></i> SHS Curriculum
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#tracks" data-bs-toggle="tab">Tracks</a></li>
                            <li><a class="dropdown-item" href="#strands" data-bs-toggle="tab">Strands</a></li>
                            <li><a class="dropdown-item" href="#shs-grades" data-bs-toggle="tab">Grade Levels</a></li>
                            <li><a class="dropdown-item" href="#shs-subjects" data-bs-toggle="tab">SHS Subjects</a></li>
                            <li><a class="dropdown-item" href="#shs-assignments" data-bs-toggle="tab">Subject Assignments</a></li>
                        </ul>
                    </li>

                    <!-- College Curriculum -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                            <i class="bi bi-building"></i> College Curriculum
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#programs" data-bs-toggle="tab">Programs</a></li>
                            <li><a class="dropdown-item" href="#college-courses" data-bs-toggle="tab">College Courses</a></li>
                            <li><a class="dropdown-item" href="#college-yearlevels" data-bs-toggle="tab">Year Levels</a></li>
                            <li><a class="dropdown-item" href="#course-assignments" data-bs-toggle="tab">Course Assignments</a></li>
                        </ul>
                    </li>

                    <!-- General Settings -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="grading-tab" data-bs-toggle="tab" data-bs-target="#grading" type="button">
                            <i class="bi bi-calculator"></i> Grading Rules
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
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
                                        <h6 class="mb-0"><?php echo htmlspecialchars($track['name']); ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text small"><?php echo htmlspecialchars($track['description']); ?></p>
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
                                        <h6 class="mb-0"><?php echo htmlspecialchars($strand['name']); ?></h6>
                                        <small><?php echo htmlspecialchars($tracks[array_search($strand['track_id'], array_column($tracks, 'id'))]['name'] ?? 'Unknown'); ?></small>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text small"><?php echo htmlspecialchars($strand['description']); ?></p>
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
                                        <div class="d-flex justify-content-between">
                                            <button class="btn btn-sm btn-outline-warning" onclick="editProgram(<?php echo $program['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewProgramCurriculum(<?php echo $program['id']; ?>)">
                                                <i class="bi bi-eye"></i> Curriculum
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
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
                            <?php foreach ($college_year_levels as $year): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card border-dark">
                                    <div class="card-header bg-dark text-white text-center">
                                        <h4 class="mb-0"><?php echo htmlspecialchars($year['name']); ?></h4>
                                    </div>
                                    <div class="card-body text-center">
                                        <p class="mb-2"><strong>Year:</strong> <?php echo $year['year_number']; ?></p>
                                        <p class="mb-2"><strong>Semesters:</strong> <?php echo $year['semesters']; ?></p>
                                        <div class="d-flex justify-content-center gap-1">
                                            <?php for ($i = 1; $i <= $year['semesters']; $i++): ?>
                                            <span class="badge bg-secondary"><?php echo $i; ?></span>
                                            <?php endfor; ?>
                                        </div>
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

                    <!-- Course Assignments Tab -->
                    <div class="tab-pane fade" id="course-assignments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">College Course Assignments</h5>
                            <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#assignCollegeCourseModal">
                                <i class="bi bi-link"></i> Assign Course
                            </button>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Assign college courses to specific programs, year levels, and semesters.
                        </div>
                        <div class="row">
                            <?php foreach ($college_programs as $program): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header bg-dark text-white">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($program['code'] . ' - ' . $program['name']); ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <?php for ($year = 1; $year <= $program['duration_years']; $year++): ?>
                                        <div class="mb-3">
                                            <h6 class="text-primary">Year <?php echo $year; ?></h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="small">1st Semester</h6>
                                                    <small class="text-muted">Courses will be listed here</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="small">2nd Semester</h6>
                                                    <small class="text-muted">Courses will be listed here</small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
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

<!-- Add Track Modal -->
<div class="modal fade" id="addTrackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Academic Track</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addTrackForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Track Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="track_name" required placeholder="e.g. Academic, TVL, Arts & Design">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the track"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Add Track
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Strand Modal -->
<div class="modal fade" id="addStrandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Academic Strand</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStrandForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Track <span class="text-danger">*</span></label>
                        <select class="form-select" name="track_id" required>
                            <option value="">-- Select Track --</option>
                            <?php foreach ($tracks as $track): ?>
                            <option value="<?php echo $track['id']; ?>"><?php echo htmlspecialchars($track['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Strand Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="strand_name" required placeholder="e.g. STEM, ABM, HUMSS">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the strand"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Add Strand
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Grade Level Modal -->
<div class="modal fade" id="addGradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Grade Level</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addGradeForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Grade Level Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="grade_name" required placeholder="e.g. Grade 11, Grade 12">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Semesters <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="semesters" required min="1" max="4" value="2">
                        <small class="text-muted">How many semesters in this grade level?</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-save"></i> Add Grade Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add SHS Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSubjectForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject_code" required placeholder="e.g. ORAL-COM-11">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" required>
                                <option value="">-- Select Category --</option>
                                <option value="core">Core</option>
                                <option value="applied">Applied</option>
                                <option value="specialized">Specialized</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject_title" required placeholder="e.g. Oral Communication in Context">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="units" required min="0.5" max="5" step="0.5" value="3">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hours per Week</label>
                            <input type="number" class="form-control" name="hours" min="1" max="10" value="3">
                            <small class="text-muted">Contact hours per week</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Prerequisites (Optional)</label>
                        <input type="text" class="form-control" name="prerequisites" placeholder="e.g. None, Grade 11 subjects">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-save"></i> Add SHS Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Subject Modal -->
<div class="modal fade" id="assignSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-link"></i> Assign Subject to Curriculum</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignSubjectForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <select class="form-select" name="subject_id" required>
                                <option value="">-- Select Subject --</option>
                                <?php
                                $subjects_result->data_seek(0);
                                while ($subject = $subjects_result->fetch_assoc()):
                                ?>
                                <option value="<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_title']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Track <span class="text-danger">*</span></label>
                            <select class="form-select" name="track_id" required onchange="loadStrands(this.value)">
                                <option value="">-- Select Track --</option>
                                <?php foreach ($tracks as $track): ?>
                                <option value="<?php echo $track['id']; ?>"><?php echo htmlspecialchars($track['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Strand <span class="text-danger">*</span></label>
                            <select class="form-select" name="strand_id" required id="strandSelect">
                                <option value="">-- Select Strand --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Grade Level <span class="text-danger">*</span></label>
                            <select class="form-select" name="grade_level_id" required>
                                <option value="">-- Select Grade Level --</option>
                                <?php foreach ($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Semester <span class="text-danger">*</span></label>
                        <select class="form-select" name="semester" required>
                            <option value="">-- Select Semester --</option>
                            <option value="1">1st Semester</option>
                            <option value="2">2nd Semester</option>
                        </select>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This assignment determines when and where this subject is offered in the curriculum.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-link"></i> Assign Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add College Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add College Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addProgramForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Program Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="program_code" required placeholder="e.g. BSCS, BSIT">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Degree Level <span class="text-danger">*</span></label>
                            <select class="form-select" name="degree_level" required>
                                <option value="">-- Select Degree Level --</option>
                                <option value="Associate">Associate Degree</option>
                                <option value="Bachelor">Bachelor's Degree</option>
                                <option value="Master">Master's Degree</option>
                                <option value="Doctorate">Doctorate</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Program Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="program_name" required placeholder="e.g. Bachelor of Science in Computer Science">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="duration_years" required min="1" max="6" value="4">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Units Required</label>
                            <input type="number" class="form-control" name="total_units" min="1" placeholder="e.g. 145">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Program Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the program"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-save"></i> Add Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add College Course Modal -->
<div class="modal fade" id="addCollegeCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add College Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCollegeCourseForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="course_code" required placeholder="e.g. CS101, MATH101">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" required>
                                <option value="">-- Select Category --</option>
                                <option value="major">Major Course</option>
                                <option value="general">General Education</option>
                                <option value="elective">Elective</option>
                                <option value="prerequisite">Prerequisite</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Course Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="course_title" required placeholder="e.g. Introduction to Computing">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Credit Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="units" required min="0.5" max="6" step="0.5" value="3">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Hours per Week</label>
                            <input type="number" class="form-control" name="hours" min="1" max="10" value="3">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lecture Hours</label>
                            <input type="number" class="form-control" name="lecture_hours" min="0" value="3">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Laboratory Hours</label>
                            <input type="number" class="form-control" name="lab_hours" min="0" value="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Prerequisites</label>
                        <input type="text" class="form-control" name="prerequisites" placeholder="e.g. CS101, MATH101 or None">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Course Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief course description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-save"></i> Add Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add College Year Level Modal -->
<div class="modal fade" id="addCollegeYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add College Year Level</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCollegeYearForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Year Level Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="year_name" required placeholder="e.g. 1st Year, 2nd Year">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year Number <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="year_number" required min="1" max="6" value="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semesters in this Year <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="semesters" required min="1" max="4" value="2">
                        <small class="text-muted">Usually 2 semesters per year</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-save"></i> Add Year Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign College Course Modal -->
<div class="modal fade" id="assignCollegeCourseModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-link"></i> Assign College Course to Curriculum</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignCollegeCourseForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" name="course_id" required>
                                <option value="">-- Select Course --</option>
                                <option value="CS101">CS101 - Introduction to Computing</option>
                                <option value="CS102">CS102 - Computer Programming 1</option>
                                <option value="MATH101">MATH101 - College Algebra</option>
                                <option value="ENG101">ENG101 - Communication Skills 1</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Program <span class="text-danger">*</span></label>
                            <select class="form-select" name="program_id" required>
                                <option value="">-- Select Program --</option>
                                <?php foreach ($college_programs as $program): ?>
                                <option value="<?php echo $program['id']; ?>"><?php echo htmlspecialchars($program['code'] . ' - ' . $program['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Year Level <span class="text-danger">*</span></label>
                            <select class="form-select" name="year_level_id" required>
                                <option value="">-- Select Year Level --</option>
                                <?php foreach ($college_year_levels as $year): ?>
                                <option value="<?php echo $year['id']; ?>"><?php echo htmlspecialchars($year['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semester <span class="text-danger">*</span></label>
                            <select class="form-select" name="semester" required>
                                <option value="">-- Select Semester --</option>
                                <option value="1">1st Semester</option>
                                <option value="2">2nd Semester</option>
                                <option value="3">Summer</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Is Required Course?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_required" id="isRequiredCheck" checked>
                            <label class="form-check-label" for="isRequiredCheck">
                                Yes, this is a required course for the program
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This assignment determines when and for which program this course is offered.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-link"></i> Assign Course
                    </button>
                </div>
            </form>
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
        const response = await fetch('process/assign_subject.php', {
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
        const response = await fetch('process/assign_college_course.php', {
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
    // Pre-select the course in the assignment modal
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
    // Pre-select the subject in the assignment modal
    document.querySelector('select[name="subject_id"]').value = id;
    new bootstrap.Modal(document.getElementById('assignSubjectModal')).show();
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