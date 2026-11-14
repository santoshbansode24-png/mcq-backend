import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    FlatList,
    ActivityIndicator,
    StatusBar,
    SafeAreaView,
    Platform,
    ScrollView,
    RefreshControl,
    Modal,
    Share,
    Alert
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';
import { API_URL } from '../api/config';
import LanguageToggle from '../components/LanguageToggle';
import { getLanguagePreference, saveLanguagePreference } from '../utils/languageStorage';
import { fetchSubjects } from '../api/subjects';
import { fetchChapters } from '../api/chapters';

// ─────────────────────────────────────────────────────────────────────────────
// COMPETITIVE EXAMS MASTER (Minimal Grid)
// ─────────────────────────────────────────────────────────────────────────────
const COMPETITIVE_CATEGORIES = [
    { id: 'all', title: 'All Exams', icon: 'apps', gradient: ['#6366F1', '#4338CA'], tint: '#EEF2FF' },
    { id: 'scholarship', title: 'State Scholarship', icon: 'ribbon', gradient: ['#4F46E5', '#3730A3'], tint: '#EEF2FF' },
    { id: 'navodaya', title: 'Navodaya', icon: 'school', gradient: ['#0284C7', '#0369A1'], tint: '#E0F2FE' },
    { id: 'sainik', title: 'Sainik School', icon: 'shield-checkmark', gradient: ['#15803D', '#166534'], tint: '#DCFCE7' },
    { id: 'olympiad', title: 'Olympiad', icon: 'trophy', gradient: ['#D97706', '#B45309'], tint: '#FEF3C7' },
    { id: 'mts', title: 'MTS / NMMS', icon: 'bulb', gradient: ['#7C3AED', '#6D28D9'], tint: '#F3E8FF' }
];

const SUBJECT_GRADIENTS = [
    ['#6366F1', '#4F46E5'],
    ['#EC4899', '#DB2777'],
    ['#0EA5E9', '#0284C7'],
    ['#F59E0B', '#D97706'],
    ['#10B981', '#059669']
];

