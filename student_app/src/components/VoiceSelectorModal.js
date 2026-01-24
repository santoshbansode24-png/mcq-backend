import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, Modal, TouchableOpacity, FlatList, ActivityIndicator } from 'react-native';
import { getHighQualityIndianVoices, setVoicePreference, getVoicePreference } from '../utils/voiceUtils';
import * as Speech from 'expo-speech';

const VoiceSelectorModal = ({ visible, onClose, onVoiceSelected }) => {
    const [voices, setVoices] = useState([]);
    const [selectedVoiceId, setSelectedVoiceId] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (visible) {
            loadVoices();
        }
    }, [visible]);

    const loadVoices = async () => {
        setLoading(true);
        try {
            const availableVoices = await getHighQualityIndianVoices();
            setVoices(availableVoices);
            const savedPref = await getVoicePreference();
            if (savedPref) {
                setSelectedVoiceId(savedPref);
            } else if (availableVoices.length > 0) {
                // Default to the first one if no preference which is likely the best Marathi/Hindi one due to sorting
                // But wait, the user might want a "Default" option? 
                // For now, let's just see if they have one selected.
            }
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleSelect = async (voiceId) => {
        setSelectedVoiceId(voiceId);
        await setVoicePreference(voiceId);
        if (onVoiceSelected) onVoiceSelected(voiceId);

        // Optional: Speak a sample
        Speech.stop();
        Speech.speak("Namaste! Check out my voice.", {
            voice: voiceId,
            language: 'en-IN'
        });
    };

    const renderItem = ({ item }) => {
        const isSelected = item.identifier === selectedVoiceId;
        return (
            <TouchableOpacity
                style={[styles.voiceItem, isSelected && styles.selectedItem]}
                onPress={() => handleSelect(item.identifier)}
            >
                <View>
                    <Text style={[styles.voiceName, isSelected && styles.selectedText]}>{item.name}</Text>
                    <Text style={[styles.voiceLang, isSelected && styles.selectedText]}>{item.language} ({item.identifier})</Text>
                </View>
                {isSelected && <Text style={styles.checkMark}>✓</Text>}
            </TouchableOpacity>
        );
    };

    return (
        <Modal
            animationType="slide"
            transparent={true}
            visible={visible}
            onRequestClose={onClose}
        >
            <View style={styles.modalOverlay}>
                <View style={styles.modalContent}>
                    <View style={styles.header}>
                        <Text style={styles.title}>Select Voice 🗣️</Text>
                        <TouchableOpacity onPress={onClose} style={styles.closeButton}>
                            <Text style={styles.closeText}>✕</Text>
                        </TouchableOpacity>
                    </View>

                    <Text style={styles.subtitle}>Choose your preferred voice for reading content. Marathi & Indian accents are prioritized.</Text>

                    {loading ? (
                        <ActivityIndicator size="large" color="#4f46e5" style={{ marginTop: 20 }} />
                    ) : (
                        <FlatList
                            data={voices}
                            renderItem={renderItem}
                            keyExtractor={(item) => item.identifier}
                            contentContainerStyle={styles.list}
                            ListEmptyComponent={<Text style={styles.emptyText}>No suitable voices found.</Text>}
                        />
                    )}

                    <TouchableOpacity style={styles.doneButton} onPress={onClose}>
                        <Text style={styles.doneButtonText}>Done</Text>
                    </TouchableOpacity>
                </View>
            </View>
        </Modal>
    );
};

const styles = StyleSheet.create({
    modalOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'flex-end',
    },
    modalContent: {
        backgroundColor: 'white',
        borderTopLeftRadius: 20,
        borderTopRightRadius: 20,
        padding: 20,
        maxHeight: '80%',
    },
    header: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 10,
    },
    title: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#1e293b',
    },
    subtitle: {
        fontSize: 14,
        color: '#64748b',
        marginBottom: 15,
    },
    closeButton: {
        padding: 5,
    },
    closeText: {
        fontSize: 20,
        color: '#64748b',
    },
    list: {
        paddingBottom: 20,
    },
    voiceItem: {
        backgroundColor: '#f8fafc',
        padding: 15,
        borderRadius: 12,
        marginBottom: 10,
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    selectedItem: {
        backgroundColor: '#e0e7ff',
        borderColor: '#4f46e5',
    },
    voiceName: {
        fontSize: 16,
        fontWeight: '600',
        color: '#334155',
    },
    voiceLang: {
        fontSize: 12,
        color: '#94a3b8',
        marginTop: 2,
    },
    selectedText: {
        color: '#4f46e5',
    },
    checkMark: {
        fontSize: 18,
        color: '#4f46e5',
        fontWeight: 'bold',
    },
    emptyText: {
        textAlign: 'center',
        color: '#94a3b8',
        marginTop: 20,
    },
    doneButton: {
        backgroundColor: '#4f46e5',
        padding: 15,
        borderRadius: 12,
        alignItems: 'center',
        marginTop: 10,
    },
    doneButtonText: {
        color: 'white',
        fontSize: 16,
        fontWeight: 'bold',
    },
});

export default VoiceSelectorModal;
