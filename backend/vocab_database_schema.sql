-- ============================================
-- Vocabulary Booster - Complete Database Schema
-- Spaced Repetition System (SRS) Implementation
-- ============================================

-- 1. VOCABULARY CATEGORIES TABLE
-- Manages word groups and access control (Free/Premium)
CREATE TABLE IF NOT EXISTS vocab_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    access_level ENUM('Free', 'Premium') DEFAULT 'Free',
    description TEXT,
    icon_emoji VARCHAR(10) DEFAULT '📚',
    word_count INT DEFAULT 0,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_access_level (access_level),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. VOCABULARY WORDS TABLE
-- Stores all word content (2,000+ words)
CREATE TABLE IF NOT EXISTS vocab_words (
    word_id INT PRIMARY KEY AUTO_INCREMENT,
    word VARCHAR(100) NOT NULL,
    definition TEXT NOT NULL,
    example_sentence TEXT,
    category_id INT NOT NULL,
    pronunciation_text VARCHAR(100),
    audio_file_url VARCHAR(255),
    difficulty_level ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
    usage_frequency ENUM('Common', 'Moderate', 'Rare') DEFAULT 'Moderate',
    synonyms TEXT COMMENT 'Comma-separated synonyms',
    antonyms TEXT COMMENT 'Comma-separated antonyms',
    word_type VARCHAR(50) COMMENT 'noun, verb, adjective, etc.',
    etymology TEXT COMMENT 'Word origin/history',
    mnemonic_hint TEXT COMMENT 'Memory aid',
    is_active BOOLEAN DEFAULT TRUE,
    times_reviewed INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES vocab_categories(category_id) ON DELETE RESTRICT,
    INDEX idx_word (word),
    INDEX idx_category (category_id),
    INDEX idx_difficulty (difficulty_level),
    INDEX idx_active (is_active),
    FULLTEXT INDEX ft_word_definition (word, definition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. USER VOCABULARY PROGRESS TABLE
-- SRS CORE: Tracks individual user progress on each word
CREATE TABLE IF NOT EXISTS user_vocab_progress (
    progress_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    word_id INT NOT NULL,
    
    -- SRS Algorithm Fields (SuperMemo-2)
    easiness_factor DECIMAL(4, 2) DEFAULT 2.5 COMMENT 'EF: 1.3 to 2.5+, affects interval growth',
    interval_days INT DEFAULT 1 COMMENT 'Days until next review',
    repetitions INT DEFAULT 0 COMMENT 'Number of successful repetitions',
    next_review_date DATE NOT NULL,
    
    -- Progress Tracking
    review_count INT DEFAULT 0 COMMENT 'Total times reviewed',
    correct_count INT DEFAULT 0 COMMENT 'Times answered correctly',
    mastery_status ENUM('New', 'Learning', 'Review', 'Mastered') DEFAULT 'New',
    
    -- Performance Metrics
    average_rating DECIMAL(3, 2) DEFAULT 0.00 COMMENT 'Average of all ratings (1-5)',
    last_rating INT DEFAULT 0 COMMENT 'Last rating given (1-5)',
    
    -- Timestamps
    first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_reviewed_at TIMESTAMP NULL,
    mastered_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (word_id) REFERENCES vocab_words(word_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_word (user_id, word_id),
    INDEX idx_user_next_review (user_id, next_review_date),
    INDEX idx_mastery_status (mastery_status),
    INDEX idx_next_review (next_review_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. MCQ-VOCABULARY LINK TABLE
-- Links vocabulary words to MCQ questions for contextual learning
CREATE TABLE IF NOT EXISTS mcq_vocab_link (
    link_id INT PRIMARY KEY AUTO_INCREMENT,
    mcq_id INT NOT NULL,
    word_id INT NOT NULL,
    relevance_score DECIMAL(3, 2) DEFAULT 1.00 COMMENT 'How relevant the word is to the question',
    link_type ENUM('keyword', 'context', 'answer') DEFAULT 'context',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mcq_id) REFERENCES mcqs(mcq_id) ON DELETE CASCADE,
    FOREIGN KEY (word_id) REFERENCES vocab_words(word_id) ON DELETE CASCADE,
    UNIQUE KEY unique_mcq_word (mcq_id, word_id),
    INDEX idx_mcq (mcq_id),
    INDEX idx_word (word_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. USER VOCABULARY STATS TABLE
-- Gamification: Tracks overall user statistics and achievements
CREATE TABLE IF NOT EXISTS user_vocab_stats (
    stats_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    
    -- Learning Progress
    total_words_learned INT DEFAULT 0 COMMENT 'Words added to learning list',
    words_in_progress INT DEFAULT 0 COMMENT 'Currently learning',
    words_mastered INT DEFAULT 0 COMMENT 'Fully mastered words',
    
    -- Review Statistics
    total_reviews INT DEFAULT 0 COMMENT 'Total review sessions',
    total_correct INT DEFAULT 0 COMMENT 'Total correct answers',
    accuracy_percentage DECIMAL(5, 2) DEFAULT 0.00,
    
    -- Streak Tracking
    current_streak INT DEFAULT 0 COMMENT 'Current daily streak',
    longest_streak INT DEFAULT 0 COMMENT 'Best streak achieved',
    last_practice_date DATE,
    
    -- Time Tracking
    total_study_time_minutes INT DEFAULT 0,
    average_session_time_minutes DECIMAL(5, 2) DEFAULT 0.00,
    
    -- Achievements
    level INT DEFAULT 1 COMMENT 'User level (1-100)',
    experience_points INT DEFAULT 0 COMMENT 'XP for gamification',
    badges_earned TEXT COMMENT 'JSON array of badge IDs',
    
    -- Premium Features
    has_premium_access BOOLEAN DEFAULT FALSE,
    premium_expiry_date DATE NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_streak (current_streak),
    INDEX idx_mastered (words_mastered),
    INDEX idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. VOCABULARY REVIEW HISTORY TABLE
-- Detailed history of all review sessions for analytics
CREATE TABLE IF NOT EXISTS vocab_review_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    word_id INT NOT NULL,
    rating INT NOT NULL COMMENT 'User rating: 1-5',
    time_taken_seconds INT DEFAULT 0,
    was_correct BOOLEAN DEFAULT FALSE,
    previous_interval INT,
    new_interval INT,
    previous_easiness DECIMAL(4, 2),
    new_easiness DECIMAL(4, 2),
    review_type ENUM('scheduled', 'extra', 'failed') DEFAULT 'scheduled',
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (word_id) REFERENCES vocab_words(word_id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, reviewed_at),
    INDEX idx_word (word_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TRIGGERS FOR AUTOMATIC UPDATES
-- ============================================

-- Update category word count when word is added/removed
DELIMITER //
CREATE TRIGGER update_category_count_insert
AFTER INSERT ON vocab_words
FOR EACH ROW
BEGIN
    UPDATE vocab_categories 
    SET word_count = word_count + 1 
    WHERE category_id = NEW.category_id;
END//

CREATE TRIGGER update_category_count_delete
AFTER DELETE ON vocab_words
FOR EACH ROW
BEGIN
    UPDATE vocab_categories 
    SET word_count = word_count - 1 
    WHERE category_id = OLD.category_id;
END//

-- Update user stats when progress changes
CREATE TRIGGER update_user_stats_on_progress
AFTER UPDATE ON user_vocab_progress
FOR EACH ROW
BEGIN
    DECLARE total_learned INT;
    DECLARE in_progress INT;
    DECLARE mastered INT;
    
    SELECT COUNT(*) INTO total_learned
    FROM user_vocab_progress
    WHERE user_id = NEW.user_id;
    
    SELECT COUNT(*) INTO in_progress
    FROM user_vocab_progress
    WHERE user_id = NEW.user_id AND mastery_status IN ('Learning', 'Review');
    
    SELECT COUNT(*) INTO mastered
    FROM user_vocab_progress
    WHERE user_id = NEW.user_id AND mastery_status = 'Mastered';
    
    UPDATE user_vocab_stats
    SET 
        total_words_learned = total_learned,
        words_in_progress = in_progress,
        words_mastered = mastered,
        updated_at = CURRENT_TIMESTAMP
    WHERE user_id = NEW.user_id;
END//

DELIMITER ;

-- ============================================
-- INITIAL DATA SEEDING
-- ============================================

-- Insert default categories
INSERT INTO vocab_categories (category_name, access_level, description, icon_emoji, display_order) VALUES
('Core Academic Words', 'Free', 'Essential words for academic success', '📖', 1),
('MCQ Starter Set', 'Free', 'Common words found in MCQ questions', '🎯', 2),
('Daily Essentials', 'Free', 'Everyday vocabulary for students', '☀️', 3),
('GRE Preparation', 'Premium', 'Advanced words for GRE exam', '🎓', 4),
('SAT Preparation', 'Premium', 'Essential SAT vocabulary', '📝', 5),
('Advanced Academic', 'Premium', 'College-level academic vocabulary', '🏛️', 6),
('Scientific Terms', 'Premium', 'Science and research vocabulary', '🔬', 7),
('Business English', 'Premium', 'Professional and business terms', '💼', 8),
('Literary Words', 'Premium', 'Advanced literary vocabulary', '📚', 9),
('Idioms & Phrases', 'Premium', 'Common English idioms', '💬', 10);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Composite indexes for common queries
CREATE INDEX idx_user_status_date ON user_vocab_progress(user_id, mastery_status, next_review_date);
CREATE INDEX idx_category_difficulty ON vocab_words(category_id, difficulty_level);

-- ============================================
-- VIEWS FOR COMMON QUERIES
-- ============================================

-- View: User's due words for today
CREATE OR REPLACE VIEW v_user_due_words AS
SELECT 
    uvp.user_id,
    uvp.word_id,
    vw.word,
    vw.definition,
    vw.example_sentence,
    vw.difficulty_level,
    uvp.mastery_status,
    uvp.review_count,
    uvp.next_review_date,
    vc.category_name
FROM user_vocab_progress uvp
JOIN vocab_words vw ON uvp.word_id = vw.word_id
JOIN vocab_categories vc ON vw.category_id = vc.category_id
WHERE uvp.next_review_date <= CURDATE()
AND vw.is_active = TRUE;

-- View: User vocabulary statistics
CREATE OR REPLACE VIEW v_user_vocab_summary AS
SELECT 
    u.user_id,
    u.name,
    COALESCE(uvs.total_words_learned, 0) as total_words,
    COALESCE(uvs.words_mastered, 0) as mastered_words,
    COALESCE(uvs.current_streak, 0) as current_streak,
    COALESCE(uvs.longest_streak, 0) as longest_streak,
    COALESCE(uvs.accuracy_percentage, 0) as accuracy,
    COALESCE(uvs.level, 1) as level
FROM users u
LEFT JOIN user_vocab_stats uvs ON u.user_id = uvs.user_id;

-- ============================================
-- COMPLETION MESSAGE
-- ============================================
SELECT 'Vocabulary Booster Database Schema Created Successfully!' as Status;