const escapeHtml = (s) =>
    String(s || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

const formatCountdownSeconds = (rem) => {
    const safeRem = Math.max(0, rem || 0);
    const hrs = Math.floor(safeRem / 3600);
    const mins = Math.floor((safeRem % 3600) / 60);
    const secs = safeRem % 60;
    return `${hrs}h ${String(mins).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s`;
};

// Isolated memoized card so 1s countdown ticks only re-render the affected upcoming exam card
const LiveExamCard = React.memo(({ exam, onStart, onShare }) => {
    const initialSeconds = exam.status === 'upcoming' ? Math.max(0, exam.seconds_to_start || 0) : 0;
    const [remainingSecs, setRemainingSecs] = useState(initialSeconds);

    useEffect(() => {
        setRemainingSecs(exam.status === 'upcoming' ? Math.max(0, exam.seconds_to_start || 0) : 0);
    }, [exam.exam_id, exam.status, exam.seconds_to_start]);

    useEffect(() => {
        if (exam.status !== 'upcoming' || remainingSecs <= 0) return;
        const timer = setInterval(() => {
            setRemainingSecs((prev) => (prev <= 1 ? 0 : prev - 1));
        }, 1000);
        return () => clearInterval(timer);
    }, [exam.status, remainingSecs > 0]);

    const isWaitingToStart = exam.status === 'upcoming' && remainingSecs > 0;

    return (
        <View style={[styles.examCard, { borderLeftColor: exam.category_color || '#6366F1' }]}>
            <View style={{ flex: 1 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 4 }}>
                    <View style={[styles.statusDot, { backgroundColor: exam.status === 'live_now' || !isWaitingToStart ? '#10b981' : '#3b82f6' }]} />
                    <Text style={styles.examStatusText}>
                        {exam.status === 'live_now' || !isWaitingToStart ? 'LIVE' : 'UPCOMING'}
                    </Text>
                </View>
                <Text style={styles.examCardTitle}>{exam.title}</Text>
                <Text style={styles.examCardMeta}>
                    {exam.duration_minutes}m • {exam.total_questions} Qs {exam.total_marks ? `• ${exam.total_marks} Marks` : ''}
                </Text>
                {isWaitingToStart && (
                    <Text style={[styles.examCardMeta, { color: '#0284C7', fontWeight: 'bold', marginTop: 4 }]}>
                        ⏳ {formatCountdownSeconds(remainingSecs)}
                    </Text>
                )}
            </View>
            <View style={{ alignItems: 'flex-end', gap: 8 }}>
                {isWaitingToStart ? (
                    <View style={[styles.btnActionSmall, { backgroundColor: '#94a3b8' }]}>
                        <Text style={styles.btnActionText}>Soon</Text>
                    </View>
                ) : (
                    <TouchableOpacity
                        style={[styles.btnActionSmall, { backgroundColor: exam.category_color || '#6366F1' }]}
                        onPress={() => onStart(exam.exam_id)}
                    >
                        <Text style={styles.btnActionText}>Start</Text>
                    </TouchableOpacity>
                )}
                <TouchableOpacity
                    style={[styles.btnActionSmall, { backgroundColor: '#25D366' }]}
                    onPress={() => onShare(exam.share_text)}
                >
                    <Ionicons name="logo-whatsapp" size={14} color="white" />
                </TouchableOpacity>
            </View>
        </View>
    );
});

// Clean reusable Grid Tile
const GridTile = ({ title, icon, gradient, badge, onPress }) => (
    <TouchableOpacity style={styles.gridTile} activeOpacity={0.85} onPress={onPress}>
        <View style={styles.gridTileTop}>
            <LinearGradient colors={gradient} style={styles.gridIconBox}>
                <Ionicons name={icon} size={24} color="white" />
            </LinearGradient>
            {badge ? (
                <View style={styles.gridBadge}>
                    <Text style={styles.gridBadgeText}>{badge}</Text>
                </View>
            ) : (
                <Ionicons name="chevron-forward" size={18} color="#cbd5e1" />
            )}
        </View>
        <Text style={styles.gridTileTitle} numberOfLines={2}>{title}</Text>
    </TouchableOpacity>
);

const ScholarshipSubjectsScreen = ({ navigation, route }) => {
    const scholarshipClassId = route.params?.scholarshipClassId || 38;
    const baseTitle = route.params?.levelTitle || 'Scholarship';

    // Page-by-page drill-down navigation stack
    // Pages: 'home' | 'live_menu' | 'live_ongoing' | 'live_history' | 'exam_list' | 'exam_menu' | 'subjects' | 'performance_menu' | 'perf_weak' | 'perf_negative' | 'perf_syllabus' | 'practice_menu' | 'practice_generator' | 'practice_exam' | 'practice_pdfs'
    const [pageStack, setPageStack] = useState(['home']);
    const currentPage = pageStack[pageStack.length - 1];

    const [selectedExamCategory, setSelectedExamCategory] = useState(null);
    const [selectedLanguage, setSelectedLanguage] = useState('marathi');
    const [categoryCounts, setCategoryCounts] = useState({});
    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const [userId, setUserId] = useState(0);

    // Live Exam States
    const [liveExamsData, setLiveExamsData] = useState({
        spotlight_exam: null,
        live_and_upcoming: [],
        past_exams: []
    });
    const [leaderboardModalVisible, setLeaderboardModalVisible] = useState(false);
    const [selectedExamLeaderboard, setSelectedExamLeaderboard] = useState(null);
    const [leaderboardLoading, setLeaderboardLoading] = useState(false);

    // Subjects State
    const [subjects, setSubjects] = useState([]);

    // Performance States
    const [performanceData, setPerformanceData] = useState(null);
    const [expandedSubjectId, setExpandedSubjectId] = useState(null);
    const [resolvingId, setResolvingId] = useState(null);
    const [negativeBasketList, setNegativeBasketList] = useState([]);
    const [negativeCount, setNegativeCount] = useState(0);

    // Practice Zone States
    const [practiceChapters, setPracticeChapters] = useState([]);
    const [selectedPracticeSubjects, setSelectedPracticeSubjects] = useState([]);
    const [selectedPracticeChapters, setSelectedPracticeChapters] = useState([]);
    const [practiceLimit, setPracticeLimit] = useState('25');
    const [loadingPracticeChapters, setLoadingPracticeChapters] = useState(false);

    const chaptersCacheRef = useRef({});

    const pushPage = useCallback((page) => {
        setPageStack((prev) => [...prev, page]);
    }, []);

    const popPage = useCallback(() => {
        if (pageStack.length > 1) {
            setPageStack((prev) => prev.slice(0, -1));
            return true;
        }
        return false;
    }, [pageStack.length]);

    // Hardware back button pops internal drill-down stack first
    useEffect(() => {
        if (navigation?.registerBackInterceptor) {
            navigation.registerBackInterceptor(() => popPage());
        }
        return () => {
            if (navigation?.unregisterBackInterceptor) {
                navigation.unregisterBackInterceptor();
            }
        };
    }, [navigation, popPage]);

    useEffect(() => {
        initScreen();
    }, []);

    const initScreen = async () => {
        try {
            const userDataStr = await AsyncStorage.getItem('user_data');
            const userData = userDataStr ? JSON.parse(userDataStr) : null;
            const uid = userData?.user_id || userData?.id || 0;
            setUserId(uid);

            const savedLang = await getLanguagePreference();
            if (savedLang) setSelectedLanguage(savedLang);

            await loadAllData(uid);
        } catch (e) {
            console.error('Init error:', e);
        } finally {
            setLoading(false);
        }
    };

    const loadAllData = async (uid = userId, force = false) => {
        if (force) setRefreshing(true);
        try {
            await Promise.all([
                loadLiveExams(uid),
                loadSubjectsData(),
                loadPerformance(uid)
            ]);
        } catch (e) {
            console.error('Error loading data:', e);
        } finally {
            if (force) setRefreshing(false);
        }
    };

    const handleLanguageChange = async (language) => {
        setSelectedLanguage(language);
        await saveLanguagePreference(language);
    };

    // ─────────────────────────────────────────────────────────────────────────
    // API HANDLERS
    // ─────────────────────────────────────────────────────────────────────────
    const loadLiveExams = async (uid = userId) => {
        try {
            const res = await axios.get(`${API_URL}/get_scholarship_live_exams.php?user_id=${uid}&class_id=${scholarshipClassId}`);
            if (res.data?.status === 'success') {
                setLiveExamsData(res.data.data);
                if (res.data.data?.category_counts) {
                    setCategoryCounts(res.data.data.category_counts);
                }
            }
        } catch (err) {
            console.log('Error loading live exams:', err);
        }
    };

    const startLiveExam = useCallback(async (examId) => {
        try {
            setActionLoading(true);
            const res = await axios.get(`${API_URL}/get_scholarship_exam_questions.php?exam_id=${examId}`);
            if (res.data?.status === 'success' && res.data.data?.questions?.length > 0) {
                const exam = res.data.data.exam;
                const questions = res.data.data.questions;
                navigation.navigate('MyExamTest', {
                    questions: questions,
                    totalQuestions: questions.length,
                    subjectName: exam.title,
                    examId: exam.exam_id,
                    durationMinutes: exam.duration_minutes,
                    positiveMarks: exam.positive_marks,
                    negativeMarks: exam.negative_marks
                });
            } else {
                Alert.alert('Notice', 'या परीक्षेसाठी अद्याप प्रश्न उपलब्ध नाहीत.');
            }
        } catch (err) {
            Alert.alert('Error', 'परीक्षेचे प्रश्न लोड करताना त्रुटी आली.');
        } finally {
            setActionLoading(false);
        }
    }, [navigation]);

    const shareExamOnWhatsApp = useCallback(async (shareText) => {
        try {
            await Share.share({ message: shareText, title: 'Live Exam' });
        } catch (e) {
            console.log('Share error:', e);
        }
    }, []);

    const openLeaderboard = async (examId) => {
        try {
            setLeaderboardLoading(true);
            setLeaderboardModalVisible(true);
            const res = await axios.get(`${API_URL}/get_scholarship_leaderboard.php?exam_id=${examId}&user_id=${userId}`);
            if (res.data?.status === 'success') {
                setSelectedExamLeaderboard(res.data.data);
            }
        } catch (e) {
            Alert.alert('Error', 'लीडरबोर्ड लोड करताना त्रुटी आली.');
        } finally {
            setLeaderboardLoading(false);
        }
    };

    const downloadPastExamPDF = async (exam) => {
        try {
            setActionLoading(true);
            const res = await axios.get(`${API_URL}/get_scholarship_exam_questions.php?exam_id=${exam.exam_id}`);
            const questions = res.data?.data?.questions || [];
            if (!questions.length) {
                Alert.alert('Notice', 'या परीक्षेचे प्रश्न उपलब्ध नाहीत.');
                return;
            }
            const qHtml = questions.map((q, idx) => `
                <div style="margin-bottom:14px;padding:10px;border:1px solid #e2e8f0;border-radius:8px;page-break-inside:avoid;">
                    <div style="font-weight:bold;margin-bottom:6px;">Q${idx + 1}. ${escapeHtml(q.question)}</div>
                    <div style="font-size:13px;line-height:1.6;">
                        <div>(A) ${escapeHtml(q.option_a)}</div>
                        <div>(B) ${escapeHtml(q.option_b)}</div>
                        <div>(C) ${escapeHtml(q.option_c)}</div>
                        <div>(D) ${escapeHtml(q.option_d)}</div>
                    </div>
                    <div style="margin-top:6px;font-size:12px;color:#15803d;"><strong>Correct Answer:</strong> ${(q.correct_answer || '').toUpperCase()}</div>
                    ${q.explanation ? `<div style="margin-top:4px;font-size:12px;color:#475569;"><em>Explanation: ${escapeHtml(q.explanation)}</em></div>` : ''}
                </div>
            `).join('');
            const html = `
                <html><head><meta charset="utf-8" /></head>
                <body style="font-family:sans-serif;padding:24px;color:#1e293b;">
                    <h2>${escapeHtml(exam.title)}</h2>
                    <p style="color:#475569;font-size:13px;margin-bottom:16px;">
                        ${exam.has_attempted ? `Rank: #${exam.user_rank || '-'} | Score: ${exam.user_score}` : `Total Questions: ${questions.length}`}
                    </p>
                    <hr style="border:none;border-top:1px solid #cbd5e1;margin-bottom:16px;" />
                    ${qHtml}
                </body></html>
            `;
            const { uri } = await Print.printToFileAsync({ html });
            if (await Sharing.isAvailableAsync()) {
                await Sharing.shareAsync(uri, { mimeType: 'application/pdf', dialogTitle: 'Download Exam Paper PDF' });
            } else {
                Alert.alert('PDF Ready', `Saved to: ${uri}`);
            }
        } catch (e) {
            Alert.alert('Error', 'PDF तयार करताना त्रुटी आली.');
        } finally {
            setActionLoading(false);
        }
    };

    const loadSubjectsData = async () => {
        try {
            const response = await fetchSubjects(scholarshipClassId);
            if (response && (response.status === 'success' || Array.isArray(response))) {
                const allData = response.data || response;
                const regular = allData.filter(s => !s.subject_name.includes('Mock Test') && !s.subject_name.includes('Previous Paper'));
                setSubjects(regular);
            }
        } catch (err) {
            console.log('Error loading subjects:', err);
        }
    };

    const loadPerformance = async (uid = userId) => {
        if (!uid) return;
        try {
            const res = await axios.get(`${API_URL}/get_scholarship_performance.php?user_id=${uid}&class_id=${scholarshipClassId}`);
            if (res.data?.status === 'success') {
                setPerformanceData(res.data.data);
                setNegativeBasketList(res.data.data.negative_basket?.questions || []);
                setNegativeCount(res.data.data.negative_basket?.total_count || 0);
            }
        } catch (err) {
            console.log('Error loading performance:', err);
        }
    };

    const toggleSubjectAccordion = (subId) => {
        setExpandedSubjectId(prev => prev === subId ? null : subId);
    };

    const handleResolveNegativeQuestion = async (item, selectedOption) => {
        if (resolvingId) return;
        const qId = item.question_id || item.mcq_id;
        setResolvingId(qId);

        try {
            const res = await axios.post(`${API_URL}/resolve_negative_question.php`, {
                user_id: userId,
                mcq_id: qId,
                selected_option: selectedOption,
                basket_id: item.basket_id || 0
            });

            if (res.data?.status === 'success' && res.data.data?.is_correct) {
                Alert.alert('🎉 बरोबर!', 'हा प्रश्न निगेटिव्ह बास्केटमधून क्लिअर झाला आहे.');
                setNegativeBasketList(prev => prev.filter(q => (q.question_id || q.mcq_id) !== qId));
                setNegativeCount(prev => Math.max(0, prev - 1));
            } else {
                Alert.alert('❌ चूक', res.data?.data?.explanation || 'पुन्हा प्रयत्न करा.');
            }
        } catch (e) {
            Alert.alert('Error', 'प्रश्न सोडवताना त्रुटी आली.');
        } finally {
            setResolvingId(null);
        }
    };

    const practiceAllMistakes = () => {
        if (!negativeBasketList || negativeBasketList.length === 0) {
            Alert.alert('अभिनंदन!', 'कोणतेही चुकलेले प्रश्न नाहीत!');
            return;
        }
        const quizQuestions = negativeBasketList.map(q => ({
            mcq_id: q.question_id || q.mcq_id,
            question: q.question,
            option_a: q.option_a,
            option_b: q.option_b,
            option_c: q.option_c,
            option_d: q.option_d,
            correct_answer: q.correct_answer,
            explanation: q.explanation,
            chapter_name: q.chapter_name
        }));
        navigation.navigate('MyExamTest', {
            questions: quizQuestions,
            totalQuestions: quizQuestions.length,
            subjectName: 'Negative Bank Practice'
        });
    };

    const togglePracticeSubject = (subject) => {
        setSelectedPracticeSubjects(prev => {
            const exists = prev.find(s => s.subject_id === subject.subject_id);
            if (exists) {
                const cached = chaptersCacheRef.current[subject.subject_id] || [];
                if (cached.length > 0) {
                    const removedIds = new Set(cached.map(c => c.chapter_id));
                    setSelectedPracticeChapters(chPrev => chPrev.filter(id => !removedIds.has(id)));
                }
                return prev.filter(s => s.subject_id !== subject.subject_id);
            } else {
                return [...prev, subject];
            }
        });
    };

    useEffect(() => {
        if (selectedPracticeSubjects.length > 0) {
            loadPracticeChapters();
        } else {
            setPracticeChapters([]);
            setSelectedPracticeChapters([]);
        }
    }, [selectedPracticeSubjects]);

    const loadPracticeChapters = async () => {
        const missingSubjects = selectedPracticeSubjects.filter(
            sub => !chaptersCacheRef.current[sub.subject_id]
        );
        if (missingSubjects.length > 0) {
            setLoadingPracticeChapters(true);
            try {
                await Promise.all(
                    missingSubjects.map(sub =>
                        fetchChapters(sub.subject_id)
                            .then(res => {
                                chaptersCacheRef.current[sub.subject_id] =
                                    res.status === 'success' && Array.isArray(res.data) ? res.data : [];
                            })
                            .catch(() => {
                                chaptersCacheRef.current[sub.subject_id] = [];
                            })
                    )
                );
            } catch (e) {
                console.error('Load chapters error:', e);
            } finally {
                setLoadingPracticeChapters(false);
            }
        }
        const assembled = selectedPracticeSubjects
            .map(sub => ({
                subjectName: sub.subject_name,
                subjectId: sub.subject_id,
                data: chaptersCacheRef.current[sub.subject_id] || []
            }))
            .filter(r => r.data.length > 0);
        setPracticeChapters(assembled);
    };

    const togglePracticeChapter = (chId) => {
        setSelectedPracticeChapters(prev =>
            prev.includes(chId) ? prev.filter(id => id !== chId) : [...prev, chId]
        );
    };

    const selectAllPracticeChapters = () => {
        const allIds = practiceChapters.flatMap(g => g.data.map(c => c.chapter_id));
        if (selectedPracticeChapters.length === allIds.length) {
            setSelectedPracticeChapters([]);
        } else {
            setSelectedPracticeChapters(allIds);
        }
    };

    const startCustomOnlineTest = async () => {
        if (selectedPracticeChapters.length === 0) {
            Alert.alert('Notice', 'कृपया किमान एक धडा निवडा.');
            return;
        }
        try {
            setActionLoading(true);
            const res = await axios.post(`${API_URL}/generate_custom_test.php`, {
                chapter_ids: selectedPracticeChapters.join(','),
                limit: parseInt(practiceLimit) || 25
            });
            if (res.data?.status === 'success') {
                navigation.navigate('MyExamTest', {
                    questions: res.data.data,
                    totalQuestions: res.data.data.length,
                    subjectName: selectedPracticeSubjects.map(s => s.subject_name).join(' & ')
                });
            } else {
                Alert.alert('Error', res.data?.message || 'प्रश्न लोड झाले नाहीत.');
            }
        } catch (e) {
            Alert.alert('Error', 'टेस्ट सुरू करताना त्रुटी आली.');
        } finally {
            setActionLoading(false);
        }
    };

    const generateExamPaperPDF = async () => {
        if (selectedPracticeChapters.length === 0) {
            Alert.alert('Notice', 'कृपया किमान एक धडा निवडा.');
            return;
        }
        try {
            setActionLoading(true);
            const res = await axios.post(`${API_URL}/generate_custom_test.php`, {
                chapter_ids: selectedPracticeChapters.join(','),
                limit: parseInt(practiceLimit) || 25
            });
            if (res.data?.status === 'success') {
                const questions = res.data.data;
                const dateStr = new Date().toLocaleDateString('mr-IN');
                const subTitle = escapeHtml(selectedPracticeSubjects.map(s => s.subject_name).join(' व '));
                const html = `
                    <html><head><meta charset="utf-8"><style>
                        body { font-family: 'Helvetica', sans-serif; padding: 30px; color: #222; }
                        .header { text-align: center; border-bottom: 2px solid #4F46E5; padding-bottom: 15px; margin-bottom: 25px; }
                        .app-name { font-size: 22px; font-weight: bold; color: #4A00E0; }
                        .exam-title { font-size: 18px; font-weight: bold; margin-top: 5px; }
                        .meta { display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 8px; }
                        .q-card { margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #eee; page-break-inside: avoid; }
                        .q-text { font-size: 15px; font-weight: bold; margin-bottom: 8px; }
                        .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 14px; }
                        .opt { background: #f8fafc; padding: 6px 10px; border-radius: 4px; border: 1px solid #e2e8f0; }
                        .page-break { page-break-before: always; }
                        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
                        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
                        th { background-color: #f1f5f9; }
                    </style></head><body>
                        <div class="header">
                            <div class="app-name">Veeru Learning App</div>
                            <div class="exam-title">${subTitle}</div>
                            <div class="meta"><span>Date: ${dateStr}</span><span>Questions: ${questions.length}</span></div>
                        </div>
                        <div>${questions.map((q, idx) => `
                            <div class="q-card">
                                <div class="q-text">Q${idx + 1}. ${escapeHtml(q.question)}</div>
                                <div class="options-grid">
                                    <div class="opt">(A) ${escapeHtml(q.option_a)}</div>
                                    <div class="opt">(B) ${escapeHtml(q.option_b)}</div>
                                    <div class="opt">(C) ${escapeHtml(q.option_c)}</div>
                                    <div class="opt">(D) ${escapeHtml(q.option_d)}</div>
                                </div>
                            </div>`).join('')}
                        </div>
                        <div class="page-break"></div>
                        <h3>Answer Key</h3>
                        <table><thead><tr><th>#</th><th>Ans</th><th>Explanation</th></tr></thead><tbody>
                            ${questions.map((q, idx) => `
                                <tr><td>${idx + 1}</td><td><strong>${escapeHtml((q.correct_answer || '').toUpperCase())}</strong></td><td>${escapeHtml(q.explanation || '-')}</td></tr>
                            `).join('')}
                        </tbody></table>
                    </body></html>
                `;
                const { uri } = await Print.printToFileAsync({ html });
                await Sharing.shareAsync(uri);
            } else {
                Alert.alert('Error', res.data?.message || 'पेपर जनरेट होऊ शकला नाही.');
            }
        } catch (e) {
            Alert.alert('Error', 'PDF तयार करताना त्रुटी आली.');
        } finally {
            setActionLoading(false);
        }
    };

    // ─────────────────────────────────────────────────────────────────────────
    // HEADER TITLE HELPER
    // ─────────────────────────────────────────────────────────────────────────
    const getHeaderTitle = () => {
        switch (currentPage) {
            case 'home': return baseTitle;
            case 'live_menu': return 'Live Exam';
            case 'live_ongoing': return 'Live Now';
            case 'live_history': return 'Exam History';
            case 'exam_list': return 'Competitive Exams';
            case 'exam_menu': return selectedExamCategory?.title || 'Competitive Exams';
            case 'subjects': return 'Subjects';
            case 'performance_menu': return 'Performance';
            case 'perf_weak': return 'Weak Chapters';
            case 'perf_negative': return 'Negative Bank';
            case 'perf_syllabus': return 'Syllabus Tracker';
            case 'practice_menu': return 'Practice Zone';
            case 'practice_generator': return 'Paper Generator';
            case 'practice_exam': return 'Practice Exam';
            case 'practice_pdfs': return 'Previous PDFs';
            default: return baseTitle;
        }
    };

    // ─────────────────────────────────────────────────────────────────────────
    // PAGE RENDERERS (ALL GRID-BASED, MINIMAL TEXT, SEPARATE PAGES)
    // ─────────────────────────────────────────────────────────────────────────

    // Page 1: Home (2-Card Grid: Live Exam | Competitive Exams)
    const renderHomePage = () => {
        const liveCount = liveExamsData?.live_and_upcoming?.length || 0;
        return (
            <ScrollView
                style={styles.pageScroll}
                contentContainerStyle={styles.gridContainer}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
            >
                <View style={styles.gridWrap}>
                    <GridTile
                        title="Live Exam"
                        icon="radio"
                        gradient={['#F43F5E', '#E11D48']}
                        badge={liveCount > 0 ? `${liveCount} Live` : null}
                        onPress={() => pushPage('live_menu')}
                    />
                    <GridTile
                        title="Competitive Exams"
                        icon="school"
                        gradient={['#6366F1', '#4F46E5']}
                        badge="6 Exams"
                        onPress={() => pushPage('exam_list')}
                    />
                </View>
            </ScrollView>
        );
    };

    // Page 2A: Live Exam Menu (2-Card Grid: Live Now | History)
    const renderLiveMenuPage = () => {
        const liveCount = liveExamsData?.live_and_upcoming?.length || 0;
        const pastCount = liveExamsData?.past_exams?.length || 0;
        return (
            <ScrollView
                style={styles.pageScroll}
                contentContainerStyle={styles.gridContainer}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
            >
                <View style={styles.gridWrap}>
                    <GridTile
                        title="Live Now"
                        icon="flash"
                        gradient={['#FF416C', '#FF4B2B']}
                        badge={liveCount > 0 ? `${liveCount}` : null}
                        onPress={() => pushPage('live_ongoing')}
                    />
                    <GridTile
                        title="History & Rank"
                        icon="trophy"
                        gradient={['#8E2DE2', '#4A00E0']}
                        badge={pastCount > 0 ? `${pastCount}` : null}
                        onPress={() => pushPage('live_history')}
                    />
                </View>
            </ScrollView>
        );
    };

    // Page 3A: Live Ongoing Exams (Banner + Click to Start)
    const renderLiveOngoingPage = () => {
        const { spotlight_exam, live_and_upcoming = [] } = liveExamsData || {};
        return (
            <ScrollView
                style={styles.pageScroll}
                contentContainerStyle={styles.listPadding}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
            >
                {spotlight_exam ? (
                    <TouchableOpacity
                        activeOpacity={0.92}
                        onPress={() => !spotlight_exam.has_attempted && startLiveExam(spotlight_exam.exam_id)}
                    >
                        <LinearGradient colors={['#FF416C', '#FF4B2B']} style={styles.spotlightCard}>
                            <View style={styles.spotlightBadge}>
                                <Text style={styles.spotlightBadgeText}>
                                    {spotlight_exam.status === 'live_now' ? '🔴 LIVE NOW' : '⏳ UPCOMING'}
                                </Text>
                            </View>
                            <Text style={styles.spotlightTitle}>{spotlight_exam.title}</Text>
                            <Text style={styles.spotlightSubtitle}>
                                {spotlight_exam.duration_minutes}m • {spotlight_exam.total_questions} Qs • +{spotlight_exam.positive_marks} Marks
                            </Text>
                            <View style={styles.spotlightActions}>
                                {spotlight_exam.has_attempted ? (
                                    <View style={styles.attemptedBadge}>
                                        <Text style={styles.attemptedText}>
                                            ✔ Score: {spotlight_exam.user_score} | Rank: #{spotlight_exam.user_rank || '-'}
                                        </Text>
                                    </View>
                                ) : (
                                    <TouchableOpacity
                                        style={styles.btnStartSpotlight}
                                        onPress={() => startLiveExam(spotlight_exam.exam_id)}
                                    >
                                        <Text style={styles.btnStartSpotlightText}>🚀 Start Live Exam</Text>
                                    </TouchableOpacity>
                                )}
                                <TouchableOpacity
                                    style={styles.btnShareSpotlight}
                                    onPress={() => shareExamOnWhatsApp(spotlight_exam.share_text)}
                                >
                                    <Ionicons name="logo-whatsapp" size={18} color="white" />
                                </TouchableOpacity>
                            </View>
                        </LinearGradient>
                    </TouchableOpacity>
                ) : (
                    <View style={styles.emptyCard}>
                        <Ionicons name="radio-outline" size={34} color="#cbd5e1" style={{ marginBottom: 8 }} />
                        <Text style={styles.emptyText}>No live exam right now.</Text>
                    </View>
                )}

                {live_and_upcoming.map(exam => (
                    <LiveExamCard
                        key={exam.exam_id}
                        exam={exam}
                        onStart={startLiveExam}
                        onShare={shareExamOnWhatsApp}
                    />
                ))}
            </ScrollView>
        );
    };

    // Page 3B: History of Live Exams (Rank + Download Paper PDF)
    const renderLiveHistoryPage = () => {
        const past_exams = liveExamsData?.past_exams || [];
        return (
            <ScrollView
                style={styles.pageScroll}
                contentContainerStyle={styles.listPadding}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
            >
                {past_exams.length === 0 ? (
                    <View style={styles.emptyCard}>
                        <Ionicons name="time-outline" size={34} color="#cbd5e1" style={{ marginBottom: 8 }} />
                        <Text style={styles.emptyText}>No past exams yet.</Text>
                    </View>
                ) : (
                    past_exams.map(exam => (
                        <View key={exam.exam_id} style={styles.pastExamCard}>
                            <View style={{ flex: 1 }}>
                                <Text style={styles.pastExamTitle}>{exam.title}</Text>
                                <Text style={styles.examCardMeta}>
                                    {exam.total_questions} Qs • {exam.duration_minutes}m
                                </Text>
                                {exam.has_attempted ? (
                                    <View style={styles.rankPill}>
                                        <Text style={styles.rankPillText}>
                                            🏅 Rank: #{exam.user_rank || '-'} • Score: {exam.user_score}
                                        </Text>
                                    </View>
                                ) : (
                                    <Text style={styles.notAttemptedText}>Not attempted</Text>
                                )}
                                <View style={{ flexDirection: 'row', gap: 8, marginTop: 10 }}>
                                    <TouchableOpacity
                                        style={[styles.btnHistoryAction, { backgroundColor: '#dcfce7' }]}
                                        onPress={() => downloadPastExamPDF(exam)}
                                    >
                                        <Ionicons name="download-outline" size={15} color="#15803d" />
                                        <Text style={[styles.btnHistoryActionText, { color: '#15803d' }]}>Download Paper</Text>
                                    </TouchableOpacity>
                                    <TouchableOpacity
                                        style={[styles.btnHistoryAction, { backgroundColor: '#f3e8ff' }]}
                                        onPress={() => openLeaderboard(exam.exam_id)}
                                    >
                                        <Ionicons name="trophy-outline" size={15} color="#4A00E0" />
                                        <Text style={[styles.btnHistoryActionText, { color: '#4A00E0' }]}>Rank List</Text>
                                    </TouchableOpacity>
                                </View>
                            </View>
                        </View>
                    ))
                )}
            </ScrollView>
        );
    };

    // Page 2B: Competitive Exams Grid (Minimal: Icon + Short Name)
    const renderExamListPage = () => (
        <ScrollView
            style={styles.pageScroll}
            contentContainerStyle={styles.gridContainer}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
        >
            <View style={styles.gridWrap}>
                {COMPETITIVE_CATEGORIES.map((cat) => {
                    const count = categoryCounts[cat.id] ?? 0;
                    return (
                        <GridTile
                            key={cat.id}
                            title={cat.title}
                            icon={cat.icon}
                            gradient={cat.gradient}
                            badge={count > 0 ? `${count}` : null}
                            onPress={() => {
                                setSelectedExamCategory(cat);
                                pushPage('exam_menu');
                            }}
                        />
                    );
                })}
            </View>
        </ScrollView>
    );

    // Page 3C: Selected Exam Menu (3-Card Grid: Subjects | Performance | Practice Zone)
    const renderExamMenuPage = () => (
        <ScrollView style={styles.pageScroll} contentContainerStyle={styles.gridContainer}>
            <View style={styles.gridWrap}>
                <GridTile
                    title="Subjects"
                    icon="book"
                    gradient={['#4F46E5', '#6366F1']}
                    badge={subjects.length > 0 ? `${subjects.length}` : null}
                    onPress={() => pushPage('subjects')}
                />
                <GridTile
                    title="Performance"
                    icon="bar-chart"
                    gradient={['#8E2DE2', '#4A00E0']}
                    badge={performanceData?.readiness_percentage ? `${performanceData.readiness_percentage}%` : null}
                    onPress={() => pushPage('performance_menu')}
                />
                <GridTile
                    title="Practice Zone"
                    icon="layers"
                    gradient={['#0D9488', '#14B8A6']}
                    onPress={() => pushPage('practice_menu')}
                />
            </View>
        </ScrollView>
    );

    // Page 4A: Subjects Grid (English, Maths, etc.)
    const renderSubjectsPage = () => (
        <FlatList
            data={subjects}
            keyExtractor={item => item.subject_id.toString()}
            numColumns={2}
            columnWrapperStyle={styles.subjectRow}
            contentContainerStyle={styles.gridContainer}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
            renderItem={({ item, index }) => (
                <GridTile
                    title={item.subject_name}
                    icon="library"
                    gradient={SUBJECT_GRADIENTS[index % SUBJECT_GRADIENTS.length]}
                    onPress={() => navigation.navigate('ScholarshipChapters', {
                        subjectId: item.subject_id,
                        subjectName: item.subject_name,
                        selectedLanguage: selectedLanguage
                    })}
                />
            )}
            ListEmptyComponent={
                <View style={styles.emptyCard}>
                    <Text style={styles.emptyText}>No subjects available.</Text>
                </View>
            }
        />
    );

    // Page 4B: Performance Menu (3-Card Grid: Weak Chapter | Negative Bank | Syllabus Tracker)
    const renderPerformanceMenuPage = () => {
        const weakCount = performanceData?.weak_chapters?.length || 0;
        const compPct = performanceData?.syllabus_summary?.completion_percentage || 0;
        return (
            <ScrollView
                style={styles.pageScroll}
                contentContainerStyle={styles.gridContainer}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
            >
                <View style={styles.gridWrap}>
                    <GridTile
                        title="Weak Chapters"
                        icon="alert-circle"
                        gradient={['#F43F5E', '#E11D48']}
                        badge={`${weakCount}`}
                        onPress={() => pushPage('perf_weak')}
                    />
                    <GridTile
                        title="Negative Bank"
                        icon="close-circle"
                        gradient={['#F59E0B', '#D97706']}
                        badge={`${negativeCount}`}
                        onPress={() => pushPage('perf_negative')}
                    />
                    <GridTile
                        title="Syllabus Tracker"
                        icon="checkmark-done-circle"
                        gradient={['#10B981', '#059669']}
                        badge={`${compPct}%`}
                        onPress={() => pushPage('perf_syllabus')}
                    />
                </View>
            </ScrollView>
        );
    };

    // Page 5A: Weak Chapters
    const renderPerfWeakPage = () => {
        const weakList = performanceData?.weak_chapters || [];
        return (
            <ScrollView style={styles.pageScroll} contentContainerStyle={styles.listPadding}>
                {weakList.length === 0 ? (
                    <View style={styles.emptyCard}>
                        <Text style={styles.emptyText}>No weak chapters! Great job.</Text>
                    </View>
                ) : (
                    weakList.map(w => (
                        <View key={w.chapter_id} style={styles.weakCard}>
                            <View style={{ flex: 1 }}>
                                <Text style={styles.weakTitle}>{w.chapter_name}</Text>
                                <Text style={styles.weakMeta}>
                                    {w.subject_name} • <Text style={{ color: '#dc2626', fontWeight: 'bold' }}>{w.accuracy_percentage}%</Text>
                                </Text>
                            </View>
                            <TouchableOpacity
                                style={styles.btnWeakPractice}
                                onPress={() => navigation.navigate('ScholarshipChapters', {
                                    subjectId: w.subject_id || 0,
                                    subjectName: w.subject_name,
                                    selectedLanguage: selectedLanguage
                                })}
                            >
                                <Text style={styles.btnWeakPracticeText}>Practice</Text>
                            </TouchableOpacity>
                        </View>
                    ))
                )}
            </ScrollView>
        );
    };

    // Page 5B: Negative Question Bank
    const renderPerfNegativePage = () => (
        <ScrollView style={styles.pageScroll} contentContainerStyle={styles.listPadding}>
            {negativeCount > 0 && (
                <TouchableOpacity style={[styles.btnGeneratePDF, { backgroundColor: '#8E2DE2', marginBottom: 14 }]} onPress={practiceAllMistakes}>
                    <Ionicons name="play" size={18} color="white" />
                    <Text style={styles.btnActionLargeText}>Practice All ({negativeCount})</Text>
                </TouchableOpacity>
            )}
            {negativeBasketList.length === 0 ? (
                <View style={styles.emptyCard}>
                    <Text style={styles.emptyText}>🎉 Negative Bank is empty!</Text>
                </View>
            ) : (
                negativeBasketList.map(item => {
                    const qId = item.question_id || item.mcq_id;
                    return (
                        <View key={qId} style={styles.basketQuestionCard}>
                            <Text style={styles.basketQText}>{item.question}</Text>
                            <View style={styles.basketOptionsGrid}>
                                {['a', 'b', 'c', 'd'].map(opt => {
                                    const optText = item[`option_${opt}`];
                                    if (!optText) return null;
                                    return (
                                        <TouchableOpacity
                                            key={opt}
                                            style={styles.basketOptBtn}
                                            disabled={resolvingId === qId}
                                            onPress={() => handleResolveNegativeQuestion(item, opt)}
                                        >
                                            <Text style={styles.basketOptBtnText}>({opt.toUpperCase()}) {optText}</Text>
                                        </TouchableOpacity>
                                    );
                                })}
                            </View>
                        </View>
                    );
                })
            )}
        </ScrollView>
    );

    // Page 5C: Syllabus Tracker
    const renderPerfSyllabusPage = () => {
        const subjectsList = performanceData?.subjects || [];
        return (
            <ScrollView style={styles.pageScroll} contentContainerStyle={styles.listPadding}>
                {subjectsList.map(sub => {
                    const isExpanded = expandedSubjectId === sub.subject_id;
                    return (
                        <View key={sub.subject_id} style={styles.accordionContainer}>
                            <TouchableOpacity
                                style={styles.accordionHeader}
                                onPress={() => toggleSubjectAccordion(sub.subject_id)}
                            >
                                <View style={{ flex: 1 }}>
                                    <Text style={styles.accordionTitle}>{sub.subject_name}</Text>
                                    <Text style={styles.accordionMeta}>
                                        {sub.completed_chapters}/{sub.total_chapters} ({sub.completion_percentage}%)
                                    </Text>
                                </View>
                                <View style={styles.accordionRight}>
                                    <View style={styles.miniProgressBar}>
                                        <View style={[styles.miniProgressFill, { width: `${sub.completion_percentage}%` }]} />
                                    </View>
                                    <Ionicons name={isExpanded ? 'chevron-up' : 'chevron-down'} size={20} color="#666" />
                                </View>
                            </TouchableOpacity>

                            {isExpanded && (
                                <View style={styles.accordionBody}>
                                    {sub.chapters.map(ch => {
                                        const isCompleted = ch.status === 'completed';
                                        const isInProgress = ch.status === 'in_progress';
                                        const statusBadge = isCompleted ? 'Done' : (isInProgress ? 'In Progress' : 'Pending');
                                        return (
                                            <View key={ch.chapter_id} style={styles.chapterStatusRow}>
                                                <View style={{ flex: 1 }}>
                                                    <Text style={styles.chapterStatusName}>{ch.chapter_name}</Text>
                                                    <Text style={styles.chapterStatusMeta}>
                                                        {ch.total_mcqs} Qs {ch.tests_taken > 0 ? `• ${ch.accuracy_percentage}%` : ''}
                                                    </Text>
                                                </View>
                                                <View style={[
                                                    styles.statusPill,
                                                    isCompleted && { backgroundColor: '#dcfce7' },
                                                    isInProgress && { backgroundColor: '#fef9c3' }
                                                ]}>
                                                    <Text style={[
                                                        styles.statusPillText,
                                                        isCompleted && { color: '#16a34a' },
                                                        isInProgress && { color: '#ca8a04' }
                                                    ]}>
                                                        {statusBadge}
                                                    </Text>
                                                </View>
                                            </View>
                                        );
                                    })}
                                </View>
                            )}
                        </View>
                    );
                })}
            </ScrollView>
        );
    };

    // Page 4C: Practice Zone Menu (3-Card Grid: Paper Generator | Practice Exam | Previous PDFs)
    const renderPracticeMenuPage = () => (
        <ScrollView style={styles.pageScroll} contentContainerStyle={styles.gridContainer}>
            <View style={styles.gridWrap}>
                <GridTile
                    title="Paper Generator"
                    icon="print"
                    gradient={['#10B981', '#059669']}
                    onPress={() => pushPage('practice_generator')}
                />
                <GridTile
                    title="Practice Exam"
                    icon="play-circle"
                    gradient={['#4F46E5', '#7C3AED']}
                    onPress={() => pushPage('practice_exam')}
                />
                <GridTile
                    title="Previous PDFs"
                    icon="document-text"
                    gradient={['#0284C7', '#0369A1']}
                    onPress={() => pushPage('practice_pdfs')}
                />
            </View>
        </ScrollView>
    );

    // Shared Practice Selector for Paper Generator & Practice Exam
    const renderPracticeSelectorPage = (mode) => (
        <ScrollView style={styles.pageScroll} contentContainerStyle={styles.listPadding}>
            <Text style={styles.sectionHeader}>1. Subjects</Text>
            <View style={styles.practiceSubjectGrid}>
                {subjects.map(sub => {
                    const isSelected = selectedPracticeSubjects.some(s => s.subject_id === sub.subject_id);
                    return (
                        <TouchableOpacity
                            key={sub.subject_id}
                            style={[styles.practiceSubBtn, isSelected && styles.practiceSubBtnSelected]}
                            onPress={() => togglePracticeSubject(sub)}
                        >
                            <Text style={[styles.practiceSubBtnText, isSelected && { color: 'white', fontWeight: 'bold' }]}>
                                {sub.subject_name}
                            </Text>
                            {isSelected && <Ionicons name="checkmark-circle" size={16} color="white" />}
                        </TouchableOpacity>
                    );
                })}
            </View>

            {selectedPracticeSubjects.length > 0 && (
                <View style={{ marginTop: 18 }}>
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                        <Text style={styles.sectionHeader}>2. Chapters</Text>
                        <TouchableOpacity onPress={selectAllPracticeChapters}>
                            <Text style={styles.selectAllText}>Select All</Text>
                        </TouchableOpacity>
                    </View>
                    {loadingPracticeChapters ? (
                        <ActivityIndicator color="#8E2DE2" style={{ marginVertical: 12 }} />
                    ) : (
                        <View style={styles.practiceChaptersBox}>
                            {practiceChapters.map(group => (
                                <View key={group.subjectId} style={{ marginBottom: 12 }}>
                                    <Text style={styles.practiceGroupHeader}>{group.subjectName}</Text>
                                    {group.data.map(ch => {
                                        const isChecked = selectedPracticeChapters.includes(ch.chapter_id);
                                        return (
                                            <TouchableOpacity
                                                key={ch.chapter_id}
                                                style={styles.practiceChapterRow}
                                                onPress={() => togglePracticeChapter(ch.chapter_id)}
                                            >
                                                <Ionicons
                                                    name={isChecked ? 'checkbox' : 'square-outline'}
                                                    size={20}
                                                    color={isChecked ? '#4F46E5' : '#94a3b8'}
                                                />
                                                <Text style={[styles.practiceChapterName, isChecked && { fontWeight: '600', color: '#4F46E5' }]}>
                                                    {ch.chapter_name}
                                                </Text>
                                            </TouchableOpacity>
                                        );
                                    })}
                                </View>
                            ))}
                        </View>
                    )}
                </View>
            )}

            {selectedPracticeChapters.length > 0 && (
                <View style={{ marginTop: 18 }}>
                    <Text style={styles.sectionHeader}>3. Questions</Text>
                    <View style={styles.limitRow}>
                        {['15', '25', '50'].map(num => (
                            <TouchableOpacity
                                key={num}
                                style={[styles.limitBtn, practiceLimit === num && styles.limitBtnSelected]}
                                onPress={() => setPracticeLimit(num)}
                            >
                                <Text style={[styles.limitBtnText, practiceLimit === num && styles.limitBtnTextSelected]}>
                                    {num}
                                </Text>
                            </TouchableOpacity>
                        ))}
                    </View>

                    {mode === 'generator' ? (
                        <TouchableOpacity style={styles.btnGeneratePDF} onPress={generateExamPaperPDF}>
                            <Ionicons name="print-outline" size={20} color="white" />
                            <Text style={styles.btnActionLargeText}>Generate PDF</Text>
                        </TouchableOpacity>
                    ) : (
                        <TouchableOpacity
                            style={[styles.btnGeneratePDF, { backgroundColor: '#4F46E5' }]}
                            onPress={startCustomOnlineTest}
                        >
                            <Ionicons name="play-circle-outline" size={20} color="white" />
                            <Text style={styles.btnActionLargeText}>Start Practice Exam</Text>
                        </TouchableOpacity>
                    )}
                </View>
            )}
        </ScrollView>
    );

    // Page 5F: Previous Exam PDFs
    const renderPracticePdfsPage = () => {
        const pastExams = liveExamsData?.past_exams || [];
        const filtered =
            selectedExamCategory && selectedExamCategory.id !== 'all'
                ? pastExams.filter(ex => ex.category_slug === selectedExamCategory.id)
                : pastExams;

        return (
            <ScrollView style={styles.pageScroll} contentContainerStyle={styles.listPadding}>
                {filtered.length === 0 ? (
                    <View style={styles.emptyCard}>
                        <Ionicons name="document-outline" size={32} color="#cbd5e1" style={{ marginBottom: 6 }} />
                        <Text style={styles.emptyText}>No previous PDFs available.</Text>
                    </View>
                ) : (
                    filtered.map(exam => (
                        <View key={exam.exam_id} style={styles.pastExamCard}>
                            <View style={{ flex: 1 }}>
                                <Text style={styles.pastExamTitle}>{exam.title}</Text>
                                <Text style={styles.examCardMeta}>
                                    {exam.total_questions} Qs • {exam.duration_minutes}m
                                </Text>
                            </View>
                            <TouchableOpacity
                                style={[styles.btnHistoryAction, { backgroundColor: '#dcfce7' }]}
                                onPress={() => downloadPastExamPDF(exam)}
                            >
                                <Ionicons name="download-outline" size={16} color="#15803d" />
                                <Text style={[styles.btnHistoryActionText, { color: '#15803d' }]}>PDF</Text>
                            </TouchableOpacity>
                        </View>
                    ))
                )}
            </ScrollView>
        );
    };

    const renderPageContent = () => {
        switch (currentPage) {
            case 'home': return renderHomePage();
            case 'live_menu': return renderLiveMenuPage();
            case 'live_ongoing': return renderLiveOngoingPage();
            case 'live_history': return renderLiveHistoryPage();
            case 'exam_list': return renderExamListPage();
            case 'exam_menu': return renderExamMenuPage();
            case 'subjects': return renderSubjectsPage();
            case 'performance_menu': return renderPerformanceMenuPage();
            case 'perf_weak': return renderPerfWeakPage();
            case 'perf_negative': return renderPerfNegativePage();
            case 'perf_syllabus': return renderPerfSyllabusPage();
            case 'practice_menu': return renderPracticeMenuPage();
            case 'practice_generator': return renderPracticeSelectorPage('generator');
            case 'practice_exam': return renderPracticeSelectorPage('practice_exam');
            case 'practice_pdfs': return renderPracticePdfsPage();
            default: return renderHomePage();
        }
    };

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="#6366F1" />

            {/* Minimal Clean Header */}
            <LinearGradient colors={['#6366F1', '#4A00E0']} style={styles.header}>
                <SafeAreaView>
                    <View style={styles.headerRow}>
                        <TouchableOpacity
                            onPress={() => {
                                if (!popPage()) navigation.goBack();
                            }}
                            style={styles.backBtn}
                        >
                            <Ionicons name="arrow-back" size={22} color="white" />
                        </TouchableOpacity>
                        <View style={{ flex: 1 }}>
                            <Text style={styles.headerTitle} numberOfLines={1}>{getHeaderTitle()}</Text>
                        </View>
                        <LanguageToggle
                            selectedLanguage={selectedLanguage}
                            onLanguageChange={handleLanguageChange}
                        />
                    </View>
                </SafeAreaView>
            </LinearGradient>

            {/* Body */}
            <View style={styles.mainContainer}>
                {loading ? (
                    <ActivityIndicator size="large" color="#6366F1" style={{ marginTop: 60 }} />
                ) : (
                    renderPageContent()
                )}
            </View>

            {/* Leaderboard Modal */}
            <Modal
                visible={leaderboardModalVisible}
                animationType="slide"
                transparent={true}
                onRequestClose={() => setLeaderboardModalVisible(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <View style={styles.modalHeader}>
                            <Text style={styles.modalTitle}>👑 Leaderboard</Text>
                            <TouchableOpacity onPress={() => setLeaderboardModalVisible(false)}>
                                <Ionicons name="close" size={24} color="#333" />
                            </TouchableOpacity>
                        </View>

                        {leaderboardLoading ? (
                            <ActivityIndicator size="large" color="#6366F1" style={{ marginVertical: 40 }} />
                        ) : (
                            <ScrollView style={{ maxHeight: 450 }}>
                                {selectedExamLeaderboard?.user_rank && (
                                    <View style={styles.myRankCard}>
                                        <Text style={styles.myRankTitle}>Your Rank</Text>
                                        <Text style={styles.myRankDetails}>
                                            #{selectedExamLeaderboard.user_rank.rank} • {selectedExamLeaderboard.user_rank.net_score} Marks • {selectedExamLeaderboard.user_rank.formatted_time}
                                        </Text>
                                    </View>
                                )}

                                {(selectedExamLeaderboard?.top_rankers || []).map(r => (
                                    <View key={r.attempt_id} style={styles.rankRow}>
                                        <View style={styles.rankBadgeCircle}>
                                            <Text style={styles.rankBadgeText}>{r.rank}</Text>
                                        </View>
                                        <View style={{ flex: 1, marginLeft: 10 }}>
                                            <Text style={styles.rankStudentName}>{r.student_name}</Text>
                                            <Text style={styles.rankTimeSpent}>{r.formatted_time}</Text>
                                        </View>
                                        <Text style={styles.rankScore}>{r.net_score}</Text>
                                    </View>
                                ))}
                            </ScrollView>
                        )}
                    </View>
                </View>
            </Modal>

            {actionLoading && (
                <View style={styles.actionLoadingOverlay}>
                    <View style={styles.actionLoadingBox}>
                        <ActivityIndicator size="large" color="#6366F1" />
                        <Text style={styles.actionLoadingText}>Loading...</Text>
                    </View>
                </View>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#f8fafc' },
    header: { paddingHorizontal: 16, paddingBottom: 14, borderBottomLeftRadius: 24, borderBottomRightRadius: 24 },
    headerRow: { flexDirection: 'row', alignItems: 'center', marginTop: Platform.OS === 'android' ? 30 : 0 },
    backBtn: { padding: 8, backgroundColor: 'rgba(255,255,255,0.2)', borderRadius: 12, marginRight: 12 },
    headerTitle: { fontSize: 19, fontWeight: 'bold', color: 'white' },

    mainContainer: { flex: 1 },
    pageScroll: { flex: 1 },
    gridContainer: { padding: 18, paddingBottom: 32 },
    listPadding: { padding: 16, paddingBottom: 32 },

    /* Minimal Attractive Grid Cards */
    gridWrap: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', gap: 14 },
    subjectRow: { justifyContent: 'space-between', marginBottom: 14 },
    gridTile: {
        width: '47.5%',
        backgroundColor: '#ffffff',
        borderRadius: 20,
        padding: 16,
        minHeight: 132,
        justifyContent: 'space-between',
        borderWidth: 1,
        borderColor: '#f1f5f9',
        shadowColor: '#0f172a',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.06,
        shadowRadius: 12,
        elevation: 3,
        marginBottom: 4
    },
    gridTileTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
    gridIconBox: { width: 48, height: 48, borderRadius: 16, justifyContent: 'center', alignItems: 'center' },
    gridBadge: { backgroundColor: '#EEF2FF', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 10 },
    gridBadgeText: { fontSize: 11, fontWeight: 'bold', color: '#4F46E5' },
    gridTileTitle: { fontSize: 15, fontWeight: '700', color: '#0f172a', marginTop: 14 },

    sectionHeader: { fontSize: 15, fontWeight: 'bold', color: '#1e293b', marginBottom: 10 },
    emptyCard: { backgroundColor: 'white', padding: 24, borderRadius: 16, alignItems: 'center', marginVertical: 10 },
    emptyText: { color: '#94a3b8', fontSize: 13, textAlign: 'center' },

    /* Spotlight Banner */
    spotlightCard: { borderRadius: 20, padding: 18, marginBottom: 16, elevation: 4 },
    spotlightBadge: { alignSelf: 'flex-start', backgroundColor: 'rgba(0,0,0,0.22)', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 20, marginBottom: 8 },
    spotlightBadgeText: { color: 'white', fontSize: 11, fontWeight: 'bold' },
    spotlightTitle: { color: 'white', fontSize: 18, fontWeight: 'bold', marginBottom: 4 },
    spotlightSubtitle: { color: 'rgba(255,255,255,0.9)', fontSize: 13, marginBottom: 14 },
    spotlightActions: { flexDirection: 'row', gap: 10 },
    btnStartSpotlight: { flex: 1, backgroundColor: 'white', paddingVertical: 12, borderRadius: 12, alignItems: 'center' },
    btnStartSpotlightText: { color: '#E11D48', fontWeight: 'bold', fontSize: 14 },
    btnShareSpotlight: { backgroundColor: '#25D366', paddingHorizontal: 16, paddingVertical: 12, borderRadius: 12, justifyContent: 'center', alignItems: 'center' },
    attemptedBadge: { flex: 1, backgroundColor: 'rgba(255,255,255,0.22)', padding: 11, borderRadius: 12 },
    attemptedText: { color: 'white', fontSize: 12, fontWeight: '600' },

    /* Exam & History Cards */
    examCard: { flexDirection: 'row', backgroundColor: 'white', padding: 16, borderRadius: 16, marginBottom: 12, alignItems: 'center', borderLeftWidth: 4, elevation: 2 },
    examCardTitle: { fontSize: 14, fontWeight: 'bold', color: '#1e293b' },
    examCardMeta: { fontSize: 12, color: '#64748b', marginTop: 3 },
    statusDot: { width: 8, height: 8, borderRadius: 4 },
    examStatusText: { fontSize: 10, fontWeight: 'bold', color: '#64748b' },
    btnActionSmall: { paddingHorizontal: 14, paddingVertical: 7, borderRadius: 8, flexDirection: 'row', alignItems: 'center' },
    btnActionText: { color: 'white', fontSize: 12, fontWeight: 'bold' },

    pastExamCard: { flexDirection: 'row', backgroundColor: 'white', padding: 16, borderRadius: 16, marginBottom: 12, alignItems: 'center', elevation: 2 },
    pastExamTitle: { fontSize: 14, fontWeight: 'bold', color: '#1e293b' },
    rankPill: { alignSelf: 'flex-start', backgroundColor: '#eef2ff', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8, marginTop: 6 },
    rankPillText: { fontSize: 12, color: '#4A00E0', fontWeight: 'bold' },
    notAttemptedText: { fontSize: 11, color: '#94a3b8', marginTop: 4 },
    btnHistoryAction: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 12, paddingVertical: 8, borderRadius: 10 },
    btnHistoryActionText: { fontSize: 12, fontWeight: 'bold' },

    /* Performance Pages */
    accordionContainer: { backgroundColor: 'white', borderRadius: 14, marginBottom: 10, overflow: 'hidden', elevation: 1 },
    accordionHeader: { flexDirection: 'row', alignItems: 'center', padding: 14 },
    accordionTitle: { fontSize: 14, fontWeight: 'bold', color: '#1e293b' },
    accordionMeta: { fontSize: 11, color: '#64748b', marginTop: 2 },
    accordionRight: { flexDirection: 'row', alignItems: 'center', gap: 8 },
    miniProgressBar: { width: 60, height: 6, backgroundColor: '#e2e8f0', borderRadius: 3, overflow: 'hidden' },
    miniProgressFill: { height: '100%', backgroundColor: '#10b981' },
    accordionBody: { paddingHorizontal: 14, paddingBottom: 10, borderTopWidth: 1, borderTopColor: '#f1f5f9' },
    chapterStatusRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: '#f8fafc' },
    chapterStatusName: { fontSize: 13, color: '#334155' },
    chapterStatusMeta: { fontSize: 11, color: '#94a3b8' },
    statusPill: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6, backgroundColor: '#f1f5f9' },
    statusPillText: { fontSize: 10, fontWeight: 'bold', color: '#64748b' },

    weakCard: { flexDirection: 'row', backgroundColor: 'white', borderLeftWidth: 4, borderLeftColor: '#f43f5e', padding: 14, borderRadius: 14, marginBottom: 10, alignItems: 'center', elevation: 1 },
    weakTitle: { fontSize: 14, fontWeight: 'bold', color: '#0f172a' },
    weakMeta: { fontSize: 12, color: '#64748b', marginTop: 2 },
    btnWeakPractice: { backgroundColor: '#f43f5e', paddingHorizontal: 14, paddingVertical: 8, borderRadius: 10 },
    btnWeakPracticeText: { color: 'white', fontSize: 12, fontWeight: 'bold' },

    basketQuestionCard: { backgroundColor: 'white', padding: 14, borderRadius: 14, marginBottom: 10, elevation: 1 },
    basketQText: { fontSize: 13, fontWeight: 'bold', color: '#1e293b', marginBottom: 10 },
    basketOptionsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    basketOptBtn: { width: '48%', backgroundColor: '#f8fafc', borderWidth: 1, borderColor: '#e2e8f0', padding: 8, borderRadius: 8 },
    basketOptBtnText: { fontSize: 12, color: '#334155' },

    /* Practice Zone Pages */
    practiceSubjectGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    practiceSubBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: 'white', borderWidth: 1, borderColor: '#cbd5e1', paddingHorizontal: 14, paddingVertical: 9, borderRadius: 20 },
    practiceSubBtnSelected: { backgroundColor: '#4F46E5', borderColor: '#4F46E5' },
    practiceSubBtnText: { fontSize: 12, color: '#475569' },
    selectAllText: { fontSize: 12, color: '#4F46E5', fontWeight: 'bold' },
    practiceChaptersBox: { backgroundColor: 'white', borderRadius: 14, padding: 12, maxHeight: 240, borderWidth: 1, borderColor: '#e2e8f0', marginTop: 8 },
    practiceGroupHeader: { fontSize: 12, fontWeight: 'bold', color: '#64748b', marginBottom: 6 },
    practiceChapterRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 6 },
    practiceChapterName: { fontSize: 13, color: '#334155', flex: 1 },
    limitRow: { flexDirection: 'row', gap: 12, marginVertical: 10 },
    limitBtn: { flex: 1, paddingVertical: 10, borderRadius: 12, backgroundColor: 'white', borderWidth: 1, borderColor: '#cbd5e1', alignItems: 'center' },
    limitBtnSelected: { backgroundColor: '#4F46E5', borderColor: '#4F46E5' },
    limitBtnText: { fontSize: 13, color: '#475569', fontWeight: '600' },
    limitBtnTextSelected: { color: 'white', fontWeight: 'bold' },
    btnGeneratePDF: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: '#10b981', paddingVertical: 14, borderRadius: 14, elevation: 2 },
    btnActionLargeText: { color: 'white', fontSize: 14, fontWeight: 'bold' },

    /* Modal & Overlay */
    modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center', padding: 20 },
    modalContent: { backgroundColor: 'white', borderRadius: 20, padding: 20, maxHeight: 520 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', borderBottomWidth: 1, borderBottomColor: '#eee', paddingBottom: 12, marginBottom: 12 },
    modalTitle: { fontSize: 16, fontWeight: 'bold', color: '#1e293b' },
    myRankCard: { backgroundColor: '#eef2ff', padding: 12, borderRadius: 12, marginBottom: 14 },
    myRankTitle: { fontSize: 12, fontWeight: 'bold', color: '#4F46E5' },
    myRankDetails: { fontSize: 13, color: '#4F46E5', marginTop: 2 },
    rankRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: '#f1f5f9' },
    rankBadgeCircle: { width: 28, height: 28, borderRadius: 14, backgroundColor: '#6366F1', justifyContent: 'center', alignItems: 'center' },
    rankBadgeText: { color: 'white', fontSize: 12, fontWeight: 'bold' },
    rankStudentName: { fontSize: 13, fontWeight: 'bold', color: '#1e293b' },
    rankTimeSpent: { fontSize: 11, color: '#94a3b8' },
    rankScore: { fontSize: 14, fontWeight: 'bold', color: '#10b981' },
    actionLoadingOverlay: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(15, 23, 42, 0.45)', justifyContent: 'center', alignItems: 'center', zIndex: 100 },
    actionLoadingBox: { backgroundColor: 'white', paddingHorizontal: 28, paddingVertical: 22, borderRadius: 16, alignItems: 'center', elevation: 6 },
    actionLoadingText: { marginTop: 10, fontSize: 13, fontWeight: '600', color: '#1e293b' }
});

export default ScholarshipSubjectsScreen;
