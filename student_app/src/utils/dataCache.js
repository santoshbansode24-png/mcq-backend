import AsyncStorage from '@react-native-async-storage/async-storage';

// Default cache duration (7 days) for safety, but effectively permanent until manual refresh
const DEFAULT_EXPIRY_MS = 7 * 24 * 60 * 60 * 1000;

export const dataCache = {
    /**
     * Save data to cache
     * @param {string} key - Unique key for the data (e.g., 'mcq_15')
     * @param {any} data - The data to store
     * @param {string} type - Content type (mcqs, notes, etc.)
     */
    set: async (key, data, type) => {
        try {
            const cacheItem = {
                data,
                timestamp: Date.now(),
                type
            };
            await AsyncStorage.setItem(`@cache_${key}`, JSON.stringify(cacheItem));
            console.log(`[Cache] Saved ${key} (${type})`);
        } catch (error) {
            console.warn('[Cache] Set failed:', error);
        }
    },

    /**
     * Get data from cache
     * @param {string} key - Unique key
     * @param {string} type - Content type
     * @returns {any|null} - The data or null if missing/expired
     */
    get: async (key, type) => {
        try {
            const raw = await AsyncStorage.getItem(`@cache_${key}`);
            if (!raw) return null;

            const cacheItem = JSON.parse(raw);

            // Optional: Check expiry (currently disabled for "Permanent" strategy)
            // if (Date.now() - cacheItem.timestamp > DEFAULT_EXPIRY_MS) return null;

            console.log(`[Cache] Hit ${key}`);
            return cacheItem.data;
        } catch (error) {
            console.warn('[Cache] Get failed:', error);
            return null;
        }
    },

    /**
     * Clear specific cache item
     */
    remove: async (key) => {
        try {
            await AsyncStorage.removeItem(`@cache_${key}`);
        } catch (error) {
            console.warn('[Cache] Remove failed:', error);
        }
    }
};
