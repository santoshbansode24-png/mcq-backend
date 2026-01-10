-- ============================================
-- Update Existing 20 Words to Set 1
-- ============================================

-- Set the first 20 words as Set 1 (Beginner)
UPDATE vocab_words 
SET set_number = 1,
    level_name = 'Beginner'
WHERE word_id <= 20;

-- Verify the update
SELECT 
    word_id,
    word,
    set_number,
    level_name,
    difficulty_level
FROM vocab_words
WHERE word_id <= 20
ORDER BY word_id;

-- Initialize user_vocab_stats for existing users
UPDATE user_vocab_stats
SET current_set = 1,
    sets_completed = 0,
    highest_set_unlocked = 1
WHERE current_set IS NULL OR current_set = 0;

SELECT 'Existing 20 words updated to Set 1!' as Status;
