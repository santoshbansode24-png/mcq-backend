import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';

// Cache TTLs per type (milliseconds)
const EXPIRY_MAP = {
    chapters: 15 * 60 * 1000,      // 15 mins (was 1 hr)
    subjects: 30 * 60 * 1000,      // 30 mins (was 2 hrs)
    default:  60 * 60 * 1000,      // 1 hour fallback (was 24 hrs)
};

// Fast L1 in-memory cache to avoid repeated AsyncStorage SQLite reads & JSON.parse
const memoryCache = new Map();
const MAX_MEMORY_CACHE_ENTRIES = 120;

const setMemoryEntry = (key, cacheItem) => {
    if (memoryCache.size >= MAX_MEMORY_CACHE_ENTRIES && !memoryCache.has(key)) {
        const oldestKey = memoryCache.keys().next().value;
        memoryCache.delete(oldestKey);
    }
    memoryCache.set(key, cacheItem);
};

const DEBUG = false;

export const dataCache = {
    /**
     * Save data to cache with a timestamp.
     */
    set: async (key, data, type) => {
        try {
            const isEmpty = Array.isArray(data) 
                ? data.length === 0 
                : (Array.isArray(data?.data) ? data.data.length === 0 : false);

            const cacheItem = { 
                data, 
                timestamp: Date.now(), 
                type,
                isEmpty
            };
            setMemoryEntry(key, cacheItem);
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
            let cacheItem = memoryCache.get(key);
            if (!cacheItem) {
                const raw = await AsyncStorage.getItem(`@cache_${key}`);
                if (!raw) return null;
                cacheItem = JSON.parse(raw);
                setMemoryEntry(key, cacheItem);
            }

            // If data was empty, revalidate quickly (30s) so newly uploaded content appears
            const ttl = cacheItem.isEmpty ? 30 * 1000 : (EXPIRY_MAP[type] || EXPIRY_MAP.default);
            const age = Date.now() - cacheItem.timestamp;

            if (age > ttl) {
                // If offline, bypass expiry and return cached data
                const netInfo = await NetInfo.fetch();
                if (!netInfo.isConnected) {
                    if (DEBUG) console.log(`[Cache] Offline: Returning expired cache for ${key}`);
                    return cacheItem.data;
                }

                // Online but expired — do NOT delete from storage!
                // Return null so the caller fetches fresh data, but keep cached data for getStale / offline fallback.
                if (DEBUG) console.log(`[Cache] Expired ${key} (age: ${Math.round(age / 60000)}min) - fetching fresh`);
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
            const memItem = memoryCache.get(key);
            if (memItem) return memItem.data;
            const raw = await AsyncStorage.getItem(`@cache_${key}`);
            if (!raw) return null;
            const cacheItem = JSON.parse(raw);
            setMemoryEntry(key, cacheItem);
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
            memoryCache.delete(key);
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
            memoryCache.clear();
            const keys = await AsyncStorage.getAllKeys();
            const cacheKeys = keys.filter(k => k.startsWith('@cache_'));
            if (cacheKeys.length > 0) await AsyncStorage.multiRemove(cacheKeys);
            if (DEBUG) console.log(`[Cache] Cleared ${cacheKeys.length} items`);
        } catch (error) {
            if (DEBUG) console.warn('[Cache] Clear failed:', error);
        }
    },
};
