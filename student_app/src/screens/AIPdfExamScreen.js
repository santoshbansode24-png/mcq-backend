import React, { useState } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    TextInput,
    Alert,
    StatusBar,
    ActivityIndicator
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';

const AIPdfExamScreen = ({ navigation, route }) => {
    // Get aggregated questions passed from PDFToExamScreen Multi Select
    const mcqs = route.params?.allMcqs || [];
    
    // Config State
    const [questionLimit, setQuestionLimit] = useState('25');
    const [loading, setLoading] = useState(false);

    const startTest = () => {
        const limit = parseInt(questionLimit);
        if (!limit || limit < 1 || limit > mcqs.length) {
            Alert.alert('Error', `Please enter a valid number of questions (1-${mcqs.length})`);
            return;
        }

        setLoading(true);
        setTimeout(() => {
            // Shuffle and slice the MCQs
            const shuffle = (array) => {
                let currentIndex = array.length, randomIndex;
                while (currentIndex !== 0) {
                    randomIndex = Math.floor(Math.random() * currentIndex);
                    currentIndex--;
                    [array[currentIndex], array[randomIndex]] = [array[randomIndex], array[currentIndex]];
                }
                return array;
            };

            const selectedQuestions = shuffle([...mcqs]).slice(0, limit);

            setLoading(false);
            
            // Navigate directly to the actual test screen, bypassing backend generation!
            navigation.navigate('MyExamTest', {
                questions: selectedQuestions,
                totalQuestions: selectedQuestions.length,
                subjectName: "AI PDF Exam" 
            });
        }, 300); // Simulate tiny delay for UX
    };

    return (
        <View style={styles.mainWrapper}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />

            <LinearGradient colors={['#00c6ff', '#0072ff']} style={styles.headerGradient}>
                <SafeAreaView edges={['top']} style={styles.headerSafe}>
                    <View style={styles.header}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Text style={styles.backButtonText}>←</Text>
                        </TouchableOpacity>
                        <View style={styles.headerTextContainer}>
                            <Text style={styles.headerTitle}>Custom PDF Exam</Text>
                            <Text style={styles.headerSubtitle}>{mcqs.length} Total Questions Available</Text>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <ScrollView style={styles.container} contentContainerStyle={styles.scrollContent}>
                
                {/* Info Card */}
                <View style={styles.infoCard}>
                    <Text style={styles.infoTitle}>AI Content Loaded</Text>
                    <Text style={styles.infoDesc}>
                        We have successfully extracted {mcqs.length} multiple-choice questions from your selected PDFs. Configure your test below.
                    </Text>
                </View>

                {/* Question Limit Config */}
                <View style={styles.section}>
                    <Text style={styles.sectionTitle}>Number of Questions to Ask</Text>
                    <View style={styles.limitContainer}>
                        {['10', '25', '50', '100'].map((num) => {
                            const numInt = parseInt(num);
                            // Disable if we don't have enough questions
                            const disabled = numInt > mcqs.length;
                            return (
                                <TouchableOpacity
                                    key={num}
                                    style={[
                                        styles.limitButton, 
                                        questionLimit === num && styles.limitButtonSelected,
                                        disabled && { opacity: 0.3 }
                                    ]}
                                    onPress={() => !disabled && setQuestionLimit(num)}
                                    disabled={disabled}
                                >
                                    <Text style={[
                                        styles.limitButtonText,
                                        questionLimit === num && styles.limitButtonTextSelected
                                    ]}>{num}</Text>
                                </TouchableOpacity>
                            )
                        })}
                    </View>
                    <TextInput
                        style={styles.customInput}
                        placeholder={`Or enter custom number (1-${mcqs.length})`}
                        placeholderTextColor="#94a3b8"
                        keyboardType="number-pad"
                        value={questionLimit}
                        onChangeText={setQuestionLimit}
                        maxLength={3}
                    />
                </View>

                {/* Start Button */}
                <TouchableOpacity
                    style={styles.startButton}
                    onPress={startTest}
                    disabled={loading || mcqs.length === 0}
                >
                    <LinearGradient colors={['#00c6ff', '#0072ff']} style={styles.startButtonGradient}>
                        {loading ? (
                            <ActivityIndicator color="white" />
                        ) : (
                            <>
                                <Text style={styles.startButtonText}>Start Test</Text>
                                <Text style={styles.startButtonSubtext}>
                                    Take exam with {questionLimit || 0} random questions
                                </Text>
                            </>
                        )}
                    </LinearGradient>
                </TouchableOpacity>

            </ScrollView>
        </View>
    );
};

const styles = StyleSheet.create({
    mainWrapper: { flex: 1, backgroundColor: '#f8fafc' },
    headerGradient: { paddingBottom: 20 },
    headerSafe: { backgroundColor: 'transparent' },
    header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, paddingBottom: 10 },
    backButton: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.2)', justifyContent: 'center', alignItems: 'center', marginRight: 15 },
    backButtonText: { fontSize: 24, color: 'white', fontWeight: 'bold' },
    headerTextContainer: { flex: 1 },
    headerTitle: { fontSize: 24, fontWeight: 'bold', color: 'white' },
    headerSubtitle: { fontSize: 14, color: 'rgba(255,255,255,0.9)', marginTop: 2 },
    container: { flex: 1 },
    scrollContent: { padding: 20, paddingBottom: 40 },
    
    infoCard: { backgroundColor: '#eff6ff', borderRadius: 12, padding: 15, marginBottom: 25, borderWidth: 1, borderColor: '#bfdbfe' },
    infoTitle: { color: '#1e40af', fontWeight: 'bold', fontSize: 16, marginBottom: 5 },
    infoDesc: { color: '#3b82f6', fontSize: 14, lineHeight: 20 },

    section: { marginBottom: 30 },
    sectionTitle: { fontSize: 18, fontWeight: 'bold', color: '#0f172a', marginBottom: 15 },
    limitContainer: { flexDirection: 'row', gap: 10, marginBottom: 15 },
    limitButton: { flex: 1, paddingVertical: 16, borderRadius: 12, backgroundColor: 'white', borderWidth: 2, borderColor: '#e2e8f0', alignItems: 'center' },
    limitButtonSelected: { borderColor: '#0072ff', backgroundColor: '#eff6ff' },
    limitButtonText: { fontSize: 18, fontWeight: '600', color: '#475569' },
    limitButtonTextSelected: { color: '#0072ff', fontWeight: 'bold' },
    customInput: { backgroundColor: 'white', borderRadius: 12, padding: 16, fontSize: 15, borderWidth: 1, borderColor: '#e2e8f0', color: '#1e293b' },
    
    startButton: { borderRadius: 16, overflow: 'hidden', elevation: 4, shadowColor: '#0072ff', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.3, shadowRadius: 8, marginTop: 10 },
    startButtonGradient: { padding: 20, alignItems: 'center' },
    startButtonText: { fontSize: 18, fontWeight: 'bold', color: 'white' },
    startButtonSubtext: { fontSize: 13, color: 'rgba(255,255,255,0.9)', marginTop: 4 }
});

export default AIPdfExamScreen;
