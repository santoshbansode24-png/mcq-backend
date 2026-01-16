import React, { useState, useRef, useEffect, useCallback, useMemo } from 'react';
import {
    View, Text, TouchableOpacity, StyleSheet,
    StatusBar, Platform, Animated, Vibration, BackHandler
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import { useLanguage } from '../context/LanguageContext';
import AsyncStorage from '@react-native-async-storage/async-storage';

// Import Screens
import HomeScreen from './HomeScreen';
import ProfileScreen from './ProfileScreen';
import cacheManager from '../utils/cache';
import LeaderboardScreen from './LeaderboardScreen';
import SubjectsScreen from './SubjectsScreen';
import ChaptersScreen from './ChaptersScreen';
import ChapterContentScreen from './ChapterContentScreen';
import PDFViewerScreen from './PDFViewerScreen';
import NotificationsScreen from './NotificationsScreen';
import AIScreen from './AIScreen';
import AITutorScreen from './AITutorScreen';
import HomeworkSolverScreen from './HomeworkSolverScreen';
import EnglishTutorScreen from './EnglishTutorScreen';
import QuizGeneratorScreen from './QuizGeneratorScreen';
import VocabBoosterScreen from './VocabBoosterScreen';
import VocabDashboardScreen from './VocabDashboardScreen';
import MentalMathsScreen from './MentalMathsScreen';
import NotesScreen from './NotesScreen';
import FlashcardsScreen from './FlashcardsScreen';
import QuickRevisionScreen from './QuickRevisionScreen';
import MyExamScreen from './MyExamScreen';
import MyExamTestScreen from './MyExamTestScreen';
import ForgotPasswordScreen from './ForgotPasswordScreen';

// --- Tab Button Component ---
const TabButton = React.memo(({ icon, label, isActive, onPress, theme }) => {
    const animValue = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        Animated.timing(animValue, {
            toValue: isActive ? 1 : 0,
            duration: 300,
            useNativeDriver: true,
        }).start();
    }, [isActive]);

    const translateY = animValue.interpolate({
        inputRange: [0, 1],
        outputRange: [0, -2]
    });

    const iconColor = isActive ? theme.primary : (theme.isDarkMode ? '#94a3b8' : '#64748b');

    return (
        <TouchableOpacity
            style={styles.tabButton}
            onPress={() => {
                if (!isActive) Vibration.vibrate(5);
                onPress();
            }}
            activeOpacity={1}
        >
            <Animated.View style={{ alignItems: 'center', transform: [{ translateY }] }}>
                <View style={styles.iconWrapper}>
                    <Ionicons name={isActive ? icon : `${icon}-outline`} size={26} color={iconColor} />
                </View>
                <Animated.Text
                    style={[
                        styles.tabLabel,
                        {
                            color: isActive ? theme.primary : theme.textSecondary,
                            opacity: isActive ? 1 : 0.7,
                            fontWeight: isActive ? '700' : '500'
                        }
                    ]}
                    numberOfLines={1}
                >
                    {label}
                </Animated.Text>
            </Animated.View>
        </TouchableOpacity>
    );
});

