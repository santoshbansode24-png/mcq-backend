import AsyncStorage from '@react-native-async-storage/async-storage';

const LANGUAGE_STORAGE_KEY = '@scholarship_language_preference';

/**
 * Save the user's language preference for Scholarship & Olympiad
 * @param {string} language - 'english' or 'marathi'
 */
export const saveLanguagePreference = async (language) => {
    try {
        await AsyncStorage.setItem(LANGUAGE_STORAGE_KEY, language);
        return true;
    } catch (error) {
        console.error('Error saving language preference:', error);
        return false;
    }
};

/**
 * Get the user's saved language preference
 * @returns {Promise<string>} - Returns 'english' or 'marathi', defaults to 'english'
 */
export const getLanguagePreference = async () => {
    try {
        const language = await AsyncStorage.getItem(LANGUAGE_STORAGE_KEY);
        return language || 'english'; // Default to English
    } catch (error) {
        console.error('Error getting language preference:', error);
        return 'english';
    }
};

/**
 * Clear the language preference (useful for logout/reset)
 */
export const clearLanguagePreference = async () => {
    try {
        await AsyncStorage.removeItem(LANGUAGE_STORAGE_KEY);
        return true;
    } catch (error) {
        console.error('Error clearing language preference:', error);
        return false;
    }
};
