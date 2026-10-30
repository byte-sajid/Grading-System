-- ==============================================================
-- University Student Marks & Grading System Database Schema
-- Database Name: varsity_grading_db
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `varsity_grading_db` 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `varsity_grading_db`;

-- -------------------------------------------------------------
-- 1. Admins Table
-- Stores authorized examination administrators
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` VARCHAR(50) DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 2. Sessions Lookup Table
-- e.g., 2020-2021, 2021-2022, 2022-2023, 2023-2024, 2024-2025
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_name` VARCHAR(20) NOT NULL UNIQUE,
    `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3. Semesters Lookup Table
-- e.g., 1st Semester to 8th Semester
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `semesters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `semester_code` VARCHAR(20) NOT NULL UNIQUE,
    `semester_name` VARCHAR(50) NOT NULL,
    `display_order` INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. Students Table
-- Stores registered university students
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `roll_no` VARCHAR(50) NOT NULL UNIQUE,
    `registration_no` VARCHAR(50) DEFAULT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `department` VARCHAR(100) DEFAULT 'Computer Science & Engineering',
    `session` VARCHAR(20) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. Marksheet / Semester Results Table
-- Stores marks for: Attendance, Midterm, and 4 CSE Subjects
-- Includes credits, calculated grades, GPA, and total scores
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `marksheet` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `roll_no` VARCHAR(50) NOT NULL,
    `session` VARCHAR(20) NOT NULL,
    `semester` VARCHAR(50) NOT NULL,
    
    -- Continuous Assessment
    `attendance_marks` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `attendance_max` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    `midterm_marks` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `midterm_max` DECIMAL(5,2) NOT NULL DEFAULT 20.00,

    -- CSE Subject 1
    `cse_sub1_code` VARCHAR(50) NOT NULL DEFAULT 'CSE-301',
    `cse_sub1_name` VARCHAR(150) NOT NULL DEFAULT 'Data Structures & Algorithms',
    `cse_sub1_marks` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `cse_sub1_max` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `cse_sub1_credit` DECIMAL(3,2) NOT NULL DEFAULT 3.00,
    `cse_sub1_grade` VARCHAR(5) DEFAULT 'F',
    `cse_sub1_point` DECIMAL(3,2) DEFAULT 0.00,

    -- CSE Subject 2
    `cse_sub2_code` VARCHAR(50) NOT NULL DEFAULT 'CSE-302',
    `cse_sub2_name` VARCHAR(150) NOT NULL DEFAULT 'Database Management Systems',
    `cse_sub2_marks` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `cse_sub2_max` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `cse_sub2_credit` DECIMAL(3,2) NOT NULL DEFAULT 3.00,
    `cse_sub2_grade` VARCHAR(5) DEFAULT 'F',
    `cse_sub2_point` DECIMAL(3,2) DEFAULT 0.00,

    -- CSE Subject 3
    `cse_sub3_code` VARCHAR(50) NOT NULL DEFAULT 'CSE-303',
    `cse_sub3_name` VARCHAR(150) NOT NULL DEFAULT 'Operating Systems',
    `cse_sub3_marks` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `cse_sub3_max` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `cse_sub3_credit` DECIMAL(3,2) NOT NULL DEFAULT 3.00,
    `cse_sub3_grade` VARCHAR(5) DEFAULT 'F',
    `cse_sub3_point` DECIMAL(3,2) DEFAULT 0.00,

    -- CSE Subject 4
    `cse_sub4_code` VARCHAR(50) NOT NULL DEFAULT 'CSE-304',
    `cse_sub4_name` VARCHAR(150) NOT NULL DEFAULT 'Computer Networks',
    `cse_sub4_marks` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `cse_sub4_max` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `cse_sub4_credit` DECIMAL(3,2) NOT NULL DEFAULT 3.00,
    `cse_sub4_grade` VARCHAR(5) DEFAULT 'F',
    `cse_sub4_point` DECIMAL(3,2) DEFAULT 0.00,

    -- Aggregate Calculations
    `total_obtained` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `total_max` DECIMAL(6,2) NOT NULL DEFAULT 430.00,
    `percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `gpa` DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    `letter_grade` VARCHAR(5) NOT NULL DEFAULT 'F',
    `status` VARCHAR(20) NOT NULL DEFAULT 'PASSED',
    `remarks` VARCHAR(150) DEFAULT 'Satisfactory',

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Prevent duplicate result for same student, session, and semester
    UNIQUE KEY `unique_student_session_semester` (`roll_no`, `session`, `semester`),
    INDEX `idx_search` (`roll_no`, `session`, `semester`),
    CONSTRAINT `fk_marksheet_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- INITIAL SEED DATA
