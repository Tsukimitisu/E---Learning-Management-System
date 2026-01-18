<form class="certificate-form" data-type="<?php echo isset($tabType) ? $tabType : 'enrollment'; ?>">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Student <span class="text-danger">*</span></label>
            <select name="student_id" class="form-select" required>
                <option value="">-- Select Student --</option>
                <?php $students->data_seek(0); while ($student = $students->fetch_assoc()): ?>
                    <option value="<?php echo $student['user_id']; ?>">
                        <?php echo htmlspecialchars($student['student_no'] . ' - ' . $student['full_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Academic Year</label>
            <select name="academic_year" class="form-select">
                <option value="">-- Select Year --</option>
                <?php $academic_years->data_seek(0); while ($ay = $academic_years->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($ay['year_name']); ?>">
                        <?php echo htmlspecialchars($ay['year_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select">
                <option value="">-- Select Semester --</option>
                <option value="1">1st Semester</option>
                <option value="2">2nd Semester</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Purpose</label>
            <select name="purpose" class="form-select">
                <option value="For Employment">For Employment</option>
                <option value="For Scholarship">For Scholarship</option>
                <option value="For Transfer">For Transfer</option>
                <option value="For Records">For Records</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Include Details</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="include_grades" value="1" checked>
                <label class="form-check-label">Include Grades</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="include_attendance" value="1">
                <label class="form-check-label">Include Attendance</label>
            </div>
        </div>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-file-earmark-pdf"></i> Preview & Generate
        </button>
    </div>
</form>
