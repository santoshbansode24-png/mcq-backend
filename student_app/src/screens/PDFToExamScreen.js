import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, FlatList,
    ActivityIndicator, Alert, Animated, Platform, StatusBar,
    Dimensions, ScrollView, Modal, TextInput, Pressable, RefreshControl,
    BackHandler, InteractionManager
} from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as DocumentPicker from 'expo-document-picker';
import Svg, { Circle } from 'react-native-svg';
import axios from 'axios';
import { useTheme } from '../context/ThemeContext';
import { API_URL } from '../api/config';

const { width } = Dimensions.get('window');
const TILE_SIZE = (width - 60) / 2;

const CircularProgress = ({ progress = 0, size = 60, strokeWidth = 5, color = "#00f3ff" }) => {
    const radius = (size - strokeWidth) / 2;
    const circumference = radius * 2 * Math.PI;
    const offset = circumference - (progress / 100) * circumference;
    return (
        <Svg width={size} height={size} style={{ position: 'absolute' }}>
            <Circle cx={size/2} cy={size/2} r={radius} stroke="#ffffff10" strokeWidth={strokeWidth} fill="transparent" />
            <Circle cx={size/2} cy={size/2} r={radius} stroke={color} strokeWidth={strokeWidth} fill="transparent" strokeDasharray={circumference} strokeDashoffset={offset} strokeLinecap="round" transform={`rotate(-90 ${size/2} ${size/2})`} />
        </Svg>
    );
};

const VIBRANT_THEMES = [
    { start: '#10b981', end: '#059669', shadow: '#10b981', bg: '#10b98115' }, // Emerald
    { start: '#f43f5e', end: '#e11d48', shadow: '#f43f5e', bg: '#f43f5e15' }, // Rose
    { start: '#3b82f6', end: '#2563eb', shadow: '#3b82f6', bg: '#3b82f615' }, // Azure
    { start: '#8b5cf6', end: '#6d28d9', shadow: '#8b5cf6', bg: '#8b5cf615' }, // Violet
    { start: '#f97316', end: '#ea580c', shadow: '#f97316', bg: '#f9731615' }, // Orange
    { start: '#06b6d4', end: '#0891b2', shadow: '#06b6d4', bg: '#06b6d415' }, // Cyan
    { start: '#ec4899', end: '#be185d', shadow: '#ec4899', bg: '#ec489915' }, // Pink
    { start: '#eab308', end: '#ca8a04', shadow: '#eab308', bg: '#eab30815' }, // Yellow
];

const getThemeColor = (idx) => VIBRANT_THEMES[(idx || 0) % VIBRANT_THEMES.length];

const FolderCard = React.memo(({ item, index, isMultiSelectMode, onFolderSelect, onFolderOptions }) => {
    const theme = getThemeColor(index !== undefined ? index : item.folder_id || 0);
    return (
        <TouchableOpacity 
            style={[styles.folderCard, isMultiSelectMode && { opacity: 0.3 }]} 
            onPress={() => onFolderSelect(item)}
            onLongPress={() => onFolderOptions('folder', item)}
            disabled={isMultiSelectMode}
        >
            <LinearGradient colors={[theme.start, theme.end]} start={{x: 0, y: 0}} end={{x: 1, y: 1}} style={[styles.glassFolder, { borderWidth: 0, shadowColor: theme.shadow, shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.4, shadowRadius: 8, elevation: 8 }]}>
                <MaterialCommunityIcons name="folder-text" size={32} color="white" />
                <Text style={styles.folderLabel} numberOfLines={1}>{item.name}</Text>
            </LinearGradient>
        </TouchableOpacity>
    );
}, (prev, next) => prev.isMultiSelectMode === next.isMultiSelectMode && prev.item.name === next.item.name && prev.index === next.index);

const JobCard = React.memo(({ item, index, isMultiSelectMode, selectModeType, isSelected, onJobSelect, onJobOptions }) => {
    const isReady = item.status === 'completed';
    const isFailed = item.status === 'failed';
    const progress = isReady ? 100 : (item.progress || 10);
    
    // Assign unique distinct dynamic color per job file
    const theme = getThemeColor(index !== undefined ? index : item.job_id || 0);

    return (
        <TouchableOpacity 
            style={[
                styles.pdfRow, 
                { 
                    borderLeftWidth: 4,
                    borderLeftColor: theme.start,
                    backgroundColor: '#ffffff08',
                    paddingLeft: 12,
                    borderColor: '#ffffff10' 
                },
                isMultiSelectMode && isSelected && { backgroundColor: theme.bg, borderColor: theme.start, borderLeftWidth: 4 }
            ]} 
            onPress={() => onJobSelect(item, isReady, isFailed)}
            onLongPress={() => onJobOptions('file', item)}
        >
            <View style={styles.pdfRowIcon}>
                {isMultiSelectMode && isReady ? (
                    <View style={[
                        styles.checkbox, 
                        isSelected && { backgroundColor: theme.start, borderColor: theme.start }
                    ]}>
                        {isSelected && <MaterialCommunityIcons name="check" size={16} color="white" />}
                    </View>
                ) : (
                    <>
                        <CircularProgress progress={progress} size={60} color={isFailed ? "#ef4444" : theme.start} strokeWidth={4} />
                        <MaterialCommunityIcons name={isFailed ? "file-cancel" : "text-box-outline"} size={26} color={isFailed ? "#ef4444" : theme.start} />
                    </>
                )}
            </View>
            <View style={styles.pdfRowContent}>
                <Text style={styles.pdfRowTitle} numberOfLines={2}>{item.file_name}</Text>
                {/* Advanced Pill Badges */}
                <View style={{ flexDirection: 'row', marginTop: 6 }}>
                    <View style={[styles.statusPill, { backgroundColor: isFailed ? '#ef444420' : (isReady ? '#10b98120' : theme.bg) }]}>
                        <View style={[styles.statusDot, { backgroundColor: isFailed ? '#ef4444' : (isReady ? '#10b981' : theme.start) }]} />
                        <Text style={[styles.statusPillText, { color: isFailed ? '#f87171' : (isReady ? '#34d399' : theme.start) }]}>
                            {isFailed ? 'Analysis Failed' : (isReady ? 'Ready to Study' : `${progress}% AI Processing`)}
                        </Text>
                    </View>
                </View>
            </View>
            {isReady && !isMultiSelectMode && <MaterialCommunityIcons name="chevron-right" size={28} color="#64748b" />}
        </TouchableOpacity>
    );
}, (prev, next) => {
    return prev.item.status === next.item.status &&
           prev.item.progress === next.item.progress &&
           prev.isMultiSelectMode === next.isMultiSelectMode &&
           prev.selectModeType === next.selectModeType &&
           prev.isSelected === next.isSelected &&
           prev.index === next.index;
});

