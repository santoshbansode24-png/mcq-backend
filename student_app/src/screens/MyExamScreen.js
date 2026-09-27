import React, { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    SectionList,
    TextInput,
    ActivityIndicator,
    Alert,
    StatusBar,
    Platform,
    RefreshControl,
    ScrollView,
    Dimensions
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useFocusEffect } from '@react-navigation/native';
import { fetchSubjects } from '../api/subjects';
import { fetchChapters } from '../api/chapters';
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

// Memoized Subject Card to prevent expensive gradient re-renders
const SubjectCard = React.memo(({ subject, index, isSelected, onPress }) => {
    const gradients = [
        ['#FF416C', '#FF4B2B'], ['#4776E6', '#8E54E9'], ['#00B4DB', '#0083B0'],
        ['#11998E', '#38EF7D'], ['#F7971E', '#FFD200'], ['#EC008C', '#FC6767'],
        ['#1A2980', '#26D0CE'], ['#F09819', '#EDDE5D']
    ];
    const colors = gradients[index % gradients.length];
    
    return (
        <TouchableOpacity
            style={[styles.subjectCard, isSelected && styles.subjectCardSelected]}
            onPress={() => onPress(subject)}
            activeOpacity={0.7}
        >
            <LinearGradient colors={isSelected ? ['#1e293b', '#0f172a'] : colors} style={styles.subjectGradient}>
                <Text style={styles.subjectIcon}>{subject.subject_name.charAt(0).toUpperCase()}</Text>
                <Text style={styles.subjectName}>{subject.subject_name}</Text>
                {isSelected && (
                    <View style={[styles.selectedBadge, { backgroundColor: '#10b981' }]}>
                        <Text style={styles.selectedBadgeText}>✓</Text>
                    </View>
                )}
            </LinearGradient>
        </TouchableOpacity>
    );
});

// Memoized Chapter Item to prevent massive re-renders when selecting a single chapter
const ChapterItem = React.memo(({ chapter, isSelected, onPress }) => {
    return (
        <TouchableOpacity
            style={[styles.chapterItem, isSelected && styles.chapterItemSelected]}
            onPress={() => onPress(chapter.chapter_id)}
        >
            <View style={[styles.checkbox, isSelected && styles.checkboxSelected]}>
                {isSelected && <Text style={styles.checkmark}>✓</Text>}
            </View>
            <View style={styles.chapterInfo}>
                <Text style={[styles.chapterName, isSelected && styles.chapterNameSelected]}>
                    {chapter.chapter_name}
                </Text>
                <Text style={styles.chapterStats}>
                    {chapter.total_mcqs || 0} MCQs available
                </Text>
            </View>
        </TouchableOpacity>
    );
});

