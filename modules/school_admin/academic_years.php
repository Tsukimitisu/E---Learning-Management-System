<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Academic Years Management";

// Fetch all academic years
$academic_years_query = "
    SELECT 
        ay.*, 
        (SELECT COUNT(DISTINCT ss.student_id) 
         FROM section_students ss 
         INNER JOIN sections s ON ss.section_id = s.id 
         WHERE s.academic_year_id = ay.id) as total_students,
        (SELECT COUNT(*) FROM sections s WHERE s.academic_year_id = ay.id) as total_sections
    FROM academic_years ay 
    ORDER BY ay.year_start DESC
";
$academic_years_result = $conn->query($academic_years_query);

// Fetch grading terms configuration
$grading_terms_result = $conn->query("SELECT * FROM grading_terms WHERE is_active = 1 ORDER BY term_order");

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_academic_year') {
        $year_name = clean_input($_POST['year_name']);
        $year_start = (int)$_POST['year_start'];
        $year_end = (int)$_POST['year_end'];
        $start_date = clean_input($_POST['start_date']);
        $end_date = clean_input($_POST['end_date']);
        
        if (empty($year_name) || $year_start == 0 || $year_end == 0) {
            $message = 'Please fill all required fields.';
            $message_type = 'danger';
        } else {
            $check = $conn->query("SELECT id FROM academic_years WHERE year_name = '$year_name'");
            if ($check->num_rows > 0) {
                $message = 'Academic year with this name already exists.';
                $message_type = 'warning';
            } else {
                $stmt = $conn->prepare("INSERT INTO academic_years (year_name, year_start, year_end, start_date, end_date, is_active, is_enrollment_open) VALUES (?, ?, ?, ?, ?, 0, 0)");
                $stmt->bind_param("siiss", $year_name, $year_start, $year_end, $start_date, $end_date);
                if ($stmt->execute()) {
                    $message = 'Academic year created successfully!';
                    $message_type = 'success';
                    header("Refresh:1");
                } else {
                    $message = 'Error creating academic year: ' . $conn->error;
                    $message_type = 'danger';
                }
            }
        }
    } elseif ($action === 'set_active') {
        $ay_id = (int)$_POST['academic_year_id'];
        
        // First deactivate all
        $conn->query("UPDATE academic_years SET is_active = 0");
        
        // Then activate selected
        $stmt = $conn->prepare("UPDATE academic_years SET is_active = 1 WHERE id = ?");
        $stmt->bind_param("i", $ay_id);
        if ($stmt->execute()) {
            $message = 'Academic year activated successfully!';
            $message_type = 'success';
            header("Refresh:1");
        } else {
            $message = 'Error activating academic year.';
            $message_type = 'danger';
        }
    } elseif ($action === 'update_terms') {
        $term_names = $_POST['term_name'] ?? [];
        $term_weights = $_POST['term_weight'] ?? [];
        
        $total_weight = array_sum($term_weights);
        if ($total_weight != 100) {
            $message = 'Total weight of all terms must equal 100%. Current total: ' . $total_weight . '%';
            $message_type = 'warning';
        } else {
            $conn->query("UPDATE grading_terms SET is_active = 0");
            
            $term_order = 1;
            foreach ($term_names as $key => $name) {
                $name = clean_input($name);
                $weight = (int)$term_weights[$key];
                
                if (!empty($name) && $weight > 0) {
                    $stmt = $conn->prepare("INSERT INTO grading_terms (term_name, term_order, weight, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE term_order = ?, weight = ?, is_active = 1");
                    $stmt->bind_param("siiii", $name, $term_order, $weight, $term_order, $weight);
                    $stmt->execute();
                    $term_order++;
                }
            }
            $message = 'Grading terms updated successfully!';
            $message_type = 'success';
            header("Refresh:1");
        }
    }
}

include '../../includes/header.php';
?>

