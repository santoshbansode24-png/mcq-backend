import React, { useState } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, ScrollView,
    ActivityIndicator, Alert, TextInput, Image, Dimensions, StatusBar,
    KeyboardAvoidingView, Platform
} from 'react-native';
import { useTheme } from '../context/ThemeContext';
import axios from 'axios';
import { API_URL } from '../api/config';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import * as DocumentPicker from 'expo-document-picker';

const { width } = Dimensions.get('window');

const QuizGeneratorScreen = ({ navigation, user }) => {
    const { theme } = useTheme();

    // Steps: 1 = Input (Text/Photo/File), 2 = Playing Quiz, 3 = Result
    const [step, setStep] = useState(1);
    const [loading, setLoading] = useState(false);

    // Input State
    const [inputType, setInputType] = useState('text'); // 'text', 'camera', 'file'
    const [inputText, setInputText] = useState('');
    const [selectedImage, setSelectedImage] = useState(null);
    const [selectedFile, setSelectedFile] = useState(null);

    // Quiz State
    const [quiz, setQuiz] = useState([]);
    const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);
    const [selectedOption, setSelectedOption] = useState(null);
    const [showExplanation, setShowExplanation] = useState(false);
    const [score, setScore] = useState(0);
    const [quizFinished, setQuizFinished] = useState(false);

    // --- Input Handlers ---

    const pickImage = async () => {
        const permissionResult = await ImagePicker.requestCameraPermissionsAsync();
        if (permissionResult.granted === false) {
            Alert.alert("Permission Required", "You need to allow camera access to take photos of your notes.");
            return;
        }

        const result = await ImagePicker.launchCameraAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            allowsEditing: true,
            aspect: [4, 3],
            quality: 0.5, // Optimize size for faster upload
            base64: true,
        });

        if (!result.canceled) {
            setSelectedImage(result.assets[0]);
            setInputType('camera');
        }
    };

    const pickDocument = async () => {
        try {
            const result = await DocumentPicker.getDocumentAsync({
                type: [
                    'application/pdf',
                    'text/plain',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // DOCX
                    'application/msword' // DOC
                ],
                copyToCacheDirectory: true
            });

            if (result.assets && result.assets.length > 0) {
                setSelectedFile(result.assets[0]);
                setInputType('file');
            }
        } catch (err) {
            console.log('Document selection cancelled');
        }
    };

    // --- API Logic ---

    const handleGenerateQuiz = async () => {
        // Validation
        if (inputType === 'text' && !inputText.trim()) {
            Alert.alert("Empty Input", "Please write some text to generate a quiz.");
            return;
        }
        if (inputType === 'camera' && !selectedImage) {
            Alert.alert("No Image", "Please take a photo first.");
            return;
        }
        if (inputType === 'file' && !selectedFile) {
            Alert.alert("No File", "Please upload a document first.");
            return;
        }

        setLoading(true);

        try {
            const formData = new FormData();
            formData.append('input_type', inputType);

            if (inputType === 'text') {
                formData.append('content', inputText);
            } else if (inputType === 'camera') {
                const uri = selectedImage.uri;
                const fileType = uri.substring(uri.lastIndexOf('.') + 1);
                formData.append('file', {
                    uri: uri,
                    name: `photo.${fileType}`,
                    type: `image/${fileType}`,
                });
            } else if (inputType === 'file') {
                formData.append('file', {
                    uri: selectedFile.uri,
                    name: selectedFile.name,
                    type: selectedFile.mimeType || 'application/octet-stream',
                });
            }

            // Call the custom PHP endpoint we created
            const response = await axios.post(`${API_URL}/ai_generate_quiz_custom.php`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            if (response.data.status === 'success') {
                setQuiz(response.data.data);
                setStep(2); // Start Quiz
                setScore(0);
                setCurrentQuestionIndex(0);
                setQuizFinished(false);
                setSelectedOption(null);
                setShowExplanation(false);
            } else {
                Alert.alert('Error', response.data.message || 'Failed to generate quiz');
            }
        } catch (error) {
            console.error(error);
            Alert.alert('Connection Error', 'Failed to connect to AI service. Please check your internet.');
        } finally {
            setLoading(false);
        }
    };

    // --- Quiz Logic ---
    const handleOptionSelect = (optionKey) => {
        if (selectedOption) return;
        setSelectedOption(optionKey);
        setShowExplanation(true);
        if (optionKey === quiz[currentQuestionIndex].correct_answer) {
            setScore(prev => prev + 1);
        }
    };

    const nextQuestion = () => {
        if (currentQuestionIndex < quiz.length - 1) {
            setCurrentQuestionIndex(prev => prev + 1);
            setSelectedOption(null);
            setShowExplanation(false);
        } else {
            setQuizFinished(true);
        }
    };

    const decodeHtml = (html) => {
        if (!html) return '';
        return html
            .replace(/&quot;/g, '"')
            .replace(/&apos;/g, "'")
            .replace(/&#039;/g, "'")
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&nbsp;/g, ' ');
    };

    // --- Render Components ---

    const renderHeader = () => (
        <View style={styles.headerContainer}>
            <LinearGradient colors={['#7c3aed', '#6d28d9']} style={styles.headerBackground}>
                <View style={styles.headerContent}>
                    <TouchableOpacity onPress={() => step === 1 ? navigation.goBack() : setStep(1)} style={styles.backButton}>
                        <Ionicons name="arrow-back" size={24} color="#fff" />
                    </TouchableOpacity>
                    <Text style={styles.headerTitle}>
                        {step === 1 ? 'AI Generator' : 'Quiz Time'}
                    </Text>
                </View>
            </LinearGradient>
        </View>
    );

    const renderInputSection = () => (
        <ScrollView style={styles.inputScroll} contentContainerStyle={{ paddingBottom: 40 }} showsVerticalScrollIndicator={false}>
            <Text style={styles.sectionTitle}>How do you want to learn?</Text>

            {/* Input Type Tabs */}
            <View style={styles.tabContainer}>
                <TouchableOpacity
                    style={[styles.tabButton, inputType === 'camera' && styles.activeTab]}
                    onPress={() => pickImage()}
                >
                    <Ionicons name="camera" size={24} color={inputType === 'camera' ? '#fff' : '#64748b'} />
                    <Text style={[styles.tabText, inputType === 'camera' && styles.activeTabText]}>Photo</Text>
                </TouchableOpacity>

                <TouchableOpacity
                    style={[styles.tabButton, inputType === 'file' && styles.activeTab]}
                    onPress={() => pickDocument()}
                >
                    <Ionicons name="document-text" size={24} color={inputType === 'file' ? '#fff' : '#64748b'} />
                    <Text style={[styles.tabText, inputType === 'file' && styles.activeTabText]}>Upload</Text>
                </TouchableOpacity>

                <TouchableOpacity
                    style={[styles.tabButton, inputType === 'text' && styles.activeTab]}
                    onPress={() => setInputType('text')}
                >
                    <Ionicons name="create" size={24} color={inputType === 'text' ? '#fff' : '#64748b'} />
                    <Text style={[styles.tabText, inputType === 'text' && styles.activeTabText]}>Write</Text>
                </TouchableOpacity>
            </View>

            {/* Input Content Area */}
            <View style={styles.inputCard}>
                {inputType === 'text' && (
                    <TextInput
                        style={styles.textInput}
                        placeholder="Paste your notes or write a topic here (e.g., 'Newton's Laws')..."
                        multiline
                        numberOfLines={8}
                        textAlignVertical="top"
                        value={inputText}
                        onChangeText={setInputText}
                    />
                )}

                {inputType === 'camera' && (
                    <View style={styles.previewContainer}>
                        {selectedImage ? (
                            <Image source={{ uri: selectedImage.uri }} style={styles.previewImage} />
                        ) : (
                            <View style={styles.placeholderView}>
                                <Ionicons name="camera-outline" size={50} color="#cbd5e1" />
                                <Text style={styles.placeholderText}>Tap 'Photo' above to take a picture</Text>
                            </View>
                        )}
                        {selectedImage && (
                            <TouchableOpacity style={styles.repickButton} onPress={pickImage}>
                                <Text style={styles.repickText}>Retake Photo</Text>
                            </TouchableOpacity>
                        )}
                    </View>
                )}

                {inputType === 'file' && (
                    <View style={styles.previewContainer}>
                        {selectedFile ? (
                            <View style={styles.fileInfo}>
                                <Ionicons name="document" size={40} color="#7c3aed" />
                                <Text style={styles.fileName}>{selectedFile.name}</Text>
                                <Text style={styles.fileSize}>{(selectedFile.size / 1024).toFixed(1)} KB</Text>
                            </View>
                        ) : (
                            <View style={styles.placeholderView}>
                                <Ionicons name="cloud-upload-outline" size={50} color="#cbd5e1" />
                                <Text style={styles.placeholderText}>Supported: PDF, DOCX, DOC, TXT</Text>
                            </View>
                        )}
                        {selectedFile && (
                            <TouchableOpacity style={styles.repickButton} onPress={pickDocument}>
                                <Text style={styles.repickText}>Choose Different File</Text>
                            </TouchableOpacity>
                        )}
                    </View>
                )}
            </View>

            <TouchableOpacity style={styles.generateButton} onPress={handleGenerateQuiz}>
                <LinearGradient
                    colors={['#7c3aed', '#6d28d9']}
                    style={styles.generateGradient}
                >
                    <Ionicons name="sparkles" size={20} color="#fff" style={{ marginRight: 8 }} />
                    <Text style={styles.generateButtonText}>Generate Quiz</Text>
                </LinearGradient>
            </TouchableOpacity>
        </ScrollView>
    );

    // --- Loading View ---
    if (loading) {
        return (
            <View style={[styles.container, styles.center]}>
                <ActivityIndicator size="large" color="#7c3aed" />
                <Text style={styles.loadingText}>AI is reading your content...</Text>
                <Text style={styles.subLoadingText}>This may take a few seconds.</Text>
            </View>
        );
    }

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="#5b21b6" />
            {renderHeader()}

            <KeyboardAvoidingView
                behavior={Platform.OS === "ios" ? "padding" : "height"}
                style={styles.content}
            >
                {step === 1 && renderInputSection()}

                {step === 2 && !quizFinished && (
                    <ScrollView contentContainerStyle={styles.quizScroll}>
                        {/* Question Card */}
                        <View style={styles.questionCard}>
                            <View style={styles.questionHeader}>
                                <Text style={styles.questionLabel}>Question {currentQuestionIndex + 1}/{quiz.length}</Text>
                                <View style={styles.scoreBadge}><Text style={styles.scoreText}>{score} pts</Text></View>
                            </View>
                            <Text style={styles.questionText}>{decodeHtml(quiz[currentQuestionIndex].question)}</Text>
                        </View>

                        {/* Options */}
                        {['a', 'b', 'c', 'd'].map((opt) => {
                            const isSelected = selectedOption === opt;
                            const isCorrect = opt === quiz[currentQuestionIndex].correct_answer;
                            const showCorrect = selectedOption && isCorrect;
                            const showWrong = isSelected && !isCorrect;

                            return (
                                <TouchableOpacity
                                    key={opt}
                                    style={[
                                        styles.optionButton,
                                        showCorrect && styles.correctOption,
                                        showWrong && styles.wrongOption,
                                        !selectedOption && styles.defaultOption
                                    ]}
                                    onPress={() => handleOptionSelect(opt)}
                                    disabled={selectedOption !== null}
                                >
                                    <View style={[styles.optionCircle, showCorrect && styles.correctCircle, showWrong && styles.wrongCircle]}>
                                        <Text style={[styles.optionLetter, (showCorrect || showWrong) && { color: '#fff' }]}>{opt.toUpperCase()}</Text>
                                    </View>
                                    <Text style={[styles.optionText, (showCorrect || showWrong) && { fontWeight: 'bold', color: '#1e293b' }]}>
                                        {decodeHtml(quiz[currentQuestionIndex][`option_${opt}`])}
                                    </Text>
                                    {showCorrect && <Ionicons name="checkmark-circle" size={24} color="#16a34a" style={styles.resultIcon} />}
                                    {showWrong && <Ionicons name="close-circle" size={24} color="#dc2626" style={styles.resultIcon} />}
                                </TouchableOpacity>
                            );
                        })}

                        {/* Explanation & Next */}
                        {showExplanation && (
                            <View style={styles.explanationBox}>
                                <View style={styles.explanationHeader}>
                                    <Ionicons name="bulb" size={20} color="#f59e0b" />
                                    <Text style={styles.explanationTitle}>Explanation</Text>
                                </View>
                                <Text style={styles.explanationText}>{decodeHtml(quiz[currentQuestionIndex].explanation)}</Text>
                                <TouchableOpacity style={styles.nextButton} onPress={nextQuestion}>
                                    <Text style={styles.nextButtonText}>{currentQuestionIndex === quiz.length - 1 ? 'Finish Quiz' : 'Next Question'}</Text>
                                    <Ionicons name="arrow-forward" size={20} color="#fff" />
                                </TouchableOpacity>
                            </View>
                        )}
                    </ScrollView>
                )}

                {step === 2 && quizFinished && (
                    <View style={styles.resultView}>
                        <LinearGradient colors={['#7c3aed', '#6d28d9']} style={styles.resultCard}>
                            <Text style={styles.resultEmoji}>🏆</Text>
                            <Text style={styles.resultTitle}>Quiz Complete!</Text>
                            <Text style={styles.finalScore}>{score} / {quiz.length}</Text>
                            <Text style={styles.resultMessage}>{score > quiz.length / 2 ? 'Great job!' : 'Keep Practicing!'}</Text>
                        </LinearGradient>
                        <TouchableOpacity style={styles.restartButton} onPress={() => { setStep(1); setInputText(''); setSelectedImage(null); setSelectedFile(null); }}>
                            <Text style={styles.restartButtonText}>Create New Quiz</Text>
                        </TouchableOpacity>
                    </View>
                )}
            </KeyboardAvoidingView>
        </View>
    );
};

