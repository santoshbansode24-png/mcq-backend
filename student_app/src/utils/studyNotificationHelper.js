import { Platform } from 'react-native';
import Constants from 'expo-constants';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
const getNotifications = () => {
    if (Constants.appOwnership === 'expo') return null;
    try {
        return require('expo-notifications');
    } catch (e) {
        return null;
    }
};

const CHANNEL_ID = 'study-planner';

/** Sets up the Android notification channel (called once per schedule run) */
const ensureChannel = async (Notifications) => {
    if (Platform.OS !== 'android') return;
    await Notifications.setNotificationChannelAsync(CHANNEL_ID, {
        name: 'Study Planner Reminders',
        importance: Notifications.AndroidImportance.MAX,
        vibrationPattern: [0, 250, 250, 250],
        lightColor: '#4338ca',
        sound: 'default',
        enableVibrate: true,
        showBadge: true,
    });
};

/** Requests permission; returns true if granted */
const ensurePermission = async (Notifications) => {
    const { status: existing } = await Notifications.getPermissionsAsync();
    if (existing === 'granted') return true;
    const { status } = await Notifications.requestPermissionsAsync();
    return status === 'granted';
};

// ---------------------------------------------------------------------------
// Extract the clean chapter / topic name from a task title
//   "Watch: Real Numbers"   → "Real Numbers"
//   "Quiz: Fractions"       → "Fractions"
//   "Mega Revision Blitz: Ch1 + Ch2" → "Ch1 + Ch2"
//   "Cards: Light"          → "Light"
// ---------------------------------------------------------------------------
const extractChapterName = (taskTitle = '') => {
    // Strip known prefixes like "Watch: " / "Read Notes: " / "Quiz: " etc.
    const clean = taskTitle.replace(/^(Watch|Read Notes|Notes|Quiz|Cards|Flashcards|Read|Pract)[:\s]+/i, '').trim();
    return clean || taskTitle;
};

/**
 * Returns a deduplicated list of pending chapter/topic names for today.
 * Blitz tasks are shown as "Revision Blitz" to keep it short.
 *
 * @param {Array} pendingTasks  - tasks with status !== 'completed'
 * @param {number} maxChapters  - max distinct chapter names to list
 * @returns {string}            - e.g. "Real Numbers, Fractions, Light"
 */
const getPendingChapterLine = (pendingTasks = [], maxChapters = 3) => {
    const seen = new Set();
    const names = [];

    for (const task of pendingTasks) {
        if (names.length >= maxChapters) break;
        if (task.task_type === 'mega') {
            if (!seen.has('__blitz__')) {
                seen.add('__blitz__');
                names.push('⚡ Revision Blitz');
            }
            continue;
        }
        const name = extractChapterName(task.title);
        if (!seen.has(name)) {
            seen.add(name);
            names.push(name);
        }
    }

    if (names.length === 0) return '';
    const extra = pendingTasks.length > maxChapters
        ? ` +${pendingTasks.length - names.length} more`
        : '';
    return names.join(', ') + extra;
};

// ---------------------------------------------------------------------------
// Smart message builder  (progress % + chapter names)
// ---------------------------------------------------------------------------

/**
 * Builds { title, body } for each notification slot.
 *
 * @param {number} completed       - tasks done
 * @param {number} total           - total tasks
 * @param {string} pushType        - 'morning' | 'afternoon' | 'evening' | 'streak_guard'
 * @param {string} chapterLine     - e.g. "Real Numbers, Fractions"  (may be empty)
 * @param {string} contextLabel    - 'today' or 'yesterday'
 */
