import React, { useState, useEffect, useRef, useMemo, useCallback } from 'react';
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
/**
 * Smartboard Breakdown Parser:
 * Splits explanation into pedagogical classroom steps:
 * Step 1: Core Concept / सोपी संकल्पना
 * Step 2: Scientific Rule & Formula / वैज्ञानिक नियम व सूत्र
 * Step 3: Exam Hack / परीक्षेचा गुरुमंत्र
 */
const parseSmartboardSteps = (item, cleanA, cleanE, lang = 'mr') => {
    if (cleanE && (cleanE.includes('|') || /step\s*\d+/i.test(cleanE))) {
        const rawParts = cleanE.split(/\||(?=step\s*\d+:?)/i)
            .map(p => p.replace(/^step\s*\d+:?\s*/i, '').trim())
            .filter(Boolean);

        if (rawParts.length >= 2) {
            return [
                {
                    stepNum: 1,
                    tag: lang === 'mr' ? 'Step 1: सोपी संकल्पना' : 'Step 1: Core Concept',
                    text: rawParts[0],
                    accent: '#38bdf8',
                    icon: 'bulb-outline',
                },
                {
                    stepNum: 2,
                    tag: lang === 'mr' ? 'Step 2: वैज्ञानिक नियम व सूत्र' : 'Step 2: Scientific Rule / Formula',
                    text: rawParts[1],
                    accent: '#34d399',
                    icon: 'calculator-outline',
                },
                {
                    stepNum: 3,
                    tag: lang === 'mr' ? 'Step 3: परीक्षेचा गुरुमंत्र' : 'Step 3: Exam Hack',
                    text: rawParts[2] || (lang === 'mr' ? 'परीक्षेत हा मुख्य मुद्दा अचूक लिहा, पूर्ण गुण मिळतील!' : 'Write this key definition for full marks!'),
                    accent: '#fbbf24',
                    icon: 'star-outline',
                },
            ];
        }
    }

    // Default 3-Step Smartboard Breakdown (Works 100% with any existing database content)
    return [
        {
            stepNum: 1,
            tag: lang === 'mr' ? 'Step 1: मुख्य उत्तर' : 'Step 1: Key Answer',
            text: cleanA,
            accent: '#34d399',
            icon: 'checkmark-circle-outline',
        },
        {
            stepNum: 2,
            tag: lang === 'mr' ? 'Step 2: संकल्पना व स्पष्टीकरण' : 'Step 2: Concept Explanation',
            text: cleanE || (lang === 'mr' ? 'ही संकल्पना नेहमी लक्षात ठेवा.' : 'Remember this concept.'),
            accent: '#38bdf8',
            icon: 'school-outline',
        },
        {
            stepNum: 3,
            tag: lang === 'mr' ? 'Step 3: परीक्षेचा गुरुमंत्र' : 'Step 3: Exam Hack',
            text: lang === 'mr' 
                ? 'परीक्षेत ही व्याख्या किंवा सूत्र अचूक लिहिल्यास पैकीच्या पैकी गुण मिळतात!' 
                : 'Write this key term in exams for full marks!',
            accent: '#fbbf24',
            icon: 'star-outline',
        },
    ];
};

/**
 * Formats Conversational Indian Teacher Script:
 * Synthesizes 1 unified continuous teacher narration per card (0ms lag)
 */
