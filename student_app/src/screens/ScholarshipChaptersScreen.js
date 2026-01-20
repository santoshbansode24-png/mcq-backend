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
    Platform
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { fetchChapters } from '../api/chapters';

const ScholarshipChaptersScreen = ({ route, navigation }) => {
    const { subjectId, subjectName } = route.params;
    const [chapters, setChapters] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadChapters();
    }, []);

    const loadChapters = async () => {
        try {
            const response = await fetchChapters(subjectId);
            if (response.status === 'success') {
                setChapters(response.data);
            }
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const renderItem = ({ item, index }) => {
        return (
            <TouchableOpacity
                style={styles.card}
                onPress={() => navigation.navigate('ScholarshipSets', {
                    chapterId: item.chapter_id,
                    chapterName: item.chapter_name,
                    subjectName: subjectName
                })}
            >
                <View style={styles.cardContent}>
                    <View style={styles.numberBox}>
                        <Text style={styles.numberText}>{index + 1}</Text>
                    </View>
                    <View style={styles.textContent}>
                        <Text style={styles.cardTitle}>{item.chapter_name}</Text>
                        <Text style={styles.cardSubtitle}>{item.total_mcqs || 0} Questions</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={24} color="#CBD5E1" />
                </View>
            </TouchableOpacity>
        );
    };

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />
            <LinearGradient colors={['#8E2DE2', '#4A00E0']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.header}>
                <SafeAreaView style={styles.safeArea}>
                    <View style={styles.headerContent}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Ionicons name="arrow-back" size={24} color="white" />
                        </TouchableOpacity>
                        <View>
                            <Text style={styles.headerTitle}>{subjectName}</Text>
                            <Text style={styles.headerSubtitle}>Select a Chapter</Text>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <View style={styles.content}>
                {loading ? (
                    <ActivityIndicator size="large" color="#FF5E62" style={{ marginTop: 50 }} />
                ) : (
                    <FlatList
                        data={chapters}
                        renderItem={renderItem}
                        keyExtractor={item => item.chapter_id.toString()}
                        contentContainerStyle={styles.listContent}
                        ListEmptyComponent={
                            <View style={styles.emptyContainer}>
                                <Text style={styles.emptyText}>No chapters available yet.</Text>
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
        backgroundColor: 'white',
        marginBottom: 15,
        borderRadius: 16,
        padding: 15,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
        borderWidth: 1,
        borderColor: '#E2E8F0',
    },
    cardContent: {
        flexDirection: 'row',
        alignItems: 'center',
    },
    numberBox: {
        width: 40,
        height: 40,
        borderRadius: 12,
        backgroundColor: '#FFF1F2',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 15,
    },
    numberText: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#FF5E62',
    },
    textContent: {
        flex: 1,
    },
    cardTitle: {
        fontSize: 16,
        fontWeight: '600',
        color: '#1E293B',
        marginBottom: 4,
    },
    cardSubtitle: {
        fontSize: 13,
        color: '#64748B',
    },
    emptyContainer: {
        alignItems: 'center',
        marginTop: 50,
    },
    emptyText: {
        color: '#64748B',
        fontSize: 16,
    }
});

export default ScholarshipChaptersScreen;
