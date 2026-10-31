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
    Dimensions,
    Animated,
    TouchableWithoutFeedback,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
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
 * Automatically detects whether text is in Marathi (Devanagari) or English
 */
const detectTextLanguage = (text) => {
    if (!text) return 'en';
    const hasDevanagari = /[\u0900-\u097F]/.test(text);
    return hasDevanagari ? 'mr' : 'en';
};

/**
 * Splits explanation into clean pedagogical story steps
 */
const parseCleanSteps = (item, cleanA, cleanE) => {
    if (cleanE && (cleanE.includes('|') || /step\s*\d+/i.test(cleanE))) {
        const rawParts = cleanE
            .split(/\||(?=step\s*\d+:?)/i)
            .map((p) => p.replace(/^step\s*\d+:?\s*/i, '').trim())
            .filter(Boolean);

        if (rawParts.length >= 2) {
            return rawParts.map((txt, idx) => ({
                stepNum: idx + 1,
                text: txt,
                icon: idx === 0 ? 'bulb-outline' : idx === 1 ? 'calculator-outline' : 'star-outline',
                accent: idx === 0 ? '#38bdf8' : idx === 1 ? '#34d399' : '#fbbf24',
            }));
        }
    }

    // Default clean steps
    const steps = [
        {
            stepNum: 1,
            text: cleanA,
            icon: 'bulb-outline',
            accent: '#38bdf8',
        },
    ];

    if (cleanE && cleanE !== cleanA) {
        steps.push({
            stepNum: 2,
            text: cleanE,
            icon: 'school-outline',
            accent: '#34d399',
        });
    }

    return steps;
};

/**
 * Builds structured Story Slides for each Reel Question
 */
const buildCardSlides = (item) => {
    const cleanQ = sanitizeCleanText(item.q || item.Question || '');
    const cleanA = sanitizeCleanText(item.a || item.Answer || '');
    const cleanE = sanitizeCleanText(item.e || item.Explanation || '');
    const optA = sanitizeCleanText(item.opt_a || '');
    const optB = sanitizeCleanText(item.opt_b || '');
    const correct = (item.correct || 'A').toUpperCase() === 'B' ? 'B' : 'A';
    const correctText = correct === 'B' ? (optB || cleanA) : (optA || cleanA);

    // Detect language of question content
    const cardLang = detectTextLanguage(cleanQ + ' ' + cleanA);
    const langCode = cardLang === 'mr' ? 'mr-IN' : 'en-IN';
    const speaker = cardLang === 'mr' ? 'rupali' : 'kavya';

    const hasPoll = Boolean(optA && optB);
    const steps = parseCleanSteps(item, cleanA, cleanE);

    const slides = [];

    // Slide 0: Question & 2-Option Guess Challenge
    if (hasPoll) {
        let qVoice = '';
        if (cardLang === 'mr') {
            qVoice = `${cleanQ} ... योग्य पर्याय निवडा.`;
        } else {
            qVoice = `${cleanQ} ... Choose the correct option.`;
        }

        slides.push({
            type: 'poll',
            slideIndex: 0,
            question: cleanQ,
            optA,
            optB,
            correct,
            audioText: qVoice,
            langCode,
            speaker,
        });
    } else {
        slides.push({
            type: 'question_only',
            slideIndex: 0,
            question: cleanQ,
            audioText: cleanQ,
            langCode,
            speaker,
        });
    }

    // Slide 1, 2, 3: Explanation Steps
    steps.forEach((step, idx) => {
        slides.push({
            type: 'step',
            slideIndex: slides.length,
            stepNum: step.stepNum,
            questionSummary: cleanQ,
            text: step.text,
            icon: step.icon,
            accent: step.accent,
            audioText: step.text,
            langCode,
            speaker,
        });
    });

    return {
        cleanQ,
        cleanA,
        cleanE,
        optA,
        optB,
        correct,
        correctText,
        cardLang,
        langCode,
        speaker,
        hasPoll,
        slides,
    };
};

