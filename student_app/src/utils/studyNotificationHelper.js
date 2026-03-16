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
        // Configure only once
        Notifications.setNotificationHandler({
            handleNotification: async () => ({
                shouldShowAlert: true,
                shouldPlaySound: true,
                shouldSetBadge: true,
            }),
        });

        // 1. Request permissions
        const { status } = await Notifications.requestPermissionsAsync();
        if (status !== 'granted') return false;

        // 2. Clear previous today's reminders
        await Notifications.cancelAllScheduledNotificationsAsync();

        if (!tasks || tasks.length === 0) return true;

        // 3. Calculate intervals
        // We start from 10 AM (or current time if later) and end at 9 PM
        const workStartTime = new Date(startTime);
        if (workStartTime.getHours() < 10) workStartTime.setHours(10, 0, 0);
        
        const workEndTime = new Date(endTime);
        if (workEndTime.getHours() > 21) workEndTime.setHours(21, 0, 0);

        const totalMinutes = (workEndTime.getTime() - workStartTime.getTime()) / (1000 * 60);
        const interval = Math.floor(totalMinutes / (tasks.length || 1));

        // 4. Schedule each task
        for (let i = 0; i < tasks.length; i++) {
            const task = tasks[i];
            const scheduleTime = new Date(workStartTime.getTime() + (i * interval * 60000));

            // Don't schedule for the past
            if (scheduleTime < new Date()) continue;

            let title = "🚀 Study Mission Update";
            let body = `Next: ${task.title}`;
            let emoji = "📖";

            if (task.task_type === 'video') { emoji = "🎥"; title = "Watch Masterclass"; }
            if (task.task_type === 'quiz') { emoji = "📝"; title = "Practice Quiz Time"; }
            if (task.task_type === 'flashcard') { emoji = "🎴"; title = "Active Recall Session"; }
            if (task.task_type === 'notes') { emoji = "📑"; title = "Revision Mission"; }

            await Notifications.scheduleNotificationAsync({
                content: {
                    title: `${emoji} ${title}`,
                    body: `${task.title} (${task.subject}). Tap to start!`,
                    data: { 
                        type: 'STUDY_REMINDER',
                        chapterId: task.chapter_id,
                        taskType: task.task_type
                    },
                    sound: true,
                },
                trigger: scheduleTime,
            });
        }
        
        // 5. Final "Streak Guard" reminder at 8 PM if not already finished
        const streakGuardTime = new Date();
        streakGuardTime.setHours(20, 0, 0);
        if (streakGuardTime > new Date()) {
            await Notifications.scheduleNotificationAsync({
                content: {
                    title: "🔥 Protect Your Streak!",
                    body: "You still have pending tasks. Finish them now to keep your XP growing!",
                },
                trigger: streakGuardTime,
            });
        }

        return true;
    } catch (error) {
        console.error("Error scheduling notifications:", error);
        return false;
    }
};

export const cancelStudyNotifications = async () => {
    const Notifications = getNotifications();
    if (Notifications) await Notifications.cancelAllScheduledNotificationsAsync();
};
