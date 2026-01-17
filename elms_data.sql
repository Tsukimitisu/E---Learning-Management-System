-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 17, 2026 at 04:36 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `elms_data`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(10) UNSIGNED NOT NULL,
  `year_name` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `year_name`, `is_active`) VALUES
(1, '2025-2026', 1);

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `target_audience` enum('all','students','teachers','staff') DEFAULT 'all',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `school_id` int(10) UNSIGNED DEFAULT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `target_audience`, `priority`, `school_id`, `branch_id`, `created_by`, `is_active`, `created_at`, `expires_at`) VALUES
(1, 'Hatdog', 'Masarap', 'all', 'normal', NULL, 1, 205, 1, '2026-01-17 01:11:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `api_keys`
--

CREATE TABLE `api_keys` (
  `id` int(10) UNSIGNED NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `api_secret` varchar(128) NOT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `last_used` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `assessment_type` enum('quiz','exam','activity','project') NOT NULL,
  `max_score` decimal(5,2) DEFAULT 100.00,
  `scheduled_date` date DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_scores`
--

CREATE TABLE `assessment_scores` (
  `id` int(10) UNSIGNED NOT NULL,
  `assessment_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `status` enum('pending','submitted','graded') DEFAULT 'pending',
  `feedback` text DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(100) NOT NULL,
  `due_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','excused') DEFAULT 'absent',
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `class_id`, `student_id`, `attendance_date`, `status`, `time_in`, `time_out`, `remarks`, `recorded_by`, `created_at`) VALUES
(1, 2, 203, '2026-01-17', 'present', NULL, NULL, '', 100, '2026-01-16 17:05:46');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `ip_address`, `timestamp`) VALUES
(1, 4, 'User logged in - Super Admin', '::1', '2026-01-16 12:59:56'),
(2, 4, 'User logged out', '::1', '2026-01-16 13:05:52'),
(3, 6, 'User logged in - Registrar', '::1', '2026-01-16 13:07:44'),
(4, 6, 'Enrolled student ID 201 into class ID 1', '::1', '2026-01-16 13:09:47'),
(5, 6, 'User logged out', '::1', '2026-01-16 13:18:37'),
(6, 100, 'User logged in - Teacher', '::1', '2026-01-16 13:18:48'),
(7, 100, 'User logged out', '::1', '2026-01-16 13:26:52'),
(8, 203, 'User logged in - Student', '::1', '2026-01-16 13:26:56'),
(9, 203, 'User logged out', '::1', '2026-01-16 13:32:35'),
(10, 204, 'User logged in - School Admin', '::1', '2026-01-16 13:32:41'),
(11, 204, 'User logged out', '::1', '2026-01-16 13:33:21'),
(12, 4, 'User logged in - Super Admin', '::1', '2026-01-16 13:41:09'),
(13, 4, 'Created school: DATAMEX COLLEGE OF SAINT ADELINE', '::1', '2026-01-16 13:41:52'),
(14, 4, 'Created branch: VALENZUELA BRANCH', '::1', '2026-01-16 13:42:11'),
(15, 4, 'Created branch: VALENZUELA BRANCH', '::1', '2026-01-16 13:42:13'),
(16, 4, 'Created branch: CALOOCAN BRANCH', '::1', '2026-01-16 13:42:23'),
(17, 4, 'User logged out', '::1', '2026-01-16 13:42:48'),
(18, 4, 'User logged in - Super Admin', '::1', '2026-01-16 13:42:55'),
(19, 4, 'User logged out', '::1', '2026-01-16 13:43:13'),
(20, 204, 'User logged in - School Admin', '::1', '2026-01-16 13:43:20'),
(21, 204, 'User logged out', '::1', '2026-01-16 14:19:24'),
(22, 4, 'User logged in - Super Admin', '::1', '2026-01-16 14:19:31'),
(23, 4, 'User logged out', '::1', '2026-01-16 14:19:54'),
(24, 203, 'User logged in - Student', '::1', '2026-01-16 14:20:02'),
(25, 203, 'User logged out', '::1', '2026-01-16 14:21:16'),
(26, 4, 'User logged in - Super Admin', '::1', '2026-01-16 14:21:34'),
(27, 205, 'User logged in - Branch Admin', '::1', '2026-01-16 16:32:39'),
(28, 205, 'User logged out', '::1', '2026-01-16 16:39:49'),
(29, 4, 'User logged in - Super Admin', '::1', '2026-01-16 16:39:58'),
(30, 4, 'User logged out', '::1', '2026-01-16 16:42:41'),
(31, 100, 'User logged in - Teacher', '::1', '2026-01-16 16:49:05'),
(32, 100, 'Updated grade for student ID 203 in class ID 1', '::1', '2026-01-16 17:03:48'),
(33, 100, 'Updated grade for student ID 203 in class ID 1', '::1', '2026-01-16 17:03:52'),
(34, 100, 'Uploaded learning material: material_1_1768583054_696a6f8e7b5e0.pptx for class ID 1', '::1', '2026-01-16 17:04:14'),
(35, 100, 'Uploaded learning material: material_1_1768583063_696a6f9773a42.pptx for class ID 1', '::1', '2026-01-16 17:04:23'),
(36, 100, 'Saved attendance for class ID 2 on 2026-01-17 (1 students)', '::1', '2026-01-16 17:05:46'),
(37, 100, 'Uploaded material: material_3_1768584379_696a74bb48747.pptx for class ID 3', '::1', '2026-01-16 17:26:19'),
(38, 100, 'User logged out', '::1', '2026-01-16 17:39:14'),
(39, 6, 'User logged in - Registrar', '::1', '2026-01-16 17:39:22'),
(40, 6, 'Enrolled student ID 202 into class ID 1', '::1', '2026-01-16 17:39:48'),
(41, 6, 'Enrolled student ID 200 into class ID 1', '::1', '2026-01-16 17:39:59'),
(42, 6, 'User logged out', '::1', '2026-01-16 17:40:17'),
(43, 205, 'User logged in - Branch Admin', '::1', '2026-01-16 17:40:24'),
(44, 4, 'User logged in - Super Admin', '::1', '2026-01-17 01:09:14'),
(45, 4, 'User logged out', '::1', '2026-01-17 01:11:06'),
(46, 205, 'User logged in - Branch Admin', '::1', '2026-01-17 01:11:12'),
(47, 205, 'Created branch announcement: Hatdog', '::1', '2026-01-17 01:11:37'),
(48, 205, 'User logged out', '::1', '2026-01-17 01:11:43'),
(49, 203, 'User logged in - Student', '::1', '2026-01-17 01:11:51'),
(50, 203, 'User logged out', '::1', '2026-01-17 01:12:53'),
(51, 100, 'User logged in - Teacher', '::1', '2026-01-17 01:13:00'),
(52, 100, 'User logged out', '::1', '2026-01-17 01:38:26'),
(53, 205, 'User logged in - Branch Admin', '::1', '2026-01-17 01:38:31'),
(54, 205, 'User logged out', '::1', '2026-01-17 01:52:57'),
(55, 204, 'User logged in - School Admin', '::1', '2026-01-17 01:53:04'),
(56, 204, 'User logged out', '::1', '2026-01-17 02:15:13'),
(57, 4, 'User logged in - Super Admin', '::1', '2026-01-17 02:31:35'),
(58, 4, 'User logged out', '::1', '2026-01-17 02:31:56'),
(59, 205, 'User logged in - Branch Admin', '::1', '2026-01-17 02:32:06');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(10) UNSIGNED NOT NULL,
  `school_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `school_id`, `name`, `address`) VALUES
(1, 1, 'Main Campus', 'Manila'),
(2, 2, 'VALENZUELA BRANCH', ''),
(3, 2, 'VALENZUELA BRANCH', ''),
(4, 2, 'CALOOCAN BRANCH', '');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `shs_track_id` int(10) UNSIGNED DEFAULT NULL,
  `section_name` varchar(50) DEFAULT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_id` int(10) UNSIGNED DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `schedule` varchar(100) DEFAULT NULL,
  `max_capacity` int(10) UNSIGNED DEFAULT 30,
  `current_enrolled` int(10) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `course_id`, `academic_year_id`, `subject_id`, `shs_track_id`, `section_name`, `branch_id`, `teacher_id`, `room`, `schedule`, `max_capacity`, `current_enrolled`) VALUES
(1, 1, NULL, NULL, NULL, 'Section 1', NULL, 100, 'Room 101', NULL, 30, 3),
(2, 2, NULL, NULL, NULL, 'Section 2', NULL, 100, 'Room 102', NULL, 25, 0),
(3, 3, NULL, NULL, NULL, 'Section 3', NULL, 100, 'Room 103', NULL, 20, 0);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `title` varchar(100) NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_code`, `title`, `branch_id`) VALUES
(1, 'CS101', 'Introduction to Computer Science', 1),
(2, 'MATH101', 'Calculus I', 1),
(3, 'ENG101', 'English Composition', 1);

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `class_id`, `status`, `created_at`) VALUES
(1, 201, 1, 'approved', '2026-01-16 13:09:47'),
(2, 203, 1, 'approved', '2026-01-16 13:26:44'),
(3, 203, 2, 'approved', '2026-01-16 13:26:44'),
(5, 202, 1, 'approved', '2026-01-16 17:39:48'),
(6, 200, 1, 'approved', '2026-01-16 17:39:59');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `midterm` decimal(5,2) DEFAULT NULL,
  `final` decimal(5,2) DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `remarks` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `student_id`, `class_id`, `midterm`, `final`, `final_grade`, `remarks`) VALUES
