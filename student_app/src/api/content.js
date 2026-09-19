import axios from 'axios';
import { API_URL } from './config';
import { dataCache } from '../utils/dataCache';

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

export const markSetCompleted = async (userId, chapterId, setIndex, type, score = 0, total = 0) => {
    try {
        const response = await axios.post(`${API_URL}/mark_set_completed.php`, {
            user_id: userId,
            chapter_id: chapterId,
            set_index: setIndex,
            type,
            score,
            total
        });
        return response.data;
    } catch (error) {
        console.error('Mark Set Completed Error:', error);
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

export const fetchMCQs = async (chapterId, forceRefresh = false, lang = 'en') => {
    const cacheKey = `mcqs_${chapterId}_${lang}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'mcqs');
        if (cached) {
            return cached;
        }
    }

    try {
        const response = await axios.get(`${API_URL}/get_mcqs.php?chapter_id=${chapterId}&lang=${lang}`);

        if (response.data && response.data.status === 'success') {
            await dataCache.set(cacheKey, response.data, 'mcqs');
        }

        return response.data;
    } catch (error) {
        const staleCached = await dataCache.getStale(cacheKey);
        if (staleCached) {
            return staleCached;
        }
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

export const fetchNotes = async (chapterId, forceRefresh = false) => {
    const cacheKey = `notes_${chapterId}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'notes');
        if (cached) {
            return cached;
        }
    }

    try {
        const response = await axios.get(`${API_URL}/get_notes.php?chapter_id=${chapterId}`);

        if (response.data && response.data.status === 'success') {
            await dataCache.set(cacheKey, response.data, 'notes');
        }

        return response.data;
    } catch (error) {
        const staleCached = await dataCache.getStale(cacheKey);
        if (staleCached) {
            return staleCached;
        }
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

export const fetchVideos = async (chapterId, forceRefresh = false) => {
    const cacheKey = `videos_${chapterId}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'videos');
        if (cached) {
            return cached;
        }
    }

    try {
        const response = await axios.get(`${API_URL}/get_videos.php?chapter_id=${chapterId}`);

        if (response.data && response.data.status === 'success') {
            await dataCache.set(cacheKey, response.data, 'videos');
        }

        return response.data;
    } catch (error) {
        const staleCached = await dataCache.getStale(cacheKey);
        if (staleCached) {
            return staleCached;
        }
        throw error.response ? error.response.data : new Error('Network Error');
    }
};

export const fetchFlashcards = async (chapterId, forceRefresh = false, lang = 'en') => {
    const cacheKey = `flashcards_${chapterId}_${lang}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'flashcards');
        if (cached) {
            return cached;
        }
    }

    try {
        const response = await axios.get(`${API_URL}/get_flashcards.php?chapter_id=${chapterId}&lang=${lang}`);
        const responseData = response.data?.data || (Array.isArray(response.data) ? response.data : null);
        const isSuccess = response.data?.status === 'success' || (Array.isArray(response.data) && response.data.length > 0);

        if (isSuccess && responseData) {
            await dataCache.set(cacheKey, response.data, 'flashcards');
        }
        return response.data;
    } catch (error) {
        const staleCached = await dataCache.getStale(cacheKey);
        if (staleCached) {
            return staleCached;
        }
        throw error;
    }
};

export const fetchQuickRevision = async (chapterId, forceRefresh = false, lang = 'en') => {
    const cacheKey = `quick_rev_${chapterId}_${lang}`;

    if (!forceRefresh) {
        const cached = await dataCache.get(cacheKey, 'quick_revision');
        if (cached) {
            return cached;
        }
    }

    try {
        const response = await axios.get(`${API_URL}/get_quick_revision.php?chapter_id=${chapterId}&lang=${lang}`);

        if (response.data && response.data.status === 'success') {
            await dataCache.set(cacheKey, response.data, 'quick_revision');
        }

        return response.data;
    } catch (error) {
        const staleCached = await dataCache.getStale(cacheKey);
        if (staleCached) {
            return staleCached;
        }
        throw error;
    }
};
