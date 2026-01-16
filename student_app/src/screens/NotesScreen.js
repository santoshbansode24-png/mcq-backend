import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Alert, Linking } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute } from '@react-navigation/native';
import { API_URL } from '../api/config';

const NotesScreen = () => {
    const navigation = useNavigation();
    const route = useRoute();
    const { chapterId, chapterName, subjectName } = route.params;

    const [loading, setLoading] = useState(true);
    const [notes, setNotes] = useState([]);

    useEffect(() => {
        fetchNotes();
    }, []);

    const fetchNotes = async () => {
        try {
            const response = await fetch(`${API_URL}/get_notes.php?chapter_id=${chapterId}`);
            const data = await response.json();

            if (data.status === 'success') {
                setNotes(data.data);
            } else {
                // Only show alert if it's an actual error, 'No notes found' is fine to just show empty state
                if (data.message !== 'No notes found for this chapter') {
                    Alert.alert('Error', data.message);
                }
            }
        } catch (error) {
            console.error('Error fetching notes:', error);
            Alert.alert('Error', 'Failed to load notes. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const openNote = async (note) => {
        if (note.note_type === 'pdf') {
            if (note.file_url) {
                const supported = await Linking.canOpenURL(note.file_url);
                if (supported) {
                    await Linking.openURL(note.file_url);
                } else {
                    Alert.alert("Error", "Cannot allow this URL: " + note.file_url);
                }
            } else {
                Alert.alert("Error", "PDF URL is missing");
            }
        } else {
            // Handle HTML type notes (future expansion, maybe navigate to a reader screen)
            Alert.alert("Info", "HTML Note viewing is not yet implemented.");
        }
    };

    const renderItem = ({ item }) => (
        <TouchableOpacity style={styles.noteCard} onPress={() => openNote(item)}>
            <View style={styles.iconContainer}>
                <Ionicons name="document-text" size={32} color="#4f46e5" />
            </View>
            <View style={styles.noteInfo}>
                <Text style={styles.noteTitle}>{item.title}</Text>
                <Text style={styles.noteType}>{item.note_type?.toUpperCase() || 'PDF'}</Text>
            </View>
            <TouchableOpacity
                style={styles.downloadButton}
                onPress={() => openNote(item)}
            >
                <Ionicons name="cloud-download-outline" size={24} color="#4f46e5" />
            </TouchableOpacity>
        </TouchableOpacity>
    );

    return (
        <View style={styles.container}>
            {/* Header */}
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                    <Ionicons name="arrow-back" size={24} color="#333" />
                </TouchableOpacity>
                <View>
                    <Text style={styles.headerTitle}>{chapterName}</Text>
                    <Text style={styles.headerSubtitle}>{subjectName} Notes</Text>
                </View>
            </View>

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
});

export default NotesScreen;
