import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity, ScrollView, ActivityIndicator, KeyboardAvoidingView, Platform, Alert, Animated } from 'react-native';
import { useTheme } from '../context/ThemeContext';
import axios from 'axios';
import { API_URL } from '../api/config';
import * as Speech from 'expo-speech';
import { Audio } from 'expo-av';
import HapticManager from '../utils/HapticManager';

const EnglishTutorScreen = ({ navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const [messages, setMessages] = useState([
        { id: 1, text: "Hi there! I'm your English Tutor. Let's practice speaking. How was your day?", sender: 'ai', correction: null }
    ]);
    const [inputText, setInputText] = useState('');
    const [loading, setLoading] = useState(false);
    const [recording, setRecording] = useState(null);
    const [isRecording, setIsRecording] = useState(false);

    const scrollViewRef = useRef();
    const scaleAnim = useRef(new Animated.Value(1)).current;

    useEffect(() => {
        // Speak the initial greeting
        speak("Hi there! I'm your English Tutor. Let's practice speaking. How was your day?");

        // Request permissions
        (async () => {
            const { status } = await Audio.requestPermissionsAsync();
            if (status !== 'granted') {
                Alert.alert('Permission needed', 'Please grant microphone permission to use the voice feature.');
            }
        })();

        return () => {
            if (recording) {
                recording.stopAndUnloadAsync();
            }
        };
    }, []);

    const speak = (text) => {
        Speech.stop();
        Speech.speak(text, {
            language: 'en-US',
            pitch: 1.0,
            rate: 0.9,
        });
    };

    const startRecording = async () => {
        try {
            await Audio.setAudioModeAsync({
                allowsRecordingIOS: true,
                playsInSilentModeIOS: true,
            });

            const { recording } = await Audio.Recording.createAsync(
                Audio.RecordingOptionsPresets.HIGH_QUALITY
            );
            setRecording(recording);
            setIsRecording(true);
            HapticManager.triggerSuccess();

            // Pulse animation
            Animated.loop(
                Animated.sequence([
                    Animated.timing(scaleAnim, { toValue: 1.2, duration: 500, useNativeDriver: true }),
                    Animated.timing(scaleAnim, { toValue: 1, duration: 500, useNativeDriver: true })
                ])
            ).start();

        } catch (err) {
            console.error('Failed to start recording', err);
            Alert.alert('Error', 'Failed to start recording.');
        }
    };

    const stopRecording = async () => {
        if (!recording) return;

        setIsRecording(false);
        scaleAnim.stopAnimation();
        scaleAnim.setValue(1);
        HapticManager.triggerSuccess();

        try {
            await recording.stopAndUnloadAsync();
            const uri = recording.getURI();
            setRecording(null);
            sendAudioMessage(uri);
        } catch (error) {
            console.error('Failed to stop recording', error);
        }
    };

    const sendAudioMessage = async (uri) => {
        setLoading(true);

        // Optimistically add a "Processing audio..." message
        const tempId = Date.now();
        setMessages(prev => [...prev, { id: tempId, text: "🎤 (Processing audio...)", sender: 'user', correction: null }]);

        const formData = new FormData();
        formData.append('audio', {
            uri: uri,
            type: 'audio/m4a', // Adjust based on recording preset if needed
            name: 'recording.m4a',
        });

        try {
            const response = await axios.post(`${API_URL}/ai_english_tutor.php`, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            handleAIResponse(response.data, tempId);
        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Network error. Please check your connection.');
            setMessages(prev => prev.filter(msg => msg.id !== tempId)); // Remove temp message
        } finally {
            setLoading(false);
        }
    };

    const sendTextMessage = async () => {
        if (!inputText.trim()) return;

        const userMsg = { id: Date.now(), text: inputText, sender: 'user', correction: null };
        setMessages(prev => [...prev, userMsg]);
        setInputText('');
        setLoading(true);

        try {
            const response = await axios.post(`${API_URL}/ai_english_tutor.php`, {
                message: userMsg.text
            });

            handleAIResponse(response.data, userMsg.id);
        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Network error. Please check your connection.');
        } finally {
            setLoading(false);
        }
    };

    const handleAIResponse = (data, userMsgId) => {
        if (data.status === 'success') {
            const aiData = data.data;

            // If it was audio, update the user message text with transcription
            if (aiData.transcription) {
                setMessages(prev => prev.map(msg =>
                    msg.id === userMsgId
                        ? { ...msg, text: "🎤 " + aiData.transcription }
                        : msg
                ));
            }

            // If there was an error, update the user's last message with correction info
            if (aiData.has_error) {
                setMessages(prev => prev.map(msg =>
                    msg.id === userMsgId
                        ? { ...msg, correction: aiData.correction, feedback: aiData.feedback }
                        : msg
                ));
                HapticManager.triggerError();
            } else {
                HapticManager.triggerSuccess();
            }

            // Add AI response
            const aiMsg = {
                id: Date.now() + 1,
                text: aiData.reply,
                sender: 'ai',
                correction: null
            };
            setMessages(prev => [...prev, aiMsg]);
            speak(aiData.reply);

        } else {
            Alert.alert('Error', 'AI is having trouble understanding. Please try again.');
            // Remove temp message if it failed
            if (!data.transcription) {
                setMessages(prev => prev.filter(msg => msg.id !== userMsgId));
            }
        }
    };

    return (
        <KeyboardAvoidingView
            style={[styles.container, { backgroundColor: theme.background }]}
            behavior={Platform.OS === 'ios' ? 'padding' : undefined}
            keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}
        >
            <View style={[styles.header, { backgroundColor: theme.card }]}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                    <Text style={[styles.backButtonText, { color: theme.text }]}>←</Text>
                </TouchableOpacity>
                <Text style={[styles.headerTitle, { color: theme.text }]}>English Tutor 🇬🇧</Text>
            </View>

            <ScrollView
                style={styles.chatContainer}
                ref={scrollViewRef}
                onContentSizeChange={() => scrollViewRef.current.scrollToEnd({ animated: true })}
                contentContainerStyle={{ paddingBottom: 20 }}
            >
                {messages.map((msg) => (
                    <View key={msg.id} style={{ alignItems: msg.sender === 'user' ? 'flex-end' : 'flex-start' }}>

                        {/* Message Bubble */}
                        <View style={[
                            styles.bubble,
                            msg.sender === 'user'
                                ? [styles.userBubble, { backgroundColor: theme.primary }]
                                : [styles.aiBubble, { backgroundColor: theme.card }]
                        ]}>
                            <Text style={[
                                styles.messageText,
                                { color: msg.sender === 'user' ? 'white' : theme.text }
                            ]}>
                                {msg.text}
                            </Text>
                        </View>

                        {/* Correction Display (Only for User messages with errors) */}
                        {msg.correction && (
                            <View style={[styles.correctionBox, { backgroundColor: '#fee2e2', borderColor: '#ef4444' }]}>
                                <Text style={styles.correctionTitle}>⚠️ Correction:</Text>
                                <Text style={styles.correctionText}>"{msg.correction}"</Text>
                                <Text style={styles.feedbackText}>{msg.feedback}</Text>
                            </View>
                        )}

                    </View>
                ))}
                {loading && (
                    <View style={styles.loadingContainer}>
                        <ActivityIndicator size="small" color={theme.primary} />
                        <Text style={[styles.loadingText, { color: theme.textSecondary }]}>Tutor is thinking...</Text>
                    </View>
                )}
            </ScrollView>

            <View style={[styles.inputContainer, { backgroundColor: theme.card, borderTopColor: theme.border }]}>
                {/* Microphone Button */}
                <TouchableOpacity
                    style={[styles.micButton, isRecording && styles.micButtonActive]}
                    onPressIn={startRecording}
                    onPressOut={stopRecording}
                    disabled={loading}
                >
                    <Animated.Text style={[styles.micIcon, { transform: [{ scale: scaleAnim }] }]}>
                        {isRecording ? '🔴' : '🎤'}
                    </Animated.Text>
                </TouchableOpacity>

                <TextInput
                    style={[styles.input, { backgroundColor: theme.background, color: theme.text }]}
                    placeholder="Type a message..."
                    placeholderTextColor={theme.textSecondary}
                    value={inputText}
                    onChangeText={setInputText}
                    multiline
                />
                <TouchableOpacity
                    style={[styles.sendButton, { backgroundColor: theme.primary, opacity: inputText.trim() ? 1 : 0.5 }]}
                    onPress={sendTextMessage}
                    disabled={!inputText.trim() || loading}
                >
                    <Text style={styles.sendButtonText}>Send</Text>
                </TouchableOpacity>
            </View>
        </KeyboardAvoidingView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingTop: 50,
        paddingBottom: 15,
        paddingHorizontal: 20,
        elevation: 4,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
    },
    backButton: {
        marginRight: 15,
    },
    backButtonText: {
        fontSize: 24,
        fontWeight: 'bold',
    },
    headerTitle: {
        fontSize: 20,
        fontWeight: 'bold',
    },
    chatContainer: {
        flex: 1,
        padding: 15,
    },
    bubble: {
        maxWidth: '80%',
        padding: 12,
        borderRadius: 20,
        marginBottom: 8,
    },
    userBubble: {
        borderBottomRightRadius: 4,
    },
    aiBubble: {
        borderBottomLeftRadius: 4,
        borderWidth: 1,
        borderColor: 'rgba(0,0,0,0.05)',
    },
    messageText: {
        fontSize: 16,
        lineHeight: 22,
    },
    correctionBox: {
        maxWidth: '80%',
        padding: 10,
        borderRadius: 12,
        marginBottom: 15,
        borderWidth: 1,
        alignSelf: 'flex-end',
    },
    correctionTitle: {
        color: '#b91c1c',
        fontWeight: 'bold',
        fontSize: 12,
        marginBottom: 2,
    },
    correctionText: {
        color: '#b91c1c',
        fontSize: 14,
        fontWeight: '600',
        marginBottom: 2,
    },
    feedbackText: {
        color: '#7f1d1d',
        fontSize: 12,
        fontStyle: 'italic',
    },
    inputContainer: {
        flexDirection: 'row',
        padding: 15,
        borderTopWidth: 1,
        alignItems: 'center',
    },
    input: {
        flex: 1,
        padding: 12,
        borderRadius: 25,
        maxHeight: 100,
        marginRight: 10,
    },
    sendButton: {
        paddingVertical: 12,
        paddingHorizontal: 20,
        borderRadius: 25,
    },
    sendButtonText: {
        color: 'white',
        fontWeight: 'bold',
    },
    loadingContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        marginLeft: 10,
        marginTop: 10,
    },
    loadingText: {
        marginLeft: 10,
        fontSize: 12,
    },
    micButton: {
        width: 44,
        height: 44,
        borderRadius: 22,
        backgroundColor: '#f3f4f6',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 10,
        borderWidth: 1,
        borderColor: '#e5e7eb',
    },
    micButtonActive: {
        backgroundColor: '#fee2e2',
        borderColor: '#ef4444',
    },
    micIcon: {
        fontSize: 20,
    }
});

export default EnglishTutorScreen;
