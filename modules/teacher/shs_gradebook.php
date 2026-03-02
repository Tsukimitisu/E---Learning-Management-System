<?php
/**
 * SHS Gradebook - Quarter-based Grading System
 * Supports: 1st Sem (Q1, Q2) and 2nd Sem (Q3, Q4)
 * Grades are whole numbers only (DepEd standard)
 */
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$selected_semester = (int)($_GET['semester'] ?? 1);
$selected_quarter = $_GET['quarter'] ?? '';
$teacher_id = $_SESSION['user_id'];

if (!in_array($selected_semester, [1, 2])) $selected_semester = 1;

// Map quarters to semester
$semester_quarters = [
    1 => ['q1' => '1st Quarter', 'q2' => '2nd Quarter'],
    2 => ['q3' => '3rd Quarter', 'q4' => '4th Quarter']
];

// Default to first quarter of the selected semester
if (empty($selected_quarter)) {
    $selected_quarter = $selected_semester == 1 ? 'q1' : 'q3';
}
$valid_quarters = array_keys($semester_quarters[$selected_semester]);
if (!in_array($selected_quarter, $valid_quarters)) {
    $selected_quarter = $valid_quarters[0];
}

$quarter_names = [
    'q1' => '1st Quarter', 'q2' => '2nd Quarter',
    'q3' => '3rd Quarter', 'q4' => '4th Quarter'
];

$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($section_id == 0 || $subject_id == 0) {
    header('Location: subjects.php');
    exit();
}

// Verify teacher is assigned
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) {
    header('Location: subjects.php');
    exit();
}

