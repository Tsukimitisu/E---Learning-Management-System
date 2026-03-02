<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

// Compatibility guard for student type label support.
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular' AFTER course_id");
$conn->query("ALTER TABLE students MODIFY COLUMN student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS previous_school VARCHAR(255) DEFAULT NULL AFTER student_type");

$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$selected_term = $_GET['term'] ?? 'prelim'; // Default to prelim
$teacher_id = $_SESSION['user_id'];

// Valid terms
$valid_terms = ['prelim', 'midterm', 'prefinal', 'final'];
if (!in_array($selected_term, $valid_terms)) {
    $selected_term = 'prelim';
}

// Term display names
$term_names = [
    'prelim' => 'Prelim',
    'midterm' => 'Midterm', 
    'prefinal' => 'Pre-Finals',
    'final' => 'Finals'
];

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($section_id == 0 || $subject_id == 0) {
    header('Location: subjects.php');
    exit();
}

/** 
 * BACKEND LOGIC - Using new section/subject structure
 */
// Verify teacher is assigned to this subject
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0) {
    header('Location: subjects.php');
    exit();
}

// Get section info
$section_query = $conn->prepare("
    SELECT s.*, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(pyl.year_name, CONCAT('Grade ', sgl.grade_level)) as year_level_name
    FROM sections s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
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

// Determine if this is a College or SHS subject
$is_college = !empty($subject_info['program_id']);
$is_shs = !empty($subject_info['shs_strand_id']) || !empty($subject_info['shs_grade_level_id']);

// Redirect SHS subjects to the dedicated SHS gradebook
if ($is_shs) {
    header('Location: shs_gradebook.php?section_id=' . $section_id . '&subject_id=' . $subject_id);
    exit();
}

// Combine for compatibility with old template
$class_info = [
    'section_name' => $section_info['section_name'],
    'subject_code' => $subject_info['subject_code'],
    'subject_title' => $subject_info['subject_title'],
    'units' => $subject_info['units'],
    'program_name' => $section_info['program_name'],
    'year_level_name' => $section_info['year_level_name'],
    'track_name' => null,
    'written_work_weight' => 30,
    'performance_task_weight' => 50,
    'quarterly_exam_weight' => 20,
    'is_college' => $is_college,
    'is_shs' => $is_shs
];

// Get students from subject-level enrollment when available (fallback to section roster)
$has_subject_enrollment_table = false;
$check_table = $conn->query("SHOW TABLES LIKE 'student_subject_enrollments'");
if ($check_table && $check_table->num_rows > 0) {
    $has_subject_enrollment_table = true;
}

$use_subject_roster = false;
if ($has_subject_enrollment_table) {
    $use_subject_roster = true;
}

if ($use_subject_roster) {
    $students = $conn->prepare("
        SELECT 
            u.id as user_id,
            COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
            CONCAT(up.last_name, ', ', up.first_name) as student_name,
            CASE
                WHEN COALESCE(st.student_type, 'regular') = 'regular' THEN 'regular'
                WHEN st.student_type = 'transferee' THEN 'transferee'
                ELSE 'irregular'
            END as student_type,
            sse.status as enrollment_status,
            g.id as grade_id,
            g.prelim,
            g.midterm,
            g.prefinal,
            g.final,
            g.final_grade,
            g.remarks,
            g.notes,
            g.version
        FROM student_subject_enrollments sse
        INNER JOIN users u ON sse.student_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN students st ON u.id = st.user_id
        LEFT JOIN grades g ON u.id = g.student_id AND g.section_id = ? AND g.subject_id = ?
        WHERE sse.section_id = ? AND sse.subject_id = ? AND sse.academic_year_id = ? AND sse.status IN ('enrolled','credited')
        ORDER BY up.last_name, up.first_name
    ");
    $students->bind_param("iiiii", $section_id, $subject_id, $section_id, $subject_id, $current_ay_id);
} else {
    $students = $conn->prepare("
        SELECT 
            u.id as user_id,
            COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
            CONCAT(up.last_name, ', ', up.first_name) as student_name,
            CASE
                WHEN COALESCE(st.student_type, 'regular') = 'regular' THEN 'regular'
                WHEN st.student_type = 'transferee' THEN 'transferee'
                ELSE 'irregular'
            END as student_type,
            g.id as grade_id,
            g.prelim,
            g.midterm,
            g.prefinal,
            g.final,
            g.final_grade,
            g.remarks,
            g.notes,
            g.version
        FROM section_students ss
        INNER JOIN users u ON ss.student_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN students st ON u.id = st.user_id
        LEFT JOIN grades g ON u.id = g.student_id AND g.section_id = ? AND g.subject_id = ?
        WHERE ss.section_id = ? AND ss.status = 'active'
        ORDER BY up.last_name, up.first_name
    ");
    $students->bind_param("iii", $section_id, $subject_id, $section_id);
}
$students->execute();
$students = $students->get_result();

// Generate export password based on subject and section
$export_password = strtoupper(substr(md5($subject_info['subject_code'] . $section_info['section_name'] . $current_ay_id), 0, 8));

$page_title = "Gradebook - " . $class_info['subject_code'];
include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/gradebook.css">
<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-modern">
                    <li class="breadcrumb-item"><a href="subjects.php">My Classes</a></li>
                    <li class="breadcrumb-item"><a href="grading_sections.php?subject_id=<?php echo $subject_id; ?>"><?php echo htmlspecialchars($class_info['subject_code']); ?></a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($class_info['section_name']); ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <?php echo htmlspecialchars($class_info['section_name'] ?: 'N/A'); ?> <span class="text-muted fw-light mx-2">|</span> <span style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($class_info['subject_title']); ?></span>
            </h4>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <!-- Term Filter Dropdown -->
            <select class="form-select form-select-sm rounded-pill shadow-sm term-filter" id="termFilter" style="width: auto; min-width: 150px; font-weight: 600;" onchange="changeTerm(this.value)">
                <option value="prelim" <?php echo $selected_term == 'prelim' ? 'selected' : ''; ?>>📋 Prelim</option>
                <option value="midterm" <?php echo $selected_term == 'midterm' ? 'selected' : ''; ?>>📋 Midterm</option>
                <option value="prefinal" <?php echo $selected_term == 'prefinal' ? 'selected' : ''; ?>>📋 Pre-Finals</option>
                <option value="final" <?php echo $selected_term == 'final' ? 'selected' : ''; ?>>📋 Finals</option>
            </select>
            <!-- Export Dropdown -->
            <div class="dropdown">
                <button class="btn btn-export btn-sm px-4 rounded-pill shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i> EXPORT
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <li><h6 class="dropdown-header"><i class="bi bi-file-earmark-pdf me-2"></i>PDF Format</h6></li>
                    <li><a class="dropdown-item" href="#" onclick="exportToPDF(); return false;"><i class="bi bi-file-pdf text-danger me-2"></i>Export as PDF</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header"><i class="bi bi-file-earmark-excel me-2"></i>Excel Format</h6></li>
                    <li><a class="dropdown-item" href="#" onclick="exportToExcel(true); return false;"><i class="bi bi-pencil-square text-primary me-2"></i>Excel (Editable)</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportToExcel(false); return false;"><i class="bi bi-lock text-warning me-2"></i>Excel (Protected)</a></li>
                </ul>
            </div>
            <button class="btn btn-import btn-sm px-4 rounded-pill shadow-sm" onclick="document.getElementById('importFile').click()" title="Import from Excel">
                <i class="bi bi-upload me-1"></i> IMPORT
            </button>
            <input type="file" id="importFile" accept=".xlsx,.xls" style="display:none" onchange="importGrades(this)">
            <button class="btn btn-save-all shadow-sm" onclick="saveAllGrades()">
                <i class="bi bi-cloud-check me-2"></i> SAVE ALL
            </button>
            <a href="grading_sections.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>
    
    <!-- Export Info Banner -->
    <div class="password-info animate__animated animate__fadeIn">
        <div class="d-flex align-items-center">
            <i class="bi bi-file-earmark-arrow-down-fill fs-4 me-3 text-primary"></i>
            <div>
                <span class="fw-bold text-dark">Export & Import Options</span>
                <div class="small text-muted">
                    <i class="bi bi-download me-1"></i> <strong>Export:</strong> Choose PDF or Excel (Editable / Protected with password: <code class="bg-white px-2 py-1 rounded border"><?php echo $export_password; ?></code>)
                    <br>
                    <i class="bi bi-upload me-1"></i> <strong>Import:</strong> Upload Excel file - format is validated automatically
                </div>
            </div>
        </div>
    </div>

    <?php if ($class_info['track_name']): ?>
    <div class="track-info-banner animate__animated animate__fadeIn">
        <div class="d-flex align-items-center">
            <i class="bi bi-info-circle-fill fs-4 me-3 text-blue"></i>
            <div>
                <span class="fw-bold text-blue">SHS TRACK: <?php echo htmlspecialchars($class_info['track_name']); ?></span>
                <div class="small text-muted">
                    Weights: Written (<?php echo $class_info['written_work_weight']; ?>%) • 
                    Performance (<?php echo $class_info['performance_task_weight']; ?>%) • 
                    Exam (<?php echo $class_info['quarterly_exam_weight']; ?>%)
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Gradebook Ledger -->
    <div class="ledger-card animate__animated animate__fadeInUp">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="min-width: 250px;">Names</th>
                        <th class="text-center" style="min-width: 120px;">Average <span class="badge bg-primary rounded-pill ms-1 term-label"><?php echo $term_names[$selected_term]; ?></span></th>
                        <th class="text-center" style="min-width: 140px;">Rating</th>
                        <th class="text-center" style="min-width: 120px;">Remarks</th>
                        <th class="text-center" style="min-width: 180px;">Notes</th>
                        <th class="text-center" style="min-width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    while ($student = $students->fetch_assoc()): 
                        $avg = $student['final_grade'] ?? 0;
                        
                        // Determine rating based on average - different for College vs SHS
                        if ($class_info['is_college']) {
                            // Philippine College Grading Scale
                            if ($avg >= 97) {
                                $rating = '1.00';
                                $rating_class = 'rating-college rating-1-00';
                            } elseif ($avg >= 94) {
                                $rating = '1.25';
                                $rating_class = 'rating-college rating-1-25';
                            } elseif ($avg >= 91) {
                                $rating = '1.50';
                                $rating_class = 'rating-college rating-1-50';
                            } elseif ($avg >= 88) {
                                $rating = '1.75';
                                $rating_class = 'rating-college rating-1-75';
                            } elseif ($avg >= 85) {
                                $rating = '2.00';
                                $rating_class = 'rating-college rating-2-00';
                            } elseif ($avg >= 82) {
                                $rating = '2.25';
                                $rating_class = 'rating-college rating-2-25';
                            } elseif ($avg >= 79) {
                                $rating = '2.50';
                                $rating_class = 'rating-college rating-2-50';
                            } elseif ($avg >= 76) {
                                $rating = '2.75';
                                $rating_class = 'rating-college rating-2-75';
                            } elseif ($avg >= 75) {
                                $rating = '3.00';
                                $rating_class = 'rating-college rating-3-00';
                            } elseif ($avg > 0) {
                                $rating = '5.00';
                                $rating_class = 'rating-college rating-5-00';
                            } else {
                                $rating = 'N/A';
                                $rating_class = 'rating-na';
                            }
                        } else {
                            // SHS Descriptive Rating
                            if ($avg >= 95) {
                                $rating = 'Excellent';
                                $rating_class = 'rating-excellent';
                            } elseif ($avg >= 90) {
                                $rating = 'Very Good';
                                $rating_class = 'rating-very-good';
                            } elseif ($avg >= 85) {
                                $rating = 'Good';
                                $rating_class = 'rating-good';
                            } elseif ($avg >= 75) {
                                $rating = 'Satisfactory';
                                $rating_class = 'rating-satisfactory';
                            } elseif ($avg > 0) {
                                $rating = 'Needs Improvement';
                                $rating_class = 'rating-needs-improvement';
                            } else {
                                $rating = 'N/A';
                                $rating_class = 'rating-na';
                            }
                        }
                    ?>
                    <?php 
                        // Determine remarks based on average
                        $remarks_display = 'N/A';
                        $remarks_class = 'bg-secondary';
                        if ($avg >= 75) {
                            $remarks_display = 'PASSED';
                            $remarks_class = 'bg-success';
                        } elseif ($avg > 0) {
                            $remarks_display = 'FAILED';
                            $remarks_class = 'bg-danger';
                        }
                    ?>
                    <?php $is_credited = (($student['enrollment_status'] ?? '') === 'credited'); ?>
                    <tr data-student-id="<?php echo $student['user_id']; ?>" data-grade-id="<?php echo $student['grade_id'] ?? 0; ?>" data-version="<?php echo $student['version'] ?? 0; ?>"<?php echo $is_credited ? ' data-credited="1"' : ''; ?>>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="badge bg-light text-dark rounded-pill"><?php echo $counter; ?></span>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($student['student_name']); ?>
                                        <?php if ($is_credited): ?>
                                            <span class="badge bg-info text-white ms-2"><i class="bi bi-patch-check-fill me-1"></i>Credited Subject</span>
                                        <?php elseif (($student['student_type'] ?? 'regular') !== 'regular'): ?>
                                            <span class="badge bg-warning text-dark ms-2"><?php echo ucfirst($student['student_type'] ?? 'irregular'); ?> Student</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted student-no"><?php echo htmlspecialchars($student['student_no']); ?></small>
                                </div>
                            </div>
                        </td>
                        <?php if ($is_credited): ?>
                        <td class="text-center" colspan="4">
                            <span class="badge bg-info bg-opacity-10 text-info border border-info px-4 py-2">
                                <i class="bi bi-patch-check-fill me-1"></i> Credited — Not Gradable
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-save-row save-grade-btn" disabled title="Credited Subject">
                                <i class="bi bi-lock-fill me-1"></i> N/A
                            </button>
                        </td>
                        <?php else: ?>
                        <td class="text-center">
                            <?php 
                                // Get grade value for selected term
                                $term_grade = $student[$selected_term] ?? 0;
                            ?>
                            <input type="number" class="grade-input term-grade-input shadow-sm" 
                                   value="<?php echo $term_grade ? number_format($term_grade, 2) : ''; ?>" 
                                   min="0" max="100" step="0.01" placeholder="0.00"
                                   data-prelim="<?php echo $student['prelim'] ?? 0; ?>"
                                   data-midterm="<?php echo $student['midterm'] ?? 0; ?>"
                                   data-prefinal="<?php echo $student['prefinal'] ?? 0; ?>"
                                   data-final="<?php echo $student['final'] ?? 0; ?>">
                        </td>
                        <td class="text-center">
                            <span class="rating-badge <?php echo $rating_class; ?>"><?php echo $rating; ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge remarks-badge rounded-pill px-3 py-2 <?php echo $remarks_class; ?>">
                                <?php echo $remarks_display; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <input type="text" class="notes-input shadow-sm" 
                                   value="<?php echo htmlspecialchars($student['notes'] ?? ''); ?>" 
                                   placeholder="Notes...">
                        </td>
                        <td class="text-center">
                            <button class="btn btn-save-row save-grade-btn" title="Save">
                                <i class="bi bi-check2 me-1"></i> SAVE
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php 
                        $counter++;
                        endwhile; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SheetJS Style Library for Excel Import/Export with formatting -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js" onerror="console.error('Failed to load XLSX library')"></script>

<!-- --- JAVASCRIPT LOGIC - Updated for term-based grading with dropdown --- -->
<script>
const SECTION_ID = <?php echo $section_id; ?>;
const SUBJECT_ID = <?php echo $subject_id; ?>;
const EXPORT_PASSWORD = '<?php echo $export_password; ?>';
const IS_COLLEGE = <?php echo $class_info['is_college'] ? 'true' : 'false'; ?>;
const SELECTED_TERM = '<?php echo $selected_term; ?>';
const TERM_NAMES = {
    'prelim': 'Prelim',
    'midterm': 'Midterm',
    'prefinal': 'Pre-Finals',
    'final': 'Finals'
};

// Term change function - AJAX-based (no page reload)
function changeTerm(term) {
    console.log('changeTerm called with:', term);
    const url = new URL(window.location.href);
    url.searchParams.set('term', term);
    
    // Fetch new content via AJAX and swap the table + header
    fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Swap ledger card (grades table)
            const newTable = doc.querySelector('.ledger-card');
            const currentTable = document.querySelector('.ledger-card');
            if (newTable && currentTable) {
                currentTable.innerHTML = newTable.innerHTML;
                // Re-attach input and button event listeners
                document.querySelectorAll('.term-grade-input').forEach(input => {
                    input.addEventListener('input', function() {
                        const row = this.closest('tr');
                        const grade = parseFloat(this.value) || 0;
                        updateRatingAndRemarks(row, grade);
                    });
                });
                document.querySelectorAll('.save-grade-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const row = this.closest('tr');
                        saveGrade(row, this);
                    });
                });
            }
            
            // Update URL without reload
            window.history.replaceState({}, '', url.toString());
            
            // Update the JS term constant dynamically
            window.SELECTED_TERM_DYNAMIC = term;
        })
        .catch(err => {
            console.error('AJAX term switch failed:', err);
            // Fallback to full reload
            window.location.href = url.toString();
        });
}

