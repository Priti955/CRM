-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 01, 2025 at 11:12 AM
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
-- Database: `crm_ticket`
--

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci,
  `last_activity` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','inprogress','completed','onhold') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `file_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `name`, `description`, `status`, `file_path`, `created_by`, `created_at`, `deleted_at`, `completed_at`, `updated_at`) VALUES
(1, 'crm ticket', 'tucket update profile', 'inprogress', NULL, 1, '2025-11-26 13:32:40', NULL, NULL, '2025-11-26 15:03:15'),
(2, 'Status Tracking', 'both the customer and the support the team', 'pending', 'storage/uploads/f_6926cc0e8c125_exospace.jpg', 2, '2025-11-26 15:14:46', NULL, NULL, '2025-11-26 15:14:46'),
(3, 'Tickets status', 'The Customer and internalnteams to monitor the progress of ticket resolution.', 'inprogress', NULL, 2, '2025-11-26 18:18:09', NULL, NULL, '2025-11-27 15:51:19'),
(4, 'Tracking and resolution', 'Support agent can work to resolve the isssue ,update the ticket.', 'inprogress', 'storage/uploads/f_692ad6a53b87c_Screenshot_2025-11-25_222809.png', 3, '2025-11-29 16:49:01', NULL, NULL, '2025-11-29 17:29:02'),
(5, 'customer infromation', 'A detailes explanation of the problem provided by the customer or agent.', 'completed', 'storage/uploads/f_692be767993e0_Screenshot_2025-11-29_164925.png', 4, '2025-11-30 11:56:27', NULL, '2025-11-30 12:17:15', '2025-11-30 12:17:15'),
(6, 'centralizing information', 'All communication and details related to the inquiry, including customer history..', 'pending', 'storage/uploads/f_692d30add10a2_Screenshot_2025-11-30_171814.png', 6, '2025-12-01 11:37:41', NULL, NULL, '2025-12-01 11:55:01');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_assignments`
--

DROP TABLE IF EXISTS `ticket_assignments`;
CREATE TABLE IF NOT EXISTS `ticket_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `assigned_to` int NOT NULL,
  `assigned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `unassigned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `assigned_to` (`assigned_to`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_assignments`
--

INSERT INTO `ticket_assignments` (`id`, `ticket_id`, `assigned_to`, `assigned_at`, `unassigned_at`) VALUES
(1, 1, 1, '2025-11-26 15:03:27', NULL),
(2, 2, 2, '2025-11-26 15:16:26', '2025-11-26 16:55:46'),
(3, 2, 1, '2025-11-26 16:55:46', '2025-11-26 17:53:27'),
(4, 2, 2, '2025-11-26 17:53:27', NULL),
(5, 3, 1, '2025-11-26 18:18:39', '2025-11-27 15:51:06'),
(6, 3, 1, '2025-11-27 15:51:06', NULL),
(7, 4, 1, '2025-11-29 17:25:42', '2025-11-29 17:28:24'),
(8, 4, 1, '2025-11-29 17:28:24', '2025-11-29 17:28:52'),
(9, 4, 1, '2025-11-29 17:28:52', NULL),
(10, 5, 2, '2025-11-30 11:56:51', NULL),
(11, 6, 2, '2025-12-01 11:55:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `role`, `is_active`, `password`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'soumya', 'sahoo@gmail.com', 'user', 1, '$2y$10$IqaZWGnNyhBxhx3zOTUzAeJpjJ//.EwH9bdMmC3L/iaR.07lY1WPS', '2025-11-26 00:53:33', '2025-12-01 13:07:29', NULL),
(2, 'pritimanjari sahoo', 'pritimanjarisahoo183@gmai.com', 'admin', 1, '$2y$10$SufoBqenfpFHkr4t6TcbOuSGUutQkCIJzBWZkUdzAqyrlfDUntvOy', '2025-11-26 15:12:18', '2025-12-01 13:07:38', NULL),
(3, 'subham', 'subham@gmail.com', 'user', 1, '$2y$10$fs1spu5ERAsuT5J.cGWXXOtK9MjiZVM3ERNiPLXB4DIm/9c77PVTG', '2025-11-27 12:46:03', '2025-11-27 12:46:03', NULL),
(4, 'Simali Sahoo', 'sahoosimali85@gmail.com', 'user', 1, '$2y$10$e9K7Jmil/lij44ie2vgymeFzCohqjqlN1Vhm6HnYNgfCgItS680Pm', '2025-11-30 11:34:35', '2025-11-30 11:34:35', NULL),
(5, 'virat kohli', 'virat@gmail.com', 'user', 1, '$2y$10$h1S4XdKw8M.SydVvHwpFxufZbshoPWqDxpa/MucWIxMFTGSsI2Msy', '2025-12-01 10:38:15', '2025-12-01 10:38:15', NULL),
(6, 'Sambit Sahoo', 'sambit@gmail.com', 'user', 0, '$2y$10$NozjZ8u5IWD9RsUTnDQRue7nIMpca0AXFdvO48THjsCi7bMvKuvUi', '2025-12-01 10:59:29', '2025-12-01 16:22:27', NULL);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
