import React, { useState, useEffect, useRef } from 'react';
import {
    View, Text, StyleSheet, ScrollView, TouchableOpacity,
    Animated, Image, Modal, Alert, ActivityIndicator, Dimensions
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import config from '../api/config';
import axios from 'axios';

import { fetchSubjects } from '../api/subjects';
import { fetchChapters } from '../api/chapters';

const { width } = Dimensions.get('window');

// --- Components ---

const LevelBadge = ({ level, xp, theme }) => (
    <View style={[styles.levelBadge, { backgroundColor: theme.cardBg }]}>
        <View style={styles.levelCircle}>
            <Text style={styles.levelText}>{level}</Text>
        </View>
        <View style={styles.xpBarContainer}>
            <Text style={[styles.xpText, { color: theme.textSecondary }]}>Level {level} Explorer</Text>
            <View style={styles.xpTrack}>
                <View style={[styles.xpFill, { width: `${Math.min(xp % 1000 / 10, 100)}%` }]} />
            </View>
        </View>
    </View>
);

const StreakFlame = ({ streak }) => (
    <View style={styles.streakContainer}>
        <Ionicons name="flame" size={32} color="#FF5722" />
        <Text style={styles.streakText}>{streak}</Text>
    </View>
);

const TaskCard = ({ task, onPress, theme }) => {
    const isCompleted = task.status === 'completed';
    return (
        <TouchableOpacity
            style={[styles.taskCard, { backgroundColor: theme.cardBg, borderColor: isCompleted ? '#4CAF50' : 'transparent', borderWidth: 2 }]}
            onPress={() => onPress(task)}
            disabled={isCompleted}
        >
            <View style={[styles.taskIcon, { backgroundColor: isCompleted ? '#E8F5E9' : '#FFF3E0' }]}>
                <Ionicons
                    name={isCompleted ? "checkmark-circle" : (task.task_type === 'video' ? "play-circle" : "book")}
                    size={28}
                    color={isCompleted ? "#4CAF50" : "#FF9800"}
                />
            </View>
            <View style={styles.taskInfo}>
                <Text style={[styles.taskSubject, { color: theme.textSecondary }]}>{task.subject}</Text>
                <Text style={[styles.taskTitle, { color: theme.text }]}>{task.title}</Text>
                <View style={styles.taskMeta}>
                    <Ionicons name="time-outline" size={14} color={theme.textSecondary} />
                    <Text style={[styles.taskMetaText, { color: theme.textSecondary }]}>{task.duration_minutes} min</Text>
                    <Text style={[styles.xpBadge, { color: '#6A1B9A' }]}>+{task.xp_reward} XP</Text>
                </View>
            </View>
            {isCompleted && (
                <View style={styles.completedBadge}>
                    <Text style={styles.completedText}>DONE</Text>
                </View>
            )}
        </TouchableOpacity>
    );
};

// --- Main Screen ---

const StudyPlannerScreen = ({ user, navigation, route }) => {
    const { theme } = useTheme();

    const [loading, setLoading] = useState(true);
    const [planExists, setPlanExists] = useState(false); // Only used for initial load check?
    const [showWizard, setShowWizard] = useState(true); // Controls view - DEFAULT TO WIZARD
    const [tasks, setTasks] = useState([]);
    const [stats, setStats] = useState({ level: 1, current_streak: 0, total_xp: 0 });

    // Wizard State
    const [wizardStep, setWizardStep] = useState(1); // 1: Time, 2: Subject, 3: Chapter
    const [availableSubjects, setAvailableSubjects] = useState([]);
    const [selectedSubjects, setSelectedSubjects] = useState([]); // Array of Subject IDs (Single for now)
    const [availableChapters, setAvailableChapters] = useState([]);
    const [selectedChapter, setSelectedChapter] = useState(null); // Chapter ID
    const [selectedHours, setSelectedHours] = useState(2); // In hours (0.25, 0.5, etc.)

    // Focus Timer
    const [activeTask, setActiveTask] = useState(null);
    const [timerVisible, setTimerVisible] = useState(false);
    const [timeLeft, setTimeLeft] = useState(0);
    const [isTimerRunning, setIsTimerRunning] = useState(false);

    useEffect(() => {
        fetchData();
        if (user?.class_id) {
            loadSubjects();
        }
    }, [user]);

    const fetchData = async () => {
        setLoading(true);
        try {
            // Check for existing goals/tasks
            const res = await axios.get(`${config.API_URL}/get_daily_tasks.php?user_id=${user.user_id}`);
            if (res.data.status === 'success') {
                setTasks(res.data.data.tasks);
                setStats(res.data.data.stats);

                const hasTasks = res.data.data.tasks.length > 0;
                setPlanExists(hasTasks);
                // Don't auto-hide wizard - let user control it
            }
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const loadSubjects = async () => {
        try {
            const response = await fetchSubjects(user.class_id);
            if (response.status === 'success') {
                setAvailableSubjects(response.data);
            }
        } catch (error) {
            console.log("Error loading subjects", error);
        }
    };

    const loadChapters = async (subjectId) => {
        setLoading(true);
        try {
            const response = await fetchChapters(subjectId);
            if (response.status === 'success') {
                setAvailableChapters(response.data);
                if (response.data.length > 0) {
                    setWizardStep(3); // Move to Chapter Step
                } else {
                    Alert.alert("No Chapters", "No chapters found for this subject.");
                }
            }
        } catch (error) {
            console.error("Error loading chapters", error);
            Alert.alert("Error", "Could not load chapters.");
        } finally {
            setLoading(false);
        }
    };

    const createPlan = async () => {
        // Validation
        if (selectedSubjects.length === 0) {
            Alert.alert("Step Missed", "Please select a subject.");
            return;
        }

        // For this new flow, Chapter selection is highly recommended but we could technically allow skipping?
        // But user request was specific. Let's enforce chapter selection if we are in this flow.
        if (wizardStep === 3 && !selectedChapter) {
            Alert.alert("Choose Chapter", "Please select a chapter to focus on.");
            return;
        }

        setLoading(true);
        try {
            const res = await axios.post(`${config.API_URL}/create_study_plan.php`, {
                user_id: user.user_id,
                focus_subjects: selectedSubjects,
                target_hours: selectedHours,
                chapter_id: selectedChapter, // Send Chapter ID
                goal_type: 'daily_habit' // Default
            });

            if (res.data.status === 'success') {
                // Reset wizard state
                setWizardStep(1);
                setSelectedSubjects([]);
                setSelectedChapter(null);
                setAvailableChapters([]);
                setShowWizard(false);

                setPlanExists(true);
                fetchData();
            }
        } catch (error) {
            console.error("Create Plan Error:", error);
            const msg = error.response?.data?.message || error.message || "Failed to create plan.";
            Alert.alert("Plan Creation Failed", msg);
        } finally {
            setLoading(false);
        }
    };

    // --- Navigation Logic ---
    const handleTaskNavigation = (task) => {
        if (task.status === 'completed') return;

        // If no chapter ID (e.g., generic revision), fallback to modal timer
        if (!task.chapter_id) {
            startTask(task);
            return;
        }

        const taskParams = {
            activeTask: {
                ...task,
                // Ensure ID is passed as number if needed
                task_id: parseInt(task.task_id),
                duration_minutes: parseInt(task.duration_minutes),
                xp_reward: parseInt(task.xp_reward)
            }
        };

        const chapterData = {
            chapter_id: task.chapter_id,
            chapter_name: task.title.split(': ')[1]?.split(' (')[0] || task.title,
            subject_id: task.subject_id || 0 // Fallback if missing, though ChapterContent mostly needs chapter_id
        };

        // Determine Destination
        switch (task.task_type) {
            case 'quiz':
                // Check if it's Flashcards based on Title (since type is shared)
                if (task.title.toLowerCase().includes('flashcard')) {
                    navigation.navigate('ChapterContent', {
                        chapter: chapterData,
                        initialTab: 'Flashcards',
                        ...taskParams
                    });
                } else {
                    // Default to MCQs
                    navigation.navigate('ChapterContent', {
                        chapter: chapterData,
                        initialTab: 'MCQs',
                        ...taskParams
                    });
                }
                break;

            case 'video':
                // If we had a specific video URL, we'd go to VideoPlayer.
                // But tasks usually just say "Watch: Chapter Name".
                // So go to ChapterContent -> Videos tab
                navigation.navigate('ChapterContent', {
                    chapter: chapterData,
                    initialTab: 'Videos',
                    ...taskParams
                });
                break;

            case 'revision':
                // Notes or Quick Revision
                if (task.title.toLowerCase().includes('quick revision')) {
                    navigation.navigate('ChapterContent', {
                        chapter: chapterData,
                        initialTab: 'QuickRevision',
                        ...taskParams
                    });
                } else {
                    navigation.navigate('ChapterContent', {
                        chapter: chapterData,
                        initialTab: 'Notes',
                        ...taskParams
                    });
                }
                break;

            default:
                // Fallback for custom types
                startTask(task);
        }
    };

    // --- Timer Logic (Modal - Fallback) ---
    useEffect(() => {
        let interval = null;
        if (isTimerRunning && timeLeft > 0) {
            interval = setInterval(() => {
                setTimeLeft((prev) => prev - 1);
            }, 1000);
        } else if (timeLeft === 0 && isTimerRunning) {
            completeTask();
        }
        return () => clearInterval(interval);
    }, [isTimerRunning, timeLeft]);

    const startTask = (task) => {
        setActiveTask(task);
        setTimeLeft(task.duration_minutes * 60); // Convert mins to seconds
        setTimerVisible(true);
        setIsTimerRunning(false); // User must press start in modal
    };

    const completeTask = async () => {
        setIsTimerRunning(false);
        setTimerVisible(false);

        try {
            await axios.post(`${config.API_URL}/update_task_status.php`, {
                user_id: user.user_id,
                task_id: activeTask.task_id,
                status: 'completed'
            });
            Alert.alert("Mission Complete!", `You earned ${activeTask.xp_reward} XP!`);
            fetchData(); // Refresh list
        } catch (error) {
            console.error(error);
        }
    };

    const formatTime = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
    };

    // --- Onboarding Wizard (Multi-Step: Time -> Subject -> Chapter) ---
    if (showWizard && !loading) {
        return (
            <View style={[styles.container, { backgroundColor: theme.background }]}>
                <LinearGradient colors={['#FF9800', '#F57C00']} style={styles.wizardHeader}>
                    {wizardStep > 1 && (
                        <TouchableOpacity
                            style={{ position: 'absolute', top: 50, left: 20, zIndex: 10 }}
                            onPress={() => setWizardStep(prev => prev - 1)}
                        >
                            <Ionicons name="arrow-back" size={28} color="white" />
                        </TouchableOpacity>
                    )}
                    {tasks.length > 0 && wizardStep === 1 && (
                        <TouchableOpacity
                            style={{ position: 'absolute', top: 50, right: 20, zIndex: 10, backgroundColor: 'rgba(255,255,255,0.2)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 15 }}
                            onPress={() => setShowWizard(false)}
                        >
                            <Text style={{ color: 'white', fontWeight: 'bold', fontSize: 12 }}>View Tasks</Text>
                        </TouchableOpacity>
                    )}
                    <Text style={styles.wizardTitle}>
                        {wizardStep === 1 ? "Pick Duration ⏳" : wizardStep === 2 ? "Pick Subject 📚" : "Pick Chapter 🎯"}
                    </Text>
                    <Text style={{ color: 'white', opacity: 0.9, marginTop: 5 }}>Step {wizardStep} of 3</Text>
                </LinearGradient>

                <ScrollView contentContainerStyle={{ padding: 20 }}>

                    {/* Step 1: Time Selection */}
                    {wizardStep === 1 && (
                        <View>
                            <Text style={[styles.label, { color: theme.text }]}>How much time do you have?</Text>
                            <View style={styles.hoursRow}>
                                {[
                                    { label: '15 min', val: 0.25 },
                                    { label: '30 min', val: 0.5 },
                                    { label: '45 min', val: 0.75 },
                                    { label: '60 min', val: 1.0 }
                                ].map(opt => (
                                    <TouchableOpacity
                                        key={opt.label}
                                        style={[styles.hourBtn, selectedHours === opt.val && styles.hourBtnSelected]}
                                        onPress={() => {
                                            setSelectedHours(opt.val);
                                            setWizardStep(2); // Auto-advance
                                        }}
                                    >
                                        <Text style={[styles.hourText, selectedHours === opt.val && styles.hourTextSelected]}>{opt.label}</Text>
                                    </TouchableOpacity>
                                ))}
                            </View>
                        </View>
                    )}

                    {/* Step 2: Subject Selection */}
                    {wizardStep === 2 && (
                        <View>
                            <Text style={[styles.label, { color: theme.text }]}>What do you want to study?</Text>
                            <View style={styles.subjectGrid}>
                                {availableSubjects.length > 0 ? (
                                    availableSubjects.map(subj => (
                                        <TouchableOpacity
                                            key={subj.subject_id}
                                            style={[styles.chip, selectedSubjects.includes(subj.subject_id) && styles.chipSelected]}
                                            onPress={() => {
                                                setSelectedSubjects([subj.subject_id]); // Single Select for this flow
                                                loadChapters(subj.subject_id);
                                            }}
                                        >
                                            <Text style={[styles.chipText, selectedSubjects.includes(subj.subject_id) && styles.chipTextSelected]}>
                                                {subj.subject_name}
                                            </Text>
                                        </TouchableOpacity>
                                    ))
                                ) : (
                                    <Text style={{ color: theme.textSecondary }}>Loading subjects...</Text>
                                )}
                            </View>
                        </View>
                    )}

                    {/* Step 3: Chapter Selection */}
                    {wizardStep === 3 && (
                        <View>
                            <Text style={[styles.label, { color: theme.text }]}>Select a Specific Chapter</Text>
                            {availableChapters.length > 0 ? (
                                availableChapters.map(ch => (
                                    <TouchableOpacity
                                        key={ch.chapter_id}
                                        style={[
                                            styles.chapterItem,
                                            { backgroundColor: theme.cardBg, borderColor: theme.border },
                                            selectedChapter === ch.chapter_id && styles.chapterItemSelected
                                        ]}
                                        onPress={() => setSelectedChapter(ch.chapter_id)}
                                    >
                                        <Text style={[
                                            styles.chapterText,
                                            { color: theme.text },
                                            selectedChapter === ch.chapter_id && styles.chapterTextSelected
                                        ]}>
                                            {ch.chapter_name}
                                        </Text>
                                        {selectedChapter === ch.chapter_id && <Ionicons name="checkmark-circle" size={24} color="white" />}
                                    </TouchableOpacity>
                                ))
                            ) : (
                                <Text style={{ color: theme.textSecondary, fontStyle: 'italic' }}>No chapters found.</Text>
                            )}

                            <TouchableOpacity style={styles.createBtn} onPress={createPlan}>
                                <Text style={styles.createBtnText}>Start My Session 🚀</Text>
                            </TouchableOpacity>
                        </View>
                    )}

                </ScrollView>
            </View>
        );
    }

    // --- Main Dashboard ---
    return (
        <View style={[styles.container, { backgroundColor: theme.background }]}>
            {/* Header */}
            <LinearGradient colors={['#6200EA', '#7C4DFF']} style={styles.header}>
                <View style={styles.headerTop}>
                    <TouchableOpacity onPress={() => navigation.goBack()}>
                        <Ionicons name="arrow-back" size={24} color="#fff" />
                    </TouchableOpacity>
                    <StreakFlame streak={stats.current_streak} />
                </View>
                <LevelBadge level={stats.level} xp={stats.total_xp} theme={theme} />
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 15 }}>
                    <Text style={styles.greeting}>Today's Missions</Text>
                    <TouchableOpacity
                        style={{ backgroundColor: 'rgba(255,255,255,0.2)', paddingHorizontal: 15, paddingVertical: 8, borderRadius: 20 }}
                        onPress={() => {
                            setWizardStep(1);
                            setSelectedSubjects([]);
                            setSelectedChapter(null);
                            setAvailableChapters([]);
                            setShowWizard(true);
                        }}
                    >
                        <Text style={{ color: 'white', fontWeight: 'bold', fontSize: 14 }}>+ New Session</Text>
                    </TouchableOpacity>
                </View>
            </LinearGradient>

            {/* Task List */}
            {loading ? (
                <ActivityIndicator size="large" color={theme.primary} style={{ marginTop: 50 }} />
            ) : (
                <ScrollView contentContainerStyle={styles.taskList}>
                    {tasks.length === 0 ? (
                        <View style={styles.emptyState}>
                            <Text style={{ color: theme.textSecondary }}>No missions for today!</Text>
                            <TouchableOpacity onPress={() => setShowWizard(true)}><Text style={{ color: theme.primary }}>Create New Plan</Text></TouchableOpacity>
                        </View>
                    ) : (
                        tasks.map((task, index) => (
                            <TaskCard
                                key={index}
                                task={task}
                                onPress={() => handleTaskNavigation(task)} // Use new navigation handler
                                theme={theme}
                            />
                        ))
                    )}
                </ScrollView>
            )}

            {/* Focus Timer Modal (Fallback) */}
            <Modal visible={timerVisible} animationType="slide" transparent={true}>
                <View style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <Text style={styles.timerTitle}>{activeTask?.title}</Text>
                        <Text style={styles.timerClock}>{formatTime(timeLeft)}</Text>

                        <View style={styles.timerControls}>
                            {!isTimerRunning ? (
                                <TouchableOpacity style={styles.startBtn} onPress={() => setIsTimerRunning(true)}>
                                    <Text style={styles.btnText}>START FOCUS</Text>
                                </TouchableOpacity>
                            ) : (
                                <TouchableOpacity style={styles.pauseBtn} onPress={() => setIsTimerRunning(false)}>
                                    <Text style={styles.btnText}>PAUSE</Text>
                                </TouchableOpacity>
                            )}

                            <TouchableOpacity style={styles.giveUpBtn} onPress={() => setTimerVisible(false)}>
                                <Text style={[styles.btnText, { color: '#666' }]}>GIVE UP</Text>
                            </TouchableOpacity>

                            {/* Dev Cheat Button */}
                            <TouchableOpacity onPress={completeTask} style={{ position: 'absolute', top: -40, right: 0 }}>
                                <Text style={{ fontSize: 10, color: '#ccc' }}>DEV: COMPLETE</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    header: { padding: 20, paddingTop: 40, borderBottomLeftRadius: 30, borderBottomRightRadius: 30, elevation: 5 },
    headerTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 15 },
    greeting: { fontSize: 22, fontWeight: 'bold', color: '#fff', marginTop: 15 },

    levelBadge: { padding: 10, borderRadius: 15, flexDirection: 'row', alignItems: 'center' },
    levelCircle: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#FFD700', justifyContent: 'center', alignItems: 'center', marginRight: 10 },
    levelText: { fontWeight: 'bold', color: '#333' },
    xpBarContainer: { flex: 1 },
    xpText: { fontSize: 12, marginBottom: 5 },
    xpTrack: { height: 8, backgroundColor: '#E0E0E0', borderRadius: 4, overflow: 'hidden' },
    xpFill: { height: '100%', backgroundColor: '#4CAF50' },

    streakContainer: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.2)', padding: 5, borderRadius: 20, paddingHorizontal: 12 },
    streakText: { color: '#fff', fontWeight: 'bold', marginLeft: 5, fontSize: 16 },

    taskList: { padding: 20 },
    taskCard: { padding: 15, borderRadius: 16, marginBottom: 15, flexDirection: 'row', alignItems: 'center', elevation: 2 },
    taskIcon: { width: 50, height: 50, borderRadius: 25, justifyContent: 'center', alignItems: 'center', marginRight: 15 },
    taskInfo: { flex: 1 },
    taskSubject: { fontSize: 12, fontWeight: '600', textTransform: 'uppercase' },
    taskTitle: { fontSize: 16, fontWeight: 'bold', marginBottom: 5 },
    taskMeta: { flexDirection: 'row', alignItems: 'center' },
    taskMetaText: { marginLeft: 5, fontSize: 12, marginRight: 10 },
    xpBadge: { fontSize: 12, fontWeight: 'bold' },
    completedBadge: { position: 'absolute', right: 10, top: 10, backgroundColor: '#E8F5E9', padding: 4, borderRadius: 4 },
    completedText: { fontSize: 10, color: '#2E7D32', fontWeight: 'bold' },

    // Wizard
    wizardHeader: { height: 200, justifyContent: 'center', alignItems: 'center', borderBottomLeftRadius: 50 },
    wizardTitle: { fontSize: 24, fontWeight: 'bold', color: '#fff' },
    label: { fontSize: 18, fontWeight: 'bold', marginTop: 25, marginBottom: 15 },
    subjectGrid: { flexDirection: 'row', flexWrap: 'wrap' },
    chip: { paddingHorizontal: 20, paddingVertical: 10, borderRadius: 20, backgroundColor: '#eee', marginRight: 10, marginBottom: 10 },
    chipSelected: { backgroundColor: '#FF9800' },
    chipText: { color: '#333' },
    chipTextSelected: { color: '#fff', fontWeight: 'bold' },
    hoursRow: { flexDirection: 'row', justifyContent: 'space-between' },
    hourBtn: { flex: 1, padding: 15, alignItems: 'center', backgroundColor: '#eee', marginHorizontal: 5, borderRadius: 10 },
    hourBtnSelected: { backgroundColor: '#6200EA' },
    hourText: { color: '#333' },
    hourTextSelected: { color: '#fff', fontWeight: 'bold' },
    createBtn: { backgroundColor: '#00C853', padding: 18, borderRadius: 15, marginTop: 40, alignItems: 'center', elevation: 5 },
    createBtnText: { color: '#fff', fontWeight: 'bold', fontSize: 18 },

    goalTypeRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 20 },
    goalTypeBtn: { flex: 1, backgroundColor: '#eee', padding: 15, borderRadius: 12, alignItems: 'center', marginHorizontal: 5 },
    goalTypeBtnSelected: { backgroundColor: '#FF9800' },
    goalTypeText: { fontWeight: 'bold', marginTop: 5, color: '#333' },
    goalTypeSub: { fontSize: 10, color: '#666', marginTop: 2 },
    dateChip: { backgroundColor: '#eee', padding: 10, borderRadius: 10, marginRight: 10, alignItems: 'center', minWidth: 80 },
    dateChipSelected: { backgroundColor: '#6200EA' },

    // Modal
    modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.8)', justifyContent: 'center', alignItems: 'center' },
    modalContent: { width: '85%', backgroundColor: '#fff', borderRadius: 20, padding: 30, alignItems: 'center' },
    timerTitle: { fontSize: 20, fontWeight: 'bold', marginBottom: 20, textAlign: 'center' },
    timerClock: { fontSize: 60, fontWeight: 'bold', color: '#6200EA', marginBottom: 40 },
    timerControls: { width: '100%' },
    startBtn: { backgroundColor: '#6200EA', padding: 15, borderRadius: 10, alignItems: 'center', marginBottom: 10 },
    pauseBtn: { backgroundColor: '#FF9800', padding: 15, borderRadius: 10, alignItems: 'center', marginBottom: 10 },
    giveUpBtn: { padding: 15, alignItems: 'center' },
    btnText: { color: '#fff', fontWeight: 'bold', fontSize: 16 },

    // Wizard Steps
    chapterItem: { flex: 1, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 15, borderRadius: 12, marginBottom: 10, borderWidth: 2, borderColor: 'transparent' },
    chapterItemSelected: { borderColor: '#4CAF50', backgroundColor: '#E8F5E9' },
    chapterText: { fontSize: 16, fontWeight: '600' },
    chapterTextSelected: { color: '#2E7D32', fontWeight: 'bold' }
});

export default StudyPlannerScreen;