// Classroom Blackboard Themes
const REEL_THEMES = [
    {
        darkGrad: ['#061a11', '#0b261b', '#103324'],
        lightGrad: ['#0b261b', '#103324', '#174230'],
        accent: '#34d399',
    },
    {
        darkGrad: ['#0d1715', '#14201d', '#1b2925'],
        lightGrad: ['#14201d', '#1b2925', '#243732'],
        accent: '#10b981',
    },
    {
        darkGrad: ['#082017', '#0e2b20', '#14382a'],
        lightGrad: ['#0e2b20', '#14382a', '#1d4d3a'],
        accent: '#6ee7b7',
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

    // Story Slide state per Card: Map<cardIndex, currentSlideIndex>
    const [cardSlideIndex, setCardSlideIndex] = useState({});
    const [userGuesses, setUserGuesses] = useState({});
    const [revealedPolls, setRevealedPolls] = useState({});
    const [pollTimeRemaining, setPollTimeRemaining] = useState({}); // Card index -> remaining seconds (3 -> 0), or null for 'reading'
    const [isAutoPlaying, setIsAutoPlaying] = useState(false);
    const [speechSpeed, setSpeechSpeed] = useState(0.92);
    const [masteredCards, setMasteredCards] = useState({});
    const [showConfetti, setShowConfetti] = useState(false);
    const [containerHeight, setContainerHeight] = useState(SCREEN_HEIGHT - 130);

    const flatListRef = useRef(null);
    const soundRef = useRef(null);
    const activePlayTokenRef = useRef(0);
    const pollTimerRef = useRef(null);
    const autoAdvanceTimerRef = useRef(null);
    const speechSpeedRef = useRef(0.92);
    const isAutoPlayingRef = useRef(false);
    const revisionDataRef = useRef([]);
    const currentIndexRef = useRef(0);
    const cardSlideIndexRef = useRef({});
    const userGuessesRef = useRef({});
    const isProgrammaticScrollRef = useRef(false);

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
        cardSlideIndexRef.current = cardSlideIndex;
    }, [cardSlideIndex]);

    useEffect(() => {
        userGuessesRef.current = userGuesses;
    }, [userGuesses]);

    useEffect(() => {
        speechSpeedRef.current = speechSpeed;
    }, [speechSpeed]);

    /* ---------------- ZERO-LAG AUDIO PREFETCHING ---------------- */
    const prefetchSlideAudios = useCallback((cardItem) => {
        if (!cardItem) return;
        const { slides, langCode, speaker } = buildCardSlides(cardItem);
        const speed = speechSpeedRef.current;

        slides.forEach((sl) => {
            if (sl.audioText) {
                prefetchGoogleTTS(sl.audioText, langCode, speed, speaker);
            }
        });
    }, []);

    // Prefetch for current and next card
    useEffect(() => {
        if (revisionData.length === 0) return;
        const cur = revisionData[currentIndex];
        const next = revisionData[currentIndex + 1];
        if (cur) prefetchSlideAudios(cur);
        if (next) prefetchSlideAudios(next);
    }, [currentIndex, revisionData, prefetchSlideAudios]);

    /* ---------------- CLEAN DATA HELPER ---------------- */
    const cleanPoints = (data) => {
        if (!Array.isArray(data)) return [];
        return data
            .map((item) => {
                if (typeof item === 'string') {
                    const parts = item.split(/[:.-]/);
                    return {
                        q: parts[0]?.trim() || '',
                        a: parts.slice(1).join(':').trim() || '',
                        e: '',
                    };
                }
                const q = item.q || item.question || item.Question || '';
                const a = item.a || item.answer || item.Answer || '';
                const e = item.e || item.explanation || item.Explanation || '';
                const opt_a = item.opt_a || item.Option_A || item.option_a || '';
                const opt_b = item.opt_b || item.Option_B || item.option_b || '';
                const correct = item.correct || item.Correct || 'A';
                return { q, a, e, opt_a, opt_b, correct };
            })
            .filter((item) => item.q && (item.a || item.opt_a));
    };

    /* ---------------- LIFECYCLE ---------------- */
    useEffect(() => {
        const prepare = async () => {
            try {
                await Audio.setAudioModeAsync({
                    allowsRecordingIOS: false,
                    playsInSilentModeIOS: true,
                    shouldDuckAndroid: true,
                    playThroughEarpieceAndroid: false,
                    staysActiveInBackground: false,
                });
            } catch (e) {}

            try {
                const stored = await AsyncStorage.getItem(`@quick_rev_mastered_${chapterId}`);
                if (stored) setMasteredCards(JSON.parse(stored));
            } catch (e) {}

            if (initialData) {
                const rawInit = Array.isArray(initialData?.key_points)
                    ? initialData.key_points
                    : Array.isArray(initialData)
                    ? (Array.isArray(initialData[0]?.key_points) ? initialData[0].key_points : initialData)
                    : [];
                const valid = cleanPoints(rawInit);
                if (valid.length > 0) {
                    setRevisionData(valid);
                    setLoading(false);
                }
            }
            await loadRevision();
        };

        prepare();

        return () => {
            activePlayTokenRef.current++;
            clearAllTimers();
            stopAllTTS();
            if (soundRef.current) {
                soundRef.current.unloadAsync().catch(() => {});
                soundRef.current = null;
            }
        };
    }, [chapterId]);

    const clearAllTimers = () => {
        if (pollTimerRef.current) {
            clearInterval(pollTimerRef.current);
            pollTimerRef.current = null;
        }
        if (autoAdvanceTimerRef.current) {
            clearTimeout(autoAdvanceTimerRef.current);
            autoAdvanceTimerRef.current = null;
        }
    };

    const stopTTS = async () => {
        activePlayTokenRef.current++;
        clearAllTimers();
        if (soundRef.current) {
            try {
                await soundRef.current.stopAsync();
                await soundRef.current.unloadAsync();
            } catch (e) {}
            soundRef.current = null;
        }
        await stopAllTTS();
    };

    const loadRevision = async (force = false) => {
        if (!chapterId) {
            setError('No chapter selected');
            setLoading(false);
            return;
        }
        try {
            setLoading(true);
            setError(null);
            const res = await fetchQuickRevision(chapterId, force);

            let rawPoints = null;
            if (res && res.status === 'success') {
                if (Array.isArray(res.data?.key_points)) {
                    rawPoints = res.data.key_points;
                } else if (Array.isArray(res.data)) {
                    if (res.data.length > 0 && Array.isArray(res.data[0]?.key_points)) {
                        rawPoints = res.data[0].key_points;
                    } else {
                        rawPoints = res.data;
                    }
                }
            }

            if (rawPoints && rawPoints.length > 0) {
                const cleaned = cleanPoints(rawPoints);
                if (cleaned.length > 0) {
                    setRevisionData(cleaned);
                    setError(null);
                } else {
                    setError('No revision cards found for this chapter.');
                }
            } else {
                if (res?.status === 'success') {
                    setError('No revision cards found for this chapter.');
                } else {
                    setError(res?.message || 'Failed to load Quick Revision.');
                }
            }
        } catch (err) {
            setError('Network error. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    /* ---------------- STORY SLIDE PROGRESSION CONTROLLER ---------------- */
    const playSlide = async (cardIdx, slideIdx) => {
        const item = revisionDataRef.current[cardIdx];
        if (!item) return;

        const { slides, langCode, speaker } = buildCardSlides(item);
        const currentSlide = slides[slideIdx];
        if (!currentSlide) return;

        await stopTTS();
        const thisToken = ++activePlayTokenRef.current;
        clearAllTimers();

        // Update card slide state
        setCardSlideIndex((prev) => ({ ...prev, [cardIdx]: slideIdx }));
        const speed = speechSpeedRef.current;

        // 1. If this is Slide 0 (Question & Poll):
        if (currentSlide.type === 'poll') {
            // Set to null while question is actively reading
            setPollTimeRemaining((prev) => ({ ...prev, [cardIdx]: null }));

            const startCountdown = () => {
                if (thisToken !== activePlayTokenRef.current) return;
                let secondsLeft = 3;
                setPollTimeRemaining((prev) => ({ ...prev, [cardIdx]: 3 }));

                if (pollTimerRef.current) clearInterval(pollTimerRef.current);
                pollTimerRef.current = setInterval(() => {
                    if (thisToken !== activePlayTokenRef.current) {
                        clearInterval(pollTimerRef.current);
                        return;
                    }
                    secondsLeft -= 1;
                    setPollTimeRemaining((prev) => ({ ...prev, [cardIdx]: Math.max(0, secondsLeft) }));

                    if (secondsLeft <= 0) {
                        clearInterval(pollTimerRef.current);
                        pollTimerRef.current = null;

                        const alreadyGuessed = userGuessesRef.current[cardIdx];
                        if (!alreadyGuessed) {
                            HapticManager.triggerWarning();
                            setRevealedPolls((prev) => ({ ...prev, [cardIdx]: true }));

                            // Hold for EXACTLY 1 SECOND (1000ms) then advance to Step 1
                            autoAdvanceTimerRef.current = setTimeout(() => {
                                if (thisToken === activePlayTokenRef.current) {
                                    playSlide(cardIdx, 1);
                                }
                            }, 1000);
                        }
                    }
                }, 1000);
            };

            // Play Question Audio first
            try {
                const soundObj = await playGoogleTTS(currentSlide.audioText, langCode, speed, speaker);
                if (thisToken !== activePlayTokenRef.current) {
                    if (soundObj?.unloadAsync) soundObj.unloadAsync().catch(() => {});
                    return;
                }
                soundRef.current = soundObj;

                if (soundObj?.setOnPlaybackStatusUpdate) {
                    let hasTriggered = false;
                    soundObj.setOnPlaybackStatusUpdate((status) => {
                        if (thisToken !== activePlayTokenRef.current) return;
                        if (status.didJustFinish && !hasTriggered) {
                            hasTriggered = true;
                            // Question fully read -> NOW START 3s COUNTDOWN!
                            startCountdown();
                        }
                    });
                } else {
                    const estDuration = Math.max(2500, currentSlide.audioText.length * 80);
                    setTimeout(() => {
                        if (thisToken === activePlayTokenRef.current) {
                            startCountdown();
                        }
                    }, estDuration);
                }
            } catch (e) {
                startCountdown();
            }

            return;
        }

        // 2. If this is an Explanation Step Slide (Slide 1, 2, 3...):
        try {
            const soundObj = await playGoogleTTS(currentSlide.audioText, langCode, speed, speaker);
            if (thisToken !== activePlayTokenRef.current) {
                if (soundObj?.unloadAsync) soundObj.unloadAsync().catch(() => {});
                return;
            }
            soundRef.current = soundObj;

            // When audio finishes, advance to next slide
            if (soundObj?.setOnPlaybackStatusUpdate) {
                soundObj.setOnPlaybackStatusUpdate((status) => {
                    if (thisToken !== activePlayTokenRef.current) return;
                    if (status.didJustFinish) {
                        const nextSlideIdx = slideIdx + 1;
                        if (nextSlideIdx < slides.length) {
                            autoAdvanceTimerRef.current = setTimeout(() => {
                                if (thisToken === activePlayTokenRef.current) {
                                    playSlide(cardIdx, nextSlideIdx);
                                }
                            }, 400);
                        } else {
                            handleCardAllSlidesComplete(cardIdx);
                        }
                    }
                });
            } else {
                const duration = Math.max(4000, currentSlide.text.length * 90);
                autoAdvanceTimerRef.current = setTimeout(() => {
                    if (thisToken === activePlayTokenRef.current) {
                        const nextSlideIdx = slideIdx + 1;
                        if (nextSlideIdx < slides.length) {
                            playSlide(cardIdx, nextSlideIdx);
                        } else {
                            handleCardAllSlidesComplete(cardIdx);
                        }
                    }
                }, duration);
            }
        } catch (e) {
            console.warn('[Slide] Play error:', e);
        }
    };

    const handleCardAllSlidesComplete = (cardIdx) => {
        if (isAutoPlayingRef.current) {
            const nextCard = cardIdx + 1;
            if (nextCard < revisionDataRef.current.length) {
                autoAdvanceTimerRef.current = setTimeout(() => {
                    scrollToIndex(nextCard);
                }, 1200);
            }
        }
    };

    /* ---------------- USER SELECTS OPTION ---------------- */
    const handleSelectOption = (cardIdx, choice, correctChoice) => {
        clearAllTimers();

        const isCorrect = choice === correctChoice;
        if (isCorrect) {
            HapticManager.triggerSuccess();
            setShowConfetti(true);
            setTimeout(() => setShowConfetti(false), 2500);
        } else {
            HapticManager.triggerWarning();
        }

        setUserGuesses((prev) => ({ ...prev, [cardIdx]: choice }));
        setRevealedPolls((prev) => ({ ...prev, [cardIdx]: true }));

        // Hold revealed answer for 1 second then smoothly move forward to Step 1
        autoAdvanceTimerRef.current = setTimeout(() => {
            playSlide(cardIdx, 1);
        }, 1000);
    };

    /* ---------------- INSTAGRAM STORY TAP NAVIGATION ---------------- */
    const handleStoryTap = (cardIdx, event) => {
        const touchX = event.nativeEvent.locationX;
        const item = revisionDataRef.current[cardIdx];
        if (!item) return;

        const { slides } = buildCardSlides(item);
        const currentSlide = cardSlideIndex[cardIdx] || 0;

        // Tapped Left 30%: Go back to previous slide
        if (touchX < SCREEN_WIDTH * 0.3) {
            if (currentSlide > 0) {
                HapticManager.triggerLight();
                playSlide(cardIdx, currentSlide - 1);
            }
        }
        // Tapped Right 30%: Skip forward to next slide
        else if (touchX > SCREEN_WIDTH * 0.7) {
            if (currentSlide + 1 < slides.length) {
                HapticManager.triggerLight();
                playSlide(cardIdx, currentSlide + 1);
            } else {
                if (cardIdx + 1 < revisionDataRef.current.length) {
                    scrollToIndex(cardIdx + 1);
                }
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
            const activeIdx = currentIndexRef.current;
            playSlide(activeIdx, cardSlideIndex[activeIdx] || 0);
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
        try {
            await AsyncStorage.setItem(`@quick_rev_mastered_${chapterId}`, JSON.stringify(updated));
        } catch (e) {}
    };

    const scrollToIndex = (index) => {
        if (index >= 0 && index < revisionData.length) {
            currentIndexRef.current = index;
            setCurrentIndex(index);
            flatListRef.current?.scrollToIndex({ index, animated: true });
            playSlide(index, 0);
        }
    };

    const handleMomentumScrollEnd = (e) => {
        const offsetY = e.nativeEvent.contentOffset.y;
        const newIndex = Math.round(offsetY / containerHeight);

        if (isProgrammaticScrollRef.current) {
            isProgrammaticScrollRef.current = false;
            return;
        }

        if (newIndex >= 0 && newIndex < revisionDataRef.current.length) {
            const changed = newIndex !== currentIndexRef.current;
            currentIndexRef.current = newIndex;
            setCurrentIndex(newIndex);
            if (changed) {
                playSlide(newIndex, 0);
            }
        }
    };

    // Auto-play the first card on load
    useEffect(() => {
        if (revisionData.length > 0 && currentIndex === 0) {
            playSlide(0, 0);
        }
    }, [revisionData]);

    /* ---------------- RENDER REEL CARD ---------------- */
    const renderCard = ({ item, index }) => {
        const cardData = buildCardSlides(item);
        const { cleanQ, optA, optB, correct, cardLang, slides } = cardData;

        const isCurrentCard = currentIndex === index;
        const currentSlideIdx = cardSlideIndex[index] || 0;
        const activeSlide = slides[currentSlideIdx] || slides[0];

        const userGuess = userGuesses[index];
        const isPollRevealed = Boolean(revealedPolls[index]);
        const correctChoice = correct;
        const secondsLeft = pollTimeRemaining[index]; // null = listening, 3..0 = countdown
        const isMastered = !!masteredCards[index];

        return (
            <View style={[styles.cardWrapper, { height: containerHeight }]}>
                {/* IMMERSIVE BLACKBOARD CARD CONTAINER */}
                <View style={styles.reelCard}>
                    {/* 1. INSTAGRAM STORY SEGMENTED PROGRESS BARS */}
                    <View style={styles.storySegmentsRow}>
                        {slides.map((_, sIdx) => (
                            <View
                                key={sIdx}
                                style={[
                                    styles.storySegment,
                                    {
                                        backgroundColor:
                                            sIdx < currentSlideIdx
                                                ? '#34d399'
                                                : sIdx === currentSlideIdx
                                                ? '#38bdf8'
                                                : 'rgba(255,255,255,0.2)',
                                    },
                                ]}
                            />
                        ))}
                    </View>

                    {/* 2. TOP MINIMAL BAR */}
                    <View style={styles.topMinimalBar}>
                        <View style={styles.cardCounterPill}>
                            <Text style={styles.cardCounterText}>
                                {index + 1} / {revisionData.length}
                            </Text>
                        </View>

                        <View style={styles.topControlsRow}>
                            <TouchableOpacity onPress={toggleSpeed} style={styles.speedChip}>
                                <Text style={styles.speedChipText}>{speechSpeed.toFixed(2)}x</Text>
                            </TouchableOpacity>

                            <TouchableOpacity onPress={() => toggleMastered(index)} style={styles.starBtn}>
                                <Ionicons
                                    name={isMastered ? 'star' : 'star-outline'}
                                    size={18}
                                    color={isMastered ? '#fbbf24' : '#94a3b8'}
                                />
                            </TouchableOpacity>
                        </View>
                    </View>

                    {/* 3. PERMANENT TOP QUESTION CARD (CONSTANT ACROSS ALL SLIDES!) */}
                    <View style={styles.constantQuestionCard}>
                        <View style={styles.questionBadgeRow}>
                            <Ionicons name="help-circle" size={15} color="#38bdf8" />
                            <Text style={styles.questionBadgeLabel}>
                                {cardLang === 'mr' ? 'प्रश्न' : 'QUESTION'}
                            </Text>
                        </View>
                        <Text style={styles.constantQuestionText}>
                            {cleanQ}
                        </Text>
                    </View>

                    {/* 4. DYNAMIC LOWER STAGE (Story interaction: Tap left=back, tap right=next) */}
                    <TouchableWithoutFeedback onPress={(e) => handleStoryTap(index, e)}>
                        <View style={styles.dynamicStageContainer}>
                            {/* SLIDE 0: 2-OPTION GUESS CHALLENGE */}
                            {activeSlide.type === 'poll' ? (
                                <View style={styles.pollStageWrap}>
                                    <View style={styles.optionsWrap}>
                                        {/* OPTION A */}
                                        <TouchableOpacity
                                            activeOpacity={0.88}
                                            disabled={isPollRevealed}
                                            onPress={() => handleSelectOption(index, 'A', correctChoice)}
                                            style={[
                                                styles.cleanOptionBtn,
                                                isPollRevealed && correctChoice === 'A' && styles.optionBtnCorrect,
                                                isPollRevealed && userGuess === 'A' && correctChoice !== 'A' && styles.optionBtnWrong,
                                            ]}
                                        >
                                            <View
                                                style={[
                                                    styles.letterCircle,
                                                    isPollRevealed && correctChoice === 'A' && { backgroundColor: '#10b981' },
                                                    isPollRevealed && userGuess === 'A' && correctChoice !== 'A' && { backgroundColor: '#ef4444' },
                                                ]}
                                            >
                                                <Text style={styles.letterText}>A</Text>
                                            </View>
                                            <Text style={styles.optionText} numberOfLines={3}>
                                                {optA}
                                            </Text>
                                            {isPollRevealed && correctChoice === 'A' && (
                                                <Ionicons name="checkmark-circle" size={24} color="#34d399" />
                                            )}
                                            {isPollRevealed && userGuess === 'A' && correctChoice !== 'A' && (
                                                <Ionicons name="close-circle" size={24} color="#ef4444" />
                                            )}
                                        </TouchableOpacity>

                                        {/* OPTION B */}
                                        <TouchableOpacity
                                            activeOpacity={0.88}
                                            disabled={isPollRevealed}
                                            onPress={() => handleSelectOption(index, 'B', correctChoice)}
                                            style={[
                                                styles.cleanOptionBtn,
                                                isPollRevealed && correctChoice === 'B' && styles.optionBtnCorrect,
                                                isPollRevealed && userGuess === 'B' && correctChoice !== 'B' && styles.optionBtnWrong,
                                            ]}
                                        >
                                            <View
                                                style={[
                                                    styles.letterCircle,
                                                    isPollRevealed && correctChoice === 'B' && { backgroundColor: '#10b981' },
                                                    isPollRevealed && userGuess === 'B' && correctChoice !== 'B' && { backgroundColor: '#ef4444' },
                                                ]}
                                            >
                                                <Text style={styles.letterText}>B</Text>
                                            </View>
                                            <Text style={styles.optionText} numberOfLines={3}>
                                                {optB}
                                            </Text>
                                            {isPollRevealed && correctChoice === 'B' && (
                                                <Ionicons name="checkmark-circle" size={24} color="#34d399" />
                                            )}
                                            {isPollRevealed && userGuess === 'B' && correctChoice !== 'B' && (
                                                <Ionicons name="close-circle" size={24} color="#ef4444" />
                                            )}
                                        </TouchableOpacity>
                                    </View>

                                    {/* STATUS / TIMER BADGE */}
                                    <View style={styles.timerBadge}>
                                        {secondsLeft === null ? (
                                            <>
                                                <Ionicons name="volume-medium-outline" size={16} color="#38bdf8" />
                                                <Text style={styles.timerBadgeText}>
                                                    {cardLang === 'mr' ? '🎙️ प्रश्न ऐका...' : '🎙️ Listen to question...'}
                                                </Text>
                                            </>
                                        ) : (
                                            <>
                                                <Ionicons name="timer-outline" size={16} color={secondsLeft <= 1 ? '#f87171' : '#38bdf8'} />
                                                <Text style={[styles.timerBadgeText, secondsLeft <= 1 && { color: '#f87171' }]}>
                                                    {isPollRevealed
                                                        ? (cardLang === 'mr' ? '✓ बरोबर उत्तर' : '✓ Correct Answer')
                                                        : `${secondsLeft} ${cardLang === 'mr' ? 'सेकंदात उत्तर निवडा' : 'seconds to answer'}`}
                                                </Text>
                                            </>
                                        )}
                                    </View>
                                </View>
                            ) : (
                                /* SLIDE 1, 2, 3: STEP EXPLANATION STAGE */
                                <View style={styles.stepStageWrap}>
                                    <View
                                        style={[
                                            styles.stepContentCard,
                                            { borderColor: activeSlide.accent || '#34d399' },
                                        ]}
                                    >
                                        <View style={styles.stepHeaderLine}>
                                            <View style={[styles.stepIconSmall, { backgroundColor: `${activeSlide.accent}20` }]}>
                                                <Ionicons name={activeSlide.icon || 'bulb-outline'} size={18} color={activeSlide.accent || '#34d399'} />
                                            </View>
                                            <Text style={[styles.stepTitleLabel, { color: activeSlide.accent || '#34d399' }]}>
                                                {activeSlide.stepNum === 1
                                                    ? (cardLang === 'mr' ? 'पायरी १: सोपी संकल्पना' : 'STEP 1: KEY CONCEPT')
                                                    : activeSlide.stepNum === 2
                                                    ? (cardLang === 'mr' ? 'पायरी २: शास्त्रीय नियम व सूत्र' : 'STEP 2: RULE & FORMULA')
                                                    : (cardLang === 'mr' ? 'पायरी ३: परीक्षेची टीप' : 'STEP 3: EXAM TIP')}
                                            </Text>
                                        </View>

                                        {/* PURE STEP EXPLANATION TEXT */}
                                        <Text style={styles.stepExplanationText}>
                                            {activeSlide.text}
                                        </Text>
                                    </View>

                                    {/* BOTTOM STEP HINT */}
                                    <Text style={styles.bottomTapHint}>
                                        {currentSlideIdx + 1 === slides.length
                                            ? (cardLang === 'mr' ? 'पुढच्या प्रश्नासाठी वर स्वाइप करा ⬆️' : 'Swipe up for next question ⬆️')
                                            : (cardLang === 'mr' ? 'उजवीकडे टॅप करा ⏭️' : 'Tap right to continue ⏭️')}
                                    </Text>
                                </View>
                            )}
                        </View>
                    </TouchableWithoutFeedback>

                    {/* 5. FOOTER CONTROLS */}
                    <View style={styles.bottomBarRow}>
                        <TouchableOpacity
                            onPress={() => playSlide(index, currentSlideIdx)}
                            style={styles.replaySlideBtn}
                        >
                            <Ionicons name="volume-high" size={18} color="#34d399" />
                            <Text style={styles.replaySlideText}>
                                {cardLang === 'mr' ? 'पुन्हा ऐका' : 'Replay Voice'}
                            </Text>
                        </TouchableOpacity>

                        {/* NEXT REEL BUTTON */}
                        {index + 1 < revisionData.length && (
                            <TouchableOpacity
                                onPress={() => scrollToIndex(index + 1)}
                                style={styles.nextReelBtn}
                            >
                                <Text style={styles.nextReelBtnText}>
                                    {cardLang === 'mr' ? 'पुढचा प्रश्न ⏭️' : 'Next Question ⏭️'}
                                </Text>
                            </TouchableOpacity>
                        )}
                    </View>
                </View>
            </View>
        );
    };

    /* ---------------- MAIN SCREEN ---------------- */
    if (loading) {
        return (
            <View style={styles.center}>
                <ActivityIndicator size="large" color="#10b981" />
                <Text style={styles.loadingText}>Loading Quick Revision...</Text>
            </View>
        );
    }

    if (error) {
        return (
            <View style={styles.center}>
                <MaterialCommunityIcons name="alert-circle-outline" size={48} color="#ef4444" style={{ marginBottom: 12 }} />
                <Text style={styles.errorText}>{error}</Text>
                <TouchableOpacity onPress={() => loadRevision(true)} style={styles.retryBtn}>
                    <Text style={{ color: '#fff', fontWeight: 'bold' }}>Retry</Text>
                </TouchableOpacity>
            </View>
        );
    }

    const currentTheme = REEL_THEMES[currentIndex % REEL_THEMES.length];
    const masteredCount = Object.values(masteredCards).filter(Boolean).length;

    return (
        <LinearGradient colors={currentTheme.darkGrad} style={styles.container}>
            <SafeAreaView style={styles.safeArea}>
                {/* CONFETTI CELEBRATION */}
                {showConfetti && (
                    <ConfettiCannon
                        count={40}
                        origin={{ x: SCREEN_WIDTH / 2, y: SCREEN_HEIGHT / 3 }}
                        fadeOut={true}
                        autoStart={true}
                    />
                )}

                {/* HEADER */}
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
                        <Ionicons name="arrow-back" size={22} color="#ffffff" />
                    </TouchableOpacity>

                    <View style={styles.headerTitleWrap}>
                        <Text style={styles.headerTitle} numberOfLines={1}>
                            {chapterName || 'Quick Revision'}
                        </Text>
                        <Text style={styles.headerSubtitle}>
                            ⭐ {masteredCount}/{revisionData.length} Mastered • Natural Teacher Voice
                        </Text>
                    </View>

                    {/* AUTO PLAY TOGGLE */}
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
                            color={isAutoPlaying ? '#ef4444' : '#34d399'}
                        />
                        <Text style={[styles.autoPlayBtnText, { color: isAutoPlaying ? '#ef4444' : '#34d399' }]}>
                            {isAutoPlaying ? 'Auto ON' : 'Auto'}
                        </Text>
                    </TouchableOpacity>
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
    container: { flex: 1, backgroundColor: '#061a11' },
    safeArea: { flex: 1, paddingTop: STATUSBAR_HEIGHT },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#061a11' },
    loadingText: { marginTop: 12, fontSize: 14, fontWeight: '600', color: '#94a3b8' },
    errorText: { color: '#ffffff', fontSize: 16, marginBottom: 20, textAlign: 'center', paddingHorizontal: 30 },
    retryBtn: { paddingHorizontal: 24, paddingVertical: 12, borderRadius: 24, backgroundColor: '#10b981' },

    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingVertical: 8,
        borderBottomWidth: 1,
        borderBottomColor: 'rgba(255,255,255,0.08)',
        backgroundColor: '#061a11',
    },
    backBtn: {
        width: 38,
        height: 38,
        borderRadius: 19,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: 'rgba(255,255,255,0.12)',
    },
    headerTitleWrap: { flex: 1, marginLeft: 12 },
    headerTitle: { fontSize: 15, fontWeight: 'bold', color: '#ffffff' },
    headerSubtitle: { fontSize: 11, color: '#34d399', marginTop: 1 },

    autoPlayBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 10,
        paddingVertical: 5,
        borderRadius: 14,
        borderWidth: 1,
        borderColor: 'rgba(52,211,153,0.3)',
        backgroundColor: 'rgba(52,211,153,0.12)',
    },
    autoPlayBtnText: { fontSize: 12, fontWeight: '700', marginLeft: 4 },

    listContainer: { flex: 1 },
    cardWrapper: {
        width: SCREEN_WIDTH,
        paddingHorizontal: 12,
        paddingVertical: 6,
        justifyContent: 'center',
    },
    reelCard: {
        flex: 1,
        borderRadius: 22,
        backgroundColor: '#0b261b',
        borderWidth: 1.5,
        borderColor: 'rgba(52,211,153,0.3)',
        paddingHorizontal: 16,
        paddingTop: 12,
        paddingBottom: 10,
        justifyContent: 'space-between',
    },

    /* Story Segment Bars */
    storySegmentsRow: {
        flexDirection: 'row',
        gap: 6,
        marginBottom: 8,
    },
    storySegment: {
        flex: 1,
        height: 3.5,
        borderRadius: 2,
    },

    /* Top Bar */
    topMinimalBar: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 8,
    },
    cardCounterPill: {
        backgroundColor: 'rgba(255,255,255,0.1)',
        paddingHorizontal: 10,
        paddingVertical: 3,
        borderRadius: 12,
    },
    cardCounterText: { color: '#cbd5e1', fontSize: 12, fontWeight: '700' },
    topControlsRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
    speedChip: {
        backgroundColor: 'rgba(255,255,255,0.1)',
        paddingHorizontal: 8,
        paddingVertical: 3,
        borderRadius: 10,
    },
    speedChipText: { color: '#34d399', fontSize: 12, fontWeight: '700' },
    starBtn: { padding: 4 },

    /* PERMANENT TOP QUESTION CARD */
    constantQuestionCard: {
        backgroundColor: 'rgba(255,255,255,0.06)',
        borderRadius: 16,
        paddingHorizontal: 16,
        paddingVertical: 14,
        borderWidth: 1,
        borderColor: 'rgba(56,189,248,0.25)',
        marginBottom: 10,
    },
    questionBadgeRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 5,
        marginBottom: 6,
    },
    questionBadgeLabel: {
        fontSize: 11,
        fontWeight: 'bold',
        color: '#38bdf8',
        letterSpacing: 0.5,
    },
    constantQuestionText: {
        fontSize: 17,
        lineHeight: 25,
        fontWeight: 'bold',
        color: '#ffffff',
    },

    /* DYNAMIC STAGE CONTAINER */
    dynamicStageContainer: {
        flex: 1,
        justifyContent: 'center',
    },

    /* Poll Stage */
    pollStageWrap: {
        flex: 1,
        justifyContent: 'center',
        gap: 12,
    },
    optionsWrap: {
        gap: 12,
    },
    cleanOptionBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#123828',
        borderRadius: 16,
        paddingHorizontal: 16,
        paddingVertical: 13,
        borderWidth: 1.5,
        borderColor: 'rgba(255,255,255,0.15)',
    },
    optionBtnCorrect: {
        backgroundColor: '#064e3b',
        borderColor: '#10b981',
    },
    optionBtnWrong: {
        backgroundColor: '#7f1d1d',
        borderColor: '#ef4444',
    },
    letterCircle: {
        width: 30,
        height: 30,
        borderRadius: 15,
        backgroundColor: 'rgba(255,255,255,0.15)',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 12,
    },
    letterText: { color: '#ffffff', fontSize: 14, fontWeight: 'bold' },
    optionText: { flex: 1, color: '#f1f5f9', fontSize: 15, fontWeight: '600' },

    timerBadge: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        alignSelf: 'center',
        backgroundColor: 'rgba(0,0,0,0.3)',
        paddingHorizontal: 14,
        paddingVertical: 6,
        borderRadius: 16,
        marginTop: 8,
    },
    timerBadgeText: { color: '#38bdf8', fontSize: 13, fontWeight: '700', marginLeft: 6 },

    /* Step Explanation Stage */
    stepStageWrap: {
        flex: 1,
        justifyContent: 'space-between',
        paddingVertical: 6,
    },
    stepContentCard: {
        backgroundColor: '#103324',
        borderRadius: 18,
        padding: 18,
        borderWidth: 1.5,
        marginVertical: 8,
    },
    stepHeaderLine: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 8,
        marginBottom: 12,
    },
    stepIconSmall: {
        width: 28,
        height: 28,
        borderRadius: 14,
        justifyContent: 'center',
        alignItems: 'center',
    },
    stepTitleLabel: {
        fontSize: 12,
        fontWeight: 'bold',
        letterSpacing: 0.5,
    },
    stepExplanationText: {
        fontSize: 17,
        lineHeight: 27,
        fontWeight: 'bold',
        color: '#ffffff',
    },
    bottomTapHint: {
        textAlign: 'center',
        color: '#94a3b8',
        fontSize: 12,
        fontWeight: '600',
    },

    /* Bottom Bar */
    bottomBarRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        borderTopWidth: 1,
        borderTopColor: 'rgba(255,255,255,0.08)',
        paddingTop: 8,
    },
    replaySlideBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 12,
        backgroundColor: 'rgba(52,211,153,0.12)',
        gap: 6,
    },
    replaySlideText: { color: '#34d399', fontSize: 12, fontWeight: '700' },
    nextReelBtn: {
        paddingHorizontal: 14,
        paddingVertical: 6,
        borderRadius: 12,
        backgroundColor: '#10b981',
    },
    nextReelBtnText: { color: '#ffffff', fontSize: 13, fontWeight: 'bold' },
});

export default QuickRevisionScreen;
