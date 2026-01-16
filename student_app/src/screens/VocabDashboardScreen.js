import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, ScrollView,
    ActivityIndicator, Dimensions, Platform, StatusBar, RefreshControl, Vibration
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import { fetchVocabStats } from '../api/vocab';

const { width } = Dimensions.get('window');
const STATUSBAR_HEIGHT = Platform.OS === 'android' ? StatusBar.currentHeight : 0;

// --- Sub-Components ---

const StatItem = ({ value, label, color, theme }) => (
    <View style={styles.statItem}>
        <Text style={[styles.statValue, { color }]}>{value}</Text>
        <Text style={[styles.statLabel, { color: theme.textSecondary }]}>{label}</Text>
    </View>
);

const SetCard = React.memo(({ setNum, status, isLocked, onPress, theme, isDarkMode }) => (
    <TouchableOpacity
        disabled={isLocked}
        onPress={onPress}
        activeOpacity={0.7}
        style={[
            styles.setCard,
            {
                backgroundColor: isDarkMode ? '#1e293b' : '#ffffff',
                borderColor: status === 'current' ? theme.primary : 'transparent',
                borderWidth: status === 'current' ? 2 : 0,
                opacity: isLocked ? 0.6 : 1,
                elevation: isLocked ? 0 : 2
            }
        ]}
    >
        <View style={[
            styles.setIcon,
            { backgroundColor: status === 'completed' ? '#dcfce7' : (isLocked ? '#f1f5f9' : '#e0f2fe') }
        ]}>
            <Ionicons
                name={status === 'completed' ? "checkmark" : (isLocked ? "lock-closed" : "book")}
                size={20}
                color={status === 'completed' ? '#16a34a' : (isLocked ? '#94a3b8' : theme.primary)}
            />
        </View>
        <Text style={[styles.setNumText, { color: theme.text }]}>Set {setNum}</Text>
        <Text style={[styles.setStatusText, { color: status === 'current' ? theme.primary : theme.textSecondary }]}>
            {status === 'current' ? 'Playing' : (status === 'completed' ? 'Done' : (isLocked ? 'Locked' : 'Start'))}
        </Text>
    </TouchableOpacity>
));

// --- Main Screen ---