const buildSmartMessage = (completed, total, pushType = 'morning', chapterLine = '', contextLabel = 'today') => {
    const pct       = total > 0 ? Math.round((completed / total) * 100) : 0;
    const remaining = total - completed;
    const chapStr   = chapterLine ? `\n📌 Pending: ${chapterLine}` : '';
    const ctx       = contextLabel; // 'today' or 'yesterday'

    // ── MORNING ──────────────────────────────────────────────────
    if (pushType === 'morning') {
        if (pct === 100) {
            return {
                title: '🌟 Yesterday was Perfect!',
                body: `You crushed 100% of ${ctx}'s plan! Today's roadmap is fresh and ready.`
            };
        }
        if (pct >= 70) {
            return {
                title: '🚀 Great Progress Yesterday!',
                body: `You hit ${pct}% of your plan. Today, aim for 100%!${chapStr}`
            };
        }
        if (pct >= 40) {
            return {
                title: '📚 Study Plan Ready!',
                body: `Yesterday: ${pct}% done. ${remaining} task${remaining !== 1 ? 's' : ''} carried forward.${chapStr}`
            };
        }
        if (pct === 0 && total > 0) {
            return {
                title: '⚠️ Don\'t Fall Behind!',
                body: `You missed yesterday's plan. Get back on track today!${chapStr}`
            };
        }
        // default morning
        return {
            title: '📖 Your Study Plan Awaits!',
            body: `Good morning! Let's start strong today.${chapStr}`
        };
    }

    // ── AFTERNOON ────────────────────────────────────────────────
    if (pushType === 'afternoon') {
        if (pct === 100) {
            return {
                title: '✅ All Tasks Done!',
                body: 'Amazing — you\'ve finished everything for today. Review your notes for bonus mastery!'
            };
        }
        if (pct >= 50) {
            return {
                title: '⚡ Halfway There!',
                body: `${pct}% done! ${remaining} task${remaining !== 1 ? 's' : ''} left.${chapStr}`
            };
        }
        return {
            title: '🎯 Study Session Time!',
            body: `${remaining} task${remaining !== 1 ? 's' : ''} still pending for today.${chapStr}`
        };
    }

    // ── EVENING ──────────────────────────────────────────────────
    if (pushType === 'evening') {
        if (pct === 100) {
            return {
                title: '🏆 Day Complete!',
                body: '100% done! Your streak is protected. See you tomorrow! 🔥'
            };
        }
        if (pct >= 60) {
            return {
                title: '🔥 Almost There!',
                body: `${pct}% done. Just ${remaining} task${remaining !== 1 ? 's' : ''} left. Finish strong!${chapStr}`
            };
        }
        return {
            title: '🔥 Don\'t Break Your Streak!',
            body: `Only ${pct}% done. ${remaining} task${remaining !== 1 ? 's' : ''} waiting for you.${chapStr}`
        };
    }

    // ── STREAK GUARD ─────────────────────────────────────────────
    return {
        title: '🔥 Last Chance — Save Your Streak!',
        body: `${remaining} task${remaining !== 1 ? 's' : ''} still pending. Finish before midnight!${chapStr}`
    };
};

// ---------------------------------------------------------------------------
// Core scheduling function
// ---------------------------------------------------------------------------

/**
 * Schedules smart study plan notifications with chapter names in the body.
 *
 * @param {Array} todayTasks      - today's task objects (from get_roadmap)
 * @param {Array} yesterdayTasks  - yesterday's task objects (may be empty)
 */
