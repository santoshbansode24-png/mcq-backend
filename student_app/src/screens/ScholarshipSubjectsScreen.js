import React, { useState, useEffect } from 'react';
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
    TextInput
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';
import { useNavigation } from '@react-navigation/native';
import { fetchSubjects } from '../api/subjects';
import { fetchChapters } from '../api/chapters';
import axios from 'axios';
import { API_URL } from '../api/config';
import { Alert } from 'react-native';

// Hardcoded ID for the Scholarship Class created in DB
const SCHOLARSHIP_CLASS_ID = 37;

const ScholarshipSubjectsScreen = ({ navigation, route }) => {
    // Get dynamic class ID and Title from route params, fallback to default (e.g. Primary)
    // Default to 38 (Primary) if not provided
    const scholarshipClassId = route.params?.scholarshipClassId || 38;
    const levelTitle = route.params?.levelTitle || "Scholarship & Olympiad";
    // const navigation = useNavigation(); // Using prop instead
    const [subjects, setSubjects] = useState([]);
    const [mockTests, setMockTests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('subjects'); // 'subjects' or 'mocks'

    // Custom Test Logic State
    const [chapters, setChapters] = useState([]);
    const [selectedSubjects, setSelectedSubjects] = useState([]);
    const [selectedChapters, setSelectedChapters] = useState([]);
    const [questionLimit, setQuestionLimit] = useState('25');
    const [loadingChapters, setLoadingChapters] = useState(false);

    useEffect(() => {
        loadSubjects();
    }, []);

    // Effect to load chapters when selected subjects change
    useEffect(() => {
        if (selectedSubjects.length > 0) {
            loadChapters();
        } else {
            setChapters([]);
            setSelectedChapters([]);
        }
    }, [selectedSubjects]);

    const loadSubjects = async () => {
        try {
            // Force refresh to bypass stale cache from previous environment
            const response = await fetchSubjects(scholarshipClassId, true);
            if (response.status === 'success') {
                const allData = response.data;

                // Separate "Mock Tests" from regular subjects
                const mocks = allData.filter(s => s.subject_name.includes("Mock Test") || s.subject_name.includes("Previous Paper"));
                const regular = allData.filter(s => !s.subject_name.includes("Mock Test") && !s.subject_name.includes("Previous Paper"));

                setSubjects(regular);
                setMockTests(mocks);
            }
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
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

            // Maintain selected chapters logic if needed, or clear invalid ones
            const allVisibleChapterIds = groupedChapters.flatMap(g => g.data.map(c => c.chapter_id));
            setSelectedChapters(prev => prev.filter(id => allVisibleChapterIds.includes(id)));

        } catch (error) {
            Alert.alert('Error', 'Failed to load chapters');
            console.error(error);
        } finally {
            setLoadingChapters(false);
        }
    };

    const toggleSubject = (subject) => {
        setSelectedSubjects(prev => {
            const exists = prev.find(s => s.subject_id === subject.subject_id);
            if (exists) {
                return prev.filter(s => s.subject_id !== subject.subject_id);
            } else {
                return [...prev, subject];
            }
        });
    };

    const toggleChapter = (chapterId) => {
        setSelectedChapters(prev => {
            if (prev.includes(chapterId)) {
                return prev.filter(id => id !== chapterId);
            } else {
                return [...prev, chapterId];
            }
        });
    };

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
            console.error(error);
            Alert.alert('Error', 'Failed to generate test. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const generatePDF = async () => {
        if (selectedSubjects.length === 0) {
            Alert.alert('Error', 'Please select at least one subject');
            return;
        }
        if (selectedChapters.length === 0) {
            Alert.alert('Error', 'Please select at least one chapter');
            return;
        }
        const limit = parseInt(questionLimit);

        setLoading(true);
        try {
            // Fetch questions (reuse same API)
            const response = await axios.post(`${API_URL}/generate_custom_test.php`, {
                chapter_ids: selectedChapters.join(','),
                limit: limit
            });

            if (response.data.status === 'success') {
                const questions = response.data.data;
                const date = new Date().toLocaleDateString();

                // Generate HTML for PDF
                const html = `
                    <html>
                        <head>
                            <style>
                                body { font-family: 'Helvetica', sans-serif; padding: 40px; }
                                .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #8E2DE2; padding-bottom: 20px; }
                                .title { font-size: 24px; font-weight: bold; color: #4A00E0; margin: 0; }
                                .subtitle { font-size: 16px; color: #666; margin-top: 5px; }
                                .meta { margin-top: 10px; font-size: 14px; color: #333; }
                                .question-container { margin-bottom: 25px; page-break-inside: avoid; }
                                .question-text { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
                                .options { margin-left: 20px; }
                                .option { margin-bottom: 5px; font-size: 14px; }
                                .page-footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 12px; color: #aaa; }
                                .answer-key { page-break-before: always; }
                                .key-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                                .key-table th, .key-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                                .key-table th { background-color: #f3f3f3; font-weight: bold; }
                                .correct-opt { font-weight: bold; color: #4A00E0; }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <h1 class="title">Veeru Learning App</h1>
                                <div class="subtitle">Scholarship & Olympiad Mock Test</div>
                                <div class="meta">Date: ${date} | Questions: ${questions.length}</div>
                            </div>

                            <div class="questions">
                                ${questions.map((q, index) => `
                                    <div class="question-container">
                                        <div class="question-text">Q${index + 1}. ${q.question}</div>
                                        <div class="options">
                                            <div class="option">A) ${q.option_a}</div>
                                            <div class="option">B) ${q.option_b}</div>
                                            <div class="option">C) ${q.option_c}</div>
                                            <div class="option">D) ${q.option_d}</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>

                            <div class="answer-key">
                                <h2 style="color: #4A00E0;">Answer Key & Explanations</h2>
                                <table class="key-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 10%;">Q.No</th>
                                            <th style="width: 15%;">Answer</th>
                                            <th>Explanation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${questions.map((q, index) => `
                                            <tr>
                                                <td>${index + 1}</td>
                                                <td class="correct-opt">${q.correct_answer.toUpperCase()}</td>
                                                <td>${q.explanation || 'No explanation available.'}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </body>
                    </html>
                `;

                // Generate PDF
                const { uri } = await Print.printToFileAsync({ html });
                await Sharing.shareAsync(uri);

            } else {
                Alert.alert('Error', response.data.message || 'Failed to generate PDF');
            }
        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Failed to generate PDF. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const renderItem = ({ item, index }) => {
        const gradients = [
            ['#DA22FF', '#9733EE'], // Magenta -> Purple
            ['#EA384D', '#D31027'], // Red -> Dark Red (Premium)
            ['#00C6FF', '#0072FF'], // Cyan -> Blue
            ['#F2994A', '#F2C94C'], // Orange -> Gold
            ['#11998e', '#38ef7d']  // Green (emerald)
        ];
        const colors = gradients[index % gradients.length];

        return (
            <TouchableOpacity
                style={styles.card}
                onPress={() => navigation.navigate('ScholarshipChapters', {
                    subjectId: item.subject_id,
                    subjectName: item.subject_name
                })}
            >
                <LinearGradient colors={colors} style={styles.cardGradient}>
                    <Text style={styles.icon}>{item.subject_name.charAt(0)}</Text>
                    <Text style={styles.cardTitle}>{item.subject_name}</Text>
                    <View style={styles.arrowContainer}>
                        <Ionicons name="arrow-forward" size={20} color="white" />
                    </View>
                </LinearGradient>
            </TouchableOpacity>
        );
    };

    const renderMockItem = ({ item }) => {
        return (
            <TouchableOpacity
                style={styles.mockCard}
                onPress={() => navigation.navigate('ScholarshipChapters', {
                    subjectId: item.subject_id,
                    subjectName: item.subject_name
                })}
            >
                <View style={styles.mockIconContainer}>
                    <Ionicons name="trophy-outline" size={32} color="#8E2DE2" />
                </View>
                <View style={styles.mockContent}>
                    <Text style={styles.mockTitle}>Full Length Mock Tests</Text>
                    <Text style={styles.mockSubtitle}>Practice with real exam timer</Text>
                </View>
                <Ionicons name="chevron-forward" size={24} color="#CBD5E1" />
            </TouchableOpacity>
        );
    };

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />
            <LinearGradient colors={['#8E2DE2', '#4A00E0']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.header}>
                <SafeAreaView style={styles.safeArea}>
                    <View style={styles.headerContent}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Ionicons name="arrow-back" size={24} color="white" />
                        </TouchableOpacity>
                        <View>
                            <Text style={styles.headerTitle}>{levelTitle}</Text>
                            <Text style={styles.headerSubtitle}>Prepare for Excellence</Text>
                        </View>
                    </View>

                    {/* Tabs */}
                    <View style={styles.tabContainer}>
                        <TouchableOpacity
                            style={[styles.tabButton, activeTab === 'subjects' && styles.activeTab]}
                            onPress={() => setActiveTab('subjects')}
                        >
                            <Text style={[styles.tabText, activeTab === 'subjects' && styles.activeTabText]}>Subjects</Text>
                        </TouchableOpacity>
                        <TouchableOpacity
                            style={[styles.tabButton, activeTab === 'mocks' && styles.activeTab]}
                            onPress={() => setActiveTab('mocks')}
                        >
                            <Text style={[styles.tabText, activeTab === 'mocks' && styles.activeTabText]}>Mock Tests</Text>
                        </TouchableOpacity>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <View style={styles.content}>
                {loading ? (
                    <ActivityIndicator size="large" color="#FF5E62" style={{ marginTop: 50 }} />
                ) : (
                    activeTab === 'subjects' ? (
                        <FlatList
                            key="subjects-list"
                            data={subjects}
                            renderItem={renderItem}
                            keyExtractor={item => item.subject_id.toString()}
                            numColumns={2}
                            columnWrapperStyle={styles.row}
                            contentContainerStyle={styles.listContent}
                            ListEmptyComponent={
                                <Text style={styles.emptyText}>No subjects found.</Text>
                            }
                        />
                    ) : (
                        <ScrollView style={styles.scrollContainer} contentContainerStyle={styles.scrollContent}>
                            {/* Subject Selection - Checkbox Style or Grid */}
                            <Text style={styles.sectionTitle}>1. Select Subjects</Text>
                            <View style={styles.subjectGrid}>
                                {subjects.map((subject, index) => {
                                    const gradients = [
                                        ['#DA22FF', '#9733EE'], // Magenta -> Purple
                                        ['#EA384D', '#D31027'], // Red -> Dark Red (Premium)
                                        ['#00C6FF', '#0072FF'], // Cyan -> Blue
                                        ['#F2994A', '#F2C94C'], // Orange -> Gold
                                        ['#11998e', '#38ef7d']  // Green (emerald)
                                    ];
                                    const colors = gradients[index % gradients.length];
                                    const isSelected = selectedSubjects.some(s => s.subject_id === subject.subject_id);

                                    return (
                                        <TouchableOpacity
                                            key={subject.subject_id}
                                            style={[styles.subjectMiniCard, isSelected && styles.subjectCardSelected]}
                                            onPress={() => toggleSubject(subject)}
                                        >
                                            <LinearGradient
                                                colors={colors}
                                                style={styles.subjectMiniGradient}
                                            >
                                                <Text style={[styles.subjectName, isSelected && { color: 'white' }]}>{subject.subject_name}</Text>
                                                {isSelected && <Ionicons name="checkmark-circle" size={20} color="white" style={styles.checkIcon} />}
                                            </LinearGradient>
                                        </TouchableOpacity>
                                    );
                                })}
                            </View>

                            {/* Chapter Selection */}
                            {selectedSubjects.length > 0 && (
                                <View style={styles.section}>
                                    <View style={styles.sectionHeader}>
                                        <Text style={styles.sectionTitle}>2. Select Chapters</Text>
                                        <TouchableOpacity onPress={selectAllChapters}>
                                            <Text style={styles.selectAllText}>Select All / Deselect</Text>
                                        </TouchableOpacity>
                                    </View>

                                    {loadingChapters ? (
                                        <ActivityIndicator color="#FF5E62" />
                                    ) : (
                                        <View style={styles.chapterList}>
                                            {chapters.map((group) => (
                                                <View key={group.subjectId}>
                                                    <Text style={styles.groupHeader}>{group.subjectName}</Text>
                                                    {group.data.map((chapter) => {
                                                        const isSelected = selectedChapters.includes(chapter.chapter_id);
                                                        return (
                                                            <TouchableOpacity
                                                                key={chapter.chapter_id}
                                                                style={[styles.chapterItem, isSelected && styles.chapterItemSelected]}
                                                                onPress={() => toggleChapter(chapter.chapter_id)}
                                                            >
                                                                <View style={[styles.checkbox, isSelected && styles.checkboxSelected]}>
                                                                    {isSelected && <Text style={styles.checkmark}>✓</Text>}
                                                                </View>
                                                                <View style={{ flex: 1 }}>
                                                                    <Text style={[styles.chapterText, isSelected && styles.chapterTextSelected]}>{chapter.chapter_name}</Text>
                                                                    <Text style={styles.mcqCount}>{chapter.total_mcqs || 0} MCQs</Text>
                                                                </View>
                                                            </TouchableOpacity>
                                                        );
                                                    })}
                                                </View>
                                            ))}
                                        </View>
                                    )}
                                </View>
                            )}

                            {/* Limit & Button */}
                            {selectedChapters.length > 0 && (
                                <View style={styles.section}>
                                    <Text style={styles.sectionTitle}>3. Question Count</Text>
                                    <View style={styles.limitContainer}>
                                        {['10', '25', '50'].map((num) => (
                                            <TouchableOpacity
                                                key={num}
                                                style={[styles.limitButton, questionLimit === num && styles.limitButtonSelected]}
                                                onPress={() => setQuestionLimit(num)}
                                            >
                                                <Text style={[styles.limitText, questionLimit === num && styles.limitTextSelected]}>{num}</Text>
                                            </TouchableOpacity>
                                        ))}
                                    </View>

                                    <TouchableOpacity
                                        style={styles.startButton}
                                        onPress={startTest}
                                        disabled={loading}
                                    >
                                        <LinearGradient colors={['#8E2DE2', '#4A00E0']} style={styles.startGradient}>
                                            {loading ? <ActivityIndicator color="white" /> : (
                                                <Text style={styles.startButtonText}>Start My Test</Text>
                                            )}
                                        </LinearGradient>
                                    </TouchableOpacity>

                                    <TouchableOpacity
                                        style={[styles.startButton, { marginTop: 15 }]}
                                        onPress={generatePDF}
                                        disabled={loading}
                                    >
                                        <LinearGradient colors={['#11998e', '#38ef7d']} style={styles.startGradient}>
                                            {loading ? <ActivityIndicator color="white" /> : (
                                                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                                                    <Ionicons name="document-text-outline" size={24} color="white" />
                                                    <Text style={styles.startButtonText}>Download PDF</Text>
                                                </View>
                                            )}
                                        </LinearGradient>
                                    </TouchableOpacity>
                                </View>
                            )}
                        </ScrollView>
                    )
                )}
            </View>
        </View >
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    header: {
        paddingBottom: 15,
        borderBottomLeftRadius: 30,
        borderBottomRightRadius: 30,
    },
    safeArea: {
        backgroundColor: 'transparent',
    },
    headerContent: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        marginTop: Platform.OS === 'android' ? 40 : 0,
        marginBottom: 20,
    },
    backButton: {
        padding: 8,
        backgroundColor: 'rgba(255,255,255,0.2)',
        borderRadius: 12,
        marginRight: 15,
    },
    headerTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        color: 'white',
    },
    headerSubtitle: {
        fontSize: 14,
        color: 'rgba(255,255,255,0.9)',
    },
    tabContainer: {
        flexDirection: 'row',
        marginHorizontal: 20,
        backgroundColor: 'rgba(255,255,255,0.2)',
        borderRadius: 15,
        padding: 4,
    },
    tabButton: {
        flex: 1,
        paddingVertical: 10,
        alignItems: 'center',
        borderRadius: 12,
    },
    activeTab: {
        backgroundColor: 'white',
    },
    tabText: {
        color: 'rgba(255,255,255,0.8)',
        fontWeight: '600',
        fontSize: 14,
    },
    activeTabText: {
        color: '#8E2DE2',
        fontWeight: 'bold',
    },
    content: {
        flex: 1,
        marginTop: 10,
    },
    listContent: {
        padding: 15,
    },
    row: {
        justifyContent: 'space-between',
    },
    card: {
        width: '48%',
        marginBottom: 15,
        borderRadius: 20,
        elevation: 4,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
        overflow: 'hidden',
    },
    cardGradient: {
        padding: 20,
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: 140,
    },
    icon: {
        fontSize: 40,
        fontWeight: 'bold',
        color: 'white',
        marginBottom: 10,
        textShadowColor: 'rgba(0,0,0,0.1)',
        textShadowOffset: { width: 0, height: 2 },
        textShadowRadius: 4,
    },
    cardTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        color: 'white',
        textAlign: 'center',
        marginBottom: 10,
    },
    arrowContainer: {
        backgroundColor: 'rgba(255,255,255,0.2)',
        padding: 8,
        borderRadius: 20,
    },
    mockCard: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'white',
        padding: 20,
        borderRadius: 16,
        marginBottom: 15,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
    },
    mockIconContainer: {
        width: 50,
        height: 50,
        borderRadius: 25,
        backgroundColor: '#FFF1F2',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 15,
    },
    mockContent: {
        flex: 1,
    },
    mockTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#1E293B',
        marginBottom: 4,
    },
    mockSubtitle: {
        fontSize: 13,
        color: '#64748B',
    },
    emptyContainer: {
        alignItems: 'center',
        marginTop: 50,
        opacity: 0.8
    },
    scrollContainer: {
        flex: 1,
    },
    scrollContent: {
        padding: 20,
        paddingBottom: 40,
    },
    sectionTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#1e293b',
        marginBottom: 15,
        marginTop: 10,
    },
    subjectGrid: {
        flexDirection: 'row',
        flexWrap: 'wrap',
        gap: 10,
    },
    subjectMiniCard: {
        width: '48%',
        borderRadius: 12,
        overflow: 'hidden',
        height: 80,
        borderWidth: 2,
        borderColor: 'transparent', // Prevent layout shift
    },
    subjectMiniGradient: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        padding: 10,
    },
    subjectCardSelected: {
        borderColor: '#8E2DE2',
        // transform: [{ scale: 0.98 }] // Removed to prevent layout glitch
    },
    subjectName: {
        fontSize: 14,
        fontWeight: 'bold',
        textAlign: 'center',
        color: '#1e293b',
    },
    checkIcon: {
        position: 'absolute',
        top: 5,
        right: 5,
    },
    sectionHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 10,
        marginTop: 20,
    },
    selectAllText: {
        color: '#FF5E62',
        fontWeight: '600',
    },
    checkbox: {
        width: 24,
        height: 24,
        borderRadius: 6,
        borderWidth: 2,
        borderColor: '#cbd5e1',
        marginRight: 10,
        justifyContent: 'center',
        alignItems: 'center',
    },
    checkboxSelected: {
        borderColor: '#8E2DE2',
        backgroundColor: '#8E2DE2',
    },
    checkmark: {
        color: 'white',
        fontWeight: 'bold',
        fontSize: 16,
    },
    chapterItem: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'white',
        padding: 15,
        borderRadius: 12,
        marginBottom: 8,
        borderWidth: 1,
        borderColor: '#f1f5f9',
    },
    chapterItemSelected: {
        borderColor: '#8E2DE2',
        backgroundColor: '#F3E5F5',
    },
    chapterText: {
        fontSize: 14,
        fontWeight: '600',
        color: '#334155',
    },
    chapterTextSelected: {
        color: '#8E2DE2',
    },
    mcqCount: {
        fontSize: 12,
        color: '#94a3b8',
    },
    groupHeader: {
        fontSize: 12,
        color: '#64748b',
        marginBottom: 5,
        fontWeight: 'bold',
        textTransform: 'uppercase',
    },
    limitContainer: {
        flexDirection: 'row',
        gap: 10,
        marginBottom: 20,
    },
    limitButton: {
        flex: 1,
        padding: 15,
        backgroundColor: 'white',
        alignItems: 'center',
        borderRadius: 12,
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    limitButtonSelected: {
        borderColor: '#8E2DE2',
        backgroundColor: '#F3E5F5',
    },
    limitText: {
        fontWeight: 'bold',
        color: '#64748b',
    },
    limitTextSelected: {
        color: '#8E2DE2',
    },
    startButton: {
        borderRadius: 16,
        overflow: 'hidden',
        marginTop: 10,
    },
    startGradient: {
        padding: 18,
        alignItems: 'center',
    },
    startButtonText: {
        color: 'white',
        fontSize: 18,
        fontWeight: 'bold',
    },
    pastPapersLink: {
        marginTop: 30,
        flexDirection: 'row',
        justifyContent: 'center',
        alignItems: 'center',
        gap: 5,
        padding: 15,
    },
    pastPapersText: {
        color: '#475569',
        fontWeight: '600',
    }
});

export default ScholarshipSubjectsScreen;
