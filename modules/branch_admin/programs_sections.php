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
?>

<style>
    .nav-pills .nav-link {
        background: #f8f9fa;
        color: #333;
        margin-right: 8px;
        border: 1px solid #e0e0e0;
        transition: all 0.2s ease;
    }
    .nav-pills .nav-link:hover {
        background: #e9ecef;
    }
    .nav-pills .nav-link.active {
        background: #800000;
        border-color: #800000;
        color: white;
    }
    
    .program-card {
        background: linear-gradient(135deg, #003366 0%, #004080 100%);
        border-radius: 15px;
        color: white;
        transition: all 0.3s ease;
        cursor: pointer;
        min-height: 200px;
    }
    .program-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 51, 102, 0.3);
    }
    .program-card.shs {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }
    .program-card.shs:hover {
        box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
    }
    
    .year-level-card {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .year-level-card:hover {
        border-color: #800000;
        box-shadow: 0 5px 15px rgba(128, 0, 0, 0.15);
    }
    .year-level-card.active {
        border-color: #800000;
        background: #fff5f5;
    }
    
    .section-card {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .section-card:hover {
        border-color: #003366;
        box-shadow: 0 5px 15px rgba(0, 51, 102, 0.15);
        transform: translateY(-3px);
    }
    
    .stat-badge {
        background: rgba(255,255,255,0.2);
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
    }
    
    .add-section-btn {
        background: #800000;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 20px;
        transition: all 0.2s ease;
    }
    .add-section-btn:hover {
        background: #600000;
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #6c757d;
    }
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 15px;
        opacity: 0.3;
    }
    
    .subject-list-item {
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .subject-list-item:last-child {
        border-bottom: none;
    }
    .subject-list-item:hover {
        background: #f8f9fa;
    }
    
    .student-item {
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
    }
    .student-item:hover {
        background: #f8f9fa;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spin { animation: spin 1s linear infinite; }
</style>

<?php include '../../includes/sidebar.php'; ?>

<div id="content">
    <div class="main-content-body">
        <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0" style="color: #003366;">
                    <i class="bi bi-grid-3x3-gap"></i> Programs & Sections Management
                </h4>
                <small class="text-muted">Academic Year: <?php echo htmlspecialchars($current_ay['year_name'] ?? 'Not Set'); ?></small>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Main View: Programs Grid -->
        <div id="programsView">
            <!-- College Programs -->
            <div class="mb-4">
                <h5 class="mb-3"><i class="bi bi-mortarboard text-primary"></i> College Programs</h5>
                <div class="row" id="collegeProgramsGrid">
                    <?php if ($programs->num_rows > 0): ?>
                        <?php while ($program = $programs->fetch_assoc()): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="program-card p-4" onclick="viewProgramYearLevels(<?php echo $program['id']; ?>, 'college', '<?php echo htmlspecialchars(addslashes($program['program_name'])); ?>')">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($program['program_code']); ?></h5>
                                            <p class="mb-0 opacity-75" style="font-size: 0.9rem;"><?php echo htmlspecialchars($program['program_name']); ?></p>
                                        </div>
                                        <i class="bi bi-journal-bookmark-fill fs-3 opacity-50"></i>
                                    </div>
                                    <div class="d-flex gap-2 mt-3">
                                        <span class="stat-badge">
                                            <i class="bi bi-book"></i> <?php echo $program['subject_count']; ?> Subjects
                                        </span>
                                        <span class="stat-badge">
                                            <i class="bi bi-layers"></i> <?php echo count($program_year_levels[$program['id']] ?? []); ?> Year Levels
                                        </span>
                                    </div>
                                    <div class="mt-3 text-end">
                                        <small class="opacity-75"><i class="bi bi-arrow-right-circle"></i> Click to manage sections</small>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="bi bi-mortarboard"></i>
                                <p>No college programs found. Contact School Admin to add programs.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SHS Strands -->
            <div class="mb-4">
                <h5 class="mb-3"><i class="bi bi-grid text-success"></i> Senior High School Strands</h5>
                <div class="row" id="shsStrandsGrid">
                    <?php if ($strands->num_rows > 0): ?>
                        <?php while ($strand = $strands->fetch_assoc()): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="program-card shs p-4" onclick="viewProgramYearLevels(<?php echo $strand['id']; ?>, 'shs', '<?php echo htmlspecialchars(addslashes($strand['strand_name'])); ?>')">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($strand['strand_code']); ?></h5>
                                            <p class="mb-0 opacity-75" style="font-size: 0.9rem;"><?php echo htmlspecialchars($strand['strand_name']); ?></p>
                                        </div>
                                        <i class="bi bi-layers fs-3 opacity-50"></i>
                                    </div>
                                    <div class="d-flex gap-2 mt-3">
                                        <span class="stat-badge">
                                            <i class="bi bi-book"></i> <?php echo $strand['subject_count']; ?> Subjects
                                        </span>
                                        <span class="stat-badge">
                                            <i class="bi bi-tag"></i> <?php echo htmlspecialchars($strand['track_name'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                    <div class="mt-3 text-end">
                                        <small class="opacity-75"><i class="bi bi-arrow-right-circle"></i> Click to manage sections</small>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="bi bi-grid"></i>
                                <p>No SHS strands found. Contact School Admin to add strands.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Year Level View (Hidden by default) -->
        <div id="yearLevelView" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <button class="btn btn-outline-secondary btn-sm me-2" onclick="backToPrograms()">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <span class="fs-5 fw-bold" id="selectedProgramName"></span>
                </div>
            </div>
            
            <div class="row" id="yearLevelCards">
                <!-- Year level cards will be loaded here -->
            </div>
        </div>

        <!-- Sections View (Hidden by default) -->
        <div id="sectionsView" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <button class="btn btn-outline-secondary btn-sm me-2" onclick="backToYearLevels()">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <span class="fs-5 fw-bold" id="selectedYearLevelName"></span>
                </div>
                <button class="add-section-btn" onclick="openAddSectionModal()">
                    <i class="bi bi-plus-circle"></i> Add Section
                </button>
            </div>
            
            <!-- Semester Tabs -->
            <ul class="nav nav-pills mb-4" id="semesterTabs">
                <li class="nav-item">
                    <a class="nav-link active" href="#" onclick="changeSemester('1st'); return false;">
                        <i class="bi bi-1-circle"></i> 1st Semester
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="changeSemester('2nd'); return false;">
                        <i class="bi bi-2-circle"></i> 2nd Semester
                    </a>
                </li>
                <li class="nav-item" id="summerTab" style="display: none;">
                    <a class="nav-link" href="#" onclick="changeSemester('summer'); return false;">
                        <i class="bi bi-sun"></i> Summer
                    </a>
                </li>
            </ul>
            
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-collection"></i> Sections - <span id="currentSemesterLabel">1st Semester</span></h6>
                            <span class="badge bg-info" id="subjectCountBadge">0 subjects in curriculum</span>
                        </div>
                        <div class="card-body">
                            <div class="row" id="sectionsList">
                                <!-- Section cards will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Detail View (Hidden by default) -->
        <div id="sectionDetailView" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <button class="btn btn-outline-secondary btn-sm me-2" onclick="backToSections()">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <span class="fs-5 fw-bold" id="selectedSectionName"></span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="openAddStudentModal()">
                        <i class="bi bi-person-plus"></i> Add Student
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="editSection(currentSectionId)">
                        <i class="bi bi-pencil"></i> Edit Section
                    </button>
                </div>
            </div>
            
            <div class="row">
                <!-- Students List -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="bi bi-people"></i> Students in Section (<span id="studentCount">0</span>)</h6>
                        </div>
                        <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;" id="sectionStudentsList">
                            <!-- Students will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- Subjects List -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header" style="background: #800000; color: white;">
                            <h6 class="mb-0"><i class="bi bi-book"></i> Subjects for this Section (<span id="subjectCount">0</span>)</h6>
                        </div>
                        <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;" id="sectionSubjectsList">
                            <!-- Subjects will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #800000 0%, #a00000 100%); color: white;">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSectionForm">
                <input type="hidden" name="program_id" id="modal_program_id">
                <input type="hidden" name="year_level_id" id="modal_year_level_id">
                <input type="hidden" name="strand_id" id="modal_strand_id">
                <input type="hidden" name="grade_level_id" id="modal_grade_level_id">
                <input type="hidden" name="program_type" id="modal_program_type">
                <input type="hidden" name="semester" id="modal_semester">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Section Name *</label>
                        <input type="text" class="form-control" name="section_name" placeholder="e.g., Section A, Block 1, 1A" required>
                        <small class="text-muted">This section will include all subjects for the selected year level and semester</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Max Capacity *</label>
                            <input type="number" class="form-control" name="max_capacity" value="40" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Room</label>
                            <input type="text" class="form-control" name="room" placeholder="e.g., Room 101">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Section Adviser</label>
                        <select class="form-select" name="adviser_id">
                            <option value="">No Adviser Assigned</option>
                            <?php 
                            $teachers->data_seek(0);
                            while ($teacher = $teachers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-check-circle"></i> Create Section
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #003366; color: white;">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSectionForm">
                <input type="hidden" name="section_id" id="edit_section_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Section Name *</label>
                        <input type="text" class="form-control" name="section_name" id="edit_section_name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Max Capacity *</label>
                            <input type="number" class="form-control" name="max_capacity" id="edit_max_capacity" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Room</label>
                            <input type="text" class="form-control" name="room" id="edit_room">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Section Adviser</label>
                        <select class="form-select" name="adviser_id" id="edit_adviser_id">
                            <option value="">No Adviser Assigned</option>
                            <?php 
                            $teachers->data_seek(0);
                            while ($teacher = $teachers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Update Section
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Student to Section Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: #28a745; color: white;">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add Students to Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="studentSearch" placeholder="Search students by name or ID...">
                </div>
                <div id="availableStudentsList" style="max-height: 400px; overflow-y: auto;">
                    <!-- Available students will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

        </div> <!-- Close container-fluid -->

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

// Program year levels data from PHP
const programYearLevels = <?php 
    $pyl_data = [];
    foreach ($program_year_levels as $pid => $levels) {
        $pyl_data[$pid] = $levels;
    }
    echo json_encode($pyl_data);
?>;

const strandGradeLevels = <?php 
    $sgl_data = [];
    foreach ($strand_grade_levels as $sid => $levels) {
        $sgl_data[$sid] = $levels;
    }
    echo json_encode($sgl_data);
?>;

// View program year levels
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
        html = `
            <div class="col-12">
                <div class="empty-state">
                    <i class="bi bi-layers"></i>
                    <p>No year levels found for this program.</p>
                </div>
            </div>
        `;
    } else {
        levels.forEach(level => {
            const levelId = currentProgramType === 'college' ? level.id : level.id;
            const levelName = currentProgramType === 'college' ? level.year_name : `Grade ${level.grade_level}`;
            const sectionCount = level.section_count || 0;
            
            html += `
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="year-level-card p-4 text-center" onclick="viewSections(${levelId}, '${levelName.replace(/'/g, "\\'")}')">
                        <div class="mb-3">
                            <i class="bi bi-mortarboard-fill fs-1" style="color: #800000;"></i>
                        </div>
                        <h5 class="fw-bold mb-2">${levelName}</h5>
                        <div class="d-flex justify-content-center gap-2">
                            <span class="badge bg-primary">${sectionCount} Sections</span>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Manage Sections</small>
                        </div>
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
    document.getElementById('sectionDetailView').style.display = 'none';
    
    // Reset semester tabs
    document.querySelectorAll('#semesterTabs .nav-link').forEach(el => el.classList.remove('active'));
    document.querySelector('#semesterTabs .nav-link').classList.add('active');
    document.getElementById('currentSemesterLabel').textContent = '1st Semester';
    
    // Show summer tab only for college
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
    container.innerHTML = '<div class="col-12 text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading sections...</div>';
    
    const params = new URLSearchParams({
        action: 'get_sections',
        program_type: currentProgramType,
        program_id: currentProgramId,
        year_level_id: currentYearLevelId,
        semester: currentSemester
    });
    
    fetch('process/sections_api.php?' + params)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.sections.length > 0) {
                let html = '';
                data.sections.forEach(section => {
                    html += `
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="section-card p-4" onclick="viewSectionDetail(${section.id}, '${section.section_name.replace(/'/g, "\\'")}')">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1 fw-bold">${section.section_name}</h5>
                                        <small class="text-muted">${section.room || 'No room assigned'}</small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation();" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="event.stopPropagation(); editSection(${section.id})"><i class="bi bi-pencil"></i> Edit</a></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="event.stopPropagation(); deleteSection(${section.id})"><i class="bi bi-trash"></i> Delete</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mb-3">
                                    <span class="badge bg-primary"><i class="bi bi-people"></i> ${section.student_count} Students</span>
                                    <span class="badge bg-secondary"><i class="bi bi-person-badge"></i> ${section.adviser_name || 'No Adviser'}</span>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: ${(section.student_count / section.max_capacity * 100)}%"></div>
                                </div>
                                <small class="text-muted">${section.student_count}/${section.max_capacity} capacity</small>
                                <div class="text-end mt-2">
                                    <small class="text-primary"><i class="bi bi-arrow-right"></i> View Details</small>
                                </div>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="bi bi-collection"></i>
                            <p>No sections created yet for this semester. Click "Add Section" to create one.</p>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="col-12 alert alert-danger">Error loading sections</div>';
            console.error('Error:', error);
        });
}

function loadSubjectCount() {
    const params = new URLSearchParams({
        action: 'get_subjects',
        program_type: currentProgramType,
        program_id: currentProgramId,
        year_level_id: currentYearLevelId,
        semester: currentSemester
    });
    
    fetch('process/sections_api.php?' + params)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('subjectCountBadge').textContent = data.subjects.length + ' subjects in curriculum';
            }
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
    container.innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading students...</div>';
    
    fetch('process/sections_api.php?action=get_section_students&section_id=' + currentSectionId)
        .then(response => response.json())
        .then(data => {
            document.getElementById('studentCount').textContent = data.students?.length || 0;
            
            if (data.success && data.students.length > 0) {
                let html = '';
                data.students.forEach((student, index) => {
                    html += `
                        <div class="student-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${index + 1}. ${student.name}</strong>
                                <br><small class="text-muted">${student.student_id || 'N/A'}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeStudentFromSection(${student.id})">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="text-center p-4 text-muted">
                        <i class="bi bi-people fs-1 d-block mb-2"></i>
                        No students enrolled yet
                    </div>
                `;
            }
        });
}

function loadSectionSubjects() {
    const container = document.getElementById('sectionSubjectsList');
    container.innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading subjects...</div>';
    
    const params = new URLSearchParams({
        action: 'get_subjects',
        program_type: currentProgramType,
        program_id: currentProgramId,
        year_level_id: currentYearLevelId,
        semester: currentSemester
    });
    
    fetch('process/sections_api.php?' + params)
        .then(response => response.json())
        .then(data => {
            document.getElementById('subjectCount').textContent = data.subjects?.length || 0;
            
            if (data.success && data.subjects.length > 0) {
                let html = '';
                data.subjects.forEach(subject => {
                    html += `
                        <div class="subject-list-item">
                            <div>
                                <strong>${subject.subject_code}</strong> - ${subject.subject_title}
                                <br><small class="text-muted">${subject.units} units | ${subject.teacher_name || 'No teacher assigned'}</small>
                            </div>
                            <span class="badge bg-info">${subject.units}u</span>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="text-center p-4 text-muted">
                        <i class="bi bi-book fs-1 d-block mb-2"></i>
                        No subjects configured for this semester
                    </div>
                `;
            }
        });
}

function backToPrograms() {
    document.getElementById('programsView').style.display = 'block';
    document.getElementById('yearLevelView').style.display = 'none';
    document.getElementById('sectionsView').style.display = 'none';
    document.getElementById('sectionDetailView').style.display = 'none';
    currentProgramId = null;
    currentProgramType = null;
    currentProgramName = null;
}

function backToYearLevels() {
    document.getElementById('programsView').style.display = 'none';
    document.getElementById('yearLevelView').style.display = 'block';
    document.getElementById('sectionsView').style.display = 'none';
    document.getElementById('sectionDetailView').style.display = 'none';
    currentYearLevelId = null;
    currentYearLevelName = null;
}

function backToSections() {
    document.getElementById('sectionsView').style.display = 'block';
    document.getElementById('sectionDetailView').style.display = 'none';
    currentSectionId = null;
    currentSectionName = null;
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

// Add section form submit
document.getElementById('addSectionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add_section');
    
    fetch('process/sections_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addSectionModal')).hide();
            showAlert('success', data.message);
            loadSections();
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Error creating section');
        console.error('Error:', error);
    });
});

function editSection(sectionId) {
    fetch('process/sections_api.php?action=get_section&section_id=' + sectionId)
        .then(response => response.json())
        .then(data => {
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
    
    fetch('process/sections_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editSectionModal')).hide();
            showAlert('success', data.message);
            loadSections();
        } else {
            showAlert('danger', data.message);
        }
    });
});

function deleteSection(sectionId) {
    if (!confirm('Are you sure you want to delete this section? All student enrollments will be removed.')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_section');
    formData.append('section_id', sectionId);
    
    fetch('process/sections_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            loadSections();
        } else {
            showAlert('danger', data.message);
        }
    });
}

