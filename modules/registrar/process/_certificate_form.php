<?php $formId = 'cert_form_' . ($tabType ?? 'enrollment'); ?>
<form class="certificate-form" data-type="<?php echo isset($tabType) ? $tabType : 'enrollment'; ?>" id="<?php echo $formId; ?>">
    <div class="row g-3">
        <!-- Filters Row -->
        <div class="col-12">
            <label class="form-label fw-semibold"><i class="bi bi-funnel me-1"></i>Filter Students</label>
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select form-select-sm cert-filter-type" data-form="<?php echo $formId; ?>">
                        <option value="">All Types</option>
                        <option value="college">College</option>
                        <option value="shs">Senior High School</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm cert-filter-program" data-form="<?php echo $formId; ?>">
                        <option value="">All Programs / Strands</option>
                        <optgroup label="College Programs" class="filter-programs-group"></optgroup>
                        <optgroup label="SHS Strands" class="filter-strands-group"></optgroup>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm cert-filter-year" data-form="<?php echo $formId; ?>">
                        <option value="">All Year/Grade</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm cert-filter-semester" data-form="<?php echo $formId; ?>">
                        <option value="">All Semesters</option>
                        <option value="1">1st Semester</option>
                        <option value="2">2nd Semester</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0 cert-search-input" 
                               data-form="<?php echo $formId; ?>" 
                               placeholder="Search...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Selection -->
        <div class="col-12">
            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
            <input type="hidden" name="student_id" class="cert-student-id" required>
            <div class="cert-student-selected d-none p-3 rounded-3 border bg-light" data-form="<?php echo $formId; ?>">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-person-check-fill text-success me-2"></i>
                        <span class="fw-bold selected-student-name"></span>
                        <span class="text-muted ms-2 selected-student-no"></span>
                        <span class="badge bg-primary ms-2 selected-student-program"></span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger cert-clear-student" data-form="<?php echo $formId; ?>">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="cert-student-list border rounded-3 mt-1" data-form="<?php echo $formId; ?>" style="max-height: 260px; overflow-y: auto;">
                <div class="text-center text-muted py-4 cert-loading">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading students...
                </div>
            </div>
        </div>

        <!-- Certificate Options -->
        <div class="col-md-4">
            <label class="form-label fw-semibold">Academic Year</label>
            <select name="academic_year" class="form-select">
                <option value="">-- Auto from enrollment --</option>
                <?php $academic_years->data_seek(0); while ($ay = $academic_years->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($ay['year_name']); ?>">
                        <?php echo htmlspecialchars($ay['year_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Semester</label>
            <select name="semester" class="form-select">
                <option value="">-- Auto from enrollment --</option>
                <option value="1">1st Semester</option>
                <option value="2">2nd Semester</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Purpose</label>
            <select name="purpose" class="form-select">
                <option value="For Employment">For Employment</option>
                <option value="For Scholarship">For Scholarship</option>
                <option value="For Transfer">For Transfer</option>
                <option value="For Records">For Records</option>
            </select>
        </div>
        <?php if ($tabType === 'grade_report'): ?>
        <div class="col-md-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="include_grades" value="1" checked>
                <label class="form-check-label">Include detailed grades</label>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-primary px-4 py-2" disabled>
            <i class="bi bi-file-earmark-pdf me-1"></i> Preview & Generate PDF
        </button>
    </div>
</form>