(1, 203, 1, 89.00, 88.00, 88.40, '0');

-- --------------------------------------------------------

--
-- Table structure for table `grade_components`
--

CREATE TABLE `grade_components` (
  `id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `component_name` varchar(100) NOT NULL,
  `component_type` enum('written','performance','quarterly','exam') NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) DEFAULT 100.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grade_locks`
--

CREATE TABLE `grade_locks` (
  `id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `grading_period` enum('prelim','midterm','final','quarterly') NOT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `locked_by` int(10) UNSIGNED DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `unlock_request` tinyint(1) DEFAULT 0,
  `unlock_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `learning_materials`
--

CREATE TABLE `learning_materials` (
  `id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `learning_materials`
--

INSERT INTO `learning_materials` (`id`, `class_id`, `file_path`, `uploaded_at`) VALUES
(1, 1, 'materials/material_1_1768583054_696a6f8e7b5e0.pptx', '2026-01-16 17:04:14'),
(2, 1, 'materials/material_1_1768583063_696a6f9773a42.pptx', '2026-01-16 17:04:23'),
(3, 3, 'materials/material_3_1768584379_696a74bb48747.pptx', '2026-01-16 17:26:19');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `proof_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(10) UNSIGNED NOT NULL,
  `program_code` varchar(20) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `degree_level` enum('Certificate','Associate','Bachelor','Master','Doctorate') DEFAULT 'Bachelor',
  `school_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `program_code`, `program_name`, `degree_level`, `school_id`, `is_active`, `created_at`) VALUES
(1, 'BSIT', 'Bachelor of Science in Information Technology', 'Bachelor', 1, 1, '2026-01-16 13:28:17'),
(2, 'BSCS', 'Bachelor of Science in Computer Science', 'Bachelor', 1, 1, '2026-01-16 13:28:17'),
(3, 'BSIS', 'Bachelor of Science in Information Systems', 'Bachelor', 1, 1, '2026-01-16 13:28:17');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(3, 'Branch Admin'),
(4, 'Registrar'),
(2, 'School Admin'),
(6, 'Student'),
(1, 'Super Admin'),
(5, 'Teacher');

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`id`, `name`, `logo`) VALUES
(1, 'Datamex University', NULL),
(2, 'DATAMEX COLLEGE OF SAINT ADELINE', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `event_type` enum('login_success','login_failed','logout','password_change','account_locked','suspicious_activity') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'low',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shs_tracks`
--

CREATE TABLE `shs_tracks` (
  `id` int(10) UNSIGNED NOT NULL,
  `track_name` varchar(100) NOT NULL,
  `track_code` varchar(20) NOT NULL,
  `written_work_weight` decimal(5,2) DEFAULT 30.00,
  `performance_task_weight` decimal(5,2) DEFAULT 50.00,
  `quarterly_exam_weight` decimal(5,2) DEFAULT 20.00,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shs_tracks`
--

INSERT INTO `shs_tracks` (`id`, `track_name`, `track_code`, `written_work_weight`, `performance_task_weight`, `quarterly_exam_weight`, `description`) VALUES
(1, 'Academic Track - STEM', 'STEM', 25.00, 50.00, 25.00, NULL),
(2, 'Academic Track - ABM', 'ABM', 30.00, 50.00, 20.00, NULL),
(3, 'Academic Track - HUMSS', 'HUMSS', 30.00, 50.00, 20.00, NULL),
(4, 'TVL Track', 'TVL', 20.00, 60.00, 20.00, NULL),
(5, 'Arts and Design Track', 'ARTS', 20.00, 60.00, 20.00, NULL),
(6, 'Sports Track', 'SPORTS', 20.00, 60.00, 20.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `student_no` varchar(20) NOT NULL,
  `course_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`user_id`, `student_no`, `course_id`) VALUES
(200, '2025-0001', 1),
(201, '2025-0002', 2),
(202, '2025-0003', 1),
(203, '2025-1001', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_grade_details`
--

CREATE TABLE `student_grade_details` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `component_id` int(10) UNSIGNED NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `encoded_by` int(10) UNSIGNED NOT NULL,
  `encoded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(10) UNSIGNED NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_title` varchar(100) NOT NULL,
  `units` int(10) UNSIGNED DEFAULT 3,
  `program_id` int(10) UNSIGNED NOT NULL,
  `shs_track_id` int(10) UNSIGNED DEFAULT NULL,
  `year_level` tinyint(3) UNSIGNED DEFAULT 1,
  `semester` tinyint(3) UNSIGNED DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `grade` decimal(5,2) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_maintenance`
--

CREATE TABLE `system_maintenance` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `affected_modules` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_modules`
--

CREATE TABLE `system_modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_modules`
--

INSERT INTO `system_modules` (`id`, `module_name`, `module_key`, `is_enabled`, `description`, `updated_at`) VALUES
(1, 'User Management', 'user_management', 1, 'Manage users and roles', '2026-01-16 16:35:05'),
(2, 'Academic Management', 'academic_management', 1, 'Programs, subjects, classes', '2026-01-16 16:35:05'),
(3, 'Enrollment', 'enrollment', 1, 'Student enrollment system', '2026-01-16 16:35:05'),
(4, 'Grading', 'grading', 1, 'Grade management', '2026-01-16 16:35:05'),
(5, 'Announcements', 'announcements', 1, 'System announcements', '2026-01-16 16:35:05'),
(6, 'Reports', 'reports', 1, 'Generate reports', '2026-01-16 16:35:05');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'site_name', 'ELMS - Datamex', 'string', 'general', 'System Name', NULL, '2026-01-16 16:35:05'),
(2, 'maintenance_mode', '0', 'boolean', 'system', 'Enable Maintenance Mode', NULL, '2026-01-16 16:35:05'),
(3, 'session_timeout', '3600', 'number', 'security', 'Session Timeout (seconds)', NULL, '2026-01-16 16:35:05'),
(4, 'max_login_attempts', '5', 'number', 'security', 'Maximum Login Attempts', NULL, '2026-01-16 16:35:05'),
(5, 'password_min_length', '8', 'number', 'security', 'Minimum Password Length', NULL, '2026-01-16 16:35:05'),
(6, 'enable_registration', '1', 'boolean', 'general', 'Allow User Registration', NULL, '2026-01-16 16:35:05'),
(7, 'backup_frequency', 'daily', 'string', 'system', 'Backup Frequency', NULL, '2026-01-16 16:35:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `status`, `last_login`, `created_at`) VALUES
(4, 'admin@elms.com', '$2y$10$HT./ovUEHrcCRGbLzjSHquhagQeVxD9iK59//YEDUfntP5pn3o3m2', 'active', '2026-01-17 10:31:35', '2026-01-16 12:57:04'),
(6, 'registrar@elms.com', '$2y$10$emmb9dv7qdUCsPWfW0Ey4u3YLcA6h99ym0DrPa1dAo8n0bV0PUeSe', 'active', '2026-01-17 01:39:22', '2026-01-16 13:06:58'),
(100, 'teacher@elms.com', '$2y$10$gS8DWoSFQX9iUAZ4r2jCvucHbM0Swd7iGB.5uG1pxlBkiKSXZf22O', 'active', '2026-01-17 09:13:00', '2026-01-16 13:08:50'),
(200, 'student1@elms.com', '$2y$10$rTenfOlur5ca6J9a5kdMiO25ZBT7cavQ.WOutUYAp5rEryIG9epbG', 'active', NULL, '2026-01-16 13:08:50'),
(201, 'student2@elms.com', '$2y$10$rTenfOlur5ca6J9a5kdMiO25ZBT7cavQ.WOutUYAp5rEryIG9epbG', 'active', NULL, '2026-01-16 13:08:50'),
(202, 'student3@elms.com', '$2y$10$rTenfOlur5ca6J9a5kdMiO25ZBT7cavQ.WOutUYAp5rEryIG9epbG', 'active', NULL, '2026-01-16 13:08:50'),
(203, 'student@elms.com', '$2y$10$KFgcfrgq5cpjkBkheHuI1Owhqc054pQv/Ukbec8GTiCoiBGxgErxK', 'active', '2026-01-17 09:11:51', '2026-01-16 13:26:44'),
(204, 'schooladmin@elms.com', '$2y$10$QA38bQbDvhQwo/.BHioND.p1Y06Oy0rcHTXOC7i4FnhmwqLyVZGcu', 'active', '2026-01-17 09:53:04', '2026-01-16 13:32:27'),
(205, 'branchadmin@elms.com', '$2y$10$Bic2FhHZbHvu3AvS8601HO0UXxxyyvi01LGZh3iIW35AmKC8kFB0i', 'active', '2026-01-17 10:32:06', '2026-01-16 16:32:28');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`user_id`, `first_name`, `last_name`, `contact_no`, `address`) VALUES
(4, 'Super', 'Administrator', '09123456789', 'Datamex HQ'),
(6, 'Maria', 'Santos', '09171234567', 'Registrar Office'),
(100, 'Juan', 'Dela Cruz', NULL, NULL),
(200, 'Pedro', 'Garcia', NULL, NULL),
(201, 'Ana', 'Reyes', NULL, NULL),
(202, 'Jose', 'Martinez', NULL, NULL),
(203, 'Maria', 'Garcia', '09181234567', 'Student Residence'),
(204, 'Academic', 'Dean', '09191234567', NULL),
(205, 'Branch', 'Coordinator', '09201234567', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(4, 1),
(6, 4),
(100, 5),
(200, 6),
(201, 6),
(202, 6),
(203, 6),
(204, 2),
(205, 3);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school` (`school_id`),
  ADD KEY `idx_branch` (`branch_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `fk_announcement_user` (`created_by`);

--
-- Indexes for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `api_key` (`api_key`),
  ADD KEY `fk_apikey_user` (`created_by`);

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class` (`class_id`);

--
-- Indexes for table `assessment_scores`
--
ALTER TABLE `assessment_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assessment_student` (`assessment_id`,`student_id`),
  ADD KEY `fk_ascore_student` (`student_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class` (`class_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class_date` (`class_id`,`attendance_date`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `fk_attendance_recorder` (`recorded_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_timestamp` (`user_id`,`timestamp`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school` (`school_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_academic_year` (`academic_year_id`),
  ADD KEY `idx_subject` (`subject_id`),
  ADD KEY `idx_branch` (`branch_id`),
  ADD KEY `fk_class_track` (`shs_track_id`),
  ADD KEY `idx_section` (`section_name`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch` (`branch_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_class` (`class_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_class` (`class_id`);

--
-- Indexes for table `grade_components`
--
ALTER TABLE `grade_components`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class` (`class_id`);

--
-- Indexes for table `grade_locks`
--
ALTER TABLE `grade_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_class_period` (`class_id`,`grading_period`);

--
-- Indexes for table `learning_materials`
--
ALTER TABLE `learning_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class` (`class_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `program_code` (`program_code`),
  ADD KEY `idx_school` (`school_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_event` (`user_id`,`event_type`),
  ADD KEY `idx_severity` (`severity`);

--
-- Indexes for table `shs_tracks`
--
ALTER TABLE `shs_tracks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `track_code` (`track_code`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `student_no` (`student_no`),
  ADD KEY `idx_student_no` (`student_no`),
  ADD KEY `idx_course` (`course_id`);

--
-- Indexes for table `student_grade_details`
--
ALTER TABLE `student_grade_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_component` (`student_id`,`component_id`),
  ADD KEY `fk_gradedtl_component` (`component_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_program` (`program_id`),
  ADD KEY `idx_subject_code` (`subject_code`),
  ADD KEY `fk_subject_track` (`shs_track_id`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assignment` (`assignment_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `system_maintenance`
--
ALTER TABLE `system_maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_maintenance_user` (`created_by`);

--
-- Indexes for table `system_modules`
--
ALTER TABLE `system_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_name` (`module_name`),
  ADD UNIQUE KEY `module_key` (`module_key`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `fk_setting_user` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `fk_userrole_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessment_scores`
--
ALTER TABLE `assessment_scores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `grade_components`
--
ALTER TABLE `grade_components`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grade_locks`
--
ALTER TABLE `grade_locks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `learning_materials`
--
ALTER TABLE `learning_materials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shs_tracks`
--
ALTER TABLE `shs_tracks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_grade_details`
--
ALTER TABLE `student_grade_details`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_maintenance`
--
ALTER TABLE `system_maintenance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_modules`
--
ALTER TABLE `system_modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=206;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcement_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_announcement_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_announcement_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD CONSTRAINT `fk_apikey_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `fk_assessment_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assessment_scores`
--
ALTER TABLE `assessment_scores`
  ADD CONSTRAINT `fk_ascore_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ascore_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assignment_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `fk_branch_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `fk_class_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_class_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_class_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_class_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_class_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_class_track` FOREIGN KEY (`shs_track_id`) REFERENCES `shs_tracks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_course_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `fk_enrollment_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `fk_grade_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grade_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `grade_components`
--
ALTER TABLE `grade_components`
  ADD CONSTRAINT `fk_gradecomp_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `grade_locks`
--
ALTER TABLE `grade_locks`
  ADD CONSTRAINT `fk_gradelock_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `learning_materials`
--
ALTER TABLE `learning_materials`
  ADD CONSTRAINT `fk_material_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `fk_program_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD CONSTRAINT `fk_seclog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_student_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_grade_details`
--
ALTER TABLE `student_grade_details`
  ADD CONSTRAINT `fk_gradedtl_component` FOREIGN KEY (`component_id`) REFERENCES `grade_components` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gradedtl_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subject_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subject_track` FOREIGN KEY (`shs_track_id`) REFERENCES `shs_tracks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `fk_submission_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_submission_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `system_maintenance`
--
ALTER TABLE `system_maintenance`
  ADD CONSTRAINT `fk_maintenance_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `fk_setting_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_userrole_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_userrole_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
