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
 * Schedules study reminders for a list of chapters
 */
export const scheduleStudyPlanNotifications = async (chapters, startTime, endTime) => {
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
                shouldSetBadge: false,
            }),
        });

        // 1. Request permissions first
        const { status } = await Notifications.requestPermissionsAsync();
        if (status !== 'granted') return false;

        // 2. Cancel existing
        await Notifications.cancelAllScheduledNotificationsAsync();

        if (!chapters || chapters.length === 0) return true;

        // 3. Calculate intervals
        const totalMinutes = (endTime.getTime() - startTime.getTime()) / (1000 * 60);
        const intervalPerChapter = Math.floor(totalMinutes / chapters.length);

        // 4. Schedule each chapter
        for (let i = 0; i < chapters.length; i++) {
            const chapter = chapters[i];
            const scheduleTime = new Date(startTime.getTime() + (i * intervalPerChapter * 60000));

            if (scheduleTime > new Date()) {
                await Notifications.scheduleNotificationAsync({
                    content: {
                        title: i === 0 ? "🚀 Time to start studying!" : "📖 Next Chapter Alert",
                        body: `Next up: ${chapter.chapter_name} (${chapter.subject_name}). Tap to start!`,
                        data: { 
                            type: 'STUDY_REMINDER',
                            chapterId: chapter.chapter_id,
                            chapterName: chapter.chapter_name,
                            subjectName: chapter.subject_name
                        },
                        sound: true,
                    },
                    trigger: scheduleTime,
                });
            }
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
