import React, { useRef, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Animated } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

const LevelRoadmap = ({ 
    totalLevels = 30, 
    maxUnlockedLevel, 
    currentSelectedLevel, 
    onSelectLevel,
    themeColor = '#3b82f6' // Default Blue
}) => {
    const scrollViewRef = useRef(null);
    const pulseAnim = useRef(new Animated.Value(1)).current;

    // Pulse animation for the currently selected level
    useEffect(() => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(pulseAnim, { toValue: 1.15, duration: 800, useNativeDriver: true }),
                Animated.timing(pulseAnim, { toValue: 1, duration: 800, useNativeDriver: true })
            ])
        ).start();
    }, []);

    // Auto-scroll to the currently selected level when it changes (or mounts)
    useEffect(() => {
        if (scrollViewRef.current) {
            // Each node is roughly 75px wide (60px circle + 15px margin)
            // We scroll so the selected node is approximately centered
            const xOffset = Math.max(0, (currentSelectedLevel - 1) * 75 - 100);
            scrollViewRef.current.scrollTo({ x: xOffset, animated: true });
        }
    }, [currentSelectedLevel]);

    const levels = Array.from({ length: totalLevels }, (_, i) => i + 1);

    const getTierInfo = (lvl) => {
        if (lvl <= 10) return { name: 'Novice', icon: 'star', color: '#f59e0b' }; 
        if (lvl <= 20) return { name: 'Intermediate', icon: 'rocket', color: '#8b5cf6' };
        return { name: 'Mastery', icon: 'diamond', color: '#ec4899' };
    };

    return (
        <View style={styles.container}>
            <View style={styles.tierHeader}>
                 <Ionicons name={getTierInfo(currentSelectedLevel).icon} size={16} color={getTierInfo(currentSelectedLevel).color} />
                 <Text style={[styles.tierText, { color: getTierInfo(currentSelectedLevel).color }]}>
                     {getTierInfo(currentSelectedLevel).name} Tier
                 </Text>
            </View>

            <ScrollView 
                ref={scrollViewRef}
                horizontal 
                showsHorizontalScrollIndicator={false}
                contentContainerStyle={styles.scrollContent}
            >
                {levels.map((lvl) => {
                    const isUnlocked = lvl <= maxUnlockedLevel;
                    const isSelected = lvl === currentSelectedLevel;
                    const isCompleted = lvl < maxUnlockedLevel; // Passed previously

                    return (
                        <View key={lvl} style={styles.nodeContainer}>
                            {/* Connecting Line (except for last node) */}
                            {lvl < totalLevels && (
                                <View style={[
                                    styles.connectorLine, 
                                    { backgroundColor: isUnlocked && lvl < maxUnlockedLevel ? themeColor : '#e2e8f0' }
                                ]} />
                            )}

                            <TouchableOpacity
                                activeOpacity={0.8}
                                disabled={!isUnlocked}
                                onPress={() => onSelectLevel(lvl)}
                                style={{ alignItems: 'center', justifyContent: 'center' }}
                            >
                                <Animated.View style={[
                                    styles.nodeCircle,
                                    isUnlocked ? { backgroundColor: themeColor } : { backgroundColor: '#f1f5f9', borderWidth: 2, borderColor: '#e2e8f0' },
                                    isSelected && { transform: [{ scale: pulseAnim }], shadowColor: themeColor, shadowOpacity: 0.6, shadowRadius: 10, elevation: 8 }
                                ]}>
                                    {!isUnlocked ? (
                                        <Ionicons name="lock-closed" size={20} color="#cbd5e1" />
                                    ) : isCompleted && !isSelected ? (
                                        <Ionicons name="checkmark" size={24} color="#fff" />
                                    ) : (
                                        <Text style={[styles.nodeText, isUnlocked ? { color: '#fff' } : { color: '#94a3b8' }]}>
                                            {lvl}
                                        </Text>
                                    )}
                                </Animated.View>
                            </TouchableOpacity>
                        </View>
                    );
                })}
            </ScrollView>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        width: '100%',
        marginVertical: 15,
        backgroundColor: 'rgba(255,255,255,0.6)',
        borderRadius: 20,
        paddingVertical: 15,
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.8)'
    },
    tierHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        marginBottom: 10,
        gap: 6
    },
    tierText: {
        fontWeight: 'bold',
        fontSize: 14,
        textTransform: 'uppercase',
        letterSpacing: 1
    },
    scrollContent: {
        paddingHorizontal: 20,
        alignItems: 'center',
        flexDirection: 'row'
    },
    nodeContainer: {
        flexDirection: 'row',
        alignItems: 'center'
    },
    connectorLine: {
        height: 6,
        width: 30, // 60(circle) + 30(line) = 90 spacing roughly (if line wasn't absolute)
        position: 'absolute',
        top: '50%',
        marginTop: -3,
        left: 55, // position just to the right of the circle
        zIndex: -1
    },
    nodeCircle: {
        width: 50,
        height: 50,
        borderRadius: 25,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 25, // Space for connector line
    },
    nodeText: {
        fontSize: 18,
        fontWeight: 'bold'
    }
});

export default LevelRoadmap;
