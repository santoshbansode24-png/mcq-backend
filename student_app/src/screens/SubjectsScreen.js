import React, { useState, useEffect, useCallback } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    FlatList,
    ActivityIndicator,
    Alert,
    SafeAreaView,
    StatusBar,
    Platform,
    RefreshControl
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { fetchSubjects } from '../api/subjects';
import { useTheme } from '../context/ThemeContext';

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

const SubjectsScreen = ({ user, navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const classId = user?.class_id;
    const [subjects, setSubjects] = useState([]);
    const [loading, setLoading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);



    // ... inside SubjectsScreen
    useFocusEffect(
        useCallback(() => {
            if (classId) loadSubjects();
        }, [classId])
    );

    const loadSubjects = async (forceRefresh = false) => {
        if (forceRefresh) {
            setRefreshing(true);
        } else {
            setLoading(true);
        }

        try {
            const response = await fetchSubjects(classId, forceRefresh);
            if (response.status === 'success') {
                setSubjects(response.data || []);
            } else if (Array.isArray(response)) {
                setSubjects(response);
            } else if (response.message !== 'No subjects found for this class' && response.message !== 'No class selected') {
                Alert.alert('Error', response.message || 'Failed to load subjects');
            }
        } catch (error) {
            Alert.alert('Error', 'Failed to load subjects. Please check your network connection.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const onRefresh = useCallback(() => {
        loadSubjects(true);
    }, [classId]);

    const renderSubjectItem = useCallback(({ item, index }) => {
        const colors = getSubjectGradient(index);
        return (
            <TouchableOpacity
                activeOpacity={0.9}
                style={styles.subjectTileWrapper}
                onPress={() => navigation.navigate('Chapters', { subject: item })}
            >
                <LinearGradient
                    colors={colors}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 1 }}
                    style={styles.subjectTileGlossy}
                >
                    {/* Glassy Overlay */}
                    <LinearGradient
                        colors={['rgba(255,255,255,0.3)', 'rgba(255,255,255,0)']}
                        style={styles.glossyOverlayTile}
                    />

                    <View style={styles.subjectIcon3D}>
                        <Text style={styles.subjectEmoji}>{getSubjectIcon(item.subject_name)}</Text>
                    </View>

                    <Text style={styles.subjectNameGlossy} numberOfLines={2}>
                        {item.subject_name}
                    </Text>

                    <View style={styles.statsBadgeSmall}>
                        <Text style={styles.statsTextSmall}>
                            {item.total_chapters} Chapters
                        </Text>
                    </View>
                </LinearGradient>
            </TouchableOpacity>
        );
    }, [navigation]);

    return (
        <View style={[styles.outerContainer, { backgroundColor: theme.background }]}>
            {/* Setting translucent to false forces the View to start BELOW the status bar on Android */}
            {/* Setting translucent to false forces the View to start BELOW the status bar on Android */}
            <StatusBar
                barStyle={isDarkMode ? 'light-content' : 'dark-content'}
                backgroundColor="transparent"
                translucent={true}
            />
            <View style={styles.safeArea}>
                <View style={styles.header}>
                    <Text style={[styles.headerTitle, { color: theme.text }]}>My Subjects</Text>
                    {user?.class_name && (
                        <View style={[styles.classBadge, { backgroundColor: isDarkMode ? '#1e293b' : '#F1F5F9' }]}>
                            <Text style={[styles.subHeader, { color: theme.textSecondary }]}>Class {user.class_name}</Text>
                        </View>
                    )}
                </View>
 
                {loading ? (
                    <View style={styles.center}>
                        <ActivityIndicator size="large" color="#4F46E5" />
                    </View>
                ) : (
                    <FlatList
                        data={subjects}
                        renderItem={renderSubjectItem}
                        keyExtractor={(item) => item.subject_id.toString()}
                        contentContainerStyle={styles.listContainer}
                        numColumns={2}
                        columnWrapperStyle={styles.columnWrapper}
                        showsVerticalScrollIndicator={false}
                        refreshControl={
                            <RefreshControl
                                refreshing={refreshing}
                                onRefresh={onRefresh}
                                colors={['#4f46e5']}
                                tintColor="#4f46e5"
                            />
                        }
                        ListEmptyComponent={
                            <View style={styles.emptyContainer}>
                                <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No subjects available.</Text>
                            </View>
                        }
                    />
                )}
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    outerContainer: {
        flex: 1,
        // Background color is handled via inline style from theme
    },
    safeArea: {
        flex: 1,
        // Double protection: If StatusBar is still overlapping, this padding will push it down
        paddingTop: Platform.OS === 'android' ? 10 : 0,
    },
    header: {
        paddingHorizontal: 24,
        paddingTop: 15,
        paddingBottom: 10,
    },
    headerTitle: {
        fontSize: 26,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    classBadge: {
        backgroundColor: '#F1F5F9',
        alignSelf: 'flex-start',
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 6,
        marginTop: 4,
    },
    subHeader: {
        fontSize: 12,
        color: '#64748B',
        fontWeight: '600',
        fontFamily: 'NotoSans-Bold',
    },
    listContainer: {
        padding: 16,
        paddingBottom: 100, // Added to lift content above bottom tab bar
    },
    columnWrapper: {
        justifyContent: 'space-between',
    },
    subjectTileWrapper: {
        width: '48%',
        marginBottom: 16,
    },
    subjectTileGlossy: {
        padding: 16,
        borderRadius: 24,
        height: 160,
        justifyContent: 'space-between',
        elevation: 8,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 8,
        overflow: 'hidden',
    },
    glossyOverlayTile: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: '60%',
    },
    subjectIcon3D: {
        width: 44,
        height: 44,
        borderRadius: 14,
        backgroundColor: 'rgba(255, 255, 255, 0.2)',
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.3)',
    },
    subjectEmoji: { fontSize: 22 },
    subjectNameGlossy: {
        fontSize: 15,
        fontWeight: '800',
        color: 'white',
        fontFamily: 'NotoSans-Bold',
        textTransform: 'uppercase',
        lineHeight: 20,
    },
    statsBadgeSmall: {
        backgroundColor: 'rgba(255, 255, 255, 0.15)',
        alignSelf: 'flex-start',
        paddingHorizontal: 8,
        paddingVertical: 2,
        borderRadius: 8,
    },
    statsTextSmall: {
        fontSize: 10,
        color: 'rgba(255, 255, 255, 0.9)',
        fontFamily: 'NotoSans-Bold',
    },
    center: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    emptyContainer: {
        alignItems: 'center',
        marginTop: 50,
    },
    emptyText: {
        color: '#94A3B8',
        fontFamily: 'NotoSans-Regular',
    }
});

export default SubjectsScreen;