import React, { useState, useEffect, useCallback, useMemo, useRef, memo } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    FlatList,
    StatusBar,
    Platform,
    BackHandler,
    Dimensions,
    Alert,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';
import { LinearGradient } from 'expo-linear-gradient';
import { Image } from 'react-native';
import { API_URL, BASE_URL } from '../api/config';
import MathJaxWebView from '../components/MathJaxWebView';
import { Ionicons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';

const { width } = Dimensions.get('window');

// ─────────────────────────────────────────────────────────────────────────────
// Helpers (defined outside to avoid recreation every render)
// ─────────────────────────────────────────────────────────────────────────────

const LATEX_RE = /(\$[^$]+\$|\\\(|\\\[|\\frac|\\sqrt|\\sum|\\int|\\alpha|\\beta|\\gamma|\\delta|\\theta|\\pi|\\sigma|\\omega|\\infty|\\times|\\div|\\pm|\\leq|\\geq|\\neq|\\approx|<[^>]+>)/;

/** Returns true if the string contains LaTeX or HTML markup. */
const hasMarkup = (str) => !!str && LATEX_RE.test(str);

const decodeHtml = (html) => {
    if (!html) return '';
    let decoded = html
        .replace(/&quot;/g, '"')
        .replace(/&apos;/g, "'")
        .replace(/&#039;/g, "'")
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&nbsp;/g, ' ');
        
    // STRIP simple tags to aggressively avoid heavy WebViews for plain text
    decoded = decoded
        .replace(/<p[^>]*>/gi, '')
        .replace(/<\/p>/gi, '\n')
        .replace(/<div[^>]*>/gi, '')
        .replace(/<\/div>/gi, '\n')
        .replace(/<br\s*[\/]?>/gi, '\n')
        .replace(/<span[^>]*>/gi, '')
        .replace(/<\/span>/gi, '')
        .trim();
        
    return decoded;
};

const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

// ─────────────────────────────────────────────────────────────────────────────
// Isolated Timer — lives in its own component so its 1-second re-renders
// NEVER touch the question/option components.
// ─────────────────────────────────────────────────────────────────────────────
const ExamTimer = memo(({ running, onTick }) => {
    const [elapsed, setElapsed] = useState(0);
    const elapsedRef = useRef(0);
    const { isDarkMode } = useTheme();

    useEffect(() => {
        if (!running) return;
        const id = setInterval(() => {
            elapsedRef.current += 1;
            setElapsed(elapsedRef.current);
            onTick && onTick(elapsedRef.current);
        }, 1000);
        return () => clearInterval(id);
    }, [running]);

    return (
        <View style={[timerStyles.badge, { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
            <Ionicons name="time-outline" size={16} color={isDarkMode ? '#cbd5e1' : '#64748b'} style={{ marginRight: 4 }} />
            <Text style={[timerStyles.text, { color: isDarkMode ? '#f8fafc' : '#0f172a' }]}>{formatTime(elapsed)}</Text>
        </View>
    );
});

const timerStyles = StyleSheet.create({
    badge: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 10,
        paddingVertical: 6,
        borderRadius: 16,
    },
    text: { fontSize: 13, fontFamily: 'NotoSans-Bold' },
});

// ─────────────────────────────────────────────────────────────────────────────
// SmartText — uses plain <Text> for plain content, WebView only for LaTeX/HTML
// This is the key optimization: most MCQ text is plain, no WebView needed.
// ─────────────────────────────────────────────────────────────────────────────
const SmartText = memo(({ content, textColor, fontSize, fontWeight, backgroundColor, style }) => {
    if (!hasMarkup(content)) {
        // Plain text — no WebView needed, renders instantly
        return (
            <Text style={[
                {
                    color: textColor || '#000',
                    fontSize: parseInt(fontSize) || 16,
                    fontFamily: fontWeight === 'bold' ? 'NotoSans-Bold' : 'NotoSans-Regular',
                    lineHeight: (parseInt(fontSize) || 16) * 1.45,
                    flexShrink: 1,
                },
                style
            ]}>{content}</Text>
        );
    }
    // Has LaTeX or HTML — use WebView
    return (
        <MathJaxWebView
            content={fontWeight === 'bold'
                ? `<div style="font-weight:bold;line-height:1.4;">${content}</div>`
                : content}
            textColor={textColor}
            fontSize={fontSize}
            backgroundColor={backgroundColor}
        />
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// OptionCard — memoized so it only re-renders when this specific option's
// state changes (not when timer ticks or other options are selected)
// ─────────────────────────────────────────────────────────────────────────────
const OptionCard = memo(({ opt, content, hasAnswered, isCorrectAnswer, isSelected, isDarkMode, onPress }) => {
    let cardBg = isDarkMode ? '#1e293b' : '#ffffff';
    let borderColor = isDarkMode ? '#334155' : '#e2e8f0';
    let selectedBorderWidth = 1;

    if (hasAnswered) {
        if (isCorrectAnswer) {
            cardBg = isDarkMode ? 'rgba(16, 185, 129, 0.15)' : '#ecfdf5';
            borderColor = '#10b981';
            selectedBorderWidth = 1.5;
        } else if (isSelected && !isCorrectAnswer) {
            cardBg = isDarkMode ? 'rgba(239, 68, 68, 0.15)' : '#fef2f2';
            borderColor = '#ef4444';
            selectedBorderWidth = 1.5;
        }
    } else if (isSelected) {
        borderColor = '#6366f1';
        cardBg = isDarkMode ? 'rgba(99, 102, 241, 0.15)' : '#eef2ff';
        selectedBorderWidth = 1.5;
    }

    const setOpacity = hasAnswered && !isCorrectAnswer && !isSelected ? 0.6 : 1;

    const letterBg = hasAnswered && isCorrectAnswer ? '#10b981'
        : hasAnswered && isSelected ? '#ef4444'
        : isSelected && !hasAnswered ? '#6366f1'
        : isDarkMode ? '#334155' : '#e0e7ff';
    
    const letterColor = (hasAnswered && (isCorrectAnswer || isSelected)) || (!hasAnswered && isSelected) 
        ? '#ffffff' : (isDarkMode ? '#cbd5e1' : '#4338ca');

    const textColor = isDarkMode ? '#f8fafc' : '#0f172a';

    return (
        <TouchableOpacity
            style={[
                styles.optionButton, 
                { backgroundColor: cardBg, borderColor, borderWidth: selectedBorderWidth, opacity: setOpacity }
            ]}
            onPress={onPress}
            disabled={hasAnswered}
            activeOpacity={0.7}
        >
            <View style={styles.optionContent} pointerEvents="none">
                <View style={[styles.optionLetterBox, { backgroundColor: letterBg }]}>
                    <Text style={[styles.optionText, { color: letterColor }]}>
                        {opt.toUpperCase()}
                    </Text>
                </View>
                <View style={{ flex: 1, justifyContent: 'center' }}>
                    <SmartText
                        content={content}
                        textColor={textColor}
                        fontSize="15px"
                        fontWeight="semibold"
                        backgroundColor="transparent"
                    />
                </View>
            </View>
        </TouchableOpacity>
    );
}, (prev, next) => {
    return prev.hasAnswered === next.hasAnswered &&
           prev.isSelected === next.isSelected &&
           prev.isCorrectAnswer === next.isCorrectAnswer &&
           prev.isDarkMode === next.isDarkMode &&
           prev.content === next.content;
});

// ─────────────────────────────────────────────────────────────────────────────
// ReviewCard — used in results FlatList (replaces inline .map())
// ─────────────────────────────────────────────────────────────────────────────
const ReviewCard = memo(({ question, index, userAnswer }) => {
    const isCorrect = userAnswer === question.correct_answer;
    const wasAnswered = !!userAnswer;
    const decoded = useMemo(() => ({
        question: decodeHtml(question.question),
        userOpt: userAnswer ? decodeHtml(question[`option_${userAnswer}`]) : null,
        correctOpt: decodeHtml(question[`option_${question.correct_answer}`]),
        explanation: question.explanation ? decodeHtml(question.explanation) : null,
    }), [question, userAnswer]);

    return (
        <View style={styles.reviewCard}>
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

            <SmartText content={decoded.question} textColor="#0f172a" fontSize="15px" />

            {wasAnswered && !isCorrect && (
                <View style={[styles.answerInfo, { marginTop: 12 }]}>
                    <Text style={styles.answerInfoLabel}>Your Answer:</Text>
                    <View style={[styles.answerBox, { backgroundColor: '#fee2e2', borderColor: '#fecaca' }]}>
                        <SmartText content={decoded.userOpt} textColor="#b91c1c" fontSize="14px" />
                    </View>
                </View>
            )}

            <View style={[styles.answerInfo, { marginTop: 12 }]}>
                <Text style={styles.answerInfoLabel}>Correct Answer:</Text>
                <View style={[styles.answerBox, { backgroundColor: '#dcfce7', borderColor: '#bbf7d0' }]}>
                    <SmartText content={decoded.correctOpt} textColor="#15803d" fontSize="14px" />
                </View>
            </View>

            {decoded.explanation && (
                <View style={styles.explanationBox}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 8 }}>
                        <Ionicons name="bulb" size={18} color="#0369a1" style={{ marginRight: 6 }} />
                        <Text style={styles.explanationTitle}>Explanation</Text>
                    </View>
                    <SmartText content={decoded.explanation} textColor="#0369a1" fontSize="14px" />
                </View>
            )}
        </View>
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Main Screen
// ─────────────────────────────────────────────────────────────────────────────
const MyExamTestScreen = ({ navigation, route }) => {
    const { questions, totalQuestions, subjectName } = route.params;
    const { isDarkMode } = useTheme();

    const [currentIndex, setCurrentIndex] = useState(0);
    const [selectedAnswers, setSelectedAnswers] = useState({});
    const [showExplanation, setShowExplanation] = useState({});
    const [showResults, setShowResults] = useState(false);
    const [liveExamRanks, setLiveExamRanks] = useState(null);
    const finalTimeRef = useRef(0);

    // Fetch dual rankings if this was a live exam
    useEffect(() => {
        if (showResults && route.params?.update_id) {
            const fetchRankings = async () => {
                try {
                    const userDataStr = await AsyncStorage.getItem('user_data');
                    const userData = userDataStr ? JSON.parse(userDataStr) : null;
                    const userId = userData?.user_id || userData?.id;

                    const res = await axios.get(`${API_URL}/teacher/get_live_exam_leaderboard.php?live_exam_id=${route.params.update_id}`);
                    if (res.data && res.data.status === 'success' && res.data.data) {
                        const leaderboard = res.data.data;
                        const myEntry = leaderboard.find(st => Number(st.id) === Number(userId));
                        if (myEntry) {
                            setLiveExamRanks({
                                examRank: myEntry.rank || myEntry.exam_rank,
                                overallRank: myEntry.overall_rank,
                                totalStudents: leaderboard.length,
                                overallScore: myEntry.overall_score
                            });
                        }
                    }
                } catch (err) {
                    console.log('[Exam] Error fetching live exam ranks:', err);
                }
            };
            fetchRankings();
        }
    }, [showResults, route.params?.update_id]);

    // Back button guard
    useEffect(() => {
        const backHandler = BackHandler.addEventListener('hardwareBackPress', () => {
            if (!showResults) {
                Alert.alert(
                    "Quit Exam?",
                    "Are you sure you want to exit? Your progress will be lost.",
                    [
                        { text: "Stay", style: "cancel" },
                        { text: "Quit", style: "destructive", onPress: () => navigation.goBack() }
                    ]
                );
                return true;
            }
            return false;
        });
        return () => backHandler.remove();
    }, [showResults, navigation]);

    const handleAnswer = useCallback((optionKey) => {
        setSelectedAnswers(prev => {
            if (prev[currentIndex]) return prev;
            return { ...prev, [currentIndex]: optionKey };
        });
        setShowExplanation(prev => ({ ...prev, [currentIndex]: true }));
    }, [currentIndex]);

    const nextQuestion = useCallback(() => {
        setCurrentIndex(prev => Math.min(prev + 1, questions.length - 1));
    }, [questions.length]);

    const previousQuestion = useCallback(() => {
        setCurrentIndex(prev => Math.max(prev - 1, 0));
    }, []);

    const finishStudyTask = useCallback(async () => {
        const { taskId, source } = route.params;
        if (source === 'study_planner' && taskId) {
            try {
                const userDataStr = await AsyncStorage.getItem('user_data');
                const userData = userDataStr ? JSON.parse(userDataStr) : null;
                const userId = userData?.user_id || userData?.id;
                if (userId) {
                    await axios.post(`${API_URL}/update_task_status.php`, {
                        user_id: userId,
                        task_id: taskId,
                        status: 'completed'
                    });
                    Alert.alert("Mission Accomplished! 🛡️", "Your Mega Revision Blitz has been recorded. You've earned 500 XP!");
                }
            } catch (err) {
                console.log('[Exam] Task update failed', err);
            }
        }
    }, [route.params]);

    const submitTest = useCallback(async () => {
        setShowResults(true);
        finishStudyTask();
        
        // Save history to backend
        try {
            const userDataStr = await AsyncStorage.getItem('user_data');
            const userData = userDataStr ? JSON.parse(userDataStr) : null;
            const userId = userData?.user_id || userData?.id;
            
            if (userId) {
                let correct = 0, incorrect = 0, unanswered = 0;
                questions.forEach((q, i) => {
                    const ans = selectedAnswers[i];
                    if (!ans) unanswered++;
                    else if (ans === q.correct_answer) correct++;
                    else incorrect++;
                });

                if (route.params?.update_id) {
                    // Send to teacher's class exam results
                    await axios.post(`${API_URL}/submit_class_exam.php`, {
                        user_id: userId,
                        update_id: route.params.update_id,
                        correct,
                        incorrect,
                        unanswered,
                        total: questions.length,
                        time_seconds: finalTimeRef.current
                    });
                } else {
                    // Save to normal personal exam history
                    await axios.post(`${API_URL}/save_exam_history.php`, {
                        user_id: userId,
                        chapter_ids: route.params?.chapterIds?.join(',') || '',
                        subject_names: subjectName || '',
                        correct,
                        incorrect,
                        unanswered,
                        total: questions.length,
                        time_seconds: finalTimeRef.current
                    });
                }
            }
        } catch (e) {
            console.log('[Exam] Failed to save history', e);
        }
    }, [finishStudyTask, questions, selectedAnswers, subjectName, route.params]);

    const results = useMemo(() => {
        let correct = 0, incorrect = 0, unanswered = 0;
        questions.forEach((q, i) => {
            const ans = selectedAnswers[i];
            if (!ans) unanswered++;
            else if (ans === q.correct_answer) correct++;
            else incorrect++;
        });
        return { correct, incorrect, unanswered };
    }, [selectedAnswers, questions]);

    // Move hooks ABOVE the early return to comply with the Rule of Hooks
    const currentQuestion = questions && questions.length > 0 ? questions[currentIndex] : {};
    const progress = ((currentIndex + 1) / (questions?.length || 1)) * 100;
    const userAnswer = selectedAnswers[currentIndex];
    const hasAnswered = !!userAnswer;

    const decodedQuestion = useMemo(
        () => currentQuestion?.question ? decodeHtml(currentQuestion.question) : '',
        [currentQuestion?.question]
    );
    const decodedOptions = useMemo(() => ({
        a: currentQuestion?.option_a ? decodeHtml(currentQuestion.option_a) : '',
        b: currentQuestion?.option_b ? decodeHtml(currentQuestion.option_b) : '',
        c: currentQuestion?.option_c ? decodeHtml(currentQuestion.option_c) : '',
        d: currentQuestion?.option_d ? decodeHtml(currentQuestion.option_d) : '',
    }), [currentQuestion]);
    const decodedExplanation = useMemo(
        () => currentQuestion?.explanation ? decodeHtml(currentQuestion.explanation) : null,
        [currentQuestion?.explanation]
    );

    // ── Results Screen ──────────────────────────────────────────────────────
    if (showResults) {
        const { correct, incorrect, unanswered } = results;
        const percentage = ((correct / questions.length) * 100).toFixed(1);

        const renderReviewItem = ({ item, index }) => (
            <ReviewCard question={item} index={index} userAnswer={selectedAnswers[index]} />
        );

        const ListHeader = (
            <>
                <View style={styles.resultsHeader}>
                    <Text style={styles.resultsEmoji}>
                        {percentage >= 75 ? '🏆' : percentage >= 50 ? '👍' : '💪'}
                    </Text>
                    <Text style={styles.resultsTitle}>Test Completed!</Text>
                    <Text style={styles.resultsScore}>{correct} / {questions.length}</Text>
                    <Text style={styles.resultsPercentage}>You scored {percentage}%</Text>
                </View>

                {liveExamRanks && (
                    <View style={{backgroundColor: '#1E293B', padding: 16, borderRadius: 16, marginBottom: 16, marginHorizontal: 16, borderWidth: 1, borderColor: '#334155'}}>
                        <Text style={{fontSize: 16, fontWeight: 'bold', color: '#F8FAFC', textAlign: 'center', marginBottom: 12}}>🏆 Your Live Exam Rankings</Text>
                        <View style={{flexDirection: 'row', gap: 12}}>
                            <View style={{flex: 1, backgroundColor: 'rgba(56, 189, 248, 0.15)', padding: 12, borderRadius: 12, alignItems: 'center', borderWidth: 1, borderColor: '#38BDF8'}}>
                                <Text style={{fontSize: 22, fontWeight: '900', color: '#38BDF8'}}>#{liveExamRanks.examRank || '1'}</Text>
                                <Text style={{fontSize: 12, color: '#94A3B8', marginTop: 2, fontWeight: '600'}}>🎯 Live Exam Rank</Text>
                            </View>
                            <View style={{flex: 1, backgroundColor: 'rgba(245, 158, 11, 0.15)', padding: 12, borderRadius: 12, alignItems: 'center', borderWidth: 1, borderColor: '#F59E0B'}}>
                                <Text style={{fontSize: 22, fontWeight: '900', color: '#F59E0B'}}>#{liveExamRanks.overallRank || 'N/A'}</Text>
                                <Text style={{fontSize: 12, color: '#94A3B8', marginTop: 2, fontWeight: '600'}}>👑 Overall Class Rank</Text>
                            </View>
                        </View>
                    </View>
                )}

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
                        <Text style={styles.timeValue}>{formatTime(finalTimeRef.current)}</Text>
                    </View>
                </View>

                <Text style={styles.reviewTitle}>Review Answers</Text>
            </>
        );

        const ListFooter = (
            <TouchableOpacity
                style={styles.homeButtonWrapper}
                onPress={() => navigation.goBack()}
                activeOpacity={0.8}
            >
                <LinearGradient colors={['#4f46e5', '#6366f1']} style={styles.homeButtonGradient}>
                    <Text style={styles.homeButtonText}>Back to List</Text>
                </LinearGradient>
            </TouchableOpacity>
        );

        return (
            <View style={styles.mainWrapper}>
                <StatusBar barStyle="dark-content" backgroundColor="transparent" translucent={true} />
                <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                    <FlatList
                        data={questions}
                        keyExtractor={(_, i) => String(i)}
                        renderItem={renderReviewItem}
                        ListHeaderComponent={ListHeader}
                        ListFooterComponent={ListFooter}
                        contentContainerStyle={styles.resultsContent}
                        initialNumToRender={5}
                        maxToRenderPerBatch={5}
                        windowSize={5}
                        removeClippedSubviews={true}
                    />
                </SafeAreaView>
            </View>
        );
    }

    // ── Exam Screen ─────────────────────────────────────────────────────────

    return (
        <View style={[styles.mainWrapper, { backgroundColor: isDarkMode ? '#0f172a' : '#f0f4f8' }]}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />

            <View style={[styles.headerContainer, { backgroundColor: isDarkMode ? '#1e293b' : '#4f46e5', borderBottomColor: isDarkMode ? '#334155' : '#4338ca' }]}>
                <SafeAreaView edges={['top']} style={styles.headerSafe}>
                    <View style={styles.testHeader}>
                        <View style={styles.testHeaderTop}>
                            <TouchableOpacity onPress={() => navigation.goBack()} style={[styles.headerBackButton, { backgroundColor: isDarkMode ? '#334155' : 'rgba(255,255,255,0.2)' }]}>
                                <Ionicons name="close" size={20} color="#ffffff" />
                            </TouchableOpacity>
                            <View style={{ flex: 1, alignItems: 'center', paddingHorizontal: 10 }}>
                                <Text style={[styles.testSubject, { color: '#ffffff' }]} numberOfLines={1}>{subjectName}</Text>
                                <Text style={[styles.questionCounter, { color: 'rgba(255,255,255,0.8)' }]}>
                                    Question {currentIndex + 1} of {questions.length}
                                </Text>
                            </View>
                            {/* Timer is isolated — its re-renders don't affect the rest */}
                            <ExamTimer
                                running={!showResults}
                                onTick={(t) => { finalTimeRef.current = t; }}
                            />
                        </View>
                        <View style={[styles.progressBarContainer, { backgroundColor: 'rgba(255,255,255,0.3)' }]}>
                            <View style={[styles.progressBar, { width: `${progress}%`, backgroundColor: '#34d399' }]} />
                        </View>
                    </View>
                </SafeAreaView>
            </View>

            <ScrollView style={[styles.container, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]} contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
                <View style={[styles.questionCard, { backgroundColor: isDarkMode ? '#1e293b' : '#ffffff', borderColor: isDarkMode ? '#334155' : '#e2e8f0' }]}>
                    <View style={styles.questionHeaderRow}>
                        <View style={styles.questionBadge}>
                            <Text style={styles.questionBadgeText}>Q{currentIndex + 1}</Text>
                        </View>
                        <Text style={styles.questionPointsText}>1 Point</Text>
                    </View>

                    {currentQuestion.image_url ? (
                        <Image
                            source={{ uri: `${BASE_URL}/uploads/${currentQuestion.image_url}` }}
                            style={styles.questionImage}
                            resizeMode="contain"
                        />
                    ) : null}

                    <SmartText
                        content={decodedQuestion}
                        textColor={isDarkMode ? '#ffffff' : '#0f172a'}
                        fontSize="17px"
                        fontWeight="bold"
                        backgroundColor="transparent"
                        style={{ marginTop: 4, lineHeight: 28 }}
                    />
                </View>

                <View style={styles.optionsList}>
                    {['a', 'b', 'c', 'd'].map((opt) => (
                        <OptionCard
                            key={opt}
                            opt={opt}
                            content={decodedOptions[opt]}
                            hasAnswered={hasAnswered}
                            isCorrectAnswer={opt === currentQuestion.correct_answer}
                            isSelected={userAnswer === opt}
                            isDarkMode={isDarkMode}
                            onPress={() => handleAnswer(opt)}
                        />
                    ))}
                </View>

                {showExplanation[currentIndex] && (
                    <View style={[styles.explanationContainer, { backgroundColor: isDarkMode ? '#1e293b' : '#ffffff', borderColor: isDarkMode ? '#1e293b' : '#e2e8f0', shadowColor: isDarkMode ? 'transparent' : '#000' }]}>
                        <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 12 }}>
                            {userAnswer === currentQuestion.correct_answer ? (
                                <Ionicons name="checkmark-circle" size={24} color="#10b981" style={{ marginRight: 8 }} />
                            ) : (
                                <Ionicons name="close-circle" size={24} color="#ef4444" style={{ marginRight: 8 }} />
                            )}
                            <Text style={[styles.explanationTitle,
                                userAnswer === currentQuestion.correct_answer ? { color: '#10b981' } : { color: '#ef4444' }
                            ]}>
                                {userAnswer === currentQuestion.correct_answer ? 'Correct!' : 'Incorrect!'}
                            </Text>
                        </View>
                        {decodedExplanation && (
                            <View style={[styles.explanationDetailBox, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc', borderColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
                                <Text style={[styles.explanationLabel, { color: isDarkMode ? '#94a3b8' : '#64748b' }]}>Explanation</Text>
                                <SmartText content={decodedExplanation} textColor={isDarkMode ? '#cbd5e1' : '#334155'} fontSize="14px" />
                            </View>
                        )}
                    </View>
                )}
            </ScrollView>

            <View style={[styles.navigationBar, { backgroundColor: isDarkMode ? '#1e293b' : '#ffffff', borderColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
                <TouchableOpacity
                    style={[styles.navButtonWrapper, currentIndex === 0 && styles.navButtonDisabled]}
                    onPress={previousQuestion}
                    disabled={currentIndex === 0}
                    activeOpacity={0.8}
                >
                    <View style={[styles.prevButton, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc', borderColor: isDarkMode ? '#334155' : '#e2e8f0' }]}>
                        <Ionicons name="arrow-back" size={20} color={isDarkMode ? '#cbd5e1' : '#64748b'} style={{ marginRight: 6 }} />
                        <Text style={[styles.prevButtonText, { color: isDarkMode ? '#cbd5e1' : '#475569' }]}>Previous</Text>
                    </View>
                </TouchableOpacity>

                {currentIndex === questions.length - 1 ? (
                    <TouchableOpacity style={[styles.navButtonWrapper, { flex: 1.5 }]} onPress={submitTest} activeOpacity={0.8}>
                        <View style={[styles.navButtonGradient, { backgroundColor: '#10b981' }]}>
                            <Text style={styles.submitButtonText}>Submit Test</Text>
                            <Ionicons name="checkmark-done" size={20} color="white" style={{ marginLeft: 6 }} />
                        </View>
                    </TouchableOpacity>
                ) : (
                    <TouchableOpacity style={[styles.navButtonWrapper, { flex: 1.5 }]} onPress={nextQuestion} activeOpacity={0.8}>
                        <View style={[styles.navButtonGradient, { backgroundColor: '#4f46e5' }]}>
                            <Text style={styles.navButtonTextWhite}>Next Question</Text>
                            <Ionicons name="arrow-forward" size={20} color="white" style={{ marginLeft: 6 }} />
                        </View>
                    </TouchableOpacity>
                )}
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    mainWrapper: { flex: 1, backgroundColor: '#f0f4f8' },
    headerContainer: { paddingBottom: 12, borderBottomWidth: 1, borderBottomLeftRadius: 20, borderBottomRightRadius: 20, shadowColor: '#4f46e5', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.15, shadowRadius: 12, elevation: 4, zIndex: 10 },
    headerSafe: { backgroundColor: 'transparent' },
    testHeader: { paddingHorizontal: 16, paddingTop: 4 },
    testHeaderTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 },
    headerBackButton: { width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
    testSubject: { fontSize: 15, fontFamily: 'NotoSans-Bold', textAlign: 'center' },
    progressBarContainer: { height: 4, borderRadius: 2, overflow: 'hidden', marginTop: 2 },
    progressBar: { height: '100%', borderRadius: 2 },
    questionCounter: { fontSize: 12, fontFamily: 'NotoSans-Bold', textAlign: 'center', marginTop: 2 },
    container: { flex: 1 },
    scrollContent: { padding: 12, paddingBottom: 110, marginTop: 4 },
    questionCard: {
        borderRadius: 16, padding: 16, marginBottom: 16,
        borderWidth: 1,
        shadowColor: '#4f46e5', shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.04, shadowRadius: 8, elevation: 1,
    },
    questionHeaderRow: {
        flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10
    },
    questionBadge: {
        backgroundColor: '#eef2ff', paddingHorizontal: 8, paddingVertical: 4,
        borderRadius: 6, alignSelf: 'flex-start',
    },
    questionBadgeText: { color: '#4f46e5', fontSize: 12, fontFamily: 'NotoSans-Bold' },
    questionPointsText: { color: '#94a3b8', fontSize: 12, fontFamily: 'NotoSans-Bold' },
    questionImage: { width: '100%', height: 120, marginBottom: 12, borderRadius: 10, backgroundColor: 'rgba(0,0,0,0.03)' },
    optionsList: { gap: 10 },
    optionButton: { 
        borderRadius: 14, 
        paddingVertical: 12, paddingHorizontal: 12,
        shadowColor: '#4f46e5', shadowOffset: { width: 0, height: 2 }, 
        shadowOpacity: 0.03, shadowRadius: 4, elevation: 0.5 
    },
    optionGradient: { paddingVertical: 0, paddingHorizontal: 0 },
    optionContent: { flexDirection: 'row', alignItems: 'center' },
    optionLetterBox: { width: 28, height: 28, borderRadius: 8, justifyContent: 'center', alignItems: 'center', marginRight: 12 },
    optionText: { fontSize: 13, fontFamily: 'NotoSans-Bold' },
    optionTextSelected: {},
    explanationContainer: { borderRadius: 16, padding: 16, marginTop: 16, marginBottom: 12, borderWidth: 1, shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.03, shadowRadius: 8, elevation: 1 },
    explanationTitle: { fontSize: 17, fontFamily: 'NotoSans-Bold' },
    explanationDetailBox: { borderRadius: 14, padding: 16, marginTop: 8, borderWidth: 1 },
    explanationLabel: { fontSize: 12, fontFamily: 'NotoSans-Bold', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 6 },
    navigationBar: {
        position: 'absolute', bottom: Platform.OS === 'ios' ? 24 : 16, left: 20, right: 20,
        flexDirection: 'row', backgroundColor: '#ffffff', paddingHorizontal: 6, paddingVertical: 6,
        borderRadius: 24, gap: 8, shadowColor: '#000', shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.12, shadowRadius: 24, elevation: 8, borderWidth: 1, borderColor: '#f1f5f9',
    },
    navButtonWrapper: { flex: 1, borderRadius: 20, overflow: 'hidden' },
    prevButton: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 16, backgroundColor: '#f8fafc', borderRadius: 20, borderWidth: 1, borderColor: '#e2e8f0' },
    prevButtonText: { fontSize: 15, fontFamily: 'NotoSans-Bold', color: '#64748b' },
    navButtonGradient: { flexDirection: 'row', paddingVertical: 16, alignItems: 'center', justifyContent: 'center', borderRadius: 20 },
    navButtonDisabled: { opacity: 0.5 },
    navButtonTextWhite: { fontSize: 15, fontFamily: 'NotoSans-Bold', color: 'white' },
    submitButtonText: { fontSize: 15, fontFamily: 'NotoSans-Bold', color: 'white' },
    // Results
    resultsContent: { padding: 24, paddingTop: Platform.OS === 'ios' ? 20 : 40, paddingBottom: 40 },
    resultsHeader: { alignItems: 'center', marginBottom: 32, paddingVertical: 10 },
    resultsEmoji: { fontSize: 70, marginBottom: 16 },
    resultsTitle: { fontSize: 28, fontFamily: 'NotoSans-Bold', color: '#0f172a', marginBottom: 8 },
    resultsScore: { fontSize: 36, fontFamily: 'NotoSans-Bold', color: '#4f46e5', marginBottom: 4 },
    resultsPercentage: { fontSize: 16, fontFamily: 'NotoSans-Regular', color: '#64748b' },
    statsGrid: { flexDirection: 'row', gap: 12, marginBottom: 24 },
    statCard: { flex: 1, borderRadius: 16, padding: 16, alignItems: 'center' },
    statNumber: { fontSize: 24, fontFamily: 'NotoSans-Bold', marginBottom: 4 },
    statLabel: { fontSize: 12, fontFamily: 'NotoSans-Bold', textTransform: 'uppercase' },
    timeCard: { flexDirection: 'row', backgroundColor: 'white', borderRadius: 16, padding: 20, alignItems: 'center', marginBottom: 40, borderWidth: 1, borderColor: '#e2e8f0', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.03, shadowRadius: 10, elevation: 2 },
    timeLabel: { fontSize: 13, color: '#64748b', fontFamily: 'NotoSans-Regular', marginBottom: 4 },
    timeValue: { fontSize: 24, fontFamily: 'NotoSans-Bold', color: '#0f172a' },
    reviewTitle: { fontSize: 20, fontFamily: 'NotoSans-Bold', color: '#0f172a', marginBottom: 20 },
    reviewCard: { backgroundColor: 'white', borderRadius: 20, padding: 20, marginBottom: 20, borderWidth: 1, borderColor: '#e2e8f0', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.03, shadowRadius: 8, elevation: 2 },
    reviewHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: '#f1f5f9' },
    reviewQuestionNumber: { fontSize: 15, fontFamily: 'NotoSans-Bold', color: '#64748b' },
    reviewBadge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8 },
    reviewBadgeCorrect: { backgroundColor: '#dcfce7' },
    reviewBadgeWrong: { backgroundColor: '#fee2e2' },
    reviewBadgeSkipped: { backgroundColor: '#fef3c7' },
    reviewBadgeText: { fontSize: 12, fontFamily: 'NotoSans-Bold' },
    answerInfo: { marginTop: 0 },
    answerInfoLabel: { fontSize: 12, fontFamily: 'NotoSans-Bold', color: '#64748b', textTransform: 'uppercase', marginBottom: 8 },
    answerBox: { padding: 12, borderRadius: 12, borderWidth: 1, borderLeftWidth: 4 },
    explanationBox: { backgroundColor: '#f0f9ff', borderRadius: 12, padding: 16, marginTop: 16, borderWidth: 1, borderColor: '#e0f2fe', borderLeftWidth: 4, borderLeftColor: '#0ea5e9' },
    homeButtonWrapper: { borderRadius: 16, overflow: 'hidden', marginTop: 10, shadowColor: '#4f46e5', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.25, shadowRadius: 16, elevation: 8 },
    homeButtonGradient: { paddingVertical: 18, alignItems: 'center' },
    homeButtonText: { fontSize: 16, fontFamily: 'NotoSans-Bold', color: 'white', letterSpacing: 0.5 },
});

export default MyExamTestScreen;