const buildConversationalTeacherScript = (item, lang = 'mr') => {
    const cleanQ = sanitizeCleanText(item.q || item.Question || '');
    const cleanA = sanitizeCleanText(item.a || item.Answer || '');
    const cleanE = sanitizeCleanText(item.e || item.Explanation || '');
    const optA = sanitizeCleanText(item.opt_a || '');
    const optB = sanitizeCleanText(item.opt_b || '');
    const correct = (item.correct || 'A').toUpperCase();
    const correctText = correct === 'B' ? (optB || cleanA) : (optA || cleanA);

    let fullTeacherScript = '';

    if (lang === 'mr') {
        if (optA && optB) {
            fullTeacherScript = `विद्यार्थ्यांनो, विचार करा — ${cleanQ}. याचं अचूक उत्तर आहे: ${correctText}. चला हे सोप्या भाषेत समजून घेऊया. ${cleanA}. ${cleanE ? 'परीक्षेसाठी ही महत्त्वाची टीप नक्की लक्षात ठेवा: ' + cleanE : ''}`;
        } else {
            fullTeacherScript = `लक्ष द्या विद्यार्थ्यांनो, प्रश्न समजून घ्या — ${cleanQ}. याचं सोपं आणि नेमकं उत्तर आहे — ${cleanA}. ${cleanE ? 'परीक्षेसाठी ही महत्त्वाची टीप नक्की लक्षात ठेवा: ' + cleanE : ''}`;
        }
    } else if (lang === 'hi') {
        if (optA && optB) {
            fullTeacherScript = `प्यारे बच्चों, सोचिए — ${cleanQ}. इसका सही उत्तर है: ${correctText}. आइए इसे समझते हैं: ${cleanA}. ${cleanE ? 'परीक्षा के लिए यह खास बात याद रखिएगा: ' + cleanE : ''}`;
        } else {
            fullTeacherScript = `प्यारे बच्चों, आइए यह सवाल समझते हैं: ${cleanQ}. इसका सीधा और आसान उत्तर है: ${cleanA}. ${cleanE ? 'परीक्षा के लिए यह खास बात याद रखिएगा: ' + cleanE : ''}`;
        }
    } else {
        if (optA && optB) {
            fullTeacherScript = `Students, think about this — ${cleanQ}. The correct answer is: ${correctText}. Let's break it down: ${cleanA}. ${cleanE ? 'Exam tip to remember: ' + cleanE : ''}`;
        } else {
            fullTeacherScript = `Students, let's understand this question: ${cleanQ}. Here is the clear explanation: ${cleanA}. ${cleanE ? 'Key exam tip to remember: ' + cleanE : ''}`;
        }
    }

    return {
        cleanQ,
        cleanA,
        cleanE,
        optA,
        optB,
        correct,
        fullTeacherScript: fullTeacherScript.trim(),
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
    const [isAutoPlaying, setIsAutoPlaying] = useState(false);
    const [userGuesses, setUserGuesses] = useState({});
    const [revealedPolls, setRevealedPolls] = useState({});
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
        const { fullTeacherScript } = scripts;
        const currentSpeed = speechSpeedRef.current;
        const langCode = preferredLanguage.current;

        if (fullTeacherScript) prefetchGoogleTTS(fullTeacherScript, langCode, currentSpeed);
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
        setActivePhase('question');
        HapticManager.triggerLight();

        const scripts = buildConversationalTeacherScript(item, language);
        const { fullTeacherScript } = scripts;
        const currentSpeed = speechSpeedRef.current;

        try {
            // Plays ultra-natural continuous single-take human teacher audio (0ms latency with prefetch)
            const soundObj = await playGoogleTTS(fullTeacherScript, preferredLanguage.current, currentSpeed);

            if (thisToken !== activePlayTokenRef.current) {
                if (soundObj?.unloadAsync) soundObj.unloadAsync().catch(() => {});
                return;
            }

            if (!soundObj) {
                setPlayingIndex(null);
                setActivePhase(null);
                return;
            }

            soundRef.current = soundObj;
            soundObj.setOnPlaybackStatusUpdate((status) => {
                if (thisToken !== activePlayTokenRef.current) return;

                // Dynamically sync visual chalk highlight with natural teacher speech
                if (status.isPlaying && status.durationMillis > 0) {
                    const ratio = status.positionMillis / status.durationMillis;
                    if (ratio < 0.35) {
                        setActivePhase('question');
                    } else if (ratio < 0.80) {
                        setActivePhase('answer');
                    } else {
                        setActivePhase('explanation');
                    }
                }

                if (status.didJustFinish) {
                    if (soundObj?.unloadAsync) soundObj.unloadAsync().catch(() => {});
                    soundRef.current = null;
                    setPlayingIndex(null);
                    setActivePhase(null);

                    // Auto-advance smoothly to next reel
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
                            }, 400);
                        } else {
                            setIsAutoPlaying(false);
                        }
                    }
                }
            });
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

    const handleSelectOption = (cardIdx, choice, correctChoice) => {
        const isCorrect = choice === correctChoice;
        if (isCorrect) {
            HapticManager.triggerSuccess();
        } else {
            HapticManager.triggerWarning();
        }
        setUserGuesses(prev => ({ ...prev, [cardIdx]: choice }));
        setRevealedPolls(prev => ({ ...prev, [cardIdx]: true }));

        if (playingIndex !== cardIdx) {
            playConversationalTTS(revisionDataRef.current[cardIdx], cardIdx);
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
        const { cleanQ, cleanA, cleanE, optA, optB, correct } = scriptData;

        const isCurrentCard = currentIndex === index;
        const isPlayingThis = playingIndex === index;
        const isMastered = !!masteredCards[index];

        const isQuestionActive = isPlayingThis && activePhase === 'question';
        const isAnswerActive = isPlayingThis && activePhase === 'answer';
        const isExplanationActive = isPlayingThis && activePhase === 'explanation';

        const hasPoll = Boolean(optA && optB);
        const userGuess = userGuesses[index];
        const isPollRevealed = Boolean(revealedPolls[index]);
        const correctChoice = correct === 'B' ? 'B' : 'A';

        const smartSteps = parseSmartboardSteps(item, cleanA, cleanE, language);

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

                        {/* 2. INTERACTIVE 2-OPTION POLL (GUESS & WIN) */}
                        {hasPoll && (
                            <View style={styles.pollSection}>
                                <View style={styles.pollHeaderRow}>
                                    <Ionicons name="game-controller-outline" size={16} color="#34d399" />
                                    <Text style={styles.pollHeaderTitle}>
                                        {language === 'mr' ? 'अंदाज लावा! योग्य पर्याय निवडा:' : 'QUICK GUESS CHALLENGE:'}
                                    </Text>
                                </View>

                                {/* OPTION A BUTTON */}
                                <TouchableOpacity
                                    activeOpacity={0.85}
                                    onPress={() => handleSelectOption(index, 'A', correctChoice)}
                                    style={[
                                        styles.pollOptionBtn,
                                        isPollRevealed && correctChoice === 'A' && styles.pollOptionCorrect,
                                        isPollRevealed && userGuess === 'A' && correctChoice !== 'A' && styles.pollOptionWrong,
                                    ]}
                                >
                                    <View style={[
                                        styles.optionLetterBadge,
                                        isPollRevealed && correctChoice === 'A' && { backgroundColor: '#34d399' },
                                        isPollRevealed && userGuess === 'A' && correctChoice !== 'A' && { backgroundColor: '#ef4444' },
                                    ]}>
                                        <Text style={styles.optionLetterText}>A</Text>
                                    </View>
                                    <Text style={styles.optionMainText}>{optA}</Text>
                                    {isPollRevealed && correctChoice === 'A' && (
                                        <Ionicons name="checkmark-circle" size={20} color="#34d399" style={styles.pollStatusIcon} />
                                    )}
                                    {isPollRevealed && userGuess === 'A' && correctChoice !== 'A' && (
                                        <Ionicons name="close-circle" size={20} color="#ef4444" style={styles.pollStatusIcon} />
                                    )}
                                </TouchableOpacity>

                                {/* OPTION B BUTTON */}
                                <TouchableOpacity
                                    activeOpacity={0.85}
                                    onPress={() => handleSelectOption(index, 'B', correctChoice)}
                                    style={[
                                        styles.pollOptionBtn,
                                        isPollRevealed && correctChoice === 'B' && styles.pollOptionCorrect,
                                        isPollRevealed && userGuess === 'B' && correctChoice !== 'B' && styles.pollOptionWrong,
                                    ]}
                                >
                                    <View style={[
                                        styles.optionLetterBadge,
                                        isPollRevealed && correctChoice === 'B' && { backgroundColor: '#34d399' },
                                        isPollRevealed && userGuess === 'B' && correctChoice !== 'B' && { backgroundColor: '#ef4444' },
                                    ]}>
                                        <Text style={styles.optionLetterText}>B</Text>
                                    </View>
                                    <Text style={styles.optionMainText}>{optB}</Text>
                                    {isPollRevealed && correctChoice === 'B' && (
                                        <Ionicons name="checkmark-circle" size={20} color="#34d399" style={styles.pollStatusIcon} />
                                    )}
                                    {isPollRevealed && userGuess === 'B' && correctChoice !== 'B' && (
                                        <Ionicons name="close-circle" size={20} color="#ef4444" style={styles.pollStatusIcon} />
                                    )}
                                </TouchableOpacity>
                            </View>
                        )}

                        {/* 3. DIGITAL SMARTBOARD STEP-BY-STEP BREAKDOWN */}
                        <View style={styles.smartboardSection}>
                            <View style={styles.smartboardHeaderLine}>
                                <Ionicons name="easel-outline" size={16} color="#34d399" />
                                <Text style={styles.smartboardSectionHeading}>
                                    {language === 'mr' ? 'डिजिटल स्मार्टबोर्ड स्पष्टीकरण' : 'SMARTBOARD STEP-BY-STEP'}
                                </Text>
                            </View>

                            {smartSteps.map((step, sIdx) => {
                                const isStepActive = isPlayingThis && (
                                    (sIdx === 0 && activePhase === 'question') ||
                                    (sIdx === 1 && activePhase === 'answer') ||
                                    (sIdx === 2 && activePhase === 'explanation')
                                );

                                return (
                                    <TouchableOpacity
                                        key={sIdx}
                                        activeOpacity={0.88}
                                        onPress={() => playConversationalTTS(item, index)}
                                        style={[
                                            styles.smartStepCard,
                                            {
                                                borderColor: isStepActive ? step.accent : 'rgba(255,255,255,0.14)',
                                                backgroundColor: isStepActive ? 'rgba(16,45,34,0.95)' : '#102a20',
                                            },
                                        ]}
                                    >
                                        <View style={styles.stepHeaderRow}>
                                            <View style={[styles.stepTagPill, { backgroundColor: `${step.accent}25` }]}>
                                                <Ionicons name={step.icon} size={13} color={step.accent} />
                                                <Text style={[styles.stepTagText, { color: step.accent }]}>{step.tag}</Text>
                                            </View>
                                            {isStepActive && (
                                                <View style={[styles.liveVoiceBadge, { backgroundColor: step.accent }]}>
                                                    <Text style={styles.liveVoiceBadgeText}>TEACHING</Text>
                                                </View>
                                            )}
                                        </View>
                                        <Text style={styles.stepBodyText}>{step.text}</Text>
                                    </TouchableOpacity>
                                );
                            })}
                        </View>
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
    pollSection: {
        marginBottom: 14,
        padding: 12,
        borderRadius: 14,
        backgroundColor: '#0a1d15',
        borderWidth: 1,
        borderColor: 'rgba(52,211,153,0.25)',
    },
    pollHeaderRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 8,
        marginBottom: 10,
    },
    pollHeaderTitle: {
        fontSize: 13,
        fontWeight: 'bold',
        color: '#34d399',
        letterSpacing: 0.5,
    },
    pollOptionBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: 12,
        paddingHorizontal: 14,
        borderRadius: 12,
        backgroundColor: '#123325',
        borderWidth: 1.5,
        borderColor: 'rgba(255,255,255,0.15)',
        marginBottom: 8,
    },
    pollOptionCorrect: {
        backgroundColor: 'rgba(52,211,153,0.22)',
        borderColor: '#34d399',
    },
    pollOptionWrong: {
        backgroundColor: 'rgba(239,68,68,0.20)',
        borderColor: '#ef4444',
    },
    optionLetterBadge: {
        width: 28,
        height: 28,
        borderRadius: 14,
        backgroundColor: 'rgba(255,255,255,0.12)',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 12,
    },
    optionLetterText: {
        color: '#ffffff',
        fontSize: 14,
        fontWeight: 'bold',
    },
    optionMainText: {
        flex: 1,
        color: '#f8fafc',
        fontSize: 16,
        fontWeight: '600',
        lineHeight: 22,
    },
    pollStatusIcon: {
        marginLeft: 8,
    },
    smartboardSection: {
        marginTop: 4,
        marginBottom: 14,
    },
    smartboardHeaderLine: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 8,
        marginBottom: 10,
    },
    smartboardSectionHeading: {
        fontSize: 13,
        fontWeight: 'bold',
        color: '#34d399',
        letterSpacing: 0.8,
    },
    smartStepCard: {
        padding: 14,
        borderRadius: 14,
        borderWidth: 1.5,
        marginBottom: 10,
    },
    stepHeaderRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 8,
    },
    stepTagPill: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 6,
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 8,
    },
    stepTagText: {
        fontSize: 12,
        fontWeight: 'bold',
        letterSpacing: 0.3,
    },
    stepBodyText: {
        color: '#ffffff',
        fontSize: 16,
        lineHeight: 24,
        fontWeight: '500',
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
