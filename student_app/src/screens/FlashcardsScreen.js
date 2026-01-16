import React, { useState, useEffect, useRef } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, ActivityIndicator,
    Animated, Dimensions, StatusBar, Platform, SafeAreaView, Pressable
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { Audio } from 'expo-av'; // Import Audio
import { useTheme } from '../context/ThemeContext';
import { fetchFlashcards, markSetCompleted } from '../api/content'; // Import from content.js for caching

import { fonts } from '../styles/typography';
import AsyncStorage from '@react-native-async-storage/async-storage'; // Import AsyncStorage

const { width } = Dimensions.get('window');
const STATUSBAR_HEIGHT = Platform.OS === 'android' ? StatusBar.currentHeight : 0;
const SWIPE_THRESHOLD = 100; // Reduced for easier swiping

// Colorful gradient combinations for cards
const CARD_GRADIENTS = [
    ['#8E2DE2', '#4A00E0'], // Rich Violet
    ['#1f4037', '#99f2c8'], // Emerald Dark (Green is readable)
    ['#C33764', '#1D2671'], // Red to Dark Blue
    ['#4568DC', '#B06AB3'], // Deep Purple to Pink (Medium)
    ['#1A2980', '#26D0CE'], // Deep Blue to Aqua
    ['#e65c00', '#F9D423'], // Deep Orange (Medium)
    ['#DD5E89', '#F7BB97'], // Dark Pink (Medium)
    ['#3ca55c', '#b5ac49'], // Dark Green
    ['#fc4a1a', '#f7b733'], // Bright Orange (Text readable with shadow)
    ['#cc2b5e', '#753a88'], // Purple Love
];

