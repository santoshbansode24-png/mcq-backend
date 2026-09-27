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
    Dimensions
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useFocusEffect } from '@react-navigation/native';
import axios from 'axios';
import { API_URL } from '../api/config';
import MathJaxWebView from '../components/MathJaxWebView';

const { width } = Dimensions.get('window');

// ─────────────────────────────────────────────────────────────────────────────
// Helpers & SmartText for Questions
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

const PerformanceReportScreen = ({ navigation, route, user }) => {
    const { themeColors, title, subtitle } = route.params || {};
    const currentThemeColors = themeColors || ['#4f46e5', '#6366f1'];
    const screenTitle = title || 'Performance Report';
    const screenSubtitle = subtitle || 'Exam Analytics & Negative Marking';

    const [performanceData, setPerformanceData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [subTab, setSubTab] = useState('negative'); // 'negative' | 'chapters' | 'attempts'
    const [chapterCategory, setChapterCategory] = useState('weak'); // 'weak' | 'average' | 'strong'
    const [mistakeLoading, setMistakeLoading] = useState(false);
    const [actionLoading, setActionLoading] = useState(false);

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
            console.log('[PerformanceReport] Error reading user_data', e);
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
            console.log('[PerformanceReport] Error loading performance data:', error);
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
                    subjectName: 'Practice My Mistakes'
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

    const practiceChapter = async (chapterId, chapterName) => {
        setActionLoading(true);
        try {
            const response = await axios.post(`${API_URL}/generate_custom_test.php`, {
                chapter_ids: String(chapterId),
                limit: 15
            });

            if (response.data?.status === 'success' && response.data.data?.length > 0) {
                navigation.navigate('MyExamTest', {
                    questions: response.data.data,
                    totalQuestions: response.data.data.length,
                    subjectName: chapterName
                });
            } else {
                Alert.alert('Notice', 'No MCQs available for this chapter.');
            }
        } catch (error) {
            Alert.alert('Error', 'Failed to generate test for this chapter.');
        } finally {
            setActionLoading(false);
        }
    };

    const stats = performanceData?.stats || {};
    const rankInfo = performanceData?.rank_info || { user_rank: 1, total_students: 1, percentile: 100 };
    const negativeQuestions = performanceData?.negative_questions || [];
    const chapterBreakdown = performanceData?.chapter_breakdown || { strong_chapters: [], average_chapters: [], weak_chapters: [] };
    const recentAttempts = performanceData?.recent_attempts || [];

    const hasExams = (parseInt(stats.total_exams) || 0) > 0 || recentAttempts.length > 0;

    const displayedChapters = chapterCategory === 'weak'
        ? chapterBreakdown.weak_chapters
        : chapterCategory === 'average'
        ? chapterBreakdown.average_chapters
        : chapterBreakdown.strong_chapters;

    return (
        <View style={styles.mainWrapper}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />

            {/* Header */}
            <LinearGradient colors={currentThemeColors} style={styles.headerGradient}>
                <SafeAreaView edges={['top']} style={styles.headerSafe}>
                    <View style={styles.header}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Text style={styles.backButtonText}>←</Text>
                        </TouchableOpacity>
                        <View style={styles.headerTextContainer}>
                            <Text style={styles.headerTitle}>{screenTitle}</Text>
                            <Text style={styles.headerSubtitle}>{screenSubtitle}</Text>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            {loading && !performanceData ? (
                <View style={styles.loadingContainer}>
                    <ActivityIndicator size="large" color="#4f46e5" />
                    <Text style={styles.loadingText}>Loading your performance report...</Text>
                </View>
            ) : (
                <ScrollView
                    style={styles.container}
                    contentContainerStyle={styles.scrollContent}
                    refreshControl={
                        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={['#4f46e5']} />
                    }
                    showsVerticalScrollIndicator={false}
                >
                    {!hasExams ? (
                        <View style={styles.emptyCard}>
                            <Ionicons name="bar-chart-outline" size={64} color="#94a3b8" style={{ marginBottom: 12 }} />
                            <Text style={styles.emptyTitle}>No Exam Attempts Yet</Text>
                            <Text style={styles.emptySubtitle}>
                                Take your first exam using the "My Exam" feature on the dashboard to unlock your comprehensive performance analysis, negative marking tracking, and class standing!
                            </Text>
                            <TouchableOpacity
                                style={styles.emptyButton}
                                onPress={() => navigation.navigate('MyExam')}
                                activeOpacity={0.8}
                            >
                                <LinearGradient colors={['#4f46e5', '#6366f1']} style={styles.emptyButtonGrad}>
                                    <Text style={styles.emptyButtonText}>Start a Test Now</Text>
                                    <Ionicons name="arrow-forward" size={18} color="white" style={{ marginLeft: 6 }} />
                                </LinearGradient>
                            </TouchableOpacity>
                        </View>
                    ) : (
                        <>
                            {/* Class Standing & Benchmark */}
                            <View style={styles.rankBanner}>
                                <LinearGradient colors={['#1e293b', '#0f172a']} style={styles.rankBannerGradient}>
                                    <View style={styles.rankBannerLeft}>
                                        <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 4 }}>
                                            <Ionicons name="trophy" size={20} color="#f59e0b" style={{ marginRight: 6 }} />
                                            <Text style={styles.rankBannerTitle}>Class Standing</Text>
                                        </View>
                                        <Text style={styles.rankBannerSubtitle}>
                                            Top {rankInfo.percentile || 100}% of {rankInfo.total_students || 1} students in your class
                                        </Text>
                                    </View>
                                    <View style={styles.rankBadge}>
                                        <Text style={styles.rankBadgeNumber}>#{rankInfo.user_rank || 1}</Text>
                                        <Text style={styles.rankBadgeLabel}>Class Rank</Text>
                                    </View>
                                </LinearGradient>
                            </View>

                            {/* Overall Score Metrics (2x2 Grid) */}
                            <View style={styles.metricsGrid}>
                                <View style={[styles.metricCard, { backgroundColor: '#f0fdf4', borderColor: '#bbf7d0' }]}>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 6 }}>
                                        <Ionicons name="trending-up" size={18} color="#16a34a" style={{ marginRight: 6 }} />
                                        <Text style={[styles.metricTitle, { color: '#15803d' }]}>Avg Net Score</Text>
                                    </View>
                                    <Text style={[styles.metricValue, { color: '#16a34a' }]}>
                                        {stats.avg_net_score > 0 ? `+${stats.avg_net_score}` : stats.avg_net_score || '0.00'}
                                    </Text>
                                    <Text style={styles.metricSub}>Net Points / Exam</Text>
                                </View>

                                <View style={[styles.metricCard, { backgroundColor: '#eff6ff', borderColor: '#bfdbfe' }]}>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 6 }}>
                                        <Ionicons name="checkmark-done-circle" size={18} color="#2563eb" style={{ marginRight: 6 }} />
                                        <Text style={[styles.metricTitle, { color: '#1d4ed8' }]}>Accuracy</Text>
                                    </View>
                                    <Text style={[styles.metricValue, { color: '#2563eb' }]}>
                                        {stats.overall_accuracy_pct || '0'}%
                                    </Text>
                                    <Text style={styles.metricSub}>Overall Accuracy</Text>
                                </View>

                                <View style={[styles.metricCard, { backgroundColor: '#fef2f2', borderColor: '#fecaca' }]}>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 6 }}>
                                        <Ionicons name="remove-circle" size={18} color="#dc2626" style={{ marginRight: 6 }} />
                                        <Text style={[styles.metricTitle, { color: '#b91c1c' }]}>Negative Marks</Text>
                                    </View>
                                    <Text style={[styles.metricValue, { color: '#dc2626' }]}>
                                        -{stats.total_negative_marks_lost || '0.00'}
                                    </Text>
                                    <Text style={styles.metricSub}>Penalty Deductions</Text>
                                </View>

                                <View style={[styles.metricCard, { backgroundColor: '#faf5ff', borderColor: '#e9d5ff' }]}>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 6 }}>
                                        <Ionicons name="newspaper-outline" size={18} color="#9333ea" style={{ marginRight: 6 }} />
                                        <Text style={[styles.metricTitle, { color: '#7e22ce' }]}>Total Exams</Text>
                                    </View>
                                    <Text style={[styles.metricValue, { color: '#9333ea' }]}>
                                        {stats.total_exams || 0}
                                    </Text>
                                    <Text style={styles.metricSub}>{stats.total_correct || 0} Correct Answers</Text>
                                </View>
                            </View>

                            {/* "Practice My Mistakes" 1-Click Action Card */}
                            <TouchableOpacity
                                style={styles.mistakeBanner}
                                onPress={practiceMistakes}
                                disabled={mistakeLoading}
                                activeOpacity={0.85}
                            >
                                <LinearGradient colors={['#6366f1', '#4f46e5']} style={styles.mistakeGradient}>
                                    <View style={styles.mistakeIconBox}>
                                        <Ionicons name="flash" size={26} color="#fbbf24" />
                                    </View>
                                    <View style={{ flex: 1, paddingRight: 8 }}>
                                        <Text style={styles.mistakeTitle}>Practice My Mistakes</Text>
                                        <Text style={styles.mistakeSub}>
                                            Target and re-attempt questions where you lost negative marks!
                                        </Text>
                                    </View>
                                    {mistakeLoading ? (
                                        <ActivityIndicator color="white" />
                                    ) : (
                                        <View style={styles.mistakeButtonPill}>
                                            <Text style={styles.mistakeButtonText}>Start</Text>
                                            <Ionicons name="arrow-forward" size={14} color="#4f46e5" />
                                        </View>
                                    )}
                                </LinearGradient>
                            </TouchableOpacity>

                            {/* Sub-tab Navigation */}
                            <View style={styles.subTabContainer}>
                                <TouchableOpacity
                                    style={[styles.subTabButton, subTab === 'negative' && styles.subTabButtonActive]}
                                    onPress={() => setSubTab('negative')}
                                >
                                    <Ionicons
                                        name="alert-circle"
                                        size={16}
                                        color={subTab === 'negative' ? '#dc2626' : '#64748b'}
                                        style={{ marginRight: 4 }}
                                    />
                                    <Text style={[styles.subTabText, subTab === 'negative' && styles.subTabTextActive]}>
                                        Negative Questions ({negativeQuestions.length})
                                    </Text>
                                </TouchableOpacity>

                                <TouchableOpacity
                                    style={[styles.subTabButton, subTab === 'chapters' && styles.subTabButtonActive]}
                                    onPress={() => setSubTab('chapters')}
                                >
                                    <Ionicons
                                        name="list"
                                        size={16}
                                        color={subTab === 'chapters' ? '#4f46e5' : '#64748b'}
                                        style={{ marginRight: 4 }}
                                    />
                                    <Text style={[styles.subTabText, subTab === 'chapters' && styles.subTabTextActive]}>
                                        Chapter Weakness
                                    </Text>
                                </TouchableOpacity>

                                <TouchableOpacity
                                    style={[styles.subTabButton, subTab === 'attempts' && styles.subTabButtonActive]}
                                    onPress={() => setSubTab('attempts')}
                                >
                                    <Ionicons
                                        name="time"
                                        size={16}
                                        color={subTab === 'attempts' ? '#4f46e5' : '#64748b'}
                                        style={{ marginRight: 4 }}
                                    />
                                    <Text style={[styles.subTabText, subTab === 'attempts' && styles.subTabTextActive]}>
                                        History ({recentAttempts.length})
                                    </Text>
                                </TouchableOpacity>
                            </View>

                            {/* View 1: Dedicated Negative Questions */}
                            {subTab === 'negative' && (
                                <View style={styles.subViewContainer}>
                                    {negativeQuestions.length === 0 ? (
                                        <View style={styles.noNegativeCard}>
                                            <Ionicons name="shield-checkmark" size={48} color="#10b981" style={{ marginBottom: 8 }} />
                                            <Text style={styles.noNegativeTitle}>No Negative Questions!</Text>
                                            <Text style={styles.noNegativeSub}>
                                                You haven't lost any marks to negative penalties in your exams. Great accuracy!
                                            </Text>
                                        </View>
                                    ) : (
                                        negativeQuestions.map((item, idx) => (
                                            <View key={item.answer_id || idx} style={styles.negativeCard}>
                                                <View style={styles.negCardHeader}>
                                                    <View style={styles.negTagBox}>
                                                        <Text style={styles.negTagText}>
                                                            {item.subject_name || 'Exam'} • {item.chapter_name || 'Practice'}
                                                        </Text>
                                                    </View>
                                                    <View style={styles.penaltyBadge}>
                                                        <Text style={styles.penaltyText}>🔴 -1.00 Mark Penalty</Text>
                                                    </View>
                                                </View>

                                                <View style={styles.negQuestionBox}>
                                                    <SmartText
                                                        content={decodeHtml(item.question)}
                                                        textColor="#0f172a"
                                                        fontSize="15px"
                                                        fontWeight="bold"
                                                    />
                                                </View>

                                                {/* Wrong Answer Chosen */}
                                                <View style={styles.negAnswerRow}>
                                                    <Text style={styles.negAnswerLabel}>Your Wrong Choice:</Text>
                                                    <View style={[styles.negAnswerPill, { backgroundColor: '#fee2e2', borderColor: '#fca5a5' }]}>
                                                        <Ionicons name="close-circle" size={16} color="#dc2626" style={{ marginRight: 6 }} />
                                                        <Text style={[styles.negAnswerText, { color: '#b91c1c' }]}>
                                                            Option {item.selected_option ? item.selected_option.toUpperCase() : 'None'}:{' '}
                                                            {decodeHtml(item[`option_${item.selected_option}`] || '')}
                                                        </Text>
                                                    </View>
                                                </View>

                                                {/* Correct Answer */}
                                                <View style={styles.negAnswerRow}>
                                                    <Text style={styles.negAnswerLabel}>Correct Answer:</Text>
                                                    <View style={[styles.negAnswerPill, { backgroundColor: '#dcfce7', borderColor: '#86efac' }]}>
                                                        <Ionicons name="checkmark-circle" size={16} color="#16a34a" style={{ marginRight: 6 }} />
                                                        <Text style={[styles.negAnswerText, { color: '#15803d' }]}>
                                                            Option {item.correct_option ? item.correct_option.toUpperCase() : ''}:{' '}
                                                            {decodeHtml(item[`option_${item.correct_option}`] || '')}
                                                        </Text>
                                                    </View>
                                                </View>

                                                {/* Explanation */}
                                                {item.explanation && (
                                                    <View style={styles.negExplanationBox}>
                                                        <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 4 }}>
                                                            <Ionicons name="bulb-outline" size={16} color="#0369a1" style={{ marginRight: 6 }} />
                                                            <Text style={styles.negExplanationTitle}>Solution & Explanation</Text>
                                                        </View>
                                                        <SmartText
                                                            content={decodeHtml(item.explanation)}
                                                            textColor="#0c4a6e"
                                                            fontSize="13px"
                                                        />
                                                    </View>
                                                )}
                                            </View>
                                        ))
                                    )}
                                </View>
                            )}

                            {/* View 2: Chapter Weakness Breakdown */}
                            {subTab === 'chapters' && (
                                <View style={styles.subViewContainer}>
                                    <View style={styles.chapterCategoryRow}>
                                        <TouchableOpacity
                                            style={[styles.categoryPill, chapterCategory === 'weak' && styles.categoryPillWeak]}
                                            onPress={() => setChapterCategory('weak')}
                                        >
                                            <Text style={[styles.categoryPillText, chapterCategory === 'weak' && styles.categoryPillTextActive]}>
                                                Weak &lt;60% ({chapterBreakdown.weak_chapters.length})
                                            </Text>
                                        </TouchableOpacity>

                                        <TouchableOpacity
                                            style={[styles.categoryPill, chapterCategory === 'average' && styles.categoryPillAvg]}
                                            onPress={() => setChapterCategory('average')}
                                        >
                                            <Text style={[styles.categoryPillText, chapterCategory === 'average' && styles.categoryPillTextActive]}>
                                                Average 60-79% ({chapterBreakdown.average_chapters.length})
                                            </Text>
                                        </TouchableOpacity>

                                        <TouchableOpacity
                                            style={[styles.categoryPill, chapterCategory === 'strong' && styles.categoryPillStrong]}
                                            onPress={() => setChapterCategory('strong')}
                                        >
                                            <Text style={[styles.categoryPillText, chapterCategory === 'strong' && styles.categoryPillTextActive]}>
                                                Strong ≥80% ({chapterBreakdown.strong_chapters.length})
                                            </Text>
                                        </TouchableOpacity>
                                    </View>

                                    {displayedChapters.length === 0 ? (
                                        <View style={styles.noChaptersCard}>
                                            <Text style={styles.noChaptersText}>
                                                No chapters currently in the {chapterCategory} category.
                                            </Text>
                                        </View>
                                    ) : (
                                        displayedChapters.map((ch, idx) => {
                                            const progressColor = chapterCategory === 'weak' ? '#ef4444' : chapterCategory === 'average' ? '#f59e0b' : '#10b981';
                                            return (
                                                <View key={ch.chapter_id || idx} style={styles.chapterCard}>
                                                    <View style={styles.chapterCardHeader}>
                                                        <View style={{ flex: 1, paddingRight: 10 }}>
                                                            <Text style={styles.chapterCardSubject}>{ch.subject_name}</Text>
                                                            <Text style={styles.chapterCardName}>{ch.chapter_name}</Text>
                                                        </View>
                                                        <View style={[styles.accuracyBadge, { backgroundColor: `${progressColor}15`, borderColor: progressColor }]}>
                                                            <Text style={[styles.accuracyBadgeText, { color: progressColor }]}>
                                                                {ch.accuracy_pct}% Accuracy
                                                            </Text>
                                                        </View>
                                                    </View>

                                                    {/* Progress Bar */}
                                                    <View style={styles.progressBarTrack}>
                                                        <View style={[styles.progressBarFill, { width: `${Math.min(ch.accuracy_pct, 100)}%`, backgroundColor: progressColor }]} />
                                                    </View>

                                                    <View style={styles.chapterCardFooter}>
                                                        <Text style={styles.chapterCardStats}>
                                                            {ch.correct_count} correct • {ch.wrong_count} wrong • {ch.total_attempted} attempts
                                                        </Text>
                                                        {chapterCategory === 'weak' && (
                                                            <TouchableOpacity
                                                                style={styles.strengthenButton}
                                                                onPress={() => practiceChapter(ch.chapter_id, ch.chapter_name)}
                                                                disabled={actionLoading}
                                                            >
                                                                <Text style={styles.strengthenButtonText}>Strengthen Chapter</Text>
                                                                <Ionicons name="arrow-forward" size={12} color="#4f46e5" />
                                                            </TouchableOpacity>
                                                        )}
                                                    </View>
                                                </View>
                                            );
                                        })
                                    )}
                                </View>
                            )}

                            {/* View 3: Recent Exams History */}
                            {subTab === 'attempts' && (
                                <View style={styles.subViewContainer}>
                                    {recentAttempts.length === 0 ? (
                                        <View style={styles.noChaptersCard}>
                                            <Text style={styles.noChaptersText}>No recorded test attempts yet.</Text>
                                        </View>
                                    ) : (
                                        recentAttempts.map((att, idx) => (
                                            <View key={att.attempt_id || idx} style={styles.attemptCard}>
                                                <View style={styles.attemptCardHeader}>
                                                    <View style={{ flex: 1 }}>
                                                        <Text style={styles.attemptCardTitle}>
                                                            {att.exam_title || 'Custom Practice Exam'}
                                                        </Text>
                                                        <Text style={styles.attemptCardDate}>
                                                            {att.completed_at ? new Date(att.completed_at).toLocaleDateString() : 'Recent'}
                                                        </Text>
                                                    </View>
                                                    <View style={styles.attemptNetScoreBadge}>
                                                        <Text style={styles.attemptNetScoreText}>
                                                            Net: {att.net_score > 0 ? `+${att.net_score}` : att.net_score} pts
                                                        </Text>
                                                    </View>
                                                </View>

                                                <View style={styles.attemptStatsRow}>
                                                    <View style={styles.attemptStatItem}>
                                                        <Text style={styles.attemptStatLabel}>Correct</Text>
                                                        <Text style={[styles.attemptStatVal, { color: '#16a34a' }]}>
                                                            +{att.positive_score} ({att.correct_count} Qs)
                                                        </Text>
                                                    </View>
                                                    <View style={styles.attemptStatItem}>
                                                        <Text style={styles.attemptStatLabel}>Negative Penalty</Text>
                                                        <Text style={[styles.attemptStatVal, { color: '#dc2626' }]}>
                                                            -{att.negative_deduction} ({att.wrong_count} Qs)
                                                        </Text>
                                                    </View>
                                                    <View style={styles.attemptStatItem}>
                                                        <Text style={styles.attemptStatLabel}>Accuracy</Text>
                                                        <Text style={[styles.attemptStatVal, { color: '#2563eb' }]}>
                                                            {att.accuracy_percentage}%
                                                        </Text>
                                                    </View>
                                                </View>
                                            </View>
                                        ))
                                    )}
                                </View>
                            )}
                        </>
                    )}
                </ScrollView>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    mainWrapper: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    headerGradient: {
        paddingBottom: 16,
    },
    headerSafe: {
        backgroundColor: 'transparent',
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        paddingBottom: 6,
    },
    backButton: {
        width: 40,
        height: 40,
        borderRadius: 20,
        backgroundColor: 'rgba(255,255,255,0.2)',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 15,
    },
    backButtonText: {
        fontSize: 24,
        color: 'white',
        fontWeight: 'bold',
    },
    headerTextContainer: {
        flex: 1,
    },
    headerTitle: {
        fontSize: 22,
        fontWeight: 'bold',
        color: 'white',
    },
    headerSubtitle: {
        fontSize: 13,
        color: 'rgba(255,255,255,0.9)',
        marginTop: 2,
    },
    container: {
        flex: 1,
    },
    scrollContent: {
        padding: 16,
        paddingBottom: 40,
    },
    loadingContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        paddingVertical: 80,
    },
    loadingText: {
        marginTop: 12,
        fontSize: 14,
        color: '#64748b',
        fontWeight: '600',
    },
    rankBanner: {
        borderRadius: 16,
        overflow: 'hidden',
        marginBottom: 16,
        elevation: 3,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.15,
        shadowRadius: 6,
    },
    rankBannerGradient: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: 16,
    },
    rankBannerLeft: {
        flex: 1,
    },
    rankBannerTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#f8fafc',
    },
    rankBannerSubtitle: {
        fontSize: 12,
        color: '#94a3b8',
        marginTop: 2,
    },
    rankBadge: {
        backgroundColor: 'rgba(245, 158, 11, 0.15)',
        borderWidth: 1,
        borderColor: '#f59e0b',
        borderRadius: 12,
        paddingHorizontal: 16,
        paddingVertical: 8,
        alignItems: 'center',
    },
    rankBadgeNumber: {
        fontSize: 22,
        fontWeight: '900',
        color: '#f59e0b',
    },
    rankBadgeLabel: {
        fontSize: 10,
        fontWeight: '700',
        color: '#fbbf24',
        textTransform: 'uppercase',
    },
    metricsGrid: {
        flexDirection: 'row',
        flexWrap: 'wrap',
        gap: 10,
        marginBottom: 16,
    },
    metricCard: {
        width: (width - 42) / 2,
        padding: 14,
        borderRadius: 14,
        borderWidth: 1,
    },
    metricTitle: {
        fontSize: 12,
        fontWeight: '700',
        textTransform: 'uppercase',
        letterSpacing: 0.3,
    },
    metricValue: {
        fontSize: 22,
        fontWeight: '900',
        marginBottom: 2,
    },
    metricSub: {
        fontSize: 11,
        color: '#64748b',
    },
    mistakeBanner: {
        borderRadius: 16,
        overflow: 'hidden',
        marginBottom: 20,
        elevation: 4,
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 8,
    },
    mistakeGradient: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 16,
    },
    mistakeIconBox: {
        width: 44,
        height: 44,
        borderRadius: 22,
        backgroundColor: 'rgba(255, 255, 255, 0.2)',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 12,
    },
    mistakeTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        color: 'white',
    },
    mistakeSub: {
        fontSize: 12,
        color: 'rgba(255, 255, 255, 0.85)',
        marginTop: 2,
    },
    mistakeButtonPill: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'white',
        paddingHorizontal: 14,
        paddingVertical: 8,
        borderRadius: 20,
        gap: 4,
    },
    mistakeButtonText: {
        fontSize: 13,
        fontWeight: 'bold',
        color: '#4f46e5',
    },
    subTabContainer: {
        flexDirection: 'row',
        backgroundColor: '#ffffff',
        borderRadius: 12,
        padding: 4,
        marginBottom: 16,
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    subTabButton: {
        flex: 1,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 10,
        borderRadius: 8,
    },
    subTabButtonActive: {
        backgroundColor: '#eef2ff',
        borderWidth: 1,
        borderColor: '#c7d2fe',
    },
    subTabText: {
        fontSize: 11,
        fontWeight: '600',
        color: '#64748b',
    },
    subTabTextActive: {
        color: '#4f46e5',
        fontWeight: '700',
    },
    subViewContainer: {
        gap: 12,
    },
    noNegativeCard: {
        backgroundColor: 'white',
        borderRadius: 16,
        padding: 30,
        alignItems: 'center',
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    noNegativeTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#0f172a',
        marginBottom: 4,
    },
    noNegativeSub: {
        fontSize: 13,
        color: '#64748b',
        textAlign: 'center',
        lineHeight: 18,
    },
    negativeCard: {
        backgroundColor: 'white',
        borderRadius: 16,
        padding: 16,
        borderWidth: 1,
        borderColor: '#fecaca',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.04,
        shadowRadius: 6,
        elevation: 1,
    },
    negCardHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 10,
    },
    negTagBox: {
        backgroundColor: '#e0f2fe',
        paddingHorizontal: 8,
        paddingVertical: 3,
        borderRadius: 6,
        maxWidth: '65%',
    },
    negTagText: {
        fontSize: 11,
        fontWeight: '700',
        color: '#0369a1',
    },
    penaltyBadge: {
        backgroundColor: '#fee2e2',
        paddingHorizontal: 8,
        paddingVertical: 3,
        borderRadius: 6,
    },
    penaltyText: {
        fontSize: 11,
        fontWeight: 'bold',
        color: '#dc2626',
    },
    negQuestionBox: {
        marginBottom: 12,
    },
    negAnswerRow: {
        marginBottom: 8,
    },
    negAnswerLabel: {
        fontSize: 11,
        fontWeight: '700',
        color: '#64748b',
        marginBottom: 4,
        textTransform: 'uppercase',
    },
    negAnswerPill: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 10,
        borderRadius: 10,
        borderWidth: 1,
    },
    negAnswerText: {
        fontSize: 13,
        fontWeight: '600',
        flex: 1,
    },
    negExplanationBox: {
        backgroundColor: '#f0f9ff',
        borderRadius: 10,
        padding: 12,
        marginTop: 8,
        borderWidth: 1,
        borderColor: '#e0f2fe',
        borderLeftWidth: 4,
        borderLeftColor: '#0ea5e9',
    },
    negExplanationTitle: {
        fontSize: 12,
        fontWeight: 'bold',
        color: '#0369a1',
    },
    chapterCategoryRow: {
        flexDirection: 'row',
        gap: 8,
        marginBottom: 8,
    },
    categoryPill: {
        flex: 1,
        paddingVertical: 8,
        borderRadius: 10,
        backgroundColor: '#f1f5f9',
        alignItems: 'center',
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    categoryPillWeak: {
        backgroundColor: '#fee2e2',
        borderColor: '#fca5a5',
    },
    categoryPillAvg: {
        backgroundColor: '#fef3c7',
        borderColor: '#fde68a',
    },
    categoryPillStrong: {
        backgroundColor: '#dcfce7',
        borderColor: '#86efac',
    },
    categoryPillText: {
        fontSize: 11,
        fontWeight: '600',
        color: '#64748b',
    },
    categoryPillTextActive: {
        fontWeight: 'bold',
        color: '#0f172a',
    },
    chapterCard: {
        backgroundColor: 'white',
        borderRadius: 14,
        padding: 14,
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    chapterCardHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
        marginBottom: 8,
    },
    chapterCardSubject: {
        fontSize: 11,
        fontWeight: '700',
        color: '#64748b',
        textTransform: 'uppercase',
    },
    chapterCardName: {
        fontSize: 14,
        fontWeight: 'bold',
        color: '#0f172a',
        marginTop: 2,
    },
    accuracyBadge: {
        paddingHorizontal: 8,
        paddingVertical: 4,
        borderRadius: 8,
        borderWidth: 1,
    },
    accuracyBadgeText: {
        fontSize: 11,
        fontWeight: 'bold',
    },
    progressBarTrack: {
        height: 6,
        backgroundColor: '#f1f5f9',
        borderRadius: 3,
        overflow: 'hidden',
        marginVertical: 8,
    },
    progressBarFill: {
        height: '100%',
        borderRadius: 3,
    },
    chapterCardFooter: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginTop: 4,
    },
    chapterCardStats: {
        fontSize: 11,
        color: '#64748b',
    },
    strengthenButton: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#eef2ff',
        paddingHorizontal: 10,
        paddingVertical: 5,
        borderRadius: 8,
        gap: 4,
    },
    strengthenButtonText: {
        fontSize: 11,
        fontWeight: 'bold',
        color: '#4f46e5',
    },
    noChaptersCard: {
        backgroundColor: 'white',
        borderRadius: 14,
        padding: 24,
        alignItems: 'center',
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    noChaptersText: {
        fontSize: 13,
        color: '#94a3b8',
    },
    attemptCard: {
        backgroundColor: 'white',
        borderRadius: 14,
        padding: 14,
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    attemptCardHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 10,
        borderBottomWidth: 1,
        borderBottomColor: '#f1f5f9',
        paddingBottom: 8,
    },
    attemptCardTitle: {
        fontSize: 14,
        fontWeight: 'bold',
        color: '#0f172a',
    },
    attemptCardDate: {
        fontSize: 11,
        color: '#94a3b8',
        marginTop: 2,
    },
    attemptNetScoreBadge: {
        backgroundColor: '#eef2ff',
        borderWidth: 1,
        borderColor: '#c7d2fe',
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 8,
    },
    attemptNetScoreText: {
        fontSize: 12,
        fontWeight: 'bold',
        color: '#4f46e5',
    },
    attemptStatsRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
    },
    attemptStatItem: {
        flex: 1,
    },
    attemptStatLabel: {
        fontSize: 10,
        color: '#64748b',
        textTransform: 'uppercase',
        fontWeight: '600',
    },
    attemptStatVal: {
        fontSize: 12,
        fontWeight: 'bold',
        marginTop: 2,
    },
    emptyCard: {
        backgroundColor: 'white',
        borderRadius: 20,
        padding: 30,
        alignItems: 'center',
        borderWidth: 1,
        borderColor: '#e2e8f0',
        marginTop: 20,
    },
    emptyTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#0f172a',
        marginBottom: 8,
    },
    emptySubtitle: {
        fontSize: 14,
        color: '#64748b',
        textAlign: 'center',
        lineHeight: 20,
        marginBottom: 20,
    },
    emptyButton: {
        borderRadius: 14,
        overflow: 'hidden',
    },
    emptyButtonGrad: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        paddingVertical: 14,
        gap: 6,
    },
    emptyButtonText: {
        color: 'white',
        fontSize: 15,
        fontWeight: 'bold',
    },
});

export default PerformanceReportScreen;