// Helper to get current term (supports dynamic switching)
function getSelectedTerm() {
    return window.SELECTED_TERM_DYNAMIC || SELECTED_TERM;
}

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - Gradebook JS initialized');
    console.log('XLSX library loaded:', typeof XLSX !== 'undefined');
    
    // Update rating and remarks when grade changes
    document.querySelectorAll('.term-grade-input').forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const grade = parseFloat(this.value) || 0;
            updateRatingAndRemarks(row, grade);
        });
    });

    // Save individual grade
    document.querySelectorAll('.save-grade-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            saveGrade(row, this);
        });
    });
});

function getRating(average) {
    if (IS_COLLEGE) {
        // Philippine College Grading Scale
        if (average >= 97) return { text: '1.00', class: 'rating-college rating-1-00' };
        if (average >= 94) return { text: '1.25', class: 'rating-college rating-1-25' };
        if (average >= 91) return { text: '1.50', class: 'rating-college rating-1-50' };
        if (average >= 88) return { text: '1.75', class: 'rating-college rating-1-75' };
        if (average >= 85) return { text: '2.00', class: 'rating-college rating-2-00' };
        if (average >= 82) return { text: '2.25', class: 'rating-college rating-2-25' };
        if (average >= 79) return { text: '2.50', class: 'rating-college rating-2-50' };
        if (average >= 76) return { text: '2.75', class: 'rating-college rating-2-75' };
        if (average >= 75) return { text: '3.00', class: 'rating-college rating-3-00' };
        if (average > 0) return { text: '5.00', class: 'rating-college rating-5-00' };
        return { text: 'N/A', class: 'rating-na' };
    } else {
        // SHS Descriptive Rating
        if (average >= 95) return { text: 'Excellent', class: 'rating-excellent' };
        if (average >= 90) return { text: 'Very Good', class: 'rating-very-good' };
        if (average >= 85) return { text: 'Good', class: 'rating-good' };
        if (average >= 75) return { text: 'Satisfactory', class: 'rating-satisfactory' };
        if (average > 0) return { text: 'Needs Improvement', class: 'rating-needs-improvement' };
        return { text: 'N/A', class: 'rating-na' };
    }
}

