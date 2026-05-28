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
import axios from 'axios';
import api, { BASE_URL, API_URL } from '../api/config';
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
// --- Optimized Sub-Components ---
const HomeGreeting = React.memo(({ userName, t, theme, isSyncing, isFullySynced, hasUpdate }) => (
    <View style={{ flex: 1, marginRight: 12, justifyContent: 'center' }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', marginBottom: 2 }}>
            <Text style={[styles.greeting, { color: theme.textSecondary, marginBottom: 0 }]}>{t('welcome')},</Text>
            {isSyncing && (
                <View style={styles.syncIndicator}>
                    <ActivityIndicator size="small" color={theme.primary} style={{ transform: [{ scale: 0.7 }] }} />
                    <Text style={styles.syncText}>Syncing...</Text>
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
));

const HomeSyncButton = React.memo(({ isSyncing, hasUpdate, isFullySynced, onSyncPress, syncRotAnim, glowAnim, theme }) => {
    const getCloudIcon = () => {
        if (isSyncing) return { name: 'cloud-sync', color: '#fff' };
        if (isFullySynced && !hasUpdate) return { name: 'cloud-check', color: '#10b981' };
        if (hasUpdate) return { name: 'cloud-download', color: '#fff' };
        return { name: 'cloud-sync', color: theme.primary };
    };
    const cloudIcon = getCloudIcon();

    return (
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
    );
});

const HomeBoosterGrid = React.memo(({ t, navigation }) => (
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
));

const HomeBanner = React.memo(({ colors, title, subtitle, icon, onPress, iconIsText = false }) => (
    <TouchableOpacity style={styles.fullWidthCard} onPress={onPress}>
        <LinearGradient colors={colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={styles.bannerGradient}>
            <View style={styles.bannerContent}>
                <View style={{ flex: 1 }}>
                    <Text style={styles.bannerTitle}>{title}</Text>
                    <Text style={styles.bannerSubtitle}>{subtitle}</Text>
                </View>
                <View style={styles.bannerIconContainer}>
                    {iconIsText ? <Text style={styles.bannerIcon}>{icon}</Text> : <MaterialCommunityIcons name={icon} size={24} color="white" />}
                </View>
            </View>
        </LinearGradient>
    </TouchableOpacity>
));

const HomeListHeader = React.memo(({ 
    userName, theme, t, isDarkMode, isSyncing, isFullySynced, hasUpdate,
    syncRotAnim, glowAnim, user, navigation, onSyncPress, onProfilePress, activeLiveExam 
}) => {
    return (
        <View>
            <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: rsv(20) }}>
                <HomeGreeting 
                    userName={userName} t={t} theme={theme} 
                    isSyncing={isSyncing} isFullySynced={isFullySynced} hasUpdate={hasUpdate} 
                />
                
                <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                    <HomeSyncButton 
                        isSyncing={isSyncing} hasUpdate={hasUpdate} isFullySynced={isFullySynced} 
                        onSyncPress={onSyncPress} syncRotAnim={syncRotAnim} glowAnim={glowAnim} theme={theme} 
                    />

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

            {activeLiveExam && (
                <HomeBanner 
                    colors={['#EF4444', '#B91C1C']}
                    title="LIVE EXAM NOW!"
                    subtitle={`${activeLiveExam.title} • ${Math.floor(activeLiveExam.remaining_seconds / 60)} mins left`}
                    icon="⚡"
                    iconIsText={true}
                    onPress={() => navigation.navigate('MCQViewer', { 
                        chapterId: activeLiveExam.chapter_id, 
                        isLiveExam: true, 
                        durationMinutes: activeLiveExam.duration_minutes 
                    })}
                />
            )}

            <Text style={[styles.sectionTitle, { color: theme.text }]}>{t('dailyBoosters')}</Text>
            <HomeBoosterGrid t={t} navigation={navigation} />

            <HomeBanner 
                colors={['#FF512F', '#DD2476']}
                title={t('studyPlanner') || 'My Study Plan'}
                subtitle="Your Daily Missions & Streaks 🔥"
                icon="compass-outline"
                onPress={() => navigation.navigate('StudyPlanner')}
            />

            <HomeBanner 
                colors={['#8E2DE2', '#4A00E0']}
                title="Scholarship & Olympiad Corner"
                subtitle="Ace your competitive exams! 🏆"
                icon="trophy-award"
                onPress={() => {
                    const studentClass = parseInt(user?.class_id);
                    let scholarshipClassId = 38;
                    if (studentClass >= 5 && studentClass <= 7) scholarshipClassId = 39;
                    else if (studentClass >= 8 && studentClass <= 10) scholarshipClassId = 40;
                    let title = 'Scholarship (Primary)';
                    if (scholarshipClassId === 39) title = 'Scholarship (Upper Primary)';
                    if (scholarshipClassId === 40) title = 'Scholarship (Secondary)';
                    navigation.navigate('ScholarshipSubjects', { scholarshipClassId, levelTitle: title });
                }}
            />

            <HomeBanner 
                colors={['#4facfe', '#00f2fe']}
                title={t('classUpdates')}
                subtitle={t('checkAnnouncements')}
                icon="🔔"
                iconIsText={true}
                onPress={() => navigation.navigate('Notifications')}
            />

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
    const [activeLiveExam, setActiveLiveExam] = useState(null);
    const glowAnim = useRef(new Animated.Value(0)).current;
    const syncRotAnim = useRef(new Animated.Value(0)).current;

    // Live Exam Polling
    useEffect(() => {
        if (!classId) return;
        const checkExam = async () => {
            try {
                const response = await axios.get(`${API_URL}/student/check_live_exam.php?class_id=${classId}`, { timeout: 8000 });
                if (response.data && response.data.status === 'success' && response.data.data) {
                    setActiveLiveExam(response.data.data);
                } else {
                    setActiveLiveExam(null);
                }
            } catch (error) {
                console.log('Error checking live exam:', error.message);
            }
        };

        checkExam(); // Check immediately
        const interval = setInterval(checkExam, 60000); // Poll every 60 seconds (1 minute) is highly optimized
        return () => clearInterval(interval);
    }, [classId]);



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

    const lastLoadTime = useRef(0);
    const lastVersionCheckTime = useRef(0);

    useFocusEffect(
        useCallback(() => {
            const task = InteractionManager.runAfterInteractions(() => {
                if (classId) {
                    const now = Date.now();
                    // Only auto-reload if subjects are empty OR more than 120 seconds passed to prevent constant DB loading
                    if (subjects.length === 0 || now - lastLoadTime.current > 120000) {
                        loadSubjects();
                        lastLoadTime.current = now;
                    }
                    
                    // Throttle version checks to once every 5 minutes (300,000 ms) instead of on every screen focus
                    if (now - lastVersionCheckTime.current > 300000) {
                        checkVersion();
                        lastVersionCheckTime.current = now;
                    }
                }
            });
            return () => task.cancel();
        }, [classId, subjects.length])
    );

    const loadSubjects = async (forceRefresh = false) => {
        // Only show loading spinner if we don't have any subjects rendered yet
        if (subjects.length === 0) {
            setLoading(true);
        }

        try {
            const response = await fetchSubjects(classId, forceRefresh);
            let subjectData = [];
            
            if (response) {
                if (response.status === 'success' && Array.isArray(response.data)) {
                    subjectData = response.data;
                } else if (Array.isArray(response)) {
                    subjectData = response;
                } else if (Array.isArray(response.data)) {
                    subjectData = response.data;
                }
            }

            setSubjects(subjectData);
        } catch (error) {
            console.log('[Home] Failed to load subjects:', error);
            if (subjects.length === 0) {
                Alert.alert('Connection Error', 'Could not load subjects. Please check your internet connection.');
            }
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const onRefresh = useCallback(async () => {
        setRefreshing(true);
        if (classId) {
            await dataCache.remove(`subjects_${classId}`);
            await loadSubjects(true);
        } else {
            setRefreshing(false);
            Alert.alert('Welcome!', 'Please go to your Profile and select your Class first.');
        }
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
            activeLiveExam={activeLiveExam}
        />
    ), [userName, theme, t, isDarkMode, isSyncing, isFullySynced, hasUpdate, user, navigation, handleSyncPress, handleProfilePress, activeLiveExam]);

    return (
        <View style={styles.container}>
            <StatusBar barStyle={isDarkMode ? "light-content" : "dark-content"} backgroundColor="transparent" translucent />
            <LinearGradient colors={isDarkMode ? ['#0f172a', '#1e1b4b'] : ['#f0f9ff', '#e0f2fe']} style={styles.background} />

            <View style={styles.contentWrapper}>
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
            </View>

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
    contentWrapper: { flex: 1 },
    scrollPadding: { paddingHorizontal: rs(24), paddingTop: rs(10) },
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: rsv(20), marginTop: 0 },
    greeting: { fontSize: rs(14), fontWeight: '600', color: '#64748b', marginBottom: rs(2), fontFamily: 'NotoSans-Regular', letterSpacing: 0.5 },
    userName: { fontSize: rs(30), fontWeight: '900', color: '#1e293b', fontFamily: 'NotoSans-Bold', letterSpacing: -0.5 },
    avatarContainer: { borderWidth: 2, borderRadius: rs(30), padding: rs(2) },
    avatar: { width: rs(50), height: rs(50), borderRadius: rs(25) },
    avatarPlaceholder: { width: rs(50), height: rs(50), borderRadius: rs(25), justifyContent: 'center', alignItems: 'center' },
    avatarText: { fontSize: rs(22), fontWeight: 'bold', color: 'white', fontFamily: 'NotoSans-Bold' },
    sectionTitle: { fontSize: rs(20), fontWeight: '700', marginBottom: rsv(15), fontFamily: 'NotoSans-Bold', textTransform: 'uppercase' },
    gridContainer: { marginBottom: rsv(25), paddingHorizontal: 2 },
    gridItem: { 
        flex: 1, 
        height: rsv(125), 
        borderRadius: rs(28), 
        overflow: 'hidden', 
        elevation: 8, 
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.15, 
        shadowRadius: 12 
    },
    gridGradient: { flex: 1, padding: rs(18), justifyContent: 'center', alignItems: 'center' },
    gridIcon: { fontSize: rs(36), marginBottom: rs(8) },
    gridTitle: { fontSize: rs(12), fontWeight: 'bold', color: 'white', fontFamily: 'NotoSans-Bold', textTransform: 'uppercase', textAlign: 'center' },
    fullWidthCard: { 
        marginBottom: rsv(20), 
        borderRadius: rs(28), 
        overflow: 'hidden', 
        elevation: 6,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.1,
        shadowRadius: 15,
    },
    bannerGradient: { padding: rs(22) },
    bannerContent: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
    bannerTitle: { fontSize: rs(18), fontWeight: '900', color: 'white', fontFamily: 'NotoSans-Bold', letterSpacing: 0.5 },
    bannerSubtitle: { fontSize: rs(12), color: 'white', opacity: 0.85, fontFamily: 'NotoSans-Regular', marginTop: 2 },
    bannerIconContainer: { 
        width: rs(48), 
        height: rs(48), 
        backgroundColor: 'rgba(255,255,255,0.25)', 
        borderRadius: rs(16), 
        justifyContent: 'center', 
        alignItems: 'center',
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.3)',
    },
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
    },
    pulseDot: {
        width: 10,
        height: 10,
        borderRadius: 5,
    }
});

export default HomeScreen;