import axios from 'axios';
import { API_URL } from './config';
import { dataCache } from '../utils/dataCache';

/**
 * Fetch mental math and abacus progress with support for caching
 */
export const fetchMentalMathProgress = async (userId, forceRefresh = false) => {
    const cacheKey = `dual_math_progress_${userId}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'analytics');
        if (cached) {
            return cached;
        }
    }

    try {
        const response = await axios.post(`${API_URL}/get_math_progress.php`, {
            user_id: userId
        });

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
 * Save progress for either Classic or Abacus and invalidate cache
 */
export const saveMathProgress = async (userId, type, newLevel) => {
    try {
        const response = await axios.post(`${API_URL}/update_math_progress.php`, {
            user_id: userId,
            type: type, // 'classic' or 'abacus'
            new_level: newLevel
        });

        if (response.data && response.data.status === 'success') {
            // Update local cache manually instead of waiting for next fetch
            // We just clear it so next fetch gets fresh
            await dataCache.remove(`dual_math_progress_${userId}`, 'analytics');
        }

        return response.data;
    } catch (error) {
        console.error('[API] saveMathProgress error:', error);
        throw error;
    }
};

