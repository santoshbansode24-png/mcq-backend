import React, { useState, useEffect, useCallback, useRef } from 'react';
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
    RefreshControl
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';
import { useFocusEffect } from '@react-navigation/native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';
import { API_URL } from '../api/config';

const AIPdfWorksheetScreen = ({ navigation, user }) => {

    const [folders, setFolders] = useState([]);
    const [pdfsList, setPdfsList] = useState([]); // Array of { folderName, data: [] }
    const [selectedFolders, setSelectedFolders] = useState([]);
    const [selectedPdfs, setSelectedPdfs] = useState([]);

    // Config State
    const [totalMarks, setTotalMarks] = useState(25);
    const [includeMCQs, setIncludeMCQs] = useState(true);
    const [includeFlashcards, setIncludeFlashcards] = useState(true);
    const [includeAnalysis, setIncludeAnalysis] = useState(false); // Map to "Long Answers"

    // UI State
    const [loadingFolders, setLoadingFolders] = useState(false);
    const [loadingPdfs, setLoadingPdfs] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

    useFocusEffect(
        useCallback(() => {
            loadFolders();
        }, [])
    );

    const onRefresh = useCallback(() => {
        setRefreshing(true);
        loadFolders().then(() => setRefreshing(false));
    }, []);

    const debounceRef = useRef(null);

    useEffect(() => {
        if (selectedFolders.length > 0) {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => {
                loadPdfs();
            }, 350);
        } else {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            setPdfsList([]);
            setSelectedPdfs([]);
        }
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [selectedFolders]);

    const loadFolders = async () => {
        if (!refreshing) setLoadingFolders(true);
        try {
            const userId = user?.user_id || 0;
            const res = await axios.get(`${API_URL}/get_pdf_folders.php?user_id=${userId}`);
            
            if (res.data.status === 'success') {
                const formattedFolders = res.data.data.map(f => ({
                    folder_id: f.folder_id, 
                    folder_name: f.name || f.folder_name 
                })).filter(f => f.folder_name);
                
                // Inject a highly-readable Root selection option so unfoldered PDFs are still accessible
                formattedFolders.unshift({ folder_id: 'root', folder_name: 'Main Vault' });

                setFolders(formattedFolders);
            }
        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Failed to load Knowledge Vault folders');
        } finally {
            setLoadingFolders(false);
            setRefreshing(false);
        }
    };

    const loadPdfs = async () => {
        setLoadingPdfs(true);
        const userId = user?.user_id || 0;
        try {
            const promises = selectedFolders.map(folder => {
                const folderParam = folder.folder_id === 'root' ? '' : folder.folder_id;
                return axios.get(`${API_URL}/get_pdf_study_status.php?user_id=${userId}&folder_id=${folderParam}`)
                    .then(res => {
                        let completedPdfs = [];
                        if (res.data.status === 'success') {
                            completedPdfs = res.data.data.filter(job => job.status === 'completed');
                        }
                        return {
                            folderName: folder.folder_name,
                            folderId: folder.folder_id,
                            data: completedPdfs
                        };
                    })
                    .catch(() => ({
                        folderName: folder.folder_name,
                        folderId: folder.folder_id,
                        data: []
                    }));
            });

            const results = await Promise.all(promises);
            const groupedPdfs = results.filter(r => r.data.length > 0);
            setPdfsList(groupedPdfs);

            // Clean up missing selections
            const allVisiblePdfIds = groupedPdfs.flatMap(g => g.data.map(c => c.job_id));
            setSelectedPdfs(prev => prev.filter(id => allVisiblePdfIds.includes(id)));

        } catch (error) {
            Alert.alert('Error', 'Failed to load PDFs from folders');
            console.error(error);
        } finally {
            setLoadingPdfs(false);
        }
    };

    const toggleFolder = (folder) => {
        setSelectedFolders(prev => {
            const exists = prev.find(f => f.folder_id === folder.folder_id);
            if (exists) return prev.filter(f => f.folder_id !== folder.folder_id);
            return [...prev, folder];
        });
    };

    const togglePdf = (jobId) => {
        setSelectedPdfs(prev => {
            if (prev.includes(jobId)) return prev.filter(id => id !== jobId);
            return [...prev, jobId];
        });
    };

    const selectAllPdfs = () => {
        const allPdfIds = pdfsList.flatMap(group => group.data.map(pdf => pdf.job_id));
        if (selectedPdfs.length === allPdfIds.length) {
            setSelectedPdfs([]);
        } else {
            setSelectedPdfs(allPdfIds);
        }
    };

    const shuffle = (array) => {
        let currentIndex = array.length, randomIndex;
        while (currentIndex !== 0) {
            randomIndex = Math.floor(Math.random() * currentIndex);
            currentIndex--;
            [array[currentIndex], array[randomIndex]] = [array[randomIndex], array[currentIndex]];
        }
        return array;
    };

    const generateWorksheet = async () => {
        if (selectedPdfs.length === 0) {
            Alert.alert('Selection Error', 'Please select at least one document to combine.');
            return;
        }

        if (!includeMCQs && !includeFlashcards && !includeAnalysis) {
            Alert.alert('Selection Error', 'Please select at least one question type.');
            return;
        }

        setGenerating(true);
        try {
            let allMCQs = [];
            let allFlashcards = [];
            let fileNames = [];

            const letterMap = ['a', 'b', 'c', 'd'];
            
            // Fetch details for each selected document
            for (let id of selectedPdfs) {
                let studyPack = null;
                try {
                    const localRaw = await AsyncStorage.getItem(`study_job_${id}`);
                    if (localRaw) {
                        studyPack = JSON.parse(localRaw);
                    }
                } catch (e) {
                    console.error("Local storage error", e);
                }

                const res = await axios.get(`${API_URL}/get_pdf_study_status.php?user_id=${user?.user_id || 0}&job_id=${id}`);
                if (res.data.status === 'success') {
                    if (!studyPack) {
                        studyPack = res.data.data.study_pack;
                        if (typeof studyPack === 'string') {
                            try {
                                studyPack = JSON.parse(studyPack);
                            } catch (e) {
                                console.error("Failed to parse studyPack JSON", e);
                            }
                        }
                    }
                    fileNames.push(res.data.data.file_name.replace('.pdf', '') || 'Document');
                    
                    if (studyPack?.flashcards) {
                        const mappedCards = studyPack.flashcards.map(raw => ({
                            question_front: raw.f || raw.front || raw.q || raw.question || '',
                            answer_back: raw.b || raw.back || raw.a || raw.answer || ''
                        }));
                        allFlashcards = [...allFlashcards, ...mappedCards];
                    }
                    if (studyPack?.mcqs) {
                        const mappedMcqs = studyPack.mcqs.map(raw => ({
                            question: raw.q || raw.question || '',
                            option_a: raw.o ? raw.o[0] : (raw.option_a || 'True'),
                            option_b: raw.o ? raw.o[1] : (raw.option_b || 'False'),
                            option_c: raw.o ? raw.o[2] : (raw.option_c || ''),
                            option_d: raw.o ? raw.o[3] : (raw.option_d || ''),
                            correct_answer: (raw.a !== undefined && letterMap[raw.a]) ? letterMap[raw.a] : (raw.correct_answer || 'a'),
                        }));
                        allMCQs = [...allMCQs, ...mappedMcqs];
                    }
                }
            }

            let finalMCQs = [];
            let finalShort = [];
            let finalLong = [];
            let currentMarks = 0;
            const targetMarks = totalMarks;

            let availableLong = includeAnalysis ? [...allFlashcards] : [];
            let availableShort = includeFlashcards ? [...allFlashcards] : [];
            let availableMCQs = includeMCQs ? [...allMCQs] : [];

            // To prevent duplicates between short and long, we shuffle ALL flashcards, and split them.
            if (includeAnalysis && includeFlashcards) {
               const mixedCards = shuffle([...allFlashcards]);
               const splitIdx = Math.floor(mixedCards.length * 0.4); // 40% long, 60% short
               availableLong = mixedCards.slice(0, splitIdx);
               availableShort = mixedCards.slice(splitIdx);
            } else if (includeAnalysis) {
               availableLong = shuffle([...allFlashcards]);
               availableShort = [];
            } else if (includeFlashcards) {
               availableShort = shuffle([...allFlashcards]);
               availableLong = [];
            }
            availableMCQs = shuffle([...availableMCQs]);

            // Add long questions first (5 marks)
            while (includeAnalysis && availableLong.length > 0 && currentMarks + 5 <= targetMarks) {
                finalLong.push(availableLong.pop());
                currentMarks += 5;
            }
            
            // Add short questions next (2 marks)
            while (includeFlashcards && availableShort.length > 0 && currentMarks + 2 <= targetMarks) {
                finalShort.push(availableShort.pop());
                currentMarks += 2;
            }
            
            // Add MCQs next (1 mark)
            while (includeMCQs && availableMCQs.length > 0 && currentMarks + 1 <= targetMarks) {
                finalMCQs.push(availableMCQs.pop());
                currentMarks += 1;
            }

            // Exceed logic if exact points not met due to strange divisions
            if (currentMarks < targetMarks) {
                while (includeMCQs && availableMCQs.length > 0 && currentMarks < targetMarks) {
                     finalMCQs.push(availableMCQs.pop());
                     currentMarks += 1;
                }
                while (includeFlashcards && availableShort.length > 0 && currentMarks < targetMarks) {
                     finalShort.push(availableShort.pop());
                     currentMarks += 2;
                }
            }

            let subjectStr = fileNames.length > 2 
                ? fileNames.slice(0,2).join(', ') + ` & +${fileNames.length - 2}`
                : fileNames.join(' & ');

            if (finalMCQs.length === 0 && finalShort.length === 0 && finalLong.length === 0) {
                Alert.alert("No Questions Found", "The selected documents do not contain enough questions of the selected type. If you have unselected certain question types, try enabling them.");
                setGenerating(false);
                return;
            }

            const html = createHTML(finalMCQs, finalShort, finalLong, subjectStr || 'Knowledge Vault Docs', currentMarks);
            const { uri } = await Print.printToFileAsync({ html });
            await Sharing.shareAsync(uri, { UTI: '.pdf', mimeType: 'application/pdf' });

        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Failed to compile worksheet. Please try again.');
        } finally {
            setGenerating(false);
        }
    };

    const createHTML = (mcqs, short, long, subjectTitle, currentMarks) => {
        const studentName = user?.name || "Student";
        const date = new Date().toLocaleDateString();
        let mcqSection = ""; let shortSection = ""; let longSection = ""; let answerKey = "";

        if (mcqs.length > 0) {
            mcqSection += `<h3>Section A: Multiple Choice Questions (1 Mark each)</h3><ol>`;
            answerKey += `<h4>Section A Answers</h4><ol>`;
            mcqs.forEach(q => {
                mcqSection += `<li class="question-item"><div class="question-text">${q.question || q.question_text || q.q}</div><div class="options-grid">
                    <div class="option">(A) ${q.option_a || 'True'}</div>
                    <div class="option">(B) ${q.option_b || 'False'}</div>
                    <div class="option">(C) ${q.option_c || ''}</div>
                    <div class="option">(D) ${q.option_d || ''}</div>
                </div></li>`;
                 answerKey += `<li>${q.correct_answer || q.answer || 'A'}</li>`;
            });
            mcqSection += `</ol>`; answerKey += `</ol>`;
        }

        if (short.length > 0) {
            shortSection += `<h3>Section B: Short Answer Questions (2 Marks each)</h3><ol>`;
            answerKey += `<h4>Section B Answers</h4><ol>`;
            short.forEach(q => {
                const questionText = q.question_front || q.question || q.front || q.q || "Explain:";
                const answerText = q.answer_back || q.answer || q.back || q.a || "";
                shortSection += `<li class="question-item"><div class="question-text">${questionText}</div><br/><br/><br/></li>`;
                answerKey += `<li>${answerText}</li>`;
            });
            shortSection += `</ol>`; answerKey += `</ol>`;
        }

        if (long.length > 0) {
            longSection += `<h3>Section C: Long Answer/Analysis Questions (5 Marks each)</h3><ol>`;
            answerKey += `<h4>Section C Analytical Concepts</h4><ul style="list-style-type: none; padding: 0;">`;
            long.forEach((q, index) => {
                 const questionText = q.question_front || q.question || q.front || q.q || "Analyze in detail:";
                 const answerText = q.answer_back || q.answer || q.back || q.a || "";
                longSection += `<li class="question-item"><div class="question-text">Discuss: ${questionText}</div><br/><br/><br/><br/><br/></li>`;
                answerKey += `<li style="margin-bottom: 20px; border-bottom: 1px dashed #ccc; padding-bottom: 15px;">
                   <div style="font-weight:bold; margin-bottom:5px;">Q${index + 1}: ${questionText}</div>
                   <div style="color: #2563eb; margin-bottom:5px;">Ans: ${answerText}</div>
               </li>`;
            });
            longSection += `</ol>`; answerKey += `</ul>`;
        }

        return `
        <html>
          <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no" />
            <style>
              @page { margin: 70px 80px; }
              body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; color: #333; line-height: 1.6; }
              .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 35px; }
              .header h1 { margin: 0; font-size: 26px; color: #4A00E0; text-transform: uppercase; letter-spacing: 1px; }
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
              <p>Topics: ${subjectTitle}</p>
            </div>
            <div class="details">
              <span>Name: ____________________</span>
              <span>Date: ${date}</span>
              <span>Total Marks: ${Math.round(currentMarks)}</span>
            </div>
            ${mcqSection}
            ${shortSection}
            ${longSection}
            
            ${(mcqs.length > 0 || short.length > 0 || long.length > 0) ? `
              <div class="page-break"></div>
              <div>
                <div class="header"><h1>Answer Key</h1></div>
                ${answerKey}
              </div>
            ` : ''}
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
                            <Text style={styles.headerTitle}>Veeru Lens Worksheet</Text>
                            <Text style={styles.headerSubtitle}>Create printable documents</Text>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <ScrollView 
                contentContainerStyle={styles.content}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
            >
                {/* Step 1: Folders */}
                <View style={styles.section}>
                    <Text style={styles.sectionHeader}>1. Select Folders</Text>
                    {loadingFolders ? (
                        <ActivityIndicator size="large" color="#C026D3" />
                    ) : (
                        <View style={styles.grid}>
                            {folders.map((folder, index) => {
                                const isSelected = selectedFolders.find(s => s.folder_id === folder.folder_id);
                                const gradients = [
                                    ['#FF6B6B', '#FFD93D'], ['#4ECDC4', '#44A8FF'], ['#A8E6CF', '#56CCF2'], 
                                    ['#FF8C42', '#FF3E96'], ['#667EEA', '#764BA2'], ['#F093FB', '#F5576C']
                                ];
                                const colors = gradients[index % gradients.length];

                                return (
                                    <TouchableOpacity
                                        key={folder.folder_id}
                                        style={[styles.subjectCard, isSelected && styles.subjectCardSelected]}
                                        onPress={() => toggleFolder(folder)}
                                        activeOpacity={0.8}
                                    >
                                        <LinearGradient
                                            colors={isSelected ? ['#A855F7', '#C026D3'] : colors}
                                            style={styles.subjectGradient}
                                        >
                                            <Text style={styles.subjectInitial}>{folder.folder_name.charAt(0)}</Text>
                                            <Text style={styles.subjectName}>{folder.folder_name}</Text>
                                            {isSelected && <Text style={styles.checkBadge}>✓</Text>}
                                        </LinearGradient>
                                    </TouchableOpacity>
                                );
                            })}
                            {folders.length === 0 && !loadingFolders && (
                                <Text style={{color: '#94a3b8'}}>Your Knowledge Vault is empty.</Text>
                            )}
                        </View>
                    )}
                </View>

                {/* Step 2: PDFs */}
                {selectedFolders.length > 0 && (
                    <View style={styles.section}>
                        <View style={styles.rowBetween}>
                            <Text style={styles.sectionHeader}>2. Select Documents</Text>
                            <TouchableOpacity onPress={selectAllPdfs}>
                                <Text style={styles.linkText}>Select All / None</Text>
                            </TouchableOpacity>
                        </View>

                        {loadingPdfs ? (
                            <ActivityIndicator size="large" color="#C026D3" />
                        ) : (
                            <View>
                                {pdfsList.map((group) => (
                                    <View key={group.folderId} style={{ marginBottom: 15 }}>
                                        <Text style={styles.groupTitle}>{group.folderName}</Text>
                                        {group.data.map(pdf => {
                                            const isSelected = selectedPdfs.includes(pdf.job_id);
                                            return (
                                                <TouchableOpacity
                                                    key={pdf.job_id}
                                                    style={[styles.chapterItem, isSelected && styles.chapterItemSelected]}
                                                    onPress={() => togglePdf(pdf.job_id)}
                                                >
                                                    <View style={[styles.checkbox, isSelected && styles.checkboxSelected]}>
                                                        {isSelected && <Ionicons name="checkmark" size={16} color="white" />}
                                                    </View>
                                                    <Text style={[styles.chapterText, isSelected && { color: '#C026D3', fontWeight: 'bold' }]} numberOfLines={2}>
                                                        {pdf.file_name}
                                                    </Text>
                                                </TouchableOpacity>
                                            );
                                        })}
                                    </View>
                                ))}
                                {pdfsList.length === 0 && !loadingPdfs && (
                                    <Text style={{color: '#94a3b8'}}>No processed documents found in the selected folder(s).</Text>
                                )}
                            </View>
                        )}
                    </View>
                )}

                {/* Step 3: Config */}
                {selectedPdfs.length > 0 && (
                    <View style={styles.section}>
                        <Text style={styles.sectionHeader}>3. Customize Paper</Text>

                        <View style={styles.card}>
                            <Text style={styles.label}>Select Total Marks: {totalMarks}</Text>
                            <View style={styles.markButtonsContainer}>
                                {[25, 40, 50, 80, 100].map((mark) => (
                                    <TouchableOpacity
                                        key={mark}
                                        style={[styles.markButton, totalMarks === mark && styles.markButtonSelected]}
                                        onPress={() => setTotalMarks(mark)}
                                    >
                                        <Text style={[styles.markButtonText, totalMarks === mark && styles.markButtonTextSelected]}>
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
                                <Text style={styles.switchLabel}>Long / Deep Questions (5 Marks)</Text>
                                <Switch value={includeAnalysis} onValueChange={setIncludeAnalysis} trackColor={{ true: '#C026D3' }} />
                            </View>
                        </View>

                        <TouchableOpacity
                            style={styles.generateBtn}
                            onPress={generateWorksheet}
                            disabled={generating}
                        >
                            <LinearGradient colors={['#2563eb', '#3b82f6']} style={styles.btnGradient}>
                                {generating ? <ActivityIndicator color="white" /> : (
                                    <>
                                        <Ionicons name="print" size={24} color="white" style={{ marginRight: 10 }} />
                                        <Text style={styles.btnText}>Generate PDF</Text>
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
    headerTitle: { fontSize: 22, fontWeight: 'bold', color: 'white' },
    headerSubtitle: { fontSize: 13, color: 'rgba(255,255,255,0.9)' },
    content: { padding: 20, paddingBottom: 50 },
    section: { marginBottom: 30 },
    sectionHeader: { fontSize: 18, fontWeight: 'bold', color: '#1e293b', marginBottom: 15 },
    grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
    subjectCard: { width: '48%', borderRadius: 16, overflow: 'hidden', elevation: 3, marginBottom: 5 },
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
