import axios from 'axios';
import { API_URL } from './config';

import AsyncStorage from '@react-native-async-storage/async-storage';

export const fetchClasses = async (board = null, forceRefresh = false) => {
    const cacheKey = board ? `classes_${board}` : 'classes_all';

    try {
        // 1. Try to load from cache first if not forcing refresh
        if (!forceRefresh) {
            const cachedData = await AsyncStorage.getItem(cacheKey);
            if (cachedData) {
                console.log(`[Classes] Loading ${cacheKey} from cache`);
                // Background refresh: don't return here if we want to ensure fresh data eventually,
                // but for speed, we return cached data and let the caller handle it.
                // For now, let's return cached data immediately.
                return JSON.parse(cachedData);
            }
        }

        // 2. Fetch from network
        console.log(`[Classes] Fetching from ${API_URL}/get_classes.php${board ? `?board=${board}` : ''}`);
        const response = await axios.get(`${API_URL}/get_classes.php`, {
            params: board ? { board } : {},
            timeout: 5000
        });

        if (response.data && response.data.status === 'success') {
            // 3. Save to cache
            await AsyncStorage.setItem(cacheKey, JSON.stringify(response.data));
            return response.data;
        }

        return response.data;
    } catch (error) {
        console.error('[Classes] Error:', error.message);

        // Final fallback: try cache even if forceRefresh was true
        const fallbackCached = await AsyncStorage.getItem(cacheKey);
        if (fallbackCached) return JSON.parse(fallbackCached);

        throw error.response ? error.response.data : new Error(error.message || 'Network Error');
    }
};

export const updateStudentClass = async (userId, classId, boardId) => {
    try {
        const response = await axios.post(`${API_URL}/update_student_class.php`, {
            user_id: userId,
            class_id: classId,
            board_type: boardId
        });
        return response.data;
    } catch (error) {
        console.error('[Classes] Update Error:', error.message);
        throw error.response ? error.response.data : new Error('Network Error');
    }
};
