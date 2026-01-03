-- ============================================
-- Veeru - Database Schema
-- Database Name: veeru_db
-- Created: November 2025
-- ============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS `veeru_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `veeru_db`;

-- ============================================
-- Table 1: Users (Admin, Teacher, Student)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `user_type` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
  `phone` VARCHAR(20),
  `class_id` INT DEFAULT NULL,
  `subscription_status` ENUM('active', 'inactive') DEFAULT 'inactive',
  `subscription_expiry` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (`email`),
  INDEX idx_user_type (`user_type`),
  INDEX idx_class_id (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 2: Classes
-- ============================================
CREATE TABLE IF NOT EXISTS `classes` (
  `class_id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_name` VARCHAR(50) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 3: Subjects
-- ============================================
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE,
  INDEX idx_class_id (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 4: Chapters
-- ============================================
CREATE TABLE IF NOT EXISTS `chapters` (
  `chapter_id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_id` INT NOT NULL,
  `chapter_name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `chapter_order` INT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE,
  INDEX idx_subject_id (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 5: Videos
-- ============================================
CREATE TABLE IF NOT EXISTS `videos` (
  `video_id` INT AUTO_INCREMENT PRIMARY KEY,
  `chapter_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `description` TEXT,
  `duration` VARCHAR(20),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`chapter_id`) ON DELETE CASCADE,
  INDEX idx_chapter_id (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 6: Notes
-- ============================================
CREATE TABLE IF NOT EXISTS `notes` (
  `note_id` INT AUTO_INCREMENT PRIMARY KEY,
  `chapter_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `file_path` VARCHAR(255),
  `content` LONGTEXT,
  `note_type` ENUM('pdf', 'html') DEFAULT 'html',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`chapter_id`) ON DELETE CASCADE,
  INDEX idx_chapter_id (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 7: MCQs (Multiple Choice Questions)
-- ============================================
CREATE TABLE IF NOT EXISTS `mcqs` (
  `mcq_id` INT AUTO_INCREMENT PRIMARY KEY,
  `chapter_id` INT NOT NULL,
  `question` TEXT NOT NULL,
  `option_a` TEXT NOT NULL,
  `option_b` TEXT NOT NULL,
  `option_c` TEXT NOT NULL,
  `option_d` TEXT NOT NULL,
  `correct_answer` ENUM('a', 'b', 'c', 'd') NOT NULL,
  `explanation` TEXT,
  `difficulty` ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`chapter_id`) ON DELETE CASCADE,
  INDEX idx_chapter_id (`chapter_id`),
  INDEX idx_difficulty (`difficulty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 8: Student Progress
-- ============================================
CREATE TABLE IF NOT EXISTS `student_progress` (
  `progress_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `chapter_id` INT,
  `video_id` INT,
  `mcq_score` INT,
  `total_mcq` INT,
  `percentage` DECIMAL(5,2),
  `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  INDEX idx_user_id (`user_id`),
  INDEX idx_chapter_id (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 9: Notifications
-- ============================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE,
  INDEX idx_teacher_id (`teacher_id`),
  INDEX idx_class_id (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 10: Subscriptions
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `plan_id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `duration_days` INT NOT NULL,
  `description` TEXT,
  `features` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Insert Default Data
-- ============================================

-- Insert Admin User (Password: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `user_type`, `subscription_status`) VALUES
('Admin User', 'admin@example.com', '$2y$10$vkamP0Hv29.J9ogZ6.0vEel1kIysAxWaE2AXU2ktDEsppKSQhPTVq', 'admin', 'active');

-- Insert Sample Teacher (Password: teacher123)
INSERT INTO `users` (`name`, `email`, `password`, `user_type`, `phone`) VALUES
('John Teacher', 'teacher@example.com', '$2y$10$KjEjJi7JanUFsuuewIAXUO/iQnnze4pGsAK1psWaDC12PFqgYA/C6', 'teacher', '1234567890');

-- Insert Sample Student (Password: student123)
INSERT INTO `users` (`name`, `email`, `password`, `user_type`, `class_id`, `phone`, `subscription_status`) VALUES
('Jane Student', 'student@example.com', '$2y$10$iYMycgOjne9WBt5d4DzRAOJetXuQeKga7SypV2zfl3HylcHhAdWh.', 'student', 1, '9876543210', 'active');

-- Insert Classes (1-12)
INSERT INTO `classes` (`class_name`) VALUES
('Class 1'), ('Class 2'), ('Class 3'), ('Class 4'), ('Class 5'),
('Class 6'), ('Class 7'), ('Class 8'), ('Class 9'), ('Class 10'),
('Class 11'), ('Class 12');

-- Insert Sample Subjects for Class 10
INSERT INTO `subjects` (`class_id`, `subject_name`, `description`) VALUES
(10, 'Mathematics', 'Advanced mathematics for Class 10'),
(10, 'Science', 'Physics, Chemistry, and Biology'),
(10, 'English', 'English language and literature'),
(10, 'Social Studies', 'History, Geography, and Civics');

-- Insert Sample Subjects for Class 9
INSERT INTO `subjects` (`class_id`, `subject_name`, `description`) VALUES
(9, 'Mathematics', 'Mathematics for Class 9'),
(9, 'Science', 'Science fundamentals'),
(9, 'English', 'English basics');

-- Insert Sample Chapters for Mathematics (Class 10)
INSERT INTO `chapters` (`subject_id`, `chapter_name`, `description`, `chapter_order`) VALUES
(1, 'Real Numbers', 'Introduction to real numbers and their properties', 1),
(1, 'Polynomials', 'Understanding polynomials and algebraic expressions', 2),
(1, 'Linear Equations', 'Solving linear equations in two variables', 3),
(1, 'Quadratic Equations', 'Quadratic equations and their solutions', 4),
(1, 'Arithmetic Progressions', 'Sequences and series', 5);

-- Insert Sample Chapters for Science (Class 10)
INSERT INTO `chapters` (`subject_id`, `chapter_name`, `description`, `chapter_order`) VALUES
(2, 'Chemical Reactions', 'Types of chemical reactions', 1),
(2, 'Acids, Bases and Salts', 'Properties and reactions', 2),
(2, 'Light - Reflection and Refraction', 'Optical phenomena', 3);

-- Insert Sample Videos
INSERT INTO `videos` (`chapter_id`, `title`, `url`, `description`, `duration`) VALUES
(1, 'Introduction to Real Numbers', 'https://www.youtube.com/watch?v=example1', 'Basic concepts of real numbers', '15:30'),
(1, 'Properties of Real Numbers', 'https://www.youtube.com/watch?v=example2', 'Detailed explanation of properties', '20:45'),
(2, 'What are Polynomials?', 'https://www.youtube.com/watch?v=example3', 'Introduction to polynomials', '18:20');

-- Insert Sample Notes
INSERT INTO `notes` (`chapter_id`, `title`, `content`, `note_type`) VALUES
(1, 'Real Numbers - Complete Notes', '<h1>Real Numbers</h1><p>Real numbers include all rational and irrational numbers...</p>', 'html'),
(2, 'Polynomials Study Guide', '<h1>Polynomials</h1><p>A polynomial is an expression consisting of variables and coefficients...</p>', 'html');

-- Insert Sample MCQs for Real Numbers
INSERT INTO `mcqs` (`chapter_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `difficulty`) VALUES
(1, 'Which of the following is a rational number?', '√2', 'π', '0.5', '√3', 'c', '0.5 can be expressed as 1/2, which is a ratio of integers', 'easy'),
(1, 'The decimal expansion of 22/7 is:', 'Terminating', 'Non-terminating and repeating', 'Non-terminating and non-repeating', 'None of these', 'b', '22/7 is a rational number with non-terminating repeating decimal', 'medium'),
(1, 'Which is an irrational number?', '0.333...', '√16', '√5', '7/3', 'c', '√5 cannot be expressed as a ratio of integers', 'easy'),
(1, 'The product of a non-zero rational and an irrational number is:', 'Always rational', 'Always irrational', 'Can be rational or irrational', 'Always zero', 'b', 'Product of rational and irrational is always irrational', 'medium'),
(1, 'HCF of 26 and 91 is:', '13', '26', '91', '1', 'a', '26 = 2 × 13, 91 = 7 × 13, so HCF is 13', 'easy');

-- Insert Sample MCQs for Polynomials
INSERT INTO `mcqs` (`chapter_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `difficulty`) VALUES
(2, 'The degree of polynomial 5x³ + 4x² + 7x is:', '1', '2', '3', '7', 'c', 'The highest power of x is 3', 'easy'),
(2, 'A polynomial of degree 2 is called:', 'Linear', 'Quadratic', 'Cubic', 'Biquadratic', 'b', 'Degree 2 polynomial is quadratic', 'easy'),
(2, 'Zero of polynomial p(x) = 2x + 5 is:', '-5/2', '5/2', '-2/5', '2/5', 'a', 'Set 2x + 5 = 0, then x = -5/2', 'medium');

-- Insert Sample Subscription Plans
INSERT INTO `subscriptions` (`plan_name`, `price`, `duration_days`, `description`, `features`) VALUES
('Free Trial', 0.00, 7, '7-day free trial', 'Access to basic content, Limited MCQs'),
('Monthly Plan', 99.00, 30, 'Monthly subscription', 'Full access to all content, Unlimited MCQs, AI Tutor'),
('Yearly Plan', 999.00, 365, 'Annual subscription with discount', 'Full access, Unlimited MCQs, AI Tutor, Priority Support, Offline Downloads');

-- Insert Sample Notification
INSERT INTO `notifications` (`teacher_id`, `class_id`, `title`, `message`) VALUES
(2, 10, 'Homework Assignment', 'Complete Chapter 1 exercises by Friday. Focus on problems 1-10.'),
(2, 10, 'Test Announcement', 'Unit test on Real Numbers and Polynomials scheduled for next Monday.');

-- ============================================
-- Create Indexes for Better Performance
-- ============================================

CREATE INDEX idx_user_class ON users(class_id, user_type);
CREATE INDEX idx_subject_class ON subjects(class_id);
CREATE INDEX idx_chapter_subject ON chapters(subject_id);
CREATE INDEX idx_mcq_chapter ON mcqs(chapter_id);
CREATE INDEX idx_progress_user_chapter ON student_progress(user_id, chapter_id);

-- ============================================
-- Database Setup Complete!
-- ============================================

-- Default Credentials:
-- Admin: admin@example.com / admin123
-- Teacher: teacher@example.com / teacher123
-- Student: student@example.com / student123