function updateRatingAndRemarks(row, grade) {
    if (grade === undefined) {
        grade = parseFloat(row.querySelector('.term-grade-input').value) || 0;
    }
    const rating = getRating(grade);
    const remarks = grade >= 75 ? 'PASSED' : (grade > 0 ? 'FAILED' : 'N/A');
    
    // Update rating badge
    const ratingBadge = row.querySelector('.rating-badge');
    ratingBadge.textContent = rating.text;
    ratingBadge.className = 'rating-badge ' + rating.class;
    
    // Update remarks badge
    const remarksBadge = row.querySelector('.remarks-badge');
    if (remarksBadge) {
        remarksBadge.textContent = remarks;
        remarksBadge.className = 'badge remarks-badge rounded-pill px-3 py-2 ' + 
            (remarks === 'PASSED' ? 'bg-success' : (remarks === 'FAILED' ? 'bg-danger' : 'bg-secondary'));
    }
}

async function saveGrade(row, btn) {
    // Skip credited students
    if (row.dataset.credited === '1') return true;

    const studentId = row.dataset.studentId;
    const gradeId = row.dataset.gradeId || 0;
    const gradeVersion = parseInt(row.dataset.version || '0', 10) || 0;
    
    // Get the grade input element
    const gradeInput = row.querySelector('.term-grade-input');
    const currentGrade = parseFloat(gradeInput.value) || 0;
    
    // Get stored values for all terms from data attributes
    let prelim = parseFloat(gradeInput.dataset.prelim) || 0;
    let midterm = parseFloat(gradeInput.dataset.midterm) || 0;
    let prefinal = parseFloat(gradeInput.dataset.prefinal) || 0;
    let finalGrade = parseFloat(gradeInput.dataset.final) || 0;
    
    // Update the appropriate term based on selected dropdown
    switch(getSelectedTerm()) {
        case 'prelim': prelim = currentGrade; break;
        case 'midterm': midterm = currentGrade; break;
        case 'prefinal': prefinal = currentGrade; break;
        case 'final': finalGrade = currentGrade; break;
    }
    
    // Update data attributes
    gradeInput.dataset.prelim = prelim;
    gradeInput.dataset.midterm = midterm;
    gradeInput.dataset.prefinal = prefinal;
    gradeInput.dataset.final = finalGrade;
    
    // Calculate overall average from all terms
    let count = 0;
    let total = 0;
    if (prelim > 0) { count++; total += prelim; }
    if (midterm > 0) { count++; total += midterm; }
    if (prefinal > 0) { count++; total += prefinal; }
    if (finalGrade > 0) { count++; total += finalGrade; }
    const overallAverage = count > 0 ? (total / count) : 0;
    
    const notes = row.querySelector('.notes-input').value || '';
    const remarks = overallAverage >= 75 ? 'PASSED' : (overallAverage > 0 ? 'FAILED' : '');
    
    // Show loading state on button
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append('section_id', SECTION_ID);
    formData.append('subject_id', SUBJECT_ID);
    formData.append('grade_id', gradeId);
    formData.append('version', gradeVersion);
    formData.append('term', getSelectedTerm());
    formData.append('term_grade', currentGrade.toFixed(2));
    formData.append('prelim', prelim.toFixed(2));
    formData.append('midterm', midterm.toFixed(2));
    formData.append('prefinal', prefinal.toFixed(2));
    formData.append('final', finalGrade.toFixed(2));
    formData.append('final_grade', overallAverage.toFixed(2));
    formData.append('remarks', remarks);
    formData.append('notes', notes);
    
    try {
        const response = await fetch('api/update_grade.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.status === 'success') {
            // Update grade_id for new records
            if (data.grade_id) {
                row.dataset.gradeId = data.grade_id;
            }
            if (data.version) {
                row.dataset.version = data.version;
            }
            
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-save-row');
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-save-row');
                btn.disabled = false;
            }, 1500);
            return true;
        } else {
            throw new Error(data.message || 'Failed to save');
        }
    } catch (error) {
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
        btn.classList.add('btn-danger');
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('btn-danger');
            btn.disabled = false;
        }, 1500);
        showAlert(error.message || 'Failed to save grade', 'danger');
        return false;
    }
}

