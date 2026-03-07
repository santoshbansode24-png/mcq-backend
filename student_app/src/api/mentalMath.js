import axios from 'axios';
import { API_URL } from './config';
import { dataCache } from '../utils/dataCache';

/**
 * Fetch mental math progress with support for caching
 */
export const fetchMentalMathProgress = async (userId, forceRefresh = false) => {
    const cacheKey = `mental_math_progress_${userId}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'analytics');
        if (cached) {
            return cached;
        }
    }

    try {
        const response = await axios.get(`${API_URL}/mental_math_get_progress.php?user_id=${userId}`);

        if (response.data && response.data.status === 'success') {
            await dataCache.set(cacheKey, response.data, 'analytics');
        }

        return response.data;
    } catch (error) {
        console.error('[API] fetchMentalMathProgress error:', error);
        throw error;
    }
};

/**
 * Save mental math progress and invalidate cache
 */
export const saveMentalMathProgress = async (userId, level, score) => {
    try {
        const response = await axios.post(`${API_URL}/mental_math_save_progress.php`, {
            user_id: userId,
            level: level,
            score: score
        });

        if (response.data && response.data.status === 'success') {
            // Update local cache manually instead of waiting for next fetch
            await dataCache.set(`mental_math_progress_${userId}`, response.data, 'analytics');
        }

        return response.data;
    } catch (error) {
        console.error('[API] saveMentalMathProgress error:', error);
        throw error;
    }
};
