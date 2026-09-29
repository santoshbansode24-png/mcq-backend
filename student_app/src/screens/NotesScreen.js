import React, { useState, useEffect, useCallback } from 'react';
import { useFocusEffect } from '@react-navigation/native';
// Fixed syntax error
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Alert, Linking, RefreshControl } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { fetchNotes as fetchNotesApi } from '../api/content';
import { BASE_URL, API_URL } from '../api/config';
import { getCachedFile } from '../utils/downloadUtils';

const NotesScreen = ({ navigation, route }) => {
    const { chapterId, chapterName } = route.params || {};
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [notes, setNotes] = useState([]);
    const [downloading, setDownloading] = useState(false);
    const [downloadProgress, setDownloadProgress] = useState(0);

    // Replace useEffect with useFocusEffect for auto-update
    useFocusEffect(
        React.useCallback(() => {
            fetchNotes();
        }, [])
    );

    const fetchNotes = async (isRefreshing = false) => {
        if (!isRefreshing && notes.length === 0) setLoading(true);
        try {
            const response = await fetchNotesApi(chapterId, isRefreshing);

            if (response && response.status === 'success') {
                setNotes(response.data || []);
            } else if (Array.isArray(response)) {
                setNotes(response);
            } else if (response?.message && response.message !== 'No notes found for this chapter') {
                Alert.alert('Notice', response.message);
            }
        } catch (error) {
            console.error('Error fetching notes:', error);
            // Don't show alert if notes are already loaded from cache
            if (notes.length === 0) {
                Alert.alert('Notice', 'Unable to fetch notes. Please check connection.');
            }
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const onRefresh = React.useCallback(() => {
        setRefreshing(true);
        fetchNotes(true);
    }, []);

    const openNote = async (item) => {
        const rawPath = item.file_url || item.file_path;

        if (!rawPath) {
            Alert.alert('Error', 'Note link is missing.');
            return;
        }

        // Handle HTML notes
        if (item.note_type === 'html') {
            navigation.navigate('HTMLViewer', {
                content: item.content,
                title: item.title
            });
            return;
        }

        // Handle PDF navigation with permanent cache
        try {
            setDownloading(true);
            setDownloadProgress(0);
            let remoteUrl = rawPath;
            if (!rawPath.startsWith('http')) {
                remoteUrl = `${BASE_URL}/${rawPath}`;
            }

            const localUri = await getCachedFile(
                remoteUrl,
                item.title,
                (progress) => setDownloadProgress(progress)
            );
            setDownloading(false);
            if (localUri) {
                navigation.navigate('PDFViewer', {
                    url: localUri,
                    title: item.title
                });
            }
        } catch (error) {
            console.error('Open Note Error:', error);
            setDownloading(false);
        }
    };

    const renderItem = ({ item }) => (
        <TouchableOpacity style={styles.noteCard} onPress={() => openNote(item)}>
            <View style={styles.iconContainer}>
                <Ionicons name="document-text" size={24} color="#4A90E2" />
            </View>
            <View style={styles.noteInfo}>
                <Text style={styles.noteTitle}>{item.title}</Text>
                <Text style={styles.noteType}>{item.subject_name} • {item.chapter_name}</Text>
            </View>
            <TouchableOpacity style={styles.downloadButton} onPress={() => openNote(item)}>
                <Ionicons name="arrow-forward-circle" size={28} color="#ccc" />
            </TouchableOpacity>
        </TouchableOpacity>
    );

    return (
        <View style={styles.container}>
            {/* ... Header */}

            {downloading && (
                <View style={styles.loadingOverlay}>
                    <View style={styles.loadingBox}>
                        <ActivityIndicator size="large" color="#4A90E2" />
                        <Text style={styles.loadingText}>Downloading Note...</Text>
                        <Text style={styles.loadingText}>{Math.round(downloadProgress * 100)}%</Text>
                    </View>
                </View>
            )}

            {loading ? (
                <View style={styles.center}>
                    <ActivityIndicator size="large" color="#4A90E2" />
                </View>
            ) : (
                <FlatList
                    data={notes}
                    renderItem={renderItem}
                    keyExtractor={(item) => item.note_id.toString()}
                    contentContainerStyle={styles.listContainer}
                    refreshControl={
                        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={['#4f46e5']} />
                    }
                    ListEmptyComponent={
                        <View style={styles.emptyContainer}>
                            <Ionicons name="folder-open-outline" size={64} color="#ccc" />
                            <Text style={styles.emptyText}>No notes available for this chapter.</Text>
                        </View>
                    }
                />
            )}
        </View>
    );
};


const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#F5F7FA',
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 20,
        backgroundColor: '#fff',
        elevation: 4,
        paddingTop: 50, // For status bar
    },
    backButton: {
        marginRight: 15,
    },
    headerTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#333',
    },
    headerSubtitle: {
        fontSize: 14,
        color: '#666',
    },
    center: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    listContainer: {
        padding: 15,
    },
    noteCard: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#fff',
        padding: 15,
        borderRadius: 12,
        marginBottom: 12,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.1,
        shadowRadius: 2,
    },
    iconContainer: {
        width: 50,
        height: 50,
        borderRadius: 25,
        backgroundColor: '#F0F7FF',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 15,
    },
    noteInfo: {
        flex: 1,
    },
    noteTitle: {
        fontSize: 16,
        fontWeight: '600',
        color: '#333',
        marginBottom: 4,
    },
    noteType: {
        fontSize: 12,
        color: '#888',
        fontWeight: '500',
    },
    emptyText: {
        marginTop: 20,
        fontSize: 16,
        color: '#999',
    },
    downloadButton: {
        padding: 8,
    },
    loadingOverlay: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'center',
        alignItems: 'center',
        zIndex: 1000,
    },
    loadingBox: {
        backgroundColor: 'white',
        padding: 20,
        borderRadius: 10,
        alignItems: 'center',
        elevation: 5,
    },
    loadingText: {
        marginTop: 10,
        fontSize: 16,
        color: '#333',
    },
});

export default NotesScreen;
