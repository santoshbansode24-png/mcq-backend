import React, { useState, useEffect, useCallback } from 'react';
import {
    View, Text, StyleSheet, FlatList, TouchableOpacity,
    ActivityIndicator, RefreshControl, Image, Linking, StatusBar
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import axios from 'axios';
import { useTheme } from '../context/ThemeContext';
import { API_URL } from '../api/config';

// Helper for relative time
const formatTimeAgo = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    let interval = seconds / 31536000;
    if (interval > 1) return Math.floor(interval) + " years ago";
    interval = seconds / 2592000;
    if (interval > 1) return Math.floor(interval) + " months ago";
    interval = seconds / 86400;
    if (interval > 1) return Math.floor(interval) + " days ago";
    interval = seconds / 3600;
    if (interval > 1) return Math.floor(interval) + " hours ago";
    interval = seconds / 60;
    if (interval > 1) return Math.floor(interval) + " minutes ago";
    return Math.floor(seconds) + " seconds ago";
};

const ClassUpdatesScreen = ({ navigation, user }) => {
    const { theme, isDarkMode } = useTheme();
    const [updates, setUpdates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    const fetchUpdates = useCallback(async () => {
        if (!user?.school_name || !user?.class_id) {
            setLoading(false);
            return;
        }
        try {
            const res = await axios.get(`${API_URL}/get_class_updates.php`, {
                params: {
                    school_name: user.school_name,
                    class_id: user.class_id
                }
            });
            if (res.data.status === 'success') {
                setUpdates(res.data.data);
            }
        } catch (error) {
            console.error("Failed to fetch class updates", error);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [user]);

    useEffect(() => {
        fetchUpdates();
    }, [fetchUpdates]);

    const onRefresh = () => {
        setRefreshing(true);
        fetchUpdates();
    };

    const handleAction = (item) => {
        if (item.update_type === 'pdf') {
            if (item.payload?.url) {
                Linking.openURL(item.payload.url);
            }
        } else if (item.update_type === 'exam') {
            if (item.payload?.chapter_ids) {
                // Instantly generate test based on teacher's payload
                navigation.navigate('MyExamTest', {
                    questions: item.payload.questions || [], 
                    totalQuestions: item.payload.questions?.length || 0,
                    subjectName: "Teacher Exam"
                });
            }
        } else if (item.update_type === 'worksheet') {
            if (item.payload?.url) {
                 Linking.openURL(item.payload.url);
            }
        }
    };

    const renderItem = ({ item }) => {
        let iconName = "notifications";
        let iconColor = "#6366f1";
        let bgColor = isDarkMode ? "#1e293b" : "#ffffff";

        if (item.update_type === 'homework') { iconName = "book"; iconColor = "#f59e0b"; }
        if (item.update_type === 'exam') { iconName = "document-text"; iconColor = "#ef4444"; }
        if (item.update_type === 'worksheet') { iconName = "print"; iconColor = "#10b981"; }
        if (item.update_type === 'photo') { iconName = "image"; iconColor = "#8b5cf6"; }
        if (item.update_type === 'pdf') { iconName = "document-attach"; iconColor = "#06b6d4"; }

        return (
            <View style={[styles.card, { backgroundColor: bgColor, borderColor: isDarkMode ? '#334155' : '#e2e8f0' }]}>
                <View style={styles.cardHeader}>
                    <View style={styles.headerLeft}>
                        <View style={[styles.iconBox, { backgroundColor: iconColor + '20' }]}>
                            <Ionicons name={iconName} size={20} color={iconColor} />
                        </View>
                        <View style={styles.headerTitles}>
                            <Text style={[styles.teacherName, { color: isDarkMode ? '#f8fafc' : '#0f172a' }]}>{item.teacher_name}</Text>
                            <Text style={styles.timeText}>{formatTimeAgo(item.created_at)}</Text>
                        </View>
                    </View>
                    <View style={[styles.badge, { backgroundColor: iconColor + '15' }]}>
                        <Text style={[styles.badgeText, { color: iconColor }]}>{item.update_type.toUpperCase()}</Text>
                    </View>
                </View>

                <Text style={[styles.title, { color: isDarkMode ? '#ffffff' : '#1e293b' }]}>{item.title}</Text>
                {item.message ? (
                    <Text style={[styles.message, { color: isDarkMode ? '#cbd5e1' : '#475569' }]}>{item.message}</Text>
                ) : null}

                {item.update_type === 'photo' && item.payload?.url && (
                    <Image source={{ uri: item.payload.url }} style={styles.photo} resizeMode="cover" />
                )}

                {['exam', 'worksheet', 'pdf'].includes(item.update_type) && (
                    <TouchableOpacity style={styles.actionButton} onPress={() => handleAction(item)}>
                        <LinearGradient colors={[iconColor, iconColor + 'dd']} style={styles.actionGradient}>
                            <Text style={styles.actionText}>
                                {item.update_type === 'exam' ? 'Start Exam' : 
                                 item.update_type === 'worksheet' ? 'Download Worksheet' : 'View PDF'}
                            </Text>
                            <Ionicons name="arrow-forward" size={16} color="white" />
                        </LinearGradient>
                    </TouchableOpacity>
                )}
            </View>
        );
    };

    if (!user?.school_name || !user?.class_id) {
        return (
            <View style={[styles.center, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]}>
                <Ionicons name="school-outline" size={60} color="#94a3b8" />
                <Text style={[styles.noSchoolText, { color: isDarkMode ? '#f8fafc' : '#0f172a' }]}>School Not Linked</Text>
                <Text style={styles.noSchoolSub}>Please update your profile with your school name to see teacher updates.</Text>
            </View>
        );
    }

    return (
        <View style={[styles.container, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]}>
            <LinearGradient colors={['#4f46e5', '#6366f1']} style={styles.header}>
                <SafeAreaView edges={['top']} style={styles.safeArea}>
                    <View style={styles.headerContent}>
                        <Ionicons name="school" size={28} color="white" style={styles.headerIcon} />
                        <View>
                            <Text style={styles.headerTitle}>Class Updates</Text>
                            <Text style={styles.headerSubtitle}>{user.school_name}</Text>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            {loading ? (
                <ActivityIndicator size="large" color="#6366f1" style={{ marginTop: 50 }} />
            ) : (
                <FlatList
                    data={updates}
                    keyExtractor={item => String(item.update_id)}
                    renderItem={renderItem}
                    contentContainerStyle={styles.listContent}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={['#6366f1']} />}
                    ListEmptyComponent={
                        <View style={styles.emptyBox}>
                            <Text style={[styles.emptyText, { color: isDarkMode ? '#94a3b8' : '#64748b' }]}>No updates from your teachers yet.</Text>
                        </View>
                    }
                />
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    header: { paddingBottom: 20, borderBottomLeftRadius: 20, borderBottomRightRadius: 20, elevation: 5 },
    safeArea: { backgroundColor: 'transparent' },
    headerContent: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, paddingTop: 10 },
    headerIcon: { marginRight: 15 },
    headerTitle: { fontSize: 22, fontFamily: 'NotoSans-Bold', color: 'white' },
    headerSubtitle: { fontSize: 13, fontFamily: 'NotoSans-Regular', color: 'rgba(255,255,255,0.8)' },
    listContent: { padding: 16, paddingBottom: 100 },
    card: { borderRadius: 16, padding: 16, marginBottom: 16, borderWidth: 1, elevation: 2, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 5 },
    cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 12 },
    headerLeft: { flexDirection: 'row', alignItems: 'center' },
    iconBox: { width: 40, height: 40, borderRadius: 10, justifyContent: 'center', alignItems: 'center', marginRight: 12 },
    teacherName: { fontSize: 15, fontFamily: 'NotoSans-Bold' },
    timeText: { fontSize: 12, fontFamily: 'NotoSans-Regular', color: '#94a3b8', marginTop: 2 },
    badge: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6 },
    badgeText: { fontSize: 10, fontFamily: 'NotoSans-Bold' },
    title: { fontSize: 17, fontFamily: 'NotoSans-Bold', marginBottom: 6 },
    message: { fontSize: 14, fontFamily: 'NotoSans-Regular', lineHeight: 20, marginBottom: 12 },
    photo: { width: '100%', height: 200, borderRadius: 12, marginBottom: 12, backgroundColor: '#f1f5f9' },
    actionButton: { borderRadius: 12, overflow: 'hidden', marginTop: 5 },
    actionGradient: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingVertical: 12 },
    actionText: { color: 'white', fontFamily: 'NotoSans-Bold', fontSize: 15 },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 20 },
    noSchoolText: { fontSize: 20, fontFamily: 'NotoSans-Bold', marginTop: 16, marginBottom: 8 },
    noSchoolSub: { fontSize: 14, fontFamily: 'NotoSans-Regular', color: '#64748b', textAlign: 'center', paddingHorizontal: 20 },
    emptyBox: { alignItems: 'center', marginTop: 60 },
    emptyText: { fontSize: 15, fontFamily: 'NotoSans-Regular' }
});

export default ClassUpdatesScreen;
