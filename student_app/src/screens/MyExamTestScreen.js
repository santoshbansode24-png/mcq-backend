import React, { useState, useEffect } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    ActivityIndicator,
    StatusBar,
    Platform,
    BackHandler
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';

const MyExamTestScreen = ({ navigation, route }) => {
    const { questions, totalQuestions, subjectName } = route.params;

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
                // Show confirmation dialog
                return true; // Prevent default back behavior
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
        // Don't allow changing answer after selection
        if (selectedAnswers[currentIndex]) return;

        setSelectedAnswers(prev => ({
            ...prev,
            [currentIndex]: optionKey
        }));

        // Show explanation immediately
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
            <ScrollView style={styles.resultsContainer} contentContainerStyle={styles.resultsContent}>
                <View style={styles.resultsHeader}>
                    <Text style={styles.resultsEmoji}>
                        {percentage >= 75 ? '🏆' : percentage >= 50 ? '👍' : '💪'}
                    </Text>
                    <Text style={styles.resultsTitle}>Test Completed!</Text>
                    <Text style={styles.resultsScore}>{correct} / {questions.length}</Text>
                    <Text style={styles.resultsPercentage}>{percentage}%</Text>
                </View>

                <View style={styles.statsGrid}>
                    <View style={[styles.statCard, { backgroundColor: '#dcfce7' }]}>
                        <Text style={[styles.statNumber, { color: '#16a34a' }]}>{correct}</Text>
                        <Text style={[styles.statLabel, { color: '#16a34a' }]}>Correct</Text>
                    </View>
                    <View style={[styles.statCard, { backgroundColor: '#fee2e2' }]}>
                        <Text style={[styles.statNumber, { color: '#dc2626' }]}>{incorrect}</Text>
                        <Text style={[styles.statLabel, { color: '#dc2626' }]}>Incorrect</Text>
                    </View>
                    <View style={[styles.statCard, { backgroundColor: '#fef3c7' }]}>
                        <Text style={[styles.statNumber, { color: '#ca8a04' }]}>{unanswered}</Text>
                        <Text style={[styles.statLabel, { color: '#ca8a04' }]}>Skipped</Text>
                    </View>
                </View>

                <View style={styles.timeCard}>
                    <Text style={styles.timeLabel}>Time Taken</Text>
                    <Text style={styles.timeValue}>{formatTime(timeElapsed)}</Text>
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
                                    <Text style={styles.reviewBadgeText}>
                                        {!wasAnswered ? 'Skipped' : isCorrect ? 'Correct' : 'Wrong'}
                                    </Text>
                                </View>
                            </View>

                            <Text style={styles.reviewQuestion}>{decodeHtml(question.question)}</Text>

                            {wasAnswered && !isCorrect && (
                                <View style={styles.answerInfo}>
                                    <Text style={styles.answerInfoLabel}>Your Answer:</Text>
                                    <Text style={styles.answerInfoWrong}>
                                        {decodeHtml(question[`option_${userAnswer}`])}
                                    </Text>
                                </View>
                            )}

                            <View style={styles.answerInfo}>
                                <Text style={styles.answerInfoLabel}>Correct Answer:</Text>
                                <Text style={styles.answerInfoCorrect}>
                                    {decodeHtml(question[`option_${question.correct_answer}`])}
                                </Text>
                            </View>

                            {question.explanation && (
                                <View style={styles.explanationBox}>
                                    <Text style={styles.explanationTitle}>Explanation:</Text>
                                    <Text style={styles.explanationText}>{decodeHtml(question.explanation)}</Text>
                                </View>
                            )}
                        </View>
                    );
                })}

                <TouchableOpacity
                    style={styles.homeButton}
                    onPress={() => navigation.navigate('Home')}
                >
                    <LinearGradient colors={['#00c6ff', '#0072ff']} style={styles.homeButtonGradient}>
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
                <SafeAreaView style={styles.container} edges={['top', 'left', 'right']}>
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

            <LinearGradient colors={['#00c6ff', '#0072ff']} style={styles.headerGradient}>
                <SafeAreaView edges={['top']} style={styles.headerSafe}>
                    <View style={styles.testHeader}>
                        <View style={styles.testHeaderTop}>
                            <Text style={styles.testSubject}>{subjectName}</Text>
                            <Text style={styles.timer}>⏱️ {formatTime(timeElapsed)}</Text>
                        </View>
                        <View style={styles.progressBarContainer}>
                            <View style={[styles.progressBar, { width: `${progress}%` }]} />
                        </View>
                        <Text style={styles.questionCounter}>
                            Question {currentIndex + 1} of {questions.length}
                        </Text>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <ScrollView style={styles.container} contentContainerStyle={styles.scrollContent}>
                <View style={styles.questionCard}>
                    <Text style={styles.questionText}>{decodeHtml(currentQuestion.question)}</Text>
                </View>

                <View style={styles.optionsList}>
                    {['a', 'b', 'c', 'd'].map((opt) => (
                        <TouchableOpacity
                            key={opt}
                            style={getOptionStyle(opt)}
                            onPress={() => handleAnswer(opt)}
                            disabled={!!selectedAnswers[currentIndex]}
                        >
                            <Text style={[
                                styles.optionText,
                                selectedAnswers[currentIndex] && (opt === currentQuestion.correct_answer || opt === selectedAnswers[currentIndex]) && styles.optionTextSelected
                            ]}>
                                {opt.toUpperCase()}. {decodeHtml(currentQuestion[`option_${opt}`])}
                            </Text>
                        </TouchableOpacity>
                    ))}
                </View>

                {/* Show explanation after answer is selected */}
                {showExplanation[currentIndex] && (
                    <View style={styles.explanationContainer}>
                        <Text style={styles.explanationTitle}>
                            {selectedAnswers[currentIndex] === currentQuestion.correct_answer ? '✅ Correct!' : '❌ Wrong!'}
                        </Text>
                        {currentQuestion.explanation && (
                            <>
                                <Text style={styles.explanationLabel}>Explanation:</Text>
                                <Text style={styles.explanationText}>{decodeHtml(currentQuestion.explanation)}</Text>
                            </>
                        )}
                    </View>
                )}
            </ScrollView>

            <View style={styles.navigationBar}>
                <TouchableOpacity
                    style={[styles.navButtonWrapper, currentIndex === 0 && styles.navButtonDisabled]}
                    onPress={previousQuestion}
                    disabled={currentIndex === 0}
                >
                    <LinearGradient
                        colors={['#ef4444', '#b91c1c']}
                        style={styles.navButtonGradient}
                    >
                        <Text style={styles.navButtonTextWhite}>
                            ← Previous
                        </Text>
                    </LinearGradient>
                </TouchableOpacity>

                {currentIndex === questions.length - 1 ? (
                    <TouchableOpacity style={styles.navButtonWrapper} onPress={submitTest}>
                        <LinearGradient colors={['#22c55e', '#15803d']} style={styles.navButtonGradient}>
                            <Text style={styles.submitButtonText}>Submit Test</Text>
                        </LinearGradient>
                    </TouchableOpacity>
                ) : (
                    <TouchableOpacity style={styles.navButtonWrapper} onPress={nextQuestion}>
                        <LinearGradient colors={['#3b82f6', '#2563eb']} style={styles.navButtonGradient}>
                            <Text style={styles.navButtonTextWhite}>Next →</Text>
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
        paddingBottom: 15,
    },
    headerSafe: {
        backgroundColor: 'transparent',
    },
    testHeader: {
        paddingHorizontal: 20,
    },
    testHeaderTop: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 12,
    },
    testSubject: {
        fontSize: 18,
        fontWeight: 'bold',
        color: 'white',
    },
    timer: {
        fontSize: 16,
        fontWeight: '600',
        color: 'white',
    },
    progressBarContainer: {
        height: 6,
        backgroundColor: 'rgba(255,255,255,0.3)',
        borderRadius: 3,
        overflow: 'hidden',
        marginBottom: 8,
    },
    progressBar: {
        height: '100%',
        backgroundColor: 'white',
        borderRadius: 3,
    },
    questionCounter: {
        fontSize: 13,
        color: 'rgba(255,255,255,0.9)',
        textAlign: 'center',
    },
    container: {
        flex: 1,
    },
    scrollContent: {
        padding: 20,
        paddingBottom: 100,
    },
    questionCard: {
        backgroundColor: '#f3e8ff', // Lavender Background
        borderRadius: 16,
        padding: 24,
        marginBottom: 24,
        elevation: 3,
        shadowColor: '#7c3aed', // Purple Shadow
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 8,
        borderTopWidth: 4,
        borderBottomWidth: 4,
        borderRightWidth: 4,
        borderLeftWidth: 4, // Explicitly set to ensure visibility
        borderColor: '#c084fc', // Medium Purple Border
        borderStyle: 'solid',
    },
    questionText: {
        fontSize: 18,
        fontWeight: '700',
        color: '#4c1d95', // Deep Purple Text for contrast
        lineHeight: 28,
    },
    optionsList: {
        gap: 12,
    },
    optionButton: {
        backgroundColor: 'white',
        borderRadius: 12,
        padding: 16,
        borderWidth: 2,
        borderColor: '#e2e8f0',
    },
    selectedOption: {
        borderColor: '#0072ff',
        backgroundColor: '#eff6ff',
    },
    correctOption: {
        borderColor: '#16a34a',
        backgroundColor: '#dcfce7',
    },
    wrongOption: {
        borderColor: '#dc2626',
        backgroundColor: '#fee2e2',
    },
    optionText: {
        fontSize: 15,
        color: '#475569',
        lineHeight: 22,
    },
    optionTextSelected: {
        fontWeight: '600',
        color: '#0f172a',
    },
    explanationContainer: {
        backgroundColor: '#f0fdf4',
        borderRadius: 12,
        padding: 16,
        marginTop: 20,
        marginBottom: 20,
    },
    explanationTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#166534',
        marginBottom: 8,
    },
    explanationLabel: {
        fontSize: 14,
        fontWeight: '600',
        color: '#166534',
        marginBottom: 4,
    },
    explanationText: {
        fontSize: 14,
        color: '#166534',
        lineHeight: 20,
    },
    navigationBar: {
        position: 'absolute',
        bottom: 0,
        left: 0,
        right: 0,
        flexDirection: 'row',
        backgroundColor: 'white',
        paddingHorizontal: 20,
        paddingVertical: 15,
        paddingBottom: Platform.OS === 'ios' ? 50 : 40, // Increased to prevent overlap
        borderTopWidth: 1,
        borderTopColor: '#e2e8f0',
        gap: 12,
    },
    navButtonWrapper: {
        flex: 1,
        borderRadius: 12,
        overflow: 'hidden',
    },
    navButtonGradient: {
        paddingVertical: 14,
        alignItems: 'center',
        justifyContent: 'center',
    },
    navButtonDisabled: {
        opacity: 0.5,
    },
    navButtonTextWhite: {
        fontSize: 15,
        fontWeight: 'bold',
        color: 'white',
    },
    submitButtonText: {
        fontSize: 15,
        fontWeight: 'bold',
        color: 'white',
    },
    resultsContainer: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    resultsContent: {
        padding: 20,
        paddingBottom: 40,
    },
    resultsHeader: {
        alignItems: 'center',
        marginBottom: 30,
        paddingVertical: 20,
    },
    resultsEmoji: {
        fontSize: 60,
        marginBottom: 15,
    },
    resultsTitle: {
        fontSize: 24,
        fontWeight: 'bold',
        color: '#0f172a',
        marginBottom: 10,
    },
    resultsScore: {
        fontSize: 32,
        fontWeight: 'bold',
        color: '#0072ff',
        marginBottom: 5,
    },
    resultsPercentage: {
        fontSize: 20,
        fontWeight: '600',
        color: '#64748b',
    },
    statsGrid: {
        flexDirection: 'row',
        gap: 10,
        marginBottom: 20,
    },
    statCard: {
        flex: 1,
        borderRadius: 12,
        padding: 16,
        alignItems: 'center',
    },
    statNumber: {
        fontSize: 28,
        fontWeight: 'bold',
        marginBottom: 4,
    },
    statLabel: {
        fontSize: 13,
        fontWeight: '600',
    },
    timeCard: {
        backgroundColor: 'white',
        borderRadius: 12,
        padding: 16,
        alignItems: 'center',
        marginBottom: 30,
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    timeLabel: {
        fontSize: 14,
        color: '#64748b',
        marginBottom: 4,
    },
    timeValue: {
        fontSize: 24,
        fontWeight: 'bold',
        color: '#0f172a',
    },
    reviewTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#0f172a',
        marginBottom: 15,
    },
    reviewCard: {
        backgroundColor: 'white',
        borderRadius: 12,
        padding: 16,
        marginBottom: 16,
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    reviewHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 12,
    },
    reviewQuestionNumber: {
        fontSize: 14,
        fontWeight: '600',
        color: '#64748b',
    },
    reviewBadge: {
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 6,
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
        fontWeight: '600',
    },
    reviewQuestion: {
        fontSize: 15,
        fontWeight: '600',
        color: '#0f172a',
        marginBottom: 12,
        lineHeight: 22,
    },
    answerInfo: {
        marginBottom: 10,
    },
    answerInfoLabel: {
        fontSize: 13,
        fontWeight: '600',
        color: '#64748b',
        marginBottom: 4,
    },
    answerInfoCorrect: {
        fontSize: 14,
        color: '#16a34a',
        fontWeight: '500',
    },
    answerInfoWrong: {
        fontSize: 14,
        color: '#dc2626',
        fontWeight: '500',
    },
    explanationBox: {
        backgroundColor: '#f0fdf4',
        borderRadius: 8,
        padding: 12,
        marginTop: 8,
    },
    explanationTitle: {
        fontSize: 13,
        fontWeight: '600',
        color: '#166534',
        marginBottom: 4,
    },
    explanationText: {
        fontSize: 14,
        color: '#166534',
        lineHeight: 20,
    },
    homeButton: {
        borderRadius: 12,
        overflow: 'hidden',
        marginTop: 20,
    },
    homeButtonGradient: {
        paddingVertical: 16,
        alignItems: 'center',
    },
    homeButtonText: {
        fontSize: 16,
        fontWeight: 'bold',
        color: 'white',
    },
});

export default MyExamTestScreen;
