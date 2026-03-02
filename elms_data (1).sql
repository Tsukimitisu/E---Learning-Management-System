-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 09:09 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `status` varchar(20) DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `year_name`, `start_date`, `end_date`, `is_active`, `status`, `created_at`, `updated_at`) VALUES
(1, '2025-2026', NULL, NULL, 1, 'current', '2026-01-19 15:13:43', '2026-01-31 13:22:58'),
(2, '2026-2027', '2026-07-19', '2027-04-19', 0, 'completed', '2026-01-19 15:14:12', '2026-01-31 13:22:58'),
(3, '2027-2028', '2026-02-03', '2026-02-03', 0, 'upcoming', '2026-02-03 03:50:37', '2026-02-03 03:50:37');

-- --------------------------------------------------------

--
-- Table structure for table `active_sessions`
--

CREATE TABLE `active_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'Hatdog', 'Masarap', 'all', 'normal', NULL, 1, 205, 1, '2026-01-17 01:11:37', NULL),
(2, 'Happy Holidays', 'Hello Good Morning', 'all', 'low', NULL, NULL, 204, 1, '2026-01-30 15:05:56', '2026-01-30 23:05:00');

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
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `curriculum_subject_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `assessment_type` enum('quiz','exam','activity','project') NOT NULL,
  `max_score` decimal(5,2) DEFAULT 100.00,
  `scheduled_date` date DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `class_id`, `section_id`, `curriculum_subject_id`, `title`, `assessment_type`, `max_score`, `scheduled_date`, `duration_minutes`, `instructions`, `created_by`, `created_at`) VALUES
