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