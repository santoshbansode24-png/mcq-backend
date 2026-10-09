import React, { useState, useEffect, useRef, useMemo } from 'react';
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
    Animated,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons, MaterialCommunityIcons, FontAwesome5 } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { Audio } from 'expo-av';
import ConfettiCannon from 'react-native-confetti-cannon';
import { useTheme } from '../context/ThemeContext';
import { useLanguage } from '../context/LanguageContext';
import { fetchQuickRevision } from '../api/content';
import { playGoogleTTS, prefetchGoogleTTS, stopAllTTS } from '../api/googleTTS';
import HapticManager from '../utils/HapticManager';

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

const sanitizeCleanText = (text) => {
    if (!text) return '';
    return decodeHtml(text)
        .replace(/^(Q\s*\d*[:.-]?|प्रश्न\s*\d*[:.-]?|सवाल\s*\d*[:.-]?)\s*/i, '')
        .replace(/^(Ans\s*[:.-]?|उत्तर\s*[:.-]?|जवाब\s*[:.-]?)\s*/i, '')
        .trim();
};

/**
 * Extracts 3-4 impactful keywords from Question and Answer for synced visual tags
 */
const extractKeywords = (question, answer) => {
    const raw = `${sanitizeCleanText(question)} ${sanitizeCleanText(answer)}`;
    const stopWords = new Set([
        'आहे', 'आहेत', 'होते', 'होता', 'नाही', 'आणि', 'किंवा', 'पण', 'तर', 'म्हणजे', 'काय', 'कसे', 'केव्हा', 'कोठे', 'या', 'त्या', 'हे', 'तो', 'ती', 'ते', 'ला', 'ना', 'नी', 'चे', 'ची', 'च्या', 'स', 'तील', 'वरील',
        'है', 'हैं', 'था', 'थे', 'थी', 'और', 'या', 'लेकिन', 'तो', 'मतलब', 'क्या', 'कैसे', 'कब', 'कहाँ', 'यह', 'वह', 'इस', 'उस', 'का', 'के', 'की', 'को', 'में', 'पर', 'से', 'ने',
        'what', 'why', 'how', 'when', 'where', 'who', 'is', 'are', 'was', 'were', 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'that', 'this', 'it'
    ]);

    const tokens = raw
        .replace(/[,;:.?!()"'`\-\/]/g, ' ')
        .split(/\s+/)
        .map((w) => w.trim())
        .filter((w) => w.length > 2 && !stopWords.has(w.toLowerCase()));

    const unique = [];
    for (const t of tokens) {
        if (!unique.some((u) => u.toLowerCase() === t.toLowerCase())) {
            unique.push(t);
        }
        if (unique.length >= 4) break;
    }
    return unique;
};

/**
 * Formats Conversational Indian Teacher Script:
 * Explains the question first in simple words (not robotic reading),
 * then explains the answer naturally, then gives the key memory tip.
 */
const buildConversationalTeacherScript = (item, lang = 'mr') => {
    const cleanQ = sanitizeCleanText(item.q || item.Question || '');
    const cleanA = sanitizeCleanText(item.a || item.Answer || '');
    const cleanE = sanitizeCleanText(item.e || item.Explanation || '');

    let questionScript = '';
    let answerScript = '';
    let explanationScript = '';

    if (lang === 'mr') {
        questionScript = `चला मित्रांनो, आधी हा प्रश्न सोप्या भाषेत समजून घेऊया! प्रश्न असा आहे: ${cleanQ}`;
        answerScript = `याचं मुख्य उत्तर आणि संकल्पना अशी आहे: ${cleanA}`;
        if (cleanE) {
            explanationScript = `परीक्षेसाठी ही खास टीप लक्षात ठेवा: ${cleanE}`;
        }
    } else if (lang === 'hi') {
        questionScript = `नमस्ते दोस्तों! चलिए पहले इस सवाल को सरल भाषा में समझते हैं! सवाल यह है: ${cleanQ}`;
        answerScript = `इसका मुख्य उत्तर और सही कारण यह है: ${cleanA}`;
        if (cleanE) {
            explanationScript = `परीक्षा के लिए यह जरूरी बात याद रखिए: ${cleanE}`;
        }
    } else {
        questionScript = `Hey students! Let's understand this question simply: ${cleanQ}`;
        answerScript = `The core answer and concept here is: ${cleanA}`;
        if (cleanE) {
            explanationScript = `Here is a golden exam tip to remember: ${cleanE}`;
        }
    }

    return {
        cleanQ,
        cleanA,
        cleanE,
        questionScript,
        answerScript,
        explanationScript,
        keywords: extractKeywords(cleanQ, cleanA),
    };
};

// 6 Vibrant theme presets rotating per card for Instagram Reels/TikTok look
const REEL_THEMES = [
    {
        darkGrad: ['#0f172a', '#1e1b4b', '#172554'],
        lightGrad: ['#f8faff', '#ede9fe', '#e0e7ff'],
        accent: '#6366f1',
        tagBg: 'rgba(99,102,241,0.15)',
        name: 'Indigo Pulse',
    },
    {
        darkGrad: ['#022c22', '#064e3b', '#065f46'],
        lightGrad: ['#f0fdf4', '#dcfce7', '#ecfdf5'],
        accent: '#10b981',
        tagBg: 'rgba(16,185,129,0.15)',
        name: 'Emerald Glow',
    },
    {
        darkGrad: ['#31103f', '#4a044e', '#581c87'],
        lightGrad: ['#faf5ff', '#f3e8ff', '#fce7f3'],
        accent: '#ec4899',
        tagBg: 'rgba(236,72,153,0.15)',
        name: 'Cosmic Magenta',
    },
    {
        darkGrad: ['#1c1917', '#451a03', '#78350f'],
        lightGrad: ['#fffbeb', '#fef3c7', '#fed7aa'],
        accent: '#f59e0b',
        tagBg: 'rgba(245,158,11,0.15)',
        name: 'Sunset Amber',
    },
    {
        darkGrad: ['#082f49', '#0c4a6e', '#164e63'],
        lightGrad: ['#f0f9ff', '#e0f2fe', '#cffafe'],
        accent: '#06b6d4',
        tagBg: 'rgba(6,182,212,0.15)',
        name: 'Electric Cyan',
    },
    {
        darkGrad: ['#3b0764', '#1e1b4b', '#431407'],
        lightGrad: ['#fff1f2', '#ffe4e6', '#ede9fe'],
        accent: '#8b5cf6',
        tagBg: 'rgba(139,92,246,0.15)',
        name: 'Neon Violet',
    },
];

const QuickRevisionScreen = ({ navigation, route }) => {
    const { theme, isDarkMode } = useTheme();
    const { language = 'mr' } = useLanguage ? useLanguage() : {};
    const { chapterId, chapterName, revisionData: initialData } = route.params || {};

    const [loading, setLoading] = useState(!initialData || initialData.length === 0);
    const [revisionData, setRevisionData] = useState([]);
    const [error, setError] = useState(null);
    const [currentIndex, setCurrentIndex] = useState(0);

    // Audio & Phase Synchronization: 'question' | 'answer' | 'explanation' | null
    const [playingIndex, setPlayingIndex] = useState(null);
    const [activePhase, setActivePhase] = useState(null);
    const [isAutoPlaying, setIsAutoPlaying] = useState(false);
    const [speechSpeed, setSpeechSpeed] = useState(0.92); // 0.85x, 0.92x, 1.1x
    const [masteredCards, setMasteredCards] = useState({});
    const [showConfetti, setShowConfetti] = useState(false);

    const [containerHeight, setContainerHeight] = useState(SCREEN_HEIGHT - 130);

    const flatListRef = useRef(null);
    const soundRef = useRef(null);
    const preferredLanguage = useRef(language === 'hi' ? 'hi-IN' : language === 'en' ? 'en-IN' : 'mr-IN');
    const isAutoPlayingRef = useRef(false);
    const revisionDataRef = useRef([]);
    const currentIndexRef = useRef(0);
    const activePlayTokenRef = useRef(0);
    const isProgrammaticScrollRef = useRef(false);
    const speechSpeedRef = useRef(0.92);

    // Keep preferredLanguage synced with user's selected language
    useEffect(() => {
        preferredLanguage.current = language === 'hi' ? 'hi-IN' : language === 'en' ? 'en-IN' : 'mr-IN';
    }, [language]);

    useEffect(() => {
        isAutoPlayingRef.current = isAutoPlaying;
    }, [isAutoPlaying]);

    useEffect(() => {
        revisionDataRef.current = revisionData;
    }, [revisionData]);

    useEffect(() => {
        currentIndexRef.current = currentIndex;
    }, [currentIndex]);

    useEffect(() => {
        speechSpeedRef.current = speechSpeed;
    }, [speechSpeed]);

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

            // Load mastered states from cache
            try {
                const stored = await AsyncStorage.getItem(`@quick_rev_mastered_${chapterId}`);
                if (stored) setMasteredCards(JSON.parse(stored));
            } catch (e) {}

            if (Array.isArray(initialData) && initialData.length > 0) {
                const valid = cleanPoints(initialData);
                if (valid.length > 0) {
                    setRevisionData(valid);
                    setLoading(false);
                }
            }

            await loadRevision();
        };

        prepareScreen();

        return () => {
            activePlayTokenRef.current++;
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
        if (
            items.length > 0 &&
            items[0]?.q?.toString().trim().toLowerCase() === 'question' &&
            items[0]?.a?.toString().trim().toLowerCase() === 'answer'
        ) {
            items = items.slice(1);
        }
        return items.filter((item) => item.q || item.Question || item.a || item.Answer);
    };

    /* ---------------- STOP TTS ---------------- */
    const stopTTS = async () => {
        activePlayTokenRef.current++;
        if (soundRef.current) {
            try {
                await soundRef.current.stopAsync();
                await soundRef.current.unloadAsync();
            } catch (e) {}
            soundRef.current = null;
        }
        await stopAllTTS();
        setPlayingIndex(null);
        setActivePhase(null);
    };

    /* ---------------- SYNCHRONIZED MULTI-PHASE CONVERSATIONAL PLAYER ---------------- */
    /**
     * Perfectly syncs Indian conversational teacher sound with visual highlights:
     * Phase 1: Explains the question simply -> Question card glows
     * Phase 2: Explains the answer naturally -> Answer card glows + Keywords illuminate
     * Phase 3: Explains the exam tip -> Memory Hook glows with sparkle
     */
    const playConversationalTTS = async (item, index, autoNext = false) => {
        if (!item) return;

        // If already playing this card and tapped again, toggle pause
        if (playingIndex === index && !autoNext) {
            await stopTTS();
            return;
        }

        await stopTTS();
        const thisToken = ++activePlayTokenRef.current;
        setPlayingIndex(index);

        const scripts = buildConversationalTeacherScript(item, language);
        const { questionScript, answerScript, explanationScript } = scripts;
        const currentSpeed = speechSpeedRef.current;

        // Helper to play a single phase and wait for its natural completion
        const runPhase = (phaseName, scriptText) => {
            return new Promise(async (resolve) => {
                if (thisToken !== activePlayTokenRef.current || !scriptText) {
                    return resolve(false);
                }

                setActivePhase(phaseName);
                HapticManager.triggerLight();

                try {
                    const soundObj = await playGoogleTTS(scriptText, preferredLanguage.current, currentSpeed);

                    if (thisToken !== activePlayTokenRef.current) {
                        if (soundObj && soundObj.unloadAsync) soundObj.unloadAsync().catch(() => {});
                        return resolve(false);
                    }

                    if (!soundObj) {
                        return resolve(true);
                    }

                    soundRef.current = soundObj;
                    soundObj.setOnPlaybackStatusUpdate((status) => {
                        if (thisToken !== activePlayTokenRef.current) return;
                        if (status.didJustFinish) {
                            if (soundObj.unloadAsync) soundObj.unloadAsync().catch(() => {});
                            soundRef.current = null;
                            resolve(true);
                        }
                    });
                } catch (err) {
                    resolve(true);
                }
            });
        };

        // --- SEQUENCE EXECUTION ---
        try {
            // Phase 1: Question Understanding
            const qDone = await runPhase('question', questionScript);
            if (!qDone || thisToken !== activePlayTokenRef.current) return;

            // Phase 2: Core Concept & Answer Explanation with Synced Keywords
            const aDone = await runPhase('answer', answerScript);
            if (!aDone || thisToken !== activePlayTokenRef.current) return;

            // Phase 3: Memory Hook / Exam Tip (if exists)
            if (explanationScript) {
                const eDone = await runPhase('explanation', explanationScript);
                if (!eDone || thisToken !== activePlayTokenRef.current) return;
            }

            // Sequence Complete
            if (thisToken === activePlayTokenRef.current) {
                setPlayingIndex(null);
                setActivePhase(null);

                // Auto-advance if Auto-Play is active
                if (isAutoPlayingRef.current) {
                    const nextIndex = index + 1;
                    const total = revisionDataRef.current.length;
                    if (nextIndex < total) {
                        isProgrammaticScrollRef.current = true;
                        scrollToIndex(nextIndex);
                        setTimeout(() => {
                            if (isAutoPlayingRef.current) {
                                playConversationalTTS(revisionDataRef.current[nextIndex], nextIndex, true);
                            }
                        }, 500);
                    } else {
                        setIsAutoPlaying(false);
                    }
                }
            }
        } catch (e) {
            if (thisToken === activePlayTokenRef.current) {
                setPlayingIndex(null);
                setActivePhase(null);
            }
        }
    };

    /* ---------------- REEL CONTROLS ---------------- */
    const toggleAutoPlay = () => {
        HapticManager.triggerLight();
        if (isAutoPlaying) {
            setIsAutoPlaying(false);
            stopTTS();
        } else {
            setIsAutoPlaying(true);
            const activeIndex = currentIndexRef.current;
            if (revisionDataRef.current[activeIndex]) {
                playConversationalTTS(revisionDataRef.current[activeIndex], activeIndex, true);
            }
        }
    };

    const toggleSpeed = () => {
        HapticManager.triggerLight();
        setSpeechSpeed((prev) => {
            if (prev <= 0.88) return 1.0;
            if (prev <= 1.02) return 1.15;
            return 0.85;
        });
    };

    const toggleMastered = async (index) => {
        HapticManager.triggerSuccess();
        const updated = { ...masteredCards, [index]: !masteredCards[index] };
        setMasteredCards(updated);
        if (updated[index]) {
            setShowConfetti(true);
            setTimeout(() => setShowConfetti(false), 3000);
        }
        try {
            await AsyncStorage.setItem(`@quick_rev_mastered_${chapterId}`, JSON.stringify(updated));
        } catch (e) {}
    };

    const scrollToIndex = (index) => {
        if (index >= 0 && index < revisionData.length) {
            currentIndexRef.current = index;
            setCurrentIndex(index);
            flatListRef.current?.scrollToIndex({ index, animated: true });
        }
    };

    const handleMomentumScrollEnd = (e) => {
        const offsetY = e.nativeEvent.contentOffset.y;
        const newIndex = Math.round(offsetY / containerHeight);

        if (isProgrammaticScrollRef.current) {
            isProgrammaticScrollRef.current = false;
            if (newIndex >= 0 && newIndex < revisionDataRef.current.length) {
                currentIndexRef.current = newIndex;
                setCurrentIndex(newIndex);
            }
            return;
        }

        if (newIndex >= 0 && newIndex < revisionDataRef.current.length) {
            const indexChanged = newIndex !== currentIndexRef.current;
            currentIndexRef.current = newIndex;
            setCurrentIndex(newIndex);

            if (indexChanged) {
                if (isAutoPlayingRef.current) {
                    playConversationalTTS(revisionDataRef.current[newIndex], newIndex, true);
                } else if (soundRef.current || playingIndex !== null) {
                    stopTTS();
                }
            }
        }
    };

    /* ---------------- RENDER REEL CARD ---------------- */
    const renderCard = ({ item, index }) => {
        const themePreset = REEL_THEMES[index % REEL_THEMES.length];
        const scriptData = buildConversationalTeacherScript(item, language);
        const { cleanQ, cleanA, cleanE, keywords } = scriptData;

        const isCurrentCard = currentIndex === index;
        const isPlayingThis = playingIndex === index;
        const isMastered = !!masteredCards[index];

        const isQuestionActive = isPlayingThis && activePhase === 'question';
        const isAnswerActive = isPlayingThis && activePhase === 'answer';
        const isExplanationActive = isPlayingThis && activePhase === 'explanation';

        return (
            <View style={[styles.cardWrapper, { height: containerHeight }]}>
                {/* MODERN REEL CARD WITH DYNAMIC ACCENT & BORDER */}
                <View
                    style={[
                        styles.reelCard,
                        {
                            backgroundColor: isDarkMode ? '#111827' : '#ffffff',
                            borderColor: isPlayingThis ? themePreset.accent : isDarkMode ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)',
                            shadowColor: isPlayingThis ? themePreset.accent : '#000',
                        },
                    ]}
                >
                    {/* TOP STATUS PILL ROW */}
                    <View style={styles.cardHeaderRow}>
                        <View style={[styles.reelBadge, { backgroundColor: themePreset.tagBg }]}>
                            <MaterialCommunityIcons name="lightning-bolt" size={15} color={themePreset.accent} />
                            <Text style={[styles.reelBadgeText, { color: themePreset.accent }]}>
                                POINT {index + 1} OF {revisionData.length}
                            </Text>
                        </View>

                        {/* LIVE TEACHER VOICE PHASE CHIP */}
                        <View
                            style={[
                                styles.phaseChip,
                                {
                                    backgroundColor: isQuestionActive
                                        ? 'rgba(99,102,241,0.2)'
                                        : isAnswerActive
                                        ? 'rgba(16,185,129,0.2)'
                                        : isExplanationActive
                                        ? 'rgba(245,158,11,0.2)'
                                        : isDarkMode
                                        ? 'rgba(255,255,255,0.06)'
                                        : '#f1f5f9',
                                },
                            ]}
                        >
                            <Ionicons
                                name={isPlayingThis ? 'volume-high' : 'sparkles-outline'}
                                size={14}
                                color={
                                    isQuestionActive
                                        ? '#6366f1'
                                        : isAnswerActive
                                        ? '#10b981'
                                        : isExplanationActive
                                        ? '#f59e0b'
                                        : theme.textSecondary
                                }
                            />
                            <Text
                                style={[
                                    styles.phaseChipText,
                                    {
                                        color: isQuestionActive
                                            ? '#6366f1'
                                            : isAnswerActive
                                            ? '#10b981'
                                            : isExplanationActive
                                            ? '#f59e0b'
                                            : theme.textSecondary,
                                    },
                                ]}
                            >
                                {isQuestionActive
                                    ? '🎙️ समजावून सांगत आहोत'
                                    : isAnswerActive
                                    ? '💡 मुख्य संकल्पना व उत्तर'
                                    : isExplanationActive
                                    ? '✨ खास परीक्षेची टीप'
                                    : '🇮🇳 Indian Teacher Voice'}
                            </Text>
                        </View>
                    </View>

                    {/* MAIN SCROLLABLE CONTENT */}
                    <ScrollView
                        style={styles.cardScroll}
                        showsVerticalScrollIndicator={false}
                        nestedScrollEnabled={true}
                    >
                        {/* 1. QUESTION SECTION (INTERACTIVE & SYNCHRONIZED) */}
                        <TouchableOpacity
                            activeOpacity={0.85}
                            onPress={() => playConversationalTTS(item, index)}
                            style={[
                                styles.questionBox,
                                {
                                    backgroundColor: isQuestionActive
                                        ? isDarkMode
                                            ? 'rgba(99,102,241,0.18)'
                                            : '#eef2ff'
                                        : isDarkMode
                                        ? 'rgba(255,255,255,0.03)'
                                        : '#f8fafc',
                                    borderColor: isQuestionActive ? '#6366f1' : 'transparent',
                                },
                            ]}
                        >
                            <View style={styles.sectionHeaderLine}>
                                <View style={[styles.sectionBullet, { backgroundColor: '#6366f1' }]} />
                                <Text style={[styles.sectionHeading, { color: '#6366f1' }]}>QUESTION CONCEPT</Text>
                                {isQuestionActive && (
                                    <View style={styles.liveVoiceBadge}>
                                        <Text style={styles.liveVoiceBadgeText}>TALKING</Text>
                                    </View>
                                )}
                            </View>
                            <Text style={[styles.questionMainText, { color: theme.text }]}>{cleanQ}</Text>
                        </TouchableOpacity>

                        {/* 2. KEYWORD HIGHLIGHTS PILL BAR (SYNCHRONIZED WITH ANSWER EXPLANATION) */}
                        {keywords.length > 0 && (
                            <View style={styles.keywordsContainer}>
                                <Text style={[styles.keywordsLabel, { color: theme.textSecondary }]}>
                                    IMP KEYPOINTS:
                                </Text>
                                <View style={styles.keywordsWrap}>
                                    {keywords.map((kw, kwIdx) => (
                                        <View
                                            key={kwIdx}
                                            style={[
                                                styles.kwPill,
                                                {
                                                    backgroundColor: isAnswerActive
                                                        ? 'rgba(16,185,129,0.2)'
                                                        : isDarkMode
                                                        ? 'rgba(255,255,255,0.06)'
                                                        : '#f1f5f9',
                                                    borderColor: isAnswerActive ? '#10b981' : isDarkMode ? 'rgba(255,255,255,0.1)' : '#e2e8f0',
                                                },
                                            ]}
                                        >
                                            <Text
                                                style={[
                                                    styles.kwText,
                                                    {
                                                        color: isAnswerActive
                                                            ? '#10b981'
                                                            : isDarkMode
                                                            ? '#cbd5e1'
                                                            : '#475569',
                                                    },
                                                ]}
                                            >
                                                #{kw}
                                            </Text>
                                        </View>
                                    ))}
                                </View>
                            </View>
                        )}

                        {/* 3. KEY ANSWER SECTION (SYNCHRONIZED WITH TEACHER EXPLANATION) */}
                        <TouchableOpacity
                            activeOpacity={0.85}
                            onPress={() => playConversationalTTS(item, index)}
                            style={[
                                styles.answerBox,
                                {
                                    backgroundColor: isAnswerActive
                                        ? isDarkMode
                                            ? 'rgba(16,185,129,0.15)'
                                            : '#f0fdf4'
                                        : isDarkMode
                                        ? 'rgba(255,255,255,0.04)'
                                        : '#f9fbfd',
                                    borderColor: isAnswerActive ? '#10b981' : isDarkMode ? 'rgba(255,255,255,0.06)' : '#e2e8f0',
                                },
                            ]}
                        >
                            <View style={styles.sectionHeaderLine}>
                                <View style={[styles.sectionBullet, { backgroundColor: '#10b981' }]} />
                                <Text style={[styles.sectionHeading, { color: '#10b981' }]}>KEY ANSWER</Text>
                                {isAnswerActive && (
                                    <View style={[styles.liveVoiceBadge, { backgroundColor: '#10b981' }]}>
                                        <Text style={styles.liveVoiceBadgeText}>EXPLAINING</Text>
                                    </View>
                                )}
                            </View>
                            <Text style={[styles.answerMainText, { color: isDarkMode ? '#f8fafc' : '#0f172a' }]}>
                                {cleanA}
                            </Text>
                        </TouchableOpacity>

                        {/* 4. MEMORY HOOK / EXPLANATION (SYNCHRONIZED WITH EXAM TIP) */}
                        {cleanE ? (
                            <TouchableOpacity
                                activeOpacity={0.85}
                                onPress={() => playConversationalTTS(item, index)}
                                style={[
                                    styles.hookBox,
                                    {
                                        backgroundColor: isExplanationActive
                                            ? isDarkMode
                                                ? 'rgba(245,158,11,0.18)'
                                                : '#fffbeb'
                                            : isDarkMode
                                            ? 'rgba(99,102,241,0.07)'
                                            : '#f5f7ff',
                                        borderColor: isExplanationActive ? '#f59e0b' : isDarkMode ? 'rgba(99,102,241,0.2)' : '#c7d2fe',
                                    },
                                ]}
                            >
                                <View style={styles.hookHeader}>
                                    <MaterialCommunityIcons
                                        name="lightbulb-on-outline"
                                        size={18}
                                        color={isExplanationActive ? '#f59e0b' : '#6366f1'}
                                    />
                                    <Text
                                        style={[
                                            styles.hookTitle,
                                            { color: isExplanationActive ? '#f59e0b' : '#6366f1' },
                                        ]}
                                    >
                                        EXAM PRO TIP / MEMORY HOOK
                                    </Text>
                                </View>
                                <Text style={[styles.hookText, { color: isDarkMode ? '#cbd5e1' : '#334155' }]}>
                                    {cleanE}
                                </Text>
                            </TouchableOpacity>
                        ) : null}
                    </ScrollView>

                    {/* REEL SIDEBAR & BOTTOM ACTIONS */}
                    <View style={styles.bottomControlBar}>
                        <TouchableOpacity
                            onPress={() => toggleMastered(index)}
                            style={[
                                styles.masterBtn,
                                isMastered && { backgroundColor: 'rgba(245,158,11,0.2)', borderColor: '#f59e0b' },
                            ]}
                        >
                            <Ionicons
                                name={isMastered ? 'star' : 'star-outline'}
                                size={18}
                                color={isMastered ? '#f59e0b' : theme.textSecondary}
                            />
                            <Text
                                style={[
                                    styles.masterBtnText,
                                    { color: isMastered ? '#f59e0b' : theme.textSecondary },
                                ]}
                            >
                                {isMastered ? 'Mastered ⭐' : 'Got this?'}
                            </Text>
                        </TouchableOpacity>

                        <View style={styles.swipeHintWrap}>
                            <Text style={[styles.swipeHint, { color: theme.textSecondary }]}>
                                {index + 1 === revisionData.length ? '🎉 All Done!' : 'Swipe up for next Reel 👆'}
                            </Text>
                        </View>

                        {/* AUDIO LISTEN BUTTON */}
                        <TouchableOpacity
                            onPress={() => playConversationalTTS(item, index)}
                            style={[
                                styles.listenPill,
                                {
                                    backgroundColor: isPlayingThis ? themePreset.accent : isDarkMode ? '#334155' : '#e2e8f0',
                                },
                            ]}
                        >
                            <Ionicons
                                name={isPlayingThis ? 'pause' : 'play'}
                                size={16}
                                color={isPlayingThis ? '#fff' : themePreset.accent}
                            />
                            <Text
                                style={[
                                    styles.listenPillText,
                                    { color: isPlayingThis ? '#fff' : isDarkMode ? '#f8fafc' : '#1e293b' },
                                ]}
                            >
                                {isPlayingThis ? 'Pause' : 'Explain'}
                            </Text>
                        </TouchableOpacity>
                    </View>
                </View>

                {/* FLOATING REEL ACTION BAR (INSTAGRAM SHORTS STYLE) */}
                <View style={styles.reelsSidebar}>
                    <TouchableOpacity
                        onPress={() => playConversationalTTS(item, index)}
                        style={[
                            styles.sidebarBtn,
                            isPlayingThis && { backgroundColor: themePreset.accent, transform: [{ scale: 1.05 }] },
                        ]}
                    >
                        <Ionicons
                            name={isPlayingThis ? 'volume-high' : 'headset-outline'}
                            size={20}
                            color={isPlayingThis ? '#fff' : theme.text}
                        />
                        <Text style={[styles.sidebarBtnLabel, isPlayingThis && { color: '#fff' }]}>
                            {isPlayingThis ? 'Active' : 'Listen'}
                        </Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                        onPress={toggleAutoPlay}
                        style={[
                            styles.sidebarBtn,
                            isAutoPlaying && { backgroundColor: '#ef4444' },
                        ]}
                    >
                        <Ionicons
                            name={isAutoPlaying ? 'pause' : 'play-skip-forward'}
                            size={18}
                            color={isAutoPlaying ? '#fff' : theme.text}
                        />
                        <Text style={[styles.sidebarBtnLabel, isAutoPlaying && { color: '#fff' }]}>
                            {isAutoPlaying ? 'Auto' : 'Play All'}
                        </Text>
                    </TouchableOpacity>

                    <TouchableOpacity onPress={toggleSpeed} style={styles.sidebarBtn}>
                        <MaterialCommunityIcons name="speedometer" size={19} color={theme.text} />
                        <Text style={styles.sidebarBtnLabel}>{speechSpeed.toFixed(2)}x</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                        onPress={() => {
                            if (index + 1 < revisionDataRef.current.length) {
                                const nextIdx = index + 1;
                                isProgrammaticScrollRef.current = true;
                                scrollToIndex(nextIdx);
                                if (isAutoPlayingRef.current) {
                                    playConversationalTTS(revisionDataRef.current[nextIdx], nextIdx, true);
                                } else {
                                    stopTTS();
                                }
                            }
                        }}
                        style={[styles.sidebarBtn, index + 1 === revisionData.length && { opacity: 0.3 }]}
                        disabled={index + 1 === revisionData.length}
                    >
                        <Ionicons name="chevron-down" size={20} color={theme.text} />
                        <Text style={styles.sidebarBtnLabel}>Next</Text>
                    </TouchableOpacity>
                </View>
            </View>
        );
    };

    /* ---------------- MAIN SCREEN ---------------- */
    if (loading) {
        return (
            <View style={[styles.center, { backgroundColor: isDarkMode ? '#0f172a' : '#eef2ff' }]}>
                <ActivityIndicator size="large" color={theme.primary} />
                <Text style={[styles.loadingText, { color: theme.textSecondary }]}>
                    Loading Quick Revision Reels...
                </Text>
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

    const currentTheme = REEL_THEMES[currentIndex % REEL_THEMES.length];
    const masteredCount = Object.values(masteredCards).filter(Boolean).length;

    return (
        <LinearGradient
            colors={isDarkMode ? currentTheme.darkGrad : currentTheme.lightGrad}
            style={styles.container}
        >
            <SafeAreaView style={styles.safeArea}>
                {/* CELEBRATION CONFETTI CANNON */}
                {showConfetti && (
                    <ConfettiCannon
                        count={40}
                        origin={{ x: SCREEN_WIDTH / 2, y: SCREEN_HEIGHT / 3 }}
                        fadeOut={true}
                        autoStart={true}
                    />
                )}

                {/* TOP HEADER */}
                <View style={[styles.header, { borderBottomColor: isDarkMode ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)' }]}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
                        <Ionicons name="arrow-back" size={22} color={theme.text} />
                    </TouchableOpacity>

                    <View style={styles.headerTitleWrap}>
                        <Text style={[styles.headerTitle, { color: theme.text }]} numberOfLines={1}>
                            {chapterName || 'Quick Revision Reels'}
                        </Text>
                        <Text style={[styles.headerSubtitle, { color: theme.textSecondary }]}>
                            ⭐ {masteredCount}/{revisionData.length} Mastered • Natural Indian Teacher
                        </Text>
                    </View>

                    {/* AUTO REEL TOGGLE */}
                    <TouchableOpacity
                        onPress={toggleAutoPlay}
                        style={[
                            styles.autoPlayBtn,
                            isAutoPlaying && { backgroundColor: '#fee2e2', borderColor: '#ef4444' },
                        ]}
                    >
                        <Ionicons
                            name={isAutoPlaying ? 'pause' : 'play-skip-forward'}
                            size={16}
                            color={isAutoPlaying ? '#ef4444' : theme.primary}
                        />
                        <Text
                            style={[
                                styles.autoPlayBtnText,
                                { color: isAutoPlaying ? '#ef4444' : theme.primary },
                            ]}
                        >
                            {isAutoPlaying ? 'Auto ON' : 'Auto'}
                        </Text>
                    </TouchableOpacity>
                </View>

                {/* INSTAGRAM STORIES STYLE SEGMENTED PROGRESS BARS */}
                <View style={styles.segmentedProgressWrapper}>
                    {revisionData.map((_, i) => (
                        <View
                            key={i}
                            style={[
                                styles.segmentBar,
                                {
                                    backgroundColor:
                                        i < currentIndex
                                            ? currentTheme.accent
                                            : i === currentIndex
                                            ? currentTheme.accent
                                            : isDarkMode
                                            ? 'rgba(255,255,255,0.18)'
                                            : 'rgba(0,0,0,0.12)',
                                    opacity: i === currentIndex ? 1 : i < currentIndex ? 0.8 : 0.35,
                                },
                            ]}
                        />
                    ))}
                </View>

                {/* VERTICAL REELS SWIPE FLATLIST */}
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
    loadingText: { marginTop: 12, fontSize: 14, fontWeight: '600' },
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
        backgroundColor: 'rgba(255,255,255,0.14)',
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
        fontSize: 11,
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
    segmentedProgressWrapper: {
        flexDirection: 'row',
        paddingHorizontal: 16,
        paddingVertical: 6,
        gap: 4,
    },
    segmentBar: {
        flex: 1,
        height: 3.5,
        borderRadius: 2,
    },
    listContainer: {
        flex: 1,
    },
    cardWrapper: {
        width: SCREEN_WIDTH,
        paddingHorizontal: 12,
        paddingVertical: 8,
        flexDirection: 'row',
        alignItems: 'center',
    },
    reelCard: {
        flex: 1,
        borderRadius: 24,
        padding: 16,
        borderWidth: 2,
        elevation: 8,
        shadowOpacity: 0.15,
        shadowRadius: 14,
        shadowOffset: { width: 0, height: 6 },
    },
    cardHeaderRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 12,
    },
    reelBadge: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 12,
    },
    reelBadgeText: {
        fontSize: 11,
        fontWeight: '800',
        marginLeft: 4,
        letterSpacing: 0.5,
    },
    phaseChip: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 12,
    },
    phaseChipText: {
        fontSize: 11,
        fontWeight: '700',
        marginLeft: 4,
    },
    cardScroll: {
        flex: 1,
    },
    questionBox: {
        borderRadius: 18,
        padding: 14,
        marginBottom: 10,
        borderWidth: 1.5,
    },
    sectionHeaderLine: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 6,
    },
    sectionBullet: {
        width: 7,
        height: 7,
        borderRadius: 3.5,
        marginRight: 6,
    },
    sectionHeading: {
        fontSize: 10.5,
        fontWeight: '800',
        letterSpacing: 0.8,
        flex: 1,
    },
    liveVoiceBadge: {
        backgroundColor: '#6366f1',
        paddingHorizontal: 6,
        paddingVertical: 2,
        borderRadius: 8,
    },
    liveVoiceBadgeText: {
        fontSize: 9,
        fontWeight: '900',
        color: '#fff',
    },
    questionMainText: {
        fontSize: 16,
        lineHeight: 24,
        fontWeight: '700',
        fontFamily: 'NotoSans-Bold',
    },
    keywordsContainer: {
        marginVertical: 6,
    },
    keywordsLabel: {
        fontSize: 10,
        fontWeight: '800',
        letterSpacing: 0.5,
        marginBottom: 4,
    },
    keywordsWrap: {
        flexDirection: 'row',
        flexWrap: 'wrap',
        gap: 6,
    },
    kwPill: {
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 10,
        borderWidth: 1,
    },
    kwText: {
        fontSize: 12,
        fontWeight: '700',
    },
    answerBox: {
        borderRadius: 18,
        padding: 14,
        marginTop: 6,
        marginBottom: 10,
        borderWidth: 1.5,
    },
    answerMainText: {
        fontSize: 15,
        lineHeight: 23,
        fontWeight: '500',
        fontFamily: 'NotoSans-Regular',
    },
    hookBox: {
        borderRadius: 16,
        padding: 12,
        borderWidth: 1.5,
        borderStyle: 'dashed',
        marginTop: 4,
        marginBottom: 10,
    },
    hookHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 4,
    },
    hookTitle: {
        fontSize: 11,
        fontWeight: '800',
        marginLeft: 6,
        letterSpacing: 0.5,
    },
    hookText: {
        fontSize: 13,
        lineHeight: 20,
        fontFamily: 'NotoSans-Regular',
    },
    bottomControlBar: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingTop: 10,
        borderTopWidth: 1,
        borderTopColor: 'rgba(0,0,0,0.06)',
    },
    masterBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 10,
        paddingVertical: 6,
        borderRadius: 14,
        borderWidth: 1,
        borderColor: 'rgba(0,0,0,0.1)',
    },
    masterBtnText: {
        fontSize: 11,
        fontWeight: '700',
        marginLeft: 4,
    },
    swipeHintWrap: {
        flex: 1,
        alignItems: 'center',
    },
    swipeHint: {
        fontSize: 11,
        fontWeight: '600',
    },
    listenPill: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 16,
    },
    listenPillText: {
        fontSize: 12,
        fontWeight: '700',
        marginLeft: 4,
    },
    reelsSidebar: {
        width: 48,
        marginLeft: 8,
        justifyContent: 'center',
        alignItems: 'center',
        gap: 12,
    },
    sidebarBtn: {
        width: 44,
        height: 48,
        borderRadius: 14,
        backgroundColor: 'rgba(255,255,255,0.12)',
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.1)',
    },
    sidebarBtnLabel: {
        fontSize: 9,
        fontWeight: '800',
        marginTop: 2,
    },
});

export default QuickRevisionScreen;