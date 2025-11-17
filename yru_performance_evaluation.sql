-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 08:56 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `yru_evaluation`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-07 06:39:19'),
(2, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-07 06:41:06'),
(3, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-07 07:29:31'),
(4, 1, 'login', NULL, NULL, NULL, NULL, '10.40.11.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-08 13:20:45'),
(5, 1, 'toggle_status', 'personnel_types', 2, NULL, NULL, '10.40.11.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-08 13:35:44'),
(6, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 02:59:06'),
(7, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 03:49:34'),
(8, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 03:52:02'),
(9, 1, 'create', 'personnel_types', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 03:53:07'),
(10, 1, 'create', 'personnel_types', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 03:55:09'),
(11, 1, 'toggle_status', 'personnel_types', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 03:59:33'),
(12, 1, 'update', 'personnel_types', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:05:39'),
(13, 1, 'update', 'personnel_types', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:06:45'),
(14, 1, 'update', 'personnel_types', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:09:55'),
(15, 1, 'toggle_status', 'personnel_types', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:09:59'),
(16, 1, 'toggle_status', 'personnel_types', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:10:01'),
(17, 1, 'delete', 'personnel_types', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:10:05'),
(18, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:15:05'),
(19, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:17:41'),
(20, 1, 'update', 'users', 1, NULL, '{\"email\":\"admin@yru.ac.th\",\"role\":\"admin\",\"is_active\":\"1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:33:46'),
(21, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:33:48'),
(22, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:33:53'),
(23, 1, 'create', 'users', 2, NULL, '{\"username\":\"user\",\"email\":\"test@test.com\",\"role\":\"staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 04:35:31'),
(24, 1, 'delete', 'users', 2, NULL, '{\"username\":\"user\",\"email\":\"test@test.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 04:36:40'),
(25, 1, 'create', 'users', 3, NULL, '{\"username\":\"user\",\"email\":\"test@test.com\",\"role\":\"staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 04:37:00'),
(26, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 04:42:34'),
(27, 3, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 04:42:37'),
(28, 3, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 04:42:48'),
(29, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 04:42:52'),
(30, 1, 'update', 'users', 3, NULL, '{\"email\":\"test@test.com\",\"role\":\"manager\",\"is_active\":\"1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 04:57:53'),
(31, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 04:57:54'),
(32, 3, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 04:57:59'),
(33, 3, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:19:44'),
(34, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:19:48'),
(35, 1, 'update', 'users', 3, NULL, '{\"email\":\"test@test.com\",\"role\":\"admin\",\"is_active\":\"1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:20:00'),
(36, 1, 'update', 'users', 3, NULL, '{\"email\":\"test@test.com\",\"role\":\"staff\",\"is_active\":\"1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:20:10'),
(37, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:20:11'),
(38, 3, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:20:14'),
(39, 3, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:20:22'),
(40, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:20:26'),
(41, 1, 'update', 'users', 3, NULL, '{\"email\":\"test@test.com\",\"role\":\"staff\",\"is_active\":\"1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:23:34'),
(42, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:23:35'),
(43, 3, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:23:38'),
(44, 3, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:24:48'),
(45, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:24:52'),
(46, 1, 'create', 'users', 4, NULL, '{\"username\":\"user1\",\"email\":\"user@user.com\",\"role\":\"manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:26:11'),
(47, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:26:13'),
(48, 4, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:26:18'),
(49, 4, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:26:24'),
(50, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:26:29'),
(51, 1, 'update', 'evaluation_aspects', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:26:54'),
(52, 1, 'update', 'evaluation_aspects', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:27:02'),
(53, 1, 'update', 'evaluation_aspects', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:27:10'),
(54, 1, 'create', 'evaluation_topics', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:27:50'),
(55, 1, 'create', 'evaluation_topics', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:28:33'),
(56, 1, 'delete', 'evaluation_topics', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:28:40'),
(57, 1, 'create', 'evaluation_topics', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:29:05'),
(58, 1, 'create', 'evaluation_topics', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:29:48'),
(59, 1, 'delete', 'evaluation_topics', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:29:51'),
(60, 1, 'delete', 'evaluation_topics', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:29:53'),
(61, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:29:58'),
(62, 3, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:30:01'),
(63, 3, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:32:20'),
(64, 4, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:32:26'),
(65, 4, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:32:37'),
(66, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:32:48'),
(67, 1, 'delete', 'evaluation_aspects', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:33:57'),
(68, 1, 'delete', 'personnel_types', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:34:36'),
(69, 1, 'create', 'evaluation_periods', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:35:28'),
(70, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:35:31'),
(71, 3, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:35:36'),
(72, 3, 'create', 'evaluations', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:36:02'),
(73, 3, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:45:22'),
(74, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:45:35'),
(75, 1, 'update', 'evaluation_aspects', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:46:15'),
(76, 1, 'delete', 'evaluation_aspects', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:46:23'),
(77, 1, 'update', 'evaluation_aspects', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:46:42'),
(78, 1, 'update', 'evaluation_aspects', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:46:50'),
(79, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:46:55'),
(80, 3, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:46:59'),
(81, 3, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:47:46'),
(82, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:47:51'),
(83, 1, 'delete', 'evaluation_aspects', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:48:37'),
(84, 1, 'delete', 'evaluation_aspects', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:48:41'),
(85, 1, 'delete', 'personnel_types', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:48:46'),
(86, 1, 'delete', 'personnel_types', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 06:48:48'),
(87, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:07:09'),
(88, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:07:14'),
(89, 1, 'update', 'evaluation_periods', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:08:00'),
(90, 1, 'update', 'evaluation_periods', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:08:13'),
(91, 1, 'update', 'evaluation_periods', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:08:18'),
(92, 1, 'update', 'evaluation_aspects', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:09:15'),
(93, 1, 'update', 'evaluation_aspects', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:09:18'),
(94, 1, 'update', 'evaluation_aspects', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:09:21'),
(95, 1, 'update', 'evaluation_aspects', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:09:24'),
(96, 1, 'update', 'evaluation_aspects', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:09:27'),
(97, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:09:39'),
(98, 6, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:09:42'),
(99, 6, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:26:10'),
(100, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:26:14'),
(101, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:26:33'),
(102, 6, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:27:01'),
(103, 6, 'create', 'evaluations', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:27:16'),
(104, 1, 'login', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-17 07:50:51'),
(105, 1, 'logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 07:54:37');

-- --------------------------------------------------------

--
-- Table structure for table `approval_history`
--

CREATE TABLE `approval_history` (
  `history_id` int(11) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `manager_user_id` int(11) NOT NULL,
  `action` enum('submit','return','approve','reject') NOT NULL,
  `comment` text DEFAULT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `approval_history`
--

INSERT INTO `approval_history` (`history_id`, `evaluation_id`, `manager_user_id`, `action`, `comment`, `previous_status`, `new_status`, `created_at`) VALUES
(1, 1, 4, 'submit', 'ส่งแบบประเมินเพื่อพิจารณา', 'draft', 'submitted', '2025-11-17 07:06:49'),
(2, 1, 2, 'approve', 'อนุมัติแบบประเมิน ผลการปฏิบัติงานอยู่ในเกณฑ์ดี', 'submitted', 'approved', '2025-11-17 07:06:49'),
(3, 2, 5, 'submit', 'ส่งแบบประเมินเพื่อพิจารณา', 'draft', 'submitted', '2025-11-17 07:06:49');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_code` varchar(20) NOT NULL,
  `department_name_th` varchar(255) NOT NULL,
  `department_name_en` varchar(255) DEFAULT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `head_user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_code`, `department_name_th`, `department_name_en`, `faculty_id`, `head_user_id`, `is_active`, `created_at`) VALUES
(1, 'EDUC', 'คณะครุศาสตร์', 'Faculty of Education', NULL, NULL, 1, '2025-11-17 07:06:49'),
(2, 'SCI', 'คณะวิทยาศาสตร์และเทคโนโลยี', 'Faculty of Science and Technology', NULL, NULL, 1, '2025-11-17 07:06:49'),
(3, 'HUM', 'คณะมนุษยศาสตร์และสังคมศาสตร์', 'Faculty of Humanities and Social Sciences', NULL, NULL, 1, '2025-11-17 07:06:49'),
(4, 'MGMT', 'คณะบริหารธุรกิจและการจัดการ', 'Faculty of Business Administration and Management', NULL, NULL, 1, '2025-11-17 07:06:49'),
(5, 'ADMIN', 'สำนักงานอธิการบดี', 'Office of the President', NULL, NULL, 1, '2025-11-17 07:06:49');

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `evaluation_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `personnel_type_id` int(11) DEFAULT NULL,
  `status` enum('draft','submitted','under_review','approved','rejected','returned') DEFAULT 'draft',
  `total_score` decimal(10,2) DEFAULT 0.00,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`evaluation_id`, `period_id`, `user_id`, `personnel_type_id`, `status`, `total_score`, `submitted_at`, `reviewed_at`, `reviewed_by`, `approved_at`, `approved_by`, `rejected_by`, `rejected_at`, `created_at`, `updated_at`) VALUES
(1, 2, 4, 1, 'approved', 85.50, '2025-04-10 14:30:00', '2025-04-12 10:15:00', 2, '2025-04-12 16:20:00', 2, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(2, 1, 5, 1, 'submitted', 88.00, '2025-11-10 09:45:00', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(3, 1, 6, 1, 'draft', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(4, 3, 6, 1, 'draft', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-17 07:27:16', '2025-11-17 07:27:16');

--
-- Triggers `evaluations`
--
DELIMITER $$
CREATE TRIGGER `tr_evaluation_status_change` AFTER UPDATE ON `evaluations` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address)
        VALUES (NEW.reviewed_by, 'status_change', 'evaluations', NEW.evaluation_id, OLD.status, NEW.status, 'system');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_aspects`
--

CREATE TABLE `evaluation_aspects` (
  `aspect_id` int(11) NOT NULL,
  `personnel_type_id` int(11) DEFAULT NULL,
  `aspect_code` varchar(20) NOT NULL,
  `aspect_name_th` varchar(255) NOT NULL,
  `aspect_name_en` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `max_score` decimal(10,2) DEFAULT 100.00,
  `weight_percentage` decimal(5,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_aspects`
--

INSERT INTO `evaluation_aspects` (`aspect_id`, `personnel_type_id`, `aspect_code`, `aspect_name_th`, `aspect_name_en`, `description`, `max_score`, `weight_percentage`, `sort_order`, `display_order`, `is_active`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'TEACHING', 'การสอน', 'Teaching', 'ด้านการจัดการเรียนการสอน', 100.00, 40.00, 1, 1, 1, NULL, 1, '2025-11-17 07:06:49', '2025-11-17 07:09:15'),
(2, 1, 'RESEARCH', 'การวิจัย', 'Research', 'ด้านการวิจัยและพัฒนา', 100.00, 30.00, 2, 2, 1, NULL, 1, '2025-11-17 07:06:49', '2025-11-17 07:09:18'),
(3, 1, 'SERVICE', 'การบริการวิชาการ', 'Academic Service', 'ด้านการบริการวิชาการแก่สังคม', 100.00, 15.00, 3, 3, 1, NULL, 1, '2025-11-17 07:06:49', '2025-11-17 07:09:21'),
(4, 1, 'CULTURE', 'การทำนุบำรุงศิลปวัฒนธรรม', 'Cultural Preservation', 'ด้านการทำนุบำรุงศิลปวัฒนธรรม', 100.00, 10.00, 4, 4, 1, NULL, 1, '2025-11-17 07:06:49', '2025-11-17 07:09:24'),
(5, 1, 'MANAGEMENT', 'การบริหาร', 'Management', 'ด้านการบริหารจัดการ', 100.00, 5.00, 5, 5, 1, NULL, 1, '2025-11-17 07:06:49', '2025-11-17 07:09:27');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_details`
--

CREATE TABLE `evaluation_details` (
  `detail_id` int(11) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `aspect_id` int(11) NOT NULL,
  `topic_id` int(11) DEFAULT NULL,
  `score` decimal(10,2) DEFAULT 0.00,
  `self_assessment` text DEFAULT NULL,
  `evidence_description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_details`
--

INSERT INTO `evaluation_details` (`detail_id`, `evaluation_id`, `aspect_id`, `topic_id`, `score`, `self_assessment`, `evidence_description`, `notes`, `created_at`) VALUES
(1, 1, 1, 1, 18.50, 'จัดทำและดำเนินการสอนตามแผนการสอนครบทุกหัวข้อ', 'แผนการสอนรายวิชา EDU101', 'ดำเนินการสอนครบตามแผน', '2025-11-17 07:06:49'),
(2, 1, 1, 2, 14.00, 'พัฒนาสื่อ PowerPoint และ Google Classroom', 'สื่อการสอน PowerPoint', 'มีการใช้เทคโนโลยีในการสอน', '2025-11-17 07:06:49'),
(3, 1, 1, 3, 13.50, 'มีการวัดผลและประเมินผลตามเกณฑ์', 'แบบทดสอบและการวัดผล', 'ประเมินผลครบถ้วน', '2025-11-17 07:06:49'),
(4, 1, 2, 6, 18.00, 'ตีพิมพ์บทความวิจัยในวารสารระดับชาติ 1 เรื่อง', 'บทความวิจัยเรื่องการพัฒนาทักษะการคิด', 'ตีพิมพ์ใน TCI กลุ่ม 1', '2025-11-17 07:06:49'),
(5, 1, 3, 13, 21.50, 'จัดโครงการอบรมครูในชุมชน 1 โครงการ', 'โครงการอบรมครู 60 คน', 'ได้รับการตอบรับดี', '2025-11-17 07:06:49'),
(6, 2, 1, 1, 19.00, 'จัดการเรียนการสอนครบถ้วนตามแผน', 'แผนการสอนและเอกสารประกอบ', 'มีการปรับปรุงแผนการสอน', '2025-11-17 07:06:49'),
(7, 2, 1, 2, 15.00, 'พัฒนาคู่มือการใช้ Google Classroom', 'คู่มือการใช้ Google Classroom', 'ได้รับความสนใจจากนักศึกษา', '2025-11-17 07:06:49'),
(8, 2, 2, 6, 20.00, 'ตีพิมพ์บทความวิจัยระดับชาติ', 'รายงานวิจัย Active Learning', 'อยู่ระหว่างรอตีพิมพ์', '2025-11-17 07:06:49'),
(9, 2, 4, 15, 34.00, 'จัดกิจกรรมวันสงกรานต์ร่วมชุมชน', 'เอกสารและภาพกิจกรรม', 'ชุมชนให้ความร่วมมือดี', '2025-11-17 07:06:49');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_managers`
--

CREATE TABLE `evaluation_managers` (
  `em_id` int(11) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `manager_user_id` int(11) NOT NULL,
  `selection_order` tinyint(4) DEFAULT 1,
  `status` enum('pending','reviewing','approved','rejected') DEFAULT 'pending',
  `review_comment` text DEFAULT NULL,
  `review_score` decimal(5,2) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_periods`
--

CREATE TABLE `evaluation_periods` (
  `period_id` int(11) NOT NULL,
  `period_name` varchar(255) NOT NULL,
  `year` int(11) NOT NULL,
  `semester` tinyint(4) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `submission_deadline` datetime NOT NULL,
  `approval_deadline` datetime DEFAULT NULL,
  `status` enum('draft','active','closed') DEFAULT 'draft',
  `is_active` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_periods`
--

INSERT INTO `evaluation_periods` (`period_id`, `period_name`, `year`, `semester`, `start_date`, `end_date`, `submission_deadline`, `approval_deadline`, `status`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'รอบการประเมินภาคต้น ปีการศึกษา 2568', 2025, 1, '2025-06-01', '2025-10-31', '2025-11-15 23:59:59', '2025-11-30 23:59:59', 'active', 1, 'รอบการประเมินผลการปฏิบัติงานภาคต้น ประจำปีการศึกษา 2568', 1, 1, '2025-11-17 07:06:49', '2025-11-17 07:08:00'),
(2, 'รอบการประเมินภาคปลาย ปีการศึกษา 2567', 2025, 2, '2024-11-01', '2025-03-31', '2025-04-15 23:59:59', '2025-04-30 23:59:59', 'closed', 0, 'รอบการประเมินผลการปฏิบัติงานภาคปลาย ประจำปีการศึกษา 2567', 1, 1, '2025-11-17 07:06:49', '2025-11-17 07:08:18'),
(3, 'รอบการประเมินประจำปี พ.ศ. 2568', 2025, 1, '2025-01-01', '2025-12-31', '2026-01-15 23:59:59', '2026-01-31 23:59:59', 'draft', 0, 'รอบการประเมินผลการปฏิบัติงานประจำปี พ.ศ. 2568', 1, 1, '2025-11-17 07:06:49', '2025-11-17 07:08:13');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_portfolios`
--

CREATE TABLE `evaluation_portfolios` (
  `link_id` int(11) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `detail_id` int(11) DEFAULT NULL,
  `portfolio_id` int(11) NOT NULL,
  `is_claimed` tinyint(1) DEFAULT 0,
  `claimed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_portfolios`
--

INSERT INTO `evaluation_portfolios` (`link_id`, `evaluation_id`, `detail_id`, `portfolio_id`, `is_claimed`, `claimed_at`, `notes`, `created_at`) VALUES
(1, 1, 1, 1, 1, '2025-04-10 14:30:00', 'ใช้ผลงานแผนการสอน', '2025-11-17 07:06:49'),
(2, 1, 2, 2, 1, '2025-04-10 14:30:00', 'ใช้สื่อการสอน PowerPoint', '2025-11-17 07:06:49'),
(3, 1, 4, 3, 1, '2025-04-10 14:30:00', 'ใช้บทความวิจัย', '2025-11-17 07:06:49'),
(4, 1, 5, 4, 1, '2025-04-10 14:30:00', 'ใช้โครงการบริการวิชาการ', '2025-11-17 07:06:49'),
(5, 2, 7, 5, 1, '2025-11-10 09:45:00', 'ใช้คู่มือ Google Classroom', '2025-11-17 07:06:49'),
(6, 2, 8, 6, 1, '2025-11-10 09:45:00', 'ใช้รายงานวิจัย', '2025-11-17 07:06:49'),
(7, 2, 9, 7, 1, '2025-11-10 09:45:00', 'ใช้เอกสารกิจกรรม', '2025-11-17 07:06:49');

--
-- Triggers `evaluation_portfolios`
--
DELIMITER $$
CREATE TRIGGER `tr_portfolio_usage_update` AFTER INSERT ON `evaluation_portfolios` FOR EACH ROW BEGIN
    UPDATE portfolios
    SET current_usage_count = current_usage_count + 1
    WHERE portfolio_id = NEW.portfolio_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_scores`
--

CREATE TABLE `evaluation_scores` (
  `score_id` int(11) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `aspect_id` int(11) NOT NULL,
  `topic_id` int(11) DEFAULT NULL,
  `score` decimal(10,2) DEFAULT 0.00,
  `weighted_score` decimal(10,2) DEFAULT 0.00,
  `evidence` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_scores`
--

INSERT INTO `evaluation_scores` (`score_id`, `evaluation_id`, `aspect_id`, `topic_id`, `score`, `weighted_score`, `evidence`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 18.50, 18.50, 'แผนการสอนรายวิชา EDU101', 'ดำเนินการสอนครบตามแผน', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(2, 1, 1, 2, 14.00, 14.00, 'สื่อการสอน PowerPoint', 'มีการใช้เทคโนโลยี', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(3, 1, 1, 3, 13.50, 13.50, 'แบบทดสอบและการวัดผล', 'ประเมินผลครบถ้วน', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(4, 1, 2, 6, 18.00, 18.00, 'บทความวิจัยเรื่องการพัฒนาทักษะการคิด', 'TCI กลุ่ม 1', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(5, 1, 3, 13, 21.50, 21.50, 'โครงการอบรมครู', 'จำนวนผู้เข้าร่วม 60 คน', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(6, 2, 1, 1, 19.00, 19.00, 'แผนการสอนและเอกสารประกอบ', 'มีการปรับปรุงแผน', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(7, 2, 1, 2, 15.00, 15.00, 'คู่มือการใช้ Google Classroom', 'นักศึกษาให้ความสนใจ', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(8, 2, 2, 6, 20.00, 20.00, 'รายงานวิจัย Active Learning', 'อยู่ระหว่างรอตีพิมพ์', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(9, 2, 4, 15, 34.00, 34.00, 'เอกสารและภาพกิจกรรม', 'ชุมชนให้ความร่วมมือดี', '2025-11-17 07:06:49', '2025-11-17 07:06:49');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_topics`
--

CREATE TABLE `evaluation_topics` (
  `topic_id` int(11) NOT NULL,
  `aspect_id` int(11) NOT NULL,
  `topic_code` varchar(50) NOT NULL,
  `topic_name_th` varchar(500) NOT NULL,
  `topic_name_en` varchar(500) DEFAULT NULL,
  `max_score` decimal(5,2) DEFAULT 0.00,
  `weight_percentage` decimal(5,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_topics`
--

INSERT INTO `evaluation_topics` (`topic_id`, `aspect_id`, `topic_code`, `topic_name_th`, `topic_name_en`, `max_score`, `weight_percentage`, `description`, `sort_order`, `display_order`, `is_active`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'TEACH-01', 'การจัดการเรียนการสอนตามแผนการสอน', 'Teaching according to course plan', 20.00, 20.00, 'การจัดทำและดำเนินการสอนตามแผนการสอนที่กำหนด', 1, 1, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(2, 1, 'TEACH-02', 'การพัฒนาสื่อและเทคโนโลยีการสอน', 'Development of teaching media and technology', 15.00, 15.00, 'การพัฒนาและใช้สื่อการสอนที่เหมาะสม', 2, 2, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(3, 1, 'TEACH-03', 'การวัดและประเมินผลการเรียน', 'Learning assessment and evaluation', 15.00, 15.00, 'การวัดและประเมินผลการเรียนของนักศึกษา', 3, 3, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(4, 1, 'TEACH-04', 'การให้คำปรึกษานักศึกษา', 'Student counseling', 10.00, 10.00, 'การให้คำปรึกษาแนะนำนักศึกษา', 4, 4, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(5, 1, 'TEACH-05', 'การพัฒนาหลักสูตร', 'Curriculum development', 10.00, 10.00, 'การมีส่วนร่วมในการพัฒนาหลักสูตร', 5, 5, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(6, 2, 'RESEARCH-01', 'บทความวิจัยในวารสารระดับชาติ', 'Research article in national journal', 20.00, 20.00, 'การตีพิมพ์บทความวิจัยในวารสารระดับชาติ', 1, 1, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(7, 2, 'RESEARCH-02', 'บทความวิจัยในวารสารระดับนานาชาติ', 'Research article in international journal', 30.00, 30.00, 'การตีพิมพ์บทความวิจัยในวารสารระดับนานาชาติ', 2, 2, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(8, 2, 'RESEARCH-03', 'การนำเสนอผลงานวิจัยในที่ประชุมวิชาการ', 'Research presentation at conferences', 15.00, 15.00, 'การนำเสนอผลงานวิจัยในที่ประชุมวิชาการ', 3, 3, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(9, 2, 'RESEARCH-04', 'การได้รับทุนวิจัย', 'Research funding', 20.00, 20.00, 'การได้รับทุนสนับสนุนการวิจัย', 4, 4, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(10, 2, 'RESEARCH-05', 'การนำผลงานวิจัยไปใช้ประโยชน์', 'Research utilization', 15.00, 15.00, 'การนำผลงานวิจัยไปใช้ประโยชน์', 5, 5, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(11, 3, 'SERVICE-01', 'การให้บริการวิชาการแก่ชุมชน', 'Community academic service', 30.00, 30.00, 'การให้บริการวิชาการแก่ชุมชนและสังคม', 1, 1, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(12, 3, 'SERVICE-02', 'การเป็นวิทยากรภายนอก', 'External speaker/trainer', 20.00, 20.00, 'การเป็นวิทยากรให้กับหน่วยงานภายนอก', 2, 2, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(13, 3, 'SERVICE-03', 'การให้คำปรึกษาแก่หน่วยงานภายนอก', 'External consultation', 20.00, 20.00, 'การให้คำปรึกษาแก่หน่วยงานภายนอก', 3, 3, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(14, 3, 'SERVICE-04', 'การจัดโครงการบริการวิชาการ', 'Academic service project management', 30.00, 30.00, 'การจัดและดำเนินโครงการบริการวิชาการ', 4, 4, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(15, 4, 'CULTURE-01', 'การอนุรักษ์และส่งเสริมศิลปวัฒนธรรมท้องถิ่น', 'Local culture preservation and promotion', 40.00, 40.00, 'การมีส่วนร่วมในการอนุรักษ์ศิลปวัฒนธรรมท้องถิ่น', 1, 1, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(16, 4, 'CULTURE-02', 'การจัดกิจกรรมทางศิลปวัฒนธรรม', 'Cultural activity management', 30.00, 30.00, 'การจัดกิจกรรมทางศิลปวัฒนธรรม', 2, 2, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(17, 4, 'CULTURE-03', 'การเผยแพร่ความรู้ทางศิลปวัฒนธรรม', 'Cultural knowledge dissemination', 30.00, 30.00, 'การเผยแพร่ความรู้ด้านศิลปวัฒนธรรม', 3, 3, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(18, 5, 'MGMT-01', 'การบริหารงานในหน้าที่ที่ได้รับมอบหมาย', 'Assigned management duties', 40.00, 40.00, 'การปฏิบัติงานบริหารตามที่ได้รับมอบหมาย', 1, 1, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(19, 5, 'MGMT-02', 'การเข้าร่วมประชุมและการทำงานเป็นทีม', 'Meeting participation and teamwork', 30.00, 30.00, 'การเข้าร่วมประชุมและทำงานร่วมกับผู้อื่น', 2, 2, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(20, 5, 'MGMT-03', 'การพัฒนาตนเองและการฝึกอบรม', 'Self-development and training', 30.00, 30.00, 'การพัฒนาตนเองและเข้าร่วมการฝึกอบรม', 3, 3, 1, NULL, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `related_id`, `related_type`, `is_read`, `read_at`, `created_at`) VALUES
(1, 4, 'แบบประเมินได้รับการอนุมัติ', 'แบบประเมินผลการปฏิบัติงานของคุณได้รับการอนุมัติแล้ว', 'evaluation', 1, 'evaluation', 1, NULL, '2025-11-17 07:06:49'),
(2, 5, 'แบบประเมินอยู่ระหว่างการตรวจสอบ', 'แบบประเมินของคุณอยู่ระหว่างการตรวจสอบโดยผู้บริหาร', 'evaluation', 2, 'evaluation', 0, NULL, '2025-11-17 07:06:49'),
(3, 2, 'มีแบบประเมินรอการพิจารณา', 'มีแบบประเมินจากอาจารย์สุดา รอการพิจารณา', 'approval', 2, 'evaluation', 0, NULL, '2025-11-17 07:06:49');

-- --------------------------------------------------------

--
-- Table structure for table `personnel_evaluation_rights`
--

CREATE TABLE `personnel_evaluation_rights` (
  `right_id` int(11) NOT NULL,
  `personnel_type` enum('academic','support','lecturer') NOT NULL,
  `aspect_id` int(11) NOT NULL,
  `can_evaluate` tinyint(1) DEFAULT 1,
  `is_required` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personnel_types`
--

CREATE TABLE `personnel_types` (
  `personnel_type_id` int(11) NOT NULL,
  `type_code` varchar(20) NOT NULL,
  `type_name_th` varchar(100) NOT NULL,
  `type_name_en` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personnel_types`
--

INSERT INTO `personnel_types` (`personnel_type_id`, `type_code`, `type_name_th`, `type_name_en`, `description`, `is_active`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'ACADEMIC', 'สายวิชาการ', 'Academic Staff', NULL, 1, NULL, NULL, '2025-11-07 07:39:44', '2025-11-07 07:39:44');

-- --------------------------------------------------------

--
-- Table structure for table `portfolios`
--

CREATE TABLE `portfolios` (
  `portfolio_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `aspect_id` int(11) NOT NULL,
  `title` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `work_type` varchar(100) DEFAULT NULL,
  `work_date` date DEFAULT NULL,
  `max_usage_count` int(11) DEFAULT 1,
  `current_usage_count` int(11) DEFAULT 0,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `is_shared` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `portfolios`
--

INSERT INTO `portfolios` (`portfolio_id`, `user_id`, `aspect_id`, `title`, `description`, `work_type`, `work_date`, `max_usage_count`, `current_usage_count`, `file_path`, `file_name`, `file_size`, `is_shared`, `created_by`, `updated_by`, `tags`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 'แผนการสอนรายวิชาพื้นฐานการศึกษา', 'แผนการสอนรายวิชา EDU101 พื้นฐานการศึกษา ภาคเรียนที่ 1/2568', 'เอกสาร', '2025-06-15', 3, 1, 'portfolios/teaching_plan_edu101.pdf', 'teaching_plan_edu101.pdf', 2048576, 1, NULL, NULL, 'การสอน,แผนการสอน', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(2, 4, 1, 'สื่อการสอน PowerPoint วิชาจิตวิทยาการศึกษา', 'สื่อการสอนแบบ PowerPoint ประกอบการสอนวิชาจิตวิทยาการศึกษา', 'สื่อการสอน', '2025-07-20', 5, 1, 'portfolios/ppt_edu_psychology.pdf', 'ppt_edu_psychology.pdf', 5242880, 1, NULL, NULL, 'การสอน,สื่อการสอน', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(3, 4, 2, 'บทความวิจัย: การพัฒนาทักษะการคิดเชิงวิพากษ์', 'บทความวิจัยตีพิมพ์ในวารสารวิชาการระดับชาติ', 'บทความวิจัย', '2025-08-01', 2, 1, 'portfolios/research_critical_thinking.pdf', 'research_critical_thinking.pdf', 3145728, 1, NULL, NULL, 'วิจัย,วารสารชาติ', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(4, 4, 3, 'โครงการอบรมครูโรงเรียนในชุมชน', 'โครงการอบรมเชิงปฏิบัติการการจัดการเรียนการสอนสำหรับครู', 'โครงการบริการวิชาการ', '2025-09-10', 1, 1, 'portfolios/training_project.pdf', 'training_project.pdf', 4194304, 1, NULL, NULL, 'บริการวิชาการ,อบรม', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(5, 5, 1, 'คู่มือการใช้งาน Google Classroom', 'คู่มือการใช้งาน Google Classroom สำหรับการจัดการเรียนการสอนออนไลน์', 'เอกสาร', '2025-06-20', 3, 1, 'portfolios/google_classroom_manual.pdf', 'google_classroom_manual.pdf', 1572864, 1, NULL, NULL, 'การสอน,เทคโนโลยี', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(6, 5, 2, 'งานวิจัย: ผลการใช้ชุดการสอนแบบ Active Learning', 'รายงานการวิจัยเรื่องผลการใช้ชุดการสอนแบบ Active Learning', 'รายงานวิจัย', '2025-07-15', 2, 1, 'portfolios/active_learning_research.pdf', 'active_learning_research.pdf', 2621440, 1, NULL, NULL, 'วิจัย,การสอน', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(7, 5, 4, 'กิจกรรมวันสงกรานต์ชุมชน', 'การจัดกิจกรรมวันสงกรานต์ร่วมกับชุมชน', 'กิจกรรม', '2025-04-13', 1, 1, 'portfolios/songkran_activity.pdf', 'songkran_activity.pdf', 3670016, 1, NULL, NULL, 'ศิลปวัฒนธรรม,ชุมชน', '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(8, 6, 2, 'test', 'test', '', '2025-11-17', 1, 0, 'portfolios/691ad38cecac6_1763365772.pdf', 'กำหนดการดำเนินกิจกรรมค่ายฯ 11-10-68.pdf', 182247, 0, 6, 6, 'test', '2025-11-17 07:25:38', '2025-11-17 07:49:32');

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_reports`
--

CREATE TABLE `scheduled_reports` (
  `schedule_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `report_name` varchar(255) NOT NULL,
  `report_type` enum('individual','department','organization') NOT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL,
  `day_of_week` tinyint(4) DEFAULT NULL COMMENT '0-6 (Sunday-Saturday)',
  `day_of_month` tinyint(4) DEFAULT NULL COMMENT '1-31',
  `time` time NOT NULL,
  `format` enum('pdf','excel','csv') NOT NULL DEFAULT 'pdf',
  `recipients` text NOT NULL COMMENT 'Email addresses separated by comma',
  `filters` text DEFAULT NULL COMMENT 'JSON encoded filters',
  `is_active` tinyint(1) DEFAULT 1,
  `last_sent_at` datetime DEFAULT NULL,
  `next_send_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_backups`
--

CREATE TABLE `system_backups` (
  `backup_id` int(11) NOT NULL,
  `backup_name` varchar(255) NOT NULL,
  `backup_path` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `backup_type` enum('manual','automatic') DEFAULT 'manual',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'system_name', 'ระบบประเมินผลการปฏิบัติงาน มหาวิทยาลัยราชภัฏยะลา', 'text', 'ชื่อระบบ', NULL, '2025-11-07 02:08:45'),
(2, 'system_version', '1.0.0', 'text', 'เวอร์ชันระบบ', NULL, '2025-11-07 02:08:45'),
(3, 'backup_auto', '1', 'boolean', 'สำรองข้อมูลอัตโนมัติ', NULL, '2025-11-07 02:08:45'),
(4, 'notification_email', '1', 'boolean', 'ส่งการแจ้งเตือนทางอีเมล', NULL, '2025-11-07 02:08:45'),
(5, 'max_file_size', '10485760', 'number', 'ขนาดไฟล์สูงสุด (bytes)', NULL, '2025-11-07 02:08:45'),
(6, 'allowed_file_types', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'text', 'ประเภทไฟล์ที่อนุญาต', NULL, '2025-11-07 02:08:45'),
(7, 'notification_line_enabled', '0', 'boolean', 'เปิดใช้งาน LINE Notify', NULL, '2025-11-07 07:39:44'),
(8, 'notification_sms_enabled', '0', 'boolean', 'เปิดใช้งาน SMS', NULL, '2025-11-07 07:39:44'),
(9, 'notification_line_token', '', 'text', 'LINE Notify Token', NULL, '2025-11-07 07:39:44'),
(10, 'smtp_host', '', 'text', 'SMTP Host', NULL, '2025-11-07 07:39:44'),
(11, 'smtp_port', '587', 'number', 'SMTP Port', NULL, '2025-11-07 07:39:44'),
(12, 'smtp_username', '', 'text', 'SMTP Username', NULL, '2025-11-07 07:39:44'),
(13, 'smtp_password', '', 'text', 'SMTP Password', NULL, '2025-11-07 07:39:44'),
(14, 'evaluation_reminder_days', '7', 'number', 'วันเตือนก่อนหมดเขต', NULL, '2025-11-07 07:39:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name_th` varchar(255) NOT NULL,
  `full_name_en` varchar(255) DEFAULT NULL,
  `personnel_type` enum('academic','support','lecturer') NOT NULL,
  `personnel_type_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `role` enum('admin','staff','manager') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `full_name_th`, `full_name_en`, `personnel_type`, `personnel_type_id`, `department_id`, `position`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$iQQp12oxojN/s9IlUxo3tOGac4fzZqLfBe1ahK8wpxo9jfRailXaK', 'admin@yru.ac.th', 'ผู้ดูแลระบบ', 'System Administrator', 'academic', 1, NULL, NULL, 'admin', 1, '2025-11-17 14:50:51', '2025-11-07 02:08:45', '2025-11-17 07:50:51'),
(2, 'dean.educ', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dean.educ@yru.ac.th', 'ศาสตราจารย์ ดร.สมชาย ใจดี', 'Prof. Dr. Somchai Jaidee', 'academic', 1, 1, 'คณบดีคณะครุศาสตร์', 'manager', 1, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(3, 'dean.sci', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dean.sci@yru.ac.th', 'รองศาสตราจารย์ ดร.สมหญิง ศรีสุข', 'Assoc. Prof. Dr. Somying Srisuk', 'academic', 1, 2, 'คณบดีคณะวิทยาศาสตร์', 'manager', 1, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(4, 'somsak.w', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'somsak.w@yru.ac.th', 'ผู้ช่วยศาสตราจารย์ ดร.สมศักดิ์ วงศ์ดี', 'Asst. Prof. Dr. Somsak Wongdee', 'academic', 1, 1, 'อาจารย์ประจำ', 'staff', 1, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(5, 'suda.p', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'suda.p@yru.ac.th', 'อาจารย์ ดร.สุดา พรหมมา', 'Dr. Suda Phromma', 'academic', 1, 2, 'อาจารย์ประจำ', 'staff', 1, NULL, '2025-11-17 07:06:49', '2025-11-17 07:06:49'),
(6, 'anon.s', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'anon.s@yru.ac.th', 'อาจารย์อานนท์ สุขใจ', 'Mr. Anon Sukjai', 'academic', 1, 3, 'อาจารย์ประจำ', 'staff', 1, '2025-11-17 14:27:01', '2025-11-17 07:06:49', '2025-11-17 07:27:01');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_aspects_with_topics`
-- (See below for the actual view)
--
CREATE TABLE `v_aspects_with_topics` (
`aspect_id` int(11)
,`personnel_type_id` int(11)
,`aspect_code` varchar(20)
,`aspect_name_th` varchar(255)
,`aspect_name_en` varchar(255)
,`description` text
,`max_score` decimal(10,2)
,`weight_percentage` decimal(5,2)
,`sort_order` int(11)
,`display_order` int(11)
,`is_active` tinyint(1)
,`created_by` int(11)
,`updated_by` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`personnel_type_name` varchar(100)
,`topic_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_evaluations_full`
-- (See below for the actual view)
--
CREATE TABLE `v_evaluations_full` (
`evaluation_id` int(11)
,`period_id` int(11)
,`user_id` int(11)
,`personnel_type_id` int(11)
,`status` enum('draft','submitted','under_review','approved','rejected','returned')
,`total_score` decimal(10,2)
,`submitted_at` datetime
,`reviewed_at` datetime
,`reviewed_by` int(11)
,`approved_at` datetime
,`approved_by` int(11)
,`rejected_by` int(11)
,`rejected_at` datetime
,`created_at` timestamp
,`updated_at` timestamp
,`full_name_th` varchar(255)
,`email` varchar(100)
,`department_id` int(11)
,`personnel_type_name` varchar(100)
,`period_name` varchar(255)
,`year` int(11)
,`semester` tinyint(4)
,`start_date` date
,`end_date` date
,`reviewed_by_name` varchar(255)
,`approved_by_name` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_users_with_type`
-- (See below for the actual view)
--
CREATE TABLE `v_users_with_type` (
`user_id` int(11)
,`username` varchar(50)
,`password` varchar(255)
,`email` varchar(100)
,`full_name_th` varchar(255)
,`full_name_en` varchar(255)
,`personnel_type` enum('academic','support','lecturer')
,`personnel_type_id` int(11)
,`department_id` int(11)
,`position` varchar(100)
,`role` enum('admin','staff','manager')
,`is_active` tinyint(1)
,`last_login` datetime
,`created_at` timestamp
,`updated_at` timestamp
,`type_code` varchar(20)
,`personnel_type_name` varchar(100)
,`department_name_th` varchar(255)
);

-- --------------------------------------------------------

--
-- Structure for view `v_aspects_with_topics`
--
DROP TABLE IF EXISTS `v_aspects_with_topics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_aspects_with_topics`  AS SELECT `ea`.`aspect_id` AS `aspect_id`, `ea`.`personnel_type_id` AS `personnel_type_id`, `ea`.`aspect_code` AS `aspect_code`, `ea`.`aspect_name_th` AS `aspect_name_th`, `ea`.`aspect_name_en` AS `aspect_name_en`, `ea`.`description` AS `description`, `ea`.`max_score` AS `max_score`, `ea`.`weight_percentage` AS `weight_percentage`, `ea`.`sort_order` AS `sort_order`, `ea`.`display_order` AS `display_order`, `ea`.`is_active` AS `is_active`, `ea`.`created_by` AS `created_by`, `ea`.`updated_by` AS `updated_by`, `ea`.`created_at` AS `created_at`, `ea`.`updated_at` AS `updated_at`, `pt`.`type_name_th` AS `personnel_type_name`, count(distinct `et`.`topic_id`) AS `topic_count` FROM ((`evaluation_aspects` `ea` left join `personnel_types` `pt` on(`ea`.`personnel_type_id` = `pt`.`personnel_type_id`)) left join `evaluation_topics` `et` on(`ea`.`aspect_id` = `et`.`aspect_id`)) GROUP BY `ea`.`aspect_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_evaluations_full`
--
DROP TABLE IF EXISTS `v_evaluations_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_evaluations_full`  AS SELECT `e`.`evaluation_id` AS `evaluation_id`, `e`.`period_id` AS `period_id`, `e`.`user_id` AS `user_id`, `e`.`personnel_type_id` AS `personnel_type_id`, `e`.`status` AS `status`, `e`.`total_score` AS `total_score`, `e`.`submitted_at` AS `submitted_at`, `e`.`reviewed_at` AS `reviewed_at`, `e`.`reviewed_by` AS `reviewed_by`, `e`.`approved_at` AS `approved_at`, `e`.`approved_by` AS `approved_by`, `e`.`rejected_by` AS `rejected_by`, `e`.`rejected_at` AS `rejected_at`, `e`.`created_at` AS `created_at`, `e`.`updated_at` AS `updated_at`, `u`.`full_name_th` AS `full_name_th`, `u`.`email` AS `email`, `u`.`department_id` AS `department_id`, `pt`.`type_name_th` AS `personnel_type_name`, `ep`.`period_name` AS `period_name`, `ep`.`year` AS `year`, `ep`.`semester` AS `semester`, `ep`.`start_date` AS `start_date`, `ep`.`end_date` AS `end_date`, `ru`.`full_name_th` AS `reviewed_by_name`, `au`.`full_name_th` AS `approved_by_name` FROM (((((`evaluations` `e` left join `users` `u` on(`e`.`user_id` = `u`.`user_id`)) left join `personnel_types` `pt` on(`e`.`personnel_type_id` = `pt`.`personnel_type_id`)) left join `evaluation_periods` `ep` on(`e`.`period_id` = `ep`.`period_id`)) left join `users` `ru` on(`e`.`reviewed_by` = `ru`.`user_id`)) left join `users` `au` on(`e`.`approved_by` = `au`.`user_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_users_with_type`
--
DROP TABLE IF EXISTS `v_users_with_type`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_users_with_type`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`username` AS `username`, `u`.`password` AS `password`, `u`.`email` AS `email`, `u`.`full_name_th` AS `full_name_th`, `u`.`full_name_en` AS `full_name_en`, `u`.`personnel_type` AS `personnel_type`, `u`.`personnel_type_id` AS `personnel_type_id`, `u`.`department_id` AS `department_id`, `u`.`position` AS `position`, `u`.`role` AS `role`, `u`.`is_active` AS `is_active`, `u`.`last_login` AS `last_login`, `u`.`created_at` AS `created_at`, `u`.`updated_at` AS `updated_at`, `pt`.`type_code` AS `type_code`, `pt`.`type_name_th` AS `personnel_type_name`, `d`.`department_name_th` AS `department_name_th` FROM ((`users` `u` left join `personnel_types` `pt` on(`u`.`personnel_type_id` = `pt`.`personnel_type_id`)) left join `departments` `d` on(`u`.`department_id` = `d`.`department_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `approval_history`
--
ALTER TABLE `approval_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `manager_user_id` (`manager_user_id`),
  ADD KEY `idx_evaluation` (`evaluation_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_code` (`department_code`),
  ADD KEY `idx_faculty` (`faculty_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`evaluation_id`),
  ADD KEY `idx_period` (`period_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_personnel_type` (`personnel_type_id`),
  ADD KEY `idx_reviewed_by` (`reviewed_by`),
  ADD KEY `idx_approved_by` (`approved_by`),
  ADD KEY `idx_status_period` (`status`,`period_id`),
  ADD KEY `idx_user_period` (`user_id`,`period_id`);

--
-- Indexes for table `evaluation_aspects`
--
ALTER TABLE `evaluation_aspects`
  ADD PRIMARY KEY (`aspect_id`),
  ADD UNIQUE KEY `aspect_code` (`aspect_code`),
  ADD KEY `idx_personnel_type` (`personnel_type_id`);

--
-- Indexes for table `evaluation_details`
--
ALTER TABLE `evaluation_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `topic_id` (`topic_id`),
  ADD KEY `idx_evaluation` (`evaluation_id`),
  ADD KEY `idx_aspect_topic` (`aspect_id`,`topic_id`);

--
-- Indexes for table `evaluation_managers`
--
ALTER TABLE `evaluation_managers`
  ADD PRIMARY KEY (`em_id`),
  ADD KEY `idx_evaluation` (`evaluation_id`),
  ADD KEY `idx_manager` (`manager_user_id`);

--
-- Indexes for table `evaluation_periods`
--
ALTER TABLE `evaluation_periods`
  ADD PRIMARY KEY (`period_id`),
  ADD KEY `idx_year_semester` (`year`,`semester`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `evaluation_portfolios`
--
ALTER TABLE `evaluation_portfolios`
  ADD PRIMARY KEY (`link_id`),
  ADD KEY `detail_id` (`detail_id`),
  ADD KEY `idx_evaluation` (`evaluation_id`),
  ADD KEY `idx_portfolio` (`portfolio_id`);

--
-- Indexes for table `evaluation_scores`
--
ALTER TABLE `evaluation_scores`
  ADD PRIMARY KEY (`score_id`),
  ADD KEY `idx_evaluation` (`evaluation_id`),
  ADD KEY `idx_aspect` (`aspect_id`),
  ADD KEY `idx_topic` (`topic_id`);

--
-- Indexes for table `evaluation_topics`
--
ALTER TABLE `evaluation_topics`
  ADD PRIMARY KEY (`topic_id`),
  ADD KEY `idx_aspect` (`aspect_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_user_type_read` (`user_id`,`type`,`is_read`);

--
-- Indexes for table `personnel_evaluation_rights`
--
ALTER TABLE `personnel_evaluation_rights`
  ADD PRIMARY KEY (`right_id`),
  ADD UNIQUE KEY `unique_personnel_aspect` (`personnel_type`,`aspect_id`),
  ADD KEY `aspect_id` (`aspect_id`);

--
-- Indexes for table `personnel_types`
--
ALTER TABLE `personnel_types`
  ADD PRIMARY KEY (`personnel_type_id`),
  ADD UNIQUE KEY `type_code` (`type_code`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `portfolios`
--
ALTER TABLE `portfolios`
  ADD PRIMARY KEY (`portfolio_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_aspect` (`aspect_id`),
  ADD KEY `idx_user_aspect` (`user_id`,`aspect_id`);

--
-- Indexes for table `scheduled_reports`
--
ALTER TABLE `scheduled_reports`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_next_send` (`next_send_at`);

--
-- Indexes for table `system_backups`
--
ALTER TABLE `system_backups`
  ADD PRIMARY KEY (`backup_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_personnel_type` (`personnel_type`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_personnel_type_id` (`personnel_type_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `approval_history`
--
ALTER TABLE `approval_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `evaluation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `evaluation_aspects`
--
ALTER TABLE `evaluation_aspects`
  MODIFY `aspect_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `evaluation_details`
--
ALTER TABLE `evaluation_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `evaluation_managers`
--
ALTER TABLE `evaluation_managers`
  MODIFY `em_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evaluation_periods`
--
ALTER TABLE `evaluation_periods`
  MODIFY `period_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `evaluation_portfolios`
--
ALTER TABLE `evaluation_portfolios`
  MODIFY `link_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `evaluation_scores`
--
ALTER TABLE `evaluation_scores`
  MODIFY `score_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `evaluation_topics`
--
ALTER TABLE `evaluation_topics`
  MODIFY `topic_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `personnel_evaluation_rights`
--
ALTER TABLE `personnel_evaluation_rights`
  MODIFY `right_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personnel_types`
--
ALTER TABLE `personnel_types`
  MODIFY `personnel_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `portfolios`
--
ALTER TABLE `portfolios`
  MODIFY `portfolio_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `scheduled_reports`
--
ALTER TABLE `scheduled_reports`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_backups`
--
ALTER TABLE `system_backups`
  MODIFY `backup_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approval_history`
--
ALTER TABLE `approval_history`
  ADD CONSTRAINT `approval_history_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approval_history_ibfk_2` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `evaluation_periods` (`period_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_details`
--
ALTER TABLE `evaluation_details`
  ADD CONSTRAINT `evaluation_details_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_details_ibfk_2` FOREIGN KEY (`aspect_id`) REFERENCES `evaluation_aspects` (`aspect_id`),
  ADD CONSTRAINT `evaluation_details_ibfk_3` FOREIGN KEY (`topic_id`) REFERENCES `evaluation_topics` (`topic_id`);

--
-- Constraints for table `evaluation_managers`
--
ALTER TABLE `evaluation_managers`
  ADD CONSTRAINT `evaluation_managers_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_managers_ibfk_2` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `evaluation_portfolios`
--
ALTER TABLE `evaluation_portfolios`
  ADD CONSTRAINT `evaluation_portfolios_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_portfolios_ibfk_2` FOREIGN KEY (`detail_id`) REFERENCES `evaluation_details` (`detail_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_portfolios_ibfk_3` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`portfolio_id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_scores`
--
ALTER TABLE `evaluation_scores`
  ADD CONSTRAINT `evaluation_scores_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_scores_ibfk_2` FOREIGN KEY (`aspect_id`) REFERENCES `evaluation_aspects` (`aspect_id`),
  ADD CONSTRAINT `evaluation_scores_ibfk_3` FOREIGN KEY (`topic_id`) REFERENCES `evaluation_topics` (`topic_id`);

--
-- Constraints for table `evaluation_topics`
--
ALTER TABLE `evaluation_topics`
  ADD CONSTRAINT `evaluation_topics_ibfk_1` FOREIGN KEY (`aspect_id`) REFERENCES `evaluation_aspects` (`aspect_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `personnel_evaluation_rights`
--
ALTER TABLE `personnel_evaluation_rights`
  ADD CONSTRAINT `personnel_evaluation_rights_ibfk_1` FOREIGN KEY (`aspect_id`) REFERENCES `evaluation_aspects` (`aspect_id`) ON DELETE CASCADE;

--
-- Constraints for table `portfolios`
--
ALTER TABLE `portfolios`
  ADD CONSTRAINT `portfolios_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `portfolios_ibfk_2` FOREIGN KEY (`aspect_id`) REFERENCES `evaluation_aspects` (`aspect_id`);

--
-- Constraints for table `scheduled_reports`
--
ALTER TABLE `scheduled_reports`
  ADD CONSTRAINT `scheduled_reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
