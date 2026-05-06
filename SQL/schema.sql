-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 23, 2025 at 10:44 AM
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
-- Database: `clinic_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `phone` varchar(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `reason` text NOT NULL,
  `appointee` enum('Doctor','Nurse','Dentist') NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_name`, `category`, `phone`, `appointment_date`, `appointment_time`, `reason`, `appointee`, `status`, `created_at`, `updated_at`) VALUES
(4, 'Prince Arvee Avena', 'College', '09168927345', '2025-04-29', '13:00:00', 'Check up', 'Nurse', 'approved', '2025-04-29 15:09:40', '2025-04-29 15:14:40'),
(5, 'Prince Arvee Avena', 'College', '09168927345', '2025-04-30', '15:00:00', 'Check up', 'Nurse', 'pending', '2025-04-29 15:24:17', '2025-04-29 15:24:17');

-- --------------------------------------------------------

--
-- Table structure for table `grade_years`
--

CREATE TABLE `grade_years` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `category` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_years`
--

INSERT INTO `grade_years` (`id`, `name`, `category`) VALUES
(1, 'Kinder', 'Pre School'),
(2, 'Grade 1', 'Elementary'),
(3, 'Grade 2', 'Elementary'),
(4, 'Grade 3', 'Elementary'),
(5, 'Grade 4', 'Elementary'),
(6, 'Grade 5', 'Elementary'),
(7, 'Grade 6', 'Elementary'),
(8, 'Grade 7', 'JHS'),
(9, 'Grade 8', 'JHS'),
(10, 'Grade 9', 'JHS'),
(11, 'Grade 10', 'JHS'),
(12, 'Grade 11', 'SHS'),
(13, 'Grade 12', 'SHS'),
(14, '1st Year', 'College'),
(15, '2nd Year', 'College'),
(16, '3rd Year', 'College'),
(17, '4th Year', 'College'),
(18, 'Graduated', 'Alumni');

-- --------------------------------------------------------

--
-- Table structure for table `log_visits`
--

CREATE TABLE `log_visits` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `other_reason` varchar(255) DEFAULT NULL,
  `took_medicine` enum('Yes','No') DEFAULT 'No',
  `medicine_id` int(11) DEFAULT NULL,
  `medicine_name` varchar(255) DEFAULT NULL,
  `medicine_quantity` int(11) DEFAULT NULL,
  `visit_date` varchar(50) NOT NULL,
  `visit_time` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `name`, `quantity`, `created_at`) VALUES
(2, 'BIogesic', 13, '2025-04-29 16:27:36');

-- --------------------------------------------------------

--
-- Table structure for table `medicine_logs`
--

CREATE TABLE `medicine_logs` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `quantity_used` int(11) NOT NULL,
  `visit_date` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_logs`
--

INSERT INTO `medicine_logs` (`id`, `medicine_id`, `patient_id`, `quantity_used`, `visit_date`, `reason`) VALUES
(2, 2, 19, 1, '2025-05-14 15:23:00', 'Headache'),
(3, 2, 22, 1, '2025-05-18 13:35:00', 'Headache'),
(4, 2, 8, 1, '2025-05-21 22:20:00', 'Headache');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `category` varchar(100) NOT NULL,
  `grade_year` varchar(50) DEFAULT NULL,
  `program_section` varchar(100) DEFAULT NULL,
  `guardian_contact` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `last_name`, `first_name`, `middle_name`, `gender`, `category`, `grade_year`, `program_section`, `guardian_contact`) VALUES
(1, 'Doe', 'John', 'A', 'Male', 'Pre School', 'Kinder', 'Section A', '123-456-7890'),
(2, 'Smith', 'Mary', 'B', 'Female', 'Elementary', 'Grade 1', 'Section B', '987-654-3210'),
(3, 'Johnson', 'Alice', 'C', 'Female', 'JHS', 'Grade 7', 'Section A', '555-123-4567'),
(4, 'Brown', 'Bob', 'D', 'Male', 'SHS', 'Grade 11', 'STEM', '555-987-6543'),
(8, 'Avena', 'Prince Arvee', 'Flores', 'Male', 'College', '3rd Year', 'BSCS', '09383072884'),
(18, 'Avena', 'Christian', 'Baral', 'Male', 'Alumni', NULL, NULL, '09168927348'),
(19, 'Ubaña', 'Juan Miguel', 'Bascuguin', 'Male', 'College', '3rd Year', 'BSHM', '09685669215'),
(20, 'Estilo', 'Ken', 'De Jesus', 'Male', 'College', '3rd Year', 'BSHM', '09917745994'),
(21, 'Rivera', 'Arabella', 'Bulan', 'Female', 'College', '3rd Year', 'BSCS', '09161819960'),
(22, 'Alday', 'Errol', 'Lasheras', 'Male', 'College', '3rd Year', 'BSCS', '09556622460');