const FlashcardsScreen = ({ navigation, route }) => {
    const themeContext = useTheme();
    const theme = themeContext?.theme || { primary: '#4f46e5', text: '#000', textSecondary: '#666' };
    const isDarkMode = themeContext?.isDarkMode || false;

    const { chapterId, chapterName, flashcardsData, setLabel, setIndex } = route.params || {}; // Get setIndex

    const [loading, setLoading] = useState(true);
    const [cards, setCards] = useState([]);
    const [currentIndex, setCurrentIndex] = useState(0);
    const [isFlipped, setIsFlipped] = useState(false);
    const isFlippedRef = useRef(false); // Ref to track valid state inside PanResponder closure
    const [error, setError] = useState(null);


    // Update ref when state changes
    useEffect(() => {
        isFlippedRef.current = isFlipped;
    }, [isFlipped]);

    // Animation Values
    // Standard Flip Animation
    const flipAnim = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        if (flashcardsData && flashcardsData.length > 0) {
            setCards(flashcardsData);
            setLoading(false);
        } else if (chapterId) {
            loadCards();
        } else {
            console.warn("No ChapterId provided to FlashcardsScreen");
            setLoading(false);
            setError("No Chapter Selected");
        }
    }, [chapterId, flashcardsData]);

    const loadCards = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await fetchFlashcards(chapterId);

            if (response && (response.status === 'success' || Array.isArray(response))) {
                const data = Array.isArray(response) ? response : (response.data || []);
                setCards(data);
            } else {
                setError(response?.message || "Failed to load cards");
            }
        } catch (error) {
            console.error("Error loading cards:", error);
            setError("Network Error: " + error.message);
        } finally {
            setLoading(false);
        }
    };



    const playFlipSound = async () => {
        try {
            const { sound } = await Audio.Sound.createAsync(
                require('../../assets/sounds/flip.mp3')
            );
            await sound.playAsync();
            // Unload sound from memory after playback
            sound.setOnPlaybackStatusUpdate(async (status) => {
                if (status.didJustFinish) {
                    await sound.unloadAsync();
                }
            });
        } catch (error) {
            console.log('Error playing sound (ensure assets/sounds/flip.mp3 exists):', error);
        }
    };

    const flipCard = () => {
        playFlipSound(); // Play sound on flip

        // Use ref to check current state
        if (isFlippedRef.current) {
            Animated.spring(flipAnim, {
                toValue: 0,
                friction: 8,
                tension: 10,
                useNativeDriver: Platform.OS !== 'web',
            }).start();
        } else {
            Animated.spring(flipAnim, {
                toValue: 180,
                friction: 8,
                tension: 10,
                useNativeDriver: Platform.OS !== 'web',
            }).start();
        }
        // Update both
        setIsFlipped(!isFlippedRef.current);
    };

    const nextCard = () => {
        if (currentIndex < cards.length - 1) {
            resetFlip();
            setTimeout(() => setCurrentIndex(prev => prev + 1), 300);
        }
    };

    const prevCard = () => {
        if (currentIndex > 0) {
            resetFlip();
            setTimeout(() => setCurrentIndex(prev => prev - 1), 300);
        }
    };

    const resetFlip = () => {
        if (isFlippedRef.current) {
            Animated.spring(flipAnim, {
                toValue: 0,
                friction: 8,
                tension: 10,
                useNativeDriver: Platform.OS !== 'web',
            }).start();
            setIsFlipped(false);
        }
    };

    const shuffleCards = () => {
        resetFlip();
        setTimeout(() => {
            const shuffled = [...cards].sort(() => Math.random() - 0.5);
            setCards(shuffled);
            setCurrentIndex(0);
        }, 300);
    };



    const frontInterpolate = flipAnim.interpolate({
        inputRange: [0, 180],
        outputRange: ['0deg', '180deg'],
    });

    const backInterpolate = flipAnim.interpolate({
        inputRange: [0, 180],
        outputRange: ['180deg', '360deg'],
    });

    const frontOpacity = flipAnim.interpolate({
        inputRange: [89, 90],
        outputRange: [1, 0]
    });

    const backOpacity = flipAnim.interpolate({
        inputRange: [89, 90],
        outputRange: [0, 1]
    });

    // Z-Index Optimization for proper touch handling
    const frontZIndex = flipAnim.interpolate({
        inputRange: [0, 90],
        outputRange: [1, 0] // Front is clickable when 0-90
    });

    const backZIndex = flipAnim.interpolate({
        inputRange: [90, 180],
        outputRange: [0, 1] // Back is clickable when 90-180
    });


    const frontAnimatedStyle = {
        transform: [{ rotateY: frontInterpolate }],
        opacity: frontOpacity,
        zIndex: frontZIndex
    };

    const backAnimatedStyle = {
        transform: [{ rotateY: backInterpolate }],
        opacity: backOpacity,
        zIndex: backZIndex
    };

    // Get gradient colors for current card
    const getCardGradient = (index) => {
        return CARD_GRADIENTS[index % CARD_GRADIENTS.length];
    };

    if (loading) return <View style={styles.center}><ActivityIndicator size="large" color={theme.primary} /></View>;

    if (error) return (
        <View style={styles.center}>
            <Text style={{ color: 'red', marginBottom: 10 }}>{error}</Text>
            <TouchableOpacity onPress={loadCards} style={styles.retryBtn}>
                <Text style={{ color: 'white' }}>Retry</Text>
            </TouchableOpacity>
        </View>
    );

    if (!cards || cards.length === 0) return (
        <View style={styles.center}>
            <Ionicons name="albums-outline" size={64} color="#ccc" />
            <Text style={{ color: theme.textSecondary, marginTop: 20 }}>No Flashcards Available for this Chapter.</Text>
            <TouchableOpacity onPress={() => navigation.goBack()} style={[styles.retryBtn, { backgroundColor: theme.textSecondary, marginTop: 20 }]}>
                <Text style={{ color: 'white' }}>Go Back</Text>
            </TouchableOpacity>
        </View>
    );

    const currentCard = cards[currentIndex];

    // Safety check: if card is undefined, show loading or error
    if (!currentCard) {
        return (
            <View style={styles.center}>
                <ActivityIndicator size="large" color={theme.primary} />
            </View>
        );
    }

    const currentGradient = getCardGradient(currentIndex);

    return (
        <SafeAreaView style={[styles.container, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]}>
            <StatusBar barStyle={isDarkMode ? 'light-content' : 'dark-content'} backgroundColor={isDarkMode ? '#0f172a' : '#f8fafc'} />

            {/* Header */}
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.iconBtn}>
                    <Ionicons name="arrow-back" size={24} color={theme.text} />
                </TouchableOpacity>
                <Text style={[styles.headerTitle, { color: theme.text }]}>
                    {setLabel ? `${setLabel}` : (chapterName || 'Flashcards')}
                </Text>
                <View style={{ flexDirection: 'row', gap: 10 }}>

                    <TouchableOpacity onPress={shuffleCards} style={styles.iconBtn}>
                        <Ionicons name="shuffle" size={24} color={theme.primary} />
                    </TouchableOpacity>
                </View>
            </View>

            {/* Progress Bar */}
            <View style={styles.progressContainer}>
                <Text style={{ color: theme.textSecondary, marginBottom: 5 }}>
                    Card {currentIndex + 1} of {cards.length}
                </Text>
                <View style={styles.progressBarBg}>
                    <View style={[styles.progressBarFill, {
                        width: `${((currentIndex + 1) / cards.length) * 100}%`,
                        backgroundColor: currentGradient[0]
                    }]} />
                </View>
            </View>



            {/* Card Area */}
            <View style={styles.cardArea}>
                <Pressable
                    onPress={flipCard}
                    style={styles.cardContainer}
                >
                    {/* Front Side */}
                    <Animated.View style={[styles.card, frontAnimatedStyle, { backfaceVisibility: 'hidden' }]}>
                        <LinearGradient
                            colors={currentGradient}
                            start={{ x: 0, y: 0 }}
                            end={{ x: 1, y: 1 }}
                            style={styles.gradientCard}
                        >
                            <Text style={styles.label}>{currentCard.subject || 'Q'} • {currentCard.topic || 'General'}</Text>
                            <View style={styles.centerContent}>
                                <Text style={styles.cardText}>{currentCard.question_front || ''}</Text>
                                <Text style={styles.tapHint}>Tap to flip</Text>
                            </View>
                        </LinearGradient>
                    </Animated.View>

                    {/* Back Side */}
                    <Animated.View style={[styles.card, styles.cardBack, backAnimatedStyle, { backfaceVisibility: 'hidden' }]}>
                        <LinearGradient
                            colors={[currentGradient[1], currentGradient[0]]} // Reverse gradient for back
                            start={{ x: 0, y: 0 }}
                            end={{ x: 1, y: 1 }}
                            style={styles.gradientCard}
                        >
                            <Text style={styles.label}>ANSWER</Text>
                            <View style={styles.centerContent}>
                                <Text style={[styles.cardText, { fontWeight: 'bold' }]}>{currentCard.answer_back || ''}</Text>
                                <Text style={styles.tapHint}>Tap to flip</Text>
                            </View>
                        </LinearGradient>
                    </Animated.View>
                </Pressable>
            </View>

            {/* Controls */}
            <View style={styles.controls}>
                <TouchableOpacity
                    style={[styles.btn, {
                        backgroundColor: currentIndex === 0 ? '#ccc' : currentGradient[0],
                        opacity: currentIndex === 0 ? 0.5 : 1
                    }]}
                    onPress={prevCard}
                    disabled={currentIndex === 0}
                >
                    <Ionicons name="arrow-back" size={24} color="white" />
                    <Text style={styles.btnText}>Prev</Text>
                </TouchableOpacity>

                <TouchableOpacity
                    style={[styles.btn, {
                        backgroundColor: currentIndex === cards.length - 1 ? '#22c55e' : (currentIndex === cards.length - 1 ? '#ccc' : currentGradient[1]),
                        opacity: 1
                    }]}
                    onPress={async () => {
                        if (currentIndex < cards.length - 1) {
                            nextCard();
                        } else {
                            // Mark as completed
                            try {
                                const userId = await AsyncStorage.getItem('user_id');
                                if (userId && chapterId && setIndex !== undefined) {
                                    await markSetCompleted(userId, chapterId, setIndex);
                                }
                                navigation.goBack();
                            } catch (e) { console.warn(e); navigation.goBack(); }
                        }
                    }}
                >
                    <Text style={styles.btnText}>{currentIndex === cards.length - 1 ? 'Finish' : 'Next'}</Text>
                    <Ionicons name={currentIndex === cards.length - 1 ? "checkmark-circle" : "arrow-forward"} size={24} color="white" />
                </TouchableOpacity>
            </View>
        </SafeAreaView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        paddingTop: Platform.OS === 'android' ? StatusBar.currentHeight : 0
    },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, paddingVertical: 10 },
    headerTitle: { fontSize: 20, fontWeight: 'bold' },
    iconBtn: { padding: 10 },

    progressContainer: { paddingHorizontal: 20, marginBottom: 10 },
    progressBarBg: { height: 6, backgroundColor: '#e2e8f0', borderRadius: 3, overflow: 'hidden' },
    progressBarFill: { height: '100%', borderRadius: 3 },

    swipeHintContainer: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        paddingHorizontal: 20,
        marginBottom: 10,
    },
    swipeHint: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 4,
    },
    swipeHintText: {
        fontSize: 11,
        opacity: 0.6,
    },

    cardArea: { flex: 1, alignItems: 'center', justifyContent: 'flex-start', padding: 20, paddingTop: 40 },
    cardContainer: { width: '100%', maxWidth: 400, aspectRatio: 0.8 },
    card: {
        width: '100%',
        height: '100%',
        borderRadius: 20,
        shadowColor: "#000",
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.3,
        shadowRadius: 15,
        elevation: 10,
        position: 'absolute',
        top: 0,
    },
    cardBack: {},
    gradientCard: {
        width: '100%',
        height: '100%',
        borderRadius: 20,
        padding: 30,
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    centerContent: { flex: 1, justifyContent: 'center', alignItems: 'center', width: '100%' },
    label: {
        alignSelf: 'flex-start',
        fontSize: 12,
        fontWeight: 'bold',
        letterSpacing: 1,
        color: 'rgba(255, 255, 255, 0.8)',
    },
    cardText: {
        fontSize: 24,
        textAlign: 'center',
        lineHeight: 32,
        fontFamily: fonts.regular,
        color: 'rgba(255, 255, 255, 0.9)',
        // Strong shadow
        textShadowColor: 'rgba(0, 0, 0, 0.75)',
        textShadowOffset: { width: 0, height: 1 },
        textShadowRadius: 4,
    },
    tapHint: {
        marginTop: 20,
        fontSize: 12,
        color: 'rgba(255, 255, 255, 0.6)',
    },

    controls: { flexDirection: 'row', justifyContent: 'space-between', padding: 30, paddingBottom: 75 },
    btn: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: 12,
        paddingHorizontal: 25,
        borderRadius: 30,
        gap: 5,
        shadowColor: "#000",
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.2,
        shadowRadius: 4,
        elevation: 3,
    },
    btnText: { color: 'white', fontWeight: 'bold', fontSize: 16 },
    retryBtn: { padding: 10, backgroundColor: '#4f46e5', borderRadius: 8 }
});

export default FlashcardsScreen;