import React, { useState, useEffect, useRef, useCallback } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, Animated, Vibration, Dimensions
} from 'react-native';
import { useTheme } from '../../context/ThemeContext';
import { MathQuestionGenerator } from '../../utils/MathQuestionGenerator';
import { Ionicons } from '@expo/vector-icons';
import ConfettiCannon from 'react-native-confetti-cannon';
import { saveMathProgress } from '../../api/mentalMath';
import LevelRoadmap from './LevelRoadmap'; // New Import

const { width } = Dimensions.get('window');

const ClassicMathTab = ({ userLevel, maxLevelAllowed, onProgressUpdate, user, sounds }) => {
    const { theme, isDarkMode } = useTheme();
    const [gameState, setGameState] = useState('START');
    
    // Level selection mode vs Playing mode
    const [currentPlayingLevel, setCurrentPlayingLevel] = useState(userLevel);
    
    const [currentQuestion, setCurrentQuestion] = useState(null);
    const [questionCount, setQuestionCount] = useState(1);
    const [score, setScore] = useState(0);
    const scoreRef = useRef(0); // will be set inline when score changes

    const [timeLeft, setTimeLeft] = useState(60);
    const [maxTimeForLevel, setMaxTimeForLevel] = useState(60);
    const timerRef = useRef(null);

    const fadeAnim = useRef(new Animated.Value(0)).current;
    const shakeAnim = useRef(new Animated.Value(0)).current;
    const scaleAnim = useRef(new Animated.Value(1)).current;
    const [feedback, setFeedback] = useState(null);
    
    const confettiRef = useRef(null);
    const scoreRef    = useRef(0); // Ref to track live score (avoids stale closure in timer)

    const TOTAL_QUESTIONS = 10;
    const PASSING_SCORE = 8; // 8/10 to pass

    const getDurationForLevel = (lvl) => {
        if (lvl <= 5) return 45;
        if (lvl <= 10) return 60;
        return 90;
    };

    const playSound = async (type) => {
        if (!sounds) return;
        try {
            if (type === 'correct' && sounds.correct) await sounds.correct.replayAsync();
            if (type === 'wrong' && sounds.wrong) await sounds.wrong.replayAsync();
            if (type === 'levelup' && sounds.levelup) await sounds.levelup.replayAsync();
        } catch (error) {
            console.log('Error playing sound', error);
        }
    };

    useEffect(() => {
        if (gameState === 'PLAYING') {
            timerRef.current = setInterval(() => {
                setTimeLeft((prev) => {
                    if (prev <= 1) {
                        clearInterval(timerRef.current);
                        // Use scoreRef to avoid stale closure — state 'score' would be wrong here
                        finishSet(scoreRef.current, true);
                        return 0;
                    }
                    return prev - 1;
                });
            }, 1000);
        } else {
            clearInterval(timerRef.current);
        }
        return () => clearInterval(timerRef.current);
    }, [gameState]);

    const startNewSet = useCallback(() => {
        setScore(0);
        scoreRef.current = 0; // reset ref too
        setQuestionCount(1);
        const duration = getDurationForLevel(currentPlayingLevel);
        setTimeLeft(duration);
        setMaxTimeForLevel(duration);
        setGameState('PLAYING');
        generateNextQuestion();
    }, [currentPlayingLevel]);

    const generateNextQuestion = () => {
        fadeAnim.setValue(0);
        shakeAnim.setValue(0);
        scaleAnim.setValue(1);

        const strategy = MathQuestionGenerator.getStrategyForLevel(currentPlayingLevel);
        const question = MathQuestionGenerator.generate(strategy);
        setCurrentQuestion(question);
        setFeedback(null);
    };

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
            scoreRef.current = newScore; // keep ref in sync
            triggerPop();
            playSound('correct');
        } else {
            Vibration.vibrate(100);
            triggerShake();
            playSound('wrong');
        }

        Animated.sequence([
            Animated.timing(fadeAnim, { toValue: 1, duration: 150, useNativeDriver: true }),
            Animated.delay(500),
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
        
        let newLevel = maxLevelAllowed;
        
        if (passed) {
            playSound('levelup');
            if (confettiRef.current) confettiRef.current.start();
            
            // If they beat their highest unlocked level, unlock the next one!
            if (currentPlayingLevel === maxLevelAllowed) {
                newLevel = maxLevelAllowed + 1;
                setCurrentPlayingLevel(newLevel);
                
                // Save it to backend
                try {
                    await saveMathProgress(user.user_id, 'classic', newLevel);
                    // Inform parent so UI updates everywhere
                    onProgressUpdate('classic', newLevel); 
                } catch (e) {
                    console.log('Failed to save progress');
                }
            } else {
                // They just replayed an old level and passed it.
                setCurrentPlayingLevel(currentPlayingLevel + 1);
            }
        } else {
            playSound('wrong');
        }
    };

    return (
        <View style={styles.container}>
            {gameState === 'RESULT' && score >= PASSING_SCORE && (
                <ConfettiCannon
                    Ref={ref => (confettiRef.current = ref)}
                    count={200}
                    origin={{ x: -10, y: 0 }}
                    autoStart={true}
                    fadeOut={true}
                />
            )}

            {gameState === 'START' && (
                <Animated.View style={[styles.card, { transform: [{ scale: 1 }] }]}>
                    <View style={styles.iconCircle}>
                        <Text style={styles.emoji}>🧠</Text>
                    </View>
                    <Text style={styles.title}>Classic Math</Text>

                    <Text style={styles.subtitle}>
                        Level {currentPlayingLevel}
                    </Text>

                    <View style={styles.rulesContainer}>
                        <Text style={styles.ruleText}>⏱️ {getDurationForLevel(currentPlayingLevel)} Seconds</Text>
                        <Text style={styles.ruleText}>✅ Score {PASSING_SCORE}/{TOTAL_QUESTIONS} to pass</Text>
                    </View>

                    <LevelRoadmap
                        totalLevels={30}
                        maxUnlockedLevel={maxLevelAllowed}
                        currentSelectedLevel={currentPlayingLevel}
                        onSelectLevel={setCurrentPlayingLevel}
                        themeColor="#3b82f6"
                    />

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

                    <Animated.View style={[styles.questionCard, { transform: [{ translateX: shakeAnim }, { scale: scaleAnim }] }]}>
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
                            ? `Level ${currentPlayingLevel} Completed!`
                            : `You need ${PASSING_SCORE} correct. Keep trying!`}
                    </Text>

                    <View style={styles.buttonRow}>
                        <TouchableOpacity
                            style={[styles.primaryButton, { backgroundColor: theme.primary, flex: 1, marginRight: 10 }]}
                            onPress={startNewSet}
                        >
                            <Text style={styles.primaryButtonText}>{score >= PASSING_SCORE ? 'Next Level' : 'Replay'}</Text>
                            <Ionicons name={score >= PASSING_SCORE ? "arrow-forward" : "refresh"} size={20} color="#fff" style={{ marginLeft: 8 }} />
                        </TouchableOpacity>
                        
                        <TouchableOpacity
                            style={[styles.primaryButton, { backgroundColor: '#475569', flex: 1 }]}
                            onPress={() => setGameState('START')}
                        >
                            <Text style={styles.primaryButtonText}>Menu</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, padding: 20, justifyContent: 'center' },
    card: {
        backgroundColor: 'rgba(255, 255, 255, 0.85)', // Glass effect
        borderRadius: 30, padding: 25,
        alignItems: 'center', shadowColor: "#3b82f6", shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.15, shadowRadius: 25, elevation: 10,
        borderWidth: 1, borderColor: 'rgba(255,255,255,0.6)',
    },
    iconCircle: {
        width: 70, height: 70, backgroundColor: '#eff6ff', borderRadius: 35,
        justifyContent: 'center', alignItems: 'center', marginBottom: 15,
        shadowColor: '#3b82f6', shadowOpacity: 0.2, shadowRadius: 10, elevation: 5
    },
    emoji: { fontSize: 40 },
    title: { fontSize: 26, fontWeight: '800', marginBottom: 5, color: '#1e293b' },
    subtitle: { fontSize: 18, textAlign: 'center', marginBottom: 15, color: '#3b82f6', fontWeight: 'bold' },
    rulesContainer: {
        backgroundColor: '#f1f5f9', padding: 15, borderRadius: 12, marginBottom: 20, width: '100%',
    },
    ruleText: { fontSize: 14, color: '#475569', textAlign: 'center', marginBottom: 5, fontWeight: '600' },
    primaryButton: {
        flexDirection: 'row', width: '100%', paddingVertical: 18, borderRadius: 20,
        justifyContent: 'center', alignItems: 'center', shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.2, shadowRadius: 8, elevation: 4,
    },
    primaryButtonText: { color: '#fff', fontSize: 18, fontWeight: 'bold' },
    buttonRow: { flexDirection: 'row', width: '100%', marginTop: 20 },
    gameWrapper: { flex: 1, justifyContent: 'center' },
    gameHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 },
    scorePill: { backgroundColor: 'rgba(255,255,255,0.7)', paddingHorizontal: 15, paddingVertical: 8, borderRadius: 15 },
    scoreText: { color: '#1e293b', fontWeight: 'bold', fontSize: 16 },
    timerPill: {
        flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.6)',
        paddingHorizontal: 20, paddingVertical: 8, borderRadius: 20,
    },
    timerUrgent: { backgroundColor: '#ef4444' },
    timerText: { color: '#fff', fontWeight: 'bold', fontSize: 18 },
    questionCard: {
        backgroundColor: '#fff', borderRadius: 30, height: 200, justifyContent: 'center',
        alignItems: 'center', marginBottom: 30, shadowColor: '#000', elevation: 10, overflow: 'hidden',
    },
    questionText: { fontSize: 48, fontWeight: '900', color: '#1e293b' },
    feedbackOverlay: {
        ...StyleSheet.absoluteFillObject, justifyContent: 'center', alignItems: 'center', zIndex: 10,
    },
    feedbackText: { color: '#fff', fontSize: 24, fontWeight: 'bold', marginTop: 10 },
    optionsGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', gap: 15 },
    optionButton: {
        width: (width - 95) / 2, backgroundColor: '#ffffff', paddingVertical: 25, borderRadius: 25,
        alignItems: 'center', elevation: 4, marginBottom: 15,
    },
    optionText: { fontSize: 32, fontWeight: 'bold', color: '#1e293b' },
    bigScore: { fontSize: 60, fontWeight: '900', color: '#333', marginVertical: 10 },
    totalScore: { fontSize: 24, color: '#888', fontWeight: '500' },
    resultMessage: { fontSize: 16, color: '#666', marginBottom: 20, textAlign: 'center' },
});

export default ClassicMathTab;
