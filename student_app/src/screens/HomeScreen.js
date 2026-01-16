import React, { useState, useEffect, useCallback } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ActivityIndicator,
    Alert,
    Image,
    RefreshControl,
    SafeAreaView,
    StatusBar,
    Platform,
    FlatList
} from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { fetchSubjects } from '../api/subjects';
import { useTheme } from '../context/ThemeContext';
import { useLanguage } from '../context/LanguageContext';
import { BASE_URL } from '../api/config';
import { dataCache } from '../utils/dataCache';

const HomeScreen = ({ user, navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const { t } = useLanguage();
    const userName = user?.name || 'Student';
    const classId = user?.class_id;

    const [subjects, setSubjects] = useState([]);
    const [loading, setLoading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

    useEffect(() => {
        if (classId) loadSubjects();
    }, [classId]);

    const loadSubjects = async (forceRefresh = false) => {
        if (!forceRefresh) setLoading(true);
        try {
            const response = await fetchSubjects(classId, forceRefresh);
            if (response.status === 'success') {
                setSubjects(response.data);
            } else {
                Alert.alert('Error', response.message);
            }
        } catch (error) {
            Alert.alert('Error', 'Failed to load subjects');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const onRefresh = useCallback(async () => {
        setRefreshing(true);
        await dataCache.remove(`subjects_${classId}`);
        await loadSubjects(true);
    }, [classId]);

    const getImageUrl = (path) => {
        if (!path) return null;
        return path.startsWith('http') ? path : `${BASE_URL}/${path}`;
    };

    // Header component to keep FlatList clean
    const ListHeader = () => (
        <View>
            <View style={styles.header}>
                <View>
                    <Text style={[styles.greeting, { color: theme.textSecondary }]}>{t('welcome')},</Text>
                    <Text style={[styles.userName, { color: theme.text }]}>{userName} 👋</Text>
                </View>
                <TouchableOpacity onPress={() => navigation.navigate('Profile')}>
                    <View style={[styles.avatarContainer, { borderColor: theme.primary }]}>
                        {user?.profile_picture ? (
                            <Image source={{ uri: getImageUrl(user.profile_picture) }} style={styles.avatar} />
                        ) : (
                            <LinearGradient colors={['#6366f1', '#a855f7']} style={styles.avatarPlaceholder}>
                                <Text style={styles.avatarText}>{userName.charAt(0)}</Text>
                            </LinearGradient>
                        )}
                    </View>
                </TouchableOpacity>
            </View>

            <Text style={[styles.sectionTitle, { color: theme.text }]}>{t('dailyBoosters')}</Text>
            <View style={styles.gridContainer}>
                <TouchableOpacity style={[styles.gridItem, { marginRight: 6 }]} onPress={() => navigation.navigate('VocabDashboard')}>
                    <LinearGradient colors={['#f093fb', '#f5576c']} style={styles.gridGradient}>
                        <MaterialCommunityIcons name="book-open-page-variant" size={32} color="white" style={{ marginBottom: 8 }} />
                        <Text style={styles.gridTitle}>{t('vocab')}</Text>
                    </LinearGradient>
                </TouchableOpacity>
                <TouchableOpacity style={[styles.gridItem, { marginHorizontal: 3 }]} onPress={() => navigation.navigate('MentalMaths')}>
                    <LinearGradient colors={['#FF512F', '#F09819']} style={styles.gridGradient}>
                        <MaterialCommunityIcons name="brain" size={32} color="white" style={{ marginBottom: 8 }} />
                        <Text style={styles.gridTitle}>{t('maths')}</Text>
                    </LinearGradient>
                </TouchableOpacity>
                <TouchableOpacity style={[styles.gridItem, { marginLeft: 6 }]} onPress={() => navigation.navigate('MyExam')}>
                    <LinearGradient colors={['#00F260', '#0575E6']} style={styles.gridGradient}>
                        <MaterialCommunityIcons name="file-document-edit-outline" size={32} color="white" style={{ marginBottom: 8 }} />
                        <Text style={styles.gridTitle}>{t('myExam')}</Text>
                    </LinearGradient>
                </TouchableOpacity>
            </View>

            <TouchableOpacity style={styles.fullWidthCard} onPress={() => navigation.navigate('Notifications')}>
                <LinearGradient colors={['#4facfe', '#00f2fe']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.bannerGradient}>
                    <View style={styles.bannerContent}>
                        <View>
                            <Text style={styles.bannerTitle}>{t('classUpdates')}</Text>
                            <Text style={styles.bannerSubtitle}>{t('checkAnnouncements')}</Text>
                        </View>
                        <View style={styles.bannerIconContainer}><Text style={styles.bannerIcon}>🔔</Text></View>
                    </View>
                </LinearGradient>
            </TouchableOpacity>

            <Text style={[styles.sectionTitle, { color: theme.text }]}>{t('yourSubjects')}</Text>
        </View>
    );

    const renderSubjectItem = ({ item, index }) => {
        const gradients = [['#FF9A9E', '#FECFEF'], ['#a18cd1', '#fbc2eb'], ['#84fab0', '#8fd3f4'], ['#a6c0fe', '#f68084']];
        const colors = gradients[index % gradients.length];

        return (
            <TouchableOpacity onPress={() => navigation.navigate('Chapters', { subject: item })} style={styles.subjectWrapper}>
                <View style={[styles.subjectCard, { borderColor: theme.border, backgroundColor: isDarkMode ? '#1f2937' : '#ffffff' }]}>
                    <LinearGradient colors={colors} style={styles.subjectIcon}>
                        <Text style={styles.subjectIconText}>{item.subject_name.charAt(0)}</Text>
                    </LinearGradient>
                    <View style={styles.subjectInfo}>
                        <Text style={[styles.subjectName, { color: theme.text }]}>{item.subject_name}</Text>
                        <Text style={[styles.subjectStats, { color: theme.textSecondary }]}>
                            {item.total_chapters} {t('chapters')} • {item.total_mcqs} {t('mcqs')}
                        </Text>
                    </View>
                    <View style={[styles.arrowContainer, { backgroundColor: isDarkMode ? '#374151' : '#f3f4f6' }]}>
                        <Text style={[styles.arrow, { color: theme.textSecondary }]}>›</Text>
                    </View>
                </View>
            </TouchableOpacity>
        );
    };

    return (
        <View style={styles.container}>
            <StatusBar barStyle={isDarkMode ? "light-content" : "dark-content"} backgroundColor="transparent" translucent />
            <LinearGradient colors={isDarkMode ? ['#0f172a', '#1e1b4b'] : ['#f0f9ff', '#e0f2fe']} style={styles.background} />

            <SafeAreaView style={styles.safeArea}>
                <FlatList
                    data={subjects}
                    keyExtractor={(item) => item.subject_id.toString()}
                    renderItem={renderSubjectItem}
                    ListHeaderComponent={ListHeader}
                    contentContainerStyle={styles.scrollPadding}
                    showsVerticalScrollIndicator={false}
                    refreshControl={
                        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[theme.primary]} tintColor={theme.primary} />
                    }
                    ListEmptyComponent={!loading && (
                        <View style={styles.emptyContainer}>
                            <Text style={[styles.emptyText, { color: theme.textSecondary }]}>{t('noSubjects')}</Text>
                        </View>
                    )}
                    ListFooterComponent={<View style={{ height: 40 }} />}
                />
            </SafeAreaView>

            {loading && !refreshing && (
                <View style={styles.loadingOverlay}>
                    <ActivityIndicator size="large" color={theme.primary} />
                </View>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    safeArea: { flex: 1 },
    background: { ...StyleSheet.absoluteFillObject },
    scrollPadding: { paddingHorizontal: 20, paddingTop: Platform.OS === 'android' ? StatusBar.currentHeight + 10 : 10 },
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 30, marginTop: 10 },
    greeting: { fontSize: 16, fontWeight: '500', marginBottom: 4 },
    userName: { fontSize: 28, fontWeight: '800' },
    avatarContainer: { borderWidth: 2, borderRadius: 30, padding: 2 },
    avatar: { width: 50, height: 50, borderRadius: 25 },
    avatarPlaceholder: { width: 50, height: 50, borderRadius: 25, justifyContent: 'center', alignItems: 'center' },
    avatarText: { fontSize: 22, fontWeight: 'bold', color: 'white' },
    sectionTitle: { fontSize: 20, fontWeight: '700', marginBottom: 15 },
    gridContainer: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 25 },
    gridItem: { flex: 1, height: 140, borderRadius: 24, overflow: 'hidden', elevation: 4, shadowOpacity: 0.2, shadowRadius: 5 },
    gridGradient: { flex: 1, padding: 20, justifyContent: 'center', alignItems: 'center' },
    gridIcon: { fontSize: 32, marginBottom: 8 },
    gridTitle: { fontSize: 15, fontWeight: 'bold', color: 'white' },
    fullWidthCard: { marginBottom: 30, borderRadius: 24, overflow: 'hidden', elevation: 4 },
    bannerGradient: { padding: 20 },
    bannerContent: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
    bannerTitle: { fontSize: 18, fontWeight: 'bold', color: 'white' },
    bannerSubtitle: { fontSize: 13, color: 'white', opacity: 0.9 },
    bannerIconContainer: { width: 44, height: 44, backgroundColor: 'rgba(255,255,255,0.2)', borderRadius: 12, justifyContent: 'center', alignItems: 'center' },
    subjectWrapper: { marginBottom: 12 },
    subjectCard: { flexDirection: 'row', alignItems: 'center', padding: 12, borderRadius: 20, borderWidth: 1 },
    subjectIcon: { width: 50, height: 50, borderRadius: 15, justifyContent: 'center', alignItems: 'center', marginRight: 12 },
    subjectIconText: { fontSize: 20, fontWeight: 'bold', color: 'white' },
    subjectInfo: { flex: 1 },
    subjectName: { fontSize: 16, fontWeight: 'bold', marginBottom: 2 },
    subjectStats: { fontSize: 12 },
    arrowContainer: { width: 28, height: 28, borderRadius: 8, justifyContent: 'center', alignItems: 'center' },
    loadingOverlay: { padding: 40, alignItems: 'center' },
    emptyContainer: { alignItems: 'center', marginTop: 20 }
});

export default HomeScreen;