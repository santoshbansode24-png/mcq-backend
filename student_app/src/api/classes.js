import axios from 'axios';
import { API_URL } from './config';

export const fetchClasses = async () => {
    try {
        console.log(`[Classes] Fetching from ${API_URL}/get_classes.php`);
        const response = await axios.get(`${API_URL}/get_classes.php`, { timeout: 5000 });
        console.log(`[Classes] Response:`, response.status);
        return response.data;
    } catch (error) {
        console.error('[Classes] Error:', error.message);
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