async function saveAllGrades() {
    const rows = document.querySelectorAll('tbody tr');
    const saveBtn = document.querySelector('.btn-save-all');
    const originalText = saveBtn.innerHTML;
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';
    
    let saved = 0;
    let failed = 0;
    
    for (const row of rows) {
        const btn = row.querySelector('.save-grade-btn');
        const success = await saveGrade(row, btn);
        if (success) saved++;
        else failed++;
    }
    
    let message = `Successfully saved ${saved} student records for ${TERM_NAMES[SELECTED_TERM]} term.`;
    if (failed > 0) message += ` ${failed} failed.`;
    
    showAlert(message, failed > 0 ? 'warning' : 'success');
    saveBtn.disabled = false;
    saveBtn.innerHTML = originalText;
}

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : (type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-exclamation-circle-fill')} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}

// Common function to gather grade data for current term
function getGradeData() {
    const rows = document.querySelectorAll('tbody tr');
    const subjectCode = '<?php echo addslashes($class_info['subject_code']); ?>';
    const subjectTitle = '<?php echo addslashes($class_info['subject_title']); ?>';
    const sectionName = '<?php echo addslashes($class_info['section_name']); ?>';
    const programName = '<?php echo addslashes($class_info['program_name']); ?>';
    const yearLevel = '<?php echo addslashes($class_info['year_level_name']); ?>';
    const academicYear = '<?php echo ($current_ay['year_start'] ?? date('Y')) . '-' . ($current_ay['year_end'] ?? (date('Y')+1)); ?>';
    
    const students = [];
    rows.forEach((row, index) => {
        const isCredited = row.dataset.credited === '1';
        const gradeInput = row.querySelector('.term-grade-input');
        const currentGrade = isCredited ? '' : (parseFloat(gradeInput?.value) || '');
        
        students.push({
            no: index + 1,
            studentNo: row.querySelector('.student-no')?.textContent?.trim() || '',
            studentName: row.querySelector('.fw-bold.text-dark')?.childNodes[0]?.textContent?.trim() || row.querySelector('.fw-bold.text-dark')?.textContent?.trim() || '',
            grade: currentGrade,
            rating: isCredited ? 'CREDITED' : (row.querySelector('.rating-badge')?.textContent?.trim() || ''),
            remarks: isCredited ? 'Credited Subject' : (row.querySelector('.remarks-badge')?.textContent?.trim() || ''),
            notes: isCredited ? '' : (row.querySelector('.notes-input')?.value?.trim() || ''),
            isCredited: isCredited
        });
    });
    
    return {
        subjectCode,
        subjectTitle,
        sectionName,
        programName,
        yearLevel,
        academicYear,
        term: TERM_NAMES[SELECTED_TERM],
        exportDate: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
        students,
        totalStudents: rows.length
    };
}

// Export to Excel (Editable or Protected)
function exportToExcel(editable = true) {
    // Check if XLSX library is loaded
    if (typeof XLSX === 'undefined') {
        showAlert('<strong>Excel library not loaded.</strong> Please refresh the page and try again.', 'danger');
        console.error('XLSX library not loaded');
        return;
    }
    
    const data = getGradeData();
    console.log('Export data:', data);
    
    // Style definitions
    const titleStyle = { font: { bold: true, sz: 16, color: { rgb: "800000" } }, alignment: { horizontal: "center", vertical: "center" } };
    const subtitleStyle = { font: { bold: true, sz: 11, color: { rgb: "003366" } }, alignment: { horizontal: "center" } };
    const labelStyle = { font: { bold: true, sz: 10 }, alignment: { horizontal: "left" } };
    const valueStyle = { font: { sz: 10 }, alignment: { horizontal: "left" } };
    const headerStyle = { 
        font: { bold: true, sz: 10, color: { rgb: "FFFFFF" } }, 
        fill: { fgColor: { rgb: "003366" } },
        alignment: { horizontal: "center", vertical: "center", wrapText: true },
        border: { top: {style:"thin"}, bottom: {style:"thin"}, left: {style:"thin"}, right: {style:"thin"} }
    };
    const dataBorder = { 
        border: { top: {style:"thin", color:{rgb:"CCCCCC"}}, bottom: {style:"thin", color:{rgb:"CCCCCC"}}, left: {style:"thin", color:{rgb:"CCCCCC"}}, right: {style:"thin", color:{rgb:"CCCCCC"}} },
        font: { sz: 10 },
        alignment: { vertical: "center" }
    };
    const dataCenter = { ...dataBorder, alignment: { horizontal: "center", vertical: "center" } };
    const passedStyle = { ...dataCenter, font: { sz: 10, bold: true, color: { rgb: "155724" } } };
    const failedStyle = { ...dataCenter, font: { sz: 10, bold: true, color: { rgb: "DC3545" } } };
    const signatureStyle = { font: { sz: 10 }, alignment: { horizontal: "center" } };
    const signatureLineStyle = { font: { sz: 10, bold: true }, alignment: { horizontal: "center" }, border: { top: {style:"thin"} } };
    
    // Build sheet data
    const sheetData = [];
    
    // Row 0: Title
    sheetData.push([{v: 'STUDENT GRADE SHEET', s: titleStyle}, '', '', '', '', '']);
    // Row 1: Subtitle
    sheetData.push([{v: 'E-Learning Management System', s: subtitleStyle}, '', '', '', '', '']);
    // Row 2: blank
    sheetData.push(['']);
    // Row 3-6: Info
    sheetData.push([{v: 'Subject:', s: labelStyle}, {v: data.subjectCode + ' - ' + data.subjectTitle, s: valueStyle}, '', {v: 'Section:', s: labelStyle}, {v: data.sectionName, s: valueStyle}, '']);
    sheetData.push([{v: 'Program:', s: labelStyle}, {v: data.programName, s: valueStyle}, '', {v: 'Year Level:', s: labelStyle}, {v: data.yearLevel, s: valueStyle}, '']);
    sheetData.push([{v: 'Term:', s: labelStyle}, {v: data.term, s: valueStyle}, '', {v: 'A.Y.:', s: labelStyle}, {v: data.academicYear, s: valueStyle}, '']);
    sheetData.push([{v: 'Date:', s: labelStyle}, {v: data.exportDate, s: valueStyle}, '', '', '', '']);
    // Row 7: blank
    sheetData.push(['']);
    // Row 8: Column Headers
    sheetData.push([
        {v: 'No.', s: headerStyle},
        {v: 'Student No.', s: headerStyle},
        {v: 'Student Name', s: headerStyle},
        {v: 'Grade', s: headerStyle},
        {v: 'Remarks', s: headerStyle},
        {v: 'Notes', s: headerStyle}
    ]);
    
    // Student data rows
    const dataStartRow = 9;
    data.students.forEach(student => {
        const remarksStyle = student.remarks === 'PASSED' ? passedStyle : (student.remarks === 'FAILED' ? failedStyle : dataCenter);
        sheetData.push([
            {v: student.no, s: dataCenter},
            {v: student.studentNo, s: dataBorder},
            {v: student.studentName, s: dataBorder},
            {v: student.grade || '', s: dataCenter},
            {v: student.remarks || '', s: remarksStyle},
            {v: student.notes || '', s: dataBorder}
        ]);
    });
    
    // Footer
    const footerRow = sheetData.length;
    sheetData.push(['']);
    sheetData.push([
        '', {v: 'Total Students:', s: {font: {bold: true, sz: 10}}}, 
        {v: data.totalStudents, s: {font: {bold: true, sz: 10}}}
    ]);
    sheetData.push(['']);
    sheetData.push(['']);
    sheetData.push([
        '', {v: 'Prepared by:', s: signatureStyle}, '', '',
        {v: 'Verified by:', s: signatureStyle}, ''
    ]);
    sheetData.push(['']);
    sheetData.push([
        '', {v: '____________________________', s: signatureLineStyle}, '', '',
        {v: '____________________________', s: signatureLineStyle}, ''
    ]);
    sheetData.push([
        '', {v: 'Instructor', s: signatureStyle}, '', '',
        {v: 'Department Head', s: signatureStyle}, ''
    ]);
    
    // Create workbook and worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(sheetData);
    
    // Set column widths
    ws['!cols'] = [
        { wch: 6 },   // No.
        { wch: 18 },  // Student No
        { wch: 35 },  // Names
        { wch: 10 },  // Grade
        { wch: 14 },  // Remarks
        { wch: 18 },  // Notes
    ];
    
    // Merge cells for title rows
    ws['!merges'] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: 5 } },  // Title
        { s: { r: 1, c: 0 }, e: { r: 1, c: 5 } },  // Subtitle
    ];
    
    // Set row heights
    ws['!rows'] = [];
    ws['!rows'][0] = { hpx: 30 };  // Title row
    ws['!rows'][1] = { hpx: 22 };  // Subtitle
    ws['!rows'][8] = { hpx: 25 };  // Header row
    
    // Add sheet protection only if not editable
    if (!editable) {
        ws['!protect'] = {
            password: EXPORT_PASSWORD,
            sheet: true,
            objects: true,
            scenarios: true,
            formatCells: false,
            formatColumns: false,
            formatRows: false,
            insertColumns: false,
            insertRows: false,
            insertHyperlinks: false,
            deleteColumns: false,
            deleteRows: false,
            selectLockedCells: true,
            sort: false,
            autoFilter: false,
            pivotTables: false,
            selectUnlockedCells: true
        };
    }
    
    XLSX.utils.book_append_sheet(wb, ws, 'Grade Sheet');
    
    // Download
    const suffix = editable ? 'Editable' : 'Protected';
    const termSuffix = SELECTED_TERM.charAt(0).toUpperCase() + SELECTED_TERM.slice(1);
    const filename = `GradeSheet_${data.subjectCode}_${data.sectionName}_${termSuffix}_${suffix}_${new Date().toISOString().slice(0,10)}.xlsx`;
    
    XLSX.writeFile(wb, filename);
    
    if (editable) {
        showAlert('<i class="bi bi-file-earmark-excel me-2"></i>Grade sheet exported as <strong>Editable Excel</strong> file for ' + data.term + ' term!', 'success');
    } else {
        showAlert('<i class="bi bi-file-earmark-excel me-2"></i>Grade sheet exported as <strong>Protected Excel</strong> file for ' + data.term + ' term!', 'success');
    }
}

