-- ========================================================
-- Fix for "Illegal mix of collations" Error
-- ========================================================
-- This script normalizes all tables to utf8mb4_unicode_ci
-- to ensure compatibility with Marathi characters and 
-- prevent errors during joins or comparisons.

USE veeru_db;

-- 1. Fix Database default
ALTER DATABASE veeru_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Fix Tables
ALTER TABLE classes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE subjects CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE chapters CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE mcqs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE notes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE app_content_updates CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3. Fix Connection-dependent values
SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';

SELECT '✓ Database and Tables normalized to utf8mb4_unicode_ci' AS 'Status';
