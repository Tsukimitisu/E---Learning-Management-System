<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Classes";
$teacher_id = $_SESSION['user_id'];

// Get all academic years for filter
$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Get selected filters from GET or use defaults
$selected_ay_id = isset($_GET['academic_year']) ? (int)$_GET['academic_year'] : 0;
$selected_semester = isset($_GET['semester']) ? $_GET['semester'] : '';

// If no academic year selected, get active one
if ($selected_ay_id == 0) {
    $active_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
    $selected_ay_id = $active_ay['id'] ?? ($academic_years[0]['id'] ?? 0);
}

// Get current academic year name
$current_ay_name = '';
foreach ($academic_years as $ay) {
    if ($ay['id'] == $selected_ay_id) {
        $current_ay_name = $ay['year_name'];
        break;
    }
}

// Get teacher's assigned classes with all details
$classes_query = $conn->prepare("
    SELECT 
        cs.id as subject_id,
        cs.subject_code,
        cs.subject_title,
        cs.units,
        cs.semester,
        cs.program_id,
        cs.year_level_id,
        cs.shs_strand_id,
        cs.shs_grade_level_id,
        COALESCE(p.program_name, ss.strand_name) as program_name,
        COALESCE(pyl.year_name, sgl.grade_name) as year_level,
        b.name as branch_name,
        tsa.branch_id,
        ay.year_name as academic_year_name
    FROM teacher_subject_assignments tsa
    INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
    INNER JOIN branches b ON tsa.branch_id = b.id
    INNER JOIN academic_years ay ON tsa.academic_year_id = ay.id
    LEFT JOIN programs p ON cs.program_id = p.id
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON cs.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    WHERE tsa.teacher_id = ? 
    AND tsa.academic_year_id = ?
    AND tsa.is_active = 1
    ORDER BY cs.semester, cs.subject_code
");

$classes_query->bind_param("ii", $teacher_id, $selected_ay_id);
$classes_query->execute();
$classes_result = $classes_query->get_result();

// Process classes and get section details for each
$classes_with_sections = [];
while ($class = $classes_result->fetch_assoc()) {
    // Convert semester number to string format
    $semester_map = [1 => '1st', 2 => '2nd', 3 => 'summer'];
    $semester_str = $semester_map[$class['semester']] ?? '1st';
    
    // Skip if semester filter is set and doesn't match
    if (!empty($selected_semester) && $semester_str != $selected_semester) {
        continue;
    }
    
    // Get sections for this subject
    if (!empty($class['program_id'])) {
        $sections_query = $conn->prepare("
            SELECT s.id, s.section_name, s.room,
                   (SELECT COUNT(*) FROM section_students ss WHERE ss.section_id = s.id AND ss.status = 'active') as student_count
            FROM sections s
            WHERE s.program_id = ? AND s.year_level_id = ?
            AND s.semester = ? AND s.branch_id = ? AND s.academic_year_id = ?
            AND s.is_active = 1
            ORDER BY s.section_name
        ");
        $sections_query->bind_param("iisii", 
            $class['program_id'], $class['year_level_id'], $semester_str, $class['branch_id'], $selected_ay_id
        );
    } else {
        $sections_query = $conn->prepare("
            SELECT s.id, s.section_name, s.room,
                   (SELECT COUNT(*) FROM section_students ss WHERE ss.section_id = s.id AND ss.status = 'active') as student_count
            FROM sections s
            WHERE s.shs_strand_id = ? AND s.shs_grade_level_id = ?
            AND s.semester = ? AND s.branch_id = ? AND s.academic_year_id = ?
            AND s.is_active = 1
            ORDER BY s.section_name
        ");
        $sections_query->bind_param("iisii", 
            $class['shs_strand_id'], $class['shs_grade_level_id'], $semester_str, $class['branch_id'], $selected_ay_id
        );
    }
    $sections_query->execute();
    $sections = $sections_query->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate total students across all sections
    $total_students = array_sum(array_column($sections, 'student_count'));
    $class['sections'] = $sections;
    $class['section_count'] = count($sections);
    $class['total_students'] = $total_students;
    $class['semester_str'] = $semester_str;
    
    $classes_with_sections[] = $class;
}

// Get unique semesters for filter
$semesters = ['1st', '2nd', 'summer'];

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
        z-index: 100;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px; 
        background-color: #f8f9fa;
    }

    .filter-section {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .filter-section .form-select {
        min-width: 180px;
        border-radius: 10px;
        border: 2px solid #e0e0e0;
        padding: 8px 15px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .filter-section .form-select:focus {
        border-color: var(--maroon);
        box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
    }

    /* Hero Class Card */
    .class-hero-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        margin-bottom: 25px;
        overflow: hidden;
        border-left: 6px solid var(--maroon);
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .class-hero-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(128, 0, 0, 0.12);
    }

    .class-hero-header {
        background: linear-gradient(135deg, #003366 0%, #004080 100%);
        color: white;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .class-hero-body {
        padding: 25px;
    }

    .class-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .class-info-item {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .class-info-item:hover {
        background: #fff;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }

    .class-info-item .label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #888;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .class-info-item .value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--blue);
    }

    .class-info-item.highlight .value {
        color: var(--maroon);
        font-size: 1.2rem;
    }

    /* Sections within Hero */
    .sections-container {
        border-top: 1px solid #eee;
        padding-top: 20px;
    }

    .sections-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .section-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .section-chip {
        background: linear-gradient(135deg, var(--maroon) 0%, #a00000 100%);
        color: white;
        padding: 12px 20px;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 200px;
    }

    .section-chip:hover {
        transform: scale(1.02);
        box-shadow: 0 5px 20px rgba(128, 0, 0, 0.3);
    }

    .section-chip .section-name {
        font-weight: 600;
        font-size: 1rem;
    }

    .section-chip .section-info {
        font-size: 0.75rem;
        opacity: 0.9;
    }

    .section-chip .student-count {
        background: rgba(255,255,255,0.2);
        padding: 5px 10px;
        border-radius: 8px;
        font-size: 0.8rem;
        margin-left: auto;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-badge.active {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
    }

    .status-badge.inactive {
        background: rgba(108, 117, 125, 0.15);
        color: #6c757d;
    }

    .action-btn {
        background: var(--maroon);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .action-btn:hover {
        background: #6b0000;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(128, 0, 0, 0.3);
    }

    .no-sections-msg {
        color: #888;
        font-style: italic;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
        text-align: center;
    }

    /* Animation delays */
    <?php for($i=1; $i<=20; $i++): ?>
        .delay-<?php echo $i; ?> { animation-delay: <?php echo $i * 0.1; ?>s; }
    <?php endfor; ?>

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .header-fixed-part { 
            flex-direction: column; 
            gap: 15px; 
        }
        .filter-section {
            flex-direction: column;
            width: 100%;
        }
        .filter-section .form-select {
            width: 100%;
        }
        .class-info-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .section-chip {
            min-width: 100%;
        }
    }

    @media (max-width: 576px) {
        .class-hero-header {
            flex-direction: column;
            text-align: center;
            gap: 10px;
        }
        .class-info-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>


<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-journal-bookmark me-2"></i>My Classes
            </h4>
            <p class="text-muted small mb-0">View and manage your assigned classes</p>
        </div>
        
        <div class="filter-section">
            <select class="form-select" id="academicYearFilter" onchange="applyFilters()">
                <?php foreach ($academic_years as $ay): ?>
                <option value="<?php echo $ay['id']; ?>" <?php echo $ay['id'] == $selected_ay_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ay['year_name']); ?>
                    <?php echo $ay['is_active'] ? '(Current)' : ''; ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select class="form-select" id="semesterFilter" onchange="applyFilters()">
                <option value="">All Semesters</option>
                <option value="1st" <?php echo $selected_semester == '1st' ? 'selected' : ''; ?>>1st Semester</option>
                <option value="2nd" <?php echo $selected_semester == '2nd' ? 'selected' : ''; ?>>2nd Semester</option>
                <option value="summer" <?php echo $selected_semester == 'summer' ? 'selected' : ''; ?>>Summer</option>
            </select>
            
            <a href="dashboard.php" class="btn btn-outline-secondary px-4 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
</div>

<div class="body-scroll-part">
    
    <?php if (count($classes_with_sections) == 0): ?>
    <div class="text-center py-5 animate__animated animate__fadeIn">
        <div class="card border-0 shadow-sm rounded-4 p-5">
            <i class="bi bi-journal-x display-1 text-muted opacity-25"></i>
            <h5 class="mt-3 text-muted">No classes found.</h5>
            <p class="small text-muted mb-0">
                <?php if (!empty($selected_semester)): ?>
                    Try selecting a different semester or "All Semesters".
                <?php else: ?>
                    No classes are assigned for the selected academic year.<br>
                    Contact your Branch Admin if you believe this is an error.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php else: ?>
    
    <?php 
    $counter = 1;
    foreach ($classes_with_sections as $class): 
        $is_college = !empty($class['program_id']);
        $program_type = $is_college ? 'College' : 'SHS';
    ?>
    <div class="class-hero-card animate__animated animate__fadeInUp delay-<?php echo $counter; ?>">
        <!-- Hero Header -->
        <div class="class-hero-header">
            <div>
                <h5 class="fw-bold mb-1">
                    <i class="bi bi-book me-2"></i>
                    <?php echo htmlspecialchars($class['subject_code']); ?> - <?php echo htmlspecialchars($class['subject_title']); ?>
                </h5>
                <small class="opacity-75">
                    <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($class['branch_name']); ?> | 
                    <i class="bi bi-mortarboard me-1"></i><?php echo htmlspecialchars($class['program_name']); ?>
                </small>
            </div>
            <span class="status-badge active">
                <i class="bi bi-check-circle me-1"></i> Active
            </span>
        </div>
        
        <!-- Hero Body -->
        <div class="class-hero-body">
            <!-- Info Grid -->
            <div class="class-info-grid">
                <div class="class-info-item">
                    <div class="label"><i class="bi bi-book me-1"></i>Subject</div>
                    <div class="value"><?php echo htmlspecialchars($class['subject_code']); ?></div>
                </div>
                
                <div class="class-info-item">
                    <div class="label"><i class="bi bi-clock me-1"></i>Schedule</div>
                    <div class="value">
                        <?php 
                        // For now, display section room/general info - can be expanded with actual schedule data
                        $rooms = array_filter(array_column($class['sections'], 'room'));
                        echo !empty($rooms) ? htmlspecialchars(implode(', ', array_unique($rooms))) : 'TBA';
                        ?>
                    </div>
                </div>
                
                <div class="class-info-item">
                    <div class="label"><i class="bi bi-collection me-1"></i>Sections</div>
                    <div class="value"><?php echo count($class['sections']); ?> Section<?php echo count($class['sections']) != 1 ? 's' : ''; ?></div>
                </div>
                
                <div class="class-info-item">
                    <div class="label"><i class="bi bi-calendar-event me-1"></i>School Year - Semester</div>
                    <div class="value"><?php echo htmlspecialchars($current_ay_name); ?> - <?php echo htmlspecialchars(ucfirst($class['semester_str'])); ?></div>
                </div>
                
                <div class="class-info-item highlight">
                    <div class="label"><i class="bi bi-people me-1"></i>Number of Students</div>
                    <div class="value"><?php echo $class['total_students']; ?></div>
                </div>
                
                <div class="class-info-item">
                    <div class="label"><i class="bi bi-info-circle me-1"></i>Notes</div>
                    <div class="value" style="font-size: 0.85rem;">
                        <?php echo $class['units']; ?> Units | <?php echo $program_type; ?> | <?php echo htmlspecialchars($class['year_level']); ?>
                    </div>
                </div>
            </div>
            
            <!-- Sections Container -->
            <div class="sections-container">
                <div class="sections-header">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-grid-3x3 me-2 text-primary"></i>Sections
                    </h6>
                    <button class="action-btn" onclick="viewSubjectSections(<?php echo $class['subject_id']; ?>)">
                        <i class="bi bi-eye me-1"></i> View All Sections
                    </button>
                </div>
                
                <?php if (empty($class['sections'])): ?>
                <div class="no-sections-msg">
                    <i class="bi bi-info-circle me-2"></i>
                    No sections available. Sections will appear once created by the Branch Admin.
                </div>
                <?php else: ?>
                <div class="section-chips">
                    <?php foreach ($class['sections'] as $section): ?>
                    <div class="section-chip" onclick="viewSectionStudents(<?php echo $section['id']; ?>, <?php echo $class['subject_id']; ?>)">
                        <div>
                            <div class="section-name"><?php echo htmlspecialchars($section['section_name']); ?></div>
                            <div class="section-info">
                                <?php echo $section['room'] ? htmlspecialchars($section['room']) : 'No Room'; ?>
                            </div>
                        </div>
                        <span class="student-count">
                            <i class="bi bi-people-fill me-1"></i><?php echo $section['student_count']; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php 
        $counter++;
    endforeach; 
    ?>
    
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function applyFilters() {
    const academicYear = document.getElementById('academicYearFilter').value;
    const semester = document.getElementById('semesterFilter').value;
    
    let url = 'subjects.php?academic_year=' + academicYear;
    if (semester) {
        url += '&semester=' + semester;
    }
    
    window.location.href = url;
}

function viewSubjectSections(subjectId) {
    window.location.href = 'subject_sections.php?subject_id=' + subjectId;
}

function viewSectionStudents(sectionId, subjectId) {
    window.location.href = 'section_students.php?section_id=' + sectionId + '&subject_id=' + subjectId;
}
</script>
</body>
</html>
