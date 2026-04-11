import React, { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import {
    View, Text, StyleSheet, ScrollView, TouchableOpacity,
    ActivityIndicator, Dimensions, Alert, Platform,
    StatusBar, Animated
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useFocusEffect } from '@react-navigation/native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import config from '../api/config';
import axios from 'axios';
import { InteractionManager, FlatList } from 'react-native';
import DateTimePicker from '@react-native-community/datetimepicker';
import { scheduleSmartStudyNotifications } from '../utils/studyNotificationHelper';

const { width } = Dimensions.get('window');

// ─── Task Type Config ────────────────────────────────────────────────────────
const TASK_CONFIG = {
    video:     { color: '#7C3AED', gradColors: ['#7C3AED','#A855F7'], icon: 'play-circle',       label: 'Video',   bg: '#F3E8FF' },
    quiz:      { color: '#EA580C', gradColors: ['#EA580C','#F97316'], icon: 'medal',              label: 'Quiz',    bg: '#FFF7ED' },
    notes:     { color: '#0284C7', gradColors: ['#0284C7','#38BDF8'], icon: 'document-text',      label: 'Notes',   bg: '#E0F2FE' },
    flashcard: { color: '#BE185D', gradColors: ['#BE185D','#EC4899'], icon: 'layers',             label: 'Cards',   bg: '#FCE7F3' },
    mega:      { color: '#DC2626', gradColors: ['#DC2626','#F97316'], icon: 'flash',              label: 'BLITZ',   bg: '#FEF2F2' },
    revision:  { color: '#059669', gradColors: ['#059669','#34D399'], icon: 'refresh-circle',     label: 'Review',  bg: '#ECFDF5' },
    default:   { color: '#475569', gradColors: ['#475569','#64748B'], icon: 'star',               label: 'Task',    bg: '#F8FAFC' },
};
const getTaskCfg = (type, isDone) => {
    if (isDone) return { color: '#16A34A', gradColors: ['#16A34A','#4ADE80'], icon: 'checkmark-circle', label: 'Done', bg: '#F0FDF4' };
    return TASK_CONFIG[type] || TASK_CONFIG.default;
};

// ─── Animated Task Tile ──────────────────────────────────────────────────────
const TaskTile = React.memo(({ task, index, onPress }) => {
    const scale = useRef(new Animated.Value(0.92)).current;
    const opacity = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        Animated.parallel([
            Animated.spring(scale,   { toValue: 1, delay: index * 60, useNativeDriver: true, tension: 80 }),
            Animated.timing(opacity, { toValue: 1, delay: index * 60, duration: 280, useNativeDriver: true }),
        ]).start();
    }, []);

    const isDone = task.status === 'completed';
    const isMega = task.task_type === 'mega';
    const cfg = getTaskCfg(task.task_type, isDone);

    // Clean chapter name (strip prefix like "Watch: ")
    const cleanTitle = task.title.replace(/^(Watch|Read Notes|Notes|Quiz|Cards|Flashcards|Read|Pract)[:\s]+/i, '').trim() || task.title;

    if (isMega && !isDone) {
        return (
            <Animated.View style={{ transform: [{ scale }], opacity }}>
                <TouchableOpacity onPress={onPress} activeOpacity={0.88}>
                    <LinearGradient
                        colors={['#831843', '#be185d', '#e11d48']}
                        start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }}
                        style={styles.megaTileCard}
                    >
                        <View style={styles.megaInner}>
                            <View style={styles.megaIconWrap}>
                                <Ionicons name="flash" size={28} color="white" />
                            </View>
                            <View style={{ flex: 1, marginLeft: 14 }}>
                                <Text style={styles.megaLabel}>⚡ MEGA REVISION BLITZ</Text>
                                <Text style={styles.megaTitle} numberOfLines={2}>
                                    {task.title.replace(/^Mega Revision Blitz[:\s]*/i, '')}
                                </Text>
                                <View style={styles.megaFooter}>
                                    <View style={styles.megaXpBadge}>
                                        <Text style={styles.megaXpText}>+{task.xp_reward} XP</Text>
                                    </View>
                                    <Text style={styles.megaTime}>⏱ {task.duration_minutes} min Exam</Text>
                                </View>
                            </View>
                            <Ionicons name="chevron-forward" size={20} color="rgba(255,255,255,0.6)" />
                        </View>
                    </LinearGradient>
                </TouchableOpacity>
            </Animated.View>
        );
    }

    return (
        <Animated.View style={{ transform: [{ scale }], opacity }}>
            <TouchableOpacity
                onPress={onPress}
                activeOpacity={0.88}
                style={[
                    styles.taskTile,
                    { borderLeftColor: cfg.color },
                    isDone && styles.taskTileDone,
                ]}
            >
                {/* Icon circle */}
                <View style={[styles.taskIconCircle, { backgroundColor: isDone ? '#F0FDF4' : cfg.bg }]}>
                    <Ionicons name={cfg.icon} size={20} color={cfg.color} />
                </View>

                {/* Content */}
                <View style={styles.taskContent}>
                    <View style={styles.taskTopRow}>
                        <View style={[styles.taskTypePill, { backgroundColor: cfg.color + '18' }]}>
                            <Text style={[styles.taskTypeText, { color: cfg.color }]}>{cfg.label}</Text>
                        </View>
                        {task.xp_reward > 0 && !isDone && (
                            <View style={styles.xpPill}>
                                <Ionicons name="star" size={9} color="#F59E0B" />
                                <Text style={styles.xpPillText}>+{task.xp_reward}</Text>
                            </View>
                        )}
                    </View>
                    <Text style={[styles.taskTitle, isDone && styles.taskTitleDone]} numberOfLines={2}>
                        {cleanTitle}
                    </Text>
                    <Text style={[styles.taskSubject, { color: cfg.color + 'BB' }]}>
                        {task.subject}
                    </Text>
                </View>

                {/* Right arrow / check */}
                <Ionicons
                    name={isDone ? 'checkmark-circle' : 'chevron-forward-circle-outline'}
                    size={isDone ? 26 : 22}
                    color={isDone ? '#16A34A' : cfg.color + '70'}
                />
            </TouchableOpacity>
        </Animated.View>
    );
});

