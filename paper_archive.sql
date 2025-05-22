CREATE DATABASE IF NOT EXISTS `paper_archive`;
USE `paper_archive`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;
SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS;
SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION;
SET NAMES utf8mb4;

CREATE TABLE `papers` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `course_id` int(11) DEFAULT NULL,
  `year` int(11) NOT NULL,
  `semester` enum('Fall','Spring','Summer') NOT NULL,
  `paper_type` enum('Quiz','Mid-term','Final','Assignment') NOT NULL,
  `is_approved` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `question_papers` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `paper_year` int(4) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `credit` int(2) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `download_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `question_papers` (`id`, `title`, `department`, `course_code`, `paper_year`, `semester`, `subject`, `credit`, `file_path`, `uploaded_at`, `download_count`) VALUES
(10, 'computer application', ' Computer Science', ' BCA-CC-T4-202', 2025, 'Semester 4', ' Database management system', 4, '../uploads/question_papers/1746804192_gegdjg.pdf', '2025-05-09 15:23:12', 0),
(12, 'ugfgf', 'gfdvdg', 'vdhvd', 2025, 'Semester 4', 'vdvdgv', 3, '../uploads/question_papers/1746952791_DBMS 2.pdf', '2025-05-11 08:39:51', 1);

CREATE TABLE `semesters` (
  `id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `semesters` (`id`, `name`) VALUES
(1, 'Semester 1'),
(2, 'Semester 2'),
(3, 'Semester 3'),
(4, 'Semester 4'),
(5, 'Semester 5'),
(6, 'Semester 6'),
(7, 'Semester 7'),
(8, 'Semester 8');

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `username`, `email`, `password`, `remember_token`, `token_expires`, `reset_token`, `reset_expires`, `created_at`, `updated_at`, `role`, `status`, `rejection_reason`) VALUES
(1, 'Tutu', 'tutu@admin.com', '$2y$10$dKwYFRwN7mBmF5iPSBjpfeDZmla9toIOloMFxyeHioarrlG5fvT52', '1aa3520cf3a653e5dfe64497ebcd3e5368105231e203f49b0c90a94478c2dfe3', '0000-00-00 00:00:00', NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'admin', 'approved', NULL),
(3, 'Tutu', 'tutu@student.com', '$2y$10$QtXvSRWE91a5lmZT48XOIO7GUk3lFjwrjZ6l8L21nE3fTH1FgK95a', NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'student', 'approved', NULL),
(4, 'Ankur Boruah', 'Ankur@student.com', '$2y$10$DwQUuFv31CSA4.l6.SbcBuEWnytJLnRZUZtvj3fNGInKXWg3LZg6', NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'student', 'approved', NULL),
(5, 'Dulen boruah', 'dulen@teacher.com', '$2y$10$6xpsdhen7vukgurEz7p8uzL09kUvuwEub55ltarvrIbQ7gV45fSS', NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'teacher', 'approved', NULL),
(6, 'John', 'john@teacher.com', '$2y$10$iVNfPgeJsuPy4MJ9ibwXs.VGpJ3ppEKMG0igA91MaTvbYVEdwxMry', NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'teacher', 'rejected', 'Not Teacherrn'),
(7, 'Rajen', 'rajen@teacher.com', '$2y$10$4VXwP0I1Ky1lUGU51L4fWOgqm1cBnMnDOpIvvz2CVamaaFHycSaxO', 'b2d98a9344e41340ec85d2feffbd57905402a05748e2180f43362ac63d1cc5e1', '0000-00-00 00:00:00', NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'teacher', 'approved', NULL),
(8, 'ankur', 'boruah@student.com', '$2y$10$IvYSg38pkEo8bgOw0nTt.fuKlyYQHgAXtujA5GnfxnvHdOedeflG', '679cb037d1596f83d9ba7bf36b80ee9272a2ed4b6d2bdf79969ababb1468ccef', '0000-00-00 00:00:00', NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'student', 'approved', NULL),
(9, 'hello', 'hello@test.com', '$2y$10$VMwXT3lCJL.Q6llE.0MSf.CeV8l1HbCBqWcABLqO0t2xaG41SbpyS', NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'teacher', 'approved', NULL),
(10, 'ankur', 'boruah@gmail.com', '$2y$10$LVlVC2VJsy8j3qbKEJGUuS1IrJfwd6ovkluX7K2vdtLbX6RcB7Rm', NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'student', 'approved', NULL),
(11, 'ankur', 'ankur@gmail.com', '$2y$10$aNX3ypamM0sQ8fBQC3em.7Ub5pjiJ6EbUa1yka4ApQVn66dINzG', NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'teacher', 'approved', NULL),
(12, 'jk', 'jka@gmail.com', '$2y$10$Ps5bJhmx0tQHIIQy1BWpn.R7flr.7BW/r7KYU5cn5UPI1BSWpuVR6', NULL, NULL, NULL, NULL, '2025-05-09 17:15:14', '2025-05-09 15:15:14', 'student', 'approved', NULL),
(13, 'tarangini konwar', 'konwartarangini@gmail.com', '$2y$10$vKi/7CbDT6sZWZrfgE9kVukR7W8Sw52NzuDqummUqtHxiJ0aqIrRa', '40422d34bf5d95383340f6607f3f18e9f6527736e9ef57b7de22162e7ff6e110', '2025-06-08 17:17:43', NULL, NULL, '2025-05-09 17:16:57', '2025-05-09 15:17:43', 'admin', 'approved', NULL),
(14, 'singham', 'hi@gmail.com', '$2y$10$OAbkOSPZEQbC/7LWHJag5e1JslypoVgMs9yd9U/2/02G.optko1s6', NULL, NULL, NULL, NULL, '2025-05-09 17:19:21', '2025-05-09 15:19:50', 'teacher', 'approved', NULL),
(15, 'Tutu', 'tutu@teacher.com', '$2y$10$LHUy/QW/1gvjk/pEtDAr1OtsQs83FPgdB4z.CRhqv/hv3NhgWMiGW', NULL, NULL, NULL, NULL, '2025-05-22 15:39:36', '2025-05-22 13:40:09', 'teacher', 'approved', NULL);

ALTER TABLE `question_papers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `semesters`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `question_papers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

COMMIT;

SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT;
SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS;
SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION;