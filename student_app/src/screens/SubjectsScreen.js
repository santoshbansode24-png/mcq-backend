import React, { useState, useEffect, useCallback } from 'react';
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
    Platform
} from 'react-native';
import { fetchSubjects } from '../api/subjects';
import { useTheme } from '../context/ThemeContext';

const SUBJECT_THEMES = [
    { bg: '#4F46E5', text: '#FFFFFF', subText: '#E0E7FF', iconBg: 'rgba(255,255,255,0.2)' },
    { bg: '#059669', text: '#FFFFFF', subText: '#D1FAE5', iconBg: 'rgba(255,255,255,0.2)' },
    { bg: '#EA580C', text: '#FFFFFF', subText: '#FFEDD5', iconBg: 'rgba(255,255,255,0.2)' },
    { bg: '#DB2777', text: '#FFFFFF', subText: '#FCE7F3', iconBg: 'rgba(255,255,255,0.2)' },
    { bg: '#2563EB', text: '#FFFFFF', subText: '#DBEAFE', iconBg: 'rgba(255,255,255,0.2)' },
    { bg: '#7C3AED', text: '#FFFFFF', subText: '#EDE9FE', iconBg: 'rgba(255,255,255,0.2)' },
];

const SubjectsScreen = ({ user, navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const classId = user?.class_id;
    const [subjects, setSubjects] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (classId) loadSubjects();
    }, [classId]);

    const loadSubjects = async () => {
        setLoading(true);
        try {
            const response = await fetchSubjects(classId);
            if (response.status === 'success') {
                setSubjects(response.data);
            } else if (response.message !== 'No subjects found for this class') {
                Alert.alert('Error', response.message);
            }
        } catch (error) {
            Alert.alert('Error', 'Failed to load subjects');
        } finally {
            setLoading(false);
        }
    };

    const renderSubjectItem = useCallback(({ item, index }) => {
        const theme = SUBJECT_THEMES[index % SUBJECT_THEMES.length];
        return (
            <TouchableOpacity
                activeOpacity={0.8}
                style={[styles.subjectTile, { backgroundColor: theme.bg }]}
                onPress={() => navigation.navigate('Chapters', { subject: item })}
            >
                <View style={[styles.subjectIcon, { backgroundColor: theme.iconBg }]}>
                    <Text style={[styles.subjectIconText, { color: theme.text }]}>
                        {item.subject_name.charAt(0).toUpperCase()}
                    </Text>
                </View>
                <View style={styles.textContainer}>
                    <Text style={[styles.subjectName, { color: theme.text }]} numberOfLines={1}>
                        {item.subject_name}
                    </Text>
                    <Text style={[styles.subjectStats, { color: theme.subText }]}>
                        {item.total_chapters} Chapters
                    </Text>
                </View>
            </TouchableOpacity>
        );
    }, [navigation]);

    return (
        <View style={[styles.outerContainer, { backgroundColor: theme.background }]}>
            {/* Setting translucent to false forces the View to start BELOW the status bar on Android */}
            {/* Setting translucent to false forces the View to start BELOW the status bar on Android */}
            <StatusBar
                barStyle={isDarkMode ? 'light-content' : 'dark-content'}
                backgroundColor={theme.background}
                translucent={false}
            />
            <SafeAreaView style={styles.safeArea}>
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
                        ListEmptyComponent={
                            <View style={styles.emptyContainer}>
                                <Text style={[styles.emptyText, { color: theme.textSecondary }]}>No subjects available.</Text>
                            </View>
                        }
                    />
                )}
            </SafeAreaView>
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
        // Color handled via inline style
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
    },
    listContainer: {
        padding: 16,
        paddingBottom: 100, // Added to lift content above bottom tab bar
    },
    columnWrapper: {
        justifyContent: 'space-between',
    },
    subjectTile: {
        width: '48%',
        borderRadius: 20,
        marginBottom: 16,
        padding: 16,
        elevation: 4,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
    },
    subjectIcon: {
        width: 40,
        height: 40,
        borderRadius: 10,
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 16,
    },
    subjectIconText: {
        fontSize: 18,
        fontWeight: 'bold',
    },
    subjectName: {
        fontSize: 16,
        fontWeight: 'bold',
    },
    subjectStats: {
        fontSize: 12,
        marginTop: 2,
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
    }
});

export default SubjectsScreen;