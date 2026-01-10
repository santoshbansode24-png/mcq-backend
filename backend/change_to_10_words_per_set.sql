-- ============================================
-- Change to 10 Words Per Set (200 Total Sets)
-- ============================================

-- Update the current 20 words to be split into Set 1 and Set 2
-- Set 1: Words 1-10
UPDATE vocab_words 
SET set_number = 1
WHERE word_id BETWEEN 1 AND 10;

-- Set 2: Words 11-20
UPDATE vocab_words 
SET set_number = 2
WHERE word_id BETWEEN 11 AND 20;

-- Update user stats to reflect new system (200 total sets)
UPDATE user_vocab_stats
SET highest_set_unlocked = 1,
    current_set = 1,
    sets_completed = 0;

-- Verify the changes
SELECT word_id, word, set_number, level_name 
FROM vocab_words 
WHERE word_id <= 20 
ORDER BY word_id;

SELECT 'Changed to 10 words per set! Now 200 total sets for 2000 words.' as Status;
