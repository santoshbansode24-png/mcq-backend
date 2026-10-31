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

    let questionScript = cleanQ;
    let answerScript = cleanA;
    let explanationScript = cleanE;

    if (lang === 'mr') {
        // Teacher tone for Marathi: Explains pedagogically with teacher guidance
        questionScript = `लक्ष द्या विद्यार्थ्यांनो, प्रश्न समजून घ्या — ${cleanQ}`;
        answerScript = `याचं सोपं आणि नेमकं उत्तर आहे — ${cleanA}`;
        if (cleanE) {
            explanationScript = `परीक्षेसाठी ही महत्त्वाची टीप नक्की लक्षात ठेवा: ${cleanE}`;
        }
    } else if (lang === 'hi') {
        // Teacher tone for Hindi
        questionScript = `प्यारे बच्चों, आइए यह सवाल समझते हैं: ${cleanQ}`;
        answerScript = `इसका सीधा और आसान उत्तर है: ${cleanA}`;
        if (cleanE) {
            explanationScript = `परीक्षा के लिए यह खास बात याद रखिएगा: ${cleanE}`;
        }
    } else {
        // Teacher tone for English
        questionScript = `Students, let's understand this question: ${cleanQ}`;
        answerScript = `Here is the clear explanation: ${cleanA}`;
        if (cleanE) {
            explanationScript = `Key exam tip to remember: ${cleanE}`;
        }
    }

    return {
        cleanQ,
        cleanA,
        cleanE,
        questionScript,
        answerScript,
        explanationScript,
        keywords: [],
    };
};