(1, 4, NULL, NULL, 'Activity Programming', 'activity', 100.00, '0000-00-00', NULL, 'make a triangle using c#', 100, '2026-02-11 03:57:40'),
(2, 4, NULL, NULL, 'Sample', 'activity', 100.00, '0000-00-00', NULL, 'sample ngani', 100, '2026-03-02 18:29:45');

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
  `submitted_file` varchar(255) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `student_notes` text DEFAULT NULL,
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
  `section_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `ip_address`, `details`, `timestamp`) VALUES
(1, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-16 12:59:56'),
(2, 4, 'User logged out', '::1', NULL, '2026-01-16 13:05:52'),
(3, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-16 13:07:44'),
(4, 6, 'Enrolled student ID 201 into class ID 1', '::1', NULL, '2026-01-16 13:09:47'),
(5, 6, 'User logged out', '::1', NULL, '2026-01-16 13:18:37'),
(6, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-16 13:18:48'),
(7, 100, 'User logged out', '::1', NULL, '2026-01-16 13:26:52'),
(8, NULL, 'User logged in - Student', '::1', NULL, '2026-01-16 13:26:56'),
(9, NULL, 'User logged out', '::1', NULL, '2026-01-16 13:32:35'),
(10, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-16 13:32:41'),
(11, 204, 'User logged out', '::1', NULL, '2026-01-16 13:33:21'),
(12, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-16 13:41:09'),
(13, 4, 'Created school: DATAMEX COLLEGE OF SAINT ADELINE', '::1', NULL, '2026-01-16 13:41:52'),
(14, 4, 'Created branch: VALENZUELA BRANCH', '::1', NULL, '2026-01-16 13:42:11'),
(15, 4, 'Created branch: VALENZUELA BRANCH', '::1', NULL, '2026-01-16 13:42:13'),
(16, 4, 'Created branch: CALOOCAN BRANCH', '::1', NULL, '2026-01-16 13:42:23'),
(17, 4, 'User logged out', '::1', NULL, '2026-01-16 13:42:48'),
(18, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-16 13:42:55'),
(19, 4, 'User logged out', '::1', NULL, '2026-01-16 13:43:13'),
(20, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-16 13:43:20'),
(21, 204, 'User logged out', '::1', NULL, '2026-01-16 14:19:24'),
(22, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-16 14:19:31'),
(23, 4, 'User logged out', '::1', NULL, '2026-01-16 14:19:54'),
(24, NULL, 'User logged in - Student', '::1', NULL, '2026-01-16 14:20:02'),
(25, NULL, 'User logged out', '::1', NULL, '2026-01-16 14:21:16'),
(26, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-16 14:21:34'),
(27, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-16 16:32:39'),
(28, 205, 'User logged out', '::1', NULL, '2026-01-16 16:39:49'),
(29, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-16 16:39:58'),
(30, 4, 'User logged out', '::1', NULL, '2026-01-16 16:42:41'),
(31, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-16 16:49:05'),
(32, 100, 'Updated grade for student ID 203 in class ID 1', '::1', NULL, '2026-01-16 17:03:48'),
(33, 100, 'Updated grade for student ID 203 in class ID 1', '::1', NULL, '2026-01-16 17:03:52'),
(34, 100, 'Uploaded learning material: material_1_1768583054_696a6f8e7b5e0.pptx for class ID 1', '::1', NULL, '2026-01-16 17:04:14'),
(35, 100, 'Uploaded learning material: material_1_1768583063_696a6f9773a42.pptx for class ID 1', '::1', NULL, '2026-01-16 17:04:23'),
(36, 100, 'Saved attendance for class ID 2 on 2026-01-17 (1 students)', '::1', NULL, '2026-01-16 17:05:46'),
(37, 100, 'Uploaded material: material_3_1768584379_696a74bb48747.pptx for class ID 3', '::1', NULL, '2026-01-16 17:26:19'),
(38, 100, 'User logged out', '::1', NULL, '2026-01-16 17:39:14'),
(39, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-16 17:39:22'),
(40, 6, 'Enrolled student ID 202 into class ID 1', '::1', NULL, '2026-01-16 17:39:48'),
(41, 6, 'Enrolled student ID 200 into class ID 1', '::1', NULL, '2026-01-16 17:39:59'),
(42, 6, 'User logged out', '::1', NULL, '2026-01-16 17:40:17'),
(43, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-16 17:40:24'),
(44, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-17 01:09:14'),
(45, 4, 'User logged out', '::1', NULL, '2026-01-17 01:11:06'),
(46, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-17 01:11:12'),
(47, 205, 'Created branch announcement: Hatdog', '::1', NULL, '2026-01-17 01:11:37'),
(48, 205, 'User logged out', '::1', NULL, '2026-01-17 01:11:43'),
(49, NULL, 'User logged in - Student', '::1', NULL, '2026-01-17 01:11:51'),
(50, NULL, 'User logged out', '::1', NULL, '2026-01-17 01:12:53'),
(51, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-17 01:13:00'),
(52, 100, 'User logged out', '::1', NULL, '2026-01-17 01:38:26'),
(53, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-17 01:38:31'),
(54, 205, 'User logged out', '::1', NULL, '2026-01-17 01:52:57'),
(55, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-17 01:53:04'),
(56, 204, 'User logged out', '::1', NULL, '2026-01-17 02:15:13'),
(57, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-17 02:31:35'),
(58, 4, 'User logged out', '::1', NULL, '2026-01-17 02:31:56'),
(59, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-17 02:32:06'),
(60, NULL, 'User logged in - Student', '::1', NULL, '2026-01-17 09:43:38'),
(61, NULL, 'User logged out', '::1', NULL, '2026-01-17 09:43:58'),
(62, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-17 09:44:48'),
(63, 100, 'User logged out', '::1', NULL, '2026-01-17 09:45:18'),
(64, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-17 09:46:35'),
(65, 4, 'User logged out', '::1', NULL, '2026-01-17 09:49:13'),
(66, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-17 09:49:32'),
(67, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-17 15:12:47'),
(68, 100, 'User logged out', '::1', NULL, '2026-01-17 15:13:05'),
(69, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-17 15:20:36'),
(70, 204, 'User logged out', '::1', NULL, '2026-01-17 15:22:37'),
(71, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-17 15:25:55'),
(72, 204, 'User logged out', '::1', NULL, '2026-01-17 15:26:54'),
(73, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-17 15:34:18'),
(74, 204, 'User logged out', '::1', NULL, '2026-01-17 15:37:08'),
(75, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-17 15:37:15'),
(76, 204, 'Created program: BSHM - Bachelor of Science in Hospitality Management', '::1', NULL, '2026-01-17 15:40:14'),
(77, 204, 'User logged out', '::1', NULL, '2026-01-17 15:57:44'),
(78, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-17 16:13:15'),
(79, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 11:55:20'),
(80, 204, 'User logged out', '::1', NULL, '2026-01-18 11:57:03'),
(81, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 11:57:08'),
(82, 100, 'User logged out', '::1', NULL, '2026-01-18 11:58:08'),
(83, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 12:12:07'),
(84, 204, 'Created program: BSHM - Bachelor of Science in Hospitality Management', '::1', NULL, '2026-01-18 13:51:57'),
(85, 204, 'User logged out', '::1', NULL, '2026-01-18 14:00:57'),
(86, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 14:01:26'),
(87, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 14:01:47'),
(88, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 14:03:58'),
(89, 204, 'User logged out', '::1', NULL, '2026-01-18 14:44:20'),
(90, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 14:44:28'),
(91, 100, 'User logged out', '::1', NULL, '2026-01-18 14:50:51'),
(92, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 14:50:56'),
(93, 205, 'Created new section \'BSIT 1 A\' for subject BSIT-102 (Introduction to Computing), assigned to teacher ID 100', '::1', NULL, '2026-01-18 15:22:11'),
(94, 205, 'User logged out', '::1', NULL, '2026-01-18 15:45:07'),
(95, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-18 15:45:12'),
(96, 6, 'User logged out', '::1', NULL, '2026-01-18 16:03:09'),
(97, NULL, 'User logged in - Student', '::1', NULL, '2026-01-18 16:03:17'),
(98, NULL, 'User logged out', '::1', NULL, '2026-01-18 16:03:32'),
(99, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-18 16:08:10'),
(100, 6, 'Generated enrollment certificate for Pedro Garcia (2025-0001)', '::1', NULL, '2026-01-18 16:09:26'),
(101, 6, 'Generated enrollment certificate for Pedro Garcia (2025-0001)', '::1', NULL, '2026-01-18 16:10:04'),
(102, 6, 'Generated enrollment certificate for Pedro Garcia (2025-0001)', '::1', NULL, '2026-01-18 16:12:53'),
(103, 6, 'User logged out', '::1', NULL, '2026-01-18 16:15:47'),
(104, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 16:15:56'),
(105, 100, 'User logged out', '::1', NULL, '2026-01-18 16:16:04'),
(106, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 16:16:10'),
(107, 205, 'User logged out', '::1', NULL, '2026-01-18 16:17:12'),
(108, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 16:17:20'),
(109, 100, 'User logged out', '::1', NULL, '2026-01-18 16:17:28'),
(110, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 16:17:36'),
(111, 205, 'User logged out', '::1', NULL, '2026-01-18 16:47:01'),
(112, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 16:47:08'),
(113, 204, 'User logged out', '::1', NULL, '2026-01-18 16:47:25'),
(114, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 16:47:32'),
(115, 205, 'User logged out', '::1', NULL, '2026-01-18 17:03:50'),
(116, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 17:03:57'),
(117, 205, 'User logged out', '::1', NULL, '2026-01-18 17:21:17'),
(118, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 17:21:23'),
(119, 204, 'User logged out', '::1', NULL, '2026-01-18 17:22:29'),
(120, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 17:22:34'),
(121, 205, 'User logged out', '::1', NULL, '2026-01-18 17:28:31'),
(122, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 17:28:36'),
(123, 100, 'User logged out', '::1', NULL, '2026-01-18 17:33:43'),
(124, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 17:42:56'),
(125, 205, 'User logged out', '::1', NULL, '2026-01-18 17:47:09'),
(126, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 17:47:14'),
(127, 100, 'Updated grade for student ID 203 in class ID 4', '::1', NULL, '2026-01-18 17:49:10'),
(128, 100, 'User logged out', '::1', NULL, '2026-01-18 17:55:29'),
(129, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 17:55:34'),
(130, 205, 'User logged out', '::1', NULL, '2026-01-18 17:56:10'),
(131, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 17:56:15'),
(132, 100, 'User logged out', '::1', NULL, '2026-01-18 17:56:42'),
(133, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 17:56:48'),
(134, 205, 'User logged out', '::1', NULL, '2026-01-18 17:57:11'),
(135, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 17:57:16'),
(136, 100, 'User logged out', '::1', NULL, '2026-01-18 17:57:29'),
(137, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 17:57:35'),
(138, 205, 'User logged out', '::1', NULL, '2026-01-18 17:57:53'),
(139, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 17:58:00'),
(140, 100, 'User logged out', '::1', NULL, '2026-01-18 18:04:05'),
(141, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 18:04:12'),
(142, 205, 'User logged out', '::1', NULL, '2026-01-18 18:06:43'),
(143, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 18:07:10'),
(144, 100, 'User logged out', '::1', NULL, '2026-01-18 18:36:55'),
(145, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 18:37:01'),
(146, 205, 'User logged out', '::1', NULL, '2026-01-18 18:38:08'),
(147, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-18 18:38:38'),
(148, 100, 'Uploaded material: material_subj4_1768761931_696d2a4b39360.pptx for subject ID 4', '::1', NULL, '2026-01-18 18:45:31'),
(149, 100, 'User logged out', '::1', NULL, '2026-01-18 18:47:19'),
(150, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 18:47:25'),
(151, 204, 'User logged out', '::1', NULL, '2026-01-18 18:54:18'),
(152, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 18:54:23'),
(153, 205, 'User logged out', '::1', NULL, '2026-01-18 18:55:35'),
(154, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 18:55:42'),
(155, 205, 'User logged out', '::1', NULL, '2026-01-18 18:55:56'),
(156, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 18:56:02'),
(157, 204, 'User logged out', '::1', NULL, '2026-01-18 19:01:39'),
(158, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 19:03:35'),
(159, 204, 'User logged out', '::1', NULL, '2026-01-18 19:05:32'),
(160, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-18 19:05:47'),
(161, 4, 'Updated system setting: password_min_length = 8', '::1', NULL, '2026-01-18 19:06:18'),
(162, 4, 'Updated system setting: enable_registration = 1', '::1', NULL, '2026-01-18 19:06:21'),
(163, 4, 'Enabled maintenance mode', '::1', NULL, '2026-01-18 19:06:28'),
(164, 4, 'Disabled maintenance mode', '::1', NULL, '2026-01-18 19:06:38'),
(165, 4, 'User logged out', '::1', NULL, '2026-01-18 19:07:30'),
(166, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 19:07:36'),
(167, 205, 'User logged out', '::1', NULL, '2026-01-18 19:07:43'),
(168, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 19:07:48'),
(169, 204, 'User logged out', '::1', NULL, '2026-01-18 19:11:51'),
(170, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 19:13:23'),
(171, 205, 'User logged out', '::1', NULL, '2026-01-18 19:13:27'),
(172, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 19:13:33'),
(173, 204, 'Created branch administrator: James Andrei Revilla (sample@elms.com)', '::1', NULL, '2026-01-18 19:14:15'),
(174, 204, 'User logged out', '::1', NULL, '2026-01-18 19:14:24'),
(175, 210, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 19:14:30'),
(176, 210, 'User logged out', '::1', NULL, '2026-01-18 19:17:24'),
(177, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 19:17:30'),
(178, 204, 'User logged out', '::1', NULL, '2026-01-18 19:21:00'),
(179, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-18 19:21:20'),
(180, 4, 'User logged out', '::1', NULL, '2026-01-18 19:30:59'),
(181, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 19:31:05'),
(182, 205, 'User logged out', '::1', NULL, '2026-01-18 19:31:17'),
(183, 210, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 19:31:22'),
(184, 210, 'User logged out', '::1', NULL, '2026-01-18 19:31:33'),
(185, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 19:31:38'),
(186, 204, 'User logged out', '::1', NULL, '2026-01-18 19:32:24'),
(187, NULL, 'User logged in - Student', '::1', NULL, '2026-01-18 19:47:12'),
(188, NULL, 'User logged out', '::1', NULL, '2026-01-18 20:07:38'),
(189, 210, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 20:07:50'),
(190, 210, 'User logged out', '::1', NULL, '2026-01-18 20:11:02'),
(191, 211, 'User logged in - Registrar', '::1', NULL, '2026-01-18 20:11:13'),
(192, 211, 'Generated enrollment certificate for Maria Garcia (2025-1001)', '::1', NULL, '2026-01-18 20:13:02'),
(193, 211, 'User logged out', '::1', NULL, '2026-01-18 20:13:32'),
(194, NULL, 'User logged in - Student', '::1', NULL, '2026-01-18 20:13:39'),
(195, NULL, 'User logged out', '::1', NULL, '2026-01-18 20:14:04'),
(196, 211, 'User logged in - Registrar', '::1', NULL, '2026-01-18 20:14:12'),
(197, 211, 'User logged out', '::1', NULL, '2026-01-18 20:38:52'),
(198, 210, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 20:39:03'),
(199, 210, 'User logged out', '::1', NULL, '2026-01-18 20:44:22'),
(200, 211, 'User logged in - Registrar', '::1', NULL, '2026-01-18 20:44:32'),
(201, 211, 'User logged out', '::1', NULL, '2026-01-18 20:44:40'),
(202, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-18 20:44:46'),
(203, 6, 'User logged out', '::1', NULL, '2026-01-18 20:45:14'),
(204, 210, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 20:45:25'),
(205, 210, 'Created teacher account for Senpai James (senpai@teacher.com)', '::1', NULL, '2026-01-18 20:47:29'),
(206, 210, 'User logged out', '::1', NULL, '2026-01-18 20:50:25'),
(207, 211, 'User logged in - Registrar', '::1', NULL, '2026-01-18 20:50:37'),
(208, 211, 'User logged out', '::1', NULL, '2026-01-18 21:00:01'),
(209, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 21:00:06'),
(210, 205, 'User logged out', '::1', NULL, '2026-01-18 21:02:59'),
(211, 211, 'User logged in - Registrar', '::1', NULL, '2026-01-18 21:03:05'),
(212, 211, 'User logged out', '::1', NULL, '2026-01-18 21:03:26'),
(213, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 21:03:32'),
(214, 205, 'User logged out', '::1', NULL, '2026-01-18 21:04:32'),
(215, 211, 'User logged in - Registrar', '::1', NULL, '2026-01-18 21:07:30'),
(216, 211, 'User logged out', '::1', NULL, '2026-01-18 21:07:52'),
(217, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 21:08:02'),
(218, 205, 'User logged out', '::1', NULL, '2026-01-18 21:10:12'),
(219, 211, 'User logged in - Registrar', '::1', NULL, '2026-01-18 21:10:20'),
(220, 211, 'User logged out', '::1', NULL, '2026-01-18 21:19:25'),
(221, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 21:19:35'),
(222, 205, 'User logged out', '::1', NULL, '2026-01-18 21:20:28'),
(223, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-18 21:20:35'),
(224, 204, 'User logged out', '::1', NULL, '2026-01-18 21:20:55'),
(225, NULL, 'User logged in - Student', '::1', NULL, '2026-01-18 21:21:07'),
(226, NULL, 'User logged out', '::1', NULL, '2026-01-18 21:22:04'),
(227, 211, 'User logged in - Registrar', '::1', NULL, '2026-01-18 21:22:49'),
(228, 211, 'User logged out', '::1', NULL, '2026-01-18 21:28:50'),
(229, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-18 21:28:57'),
(230, 6, 'User logged out', '::1', NULL, '2026-01-18 21:55:33'),
(231, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-19 02:27:32'),
(232, 4, 'User logged out', '::1', NULL, '2026-01-19 02:28:51'),
(233, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-19 02:29:01'),
(234, 204, 'User logged out', '::1', NULL, '2026-01-19 02:31:18'),
(235, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 02:31:29'),
(236, 205, 'User logged out', '::1', NULL, '2026-01-19 02:37:56'),
(237, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 02:39:31'),
(238, 205, 'User logged out', '::1', NULL, '2026-01-19 02:40:33'),
(239, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-19 02:40:39'),
(240, 204, 'User logged out', '::1', NULL, '2026-01-19 02:41:34'),
(241, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 02:41:44'),
(242, 205, 'User logged out', '::1', NULL, '2026-01-19 02:43:42'),
(243, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-19 02:43:49'),
(244, 6, 'User logged out', '::1', NULL, '2026-01-19 02:45:18'),
(245, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 02:45:33'),
(246, 100, 'User logged out', '::1', NULL, '2026-01-19 02:48:46'),
(247, NULL, 'User logged in - Student', '::1', NULL, '2026-01-19 02:48:55'),
(248, NULL, 'User logged out', '::1', NULL, '2026-01-19 02:50:58'),
(249, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-19 02:52:11'),
(250, 4, 'User logged out', '::1', NULL, '2026-01-19 02:52:23'),
(251, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-19 02:52:29'),
(252, 204, 'User logged out', '::1', NULL, '2026-01-19 02:52:41'),
(253, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-19 03:18:01'),
(254, 4, 'User logged out', '::1', NULL, '2026-01-19 03:27:06'),
(255, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 03:50:45'),
(256, 100, 'User logged out', '::1', NULL, '2026-01-19 10:05:43'),
(257, 211, 'User logged in - Registrar', '::1', NULL, '2026-01-19 10:06:01'),
(258, 211, 'User logged out', '::1', NULL, '2026-01-19 10:08:18'),
(259, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 10:08:31'),
(260, 205, 'User logged out', '::1', NULL, '2026-01-19 10:15:28'),
(261, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-19 10:15:37'),
(262, 6, 'User logged out', '::1', NULL, '2026-01-19 10:15:53'),
(263, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-19 10:16:26'),
(264, 204, 'User logged out', '::1', NULL, '2026-01-19 10:24:50'),
(265, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 10:24:55'),
(266, 205, 'User logged out', '::1', NULL, '2026-01-19 11:02:41'),
(267, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-19 11:02:46'),
(268, 204, 'User logged out', '::1', NULL, '2026-01-19 11:03:11'),
(269, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 13:00:35'),
(270, 100, 'Updated grade for student ID 203 in section ID 1, subject ID 4', '::1', NULL, '2026-01-19 13:28:09'),
(271, 100, 'Updated grade for student ID 201 in section ID 1, subject ID 4', '::1', NULL, '2026-01-19 13:28:09'),
(272, 100, 'User logged out', '::1', NULL, '2026-01-19 13:31:41'),
(273, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 13:31:47'),
(274, 205, 'User logged out', '::1', NULL, '2026-01-19 13:35:34'),
(275, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-19 13:35:41'),
(276, 204, 'User logged out', '::1', NULL, '2026-01-19 14:22:50'),
(277, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 14:22:58'),
(278, 100, 'User logged out', '::1', NULL, '2026-01-19 14:47:58'),
(279, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-19 14:48:03'),
(280, 6, 'User logged out', '::1', NULL, '2026-01-19 14:49:38'),
(281, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 14:49:42'),
(282, 205, 'User logged out', '::1', NULL, '2026-01-19 14:51:13'),
(283, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 14:51:22'),
(284, 100, 'User logged out', '::1', NULL, '2026-01-19 14:53:13'),
(285, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 14:53:19'),
(286, 205, 'User logged out', '::1', NULL, '2026-01-19 14:56:02'),
(287, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-19 14:56:12'),
(288, 204, 'User logged out', '::1', NULL, '2026-01-19 14:56:59'),
(289, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-19 14:57:04'),
(290, 6, 'User logged out', '::1', NULL, '2026-01-19 14:58:09'),
(291, NULL, 'User logged in - Student', '::1', NULL, '2026-01-19 14:58:13'),
(292, NULL, 'User logged out', '::1', NULL, '2026-01-19 14:59:33'),
(293, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 15:03:00'),
(294, 100, 'User logged out', '::1', NULL, '2026-01-19 15:04:52'),
(295, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 15:04:58'),
(296, 205, 'User logged out', '::1', NULL, '2026-01-19 15:06:35'),
(297, NULL, 'User logged in - Student', '::1', NULL, '2026-01-19 15:06:42'),
(298, NULL, 'User logged out', '::1', NULL, '2026-01-19 15:06:56'),
(299, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 15:07:01'),
(300, 205, 'User logged out', '::1', NULL, '2026-01-19 15:07:09'),
(301, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 15:07:14'),
(302, 100, 'User logged out', '::1', NULL, '2026-01-19 15:11:43'),
(303, NULL, 'User logged in - Student', '::1', NULL, '2026-01-19 15:11:49'),
(304, NULL, 'User logged out', '::1', NULL, '2026-01-19 15:12:14'),
(305, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 15:12:23'),
(306, 205, 'User logged out', '::1', NULL, '2026-01-19 15:16:05'),
(307, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-19 15:16:10'),
(308, 6, 'User logged out', '::1', NULL, '2026-01-19 15:46:20'),
(309, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 15:47:22'),
(310, 205, 'User logged out', '::1', NULL, '2026-01-19 15:53:00'),
(311, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-19 15:53:08'),
(312, 204, 'User logged out', '::1', NULL, '2026-01-19 15:53:29'),
(313, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 15:54:16'),
(314, 100, 'User logged out', '::1', NULL, '2026-01-19 15:54:35'),
(315, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-19 15:54:42'),
(316, 6, 'Created student account for James Revilla (2026-0002) - Program ID: 1', '::1', NULL, '2026-01-19 16:00:49'),
(317, 6, 'Created student account for James Revilla (2026-0003) - Program ID: 1', '::1', NULL, '2026-01-19 16:02:06'),
(318, 6, 'User logged out', '::1', NULL, '2026-01-19 16:03:45'),
(319, NULL, 'User logged in - Student', '::1', NULL, '2026-01-19 16:03:57'),
(320, NULL, 'User logged out', '::1', NULL, '2026-01-19 16:04:15'),
(321, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 16:04:23'),
(322, 100, 'User logged out', '::1', NULL, '2026-01-19 16:05:48'),
(323, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-19 16:05:56'),
(324, 4, 'User logged out', '::1', NULL, '2026-01-19 16:14:32'),
(325, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-19 16:14:38'),
(326, 4, 'User logged out', '::1', NULL, '2026-01-19 16:27:21'),
(327, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-19 16:27:27'),
(328, 6, 'Created student account for James Revilla (2026-0002) - Program ID: 1', '::1', NULL, '2026-01-19 16:28:15'),
(329, 6, 'User logged out', '::1', NULL, '2026-01-19 16:33:43'),
(330, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-19 16:46:03'),
(331, 4, 'User logged out', '::1', NULL, '2026-01-19 16:47:04'),
(332, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-19 16:50:18'),
(333, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-20 14:21:14'),
(334, 204, 'User logged out', '::1', NULL, '2026-01-20 14:24:18'),
(335, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-20 14:24:24'),
(336, 4, 'User logged out', '::1', NULL, '2026-01-20 14:25:09'),
(337, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-20 14:25:14'),
(338, 205, 'User logged out', '::1', NULL, '2026-01-20 14:26:35'),
(339, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-20 14:27:08'),
(340, 100, 'User logged out', '::1', NULL, '2026-01-20 14:35:00'),
(341, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-20 14:35:20'),
(342, 204, 'User logged out', '::1', NULL, '2026-01-21 03:08:35'),
(343, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-21 03:08:50'),
(344, 4, 'User logged out', '::1', NULL, '2026-01-21 03:12:31'),
(345, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-21 03:13:14'),
(346, 204, 'User logged out', '::1', NULL, '2026-01-21 03:15:57'),
(347, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-21 03:16:06'),
(348, 205, 'User logged out', '::1', NULL, '2026-01-21 03:20:11'),
(349, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-21 03:20:23'),
(350, 6, 'User logged out', '::1', NULL, '2026-01-21 03:23:40'),
(351, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-21 03:23:50'),
(352, 100, 'Uploaded material: material_subj4_1768965999_6970476fc42e5.pdf for subject ID 4', '::1', NULL, '2026-01-21 03:26:39'),
(353, 100, 'User logged out', '::1', NULL, '2026-01-21 03:27:30'),
(354, NULL, 'User logged in - Student', '::1', NULL, '2026-01-21 03:27:42'),
(355, NULL, 'User logged out', '::1', NULL, '2026-01-21 03:27:55'),
(356, 6, 'User logged in - Registrar', '::1', NULL, '2026-01-21 03:28:00'),
(357, 6, 'User logged out', '::1', NULL, '2026-01-21 03:32:32'),
(358, NULL, 'User logged in - Student', '::1', NULL, '2026-01-21 03:32:43'),
(359, NULL, 'User logged in - Student', '::1', NULL, '2026-01-21 03:38:47'),
(360, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-22 16:01:15'),
(361, 205, 'User logged out', '::1', NULL, '2026-01-22 16:02:16'),
(362, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-22 16:02:27'),
(363, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-24 06:41:38'),
(364, 4, 'User logged out', '::1', NULL, '2026-01-24 06:41:55'),
(365, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-24 07:57:13'),
(366, 204, 'Deactivated branch administrator ID: 210', '::1', NULL, '2026-01-24 07:57:30'),
(367, 204, 'Updated branch admin assignment for user ID 210 to branch ID 2', '::1', NULL, '2026-01-24 07:57:36'),
(368, 204, 'Updated branch admin assignment for user ID 210 to branch ID 2', '::1', NULL, '2026-01-24 07:57:42'),
(369, 204, 'Deactivated branch administrator ID: 210', '::1', NULL, '2026-01-24 07:57:46'),
(370, 204, 'Created branch administrator: James Andrei Revilla (jamessenpai9@gmail.com)', '::1', NULL, '2026-01-24 07:58:17'),
(371, 204, 'Created program: BSOA - Bachelor of Science in Organization Administration', '::1', NULL, '2026-01-24 08:00:47'),
(372, 204, 'User logged out', '::1', NULL, '2026-01-24 08:39:44'),
(373, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-24 09:29:15'),
(374, 205, 'User logged out', '::1', NULL, '2026-01-24 09:38:32'),
(375, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-24 09:40:38'),
(376, 205, 'Locked prelim records for class ID 4', '::1', NULL, '2026-01-24 09:43:18'),
(377, 205, 'Unlocked prelim records for class ID 4', '::1', NULL, '2026-01-24 09:43:23'),
(378, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-30 14:27:51'),
(379, 4, 'Scheduled maintenance: First back up test', '::1', NULL, '2026-01-30 14:36:51'),
(380, 4, 'Enabled maintenance mode', '::1', NULL, '2026-01-30 14:36:59'),
(381, 4, 'Disabled maintenance mode', '::1', NULL, '2026-01-30 14:37:04'),
(382, 4, 'User logged out', '::1', NULL, '2026-01-30 14:39:24'),
(383, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-30 14:39:30'),
(384, 204, 'User logged out', '::1', NULL, '2026-01-30 14:43:55'),
(385, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-30 14:44:01'),
(386, 4, 'User logged out', '::1', NULL, '2026-01-30 14:55:30'),
(387, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-30 14:55:38'),
(388, 204, 'User logged out', '::1', NULL, '2026-01-30 14:55:51'),
(389, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-30 14:55:56'),
(390, 205, 'User logged out', '::1', NULL, '2026-01-30 14:56:09'),
(391, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-30 14:56:18'),
(392, 204, 'Created announcement: Happy Holidays', '::1', NULL, '2026-01-30 15:05:56'),
(393, 204, 'Created program: AWERFDS - sdfsdf', '::1', NULL, '2026-01-30 15:23:43'),
(394, 204, 'User logged out', '::1', NULL, '2026-01-30 15:46:17'),
(395, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-30 15:46:23'),
(396, 205, 'User logged out', '::1', NULL, '2026-01-30 15:47:28'),
(397, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-30 15:49:00'),
(398, 204, 'Created branch: Fairview Branch', '::1', NULL, '2026-01-30 16:44:31'),
(399, 204, 'Created branch: Fairview Branch', '::1', NULL, '2026-01-30 16:54:53'),
(400, 204, 'Deleted branch: VALENZUELA BRANCH (ID: 3)', '::1', NULL, '2026-01-30 16:56:00'),
(401, 204, 'Created branch: Fairview Branch', '::1', NULL, '2026-01-30 16:56:25'),
(402, 204, 'Created branch: Fairview Branch', '::1', NULL, '2026-01-30 16:58:53'),
(403, 204, 'Created program: AWERFDS - sdfsdf', '::1', NULL, '2026-01-30 17:17:32'),
(404, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-31 07:23:34'),
(405, 204, 'Created program: AWERFDS - sdfsdf', '::1', NULL, '2026-01-31 07:33:38'),
(406, 204, 'Updated program: AWERFDS - Ewan ko', '::1', NULL, '2026-01-31 07:34:47'),
(407, 204, 'User logged out', '::1', NULL, '2026-01-31 08:04:52'),
(408, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-31 08:04:58'),
(409, 204, 'User logged out', '::1', NULL, '2026-01-31 08:22:31'),
(410, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-31 08:22:37'),
(411, 204, 'Updated program: BSCS - Bachelor of Science in Computer Science', '::1', NULL, '2026-01-31 08:26:36'),
(412, 204, 'Updated program: BSCS - Bachelor of Science in Computer Science', '::1', NULL, '2026-01-31 08:26:59'),
(413, 204, 'User logged out', '::1', NULL, '2026-01-31 08:38:21'),
(414, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 08:38:30'),
(415, 4, 'User logged out', '::1', NULL, '2026-01-31 08:39:32'),
(416, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 08:39:43'),
(417, 4, 'User logged out', '::1', NULL, '2026-01-31 08:41:54'),
(418, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 08:42:01'),
(419, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 08:42:04'),
(420, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 08:42:07'),
(421, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 08:42:14'),
(422, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 08:42:23'),
(423, 4, 'User logged out', '::1', NULL, '2026-01-31 08:47:41'),
(424, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 08:48:08'),
(425, 4, 'User logged out', '::1', NULL, '2026-01-31 08:48:36'),
(426, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 08:48:44'),
(427, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 08:48:58'),
(428, 4, 'User logged out', '::1', NULL, '2026-01-31 08:53:15'),
(429, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 08:53:24'),
(430, NULL, 'User logged out', '::1', NULL, '2026-01-31 08:53:31'),
(431, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 08:54:16'),
(432, 4, 'User logged out', '::1', NULL, '2026-01-31 09:01:42'),
(433, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 09:01:48'),
(434, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 09:01:54'),
(435, 4, 'User logged out', '::1', NULL, '2026-01-31 09:36:51'),
(436, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 09:36:58'),
(437, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 09:37:00'),
(438, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 09:37:01'),
(439, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 09:37:03'),
(440, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 09:39:09'),
(441, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 09:39:20'),
(442, 4, 'User logged out', '::1', NULL, '2026-01-31 09:45:09'),
(443, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 09:50:06'),
(444, 4, 'User logged out', '::1', NULL, '2026-01-31 10:09:54'),
(445, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:10:09'),
(446, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:10:10'),
(447, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:10:11'),
(448, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:10:12'),
(449, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:36'),
(450, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:45'),
(451, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:46'),
(452, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:51'),
(453, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:52'),
(454, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:53'),
(455, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:53'),
(456, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:53'),
(457, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:54'),
(458, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:54'),
(459, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:54'),
(460, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:54'),
(461, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:54'),
(462, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:55'),
(463, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:55'),
(464, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:55'),
(465, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:55'),
(466, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:56'),
(467, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:56'),
(468, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:56'),
(469, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:56'),
(470, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:56'),
(471, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:57'),
(472, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:13:57'),
(473, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:14:28'),
(474, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:15:08'),
(475, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:18:34'),
(476, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:19:15'),
(477, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 10:19:25'),
(478, 4, 'User logged out', '::1', NULL, '2026-01-31 10:24:24'),
(479, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:30'),
(480, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:31'),
(481, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:32'),
(482, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:32'),
(483, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:33'),
(484, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:33'),
(485, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:33'),
(486, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:35'),
(487, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:36'),
(488, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:37'),
(489, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:38'),
(490, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:38'),
(491, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:38'),
(492, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:38'),
(493, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:24:39'),
(494, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 10:26:48'),
(495, 4, 'User logged out', '::1', NULL, '2026-01-31 10:27:34'),
(496, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 10:27:45'),
(497, 4, 'User logged out', '::1', NULL, '2026-01-31 10:27:50'),
(498, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 10:27:55'),
(499, NULL, 'User logged out', '::1', NULL, '2026-01-31 10:28:00'),
(500, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:28:05'),
(501, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:28:06'),
(502, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:28:07'),
(503, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:28:08'),
(504, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:29:09'),
(505, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 10:32:01'),
(506, 4, 'User logged out', '::1', NULL, '2026-01-31 10:32:15'),
(507, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:32:20'),
(508, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:32:26'),
(509, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:32:27'),
(510, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:32:28'),
(511, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:32:29'),
(512, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:32:29'),
(513, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:32:29'),
(514, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:32:29'),
(515, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:32:29'),
(516, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:33:18'),
(517, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:33:19'),
(518, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:33:20'),
(519, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:33:20'),
(520, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:33:46'),
(521, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:33:48'),
(522, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:33:48'),
(523, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 10:33:59'),
(524, 4, 'User logged out', '::1', NULL, '2026-01-31 10:38:13'),
(525, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:38:22'),
(526, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:39:47'),
(527, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 10:39:59'),
(528, 4, 'User logged out', '::1', NULL, '2026-01-31 10:41:19'),
(529, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:24'),
(530, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:26'),
(531, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:27'),
(532, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:27'),
(533, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:28'),
(534, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:28'),
(535, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:28'),
(536, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:29'),
(537, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:29'),
(538, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:29'),
(539, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:29'),
(540, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:30'),
(541, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:30'),
(542, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:30'),
(543, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:30'),
(544, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:30'),
(545, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:30'),
(546, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:31'),
(547, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:31'),
(548, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:31'),
(549, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:31'),
(550, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:31'),
(551, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:32'),
(552, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:32'),
(553, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:32'),
(554, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:32'),
(555, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:32'),
(556, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:32'),
(557, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:33'),
(558, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:33'),
(559, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:33'),
(560, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:33'),
(561, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:33'),
(562, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:33'),
(563, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:41:33'),
(564, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 10:45:14'),
(565, NULL, 'User logged out', '::1', NULL, '2026-01-31 10:45:19'),
(566, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:24'),
(567, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:25'),
(568, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:26'),
(569, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:28'),
(570, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:29'),
(571, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:33'),
(572, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:33'),
(573, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:34'),
(574, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:34'),
(575, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:34'),
(576, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:34'),
(577, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:35'),
(578, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:35'),
(579, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:35'),
(580, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:35'),
(581, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:35'),
(582, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:36'),
(583, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:36'),
(584, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:36'),
(585, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:45:37'),
(586, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 10:45:48'),
(587, 4, 'User logged out', '::1', NULL, '2026-01-31 10:46:11'),
(588, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 10:46:16'),
(589, NULL, 'User logged out', '::1', NULL, '2026-01-31 10:46:22'),
(590, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 10:46:56'),
(591, NULL, 'User logged out', '::1', NULL, '2026-01-31 10:47:00'),
(592, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:03'),
(593, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:05'),
(594, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:06'),
(595, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:07'),
(596, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:08'),
(597, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:08'),
(598, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:08'),
(599, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:09'),
(600, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:09'),
(601, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:09'),
(602, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:09'),
(603, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:09'),
(604, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:13'),
(605, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:47:16'),
(606, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:48:50'),
(607, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:48:50'),
(608, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:49:02'),
(609, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:49:04'),
(610, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:49:05'),
(611, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:49:05'),
(612, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:49:06'),
(613, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:49:10'),
(614, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:49:10'),
(615, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:49:10'),
(616, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 10:52:17'),
(617, 4, 'User logged out', '::1', NULL, '2026-01-31 10:53:07'),
(618, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 10:53:13'),
(619, NULL, 'User logged out', '::1', NULL, '2026-01-31 10:53:16'),
(620, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:53:20'),
(621, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:53:22'),
(622, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:53:23'),
(623, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:53:23'),
(624, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:53:36'),
(625, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:53:37'),
(626, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:53:38'),
(627, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:53:38'),
(628, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 10:57:22'),
(629, NULL, 'User logged out', '::1', NULL, '2026-01-31 10:57:25'),
(630, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 10:57:33'),
(631, NULL, 'User logged out', '::1', NULL, '2026-01-31 10:57:36'),
(632, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:57:42'),
(633, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:58:36'),
(634, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:58:38'),
(635, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:58:38'),
(636, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 10:58:49'),
(637, 4, 'User logged out', '::1', NULL, '2026-01-31 10:59:02'),
(638, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 10:59:07'),
(639, NULL, 'User logged out', '::1', NULL, '2026-01-31 10:59:10'),
(640, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:59:15'),
(641, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:59:17'),
(642, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 10:59:19'),
(643, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 10:59:19'),
(644, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 10:59:32');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `ip_address`, `details`, `timestamp`) VALUES
(645, 4, 'User logged out', '::1', NULL, '2026-01-31 11:00:16'),
(646, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 11:00:22'),
(647, NULL, 'User logged out', '::1', NULL, '2026-01-31 11:00:26'),
(648, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:00:32'),
(649, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:00:34'),
(650, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:00:36'),
(651, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:00:37'),
(652, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 11:00:37'),
(653, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 11:01:23'),
(654, 4, 'User logged out', '::1', NULL, '2026-01-31 11:02:38'),
(655, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:02:43'),
(656, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:02:44'),
(657, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:02:45'),
(658, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:02:46'),
(659, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 11:02:46'),
(660, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 11:03:01'),
(661, 4, 'User logged out', '::1', NULL, '2026-01-31 11:03:20'),
(662, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:03:24'),
(663, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 11:03:24'),
(664, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:04:33'),
(665, NULL, 'Account locked due to failed login attempts', '::1', NULL, '2026-01-31 11:04:33'),
(666, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 11:05:07'),
(667, 4, 'User logged out', '::1', NULL, '2026-01-31 11:05:22'),
(668, 4, 'Failed login attempt', '::1', NULL, '2026-01-31 11:05:25'),
(669, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 11:05:31'),
(670, 4, 'User logged out', '::1', NULL, '2026-01-31 11:05:37'),
(671, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 11:05:41'),
(672, NULL, 'User logged out', '::1', NULL, '2026-01-31 11:05:45'),
(673, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:05:51'),
(674, NULL, 'User logged in - Student', '::1', NULL, '2026-01-31 11:08:32'),
(675, NULL, 'User logged out', '::1', NULL, '2026-01-31 11:08:35'),
(676, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 11:08:47'),
(677, 4, 'User logged out', '::1', NULL, '2026-01-31 11:10:00'),
(678, NULL, 'Failed login attempt', '::1', NULL, '2026-01-31 11:10:05'),
(679, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 11:10:35'),
(680, 4, 'User logged out', '::1', NULL, '2026-01-31 11:11:46'),
(681, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-31 11:11:56'),
(682, 4, 'User logged out', '::1', NULL, '2026-01-31 13:17:28'),
(683, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-31 13:17:35'),
(684, 204, 'User logged out', '::1', NULL, '2026-01-31 13:18:34'),
(685, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-31 13:18:39'),
(686, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-01 23:37:52'),
(687, 4, 'User logged out', '::1', NULL, '2026-02-01 23:39:43'),
(688, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-01 23:39:53'),
(689, 204, 'User logged out', '::1', NULL, '2026-02-01 23:42:38'),
(690, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-01 23:42:47'),
(691, 4, 'User logged out', '::1', NULL, '2026-02-01 23:43:00'),
(692, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-01 23:43:15'),
(693, 204, 'User logged out', '::1', NULL, '2026-02-01 23:44:54'),
(694, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-02 00:40:52'),
(695, 4, 'User logged in - Super Admin', '192.168.254.104', NULL, '2026-02-03 03:14:00'),
(696, 204, 'User logged in - School Admin', '192.168.254.106', NULL, '2026-02-03 03:14:44'),
(697, 204, 'User logged out', '192.168.254.106', NULL, '2026-02-03 03:18:57'),
(698, 4, 'User logged in - Super Admin', '192.168.254.106', NULL, '2026-02-03 03:19:04'),
(699, 4, 'User logged out', '192.168.254.106', NULL, '2026-02-03 03:24:10'),
(700, 4, 'User logged in - Super Admin', '192.168.254.106', NULL, '2026-02-03 03:24:17'),
(701, 4, 'User logged out', '192.168.254.106', NULL, '2026-02-03 03:25:09'),
(702, 205, 'User logged in - Branch Admin', '192.168.254.106', NULL, '2026-02-03 03:25:15'),
(703, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-03 03:42:00'),
(704, 205, 'User logged out', '::1', NULL, '2026-02-03 03:42:55'),
(705, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-03 03:43:38'),
(706, 6, 'User logged out', '::1', NULL, '2026-02-03 03:45:11'),
(707, 205, 'Failed login attempt', '::1', NULL, '2026-02-03 03:45:17'),
(708, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-03 03:45:24'),
(709, 205, 'User logged out', '::1', NULL, '2026-02-03 03:48:52'),
(710, NULL, 'User logged in - Student', '::1', NULL, '2026-02-03 03:48:57'),
(711, NULL, 'User logged out', '::1', NULL, '2026-02-03 03:49:28'),
(712, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-03 03:49:33'),
(713, 205, 'User logged out', '::1', NULL, '2026-02-03 04:52:59'),
(714, NULL, 'Failed login attempt', '::1', NULL, '2026-02-03 04:53:05'),
(715, NULL, 'User logged in - Student', '::1', NULL, '2026-02-03 04:53:12'),
(716, NULL, 'User logged out', '::1', NULL, '2026-02-03 04:56:17'),
(717, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-03 04:56:25'),
(718, 205, 'User logged out', '::1', NULL, '2026-02-03 05:07:41'),
(719, NULL, 'User logged in - Student', '::1', NULL, '2026-02-03 05:07:47'),
(720, NULL, 'User logged out', '::1', NULL, '2026-02-03 05:08:22'),
(721, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-03 05:08:30'),
(722, 205, 'User logged out', '::1', NULL, '2026-02-03 05:25:54'),
(723, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-03 05:26:02'),
(724, 6, 'User logged out', '::1', NULL, '2026-02-03 05:28:43'),
(725, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-03 05:28:48'),
(726, 205, 'User logged out', '::1', NULL, '2026-02-03 05:31:20'),
(727, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-03 05:31:26'),
(728, 6, 'User logged out', '::1', NULL, '2026-02-03 05:32:19'),
(729, NULL, 'Failed login attempt', '::1', NULL, '2026-02-03 05:32:23'),
(730, NULL, 'User logged in - Student', '::1', NULL, '2026-02-03 05:33:33'),
(731, NULL, 'User logged out', '::1', NULL, '2026-02-03 05:39:55'),
(732, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-03 05:40:01'),
(733, 205, 'User logged out', '::1', NULL, '2026-02-03 05:47:10'),
(734, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-03 05:47:17'),
(735, 4, 'User logged out', '::1', NULL, '2026-02-03 05:48:09'),
(736, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-03 05:48:16'),
(737, 204, 'User logged out', '::1', NULL, '2026-02-03 05:50:10'),
(738, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-03 05:50:15'),
(739, 204, 'User logged out', '::1', NULL, '2026-02-03 05:53:28'),
(740, 205, 'Failed login attempt', '::1', NULL, '2026-02-03 05:53:41'),
(741, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-03 05:53:43'),
(742, 205, 'User logged out', '::1', NULL, '2026-02-03 06:03:32'),
(743, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-03 06:03:39'),
(744, 204, 'Updated program: BSHM - Bachelor of Science in Hospitality Management', '::1', NULL, '2026-02-03 06:13:06'),
(745, 204, 'Updated program: BSHM - Bachelor of Science in Hospitality Management', '::1', NULL, '2026-02-03 06:13:16'),
(746, 204, 'User logged out', '::1', NULL, '2026-02-03 06:14:34'),
(747, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-03 06:14:40'),
(748, 205, 'User logged out', '::1', NULL, '2026-02-03 06:15:49'),
(749, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-03 06:16:00'),
(750, 204, 'User logged out', '::1', NULL, '2026-02-03 06:18:57'),
(751, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-03 06:19:03'),
(752, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-04 13:10:20'),
(753, 4, 'User logged out', '::1', NULL, '2026-02-04 13:10:36'),
(754, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-04 13:11:04'),
(755, 100, 'User logged out', '::1', NULL, '2026-02-04 13:12:59'),
(756, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-05 08:26:36'),
(757, 204, 'Deleted branch: Fairview Branch (ID: 8)', '::1', NULL, '2026-02-05 08:27:32'),
(758, 204, 'User logged out', '::1', NULL, '2026-02-05 08:27:53'),
(759, 205, 'Failed login attempt', '::1', NULL, '2026-02-05 08:27:58'),
(760, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 08:28:06'),
(761, 205, 'User logged out', '::1', NULL, '2026-02-05 08:30:18'),
(762, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-05 08:30:23'),
(763, 204, 'User logged out', '::1', NULL, '2026-02-05 08:35:38'),
(764, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 08:35:45'),
(765, 205, 'User logged out', '::1', NULL, '2026-02-05 08:36:37'),
(766, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-05 08:36:43'),
(767, 204, 'User logged out', '::1', NULL, '2026-02-05 08:43:27'),
(768, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 08:43:35'),
(769, 205, 'User logged out', '::1', NULL, '2026-02-05 08:44:54'),
(770, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-05 08:44:58'),
(771, 204, 'Created branch: Fairview Branch', '::1', NULL, '2026-02-05 08:45:52'),
(772, 204, 'Updated branch: Fairview Branch (ID: 9)', '::1', NULL, '2026-02-05 08:53:08'),
(773, 204, 'User logged out', '::1', NULL, '2026-02-05 09:07:08'),
(774, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 09:07:17'),
(775, 205, 'User logged out', '::1', NULL, '2026-02-05 09:13:19'),
(776, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-05 09:13:26'),
(777, 6, 'User logged out', '::1', NULL, '2026-02-05 10:06:44'),
(778, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-05 10:06:52'),
(779, 4, 'User logged out', '::1', NULL, '2026-02-05 10:07:07'),
(780, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-05 10:07:14'),
(781, 6, 'Created student account for James Revilla (2026-0002) - Program ID: 1', '::1', NULL, '2026-02-05 10:10:41'),
(782, 6, 'User logged out', '::1', NULL, '2026-02-05 10:21:52'),
(783, NULL, 'User logged in - Student', '::1', NULL, '2026-02-05 10:21:57'),
(784, NULL, 'User logged out', '::1', NULL, '2026-02-05 10:23:59'),
(785, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-05 10:24:07'),
(786, 6, 'User logged out', '::1', NULL, '2026-02-05 10:26:34'),
(787, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-05 10:26:41'),
(788, 4, 'User logged out', '::1', NULL, '2026-02-05 10:27:42'),
(789, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-05 10:27:53'),
(790, 6, 'User logged out', '::1', NULL, '2026-02-05 10:29:02'),
(791, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-05 10:29:14'),
(792, 204, 'User logged out', '::1', NULL, '2026-02-05 10:29:27'),
(793, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 10:29:32'),
(794, 205, 'User logged out', '::1', NULL, '2026-02-05 10:29:49'),
(795, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-05 10:29:54'),
(796, 204, 'User logged out', '::1', NULL, '2026-02-05 10:30:30'),
(797, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-05 10:30:36'),
(798, 6, 'User logged out', '::1', NULL, '2026-02-05 10:33:41'),
(799, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 10:33:46'),
(800, 205, 'User logged out', '::1', NULL, '2026-02-05 10:46:52'),
(801, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-05 10:46:59'),
(802, 6, 'Created student account for James Andrei Revilla (2026-0003) - Program ID: 5', '::1', NULL, '2026-02-05 10:51:25'),
(803, 6, 'User logged out', '::1', NULL, '2026-02-05 10:51:44'),
(804, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 10:51:51'),
(805, 205, 'User logged out', '::1', NULL, '2026-02-05 11:16:53'),
(806, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-05 11:16:59'),
(807, 4, 'User logged out', '::1', NULL, '2026-02-05 11:17:17'),
(808, 224, 'Failed login attempt', '::1', NULL, '2026-02-05 11:17:21'),
(809, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-05 11:20:57'),
(810, 4, 'User logged out', '::1', NULL, '2026-02-05 11:21:22'),
(811, 224, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 11:21:27'),
(812, 224, 'User logged out', '::1', NULL, '2026-02-05 11:22:02'),
(813, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 11:22:07'),
(814, 205, 'User logged out', '::1', NULL, '2026-02-05 11:22:26'),
(815, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-05 11:23:55'),
(816, 100, 'User logged out', '::1', NULL, '2026-02-05 11:24:38'),
(817, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 11:24:42'),
(818, 205, 'User logged out', '::1', NULL, '2026-02-05 11:26:17'),
(819, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-05 11:26:25'),
(820, 100, 'User logged out', '::1', NULL, '2026-02-05 11:29:44'),
(821, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-05 11:29:49'),
(822, 4, 'User logged out', '::1', NULL, '2026-02-05 11:30:02'),
(823, 216, 'Failed login attempt', '::1', NULL, '2026-02-05 11:30:06'),
(824, 216, 'Failed login attempt', '::1', NULL, '2026-02-05 11:30:15'),
(825, 216, 'Failed login attempt', '::1', NULL, '2026-02-05 11:30:21'),
(826, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-05 11:30:26'),
(827, 4, 'User logged out', '::1', NULL, '2026-02-05 11:30:59'),
(828, 216, 'User logged in - Teacher', '::1', NULL, '2026-02-05 11:31:03'),
(829, 216, 'User logged out', '::1', NULL, '2026-02-05 11:31:47'),
(830, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-05 11:31:52'),
(831, 4, 'User logged out', '::1', NULL, '2026-02-05 11:32:02'),
(832, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-05 11:32:07'),
(833, 204, 'User logged out', '::1', NULL, '2026-02-05 11:32:28'),
(834, 210, 'Failed login attempt', '::1', NULL, '2026-02-05 11:32:32'),
(835, 210, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 11:32:35'),
(836, 210, 'User logged out', '::1', NULL, '2026-02-05 12:22:45'),
(837, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-05 12:22:58'),
(838, 6, 'User logged out', '::1', NULL, '2026-02-05 13:21:54'),
(839, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-05 13:21:59'),
(840, 100, 'User logged out', '::1', NULL, '2026-02-05 14:07:18'),
(841, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-05 14:07:27'),
(842, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-06 03:25:00'),
(843, 4, 'User logged out', '::1', NULL, '2026-02-06 03:36:01'),
(844, 4, 'Failed login attempt', '::1', NULL, '2026-02-10 15:46:42'),
(845, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-10 15:47:01'),
(846, 4, 'User logged out', '::1', NULL, '2026-02-10 15:47:36'),
(847, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-10 15:47:41'),
(848, 204, 'User logged out', '::1', NULL, '2026-02-10 15:48:10'),
(849, 6, 'Failed login attempt', '::1', NULL, '2026-02-10 15:48:18'),
(850, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-10 15:48:23'),
(851, 6, 'User logged out', '::1', NULL, '2026-02-10 15:49:08'),
(852, 205, 'Failed login attempt', '::1', NULL, '2026-02-10 15:49:17'),
(853, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-10 15:49:20'),
(854, 205, 'User logged out', '::1', NULL, '2026-02-10 15:53:29'),
(855, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-10 15:53:34'),
(856, 100, 'User logged out', '::1', NULL, '2026-02-10 15:55:06'),
(857, NULL, 'User logged in - Student', '::1', NULL, '2026-02-10 15:55:11'),
(858, NULL, 'User logged out', '::1', NULL, '2026-02-10 15:57:41'),
(859, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-10 15:57:49'),
(860, 100, 'User logged out', '::1', NULL, '2026-02-10 15:58:01'),
(861, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-10 15:58:07'),
(862, 205, 'User logged out', '::1', NULL, '2026-02-10 16:04:45'),
(863, NULL, 'User logged in - Student', '::1', NULL, '2026-02-10 16:04:52'),
(864, NULL, 'User logged out', '::1', NULL, '2026-02-10 16:04:57'),
(865, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-10 16:05:05'),
(866, 100, 'User logged out', '::1', NULL, '2026-02-10 16:08:32'),
(867, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-10 16:13:21'),
(868, 205, 'User logged out', '::1', NULL, '2026-02-10 16:13:34'),
(869, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-10 16:13:40'),
(870, 6, 'User logged out', '::1', NULL, '2026-02-10 16:14:51'),
(871, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-10 16:14:55'),
(872, 205, 'User logged out', '::1', NULL, '2026-02-10 16:17:58'),
(873, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-10 16:18:10'),
(874, 100, 'User logged out', '::1', NULL, '2026-02-10 16:18:29'),
(875, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-10 16:18:34'),
(876, 205, 'User logged out', '::1', NULL, '2026-02-10 16:19:05'),
(877, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-10 16:19:14'),
(878, 100, 'Updated grade for student ID 226 in section ID 1, subject ID 3', '::1', NULL, '2026-02-10 16:22:34'),
(879, 100, 'User logged out', '::1', NULL, '2026-02-10 16:22:53'),
(880, NULL, 'User logged in - Student', '::1', NULL, '2026-02-10 16:22:57'),
(881, NULL, 'User logged out', '::1', NULL, '2026-02-10 16:23:08'),
(882, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-10 16:23:22'),
(883, 4, 'User logged out', '::1', NULL, '2026-02-10 16:23:38'),
(884, NULL, 'User logged in - Student', '::1', NULL, '2026-02-10 16:23:41'),
(885, NULL, 'User logged out', '::1', NULL, '2026-02-10 16:24:52'),
(886, NULL, 'User logged in - Student', '::1', NULL, '2026-02-10 16:24:56'),
(887, NULL, 'User logged out', '::1', NULL, '2026-02-10 16:29:51'),
(888, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-10 16:30:02'),
(889, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-11 03:43:14'),
(890, 4, 'User logged out', '::1', NULL, '2026-02-11 03:43:47'),
(891, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-11 03:43:54'),
(892, 205, 'User logged out', '::1', NULL, '2026-02-11 03:44:00'),
(893, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-11 03:44:05'),
(894, 204, 'Updated branch: Caloocan Branch (ID: 4)', '::1', NULL, '2026-02-11 03:45:17'),
(895, 204, 'User logged out', '::1', NULL, '2026-02-11 03:45:31'),
(896, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-11 03:45:37'),
(897, 205, 'User logged out', '::1', NULL, '2026-02-11 03:48:42'),
(898, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-11 03:48:50'),
(899, 6, 'Created student account for Eugine Almira (2026-0004) - Program ID: 2', '::1', NULL, '2026-02-11 03:52:25'),
(900, 6, 'User logged out', '::1', NULL, '2026-02-11 03:53:32'),
(901, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-11 03:53:41'),
(902, 100, 'Updated grade for student ID 226 in section ID 1, subject ID 3', '::1', NULL, '2026-02-11 03:56:33'),
(903, 100, 'Created assessment: Activity Programming', '::1', NULL, '2026-02-11 03:57:40'),
(904, 100, 'User logged out', '::1', NULL, '2026-02-11 03:57:54'),
(905, NULL, 'User logged in - Student', '::1', NULL, '2026-02-11 03:58:04'),
(906, 4, 'Failed login attempt', '::1', NULL, '2026-02-16 04:33:22'),
(907, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-16 04:33:26'),
(908, 4, 'User logged out', '::1', NULL, '2026-02-16 04:33:51'),
(909, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-16 04:34:01'),
(910, 204, 'User logged out', '::1', NULL, '2026-02-16 04:35:27'),
(911, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-17 06:24:04'),
(912, 205, 'User logged out', '::1', NULL, '2026-02-17 06:36:52'),
(913, NULL, 'User logged in - Student', '::1', NULL, '2026-02-17 06:36:57'),
(914, NULL, 'User logged out', '::1', NULL, '2026-02-17 06:38:16'),
(915, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-17 06:38:25'),
(916, 100, 'Updated grade for student ID 226 in section ID 1, subject ID 3', '::1', NULL, '2026-02-17 06:40:12'),
(917, 100, 'User logged out', '::1', NULL, '2026-02-17 06:40:27'),
(918, NULL, 'User logged in - Student', '::1', NULL, '2026-02-17 06:40:30'),
(919, NULL, 'User logged out', '::1', NULL, '2026-02-17 06:41:30'),
(920, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-17 06:41:37'),
(921, 100, 'Updated grade for student ID 226 in section ID 1, subject ID 5', '::1', NULL, '2026-02-17 06:42:31'),
(922, 100, 'User logged out', '::1', NULL, '2026-02-17 06:42:39'),
(923, NULL, 'User logged in - Student', '::1', NULL, '2026-02-17 06:42:45'),
(924, NULL, 'User logged out', '::1', NULL, '2026-02-17 06:44:04'),
(925, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-17 06:44:15'),
(926, 100, 'User logged out', '::1', NULL, '2026-02-17 06:44:52'),
(927, NULL, 'User logged in - Student', '::1', NULL, '2026-02-17 06:44:56'),
(928, NULL, 'User logged out', '::1', NULL, '2026-02-17 07:07:42'),
(929, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-17 07:22:47'),
(930, 6, 'User logged out', '::1', NULL, '2026-02-17 07:23:20'),
(931, NULL, 'User logged in - Student', '::1', NULL, '2026-02-17 07:25:43'),
(932, NULL, 'User logged out', '::1', NULL, '2026-02-17 07:26:21'),
(933, NULL, 'User logged in - Student', '::1', NULL, '2026-02-17 07:26:27'),
(934, NULL, 'User logged out', '::1', NULL, '2026-02-17 07:26:35'),
(935, 6, 'Failed login attempt', '::1', NULL, '2026-02-17 07:26:42'),
(936, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-17 07:26:47'),
(937, 6, 'User logged out', '::1', NULL, '2026-02-17 07:37:34'),
(938, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-17 07:37:39'),
(939, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-24 13:30:41'),
(940, 6, 'User logged out', '::1', NULL, '2026-02-24 13:31:35'),
(941, 204, 'Failed login attempt', '::1', NULL, '2026-02-24 13:31:41'),
(942, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-24 13:31:49'),
(943, 204, 'User logged out', '::1', NULL, '2026-02-24 13:32:59'),
(944, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-24 13:43:07'),
(945, 6, 'User logged out', '::1', NULL, '2026-02-24 13:43:48'),
(946, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-24 13:43:54'),
(947, 4, 'User logged out', '::1', NULL, '2026-02-24 13:46:37'),
(948, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-24 13:46:50'),
(949, 205, 'User logged out', '::1', NULL, '2026-02-24 14:35:20'),
(950, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-24 14:35:28'),
(951, 6, 'User logged out', '::1', NULL, '2026-02-24 17:05:45'),
(952, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-24 17:06:35'),
(953, 100, 'User logged in - Teacher', '::1', NULL, '2026-02-24 17:07:40'),
(954, 4, 'User logged out', '::1', NULL, '2026-02-24 17:07:44'),
(955, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-24 17:07:50'),
(956, 6, 'User logged out', '::1', NULL, '2026-02-24 17:09:03'),
(957, NULL, 'User logged in - Student', '::1', NULL, '2026-02-24 17:09:09'),
(958, 100, 'Updated grade for student ID 226 in section ID 1, subject ID 3', '::1', NULL, '2026-02-24 17:10:15'),
(959, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-25 19:40:41'),
(960, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-27 07:35:23'),
(961, 6, 'User logged out', '::1', NULL, '2026-02-27 08:28:04'),
(962, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-27 08:28:11'),
(963, 205, 'User logged out', '::1', NULL, '2026-02-27 10:11:29'),
(964, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-27 10:11:39'),
(965, 204, 'Created program: ACT - Associate Computer Technology', '::1', NULL, '2026-02-27 10:21:13'),
(966, 4, 'User logged in - Super Admin', '::1', NULL, '2026-02-27 14:13:57'),
(967, 4, 'User logged out', '::1', NULL, '2026-02-27 14:14:54'),
(968, 204, 'User logged in - School Admin', '::1', NULL, '2026-02-27 14:15:01'),
(969, 204, 'User logged out', '::1', NULL, '2026-02-27 14:16:12'),
(970, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-02-27 14:16:20'),
(971, 6, 'User logged in - Registrar', '::1', NULL, '2026-02-28 17:15:31'),
(972, 6, 'User logged out', '::1', NULL, '2026-02-28 17:46:14'),
(973, NULL, 'User logged in - Student', '::1', NULL, '2026-02-28 17:46:34'),
(974, NULL, 'User logged out', '::1', NULL, '2026-02-28 17:46:54'),
(975, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 02:32:39'),
(976, 6, 'User logged out', '::1', NULL, '2026-03-01 02:34:31'),
(977, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-01 02:34:40'),
(978, 204, 'User logged out', '::1', NULL, '2026-03-01 02:39:10'),
(979, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 02:39:22'),
(980, 6, 'User logged out', '::1', NULL, '2026-03-01 02:41:52'),
(981, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-01 02:41:58'),
(982, 204, 'User logged out', '::1', NULL, '2026-03-01 02:43:10'),
(983, 100, 'User logged in - Teacher', '::1', NULL, '2026-03-01 02:43:15'),
(984, 100, 'User logged out', '::1', NULL, '2026-03-01 02:44:34'),
(985, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-01 02:44:40'),
(986, 204, 'User logged out', '::1', NULL, '2026-03-01 03:38:23'),
(987, 100, 'User logged in - Teacher', '::1', NULL, '2026-03-01 03:38:28'),
(988, 100, 'User logged out', '::1', NULL, '2026-03-01 03:38:48'),
(989, 4, 'User logged in - Super Admin', '::1', NULL, '2026-03-01 03:39:22'),
(990, 4, 'User logged out', '::1', NULL, '2026-03-01 03:40:36'),
(991, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 03:40:46'),
(992, 6, 'Program enrollment: student 232, type regular, program 1, level 2, semester 2nd, enrolled_subjects 7', '::1', NULL, '2026-03-01 04:05:11'),
(993, 6, 'User logged out', '::1', NULL, '2026-03-01 04:06:11'),
(994, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-03-01 04:06:15'),
(995, 205, 'User logged out', '::1', NULL, '2026-03-01 04:17:16'),
(996, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 04:21:15'),
(997, 6, 'Program enrollment: student 232, type regular, program 1, level 2, semester 1st, enrolled_subjects 9', '::1', NULL, '2026-03-01 04:35:28'),
(998, 6, 'Program enrollment: student 226, type regular, program 1, level 3, semester 1st, enrolled_subjects 6', '::1', NULL, '2026-03-01 04:35:48'),
(999, 4, 'User logged in - Super Admin', '::1', NULL, '2026-03-01 05:27:57'),
(1000, 4, 'User logged out', '::1', NULL, '2026-03-01 05:46:43'),
(1001, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 05:46:52'),
(1002, 6, 'User logged out', '::1', NULL, '2026-03-01 05:47:06'),
(1003, 4, 'User logged in - Super Admin', '::1', NULL, '2026-03-01 05:50:22'),
(1004, 4, 'User logged out', '::1', NULL, '2026-03-01 05:50:36'),
(1005, NULL, 'Failed login attempt', '::1', NULL, '2026-03-01 05:50:40'),
(1006, NULL, 'Failed login attempt', '::1', NULL, '2026-03-01 05:50:42'),
(1007, NULL, 'Failed login attempt', '::1', NULL, '2026-03-01 05:50:44'),
(1008, NULL, 'Failed login attempt', '::1', NULL, '2026-03-01 05:52:21'),
(1009, NULL, 'Failed login attempt', '::1', NULL, '2026-03-01 05:52:28'),
(1010, NULL, 'User logged in - Student', '::1', NULL, '2026-03-01 05:52:35'),
(1011, NULL, 'User logged out', '::1', NULL, '2026-03-01 06:02:47'),
(1012, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-03-01 06:02:53'),
(1013, 205, 'User logged out', '::1', NULL, '2026-03-01 06:13:33'),
(1014, NULL, 'User logged in - Student', '::1', NULL, '2026-03-01 06:13:39'),
(1015, 4, 'User logged in - Super Admin', '::1', NULL, '2026-03-01 07:19:52'),
(1016, 204, 'User logged in - School Admin', '192.168.254.107', NULL, '2026-03-01 07:21:29'),
(1017, 205, 'Failed login attempt', '192.168.254.104', NULL, '2026-03-01 07:21:48'),
(1018, 205, 'User logged in - Branch Admin', '192.168.254.104', NULL, '2026-03-01 07:21:52'),
(1019, 204, 'User logged out', '192.168.254.107', NULL, '2026-03-01 07:24:17'),
(1020, 4, 'User logged out', '::1', NULL, '2026-03-01 07:24:29'),
(1021, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 07:24:34'),
(1022, 100, 'User logged in - Teacher', '192.168.254.107', NULL, '2026-03-01 07:25:17'),
(1023, 6, 'Created student account for James Andrei Revilla (2026-0001) - Program ID: 1', '::1', NULL, '2026-03-01 07:35:43'),
(1024, 6, 'Created student account for Andrei James Subaru (2026-0002) - Program ID: 1', '::1', NULL, '2026-03-01 07:36:42'),
(1025, 6, 'Program enrollment: student 234, type regular, program 1, level 1, semester 1st, enrolled_subjects 9', '::1', NULL, '2026-03-01 07:37:17'),
(1026, 6, 'Non-regular enrollment: student 235, type transferee, program 1, level 2, semester 1st, enrolled_subjects 6, completed 3', '::1', NULL, '2026-03-01 07:38:37'),
(1027, 6, 'User logged out', '::1', NULL, '2026-03-01 07:46:46'),
(1028, NULL, 'User logged in - Student', '::1', NULL, '2026-03-01 07:46:52'),
(1029, 235, 'User logged in - Student', '192.168.254.108', NULL, '2026-03-01 07:47:48'),
(1030, NULL, 'User logged out', '::1', NULL, '2026-03-01 07:56:30'),
(1031, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 07:56:44'),
(1032, 6, 'User logged out', '::1', NULL, '2026-03-01 08:03:12'),
(1033, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-01 08:03:21'),
(1034, 204, 'User logged out', '::1', NULL, '2026-03-01 08:04:53'),
(1035, 100, 'Failed login attempt', '::1', NULL, '2026-03-01 08:05:01'),
(1036, 100, 'User logged in - Teacher', '::1', NULL, '2026-03-01 08:05:07'),
(1037, 100, 'Uploaded material: material_subj15_1772352368_69a3f3704db52.pdf for subject ID 15', '::1', NULL, '2026-03-01 08:06:08'),
(1038, 205, 'User logged out', '192.168.254.104', NULL, '2026-03-01 08:06:32'),
(1039, NULL, 'Failed login attempt', '192.168.254.104', NULL, '2026-03-01 08:06:48'),
(1040, NULL, 'User logged in - Student', '192.168.254.104', NULL, '2026-03-01 08:06:55'),
(1041, 100, 'User logged out', '::1', NULL, '2026-03-01 08:08:33'),
(1042, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 08:08:40'),
(1043, 6, 'User logged out', '::1', NULL, '2026-03-01 08:08:59'),
(1044, 100, 'User logged in - Teacher', '::1', NULL, '2026-03-01 08:09:05'),
(1045, 100, 'User logged out', '::1', NULL, '2026-03-01 08:28:03'),
(1046, 6, 'Failed login attempt', '::1', NULL, '2026-03-01 08:28:11'),
(1047, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 08:28:16'),
(1048, 6, 'Year advancement: student 234, type regular, from level 1 to 2, semester 1st', '::1', NULL, '2026-03-01 08:37:08'),
(1049, 6, 'Program enrollment: student 234, type regular, program 1, level 2, semester 2nd, enrolled_subjects 7', '::1', NULL, '2026-03-01 08:38:56'),
(1050, 6, 'Program enrollment: student 234, type regular, program 1, level 2, semester 2nd, enrolled_subjects 0', '::1', NULL, '2026-03-01 08:47:59'),
(1051, 6, 'Program enrollment: student 234, type regular, program 1, level 2, semester 2nd, enrolled_subjects 0', '::1', NULL, '2026-03-01 09:05:28'),
(1052, 6, 'Program enrollment: student 234, type regular, program 1, level 2, semester 2nd, enrolled_subjects 0', '::1', NULL, '2026-03-01 09:05:56'),
(1053, 6, 'Program enrollment: student 234, type regular, program 1, level 2, semester 2nd, enrolled_subjects 0', '::1', NULL, '2026-03-01 09:06:27'),
(1054, 6, 'Program enrollment: student 234, type regular, program 1, level 2, semester 2nd, enrolled_subjects 0', '::1', NULL, '2026-03-01 09:08:31'),
(1055, 6, 'Down payment recorded: student 234, amount ₱2,125.00, method cash, ref DP-20260301-0234-e0ce', '::1', NULL, '2026-03-01 09:08:33'),
(1056, 6, 'Program enrollment: student 234, type regular, program 1, level 2, semester 2nd, enrolled_subjects 0', '::1', NULL, '2026-03-01 09:09:56'),
(1057, 6, 'Down payment recorded: student 234, amount ₱3,000.00, method cash, ref DP-20260301-0234-9dbc', '::1', NULL, '2026-03-01 09:10:13'),
(1058, 6, 'Year advancement: student 234, type regular, from level 2 to 3, semester 1st, downpayment ₱2,800.00', '::1', NULL, '2026-03-01 09:10:37'),
(1059, 6, 'Program enrollment: student 234, type regular, program 1, level 3, semester 2nd, enrolled_subjects 6', '::1', NULL, '2026-03-01 09:17:54'),
(1060, 6, 'Down payment recorded: student 234, amount ₱3,499.96, method cash, ref DP-20260301-0234-a9de', '::1', NULL, '2026-03-01 09:18:00'),
(1061, 6, 'User logged out', '::1', NULL, '2026-03-01 09:18:52'),
(1062, 4, 'User logged in - Super Admin', '::1', NULL, '2026-03-01 09:18:59'),
(1063, 4, 'User logged out', '::1', NULL, '2026-03-01 09:19:09'),
(1064, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 09:19:18'),
(1065, 6, 'Created student account for James Andrei Revilla (2026-0003) - Program ID: 1', '::1', NULL, '2026-03-01 09:19:46'),
(1066, 6, 'Program enrollment: student 236, type regular, program 1, level 1, semester 1st, enrolled_subjects 9', '::1', NULL, '2026-03-01 09:20:03'),
(1067, 6, 'Down payment recorded: student 236, amount ₱3,499.98, method cash, ref DP-20260301-0236-cce7', '::1', NULL, '2026-03-01 09:20:13'),
(1068, 6, 'Year advancement: student 236, type regular, from level 1 to 2, semester 1st, downpayment ₱3,500.00', '::1', NULL, '2026-03-01 09:21:05'),
(1069, 6, 'User logged out', '::1', NULL, '2026-03-01 09:28:48'),
(1070, 4, 'User logged in - Super Admin', '::1', NULL, '2026-03-01 09:28:54'),
(1071, 4, 'User logged out', '::1', NULL, '2026-03-01 09:29:08'),
(1072, 6, 'Failed login attempt', '::1', NULL, '2026-03-01 09:29:14'),
(1073, 6, 'Failed login attempt', '::1', NULL, '2026-03-01 09:29:21'),
(1074, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-01 09:29:24'),
(1075, 6, 'Created student account for James Andrei Revilla (2026-0003) - Program ID: 1', '::1', NULL, '2026-03-01 09:36:32'),
(1076, 6, 'Program enrollment: student 237, type regular, program 1, level 1, semester 1st, enrolled_subjects 9', '::1', NULL, '2026-03-01 09:37:19'),
(1077, 6, 'Down payment recorded: student 237, amount ₱2,500.00, method cash, ref DP-20260301-0237-e234', '::1', NULL, '2026-03-01 09:38:13'),
(1078, 6, 'Year advancement: student 237, type regular, from level 1 to 2, semester 1st, downpayment ₱2,500.00', '::1', NULL, '2026-03-01 09:39:05'),
(1079, 6, 'User logged out', '::1', NULL, '2026-03-01 09:40:15'),
(1080, 100, 'Failed login attempt', '::1', NULL, '2026-03-01 09:40:26'),
(1081, 100, 'Failed login attempt', '::1', NULL, '2026-03-01 09:40:43'),
(1082, 100, 'Failed login attempt', '::1', NULL, '2026-03-01 09:40:45'),
(1083, 100, 'User logged in - Teacher', '::1', NULL, '2026-03-01 09:41:45'),
(1084, 100, 'User logged out', '::1', NULL, '2026-03-01 09:42:10'),
(1085, NULL, 'User logged in - Student', '::1', NULL, '2026-03-01 09:42:25'),
(1086, NULL, 'User logged out', '::1', NULL, '2026-03-01 09:43:52'),
(1087, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-01 09:51:44'),
(1088, 204, 'User logged out', '::1', NULL, '2026-03-01 09:52:31'),
(1089, NULL, 'User logged in - Student', '::1', NULL, '2026-03-01 09:52:34'),
(1090, NULL, 'User logged out', '::1', NULL, '2026-03-01 10:30:46'),
(1091, NULL, 'User logged in - Student', '::1', NULL, '2026-03-02 01:41:09'),
(1092, NULL, 'User logged out', '::1', NULL, '2026-03-02 01:41:35'),
(1093, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 02:19:17'),
(1094, 6, 'User logged out', '::1', NULL, '2026-03-02 02:20:10'),
(1095, 100, 'User logged in - Teacher', '::1', NULL, '2026-03-02 02:20:15'),
(1096, 4, 'User logged in - Super Admin', '::1', NULL, '2026-03-02 12:06:08'),
(1097, 4, 'User logged out', '::1', NULL, '2026-03-02 12:06:30'),
(1098, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-02 12:06:37'),
(1099, 204, 'User logged out', '::1', NULL, '2026-03-02 12:07:30'),
(1100, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-03-02 12:07:37'),
(1101, 205, 'User logged out', '::1', NULL, '2026-03-02 12:10:48'),
(1102, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 12:10:59'),
(1103, 6, 'User logged out', '::1', NULL, '2026-03-02 13:09:45'),
(1104, 4, 'User logged in - Super Admin', '::1', NULL, '2026-03-02 13:10:20'),
(1105, 4, 'User logged out', '::1', NULL, '2026-03-02 13:12:04'),
(1106, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-02 13:12:08'),
(1107, 204, 'User logged out', '::1', NULL, '2026-03-02 13:13:05'),
(1108, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-03-02 13:13:09'),
(1109, 205, 'User logged out', '::1', NULL, '2026-03-02 13:13:52'),
(1110, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 13:13:57'),
(1111, 6, 'User logged out', '::1', NULL, '2026-03-02 13:15:16'),
(1112, 100, 'User logged in - Teacher', '::1', NULL, '2026-03-02 13:15:20'),
(1113, 100, 'User logged out', '::1', NULL, '2026-03-02 13:17:25'),
(1114, NULL, 'User logged in - Student', '::1', NULL, '2026-03-02 13:17:29'),
(1115, NULL, 'User logged out', '::1', NULL, '2026-03-02 13:18:45'),
(1116, 235, 'User logged in - Student', '::1', NULL, '2026-03-02 13:18:54'),
(1117, 235, 'User logged out', '::1', NULL, '2026-03-02 13:20:13'),
(1118, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 13:20:18'),
(1119, 6, 'Year advancement: student 237, type regular, from level 2 to 3, semester 1st, downpayment ₱3,500.00', '::1', NULL, '2026-03-02 13:22:43'),
(1120, 6, 'User logged out', '::1', NULL, '2026-03-02 13:27:49'),
(1121, 204, 'Failed login attempt', '::1', NULL, '2026-03-02 13:27:56'),
(1122, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-02 13:28:05'),
(1123, 204, 'User logged out', '::1', NULL, '2026-03-02 13:29:48'),
(1124, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 13:29:58'),
(1125, 6, 'User logged out', '::1', NULL, '2026-03-02 13:30:43'),
(1126, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-02 13:34:58'),
(1127, 204, 'User logged out', '::1', NULL, '2026-03-02 13:40:22'),
(1128, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 13:40:38'),
(1129, 6, 'User logged out', '::1', NULL, '2026-03-02 13:45:04'),
(1130, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-02 13:45:14'),
(1131, 204, 'User logged out', '::1', NULL, '2026-03-02 13:47:20'),
(1132, 6, 'Failed login attempt', '::1', NULL, '2026-03-02 13:47:31'),
(1133, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 13:47:34'),
(1134, 6, 'User logged out', '::1', NULL, '2026-03-02 13:49:49'),
(1135, 4, 'User logged in - Super Admin', '::1', NULL, '2026-03-02 13:51:24'),
(1136, 4, 'User logged out', '::1', NULL, '2026-03-02 13:55:35'),
(1137, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-02 13:55:40'),
(1138, 204, 'User logged out', '::1', NULL, '2026-03-02 14:01:19'),
(1139, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-02 14:01:26'),
(1140, 204, 'User logged out', '::1', NULL, '2026-03-02 14:02:31'),
(1141, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 14:02:36'),
(1142, 6, 'User logged out', '::1', NULL, '2026-03-02 14:05:04'),
(1143, 100, 'User logged in - Teacher', '::1', NULL, '2026-03-02 14:05:10'),
(1144, 100, 'User logged out', '::1', NULL, '2026-03-02 14:05:40'),
(1145, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-03-02 14:05:45'),
(1146, 205, 'Created teacher account for John Drew Suycano (Yujinjae05@gmail.com)', '::1', NULL, '2026-03-02 14:11:20'),
(1147, 205, 'User logged out', '::1', NULL, '2026-03-02 14:14:19'),
(1148, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 14:14:24'),
(1149, 6, 'User logged out', '::1', NULL, '2026-03-02 14:18:45'),
(1150, NULL, 'User logged in - Student', '::1', NULL, '2026-03-02 14:18:49'),
(1151, NULL, 'User logged out', '::1', NULL, '2026-03-02 14:26:38'),
(1152, 204, 'Failed login attempt', '::1', NULL, '2026-03-02 14:27:34'),
(1153, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-02 14:27:40'),
(1154, 204, 'User logged out', '::1', NULL, '2026-03-02 15:21:39'),
(1155, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 15:25:04'),
(1156, 6, 'User logged out', '::1', NULL, '2026-03-02 15:42:58'),
(1157, 100, 'User logged in - Teacher', '::1', NULL, '2026-03-02 15:43:08'),
(1158, 100, 'User logged out', '::1', NULL, '2026-03-02 15:43:37'),
(1159, NULL, 'User logged in - Student', '::1', NULL, '2026-03-02 15:43:51'),
(1160, NULL, 'User logged out', '::1', NULL, '2026-03-02 15:44:21'),
(1161, NULL, 'User logged in - Student', '::1', NULL, '2026-03-02 15:48:38'),
(1162, NULL, 'User logged out', '::1', NULL, '2026-03-02 15:51:41'),
(1163, 204, 'Failed login attempt', '::1', NULL, '2026-03-02 15:51:55'),
(1164, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-02 15:51:58'),
(1165, 204, 'User logged out', '::1', NULL, '2026-03-02 16:06:33'),
(1166, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 16:06:42'),
(1167, 6, 'User logged out', '::1', NULL, '2026-03-02 16:38:27'),
(1168, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 16:39:01'),
(1169, 6, 'Created student account for mark rufort suycano (2026-0004) - Strand ID: 3', '::1', NULL, '2026-03-02 16:40:24'),
(1170, 6, 'Program enrollment: student 239, type regular, program 3, level 5, semester 1st, enrolled_subjects 0', '::1', NULL, '2026-03-02 16:41:01'),
(1171, 6, 'User logged out', '::1', NULL, '2026-03-02 16:52:42'),
(1172, 204, 'User logged in - School Admin', '::1', NULL, '2026-03-02 16:57:12'),
(1173, 204, 'User logged out', '::1', NULL, '2026-03-02 17:20:15'),
(1174, 6, 'Failed login attempt', '::1', NULL, '2026-03-02 17:20:32'),
(1175, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 17:20:38'),
(1176, 6, 'User logged out', '::1', NULL, '2026-03-02 17:21:23'),
(1177, 239, 'User logged in - Student', '::1', NULL, '2026-03-02 17:21:37'),
(1178, 239, 'User logged out', '::1', NULL, '2026-03-02 17:21:51'),
(1179, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-03-02 17:22:02'),
(1180, 205, 'User logged out', '::1', NULL, '2026-03-02 17:24:58'),
(1181, 239, 'User logged in - Student', '::1', NULL, '2026-03-02 17:25:05'),
(1182, 239, 'User logged out', '::1', NULL, '2026-03-02 17:26:01'),
(1183, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-03-02 17:26:11'),
(1184, 205, 'User logged out', '::1', NULL, '2026-03-02 17:55:58'),
(1185, 239, 'User logged in - Student', '::1', NULL, '2026-03-02 17:56:30'),
(1186, 239, 'User logged out', '::1', NULL, '2026-03-02 17:56:47'),
(1187, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-03-02 17:56:58'),
(1188, 205, 'User logged out', '::1', NULL, '2026-03-02 17:57:49'),
(1189, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 17:58:54'),
(1190, 6, 'User logged out', '::1', NULL, '2026-03-02 18:03:51'),
(1191, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-03-02 18:16:15'),
(1192, 205, 'User logged out', '::1', NULL, '2026-03-02 18:17:04'),
(1193, 238, 'Failed login attempt', '::1', NULL, '2026-03-02 18:17:24'),
(1194, 238, 'User logged in - Teacher', '::1', NULL, '2026-03-02 18:17:27'),
(1195, 238, 'User logged out', '::1', NULL, '2026-03-02 18:18:43'),
(1196, 239, 'User logged in - Student', '::1', NULL, '2026-03-02 18:19:02'),
(1197, 239, 'User logged out', '::1', NULL, '2026-03-02 18:19:52'),
(1198, 6, 'Failed login attempt', '::1', NULL, '2026-03-02 18:19:58'),
(1199, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 18:20:01'),
(1200, 6, 'User logged out', '::1', NULL, '2026-03-02 18:23:06'),
(1201, 100, 'Failed login attempt', '::1', NULL, '2026-03-02 18:23:18'),
(1202, 100, 'User logged in - Teacher', '::1', NULL, '2026-03-02 18:23:21'),
(1203, 100, 'Updated grade for student ID 237 in section ID 9, subject ID 34', '::1', NULL, '2026-03-02 18:25:47'),
(1204, 100, 'Created assessment: Sample', '::1', NULL, '2026-03-02 18:29:45'),
(1205, 100, 'User logged out', '::1', NULL, '2026-03-02 18:30:59'),
(1206, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-03-02 18:31:09'),
(1207, 205, 'User logged out', '::1', NULL, '2026-03-02 18:51:47'),
(1208, 238, 'User logged in - Teacher', '::1', NULL, '2026-03-02 18:52:06'),
(1209, 238, 'User logged out', '::1', NULL, '2026-03-02 18:53:28'),
(1210, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 18:53:38'),
(1211, 6, 'Generated enrollment certificate for mark rufort suycano (2026-0004)', '::1', NULL, '2026-03-02 18:57:23'),
(1212, 6, 'Generated grade_report certificate for Andrei James Subaru (2026-0002)', '::1', NULL, '2026-03-02 18:57:39'),
(1213, 6, 'Generated grade_report certificate for Andrei James Subaru (2026-0002)', '::1', NULL, '2026-03-02 18:58:12'),
(1214, 6, 'Generated completion certificate for James Andrei Revilla (2026-0003)', '::1', NULL, '2026-03-02 18:58:20'),
(1215, 6, 'User logged out', '::1', NULL, '2026-03-02 18:58:58'),
(1216, 4, 'User logged in - Super Admin', '::1', NULL, '2026-03-02 18:59:05'),
(1217, 4, 'User logged out', '::1', NULL, '2026-03-02 18:59:18'),
(1218, 6, 'User logged in - Registrar', '::1', NULL, '2026-03-02 18:59:24'),
(1219, 6, 'User logged out', '::1', NULL, '2026-03-02 19:32:08');

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
(4, 2, 'Caloocan Branch', ''),
(9, 1, 'Fairview Branch', 'fairview city');

-- --------------------------------------------------------

--
-- Table structure for table `certificates_issued`
--

CREATE TABLE `certificates_issued` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `certificate_type` enum('enrollment','grade_report','completion','transcript') NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` tinyint(3) UNSIGNED DEFAULT NULL,
  `issued_by` int(10) UNSIGNED NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `certificates_issued`
