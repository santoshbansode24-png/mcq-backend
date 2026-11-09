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
    TextInput,
    RefreshControl,
    Modal,
    Share,
    Alert,
    Dimensions
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

const { width } = Dimensions.get('window');

const ScholarshipSubjectsScreen = ({ navigation, route }) => {
    const scholarshipClassId = route.params?.scholarshipClassId || 38;
    const levelTitle = route.params?.levelTitle || "Scholarship & Olympiad";

    // 4 Core Tabs: 'live' | 'subjects' | 'performance' | 'practice'
    const [activeTab, setActiveTab] = useState('live');
    const [selectedLanguage, setSelectedLanguage] = useState('marathi');
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [userId, setUserId] = useState(0);

    // Tab 1: Live Exam States
    const [liveExamsData, setLiveExamsData] = useState({
        spotlight_exam: null,
        live_and_upcoming: [],
        past_exams: []
    });
    const [leaderboardModalVisible, setLeaderboardModalVisible] = useState(false);
    const [selectedExamLeaderboard, setSelectedExamLeaderboard] = useState(null);
    const [leaderboardLoading, setLeaderboardLoading] = useState(false);

    // Tab 2: Subjects State
    const [subjects, setSubjects] = useState([]);

    // Tab 3: Performance & Syllabus Tracker States
    const [performanceData, setPerformanceData] = useState(null);
    const [expandedSubjectId, setExpandedSubjectId] = useState(null);
    const [resolvingId, setResolvingId] = useState(null);
    const [negativeBasketList, setNegativeBasketList] = useState([]);
    const [negativeCount, setNegativeCount] = useState(0);

    // Tab 4: Practice Zone / Exam Paper Generator States
    const [practiceChapters, setPracticeChapters] = useState([]);
    const [selectedPracticeSubjects, setSelectedPracticeSubjects] = useState([]);
    const [selectedPracticeChapters, setSelectedPracticeChapters] = useState([]);
    const [practiceLimit, setPracticeLimit] = useState('25');
    const [loadingPracticeChapters, setLoadingPracticeChapters] = useState(false);

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

    // ─────────────────────────────────────────────────────────────────────────────
    // 1. LIVE EXAM FUNCTIONS
    // ─────────────────────────────────────────────────────────────────────────────
    const loadLiveExams = async (uid = userId) => {
        try {
            const res = await axios.get(`${API_URL}/get_scholarship_live_exams.php?user_id=${uid}&class_id=${scholarshipClassId}`);
            if (res.data?.status === 'success') {
                setLiveExamsData(res.data.data);
            }
        } catch (err) {
            console.log('Error loading live exams:', err);
        }
    };

    const startLiveExam = async (examId) => {
        try {
            setLoading(true);
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
                Alert.alert('Notice', 'या परीक्षेसाठी अद्याप प्रश्न उपलब्ध नाहीत. कृपया नंतर पुन्हा प्रयत्न करा.');
            }
        } catch (err) {
            Alert.alert('Error', 'परीक्षेचे प्रश्न लोड करताना त्रुटी आली.');
        } finally {
            setLoading(false);
        }
    };

    const shareExamOnWhatsApp = async (shareText) => {
        try {
            await Share.share({
                message: shareText,
                title: 'शिष्यवृत्ती सराव परीक्षा'
            });
        } catch (e) {
            console.log('Share error:', e);
        }
    };

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

    // ─────────────────────────────────────────────────────────────────────────────
    // 2. SUBJECTS FUNCTIONS
    // ─────────────────────────────────────────────────────────────────────────────
    const loadSubjectsData = async () => {
        try {
            const response = await fetchSubjects(scholarshipClassId);
            if (response && (response.status === 'success' || Array.isArray(response))) {
                const allData = response.data || response;
                const regular = allData.filter(s => !s.subject_name.includes("Mock Test") && !s.subject_name.includes("Previous Paper"));
                setSubjects(regular);
            }
        } catch (err) {
            console.log('Error loading subjects:', err);
        }
    };

    // ─────────────────────────────────────────────────────────────────────────────
    // 3. PERFORMANCE & SYLLABUS TRACKER FUNCTIONS
    // ─────────────────────────────────────────────────────────────────────────────
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

    // Inline Re-solve of Negative Basket Question
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
                // Correct! Show alert & dynamically remove from list
                Alert.alert("🎉 अप्रतिम!", "तुमचे उत्तर बरोबर आहे! हा प्रश्न निगेटिव्ह बास्केटमधून क्लिअर झाला आहे.");
                setNegativeBasketList(prev => prev.filter(q => (q.question_id || q.mcq_id) !== qId));
                setNegativeCount(prev => Math.max(0, prev - 1));
            } else {
                Alert.alert("❌ चूक", `हे उत्तर चुकीचे आहे.\n\nस्पष्टीकरण: ${res.data?.data?.explanation || 'पुन्हा प्रयत्न करा.'}`);
            }
        } catch (e) {
            Alert.alert("Error", "प्रश्न सोडवताना त्रुटी आली.");
        } finally {
            setResolvingId(null);
        }
    };

    const practiceAllMistakes = () => {
        if (!negativeBasketList || negativeBasketList.length === 0) {
            Alert.alert("अभिनंदन!", "सध्या तुमच्याकडे कोणतेही चुकलेले प्रश्न नाहीत!");
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
            subjectName: 'चुकलेल्या प्रश्नांचा सराव'
        });
    };

    // ─────────────────────────────────────────────────────────────────────────────
    // 4. PRACTICE ZONE (Exam Paper Generator & Custom Test)
    // ─────────────────────────────────────────────────────────────────────────────
    const togglePracticeSubject = (subject) => {
        setSelectedPracticeSubjects(prev => {
            const exists = prev.find(s => s.subject_id === subject.subject_id);
            if (exists) {
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
        setLoadingPracticeChapters(true);
        try {
            const promises = selectedPracticeSubjects.map(sub =>
                fetchChapters(sub.subject_id)
                    .then(res => ({
                        subjectName: sub.subject_name,
                        subjectId: sub.subject_id,
                        data: res.status === 'success' ? res.data : []
                    }))
                    .catch(() => ({ subjectName: sub.subject_name, subjectId: sub.subject_id, data: [] }))
            );
            const results = await Promise.all(promises);
            setPracticeChapters(results.filter(r => r.data.length > 0));
        } catch (e) {
            console.error('Load chapters error:', e);
        } finally {
            setLoadingPracticeChapters(false);
        }
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
            setLoading(true);
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
            setLoading(false);
        }
    };

    const generateExamPaperPDF = async () => {
        if (selectedPracticeChapters.length === 0) {
            Alert.alert('Notice', 'कृपया पेपर तयार करण्यासाठी किमान एक धडा निवडा.');
            return;
        }
        try {
            setLoading(true);
            const res = await axios.post(`${API_URL}/generate_custom_test.php`, {
                chapter_ids: selectedPracticeChapters.join(','),
                limit: parseInt(practiceLimit) || 25
            });

            if (res.data?.status === 'success') {
                const questions = res.data.data;
                const dateStr = new Date().toLocaleDateString('mr-IN');
                const subTitle = selectedPracticeSubjects.map(s => s.subject_name).join(' व ');

                const html = `
                    <html>
                        <head>
                            <meta charset="utf-8">
                            <style>
                                body { font-family: 'Helvetica', sans-serif; padding: 30px; color: #222; }
                                .header { text-align: center; border-bottom: 2px solid #8E2DE2; padding-bottom: 15px; margin-bottom: 25px; }
                                .app-name { font-size: 22px; font-weight: bold; color: #4A00E0; margin: 0; }
                                .exam-title { font-size: 18px; font-weight: bold; margin-top: 5px; }
                                .meta { display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 8px; }
                                .q-card { margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
                                .q-text { font-size: 15px; font-weight: bold; margin-bottom: 8px; line-height: 1.4; }
                                .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 14px; }
                                .opt { background: #f8fafc; padding: 6px 10px; border-radius: 4px; border: 1px solid #e2e8f0; }
                                .page-break { page-break-before: always; }
                                .key-header { color: #8E2DE2; font-size: 18px; font-weight: bold; margin-bottom: 15px; }
                                table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
                                th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
                                th { background-color: #f1f5f9; font-weight: bold; }
                                .correct-ans { color: #16a34a; font-weight: bold; }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <div class="app-name">Veeru Learning App</div>
                                <div class="exam-title">शिष्यवृत्ती सराव प्रश्नपत्रिका - ${subTitle}</div>
                                <div class="meta">
                                    <span>दिनांक: ${dateStr}</span>
                                    <span>एकूण प्रश्न: ${questions.length}</span>
                                    <span>वेळ: ${questions.length * 1.5} मिनिटे</span>
                                </div>
                            </div>

                            <div class="questions">
                                ${questions.map((q, idx) => `
                                    <div class="q-card">
                                        <div class="q-text">प्र. ${idx + 1}. ${q.question}</div>
                                        <div class="options-grid">
                                            <div class="opt">(A) ${q.option_a || ''}</div>
                                            <div class="opt">(B) ${q.option_b || ''}</div>
                                            <div class="opt">(C) ${q.option_c || ''}</div>
                                            <div class="opt">(D) ${q.option_d || ''}</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>

                            <div class="page-break"></div>

                            <div class="key-header">उत्तरतालिका व सविस्तर स्पष्टीकरण (Answer Key & Solutions)</div>
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 10%;">प्र.क्र.</th>
                                        <th style="width: 15%;">अचूक उत्तर</th>
                                        <th>मराठी स्पष्टीकरण</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${questions.map((q, idx) => `
                                        <tr>
                                            <td>${idx + 1}</td>
                                            <td class="correct-ans">${(q.correct_answer || '').toUpperCase()}</td>
                                            <td>${q.explanation || 'स्पष्टीकरण उपलब्ध नाही.'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </body>
                    </html>
                `;

                const { uri } = await Print.printToFileAsync({ html });
                await Sharing.shareAsync(uri);
            } else {
                Alert.alert('Error', res.data?.message || 'पेपर जनरेट होऊ शकला नाही.');
            }
        } catch (e) {
            Alert.alert('Error', 'PDF तयार करताना त्रुटी आली.');
        } finally {
            setLoading(false);
        }
    };

    // ─────────────────────────────────────────────────────────────────────────────
    // RENDER TAB CONTENTS
    // ─────────────────────────────────────────────────────────────────────────────

    // TAB 1: LIVE EXAM RENDER
    const renderLiveExamTab = () => {
        const { spotlight_exam, live_and_upcoming = [], past_exams = [] } = liveExamsData || {};

        return (
            <ScrollView
                style={styles.tabContentScroll}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
            >
                {/* Spotlight Exam Banner */}
                {spotlight_exam && (
                    <LinearGradient colors={['#FF416C', '#FF4B2B']} style={styles.spotlightCard}>
                        <View style={styles.spotlightBadge}>
                            <Text style={styles.spotlightBadgeText}>
                                {spotlight_exam.status === 'live_now' ? '🔴 LIVE NOW' : '⏳ UPCOMING EXAM'}
                            </Text>
                        </View>
                        <Text style={styles.spotlightTitle}>{spotlight_exam.title}</Text>
                        <Text style={styles.spotlightSubtitle}>
                            ⏱️ {spotlight_exam.duration_minutes} मिनिटे | 🎯 {spotlight_exam.total_questions} प्रश्न | +{spotlight_exam.positive_marks} गुण
                        </Text>
                        {spotlight_exam.start_time && (
                            <Text style={styles.spotlightTime}>
                                📅 {spotlight_exam.start_time}
                            </Text>
                        )}
                        <View style={styles.spotlightActions}>
                            {spotlight_exam.has_attempted ? (
                                <View style={styles.attemptedBadge}>
                                    <Text style={styles.attemptedText}>
                                        ✔ तुम्ही परीक्षा दिली आहे (गुण: {spotlight_exam.user_score} | रँक: #{spotlight_exam.user_rank || '-'})
                                    </Text>
                                </View>
                            ) : (
                                <TouchableOpacity
                                    style={styles.btnStartSpotlight}
                                    onPress={() => startLiveExam(spotlight_exam.exam_id)}
                                >
                                    <Text style={styles.btnStartSpotlightText}>🚀 आताच परीक्षा द्या</Text>
                                </TouchableOpacity>
                            )}
                            <TouchableOpacity
                                style={styles.btnShareSpotlight}
                                onPress={() => shareExamOnWhatsApp(spotlight_exam.share_text)}
                            >
                                <Ionicons name="logo-whatsapp" size={18} color="white" />
                                <Text style={styles.btnShareSpotlightText}>शेअर</Text>
                            </TouchableOpacity>
                        </View>
                    </LinearGradient>
                )}

                {/* Upcoming / Active Live Exams */}
                <Text style={styles.sectionHeader}>🔴 आगामी व चालू परीक्षा (Live & Upcoming)</Text>
                {live_and_upcoming.length === 0 ? (
                    <View style={styles.emptyCard}>
                        <Text style={styles.emptyText}>सध्या कोणतीही नवीन परीक्षा शेड्युल केलेली नाही.</Text>
                    </View>
                ) : (
                    live_and_upcoming.map(exam => (
                        <View key={exam.exam_id} style={styles.examCard}>
                            <View style={{ flex: 1 }}>
                                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 4 }}>
                                    <View style={[styles.statusDot, { backgroundColor: exam.status === 'live_now' ? '#10b981' : '#3b82f6' }]} />
                                    <Text style={styles.examStatusText}>
                                        {exam.status === 'live_now' ? 'चालू आहे' : 'लवकरच'}
                                    </Text>
                                </View>
                                <Text style={styles.examCardTitle}>{exam.title}</Text>
                                <Text style={styles.examCardMeta}>
                                    {exam.duration_minutes} मिनिटे • {exam.total_questions} प्रश्न • {exam.total_participants} विद्यार्थी
                                </Text>
                            </View>
                            <View style={{ alignItems: 'flex-end', gap: 8 }}>
                                <TouchableOpacity
                                    style={[styles.btnActionSmall, { backgroundColor: '#8E2DE2' }]}
                                    onPress={() => startLiveExam(exam.exam_id)}
                                >
                                    <Text style={styles.btnActionText}>परीक्षेला बसा</Text>
                                </TouchableOpacity>
                                <TouchableOpacity
                                    style={[styles.btnActionSmall, { backgroundColor: '#25D366' }]}
                                    onPress={() => shareExamOnWhatsApp(exam.share_text)}
                                >
                                    <Ionicons name="logo-whatsapp" size={14} color="white" />
                                    <Text style={styles.btnActionText}>शेअर</Text>
                                </TouchableOpacity>
                            </View>
                        </View>
                    ))
                )}

                {/* Past Exams & Leaderboard */}
                <Text style={[styles.sectionHeader, { marginTop: 24 }]}>🏆 मागील परीक्षांचे निकाल व रँकिंग (Past Results)</Text>
                {past_exams.length === 0 ? (
                    <View style={styles.emptyCard}>
                        <Text style={styles.emptyText}>अद्याप कोणतीही पूर्ण झालेली परीक्षा नाही.</Text>
                    </View>
                ) : (
                    past_exams.map(exam => (
                        <View key={exam.exam_id} style={styles.pastExamCard}>
                            <View style={{ flex: 1 }}>
                                <Text style={styles.pastExamTitle}>{exam.title}</Text>
                                {exam.has_attempted ? (
                                    <View style={styles.rankPill}>
                                        <Text style={styles.rankPillText}>
                                            🏅 तुमचा रँक: #{exam.user_rank || '-'} | गुण: {exam.user_score}
                                        </Text>
                                    </View>
                                ) : (
                                    <Text style={styles.notAttemptedText}>तुम्ही ही परीक्षा दिली नव्हती</Text>
                                )}
                            </View>
                            <TouchableOpacity
                                style={styles.btnLeaderboard}
                                onPress={() => openLeaderboard(exam.exam_id)}
                            >
                                <Ionicons name="trophy" size={16} color="#4A00E0" />
                                <Text style={styles.btnLeaderboardText}>लीडरबोर्ड</Text>
                            </TouchableOpacity>
                        </View>
                    ))
                )}
            </ScrollView>
        );
    };

    // TAB 2: SUBJECTS RENDER
    const renderSubjectsTab = () => {
        const gradients = [
            ['#DA22FF', '#9733EE'],
            ['#EA384D', '#D31027'],
            ['#00C6FF', '#0072FF'],
            ['#F2994A', '#F2C94C'],
            ['#11998e', '#38ef7d']
        ];

        return (
            <FlatList
                data={subjects}
                keyExtractor={item => item.subject_id.toString()}
                numColumns={2}
                columnWrapperStyle={styles.subjectRow}
                contentContainerStyle={{ padding: 16 }}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
                renderItem={({ item, index }) => (
                    <TouchableOpacity
                        style={styles.subjectCard}
                        onPress={() => navigation.navigate('ScholarshipChapters', {
                            subjectId: item.subject_id,
                            subjectName: item.subject_name,
                            selectedLanguage: selectedLanguage
                        })}
                    >
                        <LinearGradient colors={gradients[index % gradients.length]} style={styles.subjectCardGradient}>
                            <Text style={styles.subjectInitial}>{item.subject_name.charAt(0)}</Text>
                            <Text style={styles.subjectCardTitle}>{item.subject_name}</Text>
                            <View style={styles.subjectArrow}>
                                <Ionicons name="arrow-forward" size={18} color="white" />
                            </View>
                        </LinearGradient>
                    </TouchableOpacity>
                )}
                ListEmptyComponent={<Text style={styles.emptyText}>कोणतेही विषय उपलब्ध नाहीत.</Text>}
            />
        );
    };

    // TAB 3: PERFORMANCE & SYLLABUS TRACKER RENDER
    const renderPerformanceTab = () => {
        const summary = performanceData?.syllabus_summary;
        const subjectsList = performanceData?.subjects || [];
        const weakList = performanceData?.weak_chapters || [];
        const readiness = performanceData?.readiness_percentage || 0;

        return (
            <ScrollView
                style={styles.tabContentScroll}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
            >
                {/* Readiness Meter Card */}
                <LinearGradient colors={['#8E2DE2', '#4A00E0']} style={styles.readinessCard}>
                    <Text style={styles.readinessLabel}>⚡ शिष्यवृत्ती तयारी स्तर (Scholarship Readiness)</Text>
                    <View style={styles.readinessRow}>
                        <Text style={styles.readinessNumber}>{readiness}%</Text>
                        <View style={{ flex: 1, marginLeft: 16 }}>
                            <Text style={styles.readinessSub}>
                                एकूण सिलॅबस पूर्ण: {summary?.completion_percentage || 0}%
                            </Text>
                            <Text style={styles.readinessSub}>
                                {summary?.completed_chapters || 0} पूर्ण • {summary?.pending_chapters || 0} बाकी
                            </Text>
                        </View>
                    </View>
                </LinearGradient>

                {/* 1. Chapter-wise Syllabus Tracker */}
                <Text style={styles.sectionHeader}>📖 १. सिलॅबस ट्रॅकर: किती अभ्यास बाकी आहे?</Text>
                <Text style={styles.sectionSubtext}>खालील कोणत्याही विषयावर क्लिक करून धड्यांची स्थिती पहा:</Text>

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
                                        {sub.completed_chapters}/{sub.total_chapters} धडे पूर्ण ({sub.completion_percentage}%)
                                    </Text>
                                </View>
                                <View style={styles.accordionRight}>
                                    <View style={styles.miniProgressBar}>
                                        <View style={[styles.miniProgressFill, { width: `${sub.completion_percentage}%` }]} />
                                    </View>
                                    <Ionicons name={isExpanded ? "chevron-up" : "chevron-down"} size={20} color="#666" />
                                </View>
                            </TouchableOpacity>

                            {isExpanded && (
                                <View style={styles.accordionBody}>
                                    {sub.chapters.map(ch => {
                                        const isCompleted = ch.status === 'completed';
                                        const isInProgress = ch.status === 'in_progress';
                                        const statusBadge = isCompleted ? '🟢 पूर्ण' : (isInProgress ? '🟡 चालू' : '⚪ बाकी');

                                        return (
                                            <View key={ch.chapter_id} style={styles.chapterStatusRow}>
                                                <View style={{ flex: 1 }}>
                                                    <Text style={styles.chapterStatusName}>{ch.chapter_name}</Text>
                                                    <Text style={styles.chapterStatusMeta}>
                                                        {ch.total_mcqs} प्रश्न {ch.tests_taken > 0 ? `• अचूकता: ${ch.accuracy_percentage}%` : ''}
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

                {/* 2. Weak Chapters Alert */}
                <Text style={[styles.sectionHeader, { marginTop: 24 }]}>🚨 २. कमकुवत घटक अलर्ट (Weak Chapters)</Text>
                {weakList.length === 0 ? (
                    <View style={styles.emptyCard}>
                        <Text style={styles.emptyText}>उत्तम! सध्या कोणताही कमकुवत घटक नाही.</Text>
                    </View>
                ) : (
                    weakList.map(w => (
                        <View key={w.chapter_id} style={styles.weakCard}>
                            <View style={{ flex: 1 }}>
                                <Text style={styles.weakTitle}>{w.chapter_name}</Text>
                                <Text style={styles.weakMeta}>
                                    विषय: {w.subject_name} • अचूकता: <Text style={{ color: '#dc2626', fontWeight: 'bold' }}>{w.accuracy_percentage}%</Text>
                                </Text>
                            </View>
                            <TouchableOpacity
                                style={styles.btnWeakPractice}
                                onPress={() => navigation.navigate('ScholarshipChapters', {
                                    subjectId: w.subject_id || 0,
                                    subjectName: w.subject_name
                                })}
                            >
                                <Text style={styles.btnWeakPracticeText}>⚡ सराव करा</Text>
                            </TouchableOpacity>
                        </View>
                    ))
                )}

                {/* 3. Negative Basket with Dynamic Inline Clearing */}
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 24, marginBottom: 8 }}>
                    <Text style={styles.sectionHeader}>🗑️ ३. निगेटिव्ह बास्केट ({negativeCount})</Text>
                    {negativeCount > 0 && (
                        <TouchableOpacity style={styles.btnPracticeAllMistakes} onPress={practiceAllMistakes}>
                            <Text style={styles.btnPracticeAllMistakesText}>🚀 सर्व चुकांची टेस्ट द्या</Text>
                        </TouchableOpacity>
                    )}
                </View>

                {negativeBasketList.length === 0 ? (
                    <View style={styles.emptyCard}>
                        <Text style={styles.emptyText}>🎉 अभिनंदन! निगेटिव्ह बास्केट पूर्णपणे क्लिअर आहे.</Text>
                    </View>
                ) : (
                    negativeBasketList.slice(0, 5).map(item => {
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
    };

    // TAB 4: PRACTICE ZONE RENDER
    const renderPracticeZoneTab = () => {
        return (
            <ScrollView
                style={styles.tabContentScroll}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadAllData(userId, true)} />}
            >
                {/* 1. Exam Paper Generator Header Card */}
                <LinearGradient colors={['#11998e', '#38ef7d']} style={styles.practiceHeroCard}>
                    <Ionicons name="newspaper-outline" size={36} color="white" />
                    <View style={{ flex: 1, marginLeft: 12 }}>
                        <Text style={styles.practiceHeroTitle}>📝 Exam Paper Generator</Text>
                        <Text style={styles.practiceHeroSubtitle}>
                            घरी प्रिंट करण्यासाठी संपूर्ण प्रश्नपत्रिका व मॉडेल उत्तरतालिका PDF मध्ये डाऊनलोड करा!
                        </Text>
                    </View>
                </LinearGradient>

                {/* Step 1: Select Subjects */}
                <Text style={styles.sectionHeader}>१. विषय निवडा (Select Subjects):</Text>
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

                {/* Step 2: Select Chapters */}
                {selectedPracticeSubjects.length > 0 && (
                    <View style={{ marginTop: 16 }}>
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                            <Text style={styles.sectionHeader}>२. धडे निवडा (Select Chapters):</Text>
                            <TouchableOpacity onPress={selectAllPracticeChapters}>
                                <Text style={styles.selectAllText}>सर्व निवडा / हटवा</Text>
                            </TouchableOpacity>
                        </View>

                        {loadingPracticeChapters ? (
                            <ActivityIndicator color="#8E2DE2" style={{ marginVertical: 12 }} />
                        ) : (
                            <View style={styles.practiceChaptersBox}>
                                {practiceChapters.map(group => (
                                    <View key={group.subjectId} style={{ marginBottom: 12 }}>
                                        <Text style={styles.practiceGroupHeader}>📌 {group.subjectName}</Text>
                                        {group.data.map(ch => {
                                            const isChecked = selectedPracticeChapters.includes(ch.chapter_id);
                                            return (
                                                <TouchableOpacity
                                                    key={ch.chapter_id}
                                                    style={styles.practiceChapterRow}
                                                    onPress={() => togglePracticeChapter(ch.chapter_id)}
                                                >
                                                    <Ionicons
                                                        name={isChecked ? "checkbox" : "square-outline"}
                                                        size={20}
                                                        color={isChecked ? "#8E2DE2" : "#888"}
                                                    />
                                                    <Text style={[styles.practiceChapterName, isChecked && { fontWeight: '600', color: '#4A00E0' }]}>
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

                {/* Step 3: Question Limit & Actions */}
                {selectedPracticeChapters.length > 0 && (
                    <View style={{ marginTop: 20 }}>
                        <Text style={styles.sectionHeader}>३. प्रश्नांची संख्या निवडा:</Text>
                        <View style={styles.limitRow}>
                            {['15', '25', '50'].map(num => (
                                <TouchableOpacity
                                    key={num}
                                    style={[styles.limitBtn, practiceLimit === num && styles.limitBtnSelected]}
                                    onPress={() => setPracticeLimit(num)}
                                >
                                    <Text style={[styles.limitBtnText, practiceLimit === num && styles.limitBtnTextSelected]}>
                                        {num} प्रश्न
                                    </Text>
                                </TouchableOpacity>
                            ))}
                        </View>

                        {/* Action Buttons */}
                        <TouchableOpacity
                            style={styles.btnGeneratePDF}
                            onPress={generateExamPaperPDF}
                        >
                            <Ionicons name="print-outline" size={20} color="white" />
                            <Text style={styles.btnActionLargeText}>🖨️ Exam Paper PDF तयार करा</Text>
                        </TouchableOpacity>

                        <TouchableOpacity
                            style={[styles.btnGeneratePDF, { backgroundColor: '#4A00E0', marginTop: 12 }]}
                            onPress={startCustomOnlineTest}
                        >
                            <Ionicons name="play-circle-outline" size={20} color="white" />
                            <Text style={styles.btnActionLargeText}>⚡ Custom Test ऑनलाईन सुरू करा</Text>
                        </TouchableOpacity>
                    </View>
                )}
            </ScrollView>
        );
    };

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="#8E2DE2" />
            
            {/* Header */}
            <LinearGradient colors={['#8E2DE2', '#4A00E0']} style={styles.header}>
                <SafeAreaView>
                    <View style={styles.headerRow}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
                            <Ionicons name="arrow-back" size={22} color="white" />
                        </TouchableOpacity>
                        <View style={{ flex: 1 }}>
                            <Text style={styles.headerTitle}>{levelTitle}</Text>
                            <Text style={styles.headerSubtitle}>इयत्ता ५ वी व ८ वी महा-सराव</Text>
                        </View>
                        <LanguageToggle
                            selectedLanguage={selectedLanguage}
                            onLanguageChange={handleLanguageChange}
                        />
                    </View>

                    {/* 4 Core Tabs */}
                    <View style={styles.tabsRow}>
                        <TouchableOpacity
                            style={[styles.tabItem, activeTab === 'live' && styles.tabItemActive]}
                            onPress={() => setActiveTab('live')}
                        >
                            <Text style={[styles.tabItemText, activeTab === 'live' && styles.tabItemTextActive]}>
                                🔴 Live Exam
                            </Text>
                        </TouchableOpacity>

                        <TouchableOpacity
                            style={[styles.tabItem, activeTab === 'subjects' && styles.tabItemActive]}
                            onPress={() => setActiveTab('subjects')}
                        >
                            <Text style={[styles.tabItemText, activeTab === 'subjects' && styles.tabItemTextActive]}>
                                📚 विषय अभ्यास
                            </Text>
                        </TouchableOpacity>

                        <TouchableOpacity
                            style={[styles.tabItem, activeTab === 'performance' && styles.tabItemActive]}
                            onPress={() => setActiveTab('performance')}
                        >
                            <Text style={[styles.tabItemText, activeTab === 'performance' && styles.tabItemTextActive]}>
                                📊 माझी प्रगती
                            </Text>
                        </TouchableOpacity>

                        <TouchableOpacity
                            style={[styles.tabItem, activeTab === 'practice' && styles.tabItemActive]}
                            onPress={() => setActiveTab('practice')}
                        >
                            <Text style={[styles.tabItemText, activeTab === 'practice' && styles.tabItemTextActive]}>
                                🎯 Practice Zone
                            </Text>
                        </TouchableOpacity>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            {/* Body */}
            <View style={styles.mainContainer}>
                {loading ? (
                    <ActivityIndicator size="large" color="#8E2DE2" style={{ marginTop: 60 }} />
                ) : (
                    activeTab === 'live' ? renderLiveExamTab() :
                    activeTab === 'subjects' ? renderSubjectsTab() :
                    activeTab === 'performance' ? renderPerformanceTab() :
                    renderPracticeZoneTab()
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
                            <Text style={styles.modalTitle}>👑 ऑल-महाराष्ट्र लीडरबोर्ड</Text>
                            <TouchableOpacity onPress={() => setLeaderboardModalVisible(false)}>
                                <Ionicons name="close" size={24} color="#333" />
                            </TouchableOpacity>
                        </View>

                        {leaderboardLoading ? (
                            <ActivityIndicator size="large" color="#8E2DE2" style={{ marginVertical: 40 }} />
                        ) : (
                            <ScrollView style={{ maxHeight: 450 }}>
                                {selectedExamLeaderboard?.user_rank && (
                                    <View style={styles.myRankCard}>
                                        <Text style={styles.myRankTitle}>तुमची कामगिरी:</Text>
                                        <Text style={styles.myRankDetails}>
                                            रँक: #{selectedExamLeaderboard.user_rank.rank} | गुण: {selectedExamLeaderboard.user_rank.net_score} | वेळ: {selectedExamLeaderboard.user_rank.formatted_time}
                                        </Text>
                                    </View>
                                )}

                                <Text style={styles.toppersHeader}>टॉप १० अव्वल विद्यार्थी:</Text>
                                {(selectedExamLeaderboard?.top_rankers || []).map(r => (
                                    <View key={r.attempt_id} style={styles.rankRow}>
                                        <View style={styles.rankBadgeCircle}>
                                            <Text style={styles.rankBadgeText}>{r.rank}</Text>
                                        </View>
                                        <View style={{ flex: 1, marginLeft: 10 }}>
                                            <Text style={styles.rankStudentName}>{r.student_name}</Text>
                                            <Text style={styles.rankTimeSpent}>वेळ: {r.formatted_time}</Text>
                                        </View>
                                        <Text style={styles.rankScore}>{r.net_score} गुण</Text>
                                    </View>
                                ))}
                            </ScrollView>
                        )}
                    </View>
                </View>
            </Modal>
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#f8fafc' },
    header: { paddingHorizontal: 16, paddingBottom: 12 },
    headerRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 12, marginTop: Platform.OS === 'android' ? 30 : 0 },
    backBtn: { padding: 8, backgroundColor: 'rgba(255,255,255,0.2)', borderRadius: 10, marginRight: 10 },
    headerTitle: { fontSize: 18, fontWeight: 'bold', color: 'white' },
    headerSubtitle: { fontSize: 12, color: 'rgba(255,255,255,0.8)' },
    tabsRow: { flexDirection: 'row', backgroundColor: 'rgba(0,0,0,0.15)', borderRadius: 12, padding: 4 },
    tabItem: { flex: 1, paddingVertical: 8, alignItems: 'center', borderRadius: 8 },
    tabItemActive: { backgroundColor: 'white' },
    tabItemText: { fontSize: 11, fontWeight: '600', color: 'rgba(255,255,255,0.85)' },
    tabItemTextActive: { color: '#4A00E0', fontWeight: 'bold' },
    mainContainer: { flex: 1 },
    tabContentScroll: { padding: 16 },
    sectionHeader: { fontSize: 15, fontWeight: 'bold', color: '#1e293b', marginBottom: 8 },
    sectionSubtext: { fontSize: 12, color: '#64748b', marginBottom: 12 },
    emptyCard: { backgroundColor: 'white', padding: 20, borderRadius: 12, alignItems: 'center', marginVertical: 8 },
    emptyText: { color: '#94a3b8', fontSize: 13 },

    /* Spotlight Card */
    spotlightCard: { borderRadius: 16, padding: 18, marginBottom: 20, shadowColor: '#FF416C', shadowOpacity: 0.3, shadowRadius: 10, elevation: 4 },
    spotlightBadge: { alignSelf: 'flex-start', backgroundColor: 'rgba(0,0,0,0.25)', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 20, marginBottom: 8 },
    spotlightBadgeText: { color: 'white', fontSize: 11, fontWeight: 'bold' },
    spotlightTitle: { color: 'white', fontSize: 18, fontWeight: 'bold', marginBottom: 4 },
    spotlightSubtitle: { color: 'rgba(255,255,255,0.9)', fontSize: 13, marginBottom: 4 },
    spotlightTime: { color: 'rgba(255,255,255,0.8)', fontSize: 12, marginBottom: 14 },
    spotlightActions: { flexDirection: 'row', gap: 10 },
    btnStartSpotlight: { flex: 1, backgroundColor: 'white', paddingVertical: 10, borderRadius: 10, alignItems: 'center' },
    btnStartSpotlightText: { color: '#FF416C', fontWeight: 'bold', fontSize: 14 },
    btnShareSpotlight: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: '#25D366', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 10 },
    btnShareSpotlightText: { color: 'white', fontWeight: 'bold', fontSize: 13 },
    attemptedBadge: { flex: 1, backgroundColor: 'rgba(255,255,255,0.2)', padding: 10, borderRadius: 10 },
    attemptedText: { color: 'white', fontSize: 12, fontWeight: '600' },

    /* Exam Cards */
    examCard: { flexDirection: 'row', backgroundColor: 'white', padding: 16, borderRadius: 12, marginBottom: 12, alignItems: 'center', borderLeftWidth: 4, borderLeftColor: '#8E2DE2', elevation: 1 },
    examCardTitle: { fontSize: 14, fontWeight: 'bold', color: '#1e293b' },
    examCardMeta: { fontSize: 12, color: '#64748b', marginTop: 4 },
    statusDot: { width: 8, height: 8, borderRadius: 4 },
    examStatusText: { fontSize: 11, fontWeight: 'bold', color: '#64748b' },
    btnActionSmall: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 6, flexDirection: 'row', alignItems: 'center', gap: 4 },
    btnActionText: { color: 'white', fontSize: 11, fontWeight: 'bold' },
    pastExamCard: { flexDirection: 'row', backgroundColor: 'white', padding: 14, borderRadius: 12, marginBottom: 10, alignItems: 'center', elevation: 1 },
    pastExamTitle: { fontSize: 13, fontWeight: '600', color: '#1e293b' },
    rankPill: { alignSelf: 'flex-start', backgroundColor: '#f1f5f9', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6, marginTop: 4 },
    rankPillText: { fontSize: 11, color: '#4A00E0', fontWeight: 'bold' },
    notAttemptedText: { fontSize: 11, color: '#94a3b8', marginTop: 2 },
    btnLeaderboard: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#f3e8ff', paddingHorizontal: 10, paddingVertical: 6, borderRadius: 8 },
    btnLeaderboardText: { fontSize: 12, fontWeight: 'bold', color: '#4A00E0' },

    /* Subject Grid */
    subjectRow: { justifyContent: 'space-between', marginBottom: 14 },
    subjectCard: { width: '48%', height: 110, borderRadius: 16, overflow: 'hidden', elevation: 2 },
    subjectCardGradient: { flex: 1, padding: 16, justifyContent: 'space-between' },
    subjectInitial: { fontSize: 24, fontWeight: 'bold', color: 'rgba(255,255,255,0.4)' },
    subjectCardTitle: { fontSize: 15, fontWeight: 'bold', color: 'white' },
    subjectArrow: { alignSelf: 'flex-end', backgroundColor: 'rgba(255,255,255,0.2)', padding: 4, borderRadius: 12 },

    /* Performance Tab */
    readinessCard: { padding: 18, borderRadius: 16, marginBottom: 18 },
    readinessLabel: { color: 'rgba(255,255,255,0.9)', fontSize: 12, fontWeight: 'bold' },
    readinessRow: { flexDirection: 'row', alignItems: 'center', marginTop: 8 },
    readinessNumber: { fontSize: 36, fontWeight: 'bold', color: 'white' },
    readinessSub: { color: 'white', fontSize: 12 },
    accordionContainer: { backgroundColor: 'white', borderRadius: 12, marginBottom: 10, overflow: 'hidden', elevation: 1 },
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
    statusPill: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 4, backgroundColor: '#f1f5f9' },
    statusPillText: { fontSize: 10, fontWeight: 'bold', color: '#64748b' },

    /* Weak Chapters & Negative Basket */
    weakCard: { flexDirection: 'row', backgroundColor: '#fff1f2', borderLeftWidth: 4, borderLeftColor: '#f43f5e', padding: 12, borderRadius: 10, marginBottom: 10, alignItems: 'center' },
    weakTitle: { fontSize: 13, fontWeight: 'bold', color: '#9f1239' },
    weakMeta: { fontSize: 11, color: '#64748b', marginTop: 2 },
    btnWeakPractice: { backgroundColor: '#f43f5e', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 6 },
    btnWeakPracticeText: { color: 'white', fontSize: 11, fontWeight: 'bold' },
    btnPracticeAllMistakes: { backgroundColor: '#8E2DE2', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 6 },
    btnPracticeAllMistakesText: { color: 'white', fontSize: 11, fontWeight: 'bold' },
    basketQuestionCard: { backgroundColor: 'white', padding: 14, borderRadius: 12, marginBottom: 10, elevation: 1 },
    basketQText: { fontSize: 13, fontWeight: 'bold', color: '#1e293b', marginBottom: 10 },
    basketOptionsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    basketOptBtn: { width: '48%', backgroundColor: '#f8fafc', borderWidth: 1, borderColor: '#e2e8f0', padding: 8, borderRadius: 8 },
    basketOptBtnText: { fontSize: 12, color: '#334155' },

    /* Practice Zone */
    practiceHeroCard: { flexDirection: 'row', alignItems: 'center', padding: 16, borderRadius: 14, marginBottom: 18 },
    practiceHeroTitle: { color: 'white', fontSize: 16, fontWeight: 'bold' },
    practiceHeroSubtitle: { color: 'rgba(255,255,255,0.9)', fontSize: 11, marginTop: 2 },
    practiceSubjectGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    practiceSubBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: 'white', borderWidth: 1, borderColor: '#cbd5e1', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 20 },
    practiceSubBtnSelected: { backgroundColor: '#8E2DE2', borderColor: '#8E2DE2' },
    practiceSubBtnText: { fontSize: 12, color: '#475569' },
    selectAllText: { fontSize: 12, color: '#8E2DE2', fontWeight: 'bold' },
    practiceChaptersBox: { backgroundColor: 'white', borderRadius: 12, padding: 12, maxHeight: 220, borderWidth: 1, borderColor: '#e2e8f0', marginTop: 8 },
    practiceGroupHeader: { fontSize: 12, fontWeight: 'bold', color: '#64748b', marginBottom: 6 },
    practiceChapterRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 6 },
    practiceChapterName: { fontSize: 13, color: '#334155', flex: 1 },
    limitRow: { flexDirection: 'row', gap: 12, marginVertical: 10 },
    limitBtn: { flex: 1, paddingVertical: 10, borderRadius: 10, backgroundColor: 'white', borderWidth: 1, borderColor: '#cbd5e1', alignItems: 'center' },
    limitBtnSelected: { backgroundColor: '#8E2DE2', borderColor: '#8E2DE2' },
    limitBtnText: { fontSize: 13, color: '#475569', fontWeight: '600' },
    limitBtnTextSelected: { color: 'white', fontWeight: 'bold' },
    btnGeneratePDF: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: '#10b981', paddingVertical: 14, borderRadius: 12, elevation: 2 },
    btnActionLargeText: { color: 'white', fontSize: 14, fontWeight: 'bold' },

    /* Modal */
    modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center', padding: 20 },
    modalContent: { backgroundColor: 'white', borderRadius: 16, padding: 20, maxHeight: 520 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', borderBottomWidth: 1, borderBottomColor: '#eee', paddingBottom: 12, marginBottom: 12 },
    modalTitle: { fontSize: 16, fontWeight: 'bold', color: '#1e293b' },
    myRankCard: { backgroundColor: '#f3e8ff', padding: 12, borderRadius: 10, marginBottom: 14 },
    myRankTitle: { fontSize: 12, fontWeight: 'bold', color: '#4A00E0' },
    myRankDetails: { fontSize: 13, color: '#4A00E0', marginTop: 2 },
    toppersHeader: { fontSize: 13, fontWeight: 'bold', color: '#64748b', marginBottom: 8 },
    rankRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: '#f1f5f9' },
    rankBadgeCircle: { width: 28, height: 28, borderRadius: 14, backgroundColor: '#8E2DE2', justifyContent: 'center', alignItems: 'center' },
    rankBadgeText: { color: 'white', fontSize: 12, fontWeight: 'bold' },
    rankStudentName: { fontSize: 13, fontWeight: 'bold', color: '#1e293b' },
    rankTimeSpent: { fontSize: 11, color: '#94a3b8' },
    rankScore: { fontSize: 14, fontWeight: 'bold', color: '#10b981' }
});

export default ScholarshipSubjectsScreen;
