-- ============================================================================
-- Add UNIQUE Constraints to Prevent Duplicate Data
-- ============================================================================
-- Purpose: Add database-level constraints to prevent duplicate entries
--          for classes, subjects, and chapters
--
-- This script will:
-- 1. Add UNIQUE constraint to classes table (class_name + board_id)
-- 2. Add UNIQUE constraint to subjects table (subject_name + class_id)
-- 3. Add UNIQUE constraint to chapters table (chapter_name + subject_id)
--
-- IMPORTANT: Run the cleanup_duplicate_classes.sql script FIRST to remove
--            existing duplicates before adding these constraints!
-- ============================================================================

USE veeru_db;

-- ============================================================================
-- STEP 1: Verify no duplicates exist (should return 0 for all)
-- ============================================================================
SELECT '=== CHECKING FOR EXISTING DUPLICATES ===' AS '';

-- Check for duplicate classes
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ PASS: No duplicate classes found'
        ELSE CONCAT('✗ WARNING: ', COUNT(*), ' duplicate class entries found - run cleanup script first!')
    END AS 'Duplicate Classes Check'
FROM (
    SELECT class_name, board_id, COUNT(*) as cnt
    FROM classes
    GROUP BY UPPER(class_name), board_id
    HAVING cnt > 1
) duplicates;

-- Check for duplicate subjects
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ PASS: No duplicate subjects found'
        ELSE CONCAT('✗ WARNING: ', COUNT(*), ' duplicate subject entries found - run cleanup script first!')
    END AS 'Duplicate Subjects Check'
FROM (
    SELECT subject_name, class_id, COUNT(*) as cnt
    FROM subjects
    GROUP BY UPPER(subject_name), class_id
    HAVING cnt > 1
) duplicates;

-- Check for duplicate chapters
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ PASS: No duplicate chapters found'
        ELSE CONCAT('✗ WARNING: ', COUNT(*), ' duplicate chapter entries found - run cleanup script first!')
    END AS 'Duplicate Chapters Check'
FROM (
    SELECT chapter_name, subject_id, COUNT(*) as cnt
    FROM chapters
    GROUP BY UPPER(chapter_name), subject_id
    HAVING cnt > 1
) duplicates;

-- ============================================================================
-- STEP 2: Add UNIQUE Constraints
-- ============================================================================
SELECT '=== ADDING UNIQUE CONSTRAINTS ===' AS '';

-- Constraint 1: Prevent duplicate class names within the same board
ALTER TABLE classes
ADD CONSTRAINT unique_class_per_board 
UNIQUE (class_name, board_id);

SELECT '✓ Added UNIQUE constraint: unique_class_per_board (class_name + board_id)' AS 'Status';

-- Constraint 2: Prevent duplicate subject names within the same class
ALTER TABLE subjects
ADD CONSTRAINT unique_subject_per_class 
UNIQUE (subject_name, class_id);

SELECT '✓ Added UNIQUE constraint: unique_subject_per_class (subject_name + class_id)' AS 'Status';

-- Constraint 3: Prevent duplicate chapter names within the same subject
ALTER TABLE chapters
ADD CONSTRAINT unique_chapter_per_subject 
UNIQUE (chapter_name, subject_id);

SELECT '✓ Added UNIQUE constraint: unique_chapter_per_subject (chapter_name + subject_id)' AS 'Status';

-- ============================================================================
-- STEP 3: Verify constraints were added successfully
-- ============================================================================
SELECT '=== VERIFYING CONSTRAINTS ===' AS '';

-- Show all constraints on classes table
SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    TABLE_NAME
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = 'veeru_db' 
  AND TABLE_NAME = 'classes'
  AND CONSTRAINT_TYPE = 'UNIQUE';

-- Show all constraints on subjects table
SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    TABLE_NAME
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = 'veeru_db' 
  AND TABLE_NAME = 'subjects'
  AND CONSTRAINT_TYPE = 'UNIQUE';

-- Show all constraints on chapters table
SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    TABLE_NAME
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = 'veeru_db' 
  AND TABLE_NAME = 'chapters'
  AND CONSTRAINT_TYPE = 'UNIQUE';

-- ============================================================================
-- STEP 4: Test constraints (optional - will fail as expected)
-- ============================================================================
SELECT '=== CONSTRAINTS ADDED SUCCESSFULLY ===' AS '';
SELECT 'You can now test by trying to insert duplicate data - it should be rejected!' AS 'Next Steps';

-- Example test (uncomment to try):
-- INSERT INTO classes (class_name, board_id) VALUES ('Class 3', 1);
-- This should fail with: ERROR 1062 (23000): Duplicate entry 'Class 3-1' for key 'unique_class_per_board'

-- ============================================================================
-- NOTES
-- ============================================================================
-- 1. These constraints are CASE-SENSITIVE by default in MySQL
-- 2. To make them case-insensitive, we'll add normalization in the admin panel
-- 3. If you need to remove a constraint later, use:
--    ALTER TABLE table_name DROP INDEX constraint_name;
-- ============================================================================
