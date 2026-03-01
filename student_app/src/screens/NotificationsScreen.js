import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator, TouchableOpacity } from 'react-native';
import { fetchNotifications } from '../api/notifications';
import { useTheme } from '../context/ThemeContext';

const NotificationsScreen = ({ navigation, user }) => {
    const { theme, isDarkMode } = useTheme();
    const [notifications, setNotifications] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (user?.class_id) {
            loadNotifications();
        }
    }, [user]);

    const loadNotifications = async () => {
        setLoading(true);
        try {
            const response = await fetchNotifications(user.class_id);
            if (response.status === 'success') {
                setNotifications(response.data);
            }
        } catch (error) {
            console.error('Failed to load notifications', error);
        } finally {
            setLoading(false);
        }
    };

    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    const renderItem = useCallback(({ item }) => (
        <View style={styles.card}>
            <View style={styles.cardHeader}>
                <View style={styles.iconContainer}>
                    <Text style={styles.iconText}>🔔</Text>
                </View>
                <View style={styles.titleContainer}>
                    <Text style={[styles.title, { color: theme.text }]} numberOfLines={2}>{item.title}</Text>
                    <Text style={styles.date}>{formatDate(item.created_at)}</Text>
                </View>
            </View>
            <View style={styles.cardBody}>
                <Text style={[styles.message, { color: theme.textSecondary }]}>{item.message}</Text>
                <View style={styles.teacherBadge}>
                    <Text style={styles.teacher}>Sent by: {item.teacher_name}</Text>
                </View>
            </View>
        </View>
    ), [theme]);

    return (
        <View style={[styles.container, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]}>
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                    <View style={styles.backButtonInner}>
                        <Text style={[styles.backButtonText, { color: theme.text }]}>←</Text>
                    </View>
                </TouchableOpacity>
                <View>
                    <Text style={[styles.headerSubtitle, { color: theme.primary }]}>SCHOOL UPDATES</Text>
                    <Text style={[styles.headerTitle, { color: theme.text }]}>Notifications</Text>
                </View>
            </View>

            {loading ? (
                <View style={styles.centerContainer}>
                    <ActivityIndicator size="large" color={theme.primary} />
                </View>
            ) : (
                <FlatList
                    data={notifications}
                    renderItem={renderItem}
                    keyExtractor={item => item.notification_id?.toString() || Math.random().toString()}
                    contentContainerStyle={styles.listContent}
                    showsVerticalScrollIndicator={false}
                    ListEmptyComponent={
                        <View style={styles.centerContainer}>
                            <Text style={{ fontSize: 40, marginBottom: 10 }}>📭</Text>
                            <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No new notifications.</Text>
                            <Text style={{ color: '#94a3b8', fontSize: 12, marginTop: 4, fontFamily: 'NotoSans-Regular' }}>You're all caught up!</Text>
                        </View>
                    }
                    onRefresh={loadNotifications}
                    refreshing={loading}
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
        paddingHorizontal: 20,
        paddingTop: 10,
        paddingBottom: 16,
    },
    backButton: {
        marginRight: 16,
    },
    backButtonInner: {
        width: 40,
        height: 40,
        borderRadius: 20,
        backgroundColor: 'rgba(100,116,139,0.1)',
        justifyContent: 'center',
        alignItems: 'center',
    },
    backButtonText: {
        fontSize: 22,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    headerSubtitle: {
        fontSize: 10,
        fontWeight: '800',
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 1,
        marginBottom: 2,
    },
    headerTitle: {
        fontSize: 24,
        fontWeight: '800',
        fontFamily: 'NotoSans-Bold',
    },
    centerContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        marginTop: 100,
    },
    listContent: {
        padding: 20,
        paddingBottom: 100,
    },
    card: {
        backgroundColor: 'white',
        borderRadius: 20,
        marginBottom: 16,
        padding: 16,
        elevation: 3,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.05,
        shadowRadius: 8,
        borderWidth: 1,
        borderColor: '#f1f5f9',
    },
    cardHeader: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        borderBottomWidth: 1,
        borderBottomColor: '#f1f5f9',
        paddingBottom: 12,
        marginBottom: 12,
    },
    iconContainer: {
        width: 40,
        height: 40,
        borderRadius: 12,
        backgroundColor: '#eef2ff',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 12,
    },
    iconText: {
        fontSize: 20,
    },
    titleContainer: {
        flex: 1,
    },
    title: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        marginBottom: 4,
    },
    date: {
        fontSize: 11,
        color: '#94a3b8',
        fontFamily: 'NotoSans-Regular',
    },
    cardBody: {
        paddingLeft: 52,
    },
    message: {
        fontSize: 14,
        lineHeight: 22,
        fontFamily: 'NotoSans-Regular',
        marginBottom: 12,
    },
    teacherBadge: {
        alignSelf: 'flex-start',
        backgroundColor: '#eff6ff',
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 8,
    },
    teacher: {
        fontSize: 11,
        color: '#3b82f6',
        fontFamily: 'NotoSans-Bold',
    },
    emptyText: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
    }
});

export default NotificationsScreen;
