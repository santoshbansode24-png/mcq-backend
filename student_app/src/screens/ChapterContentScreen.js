import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, FlatList, ActivityIndicator, Alert, ScrollView, StatusBar, Platform, RefreshControl, Image, BackHandler } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { useIsFocused } from '@react-navigation/native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { fetchMCQs, fetchNotes, fetchVideos, recordMCQAttempt, fetchFlashcards, fetchQuickRevision, markSetCompleted } from '../api/content';
import axios from 'axios';
import { API_URL, BASE_URL } from '../api/config';
import * as Speech from 'expo-speech';
import { getBestVoice } from '../utils/voiceUtils';
import { fetchSetStatus } from '../api/content';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useTheme } from '../context/ThemeContext';
import { useLanguage } from '../context/LanguageContext';
import { downloadFile, getCachedFile } from '../utils/downloadUtils';
import { dataCache } from '../utils/dataCache';
import VoiceSelectorModal from '../components/VoiceSelectorModal'; // Import VoiceSelectorModal
import NetInfo from '@react-native-community/netinfo';
import { scheduleStudyPlanNotifications } from '../utils/studyNotificationHelper';

// --- CONSTANTS MOVED OUTSIDE FOR PERFORMANCE ---

// Gradient Palette for Videos (10 Attractive Gradients)
const videoGradients = [
    ['#f97316', '#ef4444'], // Sunset (Orange -> Red)
    ['#06b6d4', '#3b82f6'], // Ocean (Cyan -> Blue)
    ['#ec4899', '#8b5cf6'], // Berry (Pink -> Purple)
    ['#84cc16', '#10b981'], // Nature (Lime -> Green)
    ['#3b82f6', '#4f46e5'], // Midnight (Blue -> Indigo)
    ['#ec4899', '#facc15'], // Candy (Pink -> Gold)
    ['#8b5cf6', '#eab308'], // Royal (Purple -> Gold)
    ['#14b8a6', '#06b6d4'], // Mint (Teal -> Cyan)
    ['#facc15', '#ef4444'], // Fire (Yellow -> Red)
    ['#6366f1', '#ec4899'], // Galaxy (Indigo -> Pink)
];

// Gradient Palette for Notes (Reordered for variety)
const noteGradients = [
    ['#84cc16', '#10b981'], // Nature (Lime -> Green)
    ['#3b82f6', '#4f46e5'], // Midnight (Blue -> Indigo)
    ['#ec4899', '#facc15'], // Candy (Pink -> Gold)
    ['#f97316', '#ef4444'], // Sunset (Orange -> Red)
    ['#14b8a6', '#06b6d4'], // Mint (Teal -> Cyan)
    ['#8b5cf6', '#eab308'], // Royal (Purple -> Gold)
    ['#06b6d4', '#3b82f6'], // Ocean (Cyan -> Blue)
    ['#ec4899', '#8b5cf6'], // Berry (Pink -> Purple)
    ['#6366f1', '#ec4899'], // Galaxy (Indigo -> Pink)
    ['#facc15', '#ef4444'], // Fire (Yellow -> Red)
];

// Color Palette for Revision Points
const revisionColors = [
    '#fff1f2', // Soft Rose
    '#fffbeb', // Soft Amber
    '#f0fdf4', // Soft Green
    '#eff6ff', // Soft Blue
    '#faf5ff', // Soft Purple
    '#fff7ed', // Soft Orange
];

// --- MEMOIZED COMPONENTS TO PREVENT FLICKER ---

const NoteItem = React.memo(({ item, index, onOpenNote }) => {
    const gradient = noteGradients[index % noteGradients.length];
    return (
        <TouchableOpacity
            style={[styles.card, { padding: 0, overflow: 'hidden', borderWidth: 0, elevation: 6, height: 90 }]}
            onPress={() => onOpenNote(item)}
        >
            <LinearGradient
                colors={gradient}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={{ paddingHorizontal: 16, width: '100%', height: '100%', justifyContent: 'center' }}
            >
                <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                    <View style={[styles.iconContainer, { backgroundColor: 'rgba(255,255,255,0.2)', width: 44, height: 44, borderRadius: 22 }]}>
                        <Text style={{ fontSize: 20 }}>📄</Text>
                    </View>
                    <View style={{ flex: 1, marginLeft: 16 }}>
                        <Text style={[styles.cardTitle, { color: 'white', fontSize: 16, fontWeight: '800', marginBottom: 2 }]} numberOfLines={1}>
                            {item.title || `Note Lesson ${index + 1} `}
                        </Text>
                        <Text style={[styles.cardSubtitle, { color: 'rgba(255,255,255,0.9)', fontWeight: 'bold', fontSize: 13 }]} numberOfLines={1}>
                            {item.note_type?.toUpperCase() || 'PDF'}
                        </Text>
                    </View>
                </View>
            </LinearGradient>
        </TouchableOpacity>
    );
});

const VideoItem = React.memo(({ item, index, onOpenVideo }) => {
    const gradient = videoGradients[index % videoGradients.length];
    return (
        <TouchableOpacity
            onPress={() => onOpenVideo(item)}
            style={[styles.card, { padding: 0, overflow: 'hidden', borderWidth: 0, elevation: 6, height: 90 }]}
        >
            <LinearGradient
                colors={gradient}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={{ paddingHorizontal: 16, width: '100%', height: '100%', justifyContent: 'center' }}
            >
                <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                    <View style={[styles.iconContainer, { backgroundColor: 'rgba(255,255,255,0.2)', width: 44, height: 44, borderRadius: 22 }]}>
                        <Text style={{ fontSize: 20 }}>▶️</Text>
                    </View>
                    <View style={{ flex: 1, marginLeft: 16 }}>
                        <Text style={[styles.cardTitle, { color: 'white', fontSize: 16, fontWeight: '800', marginBottom: 2 }]} numberOfLines={2}>
                            {item.title || `Video Lesson ${index + 1}`}
                        </Text>
                        {item.description ? (
                            <Text style={[styles.cardSubtitle, { color: 'rgba(255,255,255,0.9)', fontWeight: 'bold', fontSize: 13 }]} numberOfLines={1}>
                                {item.description}
                            </Text>
                        ) : (
                            <Text style={[styles.cardSubtitle, { color: 'rgba(255,255,255,0.85)', fontSize: 12 }]}>
                                Tap to watch
                            </Text>
                        )}
                    </View>
                </View>
            </LinearGradient>
        </TouchableOpacity>
    );
});

// --- ISOLATED TIMER COMPONENT ---
// This prevents the entire 1800-line screen from re-rendering every second
const StudyTimer = React.memo(({ initialSeconds, isActive, onFinish }) => {
    const [seconds, setSeconds] = useState(initialSeconds);
    
    useEffect(() => {
        setSeconds(initialSeconds);
    }, [initialSeconds]);

    useEffect(() => {
        let interval = null;
        if (isActive && seconds > 0) {
            interval = setInterval(() => {
                setSeconds(prev => prev - 1);
            }, 1000);
        } else if (seconds === 0 && isActive) {
            onFinish();
        }
        return () => clearInterval(interval);
    }, [isActive, seconds, onFinish]);

    const format = (s) => {
        const m = Math.floor(s / 60);
        const sec = s % 60;
        return `${m}:${sec < 10 ? '0' : ''}${sec}`;
    };

    if (!isActive) return null;

    return (
        <View style={styles.timerContainer}>
            <Text style={styles.timerText}>⏳ {format(seconds)}</Text>
            <TouchableOpacity onPress={onFinish} style={styles.finishBtn}>
                <Text style={styles.finishBtnText}>Done</Text>
            </TouchableOpacity>
        </View>
    );
});

