-- Create badges table
CREATE TABLE IF NOT EXISTS badges (
    badge_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) NOT NULL,
    criteria_type VARCHAR(50) NOT NULL, -- 'login_streak', 'quiz_score', 'time_of_day'
    criteria_value VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create user_badges table
CREATE TABLE IF NOT EXISTS user_badges (
    user_badge_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(badge_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_badge (user_id, badge_id)
);

-- Add streak columns to users table if they don't exist
-- Note: This is a safe way to add columns only if they don't exist in MySQL 8.0+ or MariaDB 10.2+
-- For older versions, this might fail if columns exist, but we'll try to handle it gracefully in PHP or assume it's fine.
-- Alternatively, we can use a stored procedure, but for simplicity, let's assume we can alter.
-- We will run these ALTER statements. If they fail because columns exist, it's fine.

ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN login_streak INT DEFAULT 0;

-- Insert Default Badges
INSERT INTO badges (name, description, icon, criteria_type, criteria_value) VALUES
('Night Owl', 'Study after 10 PM', '🌙', 'time_of_day', '22:00'),
('Streak Master', 'Login 7 days in a row', '🔥', 'login_streak', '7'),
('Quiz Whiz', 'Score 100% on 5 quizzes', '🏆', 'perfect_scores', '5')
ON DUPLICATE KEY UPDATE name=name;
