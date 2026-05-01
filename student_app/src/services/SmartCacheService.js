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
        if (isProcessing && !isPriority) {
            console.log(`[SmartCache] ⚠️ Sync already in progress. Ignoring bulk sync request.`);
            return;
        }

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
                }
                return;
            }

            console.log(`[SmartCache] 🔄 Starting bulk sync for class ${classId} (Priority: ${isPriority})...`);

            isProcessing = true;

            if (isPriority) {
                // Clear any old queue if user manually forces a priority sync (e.g. changing class)
                await AsyncStorage.removeItem(SYNC_QUEUE_KEY);
                console.log(`[SmartCache] 🧹 Cleared old sync queue for priority sync`);
            }

            // Tell UI we are syncing
            notifyListeners({ isSyncing: true, isFullySynced: false, progress: 0 });

            // 1. Sync Subjects (Wrapped in retry to prevent infinite hang)
            const subjectRes = await SmartCacheService.retry(() => fetchSubjects(classId, true));
            const isSubjectSuccess = subjectRes && (subjectRes.status === 'success' || Array.isArray(subjectRes.data) || Array.isArray(subjectRes));
            
            if (!isSubjectSuccess) {
                console.warn('[SmartCache] Failed to fetch subjects, aborting sync');
                return;
            }

            const subjects = Array.isArray(subjectRes) ? subjectRes : (subjectRes.data || []);

            // Initialize Queue
            const fullQueue = [];
            for (const subject of subjects) {
                try {
                    const chapterRes = await SmartCacheService.retry(() => fetchChapters(subject.subject_id, true));
                    const isChapterSuccess = chapterRes && (chapterRes.status === 'success' || Array.isArray(chapterRes.data) || Array.isArray(chapterRes));
                    
                    if (isChapterSuccess) {
                        const chapters = Array.isArray(chapterRes) ? chapterRes : (chapterRes.data || []);
                        chapters.forEach(ch => fullQueue.push(ch.chapter_id));
                    }
                } catch (e) {
                    console.warn(`[SmartCache] Failed to fetch chapters for subject ${subject.subject_id}`);
                }
            }

            // Save queue to storage so we can resume if app closes
            await AsyncStorage.setItem(SYNC_QUEUE_KEY, JSON.stringify(fullQueue));

            // 2. Sync GLOBAL features (Vocabulary + Mental Math)
            const savedUser = await AsyncStorage.getItem('user_data');
            if (savedUser) {
                const user = JSON.parse(savedUser);
                if (user && user.user_id) {
                    await SmartCacheService.syncGlobalContent(user.user_id).catch(e => console.warn('[SmartCache] Global content sync failed:', e.message));
                }
            }

            // 3. Process Chapter Queue
            await SmartCacheService.processSyncQueue(true);

            // 4. Mark as completed if queue is empty (or mostly empty)
            const finalQueue = await SmartCacheService.getSyncQueue();
            if (!finalQueue || finalQueue.length === 0) {
                await AsyncStorage.setItem(SYNC_STATUS_KEY, JSON.stringify({
                    lastSync: Date.now(),
                    classId: classId,
                    status: 'completed'
                }));
                // console.log(`[SmartCache] ✅ Bulk sync completed for class ${classId}`);
            }
        } catch (error) {
            console.error('[SmartCache] ❌ Bulk Sync failed:', error.message);
        } finally {
            isProcessing = false;
            await SmartCacheService.checkSyncState();
        }
    },

    processSyncQueue: async (internalCall = false) => {
        if (!internalCall && isProcessing) return;
        isProcessing = true;

        try {
            let queue = await SmartCacheService.getSyncQueue();
            
            if (!Array.isArray(queue) || queue.length === 0) {
                return; // Let finally block handle the state check
            }

            let processedCount = 0;
            let failureCount = 0;

            while (queue.length > 0) {
                const chapterId = queue[0];
                
                try {
                    // Try to sync this specific chapter
                    await SmartCacheService.syncChapterContent(chapterId);
                } catch (chapterErr) {
                    console.warn(`[SmartCache] ⚠️ Chapter ${chapterId} sync failed (skipped):`, chapterErr.message);
                    failureCount++;
                    // If we have too many failures in a row, maybe the server is down?
                    if (failureCount > 10) throw new Error('Too many failures, aborting queue');
                }

                queue.shift();
                processedCount++;

                // Save progress periodically
                if (processedCount % 5 === 0 || queue.length === 0) {
                    await AsyncStorage.setItem(SYNC_QUEUE_KEY, JSON.stringify(queue));
                }

                notifyListeners({ isSyncing: true, isFullySynced: false, itemsLeft: queue.length });

                // Throttle slightly to keep UI responsive
                await new Promise(r => setTimeout(r, processedCount % 10 === 0 ? 100 : 30));
            }

            // Final safety: if queue is empty, mark status as completed
            if (!queue || queue.length === 0) {
                const status = await SmartCacheService.getSyncStatus();
                await AsyncStorage.setItem(SYNC_STATUS_KEY, JSON.stringify({
                    ...(status || {}),
                    lastSync: Date.now(),
                    status: 'completed'
                }));
            }
        } catch (error) {
            console.warn('[SmartCache] Queue processing interrupted:', error.message);
        } finally {
            isProcessing = false;
            await SmartCacheService.checkSyncState();
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
            for (let i = 0; i < 3; i++) {
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
            let timeoutId;
            try {
                const timeoutPromise = new Promise((_, reject) => {
                    timeoutId = setTimeout(() => reject(new Error('TIMEOUT')), timeoutMs);
                });
                const result = await Promise.race([fn(), timeoutPromise]);
                clearTimeout(timeoutId);
                return result;
            } catch (err) {
                clearTimeout(timeoutId);
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
            const results = await Promise.allSettled([
                SmartCacheService.retry(() => fetchMCQs(chapterId, true)),
                SmartCacheService.retry(() => fetchFlashcards(chapterId, true)),
                SmartCacheService.retry(() => fetchQuickRevision(chapterId, true)),
                SmartCacheService.retry(() => fetchNotes(chapterId, true)),
                SmartCacheService.retry(() => fetchVideos(chapterId, true))
            ]);

            // If ALL endpoints failed, it's likely a network issue. Abort to pause the queue.
            const allFailed = results.every(r => r.status === 'rejected');
            if (allFailed) {
                throw new Error('All endpoints failed. Likely network connection issue.');
            }

            // 2. Pre-fetch MCQ images
            const mcqResult = results[0];
            const mcqRes = mcqResult.status === 'fulfilled' ? mcqResult.value : null;
            const mcqData = Array.isArray(mcqRes) ? mcqRes : (mcqRes?.data || []);
            const isMcqSuccess = Array.isArray(mcqRes) || mcqRes?.status === 'success';

            if (isMcqSuccess && Array.isArray(mcqData)) {
                mcqData.forEach(item => {
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
