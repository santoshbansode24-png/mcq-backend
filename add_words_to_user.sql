-- ============================================
-- Find your user_id and add words to review list
-- ============================================

-- Step 1: Find your user_id
SELECT user_id, name, email FROM users LIMIT 10;

-- Step 2: After you know your user_id, replace USER_ID_HERE with your actual user_id
-- Then run this to add 5 words to your review list:

-- Replace USER_ID_HERE with your actual user_id (e.g., 1, 2, 4, etc.)
INSERT INTO user_vocab_progress (user_id, word_id, next_review_date) VALUES
(USER_ID_HERE, 1, CURDATE()),  -- Eloquent
(USER_ID_HERE, 5, CURDATE()),  -- Serendipity
(USER_ID_HERE, 11, CURDATE()), -- Ephemeral
(USER_ID_HERE, 6, CURDATE()),  -- Ubiquitous
(USER_ID_HERE, 7, CURDATE());  -- Pragmatic

-- Example: If your user_id is 1, change USER_ID_HERE to 1:
-- INSERT INTO user_vocab_progress (user_id, word_id, next_review_date) VALUES
-- (1, 1, CURDATE()),
-- (1, 5, CURDATE()),
-- etc.
