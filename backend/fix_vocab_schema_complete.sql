-- Fix user_vocab_progress
CREATE TABLE IF NOT EXISTS user_vocab_progress (
    progress_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    word_id INT NOT NULL,
    easiness_factor DECIMAL(5,2) DEFAULT 2.50,
    interval_days INT DEFAULT 1,
    repetitions INT DEFAULT 0,
    next_review_date DATE,
    review_count INT DEFAULT 0,
    correct_count INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    last_rating INT DEFAULT 0,
    mastery_status VARCHAR(20) DEFAULT 'New',
    last_reviewed_at DATETIME,
    mastered_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, word_id)
);

-- Ensure columns exist in user_vocab_progress
ALTER TABLE user_vocab_progress
ADD COLUMN IF NOT EXISTS easiness_factor DECIMAL(5,2) DEFAULT 2.50,
ADD COLUMN IF NOT EXISTS interval_days INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS repetitions INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS review_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS correct_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS last_rating INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS mastery_status VARCHAR(20) DEFAULT 'New',
ADD COLUMN IF NOT EXISTS last_reviewed_at DATETIME,
ADD COLUMN IF NOT EXISTS mastered_at DATETIME;

-- Fix vocab_review_history
CREATE TABLE IF NOT EXISTS vocab_review_history (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    word_id INT NOT NULL,
    rating INT NOT NULL,
    time_taken_seconds INT DEFAULT 0,
    was_correct BOOLEAN DEFAULT 0,
    previous_interval INT,
    new_interval INT,
    previous_easiness DECIMAL(5,2),
    new_easiness DECIMAL(5,2),
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Fix user_vocab_stats (adding fields used in SRSService)
ALTER TABLE user_vocab_stats
ADD COLUMN IF NOT EXISTS total_reviews INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_practice_date DATE,
ADD COLUMN IF NOT EXISTS current_streak INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS longest_streak INT DEFAULT 0;

-- Fix vocab_words
ALTER TABLE vocab_words
ADD COLUMN IF NOT EXISTS times_reviewed INT DEFAULT 0;
