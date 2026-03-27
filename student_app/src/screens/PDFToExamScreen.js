import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, FlatList,
    ActivityIndicator, Alert, Animated, Platform, StatusBar,
    Dimensions, ScrollView, Modal, TextInput, Pressable
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
    
    const currentFolderId = pathStack[pathStack.length - 1].id;

    useEffect(() => { loadData(); }, [pathStack]);

    const loadData = async () => {
        setLoading(true);
        try {
            const folderUrl = `${API_URL}/get_pdf_folders.php?user_id=${user?.user_id || 0}&parent_id=${currentFolderId}`;
            const fRes = await axios.get(folderUrl);
            if (fRes.data.status === 'success') setFolders(fRes.data.data);
            
            const jobFilter = currentFolderId === 'root' ? 'root' : currentFolderId;
            const jRes = await axios.get(`${API_URL}/get_pdf_study_status.php?user_id=${user?.user_id || 0}&folder_id=${jobFilter}`);
            if (jRes.data.status === 'success') setJobs(jRes.data.data);
        } catch (e) { console.log("Load Error", e); } 
        finally { setLoading(false); }
    };

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
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (res.data.status === 'success') { Alert.alert("Success! 🚀", "AI is processing. Refresh in 30s."); loadData(); }
        } catch (e) { Alert.alert("Upload Task Queued", "Processing in background..."); } 
        finally { setUploading(false); }
    };

    const handleDelete = (type, id) => {
        Alert.alert("Delete Item?", `Are you sure you want to delete this ${type}?`, [
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
    };

    const startStudy = async (job, mode) => {
        setSelectedJob(null);
        const data = JSON.parse(job.study_content);
        if (mode === 'quiz') {
            navigation.navigate('MyExamTest', { questions: data.mcqs, title: job.file_name, isAI: true });
        } else {
            navigation.navigate('Flashcards', { cards: data.flashcards, title: job.file_name, isAI: true });
        }
    };

    const renderFolder = ({ item }) => (
        <TouchableOpacity 
            style={styles.folderCard} 
            onPress={() => setPathStack([...pathStack, { id: item.folder_id, name: item.name }])}
            onLongPress={() => handleDelete('folder', item.folder_id)}
        >
            <LinearGradient colors={['#ffffff15', '#ffffff05']} style={styles.glassFolder}>
                <MaterialCommunityIcons name="molecule" size={30} color="#818cf8" />
                <Text style={styles.folderLabel} numberOfLines={1}>{item.name}</Text>
            </LinearGradient>
        </TouchableOpacity>
    );

    const renderJob = ({ item }) => {
        const isReady = item.status === 'completed';
        const progress = isReady ? 100 : (item.progress || 10);
        return (
            <TouchableOpacity 
                style={styles.pdfTile} 
                onPress={() => isReady ? setSelectedJob(item) : null}
                onLongPress={() => handleDelete('file', item.job_id)}
            >
                <View style={[styles.pdfCard, { borderColor: isReady ? '#00f3ff50' : '#ffffff10' }]}>
                    <View style={styles.iconContainer}>
                        <CircularProgress progress={progress} size={85} color={isReady ? "#00f3ff" : "#f43f5e"} strokeWidth={3} />
                        <MaterialCommunityIcons name="file-pdf-box" size={40} color={isReady ? "#00f3ff" : "#f43f5e"} />
                    </View>
                    <Text style={styles.pdfName} numberOfLines={2}>{item.file_name}</Text>
                    <Text style={[styles.pdfStatus, { color: isReady ? '#00f3ff' : '#94a3b8' }]}>
                        {isReady ? '100% Complete' : `${progress}% Processing`}
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

            <ScrollView bounces={false} style={styles.body} showsVerticalScrollIndicator={false}>
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

            {/* Neon Study Hub */}
            <Modal visible={selectedJob !== null} transparent animationType="slide">
                <View style={styles.sheetOverlay}>
                    <TouchableOpacity style={{flex: 1}} onPress={() => setSelectedJob(null)} />
                    <View style={styles.sheetBox}>
                        <View style={styles.sheetBar} />
                        <View style={styles.sheetFileHeader}>
                             <View style={styles.miniPdfIcon}><MaterialCommunityIcons name="file-pdf-box" size={30} color="#f43f5e" /></View>
                             <View style={{flex:1, marginLeft: 15}}>
                                 <Text style={styles.sheetFileName} numberOfLines={1}>{selectedJob?.file_name}</Text>
                                 <Text style={styles.sheetFileStatus}>AI Analysis 100% Complete</Text>
                             </View>
                        </View>
                        
                        <View style={styles.studyModes}>
                            <TouchableOpacity style={styles.modeCard} onPress={() => startStudy(selectedJob, 'quiz')}>
                                <LinearGradient colors={['#f59e0b', '#fbbf24']} style={styles.modeIcon} shadowOpacity={0.5}>
                                    <MaterialCommunityIcons name="trophy-outline" size={32} color="white" />
                                </LinearGradient>
                                <Text style={styles.modeTitle}>AI MCQ Quiz</Text>
                                <Text style={styles.modeSub}>Practice Quiz - 25 Qs</Text>
                                <View style={styles.takeBtn}><Text style={styles.takeBtnText}>Take Quiz</Text></View>
                            </TouchableOpacity>

                            <TouchableOpacity style={styles.modeCard} onPress={() => startStudy(selectedJob, 'flash')}>
                                <LinearGradient colors={['#a855f7', '#d946ef']} style={styles.modeIcon} shadowOpacity={0.5}>
                                    <MaterialCommunityIcons name="brain" size={32} color="white" />
                                </LinearGradient>
                                <Text style={styles.modeTitle}>Concept Flashcards</Text>
                                <Text style={styles.modeSub}>Revise Terms - 45 Cards</Text>
                                <View style={[styles.takeBtn, { backgroundColor: '#a855f730' }]}><Text style={[styles.takeBtnText, { color: '#e879f9' }]}>Start Review</Text></View>
                            </TouchableOpacity>
                        </View>
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
    takeBtnText: { color: '#fbbf24', fontSize: 12, fontWeight: '900' }
});

export default PDFToExamScreen;
