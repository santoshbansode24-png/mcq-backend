import React, { useState, useEffect } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, FlatList,
    ActivityIndicator, Alert, StatusBar, Dimensions,
    ScrollView, Platform, RefreshControl, Modal, TextInput, BackHandler
} from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as DocumentPicker from 'expo-document-picker';
import axios from 'axios';
import * as FileSystem from 'expo-file-system/legacy';
import { API_URL, WORKER_SECRET } from '../api/config';

const { width } = Dimensions.get('window');

const PDFToExamScreen = ({ user, navigation }) => {
    const isFocused = useIsFocused();

    // State
    const [folders, setFolders] = useState([]);
    const [pathStack, setPathStack] = useState([{ id: 'root', name: 'Knowledge Vault' }]);
    const currentFolderId = pathStack[pathStack.length - 1].id;
    const [jobs, setJobs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

    // Modal State
    const [renameModalVisible, setRenameModalVisible] = useState(false);
    const [selectedJob, setSelectedJob] = useState(null);
    const [newFileName, setNewFileName] = useState('');
    const [createFolderModalVisible, setCreateFolderModalVisible] = useState(false);
    const [newFolderName, setNewFolderName] = useState('');
    
    const [uploadModalVisible, setUploadModalVisible] = useState(false);
    const [pendingUploadFile, setPendingUploadFile] = useState(null);
    const [uploadFileName, setUploadFileName] = useState('');
    
    const [renameFolderModalVisible, setRenameFolderModalVisible] = useState(false);
    const [selectedFolder, setSelectedFolder] = useState(null);
    const [editFolderName, setEditFolderName] = useState('');

    useEffect(() => {
        if (isFocused) {
            loadData();
        }
    }, [isFocused, pathStack]);

    useEffect(() => {
        const backAction = () => {
            if (pathStack.length > 1) {
                setPathStack(prev => prev.slice(0, -1));
                return true;
            }
            return false;
        };
        const backHandler = BackHandler.addEventListener("hardwareBackPress", backAction);
        return () => backHandler.remove();
    }, [pathStack]);

    // Polling for progress
    useEffect(() => {
        let interval;
        const activeJobs = jobs.filter(j => j.status === 'processing' || j.status === 'pending');
        
        if (activeJobs.length > 0) {
            // Only nudge the worker if there is a 'pending' job (not already processing).
            const needsNudge = activeJobs.some(j => j.status === 'pending');
            if (needsNudge) {
                // Fire and forget nudge. The PHP script uses ignore_user_abort(true)
                triggerWorker();
            }
            interval = setInterval(() => loadData(true), 5000);
        }
        return () => clearInterval(interval);
    }, [jobs]);

    const triggerWorker = async (forceId = null) => {
        try {
            const url = forceId 
                ? `${API_URL}/pdf_worker_ai.php?key=${WORKER_SECRET}&force_job_id=${forceId}`
                : `${API_URL}/pdf_worker_ai.php?key=${WORKER_SECRET}`;
            // Use 2-second timeout to prevent locking up all background Axios connections.
            await axios.get(url, { timeout: 2000 });
        } catch (e) {
            console.log("Worker Ping Background:", e.message);
        }
    };

    const loadData = async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            const folderUrl = `${API_URL}/get_pdf_folders.php?user_id=${user?.user_id || 0}&parent_id=${currentFolderId}`;
            const jobFilter = currentFolderId === 'root' ? 'root' : currentFolderId;
            const jobUrl = `${API_URL}/get_pdf_study_status.php?user_id=${user?.user_id || 0}&folder_id=${jobFilter}`;

            const [fRes, jRes] = await Promise.all([
                axios.get(folderUrl),
                axios.get(jobUrl)
            ]);

            if (fRes.data.status === 'success') setFolders(fRes.data.data);
            if (jRes.data.status === 'success') {
                // Ensure we don't accidentally remove an 'uploading' optimistic job if loadData is called concurrently
                setJobs(prevJobs => {
                    const uploadingJobs = prevJobs.filter(j => j.status === 'uploading');
                    const serverJobs = jRes.data.data;
                    
                    // Filter out any uploading jobs that have now appeared in the server response
                    const activeUploads = uploadingJobs.filter(uj => 
                        !serverJobs.some(sj => sj.file_name === uj.file_name && sj.status !== 'uploading')
                    );
                    
                    return [...activeUploads, ...serverJobs];
                });
            }
        } catch (e) {
            console.log("Fetch Error (Polling):", e.message);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const handleUpload = async () => {
        try {
            const doc = await DocumentPicker.getDocumentAsync({ type: 'application/pdf', copyToCacheDirectory: false });
            if (doc.canceled) return;

            const file = doc.assets[0];

            // Enforce max 20 MB limit
            const MAX_SIZE = 20 * 1024 * 1024;
            if (file.size && file.size > MAX_SIZE) {
                Alert.alert("File Too Large", "Please select a PDF document smaller than 20 MB.");
                return;
            }

            // Instead of uploading blindly, ask the user to confirm/fix the name
            let defaultName = file.name || 'document.pdf';
            // Strip .pdf for editing convenience
            if (defaultName.toLowerCase().endsWith('.pdf')) {
                defaultName = defaultName.substring(0, defaultName.length - 4);
            }
            
            setPendingUploadFile(file);
            setUploadFileName(defaultName);
            setUploadModalVisible(true);
        } catch (e) {
            console.log("Picker Error:", e);
        }
    };

    const executeUpload = async () => {
        if (!pendingUploadFile || !uploadFileName.trim()) return;
        setUploadModalVisible(false);

        const file = pendingUploadFile;
        // Ensure it ends with .pdf
        let finalName = uploadFileName.trim();
        if (!finalName.toLowerCase().endsWith('.pdf')) finalName += '.pdf';

        const optimisticJob = {
            job_id: 'upload_' + Date.now(),
            file_name: finalName,
            status: 'uploading',
            progress: 0
        };
        setJobs(prev => [optimisticJob, ...prev]);
        
        setUploading(true);

        let result;
        try {
            
            const formData = new FormData();
            formData.append('pdf_file', {
                uri: file.uri,
                name: finalName,
                type: 'application/pdf',
            });
            formData.append('user_id', user?.user_id?.toString());
            formData.append('custom_file_name', finalName);
            if (currentFolderId !== 'root') formData.append('folder_id', currentFolderId.toString());
            
            const response = await fetch(`${API_URL}/upload_pdf_study.php`, {
                method: 'POST',
                body: formData,
                headers: { 
                    'Accept': 'application/json',
                    'X-Custom-File-Name': encodeURIComponent(finalName)
                },
            });

            const text = await response.text(); // Read as text first to avoid JSON parse crash
            try {
                result = JSON.parse(text);
            } catch (parseErr) {
                Alert.alert("Server Error", "Server returned an unexpected response:\n" + text.substring(0, 200));
                setJobs(prev => prev.filter(j => j.job_id !== optimisticJob.job_id));
                setUploading(false);
                return;
            }

            if (result && result.status === 'success') {
                // The polling mechanism will naturally replace the uploading job with the real pending/processing job
                setTimeout(() => loadData(true), 500); 
            } else {
                setJobs(prev => prev.filter(j => j.status !== 'uploading'));
                Alert.alert("Upload Failed", result?.message || "Unknown server error.");
            }
        } catch (e) {
            setJobs(prev => prev.filter(j => j.status !== 'uploading'));
            if (e.message && e.message.toLowerCase().includes('network')) {
                Alert.alert("Connection Error", `Cannot reach server at:\n${API_URL}\n\nMake sure your phone is on WiFi (same as PC) and XAMPP Apache is running.`);
            } else {
                Alert.alert("Upload Error", e.message || "Unknown error occurred.");
            }
            console.log('Upload error:', e);
        } finally {
            setUploading(false);
            setPendingUploadFile(null);
        }
    };

    // --- Actions: Rename & Delete ---

    const handleJobOptions = (job) => {
        Alert.alert(
            "Document Options",
            job.file_name,
            [
                { text: "Rename", onPress: () => {
                    setSelectedJob(job);
                    setNewFileName(job.file_name);
                    setRenameModalVisible(true);
                }},
                { text: "Delete", onPress: () => confirmDelete(job), style: "destructive" },
                { text: "Cancel", style: "cancel" }
            ]
        );
    };

    const confirmDelete = (job) => {
        Alert.alert(
            "Delete Document",
            `Are you sure you want to delete "${job.file_name}"? This action cannot be undone.`,
            [
                { text: "Cancel", style: "cancel" },
                { text: "Delete", style: "destructive", onPress: () => executeDelete(job) }
            ]
        );
    };

    const executeDelete = async (job) => {
        try {
            const formData = new FormData();
            formData.append('user_id', user?.user_id?.toString() || '0');
            formData.append('job_id', job.job_id.toString());
            
            const response = await fetch(`${API_URL}/delete_pdf_job.php`, { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.status === 'success') {
                loadData(true);
            } else {
                Alert.alert("Error", result.message || "Failed to delete");
            }
        } catch (e) {
            console.error(e);
            Alert.alert("Error", "Check your connection");
        }
    };

    const executeRename = async () => {
        if (!newFileName.trim()) return;
        try {
            setRenameModalVisible(false);
            const formData = new FormData();
            formData.append('user_id', user?.user_id?.toString() || '0');
            formData.append('type', 'file');
            formData.append('id', selectedJob.job_id.toString());
            
            let finalName = newFileName.trim();
            if (!finalName.toLowerCase().endsWith('.pdf')) finalName += '.pdf';
            formData.append('new_name', finalName);

            const response = await fetch(`${API_URL}/rename_pdf_item.php`, { method: 'POST', body: formData });
            const result = await response.json();

            if (result.status === 'success') {
                loadData(true);
            } else {
                Alert.alert("Error", result.message || "Failed to rename");
            }
        } catch (e) {
            console.error(e);
            Alert.alert("Error", "Check your connection");
        }
    };

    const handleRetry = (job) => {
        Alert.alert(
            "Retry Analysis",
            "Do you want to retry AI analysis for this document?",
            [
                { text: "Cancel", style: "cancel" },
                { text: "Retry Now", onPress: async () => {
                    await triggerWorker(job.job_id);
                    loadData(true);
                }}
            ]
        );
    };

    const executeCreateFolder = async () => {
        if (!newFolderName.trim()) return;
        try {
            setCreateFolderModalVisible(false);
            const formData = new FormData();
            formData.append('user_id', user?.user_id?.toString() || '0');
            formData.append('name', newFolderName.trim());
            const pId = currentFolderId === 'root' ? '0' : currentFolderId;
            formData.append('parent_id', pId);

            const response = await fetch(`${API_URL}/create_pdf_folder.php`, { method: 'POST', body: formData });
            const result = await response.json();

            if (result.status === 'success') {
                setNewFolderName('');
                loadData(true);
            } else {
                Alert.alert("Error", result.message || "Failed to create folder");
            }
        } catch (e) {
            console.error(e);
            Alert.alert("Error", "Check your connection");
        }
    };

    const handleFolderOptions = (folder) => {
        Alert.alert(
            "Folder Options",
            folder.name,
            [
                { text: "Rename", onPress: () => {
                    setSelectedFolder(folder);
                    setEditFolderName(folder.name);
                    setRenameFolderModalVisible(true);
                }},
                { text: "Delete", onPress: () => confirmFolderDelete(folder), style: "destructive" },
                { text: "Cancel", style: "cancel" }
            ]
        );
    };

    const confirmFolderDelete = (folder) => {
        Alert.alert(
            "Delete Folder",
            `Are you sure you want to delete "${folder.name}"? Documents inside will be safely moved back to the Main Vault.`,
            [
                { text: "Cancel", style: "cancel" },
                { text: "Delete", style: "destructive", onPress: () => executeDeleteFolder(folder) }
            ]
        );
    };

    const executeDeleteFolder = async (folder) => {
        try {
            const formData = new FormData();
            formData.append('user_id', user?.user_id?.toString() || '0');
            formData.append('folder_id', folder.folder_id.toString());
            
            const response = await fetch(`${API_URL}/delete_pdf_folder.php`, { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.status === 'success') {
                loadData(true);
            } else {
                Alert.alert("Error", result.message || "Failed to delete folder");
            }
        } catch (e) {
            console.error(e);
            Alert.alert("Error", "Check your connection");
        }
    };

    const executeRenameFolder = async () => {
        if (!editFolderName.trim()) return;
        try {
            setRenameFolderModalVisible(false);
            const formData = new FormData();
            formData.append('user_id', user?.user_id?.toString() || '0');
            formData.append('folder_id', selectedFolder.folder_id.toString());
            formData.append('name', editFolderName.trim());

            const response = await fetch(`${API_URL}/rename_pdf_folder.php`, { method: 'POST', body: formData });
            const result = await response.json();

            if (result.status === 'success') {
                loadData(true);
            } else {
                Alert.alert("Error", result.message || "Failed to rename folder");
            }
        } catch (e) {
            console.error(e);
            Alert.alert("Error", "Check your connection");
        }
    };

    // --- Rendering ---

    const renderJobItem = React.useCallback(({ item }) => {
        const isReady = item.status === 'completed';
        const isFailed = item.status === 'failed';
        const isUploading = item.status === 'uploading';
        
        let statusColor = '#3b82f6'; // Processing
        let sText = 'Analyzing... ' + (item.progress || '0') + '%';
        let iconName = 'file-document';

        if (isUploading) {
            statusColor = '#8b5cf6'; // Purple Uploading
            sText = 'Uploading to Secure Vault...';
            iconName = 'cloud-upload';
        } else if (isReady) {
            statusColor = '#10b981'; // Green Ready
            sText = 'Ready to Study';
        } else if (isFailed) {
            statusColor = '#f43f5e'; // Red Failed
            sText = item.error_message || 'Analysis Failed';
            iconName = 'file-document-remove';
        }

        return (
            <TouchableOpacity 
                style={[styles.jobCard, isReady && { borderLeftColor: statusColor, borderLeftWidth: 3 }, isUploading && { opacity: 0.8 }]} 
                onPress={() => isReady ? navigation.navigate('StudyDetail', { job: item }) : (isFailed ? handleRetry(item) : null)}
                activeOpacity={isReady || isFailed ? 0.7 : 1}
                onLongPress={() => !isUploading && handleJobOptions(item)}
            >
                <View style={[styles.cardIconBox, { backgroundColor: statusColor + '15' }]}>
                     {isUploading ? (
                         <ActivityIndicator size="small" color={statusColor} />
                     ) : (
                         <MaterialCommunityIcons name={iconName} size={26} color={statusColor} />
                     )}
                </View>
                <View style={styles.cardDetails}>
                    <Text style={styles.fileName} numberOfLines={1}>{item.file_name}</Text>
                    <View style={[styles.statusPill, { backgroundColor: statusColor + '15' }]}>
                        {!isUploading && <View style={[styles.statusDot, { backgroundColor: statusColor }]} />}
                        <Text style={[styles.statusText, { color: statusColor }]}>{sText}</Text>
                    </View>
                    {isFailed && (
                        <Text style={styles.retryHint}>Tap to retry analysis</Text>
                    )}
                </View>
                {/* 3 Dots Options Button - Hide while uploading */}
                {!isUploading && (
                    <TouchableOpacity style={styles.optionsBtn} onPress={() => handleJobOptions(item)}>
                        <MaterialCommunityIcons name="dots-vertical" size={24} color="#64748b" />
                    </TouchableOpacity>
                )}
            </TouchableOpacity>
        );
    }, [navigation]);

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="#0b0e14" />
            <SafeAreaView style={styles.safeArea}>
                <FlatList
                    data={jobs}
                    keyExtractor={(item) => item.job_id.toString()}
                    renderItem={renderJobItem}
                    contentContainerStyle={styles.scrollContent}
                    initialNumToRender={8}
                    maxToRenderPerBatch={5}
                    windowSize={8}
                    removeClippedSubviews={Platform.OS === 'android'}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadData(true)} tintColor="#fff" />}
                    ListHeaderComponent={
                        <>
                            {/* Header Section */}
                            <View style={styles.header}>
                                <View>
                                    <Text style={styles.headerTitle}>{pathStack[pathStack.length - 1].name}</Text>
                                    <Text style={styles.headerSubtitle}>My Documents</Text>
                                </View>
                                <View style={styles.headerIcons}>
                                    <TouchableOpacity style={styles.iconBtn}>
                                        <MaterialCommunityIcons name="account-circle-outline" size={26} color="#e2e8f0" />
                                    </TouchableOpacity>
                                    <TouchableOpacity style={styles.iconBtn}>
                                        <MaterialCommunityIcons name="bell-outline" size={24} color="#e2e8f0" />
                                    </TouchableOpacity>
                                </View>
                            </View>

                            {/* Main Upload Banner */}
                            <TouchableOpacity activeOpacity={0.8} onPress={handleUpload} disabled={uploading}>
                                <LinearGradient 
                                    colors={['#fe357e', '#8831fd']} 
                                    style={styles.bannerContainer}
                                    start={{ x: 0, y: 0 }} 
                                    end={{ x: 1, y: 1 }}
                                >
                                    {uploading ? (
                                        <ActivityIndicator color="#fff" size="large" />
                                    ) : (
                                        <>
                                            <View style={styles.bannerIconWrapper}>
                                                <MaterialCommunityIcons name="file-document-plus-outline" size={32} color="#fe357e" />
                                            </View>
                                            <Text style={styles.bannerTitle}>Turn any PDF into a Study Set</Text>
                                            <Text style={styles.bannerSubtitle}>Tap to select a document from your device</Text>
                                        </>
                                    )}
                                </LinearGradient>
                            </TouchableOpacity>

                            {/* Secondary Action Buttons */}
                            <View style={styles.actionsRow}>
                                <TouchableOpacity 
                                    style={styles.actionBlock} 
                                    activeOpacity={0.8} 
                                    onPress={() => navigation.navigate('AIPdfWorksheet')}
                                >
                                    <LinearGradient 
                                        colors={['#ff512f', '#dd2476']} 
                                        style={styles.actionGradient} 
                                        start={{ x: 0, y: 0 }} 
                                        end={{ x: 1, y: 1 }}
                                    >
                                        <MaterialCommunityIcons name="file-document-edit-outline" size={28} color="#fff" style={styles.actionIcon} />
                                        <Text style={styles.actionTitle}>Worksheet</Text>
                                        <Text style={styles.actionSubtitle}>Combine PDFs</Text>
                                    </LinearGradient>
                                </TouchableOpacity>

                                <TouchableOpacity 
                                    style={[styles.actionBlock, { marginRight: 0 }]} 
                                    activeOpacity={0.8}
                                    onPress={() => navigation.navigate('AIPdfExam')}
                                >
                                    <LinearGradient 
                                        colors={['#f6d365', '#fda085']} 
                                        style={styles.actionGradient} 
                                        start={{ x: 0, y: 0 }} 
                                        end={{ x: 1, y: 1 }}
                                    >
                                        <MaterialCommunityIcons name="file-check-outline" size={28} color="#fff" style={styles.actionIcon} />
                                        <Text style={styles.actionTitle}>Custom Exam</Text>
                                        <Text style={styles.actionSubtitle}>Combine PDFs</Text>
                                    </LinearGradient>
                                </TouchableOpacity>
                            </View>

                            {/* Study Folders Section */}
                            <View style={styles.sectionHeader}>
                                <Text style={styles.sectionTitle}>Study Folders</Text>
                                <TouchableOpacity onPress={() => { setNewFolderName(''); setCreateFolderModalVisible(true); }}>
                                    <MaterialCommunityIcons name="plus-circle-outline" size={24} color="#3b82f6" />
                                </TouchableOpacity>
                            </View>

                            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.foldersScroll}>
                                {folders.map((f, index) => {
                                    const gradientColors = index % 2 === 0 ? ['#10b981', '#059669'] : ['#3b82f6', '#2563eb'];
                                    return (
                                        <TouchableOpacity 
                                            key={f.folder_id ? f.folder_id.toString() : index.toString()} 
                                            activeOpacity={0.8} 
                                            style={styles.folderCard}
                                            onPress={() => setPathStack(prev => [...prev, { id: f.folder_id, name: f.name }])}
                                            onLongPress={() => handleFolderOptions(f)}
                                        >
                                            <LinearGradient colors={gradientColors} style={styles.folderGradient} start={{x:0, y:0}} end={{x:1, y:1}}>
                                                <MaterialCommunityIcons name="folder-text-outline" size={32} color="#fff" style={styles.folderIcon} />
                                                <Text style={styles.folderText}>{f.name}</Text>
                                            </LinearGradient>
                                        </TouchableOpacity>
                                    );
                                })}
                            </ScrollView>

                            {/* My Study Materials Section */}
                            <Text style={[styles.sectionTitle, { marginTop: 5, marginBottom: 15 }]}>My Study Materials</Text>
                        </>
                    }
                    ListEmptyComponent={
                        !loading && <View style={styles.emptyContainer}>
                            <MaterialCommunityIcons name="text-box-search-outline" size={48} color="#334155" />
                            <Text style={styles.emptyText}>No study materials yet.</Text>
                            <Text style={styles.emptySubtext}>Upload a PDF to get started!</Text>
                        </View>
                    }
                />
            </SafeAreaView>

            {/* Create Folder Modal */}
            <Modal visible={createFolderModalVisible} transparent animationType="fade">
                <View style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <Text style={styles.modalTitle}>New Folder</Text>
                        <TextInput
                            style={styles.modalInput}
                            value={newFolderName}
                            onChangeText={setNewFolderName}
                            placeholder="Enter folder name"
                            placeholderTextColor="#64748b"
                            autoFocus
                        />
                        <View style={styles.modalActions}>
                            <TouchableOpacity style={styles.modalBtn} onPress={() => setCreateFolderModalVisible(false)}>
                                <Text style={styles.modalBtnText}>Cancel</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={[styles.modalBtn, styles.modalBtnPrimary]} onPress={executeCreateFolder}>
                                <Text style={styles.modalBtnTextPrimary}>Create</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>

            {/* Confirm Upload Name Modal */}
            <Modal visible={uploadModalVisible} transparent animationType="fade">
                <View style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <Text style={styles.modalTitle}>Name this Study Set</Text>
                        <TextInput
                            style={styles.modalInput}
                            value={uploadFileName}
                            onChangeText={setUploadFileName}
                            placeholder="e.g. Biology Unit 1"
                            placeholderTextColor="#64748b"
                            autoFocus
                        />
                        <View style={styles.modalActions}>
                            <TouchableOpacity style={styles.modalBtn} onPress={() => { setUploadModalVisible(false); setPendingUploadFile(null); }}>
                                <Text style={styles.modalBtnText}>Cancel</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={[styles.modalBtn, { backgroundColor: '#fe357e' }]} onPress={executeUpload}>
                                <Text style={styles.modalBtnTextPrimary}>Upload</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>

            {/* Rename Modal */}
            <Modal visible={renameModalVisible} transparent animationType="fade">
                <View style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <Text style={styles.modalTitle}>Rename Document</Text>
                        <TextInput
                            style={styles.modalInput}
                            value={newFileName}
                            onChangeText={setNewFileName}
                            placeholder="Enter new name"
                            placeholderTextColor="#64748b"
                            autoFocus
                        />
                        <View style={styles.modalActions}>
                            <TouchableOpacity style={styles.modalBtn} onPress={() => setRenameModalVisible(false)}>
                                <Text style={styles.modalBtnText}>Cancel</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={[styles.modalBtn, styles.modalBtnPrimary]} onPress={executeRename}>
                                <Text style={styles.modalBtnTextPrimary}>Rename</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>

            {/* Rename Folder Modal */}
            <Modal visible={renameFolderModalVisible} transparent animationType="fade">
                <View style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <Text style={styles.modalTitle}>Rename Folder</Text>
                        <TextInput
                            style={styles.modalInput}
                            value={editFolderName}
                            onChangeText={setEditFolderName}
                            placeholder="Enter new folder name"
                            placeholderTextColor="#64748b"
                            autoFocus
                        />
                        <View style={styles.modalActions}>
                            <TouchableOpacity style={styles.modalBtn} onPress={() => setRenameFolderModalVisible(false)}>
                                <Text style={styles.modalBtnText}>Cancel</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={[styles.modalBtn, styles.modalBtnPrimary]} onPress={executeRenameFolder}>
                                <Text style={styles.modalBtnTextPrimary}>Rename</Text>
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
    safeArea: { flex: 1 },
    scrollContent: { padding: 20, paddingBottom: 100 },
    
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 25 },
    headerTitle: { fontSize: 24, fontWeight: 'bold', color: '#fff' },
    headerSubtitle: { fontSize: 13, color: '#94a3b8', marginTop: 2 },
    headerIcons: { flexDirection: 'row', alignItems: 'center' },
    iconBtn: { marginLeft: 15 },

    bannerContainer: { padding: 25, borderRadius: 20, alignItems: 'center', marginBottom: 20 },
    bannerIconWrapper: { backgroundColor: '#ffffff20', width: 50, height: 50, borderRadius: 25, justifyContent: 'center', alignItems: 'center', marginBottom: 12 },
    bannerTitle: { fontSize: 18, fontWeight: 'bold', color: '#fff', marginBottom: 5 },
    bannerSubtitle: { fontSize: 12, color: '#f8fafc', opacity: 0.9 },

    actionsRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 30 },
    actionBlock: { flex: 1, height: 120, marginRight: 15, borderRadius: 16, overflow: 'hidden' },
    actionGradient: { flex: 1, padding: 18, justifyContent: 'center', alignItems: 'flex-start' },
    actionIcon: { marginBottom: 10 },
    actionTitle: { fontSize: 16, fontWeight: 'bold', color: '#fff' },
    actionSubtitle: { fontSize: 12, color: '#fff', opacity: 0.8, marginTop: 4 },

    sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 15 },
    sectionTitle: { fontSize: 18, fontWeight: 'bold', color: '#fff' },
    
    foldersScroll: { flexDirection: 'row', marginBottom: 15 },
    folderCard: { width: 110, height: 110, marginRight: 15, borderRadius: 18, overflow: 'hidden' },
    folderGradient: { flex: 1, padding: 15, justifyContent: 'center', alignItems: 'center' },
    folderIcon: { marginBottom: 10 },
    folderText: { fontSize: 15, fontWeight: '600', color: '#fff' },

    jobCard: { backgroundColor: '#161b22', borderRadius: 16, padding: 15, flexDirection: 'row', alignItems: 'center', marginBottom: 12, borderWidth: 1, borderColor: '#1f2937' },
    cardIconBox: { width: 50, height: 50, borderRadius: 12, justifyContent: 'center', alignItems: 'center', marginRight: 15 },
    cardDetails: { flex: 1 },
    fileName: { fontSize: 15, fontWeight: 'bold', color: '#fff', marginBottom: 8 },
    optionsBtn: { padding: 8, marginLeft: 10 },
    
    statusPill: { flexDirection: 'row', alignItems: 'center', alignSelf: 'flex-start', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 20 },
    statusDot: { width: 6, height: 6, borderRadius: 3, marginRight: 6 },
    statusText: { fontSize: 12, fontWeight: '600' },
    retryHint: { fontSize: 11, color: '#f43f5e', marginTop: 4, fontWeight: 'bold', fontStyle: 'italic' },

    emptyContainer: { alignItems: 'center', justifyContent: 'center', paddingVertical: 40 },
    emptyText: { color: '#94a3b8', fontSize: 16, fontWeight: '600', marginTop: 15 },
    emptySubtext: { color: '#64748b', fontSize: 13, marginTop: 5 },

    // Modal Styles
    modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'center', alignItems: 'center' },
    modalContent: { width: '85%', backgroundColor: '#1e293b', borderRadius: 16, padding: 20 },
    modalTitle: { fontSize: 18, fontWeight: 'bold', color: '#fff', marginBottom: 15 },
    modalInput: { backgroundColor: '#0f172a', color: '#fff', padding: 15, borderRadius: 10, fontSize: 16, marginBottom: 20, borderWidth: 1, borderColor: '#334155' },
    modalActions: { flexDirection: 'row', justifyContent: 'flex-end' },
    modalBtn: { paddingVertical: 10, paddingHorizontal: 15, marginLeft: 10, borderRadius: 8 },
    modalBtnPrimary: { backgroundColor: '#3b82f6' },
    modalBtnText: { color: '#94a3b8', fontSize: 16, fontWeight: '600' },
    modalBtnTextPrimary: { color: '#fff', fontSize: 16, fontWeight: 'bold' }
});

export default PDFToExamScreen;