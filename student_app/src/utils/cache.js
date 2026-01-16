import AsyncStorage from '@react-native-async-storage/async-storage';

// Cache duration for different data types (in milliseconds)
const CACHE_DURATIONS = {
    subjects: 30 * 60 * 1000,      // 30 minutes - subjects don't change often
    chapters: 30 * 60 * 1000,      // 30 minutes - chapters are stable
    mcqs: 15 * 60 * 1000,          // 15 minutes - MCQs might be updated
    videos: 60 * 60 * 1000,        // 1 hour - videos rarely change
    notes: 60 * 60 * 1000,         // 1 hour - notes rarely change
    analytics: 5 * 60 * 1000,      // 5 minutes - analytics change frequently
    leaderboard: 5 * 60 * 1000,    // 5 minutes - leaderboard updates often
    badges: 10 * 60 * 1000,        // 10 minutes - badges update occasionally
    notifications: 3 * 60 * 1000,  // 3 minutes - notifications need to be fresh
};

// Cache version prefix to allow invalidating old cache
const CACHE_PREFIX = 'cache_v2_';

/**
 * Cache Manager for API responses
 * Reduces server load and improves app performance by caching API responses
 */
export const cacheManager = {
    /**
     * Get cached data
     * @param {string} key - Cache key (e.g., 'subjects_1', 'chapters_5')
     * @param {string} type - Data type for cache duration (default: 'subjects')
     * @returns {Promise<any|null>} Cached data or null if not found/expired
     */
    async get(key, type = 'subjects') {
        try {
            const cached = await AsyncStorage.getItem(`${CACHE_PREFIX}${key}`);
            if (!cached) {
                console.log(`[Cache MISS] ${key} - not found`);
                return null;
            }

            const { data, timestamp, type: cachedType } = JSON.parse(cached);
            const maxAge = CACHE_DURATIONS[type] || CACHE_DURATIONS[cachedType] || 5 * 60 * 1000;
            const age = Date.now() - timestamp;
            const isExpired = age > maxAge;

            if (isExpired) {
                console.log(`[Cache EXPIRED] ${key} - ${Math.round(age / 1000)}s old (max: ${Math.round(maxAge / 1000)}s)`);
                await this.clear(key);
                return null;
            }

            console.log(`[Cache HIT] ${key} - ${Math.round(age / 1000)}s old`);
            return data;
        } catch (error) {
            console.error('[Cache] Get error:', error);
            return null;
        }
    },

    /**
     * Set cached data
     * @param {string} key - Cache key
     * @param {any} data - Data to cache
     * @param {string} type - Data type for cache duration
     */
    async set(key, data, type = 'subjects') {
        try {
            const cacheData = {
                data,
                timestamp: Date.now(),
                type
            };
            await AsyncStorage.setItem(`${CACHE_PREFIX}${key}`, JSON.stringify(cacheData));
            console.log(`[Cache SET] ${key} (type: ${type})`);
        } catch (error) {
            console.error('[Cache] Set error:', error);
        }
    },

    /**
     * Clear specific cached item
     * @param {string} key - Cache key to clear
     */
    async clear(key) {
        try {
            await AsyncStorage.removeItem(`${CACHE_PREFIX}${key}`);
            console.log(`[Cache CLEAR] ${key}`);
        } catch (error) {
            console.error('[Cache] Clear error:', error);
        }
    },

    /**
     * Clear all cached items
     * Useful for logout or force refresh
     */
    async clearAll() {
        try {
            const keys = await AsyncStorage.getAllKeys();
            const cacheKeys = keys.filter(k => k.startsWith(CACHE_PREFIX));
            await AsyncStorage.multiRemove(cacheKeys);
            console.log(`[Cache] Cleared ${cacheKeys.length} items`);
        } catch (error) {
            console.error('[Cache] Clear all error:', error);
        }
    },

    /**
     * Clear cache by type
     * @param {string} type - Type of cache to clear (e.g., 'subjects', 'chapters')
     */
    async clearByType(type) {
        try {
            const keys = await AsyncStorage.getAllKeys();
            const cacheKeys = keys.filter(k => k.startsWith(CACHE_PREFIX));

            // Get all cache items and filter by type
            const items = await AsyncStorage.multiGet(cacheKeys);
            const keysToRemove = items
                .filter(([key, value]) => {
                    try {
                        const parsed = JSON.parse(value);
                        return parsed.type === type;
                    } catch {
                        return false;
                    }
                })
                .map(([key]) => key);

            await AsyncStorage.multiRemove(keysToRemove);
            console.log(`[Cache] Cleared ${keysToRemove.length} items of type '${type}'`);
        } catch (error) {
            console.error('[Cache] Clear by type error:', error);
        }
    },

    /**
     * Get cache statistics
     * @returns {Promise<Object>} Cache stats
     */
    async getStats() {
        try {
            const keys = await AsyncStorage.getAllKeys();
            const cacheKeys = keys.filter(k => k.startsWith(CACHE_PREFIX));
            const items = await AsyncStorage.multiGet(cacheKeys);

            const stats = {
                totalItems: cacheKeys.length,
                byType: {},
                oldestItem: null,
                newestItem: null,
            };

            items.forEach(([key, value]) => {
                try {
                    const { type, timestamp } = JSON.parse(value);
                    stats.byType[type] = (stats.byType[type] || 0) + 1;

                    if (!stats.oldestItem || timestamp < stats.oldestItem) {
                        stats.oldestItem = timestamp;
                    }
                    if (!stats.newestItem || timestamp > stats.newestItem) {
                        stats.newestItem = timestamp;
                    }
                } catch (e) {
                    // Skip invalid items
                }
            });

            return stats;
        } catch (error) {
            console.error('[Cache] Get stats error:', error);
            return { totalItems: 0, byType: {} };
        }
    }
};

export default cacheManager;
