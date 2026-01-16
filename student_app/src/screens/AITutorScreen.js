import React, { useState, useRef, useEffect, useCallback } from 'react';
import {
    View, Text, StyleSheet, TextInput, TouchableOpacity, FlatList,
    ActivityIndicator, KeyboardAvoidingView, Platform, Dimensions,
    StatusBar, Vibration, SafeAreaView
} from 'react-native';
import { useTheme } from '../context/ThemeContext';
import { sendMessageToAI } from '../api/ai';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';

const { width } = Dimensions.get('window');

// --- Optimized Message Bubble Component ---
const MessageBubble = React.memo(({ item }) => {
    const isUser = item.sender === 'user';

    return (
        <View style={[styles.messageRow, isUser ? styles.userRow : styles.aiRow]}>
            {!isUser && (
                <View style={styles.avatarContainer}>
                    <LinearGradient colors={['#6366f1', '#a855f7']} style={styles.avatarGradient}>
                        <Ionicons name="sparkles" size={14} color="#FFF" />
                    </LinearGradient>
                </View>
            )}

            <View style={[
                styles.messageBubble,
                isUser ? styles.userBubble : styles.aiBubble,
                item.isError && styles.errorBubble
            ]}>
                <Text style={[
                    styles.messageText,
                    isUser ? styles.userText : styles.aiText,
                    item.isError && styles.errorText
                ]}>{item.text}</Text>

                <Text style={[styles.timestamp, isUser ? styles.userTimestamp : styles.aiTimestamp]}>
                    {new Date(item.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                </Text>
            </View>
        </View>
    );
});

// --- Main Screen ---
const AITutorScreen = ({ navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const [messages, setMessages] = useState([
        {
            id: '1',
            text: "Hello! I'm your AI Tutor. 🎓\nI can explain complex topics, solve math problems, or help you study. What are we learning today?",
            sender: 'ai',
            timestamp: new Date()
        }
    ]);
    const [inputText, setInputText] = useState('');
    const [loading, setLoading] = useState(false);

    const suggestedQuestions = [
        "Explain Quantum Physics",
        "Solve: 2x + 5 = 15",
        "Summarize WW2",
        "Study tips for Finals"
    ];

    const handleSend = useCallback(async (text = inputText) => {
        const messageText = text.trim();
        if (!messageText) return;

        // UI Updates
        Vibration.vibrate(10); // Haptic feedback
        const userMsg = { id: Date.now().toString(), text: messageText, sender: 'user', timestamp: new Date() };
        setMessages(prev => [userMsg, ...prev]);
        setInputText('');
        setLoading(true);

        try {
            // API Call
            const response = await sendMessageToAI(messageText);

            if (response.status === 'success') {
                const aiMsg = { id: (Date.now() + 1).toString(), text: response.reply, sender: 'ai', timestamp: new Date() };
                setMessages(prev => [aiMsg, ...prev]);
            } else {
                // If server sends error, throw it so catch block handles it
                throw new Error(response.message || "Unknown Error");
            }
        } catch (error) {
            // ERROR HANDLING: Show REAL error message
            const errorMsg = {
                id: (Date.now() + 1).toString(),
                text: `⚠️ Error: ${error.message || "Connection Failed"}`,
                sender: 'ai',
                isError: true,
                timestamp: new Date()
            };
            setMessages(prev => [errorMsg, ...prev]);
        } finally {
            setLoading(false);
        }
    }, [inputText]);

    return (
        <KeyboardAvoidingView
            style={[styles.container, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]}
            behavior={Platform.OS === 'ios' ? 'padding' : undefined}
            keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 0}
        >
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent />

            {/* Modern Header */}
            <LinearGradient colors={['#4f46e5', '#4338ca']} style={styles.header}>
                <SafeAreaView>
                    <View style={styles.headerContent}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Ionicons name="arrow-back" size={24} color="#fff" />
                        </TouchableOpacity>
                        <View>
                            <Text style={styles.headerTitle}>AI Tutor</Text>
                            <View style={styles.statusContainer}>
                                <View style={styles.statusDot} />
                                <Text style={styles.headerSubtitle}>Online • Gemini Pro</Text>
                            </View>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            {/* Chat Area */}
            <FlatList
                data={messages}
                renderItem={({ item }) => <MessageBubble item={item} />}
                keyExtractor={item => item.id}
                contentContainerStyle={styles.messagesList}
                inverted
                showsVerticalScrollIndicator={false}
                ListHeaderComponent={
                    loading && (
                        <View style={styles.typingContainer}>
                            <ActivityIndicator size="small" color="#6366f1" />
                            <Text style={styles.typingText}>Thinking...</Text>
                        </View>
                    )
                }
            />

            {/* Suggestions Chips */}
            {!inputText && !loading && (
                <View style={styles.suggestionsWrapper}>
                    <FlatList
                        data={suggestedQuestions}
                        horizontal
                        showsHorizontalScrollIndicator={false}
                        contentContainerStyle={styles.suggestionsContainer}
                        renderItem={({ item }) => (
                            <TouchableOpacity style={styles.chip} onPress={() => handleSend(item)}>
                                <Text style={styles.chipText}>{item}</Text>
                            </TouchableOpacity>
                        )}
                        keyExtractor={item => item}
                    />
                </View>
            )}

            {/* Input Area */}
            <View style={styles.inputWrapper}>
                <View style={[styles.inputContainer, { backgroundColor: isDarkMode ? '#1e293b' : '#fff' }]}>
                    <TextInput
                        style={[styles.input, { color: isDarkMode ? '#fff' : '#1e293b' }]}
                        placeholder="Ask anything..."
                        placeholderTextColor="#94a3b8"
                        value={inputText}
                        onChangeText={setInputText}
                        multiline
                        maxLength={1000}
                    />
                    <TouchableOpacity
                        style={[styles.sendButton, { opacity: inputText.trim() ? 1 : 0.5 }]}
                        onPress={() => handleSend()}
                        disabled={!inputText.trim()}
                    >
                        <LinearGradient colors={['#4f46e5', '#6366f1']} style={styles.sendGradient}>
                            <Ionicons name="arrow-up" size={24} color="#fff" />
                        </LinearGradient>
                    </TouchableOpacity>
                </View>
            </View>
        </KeyboardAvoidingView>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    header: {
        paddingTop: Platform.OS === 'android' ? StatusBar.currentHeight + 10 : 0,
        paddingBottom: 20,
        paddingHorizontal: 20,
        borderBottomLeftRadius: 30,
        borderBottomRightRadius: 30,
        elevation: 10,
        shadowColor: '#4f46e5', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.3, shadowRadius: 20,
        zIndex: 10,
    },
    headerContent: { flexDirection: 'row', alignItems: 'center', gap: 15 },
    backButton: { padding: 8, backgroundColor: 'rgba(255,255,255,0.2)', borderRadius: 12 },
    headerTitle: { fontSize: 24, fontWeight: 'bold', color: '#fff', letterSpacing: 0.5 },
    statusContainer: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 2 },
    statusDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#4ade80' },
    headerSubtitle: { fontSize: 13, color: 'rgba(255,255,255,0.9)', fontWeight: '500' },

    messagesList: { padding: 20, paddingBottom: 10 },
    messageRow: { flexDirection: 'row', marginBottom: 20, alignItems: 'flex-end' },
    userRow: { justifyContent: 'flex-end' },
    aiRow: { justifyContent: 'flex-start' },

    avatarContainer: { marginRight: 10, marginBottom: 5 },
    avatarGradient: { width: 36, height: 36, borderRadius: 18, justifyContent: 'center', alignItems: 'center', shadowColor: '#6366f1', shadowOpacity: 0.3, elevation: 5 },

    messageBubble: { maxWidth: width * 0.78, padding: 16, borderRadius: 24, elevation: 2, shadowColor: '#000', shadowOpacity: 0.05, shadowOffset: { width: 0, height: 2 } },
    userBubble: { backgroundColor: '#4f46e5', borderBottomRightRadius: 4 },
    aiBubble: { backgroundColor: '#fff', borderBottomLeftRadius: 4 },
    errorBubble: { backgroundColor: '#fee2e2', borderColor: '#ef4444', borderWidth: 1 },

    messageText: { fontSize: 16, lineHeight: 24 },
    userText: { color: '#fff' },
    aiText: { color: '#1e293b' },
    errorText: { color: '#b91c1c' },

    timestamp: { fontSize: 10, marginTop: 6, alignSelf: 'flex-end' },
    userTimestamp: { color: 'rgba(255,255,255,0.7)' },
    aiTimestamp: { color: '#94a3b8' },

    typingContainer: { padding: 20, flexDirection: 'row', alignItems: 'center', gap: 10 },
    typingText: { color: '#64748b', fontSize: 13, fontStyle: 'italic' },

    suggestionsWrapper: { paddingBottom: 10 },
    suggestionsContainer: { paddingHorizontal: 20, gap: 10 },
    chip: { backgroundColor: '#e0e7ff', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 20, borderWidth: 1, borderColor: '#c7d2fe' },
    chipText: { color: '#4338ca', fontSize: 13, fontWeight: '600' },

    inputWrapper: { padding: 15, paddingTop: 10 },
    inputContainer: { flexDirection: 'row', alignItems: 'flex-end', borderRadius: 28, padding: 8, paddingLeft: 20, shadowColor: '#000', shadowOpacity: 0.05, shadowOffset: { width: 0, height: 5 }, elevation: 5 },
    input: { flex: 1, fontSize: 16, maxHeight: 100, paddingVertical: 10 },

    sendButton: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', marginLeft: 10 },
    sendGradient: { width: '100%', height: '100%', justifyContent: 'center', alignItems: 'center' },
});

export default AITutorScreen;