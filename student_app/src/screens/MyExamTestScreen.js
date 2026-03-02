import React, { useState, useEffect } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    StatusBar,
    Platform,
    BackHandler,
    Dimensions
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { LinearGradient } from 'expo-linear-gradient';
import { Image } from 'react-native';
import { BASE_URL } from '../api/config';
import MathJaxWebView from '../components/MathJaxWebView';
import { Ionicons } from '@expo/vector-icons';

import { useTheme } from '../context/ThemeContext';

const { width } = Dimensions.get('window');

const MyExamTestScreen = ({ navigation, route }) => {
    const { questions, totalQuestions, subjectName } = route.params;
    const { theme, isDarkMode } = useTheme();

    const [currentIndex, setCurrentIndex] = useState(0);
    const [selectedAnswers, setSelectedAnswers] = useState({});
    const [showExplanation, setShowExplanation] = useState({});
    const [showResults, setShowResults] = useState(false);
    const [timeElapsed, setTimeElapsed] = useState(0);
    const [isTimerRunning, setIsTimerRunning] = useState(true);

    // Timer effect
    useEffect(() => {
        let interval;
        if (isTimerRunning && !showResults) {
            interval = setInterval(() => {
                setTimeElapsed(prev => prev + 1);
            }, 1000);
        }
        return () => clearInterval(interval);
    }, [isTimerRunning, showResults]);

    // Prevent back button during test
    useEffect(() => {
        const backHandler = BackHandler.addEventListener('hardwareBackPress', () => {
            if (!showResults) {
                // Return true to prevent default back behavior
                return true;
            }
            return false;
        });
        return () => backHandler.remove();
    }, [showResults]);

    const formatTime = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    };

    const handleAnswer = (optionKey) => {
        if (selectedAnswers[currentIndex]) return;

        setSelectedAnswers(prev => ({
            ...prev,
            [currentIndex]: optionKey
        }));

        setShowExplanation(prev => ({
            ...prev,
            [currentIndex]: true
        }));
    };

    const nextQuestion = () => {
        if (currentIndex < questions.length - 1) {
            setCurrentIndex(prev => prev + 1);
        }
    };

    const previousQuestion = () => {
        if (currentIndex > 0) {
            setCurrentIndex(prev => prev - 1);
        }
    };

    const submitTest = () => {
        setIsTimerRunning(false);
        setShowResults(true);
    };

    const calculateResults = () => {
        let correct = 0;
        let incorrect = 0;
        let unanswered = 0;

        questions.forEach((q, index) => {
            const userAnswer = selectedAnswers[index];
            if (!userAnswer) {
                unanswered++;
            } else if (userAnswer === q.correct_answer) {
                correct++;
            } else {
                incorrect++;
            }
        });

        return { correct, incorrect, unanswered };
    };

    const decodeHtml = (html) => {
        if (!html) return '';
        return html
            .replace(/&quot;/g, '"')
            .replace(/&apos;/g, "'")
            .replace(/&#039;/g, "'")
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&nbsp;/g, ' ');
    };

    const getOptionStyle = (optionKey) => {
        const userAnswer = selectedAnswers[currentIndex];
        const currentQuestion = questions[currentIndex];
        const hasAnswered = !!userAnswer;

        if (hasAnswered) {
            const isCorrect = optionKey === currentQuestion.correct_answer;
            const isUserAnswer = optionKey === userAnswer;

            if (isCorrect) return [styles.optionButton, styles.correctOption];
            if (isUserAnswer && !isCorrect) return [styles.optionButton, styles.wrongOption];
        }

        return styles.optionButton;
    };

    const renderResults = () => {
        const { correct, incorrect, unanswered } = calculateResults();
        const percentage = ((correct / questions.length) * 100).toFixed(1);

        return (
            <ScrollView style={styles.resultsContainer} contentContainerStyle={styles.resultsContent} showsVerticalScrollIndicator={false}>
                <View style={styles.resultsHeader}>
                    <Text style={styles.resultsEmoji}>
                        {percentage >= 75 ? '🏆' : percentage >= 50 ? '👍' : '💪'}
                    </Text>
                    <Text style={styles.resultsTitle}>Test Completed!</Text>
                    <Text style={styles.resultsScore}>{correct} / {questions.length}</Text>
                    <Text style={styles.resultsPercentage}>You scored {percentage}%</Text>
                </View>

                <View style={styles.statsGrid}>
                    <View style={[styles.statCard, { backgroundColor: '#dcfce7', borderColor: '#bbf7d0', borderWidth: 1 }]}>
                        <Ionicons name="checkmark-circle" size={24} color="#16a34a" style={{ marginBottom: 4 }} />
                        <Text style={[styles.statNumber, { color: '#16a34a' }]}>{correct}</Text>
                        <Text style={[styles.statLabel, { color: '#15803d' }]}>Correct</Text>
                    </View>
                    <View style={[styles.statCard, { backgroundColor: '#fee2e2', borderColor: '#fecaca', borderWidth: 1 }]}>
                        <Ionicons name="close-circle" size={24} color="#dc2626" style={{ marginBottom: 4 }} />
                        <Text style={[styles.statNumber, { color: '#dc2626' }]}>{incorrect}</Text>
                        <Text style={[styles.statLabel, { color: '#b91c1c' }]}>Incorrect</Text>
                    </View>
                    <View style={[styles.statCard, { backgroundColor: '#fef3c7', borderColor: '#fde68a', borderWidth: 1 }]}>
                        <Ionicons name="remove-circle" size={24} color="#d97706" style={{ marginBottom: 4 }} />
                        <Text style={[styles.statNumber, { color: '#d97706' }]}>{unanswered}</Text>
                        <Text style={[styles.statLabel, { color: '#b45309' }]}>Skipped</Text>
                    </View>
                </View>

                <View style={styles.timeCard}>
                    <Ionicons name="time-outline" size={28} color="#64748b" style={{ marginRight: 12 }} />
                    <View>
                        <Text style={styles.timeLabel}>Total Time Taken</Text>
                        <Text style={styles.timeValue}>{formatTime(timeElapsed)}</Text>
                    </View>
                </View>

                <Text style={styles.reviewTitle}>Review Answers</Text>

                {questions.map((question, index) => {
                    const userAnswer = selectedAnswers[index];
                    const isCorrect = userAnswer === question.correct_answer;
                    const wasAnswered = !!userAnswer;

                    return (
                        <View key={index} style={styles.reviewCard}>
                            <View style={styles.reviewHeader}>
                                <Text style={styles.reviewQuestionNumber}>Question {index + 1}</Text>
                                <View style={[
                                    styles.reviewBadge,
                                    !wasAnswered ? styles.reviewBadgeSkipped :
                                        isCorrect ? styles.reviewBadgeCorrect : styles.reviewBadgeWrong
                                ]}>
                                    <Text style={[styles.reviewBadgeText,
                                    !wasAnswered ? { color: '#b45309' } :
                                        isCorrect ? { color: '#15803d' } : { color: '#b91c1c' }
                                    ]}>
                                        {!wasAnswered ? 'Skipped' : isCorrect ? 'Correct' : 'Wrong'}
                                    </Text>
                                </View>
                            </View>

                            <MathJaxWebView
                                content={decodeHtml(question.question)}
                                textColor="#0f172a"
                                fontSize="15px"
                            />

                            {wasAnswered && !isCorrect && (
                                <View style={[styles.answerInfo, { marginTop: 12 }]}>
                                    <Text style={styles.answerInfoLabel}>Your Answer:</Text>
                                    <View style={[styles.answerBox, { backgroundColor: '#fee2e2', borderColor: '#fecaca' }]}>
                                        <MathJaxWebView
                                            content={decodeHtml(question[`option_${userAnswer}`])}
                                            textColor="#b91c1c"
                                            fontSize="14px"
                                        />
                                    </View>
                                </View>
                            )}

                            <View style={[styles.answerInfo, !wasAnswered || isCorrect ? { marginTop: 12 } : null]}>
                                <Text style={styles.answerInfoLabel}>Correct Answer:</Text>
                                <View style={[styles.answerBox, { backgroundColor: '#dcfce7', borderColor: '#bbf7d0' }]}>
                                    <MathJaxWebView
                                        content={decodeHtml(question[`option_${question.correct_answer}`])}
                                        textColor="#15803d"
                                        fontSize="14px"
                                    />
                                </View>
                            </View>

                            {question.explanation && (
                                <View style={styles.explanationBox}>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 8 }}>
                                        <Ionicons name="bulb" size={18} color="#0369a1" style={{ marginRight: 6 }} />
                                        <Text style={styles.explanationTitle}>Explanation</Text>
                                    </View>
                                    <MathJaxWebView
                                        content={decodeHtml(question.explanation)}
                                        textColor="#0369a1"
                                        fontSize="14px"
                                    />
                                </View>
                            )}
                        </View>
                    );
                })}

                <TouchableOpacity
                    style={styles.homeButtonWrapper}
                    onPress={() => navigation.navigate('Home')}
                    activeOpacity={0.8}
                >
                    <LinearGradient colors={['#4f46e5', '#6366f1']} style={styles.homeButtonGradient}>
                        <Text style={styles.homeButtonText}>Back to Home</Text>
                    </LinearGradient>
                </TouchableOpacity>
            </ScrollView>
        );
    };

    if (showResults) {
        return (
            <View style={styles.mainWrapper}>
                <StatusBar barStyle="dark-content" backgroundColor="transparent" translucent={true} />
                <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                    {renderResults()}
                </SafeAreaView>
            </View>
        );
    }

    const currentQuestion = questions[currentIndex];
    const progress = ((currentIndex + 1) / questions.length) * 100;

    return (
        <View style={styles.mainWrapper}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />

            <LinearGradient colors={['#7c3aed', '#6d28d9']} style={styles.headerGradient}>
                <SafeAreaView edges={['top']} style={styles.headerSafe}>
                    <View style={styles.testHeader}>
                        <View style={styles.testHeaderTop}>
                            <View style={{ flex: 1, marginRight: 10 }}>
                                <Text style={styles.testSubject} numberOfLines={1}>{subjectName}</Text>
                            </View>
                            <View style={styles.timerBadge}>
                                <Ionicons name="timer-outline" size={16} color="white" style={{ marginRight: 4 }} />
                                <Text style={styles.timer}>{formatTime(timeElapsed)}</Text>
                            </View>
                        </View>
                        <View style={styles.progressBarContainer}>
                            <View style={[styles.progressBar, { width: `${progress}%` }]} />
                        </View>
                        <Text style={styles.questionCounter}>
                            Question <Text style={{ fontFamily: 'NotoSans-Bold' }}>{currentIndex + 1}</Text> of {questions.length}
                        </Text>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <ScrollView style={styles.container} contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
                <View style={styles.questionCard}>
                    {currentQuestion.image_url ? (
                        <Image
                            source={{ uri: `${BASE_URL}/uploads/${currentQuestion.image_url}` }}
                            style={styles.questionImage}
                            resizeMode="contain"
                        />
                    ) : null}

                    <MathJaxWebView
                        content={`<div style="font-weight: 500; color: #000000;">${decodeHtml(currentQuestion.question)}</div>`}
                        textColor="#000000"
                        fontSize="17px"
                        backgroundColor="transparent"
                    />
                </View>

                <View style={styles.optionsList}>
                    {['a', 'b', 'c', 'd'].map((opt) => {
                        const isSelected = selectedAnswers[currentIndex] === opt;
                        const isCorrectAnswer = opt === currentQuestion.correct_answer;
                        const hasAnswered = !!selectedAnswers[currentIndex];

                        let gradientColors = isDarkMode ? ['#1e293b', '#334155'] : ['#ffffff', '#f8fafc'];
                        let borderColor = isDarkMode ? '#334155' : '#e2e8f0';
                        const themePrimary = '#7c3aed';
                        const themePrimaryLight = '#c4b5fd';

                        if (hasAnswered) {
                            if (isCorrectAnswer) {
                                gradientColors = ['#ecfdf5', '#d1fae5'];
                                borderColor = '#10b981';
                            } else if (isSelected && !isCorrectAnswer) {
                                gradientColors = ['#fef2f2', '#fee2e2'];
                                borderColor = '#ef4444';
                            }
                        } else if (isSelected) {
                            borderColor = themePrimary;
                            gradientColors = ['#f3e8ff', '#e9d5ff'];
                        }

                        return (
                            <TouchableOpacity
                                key={opt}
                                style={[styles.optionButton, { borderColor }]}
                                onPress={() => handleAnswer(opt)}
                                disabled={!!selectedAnswers[currentIndex]}
                                activeOpacity={0.7}
                            >
                                <LinearGradient colors={gradientColors} style={styles.optionGradient} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }}>
                                    <View style={styles.optionContent} pointerEvents="none">
                                        <View style={[
                                            styles.optionLetterBox,
                                            hasAnswered && isCorrectAnswer ? { backgroundColor: '#10b981' } :
                                                hasAnswered && isSelected ? { backgroundColor: '#ef4444' } :
                                                    { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }
                                        ]}>
                                            <Text style={[
                                                styles.optionText,
                                                hasAnswered && (isCorrectAnswer || isSelected) ? styles.optionTextSelected : { color: isDarkMode ? '#cbd5e1' : '#64748b' }
                                            ]}>
                                                {opt.toUpperCase()}
                                            </Text>
                                        </View>
                                        <View style={{ flex: 1, justifyContent: 'center' }}>
                                            <MathJaxWebView
                                                content={`<div style="font-weight: 500; line-height: 1.4;">${decodeHtml(currentQuestion[`option_${opt}`])}</div>`}
                                                textColor={hasAnswered && (isCorrectAnswer || isSelected) ? '#000000' : (isDarkMode ? '#ffffff' : '#020617')}
                                                fontSize="16px"
                                                backgroundColor="transparent"
                                            />
                                        </View>
                                    </View>
                                </LinearGradient>
                            </TouchableOpacity>
                        );
                    })}
                </View>

                {showExplanation[currentIndex] && (
                    <View style={styles.explanationContainer}>
                        <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 8 }}>
                            {selectedAnswers[currentIndex] === currentQuestion.correct_answer ? (
                                <Ionicons name="checkmark-circle" size={24} color="#16a34a" style={{ marginRight: 8 }} />
                            ) : (
                                <Ionicons name="close-circle" size={24} color="#dc2626" style={{ marginRight: 8 }} />
                            )}
                            <Text style={[styles.explanationTitle, selectedAnswers[currentIndex] === currentQuestion.correct_answer ? { color: '#16a34a' } : { color: '#dc2626' }]}>
                                {selectedAnswers[currentIndex] === currentQuestion.correct_answer ? 'Correct!' : 'Incorrect!'}
                            </Text>
                        </View>
                        {currentQuestion.explanation && (
                            <View style={styles.explanationDetailBox}>
                                <Text style={styles.explanationLabel}>Explanation</Text>
                                <MathJaxWebView
                                    content={decodeHtml(currentQuestion.explanation)}
                                    textColor="#1e293b"
                                    fontSize="14px"
                                />
                            </View>
                        )}
                    </View>
                )}
            </ScrollView>

            <View style={styles.navigationBar}>
                <TouchableOpacity
                    style={[styles.navButtonWrapper, currentIndex === 0 && styles.navButtonDisabled]}
                    onPress={previousQuestion}
                    disabled={currentIndex === 0}
                    activeOpacity={0.8}
                >
                    <View style={styles.prevButton}>
                        <Ionicons name="arrow-back" size={20} color="#64748b" style={{ marginRight: 6 }} />
                        <Text style={styles.prevButtonText}>Previous</Text>
                    </View>
                </TouchableOpacity>

                {currentIndex === questions.length - 1 ? (
                    <TouchableOpacity style={[styles.navButtonWrapper, { flex: 1.5 }]} onPress={submitTest} activeOpacity={0.8}>
                        <LinearGradient colors={['#10b981', '#059669']} style={styles.navButtonGradient}>
                            <Text style={styles.submitButtonText}>Submit Test</Text>
                            <Ionicons name="checkmark-done" size={20} color="white" style={{ marginLeft: 6 }} />
                        </LinearGradient>
                    </TouchableOpacity>
                ) : (
                    <TouchableOpacity style={[styles.navButtonWrapper, { flex: 1.5 }]} onPress={nextQuestion} activeOpacity={0.8}>
                        <LinearGradient colors={['#7c3aed', '#6d28d9']} style={styles.navButtonGradient}>
                            <Text style={styles.navButtonTextWhite}>Next Question</Text>
                            <Ionicons name="arrow-forward" size={20} color="white" style={{ marginLeft: 6 }} />
                        </LinearGradient>
                    </TouchableOpacity>
                )}
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    mainWrapper: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    headerGradient: {
        paddingBottom: 10,
        borderBottomLeftRadius: 16,
        borderBottomRightRadius: 16,
    },
    headerSafe: {
        backgroundColor: 'transparent',
    },
    testHeader: {
        paddingHorizontal: 12,
        paddingTop: 2,
    },
    testHeaderTop: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 8,
    },
    testSubject: {
        fontSize: 16,
        color: 'white',
        fontFamily: 'NotoSans-Bold',
    },
    timerBadge: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'rgba(0,0,0,0.2)',
        paddingHorizontal: 8,
        paddingVertical: 2,
        borderRadius: 12,
    },
    timer: {
        fontSize: 12,
        color: 'white',
        fontFamily: 'NotoSans-Bold',
    },
    progressBarContainer: {
        height: 4,
        backgroundColor: 'rgba(255,255,255,0.25)',
        borderRadius: 2,
        overflow: 'hidden',
        marginBottom: 4,
    },
    progressBar: {
        height: '100%',
        backgroundColor: 'white',
        borderRadius: 2,
    },
    questionCounter: {
        fontSize: 11,
        color: 'rgba(255,255,255,0.9)',
        fontFamily: 'NotoSans-Regular',
        textAlign: 'center',
    },
    container: {
        flex: 1,
    },
    scrollContent: {
        padding: 12,
        paddingBottom: 100,
        marginTop: -10,
    },
    questionCard: {
        backgroundColor: 'white',
        borderRadius: 20,
        padding: 20,
        marginBottom: 16,
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.06,
        shadowRadius: 12,
        elevation: 4,
        borderWidth: 1,
        borderColor: '#e9d5ff',
    },
    questionImage: {
        width: '100%',
        height: 100,
        marginBottom: 8,
        borderRadius: 8,
        backgroundColor: '#f1f5f9',
    },
    optionsList: {
        gap: 8,
    },
    optionButton: {
        borderRadius: 16,
        borderWidth: 1.5,
        shadowColor: '#7c3aed',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.04,
        shadowRadius: 6,
        elevation: 2,
        overflow: 'hidden',
    },
    optionGradient: {
        paddingVertical: 8,
        paddingHorizontal: 10,
    },
    optionContent: {
        flexDirection: 'row',
        alignItems: 'center',
    },
    optionLetterBox: {
        width: 28,
        height: 28,
        borderRadius: 8,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 10,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 3,
        elevation: 2,
    },
    optionText: {
        fontSize: 15,
        fontFamily: 'NotoSans-Bold',
    },
    optionTextSelected: {
        color: 'white',
    },
    explanationContainer: {
        backgroundColor: 'white',
        borderRadius: 20,
        padding: 20,
        marginTop: 24,
        marginBottom: 20,
        borderWidth: 1,
        borderColor: '#e2e8f0',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.05,
        shadowRadius: 10,
        elevation: 3,
    },
    explanationTitle: {
        fontSize: 18,
        fontFamily: 'NotoSans-Bold',
    },
    explanationDetailBox: {
        backgroundColor: '#f8fafc',
        borderRadius: 12,
        padding: 16,
        marginTop: 12,
        borderWidth: 1,
        borderColor: '#f1f5f9',
    },
    explanationLabel: {
        fontSize: 12,
        color: '#64748b',
        fontFamily: 'NotoSans-Bold',
        textTransform: 'uppercase',
        letterSpacing: 0.5,
        marginBottom: 8,
    },
    navigationBar: {
        position: 'absolute',
        bottom: Platform.OS === 'ios' ? 24 : 16,
        left: 16,
        right: 16,
        flexDirection: 'row',
        backgroundColor: '#ffffff',
        paddingHorizontal: 8,
        paddingVertical: 8,
        borderRadius: 24,
        gap: 12,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.15,
        shadowRadius: 16,
        elevation: 8,
        borderWidth: 1,
        borderColor: '#f1f5f9',
    },
    navButtonWrapper: {
        flex: 1,
        borderRadius: 18,
        overflow: 'hidden',
    },
    prevButton: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 14,
        backgroundColor: '#f8fafc',
        borderRadius: 18,
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    prevButtonText: {
        fontSize: 15,
        fontFamily: 'NotoSans-Bold',
        color: '#475569',
    },
    navButtonGradient: {
        flexDirection: 'row',
        paddingVertical: 14,
        alignItems: 'center',
        justifyContent: 'center',
    },
    navButtonDisabled: {
        opacity: 0.5,
    },
    navButtonTextWhite: {
        fontSize: 15,
        fontFamily: 'NotoSans-Bold',
        color: 'white',
    },
    submitButtonText: {
        fontSize: 15,
        fontFamily: 'NotoSans-Bold',
        color: 'white',
    },
    resultsContainer: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    resultsContent: {
        padding: 24,
        paddingTop: Platform.OS === 'ios' ? 20 : 40,
        paddingBottom: 40,
    },
    resultsHeader: {
        alignItems: 'center',
        marginBottom: 32,
        paddingVertical: 10,
    },
    resultsEmoji: {
        fontSize: 70,
        marginBottom: 16,
    },
    resultsTitle: {
        fontSize: 28,
        fontFamily: 'NotoSans-Bold',
        color: '#0f172a',
        marginBottom: 8,
    },
    resultsScore: {
        fontSize: 36,
        fontFamily: 'NotoSans-Bold',
        color: '#4f46e5',
        marginBottom: 4,
    },
    resultsPercentage: {
        fontSize: 16,
        fontFamily: 'NotoSans-Regular',
        color: '#64748b',
    },
    statsGrid: {
        flexDirection: 'row',
        gap: 12,
        marginBottom: 24,
    },
    statCard: {
        flex: 1,
        borderRadius: 16,
        padding: 16,
        alignItems: 'center',
    },
    statNumber: {
        fontSize: 24,
        fontFamily: 'NotoSans-Bold',
        marginBottom: 4,
    },
    statLabel: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
        textTransform: 'uppercase',
    },
    timeCard: {
        flexDirection: 'row',
        backgroundColor: 'white',
        borderRadius: 16,
        padding: 20,
        alignItems: 'center',
        marginBottom: 40,
        borderWidth: 1,
        borderColor: '#e2e8f0',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.03,
        shadowRadius: 10,
        elevation: 2,
    },
    timeLabel: {
        fontSize: 13,
        color: '#64748b',
        fontFamily: 'NotoSans-Regular',
        marginBottom: 4,
    },
    timeValue: {
        fontSize: 24,
        fontFamily: 'NotoSans-Bold',
        color: '#0f172a',
    },
    reviewTitle: {
        fontSize: 20,
        fontFamily: 'NotoSans-Bold',
        color: '#0f172a',
        marginBottom: 20,
    },
    reviewCard: {
        backgroundColor: 'white',
        borderRadius: 20,
        padding: 20,
        marginBottom: 20,
        borderWidth: 1,
        borderColor: '#e2e8f0',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.03,
        shadowRadius: 8,
        elevation: 2,
    },
    reviewHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 16,
        paddingBottom: 16,
        borderBottomWidth: 1,
        borderBottomColor: '#f1f5f9',
    },
    reviewQuestionNumber: {
        fontSize: 15,
        fontFamily: 'NotoSans-Bold',
        color: '#64748b',
    },
    reviewBadge: {
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 8,
    },
    reviewBadgeCorrect: {
        backgroundColor: '#dcfce7',
    },
    reviewBadgeWrong: {
        backgroundColor: '#fee2e2',
    },
    reviewBadgeSkipped: {
        backgroundColor: '#fef3c7',
    },
    reviewBadgeText: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
    },
    answerInfo: {
        marginTop: 0,
    },
    answerInfoLabel: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
        color: '#64748b',
        textTransform: 'uppercase',
        marginBottom: 8,
    },
    answerBox: {
        padding: 12,
        borderRadius: 12,
        borderWidth: 1,
        borderLeftWidth: 4,
    },
    explanationBox: {
        backgroundColor: '#f0f9ff',
        borderRadius: 12,
        padding: 16,
        marginTop: 16,
        borderWidth: 1,
        borderColor: '#e0f2fe',
        borderLeftWidth: 4,
        borderLeftColor: '#0ea5e9',
    },
    explanationTitle: {
        fontSize: 14,
        fontFamily: 'NotoSans-Bold',
        color: '#0369a1',
    },
    homeButtonWrapper: {
        borderRadius: 16,
        overflow: 'hidden',
        marginTop: 10,
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.25,
        shadowRadius: 16,
        elevation: 8,
    },
    homeButtonGradient: {
        paddingVertical: 18,
        alignItems: 'center',
    },
    homeButtonText: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        color: 'white',
        letterSpacing: 0.5,
    },
});

export default MyExamTestScreen;
