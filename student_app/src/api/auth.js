import axios from 'axios';
import { API_URL } from './config';

export const loginUser = async (email, password) => {
    // Authenticate with real backend

    try {
        const response = await axios.post(`${API_URL}/login.php`, {
            email,
            password
        });
        return response.data;
    } catch (error) {
        if (error.response) {
            // Server responded with a status code outside 2xx range
            const data = error.response.data;

            // If it's our expected JSON error format
            if (data && data.message) {
                throw data;
            }

            // If it's something else (HTML, string, or unexpected JSON)
            const msg = typeof data === 'string'
                ? data.substring(0, 100)
                : (JSON.stringify(data) || 'Unknown Server Error');

            throw new Error(`Server Error (${error.response.status}): ${msg}`);
        }
        throw new Error(error.message || 'Network Error');
    }
};

export const registerUser = async (name, email, mobile, password, schoolName, classId, board) => {
    try {
        const response = await axios.post(`${API_URL}/register.php`, {
            name,
            email,
            mobile,
            password,
            school_name: schoolName,
            class_id: classId,
            board_type: board
        });
        return response.data;
    } catch (error) {
        if (error.response) {
            const data = error.response.data;
            if (data && data.message) throw data;

            const msg = typeof data === 'string'
                ? data.substring(0, 100)
                : (JSON.stringify(data) || 'Unknown Server Error');

            throw new Error(`Server Error (${error.response.status}): ${msg}`);
        }
        throw new Error(error.message || 'Network Error');
    }
};
