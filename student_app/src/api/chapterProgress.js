import axios from 'axios';
import { API_URL } from './config';
import { cacheManager } from '../utils/cache';

/**
 * Fetch chapter-wise progress for a subject
 * @param {number} userId - Student user ID
 * @param {number} subjectId - Subject ID
 * @param {boolean} forceRefresh - Skip cache
 * @returns {Promise} - Progress data with chapters array and summary
 */
export const fetchChapterProgress = async (userId, subjectId, forceRefresh = false) => {
    const cacheKey = `chapter_progress_${userId}_${subjectId}`;

    // Try cache first (30 second TTL for real-time updates)
    if (!forceRefresh) {
        const cached = await cacheManager.get(cacheKey, 'progress', 30);
        if (cached) {
            console.log(`[API] Using cached chapter progress for subject ${subjectId}`);
            return cached;
        }
    }

    try {
        console.log(`[API] Fetching chapter progress from server for subject ${subjectId}...`);
        const response = await axios.get(
            `${API_URL}/get_chapter_progress.php?user_id=${userId}&subject_id=${subjectId}`
        );

        // Cache successful responses
        if (response.data && response.data.status === 'success') {
            await cacheManager.set(cacheKey, response.data, 'progress');
        }

        return response.data;
    } catch (error) {
        console.error('[API] Error fetching chapter progress:', error);
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

/**
 * Record MCQ attempt
 * @param {number} userId - Student user ID
 * @param {number} mcqId - MCQ ID
 * @param {number} chapterId - Chapter ID
 * @param {string} selectedAnswer - User's answer (a/b/c/d)
 * @param {string} correctAnswer - Correct answer (a/b/c/d)
 * @returns {Promise} - Response data
 */
export const recordMCQAttempt = async (userId, mcqId, chapterId, selectedAnswer, correctAnswer) => {
    try {
        const isCorrect = selectedAnswer === correctAnswer;

        const response = await axios.post(`${API_URL}/record_mcq_attempt.php`, {
            user_id: userId,
            mcq_id: mcqId,
            chapter_id: chapterId,
            selected_answer: selectedAnswer,
            correct_answer: correctAnswer,
            is_correct: isCorrect
        });

        // Clear progress cache to force refresh
        const cacheKey = `chapter_progress_${userId}_*`;
        await cacheManager.clear(cacheKey);

        return response.data;
    } catch (error) {
        console.error('[API] Error recording MCQ attempt:', error);
        throw error.response ? error.response.data : new Error('Network Error');
    }
};
