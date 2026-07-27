import React, { useState, useEffect, useCallback, useRef } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator, TouchableOpacity, Image, RefreshControl, ScrollView } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { useTheme } from '../context/ThemeContext';
import { fetchLeaderboard, fetchLiveExamsList, fetchLiveExamLeaderboard } from '../api/analytics';
import { BASE_URL } from '../api/config';
import { dataCache } from '../utils/dataCache';

const LeaderboardScreen = ({ navigation, user }) => {
    const { theme } = useTheme();
    const [activeTab, setActiveTab] = useState('overall'); // 'overall' or 'live'
    const [leaderboardData, setLeaderboardData] = useState([]);
    const [liveExams, setLiveExams] = useState([]);
    const [selectedLiveExam, setSelectedLiveExam] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    const lastLoadTime = useRef(0);

    useFocusEffect(
        useCallback(() => {
            const now = Date.now();
            if (leaderboardData.length === 0 || now - lastLoadTime.current > 60000) {
                if (activeTab === 'overall') {
                    loadLeaderboard();
                } else if (activeTab === 'live' && !selectedLiveExam) {
                    loadLiveExams();
                }
                lastLoadTime.current = now;
            }
        }, [user, activeTab, selectedLiveExam])
    );

    const onRefresh = useCallback(async () => {
        setRefreshing(true);
        if (activeTab === 'overall') {
            await dataCache.remove(`leaderboard_${user?.class_id}`);
            await loadLeaderboard(true);
        } else if (activeTab === 'live') {
            if (selectedLiveExam) {
                await loadLiveExamLeaderboard(selectedLiveExam);
            } else {
                await loadLiveExams();
            }
        }
        setRefreshing(false);
    }, [user?.class_id, activeTab, selectedLiveExam]);

    const loadLeaderboard = async (forceRefresh = false) => {
        if (!user?.class_id) return;
        setLoading(true);
        if (!forceRefresh) {
            try {
                const cached = await dataCache.get(`leaderboard_${user.class_id}`, 'leaderboard');
                if (cached) {
                    setLeaderboardData(cached);
                    setLoading(false);
                }
            } catch (e) {}
        }

        try {
            const response = await fetchLeaderboard(user.class_id);
            if (response.status === 'success') {
                setLeaderboardData(response.data);
                await dataCache.set(`leaderboard_${user.class_id}`, response.data, 'leaderboard');
            }
        } catch (error) {
            console.error('Failed to load leaderboard', error);
        } finally {
            setLoading(false);
        }
    };

    const loadLiveExams = async () => {
        if (!user?.class_id) return;
        setLoading(true);
        try {
            const response = await fetchLiveExamsList(user.class_id);
            if (response.status === 'success') {
                setLiveExams(response.data || []);
            }
        } catch (error) {
            console.error('Failed to load live exams list', error);
            setLiveExams([]);
        } finally {
            setLoading(false);
        }
    };

    const loadLiveExamLeaderboard = async (exam) => {
        setSelectedLiveExam(exam);
        setLoading(true);
        try {
            const response = await fetchLiveExamLeaderboard(exam.live_exam_id);
            if (response.status === 'success') {
                setLeaderboardData(response.data || []);
            } else {
                setLeaderboardData([]);
            }
        } catch (error) {
            console.error('Failed to load live exam leaderboard', error);
            setLeaderboardData([]);
        } finally {
            setLoading(false);
        }
    };

    const switchTab = (tab) => {
        setActiveTab(tab);
        setSelectedLiveExam(null);
        setLeaderboardData([]);
        if (tab === 'overall') {
            loadLeaderboard();
        } else {
            loadLiveExams();
        }
    };

    const getImageUrl = (path) => {
        if (!path) return null;
        if (path.startsWith('http')) return path;
        return `${BASE_URL}/${path}`;
    };

    const formatDuration = (seconds) => {
        if (!seconds || seconds <= 0) return '0s';
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
    };

    const renderItem = ({ item }) => {
        const isCurrentStudent = Number(item.id) === Number(user?.user_id || user?.id);
        const isLiveTab = activeTab === 'live';

        return (
            <View style={[
                styles.item,
                { backgroundColor: theme.card, borderColor: theme.border },
                item.rank <= 3 && { backgroundColor: item.rank === 1 ? '#FFF9C4' : item.rank === 2 ? '#F5F5F5' : '#FFE0B2' },
                isCurrentStudent && { borderColor: '#0EA5E9', borderWidth: 2 }
            ]}>
                <View style={styles.rankContainer}>
                    <Text style={[styles.rank, { color: theme.textSecondary }]}>#{item.rank}</Text>
                    {isLiveTab && <Text style={styles.rankBadgeLabel}>Exam</Text>}
                </View>

                <View style={styles.avatarContainer}>
                    {item.profile_picture ? (
                        <Image
                            source={{ uri: getImageUrl(item.profile_picture) }}
                            style={styles.avatar}
                        />
                    ) : (
                        <View style={[styles.avatarPlaceholder, { backgroundColor: theme.primary }]}>
                            <Text style={styles.avatarText}>{item.full_name?.charAt(0) || 'S'}</Text>
                        </View>
                    )}
                </View>

                <View style={styles.info}>
                    <Text style={[styles.name, { color: theme.text }]} numberOfLines={1}>
                        {item.full_name} {isCurrentStudent && '(You)'}
                    </Text>
                    {isLiveTab ? (
                        <View style={{flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 3, flexWrap: 'wrap'}}>
                            <View style={styles.overallRankPill}>
                                <Text style={styles.overallRankPillText}>👑 Overall #{item.overall_rank || 'N/A'}</Text>
                            </View>
                            <Text style={[styles.tests, { color: theme.textSecondary }]}>
                                {item.percentage}% | {formatDuration(item.time_seconds)}
                            </Text>
                        </View>
                    ) : (
                        <Text style={[styles.tests, { color: theme.textSecondary }]}>{item.tests_taken || 0} Tests</Text>
                    )}
                </View>

                <View style={styles.scoreContainer}>
                    <Text style={[styles.score, { color: theme.primary }]}>
                        {isLiveTab && item.total > 0 ? `${item.total_score}/${item.total}` : item.total_score}
                    </Text>
                    <Text style={[styles.pts, { color: theme.textSecondary }]}>{isLiveTab ? 'correct' : 'pts'}</Text>
                </View>
            </View>
        );
    };

    return (
        <View style={[styles.container, { backgroundColor: theme.background }]}>
            <View style={[styles.header, { backgroundColor: theme.card }]}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                    <Text style={[styles.backButtonText, { color: theme.text }]}>←</Text>
                </TouchableOpacity>
                <Text style={[styles.title, { color: theme.text }]}>Class Leaderboard</Text>
            </View>

            {/* Tab Navigation */}
            <View style={styles.tabContainer}>
                <TouchableOpacity
                    style={[styles.tabButton, activeTab === 'overall' && styles.activeTabButton]}
                    onPress={() => switchTab('overall')}
                >
                    <Text style={[styles.tabText, activeTab === 'overall' && styles.activeTabText]}>Overall Class</Text>
                </TouchableOpacity>
                <TouchableOpacity
                    style={[styles.tabButton, activeTab === 'live' && styles.activeTabButton]}
                    onPress={() => switchTab('live')}
                >
                    <Text style={[styles.tabText, activeTab === 'live' && styles.activeTabText]}>Live Exams</Text>
                </TouchableOpacity>
            </View>

            {loading ? (
                <ActivityIndicator size="large" color={theme.primary} style={styles.loader} />
            ) : activeTab === 'live' && !selectedLiveExam ? (
                <ScrollView 
                    style={{ flex: 1, padding: 16 }}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[theme.primary]} />}
                >
                    <Text style={{ fontSize: 18, fontWeight: 'bold', color: theme.text, marginBottom: 16 }}>Select a Live Exam</Text>
                    {liveExams.length === 0 ? (
                        <Text style={{ textAlign: 'center', color: theme.textSecondary, marginTop: 40 }}>No live exams found for your class.</Text>
                    ) : (
                        liveExams.map(exam => (
                            <TouchableOpacity
                                key={exam.live_exam_id}
                                style={[styles.examCard, { backgroundColor: theme.card, borderColor: theme.border }]}
                                onPress={() => loadLiveExamLeaderboard(exam)}
                            >
                                <Text style={[styles.examTitle, { color: theme.text }]}>{exam.title}</Text>
                                <Text style={[styles.examDate, { color: theme.textSecondary }]}>{new Date(exam.created_at).toLocaleDateString()}</Text>
                                <Text style={[styles.examStatus, { color: exam.status === 'active' ? '#EF4444' : '#10B981' }]}>
                                    {exam.status === 'active' ? '🔴 Active Now' : 'Completed'}
                                </Text>
                            </TouchableOpacity>
                        ))
                    )}
                </ScrollView>
            ) : (
                <View style={{ flex: 1 }}>
                    {activeTab === 'live' && selectedLiveExam && (
                        <TouchableOpacity
                            style={{ padding: 12, backgroundColor: 'rgba(14, 165, 233, 0.1)', flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}
                            onPress={() => setSelectedLiveExam(null)}
                        >
                            <Text style={{ color: '#0EA5E9', fontWeight: 'bold' }}>← Choose a different exam</Text>
                            <Text style={{ fontSize: 12, color: theme.textSecondary }}>Showing Exam & Overall Ranks</Text>
                        </TouchableOpacity>
                    )}

                    <FlatList
                        data={leaderboardData}
                        renderItem={renderItem}
                        keyExtractor={(item) => item.id.toString()}
                        contentContainerStyle={styles.list}
                        refreshControl={
                            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[theme.primary]} />
                        }
                        ListEmptyComponent={
                            <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No scores yet. Be the first!</Text>
                        }
                    />
                </View>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingTop: 50,
        paddingBottom: 15,
        paddingHorizontal: 20,
        elevation: 4,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
    },
    backButton: {
        marginRight: 15,
    },
    backButtonText: {
        fontSize: 24,
        fontWeight: 'bold',
    },
    title: {
        fontSize: 20,
        fontWeight: 'bold',
    },
    tabContainer: {
        flexDirection: 'row',
        backgroundColor: '#F1F5F9',
        padding: 4,
        marginHorizontal: 16,
        marginVertical: 10,
        borderRadius: 12,
    },
    tabButton: {
        flex: 1,
        paddingVertical: 10,
        alignItems: 'center',
        borderRadius: 8,
    },
    activeTabButton: {
        backgroundColor: '#FFFFFF',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.1,
        shadowRadius: 2,
        elevation: 2,
    },
    tabText: {
        fontSize: 14,
        fontWeight: '600',
        color: '#64748B',
    },
    activeTabText: {
        color: '#0EA5E9',
        fontWeight: 'bold',
    },
    list: {
        padding: 16,
    },
    item: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 14,
        borderRadius: 14,
        marginBottom: 10,
        borderWidth: 1,
        elevation: 2,
    },
    rankContainer: {
        alignItems: 'center',
        width: 36,
        marginRight: 8,
    },
    rank: {
        fontSize: 16,
        fontWeight: 'bold',
    },
    rankBadgeLabel: {
        fontSize: 9,
        color: '#64748B',
        fontWeight: 'bold',
    },
    avatarContainer: {
        marginRight: 12,
    },
    avatar: {
        width: 40,
        height: 40,
        borderRadius: 20,
    },
    avatarPlaceholder: {
        width: 40,
        height: 40,
        borderRadius: 20,
        justifyContent: 'center',
        alignItems: 'center',
    },
    avatarText: {
        color: 'white',
        fontWeight: 'bold',
        fontSize: 18,
    },
    info: {
        flex: 1,
    },
    name: {
        fontSize: 15,
        fontWeight: 'bold',
    },
    tests: {
        fontSize: 12,
    },
    overallRankPill: {
        backgroundColor: '#FEF3C7',
        paddingHorizontal: 6,
        paddingVertical: 2,
        borderRadius: 8,
    },
    overallRankPillText: {
        fontSize: 10,
        color: '#B45309',
        fontWeight: 'bold',
    },
    scoreContainer: {
        alignItems: 'center',
        minWidth: 45,
    },
    score: {
        fontSize: 16,
        fontWeight: 'bold',
    },
    pts: {
        fontSize: 10,
    },
    examCard: {
        padding: 16,
        borderRadius: 14,
        borderWidth: 1,
        marginBottom: 12,
        elevation: 1,
    },
    examTitle: {
        fontSize: 16,
        fontWeight: 'bold',
    },
    examDate: {
        fontSize: 12,
        marginTop: 4,
    },
    examStatus: {
        fontSize: 12,
        fontWeight: 'bold',
        marginTop: 6,
    },
    loader: {
        marginTop: 50,
    },
    emptyText: {
        textAlign: 'center',
        marginTop: 50,
        fontSize: 16,
    }
});

export default LeaderboardScreen;
