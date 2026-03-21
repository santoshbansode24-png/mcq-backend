import { Audio } from 'expo-av';
import { Alert } from 'react-native';

import { API_URL } from './config';

/**
 * Validates if the TTS endpoint is accessible.
 */
export const checkApiKey = () => {
    return true; // We now use the backend proxy
};

export const playGoogleTTS = async (text, languageCode = 'en-IN', speed = 0.75) => {
    try {
        const payload = {
            text: text,
            languageCode: languageCode,
            speed: speed
        };

        const PROXY_URL = `${API_URL}/proxy_tts.php`;
        console.log(`TTS: Fetching from ${PROXY_URL}...`);

        const response = await fetch(PROXY_URL, {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const data = await response.json();

        if (data.error) {
            console.error('TTS Proxy Error:', data.error);
            Alert.alert("TTS Error", data.error);
            return null;
        }

        if (data.audioContent) {
            console.log(`TTS: Success, loading audio...`);
            const { sound } = await Audio.Sound.createAsync(
                { uri: `data:audio/mp3;base64,${data.audioContent}` },
                { shouldPlay: true }
            );
            return sound;
        } else {
            console.error('TTS: No audio content received');
            return null;
        }

    } catch (error) {
        console.error('TTS Request Exception:', error);
        Alert.alert("TTS Error", "Unable to connect to the audio server.");
        return null;
    }
};
