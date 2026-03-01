<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Grades";
$student_id = (int)$_SESSION['user_id'];

/**
 * Build the list of year-level + semester combos this student has grades for.
 * Joins grades -> sections -> program_year_levels to derive year level.
 */
$filter_options_query = $conn->query("
    SELECT DISTINCT
        pyl.id as year_level_id,
        pyl.year_level,
        pyl.year_name,
        s.semester,
        ay.id as academic_year_id,
        ay.year_name as ay_name
    FROM grades g
    INNER JOIN sections s ON g.section_id = s.id
    INNER JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    INNER JOIN academic_years ay ON s.academic_year_id = ay.id
    WHERE g.student_id = $student_id
    ORDER BY pyl.year_level ASC, s.semester ASC, ay.year_name ASC
");
$filter_options = [];
if ($filter_options_query) {
    while ($row = $filter_options_query->fetch_assoc()) {
        $filter_options[] = $row;
    }
}

// Current active AY
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Current section info for the student
$section_info = $conn->query("
    SELECT s.*, COALESCE(p.program_code, ss.strand_code) as program_code,
           pyl.year_name as current_year_name, s.semester as current_semester
    FROM section_students stu
    INNER JOIN sections s ON stu.section_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

/**
 * --- AJAX HANDLER ---
 */
if (isset($_GET['ajax'])) {
    $selected_term = $_GET['term'] ?? 'all';
    $filter_yl = $_GET['year_level'] ?? 'current';
    $filter_sem = $_GET['semester'] ?? 'all';

    // Determine which section(s) to pull grades from
    if ($filter_yl === 'current') {
        $section_id = $section_info['id'] ?? 0;
        $section_ids = $section_id ? [$section_id] : [];
    } else {
        $yl_id = (int)$filter_yl;
        $sec_query = "SELECT s.id FROM sections s
                      INNER JOIN section_students ss2 ON s.id = ss2.section_id
                      WHERE ss2.student_id = $student_id AND s.year_level_id = $yl_id";
        if ($filter_sem !== 'all') {
            $filter_sem_safe = $conn->real_escape_string($filter_sem);
            $sec_query .= " AND s.semester = '$filter_sem_safe'";
        }
        $sec_result = $conn->query($sec_query);
        $section_ids = [];
        if ($sec_result) {
            while ($sr = $sec_result->fetch_assoc()) {
                $section_ids[] = (int)$sr['id'];
            }
        }
    }

    $list = [];
    if (!empty($section_ids)) {
        $ids_str = implode(',', $section_ids);
        $g_query = $conn->query("
            SELECT g.*, cs.subject_code, cs.subject_title, s.semester as sec_semester,
                   ay.year_name as ay_name, pyl.year_name as yl_name
            FROM grades g
            INNER JOIN curriculum_subjects cs ON g.subject_id = cs.id
            INNER JOIN sections s ON g.section_id = s.id
            LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
            LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
            WHERE g.student_id = $student_id AND g.section_id IN ($ids_str)
            ORDER BY cs.subject_code
        ");
        if ($g_query) {
            while ($r = $g_query->fetch_assoc()) { $list[] = $r; }
        }
    }
    if (empty($list) && $filter_yl === 'current') {
        $o_grades = $conn->query("
            SELECT g.*, c.course_code as subject_code, c.title as subject_title
            FROM grades g
            INNER JOIN classes cl ON g.class_id = cl.id
            INNER JOIN courses c ON cl.course_id = c.id
            WHERE g.student_id = $student_id
            ORDER BY c.course_code
        ");
        if ($o_grades) {
            while ($r = $o_grades->fetch_assoc()) { $list[] = $r; }
        }
    }

    $total = 0; $count = 0;
    $html = '';
    foreach ($list as $grade) {
        $val = ($selected_term == 'all') ? ($grade['final_grade'] ?? 0) : ($grade[$selected_term] ?? 0);
        if ($val > 0) { $total += $val; $count++; }

        $rating = 'N/A';
        if ($val >= 97) $rating = '1.00'; elseif ($val >= 94) $rating = '1.25'; elseif ($val >= 91) $rating = '1.50'; elseif ($val >= 88) $rating = '1.75'; elseif ($val >= 85) $rating = '2.00'; elseif ($val >= 82) $rating = '2.25'; elseif ($val >= 79) $rating = '2.50'; elseif ($val >= 76) $rating = '2.75'; elseif ($val >= 75) $rating = '3.00'; elseif ($val > 0) $rating = '5.00';

        $rem = $val >= 75 ? 'PASSED' : ($val > 0 ? 'FAILED' : 'PENDING');
        $clr = ($rem == 'PASSED') ? 'success' : (($rem == 'FAILED') ? 'danger' : 'secondary');

        $html .= '<tr><td class="ps-4"><div class="fw-bold">'.$grade['subject_code'].'</div><small class="text-muted">'.$grade['subject_title'].'</small></td>';
        if ($selected_term == 'all') {
            $html .= '<td class="text-center small">'.(($grade['prelim'] > 0) ? number_format($grade['prelim'], 2) : '-').'</td>';
            $html .= '<td class="text-center small">'.(($grade['midterm'] > 0) ? number_format($grade['midterm'], 2) : '-').'</td>';
            $html .= '<td class="text-center small">'.(($grade['prefinal'] > 0) ? number_format($grade['prefinal'], 2) : '-').'</td>';
            $html .= '<td class="text-center small">'.(($grade['final'] > 0) ? number_format($grade['final'], 2) : '-').'</td>';
            $html .= '<td class="text-center fw-bold text-maroon">'.(($grade['final_grade'] > 0) ? number_format($grade['final_grade'], 2) : '-').'</td>';
        } else {
            $html .= '<td class="text-center fw-bold text-maroon">'.(($val > 0) ? number_format($val, 2) : '-').'</td>';
        }
        $html .= '<td class="text-center"><span class="badge bg-light text-dark border">'.$rating.'</span></td><td class="text-center pe-4"><span class="badge rounded-pill bg-'.$clr.'">'.$rem.'</span></td></tr>';
    }

    if (empty($html)) {
        $html = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No grades found for this filter.</td></tr>';
    }

    echo json_encode(['table' => $html, 'gpa' => ($count > 0 ? number_format($total / $count, 2, '.', '') : "0.00")]);
    exit();
}

/**
 * --- INITIAL LOAD LOGIC ---
 */
$term_names = ['all' => 'All Terms', 'prelim' => 'Prelim', 'midterm' => 'Midterm', 'prefinal' => 'Pre-Finals', 'final' => 'Finals'];
$section_id = $section_info['id'] ?? 0;

$grades_query = $conn->query("SELECT g.*, cs.subject_code, cs.subject_title FROM grades g INNER JOIN curriculum_subjects cs ON g.subject_id = cs.id WHERE g.student_id = $student_id AND g.section_id = $section_id ORDER BY cs.subject_code");
$grades_list = [];
if ($grades_query) { while ($row = $grades_query->fetch_assoc()) { $grades_list[] = $row; } }
if (empty($grades_list)) {
    $old_grades = $conn->query("SELECT g.*, c.course_code as subject_code, c.title as subject_title FROM grades g INNER JOIN classes cl ON g.class_id = cl.id INNER JOIN courses c ON cl.course_id = c.id WHERE g.student_id = $student_id ORDER BY c.course_code");
    if ($old_grades) { while ($row = $old_grades->fetch_assoc()) { $grades_list[] = $row; } }
}

$total_grade = 0; $grade_count = 0;
foreach ($grades_list as $g) { if (($g['final_grade'] ?? 0) > 0) { $total_grade += $g['final_grade']; $grade_count++; } }
$gpa = $grade_count > 0 ? round($total_grade / $grade_count, 2) : 0;

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/grades.css">

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0 text-blue"><i class="bi bi-bar-chart-fill me-2 text-maroon"></i>My Academic Grades</h4>
            <small class="text-muted">View grades across year levels and semesters</small>
        </div>
        <div class="d-flex gap-2 btn-print-hide flex-wrap">
            <!-- Year Level / Semester Filter -->
            <select class="form-select form-select-sm rounded-pill shadow-sm" id="yearLevelFilter" style="min-width: 180px;">
                <option value="current" selected>Current Semester</option>
                <?php
                $grouped = [];
                foreach ($filter_options as $fo) {
                    $key = $fo['year_level_id'];
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = ['year_name' => $fo['year_name'], 'year_level' => $fo['year_level'], 'semesters' => []];
                    }
                    $sem_key = $fo['semester'] ?: 'all';
                    if (!in_array($sem_key, $grouped[$key]['semesters'])) {
                        $grouped[$key]['semesters'][] = $sem_key;
                    }
                }
                foreach ($grouped as $yl_id => $info):
                    foreach ($info['semesters'] as $sem):
                        $sem_label = $sem === 'all' ? '' : " - {$sem} Semester";
                        $opt_value = $yl_id . '|' . $sem;
                ?>
                    <option value="<?php echo htmlspecialchars($opt_value); ?>">
                        <?php echo htmlspecialchars($info['year_name'] . $sem_label); ?>
                    </option>
                <?php endforeach; endforeach; ?>
            </select>
            <!-- Term Filter -->
            <select class="form-select form-select-sm rounded-pill shadow-sm" id="termAjaxFilter" style="min-width: 140px;">
                <?php foreach ($term_names as $key => $name): ?>
                    <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-light btn-sm border rounded-pill px-3" onclick="window.print()"><i class="bi bi-printer"></i></button>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">

    <!-- GPA Hero -->
    <div class="gpa-hero animate__animated animate__fadeIn">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="fw-bold mb-1 text-white"><?php echo htmlspecialchars($_SESSION['name']); ?></h4>
                <p class="mb-0 opacity-75 small fw-bold text-uppercase">
                    <?php echo htmlspecialchars($section_info['program_code'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($section_info['section_name'] ?? 'N/A'); ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="bg-white bg-opacity-10 p-3 rounded-4 border border-white border-opacity-25 d-inline-block text-center">
                    <h1 class="mb-0 fw-bold" id="gpaDisplay"><?php echo number_format($gpa, 2); ?></h1>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size: 0.6rem;">General Average</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Table Card -->
    <div class="summary-card animate__animated animate__fadeInUp" id="gradeTableCard">
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0">
                <thead id="dynamicHeader"></thead>
                <tbody id="dynamicBody"></tbody>
            </table>
        </div>
    </div>

    <!-- GRADING SCALE LEGEND -->
    <div class="card border-0 shadow-sm rounded-4 legend-card animate__animated animate__fadeInUp">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3 text-muted small text-uppercase">Institutional Grading Scale</h6>
            <div class="row g-4 small">
                <div class="col-md-6 border-end">
                    <div class="d-flex justify-content-between mb-1"><span>96 - 100</span><strong>1.00 Excellent</strong></div>
                    <div class="d-flex justify-content-between mb-1"><span>91 - 95</span><strong>1.25 Very Good</strong></div>
                    <div class="d-flex justify-content-between mb-1"><span>86 - 90</span><strong>1.50 Good</strong></div>
                </div>
                <div class="col-md-6 ps-md-4">
                    <div class="d-flex justify-content-between mb-1"><span>75</span><strong>3.00 Passing</strong></div>
                    <div class="d-flex justify-content-between mb-1"><span>Below 75</span><strong class="text-danger">5.00 Failed</strong></div>
                    <div class="d-flex justify-content-between mb-1"><span>Incomplete</span><strong>INC</strong></div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    fetchGrades('all', 'current', 'all');

    $('#termAjaxFilter').on('change', function() {
        triggerFetch();
    });
    $('#yearLevelFilter').on('change', function() {
        triggerFetch();
    });
});

function triggerFetch() {
    const term = $('#termAjaxFilter').val();
    const ylVal = $('#yearLevelFilter').val();
    let yearLevel = 'current', semester = 'all';
    if (ylVal !== 'current') {
        const parts = ylVal.split('|');
        yearLevel = parts[0];
        semester = parts[1] || 'all';
    }
    fetchGrades(term, yearLevel, semester);
}

async function fetchGrades(term, yearLevel, semester) {
    const card = $('#gradeTableCard');
    card.css('opacity', '0.4');

    try {
        const response = await fetch(`?ajax=1&term=${term}&year_level=${yearLevel}&semester=${encodeURIComponent(semester)}`);
        const data = await response.json();

        let head = '<tr><th class="ps-4">Subject & Code</th>';
        if (term === 'all') {
            head += '<th class="text-center">Prelim</th><th class="text-center">Midterm</th><th class="text-center">Pre-Final</th><th class="text-center">Final</th><th class="text-center">GWA</th>';
        } else {
            head += '<th class="text-center">Term Grade</th>';
        }
        head += '<th class="text-center">Rating</th><th class="text-center pe-4">Remarks</th></tr>';

        $('#dynamicHeader').html(head);
        $('#dynamicBody').html(data.table);
        $('#gpaDisplay').text(data.gpa);

        card.css('opacity', '1');
    } catch (e) {
        console.error("AJAX Failed:", e);
        card.css('opacity', '1');
    }
}
</script>
</body>
</html>