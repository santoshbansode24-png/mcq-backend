import axios from 'axios';
import { API_URL } from './config';

/**
 * Sends Google User Data to PHP Backend
 * @param {Object} userData - { email, name, id, photo }
 */
export const googleLogin = async (userData) => {
    try {
        const response = await axios.post(`${API_URL}/google_login.php`, userData);
        return response.data;
    } catch (error) {
        console.error('Google Login API Error:', error);
        return { status: 'error', message: 'Connection to server failed' };
    }
};
