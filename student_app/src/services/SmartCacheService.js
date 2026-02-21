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

const SYNC_STATUS_KEY = '@smart_sync_status';
const SYNC_QUEUE_KEY = '@smart_sync_queue'; // To resume interrupted syncs

export const SmartCacheService = {
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
                if (queue && queue.length > 0) {
                    console.log(`[SmartCache] ⏯️ Resuming interrupted sync for ${queue.length} items...`);
                    await SmartCacheService.processSyncQueue();
                }
                return;
            }

            console.log(`[SmartCache] 🔄 Starting bulk sync for class ${classId} (Priority: ${isPriority})...`);

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
            await SmartCacheService.processSyncQueue();

            await AsyncStorage.setItem(SYNC_STATUS_KEY, JSON.stringify({
                lastSync: Date.now(),
                classId: classId,
                status: 'completed'
            }));

            console.log(`[SmartCache] ✅ Bulk sync completed for class ${classId}`);
        } catch (error) {
            console.error('[SmartCache] ❌ Sync failed:', error);
        }
    },

    /**
     * Process items in the sync queue
     */
    processSyncQueue: async () => {
        try {
            let queue = await SmartCacheService.getSyncQueue();
            if (!queue || queue.length === 0) return;

            while (queue.length > 0) {
                const chapterId = queue[0];
                console.log(`[SmartCache] 📥 Syncing Chapter: ${chapterId} (${queue.length} left)`);

                await SmartCacheService.syncChapterContent(chapterId);

                // Remove from queue after successful (or attempted) sync
                queue.shift();
                await AsyncStorage.setItem(SYNC_QUEUE_KEY, JSON.stringify(queue));

                // Small delay to prevent blocking the UI thread too much
                await new Promise(r => setTimeout(r, 100));
            }
        } catch (error) {
            console.warn('[SmartCache] Queue processing error:', error);
        }
    },

    /**
     * Sync all content types for a single chapter
     */
    syncChapterContent: async (chapterId) => {
        try {
            // Sequential to ensure it doesn't overload on low-end devices during background sync
            const mcqRes = await fetchMCQs(chapterId, true);
            await fetchFlashcards(chapterId, true);
            await fetchQuickRevision(chapterId, true);
            await fetchNotes(chapterId, true);
            await fetchVideos(chapterId, true);

            // Pre-fetch MCQ images if they exist
            if (mcqRes && mcqRes.status === 'success' && Array.isArray(mcqRes.data)) {
                mcqRes.data.forEach(item => {
                    if (item.image_url) {
                        const imgUri = `${BASE_URL}/uploads/${item.image_url}`;
                        Image.prefetch(imgUri).catch(e => console.warn('[SmartCache] Image prefetch failed:', imgUri));
                    }
                });
            }
        } catch (error) {
            console.warn(`[SmartCache] ⚠️ Failed content for chapter ${chapterId}:`, error.message);
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
    }
};
