INSERT INTO subjects (class_id, subject_name, description)
SELECT 37, 'Mock Tests', 'Full length practice exams and previous year papers'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE class_id = 37 AND subject_name = 'Mock Tests');

-- Get the subject ID
SET @mock_subject_id = (SELECT subject_id FROM subjects WHERE class_id = 37 AND subject_name = 'Mock Tests' LIMIT 1);

-- Insert sample "Papers" (Chapters)
INSERT INTO chapters (subject_id, chapter_name, chapter_order)
SELECT @mock_subject_id, 'Model Paper 2025 (Set 1)', 1
WHERE NOT EXISTS (SELECT 1 FROM chapters WHERE subject_id = @mock_subject_id AND chapter_name = 'Model Paper 2025 (Set 1)');

INSERT INTO chapters (subject_id, chapter_name, chapter_order)
SELECT @mock_subject_id, 'Previous Year Paper 2024', 2
WHERE NOT EXISTS (SELECT 1 FROM chapters WHERE subject_id = @mock_subject_id AND chapter_name = 'Previous Year Paper 2024');
