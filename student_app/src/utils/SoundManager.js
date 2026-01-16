import { Audio } from 'expo-av';

const SOUNDS = {
    correct: 'https://assets.mixkit.co/active_storage/sfx/2000/2000-preview.mp3',
    wrong: 'https://assets.mixkit.co/active_storage/sfx/2003/2003-preview.mp3',
    gameOver: 'https://assets.mixkit.co/active_storage/sfx/1435/1435-preview.mp3',
};

class SoundManager {
    static async playSound(type) {
        try {
            const { sound } = await Audio.Sound.createAsync(
                { uri: SOUNDS[type] },
                { shouldPlay: true }
            );

            // Unload sound from memory when finished
            sound.setOnPlaybackStatusUpdate(async (status) => {
                if (status.didJustFinish) {
                    await sound.unloadAsync();
                }
            });
        } catch (error) {
            console.log('Error playing sound', error);
        }
    }
}

export default SoundManager;
