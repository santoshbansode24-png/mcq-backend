import React, { useState, useEffect, useCallback, useMemo } from 'react';
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
                            {item.title || `Note Lesson ${index + 1}`}
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
                        <Text style={{ fontSize: 20 }}>🎥</Text>
                    </View>
                    <View style={{ flex: 1, marginLeft: 16 }}>
                        <Text style={[styles.cardTitle, { color: 'white', fontSize: 16, fontWeight: '800' }]} numberOfLines={1}>
                            {item.title || `Video Lesson ${index + 1}`}
                        </Text>
                        <Text style={[styles.cardSubtitle, { color: 'rgba(255,255,255,0.95)', fontSize: 13 }]} numberOfLines={1}>
                            {item.description || 'Watch video'}
                        </Text>
                    </View>
                </View>
            </LinearGradient>
        </TouchableOpacity>
    );
});

const ChapterContentScreen = ({ navigation, route }) => {
    const isFocused = useIsFocused();
    const { theme, isDarkMode } = useTheme();
    const { t } = useLanguage();
    const { chapter, activeTask } = route.params || {}; // activeTask contains timer info
    const [activeTab, setActiveTab] = useState(route.params?.initialTab || 'Flashcards'); // Use initialTab if passed
    const [downloading, setDownloading] = useState(false);
    const [downloadProgress, setDownloadProgress] = useState(0);
    const [loading, setLoading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

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
            console.log('[Timer] Starting for task:', activeTask.title);
            setTaskTimer(activeTask.duration_minutes * 60);
            setIsTaskActive(true);
        }
    }, [activeTask]);

    useEffect(() => {
        let interval = null;
        if (isTaskActive && taskTimer > 0) {
            interval = setInterval(() => {
                setTaskTimer((prev) => prev - 1);
            }, 1000);
        } else if (taskTimer === 0 && isTaskActive) {
            finishTask();
        }
        return () => clearInterval(interval);
    }, [isTaskActive, taskTimer]);

    // Handle Hardware Back Button
    useEffect(() => {
        const backAction = () => {
            if (isTaskActive) {
                Alert.alert(
                    "Abandon Mission?",
                    "Timer is still running. Do you want to leave?",
                    [
                        { text: "Stay", style: "cancel" },
                        { text: "Leave", style: "destructive", onPress: () => navigation.goBack() }
                    ]
                );
                return true;
            }
            navigation.goBack();
            return true;
        };

        const backHandler = BackHandler.addEventListener(
            "hardwareBackPress",
            backAction
        );

        return () => backHandler.remove();
    }, [isTaskActive, navigation]);

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

    // Quiz State
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
    const [flashcardSets, setFlashcardSets] = useState([]); // New state for Flashcard sets
    const [revisionData, setRevisionData] = useState([]); // New state for Quick Revision
    const [playingIndex, setPlayingIndex] = useState(null); // TTS State
    const [setStatuses, setSetStatuses] = useState({}); // Stores {'0': {status:'completed'} } for current tab
    const [userAnswers, setUserAnswers] = useState({}); // Stores user answers for current quiz {0: 'a', 1: 'b' }



    useEffect(() => {
        // Refresh status whenever the screen comes into focus
        if (isFocused && chapter?.chapter_id) {
            if (activeTab === 'MCQs') loadSetStatus('mcq');
            if (activeTab === 'Flashcards') loadSetStatus('flashcard');
        }
    }, [isFocused, activeTab]);

    useEffect(() => {
        if (isFocused && chapter?.chapter_id) {
            loadContent();
        }
    }, [isFocused, activeTab, chapter?.chapter_id]);

    const loadContent = async (isRefreshing = false) => {
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
            const force = true; // Always bypass internal API cache to get fresh data

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

                    <TouchableOpacity style={styles.backToContentButton} onPress={() => setQuizMode(false)}>
                        <Text style={styles.backToContentText}>Back to Sets</Text>
                    </TouchableOpacity>
                </View>
            );
        }

        const question = quizQuestions[currentQuestionIndex];
        return (
            <ScrollView contentContainerStyle={styles.quizContainer}>
                <View style={styles.progressContainer}>
                    <Text style={styles.progressText}>Set {currentSetIndex + 1} • Q{currentQuestionIndex + 1}/{quizQuestions.length}</Text>
                    <Text style={styles.scoreText}>Score: {score}</Text>
                </View>

                <View style={styles.questionCard}>
                    {question.image_url && (
                        <Image
                            source={{ uri: `${BASE_URL}/uploads/${question.image_url}` }}
                            style={styles.questionImage}
                            resizeMode="contain"
                        />
                    )}
                    <Text style={styles.questionText}>{decodeHtml(question.question)}</Text>
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
                        <Text style={styles.explanationTitle}>Explanation:</Text>
                        <Text style={styles.explanationText}>{decodeHtml(question.explanation) || 'No explanation available.'}</Text>
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 10 }}>
                            {currentQuestionIndex > 0 && (
                                <TouchableOpacity style={styles.prevButton} onPress={prevQuestion}>
                                    <Text style={styles.prevButtonText}>Previous</Text>
                                </TouchableOpacity>
                            )}
                            <TouchableOpacity style={[styles.nextButton, { flex: 1 }]} onPress={nextQuestion}>
                                <Text style={styles.nextButtonText}>
                                    {currentQuestionIndex === quizQuestions.length - 1 ? 'Finish Set' : 'Next Question'}
                                </Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                )}
            </ScrollView>
        );
    };

    const handleOpenNote = useCallback(async (item) => {
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

    const handleOpenVideo = useCallback((item) => {
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

        if (activeTab === 'MCQs') {
            if (quizMode) return renderQuiz();

            if (mcqSets.length === 0) {
                return (
                    <ScrollView
                        contentContainerStyle={styles.emptyContainer}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                    >
                        <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No MCQs available for this chapter.</Text>
                    </ScrollView>
                );
            }

            return (
                <ScrollView
                    contentContainerStyle={styles.setsContainer}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                >
                    <Text style={[styles.quizTitle, { color: theme.text }]}>MCQ Practice Sets</Text>
                    <Text style={[styles.quizSubtitle, { color: theme.textSecondary }]}>Select a set to start practicing</Text>

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
                        contentContainerStyle={styles.emptyContainer}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                    >
                        <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No Flashcards available for this chapter.</Text>
                    </ScrollView>
                );
            }

            return (
                <ScrollView
                    contentContainerStyle={styles.setsContainer}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                >
                    <Text style={[styles.quizTitle, { color: theme.text }]}>Flashcard Sets</Text>
                    <Text style={[styles.quizSubtitle, { color: theme.textSecondary }]}>Select a set to start learning</Text>

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
                        contentContainerStyle={styles.emptyContainer}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                    >
                        <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No Quick Revision notes available.</Text>
                    </ScrollView>
                );
            }

            return (
                <ScrollView
                    contentContainerStyle={styles.listContainer}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                >
                    <View style={{ marginBottom: 20, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                        <View>
                            <Text style={[styles.quizTitle, { color: theme.text }]}>Quick Revision</Text>
                            <Text style={[styles.quizSubtitle, { color: theme.textSecondary }]}>Key points for {chapter.chapter_name}</Text>
                        </View>
                        <TouchableOpacity
                            onPress={() => setVoiceModalVisible(true)}
                            style={{ backgroundColor: '#e0e7ff', padding: 8, borderRadius: 20, flexDirection: 'row', alignItems: 'center' }}
                        >
                            <Text style={{ fontSize: 16 }}>🗣️</Text>
                            <Text style={{ marginLeft: 5, color: '#4f46e5', fontWeight: 'bold', fontSize: 12 }}>VOICE</Text>
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
                contentContainerStyle={styles.listContainer}
                maxToRenderPerBatch={10}
                windowSize={5}
                removeClippedSubviews={Platform.OS === 'android'}
                refreshing={refreshing}
                onRefresh={onRefresh}
                ListHeaderComponent={null}
                ListEmptyComponent={
                    <View style={styles.emptyContainer}>
                        <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No {activeTab.toLowerCase()} found.</Text>
                    </View>
                }
            />
        );
    };

    return (
        <View style={[styles.mainWrapper, { backgroundColor: theme.background }]}>
            <StatusBar barStyle={isDarkMode ? 'light-content' : 'dark-content'} backgroundColor="transparent" translucent={true} />

            <SafeAreaView style={[styles.container, { backgroundColor: theme.background }]} edges={['top', 'left', 'right', 'bottom']}>
                {downloading && (
                    <View style={styles.loadingOverlay}>
                        <View style={styles.loadingBox}>
                            <ActivityIndicator size="large" color="#4A90E2" />
                            <Text style={styles.loadingText}>Downloading...</Text>
                            <Text style={styles.loadingText}>{Math.round(downloadProgress * 100)}%</Text>
                        </View>
                    </View>
                )}
                <View style={[styles.header, { backgroundColor: theme.card, borderBottomColor: theme.border }]}>
                    <TouchableOpacity onPress={() => {
                        if (isTaskActive) {
                            Alert.alert(
                                "Abandon Mission?",
                                "Timer is still running. Do you want to leave?",
                                [
                                    { text: "Stay", style: "cancel" },
                                    { text: "Leave", style: "destructive", onPress: () => navigation.goBack() }
                                ]
                            );
                        } else {
                            navigation.goBack();
                        }
                    }} style={styles.backButton}>
                        <Text style={[styles.backButtonText, { color: theme.text }]}>←</Text>
                    </TouchableOpacity>

                    <View style={{ flex: 1 }}>
                        <Text style={[styles.headerTitle, { color: theme.text }]} numberOfLines={1}>{chapter?.chapter_name || 'Content'}</Text>
                    </View>

                    {isTaskActive && (
                        <View style={styles.timerContainer}>
                            <Text style={styles.timerText}>⏳ {formatTimer(taskTimer)}</Text>
                            <TouchableOpacity onPress={finishTask} style={styles.finishBtn}>
                                <Text style={styles.finishBtnText}>Done</Text>
                            </TouchableOpacity>
                        </View>
                    )}
                </View>

                <View style={[styles.tabContainer, { backgroundColor: theme.card, borderBottomColor: theme.border }]}>
                    <View style={styles.tabsRow}>
                        {(() => {
                            const tabs = [
                                { id: 'Flashcards', icon: '🗂️', label: t('flashcards'), color: '#10b981' },
                                { id: 'MCQs', icon: '📝', label: t('mcqs'), color: '#3b82f6' },
                                { id: 'QuickRevision', icon: '⚡', label: t('revision'), color: '#f59e0b' },
                                { id: 'Videos', icon: '🎥', label: t('videos'), color: '#ef4444' },
                                { id: 'Notes', icon: '📄', label: t('notes'), color: '#8b5cf6' },
                            ];
                            return tabs.map((tab) => {
                                const isActive = activeTab === tab.id;
                                return (
                                    <TouchableOpacity
                                        key={tab.id}
                                        style={[
                                            styles.tile,
                                            {
                                                backgroundColor: isActive ? tab.color : theme.card,
                                                borderColor: tab.color,
                                                elevation: isActive ? 8 : 2,
                                                shadowColor: tab.color,
                                                transform: [{ translateY: isActive ? -4 : 0 }]
                                            }
                                        ]}
                                        onPress={() => setActiveTab(tab.id)}
                                        activeOpacity={0.9}
                                    >
                                        <Text style={[styles.tileIcon, { fontSize: 20 }]}>{tab.icon}</Text>
                                        <Text style={[
                                            styles.tileText,
                                            {
                                                color: isActive ? 'white' : tab.color,
                                                fontWeight: 'bold',
                                                fontSize: 11
                                            }
                                        ]} numberOfLines={1}>
                                            {tab.label}
                                        </Text>
                                    </TouchableOpacity>
                                );
                            });
                        })()}
                    </View>
                </View>

                {renderContent()}

                <VoiceSelectorModal
                    visible={voiceModalVisible}
                    onClose={() => setVoiceModalVisible(false)}
                    onVoiceSelected={() => {
                        // Optional: Ensure current playback stops or restarts with new voice
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
        // backgroundColor handled by theme
    },
    container: {
        flex: 1,
        // backgroundColor handled by theme
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingTop: 8,
        paddingBottom: 8,
        // backgroundColor handled by theme
        borderBottomWidth: 1,
        // borderBottomColor handled by theme
    },
    backButton: {
        padding: 5,
        marginRight: 10,
    },
    backButtonText: {
        fontSize: 24,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    headerTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        flex: 1,
        fontFamily: 'NotoSans-Bold',
        textTransform: 'uppercase',
    },
    tabContainer: {
        // backgroundColor handled by theme
        paddingVertical: 8,
        paddingHorizontal: 12,
        borderBottomWidth: 1,
        // borderBottomColor handled by theme
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
        borderRadius: 12,
        padding: 16,
        marginBottom: 16,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 4,
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
        marginTop: 50,
    },
    emptyText: {
        fontSize: 16,
        // color handled by theme
        fontStyle: 'italic',
        fontFamily: 'NotoSans-Regular',
    },
    setsContainer: {
        padding: 20,
        alignItems: 'center',
    },
    setsGrid: {
        width: '100%',
    },
    setCard: {
        backgroundColor: 'white',
        borderRadius: 16,
        padding: 16,
        marginBottom: 16,
        flexDirection: 'row',
        alignItems: 'center',
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 4,
    },
    setIcon: {
        width: 44,
        height: 44,
        borderRadius: 22,
        backgroundColor: '#e0e7ff',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 16,
    },
    setIconText: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#4f46e5',
        fontFamily: 'NotoSans-Bold',
    },
    setCardTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#1e293b',
        fontFamily: 'NotoSans-Bold',
    },
    setCardSubtitle: {
        fontSize: 13,
        color: '#64748b',
        fontFamily: 'NotoSans-Regular',
    },
    playIcon: {
        marginLeft: 'auto',
        fontSize: 18,
        color: '#cbd5e1',
    },
    quizTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        // color handled by theme
        marginBottom: 4,
        marginTop: 10,
        fontFamily: 'NotoSans-Bold',
    },
    quizSubtitle: {
        fontSize: 14,
        // color handled by theme
        marginBottom: 20,
        fontFamily: 'NotoSans-Regular',
    },
    quizContainer: {
        padding: 20,
    },
    progressContainer: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        marginBottom: 20,
    },
    progressText: {
        fontSize: 14,
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
    questionCard: {
        backgroundColor: '#f0fdfa', // Light Teal Background
        padding: 24,
        borderRadius: 16,
        marginBottom: 24,
        elevation: 3,
        shadowColor: '#0d9488', // Teal Shadow
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 8,
        borderWidth: 4,
        borderColor: '#2dd4bf', // Medium Teal Border
        borderStyle: 'solid',
        borderLeftWidth: 4, // Explicit for consistency
        borderRightWidth: 4,
        borderTopWidth: 4,
        borderBottomWidth: 4,
    },
    questionText: {
        fontSize: 18,
        fontWeight: '700',
        color: '#134e4a', // Dark Teal Text
        lineHeight: 28,
        fontFamily: 'NotoSans-Bold',
    },
    questionImage: {
        width: '100%',
        height: 200,
        borderRadius: 8,
        marginBottom: 16,
        backgroundColor: '#f0fdfa',
    },
    optionsList: {
        marginBottom: 20,
    },
    optionButton: {
        backgroundColor: 'white',
        padding: 16,
        borderRadius: 12,
        marginBottom: 10,
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
        fontSize: 15,
        color: '#334155',
        fontFamily: 'NotoSans-Regular',
    },
    whiteText: {
        color: 'white',
        fontWeight: 'bold',
    },
    explanationContainer: {
        backgroundColor: '#f0fdf4',
        padding: 16,
        borderRadius: 12,
        marginBottom: 20,
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