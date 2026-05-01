import React, { useState, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Image, ScrollView, ActivityIndicator, Alert, StatusBar, Dimensions, TextInput } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { useTheme } from '../context/ThemeContext';
import { API_URL } from '../api/config';
import { streamFetch } from '../api/streaming';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import * as Clipboard from 'expo-clipboard';
import * as Haptics from 'expo-haptics';
import * as ImageManipulator from 'expo-image-manipulator';
import Markdown from 'react-native-markdown-display';

const { width } = Dimensions.get('window');

import CustomImageCropper from '../components/CustomImageCropper';

const HomeworkSolverScreen = ({ navigation }) => {
    const { theme } = useTheme();
    const [image, setImage] = useState(null);
    const [originalImage, setOriginalImage] = useState(null); // Store original for re-cropping if needed
    const [cropperVisible, setCropperVisible] = useState(false);

    const [solution, setSolution] = useState('');
    const [userText, setUserText] = useState('');
    const [loading, setLoading] = useState(false);
    const [language, setLanguage] = useState('English');
    const scrollRef = useRef(null);

    const getUserId = async () => {
        try {
            const userData = await AsyncStorage.getItem('user_data');
            if (userData) {
                const user = JSON.parse(userData);
                return user.user_id;
            }
        } catch (e) {
            console.warn("Failed to get user ID for AI tracking", e);
        }
        return null;
    };

    const pickImage = async () => {
        const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (status !== 'granted') {
            Alert.alert('Permission needed', 'Sorry, we need camera roll permissions to make this work!');
            return;
        }

        let result = await ImagePicker.launchImageLibraryAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            allowsEditing: false, // Disable native OS cropper
            quality: 1,
        });

        if (!result.canceled) {
            setOriginalImage(result.assets[0].uri);
            setCropperVisible(true);
            setSolution('');
        }
    };

    const takePhoto = async () => {
        const { status } = await ImagePicker.requestCameraPermissionsAsync();
        if (status !== 'granted') {
            Alert.alert('Permission needed', 'Sorry, we need camera permissions to make this work!');
            return;
        }

        let result = await ImagePicker.launchCameraAsync({
            allowsEditing: false, // Disable native OS cropper
            quality: 1,
        });

        if (!result.canceled) {
            setOriginalImage(result.assets[0].uri);
            setCropperVisible(true);
            setSolution('');
        }
    };

    const handleSolve = async () => {
        if (!image && !userText.trim()) {
            Alert.alert('Empty Request', 'Please upload an image or type a question.');
            return;
        }

        setSolution('');
        setLoading(true);
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);

        try {
            const userId = await getUserId();
            const PROXY_URL = `${API_URL}/ai_homework.php`;

            const formData = new FormData();
            
            if (image) {
                // WEB FIX: Browser fetch requires a Blob, not a native file object
                if (Platform.OS === 'web') {
                    const response = await fetch(image);
                    const blob = await response.blob();
                    formData.append('image', blob, 'homework.jpg');
                } else {
                    formData.append('image', {
                        uri: image,
                        type: 'image/jpeg',
                        name: 'homework.jpg',
                    });
                }
            }
            
            if (userText.trim()) {
                formData.append('user_text', userText.trim());
            }

            formData.append('language', language);
            formData.append('prompt', "Please answer the question. If it's a grammar or language question, explain the rule. If it's a math/science question, provide the steps.");
            if (userId) formData.append('user_id', userId);

            await streamFetch(
                PROXY_URL,
                {
                    method: 'POST',
                    body: formData,
                },
                (chunk) => {
                    if (chunk.status === 'success' && chunk.chunk) {
                        setSolution(prev => prev + chunk.chunk);
                        // Auto-scroll to bottom (Optimization)
                        scrollRef.current?.scrollToEnd({ animated: true });
                    } else if (chunk.status === 'error') {
                        setLoading(false);
                        const errorMsg = chunk.message || 'The AI is currently handling too many requests. Please try again in a few minutes.';
                        Alert.alert('AI Error', errorMsg);
                    }
                },
                () => {
                    setLoading(false);
                    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
                },
                (error) => {
                    setLoading(false);
                    Alert.alert('Error', error?.message || 'Failed to connect to AI server.');
                }
            );
        } catch (error) {
            setLoading(false);
            Alert.alert('Error', 'An unexpected error occurred.');
        }
    };

    const copyToClipboard = async () => {
        try {
            if (Clipboard && Clipboard.setStringAsync) {
                await Clipboard.setStringAsync(solution);
                Haptics.selectionAsync();
                Alert.alert('Success', 'Solution copied to clipboard! 📋');
            } else {
                Alert.alert('Error', 'Clipboard feature is not available on this device version.');
            }
        } catch (e) {
            console.warn("Clipboard error", e);
            Alert.alert('Error', 'Failed to copy to clipboard.');
        }
    };

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="#be185d" />

            {/* Header */}
            <View style={styles.headerContainer}>
                <LinearGradient
                    colors={['#4f46e5', '#7c3aed']}
                    style={styles.headerBackground}
                >
                    <View style={styles.headerContent}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Ionicons name="arrow-back" size={24} color="#fff" />
                        </TouchableOpacity>
                        <Text style={styles.headerTitle}>Homework Solver</Text>
                    </View>
                </LinearGradient>
            </View>

            <ScrollView 
                ref={scrollRef}
                style={styles.content} 
                contentContainerStyle={{ paddingBottom: 60 }} 
                showsVerticalScrollIndicator={false}
            >
                {/* Image Preview & Action Area */}
                <View style={styles.card}>
                    <View style={styles.imageWrapper}>
                        {image ? (
                            <Image source={{ uri: image }} style={styles.previewImage} resizeMode="contain" />
                        ) : (
                            <View style={styles.placeholderContainer}>
                                <View style={styles.iconCircle}>
                                    <Ionicons name="camera" size={40} color="#6366f1" />
                                </View>
                                <Text style={styles.placeholderTitle}>Snap a Photo</Text>
                                <Text style={styles.placeholderText}>
                                    Take a clear picture of your homework question to get instant help.
                                </Text>
                            </View>
                        )}

                        {image && (
                            <TouchableOpacity style={styles.closeButton} onPress={() => { setImage(null); setSolution(''); setOriginalImage(null); }}>
                                <Ionicons name="close" size={20} color="#fff" />
                            </TouchableOpacity>
                        )}

                        {image && originalImage && (
                            <TouchableOpacity style={styles.reCropButton} onPress={() => setCropperVisible(true)}>
                                <Ionicons name="crop" size={16} color="#fff" />
                                <Text style={styles.reCropText}>Adjust Crop</Text>
                            </TouchableOpacity>
                        )}
                    </View>

                    {!image && (
                        <View style={[styles.buttonRow, userText.trim().length > 0 && { opacity: 0.5 }]}>
                            <TouchableOpacity 
                                style={styles.actionButton} 
                                onPress={takePhoto}
                                disabled={userText.trim().length > 0}
                            >
                                <LinearGradient colors={['#eef2ff', '#e0e7ff']} style={styles.buttonGradient}>
                                    <Ionicons name="camera" size={24} color="#4f46e5" />
                                    <Text style={[styles.buttonText, { color: '#4f46e5' }]}>Camera</Text>
                                </LinearGradient>
                            </TouchableOpacity>
                            <TouchableOpacity 
                                style={styles.actionButton} 
                                onPress={pickImage}
                                disabled={userText.trim().length > 0}
                            >
                                <LinearGradient colors={['#e0f2fe', '#bae6fd']} style={styles.buttonGradient}>
                                    <Ionicons name="images" size={24} color="#0284c7" />
                                    <Text style={[styles.buttonText, { color: '#0284c7' }]}>Gallery</Text>
                                </LinearGradient>
                            </TouchableOpacity>
                        </View>
                    )}
                </View>

                {/* Text Input Area */}
                <View style={styles.textInputContainer}>
                    <Text style={styles.inputLabel}>Or type your question here:</Text>
                    <TextInput
                        style={[styles.textInput, image && { backgroundColor: '#f1f5f9', color: '#94a3b8' }]}
                        placeholder={image ? "Text disabled (Image uploaded)" : "e.g. What is the Pythagorean theorem?"}
                        placeholderTextColor="#94a3b8"
                        multiline
                        numberOfLines={4}
                        value={userText}
                        onChangeText={setUserText}
                        editable={!image}
                    />
                </View>

                {/* Language Selector */}
                <View style={styles.languageContainer}>
                    <Text style={styles.languageTitle}>Answer Language:</Text>
                        <View style={styles.languageRow}>
                            {['English', 'Hindi', 'Marathi'].map((lang) => (
                                <TouchableOpacity
                                    key={lang}
                                    style={[styles.langButton, language === lang && styles.activeLangButton]}
                                    onPress={() => setLanguage(lang)}
                                >
                                    <Text style={[styles.langText, language === lang && styles.activeLangText]}>{lang}</Text>
                                </TouchableOpacity>
                            ))}
                        </View>
                    </View>

                {/* Solve Button */}
                <TouchableOpacity
                    style={[styles.solveButton, { opacity: loading ? 0.7 : 1 }]}
                        onPress={handleSolve}
                        disabled={loading}
                    >
                        <LinearGradient
                            colors={['#6366f1', '#4f46e5']}
                            style={styles.solveGradient}
                        >
                            {loading ? (
                                <ActivityIndicator color="#fff" />
                            ) : (
                                <>
                                    <Ionicons name="sparkles" size={24} color="#fff" style={{ marginRight: 10 }} />
                                    <Text style={styles.solveButtonText}>Solve with AI</Text>
                                </>
                            )}
                        </LinearGradient>
                    </TouchableOpacity>

                {/* Solution Display */}
                {solution ? (
                    <View style={styles.solutionContainer}>
                        <View style={styles.solutionHeader}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', flex: 1 }}>
                                <Ionicons name="sparkles" size={24} color="#4f46e5" />
                                <Text style={styles.solutionTitle}>AI Brainstorm Results</Text>
                            </View>
                            <TouchableOpacity onPress={copyToClipboard} style={styles.copyButton}>
                                <Ionicons name="copy-outline" size={20} color="#4f46e5" />
                                <Text style={styles.copyText}>Copy</Text>
                            </TouchableOpacity>
                        </View>
                        <View style={styles.solutionCard}>
                            <Markdown style={markdownStyles}>
                                {solution}
                            </Markdown>
                        </View>
                    </View>
                ) : null}
            </ScrollView>

            <CustomImageCropper
                visible={cropperVisible}
                imageUri={originalImage}
                onCropComplete={(croppedUri) => {
                    setImage(croppedUri);
                    setCropperVisible(false);
                }}
                onCancel={() => {
                    setCropperVisible(false);
                    if (!image) setOriginalImage(null);
                }}
            />
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    headerContainer: {
        marginBottom: 10,
    },
    headerBackground: {
        paddingTop: 50,
        paddingBottom: 25,
        paddingHorizontal: 20,
        borderBottomLeftRadius: 32,
        borderBottomRightRadius: 32,
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 10,
        elevation: 8,
    },
    headerContent: {
        flexDirection: 'row',
        alignItems: 'center',
    },
    backButton: {
        marginRight: 15,
        padding: 5,
    },
    headerTitle: {
        fontSize: 22,
        fontWeight: 'bold',
        color: '#fff',
        fontFamily: 'NotoSans-Bold',
    },
    content: {
        padding: 20,
    },
    card: {
        backgroundColor: '#fff',
        borderRadius: 24,
        padding: 10,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 5,
        marginBottom: 20,
    },
    imageWrapper: {
        height: 220,
        borderRadius: 24,
        backgroundColor: '#f8fafc',
        overflow: 'hidden',
        borderWidth: 1.5,
        borderColor: '#e2e8f0',
        borderStyle: 'dashed',
        position: 'relative',
    },
    previewImage: {
        width: '100%',
        height: '100%',
        backgroundColor: '#000',
    },
    placeholderContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        padding: 20,
    },
    iconCircle: {
        width: 60,
        height: 60,
        borderRadius: 30,
        backgroundColor: '#eef2ff',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 20,
    },
    placeholderTitle: {
        fontSize: 22,
        fontWeight: 'bold',
        color: '#4f46e5',
        marginBottom: 10,
        fontFamily: 'NotoSans-Bold',
    },
    placeholderText: {
        textAlign: 'center',
        color: '#64748b',
        lineHeight: 24,
        fontFamily: 'NotoSans-Regular',
    },
    closeButton: {
        position: 'absolute',
        top: 10,
        right: 10,
        backgroundColor: 'rgba(0,0,0,0.5)',
        padding: 8,
        borderRadius: 20,
        zIndex: 5,
    },
    reCropButton: {
        position: 'absolute',
        bottom: 10,
        right: 10,
        backgroundColor: 'rgba(0,0,0,0.6)',
        paddingVertical: 8,
        paddingHorizontal: 15,
        borderRadius: 20,
        flexDirection: 'row',
        alignItems: 'center',
        gap: 5,
        zIndex: 5,
    },
    reCropText: {
        color: '#fff',
        fontWeight: 'bold',
        fontSize: 14,
        fontFamily: 'NotoSans-Bold',
    },
    buttonRow: {
        flexDirection: 'row',
        gap: 12,
        padding: 10,
        marginTop: 10,
    },
    actionButton: {
        flex: 1,
        height: 56,
        borderRadius: 16,
        overflow: 'hidden',
    },
    buttonGradient: {
        flex: 1,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 8,
    },
    buttonText: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#db2777',
        fontFamily: 'NotoSans-Bold',
    },
    solveButton: {
        borderRadius: 24,
        overflow: 'hidden',
        marginBottom: 30,
        elevation: 6,
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.4,
        shadowRadius: 12,
    },
    solveGradient: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 20,
    },
    solveButtonText: {
        color: '#fff',
        fontSize: 18,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    solutionContainer: {
        marginTop: 10,
    },
    solutionHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 16,
        paddingHorizontal: 10,
    },
    copyButton: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#eef2ff',
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 12,
        gap: 5,
    },
    copyText: {
        fontSize: 12,
        color: '#4f46e5',
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    solutionTitle: {
        fontSize: 22,
        fontWeight: 'bold',
        color: '#4f46e5',
        marginLeft: 10,
        fontFamily: 'NotoSans-Bold',
    },
    solutionCard: {
        backgroundColor: '#fff',
        padding: 24,
        borderRadius: 24,
        elevation: 2,
        borderWidth: 1,
        borderColor: '#f0f9ff',
    },
    solutionText: {
        fontSize: 16,
        lineHeight: 26,
        color: '#334155',
        fontFamily: 'NotoSans-Regular',
    },
    languageContainer: {
        marginBottom: 20,
        backgroundColor: '#fff',
        padding: 20,
        borderRadius: 24,
        borderWidth: 1,
        borderColor: '#e2e8f0',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 10,
        elevation: 2,
    },
    languageTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#4f46e5',
        marginBottom: 12,
        fontFamily: 'NotoSans-Bold',
        textTransform: 'uppercase',
        letterSpacing: 1,
    },
    languageRow: {
        flexDirection: 'row',
        gap: 10,
    },
    langButton: {
        flex: 1,
        paddingVertical: 12,
        borderRadius: 14,
        borderWidth: 1.5,
        borderColor: '#f1f5f9',
        alignItems: 'center',
        backgroundColor: '#f8fafc',
    },
    activeLangButton: {
        backgroundColor: '#4f46e5',
        borderColor: '#4f46e5',
    },
    langText: {
        color: '#64748b',
        fontWeight: '700',
        fontFamily: 'NotoSans-Bold',
    },
    activeLangText: {
        color: '#fff',
        fontFamily: 'NotoSans-Bold',
    },
    textInputContainer: {
        backgroundColor: '#fff',
        borderRadius: 24,
        padding: 20,
        marginBottom: 20,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 5,
    },
    inputLabel: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#4f46e5',
        marginBottom: 10,
        fontFamily: 'NotoSans-Bold',
    },
    textInput: {
        backgroundColor: '#f8fafc',
        borderRadius: 16,
        padding: 15,
        minHeight: 80,
        textAlignVertical: 'top',
        fontSize: 16,
        color: '#334155',
        borderWidth: 1,
        borderColor: '#e2e8f0',
        fontFamily: 'NotoSans-Regular',
    },
});

const markdownStyles = StyleSheet.create({
    body: {
        fontSize: 16,
        lineHeight: 26,
        color: '#334155',
        fontFamily: 'NotoSans-Regular',
    },
    heading1: {
        fontSize: 24,
        fontWeight: 'bold',
        color: '#1e293b',
        marginTop: 10,
        marginBottom: 5,
        fontFamily: 'NotoSans-Bold',
    },
    heading2: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#1e293b',
        marginTop: 10,
        marginBottom: 5,
        fontFamily: 'NotoSans-Bold',
    },
    strong: {
        fontWeight: 'bold',
        color: '#db2777',
        fontFamily: 'NotoSans-Bold',
    },
    bullet_list: {
        marginTop: 5,
        marginBottom: 5,
    },
    list_item: {
        flexDirection: 'row',
        marginBottom: 5,
    },
    paragraph: {
        marginTop: 5,
        marginBottom: 5,
    }
});

export default HomeworkSolverScreen;
