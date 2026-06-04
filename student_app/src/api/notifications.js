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
                item.parsedPayload = null;
                item.cleanMessage = item.message;

                if (item.payload) {
                    if (typeof item.payload === 'string') {
                        try {
                            item.parsedPayload = JSON.parse(item.payload);
                        } catch (e) {}
                    } else {
                        item.parsedPayload = item.payload; 
                    }
                }

                if (!item.parsedPayload && item.cleanMessage && item.cleanMessage.includes('JSON_PAYLOAD:')) {
                    try {
                        const jsonStr = item.cleanMessage.substring(item.cleanMessage.indexOf('{')).trim();
                        if (jsonStr.startsWith('{')) {
                            item.parsedPayload = JSON.parse(jsonStr);
                            item.cleanMessage = "New Worksheet Available";
                        }
                    } catch (e) {}
                }

                if (item.parsedPayload && item.parsedPayload.type === 'worksheet_data' && item.parsedPayload.textMessage) {
                    item.cleanMessage = item.parsedPayload.textMessage;
                }

                return item;
            });
        }
        
        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};
