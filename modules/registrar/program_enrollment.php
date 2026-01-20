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
$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Fetch College programs
$programs = $conn->query("SELECT p.id, p.program_code, p.program_name, p.degree_level FROM programs p WHERE p.is_active = 1 ORDER BY p.program_code");

// Fetch program year levels
$year_levels_query = $conn->query("SELECT pyl.id, pyl.program_id, pyl.year_level, pyl.year_name FROM program_year_levels pyl WHERE pyl.is_active = 1 ORDER BY pyl.program_id, pyl.year_level");
$program_year_levels = [];
while ($row = $year_levels_query->fetch_assoc()) { $program_year_levels[$row['program_id']][] = $row; }

// Fetch SHS strands
$strands = $conn->query("SELECT s.id, s.strand_code, s.strand_name FROM shs_strands s WHERE s.is_active = 1 ORDER BY s.strand_code");

$grade_levels_query = $conn->query("SELECT sgl.id, sgl.strand_id, sgl.grade_level, sgl.grade_name FROM shs_grade_levels sgl WHERE sgl.is_active = 1 ORDER BY sgl.strand_id, sgl.grade_level");
$strand_grade_levels = [];
while ($row = $grade_levels_query->fetch_assoc()) { $strand_grade_levels[$row['strand_id']][] = $row; }

