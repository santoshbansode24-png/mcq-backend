import React, { useState, useEffect, useCallback } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, FlatList,
    ActivityIndicator, Alert, StatusBar, Dimensions,
    Modal, TextInput, RefreshControl, BackHandler, InteractionManager, Platform
} from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as DocumentPicker from 'expo-document-picker';
import * as FileSystem from 'expo-file-system';
import Svg, { Circle } from 'react-native-svg';
import axios from 'axios';
import { API_URL, WORKER_SECRET } from '../api/config';

const { width } = Dimensions.get('window');

// --- Reusable Components ---

const CircularProgress = ({ progress = 0, size = 60, strokeWidth = 5, color = "#00f3ff" }) => {
    const radius = (size - strokeWidth) / 2;
    const circumference = radius * 2 * Math.PI;
    const offset = circumference - (progress / 100) * circumference;
    return (
        <Svg width={size} height={size} style={{ position: 'absolute' }}>
            <Circle cx={size / 2} cy={size / 2} r={radius} stroke="#ffffff10" strokeWidth={strokeWidth} fill="transparent" />
            <Circle cx={size / 2} cy={size / 2} r={radius} stroke={color} strokeWidth={strokeWidth} fill="transparent" strokeDasharray={circumference} strokeDashoffset={offset} strokeLinecap="round" transform={`rotate(-90 ${size / 2} ${size / 2})`} />
        </Svg>
    );
};

const VIBRANT_THEMES = [
    { start: '#10b981', end: '#059669', shadow: '#10b981', bg: '#10b98115' },
    { start: '#f43f5e', end: '#e11d48', shadow: '#f43f5e', bg: '#f43f5e15' },
    { start: '#3b82f6', end: '#2563eb', shadow: '#3b82f6', bg: '#3b82f615' },
    { start: '#8b5cf6', end: '#6d28d9', shadow: '#8b5cf6', bg: '#8b5cf615' },
];

const getThemeColor = (idx) => VIBRANT_THEMES[(idx || 0) % VIBRANT_THEMES.length];

// --- Main Screen ---