const MyExamScreen = ({ navigation, route, user }) => {
    // Tab Navigation: 'create' | 'performance'
    const [activeTab, setActiveTab] = useState(route.params?.initialTab || 'create');

    // Use params if provided, otherwise fallback to user defaults
    const { overrideClassId, themeColors, title, subtitle } = route.params || {};
    const classId = overrideClassId || user?.class_id;

    // Theme configurations
    const currentThemeColors = themeColors || ['#00c6ff', '#0072ff'];
    const screenTitle = title || 'My Exam';
    const screenSubtitle = subtitle || (activeTab === 'create' ? 'Create Your Custom Test' : 'Student Performance Report');

    // Test Creation State
    const [subjects, setSubjects] = useState([]);
    const [chapters, setChapters] = useState([]); // Array of { subjectName, data: [] }
    const [selectedSubjects, setSelectedSubjects] = useState([]);
    const [selectedChapters, setSelectedChapters] = useState([]);
    const [questionLimit, setQuestionLimit] = useState('25');
    const [loading, setLoading] = useState(false);
    const [loadingChapters, setLoadingChapters] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

    // Performance Report State
    const [performanceData, setPerformanceData] = useState(null);
    const [perfLoading, setPerfLoading] = useState(false);
    const [perfRefreshing, setPerfRefreshing] = useState(false);
    const [perfSubTab, setPerfSubTab] = useState('negative'); // 'negative' | 'chapters' | 'attempts'
    const [chapterCategory, setChapterCategory] = useState('weak'); // 'weak' | 'average' | 'strong'
    const [mistakeLoading, setMistakeLoading] = useState(false);

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
            console.log('[MyExam] Error reading user_data', e);
        }
        return null;
    }, [user]);

    useFocusEffect(
        useCallback(() => {
            if (activeTab === 'create') {
                loadSubjects();
            } else {
                loadPerformance();
            }
        }, [activeTab])
    );

    const onRefresh = useCallback(() => {
        if (activeTab === 'create') {
            setRefreshing(true);
            loadSubjects().then(() => setRefreshing(false));
        } else {
            setPerfRefreshing(true);
            loadPerformance().then(() => setPerfRefreshing(false));
        }
    }, [activeTab]);

    const debounceRef = useRef(null);

    useEffect(() => {
        if (selectedSubjects.length > 0) {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => {
                loadChapters();
            }, 350);
        } else {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            setChapters([]);
            setSelectedChapters([]);
        }
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [selectedSubjects]);

    const loadSubjects = async () => {
        if (!refreshing) setLoading(true);
        try {
            const response = await fetchSubjects(classId);
            if (response && (response.status === 'success' || Array.isArray(response))) {
                const subjectData = response.data || response;
                setSubjects(subjectData);
            } else if (response?.message !== 'No class selected' && response?.message !== 'No subjects found for this class') {
                Alert.alert('Error', response?.message || 'Failed to load subjects');
            }
        } catch (error) {
            Alert.alert('Connection Error', 'Failed to load subjects. Please check your network connection.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const loadChapters = async () => {
        setLoadingChapters(true);
        try {
            const promises = selectedSubjects.map(subject =>
                fetchChapters(subject.subject_id)
                    .then(res => ({
                        subjectName: subject.subject_name,
                        subjectId: subject.subject_id,
                        data: res.status === 'success' ? res.data : []
                    }))
                    .catch(() => ({
                        subjectName: subject.subject_name,
                        subjectId: subject.subject_id,
                        data: []
                    }))
            );

            const results = await Promise.all(promises);
            const groupedChapters = results.filter(r => r.data.length > 0);
            setChapters(groupedChapters);

            const allVisibleChapterIds = groupedChapters.flatMap(g => g.data.map(c => c.chapter_id));
            setSelectedChapters(prev => prev.filter(id => allVisibleChapterIds.includes(id)));
        } catch (error) {
            Alert.alert('Error', 'Failed to load chapters');
            console.error(error);
        } finally {
            setLoadingChapters(false);
        }
    };

    const loadPerformance = async () => {
        if (!perfRefreshing) setPerfLoading(true);
        try {
            const uid = await getUserId();
            if (!uid) {
                setPerfLoading(false);
                setPerfRefreshing(false);
                return;
            }
            const res = await axios.get(`${API_URL}/get_student_performance.php?user_id=${uid}`);
            if (res.data && res.data.status === 'success') {
                setPerformanceData(res.data.data);
            }
        } catch (error) {
            console.log('[Performance] Load error:', error);
        } finally {
            setPerfLoading(false);
            setPerfRefreshing(false);
        }
    };

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
        setLoading(true);
        try {
            const response = await axios.post(`${API_URL}/generate_custom_test.php`, {
                chapter_ids: String(chapterId),
                limit: 15
            });

            if (response.data.status === 'success' && response.data.data?.length > 0) {
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
            setLoading(false);
        }
    };

    const toggleSubject = useCallback((subject) => {
        setSelectedSubjects(prev => {
            const exists = prev.find(s => s.subject_id === subject.subject_id);
            if (exists) {
                return prev.filter(s => s.subject_id !== subject.subject_id);
            } else {
                return [...prev, subject];
            }
        });
    }, []);

    const toggleChapter = useCallback((chapterId) => {
        setSelectedChapters(prev => {
            if (prev.includes(chapterId)) {
                return prev.filter(id => id !== chapterId);
            } else {
                return [...prev, chapterId];
            }
        });
    }, []);

    const selectAllChapters = () => {
        const allChapterIds = chapters.flatMap(group => group.data.map(ch => ch.chapter_id));
        if (selectedChapters.length === allChapterIds.length) {
            setSelectedChapters([]);
        } else {
            setSelectedChapters(allChapterIds);
        }
    };

    const startTest = async () => {
        if (selectedSubjects.length === 0) {
            Alert.alert('Error', 'Please select at least one subject');
            return;
        }
        if (selectedChapters.length === 0) {
            Alert.alert('Error', 'Please select at least one chapter');
            return;
        }
        const limit = parseInt(questionLimit);
        if (!limit || limit < 1 || limit > 100) {
            Alert.alert('Error', 'Please enter a valid number of questions (1-100)');
            return;
        }

        setLoading(true);
        try {
            const response = await axios.post(`${API_URL}/generate_custom_test.php`, {
                chapter_ids: selectedChapters.join(','),
                limit: limit
            });

            if (response.data.status === 'success') {
                navigation.navigate('MyExamTest', {
                    questions: response.data.data,
                    totalQuestions: response.data.data.length,
                    subjectName: selectedSubjects.map(s => s.subject_name).join(' & ')
                });
            } else {
                Alert.alert('Error', response.data.message || 'Failed to generate test');
            }
        } catch (error) {
            const errorMsg = error.response?.data?.message || 'Failed to generate test. Please try again.';
            Alert.alert('Error', errorMsg);
        } finally {
            setLoading(false);
        }
    };

    // ─────────────────────────────────────────────────────────────────────────
    // Render Performance Report Content
    // ─────────────────────────────────────────────────────────────────────────
    const renderPerformanceReport = () => {
        if (perfLoading && !performanceData) {
            return (
                <View style={styles.perfLoadingContainer}>
                    <ActivityIndicator size="large" color="#0072ff" />
                    <Text style={styles.perfLoadingText}>Loading your performance report...</Text>
                </View>
            );
        }

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
            <ScrollView
                style={styles.perfScrollView}
                contentContainerStyle={styles.perfScrollContent}
                refreshControl={
                    <RefreshControl refreshing={perfRefreshing} onRefresh={onRefresh} colors={['#0072ff']} />
                }
                showsVerticalScrollIndicator={false}
            >
                {!hasExams ? (
                    <View style={styles.emptyPerfCard}>
                        <Ionicons name="bar-chart-outline" size={64} color="#94a3b8" style={{ marginBottom: 12 }} />
                        <Text style={styles.emptyPerfTitle}>No Exam Attempts Yet</Text>
                        <Text style={styles.emptyPerfSubtitle}>
                            Take your first custom exam in the "Create Test" tab to see your detailed performance report, negative marking analysis, and class rank!
                        </Text>
                        <TouchableOpacity
                            style={styles.emptyPerfButton}
                            onPress={() => setActiveTab('create')}
                            activeOpacity={0.8}
                        >
                            <LinearGradient colors={['#00c6ff', '#0072ff']} style={styles.emptyPerfButtonGrad}>
                                <Text style={styles.emptyPerfButtonText}>Create Your First Test</Text>
                                <Ionicons name="arrow-forward" size={18} color="white" style={{ marginLeft: 6 }} />
                            </LinearGradient>
                        </TouchableOpacity>
                    </View>
                ) : (
                    <>
                        {/* Class Rank & Percentile Banner */}
                        <View style={styles.rankBanner}>
                            <LinearGradient colors={['#1e293b', '#0f172a']} style={styles.rankBannerGradient}>
                                <View style={styles.rankBannerLeft}>
                                    <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 4 }}>
                                        <Ionicons name="trophy" size={20} color="#f59e0b" style={{ marginRight: 6 }} />
                                        <Text style={styles.rankBannerTitle}>Class Standing</Text>
                                    </View>
                                    <Text style={styles.rankBannerSubtitle}>
                                        Top {rankInfo.percentile || 100}% of {rankInfo.total_students || 1} students
                                    </Text>
                                </View>
                                <View style={styles.rankBadge}>
                                    <Text style={styles.rankBadgeNumber}>#{rankInfo.user_rank || 1}</Text>
                                    <Text style={styles.rankBadgeLabel}>Class Rank</Text>
                                </View>
                            </LinearGradient>
                        </View>

                        {/* Overall Metrics Cards (2x2 Grid) */}
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
                                style={[styles.subTabButton, perfSubTab === 'negative' && styles.subTabButtonActive]}
                                onPress={() => setPerfSubTab('negative')}
                            >
                                <Ionicons
                                    name="alert-circle"
                                    size={16}
                                    color={perfSubTab === 'negative' ? '#dc2626' : '#64748b'}
                                    style={{ marginRight: 4 }}
                                />
                                <Text style={[styles.subTabText, perfSubTab === 'negative' && styles.subTabTextActive]}>
                                    Negative Questions ({negativeQuestions.length})
                                </Text>
                            </TouchableOpacity>

                            <TouchableOpacity
                                style={[styles.subTabButton, perfSubTab === 'chapters' && styles.subTabButtonActive]}
                                onPress={() => setPerfSubTab('chapters')}
                            >
                                <Ionicons
                                    name="list"
                                    size={16}
                                    color={perfSubTab === 'chapters' ? '#0072ff' : '#64748b'}
                                    style={{ marginRight: 4 }}
                                />
                                <Text style={[styles.subTabText, perfSubTab === 'chapters' && styles.subTabTextActive]}>
                                    Chapter Weakness
                                </Text>
                            </TouchableOpacity>

                            <TouchableOpacity
                                style={[styles.subTabButton, perfSubTab === 'attempts' && styles.subTabButtonActive]}
                                onPress={() => setPerfSubTab('attempts')}
                            >
                                <Ionicons
                                    name="time"
                                    size={16}
                                    color={perfSubTab === 'attempts' ? '#0072ff' : '#64748b'}
                                    style={{ marginRight: 4 }}
                                />
                                <Text style={[styles.subTabText, perfSubTab === 'attempts' && styles.subTabTextActive]}>
                                    History ({recentAttempts.length})
                                </Text>
                            </TouchableOpacity>
                        </View>

                        {/* View 1: Dedicated Negative Questions */}
                        {perfSubTab === 'negative' && (
                            <View style={styles.subViewContainer}>
                                {negativeQuestions.length === 0 ? (
                                    <View style={styles.noNegativeCard}>
                                        <Ionicons name="shield-checkmark" size={48} color="#10b981" style={{ marginBottom: 8 }} />
                                        <Text style={styles.noNegativeTitle}>No Negative Questions!</Text>
                                        <Text style={styles.noNegativeSub}>
                                            You haven't lost any marks to negative penalties in your exams. Great job!
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
                        {perfSubTab === 'chapters' && (
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
                                                        >
                                                            <Text style={styles.strengthenButtonText}>Strengthen Chapter</Text>
                                                            <Ionicons name="arrow-forward" size={12} color="#0072ff" />
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
                        {perfSubTab === 'attempts' && (
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
        );
    };

    return (
        <View style={styles.mainWrapper}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />

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

            {/* Top Tab Bar (Create Test vs Performance Report) */}
            <View style={styles.topTabBarContainer}>
                <View style={styles.topTabBar}>
                    <TouchableOpacity
                        style={[styles.topTabButton, activeTab === 'create' && styles.topTabButtonActive]}
                        onPress={() => setActiveTab('create')}
                        activeOpacity={0.8}
                    >
                        <Ionicons
                            name="create-outline"
                            size={16}
                            color={activeTab === 'create' ? '#0072ff' : '#64748b'}
                            style={{ marginRight: 6 }}
                        />
                        <Text style={[styles.topTabText, activeTab === 'create' && styles.topTabTextActive]}>
                            Create Test
                        </Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={[styles.topTabButton, activeTab === 'performance' && styles.topTabButtonActive]}
                        onPress={() => {
                            setActiveTab('performance');
                            if (!performanceData) loadPerformance();
                        }}
                        activeOpacity={0.8}
                    >
                        <Ionicons
                            name="bar-chart-outline"
                            size={16}
                            color={activeTab === 'performance' ? '#0072ff' : '#64748b'}
                            style={{ marginRight: 6 }}
                        />
                        <Text style={[styles.topTabText, activeTab === 'performance' && styles.topTabTextActive]}>
                            Performance Report
                        </Text>
                    </TouchableOpacity>
                </View>
            </View>

            {/* Content Based on Selected Tab */}
            {activeTab === 'performance' ? (
                renderPerformanceReport()
            ) : (
                <SectionList
                    style={styles.container}
                    contentContainerStyle={styles.scrollContent}
                    sections={selectedSubjects.length > 0 ? chapters : []}
                    keyExtractor={(item) => item.chapter_id.toString()}
                    renderItem={({ item }) => {
                        const isSelected = selectedChapters.includes(item.chapter_id);
                        return (
                            <ChapterItem 
                                chapter={item} 
                                isSelected={isSelected} 
                                onPress={toggleChapter} 
                            />
                        );
                    }}
                    renderSectionHeader={({ section: { subjectName } }) => (
                        <View style={styles.groupContainer}>
                            <Text style={styles.groupHeader}>{subjectName}</Text>
                        </View>
                    )}
                    stickySectionHeadersEnabled={false}
                    ListHeaderComponent={() => (
                        <View>
                            {/* Subject Selection */}
                            <View style={styles.section}>
                                <Text style={styles.sectionTitle}>1. Select Subjects</Text>
                                {loading ? (
                                    <ActivityIndicator size="large" color="#0072ff" style={styles.loader} />
                                ) : (
                                    <View style={styles.subjectGrid}>
                                        {subjects.map((subject, index) => {
                                            const isSelected = selectedSubjects.some(s => s.subject_id === subject.subject_id);
                                            return (
                                                <SubjectCard 
                                                    key={subject.subject_id} 
                                                    subject={subject} 
                                                    index={index} 
                                                    isSelected={isSelected} 
                                                    onPress={toggleSubject} 
                                                />
                                            );
                                        })}
                                    </View>
                                )}
                            </View>

                            {/* Chapter Selection Header */}
                            {selectedSubjects.length > 0 && (
                                <View style={[styles.section, { marginBottom: 0 }]}>
                                    <View style={styles.sectionHeader}>
                                        <Text style={styles.sectionTitle}>2. Select Chapters</Text>
                                        <TouchableOpacity onPress={selectAllChapters} style={styles.selectAllButton}>
                                            <Text style={styles.selectAllText}>
                                                Select All / Deselect All
                                            </Text>
                                        </TouchableOpacity>
                                    </View>
                                    {loadingChapters && (
                                        <ActivityIndicator size="large" color="#0072ff" style={styles.loader} />
                                    )}
                                </View>
                            )}
                        </View>
                    )}
                    ListFooterComponent={() => (
                        <View>
                            {/* Question Limit */}
                            {selectedChapters.length > 0 && (
                                <View style={[styles.section, { marginTop: 20 }]}>
                                    <Text style={styles.sectionTitle}>3. Number of Questions</Text>
                                    <View style={styles.limitContainer}>
                                        {['10', '25', '50', '100'].map((num) => (
                                            <TouchableOpacity
                                                key={num}
                                                style={[styles.limitButton, questionLimit === num && styles.limitButtonSelected]}
                                                onPress={() => setQuestionLimit(num)}
                                            >
                                                <Text style={[
                                                    styles.limitButtonText,
                                                    questionLimit === num && styles.limitButtonTextSelected
                                                ]}>{num}</Text>
                                            </TouchableOpacity>
                                        ))}
                                    </View>
                                    <TextInput
                                        style={styles.customInput}
                                        placeholder="Or enter custom number (1-100)"
                                        placeholderTextColor="#94a3b8"
                                        keyboardType="number-pad"
                                        value={questionLimit}
                                        onChangeText={setQuestionLimit}
                                        maxLength={3}
                                    />
                                </View>
                            )}

                            {/* Start Button */}
                            {selectedChapters.length > 0 && (
                                <TouchableOpacity
                                    style={styles.startButton}
                                    onPress={startTest}
                                    disabled={loading}
                                >
                                    <LinearGradient colors={['#00c6ff', '#0072ff']} style={styles.startButtonGradient}>
                                        {loading ? (
                                            <ActivityIndicator color="white" />
                                        ) : (
                                            <>
                                                <Text style={styles.startButtonText}>Start Test</Text>
                                                <Text style={styles.startButtonSubtext}>
                                                    {selectedChapters.length} chapter{selectedChapters.length > 1 ? 's' : ''} • {questionLimit} questions
                                                </Text>
                                            </>
                                        )}
                                    </LinearGradient>
                                </TouchableOpacity>
                            )}

                            {chapters.length === 0 && !loadingChapters && selectedSubjects.length > 0 && (
                                <Text style={styles.noDataText}>No chapters found for selected subjects.</Text>
                            )}
                        </View>
                    )}
                />
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
    topTabBarContainer: {
        paddingHorizontal: 16,
        paddingVertical: 10,
        backgroundColor: '#ffffff',
        borderBottomWidth: 1,
        borderBottomColor: '#e2e8f0',
    },
    topTabBar: {
        flexDirection: 'row',
        backgroundColor: '#f1f5f9',
        borderRadius: 12,
        padding: 4,
    },
    topTabButton: {
        flex: 1,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 10,
        borderRadius: 10,
    },
    topTabButtonActive: {
        backgroundColor: '#ffffff',
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.08,
        shadowRadius: 4,
    },
    topTabText: {
        fontSize: 14,
        fontWeight: '600',
        color: '#64748b',
    },
    topTabTextActive: {
        color: '#0072ff',
        fontWeight: '700',
    },
    container: {
        flex: 1,
    },
    scrollContent: {
        padding: 20,
        paddingBottom: 40,
    },
    section: {
        marginBottom: 30,
    },
    sectionHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 15,
    },
    sectionTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#0f172a',
        marginBottom: 15,
    },
    selectAllButton: {
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 8,
        backgroundColor: '#e0f2fe',
    },
    selectAllText: {
        fontSize: 13,
        fontWeight: '600',
        color: '#0369a1',
    },
    subjectGrid: {
        flexDirection: 'row',
        flexWrap: 'wrap',
        gap: 12,
    },
    subjectCard: {
        width: '48%',
        backgroundColor: 'white',
        borderRadius: 20,
        overflow: 'hidden',
        elevation: 6,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.2,
        shadowRadius: 10,
    },
    subjectCardSelected: {
        elevation: 12,
        shadowOpacity: 0.4,
        transform: [{ scale: 1.05 }],
    },
    subjectGradient: {
        flex: 1,
        padding: 20,
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: 140,
    },
    subjectIcon: {
        fontSize: 48,
        fontWeight: '900',
        color: 'white',
        marginBottom: 12,
        textShadowColor: 'rgba(0, 0, 0, 0.3)',
        textShadowOffset: { width: 0, height: 2 },
        textShadowRadius: 4,
    },
    subjectName: {
        fontSize: 15,
        fontWeight: '900',
        color: 'white',
        textAlign: 'center',
        textTransform: 'uppercase',
        letterSpacing: 0.5,
        textShadowColor: 'rgba(0, 0, 0, 0.3)',
        textShadowOffset: { width: 0, height: 2 },
        textShadowRadius: 4,
    },
    chapterItem: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'white',
        borderRadius: 12,
        padding: 16,
        borderWidth: 1,
        borderColor: '#e2e8f0',
        marginBottom: 10,
    },
    chapterItemSelected: {
        borderColor: '#0072ff',
        backgroundColor: '#eff6ff',
    },
    checkbox: {
        width: 24,
        height: 24,
        borderRadius: 6,
        borderWidth: 2,
        borderColor: '#cbd5e1',
        marginRight: 12,
        justifyContent: 'center',
        alignItems: 'center',
    },
    checkboxSelected: {
        backgroundColor: '#0072ff',
        borderColor: '#0072ff',
    },
    checkmark: {
        color: 'white',
        fontSize: 16,
        fontWeight: 'bold',
    },
    chapterInfo: {
        flex: 1,
    },
    chapterName: {
        fontSize: 15,
        fontWeight: '600',
        color: '#1e293b',
        marginBottom: 4,
    },
    chapterNameSelected: {
        color: '#0072ff',
    },
    chapterStats: {
        fontSize: 12,
        color: '#64748b',
    },
    limitContainer: {
        flexDirection: 'row',
        gap: 10,
        marginBottom: 15,
    },
    limitButton: {
        flex: 1,
        paddingVertical: 16,
        borderRadius: 12,
        backgroundColor: 'white',
        borderWidth: 2,
        borderColor: '#e2e8f0',
        alignItems: 'center',
    },
    limitButtonSelected: {
        borderColor: '#0072ff',
        backgroundColor: '#eff6ff',
    },
    limitButtonText: {
        fontSize: 18,
        fontWeight: '600',
        color: '#475569',
    },
    limitButtonTextSelected: {
        color: '#0072ff',
        fontWeight: 'bold',
    },
    customInput: {
        backgroundColor: 'white',
        borderRadius: 12,
        padding: 16,
        fontSize: 15,
        borderWidth: 1,
        borderColor: '#e2e8f0',
        color: '#1e293b',
    },
    startButton: {
        borderRadius: 16,
        overflow: 'hidden',
        elevation: 4,
        shadowColor: '#0072ff',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 8,
        marginTop: 10,
    },
    startButtonGradient: {
        padding: 20,
        alignItems: 'center',
    },
    startButtonText: {
        fontSize: 18,
        fontWeight: 'bold',
        color: 'white',
    },
    startButtonSubtext: {
        fontSize: 13,
        color: 'rgba(255,255,255,0.9)',
        marginTop: 4,
    },
    loader: {
        marginVertical: 20,
    },
    selectedBadge: {
        position: 'absolute',
        top: 12,
        right: 12,
        width: 28,
        height: 28,
        borderRadius: 14,
        backgroundColor: 'rgba(255, 255, 255, 0.3)',
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 2,
        borderColor: 'white',
        elevation: 4,
    },
    selectedBadgeText: {
        color: 'white',
        fontWeight: '900',
        fontSize: 15,
    },
    groupContainer: {
        marginBottom: 15,
    },
    groupHeader: {
        fontSize: 16,
        fontWeight: '800',
        color: '#64748b',
        marginBottom: 8,
        marginLeft: 4,
        textTransform: 'uppercase',
        letterSpacing: 1,
    },
    noDataText: {
        textAlign: 'center',
        color: '#94a3b8',
        fontSize: 14,
        marginTop: 20,
    },

    // ─────────────────────────────────────────────────────────────────────────
    // Performance Tab Styles
    // ─────────────────────────────────────────────────────────────────────────
    perfScrollView: {
        flex: 1,
    },
    perfScrollContent: {
        padding: 16,
        paddingBottom: 40,
    },
    perfLoadingContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        paddingVertical: 80,
    },
    perfLoadingText: {
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
        backgroundColor: '#eff6ff',
        borderWidth: 1,
        borderColor: '#bfdbfe',
    },
    subTabText: {
        fontSize: 11,
        fontWeight: '600',
        color: '#64748b',
    },
    subTabTextActive: {
        color: '#0072ff',
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
        backgroundColor: '#e0f2fe',
        paddingHorizontal: 10,
        paddingVertical: 5,
        borderRadius: 8,
        gap: 4,
    },
    strengthenButtonText: {
        fontSize: 11,
        fontWeight: 'bold',
        color: '#0072ff',
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
        backgroundColor: '#eff6ff',
        borderWidth: 1,
        borderColor: '#bfdbfe',
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 8,
    },
    attemptNetScoreText: {
        fontSize: 12,
        fontWeight: 'bold',
        color: '#2563eb',
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
    emptyPerfCard: {
        backgroundColor: 'white',
        borderRadius: 20,
        padding: 30,
        alignItems: 'center',
        borderWidth: 1,
        borderColor: '#e2e8f0',
        marginTop: 20,
    },
    emptyPerfTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#0f172a',
        marginBottom: 8,
    },
    emptyPerfSubtitle: {
        fontSize: 14,
        color: '#64748b',
        textAlign: 'center',
        lineHeight: 20,
        marginBottom: 20,
    },
    emptyPerfButton: {
        borderRadius: 14,
        overflow: 'hidden',
    },
    emptyPerfButtonGrad: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        paddingVertical: 14,
        gap: 6,
    },
    emptyPerfButtonText: {
        color: 'white',
        fontSize: 15,
        fontWeight: 'bold',
    },
});

export default MyExamScreen;
