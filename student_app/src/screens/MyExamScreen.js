import React, { useState, useEffect } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    TextInput,
    ActivityIndicator,
    Alert,
    StatusBar,
    Platform
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { fetchSubjects } from '../api/subjects';
import { fetchChapters } from '../api/chapters';
import axios from 'axios';
import { API_URL } from '../api/config';

const MyExamScreen = ({ navigation, route, user }) => {
    const classId = user?.class_id;

    const [subjects, setSubjects] = useState([]);
    const [chapters, setChapters] = useState([]); // Array of { subjectName, data: [] }
    const [selectedSubjects, setSelectedSubjects] = useState([]);
    const [selectedChapters, setSelectedChapters] = useState([]);
    const [questionLimit, setQuestionLimit] = useState('25');
    const [loading, setLoading] = useState(false);
    const [loadingChapters, setLoadingChapters] = useState(false);

    useEffect(() => {
        loadSubjects();
    }, []);

    useEffect(() => {
        if (selectedSubjects.length > 0) {
            loadChapters();
        } else {
            setChapters([]);
            setSelectedChapters([]);
        }
    }, [selectedSubjects]);

    const loadSubjects = async () => {
        setLoading(true);
        try {
            const response = await fetchSubjects(classId);
            if (response.status === 'success') {
                setSubjects(response.data);
            } else {
                Alert.alert('Error', response.message);
            }
        } catch (error) {
            Alert.alert('Error', 'Failed to load subjects');
        } finally {
            setLoading(false);
        }
    };

    const loadChapters = async () => {
        setLoadingChapters(true);
        // Don't clear selected chapters immediately if we want to preserve across additions,
        // but for safety, let's filter out ones that might belong to deselected subjects later.
        // For now, simpler to just re-validate or clear. Let's keep valid ones.

        try {
            // Fetch chapters for all selected subjects in parallel
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

            // Filter out empty results and format for SectionList-style rendering (or just mapped views)
            const groupedChapters = results.filter(r => r.data.length > 0);
            setChapters(groupedChapters);

            // Clean up selected chapters that are no longer visible
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
                    subjectName: selectedSubjects.map(s => s.subject_name).join(' & ') // Combine names
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

    return (
        <View style={styles.mainWrapper}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />

            <LinearGradient colors={['#00c6ff', '#0072ff']} style={styles.headerGradient}>
                <SafeAreaView edges={['top']} style={styles.headerSafe}>
                    <View style={styles.header}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Text style={styles.backButtonText}>←</Text>
                        </TouchableOpacity>
                        <View style={styles.headerTextContainer}>
                            <Text style={styles.headerTitle}>My Exam</Text>
                            <Text style={styles.headerSubtitle}>Create Your Custom Test</Text>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <ScrollView style={styles.container} contentContainerStyle={styles.scrollContent}>
                {/* Subject Selection */}
                <View style={styles.section}>
                    <Text style={styles.sectionTitle}>1. Select Subjects</Text>
                    {loading ? (
                        <ActivityIndicator size="large" color="#0072ff" style={styles.loader} />
                    ) : (
                        <View style={styles.subjectGrid}>
                            {subjects.map((subject, index) => {
                                const gradients = [
                                    ['#FF9A9E', '#FECFEF'],
                                    ['#a18cd1', '#fbc2eb'],
                                    ['#84fab0', '#8fd3f4'],
                                    ['#a6c0fe', '#f68084'],
                                    ['#ffecd2', '#fcb69f'],
                                    ['#ff9a9e', '#fad0c4']
                                ];
                                const colors = gradients[index % gradients.length];
                                const isSelected = selectedSubjects.some(s => s.subject_id === subject.subject_id);

                                return (
                                    <TouchableOpacity
                                        key={subject.subject_id}
                                        style={[styles.subjectCard, isSelected && styles.subjectCardSelected]}
                                        onPress={() => toggleSubject(subject)}
                                        activeOpacity={0.7}
                                    >
                                        <LinearGradient
                                            colors={isSelected ? ['#0072ff', '#00c6ff'] : colors}
                                            style={styles.subjectGradient}
                                        >
                                            <Text style={styles.subjectIcon}>{subject.subject_name.charAt(0)}</Text>
                                            <Text style={styles.subjectName}>{subject.subject_name}</Text>
                                            {isSelected && (
                                                <View style={styles.selectedBadge}>
                                                    <Text style={styles.selectedBadgeText}>✓</Text>
                                                </View>
                                            )}
                                        </LinearGradient>
                                    </TouchableOpacity>
                                );
                            })}
                        </View>
                    )}
                </View>

                {/* Chapter Selection */}
                {selectedSubjects.length > 0 && (
                    <View style={styles.section}>
                        <View style={styles.sectionHeader}>
                            <Text style={styles.sectionTitle}>2. Select Chapters</Text>
                            <TouchableOpacity onPress={selectAllChapters} style={styles.selectAllButton}>
                                <Text style={styles.selectAllText}>
                                    Select All / Deselect All
                                </Text>
                            </TouchableOpacity>
                        </View>
                        {loadingChapters ? (
                            <ActivityIndicator size="large" color="#0072ff" style={styles.loader} />
                        ) : (
                            <View style={styles.chapterList}>
                                {chapters.map((group) => (
                                    <View key={group.subjectId} style={styles.groupContainer}>
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
                                        })}
                                    </View>
                                ))}
                                {chapters.length === 0 && !loadingChapters && (
                                    <Text style={styles.noDataText}>No chapters found for selected subjects.</Text>
                                )}
                            </View>
                        )}
                    </View>
                )}

                {/* Question Limit */}
                {selectedChapters.length > 0 && (
                    <View style={styles.section}>
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
            </ScrollView>
        </View>
    );
};

const styles = StyleSheet.create({
    mainWrapper: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    headerGradient: {
        paddingBottom: 20,
    },
    headerSafe: {
        backgroundColor: 'transparent',
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        paddingBottom: 10,
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
        fontSize: 24,
        fontWeight: 'bold',
        color: 'white',
    },
    headerSubtitle: {
        fontSize: 14,
        color: 'rgba(255,255,255,0.9)',
        marginTop: 2,
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
        borderRadius: 16,
        overflow: 'hidden',
        elevation: 4,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.15,
        shadowRadius: 8,
    },
    subjectCardSelected: {
        elevation: 8,
        shadowOpacity: 0.3,
        transform: [{ scale: 1.02 }],
    },
    subjectGradient: {
        padding: 24,
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: 120,
    },
    subjectIcon: {
        fontSize: 36,
        marginBottom: 8,
    },
    subjectName: {
        fontSize: 15,
        fontWeight: 'bold',
        color: 'white',
        textAlign: 'center',
        textShadowColor: 'rgba(0, 0, 0, 0.2)',
        textShadowOffset: { width: 0, height: 1 },
        textShadowRadius: 2,
    },
    chapterList: {
        gap: 10,
    },
    chapterItem: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'white',
        borderRadius: 12,
        padding: 16,
        borderWidth: 1,
        borderColor: '#e2e8f0',
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
        top: 8,
        right: 8,
        width: 24,
        height: 24,
        borderRadius: 12,
        backgroundColor: 'rgba(255, 255, 255, 0.3)',
        justifyContent: 'center',
        alignItems: 'center',
    },
    selectedBadgeText: {
        color: 'white',
        fontWeight: 'bold',
        fontSize: 14,
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
});

export default MyExamScreen;
