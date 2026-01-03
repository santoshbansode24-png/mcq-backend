-- Chapter-wise Progress Tracking Schema
-- Creates mcq_attempts table to track individual question attempts

CREATE TABLE IF NOT EXISTS mcq_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mcq_id INT NOT NULL,
    chapter_id INT NOT NULL,
    selected_answer VARCHAR(1),
    correct_answer VARCHAR(1),
    is_correct BOOLEAN,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (mcq_id) REFERENCES mcqs(mcq_id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE,
    INDEX idx_user_chapter (user_id, chapter_id),
    INDEX idx_user_mcq (user_id, mcq_id),
    INDEX idx_correctness (user_id, chapter_id, is_correct)
);
