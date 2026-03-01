import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useFocusEffect } from '@react-navigation/native';
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
    FlatList,
    Animated
} from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { fetchSubjects } from '../api/subjects';
import { useTheme } from '../context/ThemeContext';
import { useLanguage } from '../context/LanguageContext';
import { BASE_URL } from '../api/config';
import { dataCache } from '../utils/dataCache';
import { SmartCacheService } from '../services/SmartCacheService';

const SkeletonItem = () => {
    const opacity = useRef(new Animated.Value(0.3)).current;

    useEffect(() => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(opacity, { toValue: 1, duration: 800, useNativeDriver: true }),
                Animated.timing(opacity, { toValue: 0.3, duration: 800, useNativeDriver: true })
            ])
        ).start();
    }, []);

    return (
        <Animated.View style={[styles.subjectCard, { opacity, backgroundColor: '#f1f5f9', borderWidth: 0 }]}>
            <View style={[styles.iconContainer, { backgroundColor: '#e2e8f0' }]} />
            <View style={{ flex: 1, marginLeft: 16 }}>
                <View style={{ height: 16, backgroundColor: '#e2e8f0', borderRadius: 4, width: '60%', marginBottom: 8 }} />
                <View style={{ height: 12, backgroundColor: '#e2e8f0', borderRadius: 4, width: '40%' }} />
            </View>
        </Animated.View>
    );
};

