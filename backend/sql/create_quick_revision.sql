CREATE TABLE IF NOT EXISTS quick_revision (
    revision_id INT PRIMARY KEY AUTO_INCREMENT,
    chapter_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    key_points JSON NOT NULL,
    summary TEXT,
    title_hi VARCHAR(255) DEFAULT NULL,
    title_mr VARCHAR(255) DEFAULT NULL,
    key_points_hi JSON DEFAULT NULL,
    key_points_mr JSON DEFAULT NULL,
    summary_hi TEXT DEFAULT NULL,
    summary_mr TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chapter_created (chapter_id, created_at DESC),
    FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