function openAddStudentModal() {
    loadAvailableStudents();
    new bootstrap.Modal(document.getElementById('addStudentModal')).show();
}

function loadAvailableStudents(search = '') {
    const container = document.getElementById('availableStudentsList');
    container.innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-repeat spin"></i> Loading students...</div>';
    
    const params = new URLSearchParams({
        action: 'get_available_students',
        section_id: currentSectionId,
        program_type: currentProgramType,
        program_id: currentProgramId,
        search: search
    });
    
    fetch('process/sections_api.php?' + params)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.students.length > 0) {
                let html = '';
                data.students.forEach(student => {
                    html += `
                        <div class="student-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${student.name}</strong>
                                <br><small class="text-muted">${student.student_id || 'N/A'}</small>
                            </div>
                            <button class="btn btn-sm btn-success" onclick="addStudentToSection(${student.id})">
                                <i class="bi bi-plus"></i> Add
                            </button>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-center p-4 text-muted">No available students found</div>';
            }
        });
}

document.getElementById('studentSearch').addEventListener('input', function() {
    loadAvailableStudents(this.value);
});

function addStudentToSection(studentId) {
    const formData = new FormData();
    formData.append('action', 'add_student_to_section');
    formData.append('section_id', currentSectionId);
    formData.append('student_id', studentId);
    
    fetch('process/sections_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadSectionStudents();
            loadAvailableStudents(document.getElementById('studentSearch').value);
        } else {
            showAlert('danger', data.message);
        }
    });
}

function removeStudentFromSection(studentId) {
    if (!confirm('Remove this student from the section?')) return;
    
    const formData = new FormData();
    formData.append('action', 'remove_student_from_section');
    formData.append('section_id', currentSectionId);
    formData.append('student_id', studentId);
    
    fetch('process/sections_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadSectionStudents();
        } else {
            showAlert('danger', data.message);
        }
    });
}

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    setTimeout(() => container.innerHTML = '', 5000);
}
</script>

<?php include '../../includes/footer.php'; ?>
