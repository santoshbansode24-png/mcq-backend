import React, { useCallback, useState, useEffect } from 'react';
import { StatusBar } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createStackNavigator } from '@react-navigation/stack';
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
import AppSplashScreen from './src/screens/AppSplashScreen';
import ClassSelectionScreen from './src/screens/ClassSelectionScreen';
import MainScreen from './src/screens/MainScreen';
import VideoPlayerScreen from './src/screens/VideoPlayerScreen';
import ForgotPasswordScreen from './src/screens/ForgotPasswordScreen';
import SubscriptionScreen from './src/screens/SubscriptionScreen';

// Server Config
import { checkServerConnection } from './src/api/config';

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

  // Check Server Status on App Start
  // Check Server Status on App Start
  useEffect(() => {
    const initServer = async () => {
      // Create a promise that rejects after 5 seconds to prevent indefinite hanging
      const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('Server check timeout')), 5000)
      );

      try {
        // Race the server check against the timeout
        await Promise.race([
          checkServerConnection(),
          timeoutPromise
        ]);

        // NEW: Check session logic moved from AppSplashScreen
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
        console.log('Server check or session check failed:', e.message);
        setInitialRoute('Login'); // Fallback to login
      } finally {
        // Always allow the app to proceed
        setServerChecked(true);
      }
    };
    initServer();
  }, []);

  const onLayoutRootView = useCallback(async () => {
    if ((fontsLoaded || error) && serverChecked && initialRoute) {
      await SplashScreen.hideAsync().catch(console.warn);
    }
  }, [fontsLoaded, error, serverChecked, initialRoute]);

  if ((!fontsLoaded && !error) || !serverChecked || !initialRoute) {
    return null;
  }

  return (
    <ErrorBoundary>
      <LanguageProvider>
        <ThemeProvider>
          <NavigationContainer onReady={onLayoutRootView}>
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
            </Stack.Navigator>
          </NavigationContainer>
        </ThemeProvider>
      </LanguageProvider>
    </ErrorBoundary>
  );
}
