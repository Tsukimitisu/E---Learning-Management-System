-- Migration: Update assessments to use new section/subject structure instead of legacy classes table
-- Add section_id and curriculum_subject_id columns to assessments table

ALTER TABLE assessments 
    ADD COLUMN section_id INT(10) UNSIGNED NULL AFTER class_id,
    ADD COLUMN curriculum_subject_id INT(10) UNSIGNED NULL AFTER section_id;

-- Make class_id nullable since new assessments will use section_id + curriculum_subject_id
ALTER TABLE assessments MODIFY COLUMN class_id INT(10) UNSIGNED NULL;
