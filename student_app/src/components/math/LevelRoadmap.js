import React, { useRef, useEffect, useCallback } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Animated } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

const TIER_INFO = [
    { maxLevel: 10, name: 'Novice',       icon: 'star',    color: '#f59e0b' },
    { maxLevel: 20, name: 'Intermediate', icon: 'rocket',  color: '#8b5cf6' },
    { maxLevel: 30, name: 'Mastery',      icon: 'diamond', color: '#ec4899' },
];

const NODE_SIZE  = 50;
const NODE_GAP   = 22; // gap between circles
const NODE_TOTAL = NODE_SIZE + NODE_GAP; // total width per node slot

const LevelRoadmap = ({
    totalLevels = 30,
    maxUnlockedLevel,
    currentSelectedLevel,
    onSelectLevel,
    themeColor = '#3b82f6'
}) => {
    const scrollViewRef = useRef(null);
    const pulseAnim     = useRef(new Animated.Value(1)).current;
    const pulseAnimRef  = useRef(null);

    // Start pulse animation — stop & restart when selectedLevel changes
    useEffect(() => {
        // Stop any existing loop
        if (pulseAnimRef.current) {
            pulseAnimRef.current.stop();
        }
        pulseAnim.setValue(1);

        pulseAnimRef.current = Animated.loop(
            Animated.sequence([
                Animated.timing(pulseAnim, { toValue: 1.18, duration: 700, useNativeDriver: true }),
                Animated.timing(pulseAnim, { toValue: 1,    duration: 700, useNativeDriver: true }),
            ])
        );
        pulseAnimRef.current.start();

        // Cleanup: stop animation when component unmounts
        return () => {
            if (pulseAnimRef.current) pulseAnimRef.current.stop();
        };
    }, [currentSelectedLevel]);

    // Auto-scroll to keep the selected level visible and roughly centred
    useEffect(() => {
        if (scrollViewRef.current && currentSelectedLevel > 0) {
            const x = Math.max(0, (currentSelectedLevel - 1) * NODE_TOTAL - 120);
            scrollViewRef.current.scrollTo({ x, animated: true });
        }
    }, [currentSelectedLevel]);

    const getTierInfo = useCallback((lvl) => {
        return TIER_INFO.find(t => lvl <= t.maxLevel) || TIER_INFO[TIER_INFO.length - 1];
    }, []);

    const tier   = getTierInfo(currentSelectedLevel);
    const levels = Array.from({ length: totalLevels }, (_, i) => i + 1);

    return (
        <View style={styles.container}>
            {/* Tier Badge */}
            <View style={styles.tierHeader}>
                <Ionicons name={tier.icon} size={15} color={tier.color} />
                <Text style={[styles.tierText, { color: tier.color }]}>
                    {tier.name} Tier — Level {currentSelectedLevel}
                </Text>
            </View>

            <ScrollView
                ref={scrollViewRef}
                horizontal
                showsHorizontalScrollIndicator={false}
                contentContainerStyle={styles.scrollContent}
            >
                {levels.map((lvl, index) => {
                    const isUnlocked  = lvl <= maxUnlockedLevel;
                    const isSelected  = lvl === currentSelectedLevel;
                    const isCompleted = lvl < maxUnlockedLevel;
                    const isLast      = index === totalLevels - 1;

                    return (
                        <View key={lvl} style={styles.nodeWrapper}>
                            {/* Connector line between nodes */}
                            {!isLast && (
                                <View style={[
                                    styles.connectorLine,
                                    { backgroundColor: isCompleted ? themeColor : '#dde1e9' }
                                ]} />
                            )}

                            <TouchableOpacity
                                activeOpacity={isUnlocked ? 0.75 : 1}
                                disabled={!isUnlocked}
                                onPress={() => onSelectLevel(lvl)}
                            >
                                <Animated.View style={[
                                    styles.nodeCircle,
                                    isUnlocked
                                        ? { backgroundColor: themeColor }
                                        : styles.nodeCircleLocked,
                                    isSelected && {
                                        transform:   [{ scale: pulseAnim }],
                                        shadowColor:  themeColor,
                                        shadowOpacity: 0.55,
                                        shadowRadius:  10,
                                        elevation:     8,
                                    }
                                ]}>
                                    {!isUnlocked ? (
                                        <Ionicons name="lock-closed" size={18} color="#b0bec5" />
                                    ) : isCompleted && !isSelected ? (
                                        <Ionicons name="checkmark" size={22} color="#fff" />
                                    ) : (
                                        <Text style={styles.nodeText}>{lvl}</Text>
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
        marginVertical: 12,
        backgroundColor: 'rgba(255,255,255,0.55)',
        borderRadius: 18,
        paddingVertical: 12,
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.75)',
    },
    tierHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        marginBottom: 10,
        gap: 6,
    },
    tierText: {
        fontWeight: '700',
        fontSize: 13,
        textTransform: 'uppercase',
        letterSpacing: 0.8,
    },
    scrollContent: {
        paddingHorizontal: 16,
        alignItems: 'center',
    },
    nodeWrapper: {
        flexDirection: 'row',
        alignItems: 'center',
    },
    connectorLine: {
        width: NODE_GAP,
        height: 5,
        borderRadius: 3,
        marginHorizontal: 0,
        alignSelf: 'center',
    },
    nodeCircle: {
        width:          NODE_SIZE,
        height:         NODE_SIZE,
        borderRadius:   NODE_SIZE / 2,
        justifyContent: 'center',
        alignItems:     'center',
        shadowOffset:   { width: 0, height: 2 },
        shadowOpacity:  0.15,
        shadowRadius:   4,
        elevation:      3,
    },
    nodeCircleLocked: {
        backgroundColor: '#f1f5f9',
        borderWidth:     1.5,
        borderColor:     '#dde1e9',
    },
    nodeText: {
        fontSize:   16,
        fontWeight: '800',
        color:      '#fff',
    },
});

export default LevelRoadmap;
