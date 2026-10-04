import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ActivityIndicator,
    Alert,
    StatusBar,
    RefreshControl,
    ScrollView,
    Dimensions,
    Platform
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useFocusEffect } from '@react-navigation/native';
import Svg, { Circle } from 'react-native-svg';
import axios from 'axios';
import { API_URL } from '../api/config';
import MathJaxWebView from '../components/MathJaxWebView';

const { width } = Dimensions.get('window');

// ─────────────────────────────────────────────────────────────────────────────
// LaTeX & Text Markup Helpers
// ─────────────────────────────────────────────────────────────────────────────
const LATEX_RE = /(\$[^$]+\$|\\\(|\\\[|\\frac|\\sqrt|\\sum|\\int|\\alpha|\\beta|\\gamma|\\delta|\\theta|\\pi|\\sigma|\\omega|\\infty|\\times|\\div|\\pm|\\leq|\\geq|\\neq|\\approx|<[^>]+>)/;

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

const SmartText = React.memo(({ content, textColor, fontSize, fontWeight, backgroundColor, style }) => {
    if (!hasMarkup(content)) {
        return (
            <Text style={[
                {
                    color: textColor || '#0f172a',
                    fontSize: parseInt(fontSize) || 15,
                    fontFamily: fontWeight === 'bold' ? 'NotoSans-Bold' : 'NotoSans-Regular',
                    lineHeight: (parseInt(fontSize) || 15) * 1.4,
                    flexShrink: 1,
                },
                style
            ]}>{content}</Text>
        );
    }
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
// Circular Progress Component (Native SVG)
// ─────────────────────────────────────────────────────────────────────────────
const CircularProgress = React.memo(({ percentage, color = '#10b981', size = 136, strokeWidth = 13 }) => {
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const clampedPct = Math.min(100, Math.max(0, percentage));
    const strokeDashoffset = circumference - (clampedPct / 100) * circumference;

    return (
        <View style={{ width: size, height: size, alignItems: 'center', justifyContent: 'center' }}>
            <Svg width={size} height={size}>
                {/* Background Ring */}
                <Circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    stroke="rgba(255, 255, 255, 0.16)"
                    strokeWidth={strokeWidth}
                    fill="none"
                />
                {/* Progress Ring */}
                <Circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    stroke={color}
                    strokeWidth={strokeWidth}
                    fill="none"
                    strokeDasharray={circumference}
                    strokeDashoffset={strokeDashoffset}
                    strokeLinecap="round"
                    transform={`rotate(-90 ${size / 2} ${size / 2})`}
                />
            </Svg>
            <View style={[StyleSheet.absoluteFillObject, { alignItems: 'center', justifyContent: 'center' }]}>
                <Text style={styles.circPctText}>{clampedPct}%</Text>
            </View>
        </View>
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Main Performance Screen
// ─────────────────────────────────────────────────────────────────────────────
const PerformanceReportScreen = ({ navigation, route, user }) => {
    const [performanceData, setPerformanceData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [mistakeLoading, setMistakeLoading] = useState(false);

    // Negative Basket Review & Resolution States
    const [showNegativeDetails, setShowNegativeDetails] = useState(false);
    const [expandedQuestionId, setExpandedQuestionId] = useState(null);
    const [resolvingId, setResolvingId] = useState(null);
    const [solvingState, setSolvingState] = useState({});

    const getUserId = useCallback(async () => {
        if (user?.user_id) return user.user_id;
        if (user?.id) return user.id;
        try {
            const userDataStr = await AsyncStorage.getItem('user_data');
            if (userDataStr) {
                const parsed = JSON.parse(userDataStr);
                return parsed.user_id || parsed.id;
            }
        } catch (e) {
            console.log('[PerformanceScreen] Error reading user_data', e);
        }
        return null;
    }, [user]);

    const loadPerformance = useCallback(async () => {
        try {
            const uid = await getUserId();
            if (!uid) {
                setLoading(false);
                setRefreshing(false);
                return;
            }
            const res = await axios.get(`${API_URL}/get_student_performance.php?user_id=${uid}`);
            if (res.data && res.data.status === 'success') {
                setPerformanceData(res.data.data);
            }
        } catch (error) {
            console.log('[PerformanceScreen] Error loading performance:', error.message);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [getUserId]);

    useFocusEffect(
        useCallback(() => {
            loadPerformance();
        }, [loadPerformance])
    );

    const onRefresh = useCallback(() => {
        setRefreshing(true);
        loadPerformance();
    }, [loadPerformance]);

    // Practice All Mistakes in Test Mode
    const practiceMistakes = async () => {
        try {
            const uid = await getUserId();
            if (!uid) {
                Alert.alert('Notice', 'Please log in to practice mistake questions.');
                return;
            }
            setMistakeLoading(true);
            const res = await axios.get(`${API_URL}/get_mistakes_practice_quiz.php?user_id=${uid}&limit=15`);
            if (res.data && res.data.status === 'success' && res.data.data?.questions?.length > 0) {
                const quizList = res.data.data.questions.map(q => ({
                    mcq_id: q.mcq_id,
                    question: q.question,
                    option_a: q.options?.a || '',
                    option_b: q.options?.b || '',
                    option_c: q.options?.c || '',
                    option_d: q.options?.d || '',
                    correct_answer: q.correct_answer,
                    explanation: q.explanation,
                    chapter_name: q.chapter_name
                }));
                navigation.navigate('MyExamTest', {
                    questions: quizList,
                    totalQuestions: quizList.length,
                    subjectName: 'Practice Negative Questions'
                });
            } else {
                Alert.alert(
                    'No Mistakes Found! 🌟',
                    'You currently have no recorded negative-marked questions! Keep up the great work.'
                );
            }
        } catch (err) {
            Alert.alert('Error', 'Failed to load mistake practice quiz. Please try again.');
        } finally {
            setMistakeLoading(false);
        }
    };

    // Re-solve Negative Question Inline
    const handleResolveQuestion = async (item, selectedOpt) => {
        const mcqId = item.question_id || item.mcq_id;
        const basketId = item.basket_id;
        if (resolvingId) return;

        setResolvingId(mcqId);
        setSolvingState(prev => ({
            ...prev,
            [mcqId]: { selected: selectedOpt, status: 'checking', message: 'Checking answer...' }
        }));

        try {
            const uid = await getUserId();
            const res = await axios.post(`${API_URL}/resolve_negative_question.php`, {
                user_id: uid,
                mcq_id: mcqId,
                selected_option: selectedOpt,
                basket_id: basketId
            });

            if (res.data?.status === 'success') {
                const isCorrect = res.data.data?.is_correct;
                setSolvingState(prev => ({
                    ...prev,
                    [mcqId]: {
                        selected: selectedOpt,
                        status: isCorrect ? 'correct' : 'wrong',
                        message: res.data.message
                    }
                }));

                if (isCorrect) {
                    setTimeout(() => {
                        setPerformanceData(prev => {
                            if (!prev) return prev;
                            const filteredQuestions = (prev.negative_basket?.questions || []).filter(
                                q => (q.question_id || q.mcq_id) !== mcqId
                            );
                            const newCount = Math.max(0, (prev.negative_basket?.total_count || 1) - 1);
                            return {
                                ...prev,
                                negative_basket: {
                                    ...prev.negative_basket,
                                    total_count: newCount,
                                    questions: filteredQuestions
                                },
                                summary: {
                                    ...prev.summary,
                                    negative_questions_count: newCount,
                                    total_correct: (prev.summary?.total_correct || 0) + 1,
                                    total_wrong: Math.max(0, (prev.summary?.total_wrong || 1) - 1)
                                }
                            };
                        });
                        setResolvingId(null);
                    }, 1100);
                } else {
                    setResolvingId(null);
                }
            } else {
                Alert.alert('Notice', res.data?.message || 'Could not verify answer');
                setResolvingId(null);
            }
        } catch (err) {
            console.log('[ResolveQuestion] Error:', err);
            Alert.alert('Error', 'Connection issue. Please try again.');
            setResolvingId(null);
        }
    };

    // Navigation Handlers
    const handlePracticeChapter = (chapter) => {
        if (!chapter?.chapter_id) return;
        navigation.navigate('ChapterContent', {
            chapter: {
                chapter_id: chapter.chapter_id,
                chapter_name: chapter.chapter_name,
                subject_id: chapter.subject_id,
                subject_name: chapter.subject_name
            },
            initialTab: 'MCQs'
        });
    };

    const handleContinueChapter = (chapter) => {
        if (!chapter?.chapter_id) return;
        navigation.navigate('ChapterContent', {
            chapter: {
                chapter_id: chapter.chapter_id,
                chapter_name: chapter.chapter_name,
                subject_id: chapter.subject_id,
                subject_name: chapter.subject_name
            }
        });
    };

    const handleStudyNextAction = (studyNext) => {
        if (!studyNext) return;
        if (studyNext.action === 'negative_basket') {
            practiceMistakes();
        } else if (studyNext.action === 'chapter_mcq' && studyNext.chapter) {
            handlePracticeChapter(studyNext.chapter);
        } else if (studyNext.action === 'chapter_content' && studyNext.chapter) {
            handleContinueChapter(studyNext.chapter);
        } else {
            navigation.navigate('Subjects');
        }
    };

    // Derive Data
    const summary = performanceData?.summary || {};
    const overall = performanceData?.overall_performance || {};
    const subjects = performanceData?.subjects || [];
    const weakChapters = performanceData?.weak_chapters || [];
    const incompleteChapters = performanceData?.incomplete_chapters || [];
    const negativeBasket = performanceData?.negative_basket || { total_count: 0, questions: [] };
    const studyNext = performanceData?.study_next || null;
    const isEmptyState = performanceData?.is_empty_state === true || (overall.attempted_count || 0) === 0;

    const overallPct = overall.percentage ?? 0;
    const overallStatus = overallPct >= 75 ? 'Good' : overallPct >= 50 ? 'Average' : 'Weak';
    const overallColor = overallPct >= 75 ? '#10b981' : overallPct >= 50 ? '#f59e0b' : '#ef4444';
    const overallStatusIcon = overallPct >= 75 ? '🟢 Good' : overallPct >= 50 ? '🟡 Average' : '🔴 Weak';

    return (
        <SafeAreaView style={styles.container} edges={['top']}>
            <StatusBar barStyle="light-content" backgroundColor="#1e1b4b" />

            {/* Header */}
            <View style={styles.header}>
                <TouchableOpacity
                    style={styles.backBtn}
                    onPress={() => navigation.goBack()}
                    hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
                >
                    <Ionicons name="arrow-back" size={24} color="#ffffff" />
                </TouchableOpacity>
                <View style={{ flex: 1 }}>
                    <Text style={styles.headerTitle}>📊 My Performance</Text>
                    <Text style={styles.headerSubtitle}>See your progress and know what to study next.</Text>
                </View>
                <TouchableOpacity
                    style={styles.refreshIconBtn}
                    onPress={onRefresh}
                    disabled={refreshing}
                >
                    <Ionicons name="refresh" size={20} color="#ffffff" />
                </TouchableOpacity>
            </View>

            {loading ? (
                <View style={styles.centerLoading}>
                    <ActivityIndicator size="large" color="#6366f1" />
                    <Text style={styles.loadingText}>Loading your performance...</Text>
                </View>
            ) : (
                <ScrollView
                    contentContainerStyle={styles.scrollContent}
                    showsVerticalScrollIndicator={false}
                    refreshControl={
                        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#6366f1" />
                    }
                >
                    {isEmptyState ? (
                        /* 16. EMPTY STATE */
                        <View style={styles.emptyContainer}>
                            <LinearGradient
                                colors={['#312e81', '#1e1b4b']}
                                style={styles.emptyCard}
                            >
                                <View style={styles.emptyIconCircle}>
                                    <Text style={{ fontSize: 48 }}>📚</Text>
                                </View>
                                <Text style={styles.emptyTitle}>Start Your Learning Journey</Text>
                                <Text style={styles.emptySubtitle}>
                                    Complete a chapter or take your first test to see your performance here.
                                </Text>
                                <TouchableOpacity
                                    style={styles.primaryActionButton}
                                    onPress={() => navigation.navigate('Subjects')}
                                    activeOpacity={0.88}
                                >
                                    <LinearGradient
                                        colors={['#10b981', '#059669']}
                                        style={styles.btnGradient}
                                    >
                                        <Text style={styles.primaryBtnText}>Start Learning →</Text>
                                    </LinearGradient>
                                </TouchableOpacity>
                            </LinearGradient>
                        </View>
                    ) : (
                        <>
                            {/* 10. STUDY NEXT (Prominent Top Recommendation) */}
                            {studyNext && (
                                <View style={styles.sectionWrap}>
                                    <View style={styles.sectionHeaderRow}>
                                        <Text style={styles.sectionHeading}>🎯 Study Next</Text>
                                    </View>
                                    <LinearGradient
                                        colors={['#4338ca', '#3b82f6']}
                                        start={{ x: 0, y: 0 }}
                                        end={{ x: 1, y: 1 }}
                                        style={styles.studyNextCard}
                                    >
                                        {/* Glossy Overlay */}
                                        <LinearGradient
                                            colors={['rgba(255,255,255,0.3)', 'rgba(255,255,255,0.03)']}
                                            style={styles.glossyOverlay}
                                        />
                                        <View style={{ zIndex: 1 }}>
                                            <Text style={styles.studyNextTitle}>{studyNext.title}</Text>
                                            <Text style={styles.studyNextSubtitle}>{studyNext.subtitle}</Text>
                                            <TouchableOpacity
                                                style={styles.studyNextBtn}
                                                onPress={() => handleStudyNextAction(studyNext)}
                                                activeOpacity={0.88}
                                            >
                                                <LinearGradient
                                                    colors={['#ffffff', '#f1f5f9']}
                                                    style={styles.studyNextBtnGradient}
                                                >
                                                    <Text style={styles.studyNextBtnText}>{studyNext.button_text}</Text>
                                                </LinearGradient>
                                            </TouchableOpacity>
                                        </View>
                                    </LinearGradient>
                                </View>
                            )}

                            {/* 3. OVERALL PERFORMANCE */}
                            <View style={styles.sectionWrap}>
                                <LinearGradient
                                    colors={['#1e1b4b', '#312e81']}
                                    start={{ x: 0, y: 0 }}
                                    end={{ x: 1, y: 1 }}
                                    style={styles.overallCard}
                                >
                                    {/* Glossy Overlay */}
                                    <LinearGradient
                                        colors={['rgba(255,255,255,0.22)', 'rgba(255,255,255,0.02)']}
                                        style={styles.glossyOverlay}
                                    />

                                    {/* Header & Status */}
                                    <View style={styles.overallTopRow}>
                                        <Text style={styles.overallCardTitle}>⭐ Overall Performance</Text>
                                        <View style={[styles.statusPill, { backgroundColor: `${overallColor}25`, borderColor: `${overallColor}60` }]}>
                                            <Text style={[styles.statusPillText, { color: overallColor }]}>
                                                {overallStatusIcon}
                                            </Text>
                                        </View>
                                    </View>

                                    {/* Circular Progress Ring */}
                                    <View style={styles.circWrap}>
                                        <CircularProgress
                                            percentage={overallPct}
                                            color={overallColor}
                                            size={136}
                                            strokeWidth={13}
                                        />
                                    </View>

                                    {/* Metrics Pill Grid */}
                                    <View style={styles.metricRow}>
                                        <View style={styles.metricPill}>
                                            <View style={[styles.metricDot, { backgroundColor: '#10b981' }]} />
                                            <Text style={styles.metricVal}>{overall.correct_count ?? 0}</Text>
                                            <Text style={styles.metricLbl}>Correct</Text>
                                        </View>
                                        <View style={styles.metricPill}>
                                            <View style={[styles.metricDot, { backgroundColor: '#ef4444' }]} />
                                            <Text style={styles.metricVal}>{overall.wrong_count ?? 0}</Text>
                                            <Text style={styles.metricLbl}>Wrong</Text>
                                        </View>
                                        <View style={styles.metricPill}>
                                            <View style={[styles.metricDot, { backgroundColor: '#38bdf8' }]} />
                                            <Text style={styles.metricVal}>{overall.attempted_count ?? 0}</Text>
                                            <Text style={styles.metricLbl}>Attempted</Text>
                                        </View>
                                    </View>
                                </LinearGradient>
                            </View>

                            {/* 4. SUBJECT PERFORMANCE */}
                            <View style={styles.sectionWrap}>
                                <Text style={styles.sectionHeading}>📚 Subject Performance</Text>
                                {subjects.length === 0 ? (
                                    <Text style={styles.subNoteText}>No subjects available for your class.</Text>
                                ) : (
                                    subjects.map((sub, idx) => {
                                        const acc = sub.accuracy_pct ?? 0;
                                        const subColor = acc >= 75 ? '#10b981' : acc >= 50 ? '#f59e0b' : acc > 0 ? '#ef4444' : '#64748b';
                                        const subLabel = acc >= 75 ? '🟢 Strong' : acc >= 50 ? '🟡 Average' : acc > 0 ? '🔴 Weak' : '⚪ Not Started';

                                        return (
                                            <View key={sub.subject_id || idx} style={styles.subjectCard}>
                                                <View style={styles.subjectTopRow}>
                                                    <Text style={styles.subjectName}>{sub.subject_name}</Text>
                                                    <View style={[styles.miniStatusBadge, { backgroundColor: `${subColor}18` }]}>
                                                        <Text style={[styles.miniStatusText, { color: subColor }]}>{subLabel}</Text>
                                                    </View>
                                                </View>
                                                <View style={styles.subjectAccRow}>
                                                    <Text style={styles.subjectAccLabel}>Accuracy:</Text>
                                                    <Text style={[styles.subjectAccValue, { color: subColor }]}>
                                                        {acc}%
                                                    </Text>
                                                </View>
                                                {/* Simple Progress Bar */}
                                                <View style={styles.progressBarTrack}>
                                                    <View style={[styles.progressBarFill, { width: `${acc}%`, backgroundColor: subColor }]} />
                                                </View>
                                            </View>
                                        );
                                    })
                                )}
                            </View>

                            {/* 5. WEAK CHAPTERS */}
                            <View style={styles.sectionWrap}>
                                <Text style={styles.sectionHeading}>🔴 Weak Chapters</Text>
                                {weakChapters.length === 0 ? (
                                    <View style={styles.celebrationCard}>
                                        <Text style={{ fontSize: 32, marginBottom: 6 }}>🎉</Text>
                                        <Text style={styles.celebrationTitle}>Great!</Text>
                                        <Text style={styles.celebrationSub}>You currently don't have any weak chapters.</Text>
                                    </View>
                                ) : (
                                    weakChapters.map((ch, idx) => (
                                        <View key={ch.chapter_id || idx} style={styles.weakChapterCard}>
                                            <View style={styles.chapterHeaderRow}>
                                                <View style={{ flex: 1 }}>
                                                    <Text style={styles.chapterTitle}>{ch.chapter_name}</Text>
                                                    <Text style={styles.chapterSubject}>{ch.subject_name}</Text>
                                                </View>
                                                <View style={styles.weakBadge}>
                                                    <Text style={styles.weakBadgeText}>🔴 Weak</Text>
                                                </View>
                                            </View>
                                            <View style={styles.chapterBottomRow}>
                                                <Text style={styles.chapterAccText}>Accuracy: <Text style={{ fontFamily: 'NotoSans-Bold', color: '#ef4444' }}>{ch.accuracy_pct}%</Text></Text>
                                                <TouchableOpacity
                                                    style={styles.practiceBtn}
                                                    onPress={() => handlePracticeChapter(ch)}
                                                    activeOpacity={0.85}
                                                >
                                                    <LinearGradient
                                                        colors={['#ef4444', '#dc2626']}
                                                        style={styles.practiceBtnGradient}
                                                    >
                                                        <Text style={styles.practiceBtnText}>Practice Now →</Text>
                                                    </LinearGradient>
                                                </TouchableOpacity>
                                            </View>
                                        </View>
                                    ))
                                )}
                            </View>

                            {/* 6. INCOMPLETE CHAPTERS */}
                            <View style={styles.sectionWrap}>
                                <Text style={styles.sectionHeading}>📖 Incomplete Chapters</Text>
                                {incompleteChapters.length === 0 ? (
                                    <View style={styles.celebrationCard}>
                                        <Text style={{ fontSize: 32, marginBottom: 6 }}>🏆</Text>
                                        <Text style={styles.celebrationTitle}>All Completed!</Text>
                                        <Text style={styles.celebrationSub}>You have completed all available chapters.</Text>
                                    </View>
                                ) : (
                                    incompleteChapters.slice(0, 10).map((ch, idx) => {
                                        const isInProgress = ch.progress_pct > 0;
                                        const statusColor = isInProgress ? '#f59e0b' : '#94a3b8';
                                        const statusLabel = isInProgress ? '🟡 In Progress' : '⚪ Not Started';
                                        const actionText = isInProgress ? 'Continue →' : 'Start Learning →';

                                        return (
                                            <View key={ch.chapter_id || idx} style={styles.incompleteChapterCard}>
                                                <View style={styles.chapterHeaderRow}>
                                                    <View style={{ flex: 1 }}>
                                                        <Text style={styles.chapterSubject}>{ch.subject_name}</Text>
                                                        <Text style={styles.chapterTitle}>{ch.chapter_name}</Text>
                                                    </View>
                                                    <View style={[styles.miniStatusBadge, { backgroundColor: `${statusColor}18` }]}>
                                                        <Text style={[styles.miniStatusText, { color: statusColor }]}>{statusLabel}</Text>
                                                    </View>
                                                </View>
                                                <View style={styles.incompleteMidRow}>
                                                    <Text style={styles.progressLabel}>Progress: <Text style={{ fontFamily: 'NotoSans-Bold', color: '#0f172a' }}>{ch.progress_pct}%</Text></Text>
                                                </View>
                                                {/* Progress Bar */}
                                                <View style={styles.progressBarTrack}>
                                                    <View style={[styles.progressBarFill, { width: `${ch.progress_pct}%`, backgroundColor: statusColor }]} />
                                                </View>
                                                <TouchableOpacity
                                                    style={styles.continueBtn}
                                                    onPress={() => handleContinueChapter(ch)}
                                                    activeOpacity={0.85}
                                                >
                                                    <LinearGradient
                                                        colors={isInProgress ? ['#f59e0b', '#d97706'] : ['#4f46e5', '#6366f1']}
                                                        style={styles.continueBtnGradient}
                                                    >
                                                        <Text style={styles.continueBtnText}>{actionText}</Text>
                                                    </LinearGradient>
                                                </TouchableOpacity>
                                            </View>
                                        );
                                    })
                                )}
                            </View>

                            {/* 8. NEGATIVE BASKET */}
                            <View style={styles.sectionWrap}>
                                <Text style={styles.sectionHeading}>🧠 Negative Basket</Text>
                                <Text style={styles.sectionSubtitle}>Questions you answered incorrectly.</Text>

                                <LinearGradient
                                    colors={['#831843', '#be123c']}
                                    start={{ x: 0, y: 0 }}
                                    end={{ x: 1, y: 1 }}
                                    style={styles.negativeBasketCard}
                                >
                                    {/* Glossy Overlay */}
                                    <LinearGradient
                                        colors={['rgba(255,255,255,0.25)', 'rgba(255,255,255,0.03)']}
                                        style={styles.glossyOverlay}
                                    />
                                    <View style={{ zIndex: 1 }}>
                                        <View style={styles.negTopRow}>
                                            <View>
                                                <Text style={styles.negCountText}>
                                                    {negativeBasket.total_count} Questions
                                                </Text>
                                                <Text style={styles.negStatusText}>Needs Revision</Text>
                                            </View>
                                            <View style={styles.negIconBadge}>
                                                <Ionicons name="alert-circle" size={32} color="#ffffff" />
                                            </View>
                                        </View>

                                        {negativeBasket.total_count > 0 && (
                                            <TouchableOpacity
                                                style={styles.practiceMistakesBtn}
                                                onPress={practiceMistakes}
                                                disabled={mistakeLoading}
                                                activeOpacity={0.88}
                                            >
                                                <LinearGradient
                                                    colors={['#ffffff', '#ffe4e6']}
                                                    style={styles.practiceMistakesBtnGradient}
                                                >
                                                    {mistakeLoading ? (
                                                        <ActivityIndicator size="small" color="#be123c" />
                                                    ) : (
                                                        <Text style={styles.practiceMistakesBtnText}>Practice Negative Questions →</Text>
                                                    )}
                                                </LinearGradient>
                                            </TouchableOpacity>
                                        )}
                                    </View>
                                </LinearGradient>

                                {/* Negative Questions List / Review Toggle */}
                                {negativeBasket.questions?.length > 0 && (
                                    <View style={styles.negQuestionsSection}>
                                        <TouchableOpacity
                                            style={styles.toggleDetailsRow}
                                            onPress={() => setShowNegativeDetails(!showNegativeDetails)}
                                            activeOpacity={0.8}
                                        >
                                            <Text style={styles.toggleDetailsText}>
                                                {showNegativeDetails ? 'Hide Questions ▲' : 'View Wrong Questions List ▼'}
                                            </Text>
                                        </TouchableOpacity>

                                        {showNegativeDetails && (
                                            negativeBasket.questions.map((q, idx) => {
                                                const qId = q.question_id || q.mcq_id;
                                                const isExpanded = expandedQuestionId === qId;
                                                const sState = solvingState[qId];

                                                return (
                                                    <View key={q.basket_id || qId || idx} style={styles.negQuestionItem}>
                                                        <View style={styles.negItemHeader}>
                                                            <Text style={styles.negCrossIcon}>❌</Text>
                                                            <View style={{ flex: 1 }}>
                                                                <SmartText
                                                                    content={decodeHtml(q.question)}
                                                                    fontSize={15}
                                                                    fontWeight="bold"
                                                                    textColor="#0f172a"
                                                                />
                                                            </View>
                                                        </View>

                                                        <View style={styles.negItemBreadcrumb}>
                                                            <Text style={styles.negBreadcrumbText}>
                                                                {q.subject_name || 'General'} → {q.chapter_name || 'Chapter'}
                                                            </Text>
                                                            <View style={styles.wrongAttemptsPill}>
                                                                <Text style={styles.wrongAttemptsText}>
                                                                    Wrong Attempts: {q.wrong_attempt_count || 1}
                                                                </Text>
                                                            </View>
                                                        </View>

                                                        <TouchableOpacity
                                                            style={styles.reviewBtn}
                                                            onPress={() => setExpandedQuestionId(isExpanded ? null : qId)}
                                                            activeOpacity={0.8}
                                                        >
                                                            <Text style={styles.reviewBtnText}>
                                                                {isExpanded ? 'Close Review ▲' : 'Review →'}
                                                            </Text>
                                                        </TouchableOpacity>

                                                        {/* Expanded Review & Inline Re-solve */}
                                                        {isExpanded && (
                                                            <View style={styles.expandedBox}>
                                                                <Text style={styles.resolvePrompt}>Re-solve now to clear from negative basket:</Text>

                                                                {['a', 'b', 'c', 'd'].map(optKey => {
                                                                    const optVal = q[`option_${optKey}`];
                                                                    if (!optVal) return null;

                                                                    const isSelected = sState?.selected === optKey;
                                                                    const isCorrectOpt = sState?.status === 'correct' && isSelected;
                                                                    const isWrongOpt = sState?.status === 'wrong' && isSelected;

                                                                    return (
                                                                        <TouchableOpacity
                                                                            key={optKey}
                                                                            style={[
                                                                                styles.optBtn,
                                                                                isSelected && styles.optBtnSelected,
                                                                                isCorrectOpt && styles.optBtnCorrect,
                                                                                isWrongOpt && styles.optBtnWrong
                                                                            ]}
                                                                            onPress={() => handleResolveQuestion(q, optKey)}
                                                                            disabled={resolvingId === qId || sState?.status === 'correct'}
                                                                        >
                                                                            <View style={[styles.optKeyCircle, isSelected && styles.optKeyCircleSelected]}>
                                                                                <Text style={[styles.optKeyText, isSelected && styles.optKeyTextSelected]}>
                                                                                    {optKey.toUpperCase()}
                                                                                </Text>
                                                                            </View>
                                                                            <View style={{ flex: 1 }}>
                                                                                <SmartText content={decodeHtml(optVal)} fontSize={14} textColor="#1e293b" />
                                                                            </View>
                                                                        </TouchableOpacity>
                                                                    );
                                                                })}

                                                                {sState?.message && (
                                                                    <View style={[
                                                                        styles.feedbackPill,
                                                                        sState.status === 'correct' ? styles.feedbackSuccess : styles.feedbackError
                                                                    ]}>
                                                                        <Text style={styles.feedbackText}>{sState.message}</Text>
                                                                    </View>
                                                                )}

                                                                {q.explanation ? (
                                                                    <View style={styles.explBox}>
                                                                        <Text style={styles.explLabel}>💡 Explanation:</Text>
                                                                        <SmartText content={decodeHtml(q.explanation)} fontSize={13} textColor="#475569" />
                                                                    </View>
                                                                ) : null}
                                                            </View>
                                                        )}
                                                    </View>
                                                );
                                            })
                                        )}
                                    </View>
                                )}
                            </View>
                        </>
                    )}
                </ScrollView>
            )}
        </SafeAreaView>
    );
};

// ─────────────────────────────────────────────────────────────────────────────
// Styles
// ─────────────────────────────────────────────────────────────────────────────
const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#0f172a',
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingTop: 8,
        paddingBottom: 16,
        backgroundColor: '#0f172a',
    },
    backBtn: {
        width: 40,
        height: 40,
        borderRadius: 20,
        backgroundColor: 'rgba(255, 255, 255, 0.1)',
        alignItems: 'center',
        justifyContent: 'center',
        marginRight: 12,
    },
    headerTitle: {
        fontSize: 22,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
    },
    headerSubtitle: {
        fontSize: 12,
        fontFamily: 'NotoSans-Regular',
        color: '#94a3b8',
        marginTop: 2,
    },
    refreshIconBtn: {
        width: 36,
        height: 36,
        borderRadius: 18,
        backgroundColor: 'rgba(255, 255, 255, 0.08)',
        alignItems: 'center',
        justifyContent: 'center',
        marginLeft: 8,
    },
    scrollContent: {
        paddingHorizontal: 16,
        paddingBottom: 40,
    },
    centerLoading: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
    },
    loadingText: {
        color: '#94a3b8',
        fontSize: 14,
        fontFamily: 'NotoSans-Medium',
        marginTop: 12,
    },

    // Glossy Overlay Helper
    glossyOverlay: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: '50%',
        borderTopLeftRadius: 20,
        borderTopRightRadius: 20,
    },

    // 10. Study Next Card
    sectionWrap: {
        marginBottom: 20,
    },
    sectionHeading: {
        fontSize: 18,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
        marginBottom: 10,
    },
    sectionSubtitle: {
        fontSize: 12,
        fontFamily: 'NotoSans-Regular',
        color: '#94a3b8',
        marginBottom: 10,
        marginTop: -6,
    },
    studyNextCard: {
        borderRadius: 20,
        padding: 18,
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.2)',
        ...Platform.select({
            android: { elevation: 6 },
            ios: { shadowColor: '#3b82f6', shadowOpacity: 0.3, shadowRadius: 8, shadowOffset: { width: 0, height: 4 } }
        }),
    },
    studyNextTitle: {
        fontSize: 18,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
        marginBottom: 4,
    },
    studyNextSubtitle: {
        fontSize: 13,
        fontFamily: 'NotoSans-Medium',
        color: 'rgba(255, 255, 255, 0.88)',
        marginBottom: 14,
    },
    studyNextBtn: {
        borderRadius: 12,
        overflow: 'hidden',
        alignSelf: 'flex-start',
    },
    studyNextBtnGradient: {
        paddingVertical: 10,
        paddingHorizontal: 18,
        borderRadius: 12,
    },
    studyNextBtnText: {
        color: '#1e3a8a',
        fontFamily: 'NotoSans-Bold',
        fontSize: 14,
    },

    // 3. Overall Performance Card
    overallCard: {
        borderRadius: 20,
        padding: 20,
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.12)',
        ...Platform.select({
            android: { elevation: 5 },
            ios: { shadowColor: '#000', shadowOpacity: 0.25, shadowRadius: 8, shadowOffset: { width: 0, height: 4 } }
        }),
    },
    overallTopRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 16,
        zIndex: 1,
    },
    overallCardTitle: {
        fontSize: 17,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
    },
    statusPill: {
        paddingHorizontal: 12,
        paddingVertical: 4,
        borderRadius: 14,
        borderWidth: 1,
    },
    statusPillText: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
    },
    circWrap: {
        alignItems: 'center',
        justifyContent: 'center',
        marginVertical: 6,
        zIndex: 1,
    },
    circPctText: {
        fontSize: 34,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
    },
    metricRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        marginTop: 18,
        zIndex: 1,
    },
    metricPill: {
        flex: 1,
        backgroundColor: 'rgba(255, 255, 255, 0.08)',
        borderRadius: 14,
        paddingVertical: 10,
        paddingHorizontal: 8,
        alignItems: 'center',
        marginHorizontal: 4,
    },
    metricDot: {
        width: 6,
        height: 6,
        borderRadius: 3,
        marginBottom: 4,
    },
    metricVal: {
        fontSize: 17,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
    },
    metricLbl: {
        fontSize: 11,
        fontFamily: 'NotoSans-Regular',
        color: '#cbd5e1',
        marginTop: 2,
    },

    // 4. Subject Performance Cards
    subjectCard: {
        backgroundColor: '#1e293b',
        borderRadius: 16,
        padding: 16,
        marginBottom: 10,
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.06)',
    },
    subjectTopRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 8,
    },
    subjectName: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
        flex: 1,
    },
    miniStatusBadge: {
        paddingHorizontal: 10,
        paddingVertical: 3,
        borderRadius: 12,
    },
    miniStatusText: {
        fontSize: 11,
        fontFamily: 'NotoSans-Bold',
    },
    subjectAccRow: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 8,
    },
    subjectAccLabel: {
        fontSize: 13,
        fontFamily: 'NotoSans-Regular',
        color: '#94a3b8',
        marginRight: 6,
    },
    subjectAccValue: {
        fontSize: 14,
        fontFamily: 'NotoSans-Bold',
    },
    progressBarTrack: {
        height: 8,
        backgroundColor: 'rgba(255, 255, 255, 0.08)',
        borderRadius: 4,
        overflow: 'hidden',
    },
    progressBarFill: {
        height: '100%',
        borderRadius: 4,
    },
    subNoteText: {
        color: '#94a3b8',
        fontSize: 13,
        fontFamily: 'NotoSans-Regular',
        fontStyle: 'italic',
    },

    // 5. Weak Chapters
    weakChapterCard: {
        backgroundColor: '#1e293b',
        borderRadius: 16,
        padding: 16,
        marginBottom: 10,
        borderLeftWidth: 4,
        borderLeftColor: '#ef4444',
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.06)',
    },
    chapterHeaderRow: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        justifyContent: 'space-between',
        marginBottom: 8,
    },
    chapterTitle: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
    },
    chapterSubject: {
        fontSize: 12,
        fontFamily: 'NotoSans-Regular',
        color: '#94a3b8',
        marginTop: 2,
    },
    weakBadge: {
        backgroundColor: 'rgba(239, 68, 68, 0.18)',
        paddingHorizontal: 10,
        paddingVertical: 3,
        borderRadius: 12,
    },
    weakBadgeText: {
        color: '#ef4444',
        fontSize: 11,
        fontFamily: 'NotoSans-Bold',
    },
    chapterBottomRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginTop: 4,
    },
    chapterAccText: {
        fontSize: 13,
        fontFamily: 'NotoSans-Regular',
        color: '#cbd5e1',
    },
    practiceBtn: {
        borderRadius: 10,
        overflow: 'hidden',
    },
    practiceBtnGradient: {
        paddingVertical: 8,
        paddingHorizontal: 14,
        borderRadius: 10,
    },
    practiceBtnText: {
        color: '#ffffff',
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
    },
    celebrationCard: {
        backgroundColor: '#1e293b',
        borderRadius: 16,
        padding: 20,
        alignItems: 'center',
        justifyContent: 'center',
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.08)',
    },
    celebrationTitle: {
        fontSize: 18,
        fontFamily: 'NotoSans-Bold',
        color: '#10b981',
        marginBottom: 4,
    },
    celebrationSub: {
        fontSize: 13,
        fontFamily: 'NotoSans-Regular',
        color: '#cbd5e1',
        textAlign: 'center',
    },

    // 6. Incomplete Chapters
    incompleteChapterCard: {
        backgroundColor: '#1e293b',
        borderRadius: 16,
        padding: 16,
        marginBottom: 10,
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.06)',
    },
    incompleteMidRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginVertical: 6,
    },
    progressLabel: {
        fontSize: 13,
        fontFamily: 'NotoSans-Regular',
        color: '#cbd5e1',
    },
    continueBtn: {
        marginTop: 12,
        borderRadius: 10,
        overflow: 'hidden',
        alignSelf: 'flex-start',
    },
    continueBtnGradient: {
        paddingVertical: 7,
        paddingHorizontal: 14,
        borderRadius: 10,
    },
    continueBtnText: {
        color: '#ffffff',
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
    },

    // 8. Negative Basket Card
    negativeBasketCard: {
        borderRadius: 20,
        padding: 18,
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.18)',
        ...Platform.select({
            android: { elevation: 6 },
            ios: { shadowColor: '#be123c', shadowOpacity: 0.3, shadowRadius: 8, shadowOffset: { width: 0, height: 4 } }
        }),
    },
    negTopRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
    },
    negCountText: {
        fontSize: 22,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
    },
    negStatusText: {
        fontSize: 13,
        fontFamily: 'NotoSans-Medium',
        color: '#fecdd3',
        marginTop: 2,
    },
    negIconBadge: {
        width: 48,
        height: 48,
        borderRadius: 24,
        backgroundColor: 'rgba(255, 255, 255, 0.15)',
        alignItems: 'center',
        justifyContent: 'center',
    },
    practiceMistakesBtn: {
        marginTop: 16,
        borderRadius: 12,
        overflow: 'hidden',
    },
    practiceMistakesBtnGradient: {
        paddingVertical: 10,
        paddingHorizontal: 16,
        alignItems: 'center',
        justifyContent: 'center',
        borderRadius: 12,
    },
    practiceMistakesBtnText: {
        color: '#be123c',
        fontFamily: 'NotoSans-Bold',
        fontSize: 14,
    },

    // Negative Questions Details
    negQuestionsSection: {
        marginTop: 12,
    },
    toggleDetailsRow: {
        paddingVertical: 10,
        alignItems: 'center',
    },
    toggleDetailsText: {
        fontSize: 13,
        fontFamily: 'NotoSans-Bold',
        color: '#f43f5e',
    },
    negQuestionItem: {
        backgroundColor: '#1e293b',
        borderRadius: 14,
        padding: 14,
        marginBottom: 10,
        borderWidth: 1,
        borderColor: 'rgba(244, 63, 94, 0.2)',
    },
    negItemHeader: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        marginBottom: 8,
    },
    negCrossIcon: {
        fontSize: 16,
        marginRight: 8,
        marginTop: 2,
    },
    negItemBreadcrumb: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginTop: 4,
        marginBottom: 10,
    },
    negBreadcrumbText: {
        fontSize: 12,
        fontFamily: 'NotoSans-Medium',
        color: '#94a3b8',
        flex: 1,
    },
    wrongAttemptsPill: {
        backgroundColor: 'rgba(239, 68, 68, 0.15)',
        paddingHorizontal: 8,
        paddingVertical: 2,
        borderRadius: 8,
    },
    wrongAttemptsText: {
        fontSize: 11,
        fontFamily: 'NotoSans-Bold',
        color: '#fca5a5',
    },
    reviewBtn: {
        alignSelf: 'flex-end',
        paddingVertical: 4,
        paddingHorizontal: 10,
        borderRadius: 8,
        backgroundColor: 'rgba(255, 255, 255, 0.08)',
    },
    reviewBtnText: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
        color: '#38bdf8',
    },
    expandedBox: {
        marginTop: 12,
        paddingTop: 12,
        borderTopWidth: 1,
        borderTopColor: 'rgba(255, 255, 255, 0.08)',
    },
    resolvePrompt: {
        fontSize: 12,
        fontFamily: 'NotoSans-Medium',
        color: '#cbd5e1',
        marginBottom: 8,
    },
    optBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'rgba(255, 255, 255, 0.05)',
        borderRadius: 10,
        padding: 10,
        marginBottom: 6,
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.08)',
    },
    optBtnSelected: {
        borderColor: '#6366f1',
        backgroundColor: 'rgba(99, 102, 241, 0.15)',
    },
    optBtnCorrect: {
        borderColor: '#10b981',
        backgroundColor: 'rgba(16, 185, 129, 0.18)',
    },
    optBtnWrong: {
        borderColor: '#ef4444',
        backgroundColor: 'rgba(239, 68, 68, 0.18)',
    },
    optKeyCircle: {
        width: 26,
        height: 26,
        borderRadius: 13,
        backgroundColor: 'rgba(255, 255, 255, 0.1)',
        alignItems: 'center',
        justifyContent: 'center',
        marginRight: 10,
    },
    optKeyCircleSelected: {
        backgroundColor: '#6366f1',
    },
    optKeyText: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
    },
    optKeyTextSelected: {
        color: '#ffffff',
    },
    feedbackPill: {
        padding: 8,
        borderRadius: 8,
        marginTop: 8,
    },
    feedbackSuccess: {
        backgroundColor: 'rgba(16, 185, 129, 0.2)',
    },
    feedbackError: {
        backgroundColor: 'rgba(239, 68, 68, 0.2)',
    },
    feedbackText: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
        textAlign: 'center',
    },
    explBox: {
        marginTop: 8,
        padding: 10,
        backgroundColor: 'rgba(255, 255, 255, 0.04)',
        borderRadius: 8,
    },
    explLabel: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
        color: '#fbbf24',
        marginBottom: 4,
    },

    // 16. Empty State
    emptyContainer: {
        paddingTop: 30,
    },
    emptyCard: {
        borderRadius: 24,
        padding: 28,
        alignItems: 'center',
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.1)',
    },
    emptyIconCircle: {
        width: 88,
        height: 88,
        borderRadius: 44,
        backgroundColor: 'rgba(255, 255, 255, 0.1)',
        alignItems: 'center',
        justifyContent: 'center',
        marginBottom: 16,
    },
    emptyTitle: {
        fontSize: 20,
        fontFamily: 'NotoSans-Bold',
        color: '#ffffff',
        textAlign: 'center',
        marginBottom: 8,
    },
    emptySubtitle: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
        color: '#cbd5e1',
        textAlign: 'center',
        lineHeight: 20,
        marginBottom: 24,
    },
    primaryActionButton: {
        borderRadius: 14,
        overflow: 'hidden',
        width: '100%',
    },
    btnGradient: {
        paddingVertical: 14,
        alignItems: 'center',
        borderRadius: 14,
    },
    primaryBtnText: {
        color: '#ffffff',
        fontSize: 15,
        fontFamily: 'NotoSans-Bold',
    },
});

export default PerformanceReportScreen;
