import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, ActivityIndicator, Dimensions, RefreshControl } from 'react-native';
import { useTheme } from '../../context/ThemeContext';
import { fetchAnalytics } from '../../api/analytics';
import { BarChart, PieChart } from 'react-native-chart-kit';

const AnalyticsScreen = ({ user }) => {
    const { theme } = useTheme();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    const loadData = async () => {
        try {
            const response = await fetchAnalytics(user.user_id);
            if (response.status === 'success') {
                setData(response.data);
            }
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        loadData();
    }, []);

    const onRefresh = () => {
        setRefreshing(true);
        loadData();
    };

    if (loading) {
        return (
            <View style={[styles.loadingContainer, { backgroundColor: theme.background }]}>
                <ActivityIndicator size="large" color={theme.primary} />
            </View>
        );
    }

    if (!data) return null;

    const { overview, subjects, recent } = data;

    // Prepare chart data
    const chartData = {
        labels: subjects.map(s => s.subject_name.substring(0, 3)),
        datasets: [{
            data: subjects.map(s => parseFloat(s.avg_score || 0))
        }]
    };

    return (
        <ScrollView
            style={[styles.container, { backgroundColor: theme.background }]}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        >
            <Text style={[styles.title, { color: theme.text }]}>My Performance</Text>

            {/* Overview Cards */}
            <View style={styles.statsRow}>
                <View style={[styles.statCard, { backgroundColor: theme.card, shadowColor: theme.cardShadow }]}>
                    <Text style={[styles.statValue, { color: theme.primary }]}>{overview.total_tests}</Text>
                    <Text style={[styles.statLabel, { color: theme.textSecondary }]}>Tests Taken</Text>
                </View>
                <View style={[styles.statCard, { backgroundColor: theme.card, shadowColor: theme.cardShadow }]}>
                    <Text style={[styles.statValue, { color: theme.success }]}>{Math.round(overview.avg_score || 0)}%</Text>
                    <Text style={[styles.statLabel, { color: theme.textSecondary }]}>Avg Score</Text>
                </View>
            </View>

            {/* Subject Performance Chart */}
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Subject Wise Performance</Text>
            <View style={[styles.chartContainer, { backgroundColor: theme.card }]}>
                <BarChart
                    data={chartData}
                    width={Dimensions.get('window').width - 40}
                    height={220}
                    yAxisLabel=""
                    yAxisSuffix="%"
                    chartConfig={{
                        backgroundColor: theme.card,
                        backgroundGradientFrom: theme.card,
                        backgroundGradientTo: theme.card,
                        decimalPlaces: 0,
                        color: (opacity = 1) => theme.primary,
                        labelColor: (opacity = 1) => theme.textSecondary,
                    }}
                    style={{ borderRadius: 16 }}
                />
            </View>

            {/* Recent Activity */}
            <Text style={[styles.sectionTitle, { color: theme.text }]}>Recent Tests</Text>
            {recent.map((item, index) => (
                <View key={index} style={[styles.recentItem, { backgroundColor: theme.card, borderColor: theme.border }]}>
                    <View>
                        <Text style={[styles.recentTitle, { color: theme.text }]}>{item.chapter_name}</Text>
                        <Text style={[styles.recentDate, { color: theme.textSecondary }]}>{new Date(item.created_at).toLocaleDateString()}</Text>
                    </View>
                    <View style={styles.scoreBadge}>
                        <Text style={[styles.scoreText, { color: theme.primary }]}>
                            {item.score}/{item.total_questions}
                        </Text>
                    </View>
                </View>
            ))}
            <View style={{ height: 20 }} />
        </ScrollView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        padding: 20,
    },
    loadingContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    title: {
        fontSize: 28,
        fontWeight: 'bold',
        marginBottom: 20,
    },
    statsRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        marginBottom: 30,
    },
    statCard: {
        width: '48%',
        padding: 20,
        borderRadius: 16,
        alignItems: 'center',
        elevation: 4,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 8,
    },
    statValue: {
        fontSize: 32,
        fontWeight: 'bold',
        marginBottom: 5,
    },
    statLabel: {
        fontSize: 14,
        fontWeight: '600',
    },
    sectionTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        marginBottom: 15,
        marginTop: 10,
    },
    chartContainer: {
        padding: 10,
        borderRadius: 16,
        marginBottom: 30,
        elevation: 2,
    },
    recentItem: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: 16,
        borderRadius: 12,
        marginBottom: 10,
        borderWidth: 1,
    },
    recentTitle: {
        fontSize: 16,
        fontWeight: '600',
        marginBottom: 4,
    },
    recentDate: {
        fontSize: 12,
    },
    scoreBadge: {
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 20,
        backgroundColor: 'rgba(79, 70, 229, 0.1)',
    },
    scoreText: {
        fontWeight: 'bold',
    }
});

export default AnalyticsScreen;
