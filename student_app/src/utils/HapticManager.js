import * as Haptics from 'expo-haptics';

class HapticManager {
    static triggerSuccess() {
        try {
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch (error) {
            console.log('Haptic feedback not available', error);
        }
    }

    static triggerError() {
        try {
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
        } catch (error) {
            console.log('Haptic feedback not available', error);
        }
    }

    static triggerWarning() {
        try {
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
        } catch (error) {
            console.log('Haptic feedback not available', error);
        }
    }

    static triggerLight() {
        try {
            Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
        } catch (error) {
            console.log('Haptic feedback not available', error);
        }
    }

    static triggerMedium() {
        try {
            Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
        } catch (error) {
            console.log('Haptic feedback not available', error);
        }
    }

    static triggerHeavy() {
        try {
            Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Heavy);
        } catch (error) {
            console.log('Haptic feedback not available', error);
        }
    }
}

export default HapticManager;
