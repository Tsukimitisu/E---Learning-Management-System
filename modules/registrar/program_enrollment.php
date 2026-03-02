<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Program Enrollment";
$registrar_id = $_SESSION['user_id'];

// Compatibility guard for environments where migrations are not yet applied.
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular' AFTER course_id");
$conn->query("ALTER TABLE students MODIFY COLUMN student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS previous_school VARCHAR(255) DEFAULT NULL AFTER student_type");


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
        CASE
            WHEN COALESCE(st.student_type, 'regular') = 'regular' THEN 'regular'
            WHEN st.student_type = 'transferee' THEN 'transferee'
            ELSE 'irregular'
        END as student_type,
        st.previous_school,
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

<link rel="stylesheet" href="css/program_enrollment.css">

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-mortarboard-fill me-2 text-maroon"></i>
                Program Enrollment
            </h4>
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
                             data-course-id="<?php echo $student['course_id'] ?? ''; ?>"
                             data-student-type="<?php echo htmlspecialchars($student['student_type'] ?? 'regular'); ?>"
                             data-previous-school="<?php echo htmlspecialchars($student['previous_school'] ?? ''); ?>">
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
                        <span class="badge bg-dark text-maroon border" id="selectedStudentHeader"></span>
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
                                <span class="h5 fw-bold text-blue" id="selectedProgramText"></span> <i class="bi bi-chevron-right mx-2 text-muted"></i> <span class="h5 fw-bold text-maroon" id="selectedYearText"></span> <i class="bi bi-chevron-right mx-2 text-muted"></i> <span class="h5 fw-bold text-success" id="selectedSemesterText">1st Semester</span>
                            </div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-3 text-start">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Enrollment Type</label>
                                    <select class="form-select" id="enrollmentTypeSelect" onchange="onEnrollmentTypeChanged()">
                                        <option value="regular">Regular</option>
                                        <option value="irregular">Irregular</option>
                                        <option value="transferee">Transferee</option>
                                    </select>
                                </div>
                                <div class="col-md-3 text-start">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Semester</label>
                                    <select class="form-select" id="enrollmentSemesterSelect" onchange="onSemesterChanged()">
                                        <option value="1st">1st Semester</option>
                                        <option value="2nd">2nd Semester</option>
                                        <option value="summer">Summer</option>
                                    </select>
                                </div>
                                <div class="col-md-3 text-start" id="voucherStatusWrap" style="display:none;">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Voucher Status</label>
                                    <select class="form-select" id="voucherStatusSelect">
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="not_applicable">N/A</option>
                                    </select>
                                </div>
                                <div class="col-md-6 text-start" id="previousSchoolWrap" style="display:none;">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Previous School</label>
                                    <input type="text" class="form-control" id="previousSchoolInput" placeholder="Enter previous school name">
                                </div>
                            </div>
                            <button class="btn btn-lg btn-maroon-save shadow-lg px-5 py-3" onclick="enrollStudent()">
                                <i class="bi bi-check-circle-fill me-2"></i> Confirm Enrollment
                            </button>
                            <button class="btn btn-lg btn-outline-warning shadow-lg px-5 py-3 ms-2" id="irregularEnrollBtn" style="display:none;" onclick="openIrregularEnrollModal()">
                                <i class="bi bi-list-check me-2"></i> Save Non-Regular Enrollment
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
                        <div class="mb-3"><label class="form-label small fw-bold">Semester</label><select class="form-select" id="bulkSemester"><option value="1st">1st Semester</option><option value="2nd">2nd Semester</option><option value="summer">Summer</option></select></div>
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

<!-- Non-Regular Subject Checklist Modal -->
<div class="modal fade" id="irregularEnrollModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background: linear-gradient(135deg, #6f42c1, #17a2b8); border:none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-list-check me-2"></i>Subject Validation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="alert alert-info small mb-3">
                    Mark credited subjects and enter the grade for each credited subject. Only unchecked subjects will be enrolled in this school.
                </div>
                <div id="irregularSubjectsContainer" style="max-height: 420px; overflow-y: auto;">
                    <div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading subjects...</div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning px-4 fw-bold shadow-sm" onclick="submitIrregularEnrollment()">Save Enrollment</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Advance Year Level Modal (with mandatory downpayment) -->
