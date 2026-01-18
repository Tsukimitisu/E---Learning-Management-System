-- Curriculum Management System Migration
-- Run this script to add the missing tables for the School Administrator curriculum management system

-- Create program_year_levels table
CREATE TABLE IF NOT EXISTS `program_year_levels` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `program_id` int(10) UNSIGNED NOT NULL,
  `year_level` tinyint(3) UNSIGNED NOT NULL,
  `year_name` varchar(20) NOT NULL,
  `semesters_count` tinyint(3) UNSIGNED DEFAULT 2,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_program_year` (`program_id`,`year_level`),
  KEY `fk_yearlevel_program` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create shs_strands table
CREATE TABLE IF NOT EXISTS `shs_strands` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `track_id` int(10) UNSIGNED NOT NULL,
  `strand_code` varchar(20) NOT NULL,
  `strand_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `strand_code` (`strand_code`),
  KEY `fk_strand_track` (`track_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create shs_grade_levels table
CREATE TABLE IF NOT EXISTS `shs_grade_levels` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `strand_id` int(10) UNSIGNED NOT NULL,
  `grade_level` tinyint(3) UNSIGNED NOT NULL,
  `grade_name` varchar(20) NOT NULL,
  `semesters_count` tinyint(3) UNSIGNED DEFAULT 2,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_strand_grade` (`strand_id`,`grade_level`),
  KEY `fk_gradelevel_strand` (`strand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create curriculum_subjects table
CREATE TABLE IF NOT EXISTS `curriculum_subjects` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(20) NOT NULL,
  `subject_title` varchar(100) NOT NULL,
  `units` decimal(3,1) DEFAULT 3.0,
  `lecture_hours` int(10) UNSIGNED DEFAULT 0,
  `lab_hours` int(10) UNSIGNED DEFAULT 0,
  `subject_type` enum('college','shs_core','shs_applied','shs_specialized') NOT NULL,
  `program_id` int(10) UNSIGNED DEFAULT NULL,
  `year_level_id` int(10) UNSIGNED DEFAULT NULL,
  `shs_strand_id` int(10) UNSIGNED DEFAULT NULL,
  `shs_grade_level_id` int(10) UNSIGNED DEFAULT NULL,
  `semester` tinyint(3) UNSIGNED DEFAULT 1,
  `prerequisites` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_code` (`subject_code`),
  KEY `idx_program_year` (`program_id`,`year_level_id`,`semester`),
  KEY `idx_shs_strand_grade` (`shs_strand_id`,`shs_grade_level_id`,`semester`),
  KEY `fk_curriculum_program` (`program_id`),
  KEY `fk_curriculum_yearlevel` (`year_level_id`),
  KEY `fk_curriculum_shs_strand` (`shs_strand_id`),
  KEY `fk_curriculum_shs_gradelevel` (`shs_grade_level_id`),
  KEY `fk_curriculum_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update classes table to use curriculum_subject_id
ALTER TABLE `classes`
  ADD COLUMN IF NOT EXISTS `curriculum_subject_id` int(10) UNSIGNED DEFAULT NULL AFTER `subject_id`,
  ADD KEY IF NOT EXISTS `idx_curriculum_subject` (`curriculum_subject_id`);

-- Update grades table to add version column for optimistic locking
ALTER TABLE `grades`
  ADD COLUMN IF NOT EXISTS `version` int(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `remarks`;

-- Insert sample data for existing tracks
INSERT IGNORE INTO `shs_strands` (`id`, `track_id`, `strand_code`, `strand_name`, `description`, `is_active`, `created_at`) VALUES
(1, 1, 'STEM', 'Science, Technology, Engineering and Mathematics', 'Focuses on scientific and technical skills', 1, NOW()),
(2, 1, 'ABM', 'Accountancy, Business and Management', 'Prepares students for business and finance careers', 1, NOW()),
(3, 1, 'HUMSS', 'Humanities and Social Sciences', 'Develops critical thinking and communication skills', 1, NOW()),
(4, 1, 'GAS', 'General Academic Strand', 'Provides a general education foundation', 1, NOW()),
(5, 2, 'ICT', 'Information and Communications Technology', 'Technical skills in IT and programming', 1, NOW()),
(6, 2, 'HE', 'Home Economics', 'Culinary arts and hospitality management', 1, NOW()),
(7, 3, 'VA', 'Visual Arts', 'Creative expression through visual media', 1, NOW()),
(8, 4, 'SP', 'Sports', 'Athletic training and sports science', 1, NOW());

-- Insert sample grade levels
INSERT IGNORE INTO `shs_grade_levels` (`id`, `strand_id`, `grade_level`, `grade_name`, `semesters_count`, `is_active`, `created_at`) VALUES
(1, 1, 11, 'Grade 11', 2, 1, NOW()),
(2, 1, 12, 'Grade 12', 2, 1, NOW()),
(3, 2, 11, 'Grade 11', 2, 1, NOW()),
(4, 2, 12, 'Grade 12', 2, 1, NOW()),
(5, 3, 11, 'Grade 11', 2, 1, NOW()),
(6, 3, 12, 'Grade 12', 2, 1, NOW()),
(7, 4, 11, 'Grade 11', 2, 1, NOW()),
(8, 4, 12, 'Grade 12', 2, 1, NOW()),
(9, 5, 11, 'Grade 11', 2, 1, NOW()),
(10, 5, 12, 'Grade 12', 2, 1, NOW()),
(11, 6, 11, 'Grade 11', 2, 1, NOW()),
(12, 6, 12, 'Grade 12', 2, 1, NOW()),
(13, 7, 11, 'Grade 11', 2, 1, NOW()),
(14, 7, 12, 'Grade 12', 2, 1, NOW()),
(15, 8, 11, 'Grade 11', 2, 1, NOW()),
(16, 8, 12, 'Grade 12', 2, 1, NOW());

-- Insert sample year levels for existing programs
INSERT IGNORE INTO `program_year_levels` (`id`, `program_id`, `year_level`, `year_name`, `semesters_count`, `is_active`, `created_at`) VALUES
(1, 1, 1, '1st Year', 2, 1, NOW()),
(2, 1, 2, '2nd Year', 2, 1, NOW()),
(3, 1, 3, '3rd Year', 2, 1, NOW()),
(4, 1, 4, '4th Year', 2, 1, NOW()),
(5, 2, 1, '1st Year', 2, 1, NOW()),
(6, 2, 2, '2nd Year', 2, 1, NOW()),
(7, 2, 3, '3rd Year', 2, 1, NOW()),
(8, 2, 4, '4th Year', 2, 1, NOW()),
(9, 3, 1, '1st Year', 2, 1, NOW()),
(10, 3, 2, '2nd Year', 2, 1, NOW()),
(11, 3, 3, '3rd Year', 2, 1, NOW()),
(12, 3, 4, '4th Year', 2, 1, NOW());

-- Add foreign key constraints
ALTER TABLE `program_year_levels`
  ADD CONSTRAINT `fk_yearlevel_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `shs_strands`
  ADD CONSTRAINT `fk_strand_track` FOREIGN KEY (`track_id`) REFERENCES `shs_tracks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `shs_grade_levels`
  ADD CONSTRAINT `fk_gradelevel_strand` FOREIGN KEY (`strand_id`) REFERENCES `shs_strands` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `curriculum_subjects`
  ADD CONSTRAINT `fk_curriculum_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_curriculum_yearlevel` FOREIGN KEY (`year_level_id`) REFERENCES `program_year_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_curriculum_shs_strand` FOREIGN KEY (`shs_strand_id`) REFERENCES `shs_strands` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_curriculum_shs_gradelevel` FOREIGN KEY (`shs_grade_level_id`) REFERENCES `shs_grade_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_curriculum_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `classes`
  ADD CONSTRAINT `fk_class_curriculum_subject` FOREIGN KEY (`curriculum_subject_id`) REFERENCES `curriculum_subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Create program_courses mapping table for college assignments
DROP TABLE IF EXISTS `program_courses`;
CREATE TABLE `program_courses` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `program_id` int(10) UNSIGNED NOT NULL,
  `year_level_id` int(10) UNSIGNED NOT NULL,
  `semester` tinyint(3) UNSIGNED NOT NULL,
  `course_code` varchar(30) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_program_course` (`program_id`,`year_level_id`,`semester`,`course_code`),
  KEY `fk_pc_program` (`program_id`),
  KEY `fk_pc_yearlevel` (`year_level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `program_courses`
  ADD CONSTRAINT `fk_pc_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pc_yearlevel` FOREIGN KEY (`year_level_id`) REFERENCES `program_year_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Update AUTO_INCREMENT values
ALTER TABLE `program_year_levels` AUTO_INCREMENT = 13;
ALTER TABLE `shs_strands` AUTO_INCREMENT = 9;
ALTER TABLE `shs_grade_levels` AUTO_INCREMENT = 17;
ALTER TABLE `curriculum_subjects` AUTO_INCREMENT = 1;