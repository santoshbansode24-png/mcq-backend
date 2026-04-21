import axios from 'axios';
import { API_URL } from './config';
import { dataCache } from '../utils/dataCache';

const CACHE_KEY = (userId) => `dual_math_progress_${userId}`;
const CACHE_TTL = 5 * 60 * 1000; // 5 minutes

/**
 * Fetch mental math and abacus progress.
 * Uses cache by default. Pass forceRefresh=true to bypass.
 */
export const fetchMentalMathProgress = async (userId, forceRefresh = false) => {
    const key = CACHE_KEY(userId);

    if (!forceRefresh) {
        try {
            const cached = await dataCache.get(key, 'analytics');
            if (cached) return cached;
        } catch (_) { /* Cache miss is fine */ }
    }

    try {
        const response = await axios.post(
            `${API_URL}/get_math_progress.php`,
            { user_id: userId },
            { timeout: 8000 }
        );

        const data = response.data;

        if (data && data.status === 'success') {
            // Ensure levels are never below 1
            data.mental_math_level = Math.max(1, parseInt(data.mental_math_level) || 1);
            data.abacus_level      = Math.max(1, parseInt(data.abacus_level) || 1);
            await dataCache.set(key, data, 'analytics');
        }

        return data;

    } catch (error) {
        console.error('[API] fetchMentalMathProgress error:', error.message);
        // Return safe defaults so the app never crashes due to a network issue
        return { status: 'success', mental_math_level: 1, abacus_level: 1 };
    }
};

/**
 * Save progress for either Classic or Abacus mode.
 * Fire-and-forget safe (won't crash the app on failure).
 */
export const saveMathProgress = async (userId, type, newLevel) => {
    const cappedLevel = Math.min(Math.max(1, newLevel), 30);

    try {
        const response = await axios.post(
            `${API_URL}/update_math_progress.php`,
            { user_id: userId, type, new_level: cappedLevel },
            { timeout: 8000 }
        );

        if (response.data?.status === 'success') {
            // Invalidate cache so next fetch gets fresh data
            try { await dataCache.remove(CACHE_KEY(userId), 'analytics'); } catch (_) {}
        }

        return response.data;

    } catch (error) {
        // Log but never crash the app — the cached local level still works
        console.error('[API] saveMathProgress error:', error.message);
        return null;
    }
};
