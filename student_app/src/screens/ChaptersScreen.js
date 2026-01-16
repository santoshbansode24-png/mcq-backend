import React, { useState, useEffect, useCallback } from 'react';
import {
    View,
    Text,
    StyleSheet,
    FlatList,
    TouchableOpacity,
    ActivityIndicator,
    Alert,
    StatusBar,
    Platform
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import Svg, { Circle } from 'react-native-svg';
import { fetchChapters } from '../api/chapters';
import { fetchChapterProgress } from '../api/chapterProgress';
import { useTheme } from '../context/ThemeContext';
import { fonts } from '../styles/typography';

// Neon Accent Colors for Chapters
const CHAPTER_ACCENTS = ['#00F5FF', '#BF00FF', '#39FF14', '#FF007F', '#FFF01F', '#FF4D00'];

const ChaptersScreen = ({ navigation, route, user }) => {
    const { theme, isDarkMode } = useTheme();
    const { subject } = route.params || {};
    const [chapters, setChapters] = useState([]);
    const [progressData, setProgressData] = useState({});
    const [summary, setSummary] = useState(null);
    const [loading, setLoading] = useState(false);

    const [refreshing, setRefreshing] = useState(false);

    useEffect(() => {
        if (subject?.subject_id && user?.user_id) {
            loadChaptersWithProgress();
        }
    }, [subject, user]);

    const loadChaptersWithProgress = async (isRefreshing = false) => {
        if (!isRefreshing) setLoading(true);
        try {
            // Load chapters
            const chaptersResponse = await fetchChapters(subject.subject_id);

            // Load progress data
            const progressResponse = await fetchChapterProgress(user.user_id, subject.subject_id);

            if (chaptersResponse.status === 'success') {
                setChapters(chaptersResponse.data);
            }

            if (progressResponse.status === 'success') {
                // Create a map of chapter_id to progress data
                const progressMap = {};
                progressResponse.data.chapters.forEach(ch => {
                    progressMap[ch.chapter_id] = ch;
                });
                setProgressData(progressMap);
                setSummary(progressResponse.data.summary);
            }
        } catch (error) {
            console.error('Error loading chapters:', error);
            Alert.alert('Error', 'Failed to load chapters');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const onRefresh = useCallback(() => {
        setRefreshing(true);
        loadChaptersWithProgress(true);
    }, [subject, user]);

    const getStatusColor = (status) => {
        switch (status) {
            case 'completed': return ['#10b981', '#059669']; // Green
            case 'in_progress': return ['#f59e0b', '#d97706']; // Orange
            default: return ['#6b7280', '#4b5563']; // Grey
        }
    };

    const renderSummarySection = () => {
        if (!summary) return null;

        const total = summary.total_chapters || 0;
        const completed = summary.completed || 0;
        const inProgress = summary.in_progress || 0;

        const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;

        // Settings for the circle
        const size = 65; // Increased from 50
        const strokeWidth = 7;
        const radius = (size - strokeWidth) / 2;
        const circumference = radius * 2 * Math.PI;
        const strokeDashoffset = circumference - (percentage / 100) * circumference;

        return (
            <LinearGradient
                colors={['#f093fb', '#f5576c']}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={styles.dashboardCard}
            >
                <View style={styles.chartContainer}>
                    <Svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
                        {/* Background Circle - Semi-transparent white */}
                        <Circle
                            cx={size / 2}
                            cy={size / 2}
                            r={radius}
                            stroke="rgba(255, 255, 255, 0.2)"
                            strokeWidth={strokeWidth}
                            fill="none"
                        />
                        {/* Progress Circle - Cyan for pop */}
                        <Circle
                            cx={size / 2}
                            cy={size / 2}
                            r={radius}
                            stroke="#ffffff"
                            strokeWidth={strokeWidth}
                            fill="none"
                            strokeDasharray={circumference}
                            strokeDashoffset={strokeDashoffset}
                            strokeLinecap="round"
                            rotation="-90"
                            origin={`${size / 2}, ${size / 2}`}
                        />
                    </Svg>
                    <View style={styles.chartTextContainer}>
                        <Text style={[styles.chartPercentage, { color: 'white' }]}>{percentage}%</Text>
                        <Text style={[styles.chartLabel, { color: 'rgba(255,255,255,0.9)' }]}>Done</Text>
                    </View>
                </View>

                <View style={styles.statsContainer}>
                    <Text style={[styles.dashboardTitle, { color: 'white' }]}>Overview</Text>

                    <View style={styles.statRow}>
                        <View style={[styles.dot, { backgroundColor: 'white' }]} />
                        <Text style={[styles.statLabel, { color: 'rgba(255,255,255,0.9)' }]}>Total</Text>
                        <Text style={[styles.statValue, { color: 'white' }]}>{total}</Text>
                    </View>

                    <View style={styles.statRow}>
                        <View style={[styles.dot, { backgroundColor: '#ffe4e6' }]} />
                        <Text style={[styles.statLabel, { color: 'rgba(255,255,255,0.9)' }]}>Completed</Text>
                        <Text style={[styles.statValue, { color: 'white' }]}>{completed}</Text>
                    </View>

                    <View style={styles.statRow}>

                        <View style={[styles.dot, { backgroundColor: '#fae8ff' }]} />
                        <Text style={[styles.statLabel, { color: 'rgba(255,255,255,0.9)' }]}>In Prog</Text>
                        <Text style={[styles.statValue, { color: 'white' }]}>{inProgress}</Text>
                    </View>
                </View>
            </LinearGradient>
        );
    };

    const renderChapterItem = useCallback(({ item, index }) => {
        const accentColor = CHAPTER_ACCENTS[index % CHAPTER_ACCENTS.length];
        const progress = progressData[item.chapter_id] || { status: 'not_started' };
        const isCompleted = progress.status === 'completed';

        return (
            <TouchableOpacity
                activeOpacity={0.8}
                style={[styles.chapterCard, { backgroundColor: theme.card, borderLeftColor: accentColor, shadowColor: theme.shadow }]}
                onPress={() => navigation.navigate('ChapterContent', { chapter: item })}
            >
                <View style={[styles.numberCircle, { backgroundColor: accentColor + '15' }]}>
                    <Text style={[styles.chapterNumber, { color: accentColor }]}>{index + 1}</Text>
                </View>

                <View style={styles.chapterInfo}>
                    <View style={styles.chapterHeader}>
                        <Text style={[styles.chapterName, { color: theme.text }]} numberOfLines={1}>{item.chapter_name}</Text>
                        {isCompleted && <Text style={styles.goldStar}>⭐</Text>}
                    </View>

                    {/* Motivational Message for Completed Chapters */}
                    {isCompleted && (
                        <View style={styles.motivationBox}>
                            <Text style={styles.motivationText}>🎉 Chapter Mastered!</Text>
                        </View>
                    )}
                </View>

                <Text style={styles.chevron}>›</Text>
            </TouchableOpacity>
        );
    }, [navigation, progressData]);

    return (
        <View style={[styles.mainWrapper, { backgroundColor: theme.background }]}>
            <StatusBar barStyle={isDarkMode ? 'light-content' : 'dark-content'} backgroundColor="transparent" translucent={true} />

            <SafeAreaView style={styles.container} edges={['top', 'left', 'right']}>
                <View style={[styles.header, { backgroundColor: theme.card, borderBottomColor: theme.border }]}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={[styles.backBtn, { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
                        <Text style={[styles.backIcon, { color: theme.text }]}>←</Text>
                    </TouchableOpacity>
                    <View style={styles.headerTextContainer}>
                        <Text style={[styles.headerTitle, { color: theme.text }]} numberOfLines={1}>{subject?.subject_name}</Text>
                        <Text style={[styles.headerSubtitle, { color: theme.textSecondary }]}>{chapters.length} Chapters</Text>
                    </View>
                </View>

                {loading ? (
                    <View style={styles.loaderContainer}>
                        <ActivityIndicator size="large" color="#00F5FF" />
                        <Text style={[styles.loaderText, { color: theme.textSecondary }]}>Loading Chapters...</Text>
                    </View>
                ) : (
                    <FlatList
                        data={chapters}
                        renderItem={renderChapterItem}
                        keyExtractor={(item) => item.chapter_id.toString()}
                        contentContainerStyle={styles.listContainer}
                        showsVerticalScrollIndicator={false}
                        ListHeaderComponent={null}
                        initialNumToRender={10}
                        maxToRenderPerBatch={10}
                        removeClippedSubviews={true}
                        refreshing={refreshing}
                        onRefresh={onRefresh}
                        ListEmptyComponent={
                            <View style={styles.emptyContainer}>
                                <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No chapters available for this subject.</Text>
                            </View>
                        }
                    />
                )}
            </SafeAreaView>
        </View>
    );
};

const styles = StyleSheet.create({
    mainWrapper: {
        flex: 1,
        // backgroundColor handled by theme
    },
    container: {
        flex: 1,
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        paddingVertical: 10,
        // backgroundColor handled by theme
        borderBottomWidth: 1,
        // borderBottomColor handled by theme
    },
    backBtn: {
        width: 40,
        height: 40,
        borderRadius: 20,
        backgroundColor: '#f1f5f9',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 12,
    },
    backIcon: {
        fontSize: 24,
        fontWeight: 'bold',
        // color handled by theme
    },
    headerTextContainer: {
        flex: 1,
    },
    headerTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#0f172a',
    },
    headerSubtitle: {
        fontSize: 14,
        color: '#64748b',
    },
    listContainer: {
        paddingHorizontal: 20,
        paddingBottom: 100, // Increased to clear bottom tabs
        paddingTop: 10,
    },
    chapterCard: {
        flexDirection: 'row',
        // backgroundColor handled by theme
        borderRadius: 14,
        padding: 16, // Increased padding
        marginBottom: 12, // Increased margin
        borderLeftWidth: 4,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
        alignItems: 'center', // Center vertically
    },
    numberCircle: {
        width: 38, // Increased size
        height: 38, // Increased size
        borderRadius: 19,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 10,
    },
    chapterNumber: {
        fontSize: 16, // Increased font size
        fontWeight: 'bold',
    },
    chapterInfo: {
        flex: 1,
    },
    chapterHeader: {
        flexDirection: 'row',
        alignItems: 'center',
    },
    chapterName: {
        fontSize: 15, // Increased font size
        // color handled by theme
        flex: 1,
        fontFamily: 'NotoSans-Bold',
    },
    goldStar: {
        fontSize: 16, // Reduced from 20
        marginLeft: 6,
    },
    motivationBox: {
        backgroundColor: '#dcfce7',
        padding: 4, // Reduced from 8
        borderRadius: 4,
        marginTop: 4, // Reduced from 8
        alignSelf: 'flex-start'
    },
    motivationText: {
        fontSize: 10, // Reduced from 12
        fontWeight: '600',
        color: '#166534',
        textAlign: 'center',
    },
    chevron: {
        fontSize: 20, // Reduced from 28
        color: '#cbd5e1',
        alignSelf: 'center',
        marginLeft: 4,
    },
    loaderContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    loaderText: {
        marginTop: 12,
        fontSize: 14,
        color: '#64748b',
    },
    emptyContainer: {
        padding: 40,
        alignItems: 'center',
    },
    emptyText: {
        fontSize: 14,
        color: '#64748b',
    },
});

export default ChaptersScreen;