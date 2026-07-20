-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 04, 2026 at 02:13 AM
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
  `email` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credentials` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `reject_attempts` int UNSIGNED NOT NULL DEFAULT '0',
  `reject_cooldown_until` datetime DEFAULT NULL,
  `max_teams` smallint UNSIGNED NOT NULL DEFAULT '4',
  `specialization` json DEFAULT NULL,
  `expertise` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_requests_email_unique` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_requests`
--

INSERT INTO `account_requests` (`id`, `email`, `first_name`, `last_name`, `middle_name`, `credentials`, `department`, `status`, `rejection_reason`, `reject_attempts`, `reject_cooldown_until`, `max_teams`, `specialization`, `expertise`, `created_at`, `updated_at`) VALUES
(2, 'kennaurillas5@gmail.com', 'Kenn', 'Aurillas', NULL, NULL, 'BSIT - Information Technology', 'approved', NULL, 0, NULL, 4, '[\"adviser\"]', '[\"Technical Writing\"]', '2026-06-26 02:54:01', '2026-06-26 21:24:37'),
(6, 'dongaurillas@gmail.com', 'Dong', 'DIng', NULL, NULL, 'BSIT - Information Technology', 'approved', NULL, 1, NULL, 4, '[\"adviser\", \"consultant\"]', '[\"Cybersecurity\", \"Technical Writing\"]', '2026-06-28 21:27:39', '2026-06-28 21:28:00');

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint UNSIGNED NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_log_student_id_index` (`student_id`),
  KEY `activity_log_created_at_index` (`created_at`)
) ENGINE=MyISAM AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `student_id`, `type`, `description`, `metadata`, `created_at`) VALUES
(1, 19, 'team_created', 'Created team <strong>mewtwo</strong>', '\"{\\\"team_id\\\":36,\\\"team_name\\\":\\\"mewtwo\\\"}\"', '2026-06-29 03:03:24'),
(2, 17, 'team_created', 'Created team <strong>bulbasaur</strong>', '\"{\\\"team_id\\\":34,\\\"team_name\\\":\\\"bulbasaur\\\"}\"', '2026-06-20 15:47:53'),
(3, 17, 'team_joined', 'Joined team <strong>bulbasaur</strong>', '\"{\\\"team_id\\\":34,\\\"team_name\\\":\\\"bulbasaur\\\"}\"', '2026-06-20 07:49:16'),
(4, 16, 'team_joined', 'Joined team <strong>bulbasaur</strong>', '\"{\\\"team_id\\\":34,\\\"team_name\\\":\\\"bulbasaur\\\"}\"', '2026-06-20 07:50:50'),
(5, 16, 'team_joined', 'Joined team <strong>bulbasaur</strong>', '\"{\\\"team_id\\\":34,\\\"team_name\\\":\\\"bulbasaur\\\"}\"', '2026-06-20 19:34:30'),
(6, 16, 'team_joined', 'Joined team <strong>mewtwo</strong>', '\"{\\\"team_id\\\":36,\\\"team_name\\\":\\\"mewtwo\\\"}\"', '2026-06-28 19:11:09'),
(7, 19, 'team_joined', 'Joined team <strong>mewtwo</strong>', '\"{\\\"team_id\\\":36,\\\"team_name\\\":\\\"mewtwo\\\"}\"', '2026-06-28 22:25:27'),
(8, 18, 'member_removed', 'Removed <strong>Jolina Rabusa</strong> from the team', '{\"team_id\": 36, \"removed_student_id\": 19}', '2026-06-28 22:41:23'),
(9, 19, 'team_joined', 'Joined team <strong>mewtwo</strong>', '{\"team_id\": 36, \"team_name\": \"mewtwo\"}', '2026-06-28 22:42:17'),
(10, 18, 'request_accepted', 'Accepted <strong>Jolina Rabusa</strong>\'s request to join the team', '{\"team_id\": 36, \"joined_student_id\": 19}', '2026-06-28 22:42:17'),
(11, 18, 'member_removed', 'Removed <strong>Jolina Rabusa</strong> from the team', '{\"team_id\": 36, \"removed_student_id\": 19}', '2026-06-28 23:02:58'),
(12, 19, 'request_sent', 'Requested to join a team', '{\"team_id\": \"36\"}', '2026-06-28 23:03:15'),
(13, 19, 'team_joined', 'Joined team <strong>mewtwo</strong>', '{\"team_id\": 36, \"team_name\": \"mewtwo\"}', '2026-06-28 23:03:46'),
(14, 18, 'request_accepted', 'Accepted <strong>Jolina Rabusa</strong>\'s request to join the team', '{\"team_id\": 36, \"joined_student_id\": 19}', '2026-06-28 23:03:46'),
(15, 18, 'leadership_transferred', 'Transferred leadership to <strong>Jolina Rabusa</strong>', '{\"team_id\": 36, \"new_leader_id\": 19}', '2026-06-28 23:05:40'),
(16, 2, 'request_sent', 'Requested to join a team', '{\"team_id\": \"2\"}', '2026-07-01 03:18:27'),
(17, 1, 'team_left', 'Left team <strong>bulbasaur</strong>', '{\"team_id\": 1}', '2026-07-01 03:37:59'),
(18, 1, 'post_created', 'Created a recruitment post to build team <strong>mewtwo</strong>', '{\"goal\": \"build_a_team\", \"team_name\": \"mewtwo\", \"recruitment_id\": null}', '2026-07-01 03:38:23'),
(19, 2, 'request_sent', 'Requested to join a team', '{\"team_id\": \"2\"}', '2026-07-01 03:38:45'),
(20, 2, 'team_joined', 'Joined team <strong>mewtwo</strong>', '{\"team_id\": 2, \"team_name\": \"mewtwo\"}', '2026-07-01 03:39:10'),
(21, 1, 'request_accepted', 'Accepted <strong>Ian Cyrus Tumambing</strong>\'s request to join the team', '{\"team_id\": 2, \"joined_student_id\": 2}', '2026-07-01 03:39:10'),
(22, 1, 'member_removed', 'Removed <strong>Ian Cyrus Tumambing</strong> from the team', '{\"team_id\": 2, \"removed_student_id\": 2}', '2026-07-01 03:39:20'),
(23, 2, 'request_sent', 'Requested to join a team', '{\"team_id\": \"2\"}', '2026-07-01 03:39:47'),
(24, 2, 'team_joined', 'Joined team <strong>mewtwo</strong>', '{\"team_id\": 2, \"team_name\": \"mewtwo\"}', '2026-07-01 03:40:12'),
(25, 1, 'request_accepted', 'Accepted <strong>Ian Cyrus Tumambing</strong>\'s request to join the team', '{\"team_id\": 2, \"joined_student_id\": 2}', '2026-07-01 03:40:12'),
(26, 1, 'leadership_transferred', 'Transferred leadership to <strong>Ian Cyrus Tumambing</strong>', '{\"team_id\": 2, \"new_leader_id\": 2}', '2026-07-01 03:40:23'),
(27, 2, 'leadership_transferred', 'Transferred leadership to <strong>Jolina Rabusa</strong>', '{\"team_id\": 2, \"new_leader_id\": 1}', '2026-07-01 03:46:35'),
(28, 1, 'leadership_transferred', 'Transferred leadership to <strong>Ian Cyrus Tumambing</strong>', '{\"team_id\": 2, \"new_leader_id\": 2}', '2026-07-01 04:36:10'),
(29, 2, 'leadership_transferred', 'Transferred leadership to <strong>Jolina Rabusa</strong>', '{\"team_id\": 2, \"new_leader_id\": 1}', '2026-07-01 04:57:28'),
(30, 1, 'team_confirmed', 'Confirmed team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:01:13'),
(31, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:34:55'),
(32, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:35:01'),
(33, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:35:14'),
(34, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:35:29'),
(35, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:39:04'),
(36, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:39:12'),
(37, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:42:48'),
(38, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:47:53'),
(39, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:48:00'),
(40, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:48:10'),
(41, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 05:52:36'),
(42, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 06:01:51'),
(43, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-01 06:03:46'),
(44, 1, 'team_updated', 'Updated team <strong>mewtwo</strong>', '{\"team_id\": 2}', '2026-07-02 11:40:11'),
(45, 3, 'request_sent', 'Requested to join a team', '{\"team_id\": \"2\"}', '2026-07-02 11:40:39'),
(46, 3, 'team_joined', 'Joined team <strong>mewtwo</strong>', '{\"team_id\": 2, \"team_name\": \"mewtwo\"}', '2026-07-02 11:42:06'),
(47, 1, 'request_accepted', 'Accepted <strong>John Kenneth Aurillas</strong>\'s request to join the team', '{\"team_id\": 2, \"joined_student_id\": 3}', '2026-07-02 11:42:06');

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
  `archived_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` int UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abstract` text COLLATE utf8mb4_unicode_ci,
  `embedding` json DEFAULT NULL,
  `embedding_generated_at` timestamp NULL DEFAULT NULL,
  `academic_year` year NOT NULL,
  `program` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint DEFAULT NULL,
  PRIMARY KEY (`archived_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_doc_tags`
--

DROP TABLE IF EXISTS `archived_doc_tags`;
CREATE TABLE IF NOT EXISTS `archived_doc_tags` (
  `tag_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `archive_id` int UNSIGNED NOT NULL,
  `tag_id` int UNSIGNED NOT NULL
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
  `email` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credentials` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `approved_status` tinyint(1) NOT NULL DEFAULT '0',
  `max_teams` smallint UNSIGNED NOT NULL DEFAULT '4',
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `reject_attempts` int UNSIGNED NOT NULL DEFAULT '0',
  `reject_cooldown_until` datetime DEFAULT NULL,
  `google_token` text COLLATE utf8mb4_unicode_ci,
  `google_token_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`expert_id`),
  UNIQUE KEY `capstone_experts_email_unique` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `capstone_experts`
--

INSERT INTO `capstone_experts` (`expert_id`, `email`, `first_name`, `last_name`, `middle_name`, `credentials`, `profile_picture`, `is_admin`, `approved_status`, `max_teams`, `department`, `rejection_reason`, `reject_attempts`, `reject_cooldown_until`, `google_token`, `google_token_expires_at`, `created_at`) VALUES
(2, 'zzekrommm@gmail.com', 'Zzek', 'rommm', NULL, 'MIT', NULL, 1, 1, 4, 'BSIT - Information Technology', NULL, 1, NULL, '{\"access_token\":\"ya29.a0AT3oNZ-Wgz7VBFCtkNZb1FlbaD5vQQZzK0sc_3nIIPbAerCHEdw8efIdG2ASf8XyjhR63cHfxaocI2xMtZ6NE8XkrV0nL_6OBMF-a_-ymWZHqfrQg5eQQKbR3Zcrh5w6kSzMlxSKX7-WTH2llsTmIMZLN50-lh4vedx_Pb4iYEqX2CYbftLOcW5ZxHi6L56-pHm1Ar8aCgYKAX0SARMSFQHGX2MixXZJKWI8y1sSXkQ3vOaMTA0206\",\"refresh_token\":\"1\\/\\/0eKT8fa41NHDoCgYIARAAGA4SNwF-L9Irqglx4HUbqWHBpMLIewAYQomX2cZEqgOfztlvh3BGvqrq5znN2YAfAUf3cov6FHSafDk\",\"token_type\":\"Bearer\"}', '2026-07-01 15:35:38', '2026-07-01 12:52:16'),
(3, 'capstrack649@gmail.com', 'Capstrack', 'Capstrack', NULL, NULL, NULL, 0, 1, 4, 'BSIT - Information Technology', NULL, 1, NULL, '{\"access_token\":\"ya29.a0AT3oNZ_PdwEHBxxYyt1j9H1SPLeipUzmnWbQ4OuhstmgqWpjjRyXsiu_irCoSx0u9vU7W6WXBb5Eubou3w2R6J2Lz3QfawSJhBvS1nA0jKhTfUgAdPo-kruRi15lM36vEnckhAKbhwktvxq2z8fSZwBGJoRBGHzfYjOaCh_u7eH6sovfkqf50GRNAXJ1vmC7e3TYmtcaCgYKAVgSARUSFQHGX2MiyW0-P-ScIV6o8YsMkFc_AQ0206\",\"refresh_token\":\"1\\/\\/0eJ_3isE-I7riCgYIARAAGA4SNwF-L9IrhC234dso4tWU7BIuaHeJMYhpNNcFqY0E0ekHjneksK9LWIugRbgJHpRTqUTwCr4T1XU\",\"token_type\":\"Bearer\"}', '2026-07-01 15:16:44', '2026-07-01 14:15:38');

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
  `specific_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `consultation_limit` int DEFAULT NULL,
  `slot_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT NULL,
  `recur` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `expertise_field` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`expertise_id`),
  KEY `expert_expertise_expert_id_foreign` (`expert_id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expert_expertise`
--

INSERT INTO `expert_expertise` (`expertise_id`, `expert_id`, `expertise_field`, `created_at`, `updated_at`) VALUES
(1, 1, 'Database Systems', '2026-07-01 04:47:38', '2026-07-01 04:47:38'),
(2, 1, 'UI/UX Design', '2026-07-01 04:47:38', '2026-07-01 04:47:38'),
(3, 1, 'Technical Writing', '2026-07-01 04:47:38', '2026-07-01 04:47:38'),
(4, 1, 'Research Methods', '2026-07-01 04:47:38', '2026-07-01 04:47:38'),
(5, 2, 'Database Systems', '2026-07-01 04:52:16', '2026-07-01 04:52:16'),
(6, 2, 'Software Engineering', '2026-07-01 04:52:16', '2026-07-01 04:52:16'),
(7, 3, 'UI/UX Design', '2026-07-01 06:15:38', '2026-07-01 06:15:38'),
(8, 3, 'Research Methods', '2026-07-01 06:15:38', '2026-07-01 06:15:38');

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
  `expense_post_id` int unsigned DEFAULT NULL,
  `receipt_image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_record_status` enum('verified','pending','rejected') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `payment_for` enum('TD','POD','FOD') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_posts`
--

DROP TABLE IF EXISTS `expense_posts`;
CREATE TABLE IF NOT EXISTS `expense_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint unsigned DEFAULT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BSIT',
  `year_level` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '3rd',
  `amount` decimal(10,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
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
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(56, '2026_06_04_160000_add_course_to_users_table', 31),
(57, '2026_06_04_170000_add_name_fields_to_users_table', 31),
(58, '2026_06_04_180000_add_middle_name_to_users_table', 31),
(74, '2026_06_06_000001_create_students_table', 32),
(75, '2026_06_06_000002_create_student_s_y_table', 32),
(76, '2026_06_09_124125_create_recruitment_table', 32),
(30, '2026_06_11_125952_drop_target_size_from_recruitment_table', 12),
(31, '2026_06_11_130907_change_roles_to_json_in_recruitment_table', 13),
(32, '2026_06_11_131752_update_recruitment_table_schema', 14),
(77, '2026_06_09_135811_create_team_table', 32),
(78, '2026_06_09_144530_add_foreign_keys_to_student_s_y_table', 32),
(79, '2026_06_11_132756_add_optional_fields_to_recruitment_table', 32),
(80, '2026_06_15_125408_drop_users_table', 32),
(81, '2026_06_15_130344_create_capstone_experts_table', 32),
(39, '2026_06_15_141047_add_credentials_to_capstone_experts_table', 18),
(40, '2026_06_16_100635_add_approved_status_to_capstone_experts_table', 19),
(41, '2026_06_16_103039_add_max_teams_to_capstone_experts_table', 19),
(42, '2026_06_12_033757_create_admins_table', 20),
(82, '2026_06_15_130344_create_expert_expertise_table', 32),
(83, '2026_06_15_130344_create_expert_roles_table', 32),
(46, '2026_06_25_000001_add_reject_cooldown_to_capstone_experts_table', 23),
(47, '2026_06_26_063452_create_account_requests_table', 24),
(48, '2026_06_26_063550_add_specialization_and_expertise_to_account_requests', 24),
(84, '2026_06_15_130344_create_roles_table', 32),
(50, '2026_06_29_030919_add_member_count_to_recruitment_table', 25),
(51, '2026_06_29_055414_normalize_year_section_format', 26),
(52, '2026_06_29_061347_add_leader_and_confirmed_to_team_table', 27),
(53, '2026_06_29_063700_create_activity_log_table', 28),
(54, '2026_06_29_063930_backfill_activity_log', 29),
(55, '2026_06_29_064951_create_notifications_table', 30),
(85, '2026_06_18_110150_create_archived_tables', 32),
(86, '2026_06_20_045829_add_student_number_to_students_table', 32),
(87, '2026_06_20_080526_create_team_invitations_table', 32),
(88, '2026_06_22_124253_add_embedding_to_archived_documents', 32),
(89, '2026_06_28_050710_add_specific_date_to_consultation_schedule', 33),
(90, '2026_06_28_054244_add_recur_to_consultation_schedule_table', 33),
(91, '2026_06_28_060000_add_slot_type_to_consultation_schedule_table', 33),
(92, '2026_06_28_034354_create_consultation_schedule_table', 33),
(93, '2026_07_01_112734_add_missing_columns_to_recruitment_and_team_tables', 34),
(94, '2026_07_01_131744_update_team_status_values_in_team_table', 35),
(95, '2026_07_06_131850_create_expense_posts_table', 39),
(96, '2026_07_06_131917_add_expense_post_id_to_financial_records_table', 39);

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
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint UNSIGNED NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notifications_student_id_is_read_index` (`student_id`,`is_read`),
  KEY `notifications_created_at_index` (`created_at`)
) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `student_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 19, 'member_removed', 'Removed from Team', 'You have been removed from the team <strong>mewtwo</strong>.', 'http://127.0.0.1:8000/student/team', 0, '2026-06-29 07:02:58'),
(2, 18, 'request_received', 'Join Request', 'Ian Cyrus Tumambing wants to join your team.', 'http://127.0.0.1:8000/student/team', 0, '2026-06-29 07:03:15'),
(3, 19, 'request_accepted', 'Request Accepted', 'Your request to join <strong>mewtwo</strong> has been accepted.', 'http://127.0.0.1:8000/student/team', 0, '2026-06-29 07:03:46'),
(4, 18, 'member_joined', 'New Team Member', 'Jolina Rabusa joined the team.', 'http://127.0.0.1:8000/student/team', 0, '2026-06-29 07:03:46'),
(5, 16, 'member_joined', 'New Team Member', 'Jolina Rabusa joined the team.', 'http://127.0.0.1:8000/student/team', 0, '2026-06-29 07:03:46'),
(6, 19, 'leadership_transferred', 'Leadership Transferred', 'You are now the team leader of <strong>mewtwo</strong>.', 'http://127.0.0.1:8000/student/team', 0, '2026-06-29 07:05:40'),
(7, 1, 'request_received', 'Join Request', 'Jolina Rabusa wants to join your team.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 03:18:27'),
(8, 1, 'request_received', 'Join Request', 'Jolina Rabusa wants to join your team.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 03:38:45'),
(9, 2, 'request_accepted', 'Request Accepted', 'Your request to join <strong>mewtwo</strong> has been accepted.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 03:39:10'),
(10, 1, 'member_joined', 'New Team Member', 'Ian Cyrus Tumambing joined the team.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 03:39:10'),
(11, 2, 'member_removed', 'Removed from Team', 'You have been removed from the team <strong>mewtwo</strong>.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 03:39:20'),
(12, 1, 'request_received', 'Join Request', 'Jolina Rabusa wants to join your team.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 03:39:47'),
(13, 2, 'request_accepted', 'Request Accepted', 'Your request to join <strong>mewtwo</strong> has been accepted.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 03:40:12'),
(14, 1, 'member_joined', 'New Team Member', 'Ian Cyrus Tumambing joined the team.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 03:40:12'),
(15, 2, 'leadership_transferred', 'Leadership Transferred', 'You are now the team leader of <strong>mewtwo</strong>.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 03:40:23'),
(16, 1, 'leadership_transferred', 'Leadership Transferred', 'You are now the team leader of <strong>mewtwo</strong>.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 03:46:35'),
(17, 2, 'leadership_transferred', 'Leadership Transferred', 'You are now the team leader of <strong>mewtwo</strong>.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 04:36:10'),
(18, 1, 'leadership_transferred', 'Leadership Transferred', 'You are now the team leader of <strong>mewtwo</strong>.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 04:57:28'),
(19, 2, 'team_confirmed', 'Team Confirmed', 'Your team <strong>mewtwo</strong> has been confirmed!', 'http://127.0.0.1:8000/student/team', 0, '2026-07-01 05:01:13'),
(20, 1, 'request_received', 'Join Request', 'Jolina Rabusa wants to join your team.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-02 11:40:39'),
(21, 3, 'request_accepted', 'Request Accepted', 'Your request to join <strong>mewtwo</strong> has been accepted.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-02 11:42:06'),
(22, 1, 'member_joined', 'New Team Member', 'John Kenneth Aurillas joined the team.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-02 11:42:06'),
(23, 2, 'member_joined', 'New Team Member', 'John Kenneth Aurillas joined the team.', 'http://127.0.0.1:8000/student/team', 0, '2026-07-02 11:42:06');

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
  `recruitment_goal` enum('build_a_team','find_a_team') COLLATE utf8mb4_unicode_ci NOT NULL,
  `looking_for` text COLLATE utf8mb4_unicode_ci,
  `introduction` text COLLATE utf8mb4_unicode_ci,
  `roles` json DEFAULT NULL,
  `member_count` tinyint UNSIGNED DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`recruitment_id`),
  KEY `recruitment_student_id_foreign` (`student_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recruitment`
--

INSERT INTO `recruitment` (`recruitment_id`, `student_id`, `recruitment_goal`, `looking_for`, `introduction`, `roles`, `member_count`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 'build_a_team', 'sample text', NULL, '[\"UI Designer\", \"App Developer\", \"Backend Dev\"]', 5, NULL, '2026-07-01 03:38:23', '2026-07-01 04:57:28');

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
  `role_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `m_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `l_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `p_picture` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `students_student_number_unique` (`student_number`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `email`, `student_number`, `f_name`, `m_name`, `l_name`, `p_picture`, `course`, `year_section`) VALUES
(1, 'jorabusa@my.cspc.edu.ph', '221001464', 'Jolina', NULL, 'Rabusa', 'avatar-06', 'Bachelor of Science in Information Technology', '3rd · B'),
(2, 'iatumambing@my.cspc.edu.ph', '231003376', 'Ian Cyrus', NULL, 'Tumambing', 'avatar-02', 'Bachelor of Science in Information Technology', '3rd · B'),
(3, 'joaurillas@my.cspc.edu.ph', '221001135', 'John Kenneth', NULL, 'Aurillas', 'avatar-07', 'Bachelor of Science in Information Technology', '3rd · B');

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
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_s_y`
--

INSERT INTO `student_s_y` (`s_y_id`, `student_id`, `team_id`, `s_role_id`, `school_year`, `program`, `student_status`) VALUES
(1, 1, 2, NULL, NULL, 'BSIT', 'Active'),
(2, 2, 2, NULL, NULL, 'BSIT', 'Active'),
(3, 3, 2, NULL, NULL, 'BSIT', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `team`
--

DROP TABLE IF EXISTS `team`;
CREATE TABLE IF NOT EXISTS `team` (
  `team_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `leader_id` bigint UNSIGNED DEFAULT NULL,
  `team_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_title` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `team_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'on-going',
  `is_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team`
--

INSERT INTO `team` (`team_id`, `leader_id`, `team_name`, `project_title`, `team_status`, `is_confirmed`, `created_at`) VALUES
(2, 1, 'mewtwo', NULL, 'on-going', 1, '2026-07-01 03:38:23');

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
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_invitations`
--

INSERT INTO `team_invitations` (`id`, `sender_id`, `receiver_id`, `team_id`, `type`, `status`, `created_at`, `updated_at`) VALUES
(2, 2, 1, 2, 'request', 'accepted', '2026-07-01 03:39:47', '2026-07-01 03:40:12'),
(3, 3, 1, 2, 'request', 'accepted', '2026-07-02 11:40:39', '2026-07-02 11:42:06');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
