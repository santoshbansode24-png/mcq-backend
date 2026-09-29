import React, { useState, useEffect } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    Alert,
    ActivityIndicator,
    Switch,
    StatusBar,
    TextInput
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
// Removed Slider import
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';

import { useTheme } from '../context/ThemeContext';
import { fetchSubjects } from '../api/subjects';
import { fetchChapters } from '../api/chapters';
import { fetchMCQs, fetchFlashcards, fetchQuickRevision } from '../api/content';

// Memoized Subject Card
const SubjectCard = React.memo(({ subject, index, isSelected, onPress }) => {
    const gradients = [
        ['#FF6B6B', '#FFD93D'],  // Vibrant Coral to Golden Yellow
        ['#4ECDC4', '#44A8FF'],  // Turquoise to Sky Blue
        ['#A8E6CF', '#56CCF2'],  // Mint Green to Ocean Blue
        ['#FF8C42', '#FF3E96'],  // Orange to Hot Pink
        ['#667EEA', '#764BA2'],  // Indigo to Purple
        ['#F093FB', '#F5576C']   // Pink to Coral Red
    ];
    const colors = gradients[index % gradients.length];

    return (
        <TouchableOpacity
            style={[styles.subjectCard, isSelected && styles.subjectCardSelected]}
            onPress={() => onPress(subject)}
            activeOpacity={0.8}
        >
            <LinearGradient
                colors={isSelected ? ['#A855F7', '#C026D3'] : colors}
                style={styles.subjectGradient}
            >
                <Text style={styles.subjectInitial}>{subject.subject_name.charAt(0)}</Text>
                <Text style={styles.subjectName}>{subject.subject_name}</Text>
                {isSelected && <Text style={styles.checkBadge}>✓</Text>}
            </LinearGradient>
        </TouchableOpacity>
    );
});

// Memoized Chapter Item
const ChapterItem = React.memo(({ chapter, isSelected, onPress }) => {
    return (
        <TouchableOpacity
            style={[styles.chapterItem, isSelected && styles.chapterItemSelected]}
            onPress={() => onPress(chapter.chapter_id)}
        >
            <View style={[styles.checkbox, isSelected && styles.checkboxSelected]}>
                {isSelected && <Ionicons name="checkmark" size={16} color="white" />}
            </View>
            <Text style={[styles.chapterText, isSelected && { color: '#C026D3', fontWeight: 'bold' }]}>
                {chapter.chapter_name}
            </Text>
        </TouchableOpacity>
    );
});

