import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, FlatList,
    ActivityIndicator, Alert, Animated, Platform, StatusBar
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as DocumentPicker from 'expo-document-picker';
import * as FileSystem from 'expo-file-system';
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useTheme } from '../context/ThemeContext';
import { API_URL } from '../api/config';

const PDFToExamScreen = ({ user, navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const [jobs, setJobs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const pulseAnim = useRef(new Animated.Value(0.6)).current;

    // Pulse animation for "Processing" cards
    useEffect(() => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(pulseAnim, { toValue: 1, duration: 1500, useNativeDriver: true }),
                Animated.timing(pulseAnim, { toValue: 0.6, duration: 1500, useNativeDriver: true })
            ])
        ).start();
    }, []);

    // Load jobs on mount
    useEffect(() => {
        loadJobs();
    }, []);

    // Polling logic: Check status every 10s if there are active jobs
    useEffect(() => {
        const hasActiveJobs = jobs.some(j => j.status === 'pending' || j.status === 'processing');
        let interval;

        if (hasActiveJobs) {
            interval = setInterval(() => {
                refreshStatuses();
            }, 10000);
        }

        return () => clearInterval(interval);
    }, [jobs]);

    const loadJobs = async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            const response = await axios.get(`${API_URL}/get_pdf_study_status.php?user_id=${user.user_id}`);
            if (response.data.status === 'success') {
                setJobs(response.data.data);
                // Check if any just finished and need syncing
                checkAndSyncPacks(response.data.data);
            }
        } catch (error) {
            console.error("Load Jobs Failed:", error);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const refreshStatuses = async () => {
        try {
            const response = await axios.get(`${API_URL}/get_pdf_study_status.php?user_id=${user.user_id}`);
            if (response.data.status === 'success') {
                setJobs(response.data.data);
                checkAndSyncPacks(response.data.data);
            }
        } catch (error) {
            console.error("Refresh Statuses Failed:", error);
        }
    };

    const checkAndSyncPacks = async (currentJobs) => {
        for (const job of currentJobs) {
            if (job.status === 'completed') {
                const localKey = `@study_pack_${job.job_id}`;
                const exists = await AsyncStorage.getItem(localKey);
                if (!exists) {
                    await fetchAndSavePack(job.job_id);
                }
            }
        }
    };

    const fetchAndSavePack = async (jobId) => {
        try {
            // 1. Fetch JSON from server
            const res = await axios.post(`${API_URL}/sync_pdf_study_content.php`, {
                user_id: user.user_id,
                job_id: jobId,
                action: 'fetch'
            });

            if (res.data.status === 'success') {
                // 2. Save locally
                await AsyncStorage.setItem(`@study_pack_${jobId}`, JSON.stringify(res.data.study_pack));
                
                // 3. Acknowledge and trigger server wipe
                await axios.post(`${API_URL}/sync_pdf_study_content.php`, {
                    user_id: user.user_id,
                    job_id: jobId,
                    action: 'acknowledge'
                });
            }
        } catch (e) {
            console.error("Sync Pack Failed:", e);
        }
    };

    const handleUpload = async () => {
        try {
            const doc = await DocumentPicker.getDocumentAsync({
                type: 'application/pdf',
                copyToCacheDirectory: true
            });

            if (doc.canceled) return;

            const file = doc.assets[0];
            
            // Limit file size (e.g., 20MB)
            if (file.size > 20 * 1024 * 1024) {
                Alert.alert("File Too Large", "Please select a PDF smaller than 20MB.");
                return;
            }

            setUploading(true);

            // Use FormData for file upload
            const formData = new FormData();
            formData.append('user_id', user.user_id.toString());
            formData.append('pdf_file', {
                uri: Platform.OS === 'android' ? file.uri : file.uri.replace('file://', ''),
                type: 'application/pdf',
                name: file.name
            });

            const res = await axios.post(`${API_URL}/upload_pdf_study.php`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            if (res.data.status === 'success') {
                Alert.alert("Success! 🚀", "PDF received. Our AI is now building your custom exam in the background.");
                loadJobs(true);
            } else {
                Alert.alert("Upload Failed", res.data.message);
            }
        } catch (error) {
            Alert.alert("Error", "Something went wrong during upload.");
            console.log(error);
        } finally {
            setUploading(false);
        }
    };

    const startStudy = async (job) => {
        const localKey = `@study_pack_${job.job_id}`;
        const packJson = await AsyncStorage.getItem(localKey);
        
        if (!packJson) {
            Alert.alert("Syncing...", "We are downloading your study pack. Please wait a second.");
            await fetchAndSavePack(job.job_id);
            return;
        }

        const pack = JSON.parse(packJson);
        navigation.navigate('MyExamTest', { 
            questions: pack.mcqs,
            title: pack.file_name.replace('.pdf', ''),
            isAI: true
        });
    };

    const renderJobItem = ({ item }) => {
        const isReady = item.status === 'completed';
        const isProcessing = item.status === 'processing' || item.status === 'pending';
        const isFailed = item.status === 'failed';

        return (
            <TouchableOpacity 
                style={[styles.jobCard, { backgroundColor: isDarkMode ? '#1e293b' : '#fff' }]}
                disabled={!isReady}
                onPress={() => startStudy(item)}
            >
                <View style={styles.cardMain}>
                    <View style={[styles.iconBox, { backgroundColor: isReady ? '#dcfce7' : '#fef9c3' }]}>
                        <MaterialCommunityIcons 
                            name={isReady ? "check-decagram" : "file-pdf-box"} 
                            size={32} 
                            color={isReady ? "#16a34a" : "#ca8a04"} 
                        />
                    </View>
                    
                    <View style={styles.infoBox}>
                        <Text style={[styles.fileName, { color: theme.text }]} numberOfLines={1}>{item.file_name}</Text>
                        <Text style={[styles.metaText, { color: theme.textSecondary }]}>
                            {item.total_pages > 0 ? `${item.total_pages} Pages` : 'Calculating...'} • {new Date(item.created_at).toLocaleDateString()}
                        </Text>
                    </View>

                    {isProcessing && (
                        <Animated.View style={[styles.statusBadge, { opacity: pulseAnim, backgroundColor: '#fef9c3' }]}>
                            <Text style={styles.statusText}>AI analyzing...</Text>
                        </Animated.View>
                    )}

                    {isReady && (
                        <View style={[styles.statusBadge, { backgroundColor: '#dcfce7' }]}>
                            <Text style={[styles.statusText, { color: '#16a34a' }]}>Study Ready ✨</Text>
                        </View>
                    )}

                    {isFailed && (
                        <View style={[styles.statusBadge, { backgroundColor: '#fee2e2' }]}>
                            <Text style={[styles.statusText, { color: '#ef4444' }]}>Retry needed</Text>
                        </View>
                    )}
                </View>

                {isProcessing && (
                    <View style={styles.progressContainer}>
                        <View style={styles.progressBarBg}>
                            <View style={[styles.progressBarFill, { width: `${item.progress}%`, backgroundColor: theme.primary }]} />
                        </View>
                        <Text style={styles.progressLabel}>{item.progress}%</Text>
                    </View>
                )}

                {isReady && (
                    <View style={styles.actionRow}>
                        <Text style={[styles.readyMessage, { color: theme.primary }]}>Tap to start Exam & Flashcards</Text>
                        <MaterialCommunityIcons name="chevron-right" size={20} color={theme.primary} />
                    </View>
                )}
            </TouchableOpacity>
        );
    };

    return (
        <View style={[styles.container, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]}>
            <StatusBar barStyle="light-content" transparent translucent />
            
            <LinearGradient colors={['#4f46e5', '#3730a3']} style={styles.header}>
                <SafeAreaView edges={['top']}>
                    <View style={styles.headerContent}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <MaterialCommunityIcons name="arrow-left" size={28} color="white" />
                        </TouchableOpacity>
                        <View>
                            <Text style={styles.headerTitle}>PDF-to-Exam</Text>
                            <Text style={styles.headerSubtitle}>Your AI Knowledge Vault</Text>
                        </View>
                        <View style={{ width: 28 }} />
                    </View>
                </SafeAreaView>
            </LinearGradient>

            <View style={styles.content}>
                <TouchableOpacity style={styles.uploadMainCard} onPress={handleUpload} disabled={uploading}>
                    <LinearGradient 
                        colors={['#6366f1', '#a855f7']} 
                        start={{ x: 0, y: 0 }} 
                        end={{ x: 1, y: 1 }} 
                        style={styles.uploadGradient}
                    >
                        {uploading ? (
                            <ActivityIndicator color="white" size="large" />
                        ) : (
                            <>
                                <View style={styles.uploadIconCircle}>
                                    <MaterialCommunityIcons name="cloud-upload" size={32} color="#6366f1" />
                                </View>
                                <Text style={styles.uploadTitle}>Upload New PDF</Text>
                                <Text style={styles.uploadSubtitle}>Turn notes into exams instantly.</Text>
                            </>
                        )}
                    </LinearGradient>
                </TouchableOpacity>

                <View style={styles.listHeader}>
                    <Text style={[styles.listTitle, { color: theme.text }]}>Personal Study Library</Text>
                    <TouchableOpacity onPress={() => loadJobs(true)}>
                        <MaterialCommunityIcons name="refresh" size={20} color={theme.primary} />
                    </TouchableOpacity>
                </View>

                {loading ? (
                    <View style={styles.centerBox}><ActivityIndicator size="large" color={theme.primary} /></View>
                ) : (
                    <FlatList
                        data={jobs}
                        keyExtractor={(item) => item.job_id.toString()}
                        renderItem={renderJobItem}
                        contentContainerStyle={styles.listContainer}
                        refreshing={refreshing}
                        onRefresh={() => loadJobs(true)}
                        ListEmptyComponent={
                            <View style={styles.emptyBox}>
                                <MaterialCommunityIcons name="file-document-outline" size={64} color="#cbd5e1" />
                                <Text style={[styles.emptyTitle, { color: theme.textSecondary }]}>Your vault is empty</Text>
                                <Text style={[styles.emptySubtitle, { color: theme.textSecondary }]}>Upload a PDF to see the AI magic!</Text>
                            </View>
                        }
                    />
                )}
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    header: { paddingBottom: 25, borderBottomLeftRadius: 32, borderBottomRightRadius: 32 },
    headerContent: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingTop: 10 },
    backButton: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(255,255,255,0.2)', justifyContent: 'center', alignItems: 'center' },
    headerTitle: { fontSize: 24, fontWeight: '800', color: 'white', fontFamily: 'NotoSans-Bold' },
    headerSubtitle: { fontSize: 14, color: 'rgba(255,255,255,0.8)', fontFamily: 'NotoSans-Regular' },
    content: { flex: 1, paddingHorizontal: 20, marginTop: -30 },
    uploadMainCard: { borderRadius: 24, overflow: 'hidden', elevation: 8, shadowOpacity: 0.3, shadowRadius: 10, shadowColor: '#6366f1', marginBottom: 25 },
    uploadGradient: { padding: 25, alignItems: 'center', justifyContent: 'center' },
    uploadIconCircle: { width: 60, height: 60, borderRadius: 30, backgroundColor: 'white', justifyContent: 'center', alignItems: 'center', marginBottom: 12 },
    uploadTitle: { fontSize: 20, fontWeight: 'bold', color: 'white', fontFamily: 'NotoSans-Bold' },
    uploadSubtitle: { fontSize: 13, color: 'rgba(255,255,255,0.9)', fontFamily: 'NotoSans-Regular' },
    listHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 15 },
    listTitle: { fontSize: 18, fontWeight: '800', fontFamily: 'NotoSans-Bold', textTransform: 'uppercase', letterSpacing: 1 },
    listContainer: { paddingBottom: 40 },
    jobCard: { borderRadius: 20, padding: 16, marginBottom: 15, elevation: 3, shadowOpacity: 0.1, shadowRadius: 5 },
    cardMain: { flexDirection: 'row', alignItems: 'center' },
    iconBox: { width: 56, height: 56, borderRadius: 16, justifyContent: 'center', alignItems: 'center', marginRight: 15 },
    infoBox: { flex: 1 },
    fileName: { fontSize: 16, fontWeight: '700', fontFamily: 'NotoSans-Bold', marginBottom: 2 },
    metaText: { fontSize: 12, opacity: 0.8 },
    statusBadge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 10 },
    statusText: { fontSize: 11, fontWeight: 'bold', color: '#854d0e' },
    progressContainer: { marginTop: 12, flexDirection: 'row', alignItems: 'center' },
    progressBarBg: { flex: 1, height: 8, backgroundColor: '#f1f5f9', borderRadius: 4, overflow: 'hidden', marginRight: 10 },
    progressBarFill: { height: '100%', borderRadius: 4 },
    progressLabel: { fontSize: 12, fontWeight: 'bold', color: '#64748b', width: 35 },
    actionRow: { marginTop: 12, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderTopWidth: 1, borderTopColor: '#f1f5f9', paddingTop: 8 },
    readyMessage: { fontSize: 13, fontWeight: '700' },
    centerBox: { flex: 1, justifyContent: 'center', alignItems: 'center', marginTop: 40 },
    emptyBox: { alignItems: 'center', marginTop: 60, paddingHorizontal: 40 },
    emptyTitle: { fontSize: 18, fontWeight: 'bold', marginTop: 15 },
    emptySubtitle: { fontSize: 14, textAlign: 'center', marginTop: 5, opacity: 0.7 }
});

export default PDFToExamScreen;
