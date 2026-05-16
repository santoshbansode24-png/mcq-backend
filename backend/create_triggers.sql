-- ============================================================================
-- Create Database Triggers for Duplicate Prevention
-- ============================================================================
-- Purpose: Automatically prevent duplicates and normalize text to UPPERCASE
--          at the database level using BEFORE INSERT triggers
--
-- Features:
-- 1. Auto-convert all text to UPPERCASE
-- 2. Trim whitespace
-- 3. Check for duplicates before insert
-- 4. Raise error if duplicate found
-- ============================================================================

USE veeru_db;

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS before_insert_class;
DROP TRIGGER IF EXISTS before_insert_subject;
DROP TRIGGER IF EXISTS before_insert_chapter;

-- ============================================================================
-- TRIGGER 1: Classes Table
-- ============================================================================
DELIMITER $$

CREATE TRIGGER before_insert_class
BEFORE INSERT ON classes
FOR EACH ROW
BEGIN
    DECLARE duplicate_count INT;
    
    -- Normalize: Convert to UPPERCASE and trim whitespace
    SET NEW.class_name = UPPER(TRIM(NEW.class_name));
    
    -- Check for duplicates (case-insensitive)
    SELECT COUNT(*) INTO duplicate_count
    FROM classes
    WHERE UPPER(class_name) = NEW.class_name
      AND board_id = NEW.board_id;
    
    -- Raise error if duplicate found
    IF duplicate_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Duplicate class name for this board. Class already exists!';
    END IF;
END$$

DELIMITER ;

SELECT '✓ Created trigger: before_insert_class' AS 'Status';

-- ============================================================================
-- TRIGGER 2: Subjects Table
-- ============================================================================
DELIMITER $$

CREATE TRIGGER before_insert_subject
BEFORE INSERT ON subjects
FOR EACH ROW
BEGIN
    DECLARE duplicate_count INT;
    
    -- Normalize: Convert to UPPERCASE and trim whitespace
    SET NEW.subject_name = UPPER(TRIM(NEW.subject_name));
    
    -- Check for duplicates (case-insensitive)
    SELECT COUNT(*) INTO duplicate_count
    FROM subjects
    WHERE UPPER(subject_name) = NEW.subject_name
      AND class_id = NEW.class_id;
    
    -- Raise error if duplicate found
    IF duplicate_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Duplicate subject name for this class. Subject already exists!';
    END IF;
END$$

DELIMITER ;

SELECT '✓ Created trigger: before_insert_subject' AS 'Status';

-- ============================================================================
-- TRIGGER 3: Chapters Table
-- ============================================================================
DELIMITER $$

CREATE TRIGGER before_insert_chapter
BEFORE INSERT ON chapters
FOR EACH ROW
BEGIN
    DECLARE duplicate_count INT;
    
    -- Normalize: Convert to UPPERCASE and trim whitespace
    SET NEW.chapter_name = UPPER(TRIM(NEW.chapter_name));
    
    -- Check for duplicates (case-insensitive)
    SELECT COUNT(*) INTO duplicate_count
    FROM chapters
    WHERE UPPER(chapter_name) = NEW.chapter_name
      AND subject_id = NEW.subject_id;
    
    -- Raise error if duplicate found
    IF duplicate_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Duplicate chapter name for this subject. Chapter already exists!';
    END IF;
END$$

DELIMITER ;

SELECT '✓ Created trigger: before_insert_chapter' AS 'Status';

-- ============================================================================
-- Verify triggers were created
-- ============================================================================
SELECT '=== VERIFYING TRIGGERS ===' AS '';

SELECT 
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    EVENT_OBJECT_TABLE,
    ACTION_TIMING
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = 'veeru_db'
  AND TRIGGER_NAME IN ('before_insert_class', 'before_insert_subject', 'before_insert_chapter');

-- ============================================================================
-- Test Examples (uncomment to try)
-- ============================================================================
SELECT '=== TRIGGERS CREATED SUCCESSFULLY ===' AS '';
SELECT 'All text will now be automatically converted to UPPERCASE!' AS 'Auto-Normalization';
SELECT 'Duplicate entries will be automatically blocked!' AS 'Duplicate Prevention';

