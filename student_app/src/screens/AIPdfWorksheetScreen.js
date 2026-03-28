import React, { useState } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    Alert,
    ActivityIndicator,
    Switch,
    StatusBar
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';

const AIPdfWorksheetScreen = ({ navigation, route, user }) => {
    // Aggregated AI Data
    const mcqs = route.params?.allMcqs || [];
    const flashcards = route.params?.allCards || [];
    const pdfNames = route.params?.subjectNames || 'Combined PDF Material';

    // Config State
    const [totalMarks, setTotalMarks] = useState(25);
    const [includeMCQs, setIncludeMCQs] = useState(true);
    const [includeFlashcards, setIncludeFlashcards] = useState(true);

    // UI State
    const [generating, setGenerating] = useState(false);

    const generateWorksheet = async () => {
        if (!includeMCQs && !includeFlashcards) {
            Alert.alert('Selection Error', 'Please enable at least one content type.');
            return;
        }

        setGenerating(true);

        try {
            // Randomize Data Source
            const shuffle = (array) => {
                let currentIndex = array.length, randomIndex;
                while (currentIndex !== 0) {
                    randomIndex = Math.floor(Math.random() * currentIndex);
                    currentIndex--;
                    [array[currentIndex], array[randomIndex]] = [array[randomIndex], array[currentIndex]];
                }
                return array;
            };

            let finalMCQs = [];
            let finalShort = [];
            let currentMarks = 0;
            const targetMarks = totalMarks;

            // Distribute Marks
            const weightShort = includeFlashcards ? 30 : 0;
            const weightMCQ = includeMCQs ? 20 : 0;
            const totalWeight = weightShort + weightMCQ;

            let quotaShort = totalWeight > 0 ? (weightShort / totalWeight) * targetMarks : 0;
            let quotaMCQ = totalWeight > 0 ? (weightMCQ / totalWeight) * targetMarks : 0;

            // Flashcards (Short Answer) takes 2 marks each
            if (includeFlashcards && flashcards.length > 0) {
                const maxQuestions = Math.floor(quotaShort / 2);
                const count = Math.max(1, Math.min(maxQuestions, flashcards.length));
                finalShort = shuffle([...flashcards]).slice(0, count);
                currentMarks += finalShort.length * 2;
            }

            // MCQs take whatever is left
            if (includeMCQs && mcqs.length > 0) {
                const remaining = targetMarks - currentMarks;
                const count = Math.max(5, Math.min(remaining, mcqs.length));
                finalMCQs = shuffle([...mcqs]).slice(0, count);
                currentMarks += finalMCQs.length * 1;
            }
            
            // Build Final HTML Content
            const html = createHTML(finalMCQs, finalShort, pdfNames, currentMarks);

            // Trigger PDF Generation and Share
            const { uri } = await Print.printToFileAsync({ html });
            await Sharing.shareAsync(uri, { UTI: '.pdf', mimeType: 'application/pdf' });

        } catch (error) {
            Alert.alert('Error', 'Failed to generate worksheet document.');
            console.error(error);
        } finally {
            setGenerating(false);
        }
    };

    const createHTML = (compiledMcqs, compiledShort, subjectTitle, currentMarks) => {
        const studentName = user?.name || "Student";
        const date = new Date().toLocaleDateString();

        let mcqSection = "";
        let shortSection = "";
        let answerKey = "";

        if (compiledMcqs.length > 0) {
            mcqSection += `<h3>Section A: Multiple Choice Questions (1 Mark each)</h3><ol>`;
            answerKey += `<h4>Section A Answers</h4><ol>`;
            compiledMcqs.forEach(q => {
                mcqSection += `
                <li class="question-item">
                    <div class="question-text">${q.question || q.question_text || q.q}</div>
                    <div class="options-grid">
                        <div class="option">(A) ${q.option_a || q.options?.[0]}</div>
                        <div class="option">(B) ${q.option_b || q.options?.[1]}</div>
                        <div class="option">(C) ${q.option_c || q.options?.[2]}</div>
                        <div class="option">(D) ${q.option_d || q.options?.[3]}</div>
                    </div>
                </li>`;
                answerKey += `<li>${q.correct_answer || q.answer}</li>`;
            });
            mcqSection += `</ol>`;
            answerKey += `</ol>`;
        }

        if (compiledShort.length > 0) {
            shortSection += `<h3>Section B: Short Answer Questions (2 Marks each)</h3><ol>`;
            answerKey += `<h4>Section B Answers</h4><ol>`;
            compiledShort.forEach(q => {
                const questionText = q.question_front || q.question || q.front || q.q || "Question";
                const answerText = q.answer_back || q.answer || q.back || q.a || "Answer";
                shortSection += `<li class="question-item"><div class="question-text">${questionText}</div><br/><br/><br/></li>`;
                answerKey += `<li>${answerText}</li>`;
            });
            shortSection += `</ol>`;
            answerKey += `</ol>`;
        }

        return `
        <html>
          <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no" />
            <style>
              @page { margin: 70px 80px; }
              body { font-family: 'Arial', 'Segoe UI', sans-serif; margin: 0; padding: 0; color: #333; line-height: 1.6; }
              .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 35px; }
              .header h1 { margin: 0; font-size: 26px; color: #A855F7; text-transform: uppercase; letter-spacing: 1px; }
              .header p { margin: 8px 0; font-size: 15px; color: #666; }
              .details { display: flex; justify-content: space-between; margin-bottom: 35px; font-weight: bold; border: 1px solid #ddd; padding: 12px 15px; font-size: 15px; }
              h3 { border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-top: 35px; margin-bottom: 20px; color: #444; font-size: 18px; }
              .question-item { margin-bottom: 20px; page-break-inside: avoid; line-height: 1.7; }
              .question-text { font-weight: 500; font-size: 16px; line-height: 1.7; }
              .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 8px; font-size: 14px; color: #555; line-height: 1.6; }
              .option { padding: 2px 0; }
              .page-break { page-break-before: always; }
              .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 100px; color: rgba(0,0,0,0.03); z-index: -1; pointer-events: none; }
            </style>
          </head>
          <body>
            <div class="watermark">VEERU APP</div>
            <div class="header">
              <h1>WORKSHEET</h1>
              <p>Subjects: ${subjectTitle}</p>
            </div>
            <div class="details">
              <span>Name: ____________________</span>
              <span>Date: ${date}</span>
              <span>Total Marks: ${Math.round(currentMarks)}</span>
            </div>
            ${mcqSection}
            ${shortSection}
            <div class="page-break"></div>
            <div class="answer-key-section">
              <div class="header">
                <h1>Answer Key</h1>
              </div>
              ${answerKey}
            </div>
          </body>
        </html>
        `;
    };

    return (
        <View style={styles.mainWrapper}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />

            <LinearGradient colors={['#A855F7', '#C026D3']} style={styles.headerGradient}>
                <SafeAreaView edges={['top']} style={styles.headerSafe}>
                    <View style={styles.header}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Ionicons name="arrow-back" size={24} color="white" />
                        </TouchableOpacity>
                        <View>
                            <Text style={styles.headerTitle}>Worksheet Generator</Text>
                            <Text style={styles.headerSubtitle}>{mcqs.length} MCQs & {flashcards.length} Short Answers loaded</Text>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <ScrollView contentContainerStyle={styles.content}>
                
                <View style={styles.infoBox}>
                    <Ionicons name="documents" size={24} color="#C026D3" style={{ marginRight: 15 }} />
                    <View style={{ flex: 1 }}>
                        <Text style={styles.infoBoxTitle}>AI Extracted Formats Ready</Text>
                        <Text style={styles.infoBoxText}>Your PDF content has been successfully processed into multiple formats for printing.</Text>
                    </View>
                </View>

                {/* Step 3: Config */}
                <View style={styles.section}>
                    <Text style={styles.sectionHeader}>Customize Paper Grading</Text>

                    <View style={styles.card}>
                        <Text style={styles.label}>Select Total Marks: {totalMarks}</Text>
                        <View style={styles.markButtonsContainer}>
                            {[25, 40, 50, 80, 100].map((mark) => (
                                <TouchableOpacity
                                    key={mark}
                                    style={[
                                        styles.markButton,
                                        totalMarks === mark && styles.markButtonSelected
                                    ]}
                                    onPress={() => setTotalMarks(mark)}
                                >
                                    <Text style={[
                                        styles.markButtonText,
                                        totalMarks === mark && styles.markButtonTextSelected
                                    ]}>
                                        {mark}
                                    </Text>
                                </TouchableOpacity>
                            ))}
                        </View>
                    </View>

                    <View style={styles.card}>
                        <View style={styles.switchRow}>
                            <Text style={styles.switchLabel}>Include MCQs (1 Mark)</Text>
                            <Switch value={includeMCQs} onValueChange={setIncludeMCQs} trackColor={{ true: '#C026D3' }} />
                        </View>
                        <View style={styles.switchRow}>
                            <Text style={styles.switchLabel}>Short Answers (2 Marks)</Text>
                            <Switch value={includeFlashcards} onValueChange={setIncludeFlashcards} trackColor={{ true: '#C026D3' }} />
                        </View>
                    </View>

                    <TouchableOpacity
                        style={styles.generateBtn}
                        onPress={generateWorksheet}
                        disabled={generating || (mcqs.length === 0 && flashcards.length === 0)}
                    >
                        <LinearGradient colors={['#2563eb', '#3b82f6']} style={styles.btnGradient}>
                            {generating ? <ActivityIndicator color="white" /> : (
                                <>
                                    <Ionicons name="print" size={24} color="white" style={{ marginRight: 10 }} />
                                    <Text style={styles.btnText}>Build Interactive PDF</Text>
                                </>
                            )}
                        </LinearGradient>
                    </TouchableOpacity>
                </View>
            </ScrollView>
        </View>
    );
};

