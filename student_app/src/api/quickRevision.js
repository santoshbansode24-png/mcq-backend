import axios from 'axios';
import { API_URL } from './config';

export const fetchQuickRevision = async (chapterId) => {
    try {
        const response = await axios.get(`${API_URL}/get_quick_revision.php`, {
            params: { chapter_id: chapterId }
        });
        return response.data;
    } catch (error) {
        console.error('Quick Revision API Error:', error);
        return { status: 'error', message: error.message };
    }
};
