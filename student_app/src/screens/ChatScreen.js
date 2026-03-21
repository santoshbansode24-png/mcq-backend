import React, { useState, useRef, useEffect } from 'react';
import {
    View, Text, StyleSheet, TextInput, TouchableOpacity,
    FlatList, KeyboardAvoidingView, Platform, ActivityIndicator,
    StatusBar, SafeAreaView, Image, Alert
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as ImagePicker from 'expo-image-picker';
import { API_URL } from '../api/config';
import { streamFetch } from '../api/streaming';
import AsyncStorage from '@react-native-async-storage/async-storage';

const ChatScreen = ({ navigation }) => {
    const [messages, setMessages] = useState([
        { id: '1', text: 'Hello! I am your AI Study Buddy. How can I help you today?', isUser: false }
    ]);
    const [inputText, setInputText] = useState('');
    const [isTyping, setIsTyping] = useState(false);
    const [selectedImage, setSelectedImage] = useState(null);
    const flatListRef = useRef(null);

    const getUserId = async () => {
        try {
            const userData = await AsyncStorage.getItem('user_data');
            if (userData) return JSON.parse(userData).user_id;
        } catch (e) {}
        return null;
    };

    const pickImage = async () => {
        const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (status !== 'granted') {
            Alert.alert('Permission needed', 'Allow access to gallery to send photos.');
            return;
        }

        let result = await ImagePicker.launchImageLibraryAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            quality: 0.7,
        });

        if (!result.canceled) {
            setSelectedImage(result.assets[0].uri);
        }
    };

    const handleSend = async () => {
        if ((inputText.trim() === '' && !selectedImage) || isTyping) return;

        const userMsg = inputText.trim();
        const userImg = selectedImage;
        const userMsgId = Date.now().toString();
        
        setMessages(prev => [...prev, { id: userMsgId, text: userMsg, image: userImg, isUser: true }]);
        setInputText('');
        setSelectedImage(null);
        setIsTyping(true);

        // Add a placeholder for AI response
        const aiMsgId = (Date.now() + 1).toString();
        setMessages(prev => [...prev, { id: aiMsgId, text: '', isUser: false }]);

        try {
            const userId = await getUserId();
            const url = `${API_URL}/ai_chat.php`;

            const formData = new FormData();
            formData.append('message', userMsg || "What is in this image?");
            if (userId) formData.append('user_id', userId);
            if (userImg) {
                formData.append('image', {
                    uri: userImg,
                    name: 'photo.jpg',
                    type: 'image/jpeg'
                });
            }

            await streamFetch(
                url,
                {
                    method: 'POST',
                    body: formData
                },
                (chunk) => {
                    if (chunk.status === 'success' && chunk.chunk) {
                        setMessages(prev => {
                            const newHistory = [...prev];
                            const msgIndex = newHistory.findIndex(m => m.id === aiMsgId);
                            if (msgIndex !== -1) {
                                newHistory[msgIndex].text += chunk.chunk;
                            }
                            return newHistory;
                        });
                    }
                },
                () => {
                    setIsTyping(false);
                },
                (error) => {
                    setIsTyping(false);
                    setMessages(prev => [
                        ...prev,
                        { id: Date.now().toString(), text: 'Sorry, I encountered an error. Please try again.', isUser: false }
                    ]);
                }
            );
        } catch (e) {
            setIsTyping(false);
        }
    };

    const renderMessage = ({ item }) => (
        <View style={[
            styles.messageWrapper,
            item.isUser ? styles.userWrapper : styles.aiWrapper
        ]}>
            {!item.isUser && (
                <View style={styles.aiAvatar}>
                    <Ionicons name="sparkles" size={16} color="#fff" />
                </View>
            )}
            <View style={[
                styles.messageBubble,
                item.isUser ? styles.userBubble : styles.aiBubble
            ]}>
                {item.image && (
                    <Image source={{ uri: item.image }} style={styles.messageImage} />
                )}
                {item.text ? (
                    <Text style={[
                        styles.messageText,
                        item.isUser ? styles.userText : styles.aiText
                    ]}>
                        {item.text}
                    </Text>
                ) : null}
            </View>
        </View>
    );

    return (
        <SafeAreaView style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="#1e1b4b" />
            
            {/* Header */}
            <LinearGradient colors={['#1e1b4b', '#312e81']} style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
                    <Ionicons name="arrow-back" size={24} color="#fff" />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>AI Study Buddy</Text>
                <View style={{ width: 40 }} />
            </LinearGradient>

            <KeyboardAvoidingView 
                behavior={Platform.OS === 'ios' ? 'padding' : undefined}
                style={styles.content}
            >
                <FlatList
                    ref={flatListRef}
                    data={messages}
                    keyExtractor={item => item.id}
                    renderItem={renderMessage}
                    contentContainerStyle={styles.listContent}
                    onContentSizeChange={() => flatListRef.current?.scrollToEnd()}
                />

                {/* Input Area */}
                <View style={styles.inputOuterContainer}>
                    {selectedImage && (
                        <View style={styles.imagePreviewBar}>
                            <Image source={{ uri: selectedImage }} style={styles.smallPreview} />
                            <TouchableOpacity onPress={() => setSelectedImage(null)} style={styles.removeImg}>
                                <Ionicons name="close-circle" size={20} color="#ef4444" />
                            </TouchableOpacity>
                        </View>
                    )}
                    <View style={styles.inputContainer}>
                        <TouchableOpacity style={styles.attachBtn} onPress={pickImage}>
                            <Ionicons name="add" size={28} color="#64748b" />
                        </TouchableOpacity>
                        <TextInput
                            style={styles.input}
                            placeholder="Ask me anything..."
                            placeholderTextColor="#94a3b8"
                            value={inputText}
                            onChangeText={setInputText}
                            multiline
                        />
                        <TouchableOpacity 
                            style={[styles.sendBtn, (!inputText.trim() && !selectedImage) && styles.sendBtnDisabled]}
                            onPress={handleSend}
                            disabled={(!inputText.trim() && !selectedImage) || isTyping}
                        >
                            {isTyping ? (
                                <ActivityIndicator size="small" color="#fff" />
                            ) : (
                                <Ionicons name="send" size={20} color="#fff" />
                            )}
                        </TouchableOpacity>
                    </View>
                </View>
            </KeyboardAvoidingView>
        </SafeAreaView>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#f8fafc' },
    header: {
        height: 60,
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 15,
        justifyContent: 'space-between',
    },
    headerTitle: { color: '#fff', fontSize: 18, fontWeight: 'bold' },
    backBtn: { padding: 5 },
    content: { flex: 1 },
    listContent: { padding: 15, paddingBottom: 20 },
    messageWrapper: { flexDirection: 'row', marginBottom: 15, maxWidth: '85%' },
    userWrapper: { alignSelf: 'flex-end', justifyContent: 'flex-end' },
    aiWrapper: { alignSelf: 'flex-start', justifyContent: 'flex-start' },
    aiAvatar: {
        width: 32, height: 32, borderRadius: 16, backgroundColor: '#6366f1',
        justifyContent: 'center', alignItems: 'center', marginRight: 8, marginTop: 4
    },
    messageBubble: { padding: 12, borderRadius: 20 },
    userBubble: { backgroundColor: '#2563eb', borderBottomRightRadius: 4 },
    aiBubble: { backgroundColor: '#fff', borderBottomLeftRadius: 4, elevation: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.1, shadowRadius: 2 },
    messageText: { fontSize: 16, lineHeight: 22 },
    messageImage: { width: 200, height: 150, borderRadius: 10, marginBottom: 8 },
    userText: { color: '#fff' },
    aiText: { color: '#1e293b' },
    inputOuterContainer: { borderTopWidth: 1, borderTopColor: '#e2e8f0', backgroundColor: '#fff' },
    imagePreviewBar: { padding: 10, flexDirection: 'row', alignItems: 'center' },
    smallPreview: { width: 50, height: 50, borderRadius: 8 },
    removeImg: { marginLeft: -10, marginTop: -40 },
    inputContainer: {
        flexDirection: 'row', padding: 10, alignItems: 'flex-end'
    },
    attachBtn: { padding: 8, marginRight: 5 },
    input: {
        flex: 1, minHeight: 45, maxHeight: 120, backgroundColor: '#f1f5f9',
        borderRadius: 22, paddingHorizontal: 18, paddingVertical: 10,
        fontSize: 16, color: '#1e293b', marginRight: 10
    },
    sendBtn: {
        width: 45, height: 45, borderRadius: 23, backgroundColor: '#2563eb',
        justifyContent: 'center', alignItems: 'center'
    },
    sendBtnDisabled: { backgroundColor: '#94a3b8' }
});

export default ChatScreen;
