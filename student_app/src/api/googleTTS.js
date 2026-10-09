import { Audio } from 'expo-av';
import * as Speech from 'expo-speech';
import { API_URL } from './config';
import { getBestVoice } from '../utils/voiceUtils';

// In-memory high-speed cache: Map<cacheKey, base64Audio>
const audioMemoryCache = new Map();
// Set of currently active prefetch promises to prevent duplicate simultaneous fetches
const activeFetches = new Map();
// Cache server availability status: null = unknown, true = available, false = unavailable
let isServerTTSAvailable = null;

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

    // If server TTS was already detected as unavailable, don't waste network requests
    if (isServerTTSAvailable === false) {
        return false;
    }

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

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 2500);

            const response = await fetch(PROXY_URL, {
                method: 'POST',
                body: JSON.stringify(payload),
                headers: {
                    'Content-Type': 'application/json',
                    'X-Veeru-Audio-Auth': 'Veeru_Audio_Shield_2026_Secure',
                },
                signal: controller.signal,
            });
            clearTimeout(timeoutId);

            if (!response.ok) {
                isServerTTSAvailable = false;
                return false;
            }

            const data = await response.json();
            if (data?.audioContent) {
                isServerTTSAvailable = true;
                audioMemoryCache.set(cacheKey, data.audioContent);
                return true;
            } else {
                if (data?.error && data.error.includes('Google API Key not configured')) {
                    isServerTTSAvailable = false;
                }
                return false;
            }
        } catch (e) {
            // Network failure or timeout
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
 * 2. If server TTS is available, fetches from server with server-side permanent disk cache (₹0)
 * 3. Instant, reliable fallback to high-quality on-device native speech (0ms latency, native onDone tracking)
 */
export const playGoogleTTS = async (text, languageCode = 'mr-IN', speed = 0.88) => {
    if (!text || typeof text !== 'string') return null;
    const cleanText = text.trim();
    if (!cleanText) return null;

    const cacheKey = getCacheKey(cleanText, languageCode, speed);
    let audioBase64 = audioMemoryCache.get(cacheKey);

    // 1. Fetch from server if not cached and server is not disabled
    if (!audioBase64 && isServerTTSAvailable !== false) {
        try {
            const PROXY_URL = `${API_URL}/proxy_tts.php`;
            const payload = {
                text: cleanText,
                languageCode: languageCode,
                speed: speed,
            };

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 2500);

            const response = await fetch(PROXY_URL, {
                method: 'POST',
                body: JSON.stringify(payload),
                headers: {
                    'Content-Type': 'application/json',
                    'X-Veeru-Audio-Auth': 'Veeru_Audio_Shield_2026_Secure',
                },
                signal: controller.signal,
            });
            clearTimeout(timeoutId);

            if (response.ok) {
                const data = await response.json();
                if (data?.audioContent) {
                    audioBase64 = data.audioContent;
                    audioMemoryCache.set(cacheKey, audioBase64);
                    isServerTTSAvailable = true;
                } else if (data?.error) {
                    isServerTTSAvailable = false;
                }
            } else {
                isServerTTSAvailable = false;
            }
        } catch (netErr) {
            // Mark server as unavailable so subsequent cards don't stall
            if (isServerTTSAvailable === null) {
                isServerTTSAvailable = false;
            }
        }
    }

    // 2. Play Google Natural Audio if Base64 available
    if (audioBase64) {
        try {
            await Speech.stop();
            const { sound } = await Audio.Sound.createAsync(
                { uri: `data:audio/mp3;base64,${audioBase64}` },
                { shouldPlay: true }
            );
            return sound;
        } catch (audioErr) {
            console.warn('[TTS] Audio playback error, falling back to Speech:', audioErr);
        }
    }

    // 3. High-Performance On-Device Native Speech (Zero lag, works offline, native event-driven)
    try {
        const speechLang = languageCode === 'mr-IN' ? 'mr-IN' : (languageCode === 'hi-IN' ? 'hi-IN' : 'en-IN');
        await Speech.stop();

        const indianVoiceId = await getBestVoice().catch(() => null);

        let isCompleted = false;
        let onStatusCallback = null;
        let safetyTimer = null;

        const notifyDone = () => {
            if (isCompleted) return;
            isCompleted = true;
            if (safetyTimer) {
                clearTimeout(safetyTimer);
                safetyTimer = null;
            }
            if (onStatusCallback) {
                onStatusCallback({ didJustFinish: true });
            }
        };

        // Safety upper-bound timer in case device TTS engine fails to fire onDone
        const safetyDurationMs = Math.max(3500, cleanText.length * 110);
        safetyTimer = setTimeout(() => {
            notifyDone();
        }, safetyDurationMs);

        Speech.speak(cleanText, {
            language: speechLang,
            voice: indianVoiceId || undefined,
            pitch: 1.05,
            rate: speed || 0.92,
            onDone: () => {
                notifyDone();
            },
            onStopped: () => {
                isCompleted = true;
                if (safetyTimer) clearTimeout(safetyTimer);
            },
            onError: (err) => {
                console.warn('[TTS] Speech speak error:', err);
                notifyDone();
            },
        });

        return {
            setOnPlaybackStatusUpdate: (cb) => {
                onStatusCallback = cb;
            },
            stopAsync: async () => {
                isCompleted = true;
                if (safetyTimer) clearTimeout(safetyTimer);
                try {
                    await Speech.stop();
                } catch (e) {}
            },
            unloadAsync: async () => {
                isCompleted = true;
                if (safetyTimer) clearTimeout(safetyTimer);
                try {
                    await Speech.stop();
                } catch (e) {}
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
        await Speech.stop();
    } catch (e) {}
};
