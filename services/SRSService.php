<?php
/**
 * Spaced Repetition System (SRS) Service
 * Implements SuperMemo-2 Algorithm for Vocabulary Learning
 * 
 * Algorithm Reference: https://www.supermemo.com/en/archives1990-2015/english/ol/sm2
 * 
 * @author MCQ Platform Team
 * @version 1.0
 */

class SRSService {
    
    private $pdo;
    
    // SRS Constants
    const MIN_EASINESS_FACTOR = 1.3;
    const DEFAULT_EASINESS_FACTOR = 2.5;
    const MAX_EASINESS_FACTOR = 2.5;
    const MASTERY_THRESHOLD_DAYS = 60;
    const MASTERY_MIN_REVIEWS = 5;
    
    // Rating thresholds
    const RATING_AGAIN = 1;      // Complete blackout
    const RATING_HARD = 2;       // Incorrect response, correct one remembered
    const RATING_GOOD = 3;       // Correct response with serious difficulty
    const RATING_EASY = 4;       // Correct response after hesitation
    const RATING_PERFECT = 5;    // Perfect response
    
    public function __construct($db) {
        $this->pdo = $db;
    }
    
    /**
     * Update user progress using SuperMemo-2 algorithm
     * 
     * @param int $userId User ID
     * @param int $wordId Word ID
     * @param int $rating User's self-rating (1-5)
     * @param int $timeTakenSeconds Time taken to answer
     * @return array Updated progress data
     */
    public function updateProgress($userId, $wordId, $rating, $timeTakenSeconds = 0) {
        try {
            // Validate rating
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Rating must be between 1 and 5");
            }
            
            // Get current progress or create new
            $progress = $this->getOrCreateProgress($userId, $wordId);
            
            // Store old values for history
            $oldEasiness = $progress['easiness_factor'];
            $oldInterval = $progress['interval_days'];
            
            // Calculate new values using SuperMemo-2
            $srsResult = $this->calculateSuperMemo2(
                $progress['easiness_factor'],
                $progress['interval_days'],
                $progress['repetitions'],
                $rating
            );
            
            $newEasiness = $srsResult['easiness'];
            $newInterval = $srsResult['interval'];
            $newRepetitions = $srsResult['repetitions'];
            
            // Calculate next review date
            $nextReviewDate = date('Y-m-d', strtotime("+{$newInterval} days"));
            
            // Update review counts
            $reviewCount = $progress['review_count'] + 1;
            $correctCount = $progress['correct_count'] + ($rating >= 3 ? 1 : 0);
            $averageRating = (($progress['average_rating'] * $progress['review_count']) + $rating) / $reviewCount;
            
            // Determine mastery status
            $masteryStatus = $this->determineMasteryStatus(
                $newInterval,
                $reviewCount,
                $rating,
                $progress['mastery_status']
            );
            
            // Update progress in database
            $sql = "UPDATE user_vocab_progress SET
                    easiness_factor = :easiness,
                    interval_days = :interval,
                    repetitions = :repetitions,
                    next_review_date = :next_review_date,
                    review_count = :review_count,
                    correct_count = :correct_count,
                    average_rating = :average_rating,
                    last_rating = :last_rating,
                    mastery_status = :mastery_status,
                    last_reviewed_at = NOW(),
                    mastered_at = CASE WHEN :mastery_status_check = 'Mastered' AND mastered_at IS NULL THEN NOW() ELSE mastered_at END
                    WHERE user_id = :user_id AND word_id = :word_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':easiness' => $newEasiness,
                ':interval' => $newInterval,
                ':repetitions' => $newRepetitions,
                ':next_review_date' => $nextReviewDate,
                ':review_count' => $reviewCount,
                ':correct_count' => $correctCount,
                ':average_rating' => $averageRating,
                ':last_rating' => $rating,
                ':mastery_status' => $masteryStatus,
                ':mastery_status_check' => $masteryStatus,
                ':user_id' => $userId,
                ':word_id' => $wordId
            ]);
            
            // Record in history
            $this->recordReviewHistory(
                $userId,
                $wordId,
                $rating,
                $timeTakenSeconds,
                $rating >= 3,
                $oldInterval,
                $newInterval,
                $oldEasiness,
                $newEasiness
            );
            
            // Update user stats
            $this->updateUserStats($userId);
            
            // Update word review count
            $this->updateWordReviewCount($wordId);
            
            return [
                'success' => true,
                'easiness_factor' => $newEasiness,
                'interval_days' => $newInterval,
                'next_review_date' => $nextReviewDate,
                'mastery_status' => $masteryStatus,
                'review_count' => $reviewCount,
                'accuracy' => round(($correctCount / $reviewCount) * 100, 2)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * SuperMemo-2 Algorithm Implementation
     * 
     * @param float $easiness Current easiness factor (EF)
     * @param int $interval Current interval in days
     * @param int $repetitions Number of successful repetitions
     * @param int $rating User rating (1-5)
     * @return array New easiness, interval, and repetitions
     */
    private function calculateSuperMemo2($easiness, $interval, $repetitions, $rating) {
        // Calculate new easiness factor
        // EF' = EF + (0.1 - (5 - q) * (0.08 + (5 - q) * 0.02))
        $newEasiness = $easiness + (0.1 - (5 - $rating) * (0.08 + (5 - $rating) * 0.02));
        
        // Ensure easiness stays within bounds
        if ($newEasiness < self::MIN_EASINESS_FACTOR) {
            $newEasiness = self::MIN_EASINESS_FACTOR;
        }
        
        // Calculate new interval and repetitions
        if ($rating < 3) {
            // Failed - reset
            $newInterval = 1;
            $newRepetitions = 0;
        } else {
            // Successful
            $newRepetitions = $repetitions + 1;
            
            if ($newRepetitions == 1) {
                $newInterval = 1;
            } elseif ($newRepetitions == 2) {
                $newInterval = 6;
            } else {
                $newInterval = round($interval * $newEasiness);
            }
        }
        
        return [
            'easiness' => round($newEasiness, 2),
            'interval' => $newInterval,
            'repetitions' => $newRepetitions
        ];
    }
    
    /**
     * Determine mastery status based on progress
     * 
     * @param int $interval Current interval
     * @param int $reviewCount Total reviews
     * @param int $rating Last rating
     * @param string $currentStatus Current mastery status
     * @return string New mastery status
     */
    private function determineMasteryStatus($interval, $reviewCount, $rating, $currentStatus) {
        // Mastered: interval > 60 days AND at least 5 reviews
        if ($interval >= self::MASTERY_THRESHOLD_DAYS && $reviewCount >= self::MASTERY_MIN_REVIEWS) {
            return 'Mastered';
        }
        
        // Review: interval > 7 days
        if ($interval > 7) {
            return 'Review';
        }
        
        // Learning: has been reviewed at least once
        if ($reviewCount > 0) {
            return 'Learning';
        }
        
        // New: never reviewed
        return 'New';
    }
    
    /**
     * Get or create user progress for a word
     * 
     * @param int $userId User ID
     * @param int $wordId Word ID
     * @return array Progress data
     */
    private function getOrCreateProgress($userId, $wordId) {
        // Try to get existing progress
        $sql = "SELECT * FROM user_vocab_progress WHERE user_id = :user_id AND word_id = :word_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':word_id' => $wordId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            return $progress;
        }
        
        // Create new progress
        $sql = "INSERT INTO user_vocab_progress (user_id, word_id, next_review_date) 
                VALUES (:user_id, :word_id, CURDATE())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':word_id' => $wordId]);
        
        // Return newly created progress
        return $this->getOrCreateProgress($userId, $wordId);
    }
    
    /**
     * Record review in history table
     */
    private function recordReviewHistory($userId, $wordId, $rating, $timeTaken, $wasCorrect, 
                                        $oldInterval, $newInterval, $oldEasiness, $newEasiness) {
        $sql = "INSERT INTO vocab_review_history 
                (user_id, word_id, rating, time_taken_seconds, was_correct, 
                 previous_interval, new_interval, previous_easiness, new_easiness)
                VALUES (:user_id, :word_id, :rating, :time_taken, :was_correct,
                        :old_interval, :new_interval, :old_easiness, :new_easiness)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':word_id' => $wordId,
            ':rating' => $rating,
            ':time_taken' => $timeTaken,
            ':was_correct' => $wasCorrect ? 1 : 0,
            ':old_interval' => $oldInterval,
            ':new_interval' => $newInterval,
            ':old_easiness' => $oldEasiness,
            ':new_easiness' => $newEasiness
        ]);
    }
    
    /**
     * Update user vocabulary statistics
     */
    private function updateUserStats($userId) {
        // Create stats record if doesn't exist
        $sql = "INSERT IGNORE INTO user_vocab_stats (user_id) VALUES (:user_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        // Update total reviews
        $sql = "UPDATE user_vocab_stats SET 
                total_reviews = total_reviews + 1,
                last_practice_date = CURDATE()
                WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        // Update streak
        $this->updateStreak($userId);
    }
    
    /**
     * Update user's daily streak
     */
    private function updateStreak($userId) {
        $sql = "SELECT last_practice_date, current_streak, longest_streak 
                FROM user_vocab_stats WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) return;
        
        $today = date('Y-m-d');
        $lastPractice = $stats['last_practice_date'];
        $currentStreak = $stats['current_streak'];
        $longestStreak = $stats['longest_streak'];
        
        // Calculate new streak
        if ($lastPractice == $today) {
            // Already practiced today, no change
            return;
        } elseif ($lastPractice == date('Y-m-d', strtotime('-1 day'))) {
            // Practiced yesterday, increment streak
            $currentStreak++;
        } else {
            // Streak broken, reset to 1
            $currentStreak = 1;
        }
        
        // Update longest streak if needed
        if ($currentStreak > $longestStreak) {
            $longestStreak = $currentStreak;
        }
        
        // Update database
        $sql = "UPDATE user_vocab_stats SET 
                current_streak = :current_streak,
                longest_streak = :longest_streak
                WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':current_streak' => $currentStreak,
            ':longest_streak' => $longestStreak,
            ':user_id' => $userId
        ]);
    }
    
    /**
     * Update word's total review count
     */
    private function updateWordReviewCount($wordId) {
        $sql = "UPDATE vocab_words SET times_reviewed = times_reviewed + 1 WHERE word_id = :word_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':word_id' => $wordId]);
    }
    
    /**
     * Get words due for review today
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of words to return
     * @return array List of due words
     */
    public function getDueWords($userId, $limit = 20) {
        $sql = "SELECT 
                    uvp.word_id,
                    uvp.mastery_status,
                    uvp.review_count,
                    uvp.next_review_date,
                    vw.word,
                    vw.definition,
                    vw.example_sentence,
                    vw.pronunciation_text,
                    vw.difficulty_level,
                    vc.category_name,
                    vc.access_level
                FROM user_vocab_progress uvp
                JOIN vocab_words vw ON uvp.word_id = vw.word_id
                JOIN vocab_categories vc ON vw.category_id = vc.category_id
                WHERE uvp.user_id = :user_id
                AND uvp.next_review_date <= CURDATE()
                AND vw.is_active = TRUE
                ORDER BY uvp.next_review_date ASC, uvp.mastery_status ASC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Add a new word to user's learning list
     * 
     * @param int $userId User ID
     * @param int $wordId Word ID
     * @return array Result
     */
    public function addNewWord($userId, $wordId) {
        try {
            // Check if word already exists for user
            $sql = "SELECT progress_id FROM user_vocab_progress 
                    WHERE user_id = :user_id AND word_id = :word_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId, ':word_id' => $wordId]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Word already in your learning list'
                ];
            }
            
            // Add word
            $sql = "INSERT INTO user_vocab_progress (user_id, word_id, next_review_date)
                    VALUES (:user_id, :word_id, CURDATE())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId, ':word_id' => $wordId]);
            
            return [
                'success' => true,
                'message' => 'Word added to your learning list'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