const PDFToExamScreen = ({ user, navigation }) => {
    const isFocused = useIsFocused();

    // State
    const [folders, setFolders] = useState([]);
    const [jobs, setJobs] = useState([]);
    const [pathStack, setPathStack] = useState([{ id: 'root', name: 'Vault' }]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

    // Selection State
    const [isMultiSelectMode, setIsMultiSelectMode] = useState(false);
    const [selectedPdfs, setSelectedPdfs] = useState([]);
    const [selectModeType, setSelectModeType] = useState(null);

    const currentFolderId = pathStack[pathStack.length - 1].id;

    useEffect(() => { loadData(); }, [pathStack]);

    // Polling for progress
    useEffect(() => {
        let interval;
        const hasPending = jobs.some(j => j.status === 'processing' || j.status === 'pending');
        
        if (hasPending) {
            // Hybrid Trigger: If a job is pending, ping the worker once to start it
            triggerWorker();
            interval = setInterval(() => loadData(true), 5000);
        }
        return () => clearInterval(interval);
    }, [jobs]);

    const triggerWorker = async () => {
        try {
            // Ping the worker with the secure key. 
            // The worker processes 1 job and returns instantly.
            await axios.get(`${API_URL}/pdf_worker_ai.php?key=${WORKER_SECRET}`);
        } catch (e) {
            // Silently fail, polling will try again
            console.log("Worker Ping Background:", e.message);
        }
    };

    // Handle Hardware Back Button
    useEffect(() => {
        const backAction = () => {
            if (isMultiSelectMode) {
                setIsMultiSelectMode(false);
                setSelectedPdfs([]);
                return true;
            }
            if (pathStack.length > 1) {
                setPathStack(prev => prev.slice(0, -1));
                return true;
            }
            return false;
        };
        const backHandler = BackHandler.addEventListener("hardwareBackPress", backAction);
        return () => backHandler.remove();
    }, [isMultiSelectMode, pathStack]);

    const loadData = async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            const folderUrl = `${API_URL}/get_pdf_folders.php?user_id=${user?.user_id || 0}&parent_id=${currentFolderId}`;
            const fRes = await axios.get(folderUrl);

            const jobFilter = currentFolderId === 'root' ? 'root' : currentFolderId;
            const jRes = await axios.get(`${API_URL}/get_pdf_study_status.php?user_id=${user?.user_id || 0}&folder_id=${jobFilter}`);

            if (fRes.data.status === 'success') setFolders(fRes.data.data);
            if (jRes.data.status === 'success') setJobs(jRes.data.data);
        } catch (e) {
            console.error("Fetch Error:", e);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const handleUpload = async () => {
        try {
            const doc = await DocumentPicker.getDocumentAsync({ type: 'application/pdf', copyToCacheDirectory: true });
            if (doc.canceled) return;

            const file = doc.assets[0];
            setUploading(true);

            // Create FormData
            const formData = new FormData();
            formData.append('pdf_file', {
                uri: Platform.OS === 'android' ? file.uri : file.uri.replace('file://', ''),
                name: file.name || 'document.pdf',
                type: 'application/pdf',
            });
            formData.append('user_id', user?.user_id?.toString());
            if (currentFolderId !== 'root') formData.append('folder_id', currentFolderId.toString());

            const response = await fetch(`${API_URL}/upload_pdf_study.php`, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json', 'Content-Type': 'multipart/form-data' },
            });

            const result = await response.json();
            if (result.status === 'success') {
                Alert.alert("Success", "PDF uploaded and processing started!");
                loadData();
            } else {
                Alert.alert("Upload Failed", result.message);
            }
        } catch (e) {
            Alert.alert("Error", "Check your connection or file size.");
            console.log(e);
        } finally {
            setUploading(false);
        }
    };

    const toggleSelect = (jobId) => {
        setSelectedPdfs(prev =>
            prev.includes(jobId) ? prev.filter(id => id !== jobId) : [...prev, jobId]
        );
    };

    const renderJobItem = ({ item, index }) => {
        const theme = getThemeColor(index);
        const isSelected = selectedPdfs.includes(item.job_id);
        const isReady = item.status === 'completed';

        return (
            <TouchableOpacity
                style={[styles.jobCard, isSelected && styles.selectedCard]}
                onPress={() => isMultiSelectMode ? toggleSelect(item.job_id) : navigation.navigate('StudyDetail', { job: item })}
            >
                <View style={styles.cardRow}>
                    <View style={styles.iconContainer}>
                        <CircularProgress progress={isReady ? 100 : (item.progress || 10)} color={theme.start} size={50} />
                        <MaterialCommunityIcons name="file-pdf-box" size={24} color={theme.start} />
                    </View>
                    <View style={{ flex: 1, marginLeft: 15 }}>
                        <Text style={styles.fileName} numberOfLines={1}>{item.file_name}</Text>
                        <Text style={[styles.statusText, { color: theme.start }]}>
                            {isReady ? "Ready to Study" : `Analyzing... ${item.progress || 0}%`}
                        </Text>
                    </View>
                    {isMultiSelectMode && isReady && (
                        <MaterialCommunityIcons
                            name={isSelected ? "checkbox-marked-circle" : "checkbox-blank-circle-outline"}
                            size={24}
                            color={isSelected ? theme.start : "#444"}
                        />
                    )}
                </View>
            </TouchableOpacity>
        );
    };

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" />
            <SafeAreaView style={styles.header}>
                <View style={styles.navRow}>
                    <Text style={styles.title}>{pathStack[pathStack.length - 1].name}</Text>
                    <TouchableOpacity onPress={handleUpload} disabled={uploading}>
                        {uploading ? <ActivityIndicator color="#fff" /> : <MaterialCommunityIcons name="plus-box" size={30} color="#fff" />}
                    </TouchableOpacity>
                </View>
            </SafeAreaView>

            <FlatList
                data={jobs}
                renderItem={renderJobItem}
                keyExtractor={item => item.job_id.toString()}
                contentContainerStyle={{ padding: 15 }}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadData(true)} />}
                ListHeaderComponent={() => (
                    <TouchableOpacity
                        style={styles.multiBtn}
                        onPress={() => setIsMultiSelectMode(!isMultiSelectMode)}
                    >
                        <LinearGradient colors={['#6366f1', '#a855f7']} style={styles.gradBtn}>
                            <Text style={{ color: '#fff', fontWeight: 'bold' }}>
                                {isMultiSelectMode ? "Cancel Selection" : "Bulk Generate Worksheet"}
                            </Text>
                        </LinearGradient>
                    </TouchableOpacity>
                )}
            />

            {isMultiSelectMode && selectedPdfs.length > 0 && (
                <TouchableOpacity style={styles.fab} onPress={() => navigation.navigate('AIPdfWorksheet', { selectedPdfs })}>
                    <Text style={styles.fabText}>Generate ({selectedPdfs.length})</Text>
                </TouchableOpacity>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#0b0e14' },
    header: { backgroundColor: '#161b22', paddingBottom: 10 },
    navRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20 },
    title: { fontSize: 20, fontWeight: 'bold', color: '#fff' },
    jobCard: { backgroundColor: '#1c2128', padding: 15, borderRadius: 12, marginBottom: 10, borderWidth: 1, borderColor: '#30363d' },
    selectedCard: { borderColor: '#6366f1', backgroundColor: '#1e1e2e' },
    cardRow: { flexDirection: 'row', alignItems: 'center' },
    iconContainer: { width: 50, height: 50, justifyContent: 'center', alignItems: 'center' },
    fileName: { color: '#fff', fontSize: 16, fontWeight: '600' },
    statusText: { fontSize: 12, marginTop: 4 },
    multiBtn: { marginBottom: 15, borderRadius: 10, overflow: 'hidden' },
    gradBtn: { padding: 15, alignItems: 'center' },
    fab: { position: 'absolute', bottom: 30, right: 20, left: 20, backgroundColor: '#10b981', padding: 18, borderRadius: 30, alignItems: 'center', elevation: 5 },
    fabText: { color: '#fff', fontWeight: 'bold', fontSize: 16 }
});

export default PDFToExamScreen;