-- --------------------------------------------------------

--
-- Table structure for table `program_sections`
--

CREATE TABLE `program_sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program_sections`
--

INSERT INTO `program_sections` (`id`, `name`, `category`) VALUES
(1, 'Section A', 'Pre School'),
(2, 'Section B', 'Pre School'),
(3, 'Section A', 'Elementary'),
(4, 'Section B', 'Elementary'),
(5, 'Section A', 'JHS'),
(6, 'Section B', 'JHS'),
(7, 'STEM', 'SHS'),
(8, 'ABM', 'SHS'),
(9, 'BSIT', 'College'),
(10, 'BSCS', 'College'),
(11, 'BSEd', 'College'),
(12, 'N/A', 'Alumni'),
(14, 'BSTM', 'College'),
(15, 'BSHM', 'College');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `last_active` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `session_id`, `last_active`) VALUES
(3, 2, '931b5qmhor04lj0s721m6fptku', '2025-05-01 05:31:44'),
(7, 1, 'mofpbekfslvg5gjngi4rjcq8kd', '2025-05-21 14:23:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `plain_password` varchar(100) DEFAULT NULL,
  `admin_category` enum('Nurse','Clinic Staff','Doctor','Dentist') NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `plain_password`, `admin_category`, `profile_picture`, `created_at`, `updated_at`) VALUES
(1, 'Arvee Avena', 'arveeavena@sscms.com', '$2y$10$LL9hHRC1I0BTOmdVxLqEv.HCpjMQFFvk7yAbz2Kw9CBiHtFUUivrW', 'sscms_arvee', 'Clinic Staff', '/SSCMS/uploads/profiles/user_1_1745632534.jpg', '2025-04-26 01:50:19', '2025-04-29 13:45:38'),
(2, 'Andrei Espinar', 'andreiespinar@sscms.com', '$2y$10$CjHJ6.4Jm5lmQYLwkbrTNe26fz9tB9B9cdASOpxUPbs1IhirotCX.', 'andreiespinar_sscms', 'Clinic Staff', NULL, '2025-04-29 13:46:49', '2025-04-29 13:46:49'),
(3, 'Angelyn Panganiban', 'angelynpanganiban@sscms.com', '$2y$10$Ur7REPJdb9oo1UKRZX9/ker3DJVsP7hLKQER62Gj5gt.intvyf7fS', NULL, 'Clinic Staff', NULL, '2025-04-29 13:54:37', '2025-04-29 13:54:37');

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `other_reason` varchar(255) DEFAULT NULL,
  `took_medicine` enum('Yes','No') NOT NULL DEFAULT 'No',
  `medicine_name` varchar(255) DEFAULT NULL,
  `medicine_quantity` int(11) DEFAULT NULL,
  `visit_time` time NOT NULL,
  `visit_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `medicine_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`id`, `patient_id`, `reason`, `other_reason`, `took_medicine`, `medicine_name`, `medicine_quantity`, `visit_time`, `visit_date`, `created_at`, `medicine_id`) VALUES