// Get section info
$section_query = $conn->prepare("
    SELECT s.*, 
           ss.strand_name, ss.strand_code,
           sgl.grade_level, sgl.grade_name,
           st.track_name
    FROM sections s
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
    LEFT JOIN shs_tracks st ON ss.track_id = st.id
    WHERE s.id = ?
");
$section_query->bind_param("i", $section_id);
$section_query->execute();
$section_info = $section_query->get_result()->fetch_assoc();

// Get subject info
$subject_query = $conn->prepare("SELECT * FROM curriculum_subjects WHERE id = ?");
$subject_query->bind_param("i", $subject_id);
$subject_query->execute();
$subject_info = $subject_query->get_result()->fetch_assoc();

// Get students with SHS grades
$has_sse = $conn->query("SHOW TABLES LIKE 'student_subject_enrollments'")->num_rows > 0;

if ($has_sse) {
    $students_q = $conn->prepare("
        SELECT 
            u.id as user_id,
            COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
            st.lrn,
            CONCAT(up.last_name, ', ', up.first_name) as student_name,
            COALESCE(st.student_type, 'regular') as student_type,
            sse.status as enrollment_status,
            sg.id as grade_id,
            sg.q1_grade, sg.q2_grade, sg.q3_grade, sg.q4_grade,
            sg.sem1_final_grade, sg.sem2_final_grade,
            sg.final_grade, sg.remarks, sg.notes, sg.version
        FROM student_subject_enrollments sse
        INNER JOIN users u ON sse.student_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN students st ON u.id = st.user_id
        LEFT JOIN shs_grades sg ON u.id = sg.student_id AND sg.section_id = ? AND sg.subject_id = ? AND sg.academic_year_id = ?
        WHERE sse.section_id = ? AND sse.subject_id = ? AND sse.academic_year_id = ? AND sse.status IN ('enrolled','credited')
        ORDER BY up.last_name, up.first_name
    ");
    $students_q->bind_param("iiiiii", $section_id, $subject_id, $current_ay_id, $section_id, $subject_id, $current_ay_id);
} else {
    $students_q = $conn->prepare("
        SELECT 
            u.id as user_id,
            COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
            st.lrn,
            CONCAT(up.last_name, ', ', up.first_name) as student_name,
            COALESCE(st.student_type, 'regular') as student_type,
            'enrolled' as enrollment_status,
            sg.id as grade_id,
            sg.q1_grade, sg.q2_grade, sg.q3_grade, sg.q4_grade,
            sg.sem1_final_grade, sg.sem2_final_grade,
            sg.final_grade, sg.remarks, sg.notes, sg.version
        FROM section_students ss
        INNER JOIN users u ON ss.student_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN students st ON u.id = st.user_id
        LEFT JOIN shs_grades sg ON u.id = sg.student_id AND sg.section_id = ? AND sg.subject_id = ? AND sg.academic_year_id = ?
        WHERE ss.section_id = ? AND ss.status = 'active'
        ORDER BY up.last_name, up.first_name
    ");
    $students_q->bind_param("iiii", $section_id, $subject_id, $current_ay_id, $section_id);
}
$students_q->execute();
$students_result = $students_q->get_result();

$export_password = strtoupper(substr(md5($subject_info['subject_code'] . $section_info['section_name'] . $current_ay_id), 0, 8));

$page_title = "SHS Gradebook - " . ($subject_info['subject_code'] ?? '');
include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/gradebook.css">
<style>
.quarter-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.quarter-tab { padding: 8px 20px; border-radius: 25px; border: 2px solid #dee2e6; background: #fff; color: #666; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; }
.quarter-tab:hover { border-color: var(--maroon); color: var(--maroon); }
.quarter-tab.active { background: linear-gradient(135deg, var(--maroon), #a00); color: #fff; border-color: var(--maroon); box-shadow: 0 3px 10px rgba(128,0,0,0.3); }
.semester-selector { min-width: 160px; }
.grade-input-shs { width: 80px; text-align: center; border: 2px solid #e9ecef; border-radius: 10px; padding: 8px; font-weight: 700; font-size: 1rem; transition: all 0.2s; }
.grade-input-shs:focus { border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(128,0,0,0.1); outline: none; }
.grade-input-shs.is-invalid { border-color: #dc3545; background: #fff5f5; }
.computed-grade { font-size: 1.1rem; font-weight: 800; }
.computed-grade.passed { color: #198754; }
.computed-grade.failed { color: #dc3545; }
.computed-grade.pending { color: #6c757d; }
.shs-info-banner { background: linear-gradient(135deg, #003366 0%, #001a33 100%); color: #fff; border-radius: 16px; padding: 18px 24px; margin-bottom: 20px; }
.remedial-badge { background: #fff3cd; color: #856404; border: 1px solid #ffc107; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-modern">
                    <li class="breadcrumb-item"><a href="subjects.php">My Classes</a></li>
                    <li class="breadcrumb-item"><a href="grading_sections.php?subject_id=<?php echo $subject_id; ?>"><?php echo htmlspecialchars($subject_info['subject_code'] ?? ''); ?></a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($section_info['section_name'] ?? ''); ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <?php echo htmlspecialchars($section_info['section_name'] ?? 'N/A'); ?>
                <span class="text-muted fw-light mx-2">|</span>
                <span style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($subject_info['subject_title'] ?? ''); ?></span>
                <span class="badge bg-warning text-dark ms-2">SHS</span>
            </h4>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <!-- Semester Selector -->
            <select class="form-select form-select-sm rounded-pill shadow-sm semester-selector" id="semesterSelector" onchange="changeSemester(this.value)">
                <option value="1" <?php echo $selected_semester == 1 ? 'selected' : ''; ?>>1st Semester</option>
                <option value="2" <?php echo $selected_semester == 2 ? 'selected' : ''; ?>>2nd Semester</option>
            </select>
            
            <button class="btn btn-save-all shadow-sm" onclick="saveAllSHSGrades()">
                <i class="bi bi-cloud-check me-2"></i> SAVE ALL
            </button>
            <a href="grading_sections.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
    </div>
    <!-- Quarter Tabs -->
    <div class="quarter-tabs mt-3">
        <?php foreach ($semester_quarters[$selected_semester] as $qKey => $qName): ?>
            <div class="quarter-tab <?php echo $selected_quarter == $qKey ? 'active' : ''; ?>" onclick="changeQuarter('<?php echo $qKey; ?>')">
                <?php echo $qName; ?>
            </div>
        <?php endforeach; ?>
        <div class="quarter-tab" style="background: #e3f2fd; color: #1565c0; border-color: #90caf9; cursor: default; font-size: 0.8rem;">
            <i class="bi bi-calculator me-1"></i> Sem <?php echo $selected_semester; ?> Final: Auto-computed
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    <div id="alertContainer"></div>

    <!-- SHS Info Banner -->
    <div class="shs-info-banner animate__animated animate__fadeIn">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-mortarboard-fill fs-4"></i>
                    <span class="fw-bold"><?php echo htmlspecialchars($section_info['strand_name'] ?? 'SHS'); ?></span>
                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($section_info['track_name'] ?? ''); ?></span>
                </div>
                <small class="opacity-75">
                    Grade <?php echo $section_info['grade_level'] ?? ''; ?> |
                    AY <?php echo ($current_ay['year_start'] ?? '') . '-' . ($current_ay['year_end'] ?? ''); ?> |
                    <?php echo $selected_semester == 1 ? '1st' : '2nd'; ?> Semester - <?php echo $quarter_names[$selected_quarter]; ?>
                </small>
            </div>
            <div class="text-end">
                <div class="small opacity-75">Grading Rule</div>
                <span class="badge bg-warning text-dark"><i class="bi bi-info-circle me-1"></i>Whole numbers only (no decimals)</span>
            </div>
        </div>
    </div>

    <!-- Gradebook Ledger -->
    <div class="ledger-card animate__animated animate__fadeInUp">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="min-width: 250px;">Student</th>
                        <th class="text-center" style="min-width: 100px;"><?php echo $quarter_names[$selected_quarter]; ?> Grade</th>
                        <?php if ($selected_semester == 1): ?>
                        <th class="text-center" style="min-width: 80px; font-size: 0.8rem;">Q1</th>
                        <th class="text-center" style="min-width: 80px; font-size: 0.8rem;">Q2</th>
                        <th class="text-center" style="min-width: 100px;">Sem 1 Final</th>
                        <?php else: ?>
                        <th class="text-center" style="min-width: 80px; font-size: 0.8rem;">Q3</th>
                        <th class="text-center" style="min-width: 80px; font-size: 0.8rem;">Q4</th>
                        <th class="text-center" style="min-width: 100px;">Sem 2 Final</th>
                        <?php endif; ?>
                        <th class="text-center" style="min-width: 100px;">Subject Final</th>
                        <th class="text-center" style="min-width: 120px;">Remarks</th>
                        <th class="text-center" style="min-width: 150px;">Notes</th>
                        <th class="text-center" style="min-width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    while ($student = $students_result->fetch_assoc()):
                        $is_credited = (($student['enrollment_status'] ?? '') === 'credited');
                        $q1 = $student['q1_grade'] ?? null;
                        $q2 = $student['q2_grade'] ?? null;
                        $q3 = $student['q3_grade'] ?? null;
                        $q4 = $student['q4_grade'] ?? null;
                        $sem1 = $student['sem1_final_grade'] ?? null;
                        $sem2 = $student['sem2_final_grade'] ?? null;
                        $final = $student['final_grade'] ?? null;
                        $remarks = $student['remarks'] ?? '';
                        
                        // Current quarter grade
                        $current_q_grade = $student[$selected_quarter . '_grade'] ?? null;
                        
                        // Determine remarks display
                        $remarks_display = 'PENDING';
                        $remarks_class = 'bg-secondary';
                        if ($remarks === 'passed') { $remarks_display = 'PASSED'; $remarks_class = 'bg-success'; }
                        elseif ($remarks === 'failed') { $remarks_display = 'FAILED'; $remarks_class = 'bg-danger'; }
                        elseif ($remarks === 'with_remedial') { $remarks_display = 'WITH REMEDIAL'; $remarks_class = 'bg-warning text-dark'; }
                        elseif ($remarks === 'incomplete') { $remarks_display = 'INCOMPLETE'; $remarks_class = 'bg-info'; }
                    ?>
                    <tr data-student-id="<?php echo $student['user_id']; ?>" 
                        data-grade-id="<?php echo $student['grade_id'] ?? 0; ?>" 
                        data-version="<?php echo $student['version'] ?? 0; ?>"
                        data-q1="<?php echo $q1 ?? ''; ?>"
                        data-q2="<?php echo $q2 ?? ''; ?>"
                        data-q3="<?php echo $q3 ?? ''; ?>"
                        data-q4="<?php echo $q4 ?? ''; ?>"
                        <?php echo $is_credited ? 'data-credited="1"' : ''; ?>>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="badge bg-light text-dark rounded-pill"><?php echo $counter; ?></span>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($student['student_name']); ?>
                                        <?php if ($is_credited): ?>
                                            <span class="badge bg-info text-white ms-2"><i class="bi bi-patch-check-fill me-1"></i>Credited</span>
                                        <?php elseif ($student['student_type'] !== 'regular'): ?>
                                            <span class="badge bg-warning text-dark ms-2"><?php echo ucfirst($student['student_type']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted student-no"><?php echo htmlspecialchars($student['student_no']); ?></small>
                                    <?php if (!empty($student['lrn'])): ?>
                                        <small class="text-primary d-block" style="font-size: 0.7rem;">LRN: <?php echo htmlspecialchars($student['lrn']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php if ($is_credited): ?>
                        <td class="text-center" colspan="7">
                            <span class="badge bg-info bg-opacity-10 text-info border border-info px-4 py-2">
                                <i class="bi bi-patch-check-fill me-1"></i> Credited — Not Gradable
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-save-row save-grade-btn" disabled><i class="bi bi-lock-fill me-1"></i> N/A</button>
                        </td>
                        <?php else: ?>
                        <!-- Quarter Grade Input -->
                        <td class="text-center">
                            <input type="number" class="grade-input-shs quarter-grade-input"
                                   value="<?php echo $current_q_grade !== null ? (int)$current_q_grade : ''; ?>"
                                   min="0" max="100" step="1" placeholder="0"
                                   oninput="validateWholeNumber(this); recalcSHSRow(this.closest('tr'))">
                        </td>
                        <!-- Q1/Q3 and Q2/Q4 display -->
                        <?php if ($selected_semester == 1): ?>
                        <td class="text-center"><span class="badge bg-light text-dark border q-display" data-q="q1"><?php echo $q1 !== null ? (int)$q1 : '-'; ?></span></td>
                        <td class="text-center"><span class="badge bg-light text-dark border q-display" data-q="q2"><?php echo $q2 !== null ? (int)$q2 : '-'; ?></span></td>
                        <td class="text-center">
                            <span class="computed-grade sem-final <?php echo $sem1 !== null ? ($sem1 >= 75 ? 'passed' : 'failed') : 'pending'; ?>">
                                <?php echo $sem1 !== null ? (int)$sem1 : '-'; ?>
                            </span>
                        </td>
                        <?php else: ?>
                        <td class="text-center"><span class="badge bg-light text-dark border q-display" data-q="q3"><?php echo $q3 !== null ? (int)$q3 : '-'; ?></span></td>
                        <td class="text-center"><span class="badge bg-light text-dark border q-display" data-q="q4"><?php echo $q4 !== null ? (int)$q4 : '-'; ?></span></td>
                        <td class="text-center">
                            <span class="computed-grade sem-final <?php echo $sem2 !== null ? ($sem2 >= 75 ? 'passed' : 'failed') : 'pending'; ?>">
                                <?php echo $sem2 !== null ? (int)$sem2 : '-'; ?>
                            </span>
                        </td>
                        <?php endif; ?>
                        <!-- Subject Final -->
                        <td class="text-center">
                            <span class="computed-grade subject-final <?php echo $final !== null ? ($final >= 75 ? 'passed' : 'failed') : 'pending'; ?>">
                                <?php echo $final !== null ? (int)$final : '-'; ?>
                            </span>
                        </td>
                        <!-- Remarks -->
                        <td class="text-center">
                            <span class="badge remarks-badge rounded-pill px-3 py-2 <?php echo $remarks_class; ?>">
                                <?php echo $remarks_display; ?>
                            </span>
                        </td>
                        <!-- Notes -->
                        <td class="text-center">
                            <input type="text" class="notes-input shadow-sm" value="<?php echo htmlspecialchars($student['notes'] ?? ''); ?>" placeholder="Notes...">
                        </td>
                        <!-- Save -->
                        <td class="text-center">
                            <button class="btn btn-save-row save-grade-btn" title="Save" onclick="saveSHSGrade(this.closest('tr'), this)">
                                <i class="bi bi-check2 me-1"></i> SAVE
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php $counter++; endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const SECTION_ID = <?php echo $section_id; ?>;
const SUBJECT_ID = <?php echo $subject_id; ?>;
const SELECTED_SEMESTER = <?php echo $selected_semester; ?>;
const SELECTED_QUARTER = '<?php echo $selected_quarter; ?>';
const ACADEMIC_YEAR_ID = <?php echo $current_ay_id; ?>;

function changeSemester(sem) {
    const url = new URL(window.location.href);
    url.searchParams.set('semester', sem);
    url.searchParams.delete('quarter');
    window.location.href = url.toString();
}

function changeQuarter(q) {
    // AJAX-based quarter switching (no page reload)
    const url = new URL(window.location.href);
    url.searchParams.set('quarter', q);
    
    // Update active tab immediately
    document.querySelectorAll('.quarter-tab').forEach(tab => tab.classList.remove('active'));
    event.target.closest('.quarter-tab').classList.add('active');
    
    // Fetch new page content and swap the table body
    fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Swap the table
            const newTable = doc.querySelector('.ledger-card');
            const currentTable = document.querySelector('.ledger-card');
            if (newTable && currentTable) {
                currentTable.innerHTML = newTable.innerHTML;
            }
            
            // Swap the info banner
            const newBanner = doc.querySelector('.shs-info-banner');
            const currentBanner = document.querySelector('.shs-info-banner');
            if (newBanner && currentBanner) {
                currentBanner.innerHTML = newBanner.innerHTML;
            }
            
            // Update URL without reload
            window.history.replaceState({}, '', url.toString());
            
            // Update the JS quarter constant
            window.SELECTED_QUARTER_DYNAMIC = q;
        })
        .catch(() => {
            // Fallback to full reload on error
            window.location.href = url.toString();
        });
}

// Override SELECTED_QUARTER getter for dynamic switching
Object.defineProperty(window, '_SELECTED_QUARTER', {
    get: function() { return window.SELECTED_QUARTER_DYNAMIC || SELECTED_QUARTER; }
});

// Helper to get current quarter (supports dynamic switching)
function getCurrentQuarter() {
    return window.SELECTED_QUARTER_DYNAMIC || SELECTED_QUARTER;
}

function validateWholeNumber(input) {
    // Remove decimals - whole numbers only
    let val = input.value;
    if (val.includes('.') || val.includes(',')) {
        val = Math.round(parseFloat(val) || 0).toString();
        input.value = val;
        input.classList.add('is-invalid');
        showAlert('Grades must be whole numbers only. Decimal values are not allowed.', 'warning');
        setTimeout(() => input.classList.remove('is-invalid'), 2000);
        return;
    }
    let num = parseInt(val, 10);
    if (isNaN(num)) return;
    if (num < 0) input.value = 0;
    if (num > 100) input.value = 100;
}

function recalcSHSRow(row) {
    if (row.dataset.credited === '1') return;
    
    const currentGrade = parseInt(row.querySelector('.quarter-grade-input').value) || null;
    
    // Update the stored quarter data
    let q1 = row.dataset.q1 !== '' ? parseInt(row.dataset.q1) : null;
    let q2 = row.dataset.q2 !== '' ? parseInt(row.dataset.q2) : null;
    let q3 = row.dataset.q3 !== '' ? parseInt(row.dataset.q3) : null;
    let q4 = row.dataset.q4 !== '' ? parseInt(row.dataset.q4) : null;
    
    // Update current quarter
    switch(getCurrentQuarter()) {
        case 'q1': q1 = currentGrade; break;
        case 'q2': q2 = currentGrade; break;
        case 'q3': q3 = currentGrade; break;
        case 'q4': q4 = currentGrade; break;
    }
    
    // Store back
    row.dataset.q1 = q1 !== null ? q1 : '';
    row.dataset.q2 = q2 !== null ? q2 : '';
    row.dataset.q3 = q3 !== null ? q3 : '';
    row.dataset.q4 = q4 !== null ? q4 : '';
    
    // Update Q display badges
    row.querySelectorAll('.q-display').forEach(el => {
        const q = el.dataset.q;
        const val = {q1, q2, q3, q4}[q];
        el.textContent = val !== null ? val : '-';
    });
    
    // Compute semester finals (rounded to nearest whole number)
    let sem1Final = null, sem2Final = null;
    if (q1 !== null && q2 !== null) sem1Final = Math.round((q1 + q2) / 2);
    if (q3 !== null && q4 !== null) sem2Final = Math.round((q3 + q4) / 2);
    
    // Update semester final display
    const semFinalEl = row.querySelector('.sem-final');
    if (semFinalEl) {
        const semVal = SELECTED_SEMESTER === 1 ? sem1Final : sem2Final;
        semFinalEl.textContent = semVal !== null ? semVal : '-';
        semFinalEl.className = 'computed-grade sem-final ' + (semVal !== null ? (semVal >= 75 ? 'passed' : 'failed') : 'pending');
    }
    
    // Compute subject final grade
    let finalGrade = null;
    if (sem1Final !== null && sem2Final !== null) {
        finalGrade = Math.round((sem1Final + sem2Final) / 2);
    } else if (sem1Final !== null) {
        finalGrade = sem1Final;
    } else if (sem2Final !== null) {
        finalGrade = sem2Final;
    }
    
    const subjectFinalEl = row.querySelector('.subject-final');
    if (subjectFinalEl) {
        subjectFinalEl.textContent = finalGrade !== null ? finalGrade : '-';
        subjectFinalEl.className = 'computed-grade subject-final ' + (finalGrade !== null ? (finalGrade >= 75 ? 'passed' : 'failed') : 'pending');
    }
    
    // Update remarks
    const remarksBadge = row.querySelector('.remarks-badge');
    if (remarksBadge && finalGrade !== null) {
        if (finalGrade >= 75) {
            remarksBadge.textContent = 'PASSED';
            remarksBadge.className = 'badge remarks-badge rounded-pill px-3 py-2 bg-success';
        } else if (finalGrade >= 70) {
            remarksBadge.textContent = 'WITH REMEDIAL';
            remarksBadge.className = 'badge remarks-badge rounded-pill px-3 py-2 bg-warning text-dark';
        } else {
            remarksBadge.textContent = 'FAILED';
            remarksBadge.className = 'badge remarks-badge rounded-pill px-3 py-2 bg-danger';
        }
    } else if (remarksBadge) {
        remarksBadge.textContent = 'PENDING';
        remarksBadge.className = 'badge remarks-badge rounded-pill px-3 py-2 bg-secondary';
    }
}

async function saveSHSGrade(row, btn) {
    if (row.dataset.credited === '1') return true;
    
    const studentId = row.dataset.studentId;
    const gradeId = row.dataset.gradeId || 0;
    const version = parseInt(row.dataset.version || '0', 10);
    
    const gradeInput = row.querySelector('.quarter-grade-input');
    const currentGrade = gradeInput.value.trim();
    
    // Validate whole number
    if (currentGrade !== '' && (currentGrade.includes('.') || currentGrade.includes(','))) {
        showAlert('Grades must be whole numbers only. Decimals are not allowed.', 'danger');
        return false;
    }
    
    const gradeVal = currentGrade !== '' ? parseInt(currentGrade, 10) : null;
    if (gradeVal !== null && (gradeVal < 0 || gradeVal > 100)) {
        showAlert('Grade must be between 0 and 100.', 'danger');
        return false;
    }
    
    const notes = row.querySelector('.notes-input')?.value || '';
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    // Build all quarter values
    let q1 = row.dataset.q1 !== '' ? parseInt(row.dataset.q1) : null;
    let q2 = row.dataset.q2 !== '' ? parseInt(row.dataset.q2) : null;
    let q3 = row.dataset.q3 !== '' ? parseInt(row.dataset.q3) : null;
    let q4 = row.dataset.q4 !== '' ? parseInt(row.dataset.q4) : null;
    
    // Update current quarter
    switch(getCurrentQuarter()) {
        case 'q1': q1 = gradeVal; break;
        case 'q2': q2 = gradeVal; break;
        case 'q3': q3 = gradeVal; break;
        case 'q4': q4 = gradeVal; break;
    }
    
    // Store updated values
    row.dataset.q1 = q1 !== null ? q1 : '';
    row.dataset.q2 = q2 !== null ? q2 : '';
    row.dataset.q3 = q3 !== null ? q3 : '';
    row.dataset.q4 = q4 !== null ? q4 : '';
    
    const fd = new FormData();
    fd.append('student_id', studentId);
    fd.append('section_id', SECTION_ID);
    fd.append('subject_id', SUBJECT_ID);
    fd.append('grade_id', gradeId);
    fd.append('version', version);
    fd.append('semester', SELECTED_SEMESTER);
    fd.append('quarter', getCurrentQuarter());
    fd.append('q1_grade', q1 !== null ? q1 : '');
    fd.append('q2_grade', q2 !== null ? q2 : '');
    fd.append('q3_grade', q3 !== null ? q3 : '');
    fd.append('q4_grade', q4 !== null ? q4 : '');
    fd.append('notes', notes);
    
    try {
        const response = await fetch('api/update_shs_grade.php', { method: 'POST', body: fd });
        const data = await response.json();
        
        if (data.status === 'success') {
            if (data.grade_id) row.dataset.gradeId = data.grade_id;
            if (data.version) row.dataset.version = data.version;
            
            // Update computed values from server
            if (data.sem1_final !== undefined) {
                row.dataset.q1 = data.q1 !== null ? data.q1 : '';
                row.dataset.q2 = data.q2 !== null ? data.q2 : '';
                row.dataset.q3 = data.q3 !== null ? data.q3 : '';
                row.dataset.q4 = data.q4 !== null ? data.q4 : '';
            }
            recalcSHSRow(row);
            
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-save-row');
            setTimeout(() => { btn.innerHTML = originalText; btn.classList.remove('btn-success'); btn.classList.add('btn-save-row'); btn.disabled = false; }, 1500);
            return true;
        } else {
            throw new Error(data.message || 'Failed to save');
        }
    } catch (error) {
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
        btn.classList.add('btn-danger');
        setTimeout(() => { btn.innerHTML = originalText; btn.classList.remove('btn-danger'); btn.disabled = false; }, 1500);
        showAlert(error.message || 'Failed to save grade', 'danger');
        return false;
    }
}

async function saveAllSHSGrades() {
    const rows = document.querySelectorAll('tbody tr:not([data-credited="1"])');
    const saveBtn = document.querySelector('.btn-save-all');
    const originalText = saveBtn.innerHTML;
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';
    
    let saved = 0, failed = 0;
    
    for (const row of rows) {
        const btn = row.querySelector('.save-grade-btn');
        if (!btn || btn.disabled) continue;
        const success = await saveSHSGrade(row, btn);
        if (success) saved++; else failed++;
    }
    
    let message = `Saved ${saved} student grades for ${getCurrentQuarter().toUpperCase()}.`;
    if (failed > 0) message += ` ${failed} failed.`;
    showAlert(message, failed > 0 ? 'warning' : 'success');
    
    saveBtn.disabled = false;
    saveBtn.innerHTML = originalText;
}

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
}
</script>

<?php include '../../includes/footer.php'; ?>