const TabSelector = React.memo(({ activeTab, onTabPress, theme, t }) => {
    const tabs = [
        { id: 'MCQs', icon: '📝', label: t('mcqs') || 'MCQs', color: '#3b82f6' },
        { id: 'Flashcards', icon: '🗂️', label: t('flashcards') || 'Flashcards', color: '#10b981' },
        { id: 'QuickRevision', icon: '⚡', label: t('revision') || 'Revision', color: '#f59e0b' },
        { id: 'Videos', icon: '🎥', label: t('videos') || 'Videos', color: '#ef4444' },
        { id: 'Notes', icon: '📄', label: t('notes') || 'Notes', color: '#8b5cf6' },
    ];

    return (
        <View style={[styles.tabContainer, { backgroundColor: theme.card, borderBottomColor: theme.border }]}>
            <View style={styles.tabsRow}>
                {tabs.map((tab) => {
                    const isActive = activeTab === tab.id;
                    return (
                        <TouchableOpacity
                            key={tab.id}
                            style={[
                                styles.tile,
                                {
                                    backgroundColor: isActive ? tab.color : theme.card,
                                    borderColor: tab.color,
                                    elevation: isActive ? 6 : 1,
                                    shadowColor: tab.color,
                                    transform: [{ translateY: isActive ? -4 : 0 }]
                                }
                            ]}
                            onPress={() => onTabPress(tab.id)}
                            activeOpacity={0.9}
                        >
                            <Text style={[styles.tileIcon, { opacity: isActive ? 1 : 0.8 }]}>{tab.icon}</Text>
                            <Text style={[
                                styles.tileText,
                                {
                                    color: isActive ? 'white' : tab.color,
                                    fontWeight: 'bold',
                                    fontSize: 10
                                }
                            ]} numberOfLines={1}>
                                {tab.label}
                            </Text>
                        </TouchableOpacity>
                    );
                })}
            </View>
        </View>
    );
});