(15, 8, 'Headache', NULL, 'Yes', 'BIogesic', 1, '12:26:00', '2025-04-30', '2025-04-30 04:26:18', NULL),
(19, 8, 'Headache', NULL, 'Yes', 'BIogesic', 1, '12:38:00', '2025-04-30', '2025-04-30 04:38:28', NULL),
(21, 8, 'Headache', NULL, 'No', NULL, NULL, '11:15:00', '2025-05-08', '2025-05-08 03:15:40', NULL),
(22, 8, 'Headache', NULL, 'No', NULL, NULL, '11:15:00', '2025-05-08', '2025-05-08 03:16:01', NULL),
(23, 8, 'Headache', NULL, 'No', NULL, NULL, '11:18:00', '2025-05-08', '2025-05-08 03:18:10', NULL),
(24, 8, 'Headache', NULL, 'No', NULL, NULL, '11:20:00', '2025-05-08', '2025-05-08 03:20:37', NULL),
(25, 8, 'Headache', NULL, 'No', NULL, NULL, '11:20:00', '2025-05-08', '2025-05-08 03:21:12', NULL),
(26, 8, 'Headache', NULL, 'No', NULL, NULL, '11:21:00', '2025-05-08', '2025-05-08 03:21:20', NULL),
(27, 8, 'Headache', NULL, 'No', NULL, NULL, '11:21:00', '2025-05-08', '2025-05-08 03:21:45', NULL),
(28, 8, 'Headache', NULL, 'No', NULL, NULL, '11:26:00', '2025-05-08', '2025-05-08 03:26:18', NULL),
(29, 8, 'Headache', NULL, 'No', NULL, NULL, '11:26:00', '2025-05-08', '2025-05-08 03:27:32', NULL),
(30, 8, 'Headache', NULL, 'No', NULL, NULL, '11:28:00', '2025-05-08', '2025-05-08 03:28:23', NULL),
(31, 8, 'Headache', NULL, 'No', NULL, NULL, '11:33:00', '2025-05-08', '2025-05-08 03:33:47', NULL),
(32, 8, 'Headache', NULL, 'No', NULL, NULL, '11:35:00', '2025-05-08', '2025-05-08 03:35:56', NULL),
(33, 8, 'Headache', NULL, 'No', NULL, NULL, '11:36:00', '2025-05-08', '2025-05-08 03:36:21', NULL),
(34, 8, 'Headache', NULL, 'No', NULL, NULL, '11:36:00', '2025-05-08', '2025-05-08 03:36:54', NULL),
(35, 8, 'Headache', NULL, 'No', NULL, NULL, '11:45:00', '2025-05-08', '2025-05-08 03:45:26', NULL),
(36, 8, 'Headache', NULL, 'No', NULL, NULL, '11:48:00', '2025-05-08', '2025-05-08 03:48:11', NULL),
(37, 8, 'Headache', NULL, 'No', NULL, NULL, '11:51:00', '2025-05-08', '2025-05-08 03:51:58', NULL),
(38, 8, 'Headache', NULL, 'No', NULL, NULL, '16:06:00', '2025-05-08', '2025-05-08 08:06:50', NULL),
(39, 8, 'Headache', NULL, 'No', NULL, NULL, '16:06:00', '2025-05-08', '2025-05-08 08:07:18', NULL),
(40, 8, 'Headache', NULL, 'No', NULL, NULL, '16:09:00', '2025-05-08', '2025-05-08 08:09:56', NULL),
(41, 8, 'Headache', NULL, 'No', NULL, NULL, '16:16:00', '2025-05-08', '2025-05-08 08:16:08', NULL),
(42, 8, 'Headache', NULL, 'No', NULL, NULL, '16:17:00', '2025-05-08', '2025-05-08 08:18:04', NULL),
(43, 8, 'Headache', NULL, 'No', NULL, NULL, '16:22:00', '2025-05-08', '2025-05-08 08:22:21', NULL),
(44, 8, 'Headache', NULL, 'No', NULL, NULL, '16:23:00', '2025-05-08', '2025-05-08 08:23:56', NULL),
(45, 8, 'Headache', NULL, 'No', NULL, NULL, '16:23:00', '2025-05-08', '2025-05-08 08:25:53', NULL),
(46, 8, 'Headache', NULL, 'No', NULL, NULL, '16:25:00', '2025-05-08', '2025-05-08 08:28:06', NULL),
(47, 19, 'Headache', NULL, 'Yes', 'BIogesic', 1, '15:23:00', '2025-05-14', '2025-05-14 07:24:22', NULL),
(48, 8, 'Headache', NULL, 'No', NULL, NULL, '15:24:00', '2025-05-14', '2025-05-14 07:25:43', NULL),
(49, 20, 'High Blood Pressure', NULL, 'No', NULL, NULL, '15:27:00', '2025-05-14', '2025-05-14 07:28:12', NULL),
(50, 21, 'Headache', NULL, 'No', NULL, NULL, '12:25:00', '2025-05-18', '2025-05-18 04:25:47', NULL),
(51, 22, 'Headache', NULL, 'Yes', 'BIogesic', 1, '13:35:00', '2025-05-18', '2025-05-18 05:36:30', NULL),
(52, 8, 'Stomach Ache', NULL, 'No', NULL, NULL, '09:30:00', '2025-05-20', '2025-05-20 01:30:28', NULL),
(53, 8, 'Headache', NULL, 'Yes', 'BIogesic', 1, '22:20:00', '2025-05-21', '2025-05-21 14:20:53', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date_status` (`appointment_date`,`status`);

--
-- Indexes for table `grade_years`
--
ALTER TABLE `grade_years`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `log_visits`
--
ALTER TABLE `log_visits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `medicine_logs`
--
ALTER TABLE `medicine_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `program_sections`
--
ALTER TABLE `program_sections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_name` (`medicine_name`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_visit_date` (`visit_date`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `grade_years`
--
ALTER TABLE `grade_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `log_visits`
--
ALTER TABLE `log_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `medicine_logs`
--
ALTER TABLE `medicine_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `program_sections`
--
ALTER TABLE `program_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `medicine_logs`
--
ALTER TABLE `medicine_logs`
  ADD CONSTRAINT `medicine_logs_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicine_logs_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `visits_ibfk_2` FOREIGN KEY (`medicine_name`) REFERENCES `medicines` (`name`) ON DELETE SET NULL,
  ADD CONSTRAINT `visits_ibfk_3` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