// --- Main Screen ---
const MainScreen = ({ navigation: parentNavigation, route }) => {
    const { theme, isDarkMode } = useTheme();
    const { t } = useLanguage();
    // Navigation History Stack: Array of { screen, params }
    // Navigation History Stack: Array of { screen, params }
    const [historyStack, setHistoryStack] = useState([{ screen: 'Home', params: {} }]);

    // Restore user variable
    // Use local state to manage user updates (e.g., class change)
    const [userState, setUserState] = useState(route.params?.user);

    // Update user state handler
    const handleUpdateUser = useCallback(async (updates) => {
        const newUser = { ...userState, ...updates };
        setUserState(newUser);

        // Also update AsyncStorage
        try {
            await AsyncStorage.setItem('user_data', JSON.stringify(newUser));
        } catch (error) {
            console.error("Failed to update user in storage:", error);
        }
    }, [userState]);

    // Derive current view and params from the top of the stack
    const currentState = historyStack[historyStack.length - 1];
    const currentView = currentState.screen;
    const viewParams = currentState.params || {};

    // Determine Active Tab
    const activeTab = useMemo(() => {
        const mapping = {
            'Home': ['Home', 'Leaderboard', 'Notifications', 'Profile', 'VocabDashboard', 'VocabBooster', 'MentalMaths', 'MyExam', 'MyExamTest'],
            'Subjects': ['Subjects', 'Chapters', 'ChapterContent', 'PDFViewer', 'Notes', 'Flashcards', 'QuickRevision'],
            'AI': ['AI', 'AITutor', 'HomeworkSolver', 'EnglishTutor', 'QuizGenerator']
        };
        for (const [key, screens] of Object.entries(mapping)) {
            if (screens.includes(currentView)) return key;
        }
        return 'Home';
    }, [currentView]);

    const handleNavigate = useCallback((screen, params = {}) => {
        if (screen === 'VideoPlayer') {
            parentNavigation.navigate(screen, params);
            return;
        }

        // Check if we are just switching tabs (Home, AI, Subjects)
        const isRootTab = ['Home', 'Subjects', 'AI'].includes(screen);

        if (isRootTab) {
            // Reset stack if switching to a root tab
            setHistoryStack([{ screen, params }]);
        } else {
            // Push to stack
            setHistoryStack(prev => [...prev, { screen, params }]);
        }
    }, [parentNavigation]);

    // Handle Hardware Back Button
    const handleGoBack = useCallback(() => {
        if (historyStack.length > 1) {
            // Pop current screen
            setHistoryStack(prev => prev.slice(0, -1));
            return true;
        } else {
            // If at root of stack but not at Home, go Home
            if (currentView !== 'Home') {
                setHistoryStack([{ screen: 'Home', params: {} }]);
                return true;
            }
            // If at Home, let default behavior happen (exit)
            return false;
        }
    }, [historyStack, currentView]);

    useEffect(() => {
        const backAction = () => {
            return handleGoBack();
        };

        const backHandler = BackHandler.addEventListener(
            'hardwareBackPress',
            backAction
        );

        return () => backHandler.remove();
    }, [handleGoBack]);

    const handleLogout = useCallback(async () => {
        try {
            // Clear all data
            await cacheManager.clearAll();
            await AsyncStorage.removeItem('user_data');

            // Navigate to Login
            parentNavigation.reset({
                index: 0,
                routes: [{ name: 'Login' }],
            });
        } catch (error) {
            console.error("Logout failed:", error);
            parentNavigation.reset({
                index: 0,
                routes: [{ name: 'Login' }],
            });
        }
    }, [parentNavigation]);

    const commonProps = {
        user: userState,
        onUserUpdate: handleUpdateUser,
        onLogout: handleLogout,
        navigation: {
            navigate: handleNavigate,
            goBack: handleGoBack,
            replace: parentNavigation.replace,
            addListener: () => { }
        },
        route: { params: viewParams }
    };

    const renderContent = () => {
        switch (currentView) {
            case 'Home': return <HomeScreen {...commonProps} />;
            case 'Subjects': return <SubjectsScreen {...commonProps} />;
            case 'AI': return <AIScreen {...commonProps} />;
            case 'Profile': return <ProfileScreen {...commonProps} />;
            case 'Leaderboard': return <LeaderboardScreen {...commonProps} />;
            case 'Notifications': return <NotificationsScreen {...commonProps} />;
            case 'Chapters': return <ChaptersScreen {...commonProps} />;
            case 'ChapterContent': return <ChapterContentScreen {...commonProps} />;
            case 'PDFViewer': return <PDFViewerScreen {...commonProps} />;
            case 'Notes': return <NotesScreen {...commonProps} />;
            case 'AITutor': return <AITutorScreen {...commonProps} />;
            case 'HomeworkSolver': return <HomeworkSolverScreen {...commonProps} />;
            case 'EnglishTutor': return <EnglishTutorScreen {...commonProps} />;
            case 'VocabDashboard': return <VocabDashboardScreen {...commonProps} />;
            case 'VocabBooster': return <VocabBoosterScreen {...commonProps} />;
            case 'MentalMaths': return <MentalMathsScreen {...commonProps} />;
            case 'QuizGenerator': return <QuizGeneratorScreen {...commonProps} />;
            case 'Flashcards': return <FlashcardsScreen {...commonProps} />;
            case 'QuickRevision': return <QuickRevisionScreen {...commonProps} />;
            case 'MyExam': return <MyExamScreen {...commonProps} />;
            case 'MyExamTest': return <MyExamTestScreen {...commonProps} />;
            case 'ForgotPassword': return <ForgotPasswordScreen {...commonProps} />;
            default: return <HomeScreen {...commonProps} />;
        }
    };

    const tabs = [
        { key: 'Home', icon: 'home', label: t('home') },
        { key: 'Subjects', icon: 'book', label: t('subject') },
        { key: 'AI', icon: 'sparkles', label: t('aiTools') },
    ];

    return (
        <View style={styles.container}>
            <StatusBar barStyle={isDarkMode ? 'light-content' : 'dark-content'} backgroundColor="transparent" translucent />

            <SafeAreaView style={styles.safeAreaTop} edges={['top']}>
                <View style={styles.content}>
                    {renderContent()}
                </View>
            </SafeAreaView>

            {/* Bottom Navigation */}
            <View style={[
                styles.bottomNavContainer,
                {
                    backgroundColor: isDarkMode ? '#1e293b' : '#ffffff',
                    borderTopColor: isDarkMode ? '#334155' : '#e2e8f0',
                    shadowColor: "#000",
                }
            ]}>
                <SafeAreaView edges={['bottom']} style={styles.safeArea}>
                    <View style={styles.tabRow}>
                        {tabs.map((tab) => (
                            <TabButton
                                key={tab.key}
                                icon={tab.icon}
                                label={tab.label}
                                isActive={activeTab === tab.key}
                                onPress={() => handleNavigate(tab.key)}
                                theme={theme}
                            />
                        ))}
                    </View>
                </SafeAreaView>
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    safeAreaTop: { flex: 1 },
    content: { flex: 1, paddingBottom: Platform.OS === 'ios' ? 85 : 65 },
    bottomNavContainer: {
        position: 'absolute', bottom: 0, left: 0, right: 0,
        borderTopWidth: 1, elevation: 10,
        shadowOffset: { width: 0, height: -3 }, shadowOpacity: 0.05, shadowRadius: 3,
        zIndex: 100,
    },
    safeArea: { width: '100%' },
    tabRow: {
        flexDirection: 'row', height: 60,
        justifyContent: 'space-around', alignItems: 'center', width: '100%',
        // paddingBottom: 0, // Removed extra padding
    },
    tabButton: { flex: 1, alignItems: 'center', justifyContent: 'center', height: '100%' },
    iconWrapper: { width: 32, height: 32, justifyContent: 'center', alignItems: 'center', marginBottom: 2 },
    tabLabel: { fontSize: 11, textAlign: 'center' },
});

export default MainScreen;