-- ==============================================================

-- 1. Default Admin (Password: admin123)
INSERT INTO `admins` (`username`, `password_hash`, `full_name`, `role`) VALUES
('admin', '$2y$12$DLd.NPuVzZVkxXBFQzySluK3XNEd3KpHnqfp.taUnHo2PHYf8Ik9K', 'System Administrator', 'admin')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- 2. Academic Sessions
INSERT INTO `sessions` (`session_name`, `is_active`) VALUES
('2020-2021', 1),
('2021-2022', 1),
('2022-2023', 1),
('2023-2024', 1),
('2024-2025', 1)
ON DUPLICATE KEY UPDATE `session_name` = `session_name`;

-- 3. Academic Semesters
INSERT INTO `semesters` (`semester_code`, `semester_name`, `display_order`) VALUES
('1st', '1st Semester', 1),
('2nd', '2nd Semester', 2),
('3rd', '3rd Semester', 3),
('4th', '4th Semester', 4),
('5th', '5th Semester', 5),
('6th', '6th Semester', 6),
('7th', '7th Semester', 7),
('8th', '8th Semester', 8)
ON DUPLICATE KEY UPDATE `semester_code` = `semester_code`;

-- 4. Sample Students
INSERT INTO `students` (`id`, `roll_no`, `registration_no`, `full_name`, `department`, `session`) VALUES
(1, 'CSE-2021-001', 'REG-2021-78901', 'Tanvir Ahmed', 'Computer Science & Engineering', '2021-2022'),
(2, 'CSE-2021-002', 'REG-2021-78902', 'Nusrat Jahan', 'Computer Science & Engineering', '2021-2022'),
(3, 'CSE-2022-015', 'REG-2022-89045', 'Rahim Chowdhury', 'Computer Science & Engineering', '2022-2023'),
(4, '101', 'REG-2021-10001', 'Sadia Islam', 'Computer Science & Engineering', '2021-2022'),
(5, '102', 'REG-2022-10002', 'Fahim Shakil', 'Computer Science & Engineering', '2022-2023')
ON DUPLICATE KEY UPDATE `roll_no` = `roll_no`;

-- 5. Sample Marksheet Records
-- Student 1: Tanvir Ahmed (5th Semester, 2021-2022) - High Achiever
INSERT INTO `marksheet` (
    `student_id`, `roll_no`, `session`, `semester`,
    `attendance_marks`, `attendance_max`,
    `midterm_marks`, `midterm_max`,
    `cse_sub1_code`, `cse_sub1_name`, `cse_sub1_marks`, `cse_sub1_max`, `cse_sub1_credit`, `cse_sub1_grade`, `cse_sub1_point`,
    `cse_sub2_code`, `cse_sub2_name`, `cse_sub2_marks`, `cse_sub2_max`, `cse_sub2_credit`, `cse_sub2_grade`, `cse_sub2_point`,
    `cse_sub3_code`, `cse_sub3_name`, `cse_sub3_marks`, `cse_sub3_max`, `cse_sub3_credit`, `cse_sub3_grade`, `cse_sub3_point`,
    `cse_sub4_code`, `cse_sub4_name`, `cse_sub4_marks`, `cse_sub4_max`, `cse_sub4_credit`, `cse_sub4_grade`, `cse_sub4_point`,
    `total_obtained`, `total_max`, `percentage`, `gpa`, `letter_grade`, `status`, `remarks`
) VALUES (
    1, 'CSE-2021-001', '2021-2022', '5th Semester',
    9.50, 10.00,
    18.50, 20.00,
    'CSE-311', 'Computer Networks', 88.00, 100.00, 3.00, 'A+', 4.00,
    'CSE-312', 'Web Technologies & Applications', 84.00, 100.00, 3.00, 'A+', 4.00,
    'CSE-313', 'Artificial Intelligence', 79.00, 100.00, 3.00, 'A', 3.75,
    'CSE-314', 'Compiler Design', 82.00, 100.00, 3.00, 'A+', 4.00,
    361.00, 430.00, 83.95, 3.94, 'A+', 'PASSED', 'Outstanding Performance'
) ON DUPLICATE KEY UPDATE `roll_no` = `roll_no`;