// Export to PDF
function exportToPDF() {
    const data = getGradeData();
    
    // Create a printable HTML content
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Grade Sheet - ${data.subjectCode} - ${data.term}</title>
            <style>
                @page { size: A4 landscape; margin: 15mm; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Times New Roman', Times, serif; font-size: 10pt; line-height: 1.4; color: #000; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { font-size: 16pt; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
                .header h2 { font-size: 12pt; font-weight: normal; margin-bottom: 15px; }
                .info-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
                .info-table td { padding: 3px 10px 3px 0; font-size: 10pt; }
                .info-table td:first-child { font-weight: bold; width: 120px; }
                .grade-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .grade-table th, .grade-table td { border: 1px solid #000; padding: 5px 6px; text-align: left; }
                .grade-table th { background-color: #f0f0f0; font-weight: bold; text-align: center; font-size: 8pt; text-transform: uppercase; }
                .grade-table td { font-size: 9pt; }
                .grade-table td:nth-child(1) { text-align: center; width: 30px; }
                .grade-table td:nth-child(4) { text-align: center; width: 80px; }
                .grade-table td:nth-child(5) { text-align: center; width: 100px; }
                .grade-table td:nth-child(6) { text-align: center; width: 80px; }
                .passed { color: #155724; font-weight: bold; }
                .failed { color: #721c24; font-weight: bold; }
                .footer { margin-top: 30px; }
                .signature-row { display: flex; justify-content: space-between; margin-top: 40px; }
                .signature-box { width: 45%; }
                .signature-line { border-top: 1px solid #000; margin-top: 40px; padding-top: 5px; text-align: center; font-size: 10pt; }
                .total-row { font-weight: bold; margin-top: 10px; font-size: 10pt; }
                .print-date { text-align: right; font-size: 9pt; color: #666; margin-top: 20px; }
                @media print {
                    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Student Grade Sheet - ${data.term} Term</h1>
                <h2>E-Learning Management System</h2>
            </div>
            
            <table class="info-table">
                <tr><td>Academic Year:</td><td>${data.academicYear}</td><td>Program:</td><td>${data.programName}</td></tr>
                <tr><td>Subject Code:</td><td>${data.subjectCode}</td><td>Year Level:</td><td>${data.yearLevel}</td></tr>
                <tr><td>Subject Title:</td><td>${data.subjectTitle}</td><td>Section:</td><td>${data.sectionName}</td></tr>
                <tr><td>Term:</td><td>${data.term}</td><td></td><td></td></tr>
            </table>
            
            <table class="grade-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Student Number</th>
                        <th>Student Name</th>
                        <th>Average</th>
                        <th>Rating</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.students.map(s => `
                        <tr>
                            <td>${s.no}</td>
                            <td>${s.studentNo}</td>
                            <td>${s.studentName}</td>
                            <td>${s.isCredited ? '' : (s.grade || '')}</td>
                            <td>${s.rating}</td>
                            <td class="${s.remarks === 'PASSED' ? 'passed' : (s.remarks === 'FAILED' ? 'failed' : '')}">${s.remarks}</td>
                            <td>${s.isCredited ? 'CREDITED' : 'Regular'}</td>
                            <td>${s.notes}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            
            <div class="total-row">Total Students: ${data.totalStudents}</div>
            
            <div class="signature-row">
                <div class="signature-box">
                    <div class="signature-line">Prepared by</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">Verified by</div>
                </div>
            </div>
            
            <div class="print-date">Generated on: ${data.exportDate}</div>
        </body>
        </html>
    `;
    
    // Open print dialog
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Wait for content to load then print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
            // Don't close automatically - let user save as PDF or print
        }, 250);
    };
    
    showAlert('<i class="bi bi-file-pdf me-2"></i>PDF preview opened for ' + data.term + ' term! Use <strong>Save as PDF</strong> in the print dialog or print directly.', 'success');
}

// Import grades from Excel - validates format automatically
function importGrades(input) {
    if (!input.files || !input.files[0]) {
        showAlert('No file selected.', 'warning');
        return;
    }
    
    // Check if XLSX library is loaded
    if (typeof XLSX === 'undefined') {
        showAlert('Excel library not loaded. Please refresh the page and try again.', 'danger');
        return;
    }
    
    const file = input.files[0];
    console.log('Importing file:', file.name);
    
    const reader = new FileReader();
    
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            
            // Get first sheet
            const sheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[sheetName];
            
            // Convert to array of arrays
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
            console.log('Parsed rows:', jsonData.length);
            
            // Validate format - look for required headers
            let headerRow = -1;
            
            for (let i = 0; i < Math.min(jsonData.length, 20); i++) {
                const row = jsonData[i];
                if (!row || row.length === 0) continue;
                
                // Convert row to uppercase strings for comparison
                const rowUpper = row.map(cell => String(cell || '').toUpperCase().trim());
                const rowJoined = rowUpper.join(' ');
                
                // Check if this row contains the required headers
                const hasStudentNo = rowJoined.includes('STUDENT NUMBER') || rowJoined.includes('STUDENT NO') || rowUpper.includes('NO.');
                const hasName = rowJoined.includes('STUDENT NAME') || rowJoined.includes('NAME');
                const hasGrade = rowJoined.includes('AVERAGE') || rowJoined.includes('GRADE');
                
                if (hasStudentNo && hasName && hasGrade) {
                    headerRow = i;
                    console.log('Found header row at:', i, rowUpper);
                    break;
                }
            }
            
            if (headerRow === -1) {
                showAlert('<strong>Invalid file format!</strong><br>The Excel file must contain columns: STUDENT NUMBER, STUDENT NAME, AVERAGE.<br>Please use the exported grade sheet as template.', 'danger');
                input.value = '';
                return;
            }
            
            // Find column indices - prioritize specific headers over generic ones
            const headerRowData = jsonData[headerRow].map(cell => String(cell || '').toUpperCase().trim());
            let studentNoCol = -1, avgCol = -1, notesCol = -1;
            
            headerRowData.forEach((cell, idx) => {
                // Match 'STUDENT NUMBER' or 'STUDENT NO.' specifically (not just 'NO.')
                if (cell.includes('STUDENT NUMBER') || cell.includes('STUDENT NO')) studentNoCol = idx;
                if (cell.includes('AVERAGE') || cell === 'GRADE') avgCol = idx;
                if (cell.includes('NOTES') || cell.includes('NOTE')) notesCol = idx;
            });
            
            // Fallback: if student number column not found, try second column
            if (studentNoCol === -1) {
                // Check if any column is just 'NO.' - that's the sequence number, student no is next
                headerRowData.forEach((cell, idx) => {
                    if (cell === 'NO.' && idx + 1 < headerRowData.length) {
                        studentNoCol = idx + 1; // Student No is the column AFTER 'No.'
                    }
                });
                if (studentNoCol === -1) studentNoCol = 1; // Final fallback: assume second column
            }
            if (avgCol === -1) {
                avgCol = 3; // Assume fourth column
            }
            
            console.log('Column indices - StudentNo:', studentNoCol, 'Average:', avgCol, 'Notes:', notesCol);
            
            let updated = 0;
            let notFound = [];
            let errors = [];
            
            // Process data rows (after header)
            for (let i = headerRow + 1; i < jsonData.length; i++) {
                const row = jsonData[i];
                if (!row || row.length < 2) continue;
                
                const studentNo = String(row[studentNoCol] || '').trim();
                const averageRaw = row[avgCol];
                const notes = notesCol >= 0 ? String(row[notesCol] || '').trim() : '';
                
                if (!studentNo || studentNo === '' || studentNo.includes('═') || studentNo.includes('Total')) continue;
                
                // Validate average is a number
                const average = parseFloat(averageRaw);
                if (averageRaw !== '' && averageRaw !== null && averageRaw !== undefined && isNaN(average)) {
                    continue; // Skip invalid rows silently
                }
                
                // Find matching row by student number
                const tableRows = document.querySelectorAll('tbody tr');
                let found = false;
                
                tableRows.forEach(tableRow => {
                    const studentNoElement = tableRow.querySelector('.student-no');
                    if (!studentNoElement) return;
                    
                    const rowStudentNo = studentNoElement.textContent.trim();
                    if (rowStudentNo === studentNo) {
                        const gradeInput = tableRow.querySelector('.term-grade-input');
                        if (gradeInput && !isNaN(average) && average >= 0) {
                            gradeInput.value = average.toFixed(2);
                            console.log('Updated grade for:', studentNo, 'to', average);
                        }
                        const notesInput = tableRow.querySelector('.notes-input');
                        if (notesInput && notes) {
                            notesInput.value = notes;
                        }
                        updateRatingAndRemarks(tableRow);
                        updated++;
                        found = true;
                    }
                });
                
                if (!found && studentNo && !studentNo.includes('Prepared') && !studentNo.includes('Verified')) {
                    notFound.push(studentNo);
                }
            }
            
            // Reset file input
            input.value = '';
            
            // Build result message
            let message = '';
            if (updated > 0) {
                message = `<strong>✓ Successfully imported ${updated} grades for ${TERM_NAMES[SELECTED_TERM]} term.</strong>`;
            } else {
                message = '<strong>No grades were imported.</strong> Make sure the student numbers in the file match those in the gradebook.';
            }
            
            if (notFound.length > 0 && notFound.length <= 5) {
                message += `<br><small class="text-muted">Students not found: ${notFound.join(', ')}</small>`;
            } else if (notFound.length > 5) {
                message += `<br><small class="text-muted">${notFound.length} students not found in this section.</small>`;
            }
            
            if (updated > 0) {
                message += '<br><strong>Click "SAVE ALL" to save changes to database.</strong>';
                showAlert(message, 'success');
            } else {
                showAlert(message, 'warning');
            }
        } catch (error) {
            console.error('Import error:', error);
            showAlert('<strong>Failed to read Excel file.</strong><br>Error: ' + error.message + '<br>Please ensure the file is a valid Excel format (.xlsx or .xls).', 'danger');
            input.value = '';
        }
    };
    
    reader.onerror = function() {
        showAlert('Failed to read the file. Please try again.', 'danger');
        input.value = '';
    };
    
    reader.readAsArrayBuffer(file);
}
</script>

<?php include '../../includes/footer.php'; ?>
