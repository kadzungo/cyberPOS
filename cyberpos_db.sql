-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 09, 2026 at 08:43 AM
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
-- Database: `cyberpos_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'admin', 'LOGIN', 'User logged in', '::1', '2026-03-06 12:19:26'),
(2, 1, 'admin', 'LOGOUT', 'User logged out', '::1', '2026-03-06 12:31:28'),
(3, 1, 'admin', 'LOGIN', 'User logged in', '::1', '2026-03-06 12:32:11'),
(4, 1, 'admin', 'START_SESSION', 'PC: pc 3', '::1', '2026-03-06 14:04:23'),
(5, 1, 'admin', 'START_SESSION', 'PC: TESTPC', '::1', '2026-03-06 14:04:31'),
(6, 1, 'admin', 'END_SESSION', 'PC: pc 3', '::1', '2026-03-06 14:04:53'),
(7, 1, 'admin', 'END_SESSION', 'PC: TESTPC', '::1', '2026-03-06 14:05:07'),
(8, 1, 'admin', 'LOGOUT', 'User logged out', '::1', '2026-03-06 14:20:26'),
(9, 1, 'admin', 'LOGIN', 'User logged in', '::1', '2026-03-06 14:21:04'),
(10, 1, 'admin', 'LOGOUT', 'User logged out', '::1', '2026-03-06 14:21:40'),
(11, 1, 'admin', 'LOGIN', 'User logged in', '::1', '2026-03-06 14:22:07'),
(12, 1, 'admin', 'LOGOUT', 'User logged out', '::1', '2026-03-06 14:22:42'),
(13, 2, 'User', 'LOGIN', 'User logged in', '::1', '2026-03-06 14:22:54'),
(14, 2, 'User', 'LOGOUT', 'User logged out', '::1', '2026-03-06 14:31:27'),
(15, 1, 'admin', 'LOGIN', 'User logged in', '::1', '2026-03-06 14:31:48'),
(16, 1, 'admin', 'UPDATE_SETTINGS', 'Settings changed | Cyber Name: \'CyberPOS\' -> \'CyberPOS\', Currency: \'KES\' -> \'KES\', Rate/hr: 55 -> 60', '::1', '2026-03-06 14:48:42'),
(17, 1, 'admin', 'LOGOUT', 'User logged out', '::1', '2026-03-06 15:51:26'),
(18, 1, 'admin', 'LOGIN', 'User logged in', '::1', '2026-03-06 15:51:39');

-- --------------------------------------------------------

--
-- Table structure for table `computers`
--

CREATE TABLE `computers` (
  `id` int(11) NOT NULL,
  `computer_name` varchar(50) DEFAULT NULL,
  `status` enum('available','occupied') DEFAULT 'available',
  `last_ping` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `computers`
--

INSERT INTO `computers` (`id`, `computer_name`, `status`, `last_ping`) VALUES
(1, 'TESTPC', 'available', '2026-03-06 14:05:07'),
(2, 'DESKTOP-9G8OHPV', 'available', '2026-02-25 14:06:01'),
(14, 'pc 3', 'available', '2026-03-06 14:04:53');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `computer_name` varchar(50) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `status` enum('active','ended') DEFAULT 'active',
  `rate_per_hour` decimal(10,2) NOT NULL DEFAULT 50.00,
  `amount_due` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `computer_name`, `start_time`, `end_time`, `status`, `rate_per_hour`, `amount_due`) VALUES
(1, 'TESTPC', '2026-02-23 20:43:18', '2026-02-23 20:48:32', 'ended', 60.00, 0.00),
(2, 'TESTPC', '2026-02-23 20:49:14', '2026-02-25 12:55:27', 'ended', 60.00, 0.00),
(3, 'DESKTOP-9G8OHPV', '2026-02-24 13:53:02', '2026-02-24 14:19:45', 'ended', 60.00, 0.00),
(4, 'DESKTOP-9G8OHPV', '2026-02-25 15:10:21', '2026-02-25 15:17:01', 'ended', 60.00, 0.00),
(5, 'DESKTOP-9G8OHPV', '2026-02-25 15:17:01', NULL, 'active', 60.00, 0.00),
(6, 'TESTPC', '2026-02-26 08:32:40', '2026-02-26 08:32:52', 'ended', 60.00, 0.00),
(7, 'TESTPC', '2026-02-26 08:33:12', '2026-02-26 08:33:24', 'ended', 60.00, 0.00),
(8, 'TESTPC', '2026-02-26 09:39:19', '2026-02-26 09:50:16', 'ended', 60.00, 0.83),
(9, 'TESTPC', '2026-02-26 10:20:13', '2026-02-26 10:30:52', 'ended', 60.00, 11.00),
(10, 'TESTPC', '2026-02-26 14:16:48', '2026-02-26 14:55:41', 'ended', 60.00, 39.00),
(11, 'pc 3', '2026-02-26 15:13:16', '2026-02-26 15:13:27', 'ended', 60.00, 1.00),
(12, 'pc 3', '2026-02-26 15:47:23', '2026-02-26 15:47:56', 'ended', 60.00, 1.00),
(13, 'TESTPC', '2026-02-26 15:47:28', '2026-02-26 15:48:15', 'ended', 60.00, 1.00),
(14, 'pc 3', '2026-02-26 16:22:14', '2026-02-26 16:24:44', 'ended', 55.00, 2.75),
(15, 'TESTPC', '2026-02-26 16:22:19', '2026-02-26 16:25:14', 'ended', 55.00, 2.75),
(16, 'pc 3', '2026-02-27 13:24:38', '2026-02-27 13:25:21', 'ended', 55.00, 0.92),
(17, 'TESTPC', '2026-02-27 13:24:44', '2026-02-27 15:11:38', 'ended', 55.00, 98.08),
(18, 'pc 3', '2026-03-03 12:28:24', '2026-03-03 12:28:55', 'ended', 55.00, 0.92),
(19, 'TESTPC', '2026-03-03 12:28:29', '2026-03-03 12:29:10', 'ended', 55.00, 0.92),
(20, 'pc 3', '2026-03-06 14:04:23', '2026-03-06 14:04:53', 'ended', 55.00, 0.92),
(21, 'TESTPC', '2026-03-06 14:04:31', '2026-03-06 14:05:07', 'ended', 55.00, 0.92);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `cyber_name` varchar(100) NOT NULL DEFAULT 'CyberPOS',
  `currency` varchar(10) NOT NULL DEFAULT 'KES',
  `rate_per_hour` decimal(10,2) NOT NULL DEFAULT 60.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `cyber_name`, `currency`, `rate_per_hour`, `updated_at`) VALUES
(1, 'CyberPOS', 'KES', 60.00, '2026-03-06 11:48:42');

-- --------------------------------------------------------

--
-- Table structure for table `system_license`
--

CREATE TABLE `system_license` (
  `id` tinyint(4) NOT NULL DEFAULT 1,
  `license_key` varchar(64) NOT NULL,
  `machine_hash` varchar(128) NOT NULL,
  `allowed_pcs` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `grace_days` int(11) NOT NULL DEFAULT 3,
  `expiry_date` date NOT NULL,
  `last_verified` date NOT NULL,
  `last_server_time` datetime DEFAULT NULL,
  `checksum` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_license`
--

INSERT INTO `system_license` (`id`, `license_key`, `machine_hash`, `allowed_pcs`, `status`, `grace_days`, `expiry_date`, `last_verified`, `last_server_time`, `checksum`) VALUES
(1, 'CYBPOS-B-TEST-0001-0001', 'ac6fa47799890a470a3ff0121b79dbd7dedea6d34401d292d6da2bd594ee6f5e', 10, 'active', 3, '2026-04-02', '2026-03-03', NULL, '6afb8c856e76a05ba058daa4a23724aaa7dcedeabb2995d5bc857bf41a9fcc35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$hCFLKo0fnsfTcXTN.OOoCeceGJFyKNbCQZ5j0Gi8qNVIqfkqXyQKu', 'admin', '2026-02-25 15:26:22'),
(2, 'User', '$2y$10$Ywvq.MybHzu01qyVD4U5t.WnJkYoCo63OHiOXobeXC5BlwLnOSt/q', 'staff', '2026-02-26 10:56:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `computers`
--
ALTER TABLE `computers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_computer_name` (`computer_name`),
  ADD UNIQUE KEY `uniq_computer_name` (`computer_name`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_license`
--
ALTER TABLE `system_license`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `computers`
--
ALTER TABLE `computers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