-- Student 2: Nusrat Jahan (5th Semester, 2021-2022) - Very Good
INSERT INTO `marksheet` (
    `student_id`, `roll_no`, `session`, `semester`,
    `attendance_marks`, `attendance_max`,
    `midterm_marks`, `midterm_max`,
    `cse_sub1_code`, `cse_sub1_name`, `cse_sub1_marks`, `cse_sub1_max`, `cse_sub1_credit`, `cse_sub1_grade`, `cse_sub1_point`,
    `cse_sub2_code`, `cse_sub2_name`, `cse_sub2_marks`, `cse_sub2_max`, `cse_sub2_credit`, `cse_sub2_grade`, `cse_sub2_point`,
    `cse_sub3_code`, `cse_sub3_name`, `cse_sub3_marks`, `cse_sub3_max`, `cse_sub3_credit`, `cse_sub3_grade`, `cse_sub3_point`,
    `cse_sub4_code`, `cse_sub4_name`, `cse_sub4_marks`, `cse_sub4_max`, `cse_sub4_credit`, `cse_sub4_grade`, `cse_sub4_point`,
    `total_obtained`, `total_max`, `percentage`, `gpa`, `letter_grade`, `status`, `remarks`
) VALUES (
    2, 'CSE-2021-002', '2021-2022', '5th Semester',
    8.00, 10.00,
    16.00, 20.00,
    'CSE-311', 'Computer Networks', 74.00, 100.00, 3.00, 'A-', 3.50,
    'CSE-312', 'Web Technologies & Applications', 78.00, 100.00, 3.00, 'A', 3.75,
    'CSE-313', 'Artificial Intelligence', 71.00, 100.00, 3.00, 'A-', 3.50,
    'CSE-314', 'Compiler Design', 68.00, 100.00, 3.00, 'B+', 3.25,
    315.00, 430.00, 73.26, 3.50, 'A-', 'PASSED', 'Very Good Performance'
) ON DUPLICATE KEY UPDATE `roll_no` = `roll_no`;

-- Student 4: Sadia Islam (Roll 101, 3rd Semester, 2021-2022)
INSERT INTO `marksheet` (
    `student_id`, `roll_no`, `session`, `semester`,
    `attendance_marks`, `attendance_max`,
    `midterm_marks`, `midterm_max`,
    `cse_sub1_code`, `cse_sub1_name`, `cse_sub1_marks`, `cse_sub1_max`, `cse_sub1_credit`, `cse_sub1_grade`, `cse_sub1_point`,
    `cse_sub2_code`, `cse_sub2_name`, `cse_sub2_marks`, `cse_sub2_max`, `cse_sub2_credit`, `cse_sub2_grade`, `cse_sub2_point`,
    `cse_sub3_code`, `cse_sub3_name`, `cse_sub3_marks`, `cse_sub3_max`, `cse_sub3_credit`, `cse_sub3_grade`, `cse_sub3_point`,
    `cse_sub4_code`, `cse_sub4_name`, `cse_sub4_marks`, `cse_sub4_max`, `cse_sub4_credit`, `cse_sub4_grade`, `cse_sub4_point`,
    `total_obtained`, `total_max`, `percentage`, `gpa`, `letter_grade`, `status`, `remarks`
) VALUES (
    4, '101', '2021-2022', '3rd Semester',
    9.00, 10.00,
    17.00, 20.00,
    'CSE-211', 'Algorithms & Complexity', 85.00, 100.00, 3.00, 'A+', 4.00,
    'CSE-212', 'Database Management Systems', 81.00, 100.00, 3.00, 'A+', 4.00,
    'CSE-213', 'Computer Architecture', 76.00, 100.00, 3.00, 'A', 3.75,
    'CSE-214', 'Theory of Computation', 70.00, 100.00, 3.00, 'A-', 3.50,
    338.00, 430.00, 78.60, 3.81, 'A', 'PASSED', 'Excellent Performance'
) ON DUPLICATE KEY UPDATE `roll_no` = `roll_no`;

