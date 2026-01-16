import React, { useState, useEffect, useRef } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, ActivityIndicator,
    Alert, Animated, Dimensions, Platform, StatusBar, Vibration
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import { fetchVocabSet, submitRating, completeVocabSet } from '../api/vocab';
import { fonts } from '../styles/typography';

const { width } = Dimensions.get('window');
const STATUSBAR_HEIGHT = Platform.OS === 'android' ? StatusBar.currentHeight : 0;

const VocabBoosterScreen = ({ user, navigation, route }) => {
    const { theme, isDarkMode } = useTheme();
    const [loading, setLoading] = useState(true);
    const [reviewWords, setReviewWords] = useState([]);
    const [currentIndex, setCurrentIndex] = useState(0);
    const [selectedOption, setSelectedOption] = useState(null);
    const [showResult, setShowResult] = useState(false);
    const [setInfo, setSetInfo] = useState(null);
    const [correctCount, setCorrectCount] = useState(0);

    const progressAnim = useRef(new Animated.Value(0)).current;
    const fadeAnim = useRef(new Animated.Value(0)).current;

    const setNumber = route?.params?.setNumber || 1;

    useEffect(() => {
        loadWords();
    }, []);

    useEffect(() => {
        if (reviewWords.length > 0) {
            Animated.timing(progressAnim, {
                toValue: ((currentIndex + 1) / reviewWords.length) * 100,
                duration: 500,
                useNativeDriver: false,
            }).start();
        }
    }, [currentIndex, reviewWords]);

    const loadWords = async () => {
        try {
            const response = await fetchVocabSet(user.user_id, setNumber);
            if (response.status === 'success') {
                const words = response.data.words || [];

                const wordsWithOptions = words.map((word) => {
                    // TRUST THE DATABASE: We use the exact options saved in the DB
                    const optionsArray = [
                        word.options.A,
                        word.options.B,
                        word.options.C,
                        word.options.D
                    ].filter(Boolean);

                    return {
                        ...word,
                        options: optionsArray,
                        // The DB stores correct_answer as "A", "B", etc. We map that to the text.
                        correctAnswerText: word.options[word.correct_answer]
                    };
                });

                setReviewWords(wordsWithOptions);
                setSetInfo(response.data);
            } else {
                Alert.alert('Error', response.message || 'Failed to load words');
            }
        } catch (error) {
            console.error('Error loading vocab:', error);
            Alert.alert('Error', 'Failed to load vocabulary.');
        } finally {
            setLoading(false);
        }
    };

    const handleOptionSelect = (option) => {
        if (showResult) return;
        Vibration.vibrate(50);
        setSelectedOption(option);
        setShowResult(true);
        Animated.timing(fadeAnim, { toValue: 1, duration: 400, useNativeDriver: true }).start();
    };

    const handleNext = async () => {
        const word = reviewWords[currentIndex];

        // Check if selected option matches the correct text
        const isCorrect = selectedOption === word.correctAnswerText;

        try {
            // 4 = Pass (Mastered), 2 = Fail (Review)
            submitRating(user.user_id, word.word_id, isCorrect ? 4 : 2, 0);

            if (isCorrect) setCorrectCount(prev => prev + 1);

            if (currentIndex < reviewWords.length - 1) {
                fadeAnim.setValue(0);
                setSelectedOption(null);
                setShowResult(false);
                setCurrentIndex(currentIndex + 1);
            } else {
                handleSetCompletion(correctCount + (isCorrect ? 1 : 0));
            }
        } catch (error) {
            console.error('Error saving progress', error);
        }
    };

    const handleSetCompletion = async (finalScore) => {
        const totalWords = reviewWords.length;
        const percentage = Math.round((finalScore / totalWords) * 100);
        const passed = percentage >= 70;
        const currentSet = setInfo?.set_number || 1;

        if (passed) {
            completeVocabSet(user.user_id, currentSet, finalScore, totalWords).catch(err => console.error(err));
        }

        Alert.alert(
            passed ? '🎉 Set Complete!' : '📚 Keep Practicing',
            passed
                ? `Score: ${percentage}%\nYou have unlocked the next set!`
                : `Score: ${percentage}%\nYou need 70% to pass. Try again!`,
            [{ text: 'Back to Dashboard', onPress: () => navigation.navigate('VocabDashboard') }]
        );
    };

    if (loading) return <View style={styles.loadingContainer}><ActivityIndicator size="large" color={theme.primary} /></View>;
    if (reviewWords.length === 0) return null;

    const word = reviewWords[currentIndex];
    const isCorrect = selectedOption === word.correctAnswerText;

    return (
        <View style={{ flex: 1, backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }}>
            <StatusBar barStyle={isDarkMode ? 'light-content' : 'dark-content'} translucent backgroundColor="transparent" />

            <View style={styles.container}>
                <LinearGradient colors={isDarkMode ? ['#0f172a', '#1e293b'] : ['#f8fafc', '#e2e8f0']} style={styles.background} />

                {/* Header */}
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.iconButton}>
                        <Ionicons name="close" size={24} color={theme.text} />
                    </TouchableOpacity>
                    <View style={styles.progressWrapper}>
                        <View style={styles.progressTrack}>
                            <Animated.View
                                style={[styles.progressFill, {
                                    backgroundColor: theme.primary,
                                    width: progressAnim.interpolate({ inputRange: [0, 100], outputRange: ['0%', '100%'] })
                                }]}
                            />
                        </View>
                    </View>
                    <View style={styles.scoreContainer}>
                        <Text style={[styles.scoreText, { color: theme.primary }]}>{correctCount}</Text>
                    </View>
                </View>

                {/* Main Content */}
                <View style={styles.mainContent}>

                    {/* Word Card */}
                    <View style={[styles.card, { backgroundColor: isDarkMode ? '#1e293b' : '#ffffff' }]}>
                        <Text style={[styles.label, { color: theme.textSecondary }]}>WORD</Text>
                        <Text style={[styles.wordText, { color: theme.text, fontFamily: fonts.regular }]}>{word.word}</Text>
                        <View style={[styles.divider, { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }]} />
                        <Text style={[styles.instruction, { color: theme.textSecondary }]}>
                            Select the correct <Text style={{ fontWeight: 'bold', color: theme.primary }}>Synonym</Text>:
                        </Text>
                    </View>

                    {/* Options Grid */}
                    <View style={styles.optionsContainer}>
                        {word.options.map((option, index) => {
                            const isSelected = selectedOption === option;
                            const isThisCorrect = option === word.correctAnswerText;

                            let borderColor = 'transparent';
                            let backgroundColor = isDarkMode ? '#334155' : '#ffffff';
                            let textColor = theme.text;

                            if (showResult) {
                                if (isThisCorrect) {
                                    borderColor = '#22c55e'; backgroundColor = isDarkMode ? 'rgba(34, 197, 94, 0.2)' : '#dcfce7'; textColor = '#15803d';
                                } else if (isSelected) {
                                    borderColor = '#ef4444'; backgroundColor = isDarkMode ? 'rgba(239, 68, 68, 0.2)' : '#fee2e2'; textColor = '#991b1b';
                                } else {
                                    backgroundColor = isDarkMode ? '#1e293b' : '#f1f5f9'; textColor = theme.textSecondary;
                                }
                            } else if (isSelected) {
                                borderColor = theme.primary; backgroundColor = isDarkMode ? '#1e293b' : '#f0f9ff'; textColor = theme.primary;
                            }

                            return (
                                <TouchableOpacity
                                    key={index}
                                    activeOpacity={0.9}
                                    disabled={showResult}
                                    onPress={() => handleOptionSelect(option)}
                                    style={[styles.optionButton, { borderColor, backgroundColor }]}
                                >
                                    <View style={styles.optionContent}>
                                        <View style={[styles.letterBadge, { backgroundColor: isDarkMode ? 'rgba(255,255,255,0.1)' : '#f1f5f9' }]}>
                                            <Text style={[styles.letterText, { color: theme.textSecondary }]}>{String.fromCharCode(65 + index)}</Text>
                                        </View>
                                        <Text style={[styles.optionText, { color: textColor, fontFamily: fonts.regular }]} numberOfLines={1}>{option}</Text>
                                    </View>
                                </TouchableOpacity>
                            );
                        })}
                    </View>

                    {/* Result Box */}
                    {showResult && (
                        <Animated.View style={[styles.resultBox, { opacity: fadeAnim, backgroundColor: isDarkMode ? '#1e293b' : '#fff' }]}>
                            <View style={styles.resultHeader}>
                                <Ionicons name={isCorrect ? "checkmark-circle" : "close-circle"} size={20} color={isCorrect ? '#16a34a' : '#dc2626'} />
                                <Text style={[styles.resultTitle, { color: isCorrect ? '#16a34a' : '#dc2626' }]}>
                                    {isCorrect ? 'Correct!' : 'Wrong'}
                                </Text>
                            </View>

                            {/* MARATHI EXPLANATION */}
                            <Text style={[styles.defLabel, { color: theme.textSecondary }]}>MARATHI MEANING:</Text>
                            <Text style={[styles.defTextMarathi, { color: theme.text }]}>
                                {word.definition_marathi || "No Marathi definition available."}
                            </Text>

                            <Text style={[styles.defLabel, { color: theme.textSecondary, marginTop: 4 }]}>ENGLISH:</Text>
                            <Text style={[styles.defText, { color: theme.textSecondary }]} numberOfLines={2}>{word.definition}</Text>
                        </Animated.View>
                    )}
                </View>

                {/* Bottom Button (Only show Next/Finish after answering) */}
                {showResult && (
                    <View style={[styles.bottomContainer, { borderTopColor: isDarkMode ? '#1e293b' : '#e2e8f0', backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]}>
                        <TouchableOpacity
                            style={[styles.actionButton, { backgroundColor: theme.primary }]}
                            onPress={handleNext}
                        >
                            <Text style={[styles.actionButtonText, { color: '#fff' }]}>
                                {currentIndex < reviewWords.length - 1 ? 'Next Word' : 'Finish'}
                            </Text>
                            <Ionicons name="arrow-forward" size={18} color="#fff" />
                        </TouchableOpacity>
                    </View>
                )}
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    container: { flex: 1 },
    background: { position: 'absolute', left: 0, right: 0, top: 0, bottom: 0 },
    header: { flexDirection: 'row', alignItems: 'center', paddingTop: STATUSBAR_HEIGHT + 10, paddingHorizontal: 16, paddingBottom: 10, gap: 12 },
    iconButton: { padding: 4 },
    progressWrapper: { flex: 1 },
    progressTrack: { height: 6, backgroundColor: 'rgba(0,0,0,0.05)', borderRadius: 3, overflow: 'hidden' },
    progressFill: { height: '100%', borderRadius: 3 },
    scoreContainer: { backgroundColor: 'rgba(0,0,0,0.05)', paddingHorizontal: 10, paddingVertical: 2, borderRadius: 8 },
    scoreText: { fontWeight: 'bold', fontSize: 13 },
    mainContent: { flex: 1, paddingHorizontal: 20, justifyContent: 'flex-start', paddingTop: 10 },
    card: { borderRadius: 20, padding: 20, alignItems: 'center', marginBottom: 15, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 5, elevation: 2 },
    label: { fontSize: 10, fontWeight: '800', letterSpacing: 1.2, marginBottom: 5 },
    wordText: { fontSize: 28, fontWeight: '800', textAlign: 'center', marginBottom: 15, fontFamily: fonts.regular },
    divider: { height: 1, width: '100%', marginBottom: 15, opacity: 0.5 },
    instruction: { fontSize: 13, fontStyle: 'italic' },
    optionsContainer: { gap: 10, marginBottom: 15 },
    optionButton: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 12, paddingHorizontal: 16, borderRadius: 12, borderWidth: 1.5 },
    optionContent: { flexDirection: 'row', alignItems: 'center', flex: 1, gap: 12 },
    letterBadge: { width: 24, height: 24, borderRadius: 8, justifyContent: 'center', alignItems: 'center' },
    letterText: { fontSize: 11, fontWeight: 'bold' },
    optionText: { fontSize: 14, fontWeight: '600', flex: 1, fontFamily: fonts.regular },
    resultBox: { borderRadius: 16, padding: 15, borderWidth: 1, borderColor: 'rgba(0,0,0,0.05)', marginTop: 'auto', marginBottom: 10 },
    resultHeader: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 8 },
    resultTitle: { fontSize: 16, fontWeight: 'bold' },
    defLabel: { fontSize: 10, fontWeight: '700', opacity: 0.6, marginBottom: 2 },
    defText: { fontSize: 13, fontWeight: '500', marginBottom: 4 },
    defTextMarathi: { fontSize: 16, fontWeight: 'bold', marginBottom: 8, lineHeight: 24 },
    bottomContainer: { padding: 20, paddingBottom: 30, borderTopWidth: 1 },
    actionButton: { flexDirection: 'row', justifyContent: 'center', alignItems: 'center', paddingVertical: 14, borderRadius: 14, gap: 8 },
    actionButtonText: { fontSize: 15, fontWeight: 'bold' },
});

export default VocabBoosterScreen;