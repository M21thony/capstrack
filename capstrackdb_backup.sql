-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 25, 2026 at 02:27 AM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `capstrackdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_requests`
--

DROP TABLE IF EXISTS `account_requests`;
CREATE TABLE IF NOT EXISTS `account_requests` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
CREATE TABLE IF NOT EXISTS `admins` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `firstname` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastname` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `phone_number` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_picture` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_email_unique` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_documents`
--

DROP TABLE IF EXISTS `archived_documents`;
CREATE TABLE IF NOT EXISTS `archived_documents` (
  `archived_id` int NOT NULL AUTO_INCREMENT,
  `team_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abstract` text COLLATE utf8mb4_unicode_ci,
  `academic_year` year NOT NULL,
  `program` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint DEFAULT NULL,
  PRIMARY KEY (`archived_id`),
  KEY `team_id` (`team_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_doc_tags`
--

DROP TABLE IF EXISTS `archived_doc_tags`;
CREATE TABLE IF NOT EXISTS `archived_doc_tags` (
  `tag_id` int NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tag_color` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`tag_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_tag_relation`
--

DROP TABLE IF EXISTS `archived_tag_relation`;
CREATE TABLE IF NOT EXISTS `archived_tag_relation` (
  `archive_id` int NOT NULL,
  `tag_id` int NOT NULL,
  KEY `archive_id` (`archive_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `capstone_documents`
--

DROP TABLE IF EXISTS `capstone_documents`;
CREATE TABLE IF NOT EXISTS `capstone_documents` (
  `file_id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `file_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_version` int DEFAULT '1',
  `document_status` enum('Under Review','Pending','Approved','Needs Revision') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`file_id`),
  KEY `student_id` (`student_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `capstone_document_review`
--

DROP TABLE IF EXISTS `capstone_document_review`;
CREATE TABLE IF NOT EXISTS `capstone_document_review` (
  `review_id` int NOT NULL AUTO_INCREMENT,
  `file_id` int NOT NULL,
  `expert_id` int NOT NULL,
  `review_status` enum('Reviewed','To Review') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`review_id`),
  KEY `file_id` (`file_id`),
  KEY `expert_id` (`expert_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `capstone_expert`
--

DROP TABLE IF EXISTS `capstone_expert`;
CREATE TABLE IF NOT EXISTS `capstone_expert` (
  `expert_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `credentials` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_teams` int DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `specialization` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expertise` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`expert_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `capstone_expert`
--

INSERT INTO `capstone_expert` (`expert_id`, `email`, `first_name`, `last_name`, `middle_name`, `profile_picture`, `is_admin`, `created_at`, `status`, `rejection_reason`, `credentials`, `department`, `max_teams`, `bio`, `specialization`, `expertise`) VALUES
(1, 'admin.capstrack@cspc.edu.ph', 'Capstone', 'Admin', NULL, NULL, 1, '2026-06-14 21:22:25', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '[\"A\",\"B\"]'),
(2, 'instructor.test@cspc.edu.ph', 'Test', 'Instructor', NULL, NULL, 0, '2026-06-14 22:13:56', 'approved', NULL, NULL, 'Information Technology', 4, 'sample text', 'consultant', '[\"Web Development\",\"Mobile Apps\"]');

-- --------------------------------------------------------

--
-- Table structure for table `capstone_experts`
--

DROP TABLE IF EXISTS `capstone_experts`;
CREATE TABLE IF NOT EXISTS `capstone_experts` (
  `expert_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `credentials` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_picture` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `max_teams` smallint UNSIGNED NOT NULL DEFAULT '4',
  `department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`expert_id`),
  UNIQUE KEY `capstone_experts_email_unique` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `capstone_experts`
--

-- Ensure approved_status is consistent with is_admin.
-- Rule: if is_admin = 1 then approved_status must be 1.
INSERT INTO `capstone_experts` (`expert_id`, `email`, `first_name`, `last_name`, `credentials`, `profile_picture`, `is_admin`, `max_teams`, `department`, `rejection_reason`, `approved_status`, `created_at`) VALUES
(5, 'hellscreamgrom9@gmail.com', 'Grom', 'Hellscream', 'MIT', NULL, 1, 4, NULL, NULL, 1, '2026-06-15 22:49:24'),
(20, 'zzekrommm@gmail.com', 'Zzek', 'rommm', 'MIT', NULL, 1, 5, 'information technology', NULL, 1, '2026-06-25 10:18:58'),
(13, 'kennaurillas08@gmail.com', 'Anonymous', 'Zzek', 'MIT', NULL, 0, 2, 'information technology', NULL, 1, '2026-06-22 20:52:24'),
(18, 'dongaurillas@gmail.com', 'Dong', 'DIng', NULL, NULL, 0, 2, 'computer science', NULL, 1, '2026-06-22 22:17:25');

-- If there are existing rows where is_admin=1 but approved_status!=1,
-- fix them (without changing schema).
UPDATE `capstone_experts`
SET `approved_status` = 1
WHERE `is_admin` = 1 AND (`approved_status` IS NULL OR `approved_status` <> 1);


-- --------------------------------------------------------

--
-- Table structure for table `capstone_expert_application`
--

DROP TABLE IF EXISTS `capstone_expert_application`;
CREATE TABLE IF NOT EXISTS `capstone_expert_application` (
  `application_id` int NOT NULL AUTO_INCREMENT,
  `team_id` int NOT NULL,
  `expert_id` int NOT NULL,
  `applicatin_status` enum('pending','accepted','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `message` text COLLATE utf8mb4_unicode_ci,
  `date_applied` datetime DEFAULT CURRENT_TIMESTAMP,
  `responded_at` datetime DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`application_id`),
  KEY `team_id` (`team_id`),
  KEY `expert_id` (`expert_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `capstone_progress`
--

DROP TABLE IF EXISTS `capstone_progress`;
CREATE TABLE IF NOT EXISTS `capstone_progress` (
  `progress_id` int NOT NULL AUTO_INCREMENT,
  `team_id` int NOT NULL,
  `milestone_id` int NOT NULL,
  `completion_status` enum('Active','Done','Overdue','Queued') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL,
  PRIMARY KEY (`progress_id`),
  KEY `team_id` (`team_id`),
  KEY `milestone_id` (`milestone_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `capstone_projects`
--

DROP TABLE IF EXISTS `capstone_projects`;
CREATE TABLE IF NOT EXISTS `capstone_projects` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultation_booking`
--

DROP TABLE IF EXISTS `consultation_booking`;
CREATE TABLE IF NOT EXISTS `consultation_booking` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `team_id` int NOT NULL,
  `schedule_id` int NOT NULL,
  `booking_status` enum('scheduled','done','rescheduled','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`booking_id`),
  KEY `student_id` (`student_id`),
  KEY `team_id` (`team_id`),
  KEY `schedule_id` (`schedule_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultation_schedule`
--

DROP TABLE IF EXISTS `consultation_schedule`;
CREATE TABLE IF NOT EXISTS `consultation_schedule` (
  `schedule_id` int NOT NULL AUTO_INCREMENT,
  `expert_id` int NOT NULL,
  `available_date` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `consultation_limit` int DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`schedule_id`),
  KEY `expert_id` (`expert_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `defense_panels`
--

DROP TABLE IF EXISTS `defense_panels`;
CREATE TABLE IF NOT EXISTS `defense_panels` (
  `defense_panel_id` int NOT NULL AUTO_INCREMENT,
  `defense_id` int NOT NULL,
  `expert_id` int NOT NULL,
  `panel_role` enum('Panel Chair','Panel Member 1','Panel Member 2') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`defense_panel_id`),
  KEY `defense_id` (`defense_id`),
  KEY `expert_id` (`expert_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `defense_schedule`
--

DROP TABLE IF EXISTS `defense_schedule`;
CREATE TABLE IF NOT EXISTS `defense_schedule` (
  `defense_id` int NOT NULL AUTO_INCREMENT,
  `team_id` int NOT NULL,
  `defense_type` enum('Pre-proposal Defense','Title Defense','Pre Oral Defense','Final Oral Defense') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schedule_date` datetime DEFAULT NULL,
  `venue` enum('Mac Lab','Open Lab') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`defense_id`),
  KEY `team_id` (`team_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expert_expertise`
--

DROP TABLE IF EXISTS `expert_expertise`;
CREATE TABLE IF NOT EXISTS `expert_expertise` (
  `expertise_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `expert_id` bigint UNSIGNED NOT NULL,
  `expertise_field` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`expertise_id`),
  KEY `expert_expertise_expert_id_foreign` (`expert_id`)
) ENGINE=MyISAM AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expert_expertise`
--

INSERT INTO `expert_expertise` (`expertise_id`, `expert_id`, `expertise_field`, `created_at`, `updated_at`) VALUES
(9, 5, 'Mobile Apps', '2026-06-15 06:49:24', '2026-06-15 06:49:24'),
(8, 5, 'Web Development', '2026-06-15 06:49:24', '2026-06-15 06:49:24'),
(10, 6, 'Machine Learning', '2026-06-19 20:12:21', '2026-06-19 20:12:21'),
(11, 6, 'Web Development', '2026-06-19 20:12:21', '2026-06-19 20:12:21'),
(12, 6, 'Technical Writing', '2026-06-19 20:12:21', '2026-06-19 20:12:21'),
(13, 7, 'Machine Learning', '2026-06-19 20:19:49', '2026-06-19 20:19:49'),
(14, 7, 'Web Development', '2026-06-19 20:19:49', '2026-06-19 20:19:49'),
(15, 7, 'Database Systems', '2026-06-19 20:19:49', '2026-06-19 20:19:49'),
(16, 8, 'Mobile Apps', '2026-06-21 19:04:07', '2026-06-21 19:04:07'),
(17, 8, 'Data Analytics', '2026-06-21 19:04:07', '2026-06-21 19:04:07'),
(18, 8, 'Database Systems', '2026-06-21 19:04:07', '2026-06-21 19:04:07'),
(19, 8, 'UI/UX Design', '2026-06-21 19:04:07', '2026-06-21 19:04:07'),
(20, 9, 'UI/UX Design', '2026-06-21 20:00:57', '2026-06-21 20:00:57'),
(21, 9, 'Research Methods', '2026-06-21 20:00:57', '2026-06-21 20:00:57'),
(22, 10, 'UI/UX Design', '2026-06-21 21:02:42', '2026-06-21 21:02:42'),
(23, 10, 'Research Methods', '2026-06-21 21:02:42', '2026-06-21 21:02:42'),
(24, 11, 'Mobile Apps', '2026-06-22 02:50:40', '2026-06-22 02:50:40'),
(25, 11, 'Database Systems', '2026-06-22 02:50:40', '2026-06-22 02:50:40'),
(26, 12, 'Software Engineering', '2026-06-22 02:55:04', '2026-06-22 02:55:04'),
(27, 12, 'Research Methods', '2026-06-22 02:55:05', '2026-06-22 02:55:05'),
(28, 13, 'Network Systems', '2026-06-22 04:52:24', '2026-06-22 04:52:24'),
(29, 13, 'Grammar & Style', '2026-06-22 04:52:24', '2026-06-22 04:52:24'),
(30, 14, 'Mobile Apps', '2026-06-22 04:55:09', '2026-06-22 04:55:09'),
(31, 14, 'Data Analytics', '2026-06-22 04:55:09', '2026-06-22 04:55:09'),
(32, 15, 'Database Systems', '2026-06-22 05:20:13', '2026-06-22 05:20:13'),
(33, 15, 'UI/UX Design', '2026-06-22 05:20:13', '2026-06-22 05:20:13'),
(34, 16, 'Database Systems', '2026-06-22 05:37:05', '2026-06-22 05:37:05'),
(35, 16, 'Software Engineering', '2026-06-22 05:37:05', '2026-06-22 05:37:05'),
(36, 17, 'Database Systems', '2026-06-22 06:06:43', '2026-06-22 06:06:43'),
(37, 17, 'Software Engineering', '2026-06-22 06:06:43', '2026-06-22 06:06:43'),
(38, 18, 'Web Development', '2026-06-22 06:17:25', '2026-06-22 06:17:25'),
(39, 18, 'Mobile Apps', '2026-06-22 06:17:25', '2026-06-22 06:17:25'),
(40, 18, 'Data Analytics', '2026-06-22 06:17:25', '2026-06-22 06:17:25'),
(41, 19, 'Web Development', '2026-06-24 18:15:41', '2026-06-24 18:15:41'),
(42, 19, 'Database Systems', '2026-06-24 18:15:41', '2026-06-24 18:15:41'),
(43, 19, 'UI/UX Design', '2026-06-24 18:15:41', '2026-06-24 18:15:41'),
(44, 20, 'Software Engineering', '2026-06-24 18:18:58', '2026-06-24 18:18:58'),
(45, 20, 'Research Methods', '2026-06-24 18:18:58', '2026-06-24 18:18:58');

-- --------------------------------------------------------

--
-- Table structure for table `expert_roles`
--

DROP TABLE IF EXISTS `expert_roles`;
CREATE TABLE IF NOT EXISTS `expert_roles` (
  `expert_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`expert_id`,`role_id`),
  KEY `expert_roles_role_id_foreign` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expert_roles`
--

INSERT INTO `expert_roles` (`expert_id`, `role_id`) VALUES
(5, 1),
(5, 2),
(6, 1),
(6, 2),
(7, 1),
(8, 3),
(9, 2),
(9, 3),
(10, 2),
(10, 3),
(11, 4),
(12, 3),
(13, 3),
(14, 2),
(15, 4),
(16, 3),
(17, 2),
(18, 2),
(19, 2),
(19, 3),
(20, 1);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `financial_records`
--

DROP TABLE IF EXISTS `financial_records`;
CREATE TABLE IF NOT EXISTS `financial_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `receipt_image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_record_status` enum('verified','pending') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `payment_for` enum('TD','POD','FOD') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grading_rubrics`
--

DROP TABLE IF EXISTS `grading_rubrics`;
CREATE TABLE IF NOT EXISTS `grading_rubrics` (
  `rubric_id` int NOT NULL AUTO_INCREMENT,
  `rubric_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rubric_type` enum('Pre-Proposal Defense','Title Defense','Pre Oral Defense','Final Oral Defense') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `total_points` decimal(6,2) DEFAULT '100.00',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rubric_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(5, '0001_01_01_000001_create_cache_table', 2),
(6, '2026_06_05_133508_create_students_table', 2),
(7, '2026_06_06_024402_create_sessions_table', 2),
(9, '2026_06_06_000003_add_missing_columns_to_students_table', 4),
(19, '2026_06_04_150000_add_profile_fields_to_users_table', 7),
(11, '2026_06_01_012751_add_role_to_users_table', 6),
(12, '2026_06_01_012752_create_account_requests_table', 6),
(13, '2026_06_01_012753_create_capstone_projects_table', 6),
(14, '2026_06_01_012754_create_project_groups_table', 6),
(15, '2026_06_01_012755_create_project_members_table', 6),
(16, '2026_06_01_014140_create_project_members_table_v2', 6),
(17, '2026_06_03_000001_add_login_fields_to_users_table', 6),
(18, '2026_06_03_000002_create_account_requests_table', 6),
(20, '2026_06_04_160000_add_course_to_users_table', 7),
(21, '2026_06_04_170000_add_name_fields_to_users_table', 7),
(22, '2026_06_04_180000_add_middle_name_to_users_table', 7),
(26, '2026_06_06_000002_create_student_s_y_table', 8),
(25, '2026_06_06_000001_create_students_table', 8),
(27, '2026_06_09_124125_create_recruitment_table', 9),
(28, '2026_06_09_135811_create_team_table', 10),
(29, '2026_06_09_144530_add_foreign_keys_to_student_s_y_table', 11),
(30, '2026_06_11_125952_drop_target_size_from_recruitment_table', 12),
(31, '2026_06_11_130907_change_roles_to_json_in_recruitment_table', 13),
(32, '2026_06_11_131752_update_recruitment_table_schema', 14),
(33, '2026_06_11_132756_add_optional_fields_to_recruitment_table', 15),
(34, '2026_06_15_125408_drop_users_table', 16),
(35, '2026_06_15_130344_create_capstone_experts_table', 17),
(36, '2026_06_15_130344_create_expert_expertise_table', 17),
(37, '2026_06_15_130344_create_expert_roles_table', 17),
(38, '2026_06_15_130344_create_roles_table', 17),
(39, '2026_06_15_141047_add_credentials_to_capstone_experts_table', 18),
(40, '2026_06_16_100635_add_approved_status_to_capstone_experts_table', 19),
(41, '2026_06_16_103039_add_max_teams_to_capstone_experts_table', 19),
(42, '2026_06_12_033757_create_admins_table', 20),
(43, '2026_06_18_110150_create_archived_tables', 21),
(44, '2026_06_20_045829_add_student_number_to_students_table', 21),
(45, '2026_06_20_080526_create_team_invitations_table', 22);

-- --------------------------------------------------------

--
-- Table structure for table `milestone`
--

DROP TABLE IF EXISTS `milestone`;
CREATE TABLE IF NOT EXISTS `milestone` (
  `milestone_id` int NOT NULL AUTO_INCREMENT,
  `milestone_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `milestone_description` text COLLATE utf8mb4_unicode_ci,
  `milestone_order` int DEFAULT NULL,
  PRIMARY KEY (`milestone_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pdf_remarks`
--

DROP TABLE IF EXISTS `pdf_remarks`;
CREATE TABLE IF NOT EXISTS `pdf_remarks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `review_id` int NOT NULL,
  `expert_id` int NOT NULL,
  `page_number` int DEFAULT NULL,
  `top_position` float DEFAULT NULL,
  `left_position` float DEFAULT NULL,
  `highlight_width` float DEFAULT NULL,
  `highlight_height` float DEFAULT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_id` (`review_id`),
  KEY `expert_id` (`expert_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_groups`
--

DROP TABLE IF EXISTS `project_groups`;
CREATE TABLE IF NOT EXISTS `project_groups` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_members`
--

DROP TABLE IF EXISTS `project_members`;
CREATE TABLE IF NOT EXISTS `project_members` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_members_table_v2`
--

DROP TABLE IF EXISTS `project_members_table_v2`;
CREATE TABLE IF NOT EXISTS `project_members_table_v2` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recruitment`
--

DROP TABLE IF EXISTS `recruitment`;
CREATE TABLE IF NOT EXISTS `recruitment` (
  `recruitment_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint UNSIGNED NOT NULL,
  `recruitment_goal` enum('build_a_team','find_a_team') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `looking_for` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `introduction` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `roles` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`recruitment_id`),
  KEY `recruitment_student_id_foreign` (`student_id`)
) ENGINE=MyISAM AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recruitment`
--

INSERT INTO `recruitment` (`recruitment_id`, `student_id`, `recruitment_goal`, `looking_for`, `introduction`, `description`, `roles`, `created_at`, `updated_at`) VALUES
(7, 8, 'build_a_team', 'Reliable', NULL, NULL, '[\"Backend Dev\"]', '2026-06-12 05:29:07', '2026-06-12 05:29:07'),
(15, 9, 'build_a_team', 'sample text', NULL, NULL, '[\"App Developer\", \"Backend Dev\"]', '2026-06-20 05:09:37', '2026-06-20 05:09:37'),
(27, 17, 'build_a_team', NULL, NULL, NULL, '[]', '2026-06-20 07:50:10', '2026-06-20 07:50:10');

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

DROP TABLE IF EXISTS `resources`;
CREATE TABLE IF NOT EXISTS `resources` (
  `resource_id` int NOT NULL AUTO_INCREMENT,
  `resource_type` enum('template','form','lesson') COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_for` enum('BSIT','BSCS','BLIS','BSIS') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `file_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_type` enum('file','link') COLLATE utf8mb4_unicode_ci DEFAULT 'file',
  `external_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_downloadable` tinyint(1) DEFAULT '1',
  `uploaded_at` datetime DEFAULT NULL,
  `uploaded_by` int NOT NULL,
  PRIMARY KEY (`resource_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'capstone_adviser', 'Serves as CaPSA/Capstone Project Subject Adviser or Thesis Subject Adviser.', '2026-06-15 06:19:58', '2026-06-15 06:19:58'),
(2, 'adviser', 'Provides academic guidance and supervision for capstone projects.', '2026-06-15 06:19:58', '2026-06-15 06:19:58'),
(3, 'consultant', 'Provides subject-matter expertise and technical advice.', '2026-06-15 06:19:58', '2026-06-15 06:19:58'),
(4, 'grammarian', 'Reviews and corrects documentation and grammar.', '2026-06-15 06:19:58', '2026-06-15 06:19:58');

-- --------------------------------------------------------

--
-- Table structure for table `rubric_criteria`
--

DROP TABLE IF EXISTS `rubric_criteria`;
CREATE TABLE IF NOT EXISTS `rubric_criteria` (
  `criterion_id` int NOT NULL AUTO_INCREMENT,
  `rubric_id` int NOT NULL,
  `criterion_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `max_points` decimal(6,2) NOT NULL,
  `score_levels` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`criterion_id`),
  KEY `rubric_id` (`rubric_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('Ju9kTTuorLJBD1RC9JRKmKn1yrSUtFU20SC8AHIq', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJHSnZEVGt0bTJPU0UxbGt6Rld2UlhKSDlXYU9aSmJZdmFFUFhyNU1OIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxfQ==', 1780721364);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `m_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `l_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `p_picture` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `students_student_number_unique` (`student_number`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `email`, `student_number`, `f_name`, `m_name`, `l_name`, `p_picture`, `course`, `year_section`) VALUES
(16, 'joaurillas@my.cspc.edu.ph', '221001135', 'John Kenneth', 'Butron', 'Aurillas', 'avatar-03', 'Bachelor of Science in Information Technology', '3rd · B'),
(17, 'kaaurillas@my.cspc.edu.ph', '231003376', 'Kate Angel', 'Butron', 'Aurillas', 'avatar-04', 'Bachelor of Science in Information Technology', '3rd · B');

-- --------------------------------------------------------

--
-- Table structure for table `student_roles`
--

DROP TABLE IF EXISTS `student_roles`;
CREATE TABLE IF NOT EXISTS `student_roles` (
  `s_role_id` int NOT NULL AUTO_INCREMENT,
  `s_role_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_role_description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`s_role_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_roles`
--

INSERT INTO `student_roles` (`s_role_id`, `s_role_name`, `s_role_description`) VALUES
(1, 'Developer', 'Software developer in team'),
(2, 'Analyst', 'System analyst'),
(3, 'Tester', 'Quality assurance tester');

-- --------------------------------------------------------

--
-- Table structure for table `student_s_y`
--

DROP TABLE IF EXISTS `student_s_y`;
CREATE TABLE IF NOT EXISTS `student_s_y` (
  `s_y_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint UNSIGNED NOT NULL,
  `team_id` bigint UNSIGNED DEFAULT NULL,
  `s_role_id` int DEFAULT NULL,
  `school_year` year DEFAULT NULL,
  `program` enum('BSIT','BSCS','BLIS','BSIS') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_status` enum('Failed','Passed','Active') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  PRIMARY KEY (`s_y_id`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_s_y`
--

INSERT INTO `student_s_y` (`s_y_id`, `student_id`, `team_id`, `s_role_id`, `school_year`, `program`, `student_status`) VALUES
(16, 17, 34, NULL, NULL, 'BSIT', 'Active'),
(15, 16, 34, NULL, NULL, 'BSIT', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `team`
--

DROP TABLE IF EXISTS `team`;
CREATE TABLE IF NOT EXISTS `team` (
  `team_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `team_status` enum('Active','Full','Need Members','Done') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`)
) ENGINE=MyISAM AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team`
--

INSERT INTO `team` (`team_id`, `team_name`, `project_title`, `team_status`, `created_at`) VALUES
(34, 'bulbasaur', NULL, 'Active', '2026-06-20 15:47:53');

-- --------------------------------------------------------

--
-- Table structure for table `team_grades`
--

DROP TABLE IF EXISTS `team_grades`;
CREATE TABLE IF NOT EXISTS `team_grades` (
  `grade_id` int NOT NULL AUTO_INCREMENT,
  `team_id` int NOT NULL,
  `defense_id` int NOT NULL,
  `rubric_id` int NOT NULL,
  `expert_id` int NOT NULL,
  `total_score` decimal(6,2) DEFAULT '0.00',
  `total_possible` decimal(6,2) DEFAULT '0.00',
  `percentage` decimal(5,2) DEFAULT '0.00',
  `overall_remarks` text COLLATE utf8mb4_unicode_ci,
  `criterion_scores` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`grade_id`),
  KEY `team_id` (`team_id`),
  KEY `defense_id` (`defense_id`),
  KEY `rubric_id` (`rubric_id`),
  KEY `expert_id` (`expert_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_invitations`
--

DROP TABLE IF EXISTS `team_invitations`;
CREATE TABLE IF NOT EXISTS `team_invitations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` bigint UNSIGNED NOT NULL,
  `receiver_id` bigint UNSIGNED NOT NULL,
  `team_id` bigint UNSIGNED DEFAULT NULL,
  `type` enum('invite','request') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','accepted','declined') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_invitations_sender_id_foreign` (`sender_id`),
  KEY `team_invitations_receiver_id_foreign` (`receiver_id`),
  KEY `team_invitations_team_id_foreign` (`team_id`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_invitations`
--

INSERT INTO `team_invitations` (`id`, `sender_id`, `receiver_id`, `team_id`, `type`, `status`, `created_at`, `updated_at`) VALUES
(1, 9, 8, NULL, 'request', 'pending', '2026-06-20 00:17:38', '2026-06-20 00:17:38'),
(2, 12, 9, 27, 'request', 'accepted', '2026-06-20 05:10:20', '2026-06-20 05:21:54'),
(3, 9, 12, 27, 'invite', 'accepted', '2026-06-20 05:30:07', '2026-06-20 05:30:42'),
(4, 9, 12, 27, 'invite', 'accepted', '2026-06-20 05:37:20', '2026-06-20 05:37:51'),
(10, 15, 13, 33, 'invite', 'accepted', '2026-06-20 07:23:45', '2026-06-20 07:24:27'),
(9, 15, 13, 33, 'request', 'accepted', '2026-06-20 07:17:18', '2026-06-20 07:17:43'),
(11, 17, 16, 34, 'request', 'accepted', '2026-06-20 07:48:44', '2026-06-20 07:49:16'),
(12, 16, 17, 34, 'request', 'accepted', '2026-06-20 07:50:18', '2026-06-20 07:50:50'),
(13, 16, 17, 34, 'request', 'accepted', '2026-06-20 19:34:14', '2026-06-20 19:34:30');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `login_success` tinyint(1) NOT NULL DEFAULT '0',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `avatar` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_section` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialization` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credentials` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expertise` text COLLATE utf8mb4_unicode_ci,
  `department` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_teams` int DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
