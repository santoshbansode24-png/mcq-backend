import React, { useRef, useEffect } from 'react';
import { Animated, StyleSheet, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';

const SkeletonLoader = ({ width = '100%', height = 80, borderRadius = 12, style, isDarkMode = false }) => {
    const opacity = useRef(new Animated.Value(0.4)).current;

    useEffect(() => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(opacity, { 
                    toValue: 0.8, 
                    duration: 800, 
                    useNativeDriver: true 
                }),
                Animated.timing(opacity, { 
                    toValue: 0.4, 
                    duration: 800, 
                    useNativeDriver: true 
                })
            ])
        ).start();
    }, [opacity]);

    const colors = isDarkMode 
        ? ['#334155', '#475569', '#334155'] 
        : ['#e2e8f0', '#f1f5f9', '#e2e8f0'];

    return (
        <Animated.View style={[{ width, height, borderRadius, opacity, overflow: 'hidden' }, style]}>
            <LinearGradient
                colors={colors}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={StyleSheet.absoluteFillObject}
            />
        </Animated.View>
    );
};

export default React.memo(SkeletonLoader);
