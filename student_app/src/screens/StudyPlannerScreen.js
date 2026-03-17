import React, { useState, useEffect } from 'react';
import {
    View, Text, StyleSheet, ScrollView, TouchableOpacity,
    ActivityIndicator, Dimensions, Alert, Platform,
    StatusBar
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import config from '../api/config';
import axios from 'axios';
import DateTimePicker from '@react-native-community/datetimepicker';
import { scheduleStudyPlanNotifications } from '../utils/studyNotificationHelper';

const { width } = Dimensions.get('window');

const StudyPlannerScreen = ({ user, navigation }) => {
    const { theme } = useTheme();

    // --- State ---
    const [loading, setLoading] = useState(true);
    const [isConfigured, setIsConfigured] = useState(false);
    const [wizardStep, setWizardStep] = useState(1);
    const [roadmap, setRoadmap] = useState([]);
    const insets = useSafeAreaInsets();

    // Form Data
    const [examDate, setExamDate] = useState(new Date());
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
                // Fix for Jan 01 1970: Check if date is valid
                if (res.data.exam_date && res.data.exam_date !== '0000-00-00' && res.data.exam_date !== '1970-01-01') {
                    setExamDate(new Date(res.data.exam_date));
                } else {
                    setExamDate(new Date()); // Fallback to today
                }
                fetchRoadmap();
            } else {
                setExamDate(new Date()); // Ensure it's today if not configured or fallback
            }
        } catch (error) {
            console.log("No existing plan found");
            setExamDate(new Date()); // Safety fallback
        } finally {
            setLoading(false);
        }
    };

    const fetchRoadmap = async () => {
        try {
            // First, trigger redistribution of missed tasks
            await axios.post(`${config.API_URL}/redistribute_tasks.php`, { user_id: user.user_id });

            const res = await axios.get(`${config.API_URL}/get_roadmap.php?user_id=${user.user_id}`);
            if (res.data.status === 'success') {
                setRoadmap(res.data.data);
                
                // Schedule notifications for today's newly loaded roadmap
                const today = new Date().toISOString().split('T')[0];
                const todayData = res.data.data.find(d => d.date === today);
                if (todayData && todayData.tasks.length > 0) {
                    const startTime = new Date();
                    const endTime = new Date();
                    endTime.setHours(21, 0, 0);
                    scheduleStudyPlanNotifications(todayData.tasks, startTime, endTime);
                }
            }
        } catch (error) {
            console.error("Error loading roadmap/redistributing:", error);
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
        if (selectedDate) {
            setExamDate(selectedDate);
            // If already configured, auto-save the new date
            if (isConfigured) {
                // We need to re-save with existing subjects/chapters or just update date?
                // For simplicity, if they change date in header, we trigger a regen
                setTimeout(() => {
                    Alert.alert(
                        "Update Roadmap?",
                        "Changing the exam date will regenerate your study plan. Continue?",
                        [
                            { text: "Cancel", style: "cancel", onPress: () => checkExistingPlan() },
                            { text: "Update", onPress: () => updatePlanWithNewDate(selectedDate) }
                        ]
                    );
                }, 500);
            }
        }
    };

    const updatePlanWithNewDate = async (newDate) => {
        setLoading(true);
        try {
            // Fetch existing selection first to be safe
            const res = await axios.post(`${config.API_URL}/setup_syllabus_path.php`, {
                user_id: user.user_id,
                exam_date: newDate.toISOString().split('T')[0],
                subject_ids: selectedSubjects,
                chapter_ids: selectedChapters
            });
            if (res.data.status === 'success') {
                fetchRoadmap();
            }
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
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

                // --- Schedule Notifications for Today's Tasks ---
                // We use a separate logic to ensure we don't crash if the immediate response lacks data
                if (res.data.status === 'success') {
                    // Fetch roadmap first to get the structured data
                    const roadRes = await axios.get(`${config.API_URL}/get_roadmap.php?user_id=${user.user_id}`);
                    if (roadRes.data.status === 'success' && roadRes.data.data) {
                        const today = new Date().toISOString().split('T')[0];
                        const todayData = roadRes.data.data.find(d => d.date === today);
                        const todayTasks = todayData?.tasks || [];
                        
                        if (todayTasks.length > 0) {
                            const startTime = new Date(); 
                            const endTime = new Date();
                            endTime.setHours(21, 0, 0); // 9 PM

                            scheduleStudyPlanNotifications(todayTasks, startTime, endTime);
                        }
                    }
                }
            } else {
                Alert.alert("Error", res.data.message || "Something went wrong.");
            }
        } catch (error) {
            console.error(error);
            Alert.alert("Error", "Failed to connect to server.");
        } finally {
            setLoading(false);
        }
    };

    const handleTaskPress = async (task) => {
        if (task.status === 'completed') {
            Alert.alert("Goal Achieved", "You have already mastered this session!");
            return;
        }

        // --- NEW: MEGA REVISION BLITZ HANDLER ---
        if (task.task_type === 'mega' || task.title.includes('Mega Revision Blitz')) {
            setLoading(true);
            try {
                // Determine medium from user profile (default to English if not found)
                const medium = user?.medium || 'english';
                const res = await axios.get(`${config.API_URL}/get_mega_revision_mcqs.php?user_id=${user.user_id}&medium=${medium}`);
                
                if (res.data.status === 'success' && res.data.data.length > 0) {
                    navigation.navigate('MyExamTest', { 
                        questions: res.data.data, 
                        totalQuestions: res.data.data.length,
                        subjectName: 'Mega Revision Blitz',
                        taskId: task.task_id,
                        source: 'study_planner'
                    });
                } else {
                    Alert.alert("No MCQs Found", "Go back and finish some chapters to unlock the Blitz!");
                }
            } catch (err) {
                console.error(err);
                Alert.alert("Error", "Failed to load Mega Revision MCQs.");
            } finally {
                setLoading(false);
            }
            return;
        }
        
        const chapterData = {
            chapter_id: task.chapter_id,
            chapter_name: task.title.split(': ').pop(),
            subject_name: task.subject
        };

        const navConfig = {
            quiz: 'MCQs',
            video: 'Videos',
            flashcard: 'Flashcards',
            notes: 'Notes'
        };

        const initialTab = navConfig[task.task_type] || 'Notes';
        navigation.navigate('ChapterContent', { chapter: chapterData, initialTab });
    };

    const getTaskStyle = (type, isCompleted) => {
        if (isCompleted) return { color: '#4CAF50', icon: 'checkmark-circle', label: 'Done' };
        switch (type) {
            case 'video': return { color: '#6200EA', icon: 'play-circle-outline', label: 'Video' };
            case 'quiz': return { color: '#E65100', icon: 'medal-outline', label: 'Quiz' };
            case 'notes': return { color: '#0091EA', icon: 'document-text-outline', label: 'Notes' };
            case 'flashcard': return { color: '#D500F9', icon: 'layers-outline', label: 'Cards' };
            case 'mega': return { color: '#FF5722', icon: 'flash-outline', label: 'BLITZ' };
            default: return { color: '#455A64', icon: 'star-outline', label: 'Goal' };
        }
    };


    if (loading) return <View style={styles.centered}><ActivityIndicator size="large" color="#4F46E5" /></View>;

    if (isConfigured) {
        return (
            <View style={[styles.container, { paddingTop: insets.top }]}>
                <LinearGradient 
                    colors={['#0f172a', '#1e293b', '#334155']} 
                    start={{x:0, y:0}} end={{x:1, y:1}}
                    style={[styles.modernHeader, { paddingTop: insets.top + 10, paddingBottom: 60 }]}
                >
                    <View style={styles.headerTop}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
                            <Ionicons name="chevron-back" size={24} color="white" />
                        </TouchableOpacity>
                        <View style={styles.headerInfoText}>
                            <Text style={styles.headerTitle}>Victory Pipeline</Text>
                            <TouchableOpacity onPress={() => setShowDatePicker(true)} style={styles.headerDateBadge}>
                                <Ionicons name="calendar-outline" size={12} color="rgba(255,255,255,0.7)" />
                                <Text style={styles.headerSub}>
                                    Deadline: {examDate instanceof Date && !isNaN(examDate) ? examDate.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) : 'Set Date'}
                                </Text>
                            </TouchableOpacity>
                        </View>
                        <TouchableOpacity onPress={() => {
                            Alert.alert(
                                "Reset Planner?",
                                "This will clear your current roadmap and allow you to start over. Continue?",
                                [
                                    { text: "Cancel", style: "cancel" },
                                    { text: "Reset", style: "destructive", onPress: () => setIsConfigured(false) }
                                ]
                            );
                        }} style={styles.resetHeaderBtn}>
                            <Ionicons name="refresh-outline" size={24} color="white" />
                        </TouchableOpacity>
                    </View>
                </LinearGradient>

                {/* --- Progress Dashboard --- */}
                <View style={styles.progressCard}>
                    <View style={styles.statsRow}>
                        <View style={styles.statItem}>
                            <Text style={styles.statVal}>{roadmap.flatMap(d => d.tasks).filter(t => t.status === 'completed').length}</Text>
                            <Text style={styles.statLab}>Completed</Text>
                        </View>
                        <View style={styles.statDivider} />
                        <View style={styles.statItem}>
                            <Text style={[styles.statVal, {color: '#6366f1'}]}>{roadmap.flatMap(d => d.tasks).length}</Text>
                            <Text style={styles.statLab}>Total Tasks</Text>
                        </View>
                        <View style={styles.statDivider} />
                        <View style={styles.statItem}>
                            <Text style={[styles.statVal, {color: '#10b981'}]}>
                                {Math.round((roadmap.flatMap(d => d.tasks).filter(t => t.status === 'completed').length / (roadmap.flatMap(d => d.tasks).length || 1)) * 100)}%
                            </Text>
                            <Text style={styles.statLab}>Mastery</Text>
                        </View>
                    </View>
                    <View style={styles.progressBarBg}>
                        <LinearGradient
                            colors={['#10b981', '#34d399']}
                            start={{ x: 0, y: 0 }}
                            end={{ x: 1, y: 0 }}
                            style={[styles.progressBarFill, { 
                                width: `${(roadmap.flatMap(d => d.tasks).filter(t => t.status === 'completed').length / (roadmap.flatMap(d => d.tasks).length || 1)) * 100}%` 
                            }]}
                        />
                    </View>
                </View>

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
                                        <TouchableOpacity 
                                            key={task.task_id} 
                                            style={[
                                                styles.smartTile, 
                                                { borderLeftColor: style.color }, 
                                                isDone && styles.completedTile,
                                                task.task_type === 'mega' && !isDone && styles.megaTile
                                            ]} 
                                            onPress={() => handleTaskPress(task)}
                                        >
                                            <View style={styles.tileLeft}>
                                                <Ionicons name={style.icon} size={24} color={task.task_type === 'mega' && !isDone ? 'white' : style.color} />
                                                <View style={styles.tileTextContainer}>
                                                    <View style={styles.tileTopRow}>
                                                        <Text style={[styles.tileTag, { color: task.task_type === 'mega' && !isDone ? 'rgba(255,255,255,0.9)' : style.color }]}>
                                                            {style.label} • {task.subject}
                                                        </Text>
                                                        {task.xp_reward > 0 && !isDone && (
                                                            <View style={[styles.xpBadge, { backgroundColor: style.color + '20' }]}>
                                                                <Text style={[styles.xpText, { color: style.color }]}>+{task.xp_reward} XP</Text>
                                                            </View>
                                                        )}
                                                    </View>
                                                    <Text style={[
                                                        styles.tileTitle, 
                                                        isDone && styles.completedText,
                                                        task.task_type === 'mega' && !isDone && styles.megaTitleText
                                                    ]} numberOfLines={2}>
                                                        {task.title.toUpperCase()}
                                                    </Text>
                                                </View>
                                            </View>
                                            <Ionicons 
                                                name={isDone ? "checkmark-circle" : "chevron-forward"} 
                                                size={isDone ? 26 : 18} 
                                                color={isDone ? "#4CAF50" : (task.task_type === 'mega' ? 'white' : "#CCC")} 
                                            />
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
        <View style={{flex: 1, backgroundColor: '#F8FAFC'}}>
            <StatusBar barStyle="light-content" backgroundColor="#312e81" translucent />
            <ScrollView 
                style={{flex: 1}} 
                contentContainerStyle={{flexGrow: 1, paddingBottom: 100}}
                showsVerticalScrollIndicator={false}
            >
                <LinearGradient 
                    colors={['#1e1b4b', '#312e81', '#4338ca']} 
                    start={{x:0, y:0}} end={{x:1, y:1}}
                    style={[styles.setupBanner, { paddingTop: insets.top + 30, paddingBottom: 50 }]}
                >
                    <View style={styles.headerIconCircle}>
                        <LinearGradient colors={['#ffffff', '#f1f5f9']} style={styles.iconCircleGrad}>
                            <Ionicons name="rocket" size={40} color="#4338ca" />
                        </LinearGradient>
                    </View>
                    <Text style={styles.setupTitle}>Strategic Study Roadmap</Text>
                    <Text style={styles.setupDesc}>AI-Powered syllabus coverage for your success.</Text>
                </LinearGradient>

                <View style={styles.wizardBox}>
                {/* Visual Step Indicator */}
                <View style={styles.stepIndicatorRow}>
                    {[1, 2, 3].map((s) => (
                        <View key={s} style={[styles.stepDot, wizardStep >= s && styles.stepDotActive]}>
                            {wizardStep > s ? (
                                <Ionicons name="checkmark" size={14} color="white" />
                            ) : (
                                <Text style={[styles.stepDotText, wizardStep === s && styles.stepDotTextActive]}>{s}</Text>
                            )}
                        </View>
                    ))}
                    <View style={styles.stepConnector} />
                </View>

                {wizardStep === 1 && (
                    <View style={styles.stepContent}>
                        <Text style={styles.stepTitle}>When is your Exam? 🗓️</Text>
                        <Text style={styles.stepSubText}>Pick the date you want to be ready by.</Text>
                        
                        <TouchableOpacity style={styles.dateSelectorCard} onPress={() => setShowDatePicker(true)}>
                            <View style={styles.dateIconBox}>
                                <Ionicons name="calendar" size={24} color="#4338ca" />
                            </View>
                            <View style={styles.dateTextBox}>
                                <Text style={styles.dateLabel}>TARGET EXAM DATE</Text>
                                <Text style={styles.dateValue}>
                                    {examDate instanceof Date && !isNaN(examDate) ? examDate.toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }) : new Date().toLocaleDateString()}
                                </Text>
                            </View>
                            <View style={styles.editDateBtn}>
                                <Text style={styles.editDateText}>CHOOSE</Text>
                            </View>
                        </TouchableOpacity>

                        {showDatePicker && (
                            <DateTimePicker 
                                value={examDate instanceof Date && !isNaN(examDate) ? examDate : new Date()} 
                                mode="date" 
                                display="default" 
                                minimumDate={new Date()} 
                                onChange={handleDateChange} 
                            />
                        )}

                        <TouchableOpacity style={styles.primaryBtn} onPress={() => setWizardStep(2)}>
                            <LinearGradient colors={['#4338ca', '#6366f1']} start={{x:0, y:0}} end={{x:1, y:0}} style={styles.primaryBtnGrad}>
                                <Text style={styles.primaryBtnText}>Continue to Subjects</Text>
                                <Ionicons name="arrow-forward-circle" size={22} color="white" />
                            </LinearGradient>
                        </TouchableOpacity>
                    </View>
                )}

                {wizardStep === 2 && (
                    <View style={styles.stepContent}>
                        <Text style={styles.stepTitle}>Select Subjects 📚</Text>
                        <Text style={styles.stepSubText}>Select what you need to master.</Text>
                        
                        <View style={styles.chipCloud}>
                            {availableSubjects.map(s => (
                                <TouchableOpacity 
                                    key={s.subject_id} 
                                    style={[styles.smallChip, selectedSubjects.includes(s.subject_id) && styles.smallChipActive]} 
                                    onPress={() => toggleSubject(s.subject_id)}
                                >
                                    <Text style={[styles.smallChipLabel, selectedSubjects.includes(s.subject_id) && styles.smallChipLabelActive]}>
                                        {s.subject_name}
                                    </Text>
                                    {selectedSubjects.includes(s.subject_id) && <Ionicons name="checkmark-circle" size={16} color="white" style={{marginLeft: 5}} />}
                                </TouchableOpacity>
                            ))}
                        </View>

                        {selectedSubjects.length > 0 && (
                            <View style={styles.chapterSection}>
                                <View style={styles.sectionHeaderRow}>
                                    <View>
                                        <Text style={styles.sectionHeading}>Curate Chapters 🛠️</Text>
                                        <Text style={styles.sectionSubHeading}>{selectedChapters.length} Chapters in Pipeline</Text>
                                    </View>
                                    <TouchableOpacity onPress={() => setSelectedChapters(allChapters.map(c => c.chapter_id))} style={styles.selectAllBtn}>
                                        <Text style={styles.selectAllText}>Select All</Text>
                                    </TouchableOpacity>
                                </View>

                                {loadingChapters ? (
                                    <View style={{padding: 40}}><ActivityIndicator color="#4338ca" size="large" /></View>
                                ) : (
                                    <View style={styles.groupedChapterList}>
                                        {selectedSubjects.map(sid => {
                                            const subject = availableSubjects.find(s => s.subject_id === sid);
                                            const subjectChapters = allChapters.filter(ch => ch.subject_id === sid);
                                            
                                            if (subjectChapters.length === 0) return null;

                                            return (
                                                <View key={sid} style={styles.subjectGroup}>
                                                    <View style={styles.subjectHeader}>
                                                        <Text style={styles.subjectHeaderLabel}>{subject?.subject_name}</Text>
                                                    </View>
                                                    
                                                    {subjectChapters.map(ch => (
                                                        <TouchableOpacity 
                                                            key={ch.chapter_id} 
                                                            style={[styles.chapterRow, !selectedChapters.includes(ch.chapter_id) && styles.chapterRowDim]} 
                                                            onPress={() => toggleChapter(ch.chapter_id)}
                                                        >
                                                            <View style={[styles.chapterCheck, selectedChapters.includes(ch.chapter_id) && styles.chapterCheckActive]}>
                                                                {selectedChapters.includes(ch.chapter_id) && <Ionicons name="checkmark" size={16} color="white" />}
                                                            </View>
                                                            <Text style={[styles.chapterRowText, selectedChapters.includes(ch.chapter_id) && styles.chapterRowTextActive]}>
                                                                {ch.chapter_name.toUpperCase()}
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
                            <TouchableOpacity onPress={() => setWizardStep(1)} style={styles.secondaryBtn}>
                                <Text style={styles.secondaryBtnText}>Back</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={styles.primaryBtnSmall} onPress={() => setWizardStep(3)}>
                                <LinearGradient colors={['#4338ca', '#6366f1']} start={{x:0, y:0}} end={{x:1, y:0}} style={styles.primaryBtnGradSmall}>
                                    <Text style={styles.primaryBtnText}>Review Plan</Text>
                                    <Ionicons name="chevron-forward" size={18} color="white" style={{marginLeft: 5}} />
                                </LinearGradient>
                            </TouchableOpacity>
                        </View>
                    </View>
                )}

                {wizardStep === 3 && (
                    <View style={styles.stepContent}>
                        <View style={styles.finalSuccessIcon}>
                            <Ionicons name="checkmark-done-circle" size={80} color="#10b981" />
                        </View>
                        <Text style={styles.stepTitle}>Ready to Blast Off! 🚀</Text>
                        <Text style={styles.stepSubText}>Your customized roadmap is prepared.</Text>
                        
                        <View style={styles.summaryCardModern}>
                            <View style={styles.summaryItem}>
                                <Ionicons name="calendar-outline" size={20} color="#64748b" />
                                <Text style={styles.summaryText}>Target Date: <Text style={{color: '#1e293b', fontWeight: 'bold'}}>{examDate.toLocaleDateString()}</Text></Text>
                            </View>
                            <View style={styles.summaryItem}>
                                <Ionicons name="book-outline" size={20} color="#64748b" />
                                <Text style={styles.summaryText}>Total Chapters: <Text style={{color: '#1e293b', fontWeight: 'bold'}}>{selectedChapters.length}</Text></Text>
                            </View>
                        </View>

                        <TouchableOpacity style={styles.launchBtnModern} onPress={savePlan}>
                            <LinearGradient colors={['#10b981', '#059669']} start={{x:0, y:0}} end={{x:1, y:0}} style={styles.launchBtnGrad}>
                                <Text style={styles.launchBtnText}>CREATE MY VICTORY PIPELINE</Text>
                            </LinearGradient>
                        </TouchableOpacity>

                        <TouchableOpacity onPress={() => setWizardStep(2)} style={styles.reviewPlanBtn}>
                            <Text style={styles.reviewPlanText}>Adjust Selection</Text>
                        </TouchableOpacity>
                    </View>
                )}
                </View>
            </ScrollView>
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F9FAFC' },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    modernHeader: { padding: 20, paddingTop: 60, borderBottomLeftRadius: 30, borderBottomRightRadius: 30 },
    headerTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
    backBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.15)', justifyContent: 'center', alignItems: 'center' },
    headerInfoText: { flex: 1, marginLeft: 15 },
    headerTitle: { fontSize: 24, fontWeight: '900', color: 'white', letterSpacing: 0.5 },
    headerDateBadge: { flexDirection: 'row', alignItems: 'center', gap: 5, marginTop: 4 },
    headerSub: { fontSize: 13, color: 'rgba(255,255,255,0.8)' },
    resetHeaderBtn: { width: 44, height: 44, borderRadius: 22, justifyContent: 'center', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.1)' },

    // --- Progress Card Styles ---
    progressCard: {
        backgroundColor: 'white',
        marginHorizontal: 16,
        marginTop: -45,
        borderRadius: 24,
        padding: 24,
        elevation: 10,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 10,
    },
    statsRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 20,
    },
    statItem: { alignItems: 'center', flex: 1 },
    statVal: { fontSize: 22, fontWeight: 'bold', color: '#1e293b' },
    statLab: { fontSize: 10, color: '#64748b', textTransform: 'uppercase', marginTop: 6, fontWeight: '800', letterSpacing: 0.5 },
    statDivider: { width: 1, height: 35, backgroundColor: '#f1f5f9' },
    progressTextRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 15,
    },
    progressLabel: {
        fontSize: 14,
        fontWeight: 'bold',
        color: '#1A237E',
    },
    progressStats: {
        fontSize: 11,
        color: '#666',
        marginTop: 2,
    },
    progressPercent: {
        fontSize: 24,
        fontWeight: '900',
        color: '#4CAF50',
    },
    progressBarBg: {
        height: 12,
        backgroundColor: '#f1f5f9',
        borderRadius: 6,
        overflow: 'hidden',
    },
    progressBarFill: {
        height: '100%',
        borderRadius: 6,
    },

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
    tileTopRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 2 },
    xpBadge: { paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6, backgroundColor: 'rgba(230, 81, 0, 0.1)' },
    xpText: { fontSize: 10, fontWeight: '800', fontFamily: 'NotoSans-Bold' },
    tileTag: { fontSize: 10, fontWeight: '800', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 2 },
    tileTitle: { fontSize: 15, fontWeight: '700', color: '#1e293b' },
    megaTile: { backgroundColor: '#FF5722', shadowColor: '#FF5722', shadowOpacity: 0.3, elevation: 8 },
    megaTitleText: { color: 'white' },
    completedText: { textDecorationLine: 'line-through', color: '#94a3b8' },

    restartButtonText: { color: '#4338ca', fontSize: 18, fontWeight: 'bold' },
    
    // Setup Optimization Styles
    setupBanner: { 
        height: 240, 
        justifyContent: 'center', 
        alignItems: 'center', 
        paddingTop: 20 
    },
    headerIconCircle: {
        width: 80,
        height: 80,
        borderRadius: 40,
        backgroundColor: 'white',
        elevation: 10,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 5 },
        shadowOpacity: 0.25,
        shadowRadius: 8,
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 15
    },
    iconCircleGrad: {
        width: '100%',
        height: '100%',
        borderRadius: 40,
        justifyContent: 'center',
        alignItems: 'center'
    },
    setupTitle: { 
        fontSize: 22, 
        fontWeight: 'bold', 
        color: 'white', 
        textAlign: 'center' 
    },
    setupDesc: { 
        fontSize: 14, 
        color: 'rgba(255,255,255,0.85)', 
        marginTop: 6, 
        textAlign: 'center' 
    },
    wizardBox: { 
        backgroundColor: 'white', 
        marginHorizontal: 16, 
        marginTop: -40, 
        borderRadius: 30, 
        padding: 24, 
        elevation: 12,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 12 },
        shadowOpacity: 0.12,
        shadowRadius: 20,
        marginBottom: 40
    },
    stepIndicatorRow: {
        flexDirection: 'row',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 30,
        position: 'relative'
    },
    stepDot: {
        width: 32,
        height: 32,
        borderRadius: 16,
        backgroundColor: '#f1f5f9',
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 2,
        borderColor: '#e2e8f0',
        zIndex: 2,
        marginHorizontal: 20
    },
    stepDotActive: {
        backgroundColor: '#4338ca',
        borderColor: '#4338ca'
    },
    stepDotText: {
        fontSize: 12,
        fontWeight: 'bold',
        color: '#94a3b8'
    },
    stepDotTextActive: {
        color: 'white'
    },
    stepConnector: {
        position: 'absolute',
        top: 15,
        left: '20%',
        right: '20%',
        height: 3,
        backgroundColor: '#f1f5f9',
        zIndex: 1
    },
    stepContent: {
        alignItems: 'center',
        width: '100%'
    },
    stepTitle: {
        fontSize: 22,
        fontWeight: 'bold',
        color: '#1e293b',
        marginBottom: 8,
        textAlign: 'center'
    },
    stepSubText: {
        fontSize: 14,
        color: '#64748b',
        marginBottom: 30,
        textAlign: 'center'
    },
    dateSelectorCard: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#f8fafc',
        width: '100%',
        padding: 20,
        borderRadius: 20,
        borderWidth: 1,
        borderColor: '#e2e8f0',
        marginBottom: 40
    },
    dateIconBox: {
        width: 48,
        height: 48,
        borderRadius: 12,
        backgroundColor: '#e0e7ff',
        justifyContent: 'center',
        alignItems: 'center'
    },
    dateTextBox: {
        flex: 1,
        marginLeft: 15
    },
    dateLabel: {
        fontSize: 10,
        fontWeight: '900',
        color: '#94a3b8',
        letterSpacing: 1
    },
    dateValue: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#1e293b',
        marginTop: 2
    },
    editDateBtn: {
        backgroundColor: 'white',
        paddingHorizontal: 16,
        paddingVertical: 8,
        borderRadius: 12,
        borderWidth: 1.5,
        borderColor: '#e2e8f0'
    },
    editDateText: {
        fontSize: 10,
        fontWeight: 'bold',
        color: '#4338ca'
    },
    primaryBtn: {
        width: '100%',
        borderRadius: 16,
        overflow: 'hidden',
        elevation: 4
    },
    primaryBtnGrad: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 18,
        gap: 10
    },
    primaryBtnText: {
        color: 'white',
        fontSize: 16,
        fontWeight: '800',
        letterSpacing: 0.5
    },
    chipCloud: {
        flexDirection: 'row',
        flexWrap: 'wrap',
        justifyContent: 'center',
        gap: 10,
        marginBottom: 20
    },
    smallChip: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingVertical: 10,
        borderRadius: 25,
        backgroundColor: '#f1f5f9',
        borderWidth: 1,
        borderColor: '#e2e8f0'
    },
    smallChipActive: {
        backgroundColor: '#4338ca',
        borderColor: '#4338ca'
    },
    smallChipLabel: {
        fontSize: 14,
        fontWeight: '600',
        color: '#64748b'
    },
    smallChipLabelActive: {
        color: 'white',
        fontWeight: '700'
    },
    sectionHeaderRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'flex-end',
        marginBottom: 15,
        marginTop: 20
    },
    sectionHeading: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#1e293b'
    },
    sectionSubHeading: {
        fontSize: 12,
        color: '#64748b',
        marginTop: 2
    },
    selectAllBtn: {
        paddingHorizontal: 12,
        paddingVertical: 6
    },
    selectAllText: {
        fontSize: 12,
        fontWeight: 'bold',
        color: '#4338ca'
    },
    subjectGroup: {
        marginBottom: 20
    },
    subjectHeader: {
        marginBottom: 10,
        paddingLeft: 4
    },
    subjectHeaderLabel: {
        fontSize: 14,
        fontWeight: 'bold',
        color: '#4338ca',
        textTransform: 'uppercase'
    },
    chapterRow: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#f8fafc',
        padding: 16,
        borderRadius: 16,
        marginBottom: 8,
        borderWidth: 1,
        borderColor: '#e2e8f0'
    },
    chapterRowDim: {
        opacity: 0.6
    },
    chapterCheck: {
        width: 24,
        height: 24,
        borderRadius: 6,
        borderWidth: 2,
        borderColor: '#cbd5e1',
        backgroundColor: 'white',
        justifyContent: 'center',
        alignItems: 'center'
    },
    chapterCheckActive: {
        backgroundColor: '#6366f1',
        borderColor: '#6366f1'
    },
    chapterRowText: {
        flex: 1,
        fontSize: 14,
        color: '#475569',
        marginLeft: 12
    },
    chapterRowTextActive: {
        color: '#1e293b',
        fontWeight: 'bold'
    },
    wizardFooter: {
        flexDirection: 'row',
        width: '100%',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginTop: 30
    },
    secondaryBtn: {
        paddingHorizontal: 20,
        paddingVertical: 12
    },
    secondaryBtnText: {
        color: '#94a3b8',
        fontWeight: 'bold'
    },
    primaryBtnSmall: {
        borderRadius: 12,
        overflow: 'hidden'
    },
    primaryBtnGradSmall: {
        paddingHorizontal: 25,
        paddingVertical: 12
    },
    finalSuccessIcon: {
        marginBottom: 15
    },
    summaryCardModern: {
        width: '100%',
        backgroundColor: '#f8fafc',
        borderRadius: 20,
        padding: 20,
        borderWidth: 1,
        borderColor: '#e2e8f0',
        marginBottom: 30
    },
    summaryItem: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 12,
        gap: 12
    },
    summaryText: {
        fontSize: 14,
        color: '#64748b'
    },
    launchBtnModern: {
        width: '100%',
        borderRadius: 20,
        overflow: 'hidden',
        elevation: 8,
        shadowColor: '#10b981',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.4,
        shadowRadius: 12
    },
    launchBtnText: {
        color: 'white',
        fontWeight: '900',
        fontSize: 16,
        letterSpacing: 1.5,
        textShadowColor: 'rgba(0, 0, 0, 0.2)',
        textShadowOffset: { width: 0, height: 1 },
        textShadowRadius: 2
    },
    launchBtnGrad: {
        width: '100%',
        paddingVertical: 20,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center'
    },
    reviewPlanBtn: {
        marginTop: 25,
        paddingVertical: 12,
        paddingHorizontal: 20,
        alignItems: 'center'
    },
    reviewPlanText: {
        color: '#94a3b8',
        fontWeight: 'bold',
        fontSize: 12
    }
});

export default StudyPlannerScreen;
