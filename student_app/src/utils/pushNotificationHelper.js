import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import Constants from 'expo-constants';
import { Platform } from 'react-native';
import { registerPushToken } from '../api/notifications';

// Set notification handler to display notifications when the app is open (foreground)
Notifications.setNotificationHandler({
    handleNotification: async () => ({
        shouldShowAlert: true,
        shouldPlaySound: true,
        shouldSetBadge: true,
    }),
});

/**
 * Request notification permissions, fetch Expo Push Token, and save it on the server
 * @param {number|string} userId - The student's user ID
 */
export async function registerForPushNotificationsAsync(userId) {
    if (Platform.OS === 'web') return null;

    if (!userId) {
        console.warn('[PushHelper] No user ID provided, skipping push token registration.');
        return null;
    }

    // Configure Android channels
    if (Platform.OS === 'android') {
        await Notifications.setNotificationChannelAsync('default', {
            name: 'Default',
            importance: Notifications.AndroidImportance.MAX,
            vibrationPattern: [0, 250, 250, 250],
            lightColor: '#C026D3',
            sound: 'default',
        });
    }

    try {
        // 1. Check permissions
        const { status: existingStatus } = await Notifications.getPermissionsAsync();
        let finalStatus = existingStatus;

        if (existingStatus !== 'granted') {
            const { status } = await Notifications.requestPermissionsAsync();
            finalStatus = status;
        }

        if (finalStatus !== 'granted') {
            console.log('[PushHelper] Notification permission not granted.');
            return null;
        }

        // 2. Fetch Expo Push Token
        const projectId = Constants?.expoConfig?.extra?.eas?.projectId ?? '8aee776b-43de-4770-81bd-3fc63d38931a';
        
        const tokenData = await Notifications.getExpoPushTokenAsync({
            projectId: projectId,
        });

        const token = tokenData.data;
        console.log('[PushHelper] Expo Push Token fetched:', token);

        // 3. Register token on the backend server
        const response = await registerPushToken(userId, token);
        console.log('[PushHelper] Push token registration server response:', response);

        return token;
    } catch (error) {
        console.error('[PushHelper] Error in push token registration flow:', error);
        return null;
    }
}
