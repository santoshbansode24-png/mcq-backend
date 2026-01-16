import React, { useEffect, useRef } from 'react';
import { View, Text, StyleSheet, Image, Animated, StatusBar, Dimensions } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { LinearGradient } from 'expo-linear-gradient';

const AppSplashScreen = ({ navigation, route }) => {
    const { user } = route.params || {};

    // Animation values
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const scaleAnim = useRef(new Animated.Value(0.8)).current;
    const textTranslateY = useRef(new Animated.Value(20)).current;

    useEffect(() => {
        console.log("Splash Screen Loaded - Dark Mode");

        // Start Animation sequences
        Animated.parallel([
            Animated.timing(fadeAnim, {
                toValue: 1,
                duration: 1000,
                useNativeDriver: true,
            }),
            Animated.spring(scaleAnim, {
                toValue: 1,
                friction: 6,
                tension: 40,
                useNativeDriver: true,
            }),
            Animated.timing(textTranslateY, {
                toValue: 0,
                duration: 1200,
                useNativeDriver: true,
            })
        ]).start();

        // Navigate after delay
        const checkSessionAndNavigate = async () => {
            // Wait at least 3 seconds for animation
            await new Promise(resolve => setTimeout(resolve, 3000));

            if (user) {
                // User passed from Login/Register -> Check valid data
                if (!user.class_id || !user.board_type) {
                    navigation.replace('Setup', { user });
                } else {
                    navigation.replace('Main', { user });
                }
            } else {
                // No user passed -> Check Storage for auto-login
                try {
                    const savedUser = await AsyncStorage.getItem('user_data');
                    if (savedUser) {
                        const userData = JSON.parse(savedUser);
                        if (!userData.class_id || !userData.board_type) {
                            navigation.replace('Setup', { user: userData });
                        } else {
                            navigation.replace('Main', { user: userData });
                        }
                    } else {
                        // No session -> Go to Login
                        navigation.replace('Login');
                    }
                } catch (error) {
                    console.error("Auto-login error:", error);
                    navigation.replace('Login');
                }
            }
        };

        checkSessionAndNavigate();

    }, []);

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="#4f46e5" />
            <View style={[styles.background, { backgroundColor: '#0f172a' }]}>
                <Animated.View style={[
                    styles.content,
                    {
                        opacity: fadeAnim,
                        transform: [{ scale: scaleAnim }]
                    }
                ]}>
                    <Image
                        source={require('../../assets/veeru_splash_dark.jpg')}
                        style={styles.character}
                        resizeMode="contain"
                    />
                </Animated.View>

                <Animated.View style={[
                    styles.textContainer,
                    {
                        opacity: fadeAnim,
                        transform: [{ translateY: textTranslateY }]
                    }
                ]}>
                    <Text style={styles.title}>VEERU</Text>
                    <Text style={styles.subtitle}>Your Smart Learning Companion</Text>
                    <Text style={styles.tagline}>Learn Smarter. Grow Faster.</Text>
                </Animated.View>
            </View>
        </View>
    );
};

const { width } = Dimensions.get('window');

const styles = StyleSheet.create({
    container: {
        flex: 1,
    },
    background: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    content: {
        alignItems: 'center',
        justifyContent: 'center',
        marginBottom: 30, // Space between image and text
    },
    character: {
        width: width * 0.8, // 80% of screen width
        height: width * 0.8, // Square aspect ratio or adjust based on image
        maxHeight: 400,
    },
    textContainer: {
        alignItems: 'center',
        position: 'absolute',
        bottom: 80, // Position text at the bottom
    },
    title: {
        fontSize: 48,
        fontWeight: '900',
        color: 'white',
        letterSpacing: 2,
        textShadowColor: 'rgba(0, 0, 0, 0.3)',
        textShadowOffset: { width: 0, height: 2 },
        textShadowRadius: 10,
    },
    subtitle: {
        fontSize: 20,
        color: '#f0f9ff',
        marginTop: 5,
        fontWeight: '600',
    },
    tagline: {
        fontSize: 16,
        color: 'rgba(255, 255, 255, 0.9)',
        marginTop: 8,
        fontStyle: 'italic',
    }
});

export default AppSplashScreen;
