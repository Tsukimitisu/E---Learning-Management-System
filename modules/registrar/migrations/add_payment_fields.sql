-- Add reference number for tracking
ALTER TABLE payments 
ADD COLUMN reference_no VARCHAR(50) UNIQUE AFTER id,
ADD COLUMN payment_method ENUM('cash','bank_transfer','online','check') DEFAULT 'cash' AFTER amount,
ADD COLUMN verified_by INT(10) UNSIGNED NULL AFTER status,
ADD COLUMN verified_at DATETIME NULL AFTER verified_by,
ADD COLUMN rejection_reason TEXT NULL AFTER verified_at,
ADD INDEX idx_student_status (student_id, status),
ADD INDEX idx_created_at (created_at),
ADD FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;

-- Add payment tracking to enrollments
ALTER TABLE enrollments
ADD COLUMN payment_verified TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN payment_amount DECIMAL(10,2) NULL AFTER payment_verified;
