import React, { useState, useEffect, useRef } from 'react';
import {
    View,
    Text,
    StyleSheet,
    ActivityIndicator,
    TouchableOpacity,
    SafeAreaView,
    StatusBar,
    Platform,
    FlatList,
    ScrollView,
    Dimensions,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { Audio } from 'expo-av';
import { useTheme } from '../context/ThemeContext';
import { useLanguage } from '../context/LanguageContext';
import { fetchQuickRevision } from '../api/content';
import { playGoogleTTS, prefetchGoogleTTS, stopAllTTS } from '../api/googleTTS';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');
const STATUSBAR_HEIGHT = Platform.OS === 'android' ? StatusBar.currentHeight : 0;

const decodeHtml = (text) => {
    if (!text || typeof text !== 'string') return '';
    return text
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&nbsp;/g, ' ');
};

const QuickRevisionScreen = ({ navigation, route }) => {
    const { theme, isDarkMode } = useTheme();
    const { language = 'mr' } = useLanguage ? useLanguage() : {};
    const { chapterId, chapterName, revisionData: initialData } = route.params || {};

    const [loading, setLoading] = useState(!initialData || initialData.length === 0);
    const [revisionData, setRevisionData] = useState([]);
    const [error, setError] = useState(null);
    const [currentIndex, setCurrentIndex] = useState(0);
    const [playingIndex, setPlayingIndex] = useState(null);
    const [isAutoPlaying, setIsAutoPlaying] = useState(false);
    const [sound, setSound] = useState(null);
    const [containerHeight, setContainerHeight] = useState(SCREEN_HEIGHT - 120);

    const flatListRef = useRef(null);
    const soundRef = useRef(null);
    const preferredLanguage = useRef(language === 'hi' ? 'hi-IN' : (language === 'en' ? 'en-IN' : 'mr-IN'));
    const isAutoPlayingRef = useRef(false);
    const revisionDataRef = useRef([]);

    // Keep preferredLanguage synced with user's selected language
    useEffect(() => {
        preferredLanguage.current = language === 'hi' ? 'hi-IN' : (language === 'en' ? 'en-IN' : 'mr-IN');
    }, [language]);

    // Keep refs in sync for audio callbacks
    useEffect(() => {
        isAutoPlayingRef.current = isAutoPlaying;
    }, [isAutoPlaying]);

    useEffect(() => {
        revisionDataRef.current = revisionData;
    }, [revisionData]);

    /* ---------------- LIFECYCLE ---------------- */
    useEffect(() => {
        const prepareScreen = async () => {
            try {
                await Audio.setAudioModeAsync({
                    allowsRecordingIOS: false,
                    playsInSilentModeIOS: true,
                    shouldDuckAndroid: true,
                    playThroughEarpieceAndroid: false,
                    staysActiveInBackground: false,
                });
            } catch (e) {
                console.warn('Audio Mode Setup Error:', e);
            }

            // If initialData provided from previous screen, use immediately
            if (Array.isArray(initialData) && initialData.length > 0) {
                const valid = cleanPoints(initialData);
                if (valid.length > 0) {
                    setRevisionData(valid);
                    prefetchUpcoming(0, valid);
                    setLoading(false);
                }
            }

            await loadRevision();
        };

        prepareScreen();

        return () => {
            if (soundRef.current) {
                soundRef.current.unloadAsync().catch(() => {});
                soundRef.current = null;
            }
            stopAllTTS();
        };
    }, [chapterId]);

    /* ---------------- LOAD API DATA ---------------- */
    const loadRevision = async (forceReconnect = false) => {
        if (!chapterId) {
            setError('No chapter selected');
            setLoading(false);
            return;
        }

        if (!forceReconnect) {
            try {
                const cacheKey = `quick_rev_${chapterId}_${language}`;
                const cachedData = await AsyncStorage.getItem(`@cache_${cacheKey}`);
                if (cachedData) {
                    const parsed = JSON.parse(cachedData);
                    if (Date.now() - parsed.timestamp < 24 * 60 * 60 * 1000) {
                        const rawPoints = parsed.data?.[0]?.key_points || [];
                        const validPoints = cleanPoints(rawPoints);
                        if (validPoints.length > 0) {
                            setRevisionData(validPoints);
                            prefetchUpcoming(0, validPoints);
                            setLoading(false);
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
            const response = await fetchQuickRevision(chapterId, forceReconnect, language);
            if (response?.status === 'success' && response?.data?.length) {
                const rawPoints = response.data[0]?.key_points || [];
                const validPoints = cleanPoints(rawPoints);
                if (validPoints.length > 0) {
                    setRevisionData(validPoints);
                    prefetchUpcoming(0, validPoints);
                } else {
                    if (revisionData.length === 0) setError('No revision points found');
                }
            } else {
                if (revisionData.length === 0) setError('Revision notes not found');
            }
        } catch (e) {
            if (revisionData.length === 0) setError('Failed to load revision');
        } finally {
            setLoading(false);
        }
    };

    const cleanPoints = (rawPoints) => {
        if (!Array.isArray(rawPoints)) return [];
        let items = [...rawPoints];
        // If first element is header row like Question / Answer, skip it
        if (
            items.length > 0 &&
            items[0]?.q?.toString().trim().toLowerCase() === 'question' &&
            items[0]?.a?.toString().trim().toLowerCase() === 'answer'
        ) {
            items = items.slice(1);
        }
        return items.filter((item) => (item.q || item.Question || item.a || item.Answer));
    };

    /* ---------------- TEXT FORMATTER & PRE-FETCH ENGINE ---------------- */
    const getTextToSpeak = (item) => {
        if (!item) return '';
        const q = decodeHtml(item.q || item.Question || '');
        const a = decodeHtml(item.a || item.Answer || '');
        const e = decodeHtml(item.e || item.Explanation || '');
        return `${q}. ${a}. ${e ? 'स्पष्टीकरण: ' + e : ''}`.trim();
    };

    const prefetchUpcoming = (startIndex, list = revisionData) => {
        if (!Array.isArray(list) || list.length === 0) return;
        for (let i = startIndex; i <= startIndex + 2 && i < list.length; i++) {
            const text = getTextToSpeak(list[i]);
            if (text) {
                prefetchGoogleTTS(text, preferredLanguage.current).catch(() => {});
            }
        }
    };

    /* ---------------- STOP TTS ---------------- */
    const stopTTS = async () => {
        if (soundRef.current) {
            try {
                await soundRef.current.stopAsync();
                await soundRef.current.unloadAsync();
            } catch (e) {}
            soundRef.current = null;
        }
        setSound(null);
        stopAllTTS();
        setPlayingIndex(null);
    };

    /* ---------------- PLAY TTS ---------------- */
    const playTTS = async (item, index, autoNext = false) => {
        if (playingIndex === index && !autoNext) {
            await stopTTS();
            return;
        }

        const textToSpeak = getTextToSpeak(item);
        if (!textToSpeak) return;

        // Pre-fetch next 2 points while current point starts playing (Zero-Lag Engine)
        prefetchUpcoming(index + 1, revisionDataRef.current);

        try {
            await stopTTS();
            setPlayingIndex(index);

            const newSound = await playGoogleTTS(textToSpeak, preferredLanguage.current);

            if (newSound) {
                soundRef.current = newSound;
                setSound(newSound);

                newSound.setOnPlaybackStatusUpdate((status) => {
                    if (status.didJustFinish) {
                        setPlayingIndex(null);
                        setSound(null);
                        soundRef.current = null;
                        newSound.unloadAsync().catch(() => {});

                        // Auto-advance to next card if auto-play is on
                        if (isAutoPlayingRef.current) {
                            const nextIndex = index + 1;
                            const total = revisionDataRef.current.length;
                            if (nextIndex < total) {
                                scrollToIndex(nextIndex);
                                playTTS(revisionDataRef.current[nextIndex], nextIndex, true);
                            } else {
                                setIsAutoPlaying(false);
                            }
                        }
                    }
                });
            } else {
                setPlayingIndex(null);
            }
        } catch (err) {
            setPlayingIndex(null);
        }
    };

    const toggleAutoPlay = () => {
        if (isAutoPlaying) {
            setIsAutoPlaying(false);
            stopTTS();
        } else {
            setIsAutoPlaying(true);
            if (revisionData.length > 0) {
                playTTS(revisionData[currentIndex], currentIndex, true);
            }
        }
    };

    const scrollToIndex = (index) => {
        if (index >= 0 && index < revisionData.length) {
            flatListRef.current?.scrollToIndex({ index, animated: true });
            setCurrentIndex(index);
            prefetchUpcoming(index + 1, revisionData);
        }
    };

    const handleMomentumScrollEnd = (e) => {
        const offsetY = e.nativeEvent.contentOffset.y;
        const newIndex = Math.round(offsetY / containerHeight);
        if (newIndex !== currentIndex && newIndex >= 0 && newIndex < revisionData.length) {
            setCurrentIndex(newIndex);
            prefetchUpcoming(newIndex + 1, revisionData);
            if (isAutoPlaying) {
                playTTS(revisionData[newIndex], newIndex, true);
            } else if (playingIndex !== null) {
                stopTTS();
            }
        }
    };

    /* ---------------- RENDER CARD ---------------- */
    const renderCard = ({ item, index }) => {
        const q = decodeHtml(item.q || item.Question || '');
        const a = decodeHtml(item.a || item.Answer || '');
        const exp = decodeHtml(item.e || item.Explanation || '');
        const isPlaying = playingIndex === index;

        return (
            <View style={[styles.cardWrapper, { height: containerHeight }]}>
                <View
                    style={[
                        styles.cardBody,
                        {
                            backgroundColor: isDarkMode ? '#1e293b' : '#ffffff',
                            borderColor: isPlaying ? theme.primary : (isDarkMode ? 'rgba(255,255,255,0.08)' : 'rgba(99,102,241,0.12)'),
                            shadowColor: isPlaying ? theme.primary : '#000',
                        },
                    ]}
                >
                    {/* CARD TOP META */}
                    <View style={styles.cardMetaRow}>
                        <View style={[styles.badgePill, { backgroundColor: isDarkMode ? 'rgba(99,102,241,0.15)' : '#e0e7ff' }]}>
                            <MaterialCommunityIcons name="flash-outline" size={14} color={theme.primary} />
                            <Text style={[styles.badgeText, { color: theme.primary }]}>
                                POINT {index + 1} OF {revisionData.length}
                            </Text>
                        </View>

                        <TouchableOpacity
                            onPress={() => playTTS(item, index)}
                            style={[
                                styles.audioPillBtn,
                                {
                                    backgroundColor: isPlaying ? theme.primary : (isDarkMode ? '#334155' : '#f1f5f9'),
                                },
                            ]}
                            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                        >
                            <Ionicons
                                name={isPlaying ? 'pause' : 'volume-medium'}
                                size={18}
                                color={isPlaying ? '#fff' : theme.primary}
                            />
                            <Text
                                style={[
                                    styles.audioPillText,
                                    { color: isPlaying ? '#fff' : (isDarkMode ? '#cbd5e1' : '#475569') },
                                ]}
                            >
                                {isPlaying ? 'Playing' : 'Listen'}
                            </Text>
                        </TouchableOpacity>
                    </View>

                    {/* SCROLLABLE INNER CONTENT */}
                    <ScrollView
                        style={styles.cardScrollContent}
                        showsVerticalScrollIndicator={false}
                        nestedScrollEnabled={true}
                    >
                        {/* QUESTION */}
                        <View style={styles.sectionHeader}>
                            <View style={[styles.sectionDot, { backgroundColor: theme.primary }]} />
                            <Text style={[styles.sectionLabel, { color: theme.primary }]}>QUESTION</Text>
                        </View>
                        <Text style={[styles.questionText, { color: theme.text }]}>{q}</Text>

                        {/* DIVIDER */}
                        <View
                            style={[
                                styles.cardDivider,
                                { backgroundColor: isDarkMode ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)' },
                            ]}
                        />

                        {/* ANSWER */}
                        <View style={styles.sectionHeader}>
                            <View style={[styles.sectionDot, { backgroundColor: '#10b981' }]} />
                            <Text style={[styles.sectionLabel, { color: '#10b981' }]}>KEY ANSWER</Text>
                        </View>
                        <Text style={[styles.answerText, { color: isDarkMode ? '#f1f5f9' : '#0f172a' }]}>{a}</Text>

                        {/* EXPLANATION / MEMORY HOOK */}
                        {exp ? (
                            <View
                                style={[
                                    styles.memoryBox,
                                    {
                                        backgroundColor: isDarkMode ? 'rgba(99,102,241,0.08)' : '#f8faff',
                                        borderColor: isDarkMode ? 'rgba(99,102,241,0.2)' : '#c7d2fe',
                                    },
                                ]}
                            >
                                <View style={styles.memoryHeader}>
                                    <Ionicons name="bulb-outline" size={16} color="#6366f1" />
                                    <Text style={styles.memoryTitle}>EXPLANATION / MEMORY HOOK</Text>
                                </View>
                                <Text
                                    style={[
                                        styles.memoryText,
                                        { color: isDarkMode ? '#cbd5e1' : '#334155' },
                                    ]}
                                >
                                    {exp}
                                </Text>
                            </View>
                        ) : null}
                    </ScrollView>

                    {/* CARD FOOTER / SWIPE HINT */}
                    <View style={styles.cardFooter}>
                        <TouchableOpacity
                            disabled={index === 0}
                            onPress={() => scrollToIndex(index - 1)}
                            style={[styles.navArrowBtn, index === 0 && { opacity: 0.3 }]}
                        >
                            <Ionicons name="chevron-up" size={20} color={isDarkMode ? '#94a3b8' : '#64748b'} />
                        </TouchableOpacity>

                        <View style={styles.swipeHintContainer}>
                            <Text style={[styles.swipeHintText, { color: isDarkMode ? '#64748b' : '#94a3b8' }]}>
                                {index + 1 === revisionData.length ? '🎉 All Points Completed' : 'Swipe up for next point 👆'}
                            </Text>
                        </View>

                        <TouchableOpacity
                            disabled={index + 1 === revisionData.length}
                            onPress={() => scrollToIndex(index + 1)}
                            style={[styles.navArrowBtn, index + 1 === revisionData.length && { opacity: 0.3 }]}
                        >
                            <Ionicons name="chevron-down" size={20} color={isDarkMode ? '#94a3b8' : '#64748b'} />
                        </TouchableOpacity>
                    </View>
                </View>
            </View>
        );
    };

    /* ---------------- RENDER MAIN ---------------- */
    if (loading) {
        return (
            <View style={[styles.center, { backgroundColor: isDarkMode ? '#0f172a' : '#eef2ff' }]}>
                <ActivityIndicator size="large" color={theme.primary} />
                <Text style={[styles.loadingText, { color: theme.textSecondary }]}>Loading Quick Revision...</Text>
            </View>
        );
    }

    if (error) {
        return (
            <View style={[styles.center, { backgroundColor: isDarkMode ? '#0f172a' : '#eef2ff' }]}>
                <MaterialCommunityIcons name="alert-circle-outline" size={48} color="#ef4444" style={{ marginBottom: 12 }} />
                <Text style={{ color: theme.text, fontSize: 16, marginBottom: 20, textAlign: 'center', paddingHorizontal: 30 }}>
                    {error}
                </Text>
                <TouchableOpacity onPress={() => loadRevision(true)} style={[styles.retryBtn, { backgroundColor: theme.primary }]}>
                    <Text style={{ color: '#fff', fontWeight: 'bold' }}>Retry</Text>
                </TouchableOpacity>
            </View>
        );
    }

    return (
        <LinearGradient
            colors={isDarkMode ? ['#0f172a', '#1e1b4b'] : ['#f1f5f9', '#e0e7ff']}
            style={styles.container}
        >
            <SafeAreaView style={styles.safeArea}>
                {/* TOP HEADER */}
                <View style={[styles.header, { borderBottomColor: isDarkMode ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)' }]}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
                        <Ionicons name="arrow-back" size={24} color={theme.text} />
                    </TouchableOpacity>

                    <View style={styles.headerTitleWrap}>
                        <Text style={[styles.headerTitle, { color: theme.text }]} numberOfLines={1}>
                            {chapterName || 'Quick Revision'}
                        </Text>
                        <Text style={[styles.headerSubtitle, { color: theme.textSecondary }]}>
                            Vertical Swipe Cards ({revisionData.length} Points)
                        </Text>
                    </View>

                    <TouchableOpacity
                        onPress={toggleAutoPlay}
                        style={[
                            styles.autoPlayBtn,
                            isAutoPlaying && { backgroundColor: '#fee2e2', borderColor: '#ef4444' },
                        ]}
                    >
                        <Ionicons
                            name={isAutoPlaying ? 'pause' : 'play-skip-forward'}
                            size={18}
                            color={isAutoPlaying ? '#ef4444' : theme.primary}
                        />
                        <Text
                            style={[
                                styles.autoPlayBtnText,
                                { color: isAutoPlaying ? '#ef4444' : theme.primary },
                            ]}
                        >
                            {isAutoPlaying ? 'Auto: ON' : 'Auto'}
                        </Text>
                    </TouchableOpacity>
                </View>

                {/* PROGRESS STORY BAR (INSTAGRAM / SHORTS STYLE) */}
                <View style={styles.progressBarWrapper}>
                    <View
                        style={[
                            styles.progressBarFill,
                            {
                                width: `${((currentIndex + 1) / Math.max(revisionData.length, 1)) * 100}%`,
                                backgroundColor: theme.primary,
                            },
                        ]}
                    />
                </View>

                {/* VERTICAL SWIPE FLATLIST */}
                <View
                    style={styles.listContainer}
                    onLayout={(e) => {
                        const h = e.nativeEvent.layout.height;
                        if (h > 0) setContainerHeight(h);
                    }}
                >
                    <FlatList
                        ref={flatListRef}
                        data={revisionData}
                        keyExtractor={(_, index) => index.toString()}
                        renderItem={renderCard}
                        pagingEnabled={Platform.OS === 'ios'}
                        snapToInterval={containerHeight}
                        snapToAlignment="start"
                        decelerationRate="fast"
                        showsVerticalScrollIndicator={false}
                        onMomentumScrollEnd={handleMomentumScrollEnd}
                        onScrollToIndexFailed={(info) => {
                            setTimeout(() => {
                                flatListRef.current?.scrollToIndex({ index: info.index, animated: true });
                            }, 100);
                        }}
                        getItemLayout={(_, index) => ({
                            length: containerHeight,
                            offset: containerHeight * index,
                            index,
                        })}
                    />
                </View>
            </SafeAreaView>
        </LinearGradient>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    safeArea: { flex: 1, paddingTop: STATUSBAR_HEIGHT },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    loadingText: { marginTop: 12, fontSize: 14, fontWeight: '500' },
    retryBtn: {
        paddingHorizontal: 24,
        paddingVertical: 12,
        borderRadius: 24,
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingVertical: 10,
        borderBottomWidth: 1,
    },
    backBtn: {
        width: 38,
        height: 38,
        borderRadius: 19,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: 'rgba(255,255,255,0.12)',
    },
    headerTitleWrap: {
        flex: 1,
        marginLeft: 12,
    },
    headerTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    headerSubtitle: {
        fontSize: 12,
        fontFamily: 'NotoSans-Regular',
        marginTop: 2,
    },
    autoPlayBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 18,
        backgroundColor: 'rgba(255,255,255,0.85)',
        borderWidth: 1,
        borderColor: 'rgba(99,102,241,0.2)',
    },
    autoPlayBtnText: {
        fontSize: 12,
        fontWeight: '700',
        marginLeft: 4,
    },
    progressBarWrapper: {
        height: 4,
        width: '100%',
        backgroundColor: 'rgba(0,0,0,0.06)',
    },
    progressBarFill: {
        height: 4,
        borderRadius: 2,
    },
    listContainer: {
        flex: 1,
    },
    cardWrapper: {
        width: SCREEN_WIDTH,
        paddingHorizontal: 16,
        paddingVertical: 12,
        justifyContent: 'center',
    },
    cardBody: {
        flex: 1,
        borderRadius: 24,
        padding: 20,
        borderWidth: 1.5,
        elevation: 6,
        shadowOpacity: 0.12,
        shadowRadius: 12,
        shadowOffset: { width: 0, height: 6 },
    },
    cardMetaRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 14,
    },
    badgePill: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 10,
        paddingVertical: 5,
        borderRadius: 14,
    },
    badgeText: {
        fontSize: 11,
        fontWeight: '800',
        letterSpacing: 0.6,
        marginLeft: 4,
    },
    audioPillBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 16,
    },
    audioPillText: {
        fontSize: 12,
        fontWeight: '700',
        marginLeft: 4,
    },
    cardScrollContent: {
        flex: 1,
    },
    sectionHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 6,
        marginTop: 4,
    },
    sectionDot: {
        width: 6,
        height: 6,
        borderRadius: 3,
        marginRight: 6,
    },
    sectionLabel: {
        fontSize: 11,
        fontWeight: '800',
        letterSpacing: 0.8,
    },
    questionText: {
        fontSize: 17,
        lineHeight: 25,
        fontWeight: '700',
        fontFamily: 'NotoSans-Bold',
        marginBottom: 14,
    },
    cardDivider: {
        height: 1,
        marginVertical: 12,
    },
    answerText: {
        fontSize: 15,
        lineHeight: 23,
        fontWeight: '500',
        fontFamily: 'NotoSans-Regular',
        marginBottom: 14,
    },
    memoryBox: {
        marginTop: 8,
        padding: 14,
        borderRadius: 16,
        borderWidth: 1,
        borderStyle: 'dashed',
        marginBottom: 10,
    },
    memoryHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 6,
    },
    memoryTitle: {
        fontSize: 11,
        fontWeight: '800',
        color: '#6366f1',
        marginLeft: 6,
        letterSpacing: 0.5,
    },
    memoryText: {
        fontSize: 13,
        lineHeight: 20,
        fontFamily: 'NotoSans-Regular',
    },
    cardFooter: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        paddingTop: 10,
        borderTopWidth: 1,
        borderTopColor: 'rgba(0,0,0,0.05)',
        marginTop: 8,
    },
    navArrowBtn: {
        width: 36,
        height: 36,
        borderRadius: 18,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: 'rgba(0,0,0,0.03)',
    },
    swipeHintContainer: {
        alignItems: 'center',
    },
    swipeHintText: {
        fontSize: 12,
        fontWeight: '600',
        letterSpacing: 0.3,
    },
});

export default QuickRevisionScreen;