// Styles
const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#f8fafc' },
    center: { justifyContent: 'center', alignItems: 'center' },
    content: { flex: 1 },
    headerContainer: { marginBottom: 10 },
    headerBackground: { paddingTop: 50, paddingBottom: 20, paddingHorizontal: 20, borderBottomLeftRadius: 30, borderBottomRightRadius: 30 },
    headerContent: { flexDirection: 'row', alignItems: 'center' },
    backButton: { marginRight: 15, padding: 5 },
    headerTitle: { fontSize: 22, fontWeight: 'bold', color: '#fff' },
    loadingText: { marginTop: 20, color: '#64748b', fontSize: 16, fontWeight: 'bold' },
    subLoadingText: { marginTop: 5, color: '#94a3b8', fontSize: 14 },

    // Input Section Styles
    inputScroll: { padding: 20 },
    sectionTitle: { fontSize: 18, fontWeight: 'bold', color: '#1e293b', marginBottom: 15 },
    tabContainer: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 20 },
    tabButton: { flex: 1, alignItems: 'center', paddingVertical: 12, backgroundColor: '#fff', borderRadius: 12, marginHorizontal: 4, borderWidth: 1, borderColor: '#e2e8f0' },
    activeTab: { backgroundColor: '#7c3aed', borderColor: '#7c3aed' },
    tabText: { marginTop: 4, fontSize: 12, fontWeight: '600', color: '#64748b' },
    activeTabText: { color: '#fff' },

    inputCard: { backgroundColor: '#fff', borderRadius: 20, padding: 15, minHeight: 200, borderWidth: 1, borderColor: '#e2e8f0', marginBottom: 20 },
    textInput: { fontSize: 16, color: '#334155', height: 180 },
    previewContainer: { alignItems: 'center', justifyContent: 'center', flex: 1 },
    previewImage: { width: '100%', height: 200, borderRadius: 12, resizeMode: 'cover' },
    placeholderView: { alignItems: 'center', justifyContent: 'center', height: 150 },
    placeholderText: { color: '#94a3b8', marginTop: 10, fontSize: 14 },
    fileInfo: { alignItems: 'center' },
    fileName: { marginTop: 10, fontSize: 16, fontWeight: 'bold', color: '#334155' },
    fileSize: { color: '#64748b', fontSize: 12 },
    repickButton: { marginTop: 15, paddingVertical: 8, paddingHorizontal: 16, backgroundColor: '#f1f5f9', borderRadius: 20 },
    repickText: { color: '#64748b', fontSize: 12, fontWeight: '600' },

    generateButton: { marginTop: 10, borderRadius: 16, overflow: 'hidden' },
    generateGradient: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 16 },
    generateButtonText: { color: '#fff', fontSize: 18, fontWeight: 'bold' },

    // Quiz Styles (Recycled from your code)
    quizScroll: { padding: 20, paddingBottom: 40 },
    questionCard: { backgroundColor: '#fff', padding: 24, borderRadius: 24, marginBottom: 24, elevation: 3 },
    questionHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 16 },
    questionLabel: { fontSize: 14, fontWeight: 'bold', color: '#7c3aed' },
    scoreBadge: { backgroundColor: '#ede9fe', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 10 },
    scoreText: { color: '#7c3aed', fontWeight: 'bold' },
    questionText: { fontSize: 18, fontWeight: '600', color: '#1e293b' },
    optionButton: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#fff', padding: 16, borderRadius: 16, marginBottom: 12, borderWidth: 1 },
    defaultOption: { borderColor: '#e2e8f0' },
    correctOption: { borderColor: '#16a34a', backgroundColor: '#f0fdf4' },
    wrongOption: { borderColor: '#dc2626', backgroundColor: '#fef2f2' },
    optionCircle: { width: 32, height: 32, borderRadius: 16, backgroundColor: '#f1f5f9', justifyContent: 'center', alignItems: 'center', marginRight: 12 },
    correctCircle: { backgroundColor: '#16a34a' },
    wrongCircle: { backgroundColor: '#dc2626' },
    optionLetter: { fontSize: 14, fontWeight: 'bold', color: '#64748b' },
    optionText: { flex: 1, fontSize: 16, color: '#475569' },
    resultIcon: { marginLeft: 8 },
    explanationBox: { marginTop: 20, backgroundColor: '#fff', borderRadius: 20, padding: 20, borderWidth: 1, borderColor: '#e2e8f0' },
    explanationHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
    explanationTitle: { fontSize: 16, fontWeight: 'bold', color: '#d97706', marginLeft: 8 },
    explanationText: { fontSize: 15, color: '#475569', marginBottom: 20 },
    nextButton: { backgroundColor: '#7c3aed', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', padding: 16, borderRadius: 16 },
    nextButtonText: { color: '#fff', fontSize: 16, fontWeight: 'bold', marginRight: 8 },
    resultView: { padding: 20, alignItems: 'center', justifyContent: 'center', flex: 1 },
    resultCard: { width: '100%', padding: 40, borderRadius: 30, alignItems: 'center', marginBottom: 40 },
    resultEmoji: { fontSize: 80, marginBottom: 20 },
    resultTitle: { fontSize: 32, fontWeight: 'bold', color: '#fff', marginBottom: 10 },
    finalScore: { fontSize: 48, fontWeight: 'bold', color: '#fff', marginBottom: 10 },
    resultMessage: { fontSize: 18, color: 'rgba(255,255,255,0.9)' },
    restartButton: { width: '100%', backgroundColor: '#fff', padding: 20, borderRadius: 20, alignItems: 'center', borderWidth: 1, borderColor: '#e2e8f0' },
    restartButtonText: { color: '#7c3aed', fontSize: 18, fontWeight: 'bold' },
});

export default QuizGeneratorScreen;