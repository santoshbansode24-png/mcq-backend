import React, { useState, useEffect } from 'react';
import {
    View, Text, StyleSheet, ScrollView, TouchableOpacity,
    ActivityIndicator, Dimensions, Alert, StatusBar
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import config from '../api/config';
import axios from 'axios';
import DateTimePicker from '@react-native-community/datetimepicker';

const { width } = Dimensions.get('window');

const StudyPlannerScreen = ({ user, navigation }) => {
    const { theme } = useTheme();

    // --- State ---
    const [loading, setLoading] = useState(true);
    const [isConfigured, setIsConfigured] = useState(false);
    const [wizardStep, setWizardStep] = useState(1);
    const [roadmap, setRoadmap] = useState([]);

    // Form Data
    const [examDate, setExamDate] = useState(new Date(Date.now() + 30 * 24 * 60 * 60 * 1000));
    const [showDatePicker, setShowDatePicker] = useState(false);
    
    // Selection State
    const [availableSubjects, setAvailableSubjects] = useState([]);
    const [selectedSubjects, setSelectedSubjects] = useState([]); // Track subject IDs
    const [allChapters, setAllChapters] = useState([]); // Flat list of all chapters for current subjects
    const [selectedChapters, setSelectedChapters] = useState([]); // Track EXACT chapter IDs to include
    const [loadingChapters, setLoadingChapters] = useState(false);

    useEffect(() => {
        checkExistingPlan();
        fetchSyllabusInfo();
    }, [user]);

    const checkExistingPlan = async () => {
        try {
            const res = await axios.get(`${config.API_URL}/get_study_status.php?user_id=${user.user_id}`);
            if (res.data.status === 'success' && res.data.is_configured) {
                setIsConfigured(true);
                setExamDate(new Date(res.data.exam_date));
                fetchRoadmap();
            }
        } catch (error) {
            console.log("No existing plan found");
        } finally {
            setLoading(false);
        }
    };

    const fetchRoadmap = async () => {
        try {
            const res = await axios.get(`${config.API_URL}/get_roadmap.php?user_id=${user.user_id}`);
            if (res.data.status === 'success') {
                setRoadmap(res.data.data);
            }
        } catch (error) {
            console.error(error);
        }
    };

    const fetchSyllabusInfo = async () => {
        try {
            const response = await axios.get(`${config.API_URL}/get_subjects.php?class_id=${user.class_id}`);
            if (response.data.status === 'success') {
                setAvailableSubjects(response.data.data);
            }
        } catch (error) {
            console.log("Error fetching subjects", error);
        }
    };

    // When subjects change, fetch their chapters
    useEffect(() => {
        if (selectedSubjects.length > 0) {
            fetchSelectedChapters();
        } else {
            setAllChapters([]);
            setSelectedChapters([]);
        }
    }, [selectedSubjects]);

    const fetchSelectedChapters = async () => {
        setLoadingChapters(true);
        try {
            const chapterPromises = selectedSubjects.map(sid => 
                axios.get(`${config.API_URL}/get_chapters.php?subject_id=${sid}`)
            );
            const results = await Promise.all(chapterPromises);
            
            let combinedChapters = [];
            results.forEach((res, index) => {
                if (res.data.status === 'success') {
                    const subjectName = availableSubjects.find(s => s.subject_id === selectedSubjects[index])?.subject_name;
                    const chaptersWithMeta = res.data.data.map(ch => ({ 
                        ...ch, 
                        subject_name: subjectName 
                    }));
                    combinedChapters = [...combinedChapters, ...chaptersWithMeta];
                }
            });
            
            setAllChapters(combinedChapters);
            // Default select ALL chapters when a subject is added
            const newChapterIds = combinedChapters.map(c => c.chapter_id);
            setSelectedChapters(newChapterIds);
            
        } catch (error) {
            console.error("Error fetching chapters", error);
        } finally {
            setLoadingChapters(false);
        }
    };

    const handleDateChange = (event, selectedDate) => {
        setShowDatePicker(false);
        if (selectedDate) setExamDate(selectedDate);
    };

    const toggleSubject = (id) => {
        if (selectedSubjects.includes(id)) {
            setSelectedSubjects(selectedSubjects.filter(sid => sid !== id));
        } else {
            setSelectedSubjects([...selectedSubjects, id]);
        }
    };

    const toggleChapter = (id) => {
        if (selectedChapters.includes(id)) {
            setSelectedChapters(selectedChapters.filter(cid => cid !== id));
        } else {
            setSelectedChapters([...selectedChapters, id]);
        }
    };

    const savePlan = async () => {
        if (selectedChapters.length === 0) {
            Alert.alert("Selection Required", "Please select at least one chapter to build your roadmap.");
            return;
        }
        setLoading(true);
        try {
            // Updated setup_syllabus_path.php will need to handle array of chapter_ids
            const res = await axios.post(`${config.API_URL}/setup_syllabus_path.php`, {
                user_id: user.user_id,
                exam_date: examDate.toISOString().split('T')[0],
                subject_ids: selectedSubjects,
                chapter_ids: selectedChapters // We send the specific chapters chosen
            });
            if (res.data.status === 'success') {
                setIsConfigured(true);
                fetchRoadmap();
            }
        } catch (error) {
            Alert.alert("Error", "Failed to save plan.");
        } finally {
            setLoading(false);
        }
    };

    const handleTaskPress = (task) => {
        if (task.status === 'completed') {
            Alert.alert("Goal Achieved", "You have already mastered this session!");
            return;
        }
        
        const chapterData = {
            chapter_id: task.chapter_id,
            chapter_name: task.title.split(': ').pop(),
            subject_name: task.subject
        };

        if (task.task_type === 'quiz') {
            navigation.navigate('ChapterContent', { chapter: chapterData, initialTab: 'MCQs' });
        } else if (task.task_type === 'video') {
            navigation.navigate('ChapterContent', { chapter: chapterData, initialTab: 'Videos' });
        } else if (task.task_type === 'flashcard') {
            navigation.navigate('ChapterContent', { chapter: chapterData, initialTab: 'Flashcards' });
        } else {
            navigation.navigate('ChapterContent', { chapter: chapterData, initialTab: 'Notes' });
        }
    };

    const getTaskStyle = (type, isCompleted) => {
        if (isCompleted) return { color: '#4CAF50', icon: 'checkmark-circle', label: 'Done' };
        switch (type) {
            case 'video': return { color: '#6200EA', icon: 'play-circle-outline', label: 'Video' };
            case 'quiz': return { color: '#E65100', icon: 'medal-outline', label: 'Quiz' };
            case 'notes': return { color: '#0091EA', icon: 'document-text-outline', label: 'Notes' };
            case 'flashcard': return { color: '#D500F9', icon: 'layers-outline', label: 'Cards' };
            default: return { color: '#455A64', icon: 'star-outline', label: 'Goal' };
        }
    };

    if (loading) return <View style={styles.centered}><ActivityIndicator size="large" color="#4F46E5" /></View>;

    if (isConfigured) {
        return (
            <View style={styles.container}>
                <LinearGradient colors={['#1A237E', '#4F46E5']} style={styles.modernHeader}>
                    <View style={styles.headerTop}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}><Ionicons name="arrow-back" size={26} color="white" /></TouchableOpacity>
                        <View style={styles.headerInfoText}>
                            <Text style={styles.headerTitle}>Victory Pipeline</Text>
                            <Text style={styles.headerSub}>Deadline: {examDate.toLocaleDateString()}</Text>
                        </View>
                        <TouchableOpacity onPress={() => setIsConfigured(false)} style={styles.resetHeaderBtn}><Ionicons name="refresh-circle" size={32} color="white" /></TouchableOpacity>
                    </View>
                </LinearGradient>

                <ScrollView style={styles.timelineBody} showsVerticalScrollIndicator={false}>
                    {roadmap.map((day) => (
                        <View key={day.date} style={styles.dayBlock}>
                            <View style={styles.dayLabelRow}>
                                <View style={[styles.dayBadge, day.is_today && styles.todayBadge]}><Text style={[styles.dayBadgeText, day.is_today && styles.todayBadgeText]}>{day.is_today ? 'TODAY' : day.display_date.split(',')[0].toUpperCase()}</Text></View>
                                <Text style={styles.dayFullDate}>{day.display_date.split(',')[1]}</Text>
                            </View>
                            <View style={styles.verticalLink} />
                            <View style={styles.dayTasks}>
                                {day.tasks.map(task => {
                                    const isDone = task.status === 'completed';
                                    const style = getTaskStyle(task.task_type, isDone);
                                    return (
                                        <TouchableOpacity key={task.task_id} style={[styles.smartTile, { borderLeftColor: style.color }, isDone && styles.completedTile]} onPress={() => handleTaskPress(task)}>
                                            <View style={styles.tileLeft}>
                                                <Ionicons name={style.icon} size={24} color={style.color} />
                                                <View style={styles.tileTextContainer}>
                                                    <Text style={[styles.tileTag, { color: style.color }]}>{style.label} • {task.subject}</Text>
                                                    <Text style={[styles.tileTitle, isDone && styles.completedText]} numberOfLines={2}>{task.title}</Text>
                                                </View>
                                            </View>
                                            <Ionicons name={isDone ? "checkmark-circle" : "chevron-forward"} size={isDone ? 26 : 18} color={isDone ? "#4CAF50" : "#CCC"} />
                                        </TouchableOpacity>
                                    );
                                })}
                            </View>
                        </View>
                    ))}
                </ScrollView>
            </View>
        );
    }

    return (
        <ScrollView style={{flex: 1, backgroundColor: '#FFF'}} contentContainerStyle={{flexGrow: 1}}>
            <LinearGradient colors={['#4F46E5', '#7C3AED']} style={styles.setupBanner}>
                <Ionicons name="airplane-outline" size={80} color="white" />
                <Text style={styles.setupTitle}>Strategic Plan</Text>
                <Text style={styles.setupDesc}>We'll map out all notes, videos, and quizzes.</Text>
            </LinearGradient>

            <View style={styles.wizardBox}>
                <Text style={styles.wizardProgress}>STEP {wizardStep} / 3</Text>
                
                {wizardStep === 1 && (
                    <View style={styles.stepContent}>
                        <Text style={styles.stepTitle}>When is your Exam? 🗓️</Text>
                        <TouchableOpacity style={styles.dateSelector} onPress={() => setShowDatePicker(true)}><Ionicons name="calendar-outline" size={24} color="#4F46E5" /><Text style={styles.dateSelectorText}>{examDate.toDateString()}</Text></TouchableOpacity>
                        {showDatePicker && <DateTimePicker value={examDate} mode="date" display="default" minimumDate={new Date()} onChange={handleDateChange} />}
                        <TouchableOpacity style={styles.primaryActionButton} onPress={() => setWizardStep(2)}><Text style={styles.primaryActionButtonLabel}>Next</Text></TouchableOpacity>
                    </View>
                )}

                {wizardStep === 2 && (
                    <View style={styles.stepContent}>
                        <Text style={styles.stepTitle}>Select Subjects 📚</Text>
                        <View style={styles.chipCloud}>
                            {availableSubjects.map(s => (
                                <TouchableOpacity key={s.subject_id} style={[styles.smallChip, selectedSubjects.includes(s.subject_id) && styles.smallChipActive]} onPress={() => toggleSubject(s.subject_id)}>
                                    <Text style={[styles.smallChipLabel, selectedSubjects.includes(s.subject_id) && styles.smallChipLabelActive]}>{s.subject_name}</Text>
                                </TouchableOpacity>
                            ))}
                        </View>

                        {selectedSubjects.length > 0 && (
                            <View style={styles.chapterSection}>
                                <Text style={styles.sectionHeading}>Curate Your Pipeline 🛠️</Text>
                                {loadingChapters ? <ActivityIndicator color="#4F46E5" /> : (
                                    <View style={styles.groupedChapterList}>
                                        {selectedSubjects.map(sid => {
                                            const subject = availableSubjects.find(s => s.subject_id === sid);
                                            const subjectChapters = allChapters.filter(ch => ch.subject_id === sid);
                                            
                                            if (subjectChapters.length === 0) return null;

                                            return (
                                                <View key={sid} style={styles.subjectGroup}>
                                                    <View style={styles.subjectHeader}>
                                                        <Text style={styles.subjectHeaderLabel}>{subject?.subject_name}</Text>
                                                        <View style={styles.subjectHeaderLine} />
                                                    </View>
                                                    
                                                    {subjectChapters.map(ch => (
                                                        <TouchableOpacity 
                                                            key={ch.chapter_id} 
                                                            style={[styles.chapterRow, !selectedChapters.includes(ch.chapter_id) && styles.chapterRowDim]} 
                                                            onPress={() => toggleChapter(ch.chapter_id)}
                                                        >
                                                            <Ionicons 
                                                                name={selectedChapters.includes(ch.chapter_id) ? "checkmark-circle" : "ellipse-outline"} 
                                                                size={22} 
                                                                color={selectedChapters.includes(ch.chapter_id) ? "#4F46E5" : "#CCC"} 
                                                            />
                                                            <Text style={[styles.chapterRowText, selectedChapters.includes(ch.chapter_id) && styles.chapterRowTextActive]}>
                                                                {ch.chapter_name}
                                                            </Text>
                                                        </TouchableOpacity>
                                                    ))}
                                                </View>
                                            );
                                        })}
                                    </View>
                                )}
                            </View>
                        )}

                        <View style={styles.wizardFooter}>
                            <TouchableOpacity onPress={() => setWizardStep(1)}><Text style={styles.wizardBack}>Back</Text></TouchableOpacity>
                            <TouchableOpacity style={styles.primaryActionButton} onPress={() => setWizardStep(3)}><Text style={styles.primaryActionButtonLabel}>Next</Text></TouchableOpacity>
                        </View>
                    </View>
                )}

                {wizardStep === 3 && (
                    <View style={styles.stepContent}>
                        <Text style={styles.stepTitle}>All Set! 🚀</Text>
                        <View style={styles.summaryCard}>
                            <Text style={styles.summaryInfo}>📅 Exam Date: {examDate.toLocaleDateString()}</Text>
                            <Text style={styles.summaryInfo}>📖 Chapters Selected: {selectedChapters.length}</Text>
                        </View>
                        <TouchableOpacity style={styles.launchBtn} onPress={savePlan}>
                            <LinearGradient colors={['#4F46E5', '#7C3AED']} style={styles.launchBtnGrad}><Text style={styles.launchBtnText}>GENERATE ROADMAP</Text></LinearGradient>
                        </TouchableOpacity>
                        <TouchableOpacity onPress={() => setWizardStep(2)} style={{marginTop:20}}><Text style={styles.wizardBack}>Change Chapters</Text></TouchableOpacity>
                    </View>
                )}
            </View>
        </ScrollView>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F9FAFC' },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    modernHeader: { padding: 25, paddingTop: 50, borderBottomLeftRadius: 30, borderBottomRightRadius: 30 },
    headerTop: { flexDirection: 'row', alignItems: 'center' },
    backBtn: { marginRight: 15 },
    headerInfoText: { flex: 1 },
    headerTitle: { fontSize: 22, fontWeight: 'bold', color: 'white' },
    headerSub: { fontSize: 13, color: 'rgba(255,255,255,0.8)', marginTop: 2 },
    resetHeaderBtn: { padding: 5 },

    timelineBody: { padding: 20 },
    dayBlock: { marginBottom: 20, position: 'relative' },
    dayLabelRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 15 },
    dayBadge: { backgroundColor: '#E0E0E0', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 6, marginRight: 10 },
    todayBadge: { backgroundColor: '#FFD600' },
    dayBadgeText: { fontSize: 10, fontWeight: 'bold', color: '#666' },
    todayBadgeText: { color: '#000' },
    dayFullDate: { fontSize: 14, fontWeight: 'bold', color: '#888' },
    verticalLink: { position: 'absolute', left: 18, top: 40, bottom: -20, width: 2, backgroundColor: '#E0E0E0', zIndex: -1 },

    dayTasks: { paddingLeft: 10 },
    smartTile: { backgroundColor: 'white', borderLeftWidth: 4, borderRadius: 15, padding: 15, flexDirection: 'row', alignItems: 'center', marginBottom: 12, elevation: 2 },
    completedTile: { backgroundColor: '#F1F8E9', borderLeftColor: '#4CAF50', elevation: 0 },
    tileLeft: { flex: 1, flexDirection: 'row', alignItems: 'center' },
    tileTextContainer: { marginLeft: 15, flex: 1 },
    tileTag: { fontSize: 9, fontWeight: '900', textTransform: 'uppercase', marginBottom: 2 },
    tileTitle: { fontSize: 14, fontWeight: '700', color: '#333' },
    completedText: { textDecorationLine: 'line-through', color: '#757575' },

    setupBanner: { height: 320, justifyContent: 'center', alignItems: 'center', borderBottomLeftRadius: 50, borderBottomRightRadius: 50 },
    setupTitle: { fontSize: 28, fontWeight: 'bold', color: 'white', marginTop: 20 },
    setupDesc: { fontSize: 14, color: 'rgba(255,255,255,0.9)', marginTop: 8, textAlign: 'center', paddingHorizontal: 50 },
    wizardBox: { backgroundColor: 'white', marginHorizontal: 25, marginTop: -40, borderRadius: 30, padding: 30, elevation: 12, marginBottom: 50 },
    wizardProgress: { fontSize: 10, fontWeight: '900', color: '#999', textAlign: 'center', marginBottom: 20 },
    stepContent: { alignItems: 'center' },
    stepTitle: { fontSize: 22, fontWeight: 'bold', color: '#1A237E', marginBottom: 30 },
    dateSelector: { width: '100%', padding: 18, backgroundColor: '#F3F4F9', borderRadius: 15, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', marginBottom: 40 },
    dateSelectorText: { fontSize: 18, fontWeight: 'bold', color: '#333', marginLeft: 12 },
    primaryActionButton: { backgroundColor: '#4F46E5', paddingHorizontal: 40, paddingVertical: 15, borderRadius: 15 },
    primaryActionButtonLabel: { color: 'white', fontWeight: 'bold', fontSize: 16 },

    chipCloud: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', marginBottom: 20 },
    smallChip: { paddingHorizontal: 15, paddingVertical: 8, borderRadius: 20, borderWidth: 1, borderColor: '#DDD', margin: 5 },
    smallChipActive: { backgroundColor: '#1A237E', borderColor: '#1A237E' },
    smallChipLabel: { fontSize: 12, fontWeight: 'bold', color: '#666' },
    smallChipLabelActive: { color: 'white' },

    chapterSection: { width: '100%', marginTop: 10, marginBottom: 30 },
    sectionHeading: { fontSize: 16, fontWeight: 'bold', color: '#444', marginBottom: 15 },
    groupedChapterList: { width: '100%' },
    subjectGroup: { marginBottom: 25 },
    subjectHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 12 },
    subjectHeaderLabel: { fontSize: 13, fontWeight: '900', color: '#4F46E5', textTransform: 'uppercase', letterSpacing: 1 },
    subjectHeaderLine: { flex: 1, height: 1, backgroundColor: '#EEE', marginLeft: 10 },
    chapterRow: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#F8F9FE', padding: 15, borderRadius: 15, marginBottom: 8, borderWidth: 1, borderColor: '#F0F0F0' },
    chapterRowDim: { opacity: 0.5, backgroundColor: 'white' },
    chapterRowText: { fontSize: 14, fontWeight: '600', color: '#666', marginLeft: 12, flex: 1 },
    chapterRowTextActive: { color: '#333', fontWeight: '700' },

    wizardFooter: { width: '100%', flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
    wizardBack: { fontWeight: 'bold', color: '#999' },
    summaryCard: { width: '100%', padding: 20, backgroundColor: '#F8F9FE', borderRadius: 20, marginBottom: 40 },
    summaryInfo: { fontSize: 14, fontWeight: 'bold', color: '#444', marginBottom: 10 },
    launchBtn: { width: '100%', borderRadius: 15, overflow: 'hidden' },
    launchBtnGrad: { padding: 20, alignItems: 'center' },
    launchBtnText: { color: 'white', fontWeight: 'bold' }
});

export default StudyPlannerScreen;
