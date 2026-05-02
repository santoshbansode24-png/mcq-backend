import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
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
    Animated,
    InteractionManager
} from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { fetchSubjects } from '../api/subjects';
import { useTheme } from '../context/ThemeContext';
import { useLanguage } from '../context/LanguageContext';
import { BASE_URL } from '../api/config';
import { dataCache } from '../utils/dataCache';
import { SmartCacheService } from '../services/SmartCacheService';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { moderateScale as rs, verticalScale as rsv } from '../utils/responsive';

const SUBJECT_GRADIENTS = [
    ['#4f46e5', '#818cf8'], // Indigo
    ['#0891b2', '#22d3ee'], // Cyan
    ['#059669', '#34d399'], // Emerald
    ['#d97706', '#fbbf24'], // Amber
    ['#db2777', '#f472b6'], // Pink
    ['#7c3aed', '#a78bfa'], // Violet
];

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
        <Animated.View style={[styles.subjectCardGlossy, { opacity, backgroundColor: '#e2e8f0', borderWidth: 0, shadowOpacity: 0, elevation: 0 }]}>
            <View style={[styles.subjectIcon3D, { backgroundColor: '#cbd5e1', borderColor: 'transparent' }]} />
            <View style={{ flex: 1, marginLeft: 16 }}>
                <View style={{ height: 16, backgroundColor: '#cbd5e1', borderRadius: 4, width: '60%', marginBottom: 8 }} />
                <View style={{ height: 12, backgroundColor: '#cbd5e1', borderRadius: 4, width: '40%' }} />
            </View>
        </Animated.View>
    );
};
// ── ListHeader must be defined OUTSIDE the component so React doesn't
// unmount/remount it on every re-render (was a major perf issue).
const HomeListHeader = React.memo(({ 
    userName, theme, t, isDarkMode, isSyncing, isFullySynced, hasUpdate,
    syncRotAnim, glowAnim, user, navigation, onSyncPress, onProfilePress 
}) => {
    const getCloudIcon = () => {
        if (isSyncing)       return { name: 'cloud-sync',     color: '#fff' };
        if (isFullySynced && !hasUpdate) return { name: 'cloud-check', color: '#10b981' };
        if (hasUpdate)       return { name: 'cloud-download', color: '#fff' };
        return                      { name: 'cloud-sync',     color: theme.primary };
    };
    const cloudIcon = getCloudIcon();

    return (
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
                        {!isSyncing && isFullySynced && !hasUpdate && (
                            <View style={styles.syncIndicator}>
                                <MaterialCommunityIcons name="check-circle" size={12} color="#10b981" />
                                <Text style={[styles.syncText, { color: '#10b981' }]}>Offline Ready</Text>
                            </View>
                        )}
                    </View>
                    <Text style={[styles.userName, { color: theme.text }]} numberOfLines={1} adjustsFontSizeToFit>{userName} 👋</Text>
                </View>
                <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                    <TouchableOpacity
                        onPress={onSyncPress}
                        style={[
                            styles.syncButton,
                            hasUpdate ? styles.syncButtonUpdate : null,
                            isFullySynced && !hasUpdate ? styles.syncButtonSynced : null
                        ]}
                        disabled={isSyncing}
                    >
                        <Animated.View style={[
                            styles.syncIconContainer,
                            {
                                transform: [{ rotate: syncRotAnim.interpolate({ inputRange: [0, 1], outputRange: ['0deg', '360deg'] }) }],
                                shadowOpacity: hasUpdate ? glowAnim : 0,
                                elevation: hasUpdate ? 5 : 0
                            }
                        ]}>
                            {isSyncing ? (
                                <ActivityIndicator size="small" color="#fff" />
                            ) : (
                                <View>
                                    <MaterialCommunityIcons name={cloudIcon.name} size={24} color={cloudIcon.color} />
                                    {hasUpdate && <View style={styles.updateDot} />}
                                </View>
                            )}
                        </Animated.View>
                    </TouchableOpacity>

                    <TouchableOpacity onPress={onProfilePress}>
                        <View style={[styles.avatarContainer, { borderColor: theme.primary }]}>
                            {user?.profile_picture ? (
                                <Image
                                    source={{ uri: user.profile_picture.startsWith('http') ? user.profile_picture : `${BASE_URL}/${user.profile_picture}` }}
                                    style={styles.avatar}
                                />
                            ) : (
                                <LinearGradient colors={['#6366f1', '#a855f7']} style={styles.avatarPlaceholder}>
                                    <Text style={styles.avatarText}>{userName.charAt(0)}</Text>
                                </LinearGradient>
                            )}
                        </View>
                    </TouchableOpacity>
                </View>
            </View>

            <Text style={[styles.sectionTitle, { color: theme.text }]}>{t('dailyBoosters')}</Text>
            <View style={styles.gridContainer}>
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
                            <Text style={styles.bannerTitle}>{t('studyPlanner') || 'My Study Plan'}</Text>
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
                let scholarshipClassId = 38;
                if (studentClass >= 5 && studentClass <= 7) scholarshipClassId = 39;
                else if (studentClass >= 8 && studentClass <= 10) scholarshipClassId = 40;
                let title = 'Scholarship (Primary)';
                if (scholarshipClassId === 39) title = 'Scholarship (Upper Primary)';
                if (scholarshipClassId === 40) title = 'Scholarship (Secondary)';
                navigation.navigate('ScholarshipSubjects', { scholarshipClassId, levelTitle: title });
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
});

const HomeScreen = ({ user, navigation, route }) => {
    const { theme, isDarkMode } = useTheme();
    const { t } = useLanguage();
    const userName = (user?.name || 'Student').split(' ')[0];
    const classId = user?.class_id;

    const [subjects, setSubjects] = useState([]);
    const [loading, setLoading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);
    const [isFullySynced, setIsFullySynced] = useState(false);
    const [hasUpdate, setHasUpdate] = useState(false);
    const [pendingServerVersion, setPendingServerVersion] = useState(null);
    const glowAnim = useRef(new Animated.Value(0)).current;
    const syncRotAnim = useRef(new Animated.Value(0)).current;



    useEffect(() => {
        const unsubscribe = SmartCacheService.subscribe((status) => {
            if (status.isSyncing !== undefined) setIsSyncing(status.isSyncing);
            if (status.isFullySynced !== undefined) {
                setIsFullySynced(status.isFullySynced);
                if (status.isFullySynced) {
                    checkVersion();
                }
            }
        });

        checkVersion();

        return () => unsubscribe();
    }, []);

    const checkVersion = async () => {
        if (!user?.board_type) return;
        try {
            const serverVer = await SmartCacheService.checkContentVersion(user.board_type);
            const localVer = await AsyncStorage.getItem(`@local_ver_${user.board_type}`);

            if (serverVer) {
                setPendingServerVersion(serverVer);
                if (!localVer || parseInt(serverVer) > parseInt(localVer)) {
                    // Only flag as update if we aren't actively syncing it right now
                    if (!isSyncing) {
                        setHasUpdate(true);
                        setIsFullySynced(false); 
                        startGlow();
                    }
                } else {
                    setHasUpdate(false);
                }
            }
        } catch (e) {
            console.log('[Home] Version check error', e);
        }
    };

    const startGlow = () => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(glowAnim, { toValue: 1, duration: 1500, useNativeDriver: true }),
                Animated.timing(glowAnim, { toValue: 0, duration: 1500, useNativeDriver: true })
            ])
        ).start();
    };

    const forceSync = async () => {
        if (isSyncing) return;

        setIsSyncing(true);

        const queue = await SmartCacheService.getSyncQueue();
        if (!hasUpdate && queue && queue.length > 0) {
            console.log('[Home] Resuming interrupted sync queue manually.');
            await SmartCacheService.processSyncQueue();
            setIsSyncing(false);
            return;
        }

        const versionToSave = pendingServerVersion;
        setHasUpdate(false);

        try {
            // Trigger priority sync
            await SmartCacheService.syncAllForClass(classId, true);

            // Update local version AFTER successful sync
            const latestVer = versionToSave || await SmartCacheService.checkContentVersion(user.board_type);
            if (latestVer) {
                await AsyncStorage.setItem(`@local_ver_${user.board_type}`, latestVer.toString());
            }
            
            setHasUpdate(false);
            setIsFullySynced(true);

            // Removed deleted data by clearing main class cache keys
            await dataCache.remove(`subjects_${classId}`);
            
            // Refresh subjects list after sync
            loadSubjects(true);
            
            Alert.alert('Sync Complete! 🚀', 'All content is now available offline.');
            
        } catch (error) {
            console.warn('[Home] Sync failed:', error.message);
        } finally {
            setIsSyncing(false);
        }
    };

    useFocusEffect(
        useCallback(() => {
            const task = InteractionManager.runAfterInteractions(() => {
                if (classId) {
                    loadSubjects();
                    checkVersion();
                }
            });
            return () => task.cancel();
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
        return SUBJECT_GRADIENTS[index % SUBJECT_GRADIENTS.length];
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

    // Stable sync press handler — memoized so HomeListHeader doesn't re-render unnecessarily
    const handleSyncPress = useCallback(async () => {
        if (isSyncing) return;
        try {
            const serverVer = await SmartCacheService.checkContentVersion(user.board_type);
            const localVer = await AsyncStorage.getItem(`@local_ver_${user.board_type}`);
            if (serverVer && (!localVer || parseInt(serverVer) > parseInt(localVer))) {
                setHasUpdate(true);
                setIsFullySynced(false);
                startGlow();
                Alert.alert('New Data Found', 'Downloading new updates...');
                forceSync();
            } else {
                if (isFullySynced && !hasUpdate) {
                    Alert.alert('Up to Date! 🚀', 'All textual data is downloaded for offline use!');
                } else {
                    forceSync();
                }
            }
        } catch {
            if (isFullySynced && !hasUpdate) {
                Alert.alert('Offline Ready', 'All textual data for your class is downloaded.');
            } else {
                forceSync();
            }
        }
    }, [isSyncing, isFullySynced, hasUpdate, user?.board_type]);

    const handleProfilePress = useCallback(() => navigation.navigate('Profile'), [navigation]);

    // Stable header reference — only re-render if these specific values change
    const listHeader = useMemo(() => (
        <HomeListHeader
            userName={userName}
            theme={theme}
            t={t}
            isDarkMode={isDarkMode}
            isSyncing={isSyncing}
            isFullySynced={isFullySynced}
            hasUpdate={hasUpdate}
            syncRotAnim={syncRotAnim}
            glowAnim={glowAnim}
            user={user}
            navigation={navigation}
            onSyncPress={handleSyncPress}
            onProfilePress={handleProfilePress}
        />
    ), [userName, theme, t, isDarkMode, isSyncing, isFullySynced, hasUpdate, user, navigation, handleSyncPress, handleProfilePress]);

    return (
        <View style={styles.container}>
            <StatusBar barStyle={isDarkMode ? "light-content" : "dark-content"} backgroundColor="transparent" translucent />
            <LinearGradient colors={isDarkMode ? ['#0f172a', '#1e1b4b'] : ['#f0f9ff', '#e0f2fe']} style={styles.background} />

            <SafeAreaView style={styles.safeArea}>
                <FlatList
                    data={subjects}
                    keyExtractor={(item) => item.subject_id.toString()}
                    renderItem={renderSubjectItem}
                    ListHeaderComponent={listHeader}
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
    scrollPadding: { paddingHorizontal: rs(20), paddingTop: Platform.OS === 'android' ? StatusBar.currentHeight : 0 },
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: rsv(20), marginTop: 0 },
    greeting: { fontSize: rs(16), fontWeight: '500', marginBottom: rs(4), fontFamily: 'NotoSans-Regular' },
    userName: { fontSize: rs(28), fontWeight: '800', fontFamily: 'NotoSans-Bold' },
    avatarContainer: { borderWidth: 2, borderRadius: rs(30), padding: rs(2) },
    avatar: { width: rs(50), height: rs(50), borderRadius: rs(25) },
    avatarPlaceholder: { width: rs(50), height: rs(50), borderRadius: rs(25), justifyContent: 'center', alignItems: 'center' },
    avatarText: { fontSize: rs(22), fontWeight: 'bold', color: 'white', fontFamily: 'NotoSans-Bold' },
    sectionTitle: { fontSize: rs(20), fontWeight: '700', marginBottom: rsv(15), fontFamily: 'NotoSans-Bold', textTransform: 'uppercase' },
    gridContainer: { marginBottom: rsv(25) },
    gridItem: { flex: 1, height: rsv(120), borderRadius: rs(24), overflow: 'hidden', elevation: 4, shadowOpacity: 0.2, shadowRadius: 5 },
    gridGradient: { flex: 1, padding: rs(15), justifyContent: 'center', alignItems: 'center' },
    gridIcon: { fontSize: rs(32), marginBottom: rs(8) },
    gridTitle: { fontSize: rs(12), fontWeight: 'bold', color: 'white', fontFamily: 'NotoSans-Bold', textTransform: 'uppercase', textAlign: 'center' },
    fullWidthCard: { marginBottom: rsv(25), borderRadius: rs(24), overflow: 'hidden', elevation: 4 },
    bannerGradient: { padding: rs(20) },
    bannerContent: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
    bannerTitle: { fontSize: rs(16), fontWeight: 'bold', color: 'white', fontFamily: 'NotoSans-Bold', textTransform: 'uppercase' },
    bannerSubtitle: { fontSize: rs(12), color: 'white', opacity: 0.9, fontFamily: 'NotoSans-Regular' },
    bannerIconContainer: { width: rs(44), height: rs(44), backgroundColor: 'rgba(255,255,255,0.2)', borderRadius: rs(12), justifyContent: 'center', alignItems: 'center' },
    subjectWrapper: { marginBottom: rsv(15) },
    subjectCardGlossy: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: rs(16),
        borderRadius: rs(24),
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
        width: rs(54),
        height: rs(54),
        borderRadius: rs(18),
        backgroundColor: 'rgba(255, 255, 255, 0.25)',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: rs(16),
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.3)',
    },
    subjectEmoji: { fontSize: rs(28) },
    subjectInfo: { flex: 1 },
    subjectNameGlossy: {
        fontSize: rs(17),
        fontWeight: '800',
        color: 'white',
        fontFamily: 'NotoSans-Bold',
        textTransform: 'uppercase',
        letterSpacing: 0.3,
    },
    subjectStatsBadge: {
        backgroundColor: 'rgba(255, 255, 255, 0.15)',
        alignSelf: 'flex-start',
        paddingHorizontal: rs(8),
        paddingVertical: rs(2),
        borderRadius: rs(8),
        marginTop: rs(4),
    },
    subjectStatsText: {
        fontSize: rs(10),
        color: 'rgba(255, 255, 255, 0.9)',
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 0.5,
    },
    arrowContainerGlossy: {
        width: rs(32),
        height: rs(32),
        borderRadius: rs(12),
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
    },
    syncButton: {
        marginRight: 12,
        width: 44,
        height: 44,
        justifyContent: 'center',
        alignItems: 'center',
    },
    syncButtonUpdate: {
        backgroundColor: '#ef4444',
        borderRadius: 22,
        shadowColor: '#ef4444',
        shadowOffset: { width: 0, height: 0 },
        shadowRadius: 10,
    },
    syncButtonSynced: {
        backgroundColor: '#10b981',
        borderRadius: 22,
        shadowColor: '#10b981',
        shadowOffset: { width: 0, height: 0 },
        shadowRadius: 10,
    },
    syncIconContainer: {
        width: 40,
        height: 40,
        backgroundColor: 'rgba(255,255,255,0.9)',
        borderRadius: 20,
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    updateDot: {
        position: 'absolute',
        top: -2,
        right: -2,
        width: 10,
        height: 10,
        backgroundColor: '#ef4444',
        borderRadius: 5,
        borderWidth: 1.5,
        borderColor: '#fff',
    }
});

export default HomeScreen;