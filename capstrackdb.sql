-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 18, 2026 at 02:23 AM
-- Server version: 8.4.7
-- PHP Version: 8.4.15

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
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `capstone_experts`
--

DROP TABLE IF EXISTS `capstone_experts`;
CREATE TABLE IF NOT EXISTS `capstone_experts` (
  `expert_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credentials` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `max_teams` smallint UNSIGNED NOT NULL DEFAULT '4',
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `approved_status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`expert_id`),
  UNIQUE KEY `capstone_experts_email_unique` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `capstone_experts`
--

INSERT INTO `capstone_experts` (`expert_id`, `email`, `first_name`, `last_name`, `credentials`, `profile_picture`, `is_admin`, `max_teams`, `department`, `rejection_reason`, `approved_status`, `created_at`) VALUES
(5, 'hellscreamgrom9@gmail.com', 'Grom', 'Hellscream', 'MIT', NULL, 1, 4, NULL, NULL, 1, '2026-06-15 22:49:24');

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
-- Table structure for table `expert_expertise`
--

DROP TABLE IF EXISTS `expert_expertise`;
CREATE TABLE IF NOT EXISTS `expert_expertise` (
  `expertise_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `expert_id` bigint UNSIGNED NOT NULL,
  `expertise_field` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`expertise_id`),
  KEY `expert_expertise_expert_id_foreign` (`expert_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expert_expertise`
--

INSERT INTO `expert_expertise` (`expertise_id`, `expert_id`, `expertise_field`, `created_at`, `updated_at`) VALUES
(9, 5, 'Mobile Apps', '2026-06-15 06:49:24', '2026-06-15 06:49:24'),
(8, 5, 'Web Development', '2026-06-15 06:49:24', '2026-06-15 06:49:24');

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
(5, 2);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
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
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(42, '2026_06_12_033757_create_admins_table', 20);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recruitment`
--

DROP TABLE IF EXISTS `recruitment`;
CREATE TABLE IF NOT EXISTS `recruitment` (
  `recruitment_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint UNSIGNED NOT NULL,
  `recruitment_goal` enum('build_a_team','find_a_team') COLLATE utf8mb4_unicode_ci NOT NULL,
  `looking_for` text COLLATE utf8mb4_unicode_ci,
  `introduction` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `roles` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`recruitment_id`),
  KEY `recruitment_student_id_foreign` (`student_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recruitment`
--

INSERT INTO `recruitment` (`recruitment_id`, `student_id`, `recruitment_goal`, `looking_for`, `introduction`, `description`, `roles`, `created_at`, `updated_at`) VALUES
(7, 8, 'build_a_team', 'Reliable', NULL, NULL, '[\"Backend Dev\"]', '2026-06-12 05:29:07', '2026-06-12 05:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
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
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` text COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `l_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `p_picture` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`student_id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `email`, `f_name`, `l_name`, `p_picture`) VALUES
(8, 'mabalilla@my.cspc.edu.ph', 'Mark Anthony', 'Balilla', 'avatar-07'),
(7, 'joaurillas@my.cspc.edu.ph', 'John Kenneth', 'Aurillas', 'avatar-08');

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
  `program` enum('BSIT','BSCS','BLIS','BSIS') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_status` enum('Failed','Passed','Active') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  PRIMARY KEY (`s_y_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_s_y`
--

INSERT INTO `student_s_y` (`s_y_id`, `student_id`, `team_id`, `s_role_id`, `school_year`, `program`, `student_status`) VALUES
(7, 8, 16, NULL, NULL, 'BSCS', 'Active'),
(6, 7, NULL, NULL, NULL, 'BSCS', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `team`
--

DROP TABLE IF EXISTS `team`;
CREATE TABLE IF NOT EXISTS `team` (
  `team_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_title` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `team_status` enum('Active','Full','Need Members','Done') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team`
--

INSERT INTO `team` (`team_id`, `team_name`, `project_title`, `team_status`, `created_at`) VALUES
(16, 'Bulbasaur', 'sample title', 'Active', '2026-06-12 12:53:36');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