const HomeScreen = ({ user, navigation, route }) => {
    const { theme, isDarkMode } = useTheme();
    const { t } = useLanguage();
    const userName = (user?.name || 'Student').split(' ')[0];
    const classId = user?.class_id;

    const [subjects, setSubjects] = useState([]);
    const [loading, setLoading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);



    useFocusEffect(
        useCallback(() => {
            if (classId) {
                loadSubjects();
            }
        }, [classId])
    );

    const loadSubjects = async (forceRefresh = false) => {
        // STALE-WHILE-REVALIDATE: Try to show cache immediately
        if (!forceRefresh) {
            try {
                const cached = await dataCache.get(`subjects_${classId}`, 'subjects');
                if (cached && Array.isArray(cached.data)) {
                    setSubjects(cached.data);
                } else if (cached && Array.isArray(cached)) {
                    setSubjects(cached);
                }
            } catch (e) {
                console.log('[Home] Cache load error', e);
            }
        }

        if (!forceRefresh && subjects.length === 0) setLoading(true);

        try {
            const response = await fetchSubjects(classId, forceRefresh);
            if (response.status === 'success') {
                setSubjects(response.data);
            } else if (Array.isArray(response)) {
                setSubjects(response);
            } else {
                // Only alert if we don't have ANY data (even stale)
                if (subjects.length === 0) Alert.alert('Error', response.message || 'Failed to load subjects');
            }
        } catch (error) {
            if (subjects.length === 0) Alert.alert('Error', 'Failed to load subjects');
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
                <View style={{ flex: 1, marginRight: 12 }}>
                    <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                        <Text style={[styles.greeting, { color: theme.textSecondary }]}>{t('welcome')},</Text>
                        {isSyncing && (
                            <View style={styles.syncIndicator}>
                                <ActivityIndicator size="small" color={theme.primary} style={{ transform: [{ scale: 0.7 }] }} />
                                <Text style={styles.syncText}>Syncing offline content...</Text>
                            </View>
                        )}
                    </View>
                    <Text style={[styles.userName, { color: theme.text }]} numberOfLines={1} adjustsFontSizeToFit>{userName} 👋</Text>
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
                {/* Row 1 */}
                <View style={{ flexDirection: 'row', marginBottom: 12 }}>
                    <TouchableOpacity style={[styles.gridItem, { marginRight: 6 }]} onPress={() => navigation.navigate('VocabDashboard')}>
                        <LinearGradient colors={['#f093fb', '#f5576c']} style={styles.gridGradient}>
                            <MaterialCommunityIcons name="book-open-page-variant" size={32} color="white" style={{ marginBottom: 8 }} />
                            <Text style={styles.gridTitle}>{t('vocab')}</Text>
                        </LinearGradient>
                    </TouchableOpacity>
                    <TouchableOpacity style={[styles.gridItem, { marginLeft: 6 }]} onPress={() => navigation.navigate('MentalMaths')}>
                        <LinearGradient colors={['#FF512F', '#F09819']} style={styles.gridGradient}>
                            <MaterialCommunityIcons name="brain" size={32} color="white" style={{ marginBottom: 8 }} />
                            <Text style={styles.gridTitle}>Mental Maths</Text>
                        </LinearGradient>
                    </TouchableOpacity>
                </View>

                {/* Row 2 */}
                <View style={{ flexDirection: 'row' }}>
                    <TouchableOpacity style={[styles.gridItem, { marginRight: 6 }]} onPress={() => navigation.navigate('MyExam')}>
                        <LinearGradient colors={['#00F260', '#0575E6']} style={styles.gridGradient}>
                            <MaterialCommunityIcons name="file-document-edit-outline" size={32} color="white" style={{ marginBottom: 8 }} />
                            <Text style={styles.gridTitle}>{t('myExam')}</Text>
                        </LinearGradient>
                    </TouchableOpacity>
                    <TouchableOpacity style={[styles.gridItem, { marginLeft: 6 }]} onPress={() => navigation.navigate('WorksheetGenerator')}>
                        <LinearGradient colors={['#A855F7', '#C026D3']} style={styles.gridGradient}>
                            <MaterialCommunityIcons name="printer-outline" size={32} color="white" style={{ marginBottom: 8 }} />
                            <Text style={styles.gridTitle}>Worksheet</Text>
                        </LinearGradient>
                    </TouchableOpacity>
                </View>
            </View>

            <TouchableOpacity style={styles.fullWidthCard} onPress={() => navigation.navigate('StudyPlanner')}>
                <LinearGradient colors={['#FF512F', '#DD2476']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={styles.bannerGradient}>
                    <View style={styles.bannerContent}>
                        <View>
                            <Text style={styles.bannerTitle}>{t('studyPlanner') || "My Study Plan"}</Text>
                            <Text style={styles.bannerSubtitle}>Your Daily Missions & Streaks 🔥</Text>
                        </View>
                        <View style={styles.bannerIconContainer}>
                            <MaterialCommunityIcons name="compass-outline" size={24} color="white" />
                        </View>
                    </View>
                </LinearGradient>
            </TouchableOpacity>

            <TouchableOpacity style={styles.fullWidthCard} onPress={() => {
                const studentClass = parseInt(user?.class_id);
                let scholarshipClassId = 38; // Default to Primary (38)

                if (studentClass >= 1 && studentClass <= 4) {
                    scholarshipClassId = 38; // Primary
                } else if (studentClass >= 5 && studentClass <= 7) {
                    scholarshipClassId = 39; // Upper Primary
                } else if (studentClass >= 8 && studentClass <= 10) {
                    scholarshipClassId = 40; // Secondary
                }
                // Determine title for better UX
                let title = "Scholarship (Primary)";
                if (scholarshipClassId === 39) title = "Scholarship (Upper Primary)";
                if (scholarshipClassId === 40) title = "Scholarship (Secondary)";

                navigation.navigate('ScholarshipSubjects', {
                    scholarshipClassId,
                    levelTitle: title
                });
            }}>
                <LinearGradient colors={['#8E2DE2', '#4A00E0']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={styles.bannerGradient}>
                    <View style={styles.bannerContent}>
                        <View>
                            <Text style={styles.bannerTitle}>Scholarship & Olympiad Corner</Text>
                            <Text style={styles.bannerSubtitle}>Ace your competitive exams! 🏆</Text>
                        </View>
                        <View style={styles.bannerIconContainer}>
                            <MaterialCommunityIcons name="trophy-award" size={24} color="white" />
                        </View>
                    </View>
                </LinearGradient>
            </TouchableOpacity>

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

    const getSubjectIcon = (name) => {
        const lowerName = name.toLowerCase();
        if (lowerName.includes('science')) return '🧬';
        if (lowerName.includes('math') || lowerName.includes('ganit')) return '📐';
        if (lowerName.includes('english')) return '🅰️';
        if (lowerName.includes('history') || lowerName.includes('itihas')) return '🏛️';
        if (lowerName.includes('marathi')) return '🚩';
        if (lowerName.includes('hindi')) return '📙';
        if (lowerName.includes('geography') || lowerName.includes('bhugol')) return '🌍';
        if (lowerName.includes('civics') || lowerName.includes('social')) return '⚖️';
        if (lowerName.includes('computer') || lowerName.includes('ict')) return '💻';
        return '📚';
    };

    const getSubjectGradient = (index) => {
        const gradients = [
            ['#4f46e5', '#818cf8'], // Indigo
            ['#0891b2', '#22d3ee'], // Cyan
            ['#059669', '#34d399'], // Emerald
            ['#d97706', '#fbbf24'], // Amber
            ['#db2777', '#f472b6'], // Pink
            ['#7c3aed', '#a78bfa'], // Violet
        ];
        return gradients[index % gradients.length];
    };

    const renderSubjectItem = useCallback(({ item, index }) => {
        const colors = getSubjectGradient(index);

        return (
            <TouchableOpacity
                activeOpacity={0.9}
                onPress={() => navigation.navigate('Chapters', { subject: item })}
                style={styles.subjectWrapper}
            >
                <LinearGradient
                    colors={colors}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 1 }}
                    style={styles.subjectCardGlossy}
                >
                    {/* Glassy Overlay */}
                    <LinearGradient
                        colors={['rgba(255,255,255,0.3)', 'rgba(255,255,255,0)']}
                        style={styles.glossyOverlaySubject}
                    />

                    <View style={styles.subjectIcon3D}>
                        <Text style={styles.subjectEmoji}>{getSubjectIcon(item.subject_name)}</Text>
                    </View>

                    <View style={styles.subjectInfo}>
                        <Text style={styles.subjectNameGlossy} numberOfLines={1}>{item.subject_name}</Text>
                        <View style={styles.subjectStatsBadge}>
                            <Text style={styles.subjectStatsText}>
                                {item.total_chapters} Chapters • {item.total_mcqs} MCQs
                            </Text>
                        </View>
                    </View>

                    <View style={styles.arrowContainerGlossy}>
                        <MaterialCommunityIcons name="chevron-right" size={24} color="white" />
                    </View>
                </LinearGradient>
            </TouchableOpacity>
        );
    }, [navigation]);

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
                    initialNumToRender={8}
                    maxToRenderPerBatch={4}
                    windowSize={5}
                    removeClippedSubviews={true}
                    refreshControl={
                        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[theme.primary]} tintColor={theme.primary} />
                    }
                    ListEmptyComponent={(!loading || subjects.length > 0) && subjects.length === 0 && (
                        <View style={styles.emptyContainer}>
                            <Text style={[styles.emptyText, { color: theme.textSecondary }]}>{t('noSubjects')}</Text>
                        </View>
                    )}
                    ListFooterComponent={<View style={{ height: 40 }} />}
                />
            </SafeAreaView>

            {loading && subjects.length === 0 && !refreshing && (
                <View style={[styles.scrollPadding, { marginTop: -20 }]}>
                    {[1, 2, 3, 4].map(i => <SkeletonItem key={i} />)}
                </View>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    safeArea: { flex: 1 },
    background: { ...StyleSheet.absoluteFillObject },
    scrollPadding: { paddingHorizontal: 20, paddingTop: Platform.OS === 'android' ? StatusBar.currentHeight : 0 },
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20, marginTop: 0 },
    greeting: { fontSize: 16, fontWeight: '500', marginBottom: 4, fontFamily: 'NotoSans-Regular' },
    userName: { fontSize: 28, fontWeight: '800', fontFamily: 'NotoSans-Bold' },
    avatarContainer: { borderWidth: 2, borderRadius: 30, padding: 2 },
    avatar: { width: 50, height: 50, borderRadius: 25 },
    avatarPlaceholder: { width: 50, height: 50, borderRadius: 25, justifyContent: 'center', alignItems: 'center' },
    avatarText: { fontSize: 22, fontWeight: 'bold', color: 'white', fontFamily: 'NotoSans-Bold' },
    sectionTitle: { fontSize: 20, fontWeight: '700', marginBottom: 15, fontFamily: 'NotoSans-Bold', textTransform: 'uppercase' },
    gridContainer: { marginBottom: 25 },
    gridItem: { flex: 1, height: 140, borderRadius: 24, overflow: 'hidden', elevation: 4, shadowOpacity: 0.2, shadowRadius: 5 },
    gridGradient: { flex: 1, padding: 20, justifyContent: 'center', alignItems: 'center' },
    gridIcon: { fontSize: 32, marginBottom: 8 },
    gridTitle: { fontSize: 13, fontWeight: 'bold', color: 'white', fontFamily: 'NotoSans-Bold', textTransform: 'uppercase', textAlign: 'center' },
    fullWidthCard: { marginBottom: 30, borderRadius: 24, overflow: 'hidden', elevation: 4 },
    bannerGradient: { padding: 20 },
    bannerContent: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
    bannerTitle: { fontSize: 16, fontWeight: 'bold', color: 'white', fontFamily: 'NotoSans-Bold', textTransform: 'uppercase' },
    bannerSubtitle: { fontSize: 13, color: 'white', opacity: 0.9, fontFamily: 'NotoSans-Regular' },
    bannerIconContainer: { width: 44, height: 44, backgroundColor: 'rgba(255,255,255,0.2)', borderRadius: 12, justifyContent: 'center', alignItems: 'center' },
    subjectWrapper: { marginBottom: 15 },
    subjectCardGlossy: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 16,
        borderRadius: 24,
        elevation: 8,
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.2,
        shadowRadius: 10,
        overflow: 'hidden',
    },
    glossyOverlaySubject: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: '60%',
        zIndex: 0,
    },
    subjectIcon3D: {
        width: 54,
        height: 54,
        borderRadius: 18,
        backgroundColor: 'rgba(255, 255, 255, 0.25)',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 16,
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.3)',
    },
    subjectEmoji: { fontSize: 28 },
    subjectInfo: { flex: 1 },
    subjectNameGlossy: {
        fontSize: 17,
        fontWeight: '800',
        color: 'white',
        fontFamily: 'NotoSans-Bold',
        textTransform: 'uppercase',
        letterSpacing: 0.3,
    },
    subjectStatsBadge: {
        backgroundColor: 'rgba(255, 255, 255, 0.15)',
        alignSelf: 'flex-start',
        paddingHorizontal: 8,
        paddingVertical: 2,
        borderRadius: 8,
        marginTop: 4,
    },
    subjectStatsText: {
        fontSize: 11,
        color: 'rgba(255, 255, 255, 0.9)',
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 0.5,
    },
    arrowContainerGlossy: {
        width: 32,
        height: 32,
        borderRadius: 12,
        backgroundColor: 'rgba(255, 255, 255, 0.2)',
        justifyContent: 'center',
        alignItems: 'center',
    },
    loadingOverlay: { padding: 40, alignItems: 'center' },
    emptyContainer: { alignItems: 'center', marginTop: 20 },
    syncIndicator: {
        flexDirection: 'row',
        alignItems: 'center',
        marginLeft: 10,
        backgroundColor: 'rgba(255,255,255,0.8)',
        paddingHorizontal: 8,
        paddingVertical: 2,
        borderRadius: 12,
        borderWidth: 1,
        borderColor: '#e2e8f0'
    },
    syncText: {
        fontSize: 10,
        color: '#64748b',
        marginLeft: 4,
        fontWeight: '500',
        fontFamily: 'NotoSans-Bold',
    }
});

export default HomeScreen;