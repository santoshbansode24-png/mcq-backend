import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    View,
    Text,
    StyleSheet,
    ScrollView,
    ActivityIndicator,
    TouchableOpacity,
    SafeAreaView,
    StatusBar,
    Platform,
    Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as Speech from 'expo-speech';
import { useTheme } from '../context/ThemeContext';
import { fetchQuickRevision } from '../api/content'; // Import from content.js for caching

const STATUSBAR_HEIGHT = Platform.OS === 'android' ? StatusBar.currentHeight : 0;

const QuickRevisionScreen = ({ navigation, route }) => {
    const { theme, isDarkMode } = useTheme();
    const { chapterId, chapterName } = route.params || {};

    const [loading, setLoading] = useState(true);
    const [revisionData, setRevisionData] = useState([]);
    const [error, setError] = useState(null);
    const [playingIndex, setPlayingIndex] = useState(null);

    // Use a ref to store the language to avoid re-fetching on every click
    const preferredLanguage = useRef('en-IN');

    /* ---------------- LIFECYCLE ---------------- */

    useEffect(() => {
        const prepareScreen = async () => {
            await loadRevision();
            await setupVoices();
        };

        prepareScreen();

        return () => {
            Speech.stop();
        };
    }, [chapterId]);

    /* ---------------- SETUP VOICES ---------------- */

    const setupVoices = async () => {
        try {
            const voices = await Speech.getVoicesAsync();
            if (voices && voices.length > 0) {
                const lang = voices.find(v => v.language.startsWith('mr'))?.language ||
                    voices.find(v => v.language.startsWith('hi'))?.language ||
                    'en-IN';
                preferredLanguage.current = lang;
            }
        } catch (e) {
            console.log('Voice setup error:', e);
        }
    };

    /* ---------------- LOAD API DATA ---------------- */

    const loadRevision = async () => {
        if (!chapterId) {
            setError('No chapter selected');
            setLoading(false);
            return;
        }

        try {
            setLoading(true);
            const response = await fetchQuickRevision(chapterId);

            if (response?.status === 'success' && response?.data?.length) {
                const points = response.data[0]?.key_points || [];
                // Removing the first point as requested by user
                setRevisionData(points.slice(1));
                setError(null);
            } else {
                setError('Revision notes not found');
            }
        } catch (e) {
            setError('Failed to load revision');
        } finally {
            setLoading(false);
        }
    };

    /* ---------------- STOP TTS ---------------- */

    const stopTTS = async () => {
        await Speech.stop();
        setPlayingIndex(null);
    };

    /* ---------------- PLAY TTS ---------------- */

    const playTTS = async (item, index) => {
        // 1. If clicking the one currently playing, stop it.
        if (playingIndex === index) {
            await stopTTS();
            return;
        }

        // 2. Identify the text to speak
        const q = item.q || item.Question || '';
        const a = item.a || item.Answer || '';
        const e = item.e || item.Explanation || ''; // Get explanation

        // Speak Q, then A, then Explanation if exists
        const textToSpeak = `${q}. ${a}. ${e ? `Explanation: ${e}` : ''}`.trim();

        if (!textToSpeak) {
            Alert.alert("Error", "No text found to read for this point.");
            return;
        }

        try {
            // 3. Always stop current speech first
            await Speech.stop();
            setPlayingIndex(null);

            // 4. Short delay allows the hardware to reset (Fixes Android "silence" bug)
            setTimeout(() => {
                setPlayingIndex(index);
                Speech.speak(textToSpeak, {
                    language: preferredLanguage.current,
                    rate: Platform.OS === 'android' ? 0.6 : 0.5, // Slower pace: 0.6 is good for Android, 0.5 for iOS
                    pitch: 1.0,
                    onDone: () => setPlayingIndex(null),
                    onStopped: () => setPlayingIndex(null),
                    onError: () => {
                        Alert.alert('TTS Error', 'Please ensure Google Speech Services is active.');
                        setPlayingIndex(null);
                    },
                });
            }, 150);

        } catch (err) {
            console.log('TTS execution error:', err);
            setPlayingIndex(null);
        }
    };

    /* ---------------- RENDER ---------------- */

    const RenderText = useCallback(
        ({ text }) => (
            <Text style={[styles.mainText, { color: theme.text }]}>
                {text}
            </Text>
        ),
        [theme]
    );

    if (loading) {
        return (
            <View style={[styles.center, { backgroundColor: isDarkMode ? '#0f172a' : '#eef2ff' }]}>
                <ActivityIndicator size="large" color={theme.primary} />
            </View>
        );
    }

    if (error) {
        return (
            <View style={[styles.center, { backgroundColor: isDarkMode ? '#0f172a' : '#eef2ff' }]}>
                <Text style={{ color: theme.text, marginBottom: 20 }}>{error}</Text>
                <TouchableOpacity onPress={loadRevision} style={{ padding: 10, backgroundColor: theme.primary, borderRadius: 8 }}>
                    <Text style={{ color: '#fff' }}>Retry</Text>
                </TouchableOpacity>
            </View>
        );
    }

    return (
        <LinearGradient
            colors={isDarkMode ? ['#0f172a', '#1e1b4b'] : ['#eef2ff', '#e0e7ff']}
            style={styles.container}
        >
            <SafeAreaView style={styles.safeArea}>
                {/* HEADER */}
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()}>
                        <Ionicons name="arrow-back" size={26} color={theme.text} />
                    </TouchableOpacity>

                    <Text style={[styles.headerTitle, { color: theme.text }]} numberOfLines={1}>
                        {chapterName || 'Quick Revision'}
                    </Text>

                    <TouchableOpacity onPress={stopTTS}>
                        <Ionicons
                            name="stop-circle"
                            size={32}
                            color={playingIndex !== null ? '#ef4444' : '#cbd5e1'}
                        />
                    </TouchableOpacity>
                </View>

                {/* CONTENT */}
                <ScrollView contentContainerStyle={styles.scrollArea} showsVerticalScrollIndicator={false}>
                    {revisionData.map((item, index) => {
                        const q = item.q || item.Question || '';
                        const a = item.a || item.Answer || '';
                        const isPlaying = playingIndex === index;

                        return (
                            <View
                                key={index}
                                style={[
                                    styles.card,
                                    { backgroundColor: isDarkMode ? '#1e293b' : '#fff' },
                                    isPlaying && { borderColor: theme.primary, borderWidth: 1 }
                                ]}
                            >
                                <View style={styles.cardHeader}>
                                    <View style={styles.pointContainer}>
                                        <Text style={styles.pointText}>POINT {index + 1}</Text>
                                    </View>

                                    <TouchableOpacity
                                        onPress={() => playTTS(item, index)}
                                        hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
                                    >
                                        <Ionicons
                                            name={isPlaying ? 'pause-circle' : 'play-circle'}
                                            size={48}
                                            color={isPlaying ? '#ef4444' : theme.primary}
                                        />
                                    </TouchableOpacity>
                                </View>

                                <View style={styles.textArea}>
                                    <Text style={[styles.label, { color: theme.primary }]}>QUESTION</Text>
                                    <RenderText text={q} />

                                    <View style={styles.line} />

                                    <Text style={[styles.label, { color: theme.primary }]}>ANSWER</Text>
                                    <RenderText text={a} />

                                    {/* Render Explanation if exists */}
                                    {(item.e || item.Explanation) && (
                                        <>
                                            <View style={[styles.line, { marginVertical: 10 }]} />
                                            <Text style={[styles.label, { color: theme.textSecondary || '#666', fontStyle: 'italic' }]}>EXPLANATION</Text>
                                            <Text style={[styles.mainText, { color: theme.textSecondary || '#555', fontSize: 14, fontStyle: 'italic' }]}>
                                                {item.e || item.Explanation}
                                            </Text>
                                        </>
                                    )}
                                </View>
                            </View>
                        );
                    })}
                </ScrollView>
            </SafeAreaView>
        </LinearGradient>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    safeArea: { flex: 1, paddingTop: STATUSBAR_HEIGHT },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 16,
        borderBottomWidth: 0.5,
        borderBottomColor: 'rgba(203, 213, 225, 0.3)',
    },
    headerTitle: {
        flex: 1,
        marginLeft: 15,
        fontSize: 18,
        fontWeight: 'bold',
    },
    scrollArea: {
        padding: 16,
        paddingBottom: 40,
    },
    card: {
        borderRadius: 20,
        padding: 18,
        marginBottom: 18,
        elevation: 4,
        shadowColor: '#000',
        shadowOpacity: 0.1,
        shadowRadius: 10,
        shadowOffset: { width: 0, height: 4 },
    },
    cardHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 12,
    },
    pointContainer: {
        backgroundColor: 'rgba(100, 116, 139, 0.1)',
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 8,
    },
    pointText: {
        fontSize: 11,
        fontWeight: 'bold',
        color: '#64748b',
    },
    textArea: { marginTop: 4 },
    label: {
        fontSize: 10,
        fontWeight: 'bold',
        marginBottom: 5,
        letterSpacing: 1,
        opacity: 0.7,
    },
    mainText: {
        fontSize: 16,
        lineHeight: 24,
        fontWeight: '500',
    },
    line: {
        height: 1,
        backgroundColor: '#e2e8f0',
        marginVertical: 15,
        opacity: 0.3,
    },
});

export default QuickRevisionScreen;