-- Test 1: Auto-capitalization
-- INSERT INTO classes (class_name, board_id) VALUES ('class 4', 1);
-- Result: Will be stored as 'CLASS 4'

-- Test 2: Duplicate prevention
-- INSERT INTO classes (class_name, board_id) VALUES ('CLASS 3', 1);
-- Result: ERROR - Duplicate class name for this board. Class already exists!

-- ============================================================================
-- NOTES
-- ============================================================================
-- 1. Triggers run BEFORE the INSERT, so they can modify the data
-- 2. UPPER() function converts text to uppercase
-- 3. TRIM() removes leading/trailing whitespace
-- 4. SIGNAL SQLSTATE '45000' raises a custom error
-- 5. To drop a trigger: DROP TRIGGER IF EXISTS trigger_name;
-- ============================================================================
-- PART 2: Smart Sync Tracking Triggers
-- ============================================================================
-- These triggers automatically update the 'app_content_updates' table 
-- whenever content is changed (INSERT, UPDATE, or DELETE).

CREATE TABLE IF NOT EXISTS app_content_updates (
    board_type VARCHAR(50) PRIMARY KEY,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed defaults
INSERT IGNORE INTO app_content_updates (board_type) VALUES ('CBSE'), ('Marathi Medium'), ('Semi English'), ('ICSE');

-- ----------------------------------------------------------------------------
-- MCQs Sync Logic: Update ONLY the specific board that changed
-- ----------------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER after_mcq_ins AFTER INSERT ON mcqs FOR EACH ROW 
BEGIN 
    UPDATE app_content_updates SET last_update = CURRENT_TIMESTAMP 
    WHERE board_type = (SELECT c.board_type FROM chapters ch JOIN subjects s ON ch.subject_id = s.subject_id JOIN classes c ON s.class_id = c.class_id WHERE ch.chapter_id = NEW.chapter_id);
END$$

CREATE TRIGGER after_mcq_upd AFTER UPDATE ON mcqs FOR EACH ROW 
BEGIN 
    UPDATE app_content_updates SET last_update = CURRENT_TIMESTAMP 
    WHERE board_type = (SELECT c.board_type FROM chapters ch JOIN subjects s ON ch.subject_id = s.subject_id JOIN classes c ON s.class_id = c.class_id WHERE ch.chapter_id = NEW.chapter_id);
END$$

CREATE TRIGGER after_mcq_del AFTER DELETE ON mcqs FOR EACH ROW 
BEGIN 
    UPDATE app_content_updates SET last_update = CURRENT_TIMESTAMP 
    WHERE board_type = (SELECT c.board_type FROM chapters ch JOIN subjects s ON ch.subject_id = s.subject_id JOIN classes c ON s.class_id = c.class_id WHERE ch.chapter_id = OLD.chapter_id);
END$$

-- ----------------------------------------------------------------------------
-- Notes Sync Logic: Update ONLY the specific board that changed
-- ----------------------------------------------------------------------------
CREATE TRIGGER after_note_ins AFTER INSERT ON notes FOR EACH ROW 
BEGIN 
    UPDATE app_content_updates SET last_update = CURRENT_TIMESTAMP 
    WHERE board_type = (SELECT c.board_type FROM chapters ch JOIN subjects s ON ch.subject_id = s.subject_id JOIN classes c ON s.class_id = c.class_id WHERE ch.chapter_id = NEW.chapter_id);
END$$

CREATE TRIGGER after_note_upd AFTER UPDATE ON notes FOR EACH ROW 
BEGIN 
    UPDATE app_content_updates SET last_update = CURRENT_TIMESTAMP 
    WHERE board_type = (SELECT c.board_type FROM chapters ch JOIN subjects s ON ch.subject_id = s.subject_id JOIN classes c ON s.class_id = c.class_id WHERE ch.chapter_id = NEW.chapter_id);
END$$

CREATE TRIGGER after_note_del AFTER DELETE ON notes FOR EACH ROW 
BEGIN 
    UPDATE app_content_updates SET last_update = CURRENT_TIMESTAMP 
    WHERE board_type = (SELECT c.board_type FROM chapters ch JOIN subjects s ON ch.subject_id = s.subject_id JOIN classes c ON s.class_id = c.class_id WHERE ch.chapter_id = OLD.chapter_id);
END$$
DELIMITER ;