--

INSERT INTO `certificates_issued` (`id`, `student_id`, `certificate_type`, `reference_no`, `purpose`, `academic_year`, `semester`, `issued_by`, `issued_at`) VALUES
(5, 239, 'enrollment', 'EC-20260303-0239-4357', 'For Employment', '2025-2026', NULL, 6, '2026-03-02 18:57:23'),
(6, 235, 'grade_report', 'GR-20260303-0235-6556', 'For Employment', '2025-2026', NULL, 6, '2026-03-02 18:57:39'),
(7, 235, 'grade_report', 'GR-20260303-0235-9109', 'For Employment', '2025-2026', NULL, 6, '2026-03-02 18:58:12');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `curriculum_subject_id` int(10) UNSIGNED DEFAULT NULL,
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

INSERT INTO `classes` (`id`, `course_id`, `academic_year_id`, `subject_id`, `curriculum_subject_id`, `shs_track_id`, `section_name`, `branch_id`, `teacher_id`, `room`, `schedule`, `max_capacity`, `current_enrolled`) VALUES
(1, 1, NULL, NULL, NULL, NULL, 'Section 1', NULL, 100, 'Room 101', NULL, 30, 3),
(2, 2, NULL, NULL, NULL, NULL, 'Section 2', NULL, 100, 'Room 102', NULL, 25, 0),
(3, 3, NULL, NULL, NULL, NULL, 'Section 3', NULL, 100, 'Room 103', NULL, 20, 0),
(4, 1, 1, NULL, 3, NULL, 'BSIT 1 A', 1, 100, 'room 301', 'MWF', 35, 2);

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
-- Table structure for table `curriculum_subjects`
--

