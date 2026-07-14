-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 07, 2026 at 06:07 PM
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
-- Database: `school_events_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_email_otps`
--

CREATE TABLE `account_email_otps` (
  `id` int(11) NOT NULL,
  `purpose` enum('register','reactivate') NOT NULL,
  `email` varchar(120) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `payload_json` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account_email_otps`
--

INSERT INTO `account_email_otps` (`id`, `purpose`, `email`, `user_id`, `otp_hash`, `payload_json`, `expires_at`, `used_at`, `attempt_count`, `created_at`) VALUES
(1, 'register', 'bojiking31@gmail.com', NULL, '$2y$10$2JcibEoXg6V3w/X/0pefuOpBUQ0rE1Urbn.5DGO5sU.kcS5JCQrWy', '{\"name\":\"boji king\",\"password_hash\":\"$2y$10$Fl\\/R2PzHuN6yI0PWNeXOpevCkAXwFwlRRVemHI\\/gBDuERnlSe.4s.\",\"role\":\"student\",\"department\":\"High school department\",\"user_code\":\"STU-440\"}', '2026-03-27 10:58:53', '2026-03-27 17:48:57', 0, '2026-03-27 09:48:53'),
(2, 'register', 'bojiking31@gmail.com', NULL, '$2y$10$o/hZUKMDm36WGXJJzhT4qek1s6oM1VOBYEb28eTN4qjRGphrDFnH2', '{\"name\":\"boji king\",\"password_hash\":\"$2y$10$ry5IRHNBTm4CCwXQPJUgW.OGeLshuflC\\/eyq3BM3H3Mh5ZhyO4nOC\",\"role\":\"student\",\"department\":\"High school department\",\"user_code\":\"STU-992\"}', '2026-03-27 10:58:57', '2026-03-27 17:49:01', 0, '2026-03-27 09:48:57'),
(3, 'register', 'bojiking31@gmail.com', NULL, '$2y$10$qFvmXmklQJwsD6wspjSYYOCUDekpYUqR4cu7J0TyrFu70LvbHW0Yy', '{\"name\":\"boji king\",\"password_hash\":\"$2y$10$YCSM2en7zcx350zWqNcSv.71yLWJo8sbAU9LguzoqFHs4rU5CTcRa\",\"role\":\"student\",\"department\":\"High school department\",\"user_code\":\"STU-677\"}', '2026-03-27 10:59:01', '2026-03-27 17:49:51', 0, '2026-03-27 09:49:01'),
(4, 'register', 'bojiking31@gmail.com', NULL, '$2y$10$4EGZtcNMt1lJt34beuolTOHY0NGGrAxLEeC/qdVEJZu8EZ66KQh0K', '{\"name\":\"boji king\",\"password_hash\":\"$2y$10$zpyG\\/vrXbYu2skJLsqUGheFA7D0IZzX7Sew15j2GJUMbxpn\\/u9Nsm\",\"role\":\"student\",\"department\":\"High school department\",\"user_code\":\"STU-601\"}', '2026-03-27 10:59:51', '2026-03-27 17:49:53', 0, '2026-03-27 09:49:51'),
(5, 'register', 'bojiking31@gmail.com', NULL, '$2y$10$Ck8/nZ/OKhhB8afcWC79R.MEvPYr5azhipWR9uaK7fdkpXTF7oKw2', '{\"name\":\"boji king\",\"password_hash\":\"$2y$10$q7sUyScjdW2ax9DHIRqNB.8c5Vxy1tg1sLq1xVPb5wV15VVOez.Fe\",\"role\":\"student\",\"department\":\"High school department\",\"user_code\":\"STU-162\"}', '2026-03-27 10:59:54', '2026-03-27 17:50:29', 0, '2026-03-27 09:49:54'),
(6, 'reactivate', 'bojiking31@gmail.com', 29, '$2y$10$D4qZVD8AKQFFzLfQYV0RUeCk8DQmjgHNOGC2wZxoZ8Y0EpgQT59Ei', NULL, '2026-03-27 11:05:13', '2026-03-27 19:26:40', 0, '2026-03-27 09:55:13'),
(7, 'register', 'deanechristiancamat121212@gmail.com', NULL, '$2y$10$xTo.WnNeZdcVlU0hoSqf.uph6sPwcqjgaFANcewyVPcpou10mw3Ju', '{\"name\":\"deane gwapo\",\"password_hash\":\"$2y$10$ip\\/XDQfYJ86kHjgQWLBkjuhnVwNpvNjoOrdECsQHhMdqS3mdRH5di\",\"role\":\"student\",\"department\":\"College of Communication, Information and Technology\",\"user_code\":\"STU-799\"}', '2026-03-27 11:30:55', '2026-03-27 18:21:46', 0, '2026-03-27 10:20:55'),
(8, 'reactivate', 'bojiking31@gmail.com', 29, '$2y$10$S3GwrBsF5DcyToZ4IDzX8OUicnw4mjLhudkad/biif.lLt28stdLa', NULL, '2026-03-27 12:36:40', '2026-03-27 22:03:57', 0, '2026-03-27 11:26:40'),
(9, 'reactivate', 'bojiking31@gmail.com', 29, '$2y$10$5oI3qusTrkA72uA2y.bYOeBUtxQmqI6qmXFoyxt.uniXuOw1DeAaG', NULL, '2026-03-27 15:13:57', '2026-03-27 22:14:07', 0, '2026-03-27 14:03:57'),
(10, 'reactivate', 'bojiking31@gmail.com', 29, '$2y$10$8g0HjNmB85Vs/J3Ox2.ln.ep6t1.hwnTtTydqUQPGTWA5E18kRlyq', NULL, '2026-03-27 15:24:07', '2026-03-27 22:15:27', 0, '2026-03-27 14:14:07'),
(11, 'reactivate', 'bojiking31@gmail.com', 29, '$2y$10$RmAJ5MGSD02RKyZIk5/lj.uYjzqx4kHL2YUqIoruLWLsYh7iWnZ/i', NULL, '2026-03-27 15:27:21', '2026-03-27 22:17:53', 0, '2026-03-27 14:17:21'),
(12, 'reactivate', 'bojiking31@gmail.com', 29, '$2y$10$q87GKh.yQ6kTzhj5ut9o3.3L4B2RrGH.oeYFPGrHfGqzYy2AAU5pu', NULL, '2026-03-27 15:41:52', '2026-03-27 22:32:21', 0, '2026-03-27 14:31:52'),
(13, 'reactivate', 'bojiking31@gmail.com', 29, '$2y$10$QSHb1fDA1qYPVzgBDcpSmOt6MdUFwrMWL2gcXINtrtiiOJ8Ih2pMO', NULL, '2026-03-27 15:50:52', '2026-03-27 22:41:18', 0, '2026-03-27 14:40:52'),
(14, 'register', 'blademale31@gmail.com', NULL, '$2y$10$IoW7BWCaGe1p3jzTNkrX9e3UsJxSbZyN3Z8OQZ1IAUhNawTPWRmbK', '{\"name\":\"blademale\",\"password_hash\":\"$2y$10$YQxrnNdwXQhRnQpu4vUpPeO6n2QtreRy6BS4msx4ZwdvE6xVjGLMW\",\"role\":\"student\",\"department\":\"High school department\",\"user_code\":\"STU-129\"}', '2026-03-30 17:10:24', '2026-03-30 23:02:16', 0, '2026-03-30 15:00:24'),
(15, 'reactivate', 'bojiking31@gmail.com', 29, '$2y$10$6MWLhyOSVoNL8x5rMHuFBOtaUdQqTU1zr.4qdFjAw.SXnW3oi8tDi', NULL, '2026-03-30 17:29:54', '2026-03-30 23:20:17', 0, '2026-03-30 15:19:54'),
(16, 'reactivate', 'bojiking31@gmail.com', 29, '$2y$10$P.akJgGlFPprndRRU1OLLegpsewiZwtuOhEDxdJB9q5nYP7IcCDoy', NULL, '2026-04-20 05:22:46', '2026-04-20 11:13:08', 0, '2026-04-20 03:12:46'),
(17, 'register', 'chona@gmail.com', NULL, '$2y$10$ur3P5MkxnEWpTZCGIfTg.uPA3NKapI4XZcwTk9yoTZ/u0YHjwlc..', '{\"name\":\"chona  baricuatro\",\"password_hash\":\"$2y$10$0anFcAXXXAF5YBWbq27LN.6y1bU2g769WdMz.JQFg1E\\/d\\/jZ4Iipe\",\"role\":\"student\",\"department\":\"College of Communication, Information and Technology\",\"student_course\":\"BS Information Technology\",\"student_year_level\":\"3rd Year\",\"user_code\":\"STU-632\"}', '2026-04-22 04:37:55', NULL, 0, '2026-04-22 02:27:55'),
(18, 'register', 'suwiliao31@gmail.com', NULL, '$2y$10$wSlZzurM/ET7e5c6LUH7..GX1pi9JMvf13KlEs5rARcW6Ot3XBqb6', '{\"name\":\"suwi liao\",\"password_hash\":\"$2y$10$QkbO9VbrxLk6\\/UQjfqsaPuw7wL0DsC460f0DUfvSpzdmPFs0oYhCK\",\"role\":\"student\",\"department\":\"College of Communication, Information and Technology\",\"student_course\":\"BS Information Technology\",\"student_year_level\":\"3rd Year\",\"user_code\":\"STU-282\"}', '2026-04-22 04:49:22', '2026-04-22 10:39:37', 0, '2026-04-22 02:39:22'),
(19, 'register', 'keanugayuma@gmail.com', NULL, '$2y$10$kjTZkVLsp.bG7oWCS32CbeiouSuH0KhD9W.U9egaDsjIlqlvukZ46', '{\"name\":\"Keanu\",\"password_hash\":\"$2y$10$WdNDvoXl.8zw8uIxxxqNzeMKax0CgHMIPvQ1liBhRe1H5ENXuNOpi\",\"role\":\"student\",\"department\":\"College of Communication, Information and Technology\",\"student_course\":\"BS Information Technology\",\"student_year_level\":\"Grade 7\",\"user_code\":\"STU-426\"}', '2026-04-27 18:31:14', '2026-04-28 00:21:58', 0, '2026-04-27 16:21:14');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `actor_id`, `actor_role`, `action`, `target_type`, `target_id`, `details`, `created_at`) VALUES
(1, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 06:56:30'),
(2, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 06:56:47'),
(3, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 07:16:18'),
(4, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 07:20:09'),
(5, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 00:28:18'),
(6, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 00:45:17'),
(7, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 01:13:21'),
(8, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 01:27:33'),
(9, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 14:52:49'),
(10, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 14:56:17'),
(11, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 14:57:03'),
(12, 14, 'student', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 14:57:51'),
(13, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 15:18:26'),
(14, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 15:33:33'),
(15, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 15:36:11'),
(16, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 15:36:39'),
(17, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 15:37:05'),
(18, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 15:37:33'),
(19, 28, 'admin', 'event_approved', 'event', 9, 'Approved event ID 9', '2026-03-14 15:37:43'),
(20, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 15:37:58'),
(21, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 15:46:35'),
(22, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 15:49:37'),
(23, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 16:01:13'),
(24, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 16:14:08'),
(25, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP 192.168.1.11 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-03-14 16:21:15'),
(26, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP 192.168.1.11 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-03-14 16:23:09'),
(27, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP 192.168.1.11 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-03-14 16:27:44'),
(28, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 01:21:01'),
(29, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP 192.168.1.11 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-03-15 01:28:03'),
(30, 14, 'student', 'login_success', 'user', 14, 'Successful login from IP 192.168.1.11 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-03-15 01:31:46'),
(31, 14, 'student', 'login_success', 'user', 14, 'Successful login from IP 192.168.1.11 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-03-15 02:16:52'),
(32, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 10:29:03'),
(33, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 10:31:49'),
(34, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP 192.168.1.13 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-03-15 10:35:08'),
(35, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP 192.168.1.13 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-03-15 10:54:27'),
(36, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 11:02:17'),
(37, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 11:35:38'),
(38, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 11:55:42'),
(39, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP 192.168.1.13 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-03-15 11:56:29'),
(40, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 12:35:18'),
(41, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 13:48:19'),
(42, 13, 'student', 'login_success', 'user', 13, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-15 13:49:15'),
(43, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 14:57:08'),
(44, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:18:53'),
(45, 17, 'organizer', 'event_submitted_pending', 'event', 10, 'Submitted for admin approval: call lablab', '2026-03-26 15:27:41'),
(46, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:28:13'),
(47, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:29:13'),
(48, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:29:39'),
(49, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:32:02'),
(50, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:32:58'),
(51, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:33:07'),
(52, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:33:47'),
(53, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:33:50'),
(54, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:34:01'),
(55, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:35:02'),
(56, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:35:17'),
(57, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:35:33'),
(58, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:35:53'),
(59, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:35:56'),
(60, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:36:54'),
(61, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:37:14'),
(62, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:38:29'),
(63, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:44:25'),
(64, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via phone', '2026-03-26 15:53:52'),
(65, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:57:39'),
(66, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:58:39'),
(67, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via email', '2026-03-26 15:58:47'),
(68, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 16:17:26'),
(69, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 16:17:54'),
(70, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via email', '2026-03-26 16:26:54'),
(71, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via email', '2026-03-26 16:31:34'),
(72, 28, 'admin', 'event_approval_otp_sent', 'event', 10, 'Sent OTP via email', '2026-03-26 16:32:53'),
(73, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 16:33:29'),
(74, 17, 'organizer', 'event_approved_via_otp', 'event', 10, 'Organizer verified OTP and event became active', '2026-03-26 16:33:41'),
(75, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 16:35:20'),
(76, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 16:38:25'),
(77, 14, 'student', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:38:37'),
(78, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:39:23'),
(79, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:40:25'),
(80, 3, 'super_admin', 'user_reactivated', 'user', 20, 'Reactivated user ID 20', '2026-03-27 09:40:30'),
(81, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:40:59'),
(82, 3, 'super_admin', 'user_reactivated', 'user', 20, 'Reactivated user ID 20', '2026-03-27 09:41:04'),
(83, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:49:13'),
(84, 29, 'student', 'register_email_verified', 'user', 29, 'Completed registration via OTP email verification', '2026-03-27 09:50:29'),
(85, 29, 'student', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:50:39'),
(86, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:54:51'),
(87, 3, 'super_admin', 'user_deactivated', 'user', 29, 'Deactivated user ID 29', '2026-03-27 09:55:07'),
(88, 3, 'super_admin', 'user_reactivated', 'user', 29, 'Sent reactivation OTP to user ID 29', '2026-03-27 09:55:16'),
(89, 30, 'student', 'register_email_verified', 'user', 30, 'Completed registration via OTP email verification', '2026-03-27 10:21:46'),
(90, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:22:20'),
(91, 3, 'super_admin', 'user_activated', 'user', 30, 'Activated pending user ID 30', '2026-03-27 10:25:49'),
(92, 30, 'student', 'login_success', 'user', 30, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:26:07'),
(93, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 11:25:35'),
(94, 3, 'super_admin', 'user_activated', 'user', 29, 'Activated pending user ID 29', '2026-03-27 11:25:39'),
(95, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 11:26:36'),
(96, 3, 'super_admin', 'user_reactivated', 'user', 29, 'Sent reactivation OTP to user ID 29', '2026-03-27 11:26:43'),
(97, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 13:39:34'),
(98, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:02:57'),
(99, 3, 'super_admin', 'user_reactivated', 'user', 29, 'Sent reactivation OTP to user ID 29', '2026-03-27 14:04:01'),
(100, 3, 'super_admin', 'user_reactivated', 'user', 29, 'Sent reactivation OTP to user ID 29', '2026-03-27 14:14:10'),
(101, 29, 'user', 'reactivation_otp_verified_pending_activation', 'user', 29, 'User completed reactivation OTP verification and is pending super admin activation', '2026-03-27 14:15:27'),
(102, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:17:18'),
(103, 3, 'super_admin', 'user_reactivated', 'user', 29, 'Sent reactivation OTP to user ID 29', '2026-03-27 14:17:24'),
(104, 29, 'user', 'reactivation_otp_verified_pending_activation', 'user', 29, 'User completed reactivation OTP verification and is pending super admin activation', '2026-03-27 14:17:53'),
(105, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:31:38'),
(106, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:31:48'),
(107, 3, 'super_admin', 'user_reactivated', 'user', 29, 'Sent reactivation OTP to user ID 29', '2026-03-27 14:31:55'),
(108, 29, 'user', 'reactivation_otp_verified_pending_activation', 'user', 29, 'User completed reactivation OTP verification and is pending super admin activation', '2026-03-27 14:32:21'),
(109, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:40:49'),
(110, 3, 'super_admin', 'user_reactivated', 'user', 29, 'Sent reactivation OTP to user ID 29', '2026-03-27 14:40:56'),
(111, 29, 'student', 'account_reactivated_by_otp', 'user', 29, 'User completed reactivation OTP verification and was auto-logged in', '2026-03-27 14:41:18'),
(112, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:50:34'),
(113, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 14:47:37'),
(114, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 14:52:02'),
(115, 31, 'student', 'register_email_verified', 'user', 31, 'Completed registration via OTP email verification', '2026-03-30 15:02:16'),
(116, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:04:02'),
(117, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:06:23'),
(118, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:07:18'),
(119, 3, 'super_admin', 'user_activated', 'user', 31, 'Activated pending user ID 31', '2026-03-30 15:07:21'),
(120, 31, 'student', 'login_success', 'user', 31, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:07:58'),
(121, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:08:24'),
(122, 17, 'organizer', 'event_submitted_pending', 'event', 11, 'Submitted for admin approval: unli siomeow kaon', '2026-03-30 15:14:14'),
(123, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:14:31'),
(124, 31, 'student', 'login_success', 'user', 31, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:15:15'),
(125, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:15:39'),
(126, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:18:01'),
(127, 3, 'super_admin', 'user_role_changed', 'user', 29, 'Changed user ID 29 role from student to admin', '2026-03-30 15:18:22'),
(128, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:18:44'),
(129, 3, 'super_admin', 'user_role_changed', 'user', 29, 'Changed user ID 29 role from admin to organizer', '2026-03-30 15:18:48'),
(130, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:19:50'),
(131, 3, 'super_admin', 'user_reactivated', 'user', 29, 'Sent reactivation OTP to user ID 29', '2026-03-30 15:19:57'),
(132, 29, 'organizer', 'account_reactivated_by_otp', 'user', 29, 'User completed reactivation OTP verification and was auto-logged in', '2026-03-30 15:20:17'),
(133, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:22:55'),
(134, 29, 'organizer', 'event_submitted_pending', 'event', 12, 'Submitted for admin approval: cozy florist', '2026-03-30 15:23:50'),
(135, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:24:15'),
(136, 3, 'super_admin', 'event_approved', 'event', 12, 'Approved event ID 12', '2026-03-30 15:24:24'),
(137, 3, 'super_admin', 'event_approved', 'event', 12, 'Approved event ID 12', '2026-03-30 15:24:28'),
(138, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:24:40'),
(139, 28, 'admin', 'event_rejected', 'event', 11, 'Rejected event ID 11', '2026-03-30 15:24:54'),
(140, 14, 'student', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:25:16'),
(141, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:30:19'),
(142, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:34:27'),
(143, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:35:18'),
(144, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 15:37:02'),
(145, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 16:01:30'),
(146, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 16:08:59'),
(147, 28, 'admin', 'login_success', 'user', 28, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 16:09:29'),
(148, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:18:39'),
(149, 17, 'organizer', 'event_submitted_pending', 'event', 13, 'Submitted for admin approval: Basketball WLC FINALS', '2026-04-17 13:20:42'),
(150, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:21:12'),
(151, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:21:54'),
(152, 3, 'super_admin', 'user_role_changed', 'user', 16, 'Changed user ID 16 role from organizer to admin', '2026-04-17 13:22:15'),
(153, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:22:51'),
(154, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:23:22'),
(155, 14, 'student', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:23:38'),
(156, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:23:57'),
(157, 3, 'super_admin', 'user_role_changed', 'user', 14, 'Changed user ID 14 role from student to admin', '2026-04-17 13:24:04'),
(158, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:24:23'),
(159, 14, 'admin', 'event_approval_otp_sent', 'event', 13, 'Sent OTP via email', '2026-04-17 13:25:21'),
(160, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:25:57'),
(161, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:26:16'),
(162, 3, 'super_admin', 'user_role_changed', 'user', 29, 'Changed user ID 29 role from organizer to admin', '2026-04-17 13:26:40'),
(163, 29, 'admin', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:26:50'),
(164, 29, 'admin', 'event_approval_otp_sent', 'event', 13, 'Sent OTP via email', '2026-04-17 13:27:04'),
(165, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:27:34'),
(166, 3, 'super_admin', 'user_role_changed', 'user', 29, 'Changed user ID 29 role from admin to organizer', '2026-04-17 13:28:30'),
(167, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:28:42'),
(168, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:28:56'),
(169, 29, 'organizer', 'event_submitted_pending', 'event', 14, 'Submitted for admin approval: Basketball Finals WLC', '2026-04-17 13:29:33'),
(170, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:29:47'),
(171, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:39:57'),
(172, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:40:39'),
(173, 14, 'admin', 'event_approval_otp_sent', 'event', 14, 'Sent OTP via email', '2026-04-17 13:41:23'),
(174, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:41:34'),
(175, 29, 'organizer', 'event_approved_via_otp', 'event', 14, 'Organizer verified OTP and event became active', '2026-04-17 13:42:04'),
(176, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:43:55'),
(177, 3, 'super_admin', 'event_rejected', 'event', 13, 'Rejected event ID 13', '2026-04-17 13:45:50'),
(178, 3, 'super_admin', 'event_rejected', 'event', 13, 'Rejected event ID 13', '2026-04-17 13:45:53'),
(179, 3, 'super_admin', 'event_rejected', 'event', 13, 'Rejected event ID 13', '2026-04-17 13:45:57'),
(180, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:47:00'),
(181, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 13:54:59'),
(182, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 14:08:14'),
(183, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 14:11:05'),
(184, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 14:29:27'),
(185, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 14:29:42'),
(186, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 15:05:42'),
(187, 18, 'student', 'login_success', 'user', 18, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-17 15:05:54'),
(188, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 01:21:18'),
(189, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 01:24:30'),
(190, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 01:29:56'),
(191, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP 192.168.1.7 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-04-19 01:37:23'),
(192, 14, 'admin', 'staff_message_sent', 'user', 17, 'Staff message (admin↔organizer)', '2026-04-19 01:47:16'),
(193, 17, 'organizer', 'staff_message_sent', 'user', 14, 'Staff message (admin↔organizer)', '2026-04-19 01:47:28'),
(194, 17, 'organizer', 'staff_message_sent', 'user', 14, 'Staff message (admin↔organizer)', '2026-04-19 01:48:04'),
(195, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 01:53:30'),
(196, 17, 'organizer', 'login_success', 'user', 17, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 01:53:49'),
(197, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 03:17:45'),
(198, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 02:10:09'),
(199, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 02:58:26'),
(200, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 02:59:21'),
(201, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 03:02:15'),
(202, 3, 'super_admin', 'user_activated', 'user', 29, 'Activated pending user ID 29', '2026-04-20 03:02:20'),
(203, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 03:03:21'),
(204, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 03:04:02'),
(205, 3, 'super_admin', 'user_reactivated', 'user', 29, 'Sent reactivation OTP to user ID 29', '2026-04-20 03:12:49'),
(206, 29, 'organizer', 'account_reactivated_by_otp', 'user', 29, 'User completed reactivation OTP verification and was auto-logged in', '2026-04-20 03:13:08'),
(207, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 03:13:37'),
(208, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 03:17:26'),
(209, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 03:21:08'),
(210, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 23:40:36'),
(211, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 23:41:06'),
(212, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:10:13'),
(213, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:13:21'),
(214, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:43:21'),
(215, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:43:45'),
(216, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:44:29'),
(217, 29, 'organizer', 'event_submitted_pending', 'event', 15, 'Submitted for admin approval: sample project', '2026-04-22 00:45:08'),
(218, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:45:27'),
(219, 14, 'admin', 'event_approval_otp_sent', 'event', 15, 'Sent OTP via email', '2026-04-22 00:45:35'),
(220, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:45:46'),
(221, 29, 'organizer', 'event_approved_via_otp', 'event', 15, 'Organizer verified OTP and event became active', '2026-04-22 00:46:01'),
(222, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:48:14'),
(223, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:48:53'),
(224, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:49:08'),
(225, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:49:36'),
(226, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:50:07'),
(227, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 00:51:22'),
(228, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 01:19:03'),
(229, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 01:19:40'),
(230, 29, 'organizer', 'event_closed_by_organizer', 'event', 15, 'Closed event: sample project', '2026-04-22 01:30:51'),
(231, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 01:31:02'),
(232, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 02:03:02'),
(233, 32, 'student', 'register_email_verified', 'user', 32, 'Completed registration via OTP email verification', '2026-04-22 02:39:37'),
(234, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 02:39:57'),
(235, 3, 'super_admin', 'user_activated', 'user', 32, 'Activated pending user ID 32', '2026-04-22 02:40:02'),
(236, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 02:40:19'),
(237, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 02:42:39'),
(238, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 03:10:13'),
(239, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 03:10:40'),
(240, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 03:23:15'),
(241, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 03:23:46'),
(242, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 03:24:24'),
(243, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 03:24:52'),
(244, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 03:25:08'),
(245, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 03:25:32'),
(246, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 14:28:59'),
(247, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 14:29:40'),
(248, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-22 15:39:07'),
(249, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 00:19:17'),
(250, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 00:21:22'),
(251, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 00:25:56'),
(252, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 01:50:20'),
(253, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 02:07:32'),
(254, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 03:09:58'),
(255, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 07:32:19'),
(256, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 07:36:45'),
(257, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 13:37:09'),
(258, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 13:37:20');
INSERT INTO `activity_logs` (`id`, `actor_id`, `actor_role`, `action`, `target_type`, `target_id`, `details`, `created_at`) VALUES
(259, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 13:39:48'),
(260, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 23:19:29'),
(261, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 23:34:50'),
(262, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 23:35:13'),
(263, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 23:42:34'),
(264, 31, 'student', 'login_success', 'user', 31, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 23:42:46'),
(265, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 23:48:32'),
(266, 31, 'student', 'login_success', 'user', 31, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 23:59:08'),
(267, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-26 23:59:52'),
(268, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 00:06:26'),
(269, 31, 'student', 'login_success', 'user', 31, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 00:06:57'),
(270, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 00:07:27'),
(271, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 00:07:48'),
(272, 31, 'student', 'login_success', 'user', 31, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 00:18:08'),
(273, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 00:18:20'),
(274, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 06:14:17'),
(275, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 06:19:39'),
(276, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:27:50'),
(277, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:31:22'),
(278, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:32:36'),
(279, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:33:45'),
(280, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:36:07'),
(281, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:40:58'),
(282, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:41:20'),
(283, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:43:36'),
(284, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:46:36'),
(285, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:47:01'),
(286, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:50:54'),
(287, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 13:59:39'),
(288, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:00:27'),
(289, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:00:52'),
(290, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2026-04-27 14:16:56'),
(291, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2026-04-27 14:17:16'),
(292, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP 192.168.1.4 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-04-27 14:18:14'),
(293, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:22:13'),
(294, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:24:55'),
(295, 29, 'organizer', 'event_submitted_pending', 'event', 16, 'Submitted for admin approval: CICTE days', '2026-04-27 14:32:19'),
(296, 14, 'admin', 'event_approval_otp_sent', 'event', 16, 'Sent OTP via email', '2026-04-27 14:37:08'),
(297, 29, 'organizer', 'event_approved_via_otp', 'event', 16, 'Organizer verified OTP and event became active', '2026-04-27 14:40:49'),
(298, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:42:15'),
(299, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:47:23'),
(300, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:47:43'),
(301, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP 192.168.1.4 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-04-27 14:50:01'),
(302, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP 192.168.1.4 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-04-27 14:50:54'),
(303, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP 192.168.1.4 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-04-27 14:51:56'),
(304, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP 192.168.1.4 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-04-27 15:02:40'),
(305, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP 192.168.1.4 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-04-27 15:08:56'),
(306, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP 192.168.1.4 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-04-27 15:11:27'),
(307, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:45:31'),
(308, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP 192.168.1.4 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-04-27 15:53:58'),
(309, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP 192.168.1.4 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-04-27 16:06:02'),
(310, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP 192.168.1.4 | UA: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-04-27 16:09:19'),
(311, 33, 'student', 'register_email_verified', 'user', 33, 'Completed registration via OTP email verification', '2026-04-27 16:21:58'),
(312, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 16:22:00'),
(313, 3, 'super_admin', 'user_activated', 'user', 33, 'Activated pending user ID 33', '2026-04-27 16:22:29'),
(314, 33, 'student', 'login_success', 'user', 33, 'Successful login from IP 192.168.1.21 | UA: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-04-27 16:22:42'),
(315, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 16:24:01'),
(316, 33, 'student', 'login_success', 'user', 33, 'Successful login from IP 192.168.1.21 | UA: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-04-27 16:26:59'),
(317, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:07:53'),
(318, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 23:22:10'),
(319, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 23:22:30'),
(320, 29, 'organizer', 'event_updated', 'event', 16, 'Updated event and sent back for approval: CICTE day', '2026-04-27 23:23:07'),
(321, 29, 'organizer', 'event_updated', 'event', 16, 'Updated event and sent back for approval: CICTE days', '2026-04-27 23:36:32'),
(322, 29, 'organizer', 'event_updated', 'event', 16, 'Updated event and sent back for approval: CICTE day', '2026-04-27 23:36:43'),
(323, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 23:43:44'),
(324, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 23:52:51'),
(325, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 23:53:55'),
(326, 14, 'admin', 'event_approval_otp_sent', 'event', 16, 'Sent OTP via email', '2026-04-27 23:54:31'),
(327, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 23:54:48'),
(328, 29, 'organizer', 'event_approved_via_otp', 'event', 16, 'Organizer verified OTP and event became active', '2026-04-27 23:55:03'),
(329, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 23:55:17'),
(330, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 21:10:07'),
(331, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 21:10:38'),
(332, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 21:23:58'),
(333, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 21:24:32'),
(334, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 21:25:25'),
(335, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 21:25:50'),
(336, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 21:31:51'),
(337, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 21:32:21'),
(338, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 21:33:06'),
(339, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 21:38:13'),
(340, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:43:12'),
(341, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:43:23'),
(342, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:43:43'),
(343, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:43:57'),
(344, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:44:07'),
(345, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:44:43'),
(346, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:44:51'),
(347, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:45:48'),
(348, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:45:58'),
(349, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:46:09'),
(350, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:46:19'),
(351, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:46:36'),
(352, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:47:51'),
(353, 27, 'multimedia', 'login_success', 'user', 27, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 21:52:44'),
(354, 32, 'student', 'login_success', 'user', 32, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 22:03:48'),
(355, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 22:04:29'),
(356, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 22:05:10'),
(357, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 22:06:04'),
(358, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 22:35:26'),
(359, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 22:44:37'),
(360, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1:**** | UA: [masked]', '2026-04-28 22:49:00'),
(361, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1:**** | UA: [masked]', '2026-05-19 14:45:39'),
(362, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1:**** | UA: [masked]', '2026-05-19 15:00:39'),
(363, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 16:05:14'),
(364, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 16:05:34'),
(365, 21, 'organizer', 'login_success', 'user', 21, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 16:08:10'),
(366, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 16:09:01'),
(367, 29, 'organizer', 'event_submitted_pending', 'event', 17, 'Submitted for admin approval: event for june', '2026-06-01 16:44:31'),
(368, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 16:44:45'),
(369, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 16:45:00'),
(370, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 16:46:17'),
(371, 29, 'organizer', 'event_cancelled_by_organizer', 'event', 17, 'Cancelled event: event for june', '2026-06-01 16:52:17'),
(372, 29, 'organizer', 'event_submitted_pending', 'event', 18, 'Submitted for admin approval: june try 3 days', '2026-06-01 16:57:47'),
(373, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 17:12:57'),
(374, 14, 'admin', 'event_approval_otp_sent', 'event', 18, 'Sent OTP via email', '2026-06-01 17:13:24'),
(375, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 17:13:47'),
(376, 29, 'organizer', 'event_approved_via_otp', 'event', 18, 'Organizer verified OTP and event became active', '2026-06-01 17:14:02'),
(377, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-02 02:38:13'),
(378, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-02 02:39:22'),
(379, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-02 02:40:45'),
(380, 29, 'organizer', 'event_submitted_pending', 'event', 19, 'Submitted for admin approval: another june sample', '2026-06-02 02:42:11'),
(381, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-02 02:42:39'),
(382, 14, 'admin', 'event_rejected', 'event', 19, 'Event 19: pending -> rejected', '2026-06-02 02:42:53'),
(383, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-02 02:43:27'),
(384, 3, 'super_admin', 'login_success', 'user', 3, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-02 14:10:07'),
(385, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-02 14:10:20'),
(386, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-02 15:32:08'),
(387, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-03 00:57:21'),
(388, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-03 01:00:20'),
(389, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-03 01:13:04'),
(390, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-03 16:48:58'),
(391, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-04 02:51:20'),
(392, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-04 04:03:27'),
(393, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-04 04:50:42'),
(394, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 05:15:40'),
(395, 19, 'student', 'login_success', 'user', 19, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 05:20:59'),
(396, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 05:21:23'),
(397, 29, 'organizer', 'event_submitted_pending', 'event', 20, 'Submitted for admin approval: ms intrams', '2026-06-06 05:24:24'),
(398, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 05:24:35'),
(399, 14, 'admin', 'event_approval_otp_sent', 'event', 20, 'Sent OTP via email', '2026-06-06 05:27:26'),
(400, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 05:27:51'),
(401, 29, 'organizer', 'event_approved_via_otp', 'event', 20, 'Organizer verified OTP and event became active', '2026-06-06 05:28:02'),
(402, 14, 'admin', 'login_success', 'user', 14, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 06:06:51'),
(403, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 07:51:38'),
(404, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-07 14:12:06'),
(405, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-07 14:15:35'),
(406, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-07 14:16:05'),
(407, 29, 'organizer', 'login_success', 'user', 29, 'Successful login from IP ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-07 14:56:31');

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `notify_email_new_event` tinyint(1) NOT NULL DEFAULT 1,
  `notify_pending_reminder` tinyint(1) NOT NULL DEFAULT 1,
  `notification_retention_days` int(11) NOT NULL DEFAULT 30,
  `otp_required_sensitive_actions` tinyint(1) NOT NULL DEFAULT 1,
  `otp_expiry_minutes` int(11) NOT NULL DEFAULT 10,
  `otp_max_attempts` int(11) NOT NULL DEFAULT 5,
  `event_lead_days` int(11) NOT NULL DEFAULT 3,
  `auto_complete_past_events` tinyint(1) NOT NULL DEFAULT 1,
  `max_event_photos` int(11) NOT NULL DEFAULT 10,
  `max_upload_size_mb` int(11) NOT NULL DEFAULT 10,
  `session_timeout_minutes` int(11) NOT NULL DEFAULT 30,
  `force_relogin_sensitive_actions` tinyint(1) NOT NULL DEFAULT 1,
  `default_dashboard_view` varchar(20) NOT NULL DEFAULT 'calendar',
  `calendar_legend_visible` tinyint(1) NOT NULL DEFAULT 1,
  `table_page_size` int(11) NOT NULL DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `photo` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `attended_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `end_time_na` tinyint(1) NOT NULL DEFAULT 0,
  `location` varchar(100) DEFAULT NULL,
  `organizer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','active','rejected','closed') DEFAULT 'pending',
  `department` varchar(800) NOT NULL DEFAULT 'ALL',
  `checkin_token` varchar(64) DEFAULT NULL,
  `reject_reason` text DEFAULT NULL,
  `registration_mode` varchar(20) NOT NULL DEFAULT 'rsvp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `date`, `end_date`, `start_time`, `end_time`, `end_time_na`, `location`, `organizer_id`, `created_at`, `status`, `department`, `checkin_token`, `reject_reason`, `registration_mode`) VALUES
(1, 'Sample Orientation', 'Orientation for new students', '2025-12-20', NULL, NULL, NULL, 0, 'Main Auditorium', 17, '2025-12-18 06:30:41', 'closed', 'ALL', 'aad31a873785a99f2d9af0a6166783ad', NULL, 'rsvp'),
(2, 'sample', 'sir', '2026-02-28', NULL, NULL, NULL, 0, 'wlc', 17, '2026-01-27 03:54:13', 'closed', 'ALL', NULL, NULL, 'rsvp'),
(3, 'sample2', 'hehe', '2026-01-30', NULL, NULL, NULL, 0, 'western leyte college', 17, '2026-01-27 15:46:49', 'closed', 'BSHM', NULL, NULL, 'rsvp'),
(4, 'For All', 'For all sample', '2026-02-04', NULL, NULL, NULL, 0, 'wlc', 17, '2026-01-28 13:30:13', 'closed', 'ALL', NULL, NULL, 'rsvp'),
(6, 'intrams badminton', 'intrams badminton- conahs vs cicte 9:00 am- 9:00 pm', '2026-03-14', NULL, NULL, NULL, 0, 'Western Leyte College, Ormoc City', 17, '2026-03-09 12:03:37', 'closed', 'ALL', 'd2a6b8f7ef3747737d80adb9b49dbc74', NULL, 'rsvp'),
(7, 'intrams basket', 'basker', '2026-03-14', NULL, NULL, NULL, 0, 'Western Leyte College, Ormoc City', 17, '2026-03-13 04:18:19', '', 'ALL', '4bf23f1b7a9438bdd999125822140d48', NULL, 'rsvp'),
(8, 'intrams swimming', 'swimming', '2026-03-14', NULL, NULL, NULL, 0, 'Western Leyte College, Ormoc City', 17, '2026-03-13 04:19:32', 'closed', 'ALL', NULL, NULL, 'rsvp'),
(9, 'dan vs adrianne sumbagay', 'sumbagay', '2026-03-16', NULL, '13:00:00', '14:34:00', 0, 'western leyte college', 17, '2026-03-14 15:35:35', 'closed', 'ALL', '3e755df4c20ca0035def27f82e30f910', NULL, 'rsvp'),
(10, 'call lablab', 'call lablab', '2026-03-27', NULL, '12:26:00', NULL, 0, 'Superdome, I. Larrazabal Boulevard, South, Ormoc City Proper, Ormoc, Leyte, Eastern Visayas, 6541, P', 17, '2026-03-26 15:27:41', 'closed', 'ALL', '507494106f5dbb3f99450ba794883503', NULL, 'rsvp'),
(11, 'unli siomeow kaon', 'unli', '2026-03-31', NULL, '12:13:00', NULL, 0, 'Superdome, I. Larrazabal Boulevard, South, Ormoc City Proper, Ormoc, Leyte, Eastern Visayas, 6541, P', 17, '2026-03-30 15:14:14', 'rejected', 'ALL', 'ba34b6270ff9f63007f806a7a64a6b34', '', 'rsvp'),
(12, 'cozy florist', 'florist', '2026-04-01', NULL, '12:23:00', NULL, 0, 'Superdome, I. Larrazabal Boulevard, South, Ormoc City Proper, Ormoc, Leyte, Eastern Visayas, 6541, P', 29, '2026-03-30 15:23:50', 'closed', 'ALL', '16f97daaa46dfae80c1cd82efc3bcc38', NULL, 'rsvp'),
(13, 'Basketball WLC FINALS', 'Conahs vs COED', '2026-04-18', NULL, '10:20:00', NULL, 0, 'Western Leyte College, Bonifacio Street, West, Ormoc City Proper, Ormoc, Leyte, Eastern Visayas, 654', 17, '2026-04-17 13:20:42', 'rejected', 'ALL', '9cd204e369d5ba461179fda9421aceac', 'reject redundanet event', 'rsvp'),
(14, 'Basketball Finals WLC', 'CONAHS VS COED', '2026-04-18', NULL, '22:30:00', NULL, 0, 'Western Leyte College, Bonifacio Street, West, Ormoc City Proper, Ormoc, Leyte, Eastern Visayas, 654', 29, '2026-04-17 13:29:33', 'closed', 'ALL', '26b3c462771fd4948f7da77e9470bbc3', NULL, 'rsvp'),
(15, 'sample project', 'sample', '2026-04-22', NULL, '08:49:00', NULL, 0, 'Western Leyte College, Bonifacio Street, West, Ormoc City Proper, Ormoc, Leyte, Eastern Visayas, 654', 29, '2026-04-22 00:45:08', 'closed', 'ALL', 'cb8489fc8ed6365e5caa83149d3b5b26', NULL, 'rsvp'),
(16, 'CICTE day', 'CICTE days school year 2025-2026', '2026-04-30', NULL, '10:30:00', NULL, 0, 'Western Leyte College, Bonifacio Street, West, Ormoc City Proper, Ormoc, Leyte, Eastern Visayas, 654', 29, '2026-04-27 14:32:19', 'closed', 'ALL', 'ddec591521a7b9a72a8a1ecbf1bd972c', NULL, 'rsvp'),
(17, 'event for june', 'june try 1 week event intrams', '2026-06-02', NULL, '10:30:00', '00:44:00', 0, 'Western Leyte College, Bonifacio Street, West, Ormoc City Proper, Ormoc, Leyte, Eastern Visayas, 654', 29, '2026-06-01 16:44:31', 'closed', 'ALL', '02651c31a0641d9a8ce8aa51e41623fe', NULL, 'rsvp'),
(18, 'june try 3 days', '3 days intrams', '2026-06-02', NULL, '00:00:00', '17:00:00', 0, 'Intrams 2026', 29, '2026-06-01 16:57:47', 'closed', 'ALL', '0bd03673fe51adb2354b78d6d4920122', NULL, 'rsvp'),
(19, 'another june sample', 'sample', '2026-06-02', NULL, '10:41:00', '22:41:00', 0, 'Tide Embankment Boulevard, Mercado, Poblacion, Basey, Samar, Eastern Visayas, 6720, Philippines', 29, '2026-06-02 02:42:11', 'rejected', 'ALL', '555798c40037114819a3d0826d09d1c9', '', 'rsvp'),
(20, 'ms intrams', 'ms intrams', '2026-06-12', NULL, '08:00:00', '16:00:00', 0, 'Superdome, I. Larrazabal Boulevard, South, Ormoc City Proper, Ormoc, Leyte, Eastern Visayas, 6541, P', 29, '2026-06-06 05:24:24', 'active', 'ALL', '8fcc7f2aae2728c8a43532a08c181caf', NULL, 'rsvp');

-- --------------------------------------------------------

--
-- Table structure for table `event_approval_otps`
--

CREATE TABLE `event_approval_otps` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `organizer_id` int(11) NOT NULL,
  `delivery_method` enum('email','phone') NOT NULL,
  `delivery_target` varchar(120) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_approval_otps`
--

INSERT INTO `event_approval_otps` (`id`, `event_id`, `organizer_id`, `delivery_method`, `delivery_target`, `otp_hash`, `expires_at`, `used_at`, `verified_by`, `created_by`, `created_at`) VALUES
(1, 10, 17, 'phone', '09085210452', '$2y$10$UnmMeUCmMGH7mrbsog8NSekd3ZrSMHg4pn6RTFFwhi2UC8Q6C26HC', '2026-03-26 16:39:13', '2026-03-26 23:29:38', NULL, 28, '2026-03-26 15:29:13'),
(2, 10, 17, 'phone', '09085210452', '$2y$10$5aqygCSqsC0DiKbLLv3vw.0aC8KCT/.AK9Z0FEFV7XPkEosadZTs6', '2026-03-26 16:39:38', '2026-03-26 23:32:02', NULL, 28, '2026-03-26 15:29:39'),
(3, 10, 17, 'phone', '09085210452', '$2y$10$G1cAcn.v8.G3/AutDlwF9OjRBg23GsZFxi/eqoxMax29E1qPbb8MO', '2026-03-26 16:42:02', '2026-03-26 23:32:58', NULL, 28, '2026-03-26 15:32:02'),
(4, 10, 17, 'phone', '09085210452', '$2y$10$4GnjaghMuh3yraujaTcjW.yYWWwWBsuF.td5ESOpAsvaiYJRTPfJ6', '2026-03-26 16:42:58', '2026-03-26 23:33:07', NULL, 28, '2026-03-26 15:32:58'),
(5, 10, 17, 'phone', '09085210452', '$2y$10$cJDIOhndapq6oVKXif7hCOKn2JetJZYIvKhoKirW5H9eb1D1UvaUm', '2026-03-26 16:43:07', '2026-03-26 23:33:50', NULL, 28, '2026-03-26 15:33:07'),
(6, 10, 17, 'phone', '09085210452', '$2y$10$4xWdWrc65ewKtaJPFzoazOAaEvJsqARRRt9QSzs7VQnnIAE81qeTy', '2026-03-26 16:43:50', '2026-03-26 23:34:01', NULL, 28, '2026-03-26 15:33:50'),
(7, 10, 17, 'phone', '09085210452', '$2y$10$OHy3Rz1oInDzBRSwhI01HulBd4l1Uz9D2KhBThiEFbjuqh1a9SBM6', '2026-03-26 16:44:01', '2026-03-26 23:35:17', NULL, 28, '2026-03-26 15:34:01'),
(8, 10, 17, 'phone', '09085210452', '$2y$10$Y/BqGOyZbz7X8BL5LUha5OCWKJ5oOGWLRTL2MZNwhO21lGZ9q/9ia', '2026-03-26 16:45:17', '2026-03-26 23:35:33', NULL, 28, '2026-03-26 15:35:17'),
(9, 10, 17, 'phone', '09085210452', '$2y$10$3bPF8t6STlgy7Wh6cdQYWeoTJzsq4qsACrtXerx1JauL9LvNyPbKe', '2026-03-26 16:45:33', '2026-03-26 23:35:55', NULL, 28, '2026-03-26 15:35:33'),
(10, 10, 17, 'phone', '09085210452', '$2y$10$ANvS/huT5AfUyh/QiNIbFuUwlNcgnmcSLSDM9.NdopzpfK.UsPTJq', '2026-03-26 16:45:55', '2026-03-26 23:36:54', NULL, 28, '2026-03-26 15:35:56'),
(11, 10, 17, 'phone', '09085210452', '$2y$10$B.8JR/s5IN9r6rbh2vPx9.uyovWxhYKAzNA.RxpIKx4FXlDttyv1O', '2026-03-26 16:46:54', '2026-03-26 23:37:14', NULL, 28, '2026-03-26 15:36:54'),
(12, 10, 17, 'phone', '09085210452', '$2y$10$AWRqR366yLEw7wNAi54bMOBpKrQz43vJ59sFirzPlC9DeNDRH1X7C', '2026-03-26 16:47:14', '2026-03-26 23:38:29', NULL, 28, '2026-03-26 15:37:14'),
(13, 10, 17, 'phone', '09085210452', '$2y$10$J/wKZjY2zqeH3rLSHZVgG.BsIAKgxdTaD5USaamu54m1G7JTI03mO', '2026-03-26 16:48:29', '2026-03-26 23:44:24', NULL, 28, '2026-03-26 15:38:29'),
(14, 10, 17, 'phone', '09085210452', '$2y$10$9BgGv2t1tN6cIAEO23ptFueGys/WtLPuNu1s1ZakKdvRCarb/RJ9G', '2026-03-26 16:54:24', '2026-03-26 23:53:51', NULL, 28, '2026-03-26 15:44:24'),
(15, 10, 17, 'phone', '09085210452', '$2y$10$Wl/lhg11Wcveuu/X1j4ohe6dPIC0kNRgfoKgc5QZlhXAlMj8V9Etu', '2026-03-26 17:03:51', '2026-03-26 23:58:45', NULL, 28, '2026-03-26 15:53:51'),
(16, 10, 17, 'email', 'kristianjamessalgado@gmail.com', '$2y$10$fgWaH1VFgYokccEupdDRn.9YqJRmrKUdNd554HZ0IZdD1iYzqAfvu', '2026-03-26 17:08:45', '2026-03-27 00:26:52', NULL, 28, '2026-03-26 15:58:45'),
(17, 10, 17, 'email', 'bojiking31@gmail.com', '$2y$10$BzEPctd63Vw/rQBGjJ1Px.QZnus5quR7L7s8ThtR1IhFo1fQ5eKcy', '2026-03-26 17:36:52', '2026-03-27 00:31:32', NULL, 28, '2026-03-26 16:26:52'),
(18, 10, 17, 'email', 'bojiking31@gmail.com', '$2y$10$3Eh0Sp2BkJl/oSWHXSJGTurguElCucCTtTJC3/2pUfDiBzl6hKvyC', '2026-03-26 17:41:32', '2026-03-27 00:32:49', NULL, 28, '2026-03-26 16:31:32'),
(19, 10, 17, 'email', 'bojiking31@gmail.com', '$2y$10$oFm/zsduFT/v3Gw6Y/GQLO7FH5Zhf5GcxCHqrZo1.WEm16kGo6aL.', '2026-03-26 17:42:49', '2026-03-27 00:33:41', NULL, 28, '2026-03-26 16:32:49'),
(20, 13, 17, 'email', 'organizer2@gmail.com', '$2y$10$XhwtdRTHfJ8nFam0NGwCjO5kXcO/NEfzk8ej4Ooh6s6GsRou72.Qm', '2026-04-17 15:35:16', '2026-04-17 21:27:01', NULL, 14, '2026-04-17 13:25:16'),
(21, 13, 17, 'email', 'organizer2@gmail.com', '$2y$10$I1k2EAyJxxt4x9E2w.gp0O2KgIci.E96xDkjo1TWLAE6UXALPlo9a', '2026-04-17 15:37:01', NULL, NULL, 29, '2026-04-17 13:27:01'),
(22, 14, 29, 'email', 'bojiking31@gmail.com', '$2y$10$8kHzNP4TL.maW18EJvEwTekr4OpL7Ak2gDRcMFzSuIvXcWtNB/woi', '2026-04-17 15:51:20', '2026-04-17 21:42:04', NULL, 14, '2026-04-17 13:41:20'),
(23, 15, 29, 'email', 'bojiking31@gmail.com', '$2y$10$0gOrJkNgzycGRzpoG7wWNuqeif2RTjKX4hVAmhahIq7IfcB.eaDra', '2026-04-22 02:55:32', '2026-04-22 08:46:01', NULL, 14, '2026-04-22 00:45:32'),
(24, 16, 29, 'email', 'bojiking31@gmail.com', '$2y$10$N1.nIwLFP2i7jBBe2Yl87.jyTSRYugSkVF05Qh7oudCZcvjbcpsM.', '2026-04-27 16:47:04', '2026-04-27 22:40:49', NULL, 14, '2026-04-27 14:37:04'),
(25, 16, 29, 'email', 'bojiking31@gmail.com', '$2y$10$X.BPuij0DqfLZzOPIWjAf.c327J/mkZO4StAc9slwwv0fRLtWDjpS', '2026-04-28 02:04:26', '2026-04-28 07:55:03', NULL, 14, '2026-04-27 23:54:26'),
(26, 18, 29, 'email', 'bojiking31@gmail.com', '$2y$10$Znp3eNxpkT1StgI8FFtDn.zqNcyhqC3mczsF0rdakR3d3/KKagkTm', '2026-06-01 19:23:20', '2026-06-02 01:14:02', NULL, 14, '2026-06-01 17:13:20'),
(27, 20, 29, 'email', 'bojiking31@gmail.com', '$2y$10$tasYSoiOvK5VIJBjFbJufucp5UbowBp.IBydjbGOn1RiT1GKUPbMO', '2026-06-06 13:37:23', '2026-06-06 13:28:02', NULL, 14, '2026-06-06 05:27:23');

-- --------------------------------------------------------

--
-- Table structure for table `event_checkin_device_locks`
--

CREATE TABLE `event_checkin_device_locks` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_hash` varchar(128) NOT NULL,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_lat` decimal(10,7) DEFAULT NULL,
  `last_lng` decimal(10,7) DEFAULT NULL,
  `last_accuracy` float DEFAULT NULL,
  `last_geo_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_checkin_device_locks`
--

INSERT INTO `event_checkin_device_locks` (`id`, `event_id`, `user_id`, `device_hash`, `first_seen_at`, `last_seen_at`, `last_lat`, `last_lng`, `last_accuracy`, `last_geo_at`) VALUES
(1, 15, 19, '3fbecd9c315bd85894abcbc56c25b9be077a48a440262ed75c7ff249c939d543', '2026-04-22 08:50:51', '2026-04-22 08:50:51', NULL, NULL, NULL, '2026-04-22 08:50:51'),
(2, 16, 32, 'TW96aWxsYS81LjAgKGlQaG9uZTsgQ1BVIGlQaG9uZSBPUyAxOF82XzIgbGlrZSBNYWMgT1MgWCkgQXBwbGVXZWJLaXQvNjA1', '2026-04-27 23:12:13', '2026-04-27 23:12:13', NULL, NULL, NULL, '2026-04-27 23:12:13'),
(3, 16, 33, 'TW96aWxsYS81LjAgKExpbnV4OyBBbmRyb2lkIDEwOyBLKSBBcHBsZVdlYktpdC81MzcuMzYgKEtIVE1MLCBsaWtlIEdlY2tv', '2026-04-28 00:27:07', '2026-04-28 00:27:07', NULL, NULL, NULL, '2026-04-28 00:27:07');

-- --------------------------------------------------------

--
-- Table structure for table `event_day_sessions`
--

CREATE TABLE `event_day_sessions` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `title` varchar(150) NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `location` varchar(255) NOT NULL DEFAULT '',
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','cancelled','delayed') NOT NULL DEFAULT 'scheduled',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `max_capacity` int(11) DEFAULT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `checkin_token` varchar(64) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_day_sessions`
--

INSERT INTO `event_day_sessions` (`id`, `event_id`, `schedule_date`, `title`, `category`, `location`, `notes`, `status`, `latitude`, `longitude`, `start_time`, `end_time`, `max_capacity`, `contact_name`, `contact_phone`, `checkin_token`, `sort_order`, `created_at`) VALUES
(1, 18, '2026-06-02', 'badminton', NULL, '0', NULL, 'scheduled', 11.0142478, 124.5949837, '08:00:00', '17:00:00', NULL, NULL, NULL, '2b471e27a8c6118ad99129919f844a17', 0, '2026-06-01 17:23:04'),
(3, 18, '2026-06-02', 'volleyball', 'Sports', 'Superdome, I. Larrazabal Boulevard, South, Ormoc City Proper, Ormoc, Leyte, Eastern Visayas, 6541, Philippines', 'secret', 'scheduled', 11.0043811, 124.6097216, '02:40:00', '14:40:00', NULL, 'KRISTIAN JAMES SALGADO', NULL, '4bfbde76dbfd69b980584315eddf16d4', 0, '2026-06-01 18:40:28');

-- --------------------------------------------------------

--
-- Table structure for table `event_day_session_attendance`
--

CREATE TABLE `event_day_session_attendance` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `checked_in_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_day_session_rsvps`
--

CREATE TABLE `event_day_session_rsvps` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_feedback`
--

CREATE TABLE `event_feedback` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `comment` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_feedback`
--

INSERT INTO `event_feedback` (`id`, `event_id`, `user_id`, `rating`, `comment`, `is_anonymous`, `created_at`) VALUES
(1, 15, 19, 5, 'it was nice', 1, '2026-04-22 03:22:59');

-- --------------------------------------------------------

--
-- Table structure for table `event_photos`
--

CREATE TABLE `event_photos` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'published' COMMENT 'draft|published',
  `published_at` datetime DEFAULT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_photos`
--

INSERT INTO `event_photos` (`id`, `event_id`, `uploaded_by`, `file_path`, `status`, `published_at`, `caption`, `created_at`) VALUES
(1, 2, 27, 'uploads/events/2/20260205_075003_69843d9ba58ee_finale.png', 'published', NULL, NULL, '2026-02-05 06:50:03'),
(3, 4, 27, 'uploads/events/20260205_175211_6984cabb37ce8_RobloxScreenShot20250601_192737133.png', 'published', NULL, NULL, '2026-02-05 16:52:11'),
(4, 2, 27, 'uploads/events/20260309_080343_69ae70cfe770f_Screenshot_2024-09-03_002302.png', 'published', NULL, NULL, '2026-03-09 07:03:43'),
(5, 4, 27, 'uploads/events/all/20260309_095140_69ae8a1c7eeff_Screenshot_2024-09-17_181103.png', 'published', NULL, NULL, '2026-03-09 08:51:40'),
(8, 6, 27, 'uploads/events/all/20260423_034557_69e979d5a3f0a_finale.png', 'published', '2026-04-23 09:46:03', NULL, '2026-04-23 01:45:57'),
(9, 6, 27, 'uploads/events/all/20260423_034959_69e97ac71cd7d_Screenshot_2024-08-04_193631.png', 'published', '2026-04-23 09:50:09', NULL, '2026-04-23 01:49:59'),
(10, 6, 27, 'uploads/events/all/20260423_051009_69e98d9146615_Screenshot_2024-12-19_212004.png', 'published', '2026-04-23 11:10:14', NULL, '2026-04-23 03:10:09'),
(11, 9, 27, 'uploads/events/all/20260423_093233_69e9cb1175619_Screenshot_2025-01-10_100811.png', 'published', '2026-04-23 15:33:18', NULL, '2026-04-23 07:32:33'),
(12, 9, 27, 'uploads/events/all/20260423_093301_69e9cb2d352b9_Screenshot_2025-02-13_211534.png', 'published', '2026-04-23 15:33:18', NULL, '2026-04-23 07:33:01'),
(13, 13, 27, 'uploads/events/all/20260423_093307_69e9cb334d8fb_Screenshot_2025-02-20_091340.png', 'published', '2026-04-23 15:33:15', NULL, '2026-04-23 07:33:07'),
(14, 13, 27, 'uploads/events/all/20260423_093313_69e9cb39a6214_Screenshot_2024-12-07_085145.png', 'published', '2026-04-23 15:33:15', NULL, '2026-04-23 07:33:13'),
(15, 11, 27, 'uploads/events/all/20260423_093324_69e9cb441c568_Screenshot_2024-12-13_220021.png', 'published', '2026-04-23 15:33:26', NULL, '2026-04-23 07:33:24'),
(16, 7, 27, 'uploads/events/all/20260423_093707_69e9cc239b7b6_Screenshot_2025-05-15_194855.png', 'published', '2026-04-23 15:37:20', NULL, '2026-04-23 07:37:07'),
(17, 7, 27, 'uploads/events/all/20260423_093716_69e9cc2c3b575_Screenshot_2025-01-12_143033.png', 'published', '2026-04-23 15:37:20', NULL, '2026-04-23 07:37:16'),
(21, 16, 27, 'uploads/events/all/20260427_174943_69ef8597f173a_elderly.png', 'draft', NULL, NULL, '2026-04-27 15:49:43');

-- --------------------------------------------------------

--
-- Table structure for table `event_schedule_dates`
--

CREATE TABLE `event_schedule_dates` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `end_time_na` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_schedule_dates`
--

INSERT INTO `event_schedule_dates` (`id`, `event_id`, `schedule_date`, `start_time`, `end_time`, `end_time_na`) VALUES
(1, 17, '2026-06-02', NULL, NULL, 1),
(2, 17, '2026-06-03', NULL, NULL, 1),
(3, 17, '2026-06-04', NULL, NULL, 1),
(4, 17, '2026-06-05', NULL, NULL, 1),
(5, 17, '2026-06-06', NULL, NULL, 1),
(6, 17, '2026-06-08', NULL, '00:44:00', 0),
(7, 18, '2026-06-02', '00:00:00', '16:56:00', 0),
(8, 18, '2026-06-03', '08:00:00', NULL, 1),
(9, 18, '2026-06-04', '08:00:00', '17:00:00', 0),
(10, 19, '2026-06-02', '10:41:00', NULL, 0),
(11, 19, '2026-06-03', '10:41:00', '22:41:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `event_tickets`
--

CREATE TABLE `event_tickets` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `ticket_type_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `ticket_code` varchar(24) NOT NULL,
  `checkin_token` varchar(64) NOT NULL,
  `status` enum('valid','used','cancelled') NOT NULL DEFAULT 'valid',
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_ticket_types`
--

CREATE TABLE `event_ticket_types` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) DEFAULT NULL COMMENT 'NULL = unlimited',
  `sold_count` int(11) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `event_id`, `read_at`, `created_at`) VALUES
