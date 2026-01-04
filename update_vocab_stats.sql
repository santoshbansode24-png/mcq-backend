ALTER TABLE user_vocab_stats
ADD COLUMN current_set INT DEFAULT 1,
ADD COLUMN sets_completed INT DEFAULT 0,
ADD COLUMN highest_set_unlocked INT DEFAULT 1,
ADD COLUMN experience_points INT DEFAULT 0,
CHANGE current_streak streak_days INT DEFAULT 0;
