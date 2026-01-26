import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Alert, Animated, Image } from 'react-native';
import { useTheme } from '../context/ThemeContext';
import axios from 'axios';
import { API_URL } from '../api/config';
import * as Speech from 'expo-speech';
import { Audio } from 'expo-av';
import HapticManager from '../utils/HapticManager';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';

const EnglishTutorScreen = ({ navigation, route, user }) => {
    const { theme } = useTheme();
    const { mission } = route.params || {};

    // State
    const [messages, setMessages] = useState([]); // Chat history
    const [isRecording, setIsRecording] = useState(false);
    const [loading, setLoading] = useState(false);
    const [hintVisible, setHintVisible] = useState(false);
    const [currentHint, setCurrentHint] = useState("");
    const [tutorMessage, setTutorMessage] = useState("Hello! I am ready.");
    const [fluencyScore, setFluencyScore] = useState(50); // Start at 50
    const [missionComplete, setMissionComplete] = useState(false);

    // Refs
    const recordingRef = useRef(null);
    const silenceTimer = useRef(null);
    const pulseAnim = useRef(new Animated.Value(1)).current;

    // 1. Initial Greeting
    useEffect(() => {
        if (mission) {
            startSession();
        }
    }, [mission]);

    // 2. Silence Detector (Visual Scaffold)
    useEffect(() => {
        if (!isRecording && !loading && !missionComplete) {
            // Reset timer on any state change that isn't idle
            clearTimeout(silenceTimer.current);
            setHintVisible(false);

            // Start 5s timer
            silenceTimer.current = setTimeout(() => {
                showScaffoldHint();
            }, 8000); // 8 seconds silence
        }
        return () => clearTimeout(silenceTimer.current);
    }, [isRecording, loading, tutorMessage]);

    // Animation Loop
    useEffect(() => {
        if (loading || isRecording) {
            Animated.loop(
                Animated.sequence([
                    Animated.timing(pulseAnim, { toValue: 1.1, duration: 800, useNativeDriver: true }),
                    Animated.timing(pulseAnim, { toValue: 1, duration: 800, useNativeDriver: true })
                ])
            ).start();
        } else {
            pulseAnim.setValue(1);
        }
    }, [loading, isRecording]);

    const startSession = () => {
        const greet = `Hello! I am your ${mission.role}. ${mission.student_task}`;
        setTutorMessage(greet);
        speak(greet);
        // Initial hints based on target vocab
        if (mission.target_vocab && mission.target_vocab.length > 0) {
            setCurrentHint(`Try using: "${mission.target_vocab[0]}"`);
        }
    };

    const showScaffoldHint = () => {
        setHintVisible(true);
        // If the backend didn't provide a specific hint last time, use generic
        if (!currentHint) {
            setCurrentHint("Press the mic and say something!");
        }
        // Play subtle sound?
        HapticManager.triggerWarning();
    };

    const speak = (text) => {
        Speech.stop();
        Speech.speak(text, { language: 'en-US', rate: 0.9, pitch: 1.0 });
    };

    const startRecording = async () => {
        try {
            if (recordingRef.current) {
                try { await recordingRef.current.stopAndUnloadAsync(); } catch (e) { }
                recordingRef.current = null;
            }
            await Audio.setAudioModeAsync({ allowsRecordingIOS: true, playsInSilentModeIOS: true });
            // OPTIMIZATION: Use lower quality for faster upload (AAC, 16kHz, Mono)
            const recordingOptions = {
                android: {
                    extension: '.m4a',
                    outputFormat: Audio.AndroidOutputFormat.MPEG_4,
                    audioEncoder: Audio.AndroidAudioEncoder.AAC,
                    sampleRate: 16000,
                    numberOfChannels: 1,
                    bitRate: 32000,
                },
                ios: {
                    extension: '.m4a',
                    audioQuality: Audio.IOSAudioQuality.MEDIUM,
                    sampleRate: 16000,
                    numberOfChannels: 1,
                    bitRate: 32000,
                    linearPCMBitDepth: 16,
                    linearPCMIsBigEndian: false,
                    linearPCMIsFloat: false,
                },
                web: {
                    mimeType: 'audio/webm',
                    bitsPerSecond: 128000,
                },
            };
            const { recording } = await Audio.Recording.createAsync(recordingOptions);
            recordingRef.current = recording;
            setIsRecording(true);
            HapticManager.triggerSuccess();
        } catch (err) {
            Alert.alert('Error', 'Microphone access failed');
        }
    };

    const stopRecording = async () => {
        setIsRecording(false);
        HapticManager.triggerSuccess();
        if (!recordingRef.current) return;

        try {
            await recordingRef.current.stopAndUnloadAsync();
            const uri = recordingRef.current.getURI();
            recordingRef.current = null;
            sendAudio(uri);
        } catch (error) {
            console.log(error);
        }
    };

    const sendAudio = async (uri) => {
        setLoading(true);
        setHintVisible(false); // Hide hint when user acts

        const formData = new FormData();
        formData.append('audio', { uri, type: 'audio/mp4', name: 'recording.m4a' });
        formData.append('level_id', mission.level_id);
        if (user && user.id) {
            formData.append('user_id', user.id);
        }

        try {
            const response = await axios.post(`${API_URL}/ai_english_tutor.php`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            if (response.data.status === 'success') {
                handleAIResponse(response.data.data);
            } else {
                Alert.alert('Error', response.data.message);
            }
        } catch (error) {
            Alert.alert('Network Error', 'Check your connection');
        } finally {
            setLoading(false);
        }
    };

    const handleAIResponse = (data) => {
        setTutorMessage(data.reply);
        speak(data.reply);

        if (data.on_screen_hint) {
            setCurrentHint(data.on_screen_hint);
        }

        if (data.fluency_score) {
            setFluencyScore(data.fluency_score);
        }

        if (data.is_goal_achieved) {
            setMissionComplete(true);
            // Play Coin Sound (Placeholder)
            HapticManager.triggerSuccess();
            // Save progress (backend call could be here but usually done in ai_tutor implicitly or separate endpoint)
            // Ideally we call an endpoint to mark level complete
        }
    };

    const renderFluencyBar = () => (
        <View style={styles.fluencyContainer}>
            <Text style={styles.fluencyLabel}>Fluency Power</Text>
            <View style={styles.barTrack}>
                <View style={[styles.barFill, { width: `${fluencyScore}%`, backgroundColor: fluencyScore > 80 ? '#22c55e' : '#facc15' }]} />
            </View>
        </View>
    );

    return (
        <View style={styles.container}>
            <LinearGradient colors={['#1e1b4b', '#312e81']} style={styles.background} />

            {/* Header */}
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.closeBtn}>
                    <Ionicons name="close" size={30} color="#fff" />
                </TouchableOpacity>
                <View style={styles.missionTag}>
                    <Text style={styles.missionText}>Level {mission?.level_id}: {mission?.role}</Text>
                </View>
            </View>

            {/* Tutor Area */}
            <View style={styles.tutorArea}>
                <Animated.View style={[styles.avatarCircle, { transform: [{ scale: pulseAnim }] }]}>
                    <Image
                        source={{ uri: `https://api.dicebear.com/7.x/avataaars/png?seed=${mission?.role}` }}
                        style={styles.avatarImage}
                    />
                </Animated.View>

                {/* Speech Bubble */}
                <View style={styles.speechBubble}>
                    <Text style={styles.tutorText}>{tutorMessage}</Text>
                </View>

                {/* Visual Scaffold Hint */}
                {hintVisible && (
                    <Animated.View style={styles.hintBox}>
                        <Text style={styles.hintTitle}>💡 Hint</Text>
                        <Text style={styles.hintText}>{currentHint}</Text>
                    </Animated.View>
                )}
            </View>

            {/* Bottom Controls */}
            <View style={styles.bottomArea}>
                {renderFluencyBar()}

                {missionComplete ? (
                    <TouchableOpacity style={styles.completeBtn} onPress={() => navigation.goBack()}>
                        <Text style={styles.completeText}>🎉 Mission Complete!</Text>
                    </TouchableOpacity>
                ) : (
                    <View style={styles.micContainer}>
                        <Text style={styles.micLabel}>{isRecording ? "Listening..." : "Hold to Speak"}</Text>
                        <TouchableOpacity
                            activeOpacity={0.8}
                            onPressIn={startRecording}
                            onPressOut={stopRecording}
                            style={[styles.micButton, isRecording && styles.micActive]}
                        >
                            <Ionicons name={isRecording ? "mic" : "mic-outline"} size={40} color="#fff" />
                        </TouchableOpacity>
                    </View>
                )}
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#1e1b4b' },
    background: { ...StyleSheet.absoluteFillObject },
    header: { paddingTop: 50, paddingHorizontal: 20, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
    missionTag: { backgroundColor: 'rgba(255,255,255,0.2)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20 },
    missionText: { color: '#fff', fontWeight: 'bold' },
    closeBtn: { padding: 5 },

    tutorArea: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 20 },
    avatarCircle: { width: 140, height: 140, borderRadius: 70, backgroundColor: '#fff', borderWidth: 4, borderColor: '#6366f1', marginBottom: 20, overflow: 'hidden' },
    avatarImage: { width: 140, height: 140 },

    speechBubble: { backgroundColor: '#fff', padding: 20, borderRadius: 20, maxWidth: '90%', elevation: 5 },
    tutorText: { fontSize: 18, color: '#1e293b', lineHeight: 26, textAlign: 'center' },

    hintBox: { marginTop: 30, backgroundColor: 'rgba(251, 191, 36, 0.2)', padding: 15, borderRadius: 12, borderWidth: 1, borderColor: '#facc15', alignItems: 'center' },
    hintTitle: { color: '#facc15', fontWeight: 'bold', marginBottom: 5 },
    hintText: { color: '#fff', fontSize: 16 },

    bottomArea: { padding: 30, paddingBottom: 50 },
    fluencyContainer: { marginBottom: 30 },
    fluencyLabel: { color: '#94a3b8', marginBottom: 8, fontSize: 12, textTransform: 'uppercase', letterSpacing: 1 },
    barTrack: { height: 8, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 4, overflow: 'hidden' },
    barFill: { height: '100%', borderRadius: 4 },

    micContainer: { alignItems: 'center', gap: 10 },
    micButton: { width: 90, height: 90, borderRadius: 45, backgroundColor: '#ef4444', justifyContent: 'center', alignItems: 'center', elevation: 10, borderWidth: 4, borderColor: 'rgba(255,255,255,0.2)' },
    micActive: { backgroundColor: '#22c55e', transform: [{ scale: 1.1 }] },
    micLabel: { color: 'rgba(255,255,255,0.7)', fontSize: 14 },

    completeBtn: { backgroundColor: '#22c55e', padding: 20, borderRadius: 16, alignItems: 'center' },
    completeText: { color: '#fff', fontSize: 20, fontWeight: 'bold' }
});

export default EnglishTutorScreen;
