import axios from 'axios';
import { API_URL } from './config';
import { cacheManager } from '../utils/cache';

export const fetchAnalytics = async (userId, forceRefresh = false) => {
    const cacheKey = `analytics_${userId}`;

    // Try cache first
    if (!forceRefresh) {
        const cached = await cacheManager.get(cacheKey, 'analytics');
        if (cached) {
            console.log(`[API] Using cached analytics for user ${userId}`);
            return cached;
        }
    }

    try {
        console.log(`[API] Fetching analytics from server for user ${userId}...`);
        const response = await axios.get(`${API_URL}/get_student_analytics.php?user_id=${userId}`);

        // Cache successful responses
        if (response.data && response.data.status === 'success') {
            await cacheManager.set(cacheKey, response.data, 'analytics');
        }

        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

export const fetchLeaderboard = async (classId, forceRefresh = false) => {
    const cacheKey = `leaderboard_${classId}`;

    // Try cache first
    if (!forceRefresh) {
        const cached = await cacheManager.get(cacheKey, 'leaderboard');
        if (cached) {
            console.log(`[API] Using cached leaderboard for class ${classId}`);
            return cached;
        }
    }

    try {
        console.log(`[API] Fetching leaderboard from server for class ${classId}...`);
        const response = await axios.get(`${API_URL}/get_mcq_leaderboard.php?class_id=${classId}`);

        // Cache successful responses
        if (response.data && response.data.status === 'success') {
            await cacheManager.set(cacheKey, response.data, 'leaderboard');
        }

        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

export const saveBookmark = async (userId, itemId, type, title, meta = {}) => {
    try {
        const response = await axios.post(`${API_URL}/save_bookmark.php`, {
            user_id: userId,
            item_id: itemId,
            type,
            title,
            meta
        });

        // Clear bookmarks cache after saving
        if (response.data && response.data.status === 'success') {
            await cacheManager.clear(`bookmarks_${userId}`);
            console.log(`[API] Bookmark saved, cache cleared for user ${userId}`);
        }

        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

export const fetchBookmarks = async (userId, forceRefresh = false) => {
    const cacheKey = `bookmarks_${userId}`;

    // Try cache first
    if (!forceRefresh) {
        const cached = await cacheManager.get(cacheKey, 'analytics');
        if (cached) {
            console.log(`[API] Using cached bookmarks for user ${userId}`);
            return cached;
        }
    }

    try {
        console.log(`[API] Fetching bookmarks from server for user ${userId}...`);
        const response = await axios.get(`${API_URL}/get_bookmarks.php?user_id=${userId}`);

        // Cache successful responses
        if (response.data && response.data.status === 'success') {
            await cacheManager.set(cacheKey, response.data, 'analytics');
        }

        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};
