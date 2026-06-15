import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';

// Cache TTLs per type (milliseconds)
const EXPIRY_MAP = {
    chapters: 60 * 60 * 1000,       // 1 hour  (chapters rarely change mid-session)
    subjects: 2 * 60 * 60 * 1000,   // 2 hours
    default:  24 * 60 * 60 * 1000,  // 24 hours fallback
};

const DEBUG = false;

export const dataCache = {
    /**
     * Save data to cache with a timestamp.
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
     * Get data from cache. Returns null if missing OR expired (unless offline).
     */
    get: async (key, type) => {
        try {
            const raw = await AsyncStorage.getItem(`@cache_${key}`);
            if (!raw) return null;

            const cacheItem = JSON.parse(raw);
            const ttl = EXPIRY_MAP[type] || EXPIRY_MAP.default;
            const age = Date.now() - cacheItem.timestamp;

            if (age > ttl) {
                // If offline, bypass expiry and return stale cache
                const netInfo = await NetInfo.fetch();
                if (!netInfo.isConnected) {
                    if (DEBUG) console.log(`[Cache] Offline: Returning expired cache for ${key}`);
                    return cacheItem.data;
                }

                // Expired & online — remove and return null so caller fetches fresh data
                if (DEBUG) console.log(`[Cache] Expired ${key} (age: ${Math.round(age / 60000)}min)`);
                await AsyncStorage.removeItem(`@cache_${key}`);
                return null;
            }

            if (DEBUG) console.log(`[Cache] Hit ${key} (age: ${Math.round(age / 60000)}min)`);
            return cacheItem.data;
        } catch (error) {
            if (DEBUG) console.warn('[Cache] Get failed:', error);
            return null;
        }
    },

    /**
     * Force get data from cache regardless of expiration. Used as a network failure fallback.
     */
    getStale: async (key) => {
        try {
            const raw = await AsyncStorage.getItem(`@cache_${key}`);
            if (!raw) return null;
            const cacheItem = JSON.parse(raw);
            return cacheItem.data;
        } catch (error) {
            if (DEBUG) console.warn('[Cache] getStale failed:', error);
            return null;
        }
    },

    /**
     * Clear a specific cache item.
     */
    remove: async (key) => {
        try {
            await AsyncStorage.removeItem(`@cache_${key}`);
        } catch (error) {
            if (DEBUG) console.warn('[Cache] Remove failed:', error);
        }
    },

    /**
     * Clear all cache items with the @cache_ prefix.
     */
    clear: async () => {
        try {
            const keys = await AsyncStorage.getAllKeys();
            const cacheKeys = keys.filter(k => k.startsWith('@cache_'));
            if (cacheKeys.length > 0) await AsyncStorage.multiRemove(cacheKeys);
            if (DEBUG) console.log(`[Cache] Cleared ${cacheKeys.length} items`);
        } catch (error) {
            if (DEBUG) console.warn('[Cache] Clear failed:', error);
        }
    },
};
