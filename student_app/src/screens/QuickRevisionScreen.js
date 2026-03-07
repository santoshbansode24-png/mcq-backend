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
    RefreshControl, // Added
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { Audio } from 'expo-av'; // Added import for setAudioModeAsync
// import * as Speech from 'expo-speech'; // Removed Expo Speech
import { useTheme } from '../context/ThemeContext';
import { fetchQuickRevision } from '../api/content'; // Import from content.js for caching
import { playGoogleTTS } from '../api/googleTTS'; // Import Google TTS

const STATUSBAR_HEIGHT = Platform.OS === 'android' ? StatusBar.currentHeight : 0;

const QuickRevisionScreen = ({ navigation, route }) => {
    const { theme, isDarkMode } = useTheme();
    const { chapterId, chapterName } = route.params || {};

    const [loading, setLoading] = useState(true);
    const [revisionData, setRevisionData] = useState([]);
    const [error, setError] = useState(null);
    const [playingIndex, setPlayingIndex] = useState(null);
    const [isAutoPlaying, setIsAutoPlaying] = useState(false);
    const [sound, setSound] = useState(null);

    // Use a ref to store the language to avoid re-fetching on every click
    const preferredLanguage = useRef('en-IN');

    /* ---------------- LIFECYCLE ---------------- */

    useEffect(() => {
        const prepareScreen = async () => {
            // Configure Audio to play even in silent mode
            try {
                await Audio.setAudioModeAsync({
                    allowsRecordingIOS: false,
                    playsInSilentModeIOS: true,
                    shouldDuckAndroid: true,
                    playThroughEarpieceAndroid: false,
                    staysActiveInBackground: false,
                });
            } catch (e) {
                console.warn("Audio Mode Setup Error:", e);
            }

            await loadRevision();
            await setupVoices();
        };

        prepareScreen();

        return () => {
            if (sound) {
                sound.unloadAsync();
            }
        };
    }, [chapterId]);

    /* ---------------- SETUP VOICES ---------------- */

    const setupVoices = async () => {
        try {
            // User requested Indian Marathi accent
            preferredLanguage.current = 'mr-IN';
        } catch (e) {
            // console.log('Voice setup error:', e);
        }
    };

    /* ---------------- LOAD API DATA ---------------- */

    const loadRevision = async (forceReconnect = false) => {
        if (!chapterId) {
            setError('No chapter selected');
            setLoading(false);
            return;
        }

        if (!forceReconnect) {
            // 1. Try cache first
            try {
                const cacheKey = `quick_rev_${chapterId}`;
                const cachedData = await AsyncStorage.getItem(`@cache_${cacheKey}`);
                if (cachedData) {
                    const parsed = JSON.parse(cachedData);
                    if (Date.now() - parsed.timestamp < 24 * 60 * 60 * 1000) {
                        const points = parsed.data[0]?.key_points || [];
                        if (points.length > 0) {
                            setRevisionData(points.slice(1));
                            setLoading(false);
                            // Fresh fetch happens in background
                        }
                    }
                }
            } catch (e) {
                console.log('[QuickRevision] Cache error:', e);
            }
        }

        if (revisionData.length === 0) setLoading(true);
        setError(null);
        try {
            const response = await fetchQuickRevision(chapterId, forceReconnect);

            if (response?.status === 'success' && response?.data?.length) {
                const points = response.data[0]?.key_points || [];
                setRevisionData(points.slice(1));
            } else {
                if (revisionData.length === 0) {
                    setError('Revision notes not found');
                }
            }
        } catch (e) {
            if (revisionData.length === 0) {
                setError('Failed to load revision');
            }
        } finally {
            setLoading(false);
        }
    };

    /* ---------------- STOP TTS ---------------- */

    const stopTTS = async () => {
        if (sound) {
            await sound.stopAsync();
            await sound.unloadAsync();
            setSound(null);
        }
        setPlayingIndex(null);
        setIsAutoPlaying(false);
    };

    /* ---------------- PLAY TTS ---------------- */

    const playTTS = async (item, index, autoNext = false) => {
        if (playingIndex === index && !autoNext) {
            await stopTTS();
            return;
        }

        const q = item.q || item.Question || '';
        const a = item.a || item.Answer || '';
        const e = item.e || item.Explanation || '';

        const textToSpeak = `${q}. ${a}. ${e || ''}`.trim();

        if (!textToSpeak) return;

        try {
            await stopTTS();
            if (autoNext) setIsAutoPlaying(true);

            const newSound = await playGoogleTTS(textToSpeak, preferredLanguage.current);

            if (newSound) {
                setSound(newSound);
                setPlayingIndex(index);

                newSound.setOnPlaybackStatusUpdate((status) => {
                    if (status.didJustFinish) {
                        setPlayingIndex(null);
                        setSound(null);
                        newSound.unloadAsync();

                        if (isAutoPlaying && index + 1 < revisionData.length) {
                            playTTS(revisionData[index + 1], index + 1, true);
                        } else {
                            setIsAutoPlaying(false);
                        }
                    }
                });
            } else {
                setIsAutoPlaying(false);
                setPlayingIndex(null);
            }
        } catch (err) {
            setIsAutoPlaying(false);
            setPlayingIndex(null);
        }
    };

    const toggleAutoPlay = () => {
        if (isAutoPlaying) {
            stopTTS();
        } else {
            if (revisionData.length > 0) {
                playTTS(revisionData[0], 0, true);
            }
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
                <View style={[styles.header, { borderBottomColor: isDarkMode ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)' }]}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
                        <Ionicons name="arrow-back" size={26} color={theme.text} />
                    </TouchableOpacity>

                    <View style={styles.headerTextContainer}>
                        <Text style={[styles.headerTitle, { color: theme.text }]} numberOfLines={1}>
                            {chapterName || 'Quick Revision'}
                        </Text>
                        <Text style={[styles.headerSubtitle, { color: theme.textSecondary }]}>{revisionData.length} Key Points</Text>
                    </View>

                    <TouchableOpacity
                        onPress={toggleAutoPlay}
                        style={[styles.headerActionButton, isAutoPlaying && { backgroundColor: '#fee2e2' }]}
                    >
                        <Ionicons
                            name={isAutoPlaying ? "pause" : "volume-high"}
                            size={24}
                            color={isAutoPlaying ? '#ef4444' : theme.primary}
                        />
                    </TouchableOpacity>
                </View>

                {/* CONTENT */}
                <ScrollView
                    contentContainerStyle={styles.scrollArea}
                    showsVerticalScrollIndicator={false}
                    refreshControl={
                        <RefreshControl refreshing={loading} onRefresh={() => loadRevision(true)} colors={[theme.primary]} />
                    }
                >
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
                                    isPlaying && { borderColor: theme.primary, borderWidth: 1.5, shadowColor: theme.primary, shadowOpacity: 0.3 }
                                ]}
                            >
                                <View style={styles.cardHeader}>
                                    <View style={styles.headerLeftControls}>
                                        <TouchableOpacity
                                            onPress={() => playTTS(item, index)}
                                            style={[styles.playIconCircle, { backgroundColor: isPlaying ? theme.primary : (isDarkMode ? '#334155' : '#f1f5f9') }]}
                                            hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
                                        >
                                            <Ionicons
                                                name={isPlaying ? 'pause' : 'play'}
                                                size={20}
                                                color={isPlaying ? '#fff' : theme.primary}
                                            />
                                        </TouchableOpacity>
                                        <View style={[styles.pointBadge, { backgroundColor: isDarkMode ? 'rgba(255,255,255,0.05)' : '#f8fafc' }]}>
                                            <Text style={[styles.pointText, { color: isDarkMode ? '#94a3b8' : '#64748b' }]}>POINT {index + 1}</Text>
                                        </View>
                                    </View>
                                </View>

                                <View style={styles.textArea}>
                                    <View style={styles.sectionRow}>
                                        <View style={[styles.indicator, { backgroundColor: theme.primary }]} />
                                        <Text style={[styles.label, { color: theme.primary }]}>QUESTION</Text>
                                    </View>
                                    <RenderText text={q} />

                                    <View style={styles.separator} />

                                    <View style={styles.sectionRow}>
                                        <View style={[styles.indicator, { backgroundColor: '#10b981' }]} />
                                        <Text style={[styles.label, { color: '#10b981' }]}>ANSWER</Text>
                                    </View>
                                    <RenderText text={a} />

                                    {(item.e || item.Explanation) && (
                                        <View style={styles.explanationBox}>
                                            <View style={styles.sectionRow}>
                                                <MaterialCommunityIcons name="information-outline" size={14} color="#64748b" />
                                                <Text style={styles.explanationLabel}>EXPLANATION</Text>
                                            </View>
                                            <Text style={[styles.explanationText, { color: isDarkMode ? '#94a3b8' : '#475569' }]}>
                                                {item.e || item.Explanation}
                                            </Text>
                                        </View>
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
        paddingHorizontal: 16,
        paddingVertical: 12,
        borderBottomWidth: 1,
    },
    backBtn: {
        width: 40,
        height: 40,
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 20,
        backgroundColor: 'rgba(255,255,255,0.1)',
    },
    headerTextContainer: {
        flex: 1,
        marginLeft: 12,
    },
    headerTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    headerSubtitle: {
        fontSize: 12,
        fontFamily: 'NotoSans-Regular',
        opacity: 0.8,
    },
    headerActionButton: {
        width: 44,
        height: 44,
        borderRadius: 22,
        backgroundColor: 'rgba(255,255,255,0.8)',
        justifyContent: 'center',
        alignItems: 'center',
        elevation: 2,
        shadowColor: '#000',
        shadowOpacity: 0.1,
        shadowRadius: 4,
        shadowOffset: { width: 0, height: 2 },
    },
    scrollArea: {
        padding: 16,
        paddingBottom: 40,
    },
    card: {
        borderRadius: 24,
        padding: 20,
        marginBottom: 20,
        elevation: 6,
        shadowColor: '#000',
        shadowOpacity: 0.1,
        shadowRadius: 10,
        shadowOffset: { width: 0, height: 4 },
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.2)',
    },
    cardHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 16,
    },
    headerLeftControls: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'rgba(0,0,0,0.02)',
        padding: 4,
        borderRadius: 30,
    },
    playIconCircle: {
        width: 36,
        height: 36,
        borderRadius: 18,
        justifyContent: 'center',
        alignItems: 'center',
        elevation: 2,
    },
    pointBadge: {
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 12,
        marginLeft: 8,
    },
    pointText: {
        fontSize: 10,
        fontWeight: 'bold',
        letterSpacing: 0.5,
    },
    textArea: { marginTop: 0 },
    sectionRow: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 6,
    },
    indicator: {
        width: 4,
        height: 12,
        borderRadius: 2,
        marginRight: 8,
    },
    label: {
        fontSize: 10,
        fontWeight: 'bold',
        letterSpacing: 1,
        fontFamily: 'NotoSans-Bold',
    },
    mainText: {
        fontSize: 16,
        lineHeight: 24,
        fontWeight: '600',
        fontFamily: 'NotoSans-Bold',
        paddingLeft: 12,
    },
    separator: {
        height: 1,
        backgroundColor: 'rgba(0,0,0,0.05)',
        marginVertical: 16,
        width: '100%',
    },
    explanationBox: {
        marginTop: 20,
        padding: 15,
        backgroundColor: 'rgba(100, 116, 139, 0.05)',
        borderRadius: 16,
        borderWidth: 1,
        borderColor: 'rgba(100, 116, 139, 0.1)',
        borderStyle: 'dashed',
    },
    explanationLabel: {
        fontSize: 9,
        fontWeight: 'bold',
        color: '#64748b',
        marginLeft: 6,
        letterSpacing: 0.5,
    },
    explanationText: {
        fontSize: 13,
        lineHeight: 20,
        fontStyle: 'italic',
        marginTop: 6,
        fontFamily: 'NotoSans-Regular',
    },
});

export default QuickRevisionScreen;