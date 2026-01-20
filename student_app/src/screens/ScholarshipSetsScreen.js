import React, { useState, useEffect } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    FlatList,
    ActivityIndicator,
    StatusBar,
    SafeAreaView,
    Alert,
    Platform
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { API_URL } from '../api/config';

const ScholarshipSetsScreen = ({ route, navigation }) => {
    // const navigation = useNavigation();
    const { chapterId, chapterName, subjectName } = route.params;
    const [sets, setSets] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchMCQs();
    }, []);

    const fetchMCQs = async () => {
        try {
            // Using existing API to get all MCQs for the chapter
            const response = await fetch(`${API_URL}/get_mcqs.php?chapter_id=${chapterId}`);
            const data = await response.json();

            if (data.status === 'success') {
                // Group MCQs into sets of 25 (or fewer for the last set)
                const allMcqs = data.data;
                const setSize = 25;
                const setsData = [];

                for (let i = 0; i < allMcqs.length; i += setSize) {
                    const chunk = allMcqs.slice(i, i + setSize);
                    setsData.push({
                        id: `set-${i / setSize + 1}`,
                        name: `Practice Set ${i / setSize + 1}`,
                        questions: chunk,
                        questionCount: chunk.length
                    });
                }
                setSets(setsData);
            } else {
                // If no MCQs found, sets remains empty
            }
        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Failed to load practice sets.');
        } finally {
            setLoading(false);
        }
    };

    const startTest = (set) => {
        // Reuse the MyExamTestScreen for the quiz interface
        navigation.navigate('MyExamTest', {
            questions: set.questions,
            totalQuestions: set.questionCount,
            subjectName: `${subjectName} - ${chapterName}`
        });
    };

    const renderItem = ({ item }) => (
        <TouchableOpacity style={styles.card} onPress={() => startTest(item)}>
            <View style={styles.iconContainer}>
                <Ionicons name="document-text-outline" size={28} color="#FF5E62" />
            </View>
            <View style={styles.cardContent}>
                <Text style={styles.cardTitle}>{item.name}</Text>
                <Text style={styles.cardSubtitle}>{item.questionCount} Questions</Text>
            </View>
            <TouchableOpacity style={styles.startButton} onPress={() => startTest(item)}>
                <Text style={styles.startButtonText}>Start</Text>
            </TouchableOpacity>
        </TouchableOpacity>
    );

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />
            <LinearGradient colors={['#8E2DE2', '#4A00E0']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.header}>
                <SafeAreaView style={styles.safeArea}>
                    <View style={styles.headerContent}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Ionicons name="arrow-back" size={24} color="white" />
                        </TouchableOpacity>
                        <View style={{ flex: 1 }}>
                            <Text style={styles.headerTitle}>{chapterName}</Text>
                            <Text style={styles.headerSubtitle} numberOfLines={1}>
                                {sets.length} Practice Sets Available
                            </Text>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <View style={styles.content}>
                {loading ? (
                    <ActivityIndicator size="large" color="#FF5E62" style={{ marginTop: 50 }} />
                ) : (
                    <FlatList
                        data={sets}
                        renderItem={renderItem}
                        keyExtractor={item => item.id}
                        contentContainerStyle={styles.listContent}
                        ListEmptyComponent={
                            <View style={styles.emptyContainer}>
                                <Ionicons name="file-tray-outline" size={64} color="#CBD5E1" />
                                <Text style={styles.emptyText}>No practice sets found for this chapter yet.</Text>
                            </View>
                        }
                    />
                )}
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    header: {
        paddingBottom: 20,
        borderBottomLeftRadius: 30,
        borderBottomRightRadius: 30,
    },
    safeArea: {
        backgroundColor: 'transparent',
    },
    headerContent: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        marginTop: Platform.OS === 'android' ? 40 : 0,
    },
    backButton: {
        padding: 8,
        backgroundColor: 'rgba(255,255,255,0.2)',
        borderRadius: 12,
        marginRight: 15,
    },
    headerTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        color: 'white',
    },
    headerSubtitle: {
        fontSize: 14,
        color: 'rgba(255,255,255,0.9)',
    },
    content: {
        flex: 1,
        marginTop: 10,
    },
    listContent: {
        padding: 20,
    },
    card: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'white',
        marginBottom: 15,
        borderRadius: 16,
        padding: 15,
        elevation: 3,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
    },
    iconContainer: {
        width: 50,
        height: 50,
        borderRadius: 15,
        backgroundColor: '#FFF1F2',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 15,
    },
    cardContent: {
        flex: 1,
    },
    cardTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#1E293B',
        marginBottom: 4,
    },
    cardSubtitle: {
        fontSize: 13,
        color: '#64748B',
    },
    startButton: {
        backgroundColor: '#FF5E62',
        paddingVertical: 8,
        paddingHorizontal: 16,
        borderRadius: 20,
    },
    startButtonText: {
        color: 'white',
        fontWeight: 'bold',
        fontSize: 13,
    },
    emptyContainer: {
        alignItems: 'center',
        marginTop: 50,
        opacity: 0.8
    },
    emptyText: {
        marginTop: 15,
        color: '#64748B',
        fontSize: 16,
        textAlign: 'center'
    }
});

export default ScholarshipSetsScreen;
