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

export const SmartCacheService = {
    /**
     * Bulk sync all data for a specific class
     */
    syncAllForClass: async (classId) => {
        try {
            // Cooldown check: Don't sync more than once every 6 hours automatically
            const status = await SmartCacheService.getSyncStatus();
            const SIX_HOURS = 6 * 60 * 60 * 1000;
            if (status && status.classId === classId && (Date.now() - status.lastSync < SIX_HOURS)) {
                console.log(`[SmartCache] ⏭️ Skipping background sync (last sync was recent).`);
                return;
            }

            console.log(`[SmartCache] 🔄 Starting bulk sync for class ${classId}...`);

            // 1. Sync Subjects
            const subjectRes = await fetchSubjects(classId, true);
            if (subjectRes.status !== 'success') return;
            const subjects = subjectRes.data;

            // 2. Sync Chapters for each subject
            for (const subject of subjects) {
                console.log(`[SmartCache]   - Syncing subject: ${subject.subject_name}`);
                const chapterRes = await fetchChapters(subject.subject_id, true);
                if (chapterRes.status === 'success') {
                    const chapters = chapterRes.data;

                    // 3. Sync Content for each chapter (Parallel Chunks of 3)
                    for (let i = 0; i < chapters.length; i += 3) {
                        const chunk = chapters.slice(i, i + 3);
                        await Promise.all(chunk.map(ch => SmartCacheService.syncChapterContent(ch.chapter_id)));
                    }
                }
            }

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
     * Sync all content types for a single chapter
     */
    syncChapterContent: async (chapterId) => {
        try {
            const [mcqRes] = await Promise.all([
                fetchMCQs(chapterId, true),
                fetchFlashcards(chapterId, true),
                fetchQuickRevision(chapterId, true),
                fetchNotes(chapterId, true),
                fetchVideos(chapterId, true)
            ]);

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
                for (let i = 0; i < chapters.length; i += 3) {
                    const chunk = chapters.slice(i, i + 3);
                    await Promise.all(chunk.map(ch => SmartCacheService.syncChapterContent(ch.chapter_id)));
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
