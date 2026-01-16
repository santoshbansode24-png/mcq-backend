import axios from 'axios';
import { API_URL } from './config';
import { dataCache } from '../utils/dataCache'; // Updated import

// New: Fetch Set Status
export const fetchSetStatus = async (userId, chapterId, type) => {
    try {
        const response = await axios.get(`${API_URL}/get_set_status.php`, {
            params: { user_id: userId, chapter_id: chapterId, type }
        });
        return response.data;
    } catch (error) {
        console.error('Fetch Set Status Error:', error);
        return { status: 'error', message: 'Network error' };
    }
};

export const recordMCQAttempt = async (userId, mcqId, chapterId, selectedAnswer, correctAnswer, isCorrect) => {
    try {
        const response = await axios.post(`${API_URL}/record_mcq_attempt.php`, {
            user_id: userId,
            mcq_id: mcqId,
            chapter_id: chapterId,
            selected_answer: selectedAnswer,
            correct_answer: correctAnswer,
            is_correct: isCorrect
        });
        return response.data;
    } catch (error) {
        console.error('Record MCQ Attempt Error:', error);
        return { status: 'error', message: 'Network error' };
    }
};

export const fetchMCQs = async (chapterId, forceRefresh = false) => {
    const cacheKey = `mcqs_${chapterId}`;

    // 1. Try cache first (if not forcing refresh)
    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'mcqs');
        if (cached) {
            console.log(`[API] Using cached MCQs for chapter ${chapterId}`);
            return cached;
        }
    }

    // 2. Network Request
    try {
        console.log(`[API] Fetching MCQs from server for chapter ${chapterId}...`);
        const response = await axios.get(`${API_URL}/get_mcqs.php?chapter_id=${chapterId}`);

        // 3. Save to Cache
        if (response.data && response.data.status === 'success') {
            await dataCache.set(cacheKey, response.data, 'mcqs');
        }

        return response.data;
    } catch (error) {
        // Fallback: If network fails and we have STALE cache, maybe return that?
        // For now, standard error handling
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

export const fetchNotes = async (chapterId, forceRefresh = false) => {
    const cacheKey = `notes_${chapterId}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'notes');
        if (cached) {
            console.log(`[API] Using cached notes for chapter ${chapterId}`);
            return cached;
        }
    }

    try {
        console.log(`[API] Fetching notes from server for chapter ${chapterId}...`);
        const response = await axios.get(`${API_URL}/get_notes.php?chapter_id=${chapterId}`);

        if (response.data && response.data.status === 'success') {
            await dataCache.set(cacheKey, response.data, 'notes');
        }

        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

export const fetchVideos = async (chapterId, forceRefresh = false) => {
    const cacheKey = `videos_${chapterId}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'videos');
        if (cached) {
            console.log(`[API] Using cached videos for chapter ${chapterId}`);
            return cached;
        }
    }

    try {
        console.log(`[API] Fetching videos from server for chapter ${chapterId}...`);
        const response = await axios.get(`${API_URL}/get_videos.php?chapter_id=${chapterId}`);

        if (response.data && response.data.status === 'success') {
            await dataCache.set(cacheKey, response.data, 'videos');
        }

        return response.data;
    } catch (error) {
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

export const fetchFlashcards = async (chapterId, forceRefresh = false) => {
    const cacheKey = `flashcards_${chapterId}`;
    console.log(`[Flashcards] Requesting for chapter ${chapterId}. ForceRefresh: ${forceRefresh}`);

    if (!forceRefresh) {
        console.log(`[Flashcards] Checking cache...`);
        const cached = await dataCache.get(cacheKey, 'flashcards');
        if (cached) {
            console.log(`[Flashcards] Cache HIT for ${chapterId}`);
            return cached;
        }
        console.log(`[Flashcards] Cache MISS for ${chapterId}`);
    }

    try {
        console.log(`[Flashcards] Fetching from server...`);
        const response = await axios.get(`${API_URL}/get_flashcards.php?chapter_id=${chapterId}`);
        console.log(`[Flashcards] Server responded. Status: ${response.status}`);

        // Aggressive Caching: If we got data back, save it.
        // This fixes issues where the API structure varies (array vs object).
        if (response.data) {
            console.log(`[Flashcards] Saving to cache (Aggressive)...`);
            await dataCache.set(cacheKey, response.data, 'flashcards');
        }
        return response.data;
    } catch (error) {
        console.error(`[Flashcards] Network Error:`, error.message);
        throw error;
    }
};

export const fetchQuickRevision = async (chapterId, forceRefresh = false) => {
    const cacheKey = `quick_rev_${chapterId}`;
    console.log(`[QuickRev] Requesting for chapter ${chapterId}`);

    if (!forceRefresh) {
        console.log(`[QuickRev] Checking cache...`);
        const cached = await dataCache.get(cacheKey, 'quick_rev');
        if (cached) {
            console.log(`[QuickRev] Cache HIT`);
            return cached;
        }
        console.log(`[QuickRev] Cache MISS`);
    }

    try {
        console.log(`[QuickRev] Fetching from server...`);
        const response = await axios.get(`${API_URL}/get_quick_revision.php?chapter_id=${chapterId}`);

        // Aggressive Caching
        if (response.data) {
            console.log(`[QuickRev] Saving to cache (Aggressive)...`);
            await dataCache.set(cacheKey, response.data, 'quick_rev');
        }
        return response.data;
    } catch (error) {
        console.error(`[QuickRev] Network Error:`, error.message);
        throw error;
    }
};