export const scheduleSmartStudyNotifications = async (todayTasks = [], yesterdayTasks = []) => {
    const Notifications = getNotifications();
    if (!Notifications) {
        console.warn('[StudyNotif] Skipped: Expo Go or library missing.');
        return false;
    }

    try {
        Notifications.setNotificationHandler({
            handleNotification: async () => ({
                shouldShowAlert: true,
                shouldPlaySound: true,
                shouldSetBadge: true,
            }),
        });

        await ensureChannel(Notifications);
        const granted = await ensurePermission(Notifications);
        if (!granted) {
            console.warn('[StudyNotif] Permission denied.');
            return false;
        }

        await Notifications.cancelAllScheduledNotificationsAsync();

        // ── Compute progress ─────────────────────────────────────────
        const totalToday        = todayTasks.length;
        const doneToday         = todayTasks.filter(t => t.status === 'completed').length;
        const pendingTodayTasks = todayTasks.filter(t => t.status !== 'completed');

        const totalYesterday    = yesterdayTasks.length;
        const doneYesterday     = yesterdayTasks.filter(t => t.status === 'completed').length;
        const pendingYestTasks  = yesterdayTasks.filter(t => t.status !== 'completed');

        // Chapter name lines for notification bodies
        const todayChapterLine  = getPendingChapterLine(pendingTodayTasks, 3);
        const yesterdayChapterLine = getPendingChapterLine(pendingYestTasks, 3);

        const now = new Date();
        const HIGH = Notifications.AndroidNotificationPriority?.HIGH ?? 'high';

        const base_data = { type: 'STUDY_PLANNER', screen: 'StudyPlanner' };

        // ── 1. MORNING (9:00 AM) — yesterday's context ───────────────
        const morningTime = new Date(); morningTime.setHours(9, 0, 0, 0);
        if (morningTime > now) {
            const msg = buildSmartMessage(
                doneYesterday, totalYesterday, 'morning', yesterdayChapterLine, 'yesterday'
            );
            await Notifications.scheduleNotificationAsync({
                content: {
                    title: msg.title,
                    body: msg.body,
                    data: base_data,
                    sound: true,
                    priority: HIGH,
                },
                trigger: { date: morningTime, channelId: CHANNEL_ID },
            });
        }

        // ── 2. AFTERNOON (2:00 PM) — today's pending ─────────────────
        const afternoonTime = new Date(); afternoonTime.setHours(14, 0, 0, 0);
        if (afternoonTime > now) {
            const msg = buildSmartMessage(
                doneToday, totalToday, 'afternoon', todayChapterLine, 'today'
            );
            await Notifications.scheduleNotificationAsync({
                content: {
                    title: msg.title,
                    body: msg.body,
                    data: base_data,
                    sound: true,
                    priority: HIGH,
                },
                trigger: { date: afternoonTime, channelId: CHANNEL_ID },
            });
        }

        // ── 3. EVENING (6:30 PM) — today's pending ───────────────────
        const eveningTime = new Date(); eveningTime.setHours(18, 30, 0, 0);
        if (eveningTime > now) {
            const msg = buildSmartMessage(
                doneToday, totalToday, 'evening', todayChapterLine, 'today'
            );
            await Notifications.scheduleNotificationAsync({
                content: {
                    title: msg.title,
                    body: msg.body,
                    data: base_data,
                    sound: true,
                    priority: HIGH,
                },
                trigger: { date: eveningTime, channelId: CHANNEL_ID },
            });
        }

        // ── 4. STREAK GUARD (8:30 PM) — only if tasks still pending ──
        const streakTime = new Date(); streakTime.setHours(20, 30, 0, 0);
        if (streakTime > now && doneToday < totalToday) {
            const msg = buildSmartMessage(
                doneToday, totalToday, 'streak_guard', todayChapterLine, 'today'
            );
            await Notifications.scheduleNotificationAsync({
                content: {
                    title: msg.title,
                    body: msg.body,
                    data: base_data,
                    sound: true,
                    priority: HIGH,
                },
                trigger: { date: streakTime, channelId: CHANNEL_ID },
            });
        }

        // ── 5. PER-TASK REMINDERS (max 4, evenly spaced until 9 PM) ──
        //    Title = task type label
        //    Body  = "Chapter Name (Subject)" — chapter name is the star
        const reminderEnd  = new Date(); reminderEnd.setHours(21, 0, 0, 0);
        const availableMs  = reminderEnd - now;
        const reminderLimit = Math.min(4, pendingTodayTasks.length);

        const typeConfig = {
            video:     { emoji: '🎥', label: 'Watch & Learn' },
            quiz:      { emoji: '📝', label: 'Practice Quiz' },
            flashcard: { emoji: '🎴', label: 'Active Recall' },
            notes:     { emoji: '📑', label: 'Read Notes' },
            mega:      { emoji: '⚡', label: 'Mega Blitz Exam' },
            revision:  { emoji: '🔄', label: 'Quick Revision' },
        };

        if (reminderLimit > 0 && availableMs > 30 * 60 * 1000) {
            const gapMs = Math.floor(availableMs / (reminderLimit + 1));

            for (let i = 0; i < reminderLimit; i++) {
                const task     = pendingTodayTasks[i];
                const fireTime = new Date(now.getTime() + (i + 1) * gapMs);
                if (fireTime >= reminderEnd) break;

                const cfg         = typeConfig[task.task_type] || { emoji: '🎯', label: 'Study Session' };
                const chapterName = extractChapterName(task.title);
                const subject     = task.subject && task.subject !== 'Full Syllabus' && task.subject !== 'Revision'
                    ? ` · ${task.subject}`
                    : '';

                // Title = type label   |   Body = Chapter name prominently
                const notifTitle = `${cfg.emoji} ${cfg.label}`;
                const notifBody  = task.task_type === 'mega'
                    ? `⚡ Time for your Mega Revision Blitz!${subject ? `\n${subject}` : ''}`
                    : `📖 "${chapterName}"${subject}\nTap to open your study plan.`;

                await Notifications.scheduleNotificationAsync({
                    content: {
                        title: notifTitle,
                        body: notifBody,
                        data: {
                            type: 'STUDY_PLANNER',
                            screen: 'StudyPlanner',
                            taskType: task.task_type,
                            chapterId: task.chapter_id,
                        },
                        sound: true,
                        priority: HIGH,
                    },
                    trigger: { date: fireTime, channelId: CHANNEL_ID },
                });
            }
        }

        console.log(
            `[StudyNotif] ✅ Scheduled. Today: ${doneToday}/${totalToday} done.`,
            `Pending chapters: ${todayChapterLine || 'none'}`
        );
        return true;

    } catch (error) {
        console.error('[StudyNotif] Error scheduling notifications:', error);
        return false;
    }
};

// ---------------------------------------------------------------------------
// Legacy compatibility export
// ---------------------------------------------------------------------------
export const scheduleStudyPlanNotifications = async (tasks, _startTime, _endTime) => {
    return scheduleSmartStudyNotifications(tasks, []);
};

export const cancelStudyNotifications = async () => {
    const Notifications = getNotifications();
    if (Notifications) {
        await Notifications.cancelAllScheduledNotificationsAsync();
        console.log('[StudyNotif] All notifications cancelled.');
    }
};
