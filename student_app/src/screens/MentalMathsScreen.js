import React, { useState, useEffect, useRef, useCallback } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, ActivityIndicator,
    Animated, Vibration, SafeAreaView, Platform, Dimensions
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { useTheme } from '../context/ThemeContext';
import { MathQuestionGenerator } from '../utils/MathQuestionGenerator';
import axios from 'axios';
import { API_URL } from '../api/config';
import { Ionicons } from '@expo/vector-icons';

const { width } = Dimensions.get('window');

const MentalMathsScreen = ({ navigation, user }) => {
    const { theme, isDarkMode } = useTheme();
    const [loading, setLoading] = useState(true);
    const [gameState, setGameState] = useState('START');

    const [level, setLevel] = useState(1);
    const [currentQuestion, setCurrentQuestion] = useState(null);
    const [questionCount, setQuestionCount] = useState(1);
    const [score, setScore] = useState(0);
    const [totalSets, setTotalSets] = useState(0);

    // Timer State
    const [timeLeft, setTimeLeft] = useState(60);
    // NEW: We store the max time for the current level to show in the UI text
    const [maxTimeForLevel, setMaxTimeForLevel] = useState(60);
    const timerRef = useRef(null);

    // Animations
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const shakeAnim = useRef(new Animated.Value(0)).current;
    const scaleAnim = useRef(new Animated.Value(1)).current;
    const [feedback, setFeedback] = useState(null);

    const TOTAL_QUESTIONS = 10;
    const PASSING_SCORE = 8;

    // ====================================================
    // 1. NEW LOGIC: Determine Timer based on Level
    // ====================================================
    const getDurationForLevel = (currentLevel) => {
        // Levels 1 to 5 = 30 Seconds
        if (currentLevel <= 5) {
            return 30;
        }
        // Level 6 and above = 60 Seconds
        else {
            return 60;
        }
    };

    useEffect(() => {
        loadProgress();
        return () => clearInterval(timerRef.current);
    }, []);

    // Timer Interval Logic
    useEffect(() => {
        if (gameState === 'PLAYING') {
            timerRef.current = setInterval(() => {
                setTimeLeft((prev) => {
                    if (prev <= 1) {
                        clearInterval(timerRef.current);
                        finishSet(score, true);
                        return 0;
                    }
                    return prev - 1;
                });
            }, 1000);
        } else {
            clearInterval(timerRef.current);
        }
        return () => clearInterval(timerRef.current);
    }, [gameState, score]);

    const loadProgress = async () => {
        try {
            const response = await axios.get(`${API_URL}/mental_math_get_progress.php?user_id=${user.user_id}`);
            if (response.data.status === 'success') {
                const data = response.data.data;
                const userLevel = parseInt(data.level) || 1;

                setLevel(userLevel);
                setTotalSets(parseInt(data.total_sets_completed) || 0);

                // Update the "Rule" text immediately based on loaded level
                setMaxTimeForLevel(getDurationForLevel(userLevel));
            }
        } catch (error) {
            console.log('Local progress used due to network error');
        } finally {
            setLoading(false);
        }
    };

    const startNewSet = useCallback(() => {
        setScore(0);
        setQuestionCount(1);

        // ====================================================
        // 2. APPLY LOGIC: Set Timer dynamically when game starts
        // ====================================================
        const duration = getDurationForLevel(level);
        setTimeLeft(duration);
        setMaxTimeForLevel(duration); // Keep track for UI display

        setGameState('PLAYING');
        generateNextQuestion();
    }, [level]);

    const generateNextQuestion = () => {
        fadeAnim.setValue(0);
        shakeAnim.setValue(0);
        scaleAnim.setValue(1);

        const strategy = MathQuestionGenerator.getStrategyForLevel(level);
        const question = MathQuestionGenerator.generate(strategy);
        setCurrentQuestion(question);
        setFeedback(null);
    };

    // ... (Animations and Answer Handling remain the same) ...
    const triggerShake = () => {
        Animated.sequence([
            Animated.timing(shakeAnim, { toValue: 10, duration: 50, useNativeDriver: true }),
            Animated.timing(shakeAnim, { toValue: -10, duration: 50, useNativeDriver: true }),
            Animated.timing(shakeAnim, { toValue: 10, duration: 50, useNativeDriver: true }),
            Animated.timing(shakeAnim, { toValue: 0, duration: 50, useNativeDriver: true })
        ]).start();
    };

    const triggerPop = () => {
        Animated.sequence([
            Animated.timing(scaleAnim, { toValue: 1.2, duration: 100, useNativeDriver: true }),
            Animated.timing(scaleAnim, { toValue: 1, duration: 100, useNativeDriver: true })
        ]).start();
    };

    const handleAnswer = (selectedOption) => {
        if (!currentQuestion || feedback) return;

        const isCorrect = selectedOption === currentQuestion.answer;
        setFeedback(isCorrect ? 'correct' : 'wrong');

        let newScore = score;
        if (isCorrect) {
            newScore = score + 1;
            setScore(newScore);
            triggerPop();
        } else {
            Vibration.vibrate(100);
            triggerShake();
        }

        Animated.sequence([
            Animated.timing(fadeAnim, { toValue: 1, duration: 150, useNativeDriver: true }),
            Animated.delay(600),
            Animated.timing(fadeAnim, { toValue: 0, duration: 150, useNativeDriver: true })
        ]).start(() => {
            if (questionCount < TOTAL_QUESTIONS) {
                setQuestionCount(prev => prev + 1);
                generateNextQuestion();
            } else {
                finishSet(newScore, false);
            }
        });
    };

    const finishSet = async (finalScore, isTimeUp = false) => {
        clearInterval(timerRef.current);
        setGameState('RESULT');

        const passed = finalScore >= PASSING_SCORE;
        const newLevel = passed ? level + 1 : level;

        if (passed) {
            setLevel(newLevel);
            setTotalSets(prev => prev + 1);
            // Update max time for the 'Next' level so UI updates if they hit replay
            setMaxTimeForLevel(getDurationForLevel(newLevel));
        }

        try {
            await axios.post(`${API_URL}/mental_math_save_progress.php`, {
                user_id: user.user_id,
                level: newLevel,
                score: finalScore
            });
        } catch (error) {
            console.error('Sync failed', error);
        }
    };

    if (loading) {
        return (
            <View style={[styles.container, { backgroundColor: theme.background, justifyContent: 'center' }]}>
                <ActivityIndicator size="large" color={theme.primary} />
            </View>
        );
    }

    const getGradientColors = () => {
        if (gameState === 'RESULT') return isDarkMode ? ['#1e1b4b', '#312e81'] : ['#a8edea', '#fed6e3'];
        if (feedback === 'wrong') return ['#7f1d1d', '#991b1b'];
        if (feedback === 'correct') return ['#14532d', '#166534'];
        return isDarkMode ? ['#0f172a', '#1e293b'] : ['#4facfe', '#00f2fe'];
    };

    return (
        <View style={styles.container}>
            <LinearGradient
                colors={getGradientColors()}
                style={styles.background}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
            />

            <SafeAreaView style={styles.safeArea}>
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                        <Ionicons name="arrow-back" size={24} color="#fff" />
                    </TouchableOpacity>
                    <View style={styles.headerBadge}>
                        <Ionicons name="trophy" size={16} color="#FFD700" style={{ marginRight: 4 }} />
                        <Text style={styles.headerBadgeText}>Level {level}</Text>
                    </View>
                </View>

                {/* Level Controls */}
                <View style={styles.levelControls}>
                    <TouchableOpacity
                        style={styles.controlBtn}
                        onPress={() => { setLevel(1); setGameState('START'); }}
                    >
                        <Ionicons name="refresh-circle" size={20} color="#fff" />
                        <Text style={styles.controlBtnText}>Level 1</Text>
                    </TouchableOpacity>

                    {level > 1 && (
                        <TouchableOpacity
                            style={styles.controlBtn}
                            onPress={() => { setLevel(level - 1); setGameState('START'); }}
                        >
                            <Ionicons name="chevron-back-circle" size={20} color="#fff" />
                            <Text style={styles.controlBtnText}>Previous Level</Text>
                        </TouchableOpacity>
                    )}
                </View>

                <View style={styles.content}>
                    {gameState === 'START' && (
                        <Animated.View style={[styles.card, { transform: [{ scale: 1 }] }]}>
                            <View style={styles.iconCircle}>
                                <Text style={styles.emoji}>🧠</Text>
                            </View>
                            <Text style={[styles.title, { color: '#333' }]}>Speed Math</Text>

                            {/* ====================================================
                                3. UI UPDATE: Show dynamic time in text
                               ==================================================== */}
                            <Text style={[styles.subtitle, { color: '#666' }]}>
                                Solve {TOTAL_QUESTIONS} questions in {maxTimeForLevel} seconds!
                            </Text>

                            <View style={styles.rulesContainer}>
                                <Text style={styles.ruleText}>⏱️ {maxTimeForLevel} Seconds</Text>
                                <Text style={styles.ruleText}>✅ Need {PASSING_SCORE} correct to pass</Text>
                            </View>

                            <TouchableOpacity
                                style={[styles.primaryButton, { backgroundColor: theme.primary }]}
                                onPress={startNewSet}
                                activeOpacity={0.8}
                            >
                                <Text style={styles.primaryButtonText}>Start Challenge</Text>
                                <Ionicons name="play" size={20} color="#fff" style={{ marginLeft: 8 }} />
                            </TouchableOpacity>
                        </Animated.View>
                    )}

                    {gameState === 'PLAYING' && currentQuestion && (
                        <View style={styles.gameWrapper}>
                            <View style={styles.gameHeader}>
                                <View style={styles.scorePill}>
                                    <Text style={styles.scoreText}>Score: {score}</Text>
                                </View>

                                <View style={[styles.timerPill, timeLeft <= 10 && styles.timerUrgent]}>
                                    <Ionicons name="time" size={18} color="#fff" style={{ marginRight: 5 }} />
                                    <Text style={styles.timerText}>{timeLeft}s</Text>
                                </View>

                                <View style={styles.scorePill}>
                                    <Text style={styles.scoreText}>{questionCount}/{TOTAL_QUESTIONS}</Text>
                                </View>
                            </View>

                            <Animated.View
                                style={[
                                    styles.questionCard,
                                    { transform: [{ translateX: shakeAnim }, { scale: scaleAnim }] }
                                ]}
                            >
                                <Text style={styles.questionText}>{currentQuestion.question}</Text>

                                <Animated.View style={[
                                    styles.feedbackOverlay,
                                    {
                                        opacity: fadeAnim,
                                        backgroundColor: feedback === 'correct' ? 'rgba(34, 197, 94, 0.9)' : 'rgba(239, 68, 68, 0.9)'
                                    }
                                ]}>
                                    <Ionicons name={feedback === 'correct' ? "checkmark-circle" : "close-circle"} size={40} color="#fff" />
                                    <Text style={styles.feedbackText}>{feedback === 'correct' ? 'Great!' : 'Oops!'}</Text>
                                </Animated.View>
                            </Animated.View>

                            <View style={styles.optionsGrid}>
                                {currentQuestion.options.map((option, index) => (
                                    <TouchableOpacity
                                        key={index}
                                        style={styles.optionButton}
                                        onPress={() => handleAnswer(option)}
                                        disabled={feedback !== null}
                                        activeOpacity={0.7}
                                    >
                                        <Text style={styles.optionText}>{option}</Text>
                                    </TouchableOpacity>
                                ))}
                            </View>
                        </View>
                    )}

                    {gameState === 'RESULT' && (
                        <View style={styles.card}>
                            <View style={styles.iconCircle}>
                                <Text style={styles.emoji}>{score >= PASSING_SCORE ? '🎉' : '⏰'}</Text>
                            </View>
                            <Text style={styles.title}>
                                {timeLeft === 0 && score < PASSING_SCORE ? "Time's Up!" :
                                    score >= PASSING_SCORE ? 'Awesome!' : 'Try Again!'}
                            </Text>

                            <Text style={styles.bigScore}>{score}<Text style={styles.totalScore}>/{TOTAL_QUESTIONS}</Text></Text>

                            <Text style={styles.resultMessage}>
                                {score >= PASSING_SCORE
                                    ? `Promoted to Level ${level}!`
                                    : `You need ${PASSING_SCORE} correct in ${maxTimeForLevel}s.`}
                            </Text>

                            <View style={styles.buttonRow}>
                                {score >= PASSING_SCORE ? (
                                    <TouchableOpacity
                                        style={[styles.primaryButton, { backgroundColor: theme.primary }]}
                                        onPress={startNewSet}
                                    >
                                        <Text style={styles.primaryButtonText}>Next Level</Text>
                                        <Ionicons name="arrow-forward" size={20} color="#fff" style={{ marginLeft: 8 }} />
                                    </TouchableOpacity>
                                ) : (
                                    <TouchableOpacity
                                        style={[styles.primaryButton, { backgroundColor: theme.primary }]}
                                        onPress={startNewSet}
                                    >
                                        <Text style={styles.primaryButtonText}>Replay Level</Text>
                                        <Ionicons name="refresh" size={20} color="#fff" style={{ marginLeft: 8 }} />
                                    </TouchableOpacity>
                                )}
                            </View>
                        </View>
                    )}
                </View>
            </SafeAreaView>
        </View>
    );
};

