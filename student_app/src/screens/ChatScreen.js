import React, { useState, useEffect, useRef } from 'react';
import {
    View, Text, StyleSheet, TextInput, TouchableOpacity,
    FlatList, KeyboardAvoidingView, Platform, ActivityIndicator, AppState
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import axios from 'axios';
import { API_URL } from '../api/config';

export default function ChatScreen({ route, navigation }) {
    const { userId, classId } = route.params || {};
    const { theme, isDarkMode } = useTheme();
    
    const [messages, setMessages] = useState([]);
    const [newMessage, setNewMessage] = useState('');
    const [loading, setLoading] = useState(true);
    const [sending, setSending] = useState(false);
    const [teacher, setTeacher] = useState(null);
    const [resolvedClassCode, setResolvedClassCode] = useState(null);
    const flatListRef = useRef(null);
    const isMounted = useRef(true);
    const appState = useRef(AppState.currentState);

    useEffect(() => {
        isMounted.current = true;
        return () => {
            isMounted.current = false;
        };
    }, []);

    useEffect(() => {
        if (!userId || !classId) {
            setLoading(false);
            return;
        }
        
        let intervalId;
        
        const initChat = async () => {
            const data = await loadData();
            if (isMounted.current && data && data.tId && data.code) {
                // Start polling — but only when app is in foreground
                intervalId = setInterval(() => {
                    if (isMounted.current && appState.current === 'active') {
                        fetchMessages(data.tId, data.code);
                    }
                }, 3000);
            }
        };
        
        // Pause/resume polling based on app state
        const appStateSub = AppState.addEventListener('change', nextState => {
            appState.current = nextState;
        });
        
        initChat();
        
        return () => {
            if (intervalId) clearInterval(intervalId);
            appStateSub.remove();
        };
    }, [userId, classId]);


    const loadData = async () => {
        try {
            // 1. Fetch Teacher ID and Class Code for this specific class
            const tRes = await axios.get(`${API_URL}/chat/get_teacher_for_class_id.php?class_id=${classId}`);
            let teacherData = null;
            let currentClassCode = null;
            if (tRes.data && tRes.data.status === 'success') {
                teacherData = tRes.data.data;
                currentClassCode = teacherData.class_code;
                if (isMounted.current) {
                    setTeacher(teacherData);
                    setResolvedClassCode(currentClassCode);
                }
            } else {
                if (isMounted.current) setLoading(false);
                return;
            }

            // 2. Fetch Messages
            await fetchMessages(teacherData.teacher_id, currentClassCode);
            return { tId: teacherData.teacher_id, code: currentClassCode };
        } catch (error) {
            console.error('Error loading chat:', error);
            return null;
        } finally {
            if (isMounted.current) {
                setLoading(false);
            }
        }
    };

    const fetchMessages = async (tId, code) => {
        try {
            let url = `${API_URL}/chat/get_messages.php?class_code=${code}&user_id=${userId}`;
            if (tId) url += `&with_user_id=${tId}`;

            const response = await axios.get(url);
            if (response.data && response.data.status === 'success') {
                if (isMounted.current) {
                    setMessages(response.data.data);
                }
            }
        } catch (error) {
            console.error('Fetch messages error:', error);
        }
    };

    const handleSend = async () => {
        if (!newMessage.trim() || !teacher || !resolvedClassCode) return;
        
        setSending(true);
        try {
            const payload = {
                sender_id: userId,
                class_code: resolvedClassCode,
                message_text: newMessage.trim(),
                receiver_id: teacher.teacher_id
            };
            
            const response = await axios.post(`${API_URL}/chat/send_message.php`, payload);
            if (response.data && response.data.status === 'success') {
                if (isMounted.current) {
                    setNewMessage('');
                }
                await fetchMessages(teacher.teacher_id, resolvedClassCode);
                setTimeout(() => {
                    if (isMounted.current) flatListRef.current?.scrollToEnd();
                }, 100);
            }
        } catch (error) {
            console.error('Send message error:', error);
        } finally {
            if (isMounted.current) {
                setSending(false);
            }
        }
    };

    const renderMessage = ({ item }) => {
        const isMine = item.sender_id === userId;
        const isBroadcast = item.receiver_id === null;
        
        let dateStr = item.created_at;
        if (dateStr && typeof dateStr === 'string') {
            dateStr = dateStr.replace(' ', 'T');
            if (!dateStr.endsWith('Z') && !dateStr.includes('+') && !dateStr.includes('-')) {
                dateStr += 'Z';
            }
        }
        
        return (
            <View style={[styles.messageWrapper, isMine ? styles.myMessage : styles.theirMessage]}>
                {!isMine && (
                    <Text style={styles.senderName}>
                        {isBroadcast ? '📢 Class Broadcast' : (item.sender_name || 'Teacher')}
                    </Text>
                )}
                <View style={[styles.messageBubble, isMine ? styles.myBubble : [styles.theirBubble, { backgroundColor: isDarkMode ? '#1e293b' : '#FFFFFF', borderColor: isDarkMode ? '#334155' : '#E5E7EB' }]]}>
                    <Text style={[styles.messageText, isMine ? styles.myText : [styles.theirText, { color: theme.text }]]}>
                        {item.message_text}
                    </Text>
                </View>
                <Text style={styles.timestamp}>
                    {new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                </Text>
            </View>
        );
    };

    return (
        <KeyboardAvoidingView 
            style={[styles.container, { backgroundColor: isDarkMode ? '#0f172a' : '#F9FAFB' }]}
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
            keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}
        >
            <LinearGradient colors={[theme.primary, theme.primaryDark || theme.primary]} style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                    <MaterialCommunityIcons name="arrow-left" size={24} color="#FFF" />
                </TouchableOpacity>
                <View>
                    <Text style={styles.headerTitle}>
                        {teacher ? `Chat with ${teacher.teacher_name}` : 'Teacher Chat'}
                    </Text>
                    <Text style={styles.headerSubtitle}>
                        {resolvedClassCode ? `Class Code: ${resolvedClassCode}` : 'Loading...'}
                    </Text>
                </View>
            </LinearGradient>

            {loading ? (
                <View style={styles.loadingContainer}>
                    <ActivityIndicator size="large" color={theme.primary} />
                </View>
            ) : (
                <FlatList
                    ref={flatListRef}
                    data={messages}
                    keyExtractor={(item) => item.id.toString()}
                    renderItem={renderMessage}
                    contentContainerStyle={styles.chatList}
                    onContentSizeChange={() => flatListRef.current?.scrollToEnd()}
                    ListEmptyComponent={
                        <View style={styles.emptyContainer}>
                            <MaterialCommunityIcons name="chat-outline" size={60} color="#9CA3AF" style={{marginBottom: 10}}/>
                            <Text style={styles.emptyText}>No messages yet.</Text>
                            <Text style={styles.emptySubText}>Send a message to your teacher!</Text>
                        </View>
                    }
                />
            )}

            <View style={[styles.inputContainer, { backgroundColor: isDarkMode ? '#1e293b' : '#FFFFFF', borderColor: isDarkMode ? '#334155' : '#E5E7EB' }]}>
                <TextInput
                    style={[styles.input, { backgroundColor: isDarkMode ? '#334155' : '#F3F4F6', color: isDarkMode ? '#FFFFFF' : '#1F2937' }]}
                    placeholder="Type a message..."
                    placeholderTextColor={isDarkMode ? '#94A3B8' : '#9CA3AF'}
                    value={newMessage}
                    onChangeText={setNewMessage}
                    multiline
                />
                <TouchableOpacity 
                    style={[styles.sendButton, { backgroundColor: theme.primary }, !newMessage.trim() && styles.sendButtonDisabled]} 
                    onPress={handleSend}
                    disabled={!newMessage.trim() || sending}
                >
                    {sending ? (
                        <ActivityIndicator color="#fff" size="small" />
                    ) : (
                        <MaterialCommunityIcons name="send" size={20} color="#FFF" />
                    )}
                </TouchableOpacity>
            </View>
        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1 },
    header: { padding: 20, paddingTop: 50, flexDirection: 'row', alignItems: 'center' },
    backButton: { padding: 10, backgroundColor: 'rgba(255,255,255,0.2)', borderRadius: 12, marginRight: 16 },
    headerTitle: { fontSize: 20, fontWeight: 'bold', color: '#FFF' },
    headerSubtitle: { fontSize: 14, color: 'rgba(255,255,255,0.8)' },
    loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    chatList: { padding: 16, paddingBottom: 30 },
    messageWrapper: { marginBottom: 16, maxWidth: '85%' },
    myMessage: { alignSelf: 'flex-end' },
    theirMessage: { alignSelf: 'flex-start' },
    senderName: { fontSize: 12, color: '#6B7280', marginBottom: 4, marginLeft: 4, fontWeight: 'bold' },
    messageBubble: { padding: 14, borderRadius: 20 },
    myBubble: { backgroundColor: '#10B981', borderBottomRightRadius: 4 },
    theirBubble: { borderBottomLeftRadius: 4, borderWidth: 1 },
    messageText: { fontSize: 16, lineHeight: 22 },
    myText: { color: '#FFFFFF' },
    timestamp: { fontSize: 11, color: '#9CA3AF', marginTop: 4, alignSelf: 'flex-end' },
    inputContainer: { flexDirection: 'row', padding: 16, borderTopWidth: 1, alignItems: 'center' },
    input: { flex: 1, borderRadius: 20, paddingHorizontal: 20, paddingTop: 14, paddingBottom: 14, fontSize: 16, maxHeight: 100 },
    sendButton: { borderRadius: 20, paddingHorizontal: 20, paddingVertical: 14, marginLeft: 12, justifyContent: 'center', alignItems: 'center' },
    sendButtonDisabled: { opacity: 0.5 },
    emptyContainer: { alignItems: 'center', marginTop: 100 },
    emptyText: { color: '#9CA3AF', fontSize: 18, fontWeight: 'bold' },
    emptySubText: { color: '#9CA3AF', fontSize: 14, marginTop: 5 }
});