// Classroom Blackboard Themes (हिरवा फळा / Dark Slate Chalkboard)
const REEL_THEMES = [
    {
        darkGrad: ['#061a11', '#0b261b', '#103324'],
        lightGrad: ['#0b261b', '#103324', '#174230'],
        accent: '#34d399',
        tagBg: 'rgba(52,211,153,0.18)',
        chalkWhite: '#ffffff',
        chalkYellow: '#fef08a',
        name: 'Emerald Chalkboard (हिरवा फळा)',
    },
    {
        darkGrad: ['#0d1715', '#14201d', '#1b2925'],
        lightGrad: ['#14201d', '#1b2925', '#243732'],
        accent: '#10b981',
        tagBg: 'rgba(16,185,129,0.18)',
        chalkWhite: '#ffffff',
        chalkYellow: '#fef08a',
        name: 'Classic Dark Slate (काळा फळा)',
    },
    {
        darkGrad: ['#082017', '#0e2b20', '#14382a'],
        lightGrad: ['#0e2b20', '#14382a', '#1d4d3a'],
        accent: '#6ee7b7',
        tagBg: 'rgba(110,231,183,0.18)',
        chalkWhite: '#ffffff',
        chalkYellow: '#fef08a',
        name: 'Deep Forest Slate',
    },
    {
        darkGrad: ['#081721', '#0e2331', '#143042'],
        lightGrad: ['#0e2331', '#143042', '#1b3f57'],
        accent: '#38bdf8',
        tagBg: 'rgba(56,189,248,0.18)',
        chalkWhite: '#ffffff',
        chalkYellow: '#fef08a',
        name: 'Classroom Navy Slate',
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
    const [countdownVal, setCountdownVal] = useState(null);
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

    // Zero-Lag Prefetching for current card and upcoming cards
    const prefetchCardAudio = useCallback((item) => {
        if (!item) return;
        const scripts = buildConversationalTeacherScript(item, language);
        const { questionScript, answerScript, explanationScript } = scripts;
        const currentSpeed = speechSpeedRef.current;
        const langCode = preferredLanguage.current;

        if (questionScript) prefetchGoogleTTS(questionScript, langCode, currentSpeed);
        if (answerScript) prefetchGoogleTTS(answerScript, langCode, currentSpeed);
        if (explanationScript) prefetchGoogleTTS(explanationScript, langCode, currentSpeed);
    }, [language]);

    useEffect(() => {
        if (revisionData.length === 0) return;
        const start = currentIndex;
        const end = Math.min(currentIndex + 2, revisionData.length - 1);
        for (let i = start; i <= end; i++) {
            if (revisionData[i]) {
                prefetchCardAudio(revisionData[i]);
            }
        }
    }, [currentIndex, revisionData, prefetchCardAudio]);

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
                        const rawPoints = Array.isArray(parsed.data) 
                            ? (parsed.data[0]?.key_points || []) 
                            : (parsed.data?.key_points || []);
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
            if (response?.status === 'success' && response?.data) {
                const rawPoints = Array.isArray(response.data) 
                    ? (response.data[0]?.key_points || []) 
                    : (response.data?.key_points || []);
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
        setCountdownVal(null);
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

        // --- SEQUENCE EXECUTION (Question -> 3s Countdown -> Answer Reveal) ---
        try {
            // Phase 1: Question Understanding
            setCountdownVal(null);
            const qDone = await runPhase('question', questionScript);
            if (!qDone || thisToken !== activePlayTokenRef.current) return;

            // Phase 2: Brisk, seamless teacher transition pause (700ms - no awkward delay)
            setActivePhase('countdown');
            setCountdownVal(1);
            HapticManager.triggerLight();
            await new Promise((r) => setTimeout(r, 700));
            if (thisToken !== activePlayTokenRef.current) return;
            setCountdownVal(null);

            // Phase 3: Core Concept & Answer Explanation with Chalk Reveal
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
        const { cleanQ, cleanA, cleanE } = scriptData;

        const isCurrentCard = currentIndex === index;
        const isPlayingThis = playingIndex === index;
        const isMastered = !!masteredCards[index];

        const isQuestionActive = isPlayingThis && activePhase === 'question';
        const isAnswerActive = isPlayingThis && activePhase === 'answer';
        const isExplanationActive = isPlayingThis && activePhase === 'explanation';

        return (
            <View style={[styles.cardWrapper, { height: containerHeight }]}>
                {/* FULL-WIDTH IMMERSIVE CHALKBOARD REEL CARD */}
                <View
                    style={[
                        styles.reelCard,
                        {
                            backgroundColor: '#0c2319',
                            borderColor: isPlayingThis ? '#34d399' : 'rgba(52,211,153,0.35)',
                            shadowColor: '#34d399',
                        },
                    ]}
                >
                    {/* TOP STATUS ROW */}
                    <View style={styles.cardHeaderRow}>
                        <View style={styles.reelBadge}>
                            <MaterialCommunityIcons name="lightning-bolt" size={16} color="#34d399" />
                            <Text style={styles.reelBadgeText}>
                                {index + 1} / {revisionData.length}
                            </Text>
                        </View>

                        {/* LIVE TEACHER VOICE PHASE CHIP */}
                        <View
                            style={[
                                styles.phaseChip,
                                {
                                    backgroundColor: isQuestionActive
                                        ? 'rgba(56,189,248,0.25)'
                                        : isAnswerActive
                                        ? 'rgba(52,211,153,0.25)'
                                        : isExplanationActive
                                        ? 'rgba(251,191,36,0.25)'
                                        : 'rgba(255,255,255,0.08)',
                                },
                            ]}
                        >
                            <Ionicons
                                name={isPlayingThis ? 'volume-high' : 'sparkles-outline'}
                                size={14}
                                color={
                                    isQuestionActive
                                        ? '#38bdf8'
                                        : isAnswerActive
                                        ? '#34d399'
                                        : isExplanationActive
                                        ? '#fbbf24'
                                        : '#94a3b8'
                                }
                            />
                            <Text
                                style={[
                                    styles.phaseChipText,
                                    {
                                        color: isQuestionActive
                                            ? '#38bdf8'
                                            : isAnswerActive
                                            ? '#34d399'
                                            : isExplanationActive
                                            ? '#fbbf24'
                                            : '#cbd5e1',
                                    },
                                ]}
                            >
                                {isQuestionActive ? '🎙️ प्रश्न स्पष्टीकरण' : isAnswerActive ? '💡 उत्तर स्पष्टीकरण' : isExplanationActive ? '✨ परीक्षेची टीप' : '🇮🇳 शिक्षक आवाज'}
                            </Text>
                        </View>

                        {/* SPEED CHIP */}
                        <TouchableOpacity onPress={toggleSpeed} style={styles.speedChip}>
                            <Text style={styles.speedChipText}>{speechSpeed.toFixed(2)}x</Text>
                        </TouchableOpacity>
                    </View>

                    {/* MAIN SCROLLABLE CONTENT */}
                    <ScrollView
                        style={styles.cardScroll}
                        showsVerticalScrollIndicator={false}
                        nestedScrollEnabled={true}
                    >
                        {/* 1. QUESTION SECTION */}
                        <TouchableOpacity
                            activeOpacity={0.88}
                            onPress={() => playConversationalTTS(item, index)}
                            style={[
                                styles.questionBox,
                                {
                                    backgroundColor: isQuestionActive ? 'rgba(56,189,248,0.18)' : '#123325',
                                    borderColor: isQuestionActive ? '#38bdf8' : 'rgba(255,255,255,0.15)',
                                },
                            ]}
                        >
                            <View style={styles.sectionHeaderLine}>
                                <Ionicons name="help-circle" size={16} color="#38bdf8" />
                                <Text style={styles.sectionHeadingQ}>QUESTION / प्रश्न</Text>
                                {isQuestionActive && (
                                    <View style={styles.liveVoiceBadge}>
                                        <Text style={styles.liveVoiceBadgeText}>SPEAKING</Text>
                                    </View>
                                )}
                            </View>
                            <Text style={styles.questionMainText}>{cleanQ}</Text>
                        </TouchableOpacity>

                        {/* INTERACTIVE TEACHER TRANSITION DISPLAY */}
                        {isPlayingThis && activePhase === 'countdown' && (
                            <View style={styles.countdownBox}>
                                <Text style={styles.countdownTitle}>💡 उत्तर स्पष्टीकरण सुरु होत आहे...</Text>
                                <View style={styles.countdownCircle}>
                                    <Ionicons name="sparkles" size={24} color="#34d399" />
                                </View>
                                <Text style={styles.countdownSubtitle}>शिक्षकांचे सोपे स्पष्टीकरण ऐका!</Text>
                            </View>
                        )}

                        {/* 2. KEY ANSWER SECTION */}
                        {isPlayingThis && (activePhase === 'question' || activePhase === 'countdown') ? (
                            <View style={[styles.answerBox, styles.answerBoxVeiled]}>
                                <View style={styles.sectionHeaderLine}>
                                    <Ionicons name="bulb-outline" size={16} color="#34d399" />
                                    <Text style={styles.sectionHeadingA}>ANSWER / उत्तर (विचार करा)</Text>
                                </View>
                                <Text style={styles.veiledText}>
                                    🔒 प्रश्न ऐकून आधी स्वतः उत्तर आठवा... टायमर संपताच उत्तर उलगडेल!
                                </Text>
                            </View>
                        ) : (
                            <TouchableOpacity
                                activeOpacity={0.88}
                                onPress={() => playConversationalTTS(item, index)}
                                style={[
                                    styles.answerBox,
                                    {
                                        backgroundColor: isAnswerActive ? 'rgba(52,211,153,0.22)' : '#153a2b',
                                        borderColor: isAnswerActive ? '#34d399' : 'rgba(52,211,153,0.3)',
                                    },
                                ]}
                            >
                                <View style={styles.sectionHeaderLine}>
                                    <Ionicons name="bulb" size={16} color="#34d399" />
                                    <Text style={styles.sectionHeadingA}>KEY ANSWER / मुख्य उत्तर</Text>
                                    {isAnswerActive && (
                                        <View style={[styles.liveVoiceBadge, { backgroundColor: '#34d399' }]}>
                                            <Text style={styles.liveVoiceBadgeText}>EXPLAINING</Text>
                                        </View>
                                    )}
                                </View>
                                <Text style={styles.answerMainText}>
                                    {cleanA}
                                </Text>
                            </TouchableOpacity>
                        )}

                        {/* 3. EXAM PRO TIP / MEMORY HOOK */}
                        {cleanE ? (
                            <TouchableOpacity
                                activeOpacity={0.88}
                                onPress={() => playConversationalTTS(item, index)}
                                style={[
                                    styles.hookBox,
                                    {
                                        backgroundColor: isExplanationActive ? 'rgba(251,191,36,0.22)' : '#262208',
                                        borderColor: isExplanationActive ? '#fbbf24' : '#f59e0b',
                                    },
                                ]}
                            >
                                <View style={styles.hookHeader}>
                                    <Ionicons name="star" size={16} color="#fbbf24" />
                                    <Text style={styles.hookTitle}>EXAM PRO TIP / परीक्षेची टीप</Text>
                                </View>
                                <Text style={styles.hookText}>
                                    {cleanE}
                                </Text>
                            </TouchableOpacity>
                        ) : null}
                    </ScrollView>

                    {/* BOTTOM REEL ACTIONS BAR */}
                    <View style={styles.bottomControlBar}>
                        <TouchableOpacity
                            onPress={() => toggleMastered(index)}
                            style={[
                                styles.masterBtn,
                                isMastered && { backgroundColor: 'rgba(251,191,36,0.25)', borderColor: '#fbbf24' },
                            ]}
                        >
                            <Ionicons
                                name={isMastered ? 'star' : 'star-outline'}
                                size={18}
                                color={isMastered ? '#fbbf24' : '#94a3b8'}
                            />
                            <Text style={[styles.masterBtnText, isMastered && { color: '#fbbf24' }]}>
                                {isMastered ? 'Mastered ⭐' : 'Got this?'}
                            </Text>
                        </TouchableOpacity>

                        {/* LISTEN / PLAY BUTTON */}
                        <TouchableOpacity
                            onPress={() => playConversationalTTS(item, index)}
                            style={[
                                styles.listenPill,
                                {
                                    backgroundColor: isPlayingThis ? '#ef4444' : '#10b981',
                                },
                            ]}
                        >
                            <Ionicons
                                name={isPlayingThis ? 'pause' : 'play'}
                                size={18}
                                color="#fff"
                            />
                            <Text style={styles.listenPillText}>
                                {isPlayingThis ? 'Pause' : 'Explain'}
                            </Text>
                        </TouchableOpacity>

                        {/* NEXT REEL BUTTON */}
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
                            style={[styles.nextBtn, index + 1 === revisionData.length && { opacity: 0.3 }]}
                            disabled={index + 1 === revisionData.length}
                        >
                            <Text style={styles.nextBtnText}>Next ⬇️</Text>
                        </TouchableOpacity>
                    </View>
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
    container: { flex: 1, backgroundColor: '#071610' },
    safeArea: { flex: 1, paddingTop: STATUSBAR_HEIGHT },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#071610' },
    loadingText: { marginTop: 12, fontSize: 14, fontWeight: '600', color: '#94a3b8' },
    retryBtn: {
        paddingHorizontal: 24,
        paddingVertical: 12,
        borderRadius: 24,
        backgroundColor: '#10b981',
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingVertical: 10,
        borderBottomWidth: 1,
        borderBottomColor: 'rgba(255,255,255,0.08)',
        backgroundColor: '#071610',
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
        color: '#ffffff',
        fontFamily: 'NotoSans-Bold',
    },
    headerSubtitle: {
        fontSize: 11,
        color: '#34d399',
        marginTop: 2,
    },
    autoPlayBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 18,
        backgroundColor: 'rgba(16,185,129,0.15)',
        borderWidth: 1,
        borderColor: '#10b981',
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
        paddingHorizontal: 10,
        paddingVertical: 6,
        justifyContent: 'center',
        alignItems: 'center',
    },
    reelCard: {
        width: '100%',
        height: '100%',
        borderRadius: 24,
        padding: 18,
        borderWidth: 2,
        elevation: 8,
        shadowOpacity: 0.25,
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
        backgroundColor: 'rgba(52,211,153,0.18)',
    },
    reelBadgeText: {
        fontSize: 12,
        fontWeight: '900',
        color: '#34d399',
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
    speedChip: {
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 12,
        backgroundColor: 'rgba(255,255,255,0.1)',
    },
    speedChipText: {
        fontSize: 11,
        fontWeight: '800',
        color: '#ffffff',
    },
    cardScroll: {
        flex: 1,
    },
    questionBox: {
        borderRadius: 18,
        padding: 16,
        marginBottom: 12,
        borderWidth: 1.5,
    },
    sectionHeaderLine: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 8,
    },
    sectionHeadingQ: {
        fontSize: 11,
        fontWeight: '900',
        letterSpacing: 0.8,
        color: '#38bdf8',
        marginLeft: 6,
        flex: 1,
    },
    sectionHeadingA: {
        fontSize: 11,
        fontWeight: '900',
        letterSpacing: 0.8,
        color: '#34d399',
        marginLeft: 6,
        flex: 1,
    },
    liveVoiceBadge: {
        backgroundColor: '#38bdf8',
        paddingHorizontal: 6,
        paddingVertical: 2,
        borderRadius: 6,
    },
    liveVoiceBadgeText: {
        fontSize: 9,
        fontWeight: '900',
        color: '#071610',
    },
    questionMainText: {
        fontSize: 20,
        lineHeight: 29,
        fontWeight: 'bold',
        color: '#ffffff',
        fontFamily: 'NotoSans-Bold',
    },
    countdownBox: {
        backgroundColor: 'rgba(52,211,153,0.15)',
        borderColor: '#34d399',
        borderWidth: 1.5,
        borderRadius: 18,
        padding: 16,
        alignItems: 'center',
        marginVertical: 12,
    },
    countdownTitle: {
        color: '#6ee7b7',
        fontSize: 14,
        fontWeight: '700',
        marginBottom: 8,
    },
    countdownCircle: {
        width: 52,
        height: 52,
        borderRadius: 26,
        backgroundColor: '#34d399',
        justifyContent: 'center',
        alignItems: 'center',
        marginVertical: 4,
    },
    countdownNumber: {
        color: '#071610',
        fontSize: 26,
        fontWeight: '900',
    },
    countdownSubtitle: {
        color: '#cbd5e1',
        fontSize: 12,
        marginTop: 6,
    },
    answerBox: {
        borderRadius: 18,
        padding: 16,
        marginBottom: 12,
        borderWidth: 1.5,
    },
    answerBoxVeiled: {
        backgroundColor: 'rgba(255,255,255,0.04)',
        borderColor: 'rgba(255,255,255,0.12)',
        borderStyle: 'dashed',
    },
    veiledText: {
        color: '#94a3b8',
        fontSize: 14,
        fontStyle: 'italic',
        lineHeight: 22,
    },
    answerMainText: {
        fontSize: 17,
        lineHeight: 26,
        fontWeight: '600',
        color: '#ffffff',
        fontFamily: 'NotoSans-Regular',
    },
    hookBox: {
        borderRadius: 18,
        padding: 14,
        borderWidth: 1.5,
        borderStyle: 'dashed',
        marginBottom: 12,
    },
    hookHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 6,
    },
    hookTitle: {
        fontSize: 11,
        fontWeight: '900',
        marginLeft: 6,
        letterSpacing: 0.5,
        color: '#fbbf24',
    },
    hookText: {
        fontSize: 15,
        lineHeight: 23,
        color: '#fef08a',
        fontWeight: '500',
        fontFamily: 'NotoSans-Regular',
    },
    bottomControlBar: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingTop: 12,
        borderTopWidth: 1,
        borderTopColor: 'rgba(255,255,255,0.1)',
        gap: 8,
    },
    masterBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 12,
        paddingVertical: 8,
        borderRadius: 16,
        backgroundColor: 'rgba(255,255,255,0.08)',
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.12)',
    },
    masterBtnText: {
        fontSize: 12,
        fontWeight: '700',
        color: '#cbd5e1',
        marginLeft: 4,
    },
    listenPill: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        paddingVertical: 10,
        borderRadius: 20,
        elevation: 4,
    },
    listenPillText: {
        fontSize: 14,
        fontWeight: '800',
        color: '#ffffff',
        marginLeft: 6,
    },
    nextBtn: {
        paddingHorizontal: 12,
        paddingVertical: 8,
        borderRadius: 16,
        backgroundColor: 'rgba(255,255,255,0.08)',
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.12)',
    },
    nextBtnText: {
        fontSize: 12,
        fontWeight: '700',
        color: '#ffffff',
    },
});

export default QuickRevisionScreen;
