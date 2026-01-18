<!-- Add Track Modal -->
<div class="modal fade" id="addTrackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Academic Track</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addTrackForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Track Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="track_name" required placeholder="e.g. STEM, ABM, HUMSS">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Track Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="track_code" required placeholder="e.g. STEM1">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Written Work % <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="written_work_weight" value="30" min="0" max="100" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Performance % <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="performance_task_weight" value="50" min="0" max="100" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Quarterly Exam % <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="quarterly_exam_weight" value="20" min="0" max="100" required>
                        </div>
                    </div>
                    <small class="text-muted d-block mb-2">Percentages must total 100%</small>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Add Track
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Track Modal -->
<div class="modal fade" id="editTrackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Academic Track</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTrackForm">
                <input type="hidden" name="track_id" id="editTrackId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Track Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="track_name" id="editTrackName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editTrackDescription" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editTrackStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save"></i> Update Track
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Strand Modal -->
<div class="modal fade" id="addStrandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Academic Strand</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStrandForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Track <span class="text-danger">*</span></label>
                        <select class="form-select" name="track_id" required>
                            <option value="">-- Select Track --</option>
                            <?php foreach ($tracks as $track): ?>
                            <option value="<?php echo $track['id']; ?>"><?php echo htmlspecialchars($track['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Strand Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="strand_name" required placeholder="e.g. Biology, Chemistry">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Strand Code</label>
                        <input type="text" class="form-control" name="strand_code" placeholder="e.g. BIO1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Add Strand
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Strand Modal -->
<div class="modal fade" id="editStrandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Academic Strand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStrandForm">
                <input type="hidden" name="strand_id" id="editStrandId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Track <span class="text-danger">*</span></label>
                        <select class="form-select" name="track_id" id="editStrandTrack" required>
                            <option value="">-- Select Track --</option>
                            <?php foreach ($tracks as $track): ?>
                            <option value="<?php echo $track['id']; ?>"><?php echo htmlspecialchars($track['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Strand Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="strand_name" id="editStrandName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editStrandDescription" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editStrandStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save"></i> Update Strand
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Grade Level Modal -->
<div class="modal fade" id="addGradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Grade Level</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addGradeForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Grade Level Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="grade_name" required placeholder="e.g. Grade 11, Grade 12">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Semesters <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="semesters" value="2" min="1" max="4" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-save"></i> Add Grade Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Grade Level Modal -->
<div class="modal fade" id="editGradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Grade Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editGradeForm">
                <input type="hidden" name="grade_id" id="editGradeId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Grade Level Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="grade_name" id="editGradeName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Semesters <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="semesters_count" id="editGradeSemesters" min="1" max="4" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editGradeStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save"></i> Update Grade Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #800000;">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSubjectForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject_code" required placeholder="e.g. STEM101">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject_title" required placeholder="e.g. Biology">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Units/Credits <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="units" value="3" min="0.5" max="6" step="0.5" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="subject_type" id="subjectTypeSelect" required onchange="updateSubjectForm()">
                                <option value="">-- Select Type --</option>
                                <option value="shs_core">SHS Core</option>
                                <option value="shs_applied">SHS Applied</option>
                                <option value="shs_specialized">SHS Specialized</option>
                                <option value="college">College</option>
                            </select>
                        </div>
                    </div>

                    <!-- SHS Fields -->
                    <div id="shsFields" style="display:none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lecture Hours</label>
                                <input type="number" class="form-control" name="shs_lecture_hours" min="0" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lab Hours</label>
                                <input type="number" class="form-control" name="shs_lab_hours" min="0" value="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Strand <span class="text-danger">*</span></label>
                                <select class="form-select" name="shs_strand_id">
                                    <option value="">-- Select Strand --</option>
                                    <?php 
                                    $strands_result = $conn->query("SELECT id, strand_name FROM shs_strands WHERE is_active = 1 ORDER BY strand_name");
                                    while ($strand = $strands_result->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $strand['id']; ?>"><?php echo htmlspecialchars($strand['strand_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Grade Level <span class="text-danger">*</span></label>
                                <select class="form-select" name="shs_grade_level_id">
                                    <option value="">-- Select Grade Level --</option>
                                    <?php 
                                    $grades_result = $conn->query("SELECT id, grade_name FROM shs_grade_levels WHERE is_active = 1 ORDER BY grade_level");
                                    while ($grade = $grades_result->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Semester</label>
                            <select class="form-select" name="shs_semester">
                                <option value="1">1st Semester</option>
                                <option value="2">2nd Semester</option>
                            </select>
                        </div>
                    </div>

                    <!-- College Fields -->
                    <div id="collegeFields" style="display:none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lecture Hours</label>
                                <input type="number" class="form-control" name="college_lecture_hours" min="0" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lab Hours</label>
                                <input type="number" class="form-control" name="college_lab_hours" min="0" value="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Program</label>
                                <select class="form-select" name="program_id">
                                    <option value="">-- Optional --</option>
                                    <?php 
                                    $programs_result = $conn->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1 ORDER BY program_code");
                                    while ($prog = $programs_result->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $prog['id']; ?>"><?php echo htmlspecialchars($prog['program_code'] . ' - ' . $prog['program_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year Level</label>
                                <select class="form-select" name="year_level_id">
                                    <option value="">-- Optional --</option>
                                    <?php 
                                    $yearlevels_result = $conn->query("SELECT id, year_name, program_id FROM program_year_levels WHERE is_active = 1 ORDER BY year_level");
                                    while ($yl = $yearlevels_result->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $yl['id']; ?>"><?php echo htmlspecialchars($yl['year_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Semester</label>
                            <select class="form-select" name="college_semester">
                                <option value="1">1st Semester</option>
                                <option value="2">2nd Semester</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Prerequisites</label>
                        <input type="text" class="form-control" name="prerequisites" placeholder="e.g. STEM100 or None">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-save"></i> Add Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add College Course Modal -->
<div class="modal fade" id="addCollegeCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add College Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCollegeCourseForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="course_code" required placeholder="e.g. CS101">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" required>
                                <option value="major">Major Course</option>
                                <option value="general">General Education</option>
                                <option value="elective">Elective</option>
                                <option value="prerequisite">Prerequisite</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="course_title" required placeholder="e.g. Data Structures">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Credit Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="units" value="3" min="0" max="6" step="0.5" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lecture Hours</label>
                            <input type="number" class="form-control" name="lecture_hours" value="3" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Laboratory Hours</label>
                            <input type="number" class="form-control" name="lab_hours" value="0" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prerequisites</label>
                            <input type="text" class="form-control" name="prerequisites" placeholder="e.g. CS100 or None">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-save"></i> Add Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Subject to SHS Modal -->
<div class="modal fade" id="assignSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-link"></i> Assign Subject (SHS)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignSubjectForm">
                <div class="modal-body">
                    <input type="hidden" name="subject_id" />
                    <div class="mb-3">
                        <label class="form-label">Strand <span class="text-danger">*</span></label>
                        <select class="form-select" name="shs_strand_id" required>
                            <option value="">-- Select Strand --</option>
                            <?php foreach ($strands as $strand): ?>
                            <option value="<?php echo $strand['id']; ?>"><?php echo htmlspecialchars($strand['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Grade Level <span class="text-danger">*</span></label>
                        <select class="form-select" name="shs_grade_level_id" required>
                            <option value="">-- Select Grade Level --</option>
                            <?php foreach ($grade_levels as $grade): ?>
                            <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semester <span class="text-danger">*</span></label>
                        <select class="form-select" name="semester" required>
                            <option value="1">1st Semester</option>
                            <option value="2">2nd Semester</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="subject_type" required>
                            <option value="shs_core">SHS Core</option>
                            <option value="shs_applied">SHS Applied</option>
                            <option value="shs_specialized">SHS Specialized</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save"></i> Assign Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign College Course Modal -->
<div class="modal fade" id="assignCollegeCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-link"></i> Assign College Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignCollegeCourseForm">
                <div class="modal-body">
                    <input type="hidden" name="course_id" />
                    <div class="mb-3">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" name="program_id" required>
                            <option value="">-- Select Program --</option>
                            <?php foreach ($college_programs as $prog): ?>
                            <?php
                                $pcode = $prog['code'] ?? ($prog['program_code'] ?? '');
                                $pname = $prog['name'] ?? ($prog['program_name'] ?? '');
                                $label = trim($pcode . ' - ' . $pname, ' -');
                                if ($label === '') { $label = $pcode ?: ($pname ?: 'Program #' . ($prog['id'] ?? '')); }
                            ?>
                            <option value="<?php echo $prog['id']; ?>"><?php echo htmlspecialchars((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <select class="form-select" name="subject_id" required>
                            <option value="">-- Select Subject --</option>
                            <?php $subjects = (isset($college_subjects) && is_array($college_subjects)) ? $college_subjects : []; ?>
                            <?php foreach ($subjects as $subj): ?>
                            <?php
                                $scode = $subj['subject_code'] ?? '';
                                $stitle = $subj['subject_title'] ?? '';
                                $label = trim($scode . ' - ' . $stitle, ' -');
                                if ($label === '') { $label = $scode ?: ($stitle ?: 'Subject #' . ($subj['id'] ?? '')); }
                            ?>
                            <option value="<?php echo $subj['id']; ?>"><?php echo htmlspecialchars((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year Level <span class="text-danger">*</span></label>
                        <select class="form-select" name="year_level_id" required>
                            <option value="">-- Select Year Level --</option>
                            <?php foreach ($college_year_levels as $yl): ?>
                            <?php $yname = $yl['name'] ?? ($yl['year_name'] ?? '');
                                  $ylabel = $yname !== '' ? $yname : ('Year #' . ($yl['id'] ?? ''));
                            ?>
                            <option value="<?php echo $yl['id']; ?>"><?php echo htmlspecialchars((string)$ylabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semester <span class="text-danger">*</span></label>
                        <select class="form-select" name="semester" required>
                            <option value="1">1st Semester</option>
                            <option value="2">2nd Semester</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-save"></i> Assign Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

        <!-- Add College Subject Modal -->
        <div class="modal fade" id="addCollegeSubjectModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add College Subject</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="addCollegeSubjectForm">
                        <input type="hidden" name="subject_type" value="college">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="subject_code" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subject Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="subject_title" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Units <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="units" value="3" min="0" step="0.5" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Lecture Hours (per week)</label>
                                    <input type="number" class="form-control" name="lecture_hours" value="3" min="0">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Lab Hours (per week)</label>
                                    <input type="number" class="form-control" name="lab_hours" value="0" min="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Semester</label>
                                    <select class="form-select" name="college_semester">
                                        <option value="1">1st Semester</option>
                                        <option value="2">2nd Semester</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Program</label>
                                    <select class="form-select" name="program_id">
                                        <option value="">-- Select Program --</option>
                                        <?php 
                                        $programs_result = $conn->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1 ORDER BY program_code");
                                        while ($prog = $programs_result->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $prog['id']; ?>"><?php echo htmlspecialchars($prog['program_code'] . ' - ' . $prog['program_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Year Level</label>
                                    <select class="form-select" name="year_level_id">
                                        <option value="">-- Select Year Level --</option>
                                        <?php 
                                        $yearlevels_result = $conn->query("SELECT id, year_name, program_id FROM program_year_levels WHERE is_active = 1 ORDER BY year_level");
                                        while ($yl = $yearlevels_result->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $yl['id']; ?>"><?php echo htmlspecialchars($yl['year_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Prerequisites</label>
                                <input type="text" class="form-control" name="prerequisites" placeholder="Optional">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Add Subject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit College Subject Modal -->
        <div class="modal fade" id="editCollegeSubjectModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit College Subject</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="editCollegeSubjectForm">
                        <input type="hidden" name="subject_id" id="editCollegeSubjectId">
                        <input type="hidden" name="subject_type" value="college">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editCollegeSubjectCode" name="subject_code" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subject Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editCollegeSubjectTitle" name="subject_title" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Units <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="editCollegeSubjectUnits" name="units" min="0" step="0.5" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Lecture Hours</label>
                                    <input type="number" class="form-control" id="editCollegeSubjectLectureHours" name="college_lecture_hours" min="0">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Lab Hours</label>
                                    <input type="number" class="form-control" id="editCollegeSubjectLabHours" name="college_lab_hours" min="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Semester</label>
                                    <select class="form-select" id="editCollegeSubjectSemester" name="college_semester">
                                        <option value="1">1st Semester</option>
                                        <option value="2">2nd Semester</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Program</label>
                                    <select class="form-select" id="editCollegeSubjectProgram" name="program_id">
                                        <option value="">-- Select Program --</option>
                                        <?php 
                                        $programs_result = $conn->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1 ORDER BY program_code");
                                        while ($prog = $programs_result->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $prog['id']; ?>"><?php echo htmlspecialchars($prog['program_code'] . ' - ' . $prog['program_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Year Level</label>
                                    <select class="form-select" id="editCollegeSubjectYearLevel" name="year_level_id">
                                        <option value="">-- Select Year Level --</option>
                                        <?php 
                                        $yearlevels_result = $conn->query("SELECT id, year_name FROM program_year_levels WHERE is_active = 1 ORDER BY year_level");
                                        while ($yl = $yearlevels_result->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $yl['id']; ?>"><?php echo htmlspecialchars($yl['year_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Prerequisites</label>
                                <input type="text" class="form-control" id="editCollegeSubjectPrerequisites" name="prerequisites">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="editCollegeSubjectStatus" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-save"></i> Update Subject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Assign College Subject Modal -->
        <div class="modal fade" id="assignCollegeSubjectModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="bi bi-link"></i> Assign College Subject</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="assignCollegeSubjectForm">
                        <input type="hidden" name="subject_id" />
                        <input type="hidden" name="semester" value="1" />
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Program / Course <span class="text-danger">*</span></label>
                                <select class="form-select" name="program_id" required>
                                    <option value="">-- Select Program --</option>
                                    <?php foreach ($college_programs as $prog): ?>
                                    <option value="<?php echo $prog['id']; ?>"><?php echo htmlspecialchars($prog['code'] . ' - ' . $prog['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Year Level <span class="text-danger">*</span></label>
                                <select class="form-select" name="year_level_id" required>
                                    <option value="">-- Select Year Level --</option>
                                    <?php foreach ($college_year_levels as $yl): ?>
                                    <option value="<?php echo $yl['id']; ?>"><?php echo htmlspecialchars($yl['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-info">
                                <i class="bi bi-save"></i> Assign Subject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


<!-- Add Program Modal -->
<!-- Add Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add College Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addProgramForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Program Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="program_code" required placeholder="e.g. BSIT, BSCS">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Program Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="program_name" required placeholder="e.g. Bachelor of Science in Information Technology">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Degree Level <span class="text-danger">*</span></label>
                        <select class="form-select" name="degree_level" required>
                            <option value="Certificate">Certificate</option>
                            <option value="Associate">Associate</option>
                            <option value="Bachelor" selected>Bachelor</option>
                            <option value="Master">Master</option>
                            <option value="Doctorate">Doctorate</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School <span class="text-danger">*</span></label>
                        <select class="form-select" name="school_id" required>
                            <option value="">-- Select School --</option>
                            <?php
                            $schools_result = $conn->query("SELECT id, name FROM schools ORDER BY name");
                            while ($school = $schools_result->fetch_assoc()) {
                                echo "<option value='{$school['id']}'>{$school['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-save"></i> Add Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add College Year Modal -->
<div class="modal fade" id="addCollegeYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add College Year Level</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCollegeYearForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" name="program_id" required>
                            <option value="">-- Select Program --</option>
                            <?php
                            $programs_result = $conn->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1 ORDER BY program_code");
                            while ($program = $programs_result->fetch_assoc()) {
                                echo "<option value='{$program['id']}'>{$program['program_code']} - {$program['program_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year Level <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="year_level" required min="1" max="6" placeholder="e.g. 1, 2, 3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="year_name" required placeholder="e.g. 1st Year, 2nd Year">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Semesters <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="semesters_count" required min="1" max="4" value="2">
                        <small class="text-muted">How many semesters in this year level?</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-save"></i> Add Year Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit College Year Modal -->
<div class="modal fade" id="editCollegeYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit College Year Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCollegeYearForm">
                <input type="hidden" name="year_id" id="editYearId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Year Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="year_name" id="editYearName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year Level <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="year_level" id="editYearNumber" min="1" max="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Semesters <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="semesters_count" id="editYearSemesters" min="1" max="4" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editYearStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save"></i> Update Year Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Program Modal -->
<div class="modal fade" id="editProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit College Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProgramForm">
                <input type="hidden" name="program_id" id="editProgramId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Program Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="program_code" id="editProgramCode" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Program Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="program_name" id="editProgramName" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Degree Level <span class="text-danger">*</span></label>
                            <select class="form-select" name="degree_level" id="editProgramLevel" required>
                                <option value="Certificate">Certificate</option>
                                <option value="Associate">Associate</option>
                                <option value="Bachelor">Bachelor</option>
                                <option value="Master">Master</option>
                                <option value="Doctorate">Doctorate</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration (years)</label>
                            <input type="number" class="form-control" name="duration_years" id="editProgramDuration" min="1" max="10">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Required Units</label>
                        <input type="number" class="form-control" name="total_units" id="editProgramUnits" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editProgramDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="editProgramStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save"></i> Update Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
