import axios from 'axios';
import { API_URL } from './config';
import { dataCache } from '../utils/dataCache';

export const fetchSubjects = async (classId, forceRefresh = false) => {
    const cacheKey = `subjects_${classId}`;

    // Try cache first (unless force refresh)
    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'subjects');
        if (cached) {
            console.log(`[API] Using cached subjects for class ${classId}`);
            return cached;
        }
    }

    try {
        console.log(`[API] Fetching subjects from server for class ${classId}...`);
        const response = await axios.get(`${API_URL}/get_subjects.php?class_id=${classId}`);

        // Cache successful responses
        if (response.data && response.data.status === 'success') {
            await dataCache.set(cacheKey, response.data, 'subjects');
        }

        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};
