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

export const fetchNotifications = async (classIdOrIds) => {
    try {
        const queryParam = typeof classIdOrIds === 'string' && classIdOrIds.includes(',') 
            ? `class_ids=${classIdOrIds}` 
            : `class_id=${classIdOrIds}`;
            
        const response = await axios.get(`${API_URL}/get_notifications.php?${queryParam}`);
        
        // OPTIMIZATION: Parse the JSON payloads here once, so the UI thread doesn't 
        // have to repeatedly parse them while scrolling through the list.
        if (response.data && response.data.status === 'success' && Array.isArray(response.data.data)) {
            response.data.data = response.data.data.map(item => {
                if (item.payload && typeof item.payload === 'string') {
                    try {
                        item.payload = JSON.parse(item.payload);
                    } catch (e) {
                        // Keep as string if parsing fails
                    }
                }
                return item;
            });
        }
        
        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};
