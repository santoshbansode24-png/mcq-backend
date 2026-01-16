import axios from 'axios';
import { API_URL } from './config';
import { dataCache } from '../utils/dataCache';

// --- internal helpers ---

/**
 * standardizes api error handling and logging
 */
const executeRequest = async (method, endpoint, data = {}, label = 'API') => {
    try {
        console.log(`[API] ${label}...`);

        const config = {
            method,
            url: `${API_URL}${endpoint}`,
        };

        if (method === 'get') {
            config.params = data;
        } else {
            config.data = data;
        }

        const response = await axios(config);
        return response.data;
    } catch (error) {
        console.error(`[API Error] ${label}:`, error.message);
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

/**
 * centralized cache clearing for user-specific data
 */
const clearUserCache = async (userId) => {
    await Promise.all([
        dataCache.remove(`vocab_review_${userId}`),
        dataCache.remove(`vocab_stats_${userId}`)
    ]);
    console.log(`[API] Cache cleared for user ${userId}`);
};

// --- exported functions ---

/**
 * Get daily review list with Read-Through Caching
 * 
 */
export const fetchReviewList = async (userId, limit = 20, forceRefresh = false) => {
    const cacheKey = `vocab_review_${userId}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'analytics');
        if (cached) {
            console.log(`[API] Using cached review list for user ${userId}`);
            return cached;
        }
    }

    const data = await executeRequest('get', '/vocab_get_review_list.php', { user_id: userId, limit }, `Fetching review list (${userId})`);

    if (data && data.status === 'success') {
        await dataCache.set(cacheKey, data, 'analytics');
    }

    return data;
};

/**
 * Submit SRS rating for a word
 */
export const submitRating = async (userId, wordId, rating, timeTaken = 0) => {
    const data = await executeRequest('post', '/vocab_submit_rating.php', {
        user_id: userId,
        word_id: wordId,
        rating,
        time_taken_seconds: timeTaken
    }, `Submitting rating ${rating} for word ${wordId}`);

    if (data && data.status === 'success') {
        await clearUserCache(userId);
    }

    return data;
};

/**
 * Add a new word to learning list
 */
export const addNewWord = async (userId, wordId) => {
    const data = await executeRequest('post', '/vocab_add_new_word.php', {
        user_id: userId,
        word_id: wordId
    }, `Adding word ${wordId}`);

    if (data && data.status === 'success') {
        await clearUserCache(userId);
    }

    return data;
};

/**
 * Get user vocabulary statistics
 */
export const fetchVocabStats = async (userId, forceRefresh = false) => {
    const cacheKey = `vocab_stats_${userId}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'analytics');
        if (cached) {
            console.log(`[API] Using cached stats for user ${userId}`);
            return cached;
        }
    }

    const data = await executeRequest('get', '/vocab_get_stats.php', { user_id: userId }, `Fetching stats (${userId})`);

    if (data && data.status === 'success') {
        await dataCache.set(cacheKey, data, 'analytics');
    }

    return data;
};

/**
 * Get premium/browse content
 */
export const fetchPremiumContent = async (userId, categoryId = 0, limit = 50) => {
    const params = { user_id: userId, limit };
    if (categoryId > 0) params.category_id = categoryId;

    return executeRequest('get', '/vocab_get_premium_content.php', params, 'Fetching premium content');
};

/**
 * Get word details
 */
export const fetchWordDetails = async (wordId, userId = 0) => {
    const params = { word_id: wordId };
    if (userId > 0) params.user_id = userId;

    return executeRequest('get', '/vocab_get_word_details.php', params, `Fetching details for word ${wordId}`);
};

/**
 * Get words for a specific set
 */
export const fetchVocabSet = async (userId, setNumber = 0) => {
    return executeRequest('get', '/vocab_get_set.php', {
        user_id: userId,
        set_number: setNumber
    }, `Fetching set ${setNumber}`);
};

/**
 * Complete a vocab set and unlock next
 */
export const completeVocabSet = async (userId, setNumber, score, totalQuestions = 10) => {
    const data = await executeRequest('post', '/vocab_complete_set.php', {
        user_id: userId,
        set_number: setNumber,
        score,
        total_questions: totalQuestions
    }, `Completing set ${setNumber}`);

    if (data && data.status === 'success') {
        await clearUserCache(userId);
    }

    return data;
};