import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator, TouchableOpacity, Image } from 'react-native';
import { useTheme } from '../context/ThemeContext';
import { fetchLeaderboard } from '../api/analytics';
import { BASE_URL } from '../api/config';

const LeaderboardScreen = ({ navigation, user }) => {
    const { theme } = useTheme();
    const [leaderboardData, setLeaderboardData] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (user?.class_id) {
            loadLeaderboard();
        }
    }, [user]);

    const loadLeaderboard = async () => {
        try {
            const response = await fetchLeaderboard(user.class_id);
            if (response.status === 'success') {
                setLeaderboardData(response.data);
            }
        } catch (error) {
            console.error('Failed to load leaderboard', error);
        } finally {
            setLoading(false);
        }
    };

    const getImageUrl = (path) => {
        if (!path) return null;
        if (path.startsWith('http')) return path;
        return `${BASE_URL}/${path}`;
    };

    const renderItem = ({ item }) => (
        <View style={[
            styles.item,
            { backgroundColor: theme.card, borderColor: theme.border },
            item.rank <= 3 && { backgroundColor: item.rank === 1 ? '#FFF9C4' : item.rank === 2 ? '#F5F5F5' : '#FFE0B2' }
        ]}>
            <Text style={[styles.rank, { color: theme.textSecondary }]}>#{item.rank}</Text>

            <View style={styles.avatarContainer}>
                {item.profile_picture ? (
                    <Image
                        source={{ uri: getImageUrl(item.profile_picture) }}
                        style={styles.avatar}
                    />
                ) : (
                    <View style={[styles.avatarPlaceholder, { backgroundColor: theme.primary }]}>
                        <Text style={styles.avatarText}>{item.full_name.charAt(0)}</Text>
                    </View>
                )}
            </View>

            <View style={styles.info}>
                <Text style={[styles.name, { color: theme.text }]}>
                    {item.full_name} {item.id === user.user_id && '(You)'}
                </Text>
                <Text style={[styles.tests, { color: theme.textSecondary }]}>{item.tests_taken} Tests</Text>
            </View>

            <View style={styles.scoreContainer}>
                <Text style={[styles.score, { color: theme.primary }]}>{item.total_score}</Text>
                <Text style={[styles.pts, { color: theme.textSecondary }]}>pts</Text>
            </View>
        </View>
    );

    return (
        <View style={[styles.container, { backgroundColor: theme.background }]}>
            <View style={[styles.header, { backgroundColor: theme.card }]}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                    <Text style={[styles.backButtonText, { color: theme.text }]}>←</Text>
                </TouchableOpacity>
                <Text style={[styles.title, { color: theme.text }]}>Class Leaderboard</Text>
            </View>

            {loading ? (
                <ActivityIndicator size="large" color={theme.primary} style={styles.loader} />
            ) : (
                <FlatList
                    data={leaderboardData}
                    renderItem={renderItem}
                    keyExtractor={(item) => item.id.toString()}
                    contentContainerStyle={styles.list}
                    ListEmptyComponent={
                        <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No scores yet. Be the first!</Text>
                    }
                />
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
    list: {
        padding: 20,
    },
    item: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 15,
        borderRadius: 12,
        marginBottom: 10,
        borderWidth: 1,
        elevation: 2,
    },
    rank: {
        fontSize: 18,
        fontWeight: 'bold',
        width: 40,
    },
    avatarContainer: {
        marginRight: 15,
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
        fontSize: 16,
        fontWeight: 'bold',
    },
    tests: {
        fontSize: 12,
    },
    scoreContainer: {
        alignItems: 'center',
    },
    score: {
        fontSize: 18,
        fontWeight: 'bold',
    },
    pts: {
        fontSize: 10,
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
