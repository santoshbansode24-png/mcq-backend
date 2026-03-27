-- ============================================================
-- Performance Indexes for My Exam Feature
-- Run once on your database (MySQL/MariaDB)
-- ============================================================

-- 1. Index on mcqs.chapter_id
--    Used by: generate_custom_test.php (WHERE chapter_id IN (...))
--             get_chapters.php (COUNT mcqs per chapter)
ALTER TABLE mcqs ADD INDEX IF NOT EXISTS idx_mcqs_chapter_id (chapter_id);

-- 2. Index on chapters.subject_id
--    Used by: get_chapters.php (WHERE ch.subject_id = ?)
ALTER TABLE chapters ADD INDEX IF NOT EXISTS idx_chapters_subject_id (subject_id);

-- 3. Covering index on chapters for the ordered listing query
--    Covers subject_id + chapter_order + chapter_name — avoids extra lookups
ALTER TABLE chapters ADD INDEX IF NOT EXISTS idx_chapters_subject_order (subject_id, chapter_order, chapter_name);

-- 4. Index on videos.chapter_id (used in COUNT(DISTINCT v.video_id))
ALTER TABLE videos ADD INDEX IF NOT EXISTS idx_videos_chapter_id (chapter_id);

-- 5. Index on notes.chapter_id (used in COUNT(DISTINCT n.note_id))
ALTER TABLE notes ADD INDEX IF NOT EXISTS idx_notes_chapter_id (chapter_id);

-- Verify indexes were created
SHOW INDEX FROM mcqs WHERE Key_name LIKE 'idx_mcqs%';
SHOW INDEX FROM chapters WHERE Key_name LIKE 'idx_chapters%';
