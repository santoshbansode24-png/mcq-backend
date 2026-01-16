import axios from 'axios';
import { API_URL } from './config';

export const fetchFlashcards = async (chapterId) => {
    // Basic validation to prevent unnecessary API calls
    if (!chapterId) throw new Error("chapterId is required");

    try {
        const { data } = await axios.get(`${API_URL}/get_flashcards.php`, {
            params: { chapter_id: chapterId },
            timeout: 5000 // Best practice: add a timeout
        });

        return data;
    } catch (error) {
        // Log detailed info for debugging, but return a clean object for the UI
        const errorMessage = error.response?.data?.message || error.message;
        console.error(`[API Error] fetchFlashcards:`, errorMessage);

        return {
            status: 'error',
            message: "Could not load flashcards. Please try again later."
        };
    }
};

export const markSetCompleted = async (userId, chapterId, setIndex) => {
    try {
        const response = await axios.post(`${API_URL}/mark_set_completed.php`, {
            user_id: userId,
            chapter_id: chapterId,
            set_index: setIndex,
            type: 'flashcard'
        });
        return response.data;
    } catch (error) {
        console.error('Mark Set Completed Error:', error);
        return { status: 'error', message: 'Network error' };
    }
};