const styles = StyleSheet.create({
    mainWrapper: { flex: 1, backgroundColor: '#f8fafc' },
    headerGradient: { paddingBottom: 20 },
    header: { flexDirection: 'row', padding: 20, alignItems: 'center' },
    backButton: { marginRight: 15 },
    headerTitle: { fontSize: 22, fontWeight: 'bold', color: 'white' },
    headerSubtitle: { fontSize: 13, color: 'rgba(255,255,255,0.9)' },
    content: { padding: 20, paddingBottom: 50 },
    section: { marginBottom: 30 },
    sectionHeader: { fontSize: 18, fontWeight: 'bold', color: '#1e293b', marginBottom: 15 },
    
    infoBox: { flexDirection: 'row', backgroundColor: '#fdf4ff', borderRadius: 12, padding: 15, marginBottom: 25, borderWidth: 1, borderColor: '#f0abfc', alignItems: 'center' },
    infoBoxTitle: { fontWeight: 'bold', color: '#86198f', fontSize: 15, marginBottom: 4 },
    infoBoxText: { color: '#a21caf', fontSize: 14, lineHeight: 20 },

    card: { backgroundColor: 'white', borderRadius: 12, padding: 15, marginBottom: 15, elevation: 2 },
    label: { fontSize: 15, fontWeight: '600', marginBottom: 10, color: '#1e293b' },
    switchLabel: { fontSize: 15, color: '#334155' },
    switchRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 8 },
    generateBtn: { borderRadius: 16, overflow: 'hidden', marginTop: 10, elevation: 4 },
    btnGradient: { padding: 18, flexDirection: 'row', justifyContent: 'center', alignItems: 'center' },
    btnText: { color: 'white', fontSize: 18, fontWeight: 'bold' },
    markButtonsContainer: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 5 },
    markButton: { flex: 1, paddingVertical: 12, marginHorizontal: 3, borderRadius: 12, backgroundColor: '#f1f5f9', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#e2e8f0' },
    markButtonSelected: { backgroundColor: '#C026D3', borderColor: '#C026D3', elevation: 3 },
    markButtonText: { fontSize: 14, fontWeight: '700', color: '#64748b' },
    markButtonTextSelected: { color: 'white' },
});

export default AIPdfWorksheetScreen;