const ChapterContentScreen = ({ navigation, route }) => {
    const isFocused = useIsFocused();
    const { theme, isDarkMode } = useTheme();
    const { t } = useLanguage();
    const { chapter, activeTask } = route.params || {}; // activeTask contains timer info
    const [activeTab, setActiveTab] = useState(route.params?.initialTab || 'MCQs'); // Use initialTab if passed
    const [downloading, setDownloading] = useState(false);
    const [downloadProgress, setDownloadProgress] = useState(0);
    const [loading, setLoading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const [voiceModalVisible, setVoiceModalVisible] = useState(false);

    // Separate states for each tab to prevent flicker/ghosting
    const [mcqData, setMcqData] = useState([]);
    const [notesData, setNotesData] = useState([]);
    const [videosData, setVideosData] = useState([]);
    const [flashcardsDataState, setFlashcardsDataState] = useState([]);
    const [revisionDataItems, setRevisionDataItems] = useState([]);

    // Timer State
    const [taskTimer, setTaskTimer] = useState(0);
    const tabRequestRef = React.useRef(activeTab); // Track latest requested tab
    const [isTaskActive, setIsTaskActive] = useState(false);

    useEffect(() => {
        if (activeTask && !isTaskActive) {
            setTaskTimer(activeTask.duration_minutes * 60);
            setIsTaskActive(true);
        }
    }, [activeTask]);

    // Timer logic is now moved to the StudyTimer component to prevent whole-screen re-renders

    // Quiz State — declared here so refs below can reference them
    const [quizMode, setQuizMode] = useState(false);
    const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);
    const [selectedOption, setSelectedOption] = useState(null);
    const [showExplanation, setShowExplanation] = useState(false);
    const [score, setScore] = useState(0);
    const [quizFinished, setQuizFinished] = useState(false);
    const [quizQuestions, setQuizQuestions] = useState([]);

    // Sets State
    const [mcqSets, setMcqSets] = useState([]);
    const [currentSetIndex, setCurrentSetIndex] = useState(0);
    const [flashcardSets, setFlashcardSets] = useState([]);
    const [revisionData, setRevisionData] = useState([]);
    const [playingIndex, setPlayingIndex] = useState(null);
    const [setStatuses, setSetStatuses] = useState({});
    const [userAnswers, setUserAnswers] = useState({});

    // Use refs so the interceptor always reads the LATEST state, avoiding stale closures
    const quizModeRef = useRef(quizMode);
    const isTaskActiveRef = useRef(isTaskActive);
    const navigationRef = useRef(navigation);
    useEffect(() => { quizModeRef.current = quizMode; }, [quizMode]);
    useEffect(() => { isTaskActiveRef.current = isTaskActive; }, [isTaskActive]);
    useEffect(() => { navigationRef.current = navigation; }, [navigation]);

    // Stable interceptor function (created once, reads from refs for latest values)
    const stableInterceptor = useRef(() => {
        if (quizModeRef.current) {
            setQuizMode(false);
            setQuizFinished(false);
            setCurrentQuestionIndex(0);
            setSelectedOption(null);
            setScore(0);
            return true; // Handled — don't pop history stack in MainScreen
        }
        if (isTaskActiveRef.current) {
            Alert.alert(
                "Abandon Mission?",
                "Timer is still running. Do you want to leave?",
                [
                    { text: "Stay", style: "cancel" },
                    { text: "Leave", style: "destructive", onPress: () => navigationRef.current.goBack() }
                ]
            );
            return true;
        }
        return false; // Not handled — let MainScreen pop the stack normally
    });

    // Register the stable interceptor ONCE on mount; unregister on unmount
    useEffect(() => {
        if (navigation.registerBackInterceptor) {
            navigation.registerBackInterceptor(stableInterceptor.current);
        }
        return () => {
            if (navigation.unregisterBackInterceptor) {
                navigation.unregisterBackInterceptor();
            }
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Header back button: runs the interceptor, falls through to goBack() if not handled
    const onHeaderBack = useCallback(() => {
        const handled = stableInterceptor.current();
        if (!handled) {
            navigationRef.current.goBack();
        }
    }, []);


    const finishTask = async () => {
        setIsTaskActive(false);
        try {
            // Get User ID from Storage
            const userDataStr = await AsyncStorage.getItem('user_data');
            const userData = userDataStr ? JSON.parse(userDataStr) : null;
            const userId = userData?.user_id || userData?.id;

            if (userId) {
                await axios.post(`${API_URL}/update_task_status.php`, {
                    user_id: userId,
                    task_id: activeTask.task_id,
                    status: 'completed'
                });
                Alert.alert("Mission Complete! 🎉", `Great job! You earned ${activeTask.xp_reward} XP!`);

                // --- Reschedule Remaining Notifications ---
                try {
                    const res = await axios.get(`${API_URL}/get_roadmap.php?user_id=${userId}`);
                    if (res.data.status === 'success' && res.data.data) {
                        const today = new Date().toISOString().split('T')[0];
                        const todayData = res.data.data.find(d => d.date === today);
                        const remainingTasks = todayData?.tasks?.filter(t => t.status !== 'completed') || [];
                        
                        if (remainingTasks.length > 0) {
                            const startTime = new Date(); // Start rescheduling from NOW
                            const endTime = new Date();
                            endTime.setHours(21, 0, 0); // 9 PM

                            const chapters = remainingTasks.map(t => ({
                                chapter_id: t.chapter_id,
                                chapter_name: t.title.split(': ').pop(),
                                subject_name: t.subject
                            }));

                            // Import helper is already added at the top
                            scheduleStudyPlanNotifications(chapters, startTime, endTime);
                        }
                    }
                } catch (err) {
                    console.log('Notification rescheduling failed', err);
                }
            }
        } catch (e) {
            console.error('[Timer] Error finishing task:', e);
        }
    };

    const formatTimer = (seconds) => {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}:${s < 10 ? '0' : ''}${s}`;
    };



    useEffect(() => {
        // Refresh status whenever the screen comes into focus
        if (isFocused && chapter?.chapter_id) {
            if (activeTab === 'MCQs') loadSetStatus('mcq');
            if (activeTab === 'Flashcards') loadSetStatus('flashcard');
        }
    }, [isFocused, activeTab]);

    useEffect(() => {
        if (isFocused && chapter?.chapter_id) {
            loadContent(false, false); // Initial load: not refreshing, not forcing
            preFetchAll(); // Start pre-fetching everything else
        }
    }, [isFocused, chapter?.chapter_id]); // Only run on mount/chapter change

    // If already loaded, switching tabs shouldn't trigger a full reload
    useEffect(() => {
        if (isFocused && chapter?.chapter_id) {
            loadContent(false, false); // Just ensures local state is set for the tab
        }
    }, [activeTab]);

    const preFetchAll = async () => {
        if (!chapter?.chapter_id) return;
        console.log("[ChapterContent] Starting background pre-fetch...");

        const tabsToFetch = ['MCQs', 'Notes', 'Videos', 'Flashcards', 'QuickRevision'].filter(t => t !== activeTab);

        // Fetch sequentially in background to avoid overwhelming the bridge
        for (const tab of tabsToFetch) {
            loadTabInBackground(tab);
        }
    };

    const loadTabInBackground = async (tab) => {
        try {
            let response;
            if (tab === 'MCQs') response = await fetchMCQs(chapter.chapter_id, false);
            else if (tab === 'Notes') response = await fetchNotes(chapter.chapter_id, false);
            else if (tab === 'Videos') response = await fetchVideos(chapter.chapter_id, false);
            else if (tab === 'Flashcards') response = await fetchFlashcards(chapter.chapter_id, false);
            else if (tab === 'QuickRevision') response = await fetchQuickRevision(chapter.chapter_id, false);

            const responseData = response?.data || (Array.isArray(response) ? response : null);
            if (responseData) {
                processLoadedData(responseData, true, tab);
            }
        } catch (e) {
            console.log(`[Pre-fetch] Failed for ${tab}:`, e.message);
        }
    };

    const loadContent = async (isRefreshing = false, forceRefresh = false) => {
        const currentTab = activeTab;
        tabRequestRef.current = currentTab;

        // Stop speech when leaving tab or refreshing
        Speech.stop();
        setPlayingIndex(null);

        // STALE-WHILE-REVALIDATE PATTERN
        let hasCache = false;
        if (!isRefreshing) {
            try {
                const cacheKeyMap = {
                    'MCQs': `mcqs_${chapter.chapter_id}`,
                    'Notes': `notes_${chapter.chapter_id}`,
                    'Videos': `videos_${chapter.chapter_id}`,
                    'Flashcards': `flashcards_${chapter.chapter_id}`,
                    'QuickRevision': `quick_rev_${chapter.chapter_id}`
                };

                const cacheKey = cacheKeyMap[currentTab];
                if (cacheKey) {
                    // Use the dataCache utility instead of manual AsyncStorage
                    const cachedData = await dataCache.get(cacheKey, currentTab.toLowerCase());
                    if (cachedData) {
                        console.log(`[Content] Stale-While-Revalidate: Showing cache for ${currentTab}`);
                        processLoadedData(cachedData, false, currentTab); // Update UI with currentTab context
                        hasCache = true;
                    }
                }
            } catch (e) {
                console.log('[Content] Cache read error', e);
            }
        }

        if (isRefreshing) {
            setRefreshing(true);
        } else {
            // Check if we already have data for this tab (either from previous load or cache)
            const currentTabData = getTabSpecificData(currentTab);
            if (!hasCache && currentTabData.length === 0) {
                setLoading(true);
            }
        }

        try {
            let response;
            const force = forceRefresh; // Respect the explicit forceRefresh flag

            if (activeTab === 'MCQs') {
                response = await fetchMCQs(chapter.chapter_id, force);
            } else if (activeTab === 'Notes') {
                response = await fetchNotes(chapter.chapter_id, force);
            } else if (activeTab === 'Videos') {
                response = await fetchVideos(chapter.chapter_id, force);
            } else if (activeTab === 'Flashcards') {
                response = await fetchFlashcards(chapter.chapter_id, force);
            } else if (activeTab === 'QuickRevision') {
                response = await fetchQuickRevision(chapter.chapter_id, force);
            }

            const responseData = response?.data || (Array.isArray(response) ? response : null);
            const isSuccess = response?.status === 'success' || Array.isArray(response);

            if (isSuccess && responseData) {
                // RACE CONDITION PROTECT: Only update if we are still on the same tab
                if (tabRequestRef.current === currentTab) {
                    processLoadedData(responseData, true, currentTab);
                }
            } else if (response) {
                const msg = response.message || '';
                if (!msg.toLowerCase().includes('no')) {
                    Alert.alert('Error', msg || 'Invalid data format');
                }
            }
        } catch (error) {
            // Only alert if we don't have ANY data (even stale)
            const tabData = getTabSpecificData(activeTab);
            if (tabData.length === 0) {
                Alert.alert('Error', 'Failed to load content. Please check your internet connection.');
            }
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const processLoadedData = (responseData, isFresh = true, targetTab = null) => {
        const actingTab = targetTab || activeTab;

        if (actingTab === 'MCQs') {
            const allMcqs = Array.isArray(responseData) ? responseData : [];
            const chunks = [];
            for (let i = 0; i < allMcqs.length; i += 10) {
                chunks.push(allMcqs.slice(i, i + 10));
            }
            setMcqSets(chunks);
            setMcqData(allMcqs);
            if (isFresh) loadSetStatus('mcq');
        } else if (actingTab === 'Flashcards') {
            const allCards = Array.isArray(responseData) ? responseData : [];
            const chunks = [];
            for (let i = 0; i < allCards.length; i += 10) {
                chunks.push(allCards.slice(i, i + 10));
            }
            setFlashcardSets(chunks);
            setFlashcardsDataState(allCards);
            if (isFresh) loadSetStatus('flashcard');
        } else if (actingTab === 'QuickRevision') {
            const points = Array.isArray(responseData) ? (responseData[0]?.key_points || []) : [];
            setRevisionData(points.slice(1));
            setRevisionDataItems(points.slice(1));
        } else if (actingTab === 'Notes') {
            setNotesData(Array.isArray(responseData) ? responseData : []);
        } else if (actingTab === 'Videos') {
            setVideosData(Array.isArray(responseData) ? responseData : []);
        }
    };

    // Helper to get correct data for current tab
    const getTabSpecificData = (tab) => {
        switch (tab) {
            case 'MCQs': return mcqData;
            case 'Notes': return notesData;
            case 'Videos': return videosData;
            case 'Flashcards': return flashcardsDataState;
            case 'QuickRevision': return revisionDataItems;
            default: return [];
        }
    };

    const loadSetStatus = async (type) => {
        try {
            // Fix: Retrieve user_data object instead of user_id string
            const userDataStr = await AsyncStorage.getItem('user_data');
            const userData = userDataStr ? JSON.parse(userDataStr) : null;
            const userId = userData?.user_id || userData?.id;
            if (userId && chapter.chapter_id) {
                console.log(`[ChapterContent] Fetching status for ${type}...`);
                const statusData = await fetchSetStatus(userId, chapter.chapter_id, type);
                console.log(`[ChapterContent] Status result for ${type}:`, statusData);
                if (statusData.status === 'success') {
                    setSetStatuses(statusData.data);
                }
            }
        } catch (e) { console.log('Status Load Error', e); }
    };

    const onRefresh = () => {
        loadContent(true);
    };

    const generateAIQuiz = async () => {
        setLoading(true);
        try {
            const response = await axios.post(`${API_URL}/ai_generate_quiz.php`, {
                chapter_id: chapter.chapter_id
            });

            if (response.data.status === 'success') {
                const newQuiz = response.data.data;
                setMcqSets(prev => [newQuiz, ...prev]);
                setActiveTab('MCQs');
                Alert.alert('Success', 'AI Quiz Generated! It has been added to the top of the list.');
            } else {
                Alert.alert('Error', response.data.message || 'Failed to generate quiz');
            }
        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Failed to connect to AI service');
        } finally {
            setLoading(false);
        }
    };

    // Helper to shuffle array
    const shuffleArray = (array) => {
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    };

    const startQuiz = (setIndex) => {
        if (!mcqSets[setIndex]) return;

        // Shuffle questions before starting
        const shuffledQuestions = shuffleArray(mcqSets[setIndex]);
        setQuizQuestions(shuffledQuestions);

        setCurrentSetIndex(setIndex);
        setCurrentQuestionIndex(0);
        setScore(0);
        setQuizFinished(false);
        setQuizMode(true);
        setUserAnswers({});
        resetQuestionState();
    };

    const resetQuestionState = () => {
        setSelectedOption(null);
        setShowExplanation(false);
    };

    const handleOptionSelect = (optionKey) => {
        if (selectedOption) return;
        setSelectedOption(optionKey);
        setShowExplanation(true);
        setUserAnswers(prev => ({ ...prev, [currentQuestionIndex]: optionKey }));

        const currentQuestion = quizQuestions[currentQuestionIndex];

        // Record Attempt
        AsyncStorage.getItem('user_data').then(userDataStr => {
            const userData = userDataStr ? JSON.parse(userDataStr) : null;
            const userId = userData?.user_id || userData?.id;
            if (userId) {
                const isCorrect = optionKey === currentQuestion.correct_answer;
                recordMCQAttempt(
                    userId,
                    currentQuestion.mcq_id,
                    chapter.chapter_id,
                    optionKey,
                    currentQuestion.correct_answer,
                    isCorrect
                );
            }
        });

        if (optionKey === currentQuestion.correct_answer) {
            setScore(prev => prev + 1);
        }
    };

    const nextQuestion = () => {
        if (currentQuestionIndex < quizQuestions.length - 1) {
            const newIndex = currentQuestionIndex + 1;
            setCurrentQuestionIndex(newIndex);

            if (userAnswers[newIndex]) {
                setSelectedOption(userAnswers[newIndex]);
                setShowExplanation(true);
            } else {
                resetQuestionState();
            }
        } else {
            setQuizFinished(true);
            // Mark as Completed
            // Mark as Completed
            AsyncStorage.getItem('user_data').then(userDataStr => {
                const userData = userDataStr ? JSON.parse(userDataStr) : null;
                const userId = userData?.user_id || userData?.id;

                // Alert.alert('Debug', `Attempting Save... User: ${userId}, Chapter: ${chapter.chapter_id}`);
                if (userId && chapter.chapter_id) {
                    markSetCompleted(userId, chapter.chapter_id, currentSetIndex, 'mcq', score, quizQuestions.length)
                        .then((res) => {
                            if (res.status === 'success') {
                                loadSetStatus('mcq');
                                // Alert.alert('Success', 'MCQ Saved!'); 
                            } else {
                                Alert.alert('Backend Error', res.message);
                            }
                        })
                        .catch(err => Alert.alert('Network Error', err.message));
                } else {
                    Alert.alert('Error', `Missing Data: User=${userId}, Chapter=${chapter?.chapter_id}`);
                }
            });

        }
    };

    const prevQuestion = () => {
        if (currentQuestionIndex > 0) {
            const newIndex = currentQuestionIndex - 1;
            setCurrentQuestionIndex(newIndex);

            if (userAnswers[newIndex]) {
                setSelectedOption(userAnswers[newIndex]);
                setShowExplanation(true);
            } else {
                resetQuestionState();
            }
        }
    };

    const getOptionStyle = (optionKey) => {
        if (!selectedOption) return styles.optionButton;

        const currentQuestion = quizQuestions[currentQuestionIndex];
        const isCorrect = optionKey === currentQuestion.correct_answer;
        const isSelected = optionKey === selectedOption;

        if (isSelected && isCorrect) return [styles.optionButton, styles.correctOption];
        if (isSelected && !isCorrect) return [styles.optionButton, styles.wrongOption];
        if (isCorrect && showExplanation) return [styles.optionButton, styles.correctOption];

        return styles.optionButton;
    };

    const decodeHtml = (html) => {
        if (!html) return '';
        return html
            .replace(/&quot;/g, '"')
            .replace(/&apos;/g, "'")
            .replace(/&#039;/g, "'")
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&nbsp;/g, ' ');
    };

    // TTS Logic
    const stopTTS = async () => {
        await Speech.stop();
        setPlayingIndex(null);
    };

    const playTTS = async (item, index) => {
        if (playingIndex === index) {
            await stopTTS();
            return;
        }

        const q = decodeHtml(item.q || item.Question || '');
        const a = decodeHtml(item.a || item.Answer || '');
        const textToSpeak = `Question. ${q}. Answer. ${a}`;

        try {
            await Speech.stop();
            setPlayingIndex(index);

            setPlayingIndex(index);

            // Get the best available voice (Prioritizes Marathi -> Hindi -> English)
            const bestVoice = await getBestVoice();
            // console.log('Using Voice:', bestVoice);

            Speech.speak(textToSpeak, {
                language: 'en-IN', // Base language (fallback)
                voice: bestVoice,  // Specific voice identifier (e.g., Marathi)
                pitch: 1.0,
                rate: 0.85,        // Slightly slower for better clarity
                onDone: () => setPlayingIndex(null),
                onStopped: () => setPlayingIndex(null),
                onError: (e) => {
                    // console.log('TTS Error', e);
                    setPlayingIndex(null);
                }
            });
        } catch (error) {
            console.error(error);
            setPlayingIndex(null);
        }
    };

    const renderQuiz = () => {
        if (quizFinished) {
            const hasNextSet = currentSetIndex < mcqSets.length - 1;

            return (
                <View style={styles.resultContainer}>
                    <Text style={styles.resultEmoji}>🏆</Text>
                    <Text style={styles.resultTitle}>Set {currentSetIndex + 1} Completed!</Text>
                    <Text style={styles.resultScore}>You scored {score} / {quizQuestions.length}</Text>

                    {hasNextSet && (
                        <TouchableOpacity style={styles.nextSetButton} onPress={() => startQuiz(currentSetIndex + 1)}>
                            <Text style={styles.nextSetButtonText}>Start Set {currentSetIndex + 2} →</Text>
                        </TouchableOpacity>
                    )}

                    <TouchableOpacity style={styles.restartButton} onPress={() => startQuiz(currentSetIndex)}>
                        <Text style={styles.restartButtonText}>Replay Set {currentSetIndex + 1}</Text>
                    </TouchableOpacity>

                    <TouchableOpacity style={styles.backToContentButton} onPress={() => {
                        setQuizMode(false);
                        setQuizFinished(false);
                    }}>
                        <Text style={styles.backToContentText}>Back to Sets</Text>
                    </TouchableOpacity>
                </View>
            );
        }

        const question = quizQuestions[currentQuestionIndex];
        return (
            <View style={{ flex: 1, backgroundColor: theme.background }}>
                <ScrollView
                    style={{ flex: 1 }}
                    contentContainerStyle={[styles.quizContainer, { paddingBottom: 20 }]}
                    showsVerticalScrollIndicator={false}
                >
                    <View style={styles.progressContainer}>
                        <Text style={styles.progressText}>Set {currentSetIndex + 1} • Q{currentQuestionIndex + 1}/{quizQuestions.length}</Text>
                        <Text style={styles.scoreText}>Score: {score}</Text>
                    </View>

                    <View style={styles.questionCardContainer}>
                        <LinearGradient
                            colors={['#fdfaff', '#ffffff']}
                            style={styles.questionCard}
                        >
                            {/* Glossy Reflection Overlay */}
                            <LinearGradient
                                colors={['rgba(255,255,255,0.6)', 'rgba(255,255,255,0)']}
                                style={styles.glossyOverlay}
                            />

                            <View style={styles.questionBadge}>
                                <Text style={styles.questionBadgeIcon}>📝</Text>
                                <Text style={styles.questionBadgeText}>QUESTION</Text>
                            </View>
                            {question.image_url && (
                                <Image
                                    source={{ uri: `${BASE_URL}/uploads/${question.image_url}` }}
                                    style={styles.questionImage}
                                    resizeMode="contain"
                                />
                            )}
                            <Text style={styles.questionText}>{decodeHtml(question.question)}</Text>
                        </LinearGradient>
                    </View>

                    <View style={styles.optionsList}>
                        {['a', 'b', 'c', 'd'].map((opt) => (
                            <TouchableOpacity
                                key={opt}
                                style={getOptionStyle(opt)}
                                onPress={() => handleOptionSelect(opt)}
                                disabled={selectedOption !== null}
                            >
                                <Text style={[
                                    styles.optionText,
                                    selectedOption && (opt === question.correct_answer || opt === selectedOption) ? styles.whiteText : null
                                ]}>
                                    {opt.toUpperCase()}. {decodeHtml(question[`option_${opt}`])}
                                </Text>
                            </TouchableOpacity>
                        ))}
                    </View>

                    {showExplanation && (
                        <View style={styles.explanationContainer}>
                            <View style={styles.explanationHeader}>
                                <Text style={styles.explanationEmoji}>💡</Text>
                                <Text style={styles.explanationTitle}>Explanation</Text>
                            </View>
                            <Text style={styles.explanationText}>{decodeHtml(question.explanation) || 'No explanation available.'}</Text>
                        </View>
                    )}
                </ScrollView>

                {/* FIXED ACTIONS - No scrolling needed to find buttons */}
                <View style={[styles.quizActionsRow, {
                    paddingHorizontal: 20,
                    paddingTop: 12,
                    paddingBottom: Platform.OS === 'ios' ? 30 : 15,
                    backgroundColor: theme.card,
                    borderTopWidth: 1,
                    borderTopColor: theme.border + '44'
                }]}>
                    <TouchableOpacity
                        style={[styles.prevButtonStylized, currentQuestionIndex === 0 && { opacity: 0.3 }]}
                        onPress={prevQuestion}
                        disabled={currentQuestionIndex === 0}
                    >
                        <LinearGradient
                            colors={['#f8fafc', '#f1f5f9']}
                            style={[styles.prevButtonGradient, { borderRadius: 16 }]}
                        >
                            <View style={styles.prevButtonContent}>
                                <Text style={styles.prevButtonIcon}>←</Text>
                                <Text style={styles.prevButtonTextStylized}>Prev</Text>
                            </View>
                        </LinearGradient>
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={styles.nextButtonStylized}
                        onPress={nextQuestion}
                        activeOpacity={0.8}
                        disabled={!selectedOption}
                    >
                        <LinearGradient
                            colors={!selectedOption ? ['#cbd5e1', '#94a3b8'] : ['#4f46e5', '#8b5cf6']}
                            start={{ x: 0, y: 0 }}
                            end={{ x: 1, y: 1 }}
                            style={styles.nextButtonGradient}
                        >
                            <Text style={styles.nextButtonTextStylized}>
                                {currentQuestionIndex === quizQuestions.length - 1 ? 'Finish Set 🏁' : 'Next Question →'}
                            </Text>
                        </LinearGradient>
                    </TouchableOpacity>
                </View>
            </View>
        );
    };

    const handleOpenNote = useCallback(async (item) => {
        const netInfo = await NetInfo.fetch();
        if (!netInfo.isConnected) {
            Alert.alert('Offline Mode', 'You are currently offline. Please connect to the internet to view PDF notes.');
            return;
        }

        const rawPath = item.file_path || item.file_url;
        if (!rawPath) {
            Alert.alert('Error', 'File path is missing');
            return;
        }

        try {
            setDownloading(true);
            setDownloadProgress(0);
            let remoteUrl = rawPath;
            if (!rawPath.startsWith('http')) {
                remoteUrl = `${BASE_URL}/${rawPath}`;
            }
            const localUri = await getCachedFile(
                remoteUrl,
                item.title,
                (progress) => setDownloadProgress(progress)
            );
            setDownloading(false);
            if (localUri) {
                navigation.navigate('PDFViewer', { url: localUri, title: item.title });
            }
        } catch (error) {
            console.error(error);
            setDownloading(false);
            Alert.alert('Error', 'Failed to open note. Check internet.');
        }
    }, [navigation]);

    const handleOpenVideo = useCallback(async (item) => {
        const netInfo = await NetInfo.fetch();
        if (!netInfo.isConnected) {
            Alert.alert('Offline Mode', 'You are currently offline. Please connect to the internet to watch videos.');
            return;
        }

        navigation.navigate('VideoPlayer', {
            videoUrl: item.url,
            title: item.title || 'Video Lesson',
            activeTask: activeTask
        });
    }, [navigation, activeTask]);

    const renderNoteItem = useCallback(({ item, index }) => (
        <NoteItem item={item} index={index} onOpenNote={handleOpenNote} />
    ), [handleOpenNote]);

    const renderVideoItem = useCallback(({ item, index }) => (
        <VideoItem item={item} index={index} onOpenVideo={handleOpenVideo} />
    ), [handleOpenVideo]);

    // Color Palette for Sets (Option 1: Pastel Rainbow)
    const setColors = [
        { bg: '#fee2e2', border: '#fca5a5', text: '#b91c1c' }, // Red
        { bg: '#ffedd5', border: '#fdba74', text: '#c2410c' }, // Orange
        { bg: '#fef3c7', border: '#fcd34d', text: '#b45309' }, // Yellow
        { bg: '#dcfce7', border: '#86efac', text: '#15803d' }, // Green
        { bg: '#dbeafe', border: '#93c5fd', text: '#1d4ed8' }, // Blue
        { bg: '#f3e8ff', border: '#d8b4fe', text: '#7e22ce' }, // Purple
    ];

    // Color Palette for MCQ Sets (Option 3: Warm Sunset)
    // Color Palette for MCQ Sets (10 Vivid Soft Colors)
    const mcqColors = [
        { bg: '#fee2e2', border: '#fca5a5', text: '#b91c1c' }, // Red
        { bg: '#ffedd5', border: '#fdba74', text: '#c2410c' }, // Orange
        { bg: '#fef9c3', border: '#fde047', text: '#a16207' }, // Yellow
        { bg: '#ecfccb', border: '#bef264', text: '#4d7c0f' }, // Lime
        { bg: '#d1fae5', border: '#6ee7b7', text: '#047857' }, // Emerald
        { bg: '#ccfbf1', border: '#5eead4', text: '#0f766e' }, // Teal
        { bg: '#e0f2fe', border: '#7dd3fc', text: '#0369a1' }, // Sky
        { bg: '#e0e7ff', border: '#a5b4fc', text: '#4338ca' }, // Indigo
        { bg: '#fae8ff', border: '#f0abfc', text: '#a21caf' }, // Fuchsia
        { bg: '#ffe4e6', border: '#fda4af', text: '#be123c' }, // Rose
    ];

    const renderContent = () => {
        // Only show full-screen loader if data is empty (no cache yet)
        const tabData = getTabSpecificData(activeTab);
        if (loading && tabData.length === 0) return <ActivityIndicator size="large" color="#4f46e5" style={styles.loader} />;

        // Add bottom padding to all scrollable content so it doesn't get hidden behind bottom tab navigator
        const contentContainerPadding = { paddingBottom: 120 };

        if (activeTab === 'MCQs') {
            if (quizMode) return renderQuiz();

            if (mcqSets.length === 0) {
                return (
                    <ScrollView
                        contentContainerStyle={[styles.emptyContainer, contentContainerPadding]}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                    >
                        <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No MCQs available for this chapter.</Text>
                    </ScrollView>
                );
            }

            return (
                <ScrollView
                    contentContainerStyle={[styles.setsContainer, contentContainerPadding]}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                >
                    <View style={styles.sectionHeader}>
                        <Text style={[styles.quizTitle, { color: theme.text }]}>MCQ Practice Sets</Text>
                        <Text style={[styles.quizSubtitle, { color: theme.textSecondary }]}>Select a set to start practicing</Text>
                    </View>

                    <View style={styles.setsGrid}>
                        {mcqSets.map((set, index) => {
                            const isSolved = setStatuses[index]?.status === 'completed';
                            const color = mcqColors[index % mcqColors.length]; // Cycle through warm colors

                            return (
                                <TouchableOpacity
                                    key={index}
                                    style={[
                                        styles.setCard,
                                        {
                                            backgroundColor: color.bg,
                                            borderColor: color.border,
                                            borderWidth: 1
                                        }
                                    ]}
                                    onPress={() => startQuiz(index)}
                                    activeOpacity={0.8}
                                >
                                    <View style={[styles.setIcon, { backgroundColor: 'white' }, isSolved && { backgroundColor: '#dcfce7' }]}>
                                        <Text style={[styles.setIconText, { color: color.text }, isSolved && { color: '#16a34a' }]}>
                                            {isSolved ? '✔' : index + 1}
                                        </Text>
                                    </View>
                                    <View style={{ flex: 1 }}>
                                        <Text style={[styles.setTitle, { color: '#334155' }]}>Set {index + 1}</Text>
                                        <Text style={[styles.setSubtitle, { color: '#64748b' }]}>
                                            {isSolved
                                                ? `✅ Solved • ${setStatuses[index]?.score}/${setStatuses[index]?.total}`
                                                : `${set.length} Questions`
                                            }
                                        </Text>
                                    </View>
                                    <View style={styles.setArrow}>
                                        <Text style={{ fontSize: 24, color: color.text }}>→</Text>
                                    </View>
                                </TouchableOpacity>
                            );
                        })}

                    </View>
                </ScrollView>
            );
        }

        if (activeTab === 'Flashcards') {
            if (flashcardSets.length === 0) {
                return (
                    <ScrollView
                        contentContainerStyle={[styles.emptyContainer, contentContainerPadding]}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                    >
                        <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No Flashcards available for this chapter.</Text>
                    </ScrollView>
                );
            }

            return (
                <ScrollView
                    contentContainerStyle={[styles.setsContainer, contentContainerPadding]}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                >
                    <View style={styles.sectionHeader}>
                        <Text style={[styles.quizTitle, { color: theme.text }]}>Flashcard Sets</Text>
                        <Text style={[styles.quizSubtitle, { color: theme.textSecondary }]}>Select a set to start learning</Text>
                    </View>

                    <View style={styles.setsGrid}>
                        {flashcardSets.map((set, index) => {
                            const isSolved = setStatuses[index]?.status === 'completed';
                            const color = setColors[index % setColors.length]; // Cycle through colors

                            return (
                                <TouchableOpacity
                                    key={index}
                                    style={[
                                        styles.setCard,
                                        {
                                            backgroundColor: color.bg,
                                            borderColor: color.border,
                                            borderWidth: 1
                                        }
                                    ]}
                                    onPress={() => {
                                        // Shuffle flashcards before navigation
                                        const shuffledCards = shuffleArray(set);
                                        navigation.navigate('Flashcards', {
                                            chapterId: chapter.chapter_id,
                                            chapterName: chapter.chapter_name,
                                            flashcardsData: shuffledCards,
                                            setLabel: `Set ${index + 1}`,
                                            setIndex: index,
                                            activeTask: activeTask // Add timer context
                                        });
                                    }}
                                    activeOpacity={0.8}
                                >
                                    <View style={[styles.setIcon, { backgroundColor: 'white' }, isSolved && { backgroundColor: '#dcfce7' }]}>
                                        <Text style={[styles.setIconText, { color: color.text }, isSolved && { color: '#16a34a' }]}>
                                            {isSolved ? '✔' : index + 1}
                                        </Text>
                                    </View>
                                    <View style={{ flex: 1 }}>
                                        <Text style={[styles.setTitle, { color: '#334155' }]}>Set {index + 1}</Text>
                                        <Text style={[styles.setSubtitle, { color: '#64748b' }]}>
                                            {isSolved ? '✅ Completed' : `${set.length} Cards`}
                                        </Text>
                                    </View>
                                    <View style={styles.setArrow}>
                                        <Text style={{ fontSize: 24, color: color.text }}>→</Text>
                                    </View>
                                </TouchableOpacity>
                            );
                        })}
                    </View>
                </ScrollView>
            );
        }

        if (activeTab === 'QuickRevision') {
            if (revisionDataItems.length === 0) {
                return (
                    <ScrollView
                        contentContainerStyle={[styles.emptyContainer, contentContainerPadding]}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                    >
                        <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No Quick Revision notes available.</Text>
                    </ScrollView>
                );
            }

            return (
                <ScrollView
                    contentContainerStyle={[styles.listContainer, contentContainerPadding]}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                >
                    <View style={styles.sectionHeaderRow}>
                        <View>
                            <Text style={[styles.quizTitle, { color: theme.text }]}>Quick Revision</Text>
                            <Text style={[styles.quizSubtitle, { color: theme.textSecondary }]}>Key points for {chapter.chapter_name}</Text>
                        </View>
                        <TouchableOpacity
                            onPress={() => setVoiceModalVisible(true)}
                            style={styles.voiceButton}
                        >
                            <Text style={styles.voiceIcon}>🗣️</Text>
                            <Text style={styles.voiceText}>VOICE</Text>
                        </TouchableOpacity>
                    </View>

                    {revisionDataItems.map((item, index) => {
                        const bgColor = revisionColors[index % revisionColors.length];
                        return (
                            <View key={`rev-${item.q || index}`} style={[styles.card, { backgroundColor: bgColor }, playingIndex === index && { borderColor: '#4f46e5', borderWidth: 2 }]}>
                                <View style={{ flexDirection: 'row', marginBottom: 8, alignItems: 'center' }}>
                                    <View style={[styles.iconContainer, { width: 30, height: 30, borderRadius: 15, backgroundColor: 'white' }]}>
                                        <Text style={{ fontSize: 14, fontWeight: 'bold', color: '#4f46e5' }}>{index + 1}</Text>
                                    </View>
                                    <Text style={[styles.cardTitle, { flex: 1, color: '#4f46e5', fontSize: 14 }]}>POINT {index + 1}</Text>

                                    <TouchableOpacity
                                        onPress={() => playTTS(item, index)}
                                        style={{ padding: 5 }}
                                    >
                                        <Text style={{ fontSize: 24 }}>
                                            {playingIndex === index ? '⏹️' : '▶️'}
                                        </Text>
                                    </TouchableOpacity>
                                </View>

                                <Text style={[styles.cardSubtitle, { fontSize: 13, fontWeight: 'bold', marginBottom: 4, color: '#64748b' }]}>QUESTION</Text>
                                <Text style={[styles.cardTitle, { fontSize: 16, marginBottom: 12 }]}>{decodeHtml(item.q || item.Question)}</Text>

                                <View style={{ height: 1, backgroundColor: 'rgba(0,0,0,0.05)', marginBottom: 12 }} />

                                <Text style={[styles.cardSubtitle, { fontSize: 13, fontWeight: 'bold', marginBottom: 4, color: '#64748b' }]}>ANSWER</Text>
                                <Text style={[styles.cardTitle, { fontSize: 16, fontWeight: 'normal', color: '#334155' }]}>{decodeHtml(item.a || item.Answer)}</Text>

                                {(item.e || item.Explanation) && (
                                    <>
                                        <View style={{ height: 1, backgroundColor: 'rgba(0,0,0,0.05)', marginVertical: 10 }} />
                                        <Text style={[styles.cardSubtitle, { fontSize: 13, fontWeight: 'bold', marginBottom: 4, color: '#64748b' }]}>EXPLANATION</Text>
                                        <Text style={[styles.cardTitle, { fontSize: 14, fontWeight: 'normal', color: '#475569', fontStyle: 'italic' }]}>
                                            {decodeHtml(item.e || item.Explanation)}
                                        </Text>
                                    </>
                                )}
                            </View>
                        );
                    })}
                </ScrollView>
            );
        }

        const currentData = getTabSpecificData(activeTab);

        // Only show full-screen loader if specific tab data is empty
        if (loading && currentData.length === 0) {
            return <ActivityIndicator size="large" color="#4f46e5" style={styles.loader} />;
        }

        return (
            <FlatList
                data={currentData}
                renderItem={activeTab === 'Notes' ? renderNoteItem : renderVideoItem}
                keyExtractor={(item, index) => (item.note_id || item.video_id || `item-${index}`).toString()}
                contentContainerStyle={[styles.listContainer, contentContainerPadding]}
                maxToRenderPerBatch={10}
                windowSize={5}
                removeClippedSubviews={Platform.OS === 'android'}
                refreshing={refreshing}
                onRefresh={onRefresh}
                ListHeaderComponent={null}
                ListEmptyComponent={
                    <View style={[styles.emptyContainer, contentContainerPadding]}>
                        <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No {activeTab.toLowerCase()} found.</Text>
                    </View>
                }
            />
        );
    };

    return (
        <View style={[styles.mainWrapper, { backgroundColor: theme.background }]}>
            <StatusBar barStyle={isDarkMode ? 'light-content' : 'dark-content'} backgroundColor="transparent" translucent={true} />

            <SafeAreaView style={[styles.container, { backgroundColor: theme.background }]} edges={['top', 'left', 'right']}>
                {downloading && (
                    <View style={styles.loadingOverlay}>
                        <View style={styles.loadingBox}>
                            <ActivityIndicator size="large" color="#4f46e5" />
                            <Text style={styles.loadingText}>Downloading...</Text>
                            <Text style={styles.loadingText}>{Math.round(downloadProgress * 100)}%</Text>
                        </View>
                    </View>
                )}

                {/* Modern Header */}
                <View style={[styles.header, { backgroundColor: theme.background }]}>
                    <TouchableOpacity onPress={onHeaderBack} style={styles.backButton}>
                        <View style={styles.backButtonInner}>
                            <Text style={[styles.backButtonText, { color: theme.text }]}>←</Text>
                        </View>
                    </TouchableOpacity>

                    <View style={{ flex: 1, marginLeft: 8 }}>
                        <Text style={[styles.headerSubtitle, { color: theme.primary }]}>CHAPTER</Text>
                        <Text style={[styles.headerTitle, { color: theme.text }]} numberOfLines={1}>
                            {chapter?.chapter_name || 'Content'}
                        </Text>
                    </View>

                    <StudyTimer initialSeconds={taskTimer} isActive={isTaskActive} onFinish={finishTask} />
                </View>

                <TabSelector activeTab={activeTab} onTabPress={setActiveTab} theme={theme} t={t} />

                {/* Main Content Area */}
                {renderContent()}

                <VoiceSelectorModal
                    visible={voiceModalVisible}
                    onClose={() => setVoiceModalVisible(false)}
                    onVoiceSelected={() => {
                        stopTTS();
                    }}
                />
            </SafeAreaView>
        </View>
    );
};

const styles = StyleSheet.create({
    mainWrapper: {
        flex: 1,
    },
    container: {
        flex: 1,
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingTop: 4,
        paddingBottom: 6,
    },
    backButton: {
        justifyContent: 'center',
        alignItems: 'center',
    },
    backButtonInner: {
        width: 40,
        height: 40,
        borderRadius: 20,
        backgroundColor: 'rgba(100,116,139,0.1)',
        justifyContent: 'center',
        alignItems: 'center',
    },
    backButtonText: {
        fontSize: 22,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    headerSubtitle: {
        fontSize: 10,
        fontWeight: '800',
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 1,
        marginBottom: 2,
    },
    headerTitle: {
        fontSize: 20,
        fontWeight: '800',
        fontFamily: 'NotoSans-Bold',
    },
    tabContainer: {
        paddingVertical: 6,
        paddingHorizontal: 12,
        borderBottomWidth: 1,
        borderBottomColor: 'rgba(100,116,139,0.1)',
    },
    tabsRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        gap: 6,
    },
    tile: {
        flex: 1,
        paddingVertical: 8,
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 12,
        backgroundColor: '#F8FAFC',
        borderWidth: 1,
        borderColor: '#E2E8F0',
    },
    activeTile: {
        backgroundColor: '#4F46E5',
        borderColor: '#4F46E5',
        elevation: 2,
        shadowColor: '#4F46E5',
        shadowOpacity: 0.3,
        shadowRadius: 4,
    },
    tileIcon: {
        fontSize: 18,
        marginBottom: 2,
    },
    tileText: {
        fontSize: 10,
        fontWeight: '600',
        color: '#64748B',
        textAlign: 'center',
        fontFamily: 'NotoSans-Bold',
    },
    activeTileText: {
        color: 'white',
        fontWeight: 'bold',
    },
    loader: {
        marginTop: 50,
    },
    listContainer: {
        padding: 20,
    },
    card: {
        backgroundColor: 'white',
        borderRadius: 16,
        padding: 16,
        marginBottom: 16,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.05,
        shadowRadius: 8,
        borderWidth: 1,
        borderColor: '#f1f5f9',
    },
    iconContainer: {
        width: 40,
        height: 40,
        borderRadius: 20,
        backgroundColor: '#e0e7ff',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 12,
    },
    cardTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#1e293b',
        marginBottom: 4,
        fontFamily: 'NotoSans-Bold',
    },
    cardSubtitle: {
        fontSize: 14,
        color: '#64748b',
        marginBottom: 4,
        fontFamily: 'NotoSans-Regular',
    },
    duration: {
        fontSize: 12,
        color: '#94a3b8',
        fontFamily: 'NotoSans-Regular',
    },
    emptyContainer: {
        alignItems: 'center',
        justifyContent: 'center',
        marginTop: 80,
    },
    emptyText: {
        fontSize: 15,
        fontFamily: 'NotoSans-Regular',
    },
    setsContainer: {
        padding: 20,
        paddingTop: 10,
    },
    sectionHeader: {
        marginBottom: 20,
    },
    sectionHeaderRow: {
        marginBottom: 20,
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
    },
    voiceButton: {
        backgroundColor: '#e0e7ff',
        paddingHorizontal: 12,
        paddingVertical: 8,
        borderRadius: 20,
        flexDirection: 'row',
        alignItems: 'center',
    },
    voiceIcon: {
        fontSize: 16,
    },
    voiceText: {
        marginLeft: 6,
        color: '#4f46e5',
        fontWeight: 'bold',
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
    },
    setsGrid: {
        width: '100%',
    },
    setCard: {
        backgroundColor: 'white',
        borderRadius: 20,
        padding: 18,
        marginBottom: 16,
        flexDirection: 'row',
        alignItems: 'center',
        elevation: 3,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.06,
        shadowRadius: 8,
    },
    setIcon: {
        width: 48,
        height: 48,
        borderRadius: 24,
        backgroundColor: '#e0e7ff',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 16,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
    },
    setIconText: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#4f46e5',
        fontFamily: 'NotoSans-Bold',
    },
    setTitle: {
        fontSize: 17,
        fontWeight: 'bold',
        color: '#1e293b',
        fontFamily: 'NotoSans-Bold',
        marginBottom: 2,
    },
    setSubtitle: {
        fontSize: 13,
        color: '#64748b',
        fontFamily: 'NotoSans-Regular',
    },
    setArrow: {
        marginLeft: 'auto',
        justifyContent: 'center',
        alignItems: 'center',
    },
    playIcon: {
        marginLeft: 'auto',
        fontSize: 18,
        color: '#cbd5e1',
    },
    quizTitle: {
        fontSize: 22,
        fontWeight: '900',
        marginBottom: 4,
        fontFamily: 'NotoSans-Bold',
    },
    quizSubtitle: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
    },
    quizContainer: {
        padding: 16,
    },
    progressContainer: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        marginBottom: 12,
        alignItems: 'center',
    },
    progressText: {
        fontSize: 12,
        color: '#64748b',
        fontWeight: '600',
        fontFamily: 'NotoSans-Bold',
    },
    scoreText: {
        fontSize: 14,
        color: '#10b981',
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    questionCardContainer: {
        marginBottom: 16,
        elevation: 8,
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 10,
        borderRadius: 20,
        backgroundColor: 'white',
    },
    questionCard: {
        padding: 20,
        paddingTop: 30, // Space for badge
        borderRadius: 20,
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    questionBadge: {
        position: 'absolute',
        top: -12,
        left: 20,
        backgroundColor: '#4f46e5',
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 10,
        paddingVertical: 6,
        borderRadius: 10,
        elevation: 5,
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.3,
        shadowRadius: 4,
    },
    questionBadgeIcon: {
        fontSize: 12,
        marginRight: 4,
    },
    questionBadgeText: {
        color: 'white',
        fontSize: 10,
        fontWeight: '900',
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 1,
    },
    glossyOverlay: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: '40%',
        zIndex: 0,
    },
    questionText: {
        fontSize: 18,
        fontWeight: '800',
        color: '#1e293b',
        lineHeight: 25,
        fontFamily: 'NotoSans-Bold',
        marginTop: 5,
    },
    questionImage: {
        width: '100%',
        height: 200,
        borderRadius: 8,
        marginBottom: 16,
        backgroundColor: '#f0fdfa',
    },
    optionsList: {
        marginBottom: 10,
    },
    optionButton: {
        backgroundColor: 'white',
        padding: 14,
        borderRadius: 12,
        marginBottom: 8,
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    correctOption: {
        backgroundColor: '#10b981',
        borderColor: '#10b981',
    },
    wrongOption: {
        backgroundColor: '#ef4444',
        borderColor: '#ef4444',
    },
    optionText: {
        fontSize: 16,
        color: '#1e293b', // Sharper dark slate
        fontFamily: 'NotoSans-Bold', // Use Bold for better sharpness
    },
    whiteText: {
        color: 'white',
        fontWeight: 'bold',
    },
    explanationContainer: {
        backgroundColor: '#f8fafc',
        padding: 12,
        borderRadius: 12,
        marginBottom: 10,
        borderWidth: 1,
        borderColor: '#f1f5f9',
    },
    explanationTitle: {
        fontSize: 14,
        fontWeight: 'bold',
        color: '#166534',
        marginBottom: 4,
        fontFamily: 'NotoSans-Bold',
    },
    explanationText: {
        fontSize: 14,
        color: '#166534',
        marginBottom: 16,
        fontFamily: 'NotoSans-Regular',
    },
    nextButton: {
        backgroundColor: '#4f46e5',
        padding: 16,
        borderRadius: 12,
        alignItems: 'center',
    },
    nextButtonText: {
        color: 'white',
        fontSize: 16,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    prevButton: {
        backgroundColor: '#cbd5e1', // Slate 300
        padding: 16,
        borderRadius: 12,
        alignItems: 'center',
        flex: 1, // Share space if needed, or fixed width
    },
    prevButtonText: {
        color: '#334155', // Slate 700
        fontSize: 16,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    // Stylized Quiz UI Additions
    quizActionsRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 12,
        marginTop: 10,
    },
    prevButtonStylized: {
        borderRadius: 16,
        overflow: 'hidden',
        height: 54,
        width: 100,
        elevation: 3,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
    },
    prevButtonGradient: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 1.5,
        borderColor: '#e2e8f0',
    },
    prevButtonContent: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 6,
    },
    prevButtonIcon: {
        fontSize: 18,
        color: '#475569',
        fontWeight: 'bold',
    },
    prevButtonTextStylized: {
        color: '#475569',
        fontSize: 15,
        fontWeight: '700',
        fontFamily: 'NotoSans-Bold',
    },
    nextButtonStylized: {
        flex: 1,
        borderRadius: 16,
        overflow: 'hidden',
        height: 54,
        elevation: 8,
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.3,
        shadowRadius: 10,
    },
    nextButtonGradient: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        paddingHorizontal: 20,
    },
    nextButtonTextStylized: {
        color: 'white',
        fontSize: 16,
        fontWeight: '800',
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 0.5,
        textShadowColor: 'rgba(0, 0, 0, 0.2)',
        textShadowOffset: { width: 1, height: 1 },
        textShadowRadius: 2,
    },
    explanationHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 8,
        marginBottom: 8,
    },
    explanationEmoji: {
        fontSize: 18,
    },
    resultContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        padding: 20,
    },
    resultEmoji: {
        fontSize: 60,
        marginBottom: 20,
    },
    resultTitle: {
        fontSize: 24,
        fontWeight: 'bold',
        color: '#0f172a',
        marginBottom: 10,
        fontFamily: 'NotoSans-Bold',
    },
    resultScore: {
        fontSize: 20,
        color: '#4f46e5',
        fontWeight: 'bold',
        marginBottom: 30,
        fontFamily: 'NotoSans-Bold',
    },
    restartButton: {
        backgroundColor: 'white',
        paddingHorizontal: 30,
        paddingVertical: 14,
        borderRadius: 30,
        marginBottom: 12,
        width: '80%',
        alignItems: 'center',
        borderWidth: 1,
        borderColor: '#4f46e5',
    },
    restartButtonText: {
        color: '#4f46e5',
        fontSize: 16,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    nextSetButton: {
        backgroundColor: '#4f46e5',
        paddingHorizontal: 30,
        paddingVertical: 14,
        borderRadius: 30,
        marginBottom: 12,
        width: '80%',
        alignItems: 'center',
        elevation: 4,
    },
    nextSetButtonText: {
        color: 'white',
        fontSize: 16,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    backToContentText: {
        color: '#64748b',
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
    },
    timerContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#f3e8ff', // Light Purple
        paddingHorizontal: 10,
        paddingVertical: 5,
        borderRadius: 20,
        borderWidth: 1,
        borderColor: '#d8b4fe'
    },
    timerText: {
        fontSize: 14,
        fontWeight: 'bold',
        color: '#7e22ce',
        marginRight: 8,
        fontVariant: ['tabular-nums']
    },
    finishBtn: {
        backgroundColor: '#7e22ce',
        paddingHorizontal: 8,
        paddingVertical: 4,
        borderRadius: 12
    },
    finishBtnText: {
        color: 'white',
        fontSize: 10,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    loadingOverlay: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'center',
        alignItems: 'center',
        zIndex: 1000,
    },
    loadingBox: {
        backgroundColor: 'white',
        padding: 20,
        borderRadius: 10,
        alignItems: 'center',
        elevation: 5,
    },
    loadingText: {
        marginTop: 10,
        fontSize: 16,
        color: '#333',
        fontFamily: 'NotoSans-Bold',
    },
});

export default ChapterContentScreen;