<div class="modal fade" id="advanceYearModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header p-4 text-white" style="background: linear-gradient(135deg, var(--blue), var(--maroon)); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-up-circle-fill me-2"></i>Advance Year Level</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="advanceYearBody">
                <div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span> Loading...</div>
            </div>
            <div class="modal-footer border-0 p-4 bg-light">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" id="confirmAdvanceBtn" onclick="confirmAdvanceYear()" disabled>
                    <i class="bi bi-arrow-up-circle me-1"></i> Advance & Pay
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Enrollment Fee Assessment Result Modal -->
<div class="modal fade" id="feeResultModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header p-4 text-white" style="background: linear-gradient(135deg, #28a745, #20c997); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-check-circle-fill me-2"></i>Enrollment Successful</h5>
            </div>
            <div class="modal-body p-4" id="feeResultBody">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer border-0 p-4 bg-light d-flex justify-content-between">
                <a href="students.php" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-people me-1"></i> View Students</a>
                <div class="d-flex gap-2">
                    <a href="payment_history.php" class="btn btn-outline-primary rounded-pill px-3" id="feeResultPaymentLink"><i class="bi bi-credit-card me-1"></i> Payment History</a>
                    <button type="button" class="btn btn-success rounded-pill px-4 fw-bold" id="feeResultDoneBtn" onclick="closeFeeModalAndReload()"><i class="bi bi-check-lg me-1"></i> Done</button>
                </div>
            </div>
        </div>
    </div>
</div>

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
let irregularSubjects = [];

function normalizeStudentType(type) {
    const value = String(type || '').toLowerCase();
    return ['regular', 'irregular', 'transferee'].includes(value) ? value : 'regular';
}

function isNonRegularType(type) {
    return normalizeStudentType(type) !== 'regular';
}

function getSelectedEnrollmentType() {
    const select = document.getElementById('enrollmentTypeSelect');
    return normalizeStudentType(select ? select.value : 'regular');
}

function normalizeSemester(value) {
    const sem = String(value || '').toLowerCase();
    if (sem === '2nd' || sem === 'summer') {
        return sem;
    }
    return '1st';
}

function getSelectedSemester() {
    const select = document.getElementById('enrollmentSemesterSelect');
    return normalizeSemester(select ? select.value : '1st');
}

function semesterLabel(value) {
    const sem = normalizeSemester(value);
    if (sem === '2nd') return '2nd Semester';
    if (sem === 'summer') return 'Summer';
    return '1st Semester';
}

function onSemesterChanged() {
    const semesterText = document.getElementById('selectedSemesterText');
    if (semesterText) {
        semesterText.textContent = semesterLabel(getSelectedSemester());
    }
}

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
        const normalizedType = normalizeStudentType(this.dataset.studentType || 'regular');
        const enrollmentTypeSelect = document.getElementById('enrollmentTypeSelect');
        enrollmentTypeSelect.value = normalizedType;
        enrollmentTypeSelect.removeAttribute('disabled');
        document.getElementById('previousSchoolInput').value = this.dataset.previousSchool || '';
        onEnrollmentTypeChanged();
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
    onSemesterChanged();
    irregularSubjects = [];
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
        onSemesterChanged();
        document.getElementById('enrollActionContainer').style.display = 'block';
        // Show voucher status for SHS
        document.getElementById('voucherStatusWrap').style.display = selectedProgramType === 'shs' ? 'block' : 'none';
        onEnrollmentTypeChanged();
    });
});

function enrollStudent() {
    if (!selectedStudentId || !selectedProgramType || !selectedProgramId || !selectedYearLevelId) {
        showAlert('warning', 'Please select student, program, and year level first.');
        return;
    }

    const studentType = getSelectedEnrollmentType();
    if (isNonRegularType(studentType)) {
        openIrregularEnrollModal();
        return;
    }

    const confirmBtn = document.querySelector('[onclick="enrollStudent()"]');
    if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...'; }

    const fd = new FormData();
    fd.append('action', 'enroll_program'); fd.append('student_id', selectedStudentId); fd.append('program_type', selectedProgramType); fd.append('program_id', selectedProgramId); fd.append('year_level_id', selectedYearLevelId);
    fd.append('semester', getSelectedSemester());
    fd.append('student_type', studentType);
    fd.append('previous_school', document.getElementById('previousSchoolInput').value || '');
    fd.append('voucher_status', document.getElementById('voucherStatusSelect').value || 'not_applicable');
    fd.append('completed_subject_ids', JSON.stringify([]));
    fetch('process/program_enrollment_api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
        if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Confirm Enrollment'; }
        if (d.success) {
            showFeeResultModal(d);
        } else {
            showAlert('danger', d.message);
        }
    }).catch(() => {
        if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Confirm Enrollment'; }
        showAlert('danger', 'Network error. Please try again.');
    });
}

function onEnrollmentTypeChanged() {
    const type = getSelectedEnrollmentType();
    const nonRegularMode = isNonRegularType(type);
    document.getElementById('previousSchoolWrap').style.display = nonRegularMode ? 'block' : 'none';
    const nonRegularBtn = document.getElementById('irregularEnrollBtn');
    if (nonRegularBtn) {
        nonRegularBtn.style.display = nonRegularMode ? 'inline-block' : 'none';
    }
}

function openIrregularEnrollModal() {
    if (!selectedStudentId || !selectedProgramType || !selectedProgramId || !selectedYearLevelId) {
        showAlert('warning', 'Please select student, program, and year level first.');
        return;
    }
    const modal = new bootstrap.Modal(document.getElementById('irregularEnrollModal'));
    modal.show();
    loadIrregularSubjects();
}