(1, 1, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"call lablab\" for approval.', 10, NULL, '2026-03-26 15:27:41'),
(2, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"call lablab\" for approval.', 10, NULL, '2026-03-26 15:27:41'),
(3, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"call lablab\" for approval.', 10, '2026-03-26 23:29:52', '2026-03-26 15:27:41'),
(24, 1, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"call lablab\" is now active.', 10, NULL, '2026-03-26 16:33:41'),
(25, 3, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"call lablab\" is now active.', 10, NULL, '2026-03-26 16:33:41'),
(26, 28, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"call lablab\" is now active.', 10, NULL, '2026-03-26 16:33:41'),
(27, 1, 'account_pending_approval', 'New account pending approval', 'Email-verified registration waiting approval: deane gwapo (student)', NULL, NULL, '2026-03-27 10:21:46'),
(28, 3, 'account_pending_approval', 'New account pending approval', 'Email-verified registration waiting approval: deane gwapo (student)', NULL, NULL, '2026-03-27 10:21:46'),
(30, 1, 'reactivation_ready', 'Reactivation OTP verified', 'User verified reactivation OTP and is ready for activation: bojiking31@gmail.com', NULL, NULL, '2026-03-27 14:15:27'),
(31, 3, 'reactivation_ready', 'Reactivation OTP verified', 'User verified reactivation OTP and is ready for activation: bojiking31@gmail.com', NULL, NULL, '2026-03-27 14:15:27'),
(32, 1, 'reactivation_ready', 'Reactivation OTP verified', 'User verified reactivation OTP and is ready for activation: bojiking31@gmail.com', NULL, NULL, '2026-03-27 14:17:53'),
(33, 3, 'reactivation_ready', 'Reactivation OTP verified', 'User verified reactivation OTP and is ready for activation: bojiking31@gmail.com', NULL, NULL, '2026-03-27 14:17:53'),
(34, 1, 'reactivation_ready', 'Reactivation OTP verified', 'User verified reactivation OTP and is ready for activation: bojiking31@gmail.com', NULL, NULL, '2026-03-27 14:32:21'),
(35, 3, 'reactivation_ready', 'Reactivation OTP verified', 'User verified reactivation OTP and is ready for activation: bojiking31@gmail.com', NULL, NULL, '2026-03-27 14:32:21'),
(36, 1, 'account_pending_approval', 'New account pending approval', 'Email-verified registration waiting approval: blademale (student)', NULL, NULL, '2026-03-30 15:02:16'),
(37, 3, 'account_pending_approval', 'New account pending approval', 'Email-verified registration waiting approval: blademale (student)', NULL, NULL, '2026-03-30 15:02:16'),
(38, 1, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"unli siomeow kaon\" for approval.', 11, NULL, '2026-03-30 15:14:14'),
(39, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"unli siomeow kaon\" for approval.', 11, NULL, '2026-03-30 15:14:14'),
(40, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"unli siomeow kaon\" for approval.', 11, NULL, '2026-03-30 15:14:14'),
(41, 1, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"cozy florist\" for approval.', 12, NULL, '2026-03-30 15:23:50'),
(42, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"cozy florist\" for approval.', 12, NULL, '2026-03-30 15:23:50'),
(43, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"cozy florist\" for approval.', 12, NULL, '2026-03-30 15:23:50'),
(46, 17, 'event_rejected', 'Event rejected', 'Your event \"unli siomeow kaon\" was rejected.', 11, '2026-04-19 09:24:33', '2026-03-30 15:24:54'),
(47, 1, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"Basketball WLC FINALS\" for approval.', 13, NULL, '2026-04-17 13:20:42'),
(48, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"Basketball WLC FINALS\" for approval.', 13, NULL, '2026-04-17 13:20:42'),
(49, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"Basketball WLC FINALS\" for approval.', 13, NULL, '2026-04-17 13:20:42'),
(50, 17, 'event_approval_otp', 'Event approval OTP', 'Your OTP for event \"Basketball WLC FINALS\" is 880060. It expires in 10 minutes.', 13, '2026-04-19 09:24:33', '2026-04-17 13:25:16'),
(51, 17, 'event_approval_otp', 'Event approval OTP', 'Your OTP for event \"Basketball WLC FINALS\" is 740463. It expires in 10 minutes.', 13, '2026-04-19 09:24:33', '2026-04-17 13:27:01'),
(52, 1, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"Basketball Finals WLC\" for approval.', 14, NULL, '2026-04-17 13:29:33'),
(53, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"Basketball Finals WLC\" for approval.', 14, NULL, '2026-04-17 13:29:33'),
(55, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"Basketball Finals WLC\" for approval.', 14, NULL, '2026-04-17 13:29:33'),
(58, 1, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"Basketball Finals WLC\" is now active.', 14, NULL, '2026-04-17 13:42:04'),
(59, 3, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"Basketball Finals WLC\" is now active.', 14, NULL, '2026-04-17 13:42:04'),
(61, 28, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"Basketball Finals WLC\" is now active.', 14, NULL, '2026-04-17 13:42:04'),
(62, 17, 'event_rejected', 'Event rejected', 'Your event \"Basketball WLC FINALS\" was rejected. Reason: reject redundanet event', 13, '2026-04-19 09:24:33', '2026-04-17 13:45:50'),
(63, 17, 'event_rejected', 'Event rejected', 'Your event \"Basketball WLC FINALS\" was rejected. Reason: reject redundanet event', 13, '2026-04-19 09:24:33', '2026-04-17 13:45:53'),
(64, 17, 'event_rejected', 'Event rejected', 'Your event \"Basketball WLC FINALS\" was rejected. Reason: reject redundanet event', 13, '2026-04-19 09:24:33', '2026-04-17 13:45:57'),
(65, 17, 'staff_message', 'Message from Admin', 'Hi', NULL, NULL, '2026-04-19 01:47:16'),
(68, 1, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"sample project\" for approval.', 15, NULL, '2026-04-22 00:45:08'),
(69, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"sample project\" for approval.', 15, NULL, '2026-04-22 00:45:08'),
(71, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"sample project\" for approval.', 15, NULL, '2026-04-22 00:45:08'),
(74, 1, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"sample project\" is now active.', 15, NULL, '2026-04-22 00:46:01'),
(75, 3, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"sample project\" is now active.', 15, NULL, '2026-04-22 00:46:01'),
(77, 28, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"sample project\" is now active.', 15, NULL, '2026-04-22 00:46:01'),
(79, 1, 'account_pending_approval', 'New account pending approval', 'Email-verified registration waiting approval: suwi liao (student)', NULL, NULL, '2026-04-22 02:39:37'),
(80, 3, 'account_pending_approval', 'New account pending approval', 'Email-verified registration waiting approval: suwi liao (student)', NULL, NULL, '2026-04-22 02:39:37'),
(81, 1, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"CICTE days\" for approval.', 16, NULL, '2026-04-27 14:32:19'),
(82, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"CICTE days\" for approval.', 16, NULL, '2026-04-27 14:32:19'),
(83, 14, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"CICTE days\" for approval.', 16, NULL, '2026-04-27 14:32:19'),
(84, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"CICTE days\" for approval.', 16, NULL, '2026-04-27 14:32:19'),
(87, 1, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"CICTE days\" is now active.', 16, NULL, '2026-04-27 14:40:49'),
(88, 3, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"CICTE days\" is now active.', 16, NULL, '2026-04-27 14:40:49'),
(89, 14, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"CICTE days\" is now active.', 16, NULL, '2026-04-27 14:40:49'),
(90, 28, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"CICTE days\" is now active.', 16, NULL, '2026-04-27 14:40:49'),
(94, 1, 'account_pending_approval', 'New account pending approval', 'Email-verified registration waiting approval: Keanu (student)', NULL, NULL, '2026-04-27 16:21:58'),
(95, 3, 'account_pending_approval', 'New account pending approval', 'Email-verified registration waiting approval: Keanu (student)', NULL, NULL, '2026-04-27 16:21:58'),
(96, 33, 'rsvp_confirmed', 'RSVP confirmed', 'You are registered for \"CICTE days\".', 16, NULL, '2026-04-27 16:23:46'),
(99, 1, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE day\" (previously \"CICTE days\"). Please review and approve.', 16, NULL, '2026-04-27 23:23:07'),
(100, 3, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE day\" (previously \"CICTE days\"). Please review and approve.', 16, NULL, '2026-04-27 23:23:07'),
(101, 14, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE day\" (previously \"CICTE days\"). Please review and approve.', 16, NULL, '2026-04-27 23:23:07'),
(102, 28, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE day\" (previously \"CICTE days\"). Please review and approve.', 16, NULL, '2026-04-27 23:23:07'),
(103, 32, 'event_updated_pending', 'Event updated', 'An event you registered for (\"CICTE day\") was updated by the organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:23:07'),
(104, 33, 'event_updated_pending', 'Event updated', 'An event you registered for (\"CICTE day\") was updated by the organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:23:07'),
(105, 26, 'event_updated_pending', 'Event updated', 'Event \"CICTE day\" was updated by organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:23:07'),
(106, 27, 'event_updated_pending', 'Event updated', 'Event \"CICTE day\" was updated by organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:23:07'),
(107, 1, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE days\" (previously \"CICTE day\"). Please review and approve.', 16, NULL, '2026-04-27 23:36:32'),
(108, 3, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE days\" (previously \"CICTE day\"). Please review and approve.', 16, NULL, '2026-04-27 23:36:32'),
(109, 14, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE days\" (previously \"CICTE day\"). Please review and approve.', 16, NULL, '2026-04-27 23:36:32'),
(110, 28, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE days\" (previously \"CICTE day\"). Please review and approve.', 16, NULL, '2026-04-27 23:36:32'),
(111, 32, 'event_updated_pending', 'Event updated', 'An event you registered for (\"CICTE days\") was updated by the organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:36:32'),
(112, 33, 'event_updated_pending', 'Event updated', 'An event you registered for (\"CICTE days\") was updated by the organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:36:32'),
(113, 26, 'event_updated_pending', 'Event updated', 'Event \"CICTE days\" was updated by organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:36:32'),
(114, 27, 'event_updated_pending', 'Event updated', 'Event \"CICTE days\" was updated by organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:36:32'),
(115, 1, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE day\" (previously \"CICTE days\"). Please review and approve.', 16, NULL, '2026-04-27 23:36:43'),
(116, 3, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE day\" (previously \"CICTE days\"). Please review and approve.', 16, NULL, '2026-04-27 23:36:43'),
(117, 14, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE day\" (previously \"CICTE days\"). Please review and approve.', 16, NULL, '2026-04-27 23:36:43'),
(118, 28, 'event_update_pending_review', 'Event update pending approval', 'Organizer updated \"CICTE day\" (previously \"CICTE days\"). Please review and approve.', 16, NULL, '2026-04-27 23:36:43'),
(119, 32, 'event_updated_pending', 'Event updated', 'An event you registered for (\"CICTE day\") was updated by the organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:36:43'),
(120, 33, 'event_updated_pending', 'Event updated', 'An event you registered for (\"CICTE day\") was updated by the organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:36:43'),
(121, 26, 'event_updated_pending', 'Event updated', 'Event \"CICTE day\" was updated by organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:36:43'),
(122, 27, 'event_updated_pending', 'Event updated', 'Event \"CICTE day\" was updated by organizer and is pending admin approval.', 16, NULL, '2026-04-27 23:36:43'),
(125, 1, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"CICTE day\" is now active.', 16, NULL, '2026-04-27 23:55:03'),
(126, 3, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"CICTE day\" is now active.', 16, NULL, '2026-04-27 23:55:03'),
(127, 14, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"CICTE day\" is now active.', 16, NULL, '2026-04-27 23:55:03'),
(128, 28, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"CICTE day\" is now active.', 16, NULL, '2026-04-27 23:55:03'),
(129, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"event for june\" for approval.', 17, NULL, '2026-06-01 16:44:31'),
(130, 14, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"event for june\" for approval.', 17, NULL, '2026-06-01 16:44:31'),
(131, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"event for june\" for approval.', 17, NULL, '2026-06-01 16:44:31'),
(132, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"june try 3 days\" for approval.', 18, NULL, '2026-06-01 16:57:47'),
(133, 14, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"june try 3 days\" for approval.', 18, NULL, '2026-06-01 16:57:47'),
(134, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"june try 3 days\" for approval.', 18, NULL, '2026-06-01 16:57:47'),
(137, 3, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"june try 3 days\" is now active.', 18, NULL, '2026-06-01 17:14:02'),
(138, 14, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"june try 3 days\" is now active.', 18, NULL, '2026-06-01 17:14:02'),
(139, 28, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"june try 3 days\" is now active.', 18, NULL, '2026-06-01 17:14:02'),
(140, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"another june sample\" for approval.', 19, NULL, '2026-06-02 02:42:11'),
(141, 14, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"another june sample\" for approval.', 19, NULL, '2026-06-02 02:42:11'),
(142, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"another june sample\" for approval.', 19, NULL, '2026-06-02 02:42:11'),
(181, 29, 'event_rsvp_new', 'New RSVP', 'sample7 confirmed RSVP for \"june try 3 days\".', 18, '2026-06-04 00:50:35', '2026-06-03 01:18:10'),
(182, 3, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"ms intrams\" for approval.', 20, NULL, '2026-06-06 05:24:24'),
(183, 14, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"ms intrams\" for approval.', 20, NULL, '2026-06-06 05:24:24'),
(184, 28, 'event_pending_review', 'New event pending approval', 'Organizer submitted \"ms intrams\" for approval.', 20, NULL, '2026-06-06 05:24:24'),
(185, 29, 'event_approval_otp', 'Event approval OTP', 'Your OTP for event \"ms intrams\" is 263233. It expires in 10 minutes.', 20, '2026-06-07 22:15:19', '2026-06-06 05:27:23'),
(186, 29, 'event_approved', 'Event approved', 'Your event \"ms intrams\" is now approved and visible to students.', 20, '2026-06-07 22:15:19', '2026-06-06 05:28:02'),
(187, 3, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"ms intrams\" is now active.', 20, NULL, '2026-06-06 05:28:02'),
(188, 14, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"ms intrams\" is now active.', 20, NULL, '2026-06-06 05:28:02'),
(189, 28, 'event_auto_approved', 'Event approved via organizer OTP', 'Organizer verified OTP. Event \"ms intrams\" is now active.', 20, NULL, '2026-06-06 05:28:02');

-- --------------------------------------------------------

--
-- Table structure for table `organizer_settings`
--

CREATE TABLE `organizer_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `default_calendar_view` varchar(20) NOT NULL DEFAULT 'dayGridMonth',
  `default_department_filter` varchar(120) NOT NULL DEFAULT 'ALL',
  `show_weekends` tinyint(1) NOT NULL DEFAULT 1,
  `week_starts_on` tinyint(4) NOT NULL DEFAULT 0,
  `notify_email_event_status` tinyint(1) NOT NULL DEFAULT 1,
  `notify_email_feedback` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `organizer_settings`
--

INSERT INTO `organizer_settings` (`id`, `user_id`, `default_calendar_view`, `default_department_filter`, `show_weekends`, `week_starts_on`, `notify_email_event_status`, `notify_email_feedback`, `created_at`, `updated_at`) VALUES
(1, 29, 'dayGridMonth', 'ALL', 1, 0, 0, 1, '2026-06-07 14:15:51', '2026-06-07 14:19:05');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `registration_date` datetime DEFAULT current_timestamp(),
  `qr_code` varchar(255) DEFAULT NULL,
  `status` enum('absent','present') DEFAULT 'absent',
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `user_id`, `event_id`, `registration_date`, `qr_code`, `status`, `time_in`, `time_out`) VALUES
(1, 13, 9, '2026-03-15 00:27:48', NULL, 'present', '2026-03-15 18:35:11', NULL),
(3, 14, 9, '2026-03-15 09:31:47', NULL, 'present', '2026-03-15 09:31:47', NULL),
(5, 30, 10, '2026-03-27 18:26:25', NULL, 'absent', NULL, NULL),
(6, 19, 15, '2026-04-22 08:50:51', NULL, 'present', '2026-04-22 08:50:51', NULL),
(8, 32, 16, '2026-04-27 23:31:10', NULL, 'absent', NULL, NULL),
(9, 33, 16, '2026-04-28 00:23:46', NULL, 'present', '2026-04-28 00:27:07', NULL),
(20, 19, 18, '2026-06-03 09:18:10', NULL, 'absent', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_messages`
--

CREATE TABLE `staff_messages` (
  `id` bigint(20) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `body` varchar(8000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_messages`
--

INSERT INTO `staff_messages` (`id`, `sender_id`, `recipient_id`, `body`, `created_at`, `read_at`) VALUES
(1, 14, 17, 'Hi', '2026-04-19 01:47:16', '2026-04-19 09:47:23'),
(2, 17, 14, 'hi', '2026-04-19 01:47:28', '2026-04-22 08:43:50'),
(3, 17, 14, 'yo', '2026-04-19 01:48:04', '2026-04-22 08:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `student_settings`
--

CREATE TABLE `student_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `rsvp_updates` tinyint(1) NOT NULL DEFAULT 1,
  `announcement_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `notif_channel_email` tinyint(1) NOT NULL DEFAULT 1,
  `default_calendar_view` varchar(20) NOT NULL DEFAULT 'dayGridMonth',
  `show_calendar_legend` tinyint(1) NOT NULL DEFAULT 1,
  `auto_add_rsvp_calendar` tinyint(1) NOT NULL DEFAULT 1,
  `reminder_timing` varchar(20) NOT NULL DEFAULT '1_day',
  `hide_past_rsvped` tinyint(1) NOT NULL DEFAULT 0,
  `share_profile_with_organizers` tinyint(1) NOT NULL DEFAULT 1,
  `allow_photo_tagging` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_settings`
--

INSERT INTO `student_settings` (`id`, `user_id`, `event_reminders`, `rsvp_updates`, `announcement_notifications`, `notif_channel_email`, `default_calendar_view`, `show_calendar_legend`, `auto_add_rsvp_calendar`, `reminder_timing`, `hide_past_rsvped`, `share_profile_with_organizers`, `allow_photo_tagging`, `created_at`, `updated_at`) VALUES
(1, 18, 1, 1, 1, 1, 'dayGridMonth', 1, 1, '1_day', 0, 1, 1, '2026-04-17 15:20:39', '2026-04-17 15:21:08'),
(4, 32, 1, 1, 1, 1, 'dayGridMonth', 1, 1, '1_day', 0, 1, 1, '2026-04-27 13:41:39', '2026-04-27 13:41:48');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_orders`
--

CREATE TABLE `ticket_orders` (
  `id` int(11) NOT NULL,
  `order_ref` varchar(32) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid','cancelled','failed') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(30) DEFAULT NULL COMMENT 'simulate, gcash, cash',
  `payment_reference` varchar(120) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_order_items`
--

CREATE TABLE `ticket_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `ticket_type_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `organizer_contact_email` varchar(100) DEFAULT NULL,
  `organizer_phone` varchar(25) DEFAULT NULL,
  `organizer_contact_method` enum('email','phone') NOT NULL DEFAULT 'email',
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','organizer','student','multimedia') NOT NULL,
  `department` enum('BSIT','BSHM','CONAHS','Senior High','High school department','College of Communication, Information and Technology','College of Accountancy and Business','School of Law and Political Science','College of Education','College of Nursing and Allied health sciences','College of Hospitality Management') DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_attempts` int(11) DEFAULT 0,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `student_course` varchar(120) DEFAULT NULL,
  `student_year_level` varchar(40) DEFAULT NULL,
  `student_academic_year` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_id`, `name`, `email`, `organizer_contact_email`, `organizer_phone`, `organizer_contact_method`, `password`, `role`, `department`, `profile_picture`, `status`, `created_at`, `failed_attempts`, `must_change_password`, `student_course`, `student_year_level`, `student_academic_year`) VALUES
(1, 'SA-001', 'Kristian', 'kristian@school.com', NULL, NULL, 'email', '$2y$10$PASTE_HASH_HERE', 'super_admin', NULL, NULL, 'inactive', '2025-12-15 17:38:46', 4, 0, NULL, NULL, NULL),
(3, 'SA-001', 'Kristian Salgado', 'kristian1@school.com', NULL, NULL, 'email', '$2y$10$IYqWZeXPvmCz3SvEmeSd6Op5eV2J3PRXeWaC5tPU3Guu7xZBs9TRK', 'super_admin', NULL, NULL, 'active', '2025-12-15 18:09:54', 0, 0, NULL, NULL, NULL),
(4, 'STU-781', 'sample', 'sample@gmail.com', NULL, NULL, 'email', 'af2bdbe1aa9b6ec1e2ade1d694f41fc71a831d0268e9891562113d8a62add1bf', 'student', NULL, NULL, 'active', '2025-12-15 18:32:46', 1, 0, NULL, NULL, NULL),
(5, 'STU-776', 'suai', 'suai@gmail.com', NULL, NULL, 'email', 'dea259230178e8b71e2ee186546e9cd6c56b7922b4519e6835d6ebc507f0c64e', 'student', NULL, NULL, 'active', '2025-12-15 18:55:30', 1, 0, NULL, NULL, NULL),
(6, 'STU-842', 'suai1', 'suai1@gmail.com', NULL, NULL, 'email', '$2y$10$wj9ZwjEM02hYCLS7tXe6e.Kn7k03k6mJWRSFHSxrXk3AXX7990DiS', 'student', NULL, NULL, 'inactive', '2025-12-15 18:57:12', 4, 0, NULL, NULL, NULL),
(7, 'STU-738', 'suai2', 'suai2@gmail.com', NULL, NULL, 'email', 'ba1889bd80d5dffe82089bb71ca4683831c4c872ef817b1306421c421130eee2', 'student', NULL, NULL, 'active', '2025-12-15 18:57:32', 0, 0, NULL, NULL, NULL),
(8, 'STU-351', 'suai3', 'suai3@gmail.com', NULL, NULL, 'email', '4e78ba3f87382d0ce4a41471737af8f0b2310c6fc7295b4a40ab00508f2f9620', 'student', NULL, NULL, 'active', '2025-12-15 18:59:36', 2, 0, NULL, NULL, NULL),
(9, 'STU-253', 'suai4', 'suai4@gmail.com', NULL, NULL, 'email', '51ee5f59976acb3565d0609c70cb939ded012c4267c3d0fce29a5af2562129d4', 'student', NULL, NULL, 'active', '2025-12-15 19:00:19', 0, 0, NULL, NULL, NULL),
(10, 'STU-781', 'suai5', 'suai5@gmail.com', NULL, NULL, 'email', 'd65c023117c75d15c1f117f53ac85232fdb728513310411aceae11449f350d43', 'student', NULL, NULL, 'inactive', '2025-12-15 19:01:36', 4, 0, NULL, NULL, NULL),
(11, 'STU-979', 'another sample', 'anothersample@gmail.com', NULL, NULL, 'email', 'a08f8859605f0b362db593d2a8e756f0f65a334800b575329cf7d5af6d424f21', 'student', NULL, NULL, 'active', '2025-12-16 07:11:30', 3, 0, NULL, NULL, NULL),
(12, 'STU-512', 'sample1', 'sample1@gmail.com', NULL, NULL, 'email', 'e85130791f31db1699f61a5e7ae7b5e85e70399414f38476091896214771cd17', 'student', NULL, NULL, 'active', '2025-12-16 07:21:16', 2, 0, NULL, NULL, NULL),
(13, 'STU-913', 'Kristian James Salgado', 'sample4@gmail.com', NULL, NULL, 'email', '$2y$10$GOSpGfllB2kBaCJLmvORrOR9ScVRgG5eOrwDxA9G8E.zhwlUVl9xq', 'student', NULL, 'uploads/profile_pictures/profile_13_1773572295_69b690c737f8d.jpeg', 'active', '2025-12-16 08:41:26', 0, 0, NULL, NULL, NULL),
(14, 'STU-226', 'Kristian James Salgado', 'sample5@gmail.com', NULL, NULL, 'email', '$2y$10$bYL4yAXTtDweHG0hnXFb8.lZmw9l8l6kQ9/5PsrCjwCh3F9WJhzY6', 'admin', 'BSIT', 'uploads/profile_pictures/profile_14_1770312568_6984d378b4f87.png', 'active', '2025-12-17 04:05:13', 0, 0, NULL, NULL, NULL),
(15, 'ORG-318', 'organizer', 'organizer@gmail.com', NULL, NULL, 'email', '154a0a277d0a9e90475532eeb50bb087f6dcf19172db5fc8091221091c772ac5', 'organizer', NULL, NULL, 'active', '2025-12-18 04:23:41', 0, 0, NULL, NULL, NULL),
(16, 'ORG-206', 'organizer1', 'organizer1@gmail.com', NULL, NULL, 'email', '154a0a277d0a9e90475532eeb50bb087f6dcf19172db5fc8091221091c772ac5', 'admin', NULL, NULL, 'inactive', '2025-12-18 06:16:33', 5, 0, NULL, NULL, NULL),
(17, 'ORG-880', 'organizer2', 'organizer2@gmail.com', 'bojiking31@gmail.com', '09085210452', 'email', '$2y$10$ztiS2BWvxE0qzbBgOASB5u7aMcmVvhm8ChxKmz08ya5T85XsDSPRm', 'organizer', NULL, 'uploads/profile_pictures/profile_17_1771385893_69953425b8545.png', 'active', '2025-12-18 06:25:39', 0, 0, NULL, NULL, NULL),
(18, 'STU-238', 'sample6', 'sample6@gmail.com', NULL, NULL, 'email', '$2y$10$y57BnjDL.S4VjYh4rV8GmuvNsvr7J.cj65WKVcnlYsyTDMpSuukCi', 'student', NULL, NULL, 'active', '2025-12-18 07:41:13', 0, 0, NULL, NULL, NULL),
(19, 'STU-558', 'sample7', 'sample7@gmail.com', NULL, NULL, 'email', '$2y$10$lyK1Fj3cb0fw2EB6rVNE/O.eqBnzPvdZFdfG8u0gbEEFaK7KN8BES', 'student', 'College of Communication, Information and Technology', NULL, 'active', '2025-12-19 05:57:19', 0, 0, 'BS Computer Science', '3rd Year', '2025-2026'),
(20, 'ORG-923', 'samplereg', 'Samplereg@gmail.com', NULL, NULL, 'email', '76cd579e5eea4f719469276719558fa1b46c0196a613cb8aa5bfcdd9a43628f8', 'organizer', NULL, NULL, 'active', '2025-12-21 06:09:01', 6, 0, NULL, NULL, NULL),
(21, 'ORG-994', 'samplereg2', 'Samplereg2@gmail.com', NULL, NULL, 'email', '$2y$10$R5xPfViZhl3hW23ie1eH1.o/9nniSR0/ZRud4LpQB5t86tu29fFli', 'organizer', NULL, NULL, 'active', '2025-12-21 06:44:58', 0, 0, NULL, NULL, NULL),
(22, 'ORG-964', 'samplereg3', 'Samplereg3@gmail.com', NULL, NULL, 'email', '8d9353a8accc17cba0ee8dab3b744552799c6f37226a5b9e48a37cb82945de8f', 'organizer', NULL, NULL, 'active', '2025-12-21 06:50:28', 1, 0, NULL, NULL, NULL),
(23, 'STU-827', 'sammilby', 'sammilby@gmail.com', NULL, NULL, 'email', '5e6eb2532b6f1eb86b9bfd41c5c1e9ca14d444eb013c223c0958b9dde57fd54f', 'student', NULL, NULL, 'active', '2025-12-22 03:55:21', 0, 0, NULL, NULL, NULL),
(24, 'STU-510', 'deanecamat', 'deanecamat@gmail.com', NULL, NULL, 'email', 'faae366a9b3bc5e637a5f10b53a826ce31546dfb03a1cc3f7849001906d13dff', 'student', NULL, NULL, 'active', '2025-12-23 12:42:31', 0, 0, NULL, NULL, NULL),
(25, 'STU-939', 'jabes', 'jabes@gmail.com', NULL, NULL, 'email', '781e60f7f136510363f9a9522ebeb73f647d94111c6e2d27fd669e0770a26aef', 'student', NULL, NULL, 'active', '2025-12-23 15:13:46', 2, 0, NULL, NULL, NULL),
(26, 'MUL-692', 'multimedia', 'multimedia@gmail.com', NULL, NULL, 'email', '$2y$10$8uY9QHmNVKHaGzqCpc3FVOFli2Ad4.ftlDkKq4.GzWEtGVpP73o2m', 'multimedia', '', 'uploads/profile_pictures/profile_26_1771386378_6995360ae6219.png', 'active', '2026-02-05 06:36:11', 0, 0, NULL, NULL, NULL),
(27, 'MUL-721', 'multimedia1', 'multimedia1@gmail.com', NULL, NULL, 'email', '$2y$10$iFLWXYM3owfuH7JrE1Qvuu10xeErUur7GmU.eEa95YnC3JQ7BiygS', 'multimedia', '', NULL, 'active', '2026-02-05 06:49:05', 0, 0, NULL, NULL, NULL),
(28, 'ADM-214', 'admin1', 'admin1@gmail.com', NULL, NULL, 'email', '$2y$10$GhltrcajVDqPr9dvTpRWhuxsXhOXupLct6Pe9tZp.HY3OcgJrDtLa', 'admin', '', NULL, 'active', '2026-03-13 04:26:02', 2, 0, NULL, NULL, NULL),
(29, 'STU-162', 'boji king', 'bojiking31@gmail.com', 'blademale31@gmail.com', '', 'email', '$2y$10$UNwoo5mRwXO0OyMSe1T6cu3Ry1vS0h3JBT4YURIjrOTkuCpdPefWm', 'organizer', 'High school department', NULL, 'active', '2026-03-27 09:50:29', 0, 0, NULL, NULL, NULL),
(30, 'STU-799', 'deane gwapo', 'deanechristiancamat121212@gmail.com', NULL, NULL, 'email', '$2y$10$ip/XDQfYJ86kHjgQWLBkjuhnVwNpvNjoOrdECsQHhMdqS3mdRH5di', 'student', 'College of Communication, Information and Technology', NULL, 'active', '2026-03-27 10:21:46', 0, 0, NULL, NULL, NULL),
(31, 'STU-129', 'blademale', 'blademale31@gmail.com', NULL, NULL, 'email', '$2y$10$YQxrnNdwXQhRnQpu4vUpPeO6n2QtreRy6BS4msx4ZwdvE6xVjGLMW', 'student', 'High school department', NULL, 'active', '2026-03-30 15:02:16', 0, 0, NULL, NULL, NULL),
(32, 'STU-282', 'suwi liao', 'suwiliao31@gmail.com', NULL, NULL, 'email', '$2y$10$QkbO9VbrxLk6/UQjfqsaPuw7wL0DsC460f0DUfvSpzdmPFs0oYhCK', 'student', 'College of Communication, Information and Technology', 'uploads/profile_pictures/profile_32_1777410689_69f12281d6ed0.jpg', 'active', '2026-04-22 02:39:37', 0, 0, 'BS Information Technology', '3rd Year', '2025-2026'),
(33, 'STU-426', 'Keanu', 'keanugayuma@gmail.com', NULL, NULL, 'email', '$2y$10$WdNDvoXl.8zw8uIxxxqNzeMKax0CgHMIPvQ1liBhRe1H5ENXuNOpi', 'student', 'College of Communication, Information and Technology', NULL, 'active', '2026-04-27 16:21:58', 0, 0, 'BS Information Technology', 'Grade 7', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_email_otps`
--
ALTER TABLE `account_email_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_purpose` (`email`,`purpose`),
  ADD KEY `idx_user_purpose` (`user_id`,`purpose`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `actor_id` (`actor_id`);

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_user_id` (`admin_user_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `checkin_token` (`checkin_token`),
  ADD KEY `organizer_id` (`organizer_id`);

--
-- Indexes for table `event_approval_otps`
--
ALTER TABLE `event_approval_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_org_id` (`organizer_id`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `event_approval_otps_verified_by_fk` (`verified_by`),
  ADD KEY `event_approval_otps_created_by_fk` (`created_by`);

--
-- Indexes for table `event_checkin_device_locks`
--
ALTER TABLE `event_checkin_device_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_event_device` (`event_id`,`device_hash`),
  ADD KEY `idx_event_user` (`event_id`,`user_id`),
  ADD KEY `fk_checkin_lock_user` (`user_id`);

--
-- Indexes for table `event_day_sessions`
--
ALTER TABLE `event_day_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `event_schedule_date` (`event_id`,`schedule_date`);

--
-- Indexes for table `event_day_session_attendance`
--
ALTER TABLE `event_day_session_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_user_att` (`session_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `event_day_session_rsvps`
--
ALTER TABLE `event_day_session_rsvps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_user` (`session_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `event_feedback`
--
ALTER TABLE `event_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_event_user_feedback` (`event_id`,`user_id`),
  ADD KEY `idx_event_feedback_event` (`event_id`),
  ADD KEY `event_feedback_user_fk` (`user_id`);

--
-- Indexes for table `event_photos`
--
ALTER TABLE `event_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `event_schedule_dates`
--
ALTER TABLE `event_schedule_dates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_schedule_unique` (`event_id`,`schedule_date`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `schedule_date` (`schedule_date`);

--
-- Indexes for table `event_tickets`
--
ALTER TABLE `event_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ticket_code` (`ticket_code`),
  ADD UNIQUE KEY `uniq_ticket_checkin_token` (`checkin_token`),
  ADD KEY `idx_event_tickets_user` (`user_id`),
  ADD KEY `idx_event_tickets_event` (`event_id`),
  ADD KEY `idx_event_tickets_order` (`order_id`),
  ADD KEY `fk_event_tickets_type` (`ticket_type_id`);

--
-- Indexes for table `event_ticket_types`
--
ALTER TABLE `event_ticket_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_ticket_types_event` (`event_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `read_at` (`read_at`);

--
-- Indexes for table `organizer_settings`
--
ALTER TABLE `organizer_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`user_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `staff_messages`
--
ALTER TABLE `staff_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pair_time` (`sender_id`,`recipient_id`,`created_at`),
  ADD KEY `idx_inbox` (`recipient_id`,`read_at`,`created_at`);

--
-- Indexes for table `student_settings`
--
ALTER TABLE `student_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `ticket_orders`
--
ALTER TABLE `ticket_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_order_ref` (`order_ref`),
  ADD KEY `idx_ticket_orders_user` (`user_id`),
  ADD KEY `idx_ticket_orders_event` (`event_id`),
  ADD KEY `idx_ticket_orders_status` (`status`);

--
-- Indexes for table `ticket_order_items`
--
ALTER TABLE `ticket_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `fk_order_items_type` (`ticket_type_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_email_otps`
--
ALTER TABLE `account_email_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=408;

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `event_approval_otps`
--
ALTER TABLE `event_approval_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `event_checkin_device_locks`
--
ALTER TABLE `event_checkin_device_locks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `event_day_sessions`
--
ALTER TABLE `event_day_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `event_day_session_attendance`
--
ALTER TABLE `event_day_session_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_day_session_rsvps`
--
ALTER TABLE `event_day_session_rsvps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event_feedback`
--
ALTER TABLE `event_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `event_photos`
--
ALTER TABLE `event_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `event_schedule_dates`
--
ALTER TABLE `event_schedule_dates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `event_tickets`
--
ALTER TABLE `event_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_ticket_types`
--
ALTER TABLE `event_ticket_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=190;

--
-- AUTO_INCREMENT for table `organizer_settings`
--
ALTER TABLE `organizer_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `staff_messages`
--
ALTER TABLE `staff_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_settings`
--
ALTER TABLE `student_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ticket_orders`
--
ALTER TABLE `ticket_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_order_items`
--
ALTER TABLE `ticket_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD CONSTRAINT `fk_admin_settings_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_approval_otps`
--
ALTER TABLE `event_approval_otps`
  ADD CONSTRAINT `event_approval_otps_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_approval_otps_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_approval_otps_organizer_fk` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_approval_otps_verified_by_fk` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_checkin_device_locks`
--
ALTER TABLE `event_checkin_device_locks`
  ADD CONSTRAINT `fk_checkin_lock_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_checkin_lock_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_day_sessions`
--
ALTER TABLE `event_day_sessions`
  ADD CONSTRAINT `event_day_sessions_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_day_session_attendance`
--
ALTER TABLE `event_day_session_attendance`
  ADD CONSTRAINT `eds_att_session_fk` FOREIGN KEY (`session_id`) REFERENCES `event_day_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `eds_att_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_day_session_rsvps`
--
ALTER TABLE `event_day_session_rsvps`
  ADD CONSTRAINT `eds_rsvp_session_fk` FOREIGN KEY (`session_id`) REFERENCES `event_day_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `eds_rsvp_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_feedback`
--
ALTER TABLE `event_feedback`
  ADD CONSTRAINT `event_feedback_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_feedback_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_photos`
--
ALTER TABLE `event_photos`
  ADD CONSTRAINT `event_photos_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_photos_user_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_schedule_dates`
--
ALTER TABLE `event_schedule_dates`
  ADD CONSTRAINT `event_schedule_dates_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_tickets`
--
ALTER TABLE `event_tickets`
  ADD CONSTRAINT `fk_event_tickets_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_tickets_order` FOREIGN KEY (`order_id`) REFERENCES `ticket_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_tickets_type` FOREIGN KEY (`ticket_type_id`) REFERENCES `event_ticket_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_ticket_types`
--
ALTER TABLE `event_ticket_types`
  ADD CONSTRAINT `fk_ticket_types_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `organizer_settings`
--
ALTER TABLE `organizer_settings`
  ADD CONSTRAINT `fk_organizer_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

--
-- Constraints for table `student_settings`
--
ALTER TABLE `student_settings`
  ADD CONSTRAINT `fk_student_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_orders`
--
ALTER TABLE `ticket_orders`
  ADD CONSTRAINT `fk_ticket_orders_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ticket_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_order_items`
--
ALTER TABLE `ticket_order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `ticket_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_type` FOREIGN KEY (`ticket_type_id`) REFERENCES `event_ticket_types` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
