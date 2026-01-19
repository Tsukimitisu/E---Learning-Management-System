-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 19, 2026 at 05:55 PM
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
(1, '2025-2026', NULL, NULL, 1, 'current', '2026-01-19 15:13:43', '2026-01-19 15:14:32'),
(2, '2026-2027', '2026-07-19', '2027-04-19', 0, 'completed', '2026-01-19 15:14:12', '2026-01-19 15:14:32');

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

--
-- Dumping data for table `active_sessions`
--

INSERT INTO `active_sessions` (`id`, `session_id`, `user_id`, `ip_address`, `user_agent`, `last_activity`, `created_at`) VALUES
(13, 'm835hk9vjnmmfe1aa13l9qlt82', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-20 00:50:22', '2026-01-19 16:50:19');

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

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `section_id`, `subject_id`, `class_id`, `student_id`, `attendance_date`, `status`, `time_in`, `time_out`, `remarks`, `recorded_by`, `created_at`) VALUES
(1, NULL, NULL, 2, 203, '2026-01-17', 'present', NULL, NULL, '', 100, '2026-01-16 17:05:46');

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
(8, 203, 'User logged in - Student', '::1', NULL, '2026-01-16 13:26:56'),
(9, 203, 'User logged out', '::1', NULL, '2026-01-16 13:32:35'),
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
(24, 203, 'User logged in - Student', '::1', NULL, '2026-01-16 14:20:02'),
(25, 203, 'User logged out', '::1', NULL, '2026-01-16 14:21:16'),
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
(49, 203, 'User logged in - Student', '::1', NULL, '2026-01-17 01:11:51'),
(50, 203, 'User logged out', '::1', NULL, '2026-01-17 01:12:53'),
(51, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-17 01:13:00'),
(52, 100, 'User logged out', '::1', NULL, '2026-01-17 01:38:26'),
(53, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-17 01:38:31'),
(54, 205, 'User logged out', '::1', NULL, '2026-01-17 01:52:57'),
(55, 204, 'User logged in - School Admin', '::1', NULL, '2026-01-17 01:53:04'),
(56, 204, 'User logged out', '::1', NULL, '2026-01-17 02:15:13'),
(57, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-17 02:31:35'),
(58, 4, 'User logged out', '::1', NULL, '2026-01-17 02:31:56'),
(59, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-17 02:32:06'),
(60, 203, 'User logged in - Student', '::1', NULL, '2026-01-17 09:43:38'),
(61, 203, 'User logged out', '::1', NULL, '2026-01-17 09:43:58'),
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
(97, 203, 'User logged in - Student', '::1', NULL, '2026-01-18 16:03:17'),
(98, 203, 'User logged out', '::1', NULL, '2026-01-18 16:03:32'),
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
(187, 203, 'User logged in - Student', '::1', NULL, '2026-01-18 19:47:12'),
(188, 203, 'User logged out', '::1', NULL, '2026-01-18 20:07:38'),
(189, 210, 'User logged in - Branch Admin', '::1', NULL, '2026-01-18 20:07:50'),
(190, 210, 'User logged out', '::1', NULL, '2026-01-18 20:11:02'),
(191, 211, 'User logged in - Registrar', '::1', NULL, '2026-01-18 20:11:13'),
(192, 211, 'Generated enrollment certificate for Maria Garcia (2025-1001)', '::1', NULL, '2026-01-18 20:13:02'),
(193, 211, 'User logged out', '::1', NULL, '2026-01-18 20:13:32'),
(194, 203, 'User logged in - Student', '::1', NULL, '2026-01-18 20:13:39'),
(195, 203, 'User logged out', '::1', NULL, '2026-01-18 20:14:04'),
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
(225, 203, 'User logged in - Student', '::1', NULL, '2026-01-18 21:21:07'),
(226, 203, 'User logged out', '::1', NULL, '2026-01-18 21:22:04'),
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
(247, 203, 'User logged in - Student', '::1', NULL, '2026-01-19 02:48:55'),
(248, 203, 'User logged out', '::1', NULL, '2026-01-19 02:50:58'),
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
(291, 203, 'User logged in - Student', '::1', NULL, '2026-01-19 14:58:13'),
(292, 203, 'User logged out', '::1', NULL, '2026-01-19 14:59:33'),
(293, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 15:03:00'),
(294, 100, 'User logged out', '::1', NULL, '2026-01-19 15:04:52'),
(295, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 15:04:58'),
(296, 205, 'User logged out', '::1', NULL, '2026-01-19 15:06:35'),
(297, 203, 'User logged in - Student', '::1', NULL, '2026-01-19 15:06:42'),
(298, 203, 'User logged out', '::1', NULL, '2026-01-19 15:06:56'),
(299, 205, 'User logged in - Branch Admin', '::1', NULL, '2026-01-19 15:07:01'),
(300, 205, 'User logged out', '::1', NULL, '2026-01-19 15:07:09'),
(301, 100, 'User logged in - Teacher', '::1', NULL, '2026-01-19 15:07:14'),
(302, 100, 'User logged out', '::1', NULL, '2026-01-19 15:11:43'),
(303, 203, 'User logged in - Student', '::1', NULL, '2026-01-19 15:11:49'),
(304, 203, 'User logged out', '::1', NULL, '2026-01-19 15:12:14'),
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
(332, 4, 'User logged in - Super Admin', '::1', NULL, '2026-01-19 16:50:18');

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
(1, 200, 'enrollment', 'EC-20260119-0200', 'For Employment', '2025-2026', 2, 6, '2026-01-18 16:09:26'),
(3, 200, 'enrollment', 'EC-20260119-0200-4618', 'For Employment', '2025-2026', 2, 6, '2026-01-18 16:12:53'),
(4, 203, 'enrollment', 'EC-20260119-0203-5981', 'For Employment', '2025-2026', 1, 211, '2026-01-18 20:13:02');

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
(2, 'STEM-101', 'BIOLOGY', 3.0, 0, 0, 'shs_core', NULL, NULL, NULL, 1, 1, '', 1, 204, '2026-01-18 13:24:54', '2026-01-18 13:24:54'),
(3, 'BSIT-102', 'Introduction to Computing', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-01-18 13:29:09', '2026-01-18 13:29:09'),
(4, 'BSIT-101', 'Object Oriented Programming', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-01-18 17:22:24', '2026-01-18 17:22:24'),
(5, 'BSIT-103', 'Funcamentals of Programming', 3.0, 0, 0, 'college', 1, 1, NULL, NULL, 1, '', 1, 204, '2026-01-19 02:41:21', '2026-01-19 10:55:23');

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
(6, 'Jamesrev235@gmail.com', 'ELMS - Datamex - Password Reset Request', 'password_reset', 'sent', NULL, NULL, '2026-01-19 16:34:37');

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

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `class_id`, `status`, `payment_verified`, `payment_amount`, `created_at`) VALUES
(1, 201, 1, 'approved', 0, NULL, '2026-01-16 13:09:47'),
(2, 203, 1, 'approved', 0, NULL, '2026-01-16 13:26:44'),
(3, 203, 2, 'approved', 0, NULL, '2026-01-16 13:26:44'),
(5, 202, 1, 'approved', 0, NULL, '2026-01-16 17:39:48'),
(6, 200, 1, 'approved', 0, NULL, '2026-01-16 17:39:59'),
(8, 200, 4, 'approved', 0, NULL, '2026-01-18 17:09:28'),
(9, 203, 4, 'approved', 0, NULL, '2026-01-18 17:45:26');

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

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `student_id`, `section_id`, `subject_id`, `academic_year_id`, `semester`, `class_id`, `prelim`, `midterm`, `prefinal`, `final`, `final_grade`, `remarks`, `notes`, `version`) VALUES
(1, 203, NULL, NULL, NULL, NULL, 1, NULL, 89.00, NULL, 88.00, 88.40, '0', NULL, 1),
(2, 203, NULL, NULL, NULL, NULL, 4, NULL, 0.00, NULL, 0.00, 0.00, 'FAILED', NULL, 1),
(6, 203, 1, 4, NULL, NULL, NULL, NULL, 0.00, NULL, 0.00, 90.00, 'PASSED', '', 1),
(7, 201, 1, 4, NULL, NULL, NULL, NULL, 0.00, NULL, 0.00, 90.00, 'PASSED', '', 1);

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
(10, NULL, 4, NULL, 'materials/material_subj4_1768761931_696d2a4b39360.pptx', '2026-01-18 18:45:31', 100);

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
(11, 'admin@elms.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1, '2026-01-19 16:50:18');

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
(1, 221, 'ce748a374a217c0e8f95dbdccb2ddee9a2d974374005320adbb665220c9b4d7b', '2026-01-20 01:34:32', 0, '2026-01-19 16:34:32');

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
  `description` text DEFAULT NULL,
  `academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `semester` enum('1st','2nd','summer') DEFAULT NULL,
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

INSERT INTO `payments` (`id`, `reference_no`, `or_number`, `student_id`, `amount`, `payment_type`, `description`, `academic_year_id`, `semester`, `branch_id`, `recorded_by`, `payment_method`, `status`, `verified_by`, `verified_at`, `rejection_reason`, `proof_file`, `created_at`) VALUES
(1, 'PAY-20260119-1174', '2024-0127', 203, 4500.00, 'Enrollment', '', 1, '1st', 2, 211, '', 'verified', 211, '2026-01-19 04:31:55', NULL, NULL, '2026-01-18 20:31:55');

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
(3, 'BSIS', 'Bachelor of Science in Information Systems', 'Bachelor', 1, 1, '2026-01-16 13:28:17'),
(5, 'BSHM', 'Bachelor of Science in Hospitality Management', 'Bachelor', 2, 1, '2026-01-18 13:51:57');

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
(5, 2, 1, '1st Year', 2, 1, '2026-01-17 15:39:11'),
(6, 2, 2, '2nd Year', 2, 1, '2026-01-17 15:39:11'),
(7, 2, 3, '3rd Year', 2, 1, '2026-01-17 15:39:11'),
(8, 2, 4, '4th Year', 2, 1, '2026-01-17 15:39:11'),
(9, 3, 1, '1st Year', 2, 1, '2026-01-17 15:39:11'),
(10, 3, 2, '2nd Year', 2, 1, '2026-01-17 15:39:11'),
(11, 3, 3, '3rd Year', 2, 1, '2026-01-17 15:39:11'),
(12, 3, 4, '4th Year', 2, 1, '2026-01-17 15:39:11'),
(13, 5, 1, '1st Year', 2, 1, '2026-01-18 13:54:04');

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
(5, 'BSIT 1 C', 1, 1, NULL, NULL, '1st', 1, 1, 40, '', NULL, 1, '2026-01-19 02:40:11', '2026-01-19 02:40:11');

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
(1, 1, 203, '2026-01-18 21:19:57', ''),
(2, 2, 203, '2026-01-18 21:19:59', ''),
(3, 2, 200, '2026-01-19 02:42:32', 'active'),
(4, 5, 202, '2026-01-19 02:42:36', 'active'),
(5, 1, 201, '2026-01-19 02:42:38', 'active'),
(6, 2, 218, '2026-01-19 02:42:39', 'active');

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
(1, 'max_login_attempts', '5', 'Maximum failed login attempts before lockout', NULL, '2026-01-19 15:29:52'),
(2, 'lockout_duration', '15', 'Account lockout duration in minutes', NULL, '2026-01-19 15:29:52'),
(3, 'password_min_length', '8', 'Minimum password length', NULL, '2026-01-19 15:29:52'),
(4, 'password_require_uppercase', '1', 'Require uppercase letter in password', NULL, '2026-01-19 15:29:52'),
(5, 'password_require_lowercase', '1', 'Require lowercase letter in password', NULL, '2026-01-19 15:29:52'),
(6, 'password_require_number', '1', 'Require number in password', NULL, '2026-01-19 15:29:52'),
(7, 'password_require_special', '0', 'Require special character in password', NULL, '2026-01-19 15:29:52'),
(8, 'session_timeout', '60', 'Session timeout in minutes', NULL, '2026-01-19 15:29:52'),
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
(20, 'password_reset_expiry', '60', 'Password reset link expiry in minutes', NULL, '2026-01-19 15:29:52');

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
(5, 2, 'ICT', 'Information and Communications Technology', 'Technical skills in IT and programming', 1, '2026-01-17 15:39:11'),
(6, 2, 'HE', 'Home Economics', 'Culinary arts and hospitality management', 1, '2026-01-17 15:39:11'),
(7, 3, 'VA', 'Visual Arts', 'Creative expression through visual media', 1, '2026-01-17 15:39:11'),
(8, 4, 'SP', 'Sports', 'Athletic training and sports science', 1, '2026-01-17 15:39:11');

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
(1, 'Academic Track - STEM', 'STEM', 25.00, 50.00, 25.00, NULL, 1),
(2, 'Academic Track - ABM', 'ABM', 30.00, 50.00, 20.00, NULL, 1),
(3, 'Academic Track - HUMSS', 'HUMSS', 30.00, 50.00, 20.00, NULL, 1),
(4, 'TVL Track', 'TVL', 20.00, 60.00, 20.00, NULL, 1),
(5, 'Arts and Design Track', 'ARTS', 20.00, 60.00, 20.00, NULL, 1),
(6, 'Sports Track', 'SPORTS', 20.00, 60.00, 20.00, NULL, 1);

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
(203, '2025-1001', 1),
(218, '2026-0001', 1),
(221, '2026-0002', 1);

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
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_fees`
--

INSERT INTO `student_fees` (`id`, `student_id`, `fee_type`, `amount`, `academic_year_id`, `semester`, `description`, `due_date`, `created_by`, `created_at`) VALUES
(1, 203, 'Tuition Fee', 11600.00, 1, '1st', '', NULL, 211, '2026-01-18 20:31:19');

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
(2, 'maintenance_mode', '0', 'boolean', 'system', 'Enable Maintenance Mode', 4, '2026-01-18 19:06:38'),
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
(5, 100, 5, 1, 1, 1, '2026-01-19 14:50:35');

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
(4, 'admin@elms.com', '$2y$10$HT./ovUEHrcCRGbLzjSHquhagQeVxD9iK59//YEDUfntP5pn3o3m2', 'active', '2026-01-20 00:50:18', '2026-01-16 12:57:04'),
(6, 'registrar@elms.com', '$2y$10$emmb9dv7qdUCsPWfW0Ey4u3YLcA6h99ym0DrPa1dAo8n0bV0PUeSe', 'active', '2026-01-20 00:27:27', '2026-01-16 13:06:58'),
(100, 'teacher@elms.com', '$2y$10$gS8DWoSFQX9iUAZ4r2jCvucHbM0Swd7iGB.5uG1pxlBkiKSXZf22O', 'active', '2026-01-20 00:04:23', '2026-01-16 13:08:50'),
(200, 'student1@elms.com', '$2y$10$rTenfOlur5ca6J9a5kdMiO25ZBT7cavQ.WOutUYAp5rEryIG9epbG', 'active', NULL, '2026-01-16 13:08:50'),
(201, 'student2@elms.com', '$2y$10$rTenfOlur5ca6J9a5kdMiO25ZBT7cavQ.WOutUYAp5rEryIG9epbG', 'active', NULL, '2026-01-16 13:08:50'),
(202, 'student3@elms.com', '$2y$10$rTenfOlur5ca6J9a5kdMiO25ZBT7cavQ.WOutUYAp5rEryIG9epbG', 'active', NULL, '2026-01-16 13:08:50'),
(203, 'student@elms.com', '$2y$10$KFgcfrgq5cpjkBkheHuI1Owhqc054pQv/Ukbec8GTiCoiBGxgErxK', 'active', '2026-01-19 23:11:49', '2026-01-16 13:26:44'),
(204, 'schooladmin@elms.com', '$2y$10$QA38bQbDvhQwo/.BHioND.p1Y06Oy0rcHTXOC7i4FnhmwqLyVZGcu', 'active', '2026-01-19 23:53:08', '2026-01-16 13:32:27'),
(205, 'branchadmin@elms.com', '$2y$10$Bic2FhHZbHvu3AvS8601HO0UXxxyyvi01LGZh3iIW35AmKC8kFB0i', 'active', '2026-01-19 23:47:22', '2026-01-16 16:32:28'),
(210, 'sample@elms.com', '$2y$10$7Xri9SoPDxk2v/ybP78NduUbh8rspsEBnffz.OksDEdm4llPiRpWu', 'active', '2026-01-19 04:45:25', '2026-01-18 19:14:15'),
(211, 'rev@registrar.com', '$2y$10$GM/9k7ytk1UmmRMd2/FkgOgA9CdZN5RupGr5iCzCCv5FKhp0zy2Rq', 'active', '2026-01-19 18:06:01', '2026-01-18 20:10:51'),
(216, 'senpai@teacher.com', '$2y$10$zoyJYBXikgTZUlgVVbL/T.uYXKmVFeqctzpFPyyr8D1CBUDjok/TO', 'active', NULL, '2026-01-18 20:47:29'),
(218, 'test.student1768771580@example.com', '$2y$10$tMk2oQWTGcl1dPYTsCU3EOs5FwkTKqzhElcNwtEAIJan9Cit8yWOK', 'active', NULL, '2026-01-18 21:26:20'),
(221, 'Jamesrev235@gmail.com', '$2y$10$HQWA1riVpeoBxhGeBR3YRu9xq7/wjuqc5gAsl3symX2bAs4iWwQOK', 'active', NULL, '2026-01-19 16:28:15');

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
(100, 'Juan', 'Dela Cruz', NULL, NULL, NULL),
(200, 'Pedro', 'Garcia', NULL, NULL, 1),
(201, 'Ana', 'Reyes', NULL, NULL, 1),
(202, 'Jose', 'Martinez', NULL, NULL, 1),
(203, 'Maria', 'Garcia', '09181234567', 'Student Residence', 1),
(204, 'Academic', 'Dean', '09191234567', NULL, NULL),
(205, 'Branch', 'Coordinator', '09201234567', NULL, 1),
(210, 'James', 'Andrei Revilla', NULL, NULL, 2),
(211, 'James', 'Revs', '0906281723', NULL, 2),
(216, 'Senpai', 'James', NULL, '', 2),
(218, 'Test', 'Student', '09987654321', 'Test Address', 1),
(221, 'James', 'Revilla', '0906281723', 'Malabon City', 1);

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
(205, 3),
(210, 3),
(211, 4),
(216, 5),
(218, 6),
(221, 6);

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
-- Indexes for table `shs_grade_levels`
--
ALTER TABLE `shs_grade_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_strand_grade` (`strand_id`,`grade_level`),
  ADD KEY `fk_gradelevel_strand` (`strand_id`);

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
  ADD KEY `idx_student_no` (`student_no`),
  ADD KEY `idx_course` (`course_id`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `active_sessions`
--
ALTER TABLE `active_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=333;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `certificates_issued`
--
ALTER TABLE `certificates_issued`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
-- AUTO_INCREMENT for table `grading_terms`
--
ALTER TABLE `grading_terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `learning_materials`
--
ALTER TABLE `learning_materials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `oauth_tokens`
--
ALTER TABLE `oauth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `program_courses`
--
ALTER TABLE `program_courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_year_levels`
--
ALTER TABLE `program_year_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `section_students`
--
ALTER TABLE `section_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_settings`
--
ALTER TABLE `security_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `shs_grade_levels`
--
ALTER TABLE `shs_grade_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `shs_strands`
--
ALTER TABLE `shs_strands`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `shs_tracks`
--
ALTER TABLE `shs_tracks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_fees`
--
ALTER TABLE `student_fees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- AUTO_INCREMENT for table `teacher_subject_assignments`
--
ALTER TABLE `teacher_subject_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=222;

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
