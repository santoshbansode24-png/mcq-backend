import { Audio } from 'expo-av';
import * as Speech from 'expo-speech';
import { API_URL } from './config';

// In-memory high-speed cache: Map<cacheKey, base64Audio>
const audioMemoryCache = new Map();
// Set of currently active prefetch promises to prevent duplicate simultaneous fetches
const activeFetches = new Map();

/**
 * Generates unique cache key for a given text, language and speed
 */
const getCacheKey = (text, languageCode = 'mr-IN', speed = 0.88) => {
    return `${languageCode}_${speed.toFixed(2)}_${text.trim()}`;
};

/**
 * Checks if audio is already available in the memory cache
 */
export const isAudioCached = (text, languageCode = 'mr-IN', speed = 0.88) => {
    if (!text) return false;
    const key = getCacheKey(text, languageCode, speed);
    return audioMemoryCache.has(key);
};

/**
 * Pre-fetches audio in the background and saves into memory cache.
 * Completely non-blocking and silent.
 */
export const prefetchGoogleTTS = async (text, languageCode = 'mr-IN', speed = 0.88) => {
    if (!text || typeof text !== 'string') return false;
    const cleanText = text.trim();
    if (!cleanText) return false;

    const cacheKey = getCacheKey(cleanText, languageCode, speed);

    // If already in memory cache, nothing to do!
    if (audioMemoryCache.has(cacheKey)) {
        return true;
    }

    // If already being fetched, wait for existing promise
    if (activeFetches.has(cacheKey)) {
        return activeFetches.get(cacheKey);
    }

    const fetchPromise = (async () => {
        try {
            const PROXY_URL = `${API_URL}/proxy_tts.php`;
            const payload = {
                text: cleanText,
                languageCode: languageCode,
                speed: speed,
            };

            const response = await fetch(PROXY_URL, {
                method: 'POST',
                body: JSON.stringify(payload),
                headers: {
                    'Content-Type': 'application/json',
                    'X-Veeru-Audio-Auth': 'Veeru_Audio_Shield_2026_Secure',
                },
            });

            const data = await response.json();
            if (data?.audioContent) {
                audioMemoryCache.set(cacheKey, data.audioContent);
                return true;
            }
            return false;
        } catch (e) {
            // Pre-fetch failure is non-fatal
            return false;
        } finally {
            activeFetches.delete(cacheKey);
        }
    })();

    activeFetches.set(cacheKey, fetchPromise);
    return fetchPromise;
};

/**
 * Plays TTS audio with Zero-Lag:
 * 1. Uses pre-fetched audio instantly if available
 * 2. If not, fetches from server with server-side permanent disk cache (₹0)
 * 3. Fallback to high-quality on-device native speech if offline
 */
export const playGoogleTTS = async (text, languageCode = 'mr-IN', speed = 0.88) => {
    if (!text || typeof text !== 'string') return null;
    const cleanText = text.trim();
    if (!cleanText) return null;

    const cacheKey = getCacheKey(cleanText, languageCode, speed);
    let audioBase64 = audioMemoryCache.get(cacheKey);

    // 1. Fetch from server if not in local cache
    if (!audioBase64) {
        try {
            const PROXY_URL = `${API_URL}/proxy_tts.php`;
            const payload = {
                text: cleanText,
                languageCode: languageCode,
                speed: speed,
            };

            const response = await fetch(PROXY_URL, {
                method: 'POST',
                body: JSON.stringify(payload),
                headers: {
                    'Content-Type': 'application/json',
                    'X-Veeru-Audio-Auth': 'Veeru_Audio_Shield_2026_Secure',
                },
            });

            const data = await response.json();
            if (data?.audioContent) {
                audioBase64 = data.audioContent;
                audioMemoryCache.set(cacheKey, audioBase64);
            }
        } catch (netErr) {
            console.log('[TTS] Server request failed, falling back to on-device Speech:', netErr);
        }
    }

    // 2. Play Google Natural Audio if Base64 available
    if (audioBase64) {
        try {
            const { sound } = await Audio.Sound.createAsync(
                { uri: `data:audio/mp3;base64,${audioBase64}` },
                { shouldPlay: true }
            );
            return sound;
        } catch (audioErr) {
            console.warn('[TTS] Audio playback error, falling back to Speech:', audioErr);
        }
    }

    // 3. Fallback: On-Device Native Speech (Zero cost, works offline)
    try {
        const speechLang = languageCode === 'mr-IN' ? 'mr-IN' : (languageCode === 'hi-IN' ? 'hi-IN' : 'en-IN');
        Speech.stop();
        Speech.speak(cleanText, {
            language: speechLang,
            pitch: 1.0,
            rate: speed || 0.9,
        });

        // Return a mock sound object matching expo-av interface for playback completion
        const estimatedDurationMs = Math.max(2000, cleanText.length * 75);
        let onStatusCallback = null;
        const timer = setTimeout(() => {
            if (onStatusCallback) {
                onStatusCallback({ didJustFinish: true });
            }
        }, estimatedDurationMs);

        return {
            setOnPlaybackStatusUpdate: (cb) => {
                onStatusCallback = cb;
            },
            stopAsync: async () => {
                clearTimeout(timer);
                Speech.stop();
            },
            unloadAsync: async () => {
                clearTimeout(timer);
                Speech.stop();
            },
        };
    } catch (speechErr) {
        console.error('[TTS] Speech fallback failed:', speechErr);
        return null;
    }
};

/**
 * Stop any ongoing on-device speech
 */
export const stopAllTTS = async () => {
    try {
        Speech.stop();
    } catch (e) {}
};