// Fetch students query (Complex logic preserved)
$students_query = "
    SELECT 
        u.id, u.email, up.first_name, up.last_name,
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
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC UI COMPONENTS --- */
    .stat-box-modern { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
    
    /* Left Panel: Student Selection */
    .student-list-box { height: 500px; overflow-y: auto; background: #fff; border: 1px solid #eee; border-radius: 12px; }
    .student-card {
        padding: 15px; border-bottom: 1px solid #f9f9f9; border-left: 4px solid transparent; 
        cursor: pointer; transition: 0.2s;
    }
    .student-card:hover { background: #fcfcfc; border-left-color: var(--blue); }
    .student-card.selected { background: #e7f5ff; border-left-color: var(--blue); }
    .student-card.enrolled-program { border-left-color: #28a745; }
    .student-card.no-program { border-left-color: #ffc107; }

    /* Right Panel: Program Cards */
    .program-card-modern {
        background: white; border-radius: 15px; border: 2px solid #f1f1f1;
        transition: 0.3s; cursor: pointer; overflow: hidden; height: 100%;
    }
    .program-card-modern:hover { border-color: var(--blue); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,51,102,0.1); }
    .program-card-modern.selected { border-color: var(--blue); background: #f0f7ff; }
    
    .program-head { background: var(--blue); color: white; padding: 12px 15px; }
    .program-head.shs { background: var(--maroon); }

    .year-level-pill {
        border-radius: 50px; font-weight: 700; font-size: 0.7rem; padding: 6px 15px;
        margin: 4px; transition: 0.2s; border: 1.5px solid #eee; background: white; color: #555;
    }
    .year-level-pill:hover { border-color: var(--maroon); color: var(--maroon); }
    .year-level-pill.active { background: var(--maroon) !important; color: white !important; border-color: var(--maroon) !important; }

    .nav-pills-custom .nav-link { color: #666; font-weight: 700; font-size: 0.8rem; border-radius: 10px; padding: 10px 20px; }
    .nav-pills-custom .nav-link.active { background: var(--blue); color: white; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-mortarboard-fill me-2 text-maroon"></i>Program Enrollment</h4>
            <p class="text-muted small mb-0"><?php echo htmlspecialchars($branch['name'] ?? 'Registrar'); ?> • AY <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#bulkEnrollModal">
                <i class="bi bi-people-fill"></i> Bulk Action
            </button>
            <a href="enroll.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">Class Enrollment</a>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Dashboard Stats -->
    <div class="row g-3 mb-4 animate__animated animate__fadeIn">
        <div class="col-md-3">
            <div class="stat-box-modern border-start border-primary border-5">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded"><i class="bi bi-people fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $students->num_rows; ?></h4><small class="text-muted">Total Students</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box-modern border-start border-success border-5">
                <div class="p-2 bg-success bg-opacity-10 text-success rounded"><i class="bi bi-check-circle fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold" id="enrolledCount">0</h4><small class="text-muted">With Program</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box-modern border-start border-warning border-5">
                <div class="p-2 bg-warning bg-opacity-10 text-warning rounded"><i class="bi bi-exclamation-circle fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold" id="notEnrolledCount">0</h4><small class="text-muted">No Program</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box-modern border-start border-info border-5">
                <div class="p-2 bg-info bg-opacity-10 text-info rounded"><i class="bi bi-grid-3x3 fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold" id="sectionAssignedCount">0</h4><small class="text-muted">Assigned</small></div>
            </div>
        </div>
    </div>

    <div id="alertContainer"></div>

    <div class="row g-4">
        <!-- LEFT: Student Selector -->
        <div class="col-lg-4 animate__animated animate__fadeInLeft">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h6 class="fw-bold mb-0 text-maroon">1. Select Candidate</h6>
                </div>
                <div class="card-body p-3">
                    <input type="text" class="form-control form-control-sm mb-3 rounded-pill" id="searchStudent" placeholder="Search Student Identity...">
                    <div class="d-flex gap-2 mb-3">
                        <select class="form-select form-select-sm rounded-pill" id="filterEnrollment">
                            <option value="">All Status</option>
                            <option value="enrolled">With Program</option>
                            <option value="not_enrolled">No Program</option>
                        </select>
                        <select class="form-select form-select-sm rounded-pill" id="filterSection">
                            <option value="">Sections</option>
                            <option value="has_section">Assigned</option>
                            <option value="no_section">Unassigned</option>
                        </select>
                    </div>

                    <div class="student-list-box shadow-xs" id="studentsListScroll">
                        <?php 
                        $e_count = 0; $ne_count = 0; $sa_count = 0;
                        $students->data_seek(0);
                        while ($student = $students->fetch_assoc()): 
                            $has_p = !empty($student['current_program_code']);
                            $has_s = $student['section_count'] > 0;
                            if ($has_p) $e_count++; else $ne_count++;
                            if ($has_s) $sa_count++;
                        ?>
                        <div class="student-card <?php echo $has_p ? 'enrolled-program' : 'no-program'; ?>" 
                             data-student-id="<?php echo $student['id']; ?>"
                             data-student-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                             data-student-no="<?php echo htmlspecialchars($student['student_no']); ?>"
                             data-has-program="<?php echo $has_p ? '1' : '0'; ?>"
                             data-has-section="<?php echo $has_s ? '1' : '0'; ?>"
                             data-program-type="<?php echo $student['program_type'] ?? ''; ?>"
                             data-course-id="<?php echo $student['course_id'] ?? ''; ?>">
                            <div class="fw-bold text-dark small mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                            <small class="text-muted d-block mb-2"><?php echo htmlspecialchars($student['student_no']); ?></small>
                            <div class="d-flex gap-1 flex-wrap">
                                <?php if ($has_p): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:0.6rem;"><?php echo htmlspecialchars($student['current_program_code']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark" style="font-size:0.6rem;">NO PROGRAM</span>
                                <?php endif; ?>
                                <?php if ($has_s): ?>
                                    <span class="badge bg-primary rounded-pill" style="font-size:0.6rem;"><?php echo $student['section_count']; ?> SECTIONS</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Program Selection -->
        <div class="col-lg-8 animate__animated animate__fadeInRight">
            <div id="enrollmentPanel" style="display: none;">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white p-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-blue">2. Assign Academic Path</h6>
                        <span class="badge bg-light text-maroon border" id="selectedStudentHeader"></span>
                    </div>
                    <div class="card-body p-4">
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-pills nav-pills-custom mb-4" id="pTypeTabs">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#collegePrograms">COLLEGE PROGRAMS</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#shsPrograms">SHS STRANDS</button></li>
                        </ul>

                        <div class="tab-content">
                            <!-- College -->
                            <div class="tab-pane fade show active" id="collegePrograms">
                                <div class="row g-3">
                                    <?php $programs->data_seek(0); while ($p = $programs->fetch_assoc()): ?>
                                    <div class="col-md-6">
                                        <div class="program-card-modern" data-program-id="<?php echo $p['id']; ?>" data-program-type="college">
                                            <div class="program-head"><h6 class="mb-0 small fw-bold"><?php echo htmlspecialchars($p['program_code']); ?></h6></div>
                                            <div class="p-3">
                                                <small class="text-muted d-block mb-3"><?php echo htmlspecialchars($p['program_name']); ?></small>
                                                <div class="year-levels-container">
                                                    <?php if (isset($program_year_levels[$p['id']])): foreach ($program_year_levels[$p['id']] as $yl): ?>
                                                        <button type="button" class="btn year-level-pill" data-year-level-id="<?php echo $yl['id']; ?>" data-year-level="<?php echo $yl['year_level']; ?>"><?php echo $yl['year_name']; ?></button>
                                                    <?php endforeach; endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                            <!-- SHS -->
                            <div class="tab-pane fade" id="shsPrograms">
                                <div class="row g-3">
                                    <?php $strands->data_seek(0); while ($s = $strands->fetch_assoc()): ?>
                                    <div class="col-md-6">
                                        <div class="program-card-modern" data-program-id="<?php echo $s['id']; ?>" data-program-type="shs">
                                            <div class="program-head shs"><h6 class="mb-0 small fw-bold"><?php echo htmlspecialchars($s['strand_code']); ?></h6></div>
                                            <div class="p-3">
                                                <small class="text-muted d-block mb-3"><?php echo htmlspecialchars($s['strand_name']); ?></small>
                                                <div class="year-levels-container">
                                                    <?php if (isset($strand_grade_levels[$s['id']])): foreach ($strand_grade_levels[$s['id']] as $gl): ?>
                                                        <button type="button" class="btn year-level-pill" data-year-level-id="<?php echo $gl['id']; ?>" data-year-level="<?php echo $gl['grade_level']; ?>"><?php echo $gl['grade_name']; ?></button>
                                                    <?php endforeach; endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action Bar -->
                        <div class="mt-5 text-center" id="enrollActionContainer" style="display: none;">
                            <div class="alert bg-light border p-3 rounded-4 mb-4">
                                <div class="small fw-bold text-muted text-uppercase mb-1">PROPOSED REGISTRATION</div>
                                <span class="h5 fw-bold text-blue" id="selectedProgramText"></span> <i class="bi bi-chevron-right mx-2 text-muted"></i> <span class="h5 fw-bold text-maroon" id="selectedYearText"></span>
                            </div>
                            <button class="btn btn-lg btn-maroon-save shadow-lg px-5 py-3" onclick="enrollStudent()">
                                <i class="bi bi-check-circle-fill me-2"></i> Confirm Enrollment
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Current Info Card -->
                <div class="card border-0 shadow-sm rounded-4 mt-4" id="currentEnrollmentCard" style="display: none;">
                    <div class="card-body p-4" id="currentEnrollmentInfo"></div>
                </div>
            </div>

            <!-- Placeholder -->
            <div id="noStudentSelected" class="text-center py-5">
                <div class="card border-0 shadow-sm rounded-4 p-5">
                    <i class="bi bi-person-plus display-1 text-muted opacity-25"></i>
                    <h5 class="mt-4 text-muted">Select a student to begin</h5>
                    <p class="small text-muted">Once a program is assigned, you can proceed to sectioning.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Modal Restored (Backend logic unchanged) -->
<div class="modal fade" id="bulkEnrollModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background: linear-gradient(135deg, #28a745, #20c997); border:none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-people-fill me-2"></i>Bulk Program Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="mb-3"><label class="form-label small fw-bold">Level Type</label><select class="form-select" id="bulkProgramType" onchange="loadBulkPrograms()"><option value="">Choose...</option><option value="college">College</option><option value="shs">SHS</option></select></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Program/Strand</label><select class="form-select" id="bulkProgram" onchange="loadBulkYearLevels()"><option value="">Select Program...</option></select></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Year Level</label><select class="form-select" id="bulkYearLevel"><option value="">Select Level...</option></select></div>
                    </div>
                    <div class="col-md-7 border-start ps-md-4">
                        <div class="d-flex justify-content-between align-items-center mb-3"><label class="form-label fw-bold small text-muted">CANDIDATES</label><div><button class="btn btn-xs btn-link text-primary p-0 me-2 small" onclick="selectAllBulkStudents()">Select All</button><button class="btn btn-xs btn-link text-muted p-0 small" onclick="clearBulkStudents()">Clear</button></div></div>
                        <div id="bulkStudentsList" style="max-height: 300px; overflow-y: auto; background:white; border-radius:10px; border:1px solid #eee; padding:15px;">
                            <?php $students->data_seek(0); while ($st = $students->fetch_assoc()): if (empty($st['current_program_code'])): ?>
                                <div class="form-check mb-2"><input type="checkbox" class="form-check-input bulk-student-cb" value="<?php echo $st['id']; ?>" id="b_<?php echo $st['id']; ?>"><label class="form-check-label small" for="b_<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['first_name'].' '.$st['last_name']); ?> (<?php echo $st['student_no']; ?>)</label></div>
                            <?php endif; endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4"><button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-success px-4 fw-bold shadow-sm" onclick="processBulkEnroll()">Complete Bulk Registration</button></div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
const programsData = <?php $programs->data_seek(0); $p_arr = []; while ($p = $programs->fetch_assoc()) { $p_arr[] = $p; } echo json_encode($p_arr); ?>;
const strandsData = <?php $strands->data_seek(0); $s_arr = []; while ($s = $strands->fetch_assoc()) { $s_arr[] = $s; } echo json_encode($s_arr); ?>;
const programYearLevels = <?php echo json_encode($program_year_levels); ?>;
const strandGradeLevels = <?php echo json_encode($strand_grade_levels); ?>;

document.getElementById('enrolledCount').textContent = <?php echo $e_count; ?>;
document.getElementById('notEnrolledCount').textContent = <?php echo $ne_count; ?>;
document.getElementById('sectionAssignedCount').textContent = <?php echo $sa_count; ?>;

let selectedStudentId = null, selectedProgramId = null, selectedProgramType = null, selectedYearLevelId = null;

/** 1. SEARCH & FILTER */
function filterStudents() {
    const s = document.getElementById('searchStudent').value.toLowerCase(), eF = document.getElementById('filterEnrollment').value, sF = document.getElementById('filterSection').value;
    document.querySelectorAll('.student-card').forEach(c => {
        const n = c.dataset.studentName.toLowerCase(), no = c.dataset.studentNo.toLowerCase(), hP = c.dataset.hasProgram === '1', hS = c.dataset.hasSection === '1';
        let show = n.includes(s) || no.includes(s);
        if (eF === 'enrolled' && !hP) show = false; if (eF === 'not_enrolled' && hP) show = false;
        if (sF === 'has_section' && !hS) show = false; if (sF === 'no_section' && hS) show = false;
        c.style.display = show ? 'block' : 'none';
    });
}
['searchStudent', 'filterEnrollment', 'filterSection'].forEach(id => document.getElementById(id).addEventListener('input', filterStudents));

/** 2. STUDENT SELECTION */
document.querySelectorAll('.student-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.student-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        selectedStudentId = this.dataset.studentId;
        document.getElementById('selectedStudentHeader').innerHTML = `<i class="bi bi-person-fill"></i> ${this.dataset.studentName} (${this.dataset.studentNo})`;
        document.getElementById('enrollmentPanel').style.display = 'block';
        document.getElementById('noStudentSelected').style.display = 'none';
        resetProgramSelection();
        showCurrentEnrollment(this);
    });
});

/** 3. ENROLLMENT LOGIC */
function resetProgramSelection() {
    selectedProgramId = null; selectedProgramType = null; selectedYearLevelId = null;
    document.querySelectorAll('.program-card-modern').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.year-level-pill').forEach(b => b.classList.remove('active'));
    document.getElementById('enrollActionContainer').style.display = 'none';
}

document.querySelectorAll('.year-level-pill').forEach(pill => {
    pill.addEventListener('click', function(e) {
        e.stopPropagation();
        const card = this.closest('.program-card-modern');
        document.querySelectorAll('.program-card-modern').forEach(c => c.classList.remove('selected'));
        document.querySelectorAll('.year-level-pill').forEach(b => b.classList.remove('active'));
        card.classList.add('selected'); this.classList.add('active');
        selectedProgramId = card.dataset.programId; selectedProgramType = card.dataset.programType; selectedYearLevelId = this.dataset.yearLevelId;
        document.getElementById('selectedProgramText').textContent = card.querySelector('.program-head h6').textContent;
        document.getElementById('selectedYearText').textContent = this.textContent;
        document.getElementById('enrollActionContainer').style.display = 'block';
    });
});

function enrollStudent() {
    const fd = new FormData();
    fd.append('action', 'enroll_program'); fd.append('student_id', selectedStudentId); fd.append('program_type', selectedProgramType); fd.append('program_id', selectedProgramId); fd.append('year_level_id', selectedYearLevelId);
    fetch('process/program_enrollment_api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
        if (d.success) { showAlert('success', d.message); setTimeout(() => location.reload(), 1200); } else showAlert('danger', d.message);
    });
}

function showCurrentEnrollment(card) {
    const info = document.getElementById('currentEnrollmentInfo');
    if (card.dataset.hasProgram === '1') {
        info.innerHTML = `<div class="d-flex align-items-center justify-content-between"><div><span class="badge bg-success px-3">REGISTERED</span> <span class="badge bg-light text-dark border">TYPE: ${card.dataset.programType.toUpperCase()}</span></div><button class="btn btn-sm btn-outline-warning fw-bold rounded-pill" onclick="resetProgramSelection(); document.getElementById('currentEnrollmentCard').style.display='none';"><i class="bi bi-arrow-repeat me-1"></i>RE-ASSIGN</button></div>`;
        document.getElementById('currentEnrollmentCard').style.display = 'block';
    } else document.getElementById('currentEnrollmentCard').style.display = 'none';
}

/** 4. BULK ENROLL LOGIC */
function loadBulkPrograms() {
    const t = document.getElementById('bulkProgramType').value, s = document.getElementById('bulkProgram');
    s.innerHTML = '<option value="">Select Program...</option>';
    (t === 'college' ? programsData : strandsData).forEach(p => { s.innerHTML += `<option value="${p.id}">${p.program_code} - ${p.program_name || p.strand_name}</option>`; });
}
function loadBulkYearLevels() {
    const t = document.getElementById('bulkProgramType').value, pId = document.getElementById('bulkProgram').value, s = document.getElementById('bulkYearLevel');
    s.innerHTML = '<option value="">Select Level...</option>';
    const levels = t === 'college' ? (programYearLevels[pId] || []) : (strandGradeLevels[pId] || []);
    levels.forEach(l => { s.innerHTML += `<option value="${l.id}">${l.year_name || l.grade_name}</option>`; });
}
function selectAllBulkStudents() { document.querySelectorAll('.bulk-student-cb').forEach(cb => cb.checked = true); }
function clearBulkStudents() { document.querySelectorAll('.bulk-student-cb').forEach(cb => cb.checked = false); }
function processBulkEnroll() {
    const ids = Array.from(document.querySelectorAll('.bulk-student-cb:checked')).map(cb => cb.value);
    if (!ids.length) return alert('Select students');
    const fd = new FormData();
    fd.append('action', 'bulk_enroll_program'); fd.append('program_type', document.getElementById('bulkProgramType').value); fd.append('program_id', document.getElementById('bulkProgram').value); fd.append('year_level_id', document.getElementById('bulkYearLevel').value); fd.append('student_ids', JSON.stringify(ids));
    fetch('process/program_enrollment_api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message); });
}

function showAlert(type, message) {
    document.getElementById('alertContainer').innerHTML = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><strong>${type === 'success' ? 'Success!' : 'System Alert'}</strong> ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>