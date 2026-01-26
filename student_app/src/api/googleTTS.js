import { Audio } from 'expo-av';
import { Alert } from 'react-native';

import { GOOGLE_API_KEY } from '../config/secrets';

// TODO: Replace with your actual Google Cloud API Key
// const GOOGLE_API_KEY = 'AIzaSyD_44YkJIddsiIF_l78-8WR2BeWUFGcuV0'; // Moved to secrets.js
const API_URL = `https://texttospeech.googleapis.com/v1/text:synthesize?key=${GOOGLE_API_KEY}`;

/**
 * Validates if the API key has been set.
 */
export const checkApiKey = () => {
    return GOOGLE_API_KEY !== 'YOUR_GOOGLE_CLOUD_API_KEY';
};

/**
 * Fetches TTS audio from Google Cloud and returns a sound object.
 * @param {string} text - The text to speak
 * @param {string} languageCode - Language code (e.g., 'en-IN', 'mr-IN', 'hi-IN')
 * @returns {Promise<Audio.Sound|null>} - The loaded sound object or null
 */
export const playGoogleTTS = async (text, languageCode = 'en-IN', speed = 0.75) => {
    try {
        if (!checkApiKey()) {
            console.warn('Google TTS: API Key not set.');
            return null;
        }

        // Auto-detect Language based on text content
        // If text contains Devanagari characters (Hindi/Marathi), prioritize Marathi voice
        const hasDevanagari = /[\u0900-\u097F]/.test(text);

        let targetLanguage = languageCode;
        if (hasDevanagari) {
            targetLanguage = 'mr-IN'; // Switch to Marathi for mixed content
            console.log("Google TTS: Detected Devanagari script, switching to mr-IN");
        }

        // Voice Selection Logic
        let name = 'en-IN-Wavenet-D'; // Default high quality English
        let ssmlGender = 'FEMALE';

        if (targetLanguage === 'mr-IN') {
            name = 'mr-IN-Wavenet-A'; // Marathi Female
        } else if (targetLanguage === 'hi-IN') {
            name = 'hi-IN-Wavenet-A'; // Hindi
        } else if (targetLanguage === 'en-IN') {
            name = 'en-IN-Wavenet-D'; // English
        }

        const body = {
            input: { text: text },
            voice: {
                languageCode: targetLanguage,
                name: name,
                ssmlGender: ssmlGender
            },
            audioConfig: {
                audioEncoding: 'MP3',
                speakingRate: speed // Use the requested speed (default 0.75)
            }
        };

        console.log(`Google TTS: requesting ${languageCode} (${name})...`);

        const response = await fetch(API_URL, {
            method: 'POST',
            body: JSON.stringify(body),
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const data = await response.json();

        if (data.error) {
            console.error('Google TTS API Error:', data.error.message);
            Alert.alert("Google TTS Error", data.error.message); // SHOW USER THE ERROR
            return null;
        }

        if (data.audioContent) {
            console.log(`Google TTS: Received ${data.audioContent.length} chars of audio data.`); // DEBUG LOG
            // Create and load the sound
            const { sound } = await Audio.Sound.createAsync(
                { uri: `data:audio/mp3;base64,${data.audioContent}` },
                { shouldPlay: true }
            );
            return sound;
        } else {
            console.error('Google TTS: No audio content received');
            Alert.alert("TTS Error", "No audio received from Google.");
            return null;
        }

    } catch (error) {
        console.error('Google TTS Request Exception:', error);
        Alert.alert("TTS Network Error", error.message);
        return null;
    }
};
