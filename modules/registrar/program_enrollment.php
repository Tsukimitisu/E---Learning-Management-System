<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Program Enrollment";
$registrar_id = $_SESSION['user_id'];

// Get registrar's branch
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;

// Get branch info
$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Fetch College programs with year levels
$programs = $conn->query("
    SELECT p.id, p.program_code, p.program_name, p.degree_level
    FROM programs p
    WHERE p.is_active = 1
    ORDER BY p.program_code
");

// Fetch program year levels
$year_levels_query = $conn->query("
    SELECT pyl.id, pyl.program_id, pyl.year_level, pyl.year_name
    FROM program_year_levels pyl
    WHERE pyl.is_active = 1
    ORDER BY pyl.program_id, pyl.year_level
");
$program_year_levels = [];
while ($row = $year_levels_query->fetch_assoc()) {
    $program_year_levels[$row['program_id']][] = $row;
}

// Fetch SHS strands with grade levels
$strands = $conn->query("
    SELECT s.id, s.strand_code, s.strand_name
    FROM shs_strands s
    WHERE s.is_active = 1
    ORDER BY s.strand_code
");

$grade_levels_query = $conn->query("
    SELECT sgl.id, sgl.strand_id, sgl.grade_level, sgl.grade_name
    FROM shs_grade_levels sgl
    WHERE sgl.is_active = 1
    ORDER BY sgl.strand_id, sgl.grade_level
");
$strand_grade_levels = [];
while ($row = $grade_levels_query->fetch_assoc()) {
    $strand_grade_levels[$row['strand_id']][] = $row;
}

// Fetch students in this branch (both enrolled and not enrolled in any program)
// Note: Students may not have branch_id assigned, so we fetch all active students
$students_query = "
    SELECT 
        u.id,
        u.email,
        up.first_name,
        up.last_name,
        COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
        st.course_id,
        COALESCE(p.program_code, ss.strand_code) as current_program_code,
        COALESCE(p.program_name, ss.strand_name) as current_program_name,
        CASE 
            WHEN st.course_id IS NOT NULL AND EXISTS (SELECT 1 FROM programs WHERE id = st.course_id) THEN 'college'
            WHEN st.course_id IS NOT NULL AND EXISTS (SELECT 1 FROM shs_strands WHERE id = st.course_id) THEN 'shs'
            ELSE NULL 
        END as program_type,
        (SELECT COUNT(*) FROM section_students ss2 
         INNER JOIN sections s ON ss2.section_id = s.id 
         WHERE ss2.student_id = u.id AND s.branch_id = $branch_id AND s.academic_year_id = $current_ay_id AND ss2.status = 'active') as section_count
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN students st ON u.id = st.user_id
    LEFT JOIN programs p ON st.course_id = p.id
    LEFT JOIN shs_strands ss ON st.course_id = ss.id
    WHERE ur.role_id = " . ROLE_STUDENT . " 
    AND u.status = 'active'
    ORDER BY up.last_name, up.first_name
";
$students = $conn->query($students_query);

include '../../includes/header.php';
?>

<style>
    .student-card {
        background: white;
        border-radius: 12px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
        cursor: pointer;
        margin-bottom: 10px;
    }
    .student-card:hover {
        border-color: #800000;
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.1);
    }
    .student-card.selected {
        border-color: #800000;
        background: #fff5f5;
    }
    .student-card.enrolled-program {
        border-left: 4px solid #28a745;
    }
    .student-card.no-program {
        border-left: 4px solid #ffc107;
    }
    
    .program-card {
        background: white;
        border-radius: 15px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
        cursor: pointer;
        overflow: hidden;
    }
    .program-card:hover {
        border-color: #003366;
        box-shadow: 0 5px 20px rgba(0, 51, 102, 0.15);
        transform: translateY(-3px);
    }
    .program-card.selected {
        border-color: #003366;
        background: linear-gradient(135deg, #f0f4f8, #ffffff);
    }
    .program-header {
        background: linear-gradient(135deg, #003366, #004080);
        color: white;
        padding: 15px;
    }
    .shs-program-header {
        background: linear-gradient(135deg, #800000, #a00000);
    }
    
    .year-level-btn {
        border-radius: 20px;
        padding: 8px 20px;
        margin: 5px;
        transition: all 0.2s ease;
    }
    .year-level-btn:hover {
        transform: scale(1.05);
    }
    .year-level-btn.active {
        background-color: #28a745 !important;
        color: white !important;
    }
    
    .enrollment-status-badge {
        font-size: 0.75rem;
        padding: 4px 12px;
        border-radius: 20px;
    }
    
    .stats-card {
        background: linear-gradient(135deg, #17a2b8, #20c997);
        color: white;
        border-radius: 12px;
        padding: 20px;
    }
    
    .bulk-checkbox {
        transform: scale(1.3);
    }
</style>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0" style="color: #003366;">
                    <i class="bi bi-mortarboard-fill"></i> Program Enrollment
                </h4>
                <small class="text-muted">
                    <i class="bi bi-building"></i> <?php echo htmlspecialchars($branch['name'] ?? 'Unknown Branch'); ?> | 
                    A.Y. <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?>
                </small>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#bulkEnrollModal">
                    <i class="bi bi-people-fill"></i> Bulk Enroll
                </button>
                <a href="students.php" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-people"></i> All Students
                </a>
                <a href="enroll.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-grid"></i> Class Enrollment
                </a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h6 class="mb-1"><i class="bi bi-people"></i> Total Students</h6>
                    <h3 class="mb-0"><?php echo $students->num_rows; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <h6 class="mb-1"><i class="bi bi-check-circle"></i> With Program</h6>
                    <h3 class="mb-0" id="enrolledCount">0</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <h6 class="mb-1"><i class="bi bi-exclamation-circle"></i> No Program</h6>
                    <h3 class="mb-0" id="notEnrolledCount">0</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #6f42c1, #e83e8c);">
                    <h6 class="mb-1"><i class="bi bi-grid-3x3"></i> Assigned to Section</h6>
                    <h3 class="mb-0" id="sectionAssignedCount">0</h3>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left: Students List -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #800000; color: white;">
                        <h6 class="mb-0"><i class="bi bi-person-check"></i> Select Student(s)</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="searchStudent" placeholder="Search by name or student no...">
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <select class="form-select form-select-sm" id="filterEnrollment">
                                <option value="">All Students</option>
                                <option value="enrolled">With Program</option>
                                <option value="not_enrolled">No Program Yet</option>
                            </select>
                            <select class="form-select form-select-sm" id="filterSection">
                                <option value="">All</option>
                                <option value="has_section">Has Section</option>
                                <option value="no_section">No Section</option>
                            </select>
                        </div>
                        
                        <div class="students-list" style="max-height: 500px; overflow-y: auto;">
                            <?php 
                            $enrolled_count = 0;
                            $not_enrolled_count = 0;
                            $section_assigned_count = 0;
                            $students->data_seek(0);
                            while ($student = $students->fetch_assoc()): 
                                $has_program = !empty($student['current_program_code']);
                                $has_section = $student['section_count'] > 0;
                                if ($has_program) $enrolled_count++;
                                else $not_enrolled_count++;
                                if ($has_section) $section_assigned_count++;
                            ?>
                            <div class="student-card p-3 <?php echo $has_program ? 'enrolled-program' : 'no-program'; ?>" 
                                 data-student-id="<?php echo $student['id']; ?>"
                                 data-student-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                 data-student-no="<?php echo htmlspecialchars($student['student_no']); ?>"
                                 data-has-program="<?php echo $has_program ? '1' : '0'; ?>"
                                 data-has-section="<?php echo $has_section ? '1' : '0'; ?>"
                                 data-program-type="<?php echo $student['program_type'] ?? ''; ?>"
                                 data-course-id="<?php echo $student['course_id'] ?? ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['student_no']); ?></small>
                                        <div class="mt-1">
                                            <?php if ($has_program): ?>
                                                <span class="badge bg-success enrollment-status-badge">
                                                    <?php echo htmlspecialchars($student['current_program_code']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark enrollment-status-badge">
                                                    <i class="bi bi-exclamation-circle"></i> No Program
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($has_section): ?>
                                                <span class="badge bg-primary enrollment-status-badge">
                                                    <?php echo $student['section_count']; ?> Section(s)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Program Selection -->
            <div class="col-lg-8">
                <div id="enrollmentPanel" style="display: none;">
                    <div class="card shadow-sm">
                        <div class="card-header" style="background-color: #003366; color: white;">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-person-badge"></i> <span id="selectedStudentName">Select a Student</span></h5>
                                <span class="badge bg-light text-dark" id="selectedStudentNo"></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Program Type Tabs -->
                            <ul class="nav nav-tabs mb-4" id="programTypeTabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#collegePrograms">
                                        <i class="bi bi-mortarboard"></i> College Programs
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#shsPrograms">
                                        <i class="bi bi-book"></i> SHS Strands
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <!-- College Programs -->
                                <div class="tab-pane fade show active" id="collegePrograms">
                                    <div class="row" id="collegeProgramsList">
                                        <?php 
                                        $programs->data_seek(0);
                                        while ($program = $programs->fetch_assoc()): 
                                        ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="program-card" data-program-id="<?php echo $program['id']; ?>" data-program-type="college">
                                                <div class="program-header">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($program['program_code']); ?></h6>
                                                    <small><?php echo htmlspecialchars($program['program_name']); ?></small>
                                                </div>
                                                <div class="p-3">
                                                    <div class="year-levels-container">
                                                        <?php if (isset($program_year_levels[$program['id']])): ?>
                                                            <?php foreach ($program_year_levels[$program['id']] as $yl): ?>
                                                            <button type="button" class="btn btn-outline-primary btn-sm year-level-btn" 
                                                                    data-year-level-id="<?php echo $yl['id']; ?>"
                                                                    data-year-level="<?php echo $yl['year_level']; ?>">
                                                                <?php echo htmlspecialchars($yl['year_name']); ?>
                                                            </button>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <small class="text-muted">No year levels defined</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>

                                <!-- SHS Strands -->
                                <div class="tab-pane fade" id="shsPrograms">
                                    <div class="row" id="shsStrandsList">
                                        <?php 
                                        $strands->data_seek(0);
                                        while ($strand = $strands->fetch_assoc()): 
                                        ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="program-card" data-program-id="<?php echo $strand['id']; ?>" data-program-type="shs">
                                                <div class="program-header shs-program-header">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($strand['strand_code']); ?></h6>
                                                    <small><?php echo htmlspecialchars($strand['strand_name']); ?></small>
                                                </div>
                                                <div class="p-3">
                                                    <div class="year-levels-container">
                                                        <?php if (isset($strand_grade_levels[$strand['id']])): ?>
                                                            <?php foreach ($strand_grade_levels[$strand['id']] as $gl): ?>
                                                            <button type="button" class="btn btn-outline-danger btn-sm year-level-btn" 
                                                                    data-year-level-id="<?php echo $gl['id']; ?>"
                                                                    data-year-level="<?php echo $gl['grade_level']; ?>">
                                                                <?php echo htmlspecialchars($gl['grade_name']); ?>
                                                            </button>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <small class="text-muted">No grade levels defined</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 text-center" id="enrollActionContainer" style="display: none;">
                                <div class="alert alert-info">
                                    <strong>Selected:</strong> <span id="selectedProgramText"></span> - <span id="selectedYearText"></span>
                                </div>
                                <button class="btn btn-lg text-white" style="background-color: #800000;" onclick="enrollStudent()">
                                    <i class="bi bi-check-circle"></i> Enroll Student in Program
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Current Enrollment Info -->
                    <div class="card shadow-sm mt-4" id="currentEnrollmentCard" style="display: none;">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-info-circle text-info"></i> Current Enrollment Status</h6>
                        </div>
                        <div class="card-body" id="currentEnrollmentInfo">
                        </div>
                    </div>
                </div>

                <!-- No Student Selected -->
                <div id="noStudentSelected">
                    <div class="card shadow-sm">
                        <div class="card-body text-center p-5">
                            <i class="bi bi-arrow-left-circle display-1 text-muted opacity-25"></i>
                            <h4 class="mt-4 text-muted">Select a Student</h4>
                            <p class="text-muted">Choose a student from the left panel to enroll them in a program and year level.</p>
                            <hr>
                            <p class="text-muted small">
                                <i class="bi bi-info-circle"></i> After enrollment, the <strong>Branch Admin</strong> will assign students to specific sections.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Enroll Modal -->
<div class="modal fade" id="bulkEnrollModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                <h5 class="modal-title"><i class="bi bi-people-fill"></i> Bulk Program Enrollment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <!-- Program Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Program Type</label>
                            <select class="form-select" id="bulkProgramType" onchange="loadBulkPrograms()">
                                <option value="">Select Type...</option>
                                <option value="college">College Program</option>
                                <option value="shs">SHS Strand</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Program/Strand</label>
                            <select class="form-select" id="bulkProgram" onchange="loadBulkYearLevels()">
                                <option value="">Select Program...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Year Level</label>
                            <select class="form-select" id="bulkYearLevel">
                                <option value="">Select Year Level...</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <!-- Student Selection -->
                        <label class="form-label fw-bold">Select Students (without program)</label>
                        <div class="mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="selectAllBulkStudents()">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearBulkStudents()">Clear All</button>
                        </div>
                        <div id="bulkStudentsList" style="max-height: 350px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                            <?php 
                            $students->data_seek(0);
                            while ($student = $students->fetch_assoc()): 
                                if (empty($student['current_program_code'])):
                            ?>
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input bulk-student-cb" value="<?php echo $student['id']; ?>" id="bulk_<?php echo $student['id']; ?>">
                                <label class="form-check-label" for="bulk_<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    <small class="text-muted">(<?php echo htmlspecialchars($student['student_no']); ?>)</small>
                                </label>
                            </div>
                            <?php 
                                endif;
                            endwhile; 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="processBulkEnroll()">
                    <i class="bi bi-check-circle"></i> Enroll Selected Students
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Data from PHP
const programsData = <?php 
    $programs->data_seek(0);
    $progs = [];
    while ($p = $programs->fetch_assoc()) {
        $progs[] = $p;
    }
    echo json_encode($progs);
?>;

const strandsData = <?php 
    $strands->data_seek(0);
    $strs = [];
    while ($s = $strands->fetch_assoc()) {
        $strs[] = $s;
    }
    echo json_encode($strs);
?>;

const programYearLevels = <?php echo json_encode($program_year_levels); ?>;
const strandGradeLevels = <?php echo json_encode($strand_grade_levels); ?>;

// Stats
document.getElementById('enrolledCount').textContent = <?php echo $enrolled_count; ?>;
document.getElementById('notEnrolledCount').textContent = <?php echo $not_enrolled_count; ?>;
document.getElementById('sectionAssignedCount').textContent = <?php echo $section_assigned_count; ?>;

let selectedStudentId = null;
let selectedStudentName = '';
let selectedProgramId = null;
let selectedProgramType = null;
let selectedYearLevelId = null;
let selectedYearLevel = null;

// Search and Filter
document.getElementById('searchStudent').addEventListener('input', filterStudents);
document.getElementById('filterEnrollment').addEventListener('change', filterStudents);
document.getElementById('filterSection').addEventListener('change', filterStudents);

function filterStudents() {
    const search = document.getElementById('searchStudent').value.toLowerCase();
    const enrollmentFilter = document.getElementById('filterEnrollment').value;
    const sectionFilter = document.getElementById('filterSection').value;
    
    document.querySelectorAll('.student-card').forEach(card => {
        const name = card.dataset.studentName.toLowerCase();
        const studentNo = card.dataset.studentNo.toLowerCase();
        const hasProgram = card.dataset.hasProgram === '1';
        const hasSection = card.dataset.hasSection === '1';
        
        let show = name.includes(search) || studentNo.includes(search);
        
        if (enrollmentFilter === 'enrolled' && !hasProgram) show = false;
        if (enrollmentFilter === 'not_enrolled' && hasProgram) show = false;
        if (sectionFilter === 'has_section' && !hasSection) show = false;
        if (sectionFilter === 'no_section' && hasSection) show = false;
        
        card.style.display = show ? 'block' : 'none';
    });
}

// Student Selection
document.querySelectorAll('.student-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.student-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        
        selectedStudentId = this.dataset.studentId;
        selectedStudentName = this.dataset.studentName;
        
        document.getElementById('selectedStudentName').textContent = selectedStudentName;
        document.getElementById('selectedStudentNo').textContent = this.dataset.studentNo;
        
        document.getElementById('enrollmentPanel').style.display = 'block';
        document.getElementById('noStudentSelected').style.display = 'none';
        
        // Reset selections
        resetProgramSelection();
        
        // Show current enrollment info
        showCurrentEnrollment(this);
        
        // Highlight current program if exists
        if (this.dataset.hasProgram === '1') {
            const programType = this.dataset.programType;
            const courseId = this.dataset.courseId;
            const yearLevel = this.dataset.yearLevel;
            
            // Switch to correct tab
            if (programType === 'shs') {
                document.querySelector('[href="#shsPrograms"]').click();
            }
            
            // Highlight the program card
            setTimeout(() => {
                const programCard = document.querySelector(`.program-card[data-program-id="${courseId}"][data-program-type="${programType}"]`);
                if (programCard) {
                    programCard.classList.add('selected');
                    // Highlight year level
                    const yearBtn = programCard.querySelector(`.year-level-btn[data-year-level="${yearLevel}"]`);
                    if (yearBtn) {
                        yearBtn.classList.add('active');
                    }
                }
            }, 100);
        }
    });
});

function showCurrentEnrollment(card) {
    const container = document.getElementById('currentEnrollmentCard');
    const info = document.getElementById('currentEnrollmentInfo');
    
    if (card.dataset.hasProgram === '1') {
        const badges = card.querySelectorAll('.enrollment-status-badge');
        let html = '<div class="d-flex align-items-center justify-content-between">';
        html += '<div>';
        badges.forEach(badge => {
            html += badge.outerHTML + ' ';
        });
        html += '</div>';
        html += '<button class="btn btn-sm btn-outline-warning" onclick="changeProgram()"><i class="bi bi-arrow-repeat"></i> Change Program</button>';
        html += '</div>';
        
        if (card.dataset.hasSection === '1') {
            html += '<div class="alert alert-info mt-3 mb-0"><i class="bi bi-info-circle"></i> This student is already assigned to section(s). Changing the program may affect their section assignments.</div>';
        }
        
        info.innerHTML = html;
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

function resetProgramSelection() {
    selectedProgramId = null;
    selectedProgramType = null;
    selectedYearLevelId = null;
    selectedYearLevel = null;
    
    document.querySelectorAll('.program-card').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.year-level-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('enrollActionContainer').style.display = 'none';
}

// Year Level Selection
document.querySelectorAll('.year-level-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        
        const programCard = this.closest('.program-card');
        
        // Deselect all
        document.querySelectorAll('.program-card').forEach(c => c.classList.remove('selected'));
        document.querySelectorAll('.year-level-btn').forEach(b => b.classList.remove('active'));
        
        // Select current
        programCard.classList.add('selected');
        this.classList.add('active');
        
        selectedProgramId = programCard.dataset.programId;
        selectedProgramType = programCard.dataset.programType;
        selectedYearLevelId = this.dataset.yearLevelId;
        selectedYearLevel = this.dataset.yearLevel;
        
        // Get program name
        const programName = programCard.querySelector('.program-header h6').textContent;
        const yearName = this.textContent.trim();
        
        document.getElementById('selectedProgramText').textContent = programName;
        document.getElementById('selectedYearText').textContent = yearName;
        document.getElementById('enrollActionContainer').style.display = 'block';
    });
});

function changeProgram() {
    resetProgramSelection();
    document.getElementById('currentEnrollmentCard').style.display = 'none';
}

function enrollStudent() {
    if (!selectedStudentId || !selectedProgramId || !selectedYearLevelId) {
        showAlert('warning', 'Please select a student, program, and year level');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'enroll_program');
    formData.append('student_id', selectedStudentId);
    formData.append('program_type', selectedProgramType);
    formData.append('program_id', selectedProgramId);
    formData.append('year_level_id', selectedYearLevelId);
    
    fetch('process/program_enrollment_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('danger', data.message || 'Error enrolling student');
        }
    })
    .catch(error => {
        showAlert('danger', 'Error enrolling student');
        console.error('Error:', error);
    });
}

// Bulk Enroll Functions
function loadBulkPrograms() {
    const type = document.getElementById('bulkProgramType').value;
    const select = document.getElementById('bulkProgram');
    select.innerHTML = '<option value="">Select Program...</option>';
    document.getElementById('bulkYearLevel').innerHTML = '<option value="">Select Year Level...</option>';
    
    if (type === 'college') {
        programsData.forEach(p => {
            select.innerHTML += `<option value="${p.id}">${p.program_code} - ${p.program_name}</option>`;
        });
    } else if (type === 'shs') {
        strandsData.forEach(s => {
            select.innerHTML += `<option value="${s.id}">${s.strand_code} - ${s.strand_name}</option>`;
        });
    }
}

function loadBulkYearLevels() {
    const type = document.getElementById('bulkProgramType').value;
    const programId = document.getElementById('bulkProgram').value;
    const select = document.getElementById('bulkYearLevel');
    select.innerHTML = '<option value="">Select Year Level...</option>';
    
    if (!programId) return;
    
    const levels = type === 'college' ? (programYearLevels[programId] || []) : (strandGradeLevels[programId] || []);
    levels.forEach(l => {
        const name = type === 'college' ? l.year_name : l.grade_name;
        const levelNum = type === 'college' ? l.year_level : l.grade_level;
        select.innerHTML += `<option value="${l.id}" data-level="${levelNum}">${name}</option>`;
    });
}

function selectAllBulkStudents() {
    document.querySelectorAll('.bulk-student-cb').forEach(cb => cb.checked = true);
}

function clearBulkStudents() {
    document.querySelectorAll('.bulk-student-cb').forEach(cb => cb.checked = false);
}

function processBulkEnroll() {
    const programType = document.getElementById('bulkProgramType').value;
    const programId = document.getElementById('bulkProgram').value;
    const yearLevelSelect = document.getElementById('bulkYearLevel');
    const yearLevelId = yearLevelSelect.value;
    const studentIds = Array.from(document.querySelectorAll('.bulk-student-cb:checked')).map(cb => cb.value);
    
    if (!programType || !programId || !yearLevelId) {
        showAlert('warning', 'Please select program type, program, and year level');
        return;
    }
    
    if (studentIds.length === 0) {
        showAlert('warning', 'Please select at least one student');
        return;
    }
    
    if (!confirm(`Enroll ${studentIds.length} student(s) in the selected program?`)) return;
    
    const formData = new FormData();
    formData.append('action', 'bulk_enroll_program');
    formData.append('program_type', programType);
    formData.append('program_id', programId);
    formData.append('year_level_id', yearLevelId);
    formData.append('student_ids', JSON.stringify(studentIds));
    
    fetch('process/program_enrollment_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('bulkEnrollModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('danger', data.message || 'Error enrolling students');
        }
    })
    .catch(error => {
        showAlert('danger', 'Error enrolling students');
        console.error('Error:', error);
    });
}

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <strong>${type === 'success' ? '<i class="bi bi-check-circle"></i>' : '<i class="bi bi-exclamation-circle"></i>'}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>
