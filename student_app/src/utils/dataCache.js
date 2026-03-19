import AsyncStorage from '@react-native-async-storage/async-storage';

// Default cache duration
const DEFAULT_EXPIRY_MS = 24 * 60 * 60 * 1000;

// Whether to emit any debug logs (set false in production)
const DEBUG = false;

export const dataCache = {
    /**
     * Save data to cache
     */
    set: async (key, data, type) => {
        try {
            const cacheItem = { data, timestamp: Date.now(), type };
            await AsyncStorage.setItem(`@cache_${key}`, JSON.stringify(cacheItem));
            if (DEBUG) console.log(`[Cache] Saved ${key} (${type})`);
        } catch (error) {
            if (DEBUG) console.warn('[Cache] Set failed:', error);
        }
    },

    /**
     * Get data from cache (stale-while-revalidate)
     */
    get: async (key, type) => {
        try {
            const raw = await AsyncStorage.getItem(`@cache_${key}`);
            if (!raw) return null;
            const cacheItem = JSON.parse(raw);
            if (DEBUG) {
                const ageHours = (Date.now() - cacheItem.timestamp) / (1000 * 60 * 60);
                if (ageHours > 24) {
                    console.log(`[Cache] Stale ${key} (${Math.round(ageHours)}h old)`);
                } else {
                    console.log(`[Cache] Hit ${key}`);
                }
            }
            return cacheItem.data;
        } catch (error) {
            if (DEBUG) console.warn('[Cache] Get failed:', error);
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
            if (DEBUG) console.warn('[Cache] Remove failed:', error);
        }
    }
};
