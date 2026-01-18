-- Migration: Update tables for new section/subject structure
-- Adds section_id and subject_id columns to grades, attendance, and learning_materials tables

-- Add columns to grades table if they don't exist
ALTER TABLE grades 
ADD COLUMN IF NOT EXISTS section_id INT NULL AFTER student_id,
ADD COLUMN IF NOT EXISTS subject_id INT NULL AFTER section_id;

-- Add columns to attendance table if they don't exist
ALTER TABLE attendance 
ADD COLUMN IF NOT EXISTS section_id INT NULL AFTER id,
ADD COLUMN IF NOT EXISTS subject_id INT NULL AFTER section_id;

-- Add columns to learning_materials table if they don't exist
ALTER TABLE learning_materials 
ADD COLUMN IF NOT EXISTS section_id INT NULL AFTER id,
ADD COLUMN IF NOT EXISTS subject_id INT NULL AFTER section_id,
ADD COLUMN IF NOT EXISTS uploaded_by INT NULL;

-- Add columns to grade_locks table if they don't exist
ALTER TABLE grade_locks 
ADD COLUMN IF NOT EXISTS section_id INT NULL AFTER id,
ADD COLUMN IF NOT EXISTS subject_id INT NULL AFTER section_id;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_grades_section_subject ON grades(section_id, subject_id);
CREATE INDEX IF NOT EXISTS idx_attendance_section_subject ON attendance(section_id, subject_id);
CREATE INDEX IF NOT EXISTS idx_materials_section_subject ON learning_materials(section_id, subject_id);
