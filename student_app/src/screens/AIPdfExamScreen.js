import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    TextInput,
    ActivityIndicator,
    Alert,
    StatusBar,
    RefreshControl
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect } from '@react-navigation/native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';
import { API_URL } from '../api/config';

const AIPdfExamScreen = ({ navigation, user }) => {
    // Theme configurations manually inherited to match MyExamScreen aesthetics
    const currentThemeColors = ['#f6d365', '#fda085'];
    const screenTitle = 'Knowledge Vault Exam';
    const screenSubtitle = 'Test yourself on multiple PDFs';

    const [folders, setFolders] = useState([]);
    const [pdfsList, setPdfsList] = useState([]); // Array of { folderName, data: [] }
    const [selectedFolders, setSelectedFolders] = useState([]);
    const [selectedPdfs, setSelectedPdfs] = useState([]);
    const [questionLimit, setQuestionLimit] = useState('25');
    
    const [loadingFolders, setLoadingFolders] = useState(false);
    const [loadingPdfs, setLoadingPdfs] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const [generating, setGenerating] = useState(false);

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

    const startTest = async () => {
        if (selectedFolders.length === 0) {
            Alert.alert('Error', 'Please select at least one folder');
            return;
        }
        if (selectedPdfs.length === 0) {
            Alert.alert('Error', 'Please select at least one document');
            return;
        }
        const limit = parseInt(questionLimit);
        if (!limit || limit < 1 || limit > 100) {
            Alert.alert('Error', 'Please enter a valid number of questions (1-100)');
            return;
        }

        setGenerating(true);
        try {
            let allMcqs = [];
            let fileNames = [];

            // Shared answer mapping array
            const letterMap = ['a', 'b', 'c', 'd'];

            // Fetch embedded study_pack for each selected PDF job
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
                    
                    if (studyPack?.mcqs) {
                        const mappedMcqs = studyPack.mcqs.map(raw => ({
                            question: raw.q || raw.question || '',
                            option_a: raw.o ? raw.o[0] : (raw.option_a || 'True'),
                            option_b: raw.o ? raw.o[1] : (raw.option_b || 'False'),
                            option_c: raw.o ? raw.o[2] : (raw.option_c || ''),
                            option_d: raw.o ? raw.o[3] : (raw.option_d || ''),
                            correct_answer: (raw.a !== undefined && letterMap[raw.a]) ? letterMap[raw.a] : (raw.correct_answer || 'a'),
                            explanation: raw.e || raw.explanation || ''
                        }));
                        allMcqs = [...allMcqs, ...mappedMcqs];
                    }
                }
            }

            if (allMcqs.length === 0) {
                Alert.alert("No Questions Found", "The selected documents do not contain enough questions of the selected type. If you have unselected certain question types, try enabling them.");
                setGenerating(false);
                return;
            }

            // Shuffle and slice to limit
            allMcqs = shuffle(allMcqs);
            const finalMCQs = allMcqs.slice(0, limit);

            let subjectStr = fileNames.length > 2 
                ? fileNames.slice(0,2).join(', ') + ` & +${fileNames.length - 2}`
                : fileNames.join(' & ');

            // Navigate identical to MyExamScreen payload
            navigation.navigate('MyExamTest', {
                questions: finalMCQs,
                totalQuestions: finalMCQs.length,
                subjectName: subjectStr || "Knowledge Vault Exam"
            });
            
        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Failed to generate test from PDFs. Please try again.');
        } finally {
            setGenerating(false);
        }
    };

    return (
        <View style={styles.mainWrapper}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent={true} />

            <LinearGradient colors={currentThemeColors} style={styles.headerGradient}>
                <SafeAreaView edges={['top']} style={styles.headerSafe}>
                    <View style={styles.header}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Text style={styles.backButtonText}>←</Text>
                        </TouchableOpacity>
                        <View style={styles.headerTextContainer}>
                            <Text style={styles.headerTitle}>{screenTitle}</Text>
                            <Text style={styles.headerSubtitle}>{screenSubtitle}</Text>
                        </View>
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <ScrollView 
                style={styles.container} 
                contentContainerStyle={styles.scrollContent}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
            >
                {/* Step 1: Folder Selection */}
                <View style={styles.section}>
                    <Text style={styles.sectionTitle}>1. Select Folders</Text>
                    {loadingFolders ? (
                        <ActivityIndicator size="large" color="#0072ff" style={styles.loader} />
                    ) : (
                        <View style={styles.subjectGrid}>
                            {folders.map((folder, index) => {
                                // Original gorgeous Subject grid gradients
                                const gradients = [
                                    ['#FF416C', '#FF4B2B'], ['#4776E6', '#8E54E9'], ['#00B4DB', '#0083B0'], 
                                    ['#11998E', '#38EF7D'], ['#F7971E', '#FFD200'], ['#EC008C', '#FC6767'], 
                                    ['#1A2980', '#26D0CE'], ['#F09819', '#EDDE5D'],
                                ];
                                const colors = gradients[index % gradients.length];
                                const isSelected = selectedFolders.some(s => s.folder_id === folder.folder_id);

                                return (
                                    <TouchableOpacity
                                        key={folder.folder_id}
                                        style={[styles.subjectCard, isSelected && styles.subjectCardSelected]}
                                        onPress={() => toggleFolder(folder)}
                                        activeOpacity={0.7}
                                    >
                                        <LinearGradient
                                            colors={isSelected ? ['#1e293b', '#0f172a'] : colors}
                                            style={styles.subjectGradient}
                                        >
                                            <Text style={styles.subjectIcon}>
                                                {folder.folder_name.charAt(0).toUpperCase()}
                                            </Text>
                                            <Text style={styles.subjectName}>{folder.folder_name}</Text>
                                            {isSelected && (
                                                <View style={[styles.selectedBadge, { backgroundColor: '#10b981' }]}>
                                                    <Text style={styles.selectedBadgeText}>✓</Text>
                                                </View>
                                            )}
                                        </LinearGradient>
                                    </TouchableOpacity>
                                );
                            })}
                            {folders.length === 0 && !loadingFolders && (
                                <Text style={styles.noDataText}>Your Knowledge Vault is empty.</Text>
                            )}
                        </View>
                    )}
                </View>

                {/* Step 2: PDF Document Selection */}
                {selectedFolders.length > 0 && (
                    <View style={styles.section}>
                        <View style={styles.sectionHeader}>
                            <Text style={styles.sectionTitle}>2. Select Documents</Text>
                            <TouchableOpacity onPress={selectAllPdfs} style={styles.selectAllButton}>
                                <Text style={styles.selectAllText}>
                                    Select All / Deselect
                                </Text>
                            </TouchableOpacity>
                        </View>
                        {loadingPdfs ? (
                            <ActivityIndicator size="large" color="#0072ff" style={styles.loader} />
                        ) : (
                            <View style={styles.chapterList}>
                                {pdfsList.map((group) => (
                                    <View key={group.folderId} style={styles.groupContainer}>
                                        <Text style={styles.groupHeader}>{group.folderName}</Text>
                                        {group.data.map((pdf) => {
                                            const isSelected = selectedPdfs.includes(pdf.job_id);
                                            return (
                                                <TouchableOpacity
                                                    key={pdf.job_id}
                                                    style={[styles.chapterItem, isSelected && styles.chapterItemSelected]}
                                                    onPress={() => togglePdf(pdf.job_id)}
                                                >
                                                    <View style={[styles.checkbox, isSelected && styles.checkboxSelected]}>
                                                        {isSelected && <Text style={styles.checkmark}>✓</Text>}
                                                    </View>
                                                    <View style={styles.chapterInfo}>
                                                        <Text style={[styles.chapterName, isSelected && styles.chapterNameSelected]} numberOfLines={2}>
                                                            {pdf.file_name}
                                                        </Text>
                                                        <Text style={styles.chapterStats}>
                                                            Extracted Data Available
                                                        </Text>
                                                    </View>
                                                </TouchableOpacity>
                                            );
                                        })}
                                    </View>
                                ))}
                                {pdfsList.length === 0 && !loadingPdfs && (
                                    <Text style={styles.noDataText}>No ready documents found in selected folder(s).</Text>
                                )}
                            </View>
                        )}
                    </View>
                )}

                {/* Step 3: Question Limit */}
                {selectedPdfs.length > 0 && (
                    <View style={styles.section}>
                        <Text style={styles.sectionTitle}>3. Number of Questions</Text>
                        <View style={styles.limitContainer}>
                            {['10', '25', '50', '100'].map((num) => (
                                <TouchableOpacity
                                    key={num}
                                    style={[styles.limitButton, questionLimit === num && styles.limitButtonSelected]}
                                    onPress={() => setQuestionLimit(num)}
                                >
                                    <Text style={[
                                        styles.limitButtonText,
                                        questionLimit === num && styles.limitButtonTextSelected
                                    ]}>{num}</Text>
                                </TouchableOpacity>
                            ))}
                        </View>
                        <TextInput
                            style={styles.customInput}
                            placeholder="Or enter custom number (1-100)"
                            placeholderTextColor="#94a3b8"
                            keyboardType="number-pad"
                            value={questionLimit}
                            onChangeText={setQuestionLimit}
                            maxLength={3}
                        />
                    </View>
                )}

                {/* Start Button */}
                {selectedPdfs.length > 0 && (
                    <TouchableOpacity
                        style={styles.startButton}
                        onPress={startTest}
                        disabled={generating}
                    >
                        <LinearGradient colors={['#00c6ff', '#0072ff']} style={styles.startButtonGradient}>
                            {generating ? (
                                <ActivityIndicator color="white" />
                            ) : (
                                <>
                                    <Text style={styles.startButtonText}>Start Test</Text>
                                    <Text style={styles.startButtonSubtext}>
                                        {selectedPdfs.length} document{selectedPdfs.length > 1 ? 's' : ''} • {questionLimit} questions
                                    </Text>
                                </>
                            )}
                        </LinearGradient>
                    </TouchableOpacity>
                )}
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
    section: { marginBottom: 30 },
    sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 15 },
    sectionTitle: { fontSize: 18, fontWeight: 'bold', color: '#0f172a', marginBottom: 15 },
    selectAllButton: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 8, backgroundColor: '#e0f2fe' },
    selectAllText: { fontSize: 13, fontWeight: '600', color: '#0369a1' },
    subjectGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
    subjectCard: { width: '48%', backgroundColor: 'white', borderRadius: 20, overflow: 'hidden', elevation: 6, shadowColor: '#000', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.2, shadowRadius: 10 },
    subjectCardSelected: { elevation: 12, shadowOpacity: 0.4, transform: [{ scale: 1.05 }] },
    subjectGradient: { flex: 1, padding: 20, alignItems: 'center', justifyContent: 'center', minHeight: 140 },
    subjectIcon: { fontSize: 48, fontWeight: '900', color: 'white', marginBottom: 12, textShadowColor: 'rgba(0, 0, 0, 0.3)', textShadowOffset: { width: 0, height: 2 }, textShadowRadius: 4 },
    subjectName: { fontSize: 15, fontWeight: '900', color: 'white', textAlign: 'center', textTransform: 'uppercase', letterSpacing: 0.5, textShadowColor: 'rgba(0, 0, 0, 0.3)', textShadowOffset: { width: 0, height: 2 }, textShadowRadius: 4 },
    chapterList: { gap: 10 },
    chapterItem: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'white', borderRadius: 12, padding: 16, borderWidth: 1, borderColor: '#e2e8f0' },
    chapterItemSelected: { borderColor: '#0072ff', backgroundColor: '#eff6ff' },
    checkbox: { width: 24, height: 24, borderRadius: 6, borderWidth: 2, borderColor: '#cbd5e1', marginRight: 12, justifyContent: 'center', alignItems: 'center' },
    checkboxSelected: { backgroundColor: '#0072ff', borderColor: '#0072ff' },
    checkmark: { color: 'white', fontSize: 16, fontWeight: 'bold' },
    chapterInfo: { flex: 1 },
    chapterName: { fontSize: 15, fontWeight: '600', color: '#1e293b', marginBottom: 4 },
    chapterNameSelected: { color: '#0072ff' },
    chapterStats: { fontSize: 12, color: '#64748b' },
    limitContainer: { flexDirection: 'row', gap: 10, marginBottom: 15 },
    limitButton: { flex: 1, paddingVertical: 16, borderRadius: 12, backgroundColor: 'white', borderWidth: 2, borderColor: '#e2e8f0', alignItems: 'center' },
    limitButtonSelected: { borderColor: '#0072ff', backgroundColor: '#eff6ff' },
    limitButtonText: { fontSize: 18, fontWeight: '600', color: '#475569' },
    limitButtonTextSelected: { color: '#0072ff', fontWeight: 'bold' },
    customInput: { backgroundColor: 'white', borderRadius: 12, padding: 16, fontSize: 15, borderWidth: 1, borderColor: '#e2e8f0', color: '#1e293b' },
    startButton: { borderRadius: 16, overflow: 'hidden', elevation: 4, shadowColor: '#0072ff', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.3, shadowRadius: 8, marginTop: 10 },
    startButtonGradient: { padding: 20, alignItems: 'center' },
    startButtonText: { fontSize: 18, fontWeight: 'bold', color: 'white' },
    startButtonSubtext: { fontSize: 13, color: 'rgba(255,255,255,0.9)', marginTop: 4 },
    loader: { marginVertical: 20 },
    selectedBadge: { position: 'absolute', top: 12, right: 12, width: 28, height: 28, borderRadius: 14, backgroundColor: 'rgba(255, 255, 255, 0.3)', justifyContent: 'center', alignItems: 'center', borderWidth: 2, borderColor: 'white', elevation: 4 },
    selectedBadgeText: { color: 'white', fontWeight: '900', fontSize: 15 },
    groupContainer: { marginBottom: 15 },
    groupHeader: { fontSize: 16, fontWeight: '800', color: '#64748b', marginBottom: 8, marginLeft: 4, textTransform: 'uppercase', letterSpacing: 1 },
    noDataText: { textAlign: 'center', color: '#94a3b8', fontSize: 14, marginTop: 20 }
});

export default AIPdfExamScreen;
