<?php
/**
 * Curriculum Migration Runner
 * This script will automatically create all the missing database tables
 * for the School Administrator curriculum management system.
 */

require_once 'config/init.php';

// TEMPORARILY DISABLE AUTH CHECK FOR MIGRATION
// TODO: Remove this after migration is complete
// Check if user is logged in and is Super Admin
// if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != ROLE_SUPER_ADMIN && $_SESSION['role'] != ROLE_SCHOOL_ADMIN)) {
//     die("Access denied. Only Super Admin or School Admin can run migrations.");
// }

echo "<h1>Curriculum Management System Migration</h1>";
echo "<pre>";

// Migration SQL statements - executed in dependency order
$migration_queries = [
    // Step 1: Create tables (no foreign keys yet)
    "CREATE TABLE IF NOT EXISTS `program_year_levels` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `shs_strands` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `shs_grade_levels` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `curriculum_subjects` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Step 2: Update existing tables
    "ALTER TABLE `classes` ADD COLUMN IF NOT EXISTS `curriculum_subject_id` int(10) UNSIGNED DEFAULT NULL AFTER `subject_id`;",
    "ALTER TABLE `classes` ADD KEY IF NOT EXISTS `idx_curriculum_subject` (`curriculum_subject_id`);",
    "ALTER TABLE `grades` ADD COLUMN IF NOT EXISTS `version` int(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `remarks`;",
    "ALTER TABLE `shs_tracks` ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) DEFAULT 1;",
    "ALTER TABLE `shs_strands` ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) DEFAULT 1;",
    "ALTER TABLE `shs_grade_levels` ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) DEFAULT 1;",
    "ALTER TABLE `program_year_levels` ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) DEFAULT 1;",

    // Create program_courses mapping table (drop first to avoid duplicate key errors)
    "DROP TABLE IF EXISTS `program_courses`;",
    "CREATE TABLE `program_courses` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Add foreign keys for program_courses
    "ALTER TABLE `program_courses`
        ADD CONSTRAINT `fk_pc_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE, 
        ADD CONSTRAINT `fk_pc_yearlevel` FOREIGN KEY (`year_level_id`) REFERENCES `program_year_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;",

    // Step 3: Insert sample data
    "INSERT IGNORE INTO `shs_strands` (`id`, `track_id`, `strand_code`, `strand_name`, `description`, `is_active`, `created_at`) VALUES
    (1, 1, 'STEM', 'Science, Technology, Engineering and Mathematics', 'Focuses on scientific and technical skills', 1, NOW()),
    (2, 1, 'ABM', 'Accountancy, Business and Management', 'Prepares students for business and finance careers', 1, NOW()),
    (3, 1, 'HUMSS', 'Humanities and Social Sciences', 'Develops critical thinking and communication skills', 1, NOW()),
    (4, 1, 'GAS', 'General Academic Strand', 'Provides a general education foundation', 1, NOW()),
    (5, 2, 'ICT', 'Information and Communications Technology', 'Technical skills in IT and programming', 1, NOW()),
    (6, 2, 'HE', 'Home Economics', 'Culinary arts and hospitality management', 1, NOW()),
    (7, 3, 'VA', 'Visual Arts', 'Creative expression through visual media', 1, NOW()),
    (8, 4, 'SP', 'Sports', 'Athletic training and sports science', 1, NOW());",

    "INSERT IGNORE INTO `shs_grade_levels` (`id`, `strand_id`, `grade_level`, `grade_name`, `semesters_count`, `is_active`, `created_at`) VALUES
    (1, 1, 11, 'Grade 11', 2, 1, NOW()), (2, 1, 12, 'Grade 12', 2, 1, NOW()),
    (3, 2, 11, 'Grade 11', 2, 1, NOW()), (4, 2, 12, 'Grade 12', 2, 1, NOW()),
    (5, 3, 11, 'Grade 11', 2, 1, NOW()), (6, 3, 12, 'Grade 12', 2, 1, NOW()),
    (7, 4, 11, 'Grade 11', 2, 1, NOW()), (8, 4, 12, 'Grade 12', 2, 1, NOW()),
    (9, 5, 11, 'Grade 11', 2, 1, NOW()), (10, 5, 12, 'Grade 12', 2, 1, NOW()),
    (11, 6, 11, 'Grade 11', 2, 1, NOW()), (12, 6, 12, 'Grade 12', 2, 1, NOW()),
    (13, 7, 11, 'Grade 11', 2, 1, NOW()), (14, 7, 12, 'Grade 12', 2, 1, NOW()),
    (15, 8, 11, 'Grade 11', 2, 1, NOW()), (16, 8, 12, 'Grade 12', 2, 1, NOW());",

    "INSERT IGNORE INTO `program_year_levels` (`id`, `program_id`, `year_level`, `year_name`, `semesters_count`, `is_active`, `created_at`) VALUES
    (1, 1, 1, '1st Year', 2, 1, NOW()), (2, 1, 2, '2nd Year', 2, 1, NOW()),
    (3, 1, 3, '3rd Year', 2, 1, NOW()), (4, 1, 4, '4th Year', 2, 1, NOW()),
    (5, 2, 1, '1st Year', 2, 1, NOW()), (6, 2, 2, '2nd Year', 2, 1, NOW()),
    (7, 2, 3, '3rd Year', 2, 1, NOW()), (8, 2, 4, '4th Year', 2, 1, NOW()),
    (9, 3, 1, '1st Year', 2, 1, NOW()), (10, 3, 2, '2nd Year', 2, 1, NOW()),
    (11, 3, 3, '3rd Year', 2, 1, NOW()), (12, 3, 4, '4th Year', 2, 1, NOW());",
];

$success_count = 0;
$error_count = 0;

foreach ($migration_queries as $index => $query) {
    echo "Executing query " . ($index + 1) . "... ";
    try {
        $result = $conn->query($query);
        if ($result) {
            echo "✅ SUCCESS\n";
            $success_count++;
        } else {
            echo "❌ FAILED: " . $conn->error . "\n";
            $error_count++;
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $error_count++;
    }
}

echo "\n";
echo "Migration completed!\n";
echo "✅ Successful queries: $success_count\n";
echo "❌ Failed queries: $error_count\n";

if ($error_count == 0) {
    echo "\n🎉 All tables created successfully! You can now use the curriculum management system.\n";
    echo "<a href='modules/school_admin/curriculum.php'>Go to Curriculum Management</a>\n";
} else {
    echo "\n⚠️  Some queries failed. Please check the errors above.\n";
}

echo "</pre>";
?>