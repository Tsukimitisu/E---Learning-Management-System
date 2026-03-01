-- Migration: Add submission columns to assessment_scores
-- Allows students to upload files and submit assessments

ALTER TABLE `assessment_scores`
    ADD COLUMN `submitted_file` VARCHAR(255) DEFAULT NULL AFTER `feedback`,
    ADD COLUMN `submitted_at` DATETIME DEFAULT NULL AFTER `submitted_file`,
    ADD COLUMN `student_notes` TEXT DEFAULT NULL AFTER `submitted_at`;
