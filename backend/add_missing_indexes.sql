-- ============================================================================
-- ADD MISSING INDEXES FOR PERFORMANCE OPTIMIZATION
-- ============================================================================
-- This script adds indexes to foreign key columns to speed up JOINs and filtering.
-- Without these, the app will slow down exponentially as data grows.
-- ============================================================================

USE veeru_db;

-- 1. Classes & Boards
ALTER TABLE classes ADD INDEX idx_board_id (board_id);

-- 2. Subjects
ALTER TABLE subjects ADD INDEX idx_class_id (class_id);

-- 3. Chapters
ALTER TABLE chapters ADD INDEX idx_subject_id (subject_id);
ALTER TABLE chapters ADD INDEX idx_chapter_order (chapter_order);

-- 4. MCQs (Crucial for performance)
ALTER TABLE mcqs ADD INDEX idx_chapter_id (chapter_id);
ALTER TABLE mcqs ADD INDEX idx_difficulty (difficulty);

-- 5. Notes & Videos
ALTER TABLE notes ADD INDEX idx_chapter_id (chapter_id);
ALTER TABLE videos ADD INDEX idx_chapter_id (chapter_id);

-- 6. User & Class Mapping
ALTER TABLE users ADD INDEX idx_class_id (class_id);
ALTER TABLE users ADD INDEX idx_school_name (school_name);

-- 7. Notifications & Class Updates (Highly active tables)
ALTER TABLE class_updates ADD INDEX idx_class_id (class_id);
ALTER TABLE class_updates ADD INDEX idx_teacher_id (teacher_id);
ALTER TABLE class_updates ADD INDEX idx_created_at (created_at);

-- 8. Test Progress (Growth table)
ALTER TABLE test_progress ADD INDEX idx_user_id (user_id);
ALTER TABLE test_progress ADD INDEX idx_chapter_id (chapter_id);
ALTER TABLE test_progress ADD INDEX idx_completed_at (completed_at);

SELECT '✓ Successfully added 15 performance indexes!' AS 'Performance Audit';
