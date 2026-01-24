<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$branch_id = get_user_branch_id();
if ($branch_id === null) {
    die("No branch assigned to this admin.");
}

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Fetch college programs with subject count
$programs = $conn->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM curriculum_subjects WHERE program_id = p.id AND is_active = 1) as subject_count
    FROM programs p 
    WHERE p.is_active = 1 
    ORDER BY p.program_code
");

// Fetch program year levels
$year_levels_query = $conn->query("
    SELECT pyl.*, p.id as program_id,
           (SELECT COUNT(*) FROM sections s 
            WHERE s.year_level_id = pyl.id AND s.branch_id = $branch_id AND s.academic_year_id = $current_ay_id) as section_count
    FROM program_year_levels pyl
    INNER JOIN programs p ON pyl.program_id = p.id
    WHERE pyl.is_active = 1
    ORDER BY pyl.program_id, pyl.year_level
");

$program_year_levels = [];
while ($row = $year_levels_query->fetch_assoc()) {
    $program_year_levels[$row['program_id']][] = $row;
}

// Fetch SHS strands
$strands = $conn->query("
    SELECT ss.*, st.track_name,
           (SELECT COUNT(*) FROM curriculum_subjects WHERE shs_strand_id = ss.id AND is_active = 1) as subject_count
    FROM shs_strands ss
    LEFT JOIN shs_tracks st ON ss.track_id = st.id
    WHERE ss.is_active = 1
    ORDER BY ss.strand_code
");

// Fetch SHS grade levels
$grade_levels_query = $conn->query("
    SELECT sgl.*, ss.id as strand_id,
           (SELECT COUNT(*) FROM sections s 
            WHERE s.shs_grade_level_id = sgl.id AND s.branch_id = $branch_id AND s.academic_year_id = $current_ay_id) as section_count
    FROM shs_grade_levels sgl
    INNER JOIN shs_strands ss ON sgl.strand_id = ss.id
    WHERE sgl.is_active = 1
    ORDER BY sgl.strand_id, sgl.grade_level
");

$strand_grade_levels = [];
while ($row = $grade_levels_query->fetch_assoc()) {
    $strand_grade_levels[$row['strand_id']][] = $row;
}

// Fetch teachers for adviser dropdown
$teachers = $conn->query("
    SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_TEACHER . " AND u.status = 'active'
    ORDER BY up.last_name, up.first_name
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SHARED UI DESIGN SYSTEM --- */
    .page-header {
        background: white; padding: 20px; border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
    }

    .nav-pills .nav-link {
        background: #f8f9fa; color: #555; margin-right: 8px; border-radius: 10px;
        font-weight: 700; font-size: 0.75rem; text-transform: uppercase;
        padding: 10px 20px; border: 1px solid #eee; transition: 0.3s;
    }
    .nav-pills .nav-link.active { background: var(--maroon); color: white; border-color: var(--maroon); box-shadow: 0 4px 10px rgba(128,0,0,0.2); }

    /* Program Cards */
    .program-card-modern {
        background: white; border-radius: 15px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s;
        cursor: pointer; height: 100%; position: relative; overflow: hidden;
        border-bottom: 4px solid var(--blue);
    }
    .program-card-modern:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .program-card-modern.shs { border-bottom-color: #28a745; }

    .program-card-header {
        padding: 25px; background: #fcfcfc; border-bottom: 1px solid #f0f0f0;
        display: flex; justify-content: space-between; align-items: start;
    }
    
    .program-icon-box {
        width: 45px; height: 45px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; margin-bottom: 10px;
    }

    /* Year Level & Section Cards */
    .year-level-card {
        background: white; border: 2px solid #e9ecef; border-radius: 15px;
        transition: all 0.3s ease; cursor: pointer; height: 100%;
    }
    .year-level-card:hover { border-color: var(--maroon); box-shadow: 0 5px 15px rgba(128, 0, 0, 0.1); transform: translateY(-3px); }

    .section-card {
        background: white; border-radius: 12px; border: 1px solid #eee;
        padding: 20px; transition: 0.3s; cursor: pointer; height: 100%;
    }
    .section-card:hover { border-color: var(--blue); background: #f8faff; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    
    .content-card { background: white; border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .card-header-modern {
        background: #fcfcfc; padding: 15px 20px; border-bottom: 1px solid #eee;
        font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;
    }

    .stat-pill {
        display: inline-flex; align-items: center; padding: 5px 12px;
        background: #f1f3f5; border-radius: 20px; font-size: 0.7rem;
        font-weight: 700; color: #666; margin-right: 5px;
    }

    /* List Items */
    .subject-list-item, .student-item {
        padding: 12px 20px; border-bottom: 1px solid #f5f5f5;
        display: flex; justify-content: space-between; align-items: center; transition: 0.2s;
    }
    .subject-list-item:hover, .student-item:hover { background-color: #fcfcfc; }

    .btn-maroon { background-color: var(--maroon); color: white; font-weight: 700; border: none; }
    .btn-maroon:hover { background-color: #600000; color: white; transform: translateY(-1px); }

    .empty-state { text-align: center; padding: 60px 20px; color: #adb5bd; }
    .empty-state i { font-size: 3.5rem; margin-bottom: 15px; display: block; opacity: 0.5; }

    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .spin { animation: spin 1s linear infinite; display: inline-block; }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-grid-3x3-gap me-2 text-maroon"></i>Programs & Sections
            </h4>
            <p class="text-muted small mb-0">Academic Year: <span class="fw-bold"><?php echo htmlspecialchars($current_ay['year_name'] ?? 'Not Set'); ?></span></p>
        </div>
        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <div id="alertContainer"></div>

    <!-- 2. MAIN VIEW: PROGRAMS GRID -->
    <div id="programsView">
        <!-- College Programs -->
        <div class="mb-5">
            <div class="d-flex align-items-center mb-4">
                <div class="bg-primary p-2 rounded-3 me-3 text-white shadow-sm"><i class="bi bi-mortarboard fs-5"></i></div>
                <h5 class="fw-bold mb-0" style="color: #444;">College Programs</h5>
            </div>
            
            <div class="row g-4" id="collegeProgramsGrid">
                <?php if ($programs->num_rows > 0): ?>
                    <?php while ($program = $programs->fetch_assoc()): ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="program-card-modern" onclick="viewProgramYearLevels(<?php echo $program['id']; ?>, 'college', '<?php echo htmlspecialchars(addslashes($program['program_name'])); ?>')">
                                <div class="program-card-header">
                                    <div>
                                        <div class="program-icon-box bg-primary bg-opacity-10 text-primary">
                                            <i class="bi bi-book-half"></i>
                                        </div>
                                        <h5 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($program['program_code']); ?></h5>
                                        <p class="text-muted small mb-0 fw-semibold line-clamp-1"><?php echo htmlspecialchars($program['program_name']); ?></p>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted opacity-50 fs-5"></i>
                                </div>
                                <div class="p-3 bg-light bg-opacity-50">
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="stat-pill"><i class="bi bi-journal-text me-1"></i> <?php echo $program['subject_count']; ?> Subjects</span>
                                        <span class="stat-pill"><i class="bi bi-layers me-1"></i> <?php echo count($program_year_levels[$program['id']] ?? []); ?> Levels</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state bg-white rounded-4 shadow-sm">
                            <i class="bi bi-mortarboard"></i>
                            <p class="fw-bold">No college programs found.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SHS Strands -->
        <div class="mb-5">
            <div class="d-flex align-items-center mb-4">
                <div class="bg-success p-2 rounded-3 me-3 text-white shadow-sm"><i class="bi bi-grid fs-5"></i></div>
                <h5 class="fw-bold mb-0" style="color: #444;">Senior High School Strands</h5>
            </div>
            
            <div class="row g-4" id="shsStrandsGrid">
                <?php if ($strands->num_rows > 0): ?>
                    <?php while ($strand = $strands->fetch_assoc()): ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="program-card-modern shs" onclick="viewProgramYearLevels(<?php echo $strand['id']; ?>, 'shs', '<?php echo htmlspecialchars(addslashes($strand['strand_name'])); ?>')">
                                <div class="program-card-header">
                                    <div>
                                        <div class="program-icon-box bg-success bg-opacity-10 text-success">
                                            <i class="bi bi-layers-half"></i>
                                        </div>
                                        <h5 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($strand['strand_code']); ?></h5>
                                        <p class="text-muted small mb-0 fw-semibold line-clamp-1"><?php echo htmlspecialchars($strand['strand_name']); ?></p>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted opacity-50 fs-5"></i>
                                </div>
                                <div class="p-3 bg-light bg-opacity-50">
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="stat-pill"><i class="bi bi-book me-1"></i> <?php echo $strand['subject_count']; ?> Subjects</span>
                                        <span class="stat-pill"><i class="bi bi-tag me-1"></i> <?php echo htmlspecialchars($strand['track_name'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state bg-white rounded-4 shadow-sm">
                            <i class="bi bi-grid"></i>
                            <p class="fw-bold">No SHS strands found.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 3. YEAR LEVEL VIEW -->
    <div id="yearLevelView" style="display: none;">
        <div class="d-flex align-items-center mb-4">
            <button class="btn btn-white border shadow-sm btn-sm me-3 rounded-circle" onclick="backToPrograms()">
                <i class="bi bi-arrow-left"></i>
            </button>
            <h5 class="fw-bold mb-0" id="selectedProgramName" style="color: var(--blue);"></h5>
        </div>
        
        <div class="row g-4" id="yearLevelCards">
            <!-- Dynamic Content -->
        </div>
    </div>

    <!-- 4. SECTIONS VIEW -->
    <div id="sectionsView" style="display: none;">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <button class="btn btn-white border shadow-sm btn-sm me-3 rounded-circle" onclick="backToYearLevels()">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <h5 class="fw-bold mb-0" id="selectedYearLevelName" style="color: var(--blue);"></h5>
            </div>
            <button class="btn btn-maroon btn-sm px-4 rounded-pill" onclick="openAddSectionModal()">
                <i class="bi bi-plus-circle me-1"></i> Add Section
            </button>
        </div>
        
        <ul class="nav nav-pills mb-4" id="semesterTabs">
            <li class="nav-item">
                <a class="nav-link active" href="#" onclick="changeSemester('1st'); return false;">1st Semester</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="changeSemester('2nd'); return false;">2nd Semester</a>
            </li>
            <li class="nav-item" id="summerTab" style="display: none;">
                <a class="nav-link" href="#" onclick="changeSemester('summer'); return false;">Summer</a>
            </li>
        </ul>
        
        <div class="content-card mb-5">
            <div class="card-header-modern bg-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-collection me-2 text-maroon"></i>Sections: <span id="currentSemesterLabel">1st Semester</span></span>
                <span class="badge bg-blue bg-opacity-10 text-blue fw-bold" id="subjectCountBadge">0 subjects in curriculum</span>
            </div>
            <div class="p-4">
                <div class="row g-3" id="sectionsList">
                    <!-- Dynamic Content -->
                </div>
            </div>
        </div>
    </div>

    <!-- 5. SECTION DETAIL VIEW -->
    <div id="sectionDetailView" style="display: none;">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <button class="btn btn-white border shadow-sm btn-sm me-3 rounded-circle" onclick="backToSections()">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <h5 class="fw-bold mb-0" id="selectedSectionName" style="color: var(--blue);"></h5>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm rounded-pill px-3" onclick="openAddStudentModal()">
                    <i class="bi bi-person-plus"></i> Add Student
                </button>
                <button class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="editSection(currentSectionId)">
                    <i class="bi bi-pencil"></i> Edit Info
                </button>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="content-card">
                    <div class="card-header-modern bg-primary text-white"><i class="bi bi-people me-2"></i>Students in Section (<span id="studentCount">0</span>)</div>
                    <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;" id="sectionStudentsList">
                        <!-- Dynamic Content -->
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="content-card">
                    <div class="card-header-modern bg-maroon text-white" style="background: var(--maroon) !important;"><i class="bi bi-book me-2"></i>Subjects / Curriculum (<span id="subjectCount">0</span>)</div>
                    <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;" id="sectionSubjectsList">
                        <!-- Dynamic Content -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-maroon text-dark py-3">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-plus-circle me-2"></i>Add New Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSectionForm">
                <input type="hidden" name="program_id" id="modal_program_id">
                <input type="hidden" name="year_level_id" id="modal_year_level_id">
                <input type="hidden" name="strand_id" id="modal_strand_id">
                <input type="hidden" name="grade_level_id" id="modal_grade_level_id">
                <input type="hidden" name="program_type" id="modal_program_type">
                <input type="hidden" name="semester" id="modal_semester">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase opacity-75">Section Name *</label>
                        <input type="text" class="form-control" name="section_name" placeholder="e.g., Section A" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold small text-uppercase opacity-75">Max Capacity *</label>
                            <input type="number" class="form-control" name="max_capacity" value="40" min="1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold small text-uppercase opacity-75">Room</label>
                            <input type="text" class="form-control" name="room" placeholder="Room 101">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase opacity-75">Section Adviser</label>
                        <select class="form-select" name="adviser_id">
                            <option value="">No Adviser Assigned</option>
                            <?php 
                            $teachers->data_seek(0);
                            while ($teacher = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light btn-sm px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon btn-sm px-4 fw-bold">Create Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-blue text-white py-3" style="background: var(--blue);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-pencil me-2"></i>Edit Section Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSectionForm">
                <input type="hidden" name="section_id" id="edit_section_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase opacity-75">Section Name *</label>
                        <input type="text" class="form-control" name="section_name" id="edit_section_name" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold small text-uppercase opacity-75">Max Capacity *</label>
                            <input type="number" class="form-control" name="max_capacity" id="edit_max_capacity" min="1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold small text-uppercase opacity-75">Room</label>
                            <input type="text" class="form-control" name="room" id="edit_room">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase opacity-75">Section Adviser</label>
                        <select class="form-select" name="adviser_id" id="edit_adviser_id">
                            <option value="">No Adviser Assigned</option>
                            <?php 
                            $teachers->data_seek(0);
                            while ($teacher = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light btn-sm px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Update Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Student to Section Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-person-plus me-2"></i>Add Students to Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <input type="text" class="form-control rounded-pill px-4" id="studentSearch" placeholder="Search students by name or ID...">
                </div>
                <div id="availableStudentsList" style="max-height: 400px; overflow-y: auto;">
                    <!-- Available students dynamically loaded -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// State variables
let currentProgramId = null;
let currentProgramType = null;
let currentProgramName = null;
let currentYearLevelId = null;
let currentYearLevelName = null;
let currentSemester = '1st';
let currentSectionId = null;
let currentSectionName = null;

// Program data from PHP
const programYearLevels = <?php echo json_encode($program_year_levels); ?>;
const strandGradeLevels = <?php echo json_encode($strand_grade_levels); ?>;

function viewProgramYearLevels(programId, type, programName) {
    currentProgramId = programId;
    currentProgramType = type;
    currentProgramName = programName;
    
    document.getElementById('selectedProgramName').textContent = programName;
    document.getElementById('programsView').style.display = 'none';
    document.getElementById('yearLevelView').style.display = 'block';
    
    renderYearLevelCards();
}

function renderYearLevelCards() {
    const container = document.getElementById('yearLevelCards');
    let html = '';
    
    const levels = currentProgramType === 'college' 
        ? programYearLevels[currentProgramId] || []
        : strandGradeLevels[currentProgramId] || [];
    
    if (levels.length === 0) {
        html = '<div class="col-12"><div class="empty-state"><i class="bi bi-layers"></i><p>No year levels found.</p></div></div>';
    } else {
        levels.forEach(level => {
            const levelId = level.id;
            const levelName = currentProgramType === 'college' ? level.year_name : `Grade ${level.grade_level}`;
            const sectionCount = level.section_count || 0;
            
            html += `
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="year-level-card p-4 text-center" onclick="viewSections(${levelId}, '${levelName.replace(/'/g, "\\'")}')">
                        <div class="mb-3"><i class="bi bi-mortarboard-fill fs-1" style="color: var(--maroon);"></i></div>
                        <h5 class="fw-bold mb-2">${levelName}</h5>
                        <div class="d-flex justify-content-center gap-2"><span class="badge bg-primary">${sectionCount} Sections</span></div>
                        <div class="mt-3"><small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Manage</small></div>
                    </div>
                </div>
            `;
        });
    }
    container.innerHTML = html;
}

function viewSections(yearLevelId, yearLevelName) {
    currentYearLevelId = yearLevelId;
    currentYearLevelName = yearLevelName;
    currentSemester = '1st';
    
    document.getElementById('selectedYearLevelName').textContent = currentProgramName + ' - ' + yearLevelName;
    document.getElementById('programsView').style.display = 'none';
    document.getElementById('yearLevelView').style.display = 'none';
    document.getElementById('sectionsView').style.display = 'block';
    
    document.querySelectorAll('#semesterTabs .nav-link').forEach(el => el.classList.remove('active'));
    document.querySelector('#semesterTabs .nav-link').classList.add('active');
    document.getElementById('currentSemesterLabel').textContent = '1st Semester';
    document.getElementById('summerTab').style.display = currentProgramType === 'college' ? 'block' : 'none';
    
    loadSections();
    loadSubjectCount();
}

function changeSemester(semester) {
    currentSemester = semester;
    document.querySelectorAll('#semesterTabs .nav-link').forEach(el => el.classList.remove('active'));
    event.target.closest('.nav-link').classList.add('active');
    const labels = { '1st': '1st Semester', '2nd': '2nd Semester', 'summer': 'Summer' };
    document.getElementById('currentSemesterLabel').textContent = labels[semester];
    loadSections();
    loadSubjectCount();
}

function loadSections() {
    const container = document.getElementById('sectionsList');
    container.innerHTML = '<div class="col-12 text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading...</div>';
    
    const params = new URLSearchParams({
        action: 'get_sections', program_type: currentProgramType, program_id: currentProgramId,
        year_level_id: currentYearLevelId, semester: currentSemester
    });
    
    fetch('process/sections_api.php?' + params)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.sections.length > 0) {
                let html = '';
                data.sections.forEach(section => {
                    const capacityPct = (section.student_count / section.max_capacity * 100);
                    html += `
                        <div class="col-lg-4 col-md-6">
                            <div class="section-card" onclick="viewSectionDetail(${section.id}, '${section.section_name.replace(/'/g, "\\'")}')">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div><h5 class="mb-1 fw-bold">${section.section_name}</h5><small class="text-muted">${section.room || 'TBA'}</small></div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" onclick="event.stopPropagation();" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="event.stopPropagation(); editSection(${section.id})"><i class="bi bi-pencil"></i> Edit</a></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="event.stopPropagation(); deleteSection(${section.id})"><i class="bi bi-trash"></i> Delete</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mb-3">
                                    <span class="badge bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i> ${section.student_count}</span>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-person"></i> ${section.adviser_name || 'No Adviser'}</span>
                                </div>
                                <div class="progress mb-2" style="height: 6px;"><div class="progress-bar bg-success" style="width: ${capacityPct}%"></div></div>
                                <small class="text-muted">${section.student_count}/${section.max_capacity} Slots</small>
                            </div>
                        </div>`;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="col-12"><div class="empty-state"><i class="bi bi-collection"></i><p>No sections found for this semester.</p></div></div>';
            }
        });
}

function loadSubjectCount() {
    const params = new URLSearchParams({
        action: 'get_subjects', program_type: currentProgramType, program_id: currentProgramId,
        year_level_id: currentYearLevelId, semester: currentSemester
    });
    fetch('process/sections_api.php?' + params).then(response => response.json()).then(data => {
        if (data.success) document.getElementById('subjectCountBadge').textContent = data.subjects.length + ' subjects in curriculum';
    });
}

function viewSectionDetail(sectionId, sectionName) {
    currentSectionId = sectionId;
    currentSectionName = sectionName;
    document.getElementById('selectedSectionName').textContent = currentProgramName + ' - ' + currentYearLevelName + ' - ' + sectionName;
    document.getElementById('sectionsView').style.display = 'none';
    document.getElementById('sectionDetailView').style.display = 'block';
    loadSectionStudents();
    loadSectionSubjects();
}

function loadSectionStudents() {
    const container = document.getElementById('sectionStudentsList');
    container.innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading...</div>';
    fetch('process/sections_api.php?action=get_section_students&section_id=' + currentSectionId)
        .then(response => response.json()).then(data => {
            document.getElementById('studentCount').textContent = data.students?.length || 0;
            if (data.success && data.students.length > 0) {
                let html = '';
                data.students.forEach((student, index) => {
                    html += `<div class="student-item"><div><strong>${index + 1}. ${student.name}</strong><br><small class="text-muted">${student.student_id || 'N/A'}</small></div>
                    <button class="btn btn-sm btn-outline-danger border-0" onclick="removeStudentFromSection(${student.id})"><i class="bi bi-trash"></i></button></div>`;
                });
                container.innerHTML = html;
            } else { container.innerHTML = '<div class="text-center p-5 text-muted"><i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>No students enrolled.</div>'; }
        });
}

function loadSectionSubjects() {
    const container = document.getElementById('sectionSubjectsList');
    container.innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading...</div>';
    const params = new URLSearchParams({
        action: 'get_subjects', program_type: currentProgramType, program_id: currentProgramId,
        year_level_id: currentYearLevelId, semester: currentSemester
    });
    fetch('process/sections_api.php?' + params).then(response => response.json()).then(data => {
        document.getElementById('subjectCount').textContent = data.subjects?.length || 0;
        if (data.success && data.subjects.length > 0) {
            let html = '';
            data.subjects.forEach(subject => {
                html += `<div class="subject-list-item"><div><strong>${subject.subject_code}</strong> - ${subject.subject_title}<br>
                <small class="text-muted">${subject.units} Units | ${subject.teacher_name || 'TBA'}</small></div><span class="badge bg-blue bg-opacity-10 text-blue">${subject.units}u</span></div>`;
            });
            container.innerHTML = html;
        } else { container.innerHTML = '<div class="text-center p-5 text-muted"><i class="bi bi-book fs-1 d-block mb-2 opacity-25"></i>No subjects configured.</div>'; }
    });
}

function backToPrograms() {
    document.getElementById('programsView').style.display = 'block';
    document.getElementById('yearLevelView').style.display = 'none';
}

function backToYearLevels() {
    document.getElementById('yearLevelView').style.display = 'block';
    document.getElementById('sectionsView').style.display = 'none';
}

function backToSections() {
    document.getElementById('sectionsView').style.display = 'block';
    document.getElementById('sectionDetailView').style.display = 'none';
    loadSections();
}

function openAddSectionModal() {
    document.getElementById('modal_program_id').value = currentProgramType === 'college' ? currentProgramId : '';
    document.getElementById('modal_strand_id').value = currentProgramType === 'shs' ? currentProgramId : '';
    document.getElementById('modal_year_level_id').value = currentProgramType === 'college' ? currentYearLevelId : '';
    document.getElementById('modal_grade_level_id').value = currentProgramType === 'shs' ? currentYearLevelId : '';
    document.getElementById('modal_program_type').value = currentProgramType;
    document.getElementById('modal_semester').value = currentSemester;
    document.getElementById('addSectionForm').reset();
    new bootstrap.Modal(document.getElementById('addSectionModal')).show();
}

document.getElementById('addSectionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add_section');
    fetch('process/sections_api.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
        if (data.success) { bootstrap.Modal.getInstance(document.getElementById('addSectionModal')).hide(); showAlert('success', data.message); loadSections(); }
        else showAlert('danger', data.message);
    });
});

function editSection(sectionId) {
    fetch('process/sections_api.php?action=get_section&section_id=' + sectionId).then(response => response.json()).then(data => {
        if (data.success) {
            document.getElementById('edit_section_id').value = data.section.id;
            document.getElementById('edit_section_name').value = data.section.section_name;
            document.getElementById('edit_max_capacity').value = data.section.max_capacity;
            document.getElementById('edit_room').value = data.section.room || '';
            document.getElementById('edit_adviser_id').value = data.section.adviser_id || '';
            new bootstrap.Modal(document.getElementById('editSectionModal')).show();
        }
    });
}

document.getElementById('editSectionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'update_section');
    fetch('process/sections_api.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
        if (data.success) { bootstrap.Modal.getInstance(document.getElementById('editSectionModal')).hide(); showAlert('success', data.message); loadSections(); }
        else showAlert('danger', data.message);
    });
});

function deleteSection(sectionId) {
    if (!confirm('Delete this section? Enrollment records will be removed.')) return;
    const formData = new FormData();
    formData.append('action', 'delete_section');
    formData.append('section_id', sectionId);
    fetch('process/sections_api.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
        if (data.success) { showAlert('success', data.message); loadSections(); }
        else showAlert('danger', data.message);
    });
}

function openAddStudentModal() { loadAvailableStudents(); new bootstrap.Modal(document.getElementById('addStudentModal')).show(); }

function loadAvailableStudents(search = '') {
    const container = document.getElementById('availableStudentsList');
    container.innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading...</div>';
    const params = new URLSearchParams({ action: 'get_available_students', section_id: currentSectionId, program_type: currentProgramType, program_id: currentProgramId, search: search });
    fetch('process/sections_api.php?' + params).then(response => response.json()).then(data => {
        if (data.success && data.students.length > 0) {
            let html = '';
            data.students.forEach(student => {
                html += `<div class="student-item"><div><strong>${student.name}</strong><br><small class="text-muted">${student.student_id || 'N/A'}</small></div>
                <button class="btn btn-sm btn-success" onclick="addStudentToSection(${student.id})"><i class="bi bi-plus"></i> Add</button></div>`;
            });
            container.innerHTML = html;
        } else container.innerHTML = '<div class="text-center p-4 text-muted">No students available.</div>';
    });
}

document.getElementById('studentSearch').addEventListener('input', function() { loadAvailableStudents(this.value); });

function addStudentToSection(studentId) {
    const formData = new FormData();
    formData.append('action', 'add_student_to_section');
    formData.append('section_id', currentSectionId);
    formData.append('student_id', studentId);
    fetch('process/sections_api.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
        if (data.success) { loadSectionStudents(); loadAvailableStudents(document.getElementById('studentSearch').value); }
        else showAlert('danger', data.message);
    });
}

function removeStudentFromSection(studentId) {
    if (!confirm('Remove student?')) return;
    const formData = new FormData();
    formData.append('action', 'remove_student_from_section');
    formData.append('section_id', currentSectionId);
    formData.append('student_id', studentId);
    fetch('process/sections_api.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
        if (data.success) loadSectionStudents();
        else showAlert('danger', data.message);
    });
}

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    setTimeout(() => container.innerHTML = '', 5000);
}
</script>

<?php include '../../includes/footer.php'; ?>