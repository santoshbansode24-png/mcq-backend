import { Audio } from 'expo-av';
import * as Speech from 'expo-speech';
import * as FileSystem from 'expo-file-system';
import { API_URL } from './config';
import { getBestVoice } from '../utils/voiceUtils';

// In-memory high-speed cache: Map<cacheKey, base64Audio>
const audioMemoryCache = new Map();
// Local file URI cache: Map<cacheKey, localFileUri>
const audioFileUriCache = new Map();
// Set of currently active prefetch promises to prevent duplicate simultaneous fetches
const activeFetches = new Map();
// Server TTS availability status: null = unknown, true = available, false = unavailable
let isServerTTSAvailable = null;

/**
 * Generates unique cache key for a given text, language, speaker and speed
 */
const getCacheKey = (text, languageCode = 'mr-IN', speed = 0.92, speaker = 'rupali') => {
    return `${languageCode}_${speaker}_${speed.toFixed(2)}_${text.trim()}`;
};

/**
 * Generates a clean alphanumeric local filename in Expo cache
 */
const getLocalFileUri = (cacheKey) => {
    let hash = 0;
    for (let i = 0; i < cacheKey.length; i++) {
        hash = ((hash << 5) - hash) + cacheKey.charCodeAt(i);
        hash |= 0;
    }
    const safeHash = Math.abs(hash).toString(36);
    return `${FileSystem.cacheDirectory}tts_${safeHash}.mp3`;
};

/**
 * Saves Base64 audio string to a real local MP3 file for Android MediaPlayer compatibility
 */
const saveBase64ToFile = async (cacheKey, base64Data) => {
    try {
        const fileUri = getLocalFileUri(cacheKey);
        const info = await FileSystem.getInfoAsync(fileUri);
        if (!info.exists || info.size === 0) {
            await FileSystem.writeAsStringAsync(fileUri, base64Data, {
                encoding: FileSystem.EncodingType.Base64,
            });
        }
        audioFileUriCache.set(cacheKey, fileUri);
        return fileUri;
    } catch (e) {
        console.warn('[TTS] Failed to cache audio to file system:', e);
        return null;
    }
};

/**
 * Checks if audio is already available in the memory/file cache
 */
export const isAudioCached = (text, languageCode = 'mr-IN', speed = 0.92, speaker = 'rupali') => {
    if (!text) return false;
    const key = getCacheKey(text, languageCode, speed, speaker);
    return audioMemoryCache.has(key) || audioFileUriCache.has(key);
};

/**
 * Pre-fetches audio in the background and saves into file cache for instant 0ms playback
 */
export const prefetchGoogleTTS = async (text, languageCode = 'mr-IN', speed = 0.92, speaker = 'rupali') => {
    if (!text || typeof text !== 'string') return false;
    const cleanText = text.trim();
    if (!cleanText) return false;

    if (isServerTTSAvailable === false) {
        return false;
    }

    const cacheKey = getCacheKey(cleanText, languageCode, speed, speaker);

    if (audioFileUriCache.has(cacheKey)) {
        return true;
    }

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
                speaker: speaker,
            };

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);

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

            if (!response.ok) return false;

            const data = await response.json();
            if (data?.audioContent) {
                isServerTTSAvailable = true;
                audioMemoryCache.set(cacheKey, data.audioContent);
                await saveBase64ToFile(cacheKey, data.audioContent);
                return true;
            } else if (data?.audioUrl) {
                isServerTTSAvailable = true;
                audioFileUriCache.set(cacheKey, data.audioUrl);
                return true;
            } else {
                if (data?.error && (data.error.includes('No TTS API') || data.error.includes('not configured'))) {
                    isServerTTSAvailable = false;
                }
                return false;
            }
        } catch (e) {
            return false;
        } finally {
            activeFetches.delete(cacheKey);
        }
    })();

    activeFetches.set(cacheKey, fetchPromise);
    return fetchPromise;
};

/**
 * Plays ultra-realistic Sarvam AI TTS audio with 0ms latency:
 * 1. Plays from local cached MP3 file on Android (ExoPlayer/MediaPlayer compatible)
 * 2. If not cached, fetches from Railway server disk cache
 * 3. Graceful fallback to on-device speech only in extreme network outage
 */
export const playGoogleTTS = async (text, languageCode = 'mr-IN', speed = 0.92, speaker = 'rupali') => {
    if (!text || typeof text !== 'string') return null;
    const cleanText = text.trim();
    if (!cleanText) return null;

    const cacheKey = getCacheKey(cleanText, languageCode, speed, speaker);
    let playUri = audioFileUriCache.get(cacheKey);
    let audioBase64 = audioMemoryCache.get(cacheKey);

    // 1. Fetch from server if not cached
    if (!playUri && !audioBase64 && isServerTTSAvailable !== false) {
        try {
            const PROXY_URL = `${API_URL}/proxy_tts.php`;
            const payload = {
                text: cleanText,
                languageCode: languageCode,
                speed: speed,
                speaker: speaker,
            };

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);

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
                    playUri = await saveBase64ToFile(cacheKey, audioBase64);
                } else if (data?.audioUrl) {
                    playUri = data.audioUrl;
                    audioFileUriCache.set(cacheKey, playUri);
                    isServerTTSAvailable = true;
                } else if (data?.error && (data.error.includes('No TTS API') || data.error.includes('not configured'))) {
                    isServerTTSAvailable = false;
                }
            }
        } catch (netErr) {
            console.warn('[TTS] Network timeout, checking local fallback');
        }
    }

    // If we have base64 but not saved to file yet, save to file now
    if (!playUri && audioBase64) {
        playUri = await saveBase64ToFile(cacheKey, audioBase64);
    }

    // 2. Play Server Natural Audio via local file URI (100% Android compatible)
    if (playUri) {
        try {
            await Speech.stop();
            const { sound } = await Audio.Sound.createAsync(
                { uri: playUri },
                { shouldPlay: true }
            );
            return sound;
        } catch (audioErr) {
            console.warn('[TTS] Error playing file URI:', audioErr);
        }
    }

    // 3. Fallback: High-Quality On-Device Speech
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

        const safetyDurationMs = Math.max(3500, cleanText.length * 110);
        safetyTimer = setTimeout(() => {
            notifyDone();
        }, safetyDurationMs);

        Speech.speak(cleanText, {
            language: speechLang,
            voice: indianVoiceId || undefined,
            pitch: 1.05,
            rate: speed || 0.92,
            onDone: () => notifyDone(),
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
                await Speech.stop();
            },
            unloadAsync: async () => {
                isCompleted = true;
                if (safetyTimer) clearTimeout(safetyTimer);
                await Speech.stop();
            },
            getStatusAsync: async () => ({ isPlaying: !isCompleted, isLoaded: true }),
        };
    } catch (err) {
        console.warn('[TTS] Native speech failed:', err);
        return null;
    }
};

/**
 * Stops all currently playing audio
 */
export const stopAllTTS = async () => {
    try {
        await Speech.stop();
    } catch (e) {}
};
