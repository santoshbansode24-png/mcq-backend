import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, FlatList,
    ActivityIndicator, Alert, Animated, Platform, StatusBar,
    Dimensions, ScrollView, Modal, TextInput, Pressable, RefreshControl
} from 'react-native';
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

const PDFToExamScreen = ({ user, navigation }) => {
    const { theme, isDarkMode } = useTheme();
    
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
            const res = await axios.post(`${API_URL}/upload_pdf_study.php`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                timeout: 120000 // 120s timeout to allow large or slow uploads
            });
            if (res.data.status === 'success') { 
                Alert.alert("Success! 🚀", "AI is processing in the background."); 
                loadData(true); 
            } else {
                Alert.alert("Error", res.data.message || "Upload failed.");
            }
        } catch (e) { 
            Alert.alert("Upload Failed", "Network timeout. Make sure your internet connection is stable and try again."); 
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
                        try {
                            await axios.post(`${API_URL}/${endpoint}`, `user_id=${user?.user_id}&${key}=${id}`, { headers: {'Content-Type': 'application/x-www-form-urlencoded'}});
                            loadData();
                        } catch(e) { Alert.alert("Error", "Delete failed"); }
                    }}
                ]);
            }}
        ]);
    };

    const handleRenameFile = async () => {
        if (!renameText.trim() || !itemToRename) return;
        try {
            await axios.post(`${API_URL}/rename_pdf_item.php`, 
                `user_id=${user?.user_id}&type=${itemToRename.type}&id=${itemToRename.id}&new_name=${encodeURIComponent(renameText)}`,
                { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
            );
            setShowRenameModal(false);
            setRenameText('');
            setItemToRename(null);
            loadData();
        } catch (e) { Alert.alert("Error", "Could not rename item"); }
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
                    // The AI typically puts the term on the "Front" (f) and definition on the "Back" (b).
                    // The user wants the question (definition) to appear first, so we map 'b' to the front.
                    question_front: f.q || f.Question || f.b || f.Back || f.back || '',
                    answer_back: f.a || f.Answer || f.f || f.Front || f.front || '',
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

    const renderFolder = ({ item }) => (
        <TouchableOpacity 
            style={styles.folderCard} 
            onPress={() => setPathStack([...pathStack, { id: item.folder_id, name: item.name }])}
            onLongPress={() => handleOptions('folder', item)}
        >
            <LinearGradient colors={['#ffffff15', '#ffffff05']} style={styles.glassFolder}>
                <MaterialCommunityIcons name="molecule" size={30} color="#818cf8" />
                <Text style={styles.folderLabel} numberOfLines={1}>{item.name}</Text>
            </LinearGradient>
        </TouchableOpacity>
    );

    const renderJob = ({ item }) => {
        const isReady = item.status === 'completed';
        const isFailed = item.status === 'failed';
        const progress = isReady ? 100 : (item.progress || 10);
        return (
            <TouchableOpacity 
                style={styles.pdfTile} 
                onPress={() => {
                    if (isReady) setSelectedJob(item);
                    else if (isFailed) Alert.alert("Failed", "This PDF could not be analyzed. Please try another one.");
                }}
                onLongPress={() => handleOptions('file', item)}
            >
                <View style={[styles.pdfCard, { borderColor: isReady ? '#00f3ff50' : (isFailed ? '#ef444450' : '#ffffff10') }]}>
                    <View style={styles.iconContainer}>
                        <CircularProgress progress={progress} size={85} color={isReady ? "#00f3ff" : (isFailed ? "#ef4444" : "#f43f5e")} strokeWidth={3} />
                        <MaterialCommunityIcons name={isFailed ? "file-cancel" : "file-pdf-box"} size={40} color={isReady ? "#00f3ff" : (isFailed ? "#ef4444" : "#f43f5e")} />
                    </View>
                    <Text style={styles.pdfName} numberOfLines={2}>{item.file_name}</Text>
                    <Text style={[styles.pdfStatus, { color: isFailed ? '#f87171' : (isReady ? '#00f3ff' : '#94a3b8') }]}>
                        {isFailed ? 'Analysis Failed' : (isReady ? '100% Complete' : `${progress}% Processing`)}
                    </Text>
                </View>
            </TouchableOpacity>
        );
    };

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" translucent />
            
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

            <ScrollView 
                bounces={true} 
                style={styles.body} 
                showsVerticalScrollIndicator={false}
                refreshControl={
                    <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#6366f1" />
                }
            >
                {/* Upload Button */}
                <TouchableOpacity style={styles.uploadBtn} onPress={handleUpload} disabled={uploading}>
                    <LinearGradient colors={['#6366f130', '#6366f110']} style={styles.uploadGrad}>
                        <View style={styles.uploadPlus}><MaterialCommunityIcons name="plus" size={24} color="white" /></View>
                        <View>
                            <Text style={styles.uTitle}>Upload PDF</Text>
                            <Text style={styles.uSub}>Upload New PDF</Text>
                        </View>
                    </LinearGradient>
                </TouchableOpacity>

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
                    ListEmptyComponent={<Text style={styles.emptySmall}>No folders yet</Text>}
                />

                {/* Tiles Section */}
                <Text style={[styles.sectionTitle, { marginTop: 25 }]}>Study Tiles</Text>
                {loading ? <ActivityIndicator size="large" color="#6366f1" style={{marginTop: 50}} /> : (
                    <FlatList 
                        data={jobs} 
                        renderItem={renderJob} 
                        numColumns={2} 
                        scrollEnabled={false}
                        columnWrapperStyle={styles.row}
                        keyExtractor={k => k.job_id.toString()}
                        ListEmptyComponent={<Text style={styles.emptyBig}>Upload a PDF to start AI analysis</Text>}
                    />
                )}
                <View style={{ height: 100 }} />
            </ScrollView>

            {/* Folder Creation Modal */}
            <Modal visible={showFolderModal} transparent animationType="fade">
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
            <Modal visible={showRenameModal} transparent animationType="fade">
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
            <Modal visible={selectedJob !== null} transparent animationType="slide">
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
                            <View style={styles.studyModes}>
                                <TouchableOpacity style={styles.modeCard} onPress={() => {
                                    const c = getCounts(selectedJob).mcqs;
                                    if (c > 10) setSelectedMode('quiz');
                                    else startStudy(selectedJob, 'quiz', 0);
                                }}>
                                    <LinearGradient colors={['#f59e0b', '#fbbf24']} style={styles.modeIcon} shadowOpacity={0.5}>
                                        <MaterialCommunityIcons name="trophy-outline" size={32} color="white" />
                                    </LinearGradient>
                                    <Text style={styles.modeTitle}>AI MCQ Quiz</Text>
                                    <Text style={styles.modeSub}>{getCounts(selectedJob).mcqs} Qs Available</Text>
                                    <View style={styles.takeBtn}><Text style={styles.takeBtnText}>Take Quiz</Text></View>
                                </TouchableOpacity>

                                <TouchableOpacity style={styles.modeCard} onPress={() => {
                                    const c = getCounts(selectedJob).flashcards;
                                    if (c > 10) setSelectedMode('flash');
                                    else startStudy(selectedJob, 'flash', 0);
                                }}>
                                    <LinearGradient colors={['#a855f7', '#d946ef']} style={styles.modeIcon} shadowOpacity={0.5}>
                                        <MaterialCommunityIcons name="brain" size={32} color="white" />
                                    </LinearGradient>
                                    <Text style={styles.modeTitle}>Concept Flashcards</Text>
                                    <Text style={styles.modeSub}>{getCounts(selectedJob).flashcards} Cards Available</Text>
                                    <View style={[styles.takeBtn, { backgroundColor: '#a855f730' }]}><Text style={[styles.takeBtnText, { color: '#e879f9' }]}>Start Review</Text></View>
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
    header: { paddingHorizontal: 20, paddingTop: 10, paddingBottom: 20 },
    topRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
    vaultTitle: { color: 'white', fontSize: 24, fontWeight: '900' },
    vaultSub: { color: '#94a3b8', fontSize: 16, fontWeight: '500' },
    headerIcons: { flexDirection: 'row', alignItems: 'center' },
    iconBtn: { marginLeft: 15 },
    redDot: { width: 8, height: 8, backgroundColor: '#f43f5e', borderRadius: 4, position: 'absolute', top: 0, right: 0, borderWidth: 2, borderColor: '#0b0e14' },
    
    body: { flex: 1, paddingHorizontal: 20 },
    uploadBtn: { marginTop: 10, borderRadius: 20, borderWidth: 1, borderColor: '#6366f150', overflow: 'hidden' },
    uploadGrad: { padding: 20, flexDirection: 'row', alignItems: 'center' },
    uploadPlus: { width: 45, height: 45, borderRadius: 15, backgroundColor: '#6366f1', alignItems: 'center', justifyContent: 'center', marginRight: 15 },
    uTitle: { color: 'white', fontSize: 18, fontWeight: 'bold' },
    uSub: { color: '#94a3b8', fontSize: 12 },

    sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 30, marginBottom: 15 },
    sectionTitle: { color: 'white', fontSize: 18, fontWeight: '800' },
    
    folderCard: { width: 100, height: 110, marginRight: 15 },
    glassFolder: { flex: 1, borderRadius: 24, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#ffffff10' },
    folderLabel: { color: 'white', fontSize: 13, fontWeight: 'bold', marginTop: 10, width: '80%', textAlign: 'center' },

    pdfTile: { width: TILE_SIZE, height: 180, marginBottom: 20 },
    pdfCard: { flex: 1, backgroundColor: '#ffffff08', borderRadius: 28, borderWidth: 1.5, padding: 15, alignItems: 'center', justifyContent: 'space-between' },
    iconContainer: { width: 85, height: 85, alignItems: 'center', justifyContent: 'center' },
    pdfName: { color: 'white', fontSize: 13, fontWeight: 'bold', textAlign: 'center' },
    pdfStatus: { fontSize: 10, fontWeight: '800' },
    row: { justifyContent: 'space-between' },
    
    backLink: { flexDirection: 'row', alignItems: 'center', marginTop: 15 },
    backText: { color: '#6366f1', fontSize: 14, fontWeight: 'bold' },
    
    emptySmall: { color: '#475569', fontSize: 12, marginVertical: 30 },
    emptyBig: { color: '#475569', fontSize: 14, textAlign: 'center', marginTop: 50 },

    modalOverlay: { flex: 1, backgroundColor: '#000000aa', justifyContent: 'center', padding: 30 },
    modalBox: { backgroundColor: '#1e293b', borderRadius: 32, padding: 25, borderWidth: 1, borderColor: '#ffffff20' },
    modalHead: { color: 'white', fontSize: 20, fontWeight: 'bold', marginBottom: 20 },
    modalInput: { backgroundColor: '#0f172a', borderRadius: 16, padding: 18, color: 'white', fontSize: 16, marginBottom: 25 },
    modalBtns: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center' },
    mCancel: { color: '#94a3b8', fontWeight: 'bold', marginRight: 25 },
    mCreate: { backgroundColor: '#6366f1', paddingHorizontal: 30, paddingVertical: 14, borderRadius: 16 },
    mCreateT: { color: 'white', fontWeight: 'bold' },

    sheetOverlay: { flex: 1, backgroundColor: '#00000060', justifyContent: 'flex-end' },
    sheetBox: { backgroundColor: '#0f172a', borderTopLeftRadius: 40, borderTopRightRadius: 40, padding: 25, paddingBottom: 50, borderTopWidth: 1, borderColor: '#ffffff10' },
    sheetBar: { width: 40, height: 5, backgroundColor: '#334155', borderRadius: 3, alignSelf: 'center', marginBottom: 30 },
    sheetFileHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 35 },
    miniPdfIcon: { backgroundColor: '#f43f5e20', padding: 10, borderRadius: 15 },
    sheetFileName: { color: 'white', fontSize: 18, fontWeight: 'bold' },
    sheetFileStatus: { color: '#10b981', fontSize: 12, fontWeight: 'bold' },
    studyModes: { flexDirection: 'row', justifyContent: 'space-between' },
    modeCard: { width: '47%', backgroundColor: '#ffffff05', borderRadius: 30, padding: 15, alignItems: 'center', borderWidth: 1, borderColor: '#ffffff08' },
    modeIcon: { width: 70, height: 70, borderRadius: 25, alignItems: 'center', justifyContent: 'center', marginBottom: 15 },
    modeTitle: { color: 'white', fontSize: 15, fontWeight: '800' },
    modeSub: { color: '#94a3b8', fontSize: 10, marginTop: 5, marginBottom: 15 },
    takeBtn: { backgroundColor: '#f59e0b20', paddingHorizontal: 20, paddingVertical: 10, borderRadius: 12 },
    takeBtnText: { color: '#fbbf24', fontSize: 12, fontWeight: '900' },
    
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
