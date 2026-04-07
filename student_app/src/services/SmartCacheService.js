import { fetchSubjects } from '../api/subjects';
import { fetchChapters } from '../api/chapters';
import {
    fetchMCQs,
    fetchFlashcards,
    fetchQuickRevision,
    fetchNotes,
    fetchVideos
} from '../api/content';
import { dataCache } from '../utils/dataCache';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Image } from 'react-native';
import { BASE_URL } from '../api/config';
import { getCachedFile } from '../utils/downloadUtils';
import { fetchVocabSet, fetchVocabStats } from '../api/vocab';
import { fetchMentalMathProgress } from '../api/mentalMath';

const SYNC_STATUS_KEY = '@smart_sync_status';
const SYNC_QUEUE_KEY = '@smart_sync_queue'; // To resume interrupted syncs

let isProcessing = false; // concurrency lock
let listeners = [];

const notifyListeners = (status) => {
    listeners.forEach(l => l(status));
};

export const SmartCacheService = {
    /**
     * Subscribe to sync status changes
     */
    subscribe: (callback) => {
        listeners.push(callback);
        // Immediately notify the new subscriber with current state
        SmartCacheService.checkSyncState();
        return () => {
            listeners = listeners.filter(l => l !== callback);
        };
    },

    /**
     * Checks if the app is currently fully synced (no queue and completed status)
     */
    checkSyncState: async () => {
        try {
            const queue = await SmartCacheService.getSyncQueue();
            const status = await SmartCacheService.getSyncStatus();
            const isFullySynced = Boolean(status && status.status === 'completed' && (!queue || queue.length === 0));
            notifyListeners({ isSyncing: isProcessing, isFullySynced });
            return isFullySynced;
        } catch (error) {
            return false;
        }
    },

    /**
     * Check for new content updates from server
     */
    checkContentVersion: async (boardType) => {
        try {
            const response = await fetch(`${BASE_URL}/api/check_content_version.php?board_type=${boardType}`);
            const data = await response.json();
            return data.status === 'success' ? data.version : null;
        } catch (error) {
            console.error('[SmartCache] Version check failed:', error);
            return null;
        }
    },
    /**
     * Bulk sync all data for a specific class
     */
    syncAllForClass: async (classId, isPriority = false) => {
        try {
            // Cooldown check: Don't sync more than once every 6 hours automatically
            // But if it's Priority (user just changed class/board), bypass cooldown
            const status = await SmartCacheService.getSyncStatus();
            const SIX_HOURS = 6 * 60 * 60 * 1000;

            if (!isPriority && status && status.classId === classId && (Date.now() - status.lastSync < SIX_HOURS)) {
                console.log(`[SmartCache] ⏭️ Skipping background sync (last sync was recent).`);

                // Even if we skip bulk sync, check if we have any pending "Resume" work
                const queue = await SmartCacheService.getSyncQueue();
                if (queue && queue.length > 0 && !isProcessing) {
                    console.log(`[SmartCache] ⏯️ Resuming interrupted sync for ${queue.length} items...`);
                    await SmartCacheService.processSyncQueue();
                } else {
                    await SmartCacheService.checkSyncState();
                }
                return;
            }

            console.log(`[SmartCache] 🔄 Starting bulk sync for class ${classId} (Priority: ${isPriority})...`);

            if (isPriority) {
                // Clear any old queue if user manually forces a priority sync (e.g. changing class)
                await AsyncStorage.removeItem(SYNC_QUEUE_KEY);
                console.log(`[SmartCache] 🧹 Cleared old sync queue for priority sync`);
            }

            // 1. Sync Subjects
            const subjectRes = await fetchSubjects(classId, true);
            if (subjectRes.status !== 'success') return;
            const subjects = subjectRes.data;

            // Initialize Queue
            const fullQueue = [];
            for (const subject of subjects) {
                const chapterRes = await fetchChapters(subject.subject_id, true);
                if (chapterRes.status === 'success') {
                    chapterRes.data.forEach(ch => fullQueue.push(ch.chapter_id));
                }
            }

            // Save queue to storage so we can resume if app closes
            await AsyncStorage.setItem(SYNC_QUEUE_KEY, JSON.stringify(fullQueue));

            notifyListeners({ isSyncing: true, isFullySynced: false, progress: 0 });

            // 2. Sync GLOBAL features (Vocabulary + Mental Math)
            // Get user from AsyncStorage if available
            const savedUser = await AsyncStorage.getItem('user_data');
            if (savedUser) {
                const user = JSON.parse(savedUser);
                if (user && user.user_id) {
                    await SmartCacheService.syncGlobalContent(user.user_id);
                }
            }

            await SmartCacheService.processSyncQueue();

            let finalQueue = await SmartCacheService.getSyncQueue();
            if (!finalQueue || finalQueue.length === 0) {
                await AsyncStorage.setItem(SYNC_STATUS_KEY, JSON.stringify({
                    lastSync: Date.now(),
                    classId: classId,
                    status: 'completed'
                }));
                // console.log(`[SmartCache] ✅ Bulk sync completed for class ${classId}`);
            }
            await SmartCacheService.checkSyncState();
        } catch (error) {
            console.error('[SmartCache] ❌ Bulk Sync aborted early:', error.message);
            // Allow the UI to unlock immediately
            notifyListeners({ isSyncing: false, isFullySynced: false });
        } finally {
            isProcessing = false;
        }
    },

    processSyncQueue: async () => {
        if (isProcessing) return;
        isProcessing = true;

        try {
            let queue = await SmartCacheService.getSyncQueue();
            
            // Validate queue is an array and filter out nulls/undefined to prevent crashes
            if (!Array.isArray(queue)) {
                queue = [];
                await AsyncStorage.removeItem(SYNC_QUEUE_KEY);
            }
            
            if (queue.length === 0) {
                isProcessing = false;
                await SmartCacheService.checkSyncState();
                return;
            }

            let processedCount = 0;
            while (queue.length > 0) {
                const chapterId = queue[0];
                // console.log(`[SmartCache] 📥 Syncing Chapter: ${chapterId} (${queue.length} left)`);

                await SmartCacheService.syncChapterContent(chapterId);

                queue.shift();
                processedCount++;

                // BATCH SAVE: Only hit AsyncStorage every 5 items to reduce bridge pressure
                if (processedCount % 5 === 0 || queue.length === 0) {
                    await AsyncStorage.setItem(SYNC_QUEUE_KEY, JSON.stringify(queue));
                }

                notifyListeners({ isSyncing: true, isFullySynced: false, itemsLeft: queue.length });

                // Small delay to prevent blocking the UI thread
                await new Promise(r => setTimeout(r, 60));
            }
            await SmartCacheService.checkSyncState();
        } catch (error) {
            console.warn('[SmartCache] Queue processing error. Network may have dropped:', error);
            notifyListeners({ isSyncing: false, isFullySynced: false });
            throw error;
        } finally {
            isProcessing = false;
        }
    },

    /**
     * Sync data types that aren't tied to a chapter (like Vocabulary or specific user progress)
     */
    syncGlobalContent: async (userId) => {
        try {
            console.log(`[SmartCache] 🌍 Syncing Global content (Vocab, Maths) for user ${userId}...`);

            // 1. Sync Mental Math Progress
            await SmartCacheService.retry(() => fetchMentalMathProgress(userId, true));

            // 2. Sync Vocabulary Data
            // Store general stats
            await SmartCacheService.retry(() => fetchVocabStats(userId, true));

            // Pre-download the first 3 sets of Vocabulary (0-2) as they are common for starting
            for (let i = 0; i <= 3; i++) {
                await SmartCacheService.retry(() => fetchVocabSet(userId, i));
            }

            console.log(`[SmartCache] ✅ Global sync completed`);
        } catch (error) {
            console.warn('[SmartCache] Global sync error:', error.message);
        }
    },

    /**
     * Helper to retry a promise-based function with a strict timeout
     */
    retry: async (fn, retries = 2, delay = 1000, timeoutMs = 12000) => {
        for (let i = 0; i < retries; i++) {
            try {
                const timeoutPromise = new Promise((_, reject) =>
                    setTimeout(() => reject(new Error('TIMEOUT')), timeoutMs)
                );
                return await Promise.race([fn(), timeoutPromise]);
            } catch (err) {
                if (i === retries - 1) throw err;
                console.warn(`[SmartCache] Retry ${i + 1}/${retries} failed (${err.message}). Retrying in ${delay}ms...`);
                await new Promise(r => setTimeout(r, delay));
            }
        }
    },

    /**
     * Sync all content types for a single chapter
     */
    syncChapterContent: async (chapterId) => {
        try {
            // 1. Fetch JSON Content in Parallel for speed
            const [mcqRes] = await Promise.all([
                SmartCacheService.retry(() => fetchMCQs(chapterId, true)),
                SmartCacheService.retry(() => fetchFlashcards(chapterId, true)),
                SmartCacheService.retry(() => fetchQuickRevision(chapterId, true)),
                SmartCacheService.retry(() => fetchNotes(chapterId, true)),
                SmartCacheService.retry(() => fetchVideos(chapterId, true))
            ]);

            // 2. Pre-fetch MCQ images
            if (mcqRes && mcqRes.status === 'success' && Array.isArray(mcqRes.data)) {
                mcqRes.data.forEach(item => {
                    if (item.image_url) {
                        const imgUri = item.image_url.startsWith('http') ? item.image_url : `${BASE_URL}/uploads/${item.image_url}`;
                        Image.prefetch(imgUri).catch(e => {}); // Silent failure for missing images
                    }
                });
            }

            // 3. SILENT PDF PRE-FETCHING (REMOVED)
            // User requested to explicitly skip downloading Videos and Notes to save local storage space.
            // Notes and Videos will only be accessed when online.
        } catch (error) {
            console.warn(`[SmartCache] ❌ Failed content for chapter ${chapterId}:`, error.message);
            throw error; // Propagate error to processSyncQueue to abort the queue
        }
    },

    /**
     * Triggered by the Refresh button on ChaptersScreen
     */
    syncSubject: async (subjectId) => {
        try {
            console.log(`[SmartCache] 🔄 Refreshing subject ${subjectId}...`);
            const chapterRes = await fetchChapters(subjectId, true);
            if (chapterRes.status === 'success') {
                const chapters = chapterRes.data;
                for (const ch of chapters) {
                    await SmartCacheService.syncChapterContent(ch.chapter_id);
                }
            }
            return true;
        } catch (error) {
            console.error('[SmartCache] Subject refresh failed:', error);
            return false;
        }
    },

    /**
     * Get the last sync time for the UI
     */
    getSyncStatus: async () => {
        const raw = await AsyncStorage.getItem(SYNC_STATUS_KEY);
        return raw ? JSON.parse(raw) : null;
    },

    /**
     * Get the current sync queue
     */
    getSyncQueue: async () => {
        const raw = await AsyncStorage.getItem(SYNC_QUEUE_KEY);
        return raw ? JSON.parse(raw) : [];
    },

    /**
     * Batch sync of the current queue (Optimized for MainScreen background tasks)
     */
    syncSyncQueueBatched: async () => {
        if (isProcessing) return false;
        try {
            const queue = await SmartCacheService.getSyncQueue();
            if (!queue || queue.length === 0) return true;

            // Simple batched processing (reuse processSyncQueue logic but non-blocking)
            await SmartCacheService.processSyncQueue();
            return true;
        } catch (e) {
            console.warn('[SmartCache] Batched sync failed:', e.message);
            return false;
        }
    }
};
