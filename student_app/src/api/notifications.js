import axios from 'axios';
import { API_URL } from './config';

export const registerPushToken = async (userId, token) => {
    try {
        const response = await axios.post(`${API_URL}/register_push_token.php`, {
            user_id: userId,
            push_token: token
        });
        return response.data;
    } catch (error) {
        console.error('Error registering push token:', error);
        return null;
    }
};

export const fetchNotifications = async (classId) => {
    try {
        const response = await axios.get(`${API_URL}/get_notifications.php?class_id=${classId}`);
        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};
