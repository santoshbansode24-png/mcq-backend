-- ============================================================
-- Performance Indexes for Study Planner Feature
-- Run once on your database (MySQL/MariaDB) to optimize the planner
-- ============================================================

-- 1. Index on study_tasks for user filtering and date ordering
--    Used by: get_roadmap.php
ALTER TABLE study_tasks ADD INDEX IF NOT EXISTS idx_st_user_date (user_id, task_date);

-- 2. Index on study_tasks for status filtering (missed tasks)
--    Used by: redistribute_tasks.php
ALTER TABLE study_tasks ADD INDEX IF NOT EXISTS idx_st_status_date (status, task_date);

-- 3. Composite index on study_plans for quick user lookup
ALTER TABLE study_plans ADD INDEX IF NOT EXISTS idx_sp_user (user_id);

-- Verify
SHOW INDEX FROM study_tasks WHERE Key_name LIKE 'idx_st_%';