// ─── Main Screen ─────────────────────────────────────────────────────────────
const StudyPlannerScreen = ({ user, navigation }) => {
    const { theme } = useTheme();
    const insets = useSafeAreaInsets();

    const [loading, setLoading]           = useState(true);
    const [isConfigured, setIsConfigured] = useState(false);
    const [wizardStep, setWizardStep]     = useState(1);
    const [roadmap, setRoadmap]           = useState([]);

    const [examDate, setExamDate]         = useState(new Date());
    const [showDatePicker, setShowDatePicker] = useState(false);

    const [availableSubjects, setAvailableSubjects]   = useState([]);
    const [selectedSubjects, setSelectedSubjects]     = useState([]);
    const [allChapters, setAllChapters]               = useState([]);
    const [selectedChapters, setSelectedChapters]     = useState([]);
    const [loadingChapters, setLoadingChapters]       = useState(false);

    // Memoized derived stats — avoid recalculating on every render
    const { allTasks, doneTasks, pct, daysLeft } = useMemo(() => {
        const allTasks  = roadmap.flatMap(d => d.tasks);
        const doneTasks = allTasks.filter(t => t.status === 'completed');
        const pct       = allTasks.length > 0 ? Math.round((doneTasks.length / allTasks.length) * 100) : 0;
        const daysLeft  = examDate instanceof Date && !isNaN(examDate)
            ? Math.max(0, Math.ceil((examDate - new Date()) / (1000 * 60 * 60 * 24)))
            : 0;
        return { allTasks, doneTasks, pct, daysLeft };
    }, [roadmap, examDate]);

    useFocusEffect(
        useCallback(() => {
            if (!user || !user.user_id) return;
            const task = InteractionManager.runAfterInteractions(() => {
                checkExistingPlan();
                fetchSyllabusInfo();
            });
            return () => task.cancel();
        }, [user])
    );

    const checkExistingPlan = async () => {
        if (!user || !user.user_id) return;
        try {
            // 1. Get configuration status and exam date
            const statusRes = await axios.get(`${config.API_URL}/get_study_status.php?user_id=${user.user_id}`);
            let configured = false;
            let fetchedExamDate = new Date();

            if (statusRes.data.status === 'success' && statusRes.data.is_configured) {
                configured = true;
                if (statusRes.data.exam_date && statusRes.data.exam_date !== '0000-00-00' && statusRes.data.exam_date !== '1970-01-01') {
                    fetchedExamDate = new Date(statusRes.data.exam_date);
                }
            }
            
            setExamDate(fetchedExamDate);
            setIsConfigured(configured);

            if (configured) {
                // 2. Fetch the roadmap (including historical context for notifications)
                const roadmapRes = await axios.get(`${config.API_URL}/get_roadmap.php?user_id=${user.user_id}&include_past=1`);
                if (roadmapRes.data.status === 'success' && roadmapRes.data.data) {
                    const fullRoadmap = roadmapRes.data.data;
                    setRoadmap(fullRoadmap);

                    // Notifications: Extract today and yesterday from THIS ALREADY FETCHED data
                    const todayStr = new Date().toISOString().split('T')[0];
                    const yesterdayDate = new Date();
                    yesterdayDate.setDate(yesterdayDate.getDate() - 1);
                    const yesterdayStr = yesterdayDate.toISOString().split('T')[0];

                    let todayTasks = [], yesterdayTasks = [];
                    fullRoadmap.forEach(d => {
                        if (d.date === todayStr) todayTasks = d.tasks;
                        if (d.date === yesterdayStr) yesterdayTasks = d.tasks;
                    });
                    scheduleSmartStudyNotifications(todayTasks, yesterdayTasks);
                }
            }
        } catch (error) {
            console.error('Error in checkExistingPlan:', error);
            setIsConfigured(false);
        } finally {
            setLoading(false);
        }
    };

    // This function is called after plan setup or explicit date change to ensure roadmap is balanced
    const fetchAndRedistributeRoadmap = async () => {
        if (!user || !user.user_id) return;
        setLoading(true);
        try {
            await axios.post(`${config.API_URL}/redistribute_tasks.php`, { user_id: user.user_id });
            const res = await axios.get(`${config.API_URL}/get_roadmap.php?user_id=${user.user_id}&include_past=1`);
            if (res.data.status === 'success') {
                setRoadmap(res.data.data);
                
                const todayStr = new Date().toISOString().split('T')[0];
                const yesterdayDate = new Date();
                yesterdayDate.setDate(yesterdayDate.getDate() - 1);
                const yesterdayStr = yesterdayDate.toISOString().split('T')[0];

                let todayTasks = [], yesterdayTasks = [];
                res.data.data.forEach(d => {
                    if (d.date === todayStr) todayTasks = d.tasks;
                    if (d.date === yesterdayStr) yesterdayTasks = d.tasks;
                });
                scheduleSmartStudyNotifications(todayTasks, yesterdayTasks);
            }
        } catch (error) {
            console.error('Error redistributing roadmap:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchSyllabusInfo = async () => {
        if (!user || !user.class_id) return;
        try {
            const response = await axios.get(`${config.API_URL}/get_subjects.php?class_id=${user.class_id}`);
            if (response.data.status === 'success') setAvailableSubjects(response.data.data);
        } catch {}
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (selectedSubjects.length > 0) fetchSelectedChapters();
            else { setAllChapters([]); setSelectedChapters([]); }
        }, 350); // 350ms debounce
        return () => clearTimeout(timer);
    }, [selectedSubjects]);

    const fetchSelectedChapters = async () => {
        setLoadingChapters(true);
        try {
            const results = await Promise.all(
                selectedSubjects.map(sid => axios.get(`${config.API_URL}/get_chapters.php?light=1&subject_id=${sid}`))
            );
            let combined = [];
            results.forEach((res, i) => {
                if (res.data.status === 'success') {
                    const subName = availableSubjects.find(s => s.subject_id === selectedSubjects[i])?.subject_name;
                    combined = [...combined, ...res.data.data.map(ch => ({ ...ch, subject_name: subName }))];
                }
            });
            setAllChapters(combined);
            setSelectedChapters(combined.map(c => c.chapter_id));
        } catch {} finally { setLoadingChapters(false); }
    };

    const handleDateChange = (event, selectedDate) => {
        setShowDatePicker(false);
        if (selectedDate) {
            setExamDate(selectedDate);
            if (isConfigured) {
                setTimeout(() => {
                    Alert.alert('Update Roadmap?', 'Changing the exam date will regenerate your study plan. Continue?', [
                        { text: 'Cancel', style: 'cancel', onPress: checkExistingPlan },
                        { text: 'Update', onPress: () => updatePlanWithNewDate(selectedDate) },
                    ]);
                }, 400);
            }
        }
    };

    const updatePlanWithNewDate = async (newDate) => {
        if (selectedChapters.length === 0) {
            Alert.alert(
                'Action Required', 
                'To change your target exam date, we need to completely rebuild your custom study plan. Please go to "Edit Plan" to resubmit your syllabus with the new date.'
            );
            checkExistingPlan(); // Revert calendar visual
            return;
        }

        if (!user || !user.user_id) return;
        setLoading(true);
        try {
            const res = await axios.post(`${config.API_URL}/setup_syllabus_path.php`, {
                user_id: user.user_id,
                exam_date: newDate.toISOString().split('T')[0],
                subject_ids: selectedSubjects,
                chapter_ids: selectedChapters,
            });
            if (res.data.status === 'success') {
                checkExistingPlan();
            } else {
                Alert.alert('Error', res.data.message || 'Something went wrong.');
            }
        } catch (error) {
            Alert.alert('Error', error.response?.data?.message || error.message || 'Failed to connect to the server while updating the date.');
        } finally { setLoading(false); }
    };

    const toggleSubject = (id) => setSelectedSubjects(prev =>
        prev.includes(id) ? prev.filter(s => s !== id) : [...prev, id]
    );
    const toggleChapter = (id) => setSelectedChapters(prev =>
        prev.includes(id) ? prev.filter(c => c !== id) : [...prev, id]
    );

    const savePlan = async () => {
        if (selectedChapters.length === 0) {
            Alert.alert('Selection Required', 'Please select at least one chapter to build your roadmap.');
            return;
        }
        if (!user || !user.user_id) return;
        setLoading(true);
        try {
            const res = await axios.post(`${config.API_URL}/setup_syllabus_path.php`, {
                user_id: user.user_id,
                exam_date: examDate.toISOString().split('T')[0],
                subject_ids: selectedSubjects,
                chapter_ids: selectedChapters,
            });
            if (res.data.status === 'success') {
                setIsConfigured(true);
                setWizardStep(1);
                checkExistingPlan();
            } else {
                Alert.alert('Error', res.data.message || 'Something went wrong.');
            }
        } catch (error) {
            Alert.alert('Error', error.response?.data?.message || error.message || 'Failed to connect to server.');
        } finally {
            setLoading(false);
        }
    };

    const handleTaskPress = useCallback(async (task) => {
        if (task.status === 'completed') {
            Alert.alert('✅ Already Done!', 'You have mastered this session. Keep it up!');
            return;
        }

        if (task.task_type === 'mega' || task.title.includes('Mega Revision Blitz') || task.title.includes('Final Mega Blitz')) {
            setLoading(true);
            try {
                const medium = user?.medium || 'english';
                let url = `${config.API_URL}/get_mega_revision_mcqs.php?user_id=${user.user_id}&medium=${medium}`;
                if (task.chapter_ids) url += `&chapter_ids=${encodeURIComponent(task.chapter_ids)}`;
                const res = await axios.get(url);
                if (res.data.status === 'success' && res.data.data.length > 0) {
                    navigation.navigate('MyExamTest', {
                        questions: res.data.data,
                        totalQuestions: res.data.data.length,
                        subjectName: task.title || 'Mega Revision Blitz',
                        taskId: task.task_id,
                        source: 'study_planner',
                    });
                } else {
                    Alert.alert('No MCQs Found', res.data.message || 'No questions available yet. Complete some chapters first!');
                }
            } catch {
                Alert.alert('Error', 'Failed to load Blitz MCQs.');
            } finally {
                setLoading(false);
            }
            return;
        }

        const tabMap = { quiz: 'MCQs', video: 'Videos', flashcard: 'Flashcards', notes: 'Notes' };
        navigation.navigate('ChapterContent', {
            chapter: { 
                chapter_id: task.chapter_id, 
                chapter_name: task.chapter_name || task.title.split(': ').pop(), 
                subject_name: task.subject_name || task.subject || '' 
            },
            initialTab: tabMap[task.task_type] || 'Notes',
        });
    }, [navigation, user]);

    // ── Loading ──────────────────────────────────────────────────────────────
    if (loading) return (
        <LinearGradient colors={['#0f172a', '#1e1b4b']} style={styles.loadingScreen}>
            <ActivityIndicator size="large" color="#818CF8" />
            <Text style={styles.loadingText}>Building your Victory Pipeline…</Text>
        </LinearGradient>
    );

    // ── Configured: Roadmap View ─────────────────────────────────────────────
    if (isConfigured) {
        return (
            <View style={[styles.container, { backgroundColor: '#F1F5F9' }]}>
                <StatusBar barStyle="light-content" translucent backgroundColor="transparent" />

                {/* ── HEADER ── */}
                <LinearGradient
                    colors={['#1e1b4b', '#312e81', '#4338ca']}
                    start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }}
                    style={[styles.header, { paddingTop: insets.top + 12 }]}
                >
                    {/* Top row */}
                    <View style={styles.headerRow}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.headerBtn}>
                            <Ionicons name="chevron-back" size={22} color="white" />
                        </TouchableOpacity>
                        <View style={{ flex: 1, alignItems: 'center' }}>
                            <Text style={styles.headerTitle}>Victory Pipeline 🚀</Text>
                            <TouchableOpacity onPress={() => setShowDatePicker(true)} style={styles.headerDateRow}>
                                <Ionicons name="calendar-outline" size={11} color="rgba(255,255,255,0.65)" />
                                <Text style={styles.headerDateText}>
                                    Exam: {examDate instanceof Date && !isNaN(examDate)
                                        ? examDate.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
                                        : 'Set Date'}
                                </Text>
                            </TouchableOpacity>
                        </View>
                        <TouchableOpacity
                            onPress={() => Alert.alert('Reset Planner?', 'This will clear your current roadmap. Continue?', [
                                { text: 'Cancel', style: 'cancel' },
                                { text: 'Reset', style: 'destructive', onPress: () => setIsConfigured(false) },
                            ])}
                            style={styles.headerBtn}
                        >
                            <Ionicons name="refresh-outline" size={22} color="white" />
                        </TouchableOpacity>
                    </View>

                    {/* Stats pill row */}
                    <View style={styles.headerStats}>
                        <View style={styles.headerStatItem}>
                            <Text style={styles.headerStatVal}>{daysLeft}</Text>
                            <Text style={styles.headerStatLab}>Days Left</Text>
                        </View>
                        <View style={styles.headerStatDivider} />
                        <View style={styles.headerStatItem}>
                            <Text style={styles.headerStatVal}>{doneTasks.length}</Text>
                            <Text style={styles.headerStatLab}>Done</Text>
                        </View>
                        <View style={styles.headerStatDivider} />
                        <View style={styles.headerStatItem}>
                            <Text style={styles.headerStatVal}>{allTasks.length - doneTasks.length}</Text>
                            <Text style={styles.headerStatLab}>Pending</Text>
                        </View>
                        <View style={styles.headerStatDivider} />
                        <View style={styles.headerStatItem}>
                            <Text style={[styles.headerStatVal, { color: '#34D399' }]}>{pct}%</Text>
                            <Text style={styles.headerStatLab}>Mastery</Text>
                        </View>
                    </View>
                </LinearGradient>

                {/* ── PROGRESS CARD (floats over header) ── */}
                <View style={styles.progressFloatCard}>
                    <View style={styles.progressLabelRow}>
                        <Text style={styles.progressLabel}>Overall Progress</Text>
                        <Text style={styles.progressPct}>{pct}%</Text>
                    </View>
                    <View style={styles.progressTrack}>
                        <LinearGradient
                            colors={pct >= 80 ? ['#059669','#34D399'] : pct >= 50 ? ['#4338CA','#818CF8'] : ['#DC2626','#F97316']}
                            start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }}
                            style={[styles.progressFill, { width: `${Math.max(pct, 2)}%` }]}
                        />
                    </View>
                    <Text style={styles.progressSub}>
                        {doneTasks.length} of {allTasks.length} tasks completed
                    </Text>
                </View>

                {showDatePicker && (
                    <DateTimePicker
                        value={examDate instanceof Date && !isNaN(examDate) ? examDate : new Date()}
                        mode="date" display="default"
                        minimumDate={new Date()}
                        onChange={handleDateChange}
                    />
                )}

                {/* ── TIMELINE ── */}
                <FlatList
                    data={roadmap}
                    keyExtractor={(item) => item.date}
                    contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 100, paddingTop: 8 }}
                    showsVerticalScrollIndicator={false}
                    maxToRenderPerBatch={5}
                    windowSize={8}
                    removeClippedSubviews={Platform.OS === 'android'}
                    ListEmptyComponent={
                        <View style={styles.emptyState}>
                            <Text style={{ fontSize: 50 }}>📋</Text>
                            <Text style={styles.emptyTitle}>Roadmap is empty</Text>
                            <Text style={styles.emptySub}>All tasks may be complete or your exam date has passed.</Text>
                        </View>
                    }
                    renderItem={({ item: day, index: dayIndex }) => {
                        const isBlitzDay = day.tasks.length > 0 && day.tasks.every(t => t.task_type === 'mega');
                        const dayDone    = day.tasks.filter(t => t.status === 'completed').length;
                        const dayTotal   = day.tasks.length;
                        const isDayDone  = dayDone === dayTotal;

                        return (
                            <View style={styles.daySection}>
                                {/* Day header */}
                                <View style={styles.dayHeader}>
                                    <View style={[
                                        styles.dayDot,
                                        day.is_today  && styles.dayDotToday,
                                        isBlitzDay    && !day.is_today && styles.dayDotBlitz,
                                        isDayDone     && !day.is_today && styles.dayDotDone,
                                    ]}>
                                        {day.is_today ? (
                                            <Ionicons name="today" size={14} color="white" />
                                        ) : isBlitzDay ? (
                                            <Ionicons name="flash" size={14} color="white" />
                                        ) : isDayDone ? (
                                            <Ionicons name="checkmark" size={14} color="white" />
                                        ) : (
                                            <Text style={styles.dayDotText}>{dayIndex + 1}</Text>
                                        )}
                                    </View>

                                    <View style={{ flex: 1, marginLeft: 12 }}>
                                        <Text style={styles.dayName}>
                                            {day.is_today
                                                ? '📍 Today'
                                                : isBlitzDay
                                                    ? '⚡ Blitz Day'
                                                    : day.display_date.split(',')[0]}
                                        </Text>
                                        <Text style={styles.dayDate}>{day.display_date}</Text>
                                    </View>

                                    {/* Mini progress pill */}
                                    <View style={[
                                        styles.dayProgressPill,
                                        isDayDone && { backgroundColor: '#DCFCE7', borderColor: '#16A34A' }
                                    ]}>
                                        <Text style={[
                                            styles.dayProgressText,
                                            isDayDone && { color: '#16A34A' }
                                        ]}>
                                            {isDayDone ? '✓ Done' : `${dayDone}/${dayTotal}`}
                                        </Text>
                                    </View>
                                </View>

                                {/* Connector line */}
                                <View style={styles.connectorLine} />

                                {/* Tasks */}
                                <View style={styles.tasksContainer}>
                                    {day.tasks.map((task, tIndex) => (
                                        <TaskTile
                                            key={task.task_id}
                                            task={task}
                                            index={tIndex}
                                            onPress={() => handleTaskPress(task)}
                                        />
                                    ))}
                                </View>
                            </View>
                        );
                    }}
                />
            </View>
        );
    }

    // ── Setup Wizard ─────────────────────────────────────────────────────────
    return (
        <View style={{ flex: 1, backgroundColor: '#F1F5F9' }}>
            <StatusBar barStyle="light-content" translucent backgroundColor="transparent" />

            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ flexGrow: 1, paddingBottom: 80 }}>
                {/* Hero Banner */}
                <LinearGradient
                    colors={['#1e1b4b', '#312e81', '#4338ca']}
                    start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }}
                    style={[styles.heroBanner, { paddingTop: insets.top + 20 }]}
                >
                    <TouchableOpacity onPress={() => navigation.goBack()} style={[styles.heroBackBtn, { top: insets.top + 10 }]}>
                        <Ionicons name="chevron-back" size={22} color="white" />
                    </TouchableOpacity>

                    <View style={styles.heroIconRing}>
                        <LinearGradient colors={['#ffffff', '#e0e7ff']} style={styles.heroIconInner}>
                            <Ionicons name="rocket" size={36} color="#4338ca" />
                        </LinearGradient>
                    </View>
                    <Text style={styles.heroTitle}>Victory Pipeline</Text>
                    <Text style={styles.heroSub}>AI-powered roadmap from today to exam day</Text>

                    {/* Step pills */}
                    <View style={styles.stepRow}>
                        {['📅 Date', '📚 Subjects', '🚀 Launch'].map((label, i) => {
                            const stepNum = i + 1;
                            const isActive = wizardStep === stepNum;
                            const isDone   = wizardStep > stepNum;
                            return (
                                <View key={i} style={styles.stepItem}>
                                    <View style={[styles.stepCircle, isActive && styles.stepCircleActive, isDone && styles.stepCircleDone]}>
                                        {isDone
                                            ? <Ionicons name="checkmark" size={14} color="white" />
                                            : <Text style={[styles.stepNum, isActive && { color: 'white' }]}>{stepNum}</Text>
                                        }
                                    </View>
                                    <Text style={[styles.stepLabel, isActive && styles.stepLabelActive]}>{label}</Text>
                                </View>
                            );
                        })}
                    </View>
                </LinearGradient>

                {/* Wizard Card */}
                <View style={styles.wizardCard}>

                    {/* ── STEP 1: Date ── */}
                    {wizardStep === 1 && (
                        <View>
                            <Text style={styles.stepTitle}>When is your Exam? 🗓️</Text>
                            <Text style={styles.stepSub}>We'll build a smart day-by-day roadmap up to this date.</Text>

                            <TouchableOpacity style={styles.dateCard} onPress={() => setShowDatePicker(true)}>
                                <LinearGradient colors={['#4338ca','#6366f1']} style={styles.dateIconBox}>
                                    <Ionicons name="calendar" size={22} color="white" />
                                </LinearGradient>
                                <View style={{ flex: 1, marginLeft: 14 }}>
                                    <Text style={styles.dateCardLabel}>TARGET EXAM DATE</Text>
                                    <Text style={styles.dateCardValue}>
                                        {examDate instanceof Date && !isNaN(examDate)
                                            ? examDate.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
                                            : 'Tap to choose'}
                                    </Text>
                                </View>
                                <View style={styles.dateEditBtn}>
                                    <Text style={styles.dateEditText}>CHANGE</Text>
                                </View>
                            </TouchableOpacity>

                            {showDatePicker && (
                                <DateTimePicker
                                    value={examDate instanceof Date && !isNaN(examDate) ? examDate : new Date()}
                                    mode="date" display="default"
                                    minimumDate={new Date()}
                                    onChange={handleDateChange}
                                />
                            )}

                            <TouchableOpacity style={styles.primaryBtn} onPress={() => setWizardStep(2)}>
                                <LinearGradient colors={['#4338ca','#6366f1']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={styles.primaryBtnInner}>
                                    <Text style={styles.primaryBtnText}>Choose Subjects →</Text>
                                </LinearGradient>
                            </TouchableOpacity>
                        </View>
                    )}

                    {/* ── STEP 2: Subjects + Chapters ── */}
                    {wizardStep === 2 && (
                        <View>
                            <Text style={styles.stepTitle}>Select Your Subjects 📚</Text>
                            <Text style={styles.stepSub}>Pick the subjects you need to master for the exam.</Text>

                            {/* Subject chips */}
                            <View style={styles.chipWrap}>
                                {availableSubjects.map(s => {
                                    const active = selectedSubjects.includes(s.subject_id);
                                    return (
                                        <TouchableOpacity
                                            key={s.subject_id}
                                            style={[styles.subjectChip, active && styles.subjectChipActive]}
                                            onPress={() => toggleSubject(s.subject_id)}
                                        >
                                            {active && <Ionicons name="checkmark-circle" size={14} color="white" style={{ marginRight: 5 }} />}
                                            <Text style={[styles.subjectChipText, active && { color: 'white' }]}>{s.subject_name}</Text>
                                        </TouchableOpacity>
                                    );
                                })}
                            </View>

                            {/* Chapters */}
                            {selectedSubjects.length > 0 && (
                                <View style={styles.chaptersSection}>
                                    <View style={styles.chaptersHeader}>
                                        <View>
                                            <Text style={styles.chaptersTitle}>Curate Chapters 🛠️</Text>
                                            <Text style={styles.chaptersCount}>{selectedChapters.length} chapters selected</Text>
                                        </View>
                                        <TouchableOpacity
                                            onPress={() => setSelectedChapters(allChapters.map(c => c.chapter_id))}
                                            style={styles.selectAllBtn}
                                        >
                                            <Text style={styles.selectAllText}>Select All</Text>
                                        </TouchableOpacity>
                                    </View>

                                    {loadingChapters ? (
                                        <View style={{ padding: 30, alignItems: 'center' }}>
                                            <ActivityIndicator color="#4338ca" size="large" />
                                        </View>
                                    ) : (
                                        selectedSubjects.map(sid => {
                                            const subject = availableSubjects.find(s => s.subject_id === sid);
                                            const subChapters = allChapters.filter(ch => ch.subject_id === sid);
                                            if (!subChapters.length) return null;
                                            return (
                                                <View key={sid} style={styles.subjectGroup}>
                                                    <View style={styles.subjectGroupHeader}>
                                                        <View style={styles.subjectGroupDot} />
                                                        <Text style={styles.subjectGroupName}>{subject?.subject_name}</Text>
                                                    </View>
                                                    {subChapters.map(ch => {
                                                        const sel = selectedChapters.includes(ch.chapter_id);
                                                        return (
                                                            <TouchableOpacity
                                                                key={ch.chapter_id}
                                                                style={[styles.chapterRow, !sel && styles.chapterRowDim]}
                                                                onPress={() => toggleChapter(ch.chapter_id)}
                                                            >
                                                                <View style={[styles.checkbox, sel && styles.checkboxActive]}>
                                                                    {sel && <Ionicons name="checkmark" size={13} color="white" />}
                                                                </View>
                                                                <Text style={[styles.chapterText, sel && styles.chapterTextActive]}>
                                                                    {ch.chapter_name.toUpperCase()}
                                                                </Text>
                                                            </TouchableOpacity>
                                                        );
                                                    })}
                                                </View>
                                            );
                                        })
                                    )}
                                </View>
                            )}

                            <View style={styles.wizardFooter}>
                                <TouchableOpacity onPress={() => setWizardStep(1)} style={styles.backBtnWiz}>
                                    <Text style={styles.backBtnText}>← Back</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={styles.primaryBtnSm} onPress={() => setWizardStep(3)}>
                                    <LinearGradient colors={['#4338ca','#6366f1']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={styles.primaryBtnSmInner}>
                                        <Text style={styles.primaryBtnText}>Review Plan →</Text>
                                    </LinearGradient>
                                </TouchableOpacity>
                            </View>
                        </View>
                    )}

                    {/* ── STEP 3: Launch ── */}
                    {wizardStep === 3 && (
                        <View style={{ alignItems: 'center' }}>
                            <LinearGradient colors={['#ECFDF5','#D1FAE5']} style={styles.launchIcon}>
                                <Ionicons name="checkmark-done-circle" size={72} color="#059669" />
                            </LinearGradient>

                            <Text style={styles.stepTitle}>Ready to Launch! 🚀</Text>
                            <Text style={styles.stepSub}>Your personalized roadmap is set. Get ready to dominate your exam!</Text>

                            {/* Summary card */}
                            <View style={styles.summaryCard}>
                                <View style={styles.summaryRow}>
                                    <View style={styles.summaryIcon}>
                                        <Ionicons name="calendar" size={18} color="#4338ca" />
                                    </View>
                                    <View style={{ flex: 1 }}>
                                        <Text style={styles.summaryLabel}>EXAM DATE</Text>
                                        <Text style={styles.summaryValue}>
                                            {examDate instanceof Date && !isNaN(examDate)
                                                ? examDate.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long' })
                                                : '—'}
                                        </Text>
                                    </View>
                                </View>
                                <View style={styles.summaryDivider} />
                                <View style={styles.summaryRow}>
                                    <View style={styles.summaryIcon}>
                                        <Ionicons name="book" size={18} color="#4338ca" />
                                    </View>
                                    <View style={{ flex: 1 }}>
                                        <Text style={styles.summaryLabel}>CHAPTERS SELECTED</Text>
                                        <Text style={styles.summaryValue}>{selectedChapters.length} chapters across {selectedSubjects.length} subject{selectedSubjects.length !== 1 ? 's' : ''}</Text>
                                    </View>
                                </View>
                                <View style={styles.summaryDivider} />
                                <View style={styles.summaryRow}>
                                    <View style={styles.summaryIcon}>
                                        <Ionicons name="flash" size={18} color="#DC2626" />
                                    </View>
                                    <View style={{ flex: 1 }}>
                                        <Text style={styles.summaryLabel}>BLITZ EXAMS</Text>
                                        <Text style={styles.summaryValue}>After every 2 chapters + Final 3-day Blitz</Text>
                                    </View>
                                </View>
                            </View>

                            <TouchableOpacity style={styles.launchBtn} onPress={savePlan}>
                                <LinearGradient colors={['#059669','#10B981']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={styles.launchBtnInner}>
                                    <Ionicons name="rocket" size={20} color="white" style={{ marginRight: 10 }} />
                                    <Text style={styles.launchBtnText}>CREATE MY VICTORY PIPELINE</Text>
                                </LinearGradient>
                            </TouchableOpacity>

                            <TouchableOpacity onPress={() => setWizardStep(2)} style={{ marginTop: 18 }}>
                                <Text style={styles.adjustText}>← Adjust Selection</Text>
                            </TouchableOpacity>
                        </View>
                    )}
                </View>
            </ScrollView>
        </View>
    );
};

// ─── Styles ──────────────────────────────────────────────────────────────────
const styles = StyleSheet.create({
    container:    { flex: 1 },
    loadingScreen: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    loadingText:   { color: '#A5B4FC', marginTop: 16, fontSize: 14, fontFamily: 'NotoSans-Regular' },

    // ── Roadmap Header ──
    header: {
        paddingHorizontal: 20,
        paddingBottom: 20,
        borderBottomLeftRadius: 0,
        borderBottomRightRadius: 0,
    },
    headerRow:    { flexDirection: 'row', alignItems: 'center', marginBottom: 16 },
    headerBtn:    { width: 38, height: 38, borderRadius: 19, backgroundColor: 'rgba(255,255,255,0.12)', justifyContent: 'center', alignItems: 'center' },
    headerTitle:  { color: 'white', fontSize: 18, fontFamily: 'NotoSans-Bold', textAlign: 'center' },
    headerDateRow: { flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 2 },
    headerDateText: { color: 'rgba(255,255,255,0.6)', fontSize: 11, fontFamily: 'NotoSans-Regular' },

    headerStats:       { flexDirection: 'row', backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 16, paddingVertical: 12, paddingHorizontal: 8 },
    headerStatItem:    { flex: 1, alignItems: 'center' },
    headerStatVal:     { color: 'white', fontSize: 20, fontFamily: 'NotoSans-Bold' },
    headerStatLab:     { color: 'rgba(255,255,255,0.55)', fontSize: 10, fontFamily: 'NotoSans-Regular', marginTop: 2 },
    headerStatDivider: { width: 1, backgroundColor: 'rgba(255,255,255,0.15)', marginHorizontal: 4 },

    // ── Progress Float Card ──
    progressFloatCard: {
        marginHorizontal: 16,
        marginTop: 12,
        backgroundColor: 'rgba(255,255,255,0.95)',
        borderRadius: 24,
        padding: 20,
        elevation: 8,
        shadowColor: '#4338ca',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.12,
        shadowRadius: 16,
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.8)',
    },
    progressLabelRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 },
    progressLabel:    { fontSize: 13, fontFamily: 'NotoSans-Bold', color: '#1e293b' },
    progressPct:      { fontSize: 18, fontFamily: 'NotoSans-Bold', color: '#4338ca' },
    progressTrack:    { height: 10, backgroundColor: '#E2E8F0', borderRadius: 5, overflow: 'hidden', marginBottom: 8 },
    progressFill:     { height: '100%', borderRadius: 5 },
    progressSub:      { fontSize: 11, color: '#94A3B8', fontFamily: 'NotoSans-Regular' },

    // ── Timeline ──
    daySection:       { marginBottom: 8 },
    dayHeader:        { flexDirection: 'row', alignItems: 'center', marginBottom: 0, paddingHorizontal: 2 },
    dayDot:           { width: 34, height: 34, borderRadius: 17, backgroundColor: '#CBD5E1', justifyContent: 'center', alignItems: 'center' },
    dayDotToday:      { backgroundColor: '#4338CA' },
    dayDotBlitz:      { backgroundColor: '#DC2626' },
    dayDotDone:       { backgroundColor: '#16A34A' },
    dayDotText:       { color: 'white', fontSize: 12, fontFamily: 'NotoSans-Bold' },
    dayName:          { fontSize: 14, fontFamily: 'NotoSans-Bold', color: '#1e293b' },
    dayDate:          { fontSize: 11, color: '#94A3B8', fontFamily: 'NotoSans-Regular', marginTop: 1 },
    dayProgressPill:  { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 12, backgroundColor: '#F1F5F9', borderWidth: 1, borderColor: '#E2E8F0' },
    dayProgressText:  { fontSize: 11, fontFamily: 'NotoSans-Bold', color: '#64748B' },
    connectorLine:    { width: 2, height: 10, backgroundColor: '#E2E8F0', marginLeft: 16, marginVertical: 2 },
    tasksContainer:   { paddingLeft: 46, paddingRight: 0 },

    // ── Task Tile ──
    taskTile: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'white',
        borderRadius: 20,
        padding: 16,
        marginBottom: 12,
        borderLeftWidth: 5,
        elevation: 4,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.06,
        shadowRadius: 8,
    },
    taskTileDone: { backgroundColor: '#DCFCE7', borderLeftColor: '#16A34A' },
    taskIconCircle: { width: 40, height: 40, borderRadius: 12, justifyContent: 'center', alignItems: 'center' },
    taskContent:    { flex: 1, marginLeft: 12 },
    taskTopRow:     { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 3 },
    taskTypePill:   { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 6 },
    taskTypeText:   { fontSize: 9, fontFamily: 'NotoSans-Bold', letterSpacing: 0.5, textTransform: 'uppercase' },
    xpPill:         { flexDirection: 'row', alignItems: 'center', gap: 2, backgroundColor: '#FFFBEB', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6 },
    xpPillText:     { fontSize: 9, fontFamily: 'NotoSans-Bold', color: '#D97706' },
    taskTitle:      { fontSize: 14, fontFamily: 'NotoSans-Bold', color: '#1e293b', lineHeight: 19, marginBottom: 2 },
    taskTitleDone:  { textDecorationLine: 'line-through', color: '#94A3B8' },
    taskSubject:    { fontSize: 11, fontFamily: 'NotoSans-Regular' },

    // ── Mega Tile ──
    megaTileCard: {
        borderRadius: 22,
        marginBottom: 14,
        elevation: 8,
        shadowColor: '#be185d',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.35,
        shadowRadius: 12,
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.2)',
    },
    megaInner:   { flexDirection: 'row', alignItems: 'center', padding: 16 },
    megaIconWrap: { width: 48, height: 48, borderRadius: 14, backgroundColor: 'rgba(255,255,255,0.15)', justifyContent: 'center', alignItems: 'center' },
    megaLabel:   { fontSize: 10, color: 'rgba(255,255,255,0.75)', fontFamily: 'NotoSans-Bold', letterSpacing: 1, marginBottom: 4 },
    megaTitle:   { fontSize: 15, color: 'white', fontFamily: 'NotoSans-Bold', lineHeight: 20, marginBottom: 8 },
    megaFooter:  { flexDirection: 'row', alignItems: 'center', gap: 10 },
    megaXpBadge: { backgroundColor: 'rgba(255,255,255,0.2)', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 8 },
    megaXpText:  { fontSize: 11, color: 'white', fontFamily: 'NotoSans-Bold' },
    megaTime:    { fontSize: 11, color: 'rgba(255,255,255,0.7)', fontFamily: 'NotoSans-Regular' },

    // ── Empty State ──
    emptyState: { alignItems: 'center', paddingTop: 60 },
    emptyTitle: { fontSize: 18, fontFamily: 'NotoSans-Bold', color: '#1e293b', marginTop: 12 },
    emptySub:   { fontSize: 13, color: '#94A3B8', textAlign: 'center', marginTop: 8, fontFamily: 'NotoSans-Regular', paddingHorizontal: 20 },

    // ── Setup Hero ──
    heroBanner: {
        paddingHorizontal: 24,
        paddingBottom: 30,
        alignItems: 'center',
    },
    heroBackBtn: { position: 'absolute', left: 16, width: 36, height: 36, borderRadius: 18, backgroundColor: 'rgba(255,255,255,0.12)', justifyContent: 'center', alignItems: 'center' },
    heroIconRing: { width: 80, height: 80, borderRadius: 40, backgroundColor: 'white', elevation: 10, shadowColor: '#000', shadowOpacity: 0.2, shadowRadius: 8, shadowOffset: { width: 0, height: 4 }, justifyContent: 'center', alignItems: 'center', marginBottom: 14 },
    heroIconInner: { width: '100%', height: '100%', borderRadius: 40, justifyContent: 'center', alignItems: 'center' },
    heroTitle:  { color: 'white', fontSize: 24, fontFamily: 'NotoSans-Bold', marginBottom: 6 },
    heroSub:    { color: 'rgba(255,255,255,0.7)', fontSize: 13, fontFamily: 'NotoSans-Regular', textAlign: 'center', marginBottom: 24 },

    // Step pills in hero
    stepRow:    { flexDirection: 'row', gap: 16, alignItems: 'center' },
    stepItem:   { alignItems: 'center' },
    stepCircle: { width: 30, height: 30, borderRadius: 15, backgroundColor: 'rgba(255,255,255,0.12)', justifyContent: 'center', alignItems: 'center', marginBottom: 5 },
    stepCircleActive: { backgroundColor: 'white' },
    stepCircleDone:   { backgroundColor: '#10B981' },
    stepNum:         { color: 'rgba(255,255,255,0.6)', fontSize: 12, fontFamily: 'NotoSans-Bold' },
    stepLabel:       { color: 'rgba(255,255,255,0.5)', fontSize: 10, fontFamily: 'NotoSans-Regular' },
    stepLabelActive: { color: 'white', fontFamily: 'NotoSans-Bold' },

    // Wizard Card
    wizardCard: {
        marginHorizontal: 16,
        marginTop: -18,
        backgroundColor: 'white',
        borderRadius: 24,
        padding: 24,
        elevation: 12,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.1,
        shadowRadius: 20,
        marginBottom: 30,
    },
    stepTitle: { fontSize: 20, fontFamily: 'NotoSans-Bold', color: '#1e293b', marginBottom: 6 },
    stepSub:   { fontSize: 13, color: '#64748B', fontFamily: 'NotoSans-Regular', marginBottom: 24, lineHeight: 19 },

    // Date Card
    dateCard: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#F8FAFC', borderRadius: 18, padding: 16, borderWidth: 1.5, borderColor: '#E2E8F0', marginBottom: 28 },
    dateIconBox: { width: 48, height: 48, borderRadius: 14, justifyContent: 'center', alignItems: 'center' },
    dateCardLabel: { fontSize: 9, fontFamily: 'NotoSans-Bold', color: '#94A3B8', letterSpacing: 1, marginBottom: 3 },
    dateCardValue: { fontSize: 15, fontFamily: 'NotoSans-Bold', color: '#1e293b' },
    dateEditBtn:   { backgroundColor: '#4338CA10', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 10 },
    dateEditText:  { fontSize: 10, fontFamily: 'NotoSans-Bold', color: '#4338CA' },

    // Primary Button
    primaryBtn:      { borderRadius: 16, overflow: 'hidden', elevation: 4, shadowColor: '#4338CA', shadowOpacity: 0.3, shadowRadius: 8, shadowOffset: { width: 0, height: 4 } },
    primaryBtnInner: { paddingVertical: 16, alignItems: 'center', justifyContent: 'center' },
    primaryBtnText:  { color: 'white', fontSize: 15, fontFamily: 'NotoSans-Bold', letterSpacing: 0.5 },

    // Subject chips
    chipWrap:       { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginBottom: 20 },
    subjectChip:    { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 24, backgroundColor: '#F8FAFC', borderWidth: 1.5, borderColor: '#E2E8F0' },
    subjectChipActive: { backgroundColor: '#4338CA', borderColor: '#4338CA' },
    subjectChipText:   { fontSize: 13, fontFamily: 'NotoSans-Bold', color: '#64748B' },

    // Chapters
    chaptersSection: { marginTop: 4 },
    chaptersHeader:  { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: 14 },
    chaptersTitle:   { fontSize: 16, fontFamily: 'NotoSans-Bold', color: '#1e293b' },
    chaptersCount:   { fontSize: 11, color: '#64748B', fontFamily: 'NotoSans-Regular', marginTop: 2 },
    selectAllBtn:    { paddingHorizontal: 12, paddingVertical: 5, borderRadius: 10, backgroundColor: '#EEF2FF' },
    selectAllText:   { fontSize: 12, fontFamily: 'NotoSans-Bold', color: '#4338CA' },
    subjectGroup:    { marginBottom: 18 },
    subjectGroupHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
    subjectGroupDot:    { width: 8, height: 8, borderRadius: 4, backgroundColor: '#4338CA', marginRight: 8 },
    subjectGroupName:   { fontSize: 12, fontFamily: 'NotoSans-Bold', color: '#4338CA', textTransform: 'uppercase', letterSpacing: 0.5 },
    chapterRow: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#F8FAFC', padding: 14, borderRadius: 14, marginBottom: 7, borderWidth: 1, borderColor: '#E2E8F0' },
    chapterRowDim:  { opacity: 0.55 },
    checkbox:       { width: 22, height: 22, borderRadius: 6, borderWidth: 2, borderColor: '#CBD5E1', backgroundColor: 'white', justifyContent: 'center', alignItems: 'center', marginRight: 12 },
    checkboxActive: { backgroundColor: '#4338CA', borderColor: '#4338CA' },
    chapterText:    { flex: 1, fontSize: 13, color: '#475569', fontFamily: 'NotoSans-Regular' },
    chapterTextActive: { color: '#1e293b', fontFamily: 'NotoSans-Bold' },

    // Wizard Footer
    wizardFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 24 },
    backBtnWiz:   { paddingVertical: 12, paddingHorizontal: 16 },
    backBtnText:  { color: '#94A3B8', fontFamily: 'NotoSans-Bold', fontSize: 14 },
    primaryBtnSm: { borderRadius: 14, overflow: 'hidden', elevation: 3 },
    primaryBtnSmInner: { paddingVertical: 13, paddingHorizontal: 24, alignItems: 'center' },

    // Launch step
    launchIcon: { width: 110, height: 110, borderRadius: 55, justifyContent: 'center', alignItems: 'center', marginBottom: 18 },
    summaryCard: { width: '100%', backgroundColor: '#F8FAFC', borderRadius: 20, borderWidth: 1, borderColor: '#E2E8F0', marginBottom: 24 },
    summaryRow:  { flexDirection: 'row', alignItems: 'center', padding: 16 },
    summaryIcon: { width: 38, height: 38, borderRadius: 10, backgroundColor: '#EEF2FF', justifyContent: 'center', alignItems: 'center', marginRight: 14 },
    summaryLabel: { fontSize: 9, fontFamily: 'NotoSans-Bold', color: '#94A3B8', letterSpacing: 1, marginBottom: 3 },
    summaryValue: { fontSize: 14, fontFamily: 'NotoSans-Bold', color: '#1e293b' },
    summaryDivider: { height: 1, backgroundColor: '#E2E8F0', marginHorizontal: 16 },
    launchBtn:      { width: '100%', borderRadius: 20, overflow: 'hidden', elevation: 8, shadowColor: '#059669', shadowOpacity: 0.35, shadowRadius: 12, shadowOffset: { width: 0, height: 6 } },
    launchBtnInner: { paddingVertical: 18, flexDirection: 'row', alignItems: 'center', justifyContent: 'center' },
    launchBtnText:  { color: 'white', fontSize: 15, fontFamily: 'NotoSans-Bold', letterSpacing: 1 },
    adjustText:     { color: '#94A3B8', fontFamily: 'NotoSans-Bold', fontSize: 13 },
});

export default StudyPlannerScreen;