const VocabDashboardScreen = ({ user, navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [stats, setStats] = useState(null);
    const [selectedLevel, setSelectedLevel] = useState('Beginner');

    const levels = useMemo(() => [
        { name: 'Beginner', label: 'Beginner', range: [1, 20], color: '#22c55e' },
        { name: 'Intermediate', label: 'Intermediate', range: [21, 50], color: '#f59e0b' },
        { name: 'Advanced', label: 'Advanced', range: [51, 80], color: '#ef4444' },
    ], []);

    useEffect(() => {
        loadStats();
    }, []);

    const loadStats = async (isRef = false) => {
        if (!isRef) setLoading(true);
        try {
            const response = await fetchVocabStats(user.user_id, true);
            if (response.status === 'success') {
                setStats(response.data);
            }
        } catch (error) {
            console.error('Stats error:', error);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const handleRefresh = useCallback(() => {
        setRefreshing(true);
        loadStats(true);
    }, []);

    const getSetStatus = useCallback((setNum) => {
        const currentSet = stats?.current_set || 1;
        const highestUnlocked = stats?.highest_set_unlocked || 1;

        if (setNum < currentSet) return { status: 'completed' };
        if (setNum === currentSet) return { status: 'current' };
        if (setNum <= highestUnlocked) return { status: 'unlocked' };
        return { status: 'locked' };
    }, [stats]);

    // Optimization: Memoize sets array to prevent recalculation on every render
    const currentSets = useMemo(() => {
        const currentLevelData = levels.find(l => l.name === selectedLevel);
        const start = currentLevelData?.range[0] || 1;
        const end = currentLevelData?.range[1] || 20;
        return Array.from({ length: (end - start + 1) }, (_, i) => start + i);
    }, [selectedLevel, levels]);

    if (loading && !refreshing) {
        return (
            <View style={[styles.loadingContainer, { backgroundColor: theme.background }]}>
                <ActivityIndicator size="large" color={theme.primary} />
            </View>
        );
    }

    return (
        <View style={{ flex: 1, backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }}>
            <StatusBar barStyle={isDarkMode ? 'light-content' : 'dark-content'} translucent backgroundColor="transparent" />

            <View style={styles.container}>
                <LinearGradient
                    colors={isDarkMode ? ['#0f172a', '#1e293b'] : ['#f8fafc', '#e2e8f0']}
                    style={styles.background}
                />

                {/* Header */}
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.iconButton}>
                        <Ionicons name="arrow-back" size={24} color={theme.text} />
                    </TouchableOpacity>
                    <Text style={[styles.headerTitle, { color: theme.text }]}>Vocabulary Booster</Text>
                    <TouchableOpacity onPress={() => loadStats(true)} style={styles.iconButton}>
                        <Ionicons name="reload" size={20} color={theme.text} />
                    </TouchableOpacity>
                </View>

                <ScrollView
                    contentContainerStyle={styles.scrollContent}
                    showsVerticalScrollIndicator={false}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={handleRefresh} tintColor={theme.primary} />}
                >
                    {/* Stats Overview */}
                    <View style={[styles.statsCard, { backgroundColor: isDarkMode ? '#1e293b' : '#fff' }]}>
                        <StatItem
                            value={stats?.current_set || 1}
                            label="Current Set"
                            color={theme.primary}
                            theme={theme}
                        />
                        <View style={[styles.verticalDivider, { backgroundColor: isDarkMode ? '#334155' : '#e2e8f0' }]} />
                        <StatItem
                            value={stats?.sets_completed || 0}
                            label="Completed"
                            color="#22c55e"
                            theme={theme}
                        />
                        <View style={[styles.verticalDivider, { backgroundColor: isDarkMode ? '#334155' : '#e2e8f0' }]} />
                        <StatItem
                            value={stats?.mastered_words || 0}
                            label="Words"
                            color="#f59e0b"
                            theme={theme}
                        />
                    </View>

                    {/* Level Tabs */}
                    <View style={styles.tabsWrapper}>
                        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.tabsContainer}>
                            {levels.map((level) => (
                                <TouchableOpacity
                                    key={level.name}
                                    onPress={() => setSelectedLevel(level.name)}
                                    activeOpacity={0.8}
                                    style={[
                                        styles.tab,
                                        selectedLevel === level.name
                                            ? { backgroundColor: level.color, borderColor: level.color }
                                            : { backgroundColor: 'transparent', borderColor: isDarkMode ? '#334155' : '#cbd5e1' }
                                    ]}
                                >
                                    <Text style={[
                                        styles.tabText,
                                        { color: selectedLevel === level.name ? '#fff' : theme.textSecondary }
                                    ]}>
                                        {level.label}
                                    </Text>
                                </TouchableOpacity>
                            ))}
                        </ScrollView>
                    </View>

                    {/* Sets Grid */}
                    <View style={styles.gridContainer}>
                        {currentSets.map((setNum) => {
                            const { status } = getSetStatus(setNum);
                            return (
                                <SetCard
                                    key={setNum}
                                    setNum={setNum}
                                    status={status}
                                    isLocked={status === 'locked'}
                                    theme={theme}
                                    isDarkMode={isDarkMode}
                                    onPress={() => {
                                        Vibration.vibrate(10); // Subtle Haptic Feedback
                                        navigation.navigate('VocabBooster', { setNumber: setNum });
                                    }}
                                />
                            );
                        })}
                    </View>

                    <View style={{ height: 40 }} />
                </ScrollView>
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    container: { flex: 1 },
    background: { position: 'absolute', left: 0, right: 0, top: 0, bottom: 0 },

    header: {
        flexDirection: 'row', alignItems: 'center',
        paddingTop: STATUSBAR_HEIGHT + 10,
        paddingHorizontal: 20, paddingBottom: 15,
        justifyContent: 'space-between'
    },
    headerTitle: { fontSize: 20, fontWeight: 'bold' },
    iconButton: { padding: 8 },
    scrollContent: { paddingBottom: 20 },

    // Stats
    statsCard: {
        flexDirection: 'row', justifyContent: 'space-between',
        marginHorizontal: 20, marginTop: 10, marginBottom: 20,
        padding: 20, borderRadius: 20,
        shadowColor: '#000', shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.05, shadowRadius: 10, elevation: 3
    },
    statItem: { alignItems: 'center', flex: 1 },
    statValue: { fontSize: 22, fontWeight: 'bold', marginBottom: 4 },
    statLabel: { fontSize: 12, fontWeight: '600' },
    verticalDivider: { width: 1, height: '80%', alignSelf: 'center' },

    // Tabs
    tabsWrapper: { marginBottom: 20 },
    tabsContainer: { paddingHorizontal: 20 },
    tab: {
        paddingHorizontal: 18, paddingVertical: 8,
        borderRadius: 20, borderWidth: 1, marginRight: 10,
    },
    tabText: { fontWeight: '600', fontSize: 13 },

    // Grid
    gridContainer: {
        flexDirection: 'row', flexWrap: 'wrap',
        paddingHorizontal: 20, gap: 12, // Native gap support in newer RN versions
        // If your RN version is old and doesn't support gap, remove it and use margins in setCard
    },
    setCard: {
        // Optimized calculation: (Total Width - Padding(40) - Gap(24)) / 3 columns
        width: (width - 64) / 3,
        aspectRatio: 0.85,
        borderRadius: 16,
        padding: 10,
        alignItems: 'center',
        justifyContent: 'center',
        shadowColor: '#000', shadowOpacity: 0.03, shadowRadius: 5,
    },
    setIcon: {
        width: 40, height: 40, borderRadius: 20,
        justifyContent: 'center', alignItems: 'center', marginBottom: 10
    },
    setNumText: { fontWeight: 'bold', fontSize: 15, marginBottom: 4 },
    setStatusText: { fontSize: 11, fontWeight: '600' }
});

export default VocabDashboardScreen;