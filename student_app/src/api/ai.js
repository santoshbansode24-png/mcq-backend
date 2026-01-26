import axios from 'axios';
import { API_URL } from './config';

// Create a dedicated Axios instance for AI
const aiClient = axios.create({
    baseURL: API_URL,
    timeout: 30000, // 30 seconds timeout for text
});

/**
 * Helper to handle errors uniformly
 */
const handleError = (error, context) => {
    console.error(`${context} Error:`, error);
    if (error.response && error.response.data) {
        // Return the actual error message from the server (e.g., "API Key Invalid")
        return {
            status: 'error',
            message: error.response.data.message || 'Server error occurred.'
        };
    }
    if (error.message.includes('timeout')) {
        return { status: 'error', message: 'Request timed out. AI is taking too long.' };
    }
    return { status: 'error', message: 'Network connection failed.' };
};

/**
 * Send Message to AI Tutor
 */
import AsyncStorage from '@react-native-async-storage/async-storage';

// ... (existing code)

/**
 * Get current user ID from storage
 */
const getUserId = async () => {
    try {
        const userData = await AsyncStorage.getItem('user_data');
        if (userData) {
            const user = JSON.parse(userData);
            return user.user_id;
        }
    } catch (e) {
        console.warn("Failed to get user ID for AI tracking", e);
    }
    return null;
};

/**
 * Send Message to AI Tutor
 */
export const sendMessageToAI = async (message) => {
    try {
        const userId = await getUserId();

        // Using production endpoint
        const response = await aiClient.post('/ai_chat.php', {
            message: message,
            user_id: userId // Add user_id for tracking
        }, {
            headers: { 'Content-Type': 'application/json' }
        });
        return response.data;
    } catch (error) {
        return handleError(error, 'AI Chat');
    }
};

/**
 * Upload Homework Image to AI
 */
export const uploadHomeworkImage = async (imageUri, prompt, language = 'English') => {
    try {
        const userId = await getUserId();

        // 1. Detect File Type dynamically (JPG vs PNG)
        const fileExtension = imageUri.split('.').pop().toLowerCase();
        const mimeType = fileExtension === 'png' ? 'image/png' : 'image/jpeg';
        const fileName = `homework_${Date.now()}.${fileExtension}`;

        // 2. Prepare Form Data
        const formData = new FormData();
        formData.append('image', {
            uri: imageUri,
            type: mimeType,
            name: fileName,
        });
        formData.append('language', language);
        formData.append('prompt', prompt || "Solve this problem step-by-step.");
        if (userId) formData.append('user_id', userId); // Add user_id

        // 3. Send Request (Longer timeout for images)
        const response = await aiClient.post('/ai_homework.php', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            timeout: 60000, // 60 seconds timeout for image processing
        });
        return response.data;
    } catch (error) {
        return handleError(error, 'Homework Upload');
    }
};