<style>
    .stat-card {
        padding: 20px;
        border-radius: 15px;
        color: white;
        text-align: center;
        transition: transform 0.3s;
    }
    .stat-card:hover { transform: translateY(-5px); }
    .stat-card h3 { font-size: 2rem; font-weight: 700; }
    .stat-card p { margin-bottom: 0; opacity: 0.9; }
    .ay-card {
        border-radius: 15px;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: 0.3s;
    }
    .ay-card:hover { transform: translateY(-3px); }
    .ay-card.active { border-left: 5px solid #28a745; }
    .term-row { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 10px; }
</style>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-calendar-range me-2"></i>Academic Years Management
            </h4>
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createAYModal">
                <i class="bi bi-plus-lg me-1"></i> New Academic Year
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Academic Years List -->
        <div class="row mb-4">
            <?php 
            $academic_years_result->data_seek(0);
            while ($ay = $academic_years_result->fetch_assoc()): 
            ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card ay-card <?php echo $ay['is_active'] ? 'active' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($ay['year_name']); ?></h5>
                                <span class="badge <?php echo $ay['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $ay['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if (!$ay['is_active']): ?>
                                    <li>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="set_active">
                                            <input type="hidden" name="academic_year_id" value="<?php echo $ay['id']; ?>">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-check-circle text-success me-2"></i> Set as Active
                                            </button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-pencil me-2"></i> Edit</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="bg-light rounded p-2">
                                    <h5 class="mb-0 text-primary"><?php echo number_format($ay['total_students']); ?></h5>
                                    <small class="text-muted">Students</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-light rounded p-2">
                                    <h5 class="mb-0 text-info"><?php echo number_format($ay['total_sections']); ?></h5>
                                    <small class="text-muted">Sections</small>
                                </div>
                            </div>
                        </div>
                        <?php if ($ay['start_date'] && $ay['end_date']): ?>
                        <div class="mt-3 text-center text-muted small">
                            <i class="bi bi-calendar3 me-1"></i>
                            <?php echo date('M d, Y', strtotime($ay['start_date'])); ?> - <?php echo date('M d, Y', strtotime($ay['end_date'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Grading Terms Configuration -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold"><i class="bi bi-sliders text-primary me-2"></i>Grading Terms Configuration</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">Configure the grading terms and their weights. The total weight must equal 100%.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_terms">
                    
                    <div id="termsContainer">
                        <?php 
                        $grading_terms_result->data_seek(0);
                        $term_count = 0;
                        while ($term = $grading_terms_result->fetch_assoc()):
                            $term_count++;
                        ?>
                        <div class="term-row d-flex align-items-center gap-3">
                            <span class="badge bg-primary rounded-pill"><?php echo $term['term_order']; ?></span>
                            <input type="text" name="term_name[]" class="form-control" value="<?php echo htmlspecialchars($term['term_name']); ?>" style="max-width: 200px;" placeholder="Term Name">
                            <div class="input-group" style="max-width: 150px;">
                                <input type="number" name="term_weight[]" class="form-control term-weight" value="<?php echo $term['weight']; ?>" min="0" max="100">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        
                        <?php if ($term_count == 0): ?>
                        <!-- Default terms if none exist -->
                        <?php 
                        $default_terms = ['Prelim', 'Midterm', 'Pre-Finals', 'Finals'];
                        foreach ($default_terms as $idx => $term_name):
                        ?>
                        <div class="term-row d-flex align-items-center gap-3">
                            <span class="badge bg-primary rounded-pill"><?php echo $idx + 1; ?></span>
                            <input type="text" name="term_name[]" class="form-control" value="<?php echo $term_name; ?>" style="max-width: 200px;" placeholder="Term Name">
                            <div class="input-group" style="max-width: 150px;">
                                <input type="number" name="term_weight[]" class="form-control term-weight" value="25" min="0" max="100">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <div>
                            <strong>Total Weight: </strong>
                            <span id="totalWeight" class="badge bg-success fs-6">100%</span>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-save me-1"></i> Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Create Academic Year Modal -->
<div class="modal fade" id="createAYModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Create Academic Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_academic_year">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Academic Year Name</label>
                        <input type="text" name="year_name" class="form-control" placeholder="e.g., 2025-2026" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Year</label>
                            <input type="number" name="year_start" class="form-control" min="2020" max="2050" value="<?php echo date('Y'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Year</label>
                            <input type="number" name="year_end" class="form-control" min="2020" max="2050" value="<?php echo date('Y') + 1; ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Calculate total weight on input change
document.querySelectorAll('.term-weight').forEach(input => {
    input.addEventListener('input', calculateTotal);
});

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.term-weight').forEach(input => {
        total += parseInt(input.value) || 0;
    });
    const badge = document.getElementById('totalWeight');
    badge.textContent = total + '%';
    badge.className = 'badge fs-6 ' + (total === 100 ? 'bg-success' : 'bg-danger');
}
calculateTotal();
</script>
