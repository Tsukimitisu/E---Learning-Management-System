<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Grades";
$student_id = $_SESSION['user_id'];

// Term filter from URL (default to viewing all terms)
$selected_term = $_GET['term'] ?? 'all';
$valid_terms = ['all', 'prelim', 'midterm', 'prefinal', 'final'];
if (!in_array($selected_term, $valid_terms)) {
    $selected_term = 'all';
}
$term_names = [
    'all' => 'All Terms',
    'prelim' => 'Prelim',
    'midterm' => 'Midterm',
    'prefinal' => 'Pre-Finals',
    'final' => 'Finals'
];

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get enrolled section
$section_info = $conn->query("
    SELECT s.*, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(p.program_code, ss.strand_code) as program_code,
           COALESCE(pyl.year_name, sgl.grade_name) as year_level,
           b.name as branch_name
    FROM section_students stu
    INNER JOIN sections s ON stu.section_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

$section_id = $section_info['id'] ?? 0;

// Get grades from the grades table - with all 4 terms
$grades_query = $conn->query("
    SELECT g.*, cs.subject_code, cs.subject_title
    FROM grades g
    INNER JOIN curriculum_subjects cs ON g.subject_id = cs.id
    WHERE g.student_id = $student_id AND g.section_id = $section_id
    ORDER BY cs.subject_code
");

// Fallback to old class-based grades if no term-based grades found
$grades_list = [];
while ($row = $grades_query->fetch_assoc()) {
    $grades_list[] = $row;
}

// If no grades from curriculum_subjects, try class-based
if (empty($grades_list)) {
    $old_grades = $conn->query("
        SELECT g.*, c.course_code as subject_code, c.title as subject_title
        FROM grades g
        INNER JOIN classes cl ON g.class_id = cl.id
        INNER JOIN courses c ON cl.course_id = c.id
        WHERE g.student_id = $student_id
        ORDER BY c.course_code
    ");
    while ($row = $old_grades->fetch_assoc()) {
        $grades_list[] = $row;
    }
}

// Calculate GPA/Average based on selected term or final_grade
$total_grade = 0;
$grade_count = 0;

foreach ($grades_list as $g) {
    $grade_value = 0;
    if ($selected_term == 'all') {
        $grade_value = $g['final_grade'] ?? 0;
    } else {
        $grade_value = $g[$selected_term] ?? 0;
    }
    
    if ($grade_value > 0) {
        $total_grade += $grade_value;
        $grade_count++;
    }
}
$gpa = $grade_count > 0 ? round($total_grade / $grade_count, 2) : 0;

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-bar-chart-fill text-primary me-2"></i>My Grades</h4>
                <small class="text-muted"><?php echo htmlspecialchars($current_ay['year_name'] ?? ''); ?></small>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <!-- Term Filter Dropdown -->
                <select class="form-select form-select-sm rounded-pill shadow-sm" style="width: auto; min-width: 150px;" onchange="window.location.href='?term='+this.value">
                    <?php foreach ($term_names as $key => $name): ?>
                    <option value="<?php echo $key; ?>" <?php echo $selected_term == $key ? 'selected' : ''; ?>>
                        📋 <?php echo $name; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary btn-sm rounded-pill" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>

        <?php if ($section_info): ?>
        <!-- Student Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($_SESSION['name']); ?></h5>
                        <p class="mb-0 text-muted">
                            <?php echo htmlspecialchars($section_info['program_code'] . ' - ' . $section_info['section_name']); ?> |
                            <?php echo htmlspecialchars($section_info['year_level']); ?> |
                            <?php echo htmlspecialchars($section_info['semester']); ?> Semester
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="d-inline-block text-center p-3 rounded-3 <?php echo $gpa >= 75 ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10">
                            <h2 class="mb-0 fw-bold <?php echo $gpa >= 75 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $gpa ?: 'N/A'; ?>
                            </h2>
                            <small class="text-muted">General Average</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Grades Summary Table -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-table text-success me-2"></i>Grades Summary</h5>
                <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo $term_names[$selected_term]; ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($grades_list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clipboard-x display-4"></i>
                    <p class="mt-2">No grades available yet</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Title</th>
                                <?php if ($selected_term == 'all'): ?>
                                <th class="text-center">Prelim</th>
                                <th class="text-center">Midterm</th>
                                <th class="text-center">Pre-Final</th>
                                <th class="text-center">Final</th>
                                <th class="text-center">Average</th>
                                <?php else: ?>
                                <th class="text-center"><?php echo $term_names[$selected_term]; ?> Grade</th>
                                <?php endif; ?>
                                <th class="text-center">Rating</th>
                                <th class="text-center">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grades_list as $grade): 
                                // Determine grade value and rating
                                if ($selected_term == 'all') {
                                    $display_grade = $grade['final_grade'] ?? 0;
                                } else {
                                    $display_grade = $grade[$selected_term] ?? 0;
                                }
                                
                                // Calculate rating (College scale)
                                $rating = 'N/A';
                                if ($display_grade >= 97) $rating = '1.00';
                                elseif ($display_grade >= 94) $rating = '1.25';
                                elseif ($display_grade >= 91) $rating = '1.50';
                                elseif ($display_grade >= 88) $rating = '1.75';
                                elseif ($display_grade >= 85) $rating = '2.00';
                                elseif ($display_grade >= 82) $rating = '2.25';
                                elseif ($display_grade >= 79) $rating = '2.50';
                                elseif ($display_grade >= 76) $rating = '2.75';
                                elseif ($display_grade >= 75) $rating = '3.00';
                                elseif ($display_grade > 0) $rating = '5.00';
                                
                                $remarks = $display_grade >= 75 ? 'PASSED' : ($display_grade > 0 ? 'FAILED' : 'Pending');
                            ?>
                            <tr>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($grade['subject_code']); ?></span></td>
                                <td><?php echo htmlspecialchars($grade['subject_title']); ?></td>
                                <?php if ($selected_term == 'all'): ?>
                                <td class="text-center"><?php echo ($grade['prelim'] ?? 0) > 0 ? number_format($grade['prelim'], 2) : '-'; ?></td>
                                <td class="text-center"><?php echo ($grade['midterm'] ?? 0) > 0 ? number_format($grade['midterm'], 2) : '-'; ?></td>
                                <td class="text-center"><?php echo ($grade['prefinal'] ?? 0) > 0 ? number_format($grade['prefinal'], 2) : '-'; ?></td>
                                <td class="text-center"><?php echo ($grade['final'] ?? 0) > 0 ? number_format($grade['final'], 2) : '-'; ?></td>
                                <td class="text-center">
                                    <strong class="<?php echo ($grade['final_grade'] >= 75) ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ($grade['final_grade'] ?? 0) > 0 ? number_format($grade['final_grade'], 2) : '-'; ?>
                                    </strong>
                                </td>
                                <?php else: ?>
                                <td class="text-center">
                                    <strong class="<?php echo ($display_grade >= 75) ? 'text-success' : ($display_grade > 0 ? 'text-danger' : 'text-muted'); ?>">
                                        <?php echo $display_grade > 0 ? number_format($display_grade, 2) : '-'; ?>
                                    </strong>
                                </td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <span class="badge <?php echo $display_grade >= 75 ? 'bg-success' : ($display_grade > 0 ? 'bg-danger' : 'bg-secondary'); ?>">
                                        <?php echo $rating; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $remarks == 'PASSED' ? 'bg-success' : ($remarks == 'FAILED' ? 'bg-danger' : 'bg-secondary'); ?>">
                                        <?php echo $remarks; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detailed Grade Components -->
        <?php if (!empty($detailed_grades)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-list-check text-info me-2"></i>Grade Components Breakdown</h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="gradeAccordion">
                    <?php $index = 0; foreach ($detailed_grades as $code => $data): $index++; ?>
                    <div class="accordion-item border-0 mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>">
                                <span class="badge bg-primary me-2"><?php echo htmlspecialchars($code); ?></span>
                                <?php echo htmlspecialchars($data['subject_title']); ?>
                            </button>
                        </h2>
                        <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" data-bs-parent="#gradeAccordion">
                            <div class="accordion-body">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Component</th>
                                            <th>Type</th>
                                            <th class="text-center">Score</th>
                                            <th class="text-center">Max Score</th>
                                            <th class="text-center">Weight</th>
                                            <th class="text-center">Weighted Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_weighted = 0;
                                        foreach ($data['components'] as $comp): 
                                            $percentage = ($comp['score'] / $comp['max_score']) * 100;
                                            $weighted = ($percentage * $comp['weight']) / 100;
                                            $total_weighted += $weighted;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($comp['component_name']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst($comp['component_type']); ?></span></td>
                                            <td class="text-center"><?php echo $comp['score'] !== null ? number_format($comp['score'], 2) : '-'; ?></td>
                                            <td class="text-center"><?php echo number_format($comp['max_score'], 2); ?></td>
                                            <td class="text-center"><?php echo $comp['weight']; ?>%</td>
                                            <td class="text-center"><?php echo number_format($weighted, 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="5" class="text-end fw-bold">Total Weighted Score:</td>
                                            <td class="text-center fw-bold"><?php echo number_format($total_weighted, 2); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Legend -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Grading Scale</h6>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><td>96-100</td><td>1.00 - Excellent</td></tr>
                            <tr><td>91-95</td><td>1.25 - Very Good</td></tr>
                            <tr><td>86-90</td><td>1.50 - Good</td></tr>
                            <tr><td>81-85</td><td>1.75 - Satisfactory</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><td>76-80</td><td>2.00 - Fair</td></tr>
                            <tr><td>75</td><td>2.25 - Passing</td></tr>
                            <tr><td>Below 75</td><td>5.00 - Failed</td></tr>
                            <tr><td>INC</td><td>Incomplete</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
