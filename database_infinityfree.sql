-- MCQ Project 2.0 - Database Schema
-- Simplified for InfinityFree Import

-- Table 1: Users
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
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (`email`),
  INDEX idx_user_type (`user_type`),
  INDEX idx_class_id (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: Classes
CREATE TABLE IF NOT EXISTS `classes` (
  `class_id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_name` VARCHAR(50) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: Subjects
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_class_id (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4: Chapters
CREATE TABLE IF NOT EXISTS `chapters` (
  `chapter_id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_id` INT NOT NULL,
  `chapter_name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `chapter_order` INT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_subject_id (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 5: Videos
CREATE TABLE IF NOT EXISTS `videos` (
  `video_id` INT AUTO_INCREMENT PRIMARY KEY,
  `chapter_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `description` TEXT,
  `duration` VARCHAR(20),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chapter_id (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 6: Notes
CREATE TABLE IF NOT EXISTS `notes` (
  `note_id` INT AUTO_INCREMENT PRIMARY KEY,
  `chapter_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `file_path` VARCHAR(255),
  `content` LONGTEXT,
  `note_type` ENUM('pdf', 'html') DEFAULT 'html',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chapter_id (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 7: MCQs
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
  INDEX idx_chapter_id (`chapter_id`),
  INDEX idx_difficulty (`difficulty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 8: Student Progress
CREATE TABLE IF NOT EXISTS `student_progress` (
  `progress_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `chapter_id` INT,
  `video_id` INT,
  `mcq_score` INT,
  `total_mcq` INT,
  `percentage` DECIMAL(5,2),
  `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (`user_id`),
  INDEX idx_chapter_id (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 9: Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_teacher_id (`teacher_id`),
  INDEX idx_class_id (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 10: Subscriptions
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

-- Table 11: Bookmarks
CREATE TABLE IF NOT EXISTS `bookmarks` (
  `bookmark_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `content_type` ENUM('mcq', 'video', 'note', 'chapter') NOT NULL,
  `content_id` INT NOT NULL,
  `chapter_id` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 12: Badges
CREATE TABLE IF NOT EXISTS `badges` (
  `badge_id` INT AUTO_INCREMENT PRIMARY KEY,
  `badge_name` VARCHAR(100) NOT NULL,
  `badge_description` TEXT,
  `badge_icon` VARCHAR(50),
  `requirement_type` VARCHAR(50),
  `requirement_value` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 13: User Badges
CREATE TABLE IF NOT EXISTS `user_badges` (
  `user_badge_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `badge_id` INT NOT NULL,
  `earned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 14: Push Tokens
CREATE TABLE IF NOT EXISTS `push_tokens` (
  `token_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `push_token` VARCHAR(255) NOT NULL,
  `device_type` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Data
INSERT INTO `users` (`name`, `email`, `password`, `user_type`, `subscription_status`) VALUES
('Admin User', 'admin@example.com', '$2y$10$vkamP0Hv29.J9ogZ6.0vEel1kIysAxWaE2AXU2ktDEsppKSQhPTVq', 'admin', 'active');

INSERT INTO `users` (`name`, `email`, `password`, `user_type`, `phone`) VALUES
('John Teacher', 'teacher@example.com', '$2y$10$KjEjJi7JanUFsuuewIAXUO/iQnnze4pGsAK1psWaDC12PFqgYA/C6', 'teacher', '1234567890');

INSERT INTO `users` (`name`, `email`, `password`, `user_type`, `class_id`, `phone`, `subscription_status`) VALUES
('Jane Student', 'student@example.com', '$2y$10$iYMycgOjne9WBt5d4DzRAOJetXuQeKga7SypV2zfl3HylcHhAdWh.', 'student', 1, '9876543210', 'active');

INSERT INTO `classes` (`class_name`) VALUES
('Class 1'), ('Class 2'), ('Class 3'), ('Class 4'), ('Class 5'),
('Class 6'), ('Class 7'), ('Class 8'), ('Class 9'), ('Class 10'),
('Class 11'), ('Class 12');

INSERT INTO `subjects` (`class_id`, `subject_name`, `description`) VALUES
(1, 'ENGLISH', 'English language basics'),
(1, 'MATHS', 'Mathematics fundamentals'),
(1, 'SCIENCE', 'Science concepts');

INSERT INTO `chapters` (`subject_id`, `chapter_name`, `description`, `chapter_order`) VALUES
(1, 'Alphabets', 'Learning A to Z', 1),
(1, 'Words', 'Basic words and vocabulary', 2);

INSERT INTO `mcqs` (`chapter_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `difficulty`) VALUES
(1, 'What is the first letter of the alphabet?', 'B', 'A', 'C', 'D', 'b', 'A is the first letter', 'easy'),
(1, 'How many letters are in the English alphabet?', '24', '25', '26', '27', 'c', 'There are 26 letters in English alphabet', 'easy');

INSERT INTO `badges` (`badge_name`, `badge_description`, `badge_icon`, `requirement_type`, `requirement_value`) VALUES
('First Steps', 'Complete your first quiz', 'star', 'quizzes_completed', 1),
('Quiz Master', 'Complete 10 quizzes', 'trophy', 'quizzes_completed', 10),
('Perfect Score', 'Get 100% in a quiz', 'medal', 'perfect_scores', 1);

INSERT INTO `subscriptions` (`plan_name`, `price`, `duration_days`, `description`, `features`) VALUES
('Free Trial', 0.00, 7, '7-day free trial', 'Access to basic content'),
('Monthly Plan', 99.00, 30, 'Monthly subscription', 'Full access to all content'),
('Yearly Plan', 999.00, 365, 'Annual subscription', 'Full access with discount');
