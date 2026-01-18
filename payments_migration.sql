-- Migration: Add payment recording fields
-- Run this migration to update the payments table for OR-based payment recording

-- Add new columns to payments table
ALTER TABLE `payments` 
ADD COLUMN `or_number` VARCHAR(50) DEFAULT NULL COMMENT 'Official Receipt Number' AFTER `status`,
ADD COLUMN `payment_type` ENUM('tuition', 'miscellaneous', 'laboratory', 'library', 'registration', 'id_card', 'diploma', 'transcript', 'clearance', 'other') DEFAULT 'tuition' AFTER `or_number`,
ADD COLUMN `description` TEXT DEFAULT NULL AFTER `payment_type`,
ADD COLUMN `academic_year_id` INT(10) UNSIGNED DEFAULT NULL AFTER `description`,
ADD COLUMN `semester` ENUM('1st', '2nd', 'summer') DEFAULT NULL AFTER `academic_year_id`,
ADD COLUMN `branch_id` INT(10) UNSIGNED DEFAULT NULL AFTER `semester`,
ADD COLUMN `recorded_by` INT(10) UNSIGNED DEFAULT NULL COMMENT 'User ID of registrar who recorded payment' AFTER `branch_id`,
ADD COLUMN `reference_no` VARCHAR(50) DEFAULT NULL COMMENT 'System generated reference' AFTER `recorded_by`,
ADD COLUMN `payment_method` ENUM('cash', 'bank_transfer', 'gcash', 'maya', 'check', 'other') DEFAULT 'cash' AFTER `reference_no`,
ADD COLUMN `verified_by` INT(10) UNSIGNED DEFAULT NULL COMMENT 'User ID who verified payment' AFTER `payment_method`,
ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `verified_by`;

-- Add index for faster lookups
ALTER TABLE `payments` 
ADD INDEX `idx_payments_or_number` (`or_number`),
ADD INDEX `idx_payments_branch` (`branch_id`),
ADD INDEX `idx_payments_type` (`payment_type`),
ADD INDEX `idx_payments_recorded_by` (`recorded_by`);

-- Add foreign key constraints (optional, depends on your setup)
-- ALTER TABLE `payments` ADD CONSTRAINT `fk_payments_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL;
-- ALTER TABLE `payments` ADD CONSTRAINT `fk_payments_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
-- ALTER TABLE `payments` ADD CONSTRAINT `fk_payments_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE SET NULL;
