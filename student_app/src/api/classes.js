import axios from 'axios';
import { API_URL } from './config';

import AsyncStorage from '@react-native-async-storage/async-storage';

export const fetchClasses = async (board = null, forceRefresh = false) => {
    const cacheKey = board ? `classes_${board}` : 'classes_all';
    const CACHE_TTL_MS = 60 * 60 * 1000; // 1 hour

    try {
        // 1. Try to load from cache first if not forcing refresh
        if (!forceRefresh) {
            const cachedRaw = await AsyncStorage.getItem(cacheKey);
            if (cachedRaw) {
                const cached = JSON.parse(cachedRaw);
                const now = Date.now();
                // Only use cache if it has a timestamp AND it's still fresh
                if (cached._cachedAt && (now - cached._cachedAt) < CACHE_TTL_MS) {
                    console.log(`[Classes] Loading ${cacheKey} from cache (age: ${Math.round((now - cached._cachedAt) / 1000)}s)`);
                    return cached.data;
                } else {
                    console.log(`[Classes] Cache expired for ${cacheKey}, refetching...`);
                    await AsyncStorage.removeItem(cacheKey);
                }
            }
        }

        // 2. Fetch from network
        console.log(`[Classes] Fetching from ${API_URL}/get_classes.php${board ? `?board=${board}` : ''}`);
        const response = await axios.get(`${API_URL}/get_classes.php`, {
            params: board ? { board } : {},
            timeout: 15000 // Increased from 5s to 15s to allow for Railway cold-starts and slow mobile data
        });

        if (response.data && response.data.status === 'success') {
            // 3. Save to cache with timestamp
            await AsyncStorage.setItem(cacheKey, JSON.stringify({
                _cachedAt: Date.now(),
                data: response.data
            }));
            return response.data;
        }

        return response.data;
    } catch (error) {
        console.error('[Classes] Error:', error.message);

        // Final fallback: try cache even if forceRefresh was true
        const fallbackCached = await AsyncStorage.getItem(cacheKey);
        if (fallbackCached) {
            const cached = JSON.parse(fallbackCached);
            return cached.data || cached; // support both old and new format
        }

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
