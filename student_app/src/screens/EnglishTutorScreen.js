import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity, ScrollView, ActivityIndicator, KeyboardAvoidingView, Platform, Alert, Animated } from 'react-native';
import { useTheme } from '../context/ThemeContext';
import axios from 'axios';
import { API_URL } from '../api/config';
import * as Speech from 'expo-speech';
import { Audio } from 'expo-av';
import HapticManager from '../utils/HapticManager';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';

const EnglishTutorScreen = ({ navigation }) => {
    const { theme } = useTheme();
    const [callActive, setCallActive] = useState(false);
    const [scenario, setScenario] = useState(null);
    const [fluencyScore, setFluencyScore] = useState(100);
    const [messages, setMessages] = useState([]);
    const [loading, setLoading] = useState(false);
    const [isRecording, setIsRecording] = useState(false);
    const [lastAiMessage, setLastAiMessage] = useState("Hi! Pick a scenario to start speaking.");

    // Refs for stable access
    const recordingRef = useRef(null);
    const pulseAnim = useRef(new Animated.Value(1)).current;

    const scenarios = [
        { id: 'Casual Chat', icon: 'chatbubbles', color: '#8b5cf6', title: 'Casual Chat', prompt: "Let's just chat!" },
        { id: 'Job Interview', icon: 'briefcase', color: '#ef4444', title: 'Job Interview', prompt: "Prepare for your dream job." },
        { id: 'Ordering Coffee', icon: 'cafe', color: '#f59e0b', title: 'Coffee Shop', prompt: "Order a latte like a pro." },
        { id: 'Travel', icon: 'airplane', color: '#10b981', title: 'Travel', prompt: "Airport and Hotel checks." },
        { id: 'First Date', icon: 'heart', color: '#ec4899', title: 'First Date', prompt: "Make a good impression." },
    ];

    useEffect(() => {
        (async () => {
            const { status } = await Audio.requestPermissionsAsync();
            if (status !== 'granted') {
                Alert.alert('Permission denied', 'You need to enable microphone access to use this feature.');
            }
        })();

        // Cleanup on unmount
        return () => {
            if (recordingRef.current) {
                recordingRef.current.stopAndUnloadAsync();
            }
        };
    }, []);

    useEffect(() => {
        if (loading || isRecording) {
            Animated.loop(
                Animated.sequence([
                    Animated.timing(pulseAnim, { toValue: 1.2, duration: 800, useNativeDriver: true }),
                    Animated.timing(pulseAnim, { toValue: 1, duration: 800, useNativeDriver: true })
                ])
            ).start();
        } else {
            pulseAnim.setValue(1);
        }
    }, [loading, isRecording]);

    const startCall = (selectedScenario) => {
        setScenario(selectedScenario);
        setCallActive(true);
        setMessages([]);
        setFluencyScore(100);
        const initialMsg = `Hi! I'm ready for our ${selectedScenario.title}. You start!`;
        setLastAiMessage(initialMsg);
        speak(initialMsg);
    };

    const endCall = () => {
        setCallActive(false);
        setScenario(null);
        Speech.stop();
    };

    const speak = (text) => {
        Speech.stop();
        Speech.speak(text, { language: 'en-US', pitch: 1.0, rate: 0.9 });
    };

    const startRecording = async () => {
        try {
            // Safety Check: If there's an active recording, stop it first
            if (recordingRef.current) {
                try { await recordingRef.current.stopAndUnloadAsync(); } catch (e) { }
                recordingRef.current = null;
            }

            await Audio.setAudioModeAsync({ allowsRecordingIOS: true, playsInSilentModeIOS: true });
            const { recording: newRecording } = await Audio.Recording.createAsync(Audio.RecordingOptionsPresets.HIGH_QUALITY);

            recordingRef.current = newRecording;
            setIsRecording(true);
            HapticManager.triggerSuccess();
        } catch (err) {
            console.error('Recording Error:', err);
            Alert.alert('Microphone Error', 'Could not access microphone. ' + (err.message || 'Check permissions.'));
        }
    };

    const stopRecording = async () => {
        setIsRecording(false);
        HapticManager.triggerSuccess();

        const rec = recordingRef.current;
        if (!rec) return;

        try {
            await rec.stopAndUnloadAsync();
            const uri = rec.getURI();
            recordingRef.current = null;
            sendAudioMessage(uri);
        } catch (error) {
            console.log("Stop recording error (ignoring):", error);
            recordingRef.current = null; // Ensure we clean up reference
        }
    };

    const sendAudioMessage = async (uri) => {
        setLoading(true);
        const formData = new FormData();
        // Use 'audio/mp4' which is more standard/acceptable for m4a files in many APIs
        formData.append('audio', { uri, type: 'audio/mp4', name: 'recording.m4a' });
        formData.append('scenario', scenario.id);

        try {
            console.log("Sending audio to:", `${API_URL}/ai_english_tutor.php`);
            const response = await axios.post(`${API_URL}/ai_english_tutor.php`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            console.log("AI Response:", response.data);
            handleAIResponse(response.data);
        } catch (error) {
            console.error("Network Error:", error);
            Alert.alert('Connection Error', 'Please check internet or server logs.');
        } finally {
            setLoading(false);
        }
    };

    const handleAIResponse = (data) => {
        if (data.status === 'success') {
            const aiData = data.data;
            setLastAiMessage(aiData.reply);
            if (aiData.fluency_score) setFluencyScore(aiData.fluency_score);
            speak(aiData.reply);

            if (aiData.has_error) {
                // Optional: Show subtle correction toast or just rely on voice feedback
            }
        } else {
            console.log("Backend returned error:", data);
            Alert.alert('AI Error', data.message || "Failed to process audio.");
        }
    };

    // --- RENDER HELPERS ---

    const renderMenu = () => (
        <ScrollView style={styles.menuContainer} contentContainerStyle={{ padding: 20 }}>
            <Text style={styles.menuTitle}>Choose a Scenario</Text>
            <Text style={styles.menuSubtitle}>What do you want to practice today?</Text>

            <View style={styles.grid}>
                {scenarios.map(item => (
                    <TouchableOpacity key={item.id} style={[styles.card, { backgroundColor: item.color }]} onPress={() => startCall(item)}>
                        <View style={styles.iconCircle}>
                            <Ionicons name={item.icon} size={32} color={item.color} />
                        </View>
                        <Text style={styles.cardTitle}>{item.title}</Text>
                        <Text style={styles.cardPrompt}>{item.prompt}</Text>
                    </TouchableOpacity>
                ))}
            </View>
        </ScrollView>
    );

    const renderCallInterface = () => (
        <View style={styles.callContainer}>
            <LinearGradient colors={['#1e1b4b', '#4c1d95', '#1e1b4b']} style={StyleSheet.absoluteFill} />

            {/* Header / Fluency Meter */}
            <View style={styles.topBar}>
                <TouchableOpacity onPress={endCall} style={styles.smallButton}>
                    <Ionicons name="chevron-down" size={30} color="#fff" />
                </TouchableOpacity>
                <View style={styles.fluencyBadge}>
                    <Text style={styles.fluencyLabel}>Fluency Score</Text>
                    <Text style={[styles.fluencyValue, { color: fluencyScore > 80 ? '#4ade80' : '#fbbf24' }]}>{fluencyScore}%</Text>
                </View>
                <TouchableOpacity style={styles.smallButton}>
                    <Ionicons name="settings-outline" size={24} color="#fff" />
                </TouchableOpacity>
            </View>

            {/* Avatar */}
            <View style={styles.avatarContainer}>
                <Animated.View style={[styles.avatarPulse, { transform: [{ scale: pulseAnim }] }]} />
                <View style={styles.avatar}>
                    <Ionicons name={scenario?.icon} size={60} color="#fff" />
                </View>
                <Text style={styles.personaName}>{scenario?.title} Bot</Text>
                <Text style={styles.statusText}>{loading ? "Thinking..." : isRecording ? "Listening..." : "Speaking..."}</Text>
            </View>

            {/* Subtitles */}
            <View style={styles.subtitleBox}>
                <Text style={styles.subtitleText}>"{lastAiMessage}"</Text>
            </View>

            {/* Controls */}
            <View style={styles.controls}>
                <TouchableOpacity style={[styles.controlBtn, { backgroundColor: '#ef4444' }]} onPress={endCall}>
                    <Ionicons name="call" size={30} color="#fff" />
                </TouchableOpacity>

                <TouchableOpacity
                    style={[styles.mainMicBtn, isRecording && styles.micActive]}
                    onPressIn={startRecording}
                    onPressOut={stopRecording}
                >
                    <Ionicons name={isRecording ? "mic" : "mic-outline"} size={40} color="#fff" />
                </TouchableOpacity>

                <TouchableOpacity style={[styles.controlBtn, { backgroundColor: 'rgba(255,255,255,0.2)' }]}>
                    <Ionicons name="volume-high" size={24} color="#fff" />
                </TouchableOpacity>
            </View>
        </View>
    );

    return (
        <View style={styles.container}>
            {callActive ? renderCallInterface() : (
                <>
                    <View style={[styles.header, { backgroundColor: theme.card }]}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Text style={[styles.backButtonText, { color: theme.text }]}>←</Text>
                        </TouchableOpacity>
                        <Text style={[styles.headerTitle, { color: theme.text }]}>English Speaking 🇬🇧</Text>
                    </View>
                    {renderMenu()}
                </>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#f8fafc' },
    header: { flexDirection: 'row', alignItems: 'center', paddingTop: 50, paddingBottom: 15, paddingHorizontal: 20, elevation: 4, backgroundColor: '#fff' },
    backButton: { marginRight: 15 },
    backButtonText: { fontSize: 24, fontWeight: 'bold' },
    headerTitle: { fontSize: 20, fontWeight: 'bold' },

    // Menu
    menuContainer: { flex: 1 },
    menuTitle: { fontSize: 28, fontWeight: '800', color: '#1e293b', marginBottom: 5 },
    menuSubtitle: { fontSize: 16, color: '#64748b', marginBottom: 25 },
    grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 15 },
    card: { width: '47%', padding: 20, borderRadius: 24, marginBottom: 15, minHeight: 180, justifyContent: 'space-between' },
    iconCircle: { width: 50, height: 50, borderRadius: 15, backgroundColor: 'rgba(255,255,255,0.9)', justifyContent: 'center', alignItems: 'center' },
    cardTitle: { fontSize: 18, fontWeight: 'bold', color: '#fff', marginTop: 15 },
    cardPrompt: { fontSize: 12, color: 'rgba(255,255,255,0.8)', marginTop: 5 },

    // Call Interface
    callContainer: { flex: 1, justifyContent: 'space-between', paddingBottom: 50 },
    topBar: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingTop: 60, paddingHorizontal: 20 },
    smallButton: { padding: 10 },
    fluencyBadge: { alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.3)', paddingHorizontal: 15, paddingVertical: 5, borderRadius: 20 },
    fluencyLabel: { color: '#9ca3af', fontSize: 10, textTransform: 'uppercase' },
    fluencyValue: { fontSize: 18, fontWeight: 'bold' },

    avatarContainer: { alignItems: 'center', justifyContent: 'center', flex: 1 },
    avatar: { width: 120, height: 120, borderRadius: 60, backgroundColor: 'rgba(255,255,255,0.1)', justifyContent: 'center', alignItems: 'center', borderWidth: 2, borderColor: 'rgba(255,255,255,0.2)', index: 2 },
    avatarPulse: { position: 'absolute', width: 120, height: 120, borderRadius: 60, backgroundColor: 'rgba(255,255,255,0.1)' },
    personaName: { color: '#fff', fontSize: 24, fontWeight: 'bold', marginTop: 20 },
    statusText: { color: '#9ca3af', marginTop: 5 },

    subtitleBox: { padding: 20, alignItems: 'center' },
    subtitleText: { color: '#fff', fontSize: 18, textAlign: 'center', lineHeight: 26, fontWeight: '500' },

    controls: { flexDirection: 'row', justifyContent: 'space-evenly', alignItems: 'center', paddingHorizontal: 30 },
    controlBtn: { width: 60, height: 60, borderRadius: 30, justifyContent: 'center', alignItems: 'center' },
    mainMicBtn: { width: 80, height: 80, borderRadius: 40, backgroundColor: '#fff', justifyContent: 'center', alignItems: 'center', elevation: 10 },
    micActive: { backgroundColor: '#fbbf24', transform: [{ scale: 1.1 }] }
});

export default EnglishTutorScreen;
