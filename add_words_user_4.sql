-- ============================================
-- Add 5 Vocabulary Words to User ID 4's Review List
-- ============================================

-- Add 5 words for immediate review (due today)
INSERT INTO user_vocab_progress (user_id, word_id, next_review_date) VALUES
(4, 1, CURDATE()),  -- Eloquent (Easy)
(4, 5, CURDATE()),  -- Serendipity (Medium)
(4, 11, CURDATE()), -- Ephemeral (Hard)
(4, 6, CURDATE()),  -- Ubiquitous (Medium)
(4, 7, CURDATE());  -- Pragmatic (Medium)

-- Verify the words were added
SELECT 
    uvp.user_id,
    vw.word,
    vw.definition,
    vw.difficulty_level,
    uvp.next_review_date,
    uvp.mastery_status
FROM user_vocab_progress uvp
JOIN vocab_words vw ON uvp.word_id = vw.word_id
WHERE uvp.user_id = 4;

-- Success message
SELECT 'Successfully added 5 words to user 4 review list! Open Vocab Booster in your app now!' as Status;