const PDFToExamScreen = ({ user, navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const isFocused = useIsFocused();
    
    // Data State
    const [folders, setFolders] = useState([]);
    const [jobs, setJobs] = useState([]);
    const [pathStack, setPathStack] = useState([{ id: 'root', name: 'Vault' }]); 
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    
    // UI State
    const [showFolderModal, setShowFolderModal] = useState(false);
    const [newFolderName, setNewFolderName] = useState('');
    const [selectedJob, setSelectedJob] = useState(null); 
    const [selectedMode, setSelectedMode] = useState(null); 
    
    // Rename State
    const [showRenameModal, setShowRenameModal] = useState(false);
    const [renameText, setRenameText] = useState('');
    const [itemToRename, setItemToRename] = useState(null);
    
    // Multi-Select Aggregate State
    const [isMultiSelectMode, setIsMultiSelectMode] = useState(false);
    const [selectModeType, setSelectModeType] = useState(null); // 'worksheet' | 'exam'
    const [selectedPdfs, setSelectedPdfs] = useState([]);
    
    const currentFolderId = pathStack[pathStack.length - 1].id;

    const [refreshing, setRefreshing] = useState(false);

    useEffect(() => { loadData(); }, [pathStack]);

    useEffect(() => {
        let timer;
        const hasPendingJobs = jobs.some(j => j.status !== 'completed' && j.status !== 'failed');
        if (hasPendingJobs) {
            timer = setInterval(() => {
                loadData(true);
            }, 3000);
        }
        return () => clearInterval(timer);
    }, [jobs]);

    useEffect(() => {
        const backAction = () => {
            if (isMultiSelectMode) {
                setIsMultiSelectMode(false);
                setSelectedPdfs([]);
                return true; // prevent default behavior
            }
            if (pathStack.length > 1) {
                setPathStack(prev => prev.slice(0, -1));
                return true; // prevent default behavior
            }
            return false; // let system handle it
        };

        const backHandler = BackHandler.addEventListener(
            "hardwareBackPress",
            backAction
        );

        return () => backHandler.remove();
    }, [isMultiSelectMode, pathStack]);

    const loadData = async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            const folderUrl = `${API_URL}/get_pdf_folders.php?user_id=${user?.user_id || 0}&parent_id=${currentFolderId}`;
            const fRes = await axios.get(folderUrl);
            if (fRes.data.status === 'success') setFolders(fRes.data.data);
            
            const jobFilter = currentFolderId === 'root' ? 'root' : currentFolderId;
            const jRes = await axios.get(`${API_URL}/get_pdf_study_status.php?user_id=${user?.user_id || 0}&folder_id=${jobFilter}`);
            if (jRes.data.status === 'success') setJobs(jRes.data.data);
        } catch (e) { console.log("Load Error", e); } 
        finally { if (!silent) setLoading(false); }
    };

    const onRefresh = useCallback(() => {
        setRefreshing(true);
        loadData(true).finally(() => setRefreshing(false));
    }, [currentFolderId, user]);

    const handleCreateFolder = async () => {
        if (!newFolderName.trim()) return;
        try {
            const pid = currentFolderId === 'root' ? 0 : currentFolderId;
            const res = await axios.post(`${API_URL}/create_pdf_folder.php`, 
                `user_id=${user?.user_id}&name=${encodeURIComponent(newFolderName)}&parent_id=${pid}`,
                { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
            );
            if (res.data.status === 'success') { setNewFolderName(''); setShowFolderModal(false); loadData(); }
        } catch (e) { Alert.alert("Error", "Could not create folder"); }
    };

    const handleUpload = async () => {
        try {
            const doc = await DocumentPicker.getDocumentAsync({ type: 'application/pdf' });
            if (doc.canceled) return;
            const file = doc.assets[0];
            setUploading(true);
            const formData = new FormData();
            formData.append('user_id', (user?.user_id || 0).toString());
            if (currentFolderId !== 'root') formData.append('folder_id', currentFolderId.toString());
            formData.append('pdf_file', {
                uri: Platform.OS === 'android' ? file.uri : file.uri.replace('file://', ''),
                type: 'application/pdf',
                name: file.name || 'document.pdf'
            });
            // Use fetch instead of axios for multipart/form-data to prevent Android APK boundary/network dropping bugs
            const response = await fetch(`${API_URL}/upload_pdf_study.php`, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    // DO NOT set Content-Type here; fetch will automatically add it with the correct boundary!
                }
            });
            
            const responseData = await response.json();
            
            if (responseData.status === 'success') { 
                Alert.alert("✅ Done!", "Your PDF is uploaded and AI is processing. It will be ready in a moment!"); 
                loadData(true); 
            } else {
                Alert.alert("Error", responseData.message || "Upload failed.");
            }
        } catch (e) { 
            console.log("Upload Error:", e);
            Alert.alert("Upload Failed", "Could not connect to server or process file. Check your internet connection.");
        } 
        finally { setUploading(false); }
    };


    const handleOptions = (type, item) => {
        const id = type === 'folder' ? item.folder_id : item.job_id;
        const name = type === 'folder' ? item.name : item.file_name;
        
        Alert.alert(`${name}`, "Choose an action", [
            { text: "Cancel", style: "cancel" },
            { text: "Rename", onPress: () => {
                setItemToRename({ type, id, name });
                setRenameText(name);
                setShowRenameModal(true);
            }},
            { text: "Delete", style: "destructive", onPress: () => {
                Alert.alert("Confirm Delete", `Are you sure you want to delete this ${type}?`, [
                    { text: "Cancel", style: "cancel" },
                    { text: "DELETE", style: "destructive", onPress: async () => {
                        const endpoint = type === 'folder' ? 'delete_pdf_folder.php' : 'delete_pdf_job.php';
                        const key = type === 'folder' ? 'folder_id' : 'job_id';
                        
                        // Optimistic Update
                        if (type === 'folder') {
                             setFolders(prev => prev.filter(f => f.folder_id !== id));
                        } else {
                             setJobs(prev => prev.filter(j => j.job_id !== id));
                        }
                        
                        try {
                            await axios.post(`${API_URL}/${endpoint}`, `user_id=${user?.user_id}&${key}=${id}`, { headers: {'Content-Type': 'application/x-www-form-urlencoded'}});
                            // Verify sync silently if needed, local state handles UI immediately
                        } catch(e) { 
                            Alert.alert("Error", "Delete failed"); 
                            loadData(); // Revert on failure
                        }
                    }}
                ]);
            }}
        ]);
    };

    const handleRenameFile = async () => {
        if (!renameText.trim() || !itemToRename) return;
        
        // Optimistic Update
        if (itemToRename.type === 'folder') {
             setFolders(prev => prev.map(f => f.folder_id === itemToRename.id ? {...f, name: renameText} : f));
        } else {
             setJobs(prev => prev.map(j => j.job_id === itemToRename.id ? {...j, file_name: renameText} : j));
        }
        
        setShowRenameModal(false);
        const newName = renameText;
        setRenameText('');
        
        try {
            await axios.post(`${API_URL}/rename_pdf_item.php`, 
                `user_id=${user?.user_id}&type=${itemToRename.type}&id=${itemToRename.id}&new_name=${encodeURIComponent(newName)}`,
                { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
            );
            setItemToRename(null);
        } catch (e) { 
            Alert.alert("Error", "Could not rename item"); 
            loadData(); // Revert on failure 
        }
    };

    const getCounts = (job) => {
        if (!job || !job.study_content) return { mcqs: 0, flashcards: 0 };
        try {
            const data = JSON.parse(job.study_content);
            return { mcqs: data.mcqs?.length || 0, flashcards: data.flashcards?.length || 0 };
        } catch(e) { return { mcqs: 0, flashcards: 0 }; }
    };

    const startStudy = async (job, mode, setIndex = 0) => {
        setSelectedJob(null);
        setSelectedMode(null);
        try {
            const data = JSON.parse(job.study_content);
            if (mode === 'quiz') {
                const allMcqs = (data.mcqs || []).map((m, i) => ({
                    mcq_id: i,
                    question: m.q || m.Question || '',
                    option_a: m.o?.[0] || m?.options?.[0] || '',
                    option_b: m.o?.[1] || m?.options?.[1] || '',
                    option_c: m.o?.[2] || m?.options?.[2] || '',
                    option_d: m.o?.[3] || m?.options?.[3] || '',
                    correct_answer: ['a', 'b', 'c', 'd'][m.a] || 'a',
                    explanation: m.e || m.Explanation || ''
                }));
                const subset = allMcqs.slice(setIndex * 10, (setIndex + 1) * 10);
                navigation.navigate('MyExamTest', { questions: subset, subjectName: `${job.file_name} (Set ${setIndex + 1})`, isAI: true });
            } else {
                const allCards = (data.flashcards || []).map((f, i) => ({
                    flashcard_id: i,
                    // Strict mapping: The 'Term' or 'Question' goes on the Front side (question_front).
                    question_front: f.f || f.Front || f.front || f.q || f.Question || '',
                    // The 'Definition' or 'Answer' goes on the Back side (answer_back). 
                    answer_back: f.b || f.Back || f.back || f.a || f.Answer || '',
                    subject: 'AI Generated',
                    topic: job.file_name
                }));
                const subset = allCards.slice(setIndex * 10, (setIndex + 1) * 10);
                navigation.navigate('Flashcards', { flashcardsData: subset, chapterId: `ai_${job.job_id}_set${setIndex}`, chapterName: `${job.file_name} (Set ${setIndex + 1})`, isAI: true });
            }
        } catch (e) {
            Alert.alert("Data Error", "Failed to parse study content.");
        }
    };

    const renderSets = (job, mode) => {
        if (!job || !job.study_content) return null;
        try {
            const data = JSON.parse(job.study_content);
            const items = mode === 'quiz' ? (data.mcqs || []) : (data.flashcards || []);
            const total = items.length;
            if (total === 0) return <Text style={{color:'white', marginTop:10}}>No items available.</Text>;
            
            const numSets = Math.ceil(total / 10);
            const setsArray = Array.from({length: numSets}, (_, i) => i);
            
            return setsArray.map((setIndex) => {
                const start = setIndex * 10 + 1;
                const end = Math.min((setIndex + 1) * 10, total);
                const count = end - start + 1;
                return (
                    <TouchableOpacity 
                        key={setIndex}
                        style={styles.setRow}
                        onPress={() => startStudy(job, mode, setIndex)}
                    >
                        <View style={styles.setRowLeft}>
                            <View style={styles.setBadge}><Text style={styles.setBadgeText}>{setIndex + 1}</Text></View>
                            <View>
                                <Text style={styles.setRowTitle}>Set {setIndex + 1}</Text>
                                <Text style={styles.setRowSub}>Questions {start} to {end} ({count} items)</Text>
                            </View>
                        </View>
                        <MaterialCommunityIcons name="play-circle" size={28} color="#10b981" />
                    </TouchableOpacity>
                );
            });
        } catch(e) { return null; }
    };

    const handleGenerateAggregate = () => {
        // Complete state flush immediately to unblock UI
        const pdfsToProcess = [...selectedPdfs];
        const modeTypeToUse = selectModeType;
        
        setIsMultiSelectMode(false);
        setSelectedPdfs([]);

        // Defer heavy parsing to ensure touch ripple and modal close are smooth
        InteractionManager.runAfterInteractions(() => {
            setTimeout(() => {
                let allMcqs = [];
                let allCards = [];
                
                pdfsToProcess.forEach(jobId => {
                    const job = jobs.find(j => j.job_id === jobId);
                    if (job && job.study_content) {
                        try {
                            const data = JSON.parse(job.study_content);
                            
                            if (data.mcqs) {
                                const normalizedMcqs = data.mcqs.map(m => {
                                    // The AI outputs index 0-3 for answer. Convert to 'a', 'b', 'c', 'd'
                                    const answerMap = ['a', 'b', 'c', 'd'];
                                    const correctLetter = (m.a !== undefined && m.a >= 0 && m.a <= 3) ? answerMap[m.a] : (m.correct_answer || 'a');

                                    return {
                                        question: m.q || m.question || m.question_text || '',
                                        option_a: (m.o && m.o[0]) ? m.o[0] : (m.option_a || ''),
                                        option_b: (m.o && m.o[1]) ? m.o[1] : (m.option_b || ''),
                                        option_c: (m.o && m.o[2]) ? m.o[2] : (m.option_c || ''),
                                        option_d: (m.o && m.o[3]) ? m.o[3] : (m.option_d || ''),
                                        correct_answer: correctLetter,
                                        explanation: m.e || m.explanation || '',
                                        source_pdf: job.file_name,
                                        ...m // Spread original just in case
                                    };
                                });
                                allMcqs = [...allMcqs, ...normalizedMcqs];
                            }
                            
                            if (data.flashcards) {
                                const normalizedCards = data.flashcards.map(f => ({
                                    question: f.f || f.front || f.q || '',
                                    answer: f.b || f.back || f.a || '',
                                    source_pdf: job.file_name,
                                    ...f
                                }));
                                allCards = [...allCards, ...normalizedCards];
                            }
                        } catch(e) { 
                            console.log("Failed to parse job content", e); 
                        }
                    }
                });
                
                // Prepare parameter payload
                const payloadParams = {
                    allMcqs: allMcqs,
                    allCards: allCards,
                    subjectNames: `Custom AI Generated (${pdfsToProcess.length} PDFs)`
                };

                // Explicit Navigation to the beautifully matched Worksheet/Exam Custom Screens
                if (modeTypeToUse === 'worksheet') {
                    navigation.navigate('AIPdfWorksheet', payloadParams);
                } else {
                    navigation.navigate('AIPdfExam', payloadParams);
                }
            }, 50); // slight delay
        });
    };

    const handleFolderSelect = useCallback((item) => {
        if (!isMultiSelectMode) setPathStack(prev => [...prev, { id: item.folder_id, name: item.name }]);
    }, [isMultiSelectMode]);

    const handleJobSelect = useCallback((item, isReady, isFailed) => {
        if (isMultiSelectMode) {
            if (!isReady) return; 
            setSelectedPdfs(prev => prev.includes(item.job_id) ? prev.filter(id => id !== item.job_id) : [...prev, item.job_id]);
        } else {
            if (isReady) setSelectedJob(item);
            else if (isFailed) {
                Alert.alert(
                    "Analysis Failed", 
                    `This PDF could not be analyzed.\n\nReason: ${item.error_message || 'Unknown error'}\n\nWould you like to delete it and try uploading again?`,
                    [
                        { text: "Keep", style: "cancel" },
                        { text: "Delete", style: "destructive", onPress: async () => {
                            setJobs(prev => prev.filter(j => j.job_id !== item.job_id));
                            try {
                                await axios.post(`${API_URL}/delete_pdf_job.php`, `user_id=${user?.user_id}&job_id=${item.job_id}`, { headers: {'Content-Type': 'application/x-www-form-urlencoded'}});
                            } catch(e) { loadData(); }
                        }}
                    ]
                );
            }
        }
    }, [isMultiSelectMode, user]);

    const renderFolder = useCallback(({ item, index }) => (
        <FolderCard 
            item={item} 
            index={index}
            isMultiSelectMode={isMultiSelectMode} 
            onFolderSelect={handleFolderSelect} 
            onFolderOptions={handleOptions} 
        />
    ), [isMultiSelectMode, handleFolderSelect]);

    const renderJob = useCallback(({ item, index }) => {
        const isSelected = selectedPdfs.includes(item.job_id);
        return (
            <JobCard 
                item={item} 
                index={index}
                isMultiSelectMode={isMultiSelectMode} 
                selectModeType={selectModeType} 
                isSelected={isSelected} 
                onJobSelect={handleJobSelect} 
                onJobOptions={handleOptions} 
            />
        );
    }, [isMultiSelectMode, selectModeType, selectedPdfs, handleJobSelect]);

    return (
        <View style={styles.container}>
            {isFocused && <StatusBar barStyle="light-content" backgroundColor="#0b0e14" /> }
            
            <View style={styles.header}>
                <SafeAreaView>
                    <View style={styles.topRow}>
                        <View>
                            <Text style={styles.vaultTitle}>Knowledge Vault</Text>
                            <Text style={styles.vaultSub}>My Documents</Text>
                        </View>
                        <View style={styles.headerIcons}>
                            <TouchableOpacity style={styles.iconBtn}><MaterialCommunityIcons name="account-circle-outline" size={26} color="white" /></TouchableOpacity>
                            <TouchableOpacity style={styles.iconBtn}><MaterialCommunityIcons name="bell-outline" size={24} color="white" /><View style={styles.redDot}/></TouchableOpacity>
                        </View>
                    </View>
                </SafeAreaView>
            </View>

            <FlatList
                data={jobs}
                renderItem={renderJob}
                keyExtractor={k => k.job_id.toString()}
                bounces={true} 
                style={styles.body} 
                showsVerticalScrollIndicator={false}
                initialNumToRender={8}
                maxToRenderPerBatch={8}
                windowSize={5}
                removeClippedSubviews={Platform.OS === 'android'}
                ListEmptyComponent={!loading ? <Text style={styles.emptyBig}>Upload a PDF above to create logic study tools</Text> : null}
                refreshControl={
                    <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#6366f1" />
                }
                ListFooterComponent={
                    <View style={{ height: 100 }} />
                }
                ListHeaderComponent={
                    <>
                        {/* New Prominent Dropzone */}
                        {!isMultiSelectMode && (
                            <TouchableOpacity style={styles.uploadZone} onPress={handleUpload} disabled={uploading}>
                                <LinearGradient colors={['#ec4899', '#8b5cf6']} start={{x: 0, y: 0}} end={{x: 1, y: 1}} style={styles.uploadGrad}>
                                    <View style={[styles.uploadIconWrap, { backgroundColor: '#ffffff30' }]}>
                                        {uploading 
                                            ? <ActivityIndicator size="small" color="white" />
                                            : <MaterialCommunityIcons name="file-document-plus-outline" size={28} color="white" />
                                        }
                                    </View>
                                    <Text style={styles.uTitle}>{uploading ? "Analyzing PDF... Please wait" : "Turn any PDF into a Study Set"}</Text>
                                    <Text style={[styles.uSub, { color: '#f3e8ff' }]}>
                                        {uploading ? "AI is generating questions & flashcards" : "Tap to select a document from your device"}
                                    </Text>
                                </LinearGradient>
                            </TouchableOpacity>
                        )}

                        {/* Global Generators Multi-Select Tools */}
                        {!isMultiSelectMode && (
                            <View style={styles.generatorsRow}>
                                <TouchableOpacity style={styles.genCard} onPress={() => { setIsMultiSelectMode(true); setSelectModeType('worksheet'); setSelectedPdfs([]); }}>
                                    <LinearGradient colors={['#f97316', '#e11d48']} start={{x: 0, y: 0}} end={{x: 1, y: 1}} style={styles.genGrad}>
                                        <MaterialCommunityIcons name="file-document-edit" size={28} color="white" />
                                        <Text style={styles.genTitle}>Worksheet</Text>
                                        <Text style={[styles.genSub, { color: '#ffe4e6' }]}>Combine PDFs</Text>
                                    </LinearGradient>
                                </TouchableOpacity>

                                <TouchableOpacity style={styles.genCard} onPress={() => { setIsMultiSelectMode(true); setSelectModeType('exam'); setSelectedPdfs([]); }}>
                                    <LinearGradient colors={['#fbbf24', '#d97706']} start={{x: 0, y: 0}} end={{x: 1, y: 1}} style={styles.genGrad}>
                                        <MaterialCommunityIcons name="text-box-check" size={28} color="white" />
                                        <Text style={styles.genTitle}>Custom Exam</Text>
                                        <Text style={[styles.genSub, { color: '#fef3c7' }]}>Combine PDFs</Text>
                                    </LinearGradient>
                                </TouchableOpacity>
                            </View>
                        )}

                        {/* Selection Instructional Banner */}
                        {isMultiSelectMode && (
                            <View style={styles.multiSelectBanner}>
                                <MaterialCommunityIcons name="checkbox-multiple-marked-circle" size={24} color={selectModeType === 'worksheet' ? "#10b981" : "#3b82f6"} />
                                <Text style={styles.multiSelectInfo}>
                                    Select PDFs below to combine them into a {selectModeType === 'worksheet' ? "Worksheet" : "Custom Exam"}.
                                </Text>
                            </View>
                        )}

                        {/* Path Navigation */}
                        {pathStack.length > 1 && (
                            <TouchableOpacity style={styles.backLink} onPress={() => setPathStack(pathStack.slice(0, -1))}>
                                <MaterialCommunityIcons name="chevron-left" size={20} color="#6366f1" />
                                <Text style={styles.backText}>{pathStack[pathStack.length-2].name}</Text>
                            </TouchableOpacity>
                        )}

                        {/* Folder Section */}
                        <View style={styles.sectionHeader}>
                            <Text style={styles.sectionTitle}>Study Folders</Text>
                            <TouchableOpacity onPress={() => setShowFolderModal(true)}><MaterialCommunityIcons name="plus-circle-outline" size={22} color="#6366f1" /></TouchableOpacity>
                        </View>
                        <FlatList 
                            data={folders} 
                            horizontal 
                            showsHorizontalScrollIndicator={false}
                            renderItem={renderFolder}
                            keyExtractor={m => m.folder_id.toString()}
                            initialNumToRender={5}
                            maxToRenderPerBatch={3}
                            windowSize={3}
                            removeClippedSubviews={Platform.OS === 'android'}
                            ListEmptyComponent={<Text style={styles.emptySmall}>No folders yet</Text>}
                        />

                        {/* Tiles Section */}
                        <Text style={[styles.sectionTitle, { marginTop: 25, marginBottom: 15 }]}>My Study Materials</Text>
                        {loading && <ActivityIndicator size="large" color="#6366f1" style={{marginTop: 50}} />}
                    </>
                }
            />

            {/* Floating Execution Bar for Multi-Select */}
            {isMultiSelectMode && (
                <View style={styles.floatingBar}>
                    <TouchableOpacity style={styles.cancelSelectBtn} onPress={() => { setIsMultiSelectMode(false); setSelectedPdfs([]); }}>
                        <MaterialCommunityIcons name="close" size={26} color="#f87171" />
                    </TouchableOpacity>
                    <TouchableOpacity 
                        style={[styles.generateBtn, selectedPdfs.length === 0 && { opacity: 0.5 }]} 
                        disabled={selectedPdfs.length === 0}
                        onPress={handleGenerateAggregate}
                    >
                        <LinearGradient colors={selectModeType === 'worksheet' ? ['#10b981', '#34d399'] : ['#3b82f6', '#60a5fa']} style={styles.genBtnGrad} start={[0,0]} end={[1,1]}>
                            <Text style={styles.genBtnText}>Generate {selectModeType === 'worksheet' ? 'Worksheet' : 'Exam'} ({selectedPdfs.length})</Text>
                            <MaterialCommunityIcons name="arrow-right" size={22} color="white" />
                        </LinearGradient>
                    </TouchableOpacity>
                </View>
            )}

            {/* Folder Creation Modal */}
            <Modal visible={showFolderModal} transparent animationType="fade" onRequestClose={() => setShowFolderModal(false)}>
                <View style={styles.modalOverlay}>
                    <View style={styles.modalBox}>
                        <Text style={styles.modalHead}>New Study Folder</Text>
                        <TextInput style={styles.modalInput} placeholder="Folder name (e.g. Physics)" placeholderTextColor="#64748b" value={newFolderName} onChangeText={setNewFolderName} autoFocus />
                        <View style={styles.modalBtns}>
                            <TouchableOpacity onPress={() => setShowFolderModal(false)}><Text style={styles.mCancel}>Cancel</Text></TouchableOpacity>
                            <TouchableOpacity onPress={handleCreateFolder} style={styles.mCreate}><Text style={styles.mCreateT}>Create</Text></TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>

            {/* Rename Item Modal */}
            <Modal visible={showRenameModal} transparent animationType="fade" onRequestClose={() => setShowRenameModal(false)}>
                <View style={styles.modalOverlay}>
                    <View style={styles.modalBox}>
                        <Text style={styles.modalHead}>Rename Item</Text>
                        <TextInput style={styles.modalInput} placeholder="New name" placeholderTextColor="#64748b" value={renameText} onChangeText={setRenameText} autoFocus />
                        <View style={styles.modalBtns}>
                            <TouchableOpacity onPress={() => setShowRenameModal(false)}><Text style={styles.mCancel}>Cancel</Text></TouchableOpacity>
                            <TouchableOpacity onPress={handleRenameFile} style={styles.mCreate}><Text style={styles.mCreateT}>Save</Text></TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>

            {/* Neon Study Hub */}
            <Modal 
                visible={selectedJob !== null} 
                transparent 
                animationType="slide" 
                onRequestClose={() => {
                    if (selectedMode !== null) {
                        setSelectedMode(null);
                    } else {
                        setSelectedJob(null);
                        setSelectedMode(null);
                    }
                }}
            >
                <View style={styles.sheetOverlay}>
                    <TouchableOpacity style={{flex: 1}} onPress={() => { setSelectedJob(null); setSelectedMode(null); }} />
                    <View style={styles.sheetBox}>
                        <View style={styles.sheetBar} />
                        <View style={styles.sheetFileHeader}>
                             <View style={styles.miniPdfIcon}><MaterialCommunityIcons name="file-pdf-box" size={30} color="#f43f5e" /></View>
                             <View style={{flex:1, marginLeft: 15}}>
                                 <Text style={styles.sheetFileName} numberOfLines={1}>{selectedJob?.file_name}</Text>
                                 <Text style={styles.sheetFileStatus}>AI Analysis 100% Complete</Text>
                             </View>
                        </View>
                        
                        {selectedMode ? (
                            <View style={styles.setsContainer}>
                                <TouchableOpacity style={styles.backToModesBtn} onPress={() => setSelectedMode(null)}>
                                    <MaterialCommunityIcons name="arrow-left" size={20} color="white" />
                                    <Text style={{color: 'white', fontWeight: 'bold', marginLeft: 8}}>Back to Modes</Text>
                                </TouchableOpacity>
                                <Text style={styles.setsTitle}>{selectedMode === 'quiz' ? 'Select Quiz Set' : 'Select Flashcard Set'}</Text>
                                <ScrollView style={{maxHeight: 250}} showsVerticalScrollIndicator={false}>
                                    {renderSets(selectedJob, selectedMode)}
                                </ScrollView>
                            </View>
                        ) : (
                            <View style={styles.studyModesGrid}>
                                {/* Feature 1 */}
                                <TouchableOpacity style={styles.modeTile} onPress={() => {
                                    const c = getCounts(selectedJob).mcqs;
                                    if (c > 10) setSelectedMode('quiz');
                                    else startStudy(selectedJob, 'quiz', 0);
                                }}>
                                    <LinearGradient colors={['#f59e0b', '#fbbf24']} style={styles.modeIconCard}><MaterialCommunityIcons name="trophy-outline" size={32} color="white" /></LinearGradient>
                                    <Text style={styles.modeTitleCard}>AI MCQ Quiz</Text>
                                    <View style={styles.takeBtnCard}><Text style={styles.takeBtnTextCard}>{getCounts(selectedJob).mcqs} Qs</Text></View>
                                </TouchableOpacity>

                                {/* Feature 2 */}
                                <TouchableOpacity style={styles.modeTile} onPress={() => {
                                    const c = getCounts(selectedJob).flashcards;
                                    if (c > 10) setSelectedMode('flash');
                                    else startStudy(selectedJob, 'flash', 0);
                                }}>
                                    <LinearGradient colors={['#a855f7', '#d946ef']} style={styles.modeIconCard}><MaterialCommunityIcons name="cards-outline" size={32} color="white" /></LinearGradient>
                                    <Text style={styles.modeTitleCard}>Flashcards</Text>
                                    <View style={styles.takeBtnCard}><Text style={styles.takeBtnTextCard}>{getCounts(selectedJob).flashcards} Cards</Text></View>
                                </TouchableOpacity>
                            </View>
                        )}
                    </View>
                </View>
            </Modal>
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#0b0e14' },
    header: { paddingHorizontal: 20, paddingTop: 10, paddingBottom: 5 },
    topRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
    vaultTitle: { color: 'white', fontSize: 22, fontWeight: '900' },
    vaultSub: { color: '#94a3b8', fontSize: 14, fontWeight: '500' },
    headerIcons: { flexDirection: 'row', alignItems: 'center' },
    iconBtn: { marginLeft: 15 },
    redDot: { width: 8, height: 8, backgroundColor: '#f43f5e', borderRadius: 4, position: 'absolute', top: 0, right: 0, borderWidth: 2, borderColor: '#0b0e14' },
    
    body: { flex: 1, paddingHorizontal: 20 },
    uploadZone: { marginTop: 5, borderRadius: 20, borderWidth: 0, overflow: 'hidden' },
    uploadGrad: { paddingVertical: 18, paddingHorizontal: 20, alignItems: 'center', justifyContent: 'center' },
    uploadIconWrap: { width: 50, height: 50, borderRadius: 25, backgroundColor: '#ffffff10', alignItems: 'center', justifyContent: 'center', marginBottom: 8 },
    uTitle: { color: 'white', fontSize: 16, fontWeight: 'bold' },
    uSub: { color: '#94a3b8', fontSize: 12, marginTop: 3, textAlign: 'center' },

    sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 35, marginBottom: 15 },
    sectionTitle: { color: 'white', fontSize: 18, fontWeight: '800' },
    
    folderCard: { width: 110, height: 110, marginRight: 15 },
    glassFolder: { flex: 1, borderRadius: 24, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#ffffff10' },
    folderLabel: { color: 'white', fontSize: 13, fontWeight: 'bold', marginTop: 10, width: '85%', textAlign: 'center' },

    pdfRow: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#ffffff08', borderRadius: 20, padding: 15, marginBottom: 15, borderWidth: 1.5, borderColor: '#ffffff10', overflow: 'hidden' },
    pdfRowIcon: { width: 60, height: 60, alignItems: 'center', justifyContent: 'center', marginRight: 15 },
    pdfRowContent: { flex: 1, justifyContent: 'center' },
    pdfRowTitle: { color: 'white', fontSize: 15, fontWeight: 'bold', marginBottom: 2 },
    statusPill: { flexDirection: 'row', alignItems: 'center', alignSelf: 'flex-start', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 12 },
    statusDot: { width: 6, height: 6, borderRadius: 3, marginRight: 6 },
    statusPillText: { fontSize: 11, fontWeight: 'bold' },
    
    backLink: { flexDirection: 'row', alignItems: 'center', marginTop: 20 },
    backText: { color: '#6366f1', fontSize: 15, fontWeight: 'bold' },
    
    emptySmall: { color: '#475569', fontSize: 13, marginVertical: 30 },
    emptyBig: { color: '#475569', fontSize: 15, textAlign: 'center', marginTop: 50 },

    modalOverlay: { flex: 1, backgroundColor: '#000000dd', justifyContent: 'center', padding: 25 },
    modalBox: { backgroundColor: '#1e293b', borderRadius: 32, padding: 25, borderWidth: 1, borderColor: '#ffffff20' },
    modalHead: { color: 'white', fontSize: 20, fontWeight: 'bold', marginBottom: 20 },
    modalInput: { backgroundColor: '#0b0e14', borderRadius: 16, padding: 18, color: 'white', fontSize: 16, marginBottom: 25 },
    modalBtns: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center' },
    mCancel: { color: '#94a3b8', fontWeight: 'bold', marginRight: 25 },
    mCreate: { backgroundColor: '#6366f1', paddingHorizontal: 30, paddingVertical: 14, borderRadius: 16 },
    mCreateT: { color: 'white', fontWeight: 'bold' },

    sheetOverlay: { flex: 1, backgroundColor: '#00000090', justifyContent: 'flex-end' },
    sheetBox: { backgroundColor: '#0f172a', borderTopLeftRadius: 40, borderTopRightRadius: 40, padding: 25, paddingBottom: 50, borderTopWidth: 1, borderColor: '#334155' },
    sheetBar: { width: 50, height: 6, backgroundColor: '#334155', borderRadius: 3, alignSelf: 'center', marginBottom: 30 },
    sheetFileHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 25 },
    miniPdfIcon: { backgroundColor: '#f43f5e20', padding: 12, borderRadius: 18 },
    sheetFileName: { color: 'white', fontSize: 18, fontWeight: 'bold', paddingRight: 10 },
    sheetFileStatus: { color: '#10b981', fontSize: 13, fontWeight: 'bold', marginTop: 4 },
    
    studyModesGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', gap: 15 },
    modeTile: { width: '47%', backgroundColor: '#ffffff05', borderRadius: 28, padding: 18, alignItems: 'center', borderWidth: 1, borderColor: '#ffffff08' },
    modeIconCard: { width: 60, height: 60, borderRadius: 20, alignItems: 'center', justifyContent: 'center', marginBottom: 15 },
    modeTitleCard: { color: 'white', fontSize: 14, fontWeight: '900', textAlign: 'center', marginBottom: 10 },
    modeSubCard: { color: '#94a3b8', fontSize: 11, fontWeight: '700' },
    takeBtnCard: { backgroundColor: '#ffffff10', paddingHorizontal: 16, paddingVertical: 8, borderRadius: 12 },
    takeBtnTextCard: { color: 'white', fontSize: 12, fontWeight: '900' },
    
    // Aggregator Top Tools UI
    generatorsRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 15 },
    genCard: { width: '48%', backgroundColor: '#ffffff05', borderRadius: 20, borderWidth: 1, borderColor: '#ffffff10', overflow: 'hidden' },
    genGrad: { padding: 15, alignItems: 'center' },
    genTitle: { color: 'white', fontSize: 13, fontWeight: 'bold', marginTop: 10 },
    genSub: { color: '#94a3b8', fontSize: 11, marginTop: 2 },
    
    // Multi Select UI
    multiSelectBanner: { flexDirection: 'row', backgroundColor: '#1e293b', padding: 15, borderRadius: 16, borderWidth: 1, borderColor: '#334155', marginTop: 10, alignItems: 'center' },
    multiSelectInfo: { color: 'white', fontSize: 13, flex: 1, marginLeft: 15, fontWeight: '500' },
    checkbox: { width: 24, height: 24, borderRadius: 6, borderWidth: 2, borderColor: '#64748b', alignItems: 'center', justifyContent: 'center' },
    
    floatingBar: { position: 'absolute', bottom: 30, left: 20, right: 20, flexDirection: 'row', alignItems: 'center', backgroundColor: '#1e293b', borderRadius: 100, padding: 10, shadowColor: '#000', shadowOffset: { width: 0, height: 10}, shadowOpacity: 0.5, shadowRadius: 20, elevation: 15, borderWidth: 1, borderColor: '#ffffff20' },
    cancelSelectBtn: { width: 50, height: 50, borderRadius: 25, backgroundColor: '#ffffff10', alignItems: 'center', justifyContent: 'center', marginRight: 10 },
    generateBtn: { flex: 1, borderRadius: 25, overflow: 'hidden' },
    genBtnGrad: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 25, height: 50 },
    genBtnText: { color: 'white', fontSize: 16, fontWeight: 'bold' },
    
    // Sets UI Styles
    setsContainer: { marginTop: 10 },
    backToModesBtn: { flexDirection: 'row', alignItems: 'center', marginBottom: 15, alignSelf: 'flex-start', paddingVertical: 5, paddingHorizontal: 10, backgroundColor: '#ffffff10', borderRadius: 10 },
    setsTitle: { color: 'white', fontSize: 18, fontWeight: 'bold', marginBottom: 15 },
    setRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: '#ffffff08', padding: 15, borderRadius: 16, marginBottom: 10, borderWidth: 1, borderColor: '#ffffff15' },
    setRowLeft: { flexDirection: 'row', alignItems: 'center' },
    setBadge: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#6366f140', alignItems: 'center', justifyContent: 'center', marginRight: 15 },
    setBadgeText: { color: '#818cf8', fontWeight: 'bold', fontSize: 16 },
    setRowTitle: { color: 'white', fontSize: 16, fontWeight: 'bold' },
    setRowSub: { color: '#94a3b8', fontSize: 12, marginTop: 2 },
});

export default PDFToExamScreen;