const WorksheetGeneratorScreen = ({ navigation, user }) => {
    const { theme, isDarkMode } = useTheme();

    // Data State
    const [subjects, setSubjects] = useState([]);
    const [chapters, setChapters] = useState([]); // Array of { subjectName, data: [] }

    // Selection State
    const [selectedSubjects, setSelectedSubjects] = useState([]);
    const [selectedChapterIds, setSelectedChapterIds] = useState([]);

    // Config State
    const [mode, setMode] = useState('auto'); // 'auto' | 'manual'
    const [totalMarks, setTotalMarks] = useState(25);
    const [includeMCQs, setIncludeMCQs] = useState(true);
    const [includeFlashcards, setIncludeFlashcards] = useState(true);
    const [includeRevision, setIncludeRevision] = useState(false);

    // Manual Marks Breakdown State
    const [manualMCQMarks, setManualMCQMarks] = useState('10');
    const [manualShortMarks, setManualShortMarks] = useState('10');
    const [manualLongMarks, setManualLongMarks] = useState('20');

    // Memoized Manual Calculation Values
    const {
        manualMCQCount,
        manualShortCount,
        manualLongCount,
        manualTotalMarks,
        manualTotalQuestions
    } = React.useMemo(() => {
        const mcqMarks = parseInt(manualMCQMarks, 10) || 0;
        const shortMarks = parseInt(manualShortMarks, 10) || 0;
        const longMarks = parseInt(manualLongMarks, 10) || 0;

        const mcqCount = Math.floor(mcqMarks / 1);
        const shortCount = Math.floor(shortMarks / 2);
        const longCount = Math.floor(longMarks / 5);

        return {
            manualMCQCount: mcqCount,
            manualShortCount: shortCount,
            manualLongCount: longCount,
            manualTotalMarks: (mcqCount * 1) + (shortCount * 2) + (longCount * 5),
            manualTotalQuestions: mcqCount + shortCount + longCount
        };
    }, [manualMCQMarks, manualShortMarks, manualLongMarks]);

    // UI State
    const [loading, setLoading] = useState(false);
    const [loadingChapters, setLoadingChapters] = useState(false);
    const [generating, setGenerating] = useState(false);

    // Load Subjects on Mount
    useEffect(() => {
        loadSubjects();
    }, []);

    // Load Chapters when Selected Subjects Change
    useEffect(() => {
        if (selectedSubjects.length > 0) {
            loadChapters();
        } else {
            setChapters([]);
            setSelectedChapterIds([]);
        }
    }, [selectedSubjects]);

    const loadSubjects = async () => {
        setLoading(true);
        try {
            const classId = user?.class_id || 10;
            const response = await fetchSubjects(classId);
            if (response && (response.status === 'success' || Array.isArray(response))) {
                setSubjects(response.data || response);
            } else if (response?.message !== 'No class selected' && response?.message !== 'No subjects found for this class') {
                Alert.alert('Error', response?.message || 'Failed to load subjects');
            }
        } catch (error) {
            Alert.alert('Connection Error', 'Failed to load subjects. Please check your network connection.');
            console.error("Failed to load subjects", error);
        } finally {
            setLoading(false);
        }
    };

    const loadChapters = async () => {
        setLoadingChapters(true);
        try {
            const promises = selectedSubjects.map(subject =>
                fetchChapters(subject.subject_id)
                    .then(res => ({
                        subjectName: subject.subject_name,
                        subjectId: subject.subject_id,
                        data: res.status === 'success' ? res.data : []
                    }))
                    .catch(() => ({
                        subjectName: subject.subject_name,
                        subjectId: subject.subject_id,
                        data: []
                    }))
            );

            const results = await Promise.all(promises);
            const groupedChapters = results.filter(r => r.data.length > 0);
            setChapters(groupedChapters);

            // Cleanup invalid selections
            const allVisibleIds = groupedChapters.flatMap(g => g.data.map(c => c.chapter_id));
            setSelectedChapterIds(prev => prev.filter(id => allVisibleIds.includes(id)));

        } catch (error) {
            console.error("Failed to load chapters", error);
        } finally {
            setLoadingChapters(false);
        }
    };

    // Toggles
    const toggleSubject = React.useCallback((subject) => {
        setSelectedSubjects(prev => {
            const exists = prev.find(s => s.subject_id === subject.subject_id);
            if (exists) return prev.filter(s => s.subject_id !== subject.subject_id);
            return [...prev, subject];
        });
    }, []);

    const toggleChapter = React.useCallback((chapterId) => {
        setSelectedChapterIds(prev => {
            if (prev.includes(chapterId)) return prev.filter(id => id !== chapterId);
            return [...prev, chapterId];
        });
    }, []);

    const selectAllChapters = () => {
        const allIds = chapters.flatMap(group => group.data.map(c => c.chapter_id));
        if (selectedChapterIds.length === allIds.length) setSelectedChapterIds([]);
        else setSelectedChapterIds(allIds);
    };

    // --- Generation Logic ---

    const generateWorksheet = async () => {
        if (selectedChapterIds.length === 0) {
            Alert.alert('Selection Error', 'Please select at least one chapter.');
            return;
        }

        if (mode === 'auto') {
            if (!includeMCQs && !includeFlashcards && !includeRevision) {
                Alert.alert('Selection Error', 'Please select at least one content type.');
                return;
            }
        } else {
            if (manualTotalMarks <= 0) {
                Alert.alert('Selection Error', 'Please enter marks for at least one question type (MCQ, Short Answers, or Long Answers).');
                return;
            }
        }

        setGenerating(true);

        try {
            let allMCQs = [];
            let allFlashcards = [];
            let allRevision = [];

            const shouldFetchMCQ = mode === 'auto' ? includeMCQs : manualMCQCount > 0;
            const shouldFetchShort = mode === 'auto' ? includeFlashcards : manualShortCount > 0;
            const shouldFetchLong = mode === 'auto' ? includeRevision : manualLongCount > 0;

            // 1. Fetch content for ALL selected chapters
            const promises = selectedChapterIds.map(async (chapterId) => {
                const results = { mcqs: [], flashcards: [], revision: [] };

                if (shouldFetchMCQ) {
                    try {
                        const res = await fetchMCQs(chapterId);
                        if (res.status === 'success') results.mcqs = res.data;
                    } catch (e) { }
                }
                if (shouldFetchShort) {
                    try {
                        const res = await fetchFlashcards(chapterId);
                        if (Array.isArray(res)) results.flashcards = res;
                        else if (res.data) results.flashcards = res.data;
                    } catch (e) { }
                }
                if (shouldFetchLong) {
                    try {
                        const res = await fetchQuickRevision(chapterId);
                        if (res.data) results.revision = res.data;
                    } catch (e) { }
                }
                return results;
            });

            const chapterResults = await Promise.all(promises);

            chapterResults.forEach(res => {
                allMCQs = [...allMCQs, ...res.mcqs];
                allFlashcards = [...allFlashcards, ...res.flashcards];
                allRevision = [...allRevision, ...res.revision];
            });

            // 2. Filter/Randomize (Using Fisher-Yates shuffle for true randomness)
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
            let finalLong = [];
            let currentMarks = 0;

            // --- Long Answer Extraction Logic (Robust) ---
            let extractedQuestions = [];
            if ((mode === 'auto' ? includeRevision : manualLongCount > 0) && allRevision.length > 0) {
                allRevision.forEach(doc => {
                    let points = doc.key_points || doc.content || doc.point; // Handle legacy keys

                    // Safety: Ensure points is parsable if string
                    if (typeof points === 'string' && points.trim().startsWith('[')) {
                        try { points = JSON.parse(points); } catch (e) { }
                    }

                    if (Array.isArray(points)) {
                        // Case A: New format Q&A Array
                        points.forEach(item => {
                            if (item.q && item.a && item.q !== "Question") {
                                extractedQuestions.push({
                                    q: item.q,
                                    a: item.a,
                                    e: item.e || null
                                });
                            }
                        });
                    } else if (typeof points === 'string') {
                        // Case B: Legacy string content
                        extractedQuestions.push({
                            q: `Explain the key concepts of: ${doc.title || "Subject Matter"}`,
                            a: points,
                            e: null
                        });
                    }
                });

                // Fallback: If title extraction works but empty content
                if (extractedQuestions.length === 0 && allRevision.length > 0) {
                    allRevision.forEach(doc => {
                        if (doc.title) {
                            extractedQuestions.push({
                                q: `Write a short note on: ${doc.title}`,
                                a: "Refer to textbook or class notes.",
                                e: null
                            });
                        }
                    });
                }
            }

            if (mode === 'manual') {
                // MANUAL MARKS DISTRIBUTION
                if (manualLongCount > 0 && extractedQuestions.length > 0) {
                    finalLong = shuffle(extractedQuestions).slice(0, manualLongCount);
                    currentMarks += finalLong.length * 5;
                }

                if (manualShortCount > 0 && allFlashcards.length > 0) {
                    finalShort = shuffle([...allFlashcards]).slice(0, manualShortCount);
                    currentMarks += finalShort.length * 2;
                }

                if (manualMCQCount > 0 && allMCQs.length > 0) {
                    finalMCQs = shuffle([...allMCQs]).slice(0, manualMCQCount);
                    currentMarks += finalMCQs.length * 1;
                }
            } else {
                // AUTO DYNAMIC MARKS DISTRIBUTION
                const targetMarks = totalMarks;
                const weightLong = includeRevision ? 50 : 0;
                const weightShort = includeFlashcards ? 30 : 0;
                const weightMCQ = includeMCQs ? 20 : 0;
                const totalWeight = weightLong + weightShort + weightMCQ;

                let quotaLong = totalWeight > 0 ? (weightLong / totalWeight) * targetMarks : 0;
                let quotaShort = totalWeight > 0 ? (weightShort / totalWeight) * targetMarks : 0;
                let quotaMCQ = totalWeight > 0 ? (weightMCQ / totalWeight) * targetMarks : 0;

                if (includeRevision && extractedQuestions.length > 0) {
                    const maxQuestions = Math.floor(quotaLong / 5);
                    const count = Math.min(Math.max(1, maxQuestions), extractedQuestions.length);
                    finalLong = shuffle(extractedQuestions).slice(0, count);
                    currentMarks += finalLong.length * 5;
                }

                if (includeFlashcards && allFlashcards.length > 0) {
                    const remainingForOthers = targetMarks - currentMarks;
                    let limit = 0;

                    if (!includeMCQs) {
                        limit = remainingForOthers;
                    } else {
                        const remainingWeight = weightShort + weightMCQ;
                        limit = remainingWeight > 0 ? (weightShort / remainingWeight) * remainingForOthers : remainingForOthers;
                    }

                    const maxQuestions = Math.floor(limit / 2);
                    const count = Math.min(Math.max(1, maxQuestions), allFlashcards.length);

                    finalShort = shuffle([...allFlashcards]).slice(0, count);
                    currentMarks += finalShort.length * 2;
                }

                if (includeMCQs && allMCQs.length > 0) {
                    const remaining = targetMarks - currentMarks;
                    const count = Math.min(Math.max(remaining, 5), allMCQs.length);
                    finalMCQs = shuffle([...allMCQs]).slice(0, count);
                    currentMarks += finalMCQs.length * 1;
                }
            }

            if (finalMCQs.length === 0 && finalShort.length === 0 && finalLong.length === 0) {
                Alert.alert('No Questions Found', 'Could not find questions for the selected chapters and categories.');
                return;
            }

            // 3. Generate HTML
            const subjectNames = selectedSubjects.map(s => s.subject_name).join(' & ');
            const html = createHTML(finalMCQs, finalShort, finalLong, subjectNames, currentMarks);

            // 4. Print
            const { uri } = await Print.printToFileAsync({ html });
            await Sharing.shareAsync(uri, { UTI: '.pdf', mimeType: 'application/pdf' });

        } catch (error) {
            Alert.alert('Error', 'Failed to generate worksheet.');
            console.error(error);
        } finally {
            setGenerating(false);
        }
    };

    const createHTML = (mcqs, short, long, subjectTitle, currentMarks) => {
        const studentName = user?.name || "Student";
        const date = new Date().toLocaleDateString();

        let mcqSection = "";
        let shortSection = "";
        let longSection = "";
        let answerKey = "";

        if (mcqs.length > 0) {
            mcqSection += `<h3>Section A: Multiple Choice Questions (1 Mark each)</h3><ol>`;
            answerKey += `<h4>Section A Answers</h4><ol>`;
            mcqs.forEach(q => {
                mcqSection += `
                <li class="question-item">
                    <div class="question-text">${q.question || q.question_text}</div>
                    <div class="options-grid">
                        <div class="option">(A) ${q.option_a}</div>
                        <div class="option">(B) ${q.option_b}</div>
                        <div class="option">(C) ${q.option_c}</div>
                        <div class="option">(D) ${q.option_d}</div>
                    </div>
                </li>`;
                answerKey += `<li>${q.correct_answer}</li>`;
            });
            mcqSection += `</ol>`;
            answerKey += `</ol>`;
        }

        if (short.length > 0) {
            shortSection += `<h3>Section B: Short Answer Questions (2 Marks each)</h3><ol>`;
            answerKey += `<h4>Section B Answers</h4><ol>`;
            short.forEach(q => {
                const questionText = q.question_front || q.question || q.front || "Question";
                const answerText = q.answer_back || q.answer || q.back || "Answer";
                shortSection += `<li class="question-item"><div class="question-text">${questionText}</div><br/><br/><br/></li>`;
                answerKey += `<li>${answerText}</li>`;
            });
            shortSection += `</ol>`;
            answerKey += `</ol>`;
        }

        if (long.length > 0) {
            longSection += `<h3>Section C: Long Answer Questions (5 Marks each)</h3><ol>`;
            answerKey += `<h4>Section C Model Answers</h4><ul style="list-style-type: none; padding: 0;">`;
            long.forEach((q, index) => {
                // Handle legacy string answers by wrapping in ul
                const cleanAnswer = (typeof q.a === 'string' && q.a.includes('\\n')) ?
                    '<ul>' + q.a.split('\\n').map(l => `<li>${l}</li>`).join('') + '</ul>' :
                    q.a;

                longSection += `<li class="question-item"><div class="question-text">${q.q}</div><br/><br/><br/><br/><br/></li>`;

                answerKey += `
               <li style="margin-bottom: 20px; border-bottom: 1px dashed #ccc; padding-bottom: 15px;">
                   <div style="font-weight:bold; margin-bottom:5px;">Q${index + 1}: ${q.q}</div>
                   <div style="color: #2563eb; margin-bottom:5px;">Ans: ${cleanAnswer}</div>
                   ${q.e ? `<div style="font-size: 0.9em; color: #555; background:#f9f9f9; padding:5px; border-left: 3px solid #ccc;"><em>Note: ${q.e}</em></div>` : ''}
               </li>`;
            });
            longSection += `</ol>`;
            answerKey += `</ul>`;
        }

        return `
        <html>
          <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no" />
            <style>
              @page {
                margin: 70px 80px;
              }
              body { 
                font-family: 'Arial', 'Segoe UI', sans-serif; 
                margin: 0;
                padding: 0;
                color: #333; 
                line-height: 1.6;
              }
              .content-wrapper {
                padding: 0;
              }
              .header { 
                text-align: center; 
                border-bottom: 2px solid #333; 
                padding-bottom: 20px; 
                margin-bottom: 35px; 
              }
              .header h1 { 
                margin: 0; 
                font-size: 26px; 
                color: #4A00E0; 
                text-transform: uppercase; 
                letter-spacing: 1px;
              }
              .header p { 
                margin: 8px 0; 
                font-size: 15px; 
                color: #666; 
              }
              .details { 
                display: flex; 
                justify-content: space-between; 
                margin-bottom: 35px; 
                font-weight: bold; 
                border: 1px solid #ddd; 
                padding: 12px 15px; 
                font-size: 15px;
              }
              h3 { 
                border-bottom: 1px solid #ddd; 
                padding-bottom: 8px; 
                margin-top: 35px; 
                margin-bottom: 20px;
                color: #444; 
                font-size: 18px;
              }
              .question-item { 
                margin-bottom: 20px; 
                page-break-inside: avoid; 
                line-height: 1.7;
              }
              .question-text { 
                font-weight: 500; 
                font-size: 16px; 
                line-height: 1.7;
              }
              .options-grid { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 10px; 
                margin-top: 8px; 
                font-size: 14px; 
                color: #555; 
                line-height: 1.6;
              }
              .option {
                padding: 2px 0;
              }
              .page-break { 
                page-break-before: always; 
              }
              .answer-key-section {
                padding-top: 0;
              }
              .watermark { 
                position: fixed; 
                top: 50%; 
                left: 50%; 
                transform: translate(-50%, -50%) rotate(-45deg); 
                font-size: 100px; 
                color: rgba(0,0,0,0.03); 
                z-index: -1; 
                pointer-events: none; 
              }
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
            ${longSection}

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
                            <Text style={styles.headerSubtitle}>Create custom printable exams</Text>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <ScrollView contentContainerStyle={styles.content}>

                {/* Step 1: Subjects */}
                <View style={styles.section}>
                    <Text style={styles.sectionHeader}>1. Select Subjects</Text>
                    {loading ? (
                        <ActivityIndicator size="large" color="#C026D3" />
                    ) : (
                        <View style={styles.grid}>
                            {subjects.map((subject, index) => (
                                <SubjectCard
                                    key={subject.subject_id}
                                    subject={subject}
                                    index={index}
                                    isSelected={!!selectedSubjects.find(s => s.subject_id === subject.subject_id)}
                                    onPress={toggleSubject}
                                />
                            ))}
                        </View>
                    )}
                </View>

                {/* Step 2: Chapters */}
                {selectedSubjects.length > 0 && (
                    <View style={styles.section}>
                        <View style={styles.rowBetween}>
                            <Text style={styles.sectionHeader}>2. Select Chapters</Text>
                            <TouchableOpacity onPress={selectAllChapters}>
                                <Text style={styles.linkText}>Select All / None</Text>
                            </TouchableOpacity>
                        </View>

                        {loadingChapters ? (
                            <ActivityIndicator size="large" color="#C026D3" />
                        ) : (
                            <View>
                                {chapters.map((group) => (
                                    <View key={group.subjectId} style={{ marginBottom: 15 }}>
                                        <Text style={styles.groupTitle}>{group.subjectName}</Text>
                                        {group.data.map(chapter => (
                                            <ChapterItem
                                                key={chapter.chapter_id}
                                                chapter={chapter}
                                                isSelected={selectedChapterIds.includes(chapter.chapter_id)}
                                                onPress={toggleChapter}
                                            />
                                        ))}
                                    </View>
                                ))}
                            </View>
                        )}
                    </View>
                )}

                {/* Step 3: Config */}
                {selectedChapterIds.length > 0 && (
                    <View style={styles.section}>
                        <Text style={styles.sectionHeader}>3. Customize Paper</Text>

                        {/* Segmented Mode Switch */}
                        <View style={styles.segmentedContainer}>
                            <TouchableOpacity
                                style={[styles.segmentBtn, mode === 'auto' && styles.segmentBtnActive]}
                                onPress={() => setMode('auto')}
                                activeOpacity={0.8}
                            >
                                <Ionicons
                                    name="flash"
                                    size={16}
                                    color={mode === 'auto' ? 'white' : '#64748b'}
                                    style={{ marginRight: 6 }}
                                />
                                <Text style={[styles.segmentBtnText, mode === 'auto' && styles.segmentBtnTextActive]}>
                                    Auto Preset
                                </Text>
                            </TouchableOpacity>

                            <TouchableOpacity
                                style={[styles.segmentBtn, mode === 'manual' && styles.segmentBtnActive]}
                                onPress={() => setMode('manual')}
                                activeOpacity={0.8}
                            >
                                <Ionicons
                                    name="create"
                                    size={16}
                                    color={mode === 'manual' ? 'white' : '#64748b'}
                                    style={{ marginRight: 6 }}
                                />
                                <Text style={[styles.segmentBtnText, mode === 'manual' && styles.segmentBtnTextActive]}>
                                    Manual Breakdown
                                </Text>
                            </TouchableOpacity>
                        </View>

                        {mode === 'auto' ? (
                            <>
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
                                    <View style={styles.switchRow}>
                                        <Text style={styles.switchLabel}>Long Answers (5 Marks)</Text>
                                        <Switch value={includeRevision} onValueChange={setIncludeRevision} trackColor={{ true: '#C026D3' }} />
                                    </View>
                                </View>
                            </>
                        ) : (
                            /* Manual Marks Breakdown Card */
                            <View style={styles.card}>
                                <Text style={styles.label}>Enter Marks Breakdown</Text>
                                <Text style={styles.sublabel}>
                                    Specify marks for each section. Questions are calculated automatically.
                                </Text>

                                {/* MCQ Row */}
                                <View style={styles.manualRow}>
                                    <View style={styles.manualInfo}>
                                        <View style={styles.manualTypeBadge}>
                                            <Text style={styles.manualTypeBadgeText}>1 Mark Each</Text>
                                        </View>
                                        <Text style={styles.manualTitle}>Multiple Choice (MCQ)</Text>
                                        <Text style={styles.manualSubtitle}>
                                            {manualMCQCount} {manualMCQCount === 1 ? 'Question' : 'Questions'} ({manualMCQCount * 1} Marks)
                                        </Text>
                                    </View>
                                    <View style={styles.manualInputWrapper}>
                                        <TextInput
                                            style={styles.manualInput}
                                            value={manualMCQMarks}
                                            onChangeText={(val) => setManualMCQMarks(val.replace(/[^0-9]/g, ''))}
                                            keyboardType="number-pad"
                                            maxLength={3}
                                            placeholder="0"
                                            placeholderTextColor="#94a3b8"
                                        />
                                        <Text style={styles.manualInputUnit}>Marks</Text>
                                    </View>
                                </View>

                                <View style={styles.divider} />

                                {/* Short Answer Row */}
                                <View style={styles.manualRow}>
                                    <View style={styles.manualInfo}>
                                        <View style={[styles.manualTypeBadge, { backgroundColor: '#e0f2fe' }]}>
                                            <Text style={[styles.manualTypeBadgeText, { color: '#0284c7' }]}>2 Marks Each</Text>
                                        </View>
                                        <Text style={styles.manualTitle}>Short Answers</Text>
                                        <Text style={styles.manualSubtitle}>
                                            {manualShortCount} {manualShortCount === 1 ? 'Question' : 'Questions'} ({manualShortCount * 2} Marks)
                                        </Text>
                                    </View>
                                    <View style={styles.manualInputWrapper}>
                                        <TextInput
                                            style={styles.manualInput}
                                            value={manualShortMarks}
                                            onChangeText={(val) => setManualShortMarks(val.replace(/[^0-9]/g, ''))}
                                            keyboardType="number-pad"
                                            maxLength={3}
                                            placeholder="0"
                                            placeholderTextColor="#94a3b8"
                                        />
                                        <Text style={styles.manualInputUnit}>Marks</Text>
                                    </View>
                                </View>

                                <View style={styles.divider} />

                                {/* Long Answer Row */}
                                <View style={styles.manualRow}>
                                    <View style={styles.manualInfo}>
                                        <View style={[styles.manualTypeBadge, { backgroundColor: '#fef3c7' }]}>
                                            <Text style={[styles.manualTypeBadgeText, { color: '#d97706' }]}>5 Marks Each</Text>
                                        </View>
                                        <Text style={styles.manualTitle}>Long Answers</Text>
                                        <Text style={styles.manualSubtitle}>
                                            {manualLongCount} {manualLongCount === 1 ? 'Question' : 'Questions'} ({manualLongCount * 5} Marks)
                                        </Text>
                                    </View>
                                    <View style={styles.manualInputWrapper}>
                                        <TextInput
                                            style={styles.manualInput}
                                            value={manualLongMarks}
                                            onChangeText={(val) => setManualLongMarks(val.replace(/[^0-9]/g, ''))}
                                            keyboardType="number-pad"
                                            maxLength={3}
                                            placeholder="0"
                                            placeholderTextColor="#94a3b8"
                                        />
                                        <Text style={styles.manualInputUnit}>Marks</Text>
                                    </View>
                                </View>

                                {/* Summary Box */}
                                <View style={styles.summaryBox}>
                                    <View style={styles.summaryItem}>
                                        <Text style={styles.summaryLabel}>Total Questions</Text>
                                        <Text style={styles.summaryValue}>{manualTotalQuestions}</Text>
                                    </View>
                                    <View style={styles.summaryDivider} />
                                    <View style={styles.summaryItem}>
                                        <Text style={styles.summaryLabel}>Total Paper Marks</Text>
                                        <Text style={[styles.summaryValue, { color: '#C026D3' }]}>{manualTotalMarks}</Text>
                                    </View>
                                </View>
                            </View>
                        )}

                        <TouchableOpacity
                            style={styles.generateBtn}
                            onPress={generateWorksheet}
                            disabled={generating}
                        >
                            <LinearGradient colors={['#2563eb', '#3b82f6']} style={styles.btnGradient}>
                                {generating ? <ActivityIndicator color="white" /> : (
                                    <>
                                        <Ionicons name="print" size={24} color="white" style={{ marginRight: 10 }} />
                                        <Text style={styles.btnText}>
                                            Generate PDF ({mode === 'manual' ? `${manualTotalMarks} Marks` : `${selectedChapterIds.length} Chapters`})
                                        </Text>
                                    </>
                                )}
                            </LinearGradient>
                        </TouchableOpacity>
                    </View>
                )}

            </ScrollView>
        </View>
    );
};

const styles = StyleSheet.create({
    mainWrapper: { flex: 1, backgroundColor: '#f8fafc' },
    headerGradient: { paddingBottom: 20 },
    header: { flexDirection: 'row', padding: 20, alignItems: 'center' },
    backButton: { marginRight: 15 },
    headerTitle: { fontSize: 22, fontWeight: 'bold', color: 'white', fontFamily: 'NotoSans-Bold' },
    headerSubtitle: { fontSize: 13, color: 'rgba(255,255,255,0.9)', fontFamily: 'NotoSans-Regular' },
    content: { padding: 20, paddingBottom: 50 },
    section: { marginBottom: 30 },
    sectionHeader: { fontSize: 18, fontWeight: 'bold', color: '#1e293b', marginBottom: 15, fontFamily: 'NotoSans-Bold' },
    grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
    subjectCard: { width: '48%', borderRadius: 16, overflow: 'hidden', elevation: 3 },
    subjectCardSelected: { transform: [{ scale: 1.02 }], elevation: 6 },
    subjectGradient: { padding: 20, alignItems: 'center', justifyContent: 'center', minHeight: 110 },
    subjectInitial: { fontSize: 32, fontWeight: 'bold', color: 'white', opacity: 0.9 },
    subjectName: { color: 'white', fontWeight: 'bold', marginTop: 5, textAlign: 'center' },
    checkBadge: { position: 'absolute', top: 10, right: 10, color: 'white', fontWeight: 'bold', fontSize: 16 },
    rowBetween: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 15 },
    linkText: { color: '#C026D3', fontWeight: '600' },
    groupTitle: { fontSize: 14, fontWeight: 'bold', color: '#64748b', textTransform: 'uppercase', marginBottom: 8, marginTop: 10 },
    chapterItem: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'white', padding: 15, borderRadius: 12, marginBottom: 8, borderWidth: 1, borderColor: '#e2e8f0' },
    chapterItemSelected: { borderColor: '#C026D3', backgroundColor: '#fdf4ff' },
    checkbox: { width: 22, height: 22, borderRadius: 6, borderWidth: 2, borderColor: '#cbd5e1', marginRight: 12, alignItems: 'center', justifyContent: 'center' },
    checkboxSelected: { backgroundColor: '#C026D3', borderColor: '#C026D3' },
    chapterText: { fontSize: 15, color: '#334155', flex: 1 },
    card: { backgroundColor: 'white', borderRadius: 12, padding: 15, marginBottom: 15, elevation: 2 },
    label: { fontSize: 15, fontWeight: '600', marginBottom: 10, color: '#1e293b', fontFamily: 'NotoSans-Bold' },
    switchLabel: { fontSize: 15, color: '#334155', fontFamily: 'NotoSans-Regular' },
    slider: { width: '100%', height: 40, marginTop: 5 },
    switchRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 8 },
    generateBtn: { borderRadius: 16, overflow: 'hidden', marginTop: 10, elevation: 4 },
    btnGradient: { padding: 18, flexDirection: 'row', justifyContent: 'center', alignItems: 'center' },
    btnText: { color: 'white', fontSize: 18, fontWeight: 'bold' },
    markButtonsContainer: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        marginTop: 5,
    },
    markButton: {
        flex: 1,
        paddingVertical: 12,
        marginHorizontal: 3,
        borderRadius: 12,
        backgroundColor: '#f1f5f9',
        alignItems: 'center',
        justifyContent: 'center',
        borderWidth: 1,
        borderColor: '#e2e8f0',
    },
    markButtonSelected: {
        backgroundColor: '#C026D3',
        borderColor: '#C026D3',
        elevation: 3,
        shadowColor: '#C026D3',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.3,
        shadowRadius: 4,
    },
    markButtonText: {
        fontSize: 14,
        fontWeight: '700',
        color: '#64748b',
    },
    markButtonTextSelected: {
        color: 'white',
    },
    // Segmented Mode Control
    segmentedContainer: {
        flexDirection: 'row',
        backgroundColor: '#e2e8f0',
        borderRadius: 12,
        padding: 4,
        marginBottom: 16,
    },
    segmentBtn: {
        flex: 1,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 10,
        borderRadius: 9,
    },
    segmentBtnActive: {
        backgroundColor: '#C026D3',
        elevation: 2,
        shadowColor: '#C026D3',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.25,
        shadowRadius: 3,
    },
    segmentBtnText: {
        fontSize: 13,
        fontWeight: '700',
        color: '#64748b',
        fontFamily: 'NotoSans-Bold',
    },
    segmentBtnTextActive: {
        color: 'white',
    },
    sublabel: {
        fontSize: 12,
        color: '#64748b',
        marginBottom: 14,
        fontFamily: 'NotoSans-Regular',
        lineHeight: 18,
    },
    // Manual Input Rows
    manualRow: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingVertical: 10,
    },
    manualInfo: {
        flex: 1,
        paddingRight: 10,
    },
    manualTypeBadge: {
        alignSelf: 'flex-start',
        backgroundColor: '#fdf4ff',
        paddingHorizontal: 8,
        paddingVertical: 3,
        borderRadius: 6,
        marginBottom: 4,
    },
    manualTypeBadgeText: {
        fontSize: 11,
        fontWeight: '700',
        color: '#C026D3',
        fontFamily: 'NotoSans-Bold',
    },
    manualTitle: {
        fontSize: 15,
        fontWeight: '700',
        color: '#1e293b',
        fontFamily: 'NotoSans-Bold',
    },
    manualSubtitle: {
        fontSize: 12,
        color: '#64748b',
        marginTop: 2,
        fontFamily: 'NotoSans-Regular',
    },
    manualInputWrapper: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#f8fafc',
        borderWidth: 1.5,
        borderColor: '#cbd5e1',
        borderRadius: 10,
        paddingHorizontal: 8,
        minWidth: 92,
        height: 42,
        justifyContent: 'center',
    },
    manualInput: {
        fontSize: 16,
        fontWeight: '700',
        color: '#1e293b',
        width: 42,
        textAlign: 'center',
        padding: 0,
    },
    manualInputUnit: {
        fontSize: 12,
        fontWeight: '600',
        color: '#64748b',
        marginLeft: 2,
    },
    divider: {
        height: 1,
        backgroundColor: '#f1f5f9',
        marginVertical: 4,
    },
    // Summary Box
    summaryBox: {
        backgroundColor: '#f8fafc',
        borderRadius: 12,
        padding: 12,
        marginTop: 12,
        borderWidth: 1,
        borderColor: '#e2e8f0',
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-around',
    },
    summaryItem: {
        alignItems: 'center',
    },
    summaryLabel: {
        fontSize: 11,
        color: '#64748b',
        fontWeight: '700',
        textTransform: 'uppercase',
        marginBottom: 2,
        fontFamily: 'NotoSans-Bold',
    },
    summaryValue: {
        fontSize: 18,
        fontWeight: '800',
        color: '#1e293b',
        fontFamily: 'NotoSans-Bold',
    },
    summaryDivider: {
        width: 1,
        height: 28,
        backgroundColor: '#cbd5e1',
    },
});

export default WorksheetGeneratorScreen;
