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
    // Check if we are in Expo Go to avoid crash in SDK 53+
    const isExpoGo = Constants.appOwnership === 'expo';
    
    if (isExpoGo) {
        console.warn('Notifications are disabled in Expo Go. Use a Development Build to enable this feature.');
        return;
    }

    try {
        const Notifications = require('expo-notifications');
        // Listener when a notification is clicked while app is in foreground or background
        const subscription = Notifications.addNotificationResponseReceivedListener(response => {
          const data = response.notification.request.content.data;
          
          if (data?.type === 'STUDY_REMINDER' && data?.chapterId) {
            // Map task types to screen tabs
            const tabMap = {
                'quiz': 'MCQs',
                'video': 'Videos',
                'flashcard': 'Flashcards',
                'notes': 'Notes'
            };
            const targetTab = tabMap[data.taskType] || 'Notes';

            // Navigate directly to ChapterContent with the correct tab active
            if (navigationRef.isReady()) {
              navigationRef.navigate('ChapterContent', { 
                chapter: {
                  chapter_id: data.chapterId,
                  chapter_name: data.chapterName || 'Chapter Details',
                  subject_name: data.subjectName || ''
                }, 
                initialTab: targetTab
              });
            }
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
      // Create a promise that rejects after 5 seconds to prevent indefinite hanging
      const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('Server check timeout')), 5000)
      );

      try {
        // 1. Attempt Server Check (Non-blocking)
        await Promise.race([
          checkServerConnection(),
          timeoutPromise
        ]);
      } catch (e) {
        console.log('Server check timed out or failed (likely offline):', e.message);
      }

      // 2. Always Check Session, even if offline
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
