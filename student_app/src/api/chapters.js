import axios from 'axios';
import { API_URL } from './config';
import { dataCache } from '../utils/dataCache';

export const fetchChapters = async (subjectId, forceRefresh = false) => {
    const cacheKey = `chapters_${subjectId}`;

    // Try cache first
    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'chapters');
        if (cached) {
            console.log(`[API] Using cached chapters for subject ${subjectId}`);
            return cached;
        }
    }

    try {
        console.log(`[API] Fetching chapters from server for subject ${subjectId}...`);
        const response = await axios.get(`${API_URL}/get_chapters.php?subject_id=${subjectId}`);

        // Cache successful responses
        if (response.data && response.data.status === 'success') {
            await dataCache.set(cacheKey, response.data, 'chapters');
        }

        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};