CREATE TABLE `curriculum_subjects` (
  `id` int(10) UNSIGNED NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `curriculum_subjects`
--

INSERT INTO `curriculum_subjects` (`id`, `subject_code`, `subject_title`, `units`, `lecture_hours`, `lab_hours`, `subject_type`, `program_id`, `year_level_id`, `shs_strand_id`, `shs_grade_level_id`, `semester`, `prerequisites`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(12, 'CORE 1', 'ART APPRECIATION', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-03-01 02:35:50', '2026-03-01 02:35:50'),
(13, 'RIZAL', 'RIZAL&#039;S LIFE WORKS &amp; WRITTING', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-03-01 02:36:21', '2026-03-01 02:36:21'),
(14, 'ITE 1', 'INTRODUCTION TO COMPUTING', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-03-01 02:36:47', '2026-03-01 02:36:47'),
(15, 'ITE 2', 'FUNDAMENTALS OF PROGRAMMING', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-03-01 02:37:14', '2026-03-01 02:37:14'),
(16, 'CORE 2', 'PURPOSIVE COMMINICATION', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-03-01 02:37:37', '2026-03-01 02:37:37'),
(17, 'CORE 3', 'MATHEMATICS IN THE MODERN WORLD', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-03-01 02:38:12', '2026-03-01 02:38:12'),
(18, 'PE 1', 'PHYSICAL EDUCATION', 2.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-03-01 02:52:43', '2026-03-01 03:04:41'),
(19, 'NSTP 1', 'NATIONAL SERVICE TRAINING PROGRAM 1', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-03-01 02:56:16', '2026-03-01 02:56:16'),
(20, 'CVED 101', 'VALUES EDUCATION', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-03-01 02:59:37', '2026-03-01 02:59:37'),
(21, 'CORE 4', 'UNDERSTANDING THE SELF', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:00:14', '2026-03-01 03:00:14'),
(22, 'CORE 5', 'SCIENCE TECHNOLOGY &amp; SOCIETY', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:02:20', '2026-03-01 03:02:20'),
(23, 'ITE 3', 'FUNDAMENTALS OF PROGRAMMING 2', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:05:25', '2026-03-01 03:05:50'),
(24, 'ITE MAJOR 1', 'INTRO TO HUMAN INTERACTION', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:06:27', '2026-03-01 03:06:27'),
(25, 'ITE 4', 'DATA STRUCTURES &amp; ALGORITHMS', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:07:19', '2026-03-01 03:07:19'),
(26, 'ITE MAJOR 2', 'DISCRETE MATHEMATICS', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:08:06', '2026-03-01 03:08:06'),
(27, 'PE 2', 'PHYSICAL EDUCATION 2', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:08:33', '2026-03-01 03:08:33'),
(28, 'NSTP 2', 'NATIONAL SERVICE TRAINING PROGRAM 2', 2.0, 0, 0, 'college', 1, 1, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:08:56', '2026-03-01 03:09:12'),
(29, 'CVED 102', 'VALUES EDUCATION 2', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:10:45', '2026-03-01 03:10:45'),
(30, 'ELECTIVE 1', 'OBJECT ORIENTED PROGRAMMING', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:11:36', '2026-03-01 03:11:36'),
(31, 'FIL 1', 'FILIPINO 1(KONTEKSWALISADONG KOMUNIKASYON SA FILIPINO)', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:13:04', '2026-03-01 03:13:04'),
(32, 'APC 1', 'PLATFORM TECHNOLOGY', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:13:42', '2026-03-01 03:13:42'),
(33, 'CORE 6', 'THE CONTEMPORARY WORLD', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:15:07', '2026-03-01 03:15:07'),
(34, 'APC 2', 'MULTIMEDIA SYSTEMS', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:15:43', '2026-03-01 03:15:43'),
(35, 'APC 3', 'WEB SYSTEM TECHNOLOGIES', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:17:21', '2026-03-01 03:17:21'),
(36, 'ITE MAJOR 3', 'FUNDAMENTALS OF DATABASE SYSTEM', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:17:50', '2026-03-01 03:17:50'),
(37, 'CVED 103', 'MORALITY &amp; SOCIAL RESPONSIBILITY', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:18:17', '2026-03-01 03:27:32'),
(38, 'PE 3', 'PHYSICAL EDUCATION 3', 2.0, 0, 0, 'college', 1, 2, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:18:35', '2026-03-01 03:19:41'),
(39, 'CORE 7', 'ETHICS', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:18:58', '2026-03-01 03:18:58'),
(40, 'FIL 2', 'FILIPINO 2', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:19:26', '2026-03-01 03:19:26'),
(41, 'CORE 8', 'READING IN THE PHILIPPINE HISTORY', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:20:20', '2026-03-01 03:20:20'),
(42, 'ITE 5', 'INFORMATION MANAGEMENT', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:20:55', '2026-03-01 03:20:55'),
(43, 'ITE MAJOR 4', 'DATA COMMUNICATION AND NETWORKING 1', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:21:42', '2026-03-01 03:21:42'),
(44, 'ITE MAJOR 5', 'QUANTITATIVE METHODS', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:24:31', '2026-03-01 03:24:31'),
(45, 'CVED 104', 'PEACE, EDUCATION, MARRIEGE, &amp; FAMILY PLANNING', 3.0, 0, 0, 'college', 1, 2, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:26:49', '2026-03-01 03:26:49'),
(46, 'PE 4', 'PHYSICAL EDUCATION 4', 2.0, 0, 0, 'college', 1, NULL, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:28:04', '2026-03-01 03:28:04'),
(47, 'PE4', 'PHYSICAL EDUCATION 4', 3.0, 0, 0, 'college', 1, NULL, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:29:42', '2026-03-01 03:29:42'),
(48, 'ELECTIVE', 'HUMAN COMPUTER INTERACTION 2', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:30:26', '2026-03-01 03:30:26'),
(49, 'ITE MAJOR 6', 'DATA COMMUNICATION AND NETWORKING 2', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:30:53', '2026-03-01 03:30:53'),
(50, 'ITE MAJOR 7', 'SYSTEM INTEGRATION AND ARCHITECHTURE 1', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:32:32', '2026-03-01 03:32:32'),
(51, 'ITE MAJOR 8', 'INTEGRATIVE PROGRAMMING TECHNOLOGIES 1', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:33:07', '2026-03-01 03:33:07'),
(52, 'APC 4', 'MOBILE TECHNOLOGY', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:33:34', '2026-03-01 03:33:34'),
(53, 'APC 5', 'SOFTWARE ENGINEERING', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 1, '', 1, 204, '2026-03-01 03:33:55', '2026-03-01 03:33:55'),
(54, 'ITE MAJOR 9', 'INFORMATION ASSURANCE &amp; SECURITY 1', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:34:39', '2026-03-01 03:34:39'),
(55, 'ITE MAJOR 10', 'SOCIAL &amp; PROFESSIONAL ISSUES', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:35:43', '2026-03-01 03:35:43'),
(56, 'ITE MAJOR 11', 'CAPSTONE PROJECT &amp; RESEARCH', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:36:36', '2026-03-01 03:36:36'),
(57, 'ITE 6', 'APPLICATION DEVELOPMENT &amp; EMERGING TECHNOLOGIES', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:37:22', '2026-03-01 03:37:22'),
(58, 'ELECTIVE 3', 'SYSTEM INTEGRATION AND ARCHITECHTURE 2', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:37:59', '2026-03-01 03:37:59'),
(59, 'APC 6', 'CLOUD COMPUTING', 3.0, 0, 0, 'college', 1, 3, NULL, NULL, 2, '', 1, 204, '2026-03-01 03:38:17', '2026-03-01 03:38:17'),
(60, 'CORE', 'ORAL COMMUNICATION', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 1, '', 1, 204, '2026-03-02 16:57:48', '2026-03-02 16:57:48'),
(61, 'CORE2', 'KOMUNIKASYON AT PANANALIKSIK SA WIKA AT KULTURANG PILIPINO', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 1, '', 1, 204, '2026-03-02 16:59:00', '2026-03-02 16:59:00'),
(62, 'CORE3', 'GENERAL MATHEMATICS', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 1, '', 1, 204, '2026-03-02 17:00:24', '2026-03-02 17:00:24'),
(63, 'CORE4', 'EARTH AND LIFE SCIENCE', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 1, '', 1, 204, '2026-03-02 17:01:12', '2026-03-02 17:01:12'),
(64, 'CORE5', 'UNDERSTANDING CULTURE, SOCIETY AND POLITICS', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 1, '', 1, 204, '2026-03-02 17:02:20', '2026-03-02 17:02:20'),
(65, 'CORE6', 'PERSONAL DEVELOPMENT', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 1, '', 1, 204, '2026-03-02 17:02:50', '2026-03-02 17:02:50'),
(66, 'CORE7', 'PHYSICAL EDUCATION AND HEALTH 1', 1.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 1, '', 1, 204, '2026-03-02 17:04:05', '2026-03-02 17:12:05'),
(67, 'APPLIED', 'ENGLISH FOR ACADEMIC AND PROFESSIONAL PURPOSES', 4.0, 0, 0, 'shs_applied', NULL, NULL, 3, 1, 1, '', 1, 204, '2026-03-02 17:05:11', '2026-03-02 17:05:11'),
(68, 'SPECIALIZED', 'DISCIPLINES AND IDEAS IN THE SOCIAL SCIENCES', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 1, '', 1, 204, '2026-03-02 17:05:59', '2026-03-02 17:05:59'),
(69, 'CORE8', 'READING AND WRITTING SKILLS', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 2, '', 1, 204, '2026-03-02 17:06:46', '2026-03-02 17:06:46'),
(70, 'CORE9', 'PAGBASA AT PAGSUSURI NG IBA&#039;T IBANG TEKSTO TUNGO SA PANANALIKSIK', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 2, '', 1, 204, '2026-03-02 17:07:36', '2026-03-02 17:07:36'),
(71, 'CORE10', '21ST CENTURY LITERATURE FROM THE PHILIPPINES AND THE WORLD', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 2, '', 1, 204, '2026-03-02 17:08:30', '2026-03-02 17:08:30'),
(72, 'CORE11', 'STATISTICS AND PROBABILITY', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 2, '', 1, 204, '2026-03-02 17:09:10', '2026-03-02 17:09:10'),
(73, 'CORE12', 'PHYSICAL SCIENCE', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 2, '', 1, 204, '2026-03-02 17:09:37', '2026-03-02 17:09:37'),
(74, 'CORE13', 'PHYSICAL EDUCATION AND HEALTH 2', 1.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 2, '', 1, 204, '2026-03-02 17:10:09', '2026-03-02 17:12:23'),
(75, 'APPLIED 1', 'RESEARCH 1', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 2, '', 1, 204, '2026-03-02 17:10:41', '2026-03-02 17:10:41'),
(76, 'SPECIALIZED 2', 'DISCIPLINE AND IDEAS IN THE APPLIED SOCIAL SCIENCES', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 2, '', 1, 204, '2026-03-02 17:11:38', '2026-03-02 17:11:38'),
(77, 'SPECIALIZED 3', 'PHILIPPINE POLITICS AND GOVERNANCE', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 1, 2, '', 1, 204, '2026-03-02 17:13:10', '2026-03-02 17:13:10'),
(78, 'CORE14', 'MEDIA AND INFORMATION LITERACY', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 2, 1, '', 1, 204, '2026-03-02 17:14:43', '2026-03-02 17:14:43'),
(79, 'CORE15', 'PAGSULAT SA FILIPINO SA PILING LARANG', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 2, 1, '', 1, 204, '2026-03-02 17:15:19', '2026-03-02 17:15:19'),
(80, 'CORE16', 'INTRODUCTION TO THE PHILISOPHY OF HUMAN PERSON', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 2, 1, '', 1, 204, '2026-03-02 17:16:07', '2026-03-02 17:16:07'),
(81, 'CORE17', 'CONTEMPORARY PHILIPPINE ARTS FROM THE REGION', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 2, 1, '', 1, 204, '2026-03-02 17:17:08', '2026-03-02 17:17:08'),
(82, 'CORE18', 'PHYSICAL EDUCATION AND HEALTH 3', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 2, 1, '', 1, 204, '2026-03-02 17:17:51', '2026-03-02 17:17:51'),
(83, 'APPLIED 2', 'EMPOWERMENT TECHNOLOGIES(E-TECH): ICT FOR PROFESSIONAL TRACKS', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 2, 1, '', 1, 204, '2026-03-02 17:18:45', '2026-03-02 17:18:45'),
(84, 'SPECIALIZED 4', 'CREATIVE WRITTING', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 2, 1, '', 1, 204, '2026-03-02 17:19:21', '2026-03-02 17:19:21'),
(85, 'SPECIALIZED 5', 'INTRODUCTION TO WORLD RELIGIONS AND BELIEF SYSTEMS', 4.0, 0, 0, 'shs_core', NULL, NULL, 3, 2, 1, '', 1, 204, '2026-03-02 17:20:10', '2026-03-02 17:20:10');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `template_type` varchar(50) DEFAULT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_by` int(10) UNSIGNED DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `recipient_email`, `subject`, `template_type`, `status`, `error_message`, `sent_by`, `sent_at`) VALUES
(1, 'revillajamesandrei4@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'failed', 'SMTP Error: Could not authenticate.', 6, '2026-01-19 16:00:51'),
(2, 'jamessenpai9@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'failed', 'SMTP Error: Could not authenticate.', 6, '2026-01-19 16:02:09'),
(3, 'revillajamesandrei4@gmail.com', 'ELMS Test Email', 'test', 'failed', 'SMTP Error: Could not authenticate.', 4, '2026-01-19 16:16:14'),
(4, 'revillajamesandrei4@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-19 16:16:51'),
(5, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'sent', NULL, 6, '2026-01-19 16:28:19'),
(6, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Password Reset Request', 'password_reset', 'sent', NULL, NULL, '2026-01-19 16:34:37'),
(7, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:10:50'),
(8, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:10:57'),
(9, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:11:03'),
(10, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:11:07'),
(11, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:11:12'),
(12, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:11:16'),
(13, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:11:24'),
(14, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:11:30'),
(15, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:11:38'),
(16, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:11:42'),
(17, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:12:11'),
(18, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:12:15'),
(19, 'rainielcrtz@gmail.com', 'ELMS Test Email', 'test', 'sent', NULL, 4, '2026-01-21 03:12:19'),
(20, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Password Reset Request', 'password_reset', 'sent', NULL, NULL, '2026-01-24 08:52:08'),
(21, 'you@example.com', 'ELMS - Datamex - SMTP Test Message', 'smtp_test', 'sent', NULL, NULL, '2026-01-30 14:25:08'),
(22, 'you@example.com', 'ELMS - Datamex - SMTP Test Message', 'smtp_test', 'sent', NULL, 204, '2026-01-31 07:23:57'),
(23, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Password Reset Request', 'password_reset', 'sent', NULL, NULL, '2026-02-03 05:32:35'),
(24, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'sent', NULL, 6, '2026-02-05 10:10:46'),
(25, 'revillajamesandrei4@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'sent', NULL, 6, '2026-02-05 10:51:30'),
(26, 'jamessenpai9@gmail.com', 'ELMS - Datamex - Password Reset Request', 'password_reset', 'sent', NULL, NULL, '2026-02-05 11:17:31'),
(27, 'yujinjae05@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'sent', NULL, 6, '2026-02-11 03:52:32'),
(28, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Password Reset Request', 'password_reset', 'failed', 'Email domain does not exist', NULL, '2026-02-16 04:36:19'),
(29, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Password Reset Request', 'password_reset', 'sent', NULL, NULL, '2026-02-17 07:07:52'),
(30, 'revillajamesandrei4@gmail.com', 'ELMS - Datamex - Password Reset Request', 'password_reset', 'sent', NULL, NULL, '2026-02-18 00:12:38'),
(31, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'sent', NULL, 6, '2026-03-01 07:35:47'),
(32, 'revillajamesandrei4@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'sent', NULL, 6, '2026-03-01 07:36:47'),
(33, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'sent', NULL, 6, '2026-03-01 09:19:50'),
(34, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'sent', NULL, 6, '2026-03-01 09:36:37'),
(35, 'suycanomarkrufort@gmail.com', 'ELMS - Datamex - Your Account Has Been Created', 'account_creation', 'sent', NULL, 6, '2026-03-02 16:40:30');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `payment_verified` tinyint(1) DEFAULT 0,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `semester` varchar(10) DEFAULT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `prelim` decimal(5,2) DEFAULT NULL,
  `midterm` decimal(5,2) DEFAULT NULL,
  `prefinal` decimal(5,2) DEFAULT NULL,
  `final` decimal(5,2) DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `remarks` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `version` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

--
-- Dumping data for table `grade_locks`
--

INSERT INTO `grade_locks` (`id`, `class_id`, `grading_period`, `is_locked`, `locked_by`, `locked_at`, `unlock_request`, `unlock_reason`) VALUES
(1, 4, 'prelim', 0, NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `grading_terms`
--

CREATE TABLE `grading_terms` (
  `id` int(11) NOT NULL,
  `term_name` varchar(50) NOT NULL,
  `term_code` varchar(20) NOT NULL,
  `term_order` tinyint(3) NOT NULL,
  `weight_percentage` decimal(5,2) DEFAULT 25.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grading_terms`
--

INSERT INTO `grading_terms` (`id`, `term_name`, `term_code`, `term_order`, `weight_percentage`, `is_active`, `created_at`) VALUES
(1, 'Prelim', 'prelim', 1, 25.00, 1, '2026-01-19 14:19:54'),
(2, 'Midterm', 'midterm', 2, 25.00, 1, '2026-01-19 14:19:54'),
(3, 'Pre-Finals', 'prefinal', 3, 25.00, 1, '2026-01-19 14:19:54'),
(4, 'Finals', 'final', 4, 25.00, 1, '2026-01-19 14:19:54');

-- --------------------------------------------------------

--
-- Table structure for table `learning_materials`
--

CREATE TABLE `learning_materials` (
  `id` int(10) UNSIGNED NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `learning_materials`
--

INSERT INTO `learning_materials` (`id`, `section_id`, `subject_id`, `class_id`, `file_path`, `uploaded_at`, `uploaded_by`) VALUES
(1, NULL, NULL, 1, 'materials/material_1_1768583054_696a6f8e7b5e0.pptx', '2026-01-16 17:04:14', NULL),
(2, NULL, NULL, 1, 'materials/material_1_1768583063_696a6f9773a42.pptx', '2026-01-16 17:04:23', NULL),
(3, NULL, NULL, 3, 'materials/material_3_1768584379_696a74bb48747.pptx', '2026-01-16 17:26:19', NULL),
(10, NULL, 4, NULL, 'materials/material_subj4_1768761931_696d2a4b39360.pptx', '2026-01-18 18:45:31', 100),
(11, NULL, 4, NULL, 'materials/material_subj4_1768965999_6970476fc42e5.pdf', '2026-01-21 03:26:39', 100),
(12, NULL, 15, NULL, 'materials/material_subj15_1772352368_69a3f3704db52.pdf', '2026-03-01 08:06:08', 100);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `user_agent`, `success`, `attempted_at`) VALUES
(1, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 15:47:22'),
(2, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 15:53:08'),
(3, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 15:54:16'),
(4, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 15:54:42'),
(6, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 16:04:23'),
(7, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 16:05:56'),
(8, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 16:14:38'),
(9, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 16:27:27'),
(10, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 16:46:03'),
(11, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 16:50:18'),
(12, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-20 14:21:13'),
(13, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-20 14:24:24'),
(14, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-20 14:25:14'),
(15, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-20 14:27:08'),
(16, 'school@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 0, '2026-01-20 14:35:06'),
(17, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-20 14:35:20'),
(18, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-21 03:08:50'),
(19, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-21 03:12:57'),
(20, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-21 03:13:14'),
(21, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-21 03:16:06'),
(22, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-21 03:20:23'),
(23, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-21 03:23:48'),
(24, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-21 03:23:50'),
(26, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-21 03:28:00'),
(29, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-22 16:01:15'),
(30, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-22 16:02:27'),
(31, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-24 06:41:38'),
(32, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-24 07:57:13'),
(33, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-24 09:29:15'),
(34, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-24 09:40:38'),
(35, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-30 14:27:51'),
(36, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-30 14:39:30'),
(37, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-30 14:44:01'),
(38, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-30 14:55:38'),
(39, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-30 14:55:56'),
(40, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-30 14:56:18'),
(41, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-30 15:46:22'),
(42, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-30 15:49:00'),
(43, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 07:23:34'),
(44, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 08:04:58'),
(45, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 08:22:37'),
(46, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 08:38:30'),
(47, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 08:39:36'),
(48, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 08:39:43'),
(52, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 08:42:23'),
(56, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 08:48:07'),
(58, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 08:48:58'),
(61, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 08:54:16'),
(63, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 09:01:54'),
(68, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 09:39:20'),
(69, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 09:50:06'),
(73, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 10:19:25'),
(74, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 10:26:48'),
(75, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 10:27:45'),
(80, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 10:32:01'),
(81, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 10:33:59'),
(82, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 10:39:59'),
(104, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 10:45:48'),
(128, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 10:52:17'),
(141, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 10:58:49'),
(146, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 10:59:32'),
(152, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 11:01:23'),
(157, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 11:03:01'),
(160, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 11:05:07'),
(161, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 11:05:25'),
(162, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 11:05:31'),
(169, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 11:08:47'),
(171, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 11:10:35'),
(172, 'admin@elms.com', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-01-31 11:11:56'),
(173, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 13:17:35'),
(174, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-31 13:18:39'),
(175, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-01 23:37:52'),
(176, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 1, '2026-02-01 23:39:53'),
(177, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-01 23:42:47'),
(178, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-01 23:43:15'),
(179, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-02 00:40:52'),
(180, 'admin@elms.com', '192.168.254.104', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1, '2026-02-03 03:14:00'),
(181, 'schooladmin@elms.com', '192.168.254.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 03:14:44'),
(182, 'admin@elms.com', '192.168.254.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 03:19:04'),
(183, 'admin@elms.com', '192.168.254.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 03:24:17'),
(184, 'branchadmin@elms.com', '192.168.254.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 03:25:15'),
(185, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 03:42:00'),
(186, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 03:43:38'),
(187, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 03:45:17'),
(188, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 03:45:24'),
(190, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 03:49:33'),
(193, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 04:56:25'),
(195, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 1, '2026-02-03 05:08:30'),
(196, 'registrar@elms.com', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 1, '2026-02-03 05:26:02'),
(197, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 05:28:48'),
(198, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 05:31:26'),
(201, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 05:40:01'),
(202, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 05:47:17'),
(203, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 05:48:16'),
(204, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 05:50:15'),
(205, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 05:53:41'),
(206, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 05:53:43'),
(207, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 06:03:39'),
(208, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 06:14:40'),
(209, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 06:16:00'),
(210, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-03 06:19:03'),
(211, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-04 13:10:20'),
(212, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-04 13:11:04'),
(213, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 08:26:36'),
(214, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 08:27:58'),
(215, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 08:28:06'),
(216, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 08:30:22'),
(217, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 08:35:45'),
(218, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 08:36:43'),
(219, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 1, '2026-02-05 08:43:35'),
(220, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 08:44:58'),
(221, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 09:07:17'),
(222, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 09:13:26'),
(223, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:06:52'),
(224, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:07:14'),
(226, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:24:07'),
(227, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:26:41'),
(228, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:27:53'),
(229, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:29:14'),
(230, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:29:32'),
(231, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:29:54'),
(232, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:30:36'),
(233, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:33:46'),
(234, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:46:59'),
(235, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 10:51:51'),
(236, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:16:59'),
(237, 'jamessenpai9@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:17:21'),
(238, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:20:57'),
(239, 'jamessenpai9@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:21:27'),
(240, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:22:07'),
(241, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:23:55'),
(242, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:24:42'),
(243, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:26:25'),
(244, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:29:49'),
(245, 'senpai@teacher.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:30:06'),
(246, 'senpai@teacher.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:30:15'),
(247, 'senpai@teacher.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:30:21'),
(248, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:30:26'),
(249, 'senpai@teacher.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:31:03'),
(250, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:31:52'),
(251, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:32:07'),
(252, 'sample@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:32:32'),
(253, 'sample@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 11:32:35'),
(254, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 12:22:58'),
(255, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 13:21:59'),
(256, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-05 14:07:27'),
(257, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-06 03:25:00'),
(258, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 15:46:42'),
(259, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 15:47:01'),
(260, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 15:47:41'),
(261, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 15:48:18'),
(262, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 15:48:23'),
(263, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 15:49:17'),
(264, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 15:49:20'),
(265, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 15:53:34'),
(267, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 15:57:49'),
(268, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 15:58:07'),
(270, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 16:05:05'),
(271, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 16:13:21'),
(272, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 16:13:40'),
(273, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 16:14:55'),
(274, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 16:18:10'),
(275, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 16:18:34'),
(276, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 16:19:14'),
(278, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 16:23:22'),
(281, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-10 16:30:02'),
(282, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-11 03:43:14'),
(283, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-11 03:43:54'),
(284, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-11 03:44:05'),
(285, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-11 03:45:37'),
(286, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-11 03:48:50'),
(287, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-02-11 03:53:41'),
(289, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-16 04:33:22'),
(290, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-16 04:33:26'),
(291, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-16 04:34:01'),
(292, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-17 06:24:04'),
(294, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-17 06:38:25'),
(296, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-17 06:41:37'),
(298, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-17 06:44:15'),
(300, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-17 07:22:47'),
(301, 'student@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 0, '2026-02-17 07:25:23'),
(302, 'student@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 0, '2026-02-17 07:25:30'),
(305, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-17 07:26:42'),
(306, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-17 07:26:47'),
(307, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-17 07:37:39'),
(308, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-24 13:30:41'),
(309, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-24 13:31:41'),
(310, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-24 13:31:49'),
(311, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-24 13:43:07'),
(312, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-24 13:43:54'),
(313, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-24 13:46:50'),
(314, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-24 14:35:28'),
(315, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-24 17:06:35'),
(316, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', 1, '2026-02-24 17:07:40'),
(317, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-24 17:07:50'),
(319, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-25 19:40:41'),
(320, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-27 07:35:23'),
(321, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-27 08:28:10'),
(322, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-27 10:11:39'),
(323, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-27 14:13:57'),
(324, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-27 14:15:01'),
(325, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-27 14:16:20'),
(326, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-02-28 17:15:31'),
(327, 'student@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 0, '2026-02-28 17:46:25'),
(329, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 02:32:38'),
(330, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 02:34:40'),
(331, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 02:39:22'),
(332, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 02:41:58'),
(333, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 02:43:15'),
(334, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 02:44:39'),
(335, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 03:38:28'),
(336, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 03:39:22'),
(337, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 03:40:46'),
(338, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 04:06:15'),
(339, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 04:21:15'),
(340, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 05:27:57'),
(341, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 05:46:52'),
(342, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 05:50:22'),
(349, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 06:02:53'),
(351, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 07:19:52'),
(352, 'schooladmin@elms.com', '192.168.254.107', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 1, '2026-03-01 07:21:29'),
(353, 'branchadmin@elms.com', '192.168.254.104', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 1, '2026-03-01 07:21:48'),
(354, 'branchadmin@elms.com', '192.168.254.104', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 1, '2026-03-01 07:21:52'),
(355, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 07:24:34'),
(356, 'teacher@elms.com', '192.168.254.107', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 1, '2026-03-01 07:25:17'),
(358, 'revillajamesandrei4@gmail.com', '192.168.254.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', 1, '2026-03-01 07:47:48'),
(359, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 07:56:44'),
(360, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 08:03:21'),
(361, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 08:05:01'),
(362, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 08:05:07'),
(365, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 08:08:40'),
(366, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 08:09:05'),
(367, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 08:28:11'),
(368, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 08:28:16'),
(369, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:18:59'),
(370, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:19:18'),
(371, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:28:54'),
(372, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:29:14'),
(373, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:29:21'),
(374, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:29:24'),
(375, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:40:26'),
(376, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:40:43'),
(377, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:40:45'),
(378, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:41:45'),
(379, 'student@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 0, '2026-03-01 09:42:16'),
(381, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-01 09:51:44'),
(384, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 02:19:17'),
(385, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 02:20:15'),
(386, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 12:06:08'),
(387, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 12:06:37'),
(388, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 12:07:37'),
(389, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 12:10:59'),
(390, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:10:20'),
(391, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:12:08'),
(392, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:13:09'),
(393, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:13:57'),
(394, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:15:20'),
(396, 'revillajamesandrei4@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:18:54'),
(397, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:20:18'),
(398, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:27:56'),
(399, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:28:05'),
(400, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:29:58'),
(401, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:34:58'),
(402, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:40:38'),
(403, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:45:14'),
(404, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:47:31'),
(405, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:47:34'),
(406, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:51:24'),
(407, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 13:55:40'),
(408, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 14:01:26'),
(409, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 14:02:36'),
(410, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 14:05:09'),
(411, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 14:05:45'),
(412, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 14:14:24'),
(414, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 14:27:34'),
(415, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 14:27:40'),
(416, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 15:25:04'),
(417, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 15:43:08'),
(420, 'student@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 0, '2026-03-02 15:51:47'),
(421, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 15:51:55'),
(422, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 15:51:58'),
(423, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 16:06:42'),
(424, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 16:39:01');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `user_agent`, `success`, `attempted_at`) VALUES
(425, 'schooladmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 16:57:12'),
(426, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 17:20:32'),
(427, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 17:20:38'),
(428, 'suycanomarkrufort@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 17:21:37'),
(429, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 17:22:02'),
(430, 'suycanomarkrufort@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 17:25:05'),
(431, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 17:26:11'),
(432, 'suycanomarkrufort@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 17:56:30'),
(433, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 17:56:58'),
(434, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 17:58:54'),
(435, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:16:15'),
(436, 'Yujinjae05@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:17:24'),
(437, 'Yujinjae05@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:17:27'),
(438, 'suycanomarkrufort@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:19:02'),
(439, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:19:58'),
(440, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:20:01'),
(441, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:23:18'),
(442, 'teacher@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:23:21'),
(443, 'branchadmin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:31:09'),
(444, 'Yujinjae05@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:52:06'),
(445, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:53:38'),
(446, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:59:05'),
(447, 'registrar@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 1, '2026-03-02 18:59:24');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'Recipient user ID',
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info' COMMENT 'info, success, warning, danger, announcement, enrollment, payment, grade, material',
  `link` varchar(500) DEFAULT NULL COMMENT 'Optional URL to navigate to',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `link`, `is_read`, `created_by`, `created_at`, `read_at`) VALUES
(1, 234, 'Enrollment Confirmed', 'You have been enrolled in 9 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 07:37:17', NULL),
(2, 234, 'New Learning Material', 'A new learning material has been uploaded for your subject.', 'material', NULL, 0, 100, '2026-03-01 08:06:08', NULL),
(3, 234, 'Payment Recorded', 'A payment of ₱5,000.00 has been recorded. Ref: PAY-20260301-2156', 'payment', NULL, 0, 6, '2026-03-01 08:36:56', NULL),
(4, 234, 'Enrollment Confirmed', 'You have been enrolled in 7 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 08:38:56', NULL),
(5, 234, 'Enrollment Confirmed', 'You have been enrolled in 0 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 08:47:59', NULL),
(6, 234, 'Payment Recorded', 'A payment of ₱8,500.00 has been recorded. Ref: PAY-20260301-1353', 'payment', NULL, 0, 6, '2026-03-01 08:49:02', NULL),
(7, 234, 'Enrollment Confirmed', 'You have been enrolled in 0 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 09:05:28', NULL),
(8, 234, 'Enrollment Confirmed', 'You have been enrolled in 0 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 09:05:56', NULL),
(9, 234, 'Enrollment Confirmed', 'You have been enrolled in 0 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 09:06:27', NULL),
(10, 234, 'Enrollment Confirmed', 'You have been enrolled in 0 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 09:08:31', NULL),
(11, 234, 'Down Payment Recorded', 'Your down payment of ₱2,125.00 has been recorded successfully.', 'payment', NULL, 0, 6, '2026-03-01 09:08:33', NULL),
(12, 234, 'Enrollment Confirmed', 'You have been enrolled in 0 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 09:09:56', NULL),
(13, 234, 'Down Payment Recorded', 'Your down payment of ₱3,000.00 has been recorded successfully.', 'payment', NULL, 0, 6, '2026-03-01 09:10:13', NULL),
(14, 234, 'Year Level Advanced', 'You have been advanced to 3rd Year. Down payment of ₱2,800.00 recorded.', 'enrollment', NULL, 0, 6, '2026-03-01 09:10:37', NULL),
(15, 234, 'Enrollment Confirmed', 'You have been enrolled in 6 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 09:17:54', NULL),
(16, 234, 'Down Payment Recorded', 'Your down payment of ₱3,499.96 has been recorded successfully.', 'payment', NULL, 0, 6, '2026-03-01 09:18:00', NULL),
(17, 236, 'Enrollment Confirmed', 'You have been enrolled in 9 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 09:20:03', NULL),
(18, 236, 'Down Payment Recorded', 'Your down payment of ₱3,499.98 has been recorded successfully.', 'payment', NULL, 0, 6, '2026-03-01 09:20:13', NULL),
(19, 236, 'Payment Recorded', 'A payment of ₱5,000.02 has been recorded. Ref: PAY-20260301-0965', 'payment', NULL, 0, 6, '2026-03-01 09:20:53', NULL),
(20, 236, 'Year Level Advanced', 'You have been advanced to 2nd Year. Down payment of ₱3,500.00 recorded.', 'enrollment', NULL, 0, 6, '2026-03-01 09:21:05', NULL),
(21, 237, 'Enrollment Confirmed', 'You have been enrolled in 9 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-01 09:37:19', NULL),
(22, 237, 'Down Payment Recorded', 'Your down payment of ₱2,500.00 has been recorded successfully.', 'payment', NULL, 0, 6, '2026-03-01 09:38:13', NULL),
(23, 237, 'Payment Recorded', 'A payment of ₱5,000.00 has been recorded. Ref: PAY-20260301-1529', 'payment', NULL, 0, 6, '2026-03-01 09:38:47', NULL),
(24, 237, 'Year Level Advanced', 'You have been advanced to 2nd Year. Down payment of ₱2,500.00 recorded.', 'enrollment', NULL, 0, 6, '2026-03-01 09:39:05', NULL),
(25, 237, 'Payment Recorded', 'A payment of ₱5,000.00 has been recorded. Ref: PAY-20260302-3985', 'payment', NULL, 0, 6, '2026-03-02 13:21:57', NULL),
(26, 237, 'Year Level Advanced', 'You have been advanced to 3rd Year. Down payment of ₱3,500.00 recorded.', 'enrollment', NULL, 1, 6, '2026-03-02 13:22:41', '2026-03-02 15:49:17'),
(27, 237, 'Payment Recorded', 'A payment of ₱1,000.00 has been recorded. Ref: PAY-20260303-0813', 'payment', NULL, 0, 6, '2026-03-02 16:07:09', NULL),
(28, 239, 'Enrollment Confirmed', 'You have been enrolled in 0 subject(s) for this term.', 'enrollment', NULL, 0, 6, '2026-03-02 16:41:01', NULL),
(29, 237, 'Grade Updated', 'A new grade has been posted for one of your subjects.', 'grade', NULL, 0, 100, '2026-03-02 18:25:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `oauth_tokens`
--

CREATE TABLE `oauth_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_user_id` varchar(255) DEFAULT NULL,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
(4, 224, 'c4fc97b88e4f9f77876502004f1362626e15ce998b7ca7b88ea2fab3f423512d', '2026-02-05 20:17:27', 0, '2026-02-05 11:17:27');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `or_number` varchar(50) DEFAULT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` varchar(100) DEFAULT NULL,
  `other_type_description` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `semester` enum('1st','2nd','summer') DEFAULT NULL,
  `term` enum('prelim','midterm','prefinals','finals','full','downpayment') DEFAULT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','online','check') DEFAULT 'cash',
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `reference_no`, `or_number`, `student_id`, `amount`, `payment_type`, `other_type_description`, `description`, `academic_year_id`, `semester`, `term`, `branch_id`, `recorded_by`, `payment_method`, `status`, `verified_by`, `verified_at`, `rejection_reason`, `proof_file`, `created_at`) VALUES
(10, 'PAY-20260301-1001', NULL, 235, 3500.00, 'Tuition', NULL, 'Down payment upon enrollment', 1, '1st', NULL, 1, 6, 'cash', 'verified', NULL, NULL, NULL, NULL, '2026-03-01 07:36:42');

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
(5, 'BSHM', 'Bachelor of Science in Hospitality Management', 'Bachelor', 2, 1, '2026-01-18 13:51:57'),
(10, 'ACT', 'Associate Computer Technology', 'Associate', 2, 1, '2026-02-27 10:21:13');

-- --------------------------------------------------------

--
-- Table structure for table `program_courses`
--

CREATE TABLE `program_courses` (
  `id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `year_level_id` int(10) UNSIGNED NOT NULL,
  `semester` tinyint(3) UNSIGNED NOT NULL,
  `course_code` varchar(30) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_tuition_fees`
--

CREATE TABLE `program_tuition_fees` (
  `id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `program_type` enum('college','shs') NOT NULL DEFAULT 'college',
  `year_level_id` int(10) UNSIGNED DEFAULT NULL,
  `semester` enum('1st','2nd','summer') DEFAULT '1st',
  `tuition_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `misc_fee` decimal(10,2) DEFAULT 0.00,
  `lab_fee` decimal(10,2) DEFAULT 0.00,
  `other_fees` decimal(10,2) DEFAULT 0.00,
  `total_fee` decimal(10,2) GENERATED ALWAYS AS (`tuition_fee` + `misc_fee` + `lab_fee` + `other_fees`) STORED,
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `program_tuition_fees`
--

INSERT INTO `program_tuition_fees` (`id`, `program_id`, `program_type`, `year_level_id`, `semester`, `tuition_fee`, `misc_fee`, `lab_fee`, `other_fees`, `academic_year_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'college', 1, '1st', 8500.00, 0.00, 0.00, 0.00, 1, 1, '2026-02-05 09:52:56', '2026-03-01 07:33:01'),
(2, 5, 'college', 13, '1st', 9000.00, 0.00, 0.00, 0.00, 1, 1, '2026-02-05 10:28:18', '2026-02-05 10:28:18'),
(3, 6, 'college', 17, '1st', 9500.00, 0.00, 0.00, 0.00, 1, 0, '2026-02-05 10:30:49', '2026-03-01 07:25:39'),
(4, 2, 'college', 5, '1st', 8500.00, 0.00, 0.00, 0.00, 1, 0, '2026-02-11 03:50:37', '2026-03-01 07:25:45'),
(5, 1, 'college', 2, '1st', 8500.00, 0.00, 0.00, 0.00, 1, 1, '2026-02-27 07:35:57', '2026-02-27 07:35:57'),
(6, 1, 'college', 1, '2nd', 8500.00, 0.00, 0.00, 0.00, 1, 1, '2026-03-01 07:32:35', '2026-03-01 07:32:35'),
(7, 1, 'college', 2, '2nd', 8500.00, 0.00, 0.00, 0.00, 1, 1, '2026-03-01 07:33:22', '2026-03-01 07:33:22'),
(8, 1, 'college', 3, '1st', 11200.00, 0.00, 0.00, 0.00, 1, 1, '2026-03-01 07:33:51', '2026-03-01 07:33:51'),
(9, 1, 'college', 3, '2nd', 11200.00, 0.00, 0.00, 0.00, 1, 1, '2026-03-01 07:34:14', '2026-03-01 07:34:14');

-- --------------------------------------------------------

--
-- Table structure for table `program_year_levels`
--

CREATE TABLE `program_year_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `year_level` tinyint(3) UNSIGNED NOT NULL,
  `year_name` varchar(20) NOT NULL,
  `semesters_count` tinyint(3) UNSIGNED DEFAULT 2,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `program_year_levels`
--

INSERT INTO `program_year_levels` (`id`, `program_id`, `year_level`, `year_name`, `semesters_count`, `is_active`, `created_at`) VALUES
(1, 1, 1, '1st Year', 2, 1, '2026-01-17 15:39:11'),
(2, 1, 2, '2nd Year', 2, 1, '2026-01-17 15:39:11'),
(3, 1, 3, '3rd Year', 2, 1, '2026-01-17 15:39:11'),
(4, 1, 4, '4th Year', 2, 1, '2026-01-17 15:39:11'),
(13, 5, 1, '1st Year', 2, 1, '2026-01-18 13:54:04'),
(18, 5, 2, '2nd year', 2, 1, '2026-02-27 10:18:59'),
(19, 5, 3, '3rd year', 2, 1, '2026-02-27 10:19:29'),
(20, 5, 4, '4th', 2, 1, '2026-02-27 10:19:43'),
(21, 10, 1, '1st Year', 2, 1, '2026-02-27 10:21:28'),
(22, 10, 2, '2nd year', 2, 1, '2026-02-27 10:21:41');

-- --------------------------------------------------------

--
-- Table structure for table `resource_locks`
--

CREATE TABLE `resource_locks` (
  `id` int(11) NOT NULL,
  `lock_key` varchar(100) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `year_level_id` int(11) DEFAULT NULL,
  `shs_strand_id` int(11) DEFAULT NULL,
  `shs_grade_level_id` int(11) DEFAULT NULL,
  `semester` enum('1st','2nd','summer') DEFAULT '1st',
  `academic_year_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `max_capacity` int(11) DEFAULT 40,
  `room` varchar(50) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `program_id`, `year_level_id`, `shs_strand_id`, `shs_grade_level_id`, `semester`, `academic_year_id`, `branch_id`, `max_capacity`, `room`, `adviser_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'BSIT 1 A', 1, 1, NULL, NULL, '1st', 1, 1, 40, '', NULL, 1, '2026-01-18 17:43:45', '2026-01-18 17:43:45'),
(2, 'BSIT 1 B', 1, 1, NULL, NULL, '1st', 1, 1, 40, '', NULL, 1, '2026-01-18 17:57:09', '2026-01-18 17:57:09'),
(3, 'BSIT 1 A', 1, 1, NULL, NULL, '1st', 1, 2, 40, '', NULL, 1, '2026-01-18 20:39:19', '2026-01-18 20:39:19'),
(4, 'BSIT 1 B', 1, 1, NULL, NULL, '1st', 1, 2, 40, '', NULL, 1, '2026-01-18 20:39:24', '2026-01-18 20:39:24'),
(5, 'BSIT 1 C', 1, 1, NULL, NULL, '1st', 1, 1, 40, '', NULL, 1, '2026-01-19 02:40:11', '2026-01-19 02:40:11'),
(6, 'BSCS 1A', 2, 5, NULL, NULL, '1st', 1, 1, 40, '', 216, 1, '2026-01-31 13:19:24', '2026-01-31 13:19:24'),
(7, 'Ilang ilang', NULL, NULL, 2, 3, '1st', 1, 1, 40, '', NULL, 1, '2026-02-03 03:28:12', '2026-02-03 03:28:12'),
(8, 'BSIT 3 A', 1, 3, NULL, NULL, '1st', 1, 1, 40, '', 100, 1, '2026-03-01 06:03:41', '2026-03-01 06:03:41'),
(9, 'BSIT 2A', 1, 2, NULL, NULL, '1st', 1, 1, 40, '', NULL, 1, '2026-03-01 07:42:08', '2026-03-01 07:42:08'),
(10, 'Ilang ilang', NULL, NULL, 3, 5, '1st', 1, 1, 40, '', 238, 1, '2026-03-02 17:22:36', '2026-03-02 17:22:36');

-- --------------------------------------------------------

--
-- Table structure for table `section_students`
--

CREATE TABLE `section_students` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','dropped','transferred') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section_students`
--

INSERT INTO `section_students` (`id`, `section_id`, `student_id`, `enrolled_at`, `status`) VALUES
(33, 1, 226, '2026-02-10 16:18:46', ''),
(34, 2, 234, '2026-03-01 07:44:46', 'active'),
(35, 9, 235, '2026-03-01 07:45:56', 'active'),
(36, 9, 237, '2026-03-02 17:26:45', 'active'),
(37, 10, 239, '2026-03-02 17:55:33', 'active');

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

--
-- Dumping data for table `security_logs`
--

INSERT INTO `security_logs` (`id`, `user_id`, `event_type`, `ip_address`, `user_agent`, `details`, `severity`, `created_at`) VALUES
(1, NULL, 'login_failed', '::1', NULL, 'User login failed', 'medium', '2026-01-31 08:47:47'),
(2, NULL, 'login_failed', '::1', NULL, 'User login failed', 'medium', '2026-01-31 08:47:54'),
(3, NULL, 'login_failed', '::1', NULL, 'User login failed', 'medium', '2026-01-31 08:47:55'),
(4, 4, 'login_success', '::1', NULL, 'User login successful', '', '2026-01-31 08:48:07'),
(5, NULL, 'account_locked', '::1', NULL, 'Account locked due to failed login attempts', 'high', '2026-01-31 08:48:44'),
(6, NULL, 'login_failed', '::1', NULL, 'User login failed', 'medium', '2026-01-31 08:48:47'),
(7, 4, 'login_success', '::1', NULL, 'User login successful', '', '2026-01-31 08:48:58'),
(8, NULL, 'login_success', '::1', NULL, 'User login successful', '', '2026-01-31 08:53:24'),
(9, NULL, 'login_failed', '::1', NULL, 'User login failed', 'medium', '2026-01-31 08:53:37'),
(10, 4, 'login_success', '::1', NULL, 'User login successful', '', '2026-01-31 08:54:16'),
(11, NULL, 'login_failed', '::1', NULL, 'User login failed', 'medium', '2026-01-31 09:01:48'),
(12, 4, 'login_success', '::1', NULL, 'User login successful', '', '2026-01-31 09:01:54'),
(13, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 09:36:58'),
(14, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 09:37:00'),
(15, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 09:37:01'),
(16, NULL, 'account_locked', '::1', NULL, 'Account locked due to failed login attempts', 'high', '2026-01-31 09:37:03'),
(17, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 09:39:09'),
(18, 4, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 09:39:20'),
(19, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 09:50:06'),
(20, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:10:09'),
(21, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:10:10'),
(22, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:10:11'),
(23, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:10:12'),
(24, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:36'),
(25, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:45'),
(26, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:46'),
(27, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:51'),
(28, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:52'),
(29, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:53'),
(30, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:53'),
(31, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:53'),
(32, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:54'),
(33, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:54'),
(34, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:54'),
(35, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:54'),
(36, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:54'),
(37, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:55'),
(38, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:55'),
(39, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:55'),
(40, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:55'),
(41, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:56'),
(42, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:56'),
(43, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:56'),
(44, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:56'),
(45, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:56'),
(46, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:57'),
(47, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:13:57'),
(48, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:14:28'),
(49, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:15:08'),
(50, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:18:34'),
(51, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:19:15'),
(52, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:19:25'),
(53, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:30'),
(54, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:31'),
(55, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:32'),
(56, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:32'),
(57, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:33'),
(58, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:33'),
(59, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:33'),
(60, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:35'),
(61, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:36'),
(62, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:37'),
(63, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:38'),
(64, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:38'),
(65, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:38'),
(66, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:38'),
(67, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:24:39'),
(68, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:26:48'),
(69, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:27:45'),
(70, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:27:55'),
(71, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:28:05'),
(72, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:28:06'),
(73, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:28:07'),
(74, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:28:08'),
(75, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:29:09'),
(76, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:32:01'),
(77, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:32:20'),
(78, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:32:26'),
(79, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:32:27'),
(80, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:32:28'),
(81, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:32:29'),
(82, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:32:29'),
(83, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:32:29'),
(84, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:32:29'),
(85, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:32:29'),
(86, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:33:18'),
(87, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:33:19'),
(88, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:33:20'),
(89, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:33:20'),
(90, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:33:46'),
(91, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:33:48'),
(92, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:33:48'),
(93, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:33:59'),
(94, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:38:22'),
(95, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:39:47'),
(96, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:39:59'),
(97, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:24'),
(98, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:26'),
(99, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:27'),
(100, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:27'),
(101, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:28'),
(102, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:28'),
(103, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:28'),
(104, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:29'),
(105, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:29'),
(106, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:29'),
(107, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:29'),
(108, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:30'),
(109, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:30'),
(110, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:30'),
(111, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:30'),
(112, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:30'),
(113, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:30'),
(114, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:31'),
(115, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:31'),
(116, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:31'),
(117, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:31'),
(118, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:31'),
(119, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:32'),
(120, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:32'),
(121, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:32'),
(122, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:32'),
(123, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:32'),
(124, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:32'),
(125, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:33'),
(126, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:33'),
(127, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:33'),
(128, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:33'),
(129, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:33'),
(130, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:33'),
(131, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:41:33'),
(132, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:45:14'),
(133, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:24'),
(134, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:25'),
(135, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:26'),
(136, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:28'),
(137, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:29'),
(138, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:33'),
(139, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:33'),
(140, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:34'),
(141, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:34'),
(142, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:34'),
(143, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:34'),
(144, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:35'),
(145, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:35'),
(146, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:35'),
(147, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:35'),
(148, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:35'),
(149, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:36'),
(150, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:36'),
(151, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:36'),
(152, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:45:37'),
(153, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:45:48'),
(154, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:46:16'),
(155, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 10:46:56'),
(156, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:03'),
(157, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:05'),
(158, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:06'),
(159, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:07'),
(160, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:08'),
(161, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:08'),
(162, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:08'),
(163, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:09'),
(164, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:09'),
(165, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:09'),
(166, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:09'),
(167, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:09'),
(168, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:13'),
(169, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:47:16'),
(170, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:48:50'),
(171, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:48:50'),
(172, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:49:02'),
(173, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:49:04'),
(174, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:49:05'),
(175, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:49:05'),
(176, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:49:06'),
(177, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:49:10'),
(178, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:49:10'),
(179, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:49:10'),
(180, 4, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 10:52:17'),
(181, NULL, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 10:53:13'),
(182, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:53:20'),
(183, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:53:22'),
(184, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:53:23'),
(185, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:53:23'),
(186, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:53:36'),
(187, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:53:37'),
(188, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:53:38'),
(189, NULL, 'account_locked', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'Account locked due to failed login attempts', 'high', '2026-01-31 10:53:38'),
(190, NULL, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 10:57:22'),
(191, NULL, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 10:57:33'),
(192, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:57:42'),
(193, NULL, '', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '{\"attempts\":1,\"max_attempts\":3,\"lock_count\":0}', 'low', '2026-01-31 10:57:42'),
(194, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:58:36'),
(195, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:58:38'),
(196, NULL, 'account_locked', '::1', NULL, 'Account locked due to failed login attempts', 'high', '2026-01-31 10:58:38'),
(197, 4, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 10:58:49'),
(198, NULL, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 10:59:07'),
(199, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:59:15'),
(200, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:59:17'),
(201, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 10:59:19'),
(202, NULL, 'account_locked', '::1', NULL, 'Account locked due to failed login attempts', 'high', '2026-01-31 10:59:19'),
(203, 4, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 10:59:32'),
(204, NULL, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 11:00:22'),
(205, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:00:32'),
(206, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:00:34'),
(207, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:00:36'),
(208, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:00:37'),
(209, NULL, 'account_locked', '::1', NULL, 'Account locked due to failed login attempts', 'high', '2026-01-31 11:00:37'),
(210, 4, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 11:01:23'),
(211, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:02:43'),
(212, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:02:44'),
(213, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:02:45'),
(214, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:02:46'),
(215, NULL, 'account_locked', '::1', NULL, 'Account locked due to failed login attempts', 'high', '2026-01-31 11:02:46'),
(216, 4, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 11:03:01'),
(217, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:03:24'),
(218, NULL, 'account_locked', '::1', NULL, 'Account locked due to failed login attempts', 'high', '2026-01-31 11:03:24'),
(219, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:04:33'),
(220, NULL, 'account_locked', '::1', NULL, 'Account locked due to failed login attempts', 'high', '2026-01-31 11:04:33'),
(221, 4, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 11:05:07'),
(222, 4, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:05:25'),
(223, 4, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 11:05:31'),
(224, NULL, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 11:05:41'),
(225, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:05:51'),
(226, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-01-31 11:10:05'),
(227, 4, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 11:10:35'),
(228, 4, 'login_success', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-01-31 11:11:56'),
(229, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 13:17:35'),
(230, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-01-31 13:18:39'),
(231, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-01 23:37:52'),
(232, 204, 'login_success', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'User login successful', '', '2026-02-01 23:39:53'),
(233, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-01 23:42:47'),
(234, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-01 23:43:15'),
(235, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-02 00:40:52'),
(236, 4, 'login_success', '192.168.254.104', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-02-03 03:14:00');
INSERT INTO `security_logs` (`id`, `user_id`, `event_type`, `ip_address`, `user_agent`, `details`, `severity`, `created_at`) VALUES
(237, 204, 'login_success', '192.168.254.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 03:14:44'),
(238, 4, 'login_success', '192.168.254.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 03:19:04'),
(239, 4, 'login_success', '192.168.254.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 03:24:17'),
(240, 205, 'login_success', '192.168.254.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 03:25:15'),
(241, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 03:42:00'),
(242, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 03:43:38'),
(243, 205, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-03 03:45:17'),
(244, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 03:45:24'),
(245, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 03:48:57'),
(246, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 03:49:33'),
(247, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-03 04:53:05'),
(248, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 04:53:12'),
(249, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 04:56:25'),
(250, NULL, 'login_success', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'User login successful', '', '2026-02-03 05:07:47'),
(251, 205, 'login_success', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'User login successful', '', '2026-02-03 05:08:30'),
(252, 6, 'login_success', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'User login successful', '', '2026-02-03 05:26:02'),
(253, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 05:28:48'),
(254, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 05:31:26'),
(255, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-03 05:32:23'),
(256, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 05:33:33'),
(257, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 05:40:01'),
(258, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 05:47:17'),
(259, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 05:48:16'),
(260, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 05:50:15'),
(261, 205, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-03 05:53:41'),
(262, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 05:53:43'),
(263, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 06:03:39'),
(264, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 06:14:40'),
(265, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 06:16:00'),
(266, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-03 06:19:03'),
(267, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-04 13:10:20'),
(268, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-04 13:11:04'),
(269, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 08:26:36'),
(270, 205, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-05 08:27:58'),
(271, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 08:28:06'),
(272, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 08:30:22'),
(273, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 08:35:45'),
(274, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 08:36:43'),
(275, 205, 'login_success', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'User login successful', '', '2026-02-05 08:43:35'),
(276, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 08:44:58'),
(277, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 09:07:17'),
(278, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 09:13:26'),
(279, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:06:52'),
(280, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:07:14'),
(281, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:21:57'),
(282, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:24:07'),
(283, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:26:41'),
(284, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:27:53'),
(285, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:29:14'),
(286, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:29:32'),
(287, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:29:54'),
(288, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:30:36'),
(289, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:33:46'),
(290, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:46:59'),
(291, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 10:51:51'),
(292, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:16:59'),
(293, 224, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-05 11:17:21'),
(294, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:20:57'),
(295, 224, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:21:27'),
(296, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:22:07'),
(297, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:23:55'),
(298, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:24:42'),
(299, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:26:25'),
(300, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:29:49'),
(301, 216, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-05 11:30:06'),
(302, 216, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-05 11:30:15'),
(303, 216, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-05 11:30:21'),
(304, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:30:26'),
(305, 216, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:31:03'),
(306, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:31:52'),
(307, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:32:07'),
(308, 210, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-05 11:32:32'),
(309, 210, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 11:32:35'),
(310, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 12:22:58'),
(311, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 13:21:59'),
(312, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-05 14:07:27'),
(313, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-06 03:25:00'),
(314, 4, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-10 15:46:42'),
(315, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 15:47:01'),
(316, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 15:47:41'),
(317, 6, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-10 15:48:18'),
(318, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 15:48:23'),
(319, 205, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-10 15:49:17'),
(320, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 15:49:20'),
(321, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 15:53:34'),
(322, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 15:55:11'),
(323, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 15:57:49'),
(324, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 15:58:07'),
(325, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:04:52'),
(326, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:05:05'),
(327, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:13:21'),
(328, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:13:40'),
(329, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:14:55'),
(330, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:18:10'),
(331, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:18:34'),
(332, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:19:14'),
(333, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:22:57'),
(334, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:23:22'),
(335, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:23:41'),
(336, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:24:56'),
(337, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-10 16:30:02'),
(338, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-11 03:43:14'),
(339, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-11 03:43:54'),
(340, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-11 03:44:05'),
(341, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-11 03:45:37'),
(342, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-11 03:48:50'),
(343, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-11 03:53:41'),
(344, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-11 03:58:04'),
(345, 4, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-16 04:33:22'),
(346, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-16 04:33:26'),
(347, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-16 04:34:01'),
(348, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 06:24:04'),
(349, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 06:36:57'),
(350, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 06:38:25'),
(351, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 06:40:30'),
(352, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 06:41:37'),
(353, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 06:42:45'),
(354, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 06:44:15'),
(355, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 06:44:56'),
(356, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 07:22:47'),
(357, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-17 07:25:23'),
(358, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-17 07:25:30'),
(359, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 07:25:43'),
(360, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 07:26:27'),
(361, 6, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-17 07:26:42'),
(362, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 07:26:47'),
(363, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-17 07:37:39'),
(364, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-24 13:30:41'),
(365, 204, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-24 13:31:41'),
(366, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-24 13:31:49'),
(367, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-24 13:43:07'),
(368, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-24 13:43:54'),
(369, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-24 13:46:50'),
(370, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-24 14:35:28'),
(371, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-24 17:06:35'),
(372, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', 'User login successful', '', '2026-02-24 17:07:40'),
(373, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-24 17:07:50'),
(374, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-24 17:09:09'),
(375, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-25 19:40:41'),
(376, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-27 07:35:23'),
(377, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-27 08:28:10'),
(378, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-27 10:11:39'),
(379, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-27 14:13:57'),
(380, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-27 14:15:01'),
(381, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-27 14:16:20'),
(382, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-28 17:15:31'),
(383, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-02-28 17:46:25'),
(384, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-02-28 17:46:34'),
(385, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 02:32:38'),
(386, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 02:34:40'),
(387, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 02:39:22'),
(388, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 02:41:58'),
(389, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 02:43:15'),
(390, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 02:44:39'),
(391, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 03:38:28'),
(392, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 03:39:22'),
(393, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 03:40:46'),
(394, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 04:06:15'),
(395, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 04:21:15'),
(396, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 05:27:57'),
(397, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 05:46:52'),
(398, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 05:50:22'),
(399, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 05:50:40'),
(400, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 05:50:42'),
(401, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 05:50:44'),
(402, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 05:52:21'),
(403, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 05:52:28'),
(404, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 05:52:35'),
(405, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 06:02:53'),
(406, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 06:13:39'),
(407, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 07:19:52'),
(408, 204, 'login_success', '192.168.254.107', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-03-01 07:21:29'),
(409, 205, 'login_failed', '192.168.254.104', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-03-01 07:21:48'),
(410, 205, 'login_success', '192.168.254.104', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-03-01 07:21:52'),
(411, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 07:24:34'),
(412, 100, 'login_success', '192.168.254.107', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-03-01 07:25:17'),
(413, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 07:46:52'),
(414, 235, 'login_success', '192.168.254.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', 'User login successful', '', '2026-03-01 07:47:48'),
(415, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 07:56:44'),
(416, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 08:03:21'),
(417, 100, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 08:05:01'),
(418, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 08:05:07'),
(419, NULL, 'login_failed', '192.168.254.104', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'User login failed', 'medium', '2026-03-01 08:06:48'),
(420, NULL, 'login_success', '192.168.254.104', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 'User login successful', '', '2026-03-01 08:06:55'),
(421, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 08:08:40'),
(422, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 08:09:05'),
(423, 6, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 08:28:11'),
(424, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 08:28:16'),
(425, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 09:18:59'),
(426, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 09:19:18'),
(427, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 09:28:54'),
(428, 6, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 09:29:14'),
(429, 6, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 09:29:21'),
(430, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 09:29:24'),
(431, 100, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 09:40:26'),
(432, 100, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 09:40:43'),
(433, 100, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 09:40:45'),
(434, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 09:41:45'),
(435, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-01 09:42:16'),
(436, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 09:42:25'),
(437, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 09:51:44'),
(438, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-01 09:52:34'),
(439, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 01:41:09'),
(440, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 02:19:17'),
(441, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 02:20:15'),
(442, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 12:06:08'),
(443, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 12:06:37'),
(444, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 12:07:37'),
(445, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 12:10:59'),
(446, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:10:20'),
(447, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:12:08'),
(448, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:13:09'),
(449, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:13:57'),
(450, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:15:20'),
(451, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:17:29'),
(452, 235, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:18:54'),
(453, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:20:18'),
(454, 204, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-02 13:27:56'),
(455, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:28:05'),
(456, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:29:58'),
(457, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:34:58'),
(458, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:40:38'),
(459, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:45:14'),
(460, 6, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-02 13:47:31'),
(461, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:47:34'),
(462, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:51:24'),
(463, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 13:55:40'),
(464, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 14:01:26'),
(465, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 14:02:36'),
(466, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 14:05:10'),
(467, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 14:05:45'),
(468, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 14:14:24'),
(469, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 14:18:49'),
(470, 204, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-02 14:27:34'),
(471, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 14:27:40'),
(472, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 15:25:04'),
(473, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 15:43:08'),
(474, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 15:43:51'),
(475, NULL, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 15:48:38'),
(476, NULL, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-02 15:51:47'),
(477, 204, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-02 15:51:55'),
(478, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 15:51:58'),
(479, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 16:06:42'),
(480, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 16:39:01'),
(481, 204, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 16:57:12'),
(482, 6, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-02 17:20:32'),
(483, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 17:20:38');
INSERT INTO `security_logs` (`id`, `user_id`, `event_type`, `ip_address`, `user_agent`, `details`, `severity`, `created_at`) VALUES
(484, 239, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 17:21:37'),
(485, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 17:22:02'),
(486, 239, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 17:25:05'),
(487, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 17:26:11'),
(488, 239, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 17:56:30'),
(489, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 17:56:58'),
(490, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 17:58:54'),
(491, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 18:16:15'),
(492, 238, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-02 18:17:24'),
(493, 238, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 18:17:27'),
(494, 239, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 18:19:02'),
(495, 6, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-02 18:19:58'),
(496, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 18:20:01'),
(497, 100, 'login_failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login failed', 'medium', '2026-03-02 18:23:18'),
(498, 100, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 18:23:21'),
(499, 205, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 18:31:09'),
(500, 238, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 18:52:06'),
(501, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 18:53:38'),
(502, 4, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 18:59:05'),
(503, 6, 'login_success', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'User login successful', '', '2026-03-02 18:59:24');

-- --------------------------------------------------------

--
-- Table structure for table `security_settings`
--

CREATE TABLE `security_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_settings`
--

INSERT INTO `security_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'max_login_attempts', '3', 'Maximum failed login attempts before lockout', 4, '2026-03-01 05:50:32'),
(2, 'lockout_duration', '1', 'Account lockout duration in minutes', 4, '2026-03-01 05:50:32'),
(3, 'password_min_length', '8', 'Minimum password length', 4, '2026-01-31 08:39:28'),
(4, 'password_require_uppercase', '1', 'Require uppercase letter in password', 4, '2026-01-31 08:39:28'),
(5, 'password_require_lowercase', '1', 'Require lowercase letter in password', 4, '2026-01-31 08:39:28'),
(6, 'password_require_number', '1', 'Require number in password', 4, '2026-01-31 08:39:28'),
(7, 'password_require_special', '0', 'Require special character in password', 4, '2026-01-31 08:39:28'),
(8, 'session_timeout', '60', 'Session timeout in minutes', 4, '2026-01-31 08:39:28'),
(9, 'enable_2fa', '0', 'Enable two-factor authentication', NULL, '2026-01-19 15:29:52'),
(10, 'enable_google_login', '1', 'Enable Google OAuth login', NULL, '2026-01-19 15:29:52'),
(11, 'google_client_id', '', 'Google OAuth Client ID', NULL, '2026-01-19 15:29:52'),
(12, 'google_client_secret', '', 'Google OAuth Client Secret', NULL, '2026-01-19 15:29:52'),
(13, 'smtp_host', 'smtp.gmail.com', 'SMTP server host', 4, '2026-01-19 16:16:41'),
(14, 'smtp_port', '587', 'SMTP server port', 4, '2026-01-19 16:16:41'),
(15, 'smtp_username', 'revillajames40@gmail.com', 'SMTP username (Gmail address)', 4, '2026-01-19 16:16:41'),
(16, 'smtp_password', 'hind kzcv kbac ojcp', 'SMTP password (App password)', 4, '2026-01-19 16:16:41'),
(17, 'smtp_from_email', '', 'From email address', 4, '2026-01-19 16:16:41'),
(18, 'smtp_from_name', 'DATAMEX COLLEGE OF SAINT ADELINE', 'From name for emails', 4, '2026-01-19 16:24:35'),
(19, 'enable_email_verification', '1', 'Require email verification for new accounts', NULL, '2026-01-19 15:29:52'),
(20, 'password_reset_expiry', '60', 'Password reset link expiry in minutes', 4, '2026-01-31 08:39:28'),
(21, 'lockout_cycles', '3', 'Allowed lockout cycles before permanent lock', 4, '2026-03-01 05:50:32');

-- --------------------------------------------------------

--
-- Table structure for table `shs_grades`
--

CREATE TABLE `shs_grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `semester` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1=1st Semester, 2=2nd Semester',
  `q1_grade` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Quarter 1 grade (whole number)',
  `q2_grade` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Quarter 2 grade (whole number)',
  `q3_grade` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Quarter 3 grade (whole number)',
  `q4_grade` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Quarter 4 grade (whole number)',
  `sem1_final_grade` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Computed: round((Q1+Q2)/2)',
  `sem2_final_grade` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Computed: round((Q3+Q4)/2)',
  `final_grade` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Computed: round((sem1+sem2)/2) or single semester',
  `remarks` enum('passed','failed','with_remedial','incomplete','') DEFAULT '',
  `status` enum('active','dropped','credited') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shs_grade_levels`
--

CREATE TABLE `shs_grade_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `strand_id` int(10) UNSIGNED NOT NULL,
  `grade_level` tinyint(3) UNSIGNED NOT NULL,
  `grade_name` varchar(20) NOT NULL,
  `semesters_count` tinyint(3) UNSIGNED DEFAULT 2,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shs_grade_levels`
--

INSERT INTO `shs_grade_levels` (`id`, `strand_id`, `grade_level`, `grade_name`, `semesters_count`, `is_active`, `created_at`) VALUES
(1, 1, 11, 'Grade 11', 2, 1, '2026-01-17 15:39:11'),
(2, 1, 12, 'Grade 12', 2, 1, '2026-01-17 15:39:11'),
(3, 2, 11, 'Grade 11', 2, 1, '2026-01-17 15:39:11'),
(4, 2, 12, 'Grade 12', 2, 1, '2026-01-17 15:39:11'),
(5, 3, 11, 'Grade 11', 2, 1, '2026-01-17 15:39:11'),
(6, 3, 12, 'Grade 12', 2, 1, '2026-01-17 15:39:11'),
(7, 4, 11, 'Grade 11', 2, 1, '2026-01-17 15:39:11'),
(8, 4, 12, 'Grade 12', 2, 1, '2026-01-17 15:39:11'),
(9, 5, 11, 'Grade 11', 2, 1, '2026-01-17 15:39:11'),
(10, 5, 12, 'Grade 12', 2, 1, '2026-01-17 15:39:11'),
(11, 6, 11, 'Grade 11', 2, 1, '2026-01-17 15:39:11'),
(12, 6, 12, 'Grade 12', 2, 1, '2026-01-17 15:39:11'),
(13, 7, 11, 'Grade 11', 2, 1, '2026-01-17 15:39:11'),
(14, 7, 12, 'Grade 12', 2, 1, '2026-01-17 15:39:11'),
(15, 8, 11, 'Grade 11', 2, 1, '2026-01-17 15:39:11'),
(16, 8, 12, 'Grade 12', 2, 1, '2026-01-17 15:39:11');

-- --------------------------------------------------------

--
-- Table structure for table `shs_graduation_requirements`
--

CREATE TABLE `shs_graduation_requirements` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `strand_id` int(11) NOT NULL,
  `total_required_subjects` int(11) NOT NULL DEFAULT 0,
  `completed_subjects` int(11) NOT NULL DEFAULT 0,
  `missing_subjects` int(11) NOT NULL DEFAULT 0,
  `has_remedial_subjects` tinyint(1) NOT NULL DEFAULT 0,
  `graduation_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `last_checked_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shs_strands`
--

CREATE TABLE `shs_strands` (
  `id` int(10) UNSIGNED NOT NULL,
  `track_id` int(10) UNSIGNED NOT NULL,
  `strand_code` varchar(20) NOT NULL,
  `strand_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shs_strands`
--

INSERT INTO `shs_strands` (`id`, `track_id`, `strand_code`, `strand_name`, `description`, `is_active`, `created_at`) VALUES
(1, 1, 'STEM', 'Science, Technology, Engineering and Mathematics', 'Focuses on scientific and technical skills', 1, '2026-01-17 15:39:11'),
(2, 1, 'ABM', 'Accountancy, Business and Management', 'Prepares students for business and finance careers', 1, '2026-01-17 15:39:11'),
(3, 1, 'HUMSS', 'Humanities and Social Sciences', 'Develops critical thinking and communication skills', 1, '2026-01-17 15:39:11'),
(4, 1, 'GAS', 'General Academic Strand', 'Provides a general education foundation', 1, '2026-01-17 15:39:11'),
(5, 4, 'ICT', 'Information and Communications Technology', 'Technical skills in IT and programming', 1, '2026-01-17 15:39:11'),
(6, 4, 'HE', 'Home Economics', 'Culinary arts and hospitality management', 1, '2026-01-17 15:39:11');

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
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shs_tracks`
--

INSERT INTO `shs_tracks` (`id`, `track_name`, `track_code`, `written_work_weight`, `performance_task_weight`, `quarterly_exam_weight`, `description`, `is_active`) VALUES
(1, 'Academic Track', 'ACAD', 25.00, 50.00, 25.00, 'Prepares students who wish to pursue higher education or college degrees. Contains strands like STEM, ABM, HUMSS, and GAS.', 1),
(2, 'Academic Track - ABM', 'ABM', 30.00, 50.00, 20.00, NULL, 0),
(3, 'Academic Track - HUMSS', 'HUMSS', 30.00, 50.00, 20.00, NULL, 0),
(4, 'TVL Track', 'TVL', 20.00, 60.00, 20.00, 'Technical-Vocational-Livelihood track for students pursuing technical/vocational skills. Contains strands like ICT, Home Economics, and more.', 1),
(5, 'Arts and Design Track', 'ARTS', 20.00, 60.00, 20.00, NULL, 0),
(6, 'Sports Track', 'SPORTS', 20.00, 60.00, 20.00, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `student_no` varchar(20) NOT NULL,
  `lrn` varchar(12) DEFAULT NULL,
  `course_id` int(10) UNSIGNED DEFAULT NULL,
  `program_type` enum('college','shs') DEFAULT NULL,
  `student_type` enum('regular','irregular','transferee') NOT NULL DEFAULT 'regular',
  `previous_school` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`user_id`, `student_no`, `lrn`, `course_id`, `program_type`, `student_type`, `previous_school`) VALUES
(235, '2026-0002', NULL, 1, 'college', 'transferee', 'Duon High School'),
(239, '2026-0004', '105010070123', 3, 'shs', 'regular', '');

-- --------------------------------------------------------

--
-- Table structure for table `student_completed_subjects`
--

CREATE TABLE `student_completed_subjects` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `completion_source` varchar(255) DEFAULT NULL,
  `completion_type` enum('credited','bridging','remedial') DEFAULT 'credited',
  `semester` tinyint(4) DEFAULT NULL,
  `previous_subject_name` varchar(255) DEFAULT NULL,
  `previous_grade` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_completed_subjects`
--

INSERT INTO `student_completed_subjects` (`id`, `student_id`, `subject_id`, `completion_source`, `completion_type`, `semester`, `previous_subject_name`, `previous_grade`, `remarks`, `recorded_by`, `created_at`) VALUES
(1, 235, 32, 'Duon High School', 'credited', NULL, '', '1.5', 'Completed in previous school', 6, '2026-03-01 07:38:37'),
(2, 235, 34, 'Duon High School', 'credited', NULL, '', '1.25', 'Completed in previous school', 6, '2026-03-01 07:38:37'),
(3, 235, 35, 'Duon High School', 'credited', NULL, '', '2.0', 'Completed in previous school', 6, '2026-03-01 07:38:37');

-- --------------------------------------------------------

--
-- Table structure for table `student_fees`
--

CREATE TABLE `student_fees` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `fee_type` varchar(100) NOT NULL COMMENT 'e.g., Tuition Fee, Enrollment Fee, Misc Fee',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `semester` enum('1st','2nd','summer') DEFAULT '1st',
  `year_level_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_fees`
--

INSERT INTO `student_fees` (`id`, `student_id`, `fee_type`, `amount`, `academic_year_id`, `semester`, `year_level_id`, `description`, `due_date`, `created_by`, `created_at`) VALUES
(1, 203, 'Tuition Fee', 11600.00, 1, '1st', NULL, '', NULL, 211, '2026-01-18 20:31:19'),
(2, 221, 'Tuition Fee', 11100.00, 1, '1st', NULL, '', NULL, 6, '2026-01-21 03:21:50'),
(3, 226, 'Tuition', 8700.00, 1, '1st', NULL, 'Tuition Fee', NULL, 6, '2026-02-05 10:10:41'),
(4, 231, 'Tuition', 9000.00, 1, '1st', NULL, 'Tuition Fee', NULL, 6, '2026-02-05 10:51:25'),
(5, 232, 'Tuition', 8500.00, 1, '1st', NULL, 'Tuition Fee', NULL, 6, '2026-02-11 03:52:25'),
(7, 234, 'Tuition', 8500.00, 1, '1st', NULL, 'Tuition Fee', NULL, 6, '2026-03-01 07:35:43'),
(8, 235, 'Tuition', 8500.00, 1, '1st', NULL, 'Tuition Fee', NULL, 6, '2026-03-01 07:36:42'),
(9, 234, 'Tuition Fee', 8500.00, 1, '2nd', NULL, 'Auto-assessed tuition for 2nd semester enrollment', NULL, 6, '2026-03-01 08:38:56'),
(10, 236, 'Tuition Fee', 8500.00, 1, '1st', NULL, 'Auto-assessed tuition for 1st semester enrollment', NULL, 6, '2026-03-01 09:20:03'),
(11, 237, 'Tuition Fee', 8500.00, 1, '1st', 1, 'Auto-assessed tuition for 1st semester enrollment', NULL, 6, '2026-03-01 09:37:19'),
(12, 237, 'Discount', -1000.00, 1, '1st', 1, 'Discount: Early Enrollment (₱1,000.00)', NULL, 6, '2026-03-01 09:37:19'),
(13, 237, 'Tuition Fee', 8500.00, 1, '1st', 2, 'Auto-assessed tuition for 1st semester enrollment', NULL, 6, '2026-03-01 09:39:05'),
(14, 237, 'Discount', -1000.00, 1, '1st', 2, 'Discount: Early Enrollment (₱1,000.00)', NULL, 6, '2026-03-01 09:39:05'),
(15, 237, 'Tuition Fee', 11200.00, 1, '1st', 3, 'Auto-assessed tuition for 1st semester enrollment', NULL, 6, '2026-03-02 13:22:41'),
(16, 237, 'Discount', -1000.00, 1, '1st', 3, 'Discount: Early Enrollment (₱1,000.00)', NULL, 6, '2026-03-02 13:22:41');

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
-- Table structure for table `student_promotions`
--

CREATE TABLE `student_promotions` (
  `id` int(11) NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `from_academic_year_id` int(11) NOT NULL,
  `to_academic_year_id` int(11) NOT NULL,
  `from_year_level_id` int(10) UNSIGNED DEFAULT NULL,
  `to_year_level_id` int(10) UNSIGNED DEFAULT NULL,
  `from_shs_grade_level_id` int(11) DEFAULT NULL,
  `to_shs_grade_level_id` int(11) DEFAULT NULL,
  `program_id` int(10) UNSIGNED DEFAULT NULL,
  `shs_strand_id` int(11) DEFAULT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `promotion_type` enum('promoted','retained','graduated','transferred') NOT NULL,
  `gwa` decimal(4,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `promoted_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_promotions`
--

INSERT INTO `student_promotions` (`id`, `student_id`, `from_academic_year_id`, `to_academic_year_id`, `from_year_level_id`, `to_year_level_id`, `from_shs_grade_level_id`, `to_shs_grade_level_id`, `program_id`, `shs_strand_id`, `branch_id`, `promotion_type`, `gwa`, `remarks`, `promoted_by`, `created_at`) VALUES
(1, 200, 1, 1, 1, 2, NULL, NULL, 1, NULL, 1, 'promoted', NULL, NULL, 205, '2026-01-19 15:06:00'),
(2, 202, 1, 1, 1, 2, NULL, NULL, 1, NULL, 1, 'promoted', NULL, NULL, 205, '2026-01-19 15:06:00'),
(3, 201, 1, 1, 1, 2, NULL, NULL, 1, NULL, 1, 'promoted', NULL, NULL, 205, '2026-01-19 15:06:00'),
(4, 218, 1, 1, 1, 2, NULL, NULL, 1, NULL, 1, 'promoted', NULL, NULL, 205, '2026-01-19 15:06:00');

-- --------------------------------------------------------

--
-- Table structure for table `student_subject_enrollments`
--

CREATE TABLE `student_subject_enrollments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `status` enum('enrolled','completed','dropped','credited') NOT NULL DEFAULT 'enrolled',
  `enrollment_type` enum('regular','irregular','transferee') NOT NULL DEFAULT 'regular',
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_subject_enrollments`
--

INSERT INTO `student_subject_enrollments` (`id`, `student_id`, `subject_id`, `section_id`, `academic_year_id`, `status`, `enrollment_type`, `recorded_by`, `created_at`, `updated_at`) VALUES
(1, 226, 3, 1, 1, 'enrolled', 'regular', NULL, '2026-02-27 07:34:54', '2026-02-27 07:34:54'),
(2, 226, 5, 1, 1, 'enrolled', 'regular', NULL, '2026-02-27 07:34:54', '2026-02-27 07:34:54'),
(8, 232, 39, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:05:11', '2026-03-01 04:05:11'),
(9, 232, 41, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:05:11', '2026-03-01 04:05:11'),
(10, 232, 45, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:05:11', '2026-03-01 04:05:11'),
(11, 232, 40, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:05:11', '2026-03-01 04:05:11'),
(12, 232, 42, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:05:11', '2026-03-01 04:05:11'),
(13, 232, 43, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:05:11', '2026-03-01 04:05:11'),
(14, 232, 44, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:05:11', '2026-03-01 04:05:11'),
(15, 232, 32, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:28', '2026-03-01 04:35:28'),
(16, 232, 34, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:28', '2026-03-01 04:35:28'),
(17, 232, 35, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:28', '2026-03-01 04:35:28'),
(18, 232, 33, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:28', '2026-03-01 04:35:28'),
(19, 232, 37, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:28', '2026-03-01 04:35:28'),
(20, 232, 30, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:28', '2026-03-01 04:35:28'),
(21, 232, 31, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:28', '2026-03-01 04:35:28'),
(22, 232, 36, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:28', '2026-03-01 04:35:28'),
(23, 232, 38, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:28', '2026-03-01 04:35:28'),
(24, 226, 52, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:48', '2026-03-01 04:35:48'),
(25, 226, 53, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:48', '2026-03-01 04:35:48'),
(26, 226, 48, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:48', '2026-03-01 04:35:48'),
(27, 226, 49, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:48', '2026-03-01 04:35:48'),
(28, 226, 50, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:48', '2026-03-01 04:35:48'),
(29, 226, 51, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 04:35:48', '2026-03-01 04:35:48'),
(30, 234, 12, 2, 1, 'enrolled', 'regular', 205, '2026-03-01 07:37:17', '2026-03-01 07:44:46'),
(31, 234, 16, 2, 1, 'enrolled', 'regular', 205, '2026-03-01 07:37:17', '2026-03-01 07:44:46'),
(32, 234, 17, 2, 1, 'enrolled', 'regular', 205, '2026-03-01 07:37:17', '2026-03-01 07:44:46'),
(33, 234, 20, 2, 1, 'enrolled', 'regular', 205, '2026-03-01 07:37:17', '2026-03-01 07:44:46'),
(34, 234, 14, 2, 1, 'enrolled', 'regular', 205, '2026-03-01 07:37:17', '2026-03-01 07:44:46'),
(35, 234, 15, 2, 1, 'enrolled', 'regular', 205, '2026-03-01 07:37:17', '2026-03-01 07:44:46'),
(36, 234, 19, 2, 1, 'enrolled', 'regular', 205, '2026-03-01 07:37:17', '2026-03-01 07:44:46'),
(37, 234, 18, 2, 1, 'enrolled', 'regular', 205, '2026-03-01 07:37:17', '2026-03-01 07:44:46'),
(38, 234, 13, 2, 1, 'enrolled', 'regular', 205, '2026-03-01 07:37:17', '2026-03-01 07:44:46'),
(39, 235, 32, 9, 1, 'credited', 'transferee', 205, '2026-03-01 07:38:37', '2026-03-01 07:45:56'),
(40, 235, 34, 9, 1, 'credited', 'transferee', 205, '2026-03-01 07:38:37', '2026-03-01 07:45:56'),
(41, 235, 35, 9, 1, 'credited', 'transferee', 205, '2026-03-01 07:38:37', '2026-03-01 07:45:56'),
(42, 235, 33, 9, 1, 'enrolled', 'transferee', 205, '2026-03-01 07:38:37', '2026-03-01 07:45:56'),
(43, 235, 37, 9, 1, 'enrolled', 'transferee', 205, '2026-03-01 07:38:37', '2026-03-01 07:45:56'),
(44, 235, 30, 9, 1, 'enrolled', 'transferee', 205, '2026-03-01 07:38:37', '2026-03-01 07:45:56'),
(45, 235, 31, 9, 1, 'enrolled', 'transferee', 205, '2026-03-01 07:38:37', '2026-03-01 07:45:56'),
(46, 235, 36, 9, 1, 'enrolled', 'transferee', 205, '2026-03-01 07:38:37', '2026-03-01 07:45:56'),
(47, 235, 38, 9, 1, 'enrolled', 'transferee', 205, '2026-03-01 07:38:37', '2026-03-01 07:45:56'),
(66, 234, 32, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:37:08', '2026-03-01 08:37:08'),
(67, 234, 34, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:37:08', '2026-03-01 08:37:08'),
(68, 234, 35, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:37:08', '2026-03-01 08:37:08'),
(69, 234, 33, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:37:08', '2026-03-01 08:37:08'),
(70, 234, 37, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:37:08', '2026-03-01 08:37:08'),
(71, 234, 30, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:37:08', '2026-03-01 08:37:08'),
(72, 234, 31, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:37:08', '2026-03-01 08:37:08'),
(73, 234, 36, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:37:08', '2026-03-01 08:37:08'),
(74, 234, 38, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:37:08', '2026-03-01 08:37:08'),
(75, 234, 39, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:38:56', '2026-03-01 08:38:56'),
(76, 234, 41, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:38:56', '2026-03-01 08:38:56'),
(77, 234, 45, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:38:56', '2026-03-01 08:38:56'),
(78, 234, 40, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:38:56', '2026-03-01 08:38:56'),
(79, 234, 42, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:38:56', '2026-03-01 08:38:56'),
(80, 234, 43, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:38:56', '2026-03-01 08:38:56'),
(81, 234, 44, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 08:38:56', '2026-03-01 08:38:56'),
(106, 234, 52, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:10:37', '2026-03-01 09:10:37'),
(107, 234, 53, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:10:37', '2026-03-01 09:10:37'),
(108, 234, 48, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:10:37', '2026-03-01 09:10:37'),
(109, 234, 49, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:10:37', '2026-03-01 09:10:37'),
(110, 234, 50, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:10:37', '2026-03-01 09:10:37'),
(111, 234, 51, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:10:37', '2026-03-01 09:10:37'),
(112, 234, 59, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:17:54', '2026-03-01 09:17:54'),
(113, 234, 58, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:17:54', '2026-03-01 09:17:54'),
(114, 234, 57, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:17:54', '2026-03-01 09:17:54'),
(115, 234, 55, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:17:54', '2026-03-01 09:17:54'),
(116, 234, 56, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:17:54', '2026-03-01 09:17:54'),
(117, 234, 54, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:17:54', '2026-03-01 09:17:54'),
(118, 236, 12, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:20:03', '2026-03-01 09:20:03'),
(119, 236, 16, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:20:03', '2026-03-01 09:20:03'),
(120, 236, 17, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:20:03', '2026-03-01 09:20:03'),
(121, 236, 20, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:20:03', '2026-03-01 09:20:03'),
(122, 236, 14, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:20:03', '2026-03-01 09:20:03'),
(123, 236, 15, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:20:03', '2026-03-01 09:20:03'),
(124, 236, 19, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:20:03', '2026-03-01 09:20:03'),
(125, 236, 18, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:20:03', '2026-03-01 09:20:03'),
(126, 236, 13, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:20:03', '2026-03-01 09:20:03'),
(127, 236, 32, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:21:05', '2026-03-01 09:21:05'),
(128, 236, 34, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:21:05', '2026-03-01 09:21:05'),
(129, 236, 35, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:21:05', '2026-03-01 09:21:05'),
(130, 236, 33, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:21:05', '2026-03-01 09:21:05'),
(131, 236, 37, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:21:05', '2026-03-01 09:21:05'),
(132, 236, 30, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:21:05', '2026-03-01 09:21:05'),
(133, 236, 31, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:21:05', '2026-03-01 09:21:05'),
(134, 236, 36, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:21:05', '2026-03-01 09:21:05'),
(135, 236, 38, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:21:05', '2026-03-01 09:21:05'),
(136, 237, 12, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:37:19', '2026-03-01 09:37:19'),
(137, 237, 16, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:37:19', '2026-03-01 09:37:19'),
(138, 237, 17, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:37:19', '2026-03-01 09:37:19'),
(139, 237, 20, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:37:19', '2026-03-01 09:37:19'),
(140, 237, 14, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:37:19', '2026-03-01 09:37:19'),
(141, 237, 15, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:37:19', '2026-03-01 09:37:19'),
(142, 237, 19, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:37:19', '2026-03-01 09:37:19'),
(143, 237, 18, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:37:19', '2026-03-01 09:37:19'),
(144, 237, 13, NULL, 1, 'enrolled', 'regular', 6, '2026-03-01 09:37:19', '2026-03-01 09:37:19'),
(145, 237, 32, 9, 1, 'enrolled', 'regular', 205, '2026-03-01 09:39:05', '2026-03-02 17:26:45'),
(146, 237, 34, 9, 1, 'enrolled', 'regular', 205, '2026-03-01 09:39:05', '2026-03-02 17:26:45'),
(147, 237, 35, 9, 1, 'enrolled', 'regular', 205, '2026-03-01 09:39:05', '2026-03-02 17:26:45'),
(148, 237, 33, 9, 1, 'enrolled', 'regular', 205, '2026-03-01 09:39:05', '2026-03-02 17:26:45'),
(149, 237, 37, 9, 1, 'enrolled', 'regular', 205, '2026-03-01 09:39:05', '2026-03-02 17:26:45'),
(150, 237, 30, 9, 1, 'enrolled', 'regular', 205, '2026-03-01 09:39:05', '2026-03-02 17:26:45'),
(151, 237, 31, 9, 1, 'enrolled', 'regular', 205, '2026-03-01 09:39:05', '2026-03-02 17:26:45'),
(152, 237, 36, 9, 1, 'enrolled', 'regular', 205, '2026-03-01 09:39:05', '2026-03-02 17:26:45'),
(153, 237, 38, 9, 1, 'enrolled', 'regular', 205, '2026-03-01 09:39:05', '2026-03-02 17:26:45'),
(154, 237, 52, NULL, 1, 'enrolled', 'regular', 6, '2026-03-02 13:22:41', '2026-03-02 13:22:41'),
(155, 237, 53, NULL, 1, 'enrolled', 'regular', 6, '2026-03-02 13:22:41', '2026-03-02 13:22:41'),
(156, 237, 48, NULL, 1, 'enrolled', 'regular', 6, '2026-03-02 13:22:41', '2026-03-02 13:22:41'),
(157, 237, 49, NULL, 1, 'enrolled', 'regular', 6, '2026-03-02 13:22:41', '2026-03-02 13:22:41'),
(158, 237, 50, NULL, 1, 'enrolled', 'regular', 6, '2026-03-02 13:22:41', '2026-03-02 13:22:41'),
(159, 237, 51, NULL, 1, 'enrolled', 'regular', 6, '2026-03-02 13:22:41', '2026-03-02 13:22:41'),
(169, 239, 60, 10, 1, 'enrolled', 'regular', 205, '2026-03-02 17:55:33', '2026-03-02 17:55:33'),
(170, 239, 61, 10, 1, 'enrolled', 'regular', 205, '2026-03-02 17:55:33', '2026-03-02 17:55:33'),
(171, 239, 62, 10, 1, 'enrolled', 'regular', 205, '2026-03-02 17:55:33', '2026-03-02 17:55:33'),
(172, 239, 63, 10, 1, 'enrolled', 'regular', 205, '2026-03-02 17:55:33', '2026-03-02 17:55:33'),
(173, 239, 64, 10, 1, 'enrolled', 'regular', 205, '2026-03-02 17:55:33', '2026-03-02 17:55:33'),
(174, 239, 65, 10, 1, 'enrolled', 'regular', 205, '2026-03-02 17:55:33', '2026-03-02 17:55:33'),
(175, 239, 66, 10, 1, 'enrolled', 'regular', 205, '2026-03-02 17:55:33', '2026-03-02 17:55:33'),
(176, 239, 67, 10, 1, 'enrolled', 'regular', 205, '2026-03-02 17:55:33', '2026-03-02 17:55:33'),
(177, 239, 68, 10, 1, 'enrolled', 'regular', 205, '2026-03-02 17:55:33', '2026-03-02 17:55:33');

-- --------------------------------------------------------

--
-- Table structure for table `student_term_enrollments`
--

CREATE TABLE `student_term_enrollments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `program_type` enum('college','shs') NOT NULL DEFAULT 'college',
  `program_id` int(10) UNSIGNED NOT NULL,
  `year_level_id` int(10) UNSIGNED NOT NULL,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `semester` enum('1st','2nd','summer') NOT NULL DEFAULT '1st',
  `student_type` enum('regular','irregular','transferee') NOT NULL DEFAULT 'regular',
  `voucher_status` enum('yes','no') DEFAULT 'no',
  `enrollment_status` enum('pending','approved','rejected') DEFAULT 'approved',
  `previous_school` varchar(255) DEFAULT NULL,
  `status` enum('enrolled','completed','cancelled') NOT NULL DEFAULT 'enrolled',
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_term_enrollments`
--

INSERT INTO `student_term_enrollments` (`id`, `student_id`, `program_type`, `program_id`, `year_level_id`, `academic_year_id`, `semester`, `student_type`, `voucher_status`, `enrollment_status`, `previous_school`, `status`, `recorded_by`, `created_at`, `updated_at`) VALUES
(5, 232, 'college', 1, 2, 1, '2nd', 'regular', 'no', 'approved', '', 'enrolled', 6, '2026-03-01 04:05:11', '2026-03-01 04:05:11'),
(6, 232, 'college', 1, 2, 1, '1st', 'regular', 'no', 'approved', '', 'enrolled', 6, '2026-03-01 04:35:28', '2026-03-01 04:35:28'),
(7, 226, 'college', 1, 3, 1, '1st', 'regular', 'no', 'approved', '', 'enrolled', 6, '2026-03-01 04:35:48', '2026-03-01 04:35:48'),
(8, 234, 'college', 1, 3, 1, '1st', 'regular', 'no', 'approved', '', 'enrolled', 6, '2026-03-01 07:37:17', '2026-03-01 09:10:37'),
(9, 235, 'college', 1, 2, 1, '1st', 'transferee', 'no', 'approved', 'Duon High School', 'enrolled', 6, '2026-03-01 07:38:37', '2026-03-01 07:38:37'),
(11, 234, 'college', 1, 3, 1, '2nd', 'regular', 'no', 'approved', '', 'enrolled', 6, '2026-03-01 08:38:56', '2026-03-01 09:17:54'),
(24, 236, 'college', 1, 2, 1, '1st', 'regular', 'no', 'approved', '', 'enrolled', 6, '2026-03-01 09:20:03', '2026-03-01 09:21:05'),
(26, 237, 'college', 1, 3, 1, '1st', 'regular', '', '', '', 'enrolled', 6, '2026-03-01 09:37:19', '2026-03-02 13:22:41'),
(29, 239, 'shs', 3, 5, 1, '1st', 'regular', '', '', '', 'enrolled', 6, '2026-03-02 16:41:01', '2026-03-02 16:41:01');

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

--
-- Dumping data for table `system_maintenance`
--

INSERT INTO `system_maintenance` (`id`, `title`, `description`, `start_time`, `end_time`, `is_active`, `affected_modules`, `created_by`, `created_at`) VALUES
(1, 'First back up test', '', '2026-01-30 22:36:00', '2026-01-30 22:36:00', 0, NULL, 4, '2026-01-30 14:36:51');

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
(2, 'maintenance_mode', '0', 'boolean', 'system', 'Enable Maintenance Mode', 4, '2026-01-30 14:37:04'),
(3, 'session_timeout', '3600', 'number', 'security', 'Session Timeout (seconds)', NULL, '2026-01-16 16:35:05'),
(4, 'max_login_attempts', '5', 'number', 'security', 'Maximum Login Attempts', NULL, '2026-01-16 16:35:05'),
(5, 'password_min_length', '8', 'number', 'security', 'Minimum Password Length', 4, '2026-01-18 19:06:18'),
(6, 'enable_registration', '1', 'boolean', 'general', 'Allow User Registration', 4, '2026-01-18 19:06:21'),
(7, 'backup_frequency', 'daily', 'string', 'system', 'Backup Frequency', NULL, '2026-01-16 16:35:05');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subject_assignments`
--

CREATE TABLE `teacher_subject_assignments` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `curriculum_subject_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_subject_assignments`
--

INSERT INTO `teacher_subject_assignments` (`id`, `teacher_id`, `curriculum_subject_id`, `branch_id`, `academic_year_id`, `is_active`, `assigned_at`) VALUES
(1, 100, 4, 1, 1, 1, '2026-01-18 17:55:47'),
(2, 100, 3, 1, 1, 1, '2026-01-18 17:56:06'),
(3, 216, 4, 2, 1, 1, '2026-01-18 20:50:08'),
(4, 216, 3, 2, 1, 1, '2026-01-18 20:50:13'),
(5, 100, 5, 1, 1, 1, '2026-01-19 14:50:35'),
(6, 100, 12, 1, 1, 0, '2026-03-01 04:06:32'),
(7, 100, 16, 1, 1, 0, '2026-03-01 04:06:37'),
(8, 100, 14, 1, 1, 1, '2026-03-01 07:43:20'),
(9, 100, 15, 1, 1, 1, '2026-03-01 07:43:24'),
(10, 100, 34, 1, 1, 1, '2026-03-01 07:46:23'),
(11, 100, 35, 1, 1, 1, '2026-03-01 07:46:27'),
(12, 100, 30, 1, 1, 1, '2026-03-01 07:46:32'),
(13, 100, 36, 1, 1, 1, '2026-03-01 07:46:36'),
(14, 238, 67, 1, 1, 1, '2026-03-02 18:16:40'),
(15, 238, 60, 1, 1, 1, '2026-03-02 18:16:46'),
(16, 238, 65, 1, 1, 1, '2026-03-02 18:16:57');

-- --------------------------------------------------------

--
-- Table structure for table `tuition_discounts`
--

CREATE TABLE `tuition_discounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'e.g., Early Bird Discount, Scholarship Discount',
  `discount_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage' COMMENT 'percentage = % off tuition, fixed = flat amount off',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage (0-100) or fixed amount',
  `start_date` date NOT NULL COMMENT 'Discount validity start date',
  `end_date` date NOT NULL COMMENT 'Discount validity end date',
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tuition_discounts`
--

INSERT INTO `tuition_discounts` (`id`, `name`, `discount_type`, `value`, `start_date`, `end_date`, `academic_year_id`, `description`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Early Enrollment', 'fixed', 1000.00, '2026-03-01', '2026-03-02', 1, '', 1, 6, '2026-03-01 09:23:31', '2026-03-01 09:23:31');

-- --------------------------------------------------------

--
-- Table structure for table `tuition_penalties`
--

CREATE TABLE `tuition_penalties` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'e.g., Late Enrollment Penalty, Re-enrollment Fee',
  `penalty_type` enum('percentage','fixed') NOT NULL DEFAULT 'fixed' COMMENT 'percentage = % added to tuition, fixed = flat amount added',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage (0-100) or fixed amount',
  `start_date` date NOT NULL COMMENT 'Penalty applies from this date onward',
  `applicable_term` enum('all','prelim','midterm','prefinals','finals') NOT NULL DEFAULT 'all' COMMENT 'Which payment term this penalty applies to',
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tuition_penalties`
--

INSERT INTO `tuition_penalties` (`id`, `name`, `penalty_type`, `value`, `start_date`, `applicable_term`, `academic_year_id`, `description`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Pre lim penalty', 'fixed', 100.00, '2026-03-01', 'all', 1, '', 0, 6, '2026-03-02 15:36:43', '2026-03-02 16:23:21'),
(2, 'penaly prelim', 'fixed', 100.00, '2026-03-02', 'prelim', 1, '', 1, 6, '2026-03-02 16:24:06', '2026-03-02 16:24:06');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lock_count` int(11) NOT NULL DEFAULT 0,
  `last_locked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `status`, `last_login`, `created_at`, `lock_count`, `last_locked_at`) VALUES
(4, 'admin@elms.com', '$2y$10$HT./ovUEHrcCRGbLzjSHquhagQeVxD9iK59//YEDUfntP5pn3o3m2', 'active', '2026-03-03 02:59:05', '2026-01-16 12:57:04', 0, NULL),
(6, 'registrar@elms.com', '$2y$10$emmb9dv7qdUCsPWfW0Ey4u3YLcA6h99ym0DrPa1dAo8n0bV0PUeSe', 'active', '2026-03-03 02:59:24', '2026-01-16 13:06:58', 0, NULL),
(100, 'teacher@elms.com', '$2y$10$gS8DWoSFQX9iUAZ4r2jCvucHbM0Swd7iGB.5uG1pxlBkiKSXZf22O', 'active', '2026-03-03 02:23:21', '2026-01-16 13:08:50', 0, NULL),
(204, 'schooladmin@elms.com', '$2y$10$QA38bQbDvhQwo/.BHioND.p1Y06Oy0rcHTXOC7i4FnhmwqLyVZGcu', 'active', '2026-03-03 00:57:12', '2026-01-16 13:32:27', 0, NULL),
(205, 'branchadmin@elms.com', '$2y$10$Bic2FhHZbHvu3AvS8601HO0UXxxyyvi01LGZh3iIW35AmKC8kFB0i', 'active', '2026-03-03 02:31:09', '2026-01-16 16:32:28', 0, NULL),
(210, 'sample@elms.com', '$2y$10$7Xri9SoPDxk2v/ybP78NduUbh8rspsEBnffz.OksDEdm4llPiRpWu', 'active', '2026-02-05 19:32:35', '2026-01-18 19:14:15', 0, NULL),
(211, 'rev@registrar.com', '$2y$10$GM/9k7ytk1UmmRMd2/FkgOgA9CdZN5RupGr5iCzCCv5FKhp0zy2Rq', 'active', '2026-01-19 18:06:01', '2026-01-18 20:10:51', 0, NULL),
(216, 'senpai@teacher.com', '$2y$10$U8SYILHG/24ZXUUTUpyC3.HbI3W8tbsOicY7/zfM.Z29MkUfwWQk.', 'active', '2026-02-05 19:31:03', '2026-01-18 20:47:29', 0, NULL),
(224, 'jamessenpai9@gmail.com', '$2y$10$i8fTVxxUibMVx8M5I6oDEecc7AMZMedGS4Hrac2wQeVAJop/Z4Sxi', 'active', '2026-02-05 19:21:27', '2026-01-24 07:58:17', 0, NULL),
(235, 'revillajamesandrei4@gmail.com', '$2y$10$DA8FNEF3JSD4fRer1Im4Xue8jZaRy9eyJcvxrE1O26Pelu1rAk.6W', 'active', '2026-03-02 21:18:54', '2026-03-01 07:36:42', 0, NULL),
(238, 'Yujinjae05@gmail.com', '$2y$10$d2vx7TOccroLPpR0ZmS3NuL69j3BwspUf4zdwsJX/wsCwySWnIIPa', 'active', '2026-03-03 02:52:06', '2026-03-02 14:11:20', 0, NULL),
(239, 'suycanomarkrufort@gmail.com', '$2y$10$eEuplmUpue3rZ4JFD/bZseyYAk9YfDOXda44d2bkISYGJmT3htFOe', 'active', '2026-03-03 02:19:02', '2026-03-02 16:40:24', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`user_id`, `first_name`, `last_name`, `contact_no`, `address`, `branch_id`) VALUES
(4, 'Super', 'Administrator', '09123456789', 'Datamex HQ', NULL),
(6, 'Maria', 'Santos', '09171234567', 'Registrar Office', 1),
(100, 'Juan', 'Dela Cruz', NULL, NULL, 1),
(204, 'Academic', 'Dean', '09191234567', NULL, NULL),
(205, 'Branch', 'Coordinator', '09201234567', NULL, 1),
(210, 'James', 'Andrei Revilla', '', '', 2),
(211, 'James', 'Revs', '0906281723', NULL, 2),
(216, 'Senpai', 'James', '', '', 2),
(224, 'James', 'Andrei Revilla', '09181234567', 'Dyan Lng Sa TAbi', 4),
(235, 'Andrei James', 'Subaru', '0909936123', 'Malabon City', 1),
(238, 'John Drew', 'Suycano', NULL, 'Dyanlang', 1),
(239, 'mark rufort', 'suycano', '0906281723', 'dyan lng sa tabi', 1);

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
(204, 2),
(205, 3),
(210, 3),
(211, 4),
(216, 5),
(224, 3),
(235, 6),
(238, 5),
(239, 6);

-- --------------------------------------------------------

--
-- Table structure for table `year_levels`
--

CREATE TABLE `year_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `level_name` varchar(50) NOT NULL,
  `level_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `program_type` enum('college','shs','both') NOT NULL DEFAULT 'both'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `year_levels`
--

INSERT INTO `year_levels` (`id`, `level_name`, `level_order`, `program_type`) VALUES
(1, '1st Year', 1, 'college'),
(2, '2nd Year', 2, 'college'),
(3, '3rd Year', 3, 'college'),
(4, '4th Year', 4, 'college'),
(5, 'Grade 11', 5, 'shs'),
(6, 'Grade 12', 6, 'shs');

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
-- Indexes for table `active_sessions`
--
ALTER TABLE `active_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_activity` (`last_activity`);

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
-- Indexes for table `certificates_issued`
--
ALTER TABLE `certificates_issued`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_no` (`reference_no`),
  ADD KEY `issued_by` (`issued_by`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_type` (`certificate_type`),
  ADD KEY `idx_reference` (`reference_no`);

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
  ADD KEY `idx_section` (`section_name`),
  ADD KEY `idx_curriculum_subject` (`curriculum_subject_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch` (`branch_id`);

--
-- Indexes for table `curriculum_subjects`
--
ALTER TABLE `curriculum_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`),
  ADD KEY `idx_program_year` (`program_id`,`year_level_id`,`semester`),
  ADD KEY `idx_shs_strand_grade` (`shs_strand_id`,`shs_grade_level_id`,`semester`),
  ADD KEY `fk_curriculum_program` (`program_id`),
  ADD KEY `fk_curriculum_yearlevel` (`year_level_id`),
  ADD KEY `fk_curriculum_shs_strand` (`shs_strand_id`),
  ADD KEY `fk_curriculum_shs_gradelevel` (`shs_grade_level_id`),
  ADD KEY `fk_curriculum_created_by` (`created_by`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_class` (`class_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_status_date` (`status`,`created_at`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_class` (`class_id`),
  ADD KEY `idx_student_class` (`student_id`,`class_id`);

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
-- Indexes for table `grading_terms`
--
ALTER TABLE `grading_terms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `learning_materials`
--
ALTER TABLE `learning_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class` (`class_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `ip_address` (`ip_address`),
  ADD KEY `attempted_at` (`attempted_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_notif_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_notif_type` (`type`);

--
-- Indexes for table `oauth_tokens`
--
ALTER TABLE `oauth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`provider`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_no` (`reference_no`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_student_status` (`student_id`,`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `program_code` (`program_code`),
  ADD KEY `idx_school` (`school_id`);

--
-- Indexes for table `program_courses`
--
ALTER TABLE `program_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_program_course` (`program_id`,`year_level_id`,`semester`,`course_code`),
  ADD KEY `fk_pc_program` (`program_id`),
  ADD KEY `fk_pc_yearlevel` (`year_level_id`);

--
-- Indexes for table `program_tuition_fees`
--
ALTER TABLE `program_tuition_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_program` (`program_id`),
  ADD KEY `idx_year_level` (`year_level_id`);

--
-- Indexes for table `program_year_levels`
--
ALTER TABLE `program_year_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_program_year` (`program_id`,`year_level`),
  ADD KEY `fk_yearlevel_program` (`program_id`);

--
-- Indexes for table `resource_locks`
--
ALTER TABLE `resource_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lock_key` (`lock_key`),
  ADD KEY `idx_lock_key` (`lock_key`),
  ADD KEY `idx_expires` (`expires_at`);

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
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_program` (`program_id`),
  ADD KEY `idx_year_level` (`year_level_id`),
  ADD KEY `idx_strand` (`shs_strand_id`),
  ADD KEY `idx_grade_level` (`shs_grade_level_id`),
  ADD KEY `idx_academic_year` (`academic_year_id`),
  ADD KEY `idx_branch` (`branch_id`);

--
-- Indexes for table `section_students`
--
ALTER TABLE `section_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`section_id`,`student_id`),
  ADD KEY `idx_section` (`section_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_event` (`user_id`,`event_type`),
  ADD KEY `idx_severity` (`severity`);

--
-- Indexes for table `security_settings`
--
ALTER TABLE `security_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `shs_grades`
--
ALTER TABLE `shs_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_shs_grade_unique` (`student_id`,`section_id`,`subject_id`,`academic_year_id`),
  ADD KEY `idx_shs_grade_student` (`student_id`),
  ADD KEY `idx_shs_grade_section` (`section_id`),
  ADD KEY `idx_shs_grade_subject` (`subject_id`),
  ADD KEY `idx_shs_grade_ay` (`academic_year_id`);

--
-- Indexes for table `shs_grade_levels`
--
ALTER TABLE `shs_grade_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_strand_grade` (`strand_id`,`grade_level`),
  ADD KEY `fk_gradelevel_strand` (`strand_id`);

--
-- Indexes for table `shs_graduation_requirements`
--
ALTER TABLE `shs_graduation_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_shs_grad_student` (`student_id`,`strand_id`),
  ADD KEY `idx_shs_grad_eligible` (`graduation_eligible`);

--
-- Indexes for table `shs_strands`
--
ALTER TABLE `shs_strands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `strand_code` (`strand_code`),
  ADD KEY `fk_strand_track` (`track_id`);

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
  ADD UNIQUE KEY `idx_students_lrn` (`lrn`),
  ADD KEY `idx_student_no` (`student_no`),
  ADD KEY `idx_course` (`course_id`);

--
-- Indexes for table `student_completed_subjects`
--
ALTER TABLE `student_completed_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_subject` (`student_id`,`subject_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_subject` (`subject_id`),
  ADD KEY `idx_recorded_by` (`recorded_by`);

--
-- Indexes for table `student_fees`
--
ALTER TABLE `student_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_fees_student` (`student_id`),
  ADD KEY `idx_student_fees_type` (`fee_type`),
  ADD KEY `idx_student_fees_ay` (`academic_year_id`);

--
-- Indexes for table `student_grade_details`
--
ALTER TABLE `student_grade_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_component` (`student_id`,`component_id`),
  ADD KEY `fk_gradedtl_component` (`component_id`);

--
-- Indexes for table `student_promotions`
--
ALTER TABLE `student_promotions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_promotion` (`student_id`),
  ADD KEY `idx_academic_year` (`from_academic_year_id`,`to_academic_year_id`);

--
-- Indexes for table `student_subject_enrollments`
--
ALTER TABLE `student_subject_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_subject_ay` (`student_id`,`subject_id`,`academic_year_id`),
  ADD KEY `idx_student_status` (`student_id`,`status`),
  ADD KEY `idx_subject_status` (`subject_id`,`status`),
  ADD KEY `idx_section_subject_status` (`section_id`,`subject_id`,`status`),
  ADD KEY `idx_academic_year` (`academic_year_id`),
  ADD KEY `idx_recorded_by` (`recorded_by`);

--
-- Indexes for table `student_term_enrollments`
--
ALTER TABLE `student_term_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_term` (`student_id`,`academic_year_id`,`semester`),
  ADD KEY `idx_student_ay` (`student_id`,`academic_year_id`),
  ADD KEY `idx_program_level` (`program_id`,`year_level_id`),
  ADD KEY `idx_recorded_by` (`recorded_by`);

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
-- Indexes for table `teacher_subject_assignments`
--
ALTER TABLE `teacher_subject_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`teacher_id`,`curriculum_subject_id`,`branch_id`,`academic_year_id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_subject` (`curriculum_subject_id`),
  ADD KEY `idx_branch` (`branch_id`),
  ADD KEY `idx_ay` (`academic_year_id`);

--
-- Indexes for table `tuition_discounts`
--
ALTER TABLE `tuition_discounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_discount_dates` (`start_date`,`end_date`),
  ADD KEY `idx_discount_active` (`is_active`),
  ADD KEY `idx_discount_ay` (`academic_year_id`);

--
-- Indexes for table `tuition_penalties`
--
ALTER TABLE `tuition_penalties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_penalty_start` (`start_date`),
  ADD KEY `idx_penalty_active` (`is_active`),
  ADD KEY `idx_penalty_ay` (`academic_year_id`);

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
-- Indexes for table `year_levels`
--
ALTER TABLE `year_levels`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `active_sessions`
--
ALTER TABLE `active_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12658;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1220;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `certificates_issued`
--
ALTER TABLE `certificates_issued`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `curriculum_subjects`
--
ALTER TABLE `curriculum_subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `grade_components`
--
ALTER TABLE `grade_components`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grade_locks`
--
ALTER TABLE `grade_locks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `grading_terms`
--
ALTER TABLE `grading_terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `learning_materials`
--
ALTER TABLE `learning_materials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=448;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `oauth_tokens`
--
ALTER TABLE `oauth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `program_courses`
--
ALTER TABLE `program_courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_tuition_fees`
--
ALTER TABLE `program_tuition_fees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `program_year_levels`
--
ALTER TABLE `program_year_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `resource_locks`
--
ALTER TABLE `resource_locks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `section_students`
--
ALTER TABLE `section_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=504;

--
-- AUTO_INCREMENT for table `security_settings`
--
ALTER TABLE `security_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `shs_grades`
--
ALTER TABLE `shs_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shs_grade_levels`
--
ALTER TABLE `shs_grade_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `shs_graduation_requirements`
--
ALTER TABLE `shs_graduation_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shs_strands`
--
ALTER TABLE `shs_strands`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `shs_tracks`
--
ALTER TABLE `shs_tracks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_completed_subjects`
--
ALTER TABLE `student_completed_subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_fees`
--
ALTER TABLE `student_fees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `student_grade_details`
--
ALTER TABLE `student_grade_details`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_promotions`
--
ALTER TABLE `student_promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `student_subject_enrollments`
--
ALTER TABLE `student_subject_enrollments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `student_term_enrollments`
--
ALTER TABLE `student_term_enrollments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- AUTO_INCREMENT for table `teacher_subject_assignments`
--
ALTER TABLE `teacher_subject_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tuition_discounts`
--
ALTER TABLE `tuition_discounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tuition_penalties`
--
ALTER TABLE `tuition_penalties`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=240;

--
-- AUTO_INCREMENT for table `year_levels`
--
ALTER TABLE `year_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
-- Constraints for table `certificates_issued`
--
ALTER TABLE `certificates_issued`
  ADD CONSTRAINT `certificates_issued_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificates_issued_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`);

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
  ADD CONSTRAINT `fk_payment_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `fk_program_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `program_courses`
--
ALTER TABLE `program_courses`
  ADD CONSTRAINT `fk_pc_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pc_yearlevel` FOREIGN KEY (`year_level_id`) REFERENCES `program_year_levels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD CONSTRAINT `fk_seclog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
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
