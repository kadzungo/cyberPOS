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
-- Database: `cyberpos_license_server`
--

-- --------------------------------------------------------

--
-- Table structure for table `licenses`
--

CREATE TABLE `licenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `license_key` varchar(64) NOT NULL,
  `plan` enum('MONTHLY','YEARLY','LIFETIME') NOT NULL DEFAULT 'MONTHLY',
  `status` enum('UNUSED','ACTIVE','EXPIRED','SUSPENDED') NOT NULL DEFAULT 'UNUSED',
  `max_activations` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `allowed_pcs` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `activations_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `issue_date` date NOT NULL,
  `start_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `licenses`
--

INSERT INTO `licenses` (`id`, `license_key`, `plan`, `status`, `max_activations`, `allowed_pcs`, `activations_count`, `issue_date`, `start_date`, `expiry_date`, `customer_name`, `customer_phone`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'CYBPOS-B-TEST-0001-0001', 'MONTHLY', 'ACTIVE', 1, 1, 1, '2026-02-27', '2026-03-03', '2026-04-02', 'Local Test', NULL, NULL, '2026-02-27 12:15:53', '2026-03-03 08:28:40');

-- --------------------------------------------------------

--
-- Table structure for table `license_activations`
--

CREATE TABLE `license_activations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `license_id` bigint(20) UNSIGNED NOT NULL,
  `machine_hash` varchar(128) NOT NULL,
  `machine_name` varchar(120) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `activated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime DEFAULT NULL,
  `status` enum('ACTIVE','REVOKED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `license_activations`
--

INSERT INTO `license_activations` (`id`, `license_id`, `machine_hash`, `machine_name`, `ip_address`, `activated_at`, `last_seen_at`, `status`, `created_at`) VALUES
(1, 1, 'ac6fa47799890a470a3ff0121b79dbd7dedea6d34401d292d6da2bd594ee6f5e', 'DESKTOP-9G8OHPV', '::1', '2026-03-03 11:28:40', '2026-03-03 12:30:49', 'ACTIVE', '2026-03-03 08:28:40');

-- --------------------------------------------------------

--
-- Table structure for table `license_events`
--

CREATE TABLE `license_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `license_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` enum('CREATE','ACTIVATE','VERIFY','RENEW','SUSPEND','UNSUSPEND','EXPIRE','REVOKE') NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `license_events`
--

INSERT INTO `license_events` (`id`, `license_id`, `event_type`, `message`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'ACTIVATE', 'Activated on machine', '::1', NULL, '2026-03-03 08:28:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `licenses`
--
ALTER TABLE `licenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Indexes for table `license_activations`
--
ALTER TABLE `license_activations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_license_machine` (`license_id`,`machine_hash`),
  ADD KEY `idx_license` (`license_id`),
  ADD KEY `idx_machine` (`machine_hash`),
  ADD KEY `idx_last_seen` (`last_seen_at`);

--
-- Indexes for table `license_events`
--
ALTER TABLE `license_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_license` (`license_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `licenses`
--
ALTER TABLE `licenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `license_activations`
--
ALTER TABLE `license_activations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `license_events`
--
ALTER TABLE `license_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `license_activations`
--
ALTER TABLE `license_activations`
  ADD CONSTRAINT `fk_activation_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `license_events`
--
ALTER TABLE `license_events`
  ADD CONSTRAINT `fk_event_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
