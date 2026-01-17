import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, FlatList, ActivityIndicator, Alert, ScrollView, StatusBar, Platform, RefreshControl } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { useIsFocused } from '@react-navigation/native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { fetchMCQs, fetchNotes, fetchVideos, recordMCQAttempt, fetchFlashcards, fetchQuickRevision } from '../api/content';
import axios from 'axios';
import { API_URL } from '../api/config';
import * as Speech from 'expo-speech';
import { getBestVoice } from '../utils/voiceUtils'; // Assuming this util exists or I should use the logic from QuickRevisionScreen
import { fetchSetStatus } from '../api/content'; // Import new API
import AsyncStorage from '@react-native-async-storage/async-storage'; // Ensure imported
import { useTheme } from '../context/ThemeContext';
import { useLanguage } from '../context/LanguageContext';

const ChapterContentScreen = ({ navigation, route }) => {
    const isFocused = useIsFocused();
    const { theme } = useTheme();
    const { t } = useLanguage();
    const { chapter } = route.params || {};
    const [activeTab, setActiveTab] = useState('Flashcards'); // Default: Flashcards
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(false);

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
    const [setStatuses, setSetStatuses] = useState({}); // Stores { '0': {status:'completed'} } for current tab
    const [userAnswers, setUserAnswers] = useState({}); // Stores user answers for current quiz { 0: 'a', 1: 'b' }

    const [refreshing, setRefreshing] = useState(false);

    useEffect(() => {
        // Refresh status whenever the screen comes into focus
        if (isFocused && chapter?.chapter_id) {
            if (activeTab === 'MCQs') loadSetStatus('mcq');
            if (activeTab === 'Flashcards') loadSetStatus('flashcard');
        }
    }, [isFocused, activeTab]);

    useEffect(() => {
        console.log('[ChapterContent] Route Params:', route.params);
        console.log('[ChapterContent] Chapter Object:', chapter);
        if (chapter?.chapter_id) {
            loadContent();
        } else {
            console.error('[ChapterContent] No chapter_id found!');
        }
    }, [chapter, activeTab]);

    const loadContent = async (isRefreshing = false) => {
        // Optimization: Avoid fetching for tabs that verify navigation only
        /* if (activeTab === 'QuickRevision') {
            setLoading(false);
            setRefreshing(false);
            return;
        } */

        // Stop speech when leaving tab or refreshing
        Speech.stop();
        setPlayingIndex(null);

        if (isRefreshing) {
            setRefreshing(true);
        } else {
            setLoading(true);
        }

        // Only clear data if NOT refreshing, to prevent flickering
        if (!isRefreshing) {
            setData([]);
            setData([]);
            setMcqSets([]);
            setFlashcardSets([]);
            setRevisionData([]);
            setQuizMode(false);
            setQuizFinished(false);
        }

        try {
            let response;
            // Force refresh passed to API calls
            const force = isRefreshing;

            if (activeTab === 'MCQs') {
                response = await fetchMCQs(chapter.chapter_id, force);
            } else if (activeTab === 'Notes') {
                response = await fetchNotes(chapter.chapter_id, force);
            } else if (activeTab === 'Videos') {
                response = await fetchVideos(chapter.chapter_id, force);
            } else if (activeTab === 'Flashcards') {
                response = await fetchFlashcards(chapter.chapter_id);
            } else if (activeTab === 'QuickRevision') {
                response = await fetchQuickRevision(chapter.chapter_id);
            }

            if (response && response.status === 'success') {
                if (activeTab === 'MCQs') {
                    // Chunk MCQs into sets of 10
                    const allMcqs = response.data;
                    const chunks = [];
                    for (let i = 0; i < allMcqs.length; i += 10) {
                        chunks.push(allMcqs.slice(i, i + 10));
                    }
                    setMcqSets(chunks);
                    setData(allMcqs);
                    // Fetch Status for MCQs
                    loadSetStatus('mcq');
                } else if (activeTab === 'Flashcards') {
                    // Chunk Flashcards into sets of 10
                    const allCards = Array.isArray(response) ? response : (response.data || []);
                    const chunks = [];
                    for (let i = 0; i < allCards.length; i += 10) {
                        chunks.push(allCards.slice(i, i + 10));
                    }
                    setFlashcardSets(chunks);
                    setData(allCards);
                    // Fetch Status for Flashcards
                    loadSetStatus('flashcard');
                } else if (activeTab === 'QuickRevision') {
                    if (response.data && response.data.length > 0) {
                        // The API returns an array, the first item contains key_points
                        const points = response.data[0]?.key_points || [];
                        // User previously requested to skip the first point
                        setRevisionData(points.slice(1));
                    } else {
                        setRevisionData([]);
                    }
                } else {
                    setData(response.data);
                }
            } else if (response) {
                if (!response.message.includes('No')) {
                    Alert.alert('Error', response.message);
                }
            }
        } catch (error) {
            Alert.alert('Error', 'Failed to load content');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const loadSetStatus = async (type) => {
        try {
            const userId = await AsyncStorage.getItem('user_id');
            if (userId && chapter.chapter_id) {
                const statusData = await fetchSetStatus(userId, chapter.chapter_id, type);
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
        AsyncStorage.getItem('user_id').then(userId => {
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
            console.log('Using Voice:', bestVoice);

            Speech.speak(textToSpeak, {
                language: 'en-IN', // Base language (fallback)
                voice: bestVoice,  // Specific voice identifier (e.g., Marathi)
                pitch: 1.0,
                rate: 0.85,        // Slightly slower for better clarity
                onDone: () => setPlayingIndex(null),
                onStopped: () => setPlayingIndex(null),
                onError: (e) => {
                    console.log('TTS Error', e);
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

    // Color Palette for Items (User Requested: Poppy Noisy Neon)
    const itemColors = [
        { border: '#06b6d4', shadow: '#06b6d4' }, // Neon Cyan
        { border: '#d946ef', shadow: '#d946ef' }, // Neon Magenta
        { border: '#84cc16', shadow: '#84cc16' }, // Neon Lime
        { border: '#facc15', shadow: '#facc15' }, // Neon Yellow
    ];

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

    // Color Palette for Revision Points (User Requested: Different Soft Colors)
    const revisionColors = [
        '#fff1f2', // Soft Rose
        '#fffbeb', // Soft Amber
        '#f0fdf4', // Soft Green
        '#eff6ff', // Soft Blue
        '#faf5ff', // Soft Purple
        '#fff7ed', // Soft Orange
    ];

    const renderNoteItem = ({ item, index }) => {
        const gradient = noteGradients[index % noteGradients.length];
        return (
            <TouchableOpacity
                style={[styles.card, { padding: 0, overflow: 'hidden', borderWidth: 0, elevation: 6 }]}
                onPress={() => navigation.navigate('PDFViewer', { url: item.file_url, title: item.title })}
            >
                <LinearGradient
                    colors={gradient}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 1 }}
                    style={{ padding: 16, width: '100%', height: '100%', flexDirection: 'row', alignItems: 'center' }}
                >
                    <View style={[styles.iconContainer, { backgroundColor: 'rgba(255,255,255,0.2)' }]}>
                        <Text style={{ fontSize: 20 }}>📄</Text>
                    </View>
                    <View style={{ flex: 1 }}>
                        <Text style={[styles.cardTitle, { color: 'white' }]}>{item.title}</Text>
                        <Text style={[styles.cardSubtitle, { color: 'rgba(255,255,255,0.9)', fontWeight: 'bold' }]}>{item.note_type?.toUpperCase() || 'PDF'}</Text>
                    </View>
                    <TouchableOpacity onPress={() => navigation.navigate('PDFViewer', { url: item.file_url, title: item.title })}>
                        <Text style={{ fontSize: 24, color: 'white' }}>⬇️</Text>
                    </TouchableOpacity>
                </LinearGradient>
            </TouchableOpacity>
        );
    };

    const renderVideoItem = ({ item, index }) => {
        const gradient = videoGradients[index % videoGradients.length];
        return (
            <TouchableOpacity
                onPress={() => navigation.navigate('VideoPlayer', { videoUrl: item.url, title: item.title })}
                style={[styles.card, { padding: 0, overflow: 'hidden', borderWidth: 0, elevation: 6 }]} // Remove default padding for gradient
            >
                <LinearGradient
                    colors={gradient}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 1 }}
                    style={{ padding: 16, width: '100%', height: '100%' }}
                >
                    <Text style={[styles.cardTitle, { color: 'white' }]}>🎥 {item.title}</Text>
                    <Text style={[styles.cardSubtitle, { color: 'rgba(255,255,255,0.95)' }]}>{item.description || 'Click to watch video'}</Text>
                    <Text style={[styles.duration, { color: 'white', fontWeight: 'bold', opacity: 0.9 }]}>Duration: {item.duration || 'N/A'}</Text>
                </LinearGradient>
            </TouchableOpacity>
        );
    };

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
        if (loading) return <ActivityIndicator size="large" color="#4f46e5" style={styles.loader} />;

        if (activeTab === 'MCQs') {
            if (quizMode) return renderQuiz();

            if (mcqSets.length === 0) {
                return (
                    <ScrollView
                        contentContainerStyle={styles.emptyContainer}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                    >
                        <Text style={styles.emptyText}>No MCQs available for this chapter.</Text>
                    </ScrollView>
                );
            }

            return (
                <ScrollView
                    contentContainerStyle={styles.setsContainer}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                >
                    <Text style={styles.quizTitle}>MCQ Practice Sets</Text>
                    <Text style={styles.quizSubtitle}>Select a set to start practicing</Text>

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
                                        <Text style={[styles.setIconText, { color: color.text }, isSolved && { color: '#16a34a' }]}>{index + 1}</Text>
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
                        <Text style={styles.emptyText}>No Flashcards available for this chapter.</Text>
                    </ScrollView>
                );
            }

            return (
                <ScrollView
                    contentContainerStyle={styles.setsContainer}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                >
                    <Text style={styles.quizTitle}>Flashcard Sets</Text>
                    <Text style={styles.quizSubtitle}>Select a set to start learning</Text>

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
                                            setIndex: index
                                        });
                                    }}
                                >
                                    <View style={[styles.setIcon, { backgroundColor: 'white' }, isSolved && { backgroundColor: '#dcfce7' }]}>
                                        <Text style={[styles.setIconText, { color: color.text }, isSolved && { color: '#16a34a' }]}>{index + 1}</Text>
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
            if (revisionData.length === 0) {
                return (
                    <ScrollView
                        contentContainerStyle={styles.emptyContainer}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                    >
                        <Text style={styles.emptyText}>No Quick Revision notes available.</Text>
                    </ScrollView>
                );
            }

            return (
                <ScrollView
                    contentContainerStyle={styles.listContainer}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                >
                    <View style={{ marginBottom: 20 }}>
                        <Text style={styles.quizTitle}>Quick Revision</Text>
                        <Text style={styles.quizSubtitle}>Key points for {chapter.chapter_name}</Text>
                    </View>

                    {revisionData.map((item, index) => {
                        const bgColor = revisionColors[index % revisionColors.length];
                        return (
                            <View key={index} style={[styles.card, { backgroundColor: bgColor }, playingIndex === index && { borderColor: '#4f46e5', borderWidth: 2 }]}>
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

        return (
            <FlatList
                data={data}
                renderItem={activeTab === 'Notes' ? renderNoteItem : renderVideoItem}
                keyExtractor={(item) => (item.note_id || item.video_id || Math.random()).toString()}
                contentContainerStyle={styles.listContainer}
                refreshing={refreshing}
                onRefresh={onRefresh}
                ListHeaderComponent={null}
                ListEmptyComponent={
                    <View style={styles.emptyContainer}>
                        <Text style={styles.emptyText}>No {activeTab.toLowerCase()} found.</Text>
                    </View>
                }
            />
        );
    };

    return (
        <View style={styles.mainWrapper}>
            <StatusBar barStyle="dark-content" backgroundColor="transparent" translucent={true} />

            <SafeAreaView style={styles.container} edges={['top', 'left', 'right', 'bottom']}>
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                        <Text style={styles.backButtonText}>←</Text>
                    </TouchableOpacity>
                    <Text style={styles.headerTitle} numberOfLines={1}>{chapter?.chapter_name || 'Content'}</Text>
                </View>

                <View style={styles.tabContainer}>
                    <View style={styles.tabsRow}>
                        {[
                            { id: 'Flashcards', icon: '🗂️', label: t('flashcards'), color: '#10b981', lightColor: '#ecfdf5' }, // Emerald
                            { id: 'MCQs', icon: '📝', label: t('mcqs'), color: '#3b82f6', lightColor: '#eff6ff' }, // Blue
                            { id: 'Videos', icon: '🎥', label: t('videos'), color: '#ef4444', lightColor: '#fef2f2' }, // Red
                            { id: 'QuickRevision', icon: '⚡', label: t('revision'), color: '#f59e0b', lightColor: '#fffbeb' }, // Amber
                            { id: 'Notes', icon: '📄', label: t('notes'), color: '#8b5cf6', lightColor: '#f5f3ff' }, // Violet
                        ].map((tab) => {
                            const isActive = activeTab === tab.id;
                            return (
                                <TouchableOpacity
                                    key={tab.id}
                                    style={[
                                        styles.tile,
                                        {
                                            backgroundColor: isActive ? tab.color : 'white',
                                            borderColor: tab.color,
                                            elevation: isActive ? 8 : 2, // Pop up effect
                                            shadowColor: tab.color, // Colored shadow
                                            transform: [{ translateY: isActive ? -4 : 0 }] // Physical lift
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
                        })}
                    </View>
                </View>

                {renderContent()}
            </SafeAreaView>
        </View>
    );
};

const styles = StyleSheet.create({
    mainWrapper: {
        flex: 1,
        backgroundColor: '#FFFFFF',
    },
    container: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        paddingTop: 0, // Removed extra padding, relying on SafeAreaView
        paddingBottom: 12,
        backgroundColor: 'white',
        borderBottomWidth: 1,
        borderBottomColor: '#e2e8f0',
    },
    backButton: {
        padding: 5,
        marginRight: 10,
    },
    backButtonText: {
        fontSize: 24,
        color: '#333',
        fontWeight: 'bold',
    },
    headerTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#0f172a',
        flex: 1,
    },
    tabContainer: {
        backgroundColor: 'white',
        paddingVertical: 12,
        paddingHorizontal: 12,
        borderBottomWidth: 1,
        borderBottomColor: '#F1F5F9',
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
        color: '#333',
        marginBottom: 4,
    },
    cardSubtitle: {
        fontSize: 14,
        color: '#64748b',
        marginBottom: 4,
    },
    duration: {
        fontSize: 12,
        color: '#94a3b8',
    },
    emptyContainer: {
        alignItems: 'center',
        marginTop: 50,
    },
    emptyText: {
        fontSize: 16,
        color: '#94a3b8',
        fontStyle: 'italic',
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
    },
    setCardTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#1e293b',
    },
    setCardSubtitle: {
        fontSize: 13,
        color: '#64748b',
    },
    playIcon: {
        marginLeft: 'auto',
        fontSize: 18,
        color: '#cbd5e1',
    },
    quizTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#0f172a',
        marginBottom: 4,
        marginTop: 10,
    },
    quizSubtitle: {
        fontSize: 14,
        color: '#64748b',
        marginBottom: 20,
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
    },
    scoreText: {
        fontSize: 14,
        color: '#10b981',
        fontWeight: 'bold',
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
    },
    explanationText: {
        fontSize: 14,
        color: '#166534',
        marginBottom: 16,
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
    },
    resultScore: {
        fontSize: 20,
        color: '#4f46e5',
        fontWeight: 'bold',
        marginBottom: 30,
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
    },
    backToContentButton: {
        padding: 16,
    },
    backToContentText: {
        color: '#64748b',
        fontSize: 14,
    },
});

export default ChapterContentScreen;