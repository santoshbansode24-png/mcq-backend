import React, { useState, useRef, useEffect, useCallback, useMemo } from 'react';
import {
    View, Text, TouchableOpacity, StyleSheet,
    StatusBar, Platform, Animated, Vibration, BackHandler,
    InteractionManager
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
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
import { SmartCacheService } from '../services/SmartCacheService';
import PDFViewerScreen from './PDFViewerScreen';
import NotificationsScreen from './NotificationsScreen';
import AIScreen from './AIScreen';
import PDFToExamScreen from './PDFToExamScreen';

import HomeworkSolverScreen from './HomeworkSolverScreen';
import EnglishMissionMapScreen from './EnglishMissionMapScreen';
import VocabBoosterScreen from './VocabBoosterScreen';
import VocabDashboardScreen from './VocabDashboardScreen';
import MentalMathsScreen from './MentalMathsScreen';
import NotesScreen from './NotesScreen';
import FlashcardsScreen from './FlashcardsScreen';
import QuickRevisionScreen from './QuickRevisionScreen';
import MyExamScreen from './MyExamScreen';
import MyExamTestScreen from './MyExamTestScreen';
import ForgotPasswordScreen from './ForgotPasswordScreen';
import ScholarshipSubjectsScreen from './ScholarshipSubjectsScreen';
import ScholarshipChaptersScreen from './ScholarshipChaptersScreen';
import ScholarshipSetsScreen from './ScholarshipSetsScreen';
import WorksheetGeneratorScreen from './WorksheetGeneratorScreen';

import AIPdfExamScreen from './AIPdfExamScreen';
import AIPdfWorksheetScreen from './AIPdfWorksheetScreen';
import AIPdfNotesScreen from './AIPdfNotesScreen';

import StudyPlannerScreen from './StudyPlannerScreen';
import StudyDetailScreen from './StudyDetailScreen';
import ClassUpdatesScreen from './ClassUpdatesScreen';

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
    const insets = useSafeAreaInsets();

    // Navigation History Stack
    const [historyStack, setHistoryStack] = useState([{ screen: 'Home', params: {} }]);
    const [isHistoryLoaded, setIsHistoryLoaded] = useState(false);

    // Back Intercept Ref: child screens can register a function here to intercept back
    const backInterceptorRef = useRef(null);
    const registerBackInterceptor = useCallback((fn) => {
        backInterceptorRef.current = fn;
    }, []);
    const unregisterBackInterceptor = useCallback(() => {
        backInterceptorRef.current = null;
    }, []);

    // User State
    const [userState, setUserState] = useState(route.params?.user);

    // Load navigation history on mount
    useEffect(() => {
        const loadHistory = async () => {
            try {
                const savedHistory = await AsyncStorage.getItem('nav_history');
                if (savedHistory) {
                    const parsed = JSON.parse(savedHistory);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        setHistoryStack(parsed);
                    }
                }
            } catch (e) {
                console.error("Failed to load nav history", e);
            } finally {
                setIsHistoryLoaded(true);
            }
        };
        loadHistory();
    }, []);

    // Handle deep-link from notification: if App.js passes initialScreen, push it
    useEffect(() => {
        const screen = route?.params?.initialScreen;
        const initialParams = route?.params?.initialParams || {};
        if (screen && screen !== 'Home') {
            // Push after a short delay so the nav stack is ready
            const timer = setTimeout(() => {
                setHistoryStack(prev => {
                    // Avoid duplicate on re-render
                    if (prev[prev.length - 1]?.screen === screen) return prev;
                    return [...prev, { screen, params: initialParams }];
                });
            }, 200);
            return () => clearTimeout(timer);
        }
    }, [route?.params?.initialScreen, route?.params?.initialParams]);

    // Save navigation history with debounce — avoids hammering AsyncStorage on rapid navigation
    const saveTimerRef = useRef(null);
    useEffect(() => {
        if (!isHistoryLoaded) return;
        if (saveTimerRef.current) clearTimeout(saveTimerRef.current);
        saveTimerRef.current = setTimeout(async () => {
            try {
                await AsyncStorage.setItem('nav_history', JSON.stringify(historyStack));
            } catch (_) {/* silent – non-critical */}
        }, 500);
        return () => clearTimeout(saveTimerRef.current);
    }, [historyStack, isHistoryLoaded]);

    useEffect(() => {
        let timer;
        const triggerSync = () => {
            if (userState?.class_id) {
                const isPriority = route.params?.isNewSelection === true;
                // Defer heavy background sync by 4 seconds. 
                // This completely prevents UI stutter during critical app startup and navigation settling.
                timer = setTimeout(() => {
                    InteractionManager.runAfterInteractions(() => {
                        SmartCacheService.syncAllForClass(userState.class_id, isPriority);
                    });
                }, 4000);
            }
        };
        triggerSync();
        return () => {
            if (timer) clearTimeout(timer);
        };
    }, [userState?.class_id, route.params?.isNewSelection]);

    // SELF-HEALING: Load user data if it's missing or refresh it when screen focuses
    useEffect(() => {
        const recoverUser = async () => {
            try {
                const savedUser = await AsyncStorage.getItem('user_data');
                if (savedUser) {
                    const parsedUser = JSON.parse(savedUser);
                    if (parsedUser && parsedUser.user_id) {
                        // Only update state if something changed to avoid unnecessary re-renders
                        setUserState(prev => {
                            if (JSON.stringify(prev) !== JSON.stringify(parsedUser)) {
                                console.log("[MainScreen] Recovered/Refreshed user data from AsyncStorage");
                                return parsedUser;
                            }
                            return prev;
                        });
                    }
                }
            } catch (e) {
                console.error("Failed to recover user data", e);
            }
        };

        // Run immediately on mount
        recoverUser();

        // Run on every screen focus
        const unsubscribe = parentNavigation.addListener('focus', () => {
            recoverUser();
        });

        return unsubscribe;
    }, [parentNavigation]);

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
            'Home': ['Home', 'Leaderboard', 'Notifications', 'Profile', 'VocabDashboard', 'VocabBooster', 'MentalMaths', 'MyExam', 'MyExamTest', 'ScholarshipSubjects', 'ScholarshipChapters', 'ScholarshipSets'],
            'Subjects': ['Subjects', 'Chapters', 'ChapterContent', 'PDFViewer', 'Notes', 'Flashcards', 'QuickRevision', 'WorksheetGenerator', 'StudyPlanner'],
            'AI': ['AI', 'HomeworkSolver', 'EnglishMissionMap', 'PDFToExam', 'AIPdfExam', 'AIPdfWorksheet', 'StudyDetail', 'AIPdfNotes'],
            'ClassUpdates': ['ClassUpdates']
        };
        for (const [key, screens] of Object.entries(mapping)) {
            if (screens.includes(currentView)) return key;
        }
        return 'Home';
    }, [currentView]);

    const handleNavigate = useCallback((screen, params = {}) => {
        if (screen === 'VideoPlayer' || screen === 'Subscription' || screen === 'LiveClass' || screen === 'Chat') {
            parentNavigation.navigate(screen, params);
            return;
        }

        // --- PREMIUM FEATURE LOCK ---
        const premiumScreens = [
            'PDFViewer', 'Notes', 'Flashcards', 'QuickRevision', 'MyExam', 'MyExamTest',
            'WorksheetGenerator', 'StudyPlanner', 'VocabDashboard', 'VocabBooster', 'MentalMaths',
            'ScholarshipSubjects', 'ScholarshipChapters', 'ScholarshipSets', 'AI', 'HomeworkSolver',
            'EnglishMissionMap', 'PDFToExam', 'AIPdfExam', 'AIPdfWorksheet', 'StudyDetail', 'AIPdfNotes'
        ];

        // If user is trying to access a premium screen and subscription is NOT active
        if (premiumScreens.includes(screen) && userState?.subscription_status !== 'active') {
            parentNavigation.navigate('Subscription');
            return; // Block navigation and show paywall
        }
        // ----------------------------

        // Check if we are just switching tabs (Home, AI, Subjects, ClassUpdates)
        const isRootTab = ['Home', 'Subjects', 'AI', 'ClassUpdates'].includes(screen);

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
        // First, give current screen a chance to intercept (e.g., ChapterContent in quizMode)
        if (backInterceptorRef.current) {
            const intercepted = backInterceptorRef.current();
            if (intercepted) return true; // Screen handled it internally, don't pop stack
        }

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
            await AsyncStorage.removeItem('nav_history');

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

    const commonProps = useMemo(() => ({
        user: userState,
        onUserUpdate: handleUpdateUser,
        onLogout: handleLogout,
        navigation: {
            navigate: handleNavigate,
            goBack: handleGoBack,
            replace: parentNavigation.replace,
            addListener: () => { return () => { }; }, // Proper mock return
            registerBackInterceptor,
            unregisterBackInterceptor,
        },
        route: { params: viewParams }
    }), [userState, handleUpdateUser, handleLogout, handleNavigate, handleGoBack, parentNavigation.replace, registerBackInterceptor, unregisterBackInterceptor, viewParams]);

    const renderContent = () => {
        switch (currentView) {
            case 'Home': return <HomeScreen {...commonProps} />;
            case 'Subjects': return <SubjectsScreen {...commonProps} />;
            case 'AI': return <AIScreen {...commonProps} />;
            case 'PDFToExam': return <PDFToExamScreen {...commonProps} />;
            case 'Profile': return <ProfileScreen {...commonProps} />;
            case 'Leaderboard': return <LeaderboardScreen {...commonProps} />;
            case 'Notifications': return <NotificationsScreen {...commonProps} />;
            case 'Chapters': return <ChaptersScreen {...commonProps} />;
            case 'ChapterContent': return <ChapterContentScreen {...commonProps} />;
            case 'PDFViewer': return <PDFViewerScreen {...commonProps} />;
            case 'Notes': return <NotesScreen {...commonProps} />;

            case 'HomeworkSolver': return <HomeworkSolverScreen {...commonProps} />;
            case 'EnglishMissionMap': return <EnglishMissionMapScreen {...commonProps} />;
            case 'VocabDashboard': return <VocabDashboardScreen {...commonProps} />;
            case 'VocabBooster': return <VocabBoosterScreen {...commonProps} />;
            case 'MentalMaths': return <MentalMathsScreen {...commonProps} />;
            case 'Flashcards': return <FlashcardsScreen {...commonProps} />;
            case 'QuickRevision': return <QuickRevisionScreen {...commonProps} />;
            case 'MyExam': return <MyExamScreen {...commonProps} />;
            case 'MyExamTest': return <MyExamTestScreen {...commonProps} />;
            case 'ForgotPassword': return <ForgotPasswordScreen {...commonProps} />;
            case 'ScholarshipSubjects': return <ScholarshipSubjectsScreen {...commonProps} />;
            case 'ScholarshipChapters': return <ScholarshipChaptersScreen {...commonProps} />;
            case 'ScholarshipSets': return <ScholarshipSetsScreen {...commonProps} />;
            case 'WorksheetGenerator': return <WorksheetGeneratorScreen {...commonProps} />;
            case 'StudyPlanner': return <StudyPlannerScreen {...commonProps} />;
            case 'AIPdfExam': return <AIPdfExamScreen {...commonProps} />;
            case 'AIPdfWorksheet': return <AIPdfWorksheetScreen {...commonProps} />;
            case 'StudyDetail': return <StudyDetailScreen {...commonProps} />;
            case 'AIPdfNotes': return <AIPdfNotesScreen {...commonProps} />;
            case 'ClassUpdates': return <ClassUpdatesScreen {...commonProps} />;
            default: return <HomeScreen {...commonProps} />;
        }
    };

    const tabs = [
        { key: 'Home', icon: 'home', label: t('home') },
        { key: 'Subjects', icon: 'book', label: t('subject') },
        { key: 'AI', icon: 'sparkles', label: t('aiTools') },
        { key: 'ClassUpdates', icon: 'school', label: 'Class' },
    ];

    return (
        <View style={styles.container}>
            <StatusBar barStyle={isDarkMode ? 'light-content' : 'dark-content'} backgroundColor="transparent" translucent />

            <SafeAreaView style={[styles.safeAreaTop, { backgroundColor: isDarkMode ? '#0f172a' : '#f0f9ff' }]} edges={['top']}>
                <View style={styles.content}>
                    {renderContent()}
                </View>
            </SafeAreaView>

            {/* Bottom Navigation */}
            <View style={[
                styles.bottomNavContainer,
                {
                    backgroundColor: isDarkMode ? '#1e293b' : '#ffffff',
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
    safeAreaTop: { flex: 1, paddingTop: Platform.OS === 'android' ? 10 : 0 },
    content: { flex: 1 },
    bottomNavContainer: {
        elevation: 15,
        shadowOffset: { width: 0, height: -4 }, shadowOpacity: 0.08, shadowRadius: 6,
    },
    safeArea: { width: '100%' },
    tabRow: {
        flexDirection: 'row', height: 60,
        justifyContent: 'space-around', alignItems: 'center', width: '100%',
        // paddingBottom: 0, // Removed extra padding
    },
    tabButton: { flex: 1, alignItems: 'center', justifyContent: 'center', height: '100%' },
    iconWrapper: { width: 32, height: 32, justifyContent: 'center', alignItems: 'center', marginBottom: 2 },
    tabLabel: { fontSize: 11, textAlign: 'center', fontFamily: 'NotoSans-Bold' },
});

export default MainScreen;