function loadIrregularSubjects() {
    const container = document.getElementById('irregularSubjectsContainer');
    container.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading subjects...</div>';

    const params = new URLSearchParams({
        action: 'get_subjects_for_enrollment',
        student_id: selectedStudentId,
        program_type: selectedProgramType,
        program_id: selectedProgramId,
        year_level_id: selectedYearLevelId,
        semester: getSelectedSemester(),
        all_semesters: '1'
    });

    fetch(`process/program_enrollment_api.php?${params.toString()}`)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(text => {
            try { return JSON.parse(text); } catch(e) { console.error('Invalid JSON from enrollment API:', text.substring(0, 500)); throw new Error('Invalid response from server'); }
        })
        .then(data => {
            if (!data.success) {
                container.innerHTML = `<div class="alert alert-danger small">${data.message || 'Failed to load subjects.'}</div>`;
                return;
            }
            irregularSubjects = data.subjects || [];
            if (!irregularSubjects.length) {
                container.innerHTML = '<div class="alert alert-warning small mb-0">No curriculum subjects found for this level.</div>';
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Subject</th><th class="text-center">Semester</th><th class="text-center">Credited</th><th style="min-width:140px;">Grade</th><th style="min-width:220px;">Previous Subject Name (Optional)</th></tr></thead><tbody>';
            irregularSubjects.forEach(s => {
                const checked = s.already_completed ? 'checked' : '';
                const statusTag = s.already_enrolled ? '<span class="badge bg-success ms-2">already enrolled</span>' : '';
                const grade = escapeHtml(s.previous_grade || '');
                const previousSubjectName = escapeHtml(s.previous_subject_name || '');
                html += `<tr>
                    <td>
                        <div class="fw-bold">${escapeHtml(s.subject_code || '')}</div>
                        <small class="text-muted">${escapeHtml(s.subject_title || '')}</small>${statusTag}
                    </td>
                    <td class="text-center"><span class="badge bg-light text-dark border">${Number(s.semester) === 2 ? '2nd' : (Number(s.semester) === 3 ? 'Summer' : '1st')}</span></td>
                    <td class="text-center">
                        <input class="form-check-input irregular-completed-subject" type="checkbox" value="${s.id}" ${checked}>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm irregular-grade-input" data-subject-id="${s.id}" value="${grade}" ${checked ? '' : 'disabled'} placeholder="e.g. 89 or 2.0">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm irregular-previous-subject-input" data-subject-id="${s.id}" value="${previousSubjectName}" ${checked ? '' : 'disabled'} placeholder="Previous school subject title">
                    </td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;

            container.querySelectorAll('.irregular-completed-subject').forEach(cb => {
                cb.addEventListener('change', function() {
                    const sid = this.value;
                    const gradeInput = container.querySelector(`.irregular-grade-input[data-subject-id="${sid}"]`);
                    const previousSubjectInput = container.querySelector(`.irregular-previous-subject-input[data-subject-id="${sid}"]`);
                    if (gradeInput) gradeInput.disabled = !this.checked;
                    if (previousSubjectInput) previousSubjectInput.disabled = !this.checked;
                });
            });
        })
        .catch(err => {
            console.error('loadIrregularSubjects error:', err);
            container.innerHTML = '<div class="alert alert-danger small">Failed to load subjects. Check browser console for details.</div>';
        });
}

function submitIrregularEnrollment() {
    const studentType = getSelectedEnrollmentType();
    if (!isNonRegularType(studentType)) {
        showAlert('warning', 'Please select Irregular or Transferee enrollment type.');
        return;
    }

    const previousSchool = (document.getElementById('previousSchoolInput').value || '').trim();
    if (!previousSchool) {
        showAlert('warning', 'Previous school is required for non-regular enrollment.');
        return;
    }

    const completedIds = Array.from(document.querySelectorAll('.irregular-completed-subject:checked')).map(el => parseInt(el.value, 10)).filter(Boolean);
    const completedDetails = {};
    const missingGrades = [];
    completedIds.forEach((sid) => {
        const gradeInput = document.querySelector(`.irregular-grade-input[data-subject-id="${sid}"]`);
        const previousSubjectInput = document.querySelector(`.irregular-previous-subject-input[data-subject-id="${sid}"]`);
        const grade = (gradeInput?.value || '').trim();
        const previousSubjectName = (previousSubjectInput?.value || '').trim();

        if (!grade) {
            missingGrades.push(sid);
            return;
        }

        completedDetails[sid] = {
            grade,
            previous_subject_name: previousSubjectName
        };
    });

    if (missingGrades.length > 0) {
        showAlert('warning', 'Please enter a grade for every credited subject.');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'enroll_program_irregular');
    fd.append('student_id', selectedStudentId);
    fd.append('program_type', selectedProgramType);
    fd.append('program_id', selectedProgramId);
    fd.append('year_level_id', selectedYearLevelId);
    fd.append('semester', getSelectedSemester());
    fd.append('student_type', studentType);
    fd.append('previous_school', previousSchool);
    fd.append('completed_subject_ids', JSON.stringify(completedIds));
    fd.append('completed_subject_details', JSON.stringify(completedDetails));

    fetch('process/program_enrollment_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('irregularEnrollModal'));
                if (modal) modal.hide();
                showFeeResultModal(d);
            } else {
                showAlert('danger', d.message || 'Failed to save non-regular enrollment.');
            }
        });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function showCurrentEnrollment(card) {
    const info = document.getElementById('currentEnrollmentInfo');
    const cardEl = document.getElementById('currentEnrollmentCard');
    
    if (card.dataset.hasProgram === '1') {
        // Show loading state immediately
        info.innerHTML = `<div class="text-center py-2"><span class="spinner-border spinner-border-sm me-2"></span> Loading enrollment details...</div>`;
        cardEl.style.display = 'block';
        
        // Fetch detailed enrollment info and balance from API
        fetch(`process/program_enrollment_api.php?action=get_student_balance&student_id=${selectedStudentId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.latest_enrollment) {
                    const enrollment = data.latest_enrollment;
                    const balance = data.balance;
                    const typeBadge = enrollment.student_type === 'transferee' 
                        ? 'bg-warning text-dark' 
                        : (enrollment.student_type === 'irregular' ? 'bg-info' : 'bg-secondary');
                    info.innerHTML = `
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <span class="badge bg-success px-3">ENROLLED</span>
                                <span class="fw-bold ms-2">${enrollment.program_code || card.dataset.programType.toUpperCase()} &mdash; ${enrollment.year_level_name || ''}</span>
                                <span class="badge bg-primary bg-opacity-10 text-primary ms-2">${enrollment.semester} Sem</span>
                                <span class="badge ${typeBadge} ms-2">${(enrollment.student_type || 'regular').toUpperCase()}</span>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="badge ${balance > 0 ? 'bg-danger' : 'bg-success'} px-3 py-2">
                                    Balance: \u20b1${Number(balance).toLocaleString('en-PH', {minimumFractionDigits: 2})}
                                </span>
                                ${balance > 0 
                                    ? `<button class="btn btn-sm btn-secondary fw-bold rounded-pill" disabled title="Student must be fully paid before advancing">
                                        <i class="bi bi-lock me-1"></i>Advance Year
                                       </button>
                                       <span class="text-danger small fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Fully paid required</span>`
                                    : `<button class="btn btn-sm btn-primary fw-bold rounded-pill" onclick="advanceToNextYear()">
                                        <i class="bi bi-arrow-up-circle me-1"></i>Advance Year
                                       </button>`
                                }
                                <button class="btn btn-sm btn-outline-warning fw-bold rounded-pill" onclick="resetProgramSelection(); document.getElementById('currentEnrollmentCard').style.display='none';">
                                    <i class="bi bi-arrow-repeat me-1"></i>RE-ASSIGN
                                </button>
                            </div>
                        </div>`;
                } else {
                    info.innerHTML = `<div class="d-flex align-items-center justify-content-between"><div><span class="badge bg-success px-3">REGISTERED</span> <span class="badge bg-light text-dark border">TYPE: ${card.dataset.programType.toUpperCase()}</span></div><button class="btn btn-sm btn-outline-warning fw-bold rounded-pill" onclick="resetProgramSelection(); document.getElementById('currentEnrollmentCard').style.display='none';"><i class="bi bi-arrow-repeat me-1"></i>RE-ASSIGN</button></div>`;
                }
            })
            .catch(() => {
                info.innerHTML = `<div class="d-flex align-items-center justify-content-between"><div><span class="badge bg-success px-3">REGISTERED</span></div><button class="btn btn-sm btn-outline-warning fw-bold rounded-pill" onclick="resetProgramSelection(); document.getElementById('currentEnrollmentCard').style.display='none';"><i class="bi bi-arrow-repeat me-1"></i>RE-ASSIGN</button></div>`;
            });
    } else {
        cardEl.style.display = 'none';
    }
}

function advanceToNextYear() {
    if (!selectedStudentId) {
        showAlert('warning', 'Please select a student first.');
        return;
    }
    
    const semester = getSelectedSemester();
    const body = document.getElementById('advanceYearBody');
    const confirmBtn = document.getElementById('confirmAdvanceBtn');
    confirmBtn.disabled = true;
    
    // Show modal with loading state
    body.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary me-2"></span> Fetching advancement details...</div>';
    new bootstrap.Modal(document.getElementById('advanceYearModal')).show();
    
    // Fetch preview data
    fetch(`process/program_enrollment_api.php?action=preview_advance&student_id=${selectedStudentId}&semester=${semester}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = `<div class="alert alert-danger border-0"><i class="bi bi-x-circle me-1"></i> ${data.message}</div>`;
                return;
            }
            
            const baseFee = parseFloat(data.tuition_fee || 0);
            const adjustedFee = parseFloat(data.adjusted_fee || baseFee);
            const fee = adjustedFee > 0 ? adjustedFee : baseFee;
            const minDp = parseFloat(data.min_downpayment || 0);
            const semLabel = semester === '2nd' ? '2nd Semester' : (semester === 'summer' ? 'Summer' : '1st Semester');
            const discounts = data.discounts || [];
            const penalties = data.penalties || [];
            
            let html = `
                <div class="text-center mb-4">
                    <div class="bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:56px;height:56px;">
                        <i class="bi bi-arrow-up-circle text-primary fs-3"></i>
                    </div>
                    <h6 class="fw-bold mb-1">${escapeHtml(data.program_code)}</h6>
                    <div class="d-flex justify-content-center align-items-center gap-2">
                        <span class="badge bg-secondary">${escapeHtml(data.current_year)}</span>
                        <i class="bi bi-arrow-right text-muted"></i>
                        <span class="badge bg-primary px-3">${escapeHtml(data.next_year)}</span>
                    </div>
                    <span class="badge bg-info bg-opacity-10 text-info mt-2">${semLabel}</span>
                </div>
                
                <div class="border rounded-4 p-3 mb-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted fw-bold text-uppercase">Base Tuition Fee</span>
                        <span class="fw-bold fs-5" style="color: var(--maroon);">${baseFee > 0 ? '₱' + baseFee.toLocaleString('en-PH', {minimumFractionDigits: 2}) : 'Not configured'}</span>
                    </div>`;

            // Show discount line items
            if (discounts.length > 0) {
                discounts.forEach(d => {
                    html += `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-success"><i class="bi bi-tag-fill me-1"></i>${escapeHtml(d.description)}</span>
                        <span class="fw-bold text-success">-₱${parseFloat(d.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                    </div>`;
                });
            }
            // Show penalty line items
            if (penalties.length > 0) {
                penalties.forEach(p => {
                    html += `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>${escapeHtml(p.description)}</span>
                        <span class="fw-bold text-danger">+₱${parseFloat(p.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                    </div>`;
                });
            }
            // Show adjusted total if adjustments present
            if (discounts.length > 0 || penalties.length > 0) {
                html += `
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-uppercase" style="color: var(--blue);">Adjusted Tuition Fee</span>
                        <span class="fw-bold fs-5" style="color: var(--blue);">₱${fee.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                    </div>`;
            }

            html += `
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Min. Down Payment (25%)</span>
                        <span class="fw-bold text-primary">₱${minDp.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                    </div>
                </div>

                <div class="border border-primary rounded-4 p-3 bg-primary bg-opacity-5">
                    <p class="small fw-bold text-primary text-uppercase mb-3"><i class="bi bi-cash-stack me-1"></i> Mandatory Down Payment</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold mb-1">Amount (₱) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="advanceDownpaymentAmount" 
                                   min="${minDp}" max="${fee > 0 ? fee : 999999}" step="0.01" value="${minDp > 0 ? minDp : ''}" 
                                   placeholder="Min: ₱${minDp.toLocaleString('en-PH', {minimumFractionDigits: 2})}"
                                   oninput="validateAdvanceDownpayment(${minDp}, ${fee})">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold mb-1">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" id="advancePaymentMethod">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="online">Online Payment</option>
                                <option value="check">Check</option>
                            </select>
                        </div>
                    </div>
                    <div id="advancePaymentFeedback" class="mt-2"></div>`;
                    
            if (fee > 0 && minDp > 0) {
                const remaining = fee - minDp;
                const perTerm = Math.ceil(remaining / 4);
                html += `
                    <div class="mt-3 small text-muted text-center border-top pt-2">
                        <i class="bi bi-info-circle me-1"></i> Remaining ₱${remaining.toLocaleString('en-PH', {minimumFractionDigits: 2})} payable in 4 terms × ₱${perTerm.toLocaleString('en-PH', {minimumFractionDigits: 2})}
                    </div>`;
            }
            
            html += `</div>`;
            
            body.innerHTML = html;
            
            // Enable confirm button if min DP met
            validateAdvanceDownpayment(minDp, fee);
        })
        .catch(() => {
            body.innerHTML = '<div class="alert alert-danger border-0"><i class="bi bi-x-circle me-1"></i> Failed to load advancement preview.</div>';
        });
}

function validateAdvanceDownpayment(minDp, maxFee) {
    const input = document.getElementById('advanceDownpaymentAmount');
    const btn = document.getElementById('confirmAdvanceBtn');
    const feedback = document.getElementById('advancePaymentFeedback');
    if (!input || !btn) return;
    
    const amount = parseFloat(input.value) || 0;
    
    if (amount >= minDp && amount > 0) {
        btn.disabled = false;
        feedback.innerHTML = amount >= maxFee && maxFee > 0
            ? '<div class="small text-success"><i class="bi bi-check-circle me-1"></i>Full payment — no remaining balance.</div>'
            : '';
    } else {
        btn.disabled = true;
        if (input.value !== '' && amount < minDp) {
            feedback.innerHTML = `<div class="small text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Minimum is ₱${minDp.toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>`;
        } else {
            feedback.innerHTML = '';
        }
    }
}

function confirmAdvanceYear() {
    const btn = document.getElementById('confirmAdvanceBtn');
    const amount = parseFloat(document.getElementById('advanceDownpaymentAmount').value) || 0;
    const method = document.getElementById('advancePaymentMethod').value;
    
    if (amount <= 0) {
        document.getElementById('advancePaymentFeedback').innerHTML = '<div class="small text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Down payment amount is required.</div>';
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
    
    const semester = getSelectedSemester();
    const studentType = getSelectedEnrollmentType();
    const previousSchool = document.getElementById('previousSchoolInput').value || '';
    
    const fd = new FormData();
    fd.append('action', 'enroll_next_year');
    fd.append('student_id', selectedStudentId);
    fd.append('semester', semester);
    fd.append('student_type', studentType);
    fd.append('previous_school', previousSchool);
    fd.append('completed_subject_ids', JSON.stringify([]));
    fd.append('downpayment_amount', amount);
    fd.append('payment_method', method);
    
    fetch('process/program_enrollment_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            // Close advance modal
            const advModal = bootstrap.Modal.getInstance(document.getElementById('advanceYearModal'));
            if (advModal) advModal.hide();
            
            if (d.success) {
                showFeeResultModal(d);
            } else {
                showAlert('danger', d.message);
            }
            
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-up-circle me-1"></i> Advance & Pay';
        })
        .catch(() => {
            showAlert('danger', 'Network error during advancement.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-up-circle me-1"></i> Advance & Pay';
        });
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
    fd.append('action', 'bulk_enroll_program'); fd.append('program_type', document.getElementById('bulkProgramType').value); fd.append('program_id', document.getElementById('bulkProgram').value); fd.append('year_level_id', document.getElementById('bulkYearLevel').value); fd.append('semester', normalizeSemester(document.getElementById('bulkSemester').value)); fd.append('student_ids', JSON.stringify(ids)); fd.append('student_type', 'regular');
    fetch('process/program_enrollment_api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message); });
}

function showFeeResultModal(response) {
    const meta = response.meta || {};
    const fee = parseFloat(meta.tuition_fee_assessed || 0);
    const semesterBalance = parseFloat(meta.semester_balance || 0);
    const totalBalance = parseFloat(meta.total_balance || 0);
    const semester = meta.semester || '1st';
    const studentType = meta.student_type || 'regular';
    const enrolledCount = meta.enrolled_count || 0;
    const completedCount = meta.completed_count || 0;
    const totalSubjects = meta.total_subjects || 0;
    const isFirstEnrollment = !meta.is_re_enrollment;

    // Discount / Penalty data
    const discountsApplied = meta.discounts_applied || [];
    const penaltiesApplied = meta.penalties_applied || [];
    const totalDiscount = parseFloat(meta.total_discount || 0);
    const totalPenalty = parseFloat(meta.total_penalty || 0);
    const adjustedFee = Math.max(0, fee - totalDiscount + totalPenalty);

    const semesterLabel = semester === '2nd' ? '2nd Semester' : (semester === 'summer' ? 'Summer' : '1st Semester');
    const programText = document.getElementById('selectedProgramText')?.textContent || '';
    const yearText = document.getElementById('selectedYearText')?.textContent || '';
    const studentName = document.getElementById('selectedStudentHeader')?.textContent || '';

    const typeBadge = studentType === 'transferee' 
        ? '<span class="badge bg-warning text-dark">TRANSFEREE</span>'
        : (studentType === 'irregular' ? '<span class="badge bg-info">IRREGULAR</span>' : '<span class="badge bg-secondary">REGULAR</span>');

    let html = `
        <div class="text-center mb-4">
            <div class="bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:60px;height:60px;">
                <i class="bi bi-check-lg text-success fs-3"></i>
            </div>
            <p class="text-muted small mb-1">${escapeHtml(studentName)}</p>
            <h5 class="fw-bold mb-1">${escapeHtml(programText)} &mdash; ${escapeHtml(yearText)}</h5>
            <span class="badge bg-primary bg-opacity-10 text-primary">${semesterLabel}</span> ${typeBadge}
        </div>

        <div class="bg-light rounded-4 p-3 mb-3">
            <div class="row g-2 text-center">
                <div class="col-4">
                    <div class="fw-bold fs-5 text-primary">${totalSubjects}</div>
                    <small class="text-muted">Total Subjects</small>
                </div>
                <div class="col-4">
                    <div class="fw-bold fs-5 text-success">${enrolledCount}</div>
                    <small class="text-muted">Enrolled</small>
                </div>
                <div class="col-4">
                    <div class="fw-bold fs-5 text-warning">${completedCount}</div>
                    <small class="text-muted">Credited</small>
                </div>
            </div>
        </div>

        <div class="border rounded-4 overflow-hidden mb-3">
            <div class="p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted fw-bold text-uppercase">Base Tuition Fee</span>
                    <span class="fw-bold fs-5" style="color: var(--maroon);">₱${fee.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                </div>`;

    // Show discount line items
    if (discountsApplied.length > 0) {
        discountsApplied.forEach(d => {
            html += `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small text-success"><i class="bi bi-tag-fill me-1"></i>${escapeHtml(d.description)}</span>
                    <span class="fw-bold text-success">-₱${parseFloat(d.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                </div>`;
        });
    }

    // Show penalty line items
    if (penaltiesApplied.length > 0) {
        penaltiesApplied.forEach(p => {
            html += `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>${escapeHtml(p.description)}</span>
                    <span class="fw-bold text-danger">+₱${parseFloat(p.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                </div>`;
        });
    }

    // Show adjusted total if there are discounts or penalties
    if (discountsApplied.length > 0 || penaltiesApplied.length > 0) {
        html += `
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-bold text-uppercase" style="color: var(--blue);">Adjusted Total Fee</span>
                    <span class="fw-bold fs-5" style="color: var(--blue);">₱${adjustedFee.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                </div>`;
    }
    
    if (fee > 0) {
        html += `
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted">${semesterLabel} Balance</span>
                    <span class="fw-bold ${semesterBalance > 0 ? 'text-danger' : 'text-success'}">₱${semesterBalance.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Total Outstanding Balance</span>
                    <span class="fw-bold ${totalBalance > 0 ? 'text-danger' : 'text-success'}">₱${totalBalance.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                </div>`;
    } else {
        html += `
                <div class="alert alert-warning small mb-0 mt-2 py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i> No tuition fee configured for this program/semester. Please configure it in <a href="tuition_fees.php" class="fw-bold">Tuition Fee Management</a>.
                </div>`;
    }

    html += `
            </div>
        </div>`;

    // Down payment section
    const doneBtn = document.getElementById('feeResultDoneBtn');
    const effectiveFee = (discountsApplied.length > 0 || penaltiesApplied.length > 0) ? adjustedFee : fee;
    const alreadyPaidDP = parseFloat(meta.downpayment_amount || 0);
    const dpReference = meta.downpayment_reference || '';

    if (fee > 0 && alreadyPaidDP > 0 && dpReference) {
        // Downpayment was already recorded during advancement — show summary instead of form
        html += `
        <div class="border border-success rounded-4 overflow-hidden mb-3" id="downpaymentSection">
            <div class="p-3 bg-success bg-opacity-10">
                <p class="small fw-bold text-success text-uppercase mb-2"><i class="bi bi-check-circle-fill me-1"></i> Down Payment Recorded</p>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="border rounded-3 p-2 bg-white text-center h-100">
                            <small class="text-muted d-block">Down Payment Paid</small>
                            <span class="fw-bold text-success">₱${alreadyPaidDP.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-2 bg-white text-center h-100">
                            <small class="text-muted d-block">Remaining Balance</small>
                            <span class="fw-bold text-danger">₱${semesterBalance.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                </div>
                <p class="small text-muted mb-0 text-center">Reference: <strong>${escapeHtml(dpReference)}</strong></p>
            </div>
        </div>`;
        if (doneBtn) {
            doneBtn.disabled = false;
            doneBtn.title = '';
            doneBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Done';
            doneBtn.classList.remove('btn-secondary');
            doneBtn.classList.add('btn-success');
        }
    } else if (fee > 0 && semesterBalance > 0) {
        // No downpayment yet — show mandatory downpayment form
        const downpayment = Math.ceil(effectiveFee * 0.25);
        const remaining = effectiveFee - downpayment;
        const perTerm = Math.ceil(remaining / 4);
        html += `
        <div class="border border-danger rounded-4 overflow-hidden mb-3" id="downpaymentSection">
            <div class="p-3 bg-danger bg-opacity-10">
                <p class="small fw-bold text-danger text-uppercase mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Mandatory Down Payment Required</p>
                <p class="small text-muted mb-3">A minimum down payment is required to complete the enrollment process.</p>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="border rounded-3 p-2 bg-white text-center h-100">
                            <small class="text-muted d-block">Full Payment</small>
                            <span class="fw-bold text-success">₱${effectiveFee.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-2 bg-white text-center h-100">
                            <small class="text-muted d-block">Min Down Payment (25%)</small>
                            <span class="fw-bold text-primary">₱${downpayment.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Amount (₱)</label>
                        <input type="number" class="form-control" id="downpaymentAmount" min="${downpayment}" max="${effectiveFee}" step="0.01" value="${downpayment}" placeholder="Min: ₱${downpayment}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Payment Method</label>
                        <select class="form-select" id="downpaymentMethod">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="online">Online Payment</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-danger w-100 fw-bold" id="processDownpaymentBtn" onclick="processDownPayment()">
                            <i class="bi bi-cash-stack me-1"></i> Record Payment
                        </button>
                    </div>
                </div>
                <div id="downpaymentFeedback" class="mt-2"></div>
                <div class="mt-2 small text-muted text-center">
                    Installment option: 4 terms × ₱${perTerm.toLocaleString('en-PH', {minimumFractionDigits: 2})} after down payment
                </div>
            </div>
        </div>`;
        // Disable Done button until down payment is made
        if (doneBtn) {
            doneBtn.disabled = true;
            doneBtn.title = 'Record down payment first';
            doneBtn.innerHTML = '<i class="bi bi-lock me-1"></i> Pay First';
        }
    } else {
        if (doneBtn) {
            doneBtn.disabled = false;
            doneBtn.title = '';
            doneBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Done';
        }
    }

    document.getElementById('feeResultBody').innerHTML = html;
    const feeModal = new bootstrap.Modal(document.getElementById('feeResultModal'));
    feeModal.show();
}

function processDownPayment() {
    const amountInput = document.getElementById('downpaymentAmount');
    const methodSelect = document.getElementById('downpaymentMethod');
    const feedback = document.getElementById('downpaymentFeedback');
    const btn = document.getElementById('processDownpaymentBtn');
    
    const amount = parseFloat(amountInput.value);
    const minAmount = parseFloat(amountInput.min);
    
    if (!amount || amount < minAmount) {
        feedback.innerHTML = `<div class="alert alert-warning small py-1 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Minimum down payment is ₱${minAmount.toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>`;
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
    feedback.innerHTML = '';
    
    const fd = new FormData();
    fd.append('action', 'record_downpayment');
    fd.append('student_id', selectedStudentId);
    fd.append('amount', amount);
    fd.append('payment_method', methodSelect.value);
    
    fetch('process/program_enrollment_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                feedback.innerHTML = `<div class="alert alert-success small py-1 mb-0"><i class="bi bi-check-circle me-1"></i> ${d.message}</div>`;
                // Disable payment form
                amountInput.disabled = true;
                methodSelect.disabled = true;
                btn.style.display = 'none';
                // Update the balance display
                const section = document.getElementById('downpaymentSection');
                if (section) section.classList.remove('border-danger');
                if (section) section.classList.add('border-success');

                // Update displayed balance numbers with new values
                if (typeof d.new_balance !== 'undefined') {
                    const newSemBal = parseFloat(d.new_balance);
                    const newTotBal = parseFloat(d.new_total_balance ?? d.new_balance);
                    const balanceRows = document.querySelectorAll('#feeResultBody .d-flex.justify-content-between');
                    balanceRows.forEach(row => {
                        const label = row.querySelector('.text-muted');
                        const valueEl = row.querySelector('.fw-bold:last-child');
                        if (label && valueEl) {
                            const labelText = label.textContent.trim();
                            if (labelText.includes('Semester') && labelText.includes('Balance')) {
                                valueEl.textContent = '₱' + newSemBal.toLocaleString('en-PH', {minimumFractionDigits: 2});
                                valueEl.className = 'fw-bold ' + (newSemBal > 0 ? 'text-danger' : 'text-success');
                            } else if (labelText.includes('Total') && labelText.includes('Balance')) {
                                valueEl.textContent = '₱' + newTotBal.toLocaleString('en-PH', {minimumFractionDigits: 2});
                                valueEl.className = 'fw-bold ' + (newTotBal > 0 ? 'text-danger' : 'text-success');
                            }
                        }
                    });
                }

                // Enable Done button
                const doneBtn = document.getElementById('feeResultDoneBtn');
                if (doneBtn) {
                    doneBtn.disabled = false;
                    doneBtn.title = '';
                    doneBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Done';
                    doneBtn.classList.remove('btn-secondary');
                    doneBtn.classList.add('btn-success');
                }
            } else {
                feedback.innerHTML = `<div class="alert alert-danger small py-1 mb-0"><i class="bi bi-x-circle me-1"></i> ${d.message}</div>`;
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-cash-stack me-1"></i> Record Payment';
            }
        })
        .catch(err => {
            console.error('Down payment error:', err);
            feedback.innerHTML = '<div class="alert alert-danger small py-1 mb-0"><i class="bi bi-x-circle me-1"></i> Network error. Please try again.</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cash-stack me-1"></i> Record Payment';
        });
}

function closeFeeModalAndReload() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('feeResultModal'));
    if (modal) modal.hide();
    location.reload();
}

function showAlert(type, message) {
    document.getElementById('alertContainer').innerHTML = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><strong>${type === 'success' ? 'Success!' : 'System Alert'}</strong> ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}

onSemesterChanged();
</script>
</body>
</html>
