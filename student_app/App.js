import React, { useCallback, useState, useEffect } from 'react';
import { StatusBar } from 'react-native';
import { NavigationContainer, createNavigationContainerRef } from '@react-navigation/native';
import { createStackNavigator } from '@react-navigation/stack';
import Constants from 'expo-constants';
import { ThemeProvider } from './src/context/ThemeContext';
import { LanguageProvider } from './src/context/LanguageContext';
import ErrorBoundary from './src/components/ErrorBoundary';

// Expo Fonts & Splash Screen
import { useFonts } from 'expo-font';
import * as SplashScreen from 'expo-splash-screen';
import AsyncStorage from '@react-native-async-storage/async-storage';

// Screen Imports
import LoginScreen from './src/screens/LoginScreen';
import RegisterScreen from './src/screens/RegisterScreen';
import SetupScreen from './src/screens/SetupScreen';
import ClassSelectionScreen from './src/screens/ClassSelectionScreen';
import MainScreen from './src/screens/MainScreen';
import VideoPlayerScreen from './src/screens/VideoPlayerScreen';
import ForgotPasswordScreen from './src/screens/ForgotPasswordScreen';
import SubscriptionScreen from './src/screens/SubscriptionScreen';

// Server Config
import { checkServerConnection } from './src/api/config';

// Create a navigation project-wide reference
export const navigationRef = createNavigationContainerRef();

const Stack = createStackNavigator();

// Prevent splash screen from auto-hiding
SplashScreen.preventAutoHideAsync();

export default function App() {
  const [serverChecked, setServerChecked] = useState(false);
  const [initialRoute, setInitialRoute] = useState(null);
  const [fontsLoaded, error] = useFonts({
    'NotoSans-Regular': require('./assets/fonts/NotoSansDevanagari-Regular.ttf'),
    'NotoSans-Bold': require('./assets/fonts/NotoSansDevanagari-Bold.ttf'),
  });

  // --- Notification Deep Linking Logic ---
  useEffect(() => {
    const isExpoGo = Constants.appOwnership === 'expo';
    if (isExpoGo) {
        console.warn('Notifications are disabled in Expo Go. Use a Development Build to enable this feature.');
        return;
    }

    const handleNotificationTap = (data) => {
        if (!navigationRef.isReady() || !data) return;

        // ── Study Planner notification ──
        if (data.type === 'STUDY_PLANNER') {
            if (data.taskType && data.taskType !== 'mega' && data.chapterId) {
                const tabMap = {
                    'quiz': 'MCQs',
                    'video': 'Videos',
                    'flashcard': 'Flashcards',
                    'notes': 'Notes'
                };
                navigationRef.navigate('Main', {
                    initialScreen: 'ChapterContent',
                    initialParams: {
                        chapter: {
                            chapter_id: data.chapterId,
                            chapter_name: data.taskTitle ? data.taskTitle.replace(/^(Watch|Read Notes|Notes|Quiz|Cards|Flashcards|Read|Pract)[:\s]+/i, '').trim() : 'Study Session',
                            subject_name: data.subjectName || ''
                        },
                        initialTab: tabMap[data.taskType] || 'Notes'
                    }
                });
            } else {
                // Navigate to Main first (so the inner nav stack is mounted),
                // then push StudyPlanner inside it via the custom navigation params.
                navigationRef.navigate('Main', { initialScreen: 'StudyPlanner' });
            }
            return;
        }

        // ── Legacy chapter-specific study reminder ──
        if (data.type === 'STUDY_REMINDER' && data.chapterId) {
            const tabMap = {
                'quiz': 'MCQs',
                'video': 'Videos',
                'flashcard': 'Flashcards',
                'notes': 'Notes'
            };
            navigationRef.navigate('ChapterContent', {
                chapter: {
                    chapter_id: data.chapterId,
                    chapter_name: data.chapterName || 'Chapter Details',
                    subject_name: data.subjectName || ''
                },
                initialTab: tabMap[data.taskType] || 'Notes'
            });
        }
    };

    try {
        const Notifications = require('expo-notifications');

        // Handle tap when app is in foreground / background
        const subscription = Notifications.addNotificationResponseReceivedListener(response => {
            handleNotificationTap(response.notification.request.content.data);
        });

        // Handle cold launch — app opened directly by tapping a notification
        Notifications.getLastNotificationResponseAsync().then(response => {
            if (response) {
                handleNotificationTap(response.notification.request.content.data);
            }
        });

        return () => subscription.remove();
    } catch (e) {
        console.log('Failed to initialize notifications:', e.message);
    }
  }, []);

  // Check Server Status on App Start
  useEffect(() => {
    const initServer = async () => {
      // 1. Kick off server check in the background (NON-BLOCKING - never delays splash)
      checkServerConnection().catch(e => {
        console.log('Server check failed (likely offline):', e.message);
      });

      // 2. Always Check Session immediately, even if offline
      try {
        const savedUser = await AsyncStorage.getItem('user_data');
        const userData = savedUser ? JSON.parse(savedUser) : null;
        if (userData) {
          if (!userData.class_id || !userData.board_type) {
            setInitialRoute('Setup');
          } else {
            setInitialRoute('Main');
          }
        } else {
          setInitialRoute('Login');
        }
      } catch (e) {
        console.error('Session check failed:', e);
        setInitialRoute('Login');
      } finally {
        setServerChecked(true);
      }
    };

    initServer();
    
    // ✅ Safety net: If splash is still visible after 3 seconds, force-hide it.
    const safetyTimer = setTimeout(() => {
      SplashScreen.hideAsync().catch(() => {});
    }, 3000);

    return () => clearTimeout(safetyTimer);
  }, []);

  useEffect(() => {
    if ((fontsLoaded || error) && serverChecked && initialRoute) {
      SplashScreen.hideAsync().catch(console.warn);
    }
  }, [fontsLoaded, error, serverChecked, initialRoute]);

  if ((!fontsLoaded && !error) || !serverChecked || !initialRoute) {
    return null;
  }

  return (
    <ErrorBoundary>
      <LanguageProvider>
        <ThemeProvider>
          <NavigationContainer ref={navigationRef}>
            <StatusBar barStyle="dark-content" backgroundColor="#fff" />
            <Stack.Navigator
              initialRouteName={initialRoute}
              screenOptions={{
                headerShown: false,
                animationEnabled: true
              }}
            >
              <Stack.Screen name="Login" component={LoginScreen} />
              <Stack.Screen name="Register" component={RegisterScreen} />
              <Stack.Screen name="Setup" component={SetupScreen} />
              <Stack.Screen name="ClassSelection" component={ClassSelectionScreen} />
              <Stack.Screen name="Main" component={MainScreen} />
              <Stack.Screen name="VideoPlayer" component={VideoPlayerScreen} />
              <Stack.Screen name="ForgotPassword" component={ForgotPasswordScreen} />
              <Stack.Screen name="Subscription" component={SubscriptionScreen} />
              <Stack.Screen name="ChapterContent" component={require('./src/screens/ChapterContentScreen').default} />
            </Stack.Navigator>
          </NavigationContainer>
        </ThemeProvider>
      </LanguageProvider>
    </ErrorBoundary>
  );
}