// Styles remain exactly the same as before
const styles = StyleSheet.create({
    container: { flex: 1 },
    background: { position: 'absolute', left: 0, right: 0, top: 0, bottom: 0 },
    safeArea: { flex: 1 },
    header: {
        flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
        paddingHorizontal: 20, paddingVertical: 10, marginTop: Platform.OS === 'android' ? 30 : 0,
    },
    backButton: {
        width: 40, height: 40, backgroundColor: 'rgba(255,255,255,0.2)',
        borderRadius: 20, justifyContent: 'center', alignItems: 'center',
    },
    headerBadge: {
        flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.3)',
        paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20,
        borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)',
    },
    headerBadgeText: { color: '#fff', fontWeight: 'bold', fontSize: 14 },
    levelControls: {
        flexDirection: 'row', justifyContent: 'center', gap: 10, marginBottom: 10,
        paddingHorizontal: 20
    },
    controlBtn: {
        flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.3)', // Darker background for visibility
        paddingHorizontal: 16, paddingVertical: 10, borderRadius: 25, gap: 6,
        borderWidth: 1, borderColor: 'rgba(255,255,255,0.4)', // Distinct border
        shadowColor: "#000", shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.2, shadowRadius: 3, elevation: 3
    },
    controlBtnText: { color: '#fff', fontSize: 13, fontWeight: 'bold', letterSpacing: 0.5 },
    content: { flex: 1, padding: 20, justifyContent: 'center' },
    card: {
        backgroundColor: 'rgba(255, 255, 255, 0.95)', borderRadius: 30, padding: 30,
        alignItems: 'center', shadowColor: "#000", shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.2, shadowRadius: 20, elevation: 10,
    },
    iconCircle: {
        width: 80, height: 80, backgroundColor: '#f0f9ff', borderRadius: 40,
        justifyContent: 'center', alignItems: 'center', marginBottom: 20,
    },
    emoji: { fontSize: 40 },
    title: { fontSize: 26, fontWeight: '800', marginBottom: 5, color: '#1e293b' },
    subtitle: { fontSize: 16, textAlign: 'center', marginBottom: 15, color: '#64748b' },
    rulesContainer: {
        backgroundColor: '#f1f5f9', padding: 15, borderRadius: 12, marginBottom: 25, width: '100%',
    },
    ruleText: { fontSize: 14, color: '#475569', textAlign: 'center', marginBottom: 5, fontWeight: '600' },
    primaryButton: {
        flexDirection: 'row', width: '100%', paddingVertical: 18, borderRadius: 20,
        justifyContent: 'center', alignItems: 'center', shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.2, shadowRadius: 8, elevation: 4,
    },
    primaryButtonText: { color: '#fff', fontSize: 18, fontWeight: 'bold' },
    buttonRow: { flexDirection: 'row', width: '100%', marginTop: 20 },
    outlineButton: {
        paddingHorizontal: 25, paddingVertical: 16, borderRadius: 20, borderWidth: 2,
        justifyContent: 'center', alignItems: 'center',
    },
    outlineButtonText: { fontSize: 16, fontWeight: '700' },
    gameWrapper: { flex: 1, justifyContent: 'center' },
    gameHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 },
    scorePill: { backgroundColor: 'rgba(255,255,255,0.2)', paddingHorizontal: 15, paddingVertical: 8, borderRadius: 15 },
    scoreText: { color: '#fff', fontWeight: 'bold', fontSize: 16 },
    timerPill: {
        flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.4)',
        paddingHorizontal: 20, paddingVertical: 8, borderRadius: 20,
        borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)'
    },
    timerUrgent: { backgroundColor: '#ef4444', borderColor: '#ef4444' },
    timerText: { color: '#fff', fontWeight: 'bold', fontSize: 18 },
    questionCard: {
        backgroundColor: '#fff', borderRadius: 30, height: 200, justifyContent: 'center',
        alignItems: 'center', marginBottom: 30, shadowColor: '#000',
        shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.15, shadowRadius: 20,
        elevation: 10, overflow: 'hidden',
    },
    questionText: { fontSize: 48, fontWeight: '900', color: '#1e293b' },
    feedbackOverlay: {
        ...StyleSheet.absoluteFillObject, justifyContent: 'center', alignItems: 'center', zIndex: 10,
    },
    feedbackText: { color: '#fff', fontSize: 24, fontWeight: 'bold', marginTop: 10 },
    optionsGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', gap: 15 },
    optionButton: {
        width: (width - 55) / 2, backgroundColor: 'rgba(255,255,255,0.9)', paddingVertical: 25,
        borderRadius: 25, alignItems: 'center', shadowColor: '#000', shadowOpacity: 0.05,
        shadowRadius: 10, elevation: 3, marginBottom: 15,
    },
    optionText: { fontSize: 28, fontWeight: 'bold', color: '#333' },
    bigScore: { fontSize: 60, fontWeight: '900', color: '#333', marginVertical: 10 },
    totalScore: { fontSize: 24, color: '#888', fontWeight: '500' },
    resultMessage: { fontSize: 16, color: '#666', marginBottom: 20 },
});

export default MentalMathsScreen;