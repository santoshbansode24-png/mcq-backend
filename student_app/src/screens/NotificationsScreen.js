import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator, TouchableOpacity, Linking, Image } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { fetchNotifications } from '../api/notifications';
import { useTheme } from '../context/ThemeContext';
import { BASE_URL } from '../api/config';

const NotificationsScreen = ({ user, navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const [notifications, setNotifications] = useState([]);
    const [loading, setLoading] = useState(true);

    const loadNotifications = async () => {
        setLoading(true);
        try {
            const response = await fetchNotifications(user.class_id);
            if (response.status === 'success') {
                // Filter out class updates (worksheets, homework, etc.) so they only appear in the Class tab
                const filteredNotifications = response.data.filter(item => {
                    // Exclude any raw worksheet data
                    if (item.message && item.message.includes('JSON_PAYLOAD:')) return false;
                    
                    // Exclude specific class material types
                    const classTypes = ['pdf', 'photo', 'worksheet', 'homework', 'live_class', 'material'];
                    if (classTypes.includes(item.update_type)) return false;
                    
                    // If it has a teacher_name, it's usually a class-specific announcement
                    if (item.teacher_name) return false;
                    
                    return true;
                });
                
                setNotifications(filteredNotifications);
            }
        } catch (error) {
            console.error('Failed to load notifications', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (user?.class_id) {
            loadNotifications();
        } else {
            setLoading(false);
        }
    }, [user]);

    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    const renderItem = useCallback(({ item }) => {
        const hasFile = item.payload && (item.payload.file_url || item.payload.url);
        const isPdf = item.update_type === 'pdf';
        const isPhoto = item.update_type === 'photo';

        const openAttachment = () => {
            if (hasFile) {
                const fileUrl = item.payload.file_url || item.payload.url;
                const url = fileUrl.startsWith('http') ? fileUrl : `${BASE_URL}/${fileUrl}`;
                Linking.openURL(url);
            }
        };

        return (
            <View style={[styles.card, { backgroundColor: isDarkMode ? '#1e293b' : '#fff' }]}>
                <View style={styles.cardHeader}>
                    <View style={[styles.iconContainer, { backgroundColor: isPdf ? '#FEE2E2' : isPhoto ? '#ECFDF5' : '#EEF2FF' }]}>
                        <MaterialCommunityIcons name={isPdf ? "file-pdf-box" : isPhoto ? "image" : "bell-outline"} size={22} color={isPdf ? "#EF4444" : isPhoto ? "#10B981" : "#6366F1"} />
                    </View>
                    <View style={styles.titleContainer}>
                        <Text style={[styles.title, { color: theme.text }]} numberOfLines={2}>{item.title}</Text>
                        <Text style={styles.date}>{formatDate(item.created_at)}</Text>
                    </View>
                </View>
                <View style={styles.cardBody}>
                    <Text style={[styles.message, { color: theme.textSecondary }]}>{item.message}</Text>
                    
                    {hasFile && (
                        <TouchableOpacity style={[styles.attachmentButton, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]} onPress={openAttachment}>
                            <MaterialCommunityIcons 
                                name={isPdf ? "file-pdf-box" : "image"} 
                                size={20} 
                                color={isPdf ? "#EF4444" : "#10B981"} 
                            />
                            <Text style={[styles.attachmentText, { color: isPdf ? "#EF4444" : "#10B981" }]}>
                                {isPdf ? 'View PDF Document' : 'View Image Attachment'}
                            </Text>
                        </TouchableOpacity>
                    )}

                    <View style={[styles.teacherBadge, { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
                        <Text style={[styles.teacher, { color: '#64748b' }]}>Sent by: {item.teacher_name}</Text>
                    </View>
                </View>
            </View>
        );
    }, [theme]);

    return (
        <View style={[styles.container, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]}>
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()}>
                    <View style={[styles.backButton, { backgroundColor: isDarkMode ? '#1e293b' : '#fff' }]}>
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
        paddingTop: 50,
        paddingBottom: 20,
    },
    backButton: {
        width: 40,
        height: 40,
        borderRadius: 20,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 15,
        elevation: 2,
    },
    backButtonText: {
        fontSize: 20,
        fontWeight: 'bold',
    },
    headerTitle: {
        fontSize: 24,
        fontFamily: 'NotoSans-Bold',
    },
    headerSubtitle: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 1,
    },
    centerContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    listContent: {
        paddingHorizontal: 20,
        paddingBottom: 40,
    },
    card: {
        borderRadius: 20,
        padding: 16,
        marginBottom: 16,
        elevation: 2,
    },
    cardHeader: {
        flexDirection: 'row',
        marginBottom: 12,
    },
    iconContainer: {
        width: 44,
        height: 44,
        borderRadius: 12,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 12,
    },
    titleContainer: {
        flex: 1,
        justifyContent: 'center',
    },
    title: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
    },
    date: {
        fontSize: 11,
        color: '#94a3b8',
        marginTop: 2,
    },
    cardBody: {
        marginTop: 4,
    },
    message: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
        lineHeight: 20,
        marginBottom: 12,
    },
    attachmentButton: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 10,
        borderRadius: 12,
        marginBottom: 12,
    },
    attachmentText: {
        marginLeft: 8,
        fontSize: 13,
        fontWeight: 'bold',
    },
    teacherBadge: {
        paddingHorizontal: 10,
        paddingVertical: 5,
        borderRadius: 8,
        alignSelf: 'flex-start',
    },
    teacher: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
    },
    emptyText: {
        fontSize: 16,
        marginTop: 10,
    }
});

export default NotificationsScreen;
