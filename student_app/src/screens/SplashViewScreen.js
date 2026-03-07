import React, { useEffect, useRef } from 'react';
import { View, Text, StyleSheet, Image, Animated, Dimensions, StatusBar } from 'react-native';

const { width, height } = Dimensions.get('window');

const SplashViewScreen = ({ onFinish }) => {
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const scaleAnim = useRef(new Animated.Value(0.9)).current; // Start slightly smaller
    const slideAnim = useRef(new Animated.Value(20)).current;
    const textFadeAnim = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        // Sequenced animation for a professional feel
        Animated.sequence([
            // 1. Fade in and Scale up the logo/mascot
            Animated.parallel([
                Animated.timing(fadeAnim, {
                    toValue: 1,
                    duration: 800,
                    useNativeDriver: true,
                }),
                Animated.spring(scaleAnim, {
                    toValue: 1,
                    friction: 4,
                    useNativeDriver: true,
                })
            ]),
            // 2. Slide up and fade in the text
            Animated.parallel([
                Animated.timing(slideAnim, {
                    toValue: 0,
                    duration: 600,
                    useNativeDriver: true,
                }),
                Animated.timing(textFadeAnim, {
                    toValue: 1,
                    duration: 600,
                    useNativeDriver: true,
                })
            ])
        ]).start();

        // Hold for a moment then finish
        const timer = setTimeout(() => {
            onFinish();
        }, 3000); // 3 seconds total splash time

        return () => clearTimeout(timer);
    }, []);

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="#0f172a" />

            <Animated.View style={[
                styles.content,
                {
                    opacity: fadeAnim,
                    transform: [{ scale: scaleAnim }]
                }
            ]}>
                {/* Mascot / Logo Image */}
                <Image
                    source={require('../../assets/veeru_splash_dark.jpg')}
                    style={styles.logo}
                    resizeMode="contain"
                />

                {/* Text Section */}
                <Animated.View style={[
                    styles.textContainer,
                    {
                        opacity: textFadeAnim,
                        transform: [{ translateY: slideAnim }]
                    }
                ]}>
                    <Text style={styles.titleText}>VEERU</Text>
                    <Text style={styles.subtitleText}>Your Smart Learning Companion</Text>
                    <Text style={styles.mottoText}>Learn Smarter. Grow Faster.</Text>
                </Animated.View>
            </Animated.View>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#0f172a', // Matching native splash background
        justifyContent: 'center',
        alignItems: 'center',
    },
    content: {
        width: '100%',
        alignItems: 'center',
    },
    logo: {
        width: width * 0.7,
        height: width * 0.7,
        marginBottom: 20,
    },
    textContainer: {
        alignItems: 'center',
        marginTop: 20,
    },
    titleText: {
        fontSize: 42,
        color: '#ffffff',
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 2,
        marginBottom: 10,
    },
    subtitleText: {
        fontSize: 18,
        color: '#ffffff',
        fontFamily: 'NotoSans-Bold',
        opacity: 0.9,
        marginBottom: 5,
    },
    mottoText: {
        fontSize: 14,
        color: '#cbd5e1', // slate-300
        fontFamily: 'NotoSans-Regular',
        fontStyle: 'italic',
        marginTop: 5,
    },
});

export default SplashViewScreen;
