import * as Speech from 'expo-speech';
import { Platform } from 'react-native';

/**
 * Finds the best available voice for the context.
 * Prioritizes:
 * 1. Preferred language (e.g., 'mr-IN' for Marathi)
 * 2. High Quality / Network / Premium voices
 * 3. Fallback to Indian English ('en-IN')
 */
export const getBestVoice = async () => {
    try {
        const voices = await Speech.getAvailableVoicesAsync();

        if (!voices || voices.length === 0) return null;

        // Helper to check for female attributes
        const isFemale = (v) => {
            const id = v.identifier.toLowerCase();
            const name = v.name.toLowerCase();
            return id.includes('female') || name.includes('female') ||
                id.includes('woman') || name.includes('woman') ||
                // Common Indian Female TTS Names (Google/Samsung/iOS)
                name.includes('veena') || name.includes('lekha') || name.includes('sangeeta') ||
                name.includes('rani') || name.includes('shruthi') || name.includes('kavya');
        };

        // Helper to check for high quality
        const isHighQuality = (v) => {
            const id = v.identifier.toLowerCase();
            return id.includes('network') || id.includes('wavenet') || id.includes('premium') || id.includes('enhanced');
        };

        // Priority List
        const priorities = [
            { lang: 'mr-IN', gender: 'female' }, // Marathi Female
            { lang: 'hi-IN', gender: 'female' }, // Hindi Female (Sound very Indian)
            { lang: 'en-IN', gender: 'female' }, // English India Female
            { lang: 'mr-IN', gender: 'any' },    // Marathi Any
            { lang: 'hi-IN', gender: 'any' },    // Hindi Any
            { lang: 'en-IN', gender: 'any' }     // English India Any
        ];

        for (const priority of priorities) {
            const matches = voices.filter(v =>
                v.language.includes(priority.lang) &&
                (priority.gender === 'any' || isFemale(v))
            );

            if (matches.length > 0) {
                // Try to find high quality match first
                const best = matches.find(isHighQuality) || matches[0];
                console.log(`✅ Selected Voice (${priority.lang} | ${priority.gender}): ${best.name} (${best.identifier})`);
                return best.identifier;
            }
        }

        // Ultimate Fallback: Any voice with 'IN' (India)
        const fallback = voices.find(v => v.language.includes('IN'));
        if (fallback) {
            console.log("⚠️ Fallback to generic Indian voice:", fallback.identifier);
            return fallback.identifier;
        }

        console.log("⚠️ No Indian voice found. Using system default.");
        return null;

    } catch (error) {
        console.error("Error fetching voices:", error);
        return null;
    }
};
