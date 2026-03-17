import { Platform } from 'react-native';
import Constants from 'expo-constants';

const getNotifications = () => {
    if (Constants.appOwnership === 'expo') {
        return null;
    }
    try {
        return require('expo-notifications');
    } catch (e) {
        return null;
    }
};

/**
 * Schedules study reminders for a list of tasks
 */
export const scheduleStudyPlanNotifications = async (tasks, startTime, endTime) => {
    const Notifications = getNotifications();
    if (!Notifications) {
        console.warn('Notification scheduling skipped: Running in Expo Go or library missing.');
        return false;
    }

    try {
        // --- Android Channel Setup ---
        if (Platform.OS === 'android') {
            await Notifications.setNotificationChannelAsync('study-reminders', {
                name: 'Study Reminders',
                importance: Notifications.AndroidImportance.MAX,
                vibrationPattern: [0, 250, 250, 250],
                lightColor: '#4338ca',
                sound: 'default',
            });
        }

        // Configure only once
        Notifications.setNotificationHandler({
            handleNotification: async () => ({
                shouldShowAlert: true,
                shouldPlaySound: true,
                shouldSetBadge: true,
            }),
        });

        // 1. Request permissions with status check first
        const { status: existingStatus } = await Notifications.getPermissionsAsync();
        let finalStatus = existingStatus;
        if (existingStatus !== 'granted') {
            const { status } = await Notifications.requestPermissionsAsync();
            finalStatus = status;
        }
        if (finalStatus !== 'granted') return false;

        // 2. Clear previous scheduled study reminders to avoid duplicates
        // Instead of cancelAll, we could cancel specific ones, but cancelAll is safer for "Today's Roadmap"
        await Notifications.cancelAllScheduledNotificationsAsync();

        if (!tasks || tasks.length === 0) return true;

        // 3. Calculate intervals
        // Use provided startTime or default to Now/10 AM
        let workStartTime = new Date();
        if (startTime && !isNaN(new Date(startTime).getTime())) {
            workStartTime = new Date(startTime);
        }

        // Ensure we don't start before 10 AM (unless specified)
        if (workStartTime.getHours() < 10) {
            workStartTime.setHours(10, 0, 0, 0);
        }

        // Default end time to 9 PM if not provided
        let workEndTime = new Date();
        workEndTime.setHours(21, 0, 0, 0);
        if (endTime && !isNaN(new Date(endTime).getTime())) {
            workEndTime = new Date(endTime);
        }

        // Calculate total available minutes from now until end time
        const now = new Date();
        const effectiveStart = workStartTime > now ? workStartTime : now;
        const totalMinutes = (workEndTime.getTime() - effectiveStart.getTime()) / (1000 * 60);

        if (totalMinutes <= 15) {
             console.log("[NotificationHelper] Too late to schedule tasks for today.");
             return true; 
        }

        // Space tasks evenly, but with a minimum 30 min gap and maximum 2 hour gap
        let interval = Math.floor(totalMinutes / (tasks.length || 1));
        if (interval < 30) interval = 30; // Min 30 mins
        if (interval > 120) interval = 120; // Max 2 hours

        // 4. Schedule each task
        for (let i = 0; i < tasks.length; i++) {
            const task = tasks[i];
            const scheduleTime = new Date(effectiveStart.getTime() + (i * interval * 60000));

            // Don't schedule for the past or too late tonight
            if (scheduleTime < new Date()) continue;
            if (scheduleTime > workEndTime) break;

            // Normalize task fields to support different response formats
            const taskTitle = task.title || task.chapter_name || 'Study Task';
            const cleanTitle = taskTitle.replace(/.*: /, '');
            const taskSubject = task.subject || task.subject_name || '';

            let title = "🚀 Study Mission Update";
            let emoji = "📖";

            // Map task types to titles/emojis
            const typeConfig = {
                video: { emoji: "🎥", title: "Watch Masterclass" },
                quiz: { emoji: "📝", title: "Practice Quiz Time" },
                flashcard: { emoji: "🎴", title: "Active Recall Session" },
                notes: { emoji: "📑", title: "Revision Mission" }
            };

            const config = typeConfig[task.task_type] || { emoji: "🎯", title: "Next Study Session" };

            await Notifications.scheduleNotificationAsync({
                content: {
                    title: `${config.emoji} ${config.title}`,
                    body: `${cleanTitle} (${taskSubject}). Tap to start!`,
                    data: { 
                        type: 'STUDY_REMINDER',
                        chapterId: task.chapter_id,
                        taskType: task.task_type,
                        chapterName: cleanTitle,
                        subjectName: taskSubject
                    },
                    sound: true,
                    priority: Notifications.AndroidNotificationPriority.HIGH,
                },
                trigger: scheduleTime,
                channelId: 'study-reminders',
            });
        }
        
        // 5. Final "Streak Guard" reminder at 8:30 PM
        const streakGuardTime = new Date();
        streakGuardTime.setHours(20, 30, 0, 0);
        if (streakGuardTime > new Date()) {
            await Notifications.scheduleNotificationAsync({
                content: {
                    title: "🔥 Protect Your Streak!",
                    body: "You still have pending tasks. Finish them now to keep your XP growing!",
                    data: { type: 'STREAK_GUARD' }
                },
                trigger: streakGuardTime,
                channelId: 'study-reminders',
            });
        }

        console.log(`[NotificationHelper] Scheduled ${tasks.length} tasks for today.`);
        return true;
    } catch (error) {
        console.error("Error scheduling notifications:", error);
        return false;
    }
};

export const cancelStudyNotifications = async () => {
    const Notifications = getNotifications();
    if (Notifications) {
        await Notifications.cancelAllScheduledNotificationsAsync();
        console.log("[NotificationHelper] All notifications cancelled.");
    }
};

