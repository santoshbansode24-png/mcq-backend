import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { 
    View, Text, StyleSheet, SectionList, ActivityIndicator, 
    TouchableOpacity, Linking, Image, TextInput, Alert,
    RefreshControl, Platform, ScrollView, LayoutAnimation, UIManager,
    Modal
} from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { fetchNotifications } from '../api/notifications';
import { useTheme } from '../context/ThemeContext';
import axios from 'axios';
import { BASE_URL, API_URL } from '../api/config';
import * as Print from 'expo-print';
import * as FileSystem from 'expo-file-system/legacy';
import { LinearGradient } from 'expo-linear-gradient';

if (Platform.OS === 'android' && UIManager.setLayoutAnimationEnabledExperimental) {
    UIManager.setLayoutAnimationEnabledExperimental(true);
}

const createHTML = (mcqs, short, long, subjectTitle, currentMarks, schoolName, date) => {
    let mcqSection = "", shortSection = "", longSection = "";

    if (mcqs && mcqs.length > 0) {
        mcqSection += `<h3>Section A: Multiple Choice Questions (1 Mark each)</h3><ol>`;
        mcqs.forEach(q => {
            mcqSection += `<li class="question-item"><div class="question-text">${q.question || q.question_text}</div><div class="options-grid"><div class="option">(A) ${q.option_a}</div><div class="option">(B) ${q.option_b}</div><div class="option">(C) ${q.option_c}</div><div class="option">(D) ${q.option_d}</div></div></li>`;
        });
        mcqSection += `</ol>`;
    }

    if (short && short.length > 0) {
        shortSection += `<h3>Section B: Short Answer Questions (2 Marks each)</h3><ol>`;
        short.forEach(q => {
            const qText = q.question_front || q.question || q.front || "Question";
            shortSection += `<li class="question-item"><div class="question-text">${q.isCustom ? '<span style="color:#C026D3;">[Custom]</span> ' : ''}${qText}</div><br/><br/><br/></li>`;
        });
        shortSection += `</ol>`;
    }

    if (long && long.length > 0) {
        longSection += `<h3>Section C: Long Answer Questions (5 Marks each)</h3><ol>`;
        long.forEach((q) => {
            longSection += `<li class="question-item"><div class="question-text">${q.q}</div><br/><br/><br/><br/><br/></li>`;
        });
        longSection += `</ol>`;
    }

    return `
    <html>
      <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no" />
        <style>
          @page { margin: 70px 80px; }
          body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; color: #333; line-height: 1.6; }
          .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 35px; }
          .header h1 { margin: 0; font-size: 26px; color: #4A00E0; text-transform: uppercase; }
          .header h2 { margin: 5px 0; font-size: 18px; color: #333; }
          .details { display: flex; justify-content: space-between; margin-bottom: 35px; font-weight: bold; border: 1px solid #ddd; padding: 12px 15px; font-size: 15px; }
          h3 { border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-top: 35px; margin-bottom: 20px; color: #444; font-size: 18px; }
          .question-item { margin-bottom: 20px; page-break-inside: avoid; }
          .question-text { font-weight: 500; font-size: 16px; }
          .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 8px; font-size: 14px; }
          .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80px; color: rgba(0,0,0,0.03); z-index: -1; }
        </style>
      </head>
      <body>
        <div class="watermark">${schoolName || 'VEERU APP'}</div>
        <div class="header">
          <h2>${schoolName || 'VEERU APP'}</h2>
          <h1>WORKSHEET</h1>
          <p>Subjects: ${subjectTitle}</p>
        </div>
        <div class="details"><span>Name: ____________________</span><span>Date: ${date || new Date().toLocaleDateString()}</span><span>Total Marks: ${Math.round(currentMarks)}</span></div>
        ${mcqSection + shortSection + longSection}
      </body>
    </html>
    `;
};

const ClassUpdatesScreen = ({ user, onUserUpdate, navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const [notifications, setNotifications] = useState([]);
    const [loading, setLoading] = useState(true);
    const [joinCode, setJoinCode] = useState('');
    const [joining, setJoining] = useState(false);
    const [showJoinForm, setShowJoinForm] = useState(!user?.class_id);

    const [activeTab, setActiveTab] = useState('Notifications');
    const [expandedCardIds, setExpandedCardIds] = useState({});
    const [selectedImageViewUrl, setSelectedImageViewUrl] = useState(null);
    const [imageViewVisible, setImageViewVisible] = useState(false);
    const [imageLoading, setImageLoading] = useState(false);
    const [imageError, setImageError] = useState(false);
    const [hasTriedFallback, setHasTriedFallback] = useState(false);

    const handleCloseImageViewer = () => {
        setImageViewVisible(false);
        setSelectedImageViewUrl(null);
        setImageLoading(false);
        setImageError(false);
        setHasTriedFallback(false);
    };

    const handleImageError = () => {
        if (!hasTriedFallback && selectedImageViewUrl) {
            let fallbackUrl = selectedImageViewUrl;
            
            // Check if the current URL has /backend/
            if (selectedImageViewUrl.includes('/backend/uploads/')) {
                // Try removing /backend/
                fallbackUrl = selectedImageViewUrl.replace('/backend/uploads/', '/uploads/');
            } else if (selectedImageViewUrl.includes('/uploads/') && !selectedImageViewUrl.includes('/backend/uploads/')) {
                // Try adding /backend/
                fallbackUrl = selectedImageViewUrl.replace('/uploads/', '/backend/uploads/');
            }
            
            if (fallbackUrl !== selectedImageViewUrl) {
                console.log(`[Image Load Failure] Trying fallback URL: ${fallbackUrl}`);
                setHasTriedFallback(true);
                setSelectedImageViewUrl(fallbackUrl);
                return;
            }
        }
        
        console.error(`[Image Load Failure] All URL attempts failed for: ${selectedImageViewUrl}`);
        setImageError(true);
        setImageLoading(false);
    };

    const toggleExpand = (id) => {
        LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
        setExpandedCardIds(prev => ({
            ...prev,
            [id]: !prev[id]
        }));
    };

    const [joinedClasses, setJoinedClasses] = useState([]);
    const [selectedClassId, setSelectedClassId] = useState('all');

    useEffect(() => {
        if (user?.user_id || user?.id) {
            loadJoinedClasses();
        } else {
            setLoading(false);
        }
    }, [user?.user_id, user?.id]);

    const loadJoinedClasses = async () => {
        try {
            const studentId = user?.user_id || user?.id;
            const response = await axios.get(`${API_URL}/student/get_joined_classes.php?student_id=${studentId}`);
            if (response.data && response.data.status === 'success') {
                const classes = response.data.data;
                setJoinedClasses(classes);
                if (classes.length > 0) {
                    setShowJoinForm(false);
                    loadNotifications(classes, selectedClassId);
                } else {
                    setShowJoinForm(true);
                    setNotifications([]);
                    setLoading(false);
                }
            }
        } catch (error) {
            console.error('Failed to load joined classes', error);
            setLoading(false);
        }
    };

    const loadNotifications = async (classesList = joinedClasses, currentSelectedId = selectedClassId) => {
        if (!classesList || classesList.length === 0) {
            setNotifications([]);
            setLoading(false);
            return;
        }

        setLoading(true);
        try {
            let fetchIdParam = currentSelectedId;
            if (currentSelectedId === 'all') {
                fetchIdParam = classesList.map(c => c.class_id).join(',');
            }

            const response = await fetchNotifications(fetchIdParam);
            if (response.status === 'success') {
                setNotifications(response.data);
            }
        } catch (error) {
            console.error('Failed to load notifications', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSelectClass = (cId) => {
        setSelectedClassId(cId);
        loadNotifications(joinedClasses, cId);
    };

    const handleJoinClass = async () => {
        if (joinCode.length < 4) {
            Alert.alert("Invalid Code", "Please enter a valid 6-digit class code.");
            return;
        }

        setJoining(true);
        try {
            const studentId = user?.user_id || user?.id;
            const response = await axios.post(`${API_URL}/student/join_classroom.php`, {
                student_id: studentId,
                class_code: joinCode
            });
            const result = response.data;

            if (result.status === 'success') {
                Alert.alert("Success 🎉", result.message);
                setShowJoinForm(false);
                
                // Refresh the joined classes list to include the newly joined class
                await loadJoinedClasses();
                
                // Sync the user's primary standard, name, and board type locally
                try {
                    if (onUserUpdate && result.data) {
                        onUserUpdate({ 
                            class_id: result.data.class_id,
                            class_name: result.data.class_name,
                            board_type: result.data.board_type
                        });
                    }
                } catch (stateErr) {
                    console.error("Failed to propagate user class update state:", stateErr);
                }
            } else {
                Alert.alert("Error", result.message || "Failed to join class.");
            }
        } catch (error) {
            console.error("Join Class Error:", error);
            
            if (error.response && error.response.data) {
                Alert.alert("Error", error.response.data.message || "Failed to join class. Please try again.");
            } else {
                Alert.alert("Connection Error", "Could not connect to server. Please check your internet connection.");
            }
        } finally {
            setJoining(false);
        }
    };

    const formatDate = (dateString) => {
        let cleanDateStr = dateString;
        if (cleanDateStr && typeof cleanDateStr === 'string') {
            cleanDateStr = cleanDateStr.replace(' ', 'T');
            if (!cleanDateStr.endsWith('Z') && !cleanDateStr.includes('+') && !cleanDateStr.includes('-')) {
                cleanDateStr += 'Z';
            }
        }
        const date = new Date(cleanDateStr);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    };

    // ── Shared handler for ALL "Start Live Exam" buttons (was duplicated 4x) ──
    const handleStartLiveExam = useCallback(async (session) => {
        try {
            setLoading(true);
            const classId = session.class_id;
            const userId = user?.user_id || user?.id || 0;
            const examId = session.parsedPayload?.exam_id || session.payload?.exam_id || 0;
            const response = await axios.get(`${API_URL}/student/check_live_exam.php?class_id=${classId}&user_id=${userId}&exam_id=${examId}`);
            if (response.data && response.data.status === 'success') {
                if (response.data.data) {
                    const examData = response.data.data;
                    if (examData.questions && examData.questions.length > 0) {
                        navigation.navigate('MyExamTest', {
                            questions: examData.questions,
                            totalQuestions: examData.questions.length,
                            subjectName: examData.title,
                            update_id: examData.exam_id
                        });
                    } else {
                        Alert.alert('Notice', 'This exam does not contain any questions yet.');
                    }
                } else {
                    const message = response.data.message || 'This live exam has already ended or is no longer active.';
                    Alert.alert('Notice', message);
                }
            } else {
                Alert.alert('Exam Ended', 'This live exam has already ended or is no longer active.');
            }
        } catch (err) {
            const errorMsg = err.response && err.response.data && err.response.data.message
                ? err.response.data.message
                : (err.message || String(err));
            Alert.alert('Connection Error', 'Failed to connect to exam server:\n' + errorMsg);
            console.error('[handleStartLiveExam]:', err);
        } finally {
            setLoading(false);
        }
    }, [user, navigation, API_URL]);

    const groupNotifications = useCallback((data) => {
        const groups = [];
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);

        const todayStr = today.toDateString();
        const yesterdayStr = yesterday.toDateString();

        const sections = {
            'Today': [],
            'Yesterday': [],
            'Earlier': []
        };

        data.forEach(item => {
            let dateStr = item.created_at;
            if (dateStr && typeof dateStr === 'string') {
                dateStr = dateStr.replace(' ', 'T');
                if (!dateStr.endsWith('Z') && !dateStr.includes('+') && !dateStr.includes('-')) {
                    dateStr += 'Z';
                }
            }
            const itemDate = new Date(dateStr);
            const itemDateStr = itemDate.toDateString();

            if (itemDateStr === todayStr) {
                sections['Today'].push(item);
            } else if (itemDateStr === yesterdayStr) {
                sections['Yesterday'].push(item);
            } else {
                sections['Earlier'].push(item);
            }
        });

        if (sections['Today'].length > 0) groups.push({ title: 'Today', data: sections['Today'] });
        if (sections['Yesterday'].length > 0) groups.push({ title: 'Yesterday', data: sections['Yesterday'] });
        if (sections['Earlier'].length > 0) groups.push({ title: 'Earlier', data: sections['Earlier'] });

        return groups;
    }, []);

    const isEventActive = useCallback((item) => {
        if (item.update_type !== 'live_class' && item.update_type !== 'live_exam') return false;
        if (item.parsedPayload && (item.parsedPayload.status === 'completed' || item.parsedPayload.ended === true)) return false;
        
        let scheduledTimeStr = item.created_at;
        if (item.parsedPayload && item.parsedPayload.scheduled_time) {
            scheduledTimeStr = item.parsedPayload.scheduled_time;
        }

        // Replace space with 'T' and append 'Z' for reliable UTC parsing on iOS/Safari/Android
        if (scheduledTimeStr && typeof scheduledTimeStr === 'string') {
            scheduledTimeStr = scheduledTimeStr.replace(' ', 'T');
            if (!scheduledTimeStr.endsWith('Z') && !scheduledTimeStr.includes('+') && !scheduledTimeStr.includes('-')) {
                scheduledTimeStr += 'Z';
            }
        }

        const scheduledTime = new Date(scheduledTimeStr).getTime();
        const now = new Date().getTime();
        
        // Active if scheduled for the future, or started less than 3 hours ago
        return (scheduledTime + 10800000) > now;
    }, []);

    const activeSessions = useMemo(() => notifications.filter(isEventActive), [notifications, isEventActive]);
    const tabData = useMemo(() => notifications.filter(item => {
        // Only hide active events from the general Updates feed, let them show up in their specific tabs
        if (isEventActive(item) && activeTab === 'Notifications') return false;
        
        const hasFileUrl = item.payload && (item.payload.file_url || item.payload.url);
        const urlStr = hasFileUrl ? (item.payload.file_url || item.payload.url).toLowerCase() : '';
        const isUrlPdf = urlStr.includes('.pdf') || urlStr.includes('.doc');
        
        const isWksht = item.update_type === 'worksheet' || item.update_type === 'material' || (item.parsedPayload && item.parsedPayload.type === 'worksheet_data');
        const isClassRecording = item.update_type === 'live_class';
        const isLiveExam = item.update_type === 'live_exam';

        if (activeTab === 'Worksheets') {
             return isWksht;
        } else if (activeTab === 'Recordings') {
             return isClassRecording;
        } else if (activeTab === 'Live Exams') {
             return isLiveExam;
        } else {
             // Updates tab
             return !isWksht && !isClassRecording && !isLiveExam;
        }
    }), [notifications, isEventActive, activeTab]);

    const groupedData = useMemo(() => groupNotifications(tabData), [tabData, groupNotifications]);



    const renderItem = useCallback(({ item }) => {
        const hasFile = item.payload && (item.payload.file_url || item.payload.url);
        const urlStr = hasFile ? (item.payload.file_url || item.payload.url).toLowerCase() : '';
        const isUrlPdf = urlStr.includes('.pdf');
        
        const isPdf = item.update_type === 'pdf' || isUrlPdf;
        const isPhoto = item.update_type === 'photo' && !isUrlPdf;
        const isExam = item.update_type === 'live_exam';
        const isHomework = item.update_type === 'homework' && !isUrlPdf;
        const isWorksheet = item.update_type === 'worksheet' || item.update_type === 'material';
        const isLiveClass = item.update_type === 'live_class';

        let displayMessage = item.cleanMessage || item.message;
        let htmlPayload = null;
        let payloadData = item.parsedPayload || null;
        const getUrlType = (url) => {
            if (!url || typeof url !== 'string') return 'other';
            const cleanUrl = url.toLowerCase().split('?')[0];
            if (cleanUrl.endsWith('.pdf')) return 'pdf';
            if (cleanUrl.endsWith('.png') || cleanUrl.endsWith('.jpg') || cleanUrl.endsWith('.jpeg') || cleanUrl.endsWith('.gif') || cleanUrl.endsWith('.webp')) return 'image';
            return 'other';
        };

        let finalUrl = null;
        let urlType = 'other';
        
        if (hasFile) {
            const fileUrl = item.payload.file_url || item.payload.url;
            
            // Clean the fileUrl — strip leading slash and whitespace
            let cleanFileUrl = fileUrl.trim();
            if (cleanFileUrl.startsWith('/')) {
                cleanFileUrl = cleanFileUrl.substring(1);
            }
            
            // Build absolute URL: if already absolute, use as-is.
            // If starts with uploads/, prepend the backend base URL.
            const url = cleanFileUrl.startsWith('http') 
                ? cleanFileUrl 
                : `${API_URL.replace('/api', '')}/${cleanFileUrl}`;
            
            // Remove potential double slashes (except protocol ones)
            finalUrl = url.replace(/([^:]\/)\/+/g, '$1');
            urlType = getUrlType(finalUrl);
        }

        const openAttachment = () => {
            if (hasFile && finalUrl) {
                if (urlType === 'pdf') {
                    navigation.navigate('PDFViewer', { url: finalUrl, title: item.title || 'Document' });
                } else if (urlType === 'image') {
                    setImageLoading(true);
                    setImageError(false);
                    setHasTriedFallback(false);
                    setSelectedImageViewUrl(finalUrl);
                    setImageViewVisible(true);
                } else {
                    Linking.openURL(finalUrl);
                }
            }
        };

        const generateAndOpenLocalPdf = async () => {
            if (!payloadData && !htmlPayload) return;
            try {
                let currentHtmlPayload = htmlPayload;
                
                if (!currentHtmlPayload && payloadData) {
                    if (payloadData.type === 'worksheet_data' && payloadData.data) {
                        const d = payloadData.data;
                        currentHtmlPayload = createHTML(d.mcqs, d.short, d.long, payloadData.subjectNames, d.totalMarks, d.schoolName, d.date);
                    } else if (payloadData.html) {
                        currentHtmlPayload = payloadData.html;
                    }
                }
                
                if (!currentHtmlPayload) {
                    Alert.alert('Error', 'No worksheet content available.');
                    return;
                }

                const worksheetTitle = item.title || 'Worksheet';
                const fileUri = `${FileSystem.cacheDirectory}worksheet_${item.notification_id || item.id}.pdf`;
                const fileInfo = await FileSystem.getInfoAsync(fileUri);

                // Only generate the PDF if it hasn't been generated yet
                if (!fileInfo.exists) {
                    const { uri } = await Print.printToFileAsync({ html: currentHtmlPayload });
                    await FileSystem.copyAsync({ from: uri, to: fileUri });
                }
                
                // Navigate to the built-in PDF viewer (works on all devices, no external app needed)
                navigation.navigate('PDFViewer', { url: fileUri, title: worksheetTitle });

            } catch (error) {
                console.error('Error generating PDF:', error);
                Alert.alert('Error', `Failed to open PDF worksheet.\nDetails: ${error.message || 'Unknown error'}`);
            }
        };

        if (activeTab === 'Notifications') {
            const cardId = item.notification_id || item.id;
            const isExpanded = !!expandedCardIds[cardId];
            
            return (
                <View style={[styles.updateCard, { backgroundColor: isDarkMode ? '#082f49' : '#f0f9ff' }]}>
                    <LinearGradient
                        colors={isDarkMode ? ['#3B82F6', '#1E40AF'] : ['#93C5FD', '#3B82F6']}
                        start={{ x: 0, y: 0 }}
                        end={{ x: 1, y: 0 }}
                        style={styles.updateCardAccent}
                    />
                    <TouchableOpacity 
                        activeOpacity={0.7}
                        onPress={() => toggleExpand(cardId)}
                        style={styles.updateHeaderTouch}
                    >
                        <View style={styles.cardHeader}>
                            <View style={[styles.iconContainer, { 
                                backgroundColor: isPdf ? '#FEE2E2' : 
                                                isPhoto ? '#ECFDF5' : 
                                                isExam ? '#FEF3C7' : 
                                                isHomework ? '#E0F2FE' : 
                                                isWorksheet ? '#F5F3FF' : 
                                                isLiveClass ? '#FFE4E6' : '#EEF2FF' 
                            }]}>
                                <MaterialCommunityIcons 
                                    name={
                                        isPdf ? "file-pdf-box" : 
                                        isPhoto ? "image" : 
                                        isExam ? "timer-outline" : 
                                        (payloadData || htmlPayload || isWorksheet) ? "file-document-edit-outline" :
                                        isHomework ? "home-edit-outline" : 
                                        isLiveClass ? "youtube-tv" : "bell-outline"
                                    } 
                                    size={22} 
                                    color={
                                        isPdf ? "#EF4444" : 
                                        isPhoto ? "#10B981" : 
                                        isExam ? "#D97706" : 
                                        (payloadData || htmlPayload || isWorksheet) ? "#8B5CF6" :
                                        isHomework ? "#0EA5E9" : 
                                        isLiveClass ? "#E11D48" : "#6366F1"
                                    }
                                />
                            </View>
                            <View style={styles.titleContainer}>
                                <View style={styles.typeRow}>
                                    <Text style={[styles.typeTag, { 
                                        color: isExam ? '#D97706' : (payloadData || htmlPayload || isWorksheet) ? '#8B5CF6' : isHomework ? '#0EA5E9' : isLiveClass ? '#E11D48' : '#6366F1' 
                                    }]}>
                                        {isLiveClass ? '🔴 LIVE CLASS' : (((payloadData || htmlPayload) ? 'WORKSHEET' : item.update_type?.toUpperCase()) || 'ANNOUNCEMENT')}
                                    </Text>
                                    <Text style={styles.date}>{formatDate(item.created_at)}</Text>
                                </View>
                                <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 2 }}>
                                    <Text style={[styles.title, { color: theme.text, flex: 1, paddingRight: 12 }]} numberOfLines={2}>{item.title}</Text>
                                    <MaterialCommunityIcons 
                                        name={isExpanded ? "chevron-up" : "chevron-down"} 
                                        size={22} 
                                        color={isDarkMode ? '#94a3b8' : '#64748b'} 
                                    />
                                </View>
                            </View>
                        </View>
                    </TouchableOpacity>

                    {!isExpanded && (
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 8, paddingHorizontal: 4 }}>
                            <View style={[styles.teacherBadge, { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
                                <MaterialCommunityIcons name="account-tie" size={14} color="#64748b" />
                                <Text style={[styles.teacher, { color: '#64748b' }]}>{item.teacher_name}</Text>
                            </View>
                            <TouchableOpacity onPress={() => toggleExpand(cardId)}>
                                <Text style={{ fontSize: 12, color: theme.primary, fontFamily: 'NotoSans-Bold' }}>Tap to view details</Text>
                            </TouchableOpacity>
                        </View>
                    )}

                    {isExpanded && (
                        <View style={styles.expandedContent}>
                            <View style={[styles.divider, { backgroundColor: isDarkMode ? '#334155' : '#e2e8f0' }]} />
                            
                            <Text style={[styles.expandedMessage, { color: theme.textSecondary }]}>{displayMessage}</Text>
                            
                            {isLiveClass && (
                                <TouchableOpacity 
                                    style={[styles.actionButton, { backgroundColor: '#E11D48' }]} 
                                    onPress={() => {
                                        navigation.navigate('LiveClass', { 
                                            classUpdate: item,
                                            userId: user?.user_id || user?.id
                                        });
                                    }}
                                >
                                    <MaterialCommunityIcons name="youtube-tv" size={20} color="white" />
                                    <Text style={styles.actionButtonText}>Join Live Class</Text>
                                </TouchableOpacity>
                            )}

                            {isExam && (
                                <TouchableOpacity 
                                    style={[styles.actionButton, { backgroundColor: '#D97706' }]} 
                                    onPress={() => handleStartLiveExam(item)}
                                >
                                    <MaterialCommunityIcons name="play-circle" size={20} color="white" />
                                    <Text style={styles.actionButtonText}>Start Live Exam</Text>
                                </TouchableOpacity>
                            )}

                            {isHomework && !payloadData && !htmlPayload && (
                                <TouchableOpacity 
                                    style={[styles.actionButton, { backgroundColor: '#0EA5E9' }]} 
                                    onPress={() => Alert.alert(item.title || "Homework", displayMessage || "No description provided.")}
                                >
                                    <MaterialCommunityIcons name="clipboard-text" size={20} color="white" />
                                    <Text style={styles.actionButtonText}>View Homework</Text>
                                </TouchableOpacity>
                            )}

                            {(payloadData || htmlPayload) && (
                                <TouchableOpacity 
                                    style={[styles.actionButton, { backgroundColor: '#8B5CF6' }]} 
                                    onPress={generateAndOpenLocalPdf}
                                >
                                    <MaterialCommunityIcons name="file-document-edit-outline" size={20} color="white" />
                                    <Text style={styles.actionButtonText}>Open Worksheet</Text>
                                </TouchableOpacity>
                            )}




                            {hasFile && urlType === 'image' && (
                                <TouchableOpacity onPress={openAttachment} activeOpacity={0.9} style={{ marginTop: 12, borderRadius: 12, overflow: 'hidden', backgroundColor: isDarkMode ? '#1e293b' : '#f1f5f9' }}>
                                    <Image source={{ uri: finalUrl }} style={{ width: '100%', height: 200, resizeMode: 'cover' }} />
                                    <View style={{ position: 'absolute', bottom: 10, right: 10, backgroundColor: 'rgba(0,0,0,0.6)', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8, flexDirection: 'row', alignItems: 'center' }}>
                                        <MaterialCommunityIcons name="fullscreen" size={16} color="white" />
                                        <Text style={{ color: 'white', fontSize: 12, marginLeft: 4, fontFamily: 'NotoSans-Medium' }}>View Full</Text>
                                    </View>
                                </TouchableOpacity>
                            )}

                            {hasFile && urlType !== 'image' && (
                                <TouchableOpacity 
                                    style={[styles.attachmentButton, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]} 
                                    onPress={openAttachment}
                                >
                                    <MaterialCommunityIcons 
                                        name={isPdf ? "file-pdf-box" : "file-outline"} 
                                        size={20} 
                                        color={isPdf ? "#EF4444" : "#64748b"} 
                                    />
                                    <Text style={[styles.attachmentText, { color: isPdf ? "#EF4444" : "#64748b" }]}>
                                        {isPdf ? 'Download PDF' : 'Open'}
                                    </Text>
                                </TouchableOpacity>
                            )}

                            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 12, paddingHorizontal: 4 }}>
                                <View style={[styles.teacherBadge, { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
                                    <MaterialCommunityIcons name="account-tie" size={14} color="#64748b" />
                                    <Text style={[styles.teacher, { color: '#64748b' }]}>{item.teacher_name}</Text>
                                </View>
                                <TouchableOpacity onPress={() => toggleExpand(cardId)}>
                                    <Text style={{ fontSize: 12, color: theme.primary, fontFamily: 'NotoSans-Bold' }}>Collapse</Text>
                                </TouchableOpacity>
                            </View>
                        </View>
                    )}
                </View>
            );
        }

        if (activeTab === 'Live Exams') {

            return (
                <View style={[styles.examCard, { backgroundColor: isDarkMode ? '#451a03' : '#fffbeb' }]}>
                    <LinearGradient
                        colors={['#F59E0B', '#D97706']}
                        start={{ x: 0, y: 0 }}
                        end={{ x: 1, y: 0 }}
                        style={styles.examCardAccent}
                    />
                    
                    <View style={styles.examHeader}>
                        <View style={[styles.examIconContainer, { backgroundColor: isDarkMode ? '#334155' : '#FEF3C7' }]}>
                            <MaterialCommunityIcons 
                                name="timer-outline" 
                                size={26} 
                                color="#D97706" 
                            />
                        </View>
                        
                        <View style={styles.examTitleContainer}>
                            <View style={styles.examTypeRow}>
                                <Text style={[styles.examTypeTag, { color: '#D97706' }]}>
                                    LIVE EXAM
                                </Text>
                                <Text style={styles.examDate}>{formatDate(item.created_at)}</Text>
                            </View>
                            <Text style={[styles.examTitle, { color: theme.text }]} numberOfLines={2}>
                                {item.title}
                            </Text>
                        </View>
                    </View>

                    <View style={styles.examBody}>
                        {displayMessage && (
                            <Text style={[styles.examMessage, { color: theme.textSecondary }]}>{displayMessage}</Text>
                        )}
                        
                        <View style={styles.examMetaRow}>
                            <View style={[styles.teacherBadge, { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
                                <MaterialCommunityIcons name="account-tie" size={14} color="#64748b" />
                                <Text style={[styles.teacher, { color: '#64748b' }]}>{item.teacher_name}</Text>
                            </View>
                        </View>

                        <TouchableOpacity 
                            onPress={() => handleStartLiveExam(item)}
                            activeOpacity={0.8}
                        >
                            <LinearGradient
                                colors={['#F59E0B', '#D97706']}
                                start={{ x: 0, y: 0 }}
                                end={{ x: 1, y: 0 }}
                                style={styles.examStartButton}
                            >
                                <MaterialCommunityIcons name="play-circle-outline" size={18} color="white" style={{ marginRight: 6 }} />
                                <Text style={styles.examStartButtonText}>Start Exam</Text>
                            </LinearGradient>
                        </TouchableOpacity>
                    </View>
                </View>
            );
        }

        if (activeTab === 'Recordings') {
            const handleOpenRecording = () => {
                navigation.navigate('LiveClass', { 
                    classUpdate: item,
                    userId: user?.user_id || user?.id
                });
            };

            return (
                <View style={[styles.recordingCard, { backgroundColor: isDarkMode ? '#1e293b' : '#ffffff' }]}>
                    <LinearGradient
                        colors={['#EF4444', '#F87171']}
                        start={{ x: 0, y: 0 }}
                        end={{ x: 1, y: 0 }}
                        style={styles.recordingCardAccent}
                    />
                    
                    <View style={styles.recordingHeader}>
                        <View style={[styles.recordingIconContainer, { backgroundColor: isDarkMode ? '#334155' : '#FFE4E6' }]}>
                            <MaterialCommunityIcons 
                                name="video-play-outline" 
                                size={26} 
                                color="#EF4444" 
                            />
                        </View>
                        
                        <View style={styles.recordingTitleContainer}>
                            <View style={styles.recordingTypeRow}>
                                <Text style={[styles.recordingTypeTag, { color: '#EF4444' }]}>
                                    CLASS RECORDING
                                </Text>
                                <Text style={styles.recordingDate}>{formatDate(item.created_at)}</Text>
                            </View>
                            <Text style={[styles.recordingTitle, { color: theme.text }]} numberOfLines={2}>
                                {item.title}
                            </Text>
                        </View>
                    </View>

                    <View style={styles.recordingBody}>
                        {displayMessage && (
                            <Text style={[styles.recordingMessage, { color: theme.textSecondary }]}>{displayMessage}</Text>
                        )}
                        
                        <View style={styles.recordingMetaRow}>
                            <View style={[styles.teacherBadge, { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
                                <MaterialCommunityIcons name="account-tie" size={14} color="#64748b" />
                                <Text style={[styles.teacher, { color: '#64748b' }]}>{item.teacher_name}</Text>
                            </View>
                        </View>

                        <TouchableOpacity 
                            onPress={handleOpenRecording}
                            activeOpacity={0.8}
                        >
                            <LinearGradient
                                colors={['#EF4444', '#DC2626']}
                                start={{ x: 0, y: 0 }}
                                end={{ x: 1, y: 0 }}
                                style={styles.recordingStartButton}
                            >
                                <MaterialCommunityIcons name="play-circle" size={18} color="white" style={{ marginRight: 6 }} />
                                <Text style={styles.recordingStartButtonText}>Watch Recording</Text>
                            </LinearGradient>
                        </TouchableOpacity>
                    </View>
                </View>
            );
        }

        if (activeTab === 'Worksheets') {
            const isDynamicWksht = (payloadData && (payloadData.type === 'worksheet_data' && payloadData.data || payloadData.html)) || htmlPayload;
            const isStaticWksht = hasFile;
            
            const handleOpenWorksheet = () => {
                if (isDynamicWksht) {
                    generateAndOpenLocalPdf();
                } else if (isStaticWksht) {
                    openAttachment();
                } else {
                    Alert.alert("Worksheet", "No attachment file or content available.");
                }
            };

            return (
                <View style={[styles.worksheetCard, { backgroundColor: isDarkMode ? '#2e1065' : '#faf5ff' }]}>
                    <LinearGradient
                        colors={isDarkMode ? ['#8B5CF6', '#4F46E5'] : ['#A78BFA', '#8B5CF6']}
                        start={{ x: 0, y: 0 }}
                        end={{ x: 1, y: 0 }}
                        style={styles.worksheetCardAccent}
                    />
                    
                    <View style={styles.worksheetHeader}>
                        <View style={[styles.worksheetIconContainer, { backgroundColor: isDarkMode ? '#334155' : '#F5F3FF' }]}>
                            <MaterialCommunityIcons 
                                name={isStaticWksht ? "file-pdf-box" : "file-document-edit-outline"} 
                                size={26} 
                                color="#8B5CF6" 
                            />
                        </View>
                        
                        <View style={styles.worksheetTitleContainer}>
                            <View style={styles.worksheetTypeRow}>
                                <Text style={[styles.worksheetTypeTag, { color: '#8B5CF6' }]}>
                                    {isDynamicWksht ? 'AI WORKSHEET' : 'PRACTICE WORKSHEET'}
                                </Text>
                                <Text style={styles.worksheetDate}>{formatDate(item.created_at)}</Text>
                            </View>
                            <Text style={[styles.worksheetTitle, { color: theme.text }]} numberOfLines={2}>
                                {item.title}
                            </Text>
                        </View>
                    </View>

                    <View style={styles.worksheetBody}>
                        <View style={styles.worksheetMetaRow}>
                            <View style={[styles.teacherBadge, { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
                                <MaterialCommunityIcons name="account-tie" size={14} color="#64748b" />
                                <Text style={[styles.teacher, { color: '#64748b' }]}>{item.teacher_name}</Text>
                            </View>
                        </View>

                        <TouchableOpacity 
                            onPress={handleOpenWorksheet}
                            activeOpacity={0.8}
                        >
                            <LinearGradient
                                colors={['#8B5CF6', '#7C3AED']}
                                start={{ x: 0, y: 0 }}
                                end={{ x: 1, y: 0 }}
                                style={styles.worksheetOpenButton}
                            >
                                <MaterialCommunityIcons name="eye-outline" size={18} color="white" style={{ marginRight: 6 }} />
                                <Text style={styles.worksheetOpenButtonText}>Open</Text>
                            </LinearGradient>
                        </TouchableOpacity>
                    </View>
                </View>
            );
        }

        return (
            <View style={[styles.card, { backgroundColor: isDarkMode ? '#1e293b' : 'rgba(255, 255, 255, 0.65)' }]}>
                <View style={styles.cardHeader}>
                    <View style={[styles.iconContainer, { 
                        backgroundColor: isPdf ? '#FEE2E2' : 
                                        isPhoto ? '#ECFDF5' : 
                                        isExam ? '#FEF3C7' : 
                                        isHomework ? '#E0F2FE' : 
                                        isWorksheet ? '#F5F3FF' : 
                                        isLiveClass ? '#FFE4E6' : '#EEF2FF' 
                    }]}>
                        <MaterialCommunityIcons 
                            name={
                                isPdf ? "file-pdf-box" : 
                                isPhoto ? "image" : 
                                isExam ? "timer-outline" : 
                                (payloadData || htmlPayload || isWorksheet) ? "file-document-edit-outline" :
                                isHomework ? "home-edit-outline" : 
                                isLiveClass ? "youtube-tv" : "bell-outline"
                            } 
                            size={22} 
                            color={
                                isPdf ? "#EF4444" : 
                                isPhoto ? "#10B981" : 
                                isExam ? "#D97706" : 
                                (payloadData || htmlPayload || isWorksheet) ? "#8B5CF6" :
                                isHomework ? "#0EA5E9" : 
                                isLiveClass ? "#E11D48" : "#6366F1"
                            }
                        />
                    </View>
                    <View style={styles.titleContainer}>
                        <View style={styles.typeRow}>
                            <Text style={[styles.typeTag, { 
                                color: isExam ? '#D97706' : (payloadData || htmlPayload || isWorksheet) ? '#8B5CF6' : isHomework ? '#0EA5E9' : isLiveClass ? '#E11D48' : '#94a3b8' 
                            }]}>
                                {isLiveClass ? '🔴 LIVE CLASS' : (((payloadData || htmlPayload) ? 'WORKSHEET' : item.update_type?.toUpperCase()) || 'ANNOUNCEMENT')}
                            </Text>
                            <Text style={styles.date}>{formatDate(item.created_at)}</Text>
                        </View>
                        <Text style={[styles.title, { color: theme.text }]} numberOfLines={2}>{item.title}</Text>
                    </View>
                </View>

                <View style={styles.cardBody}>
                    <Text style={[styles.message, { color: theme.textSecondary }]}>{displayMessage}</Text>
                    
                    {isLiveClass && (
                        <TouchableOpacity 
                            style={[styles.actionButton, { backgroundColor: '#E11D48' }]} 
                            onPress={() => {
                                navigation.navigate('LiveClass', { 
                                    classUpdate: item,
                                    userId: user?.user_id || user?.id
                                });
                            }}
                        >
                            <MaterialCommunityIcons name="youtube-tv" size={20} color="white" />
                            <Text style={styles.actionButtonText}>Join Live Class</Text>
                        </TouchableOpacity>
                    )}


                    {isExam && (
                        <TouchableOpacity 
                            style={[styles.actionButton, { backgroundColor: '#D97706' }]} 
                            onPress={() => handleStartLiveExam(item)}
                        >
                            <MaterialCommunityIcons name="play-circle" size={20} color="white" />
                            <Text style={styles.actionButtonText}>Start Live Exam</Text>
                        </TouchableOpacity>
                    )}

                    {isHomework && !payloadData && !htmlPayload && (
                        <TouchableOpacity 
                            style={[styles.actionButton, { backgroundColor: '#0EA5E9' }]} 
                            onPress={() => Alert.alert(item.title || "Homework", displayMessage || "No description provided.")}
                        >
                            <MaterialCommunityIcons name="clipboard-text" size={20} color="white" />
                            <Text style={styles.actionButtonText}>View Homework</Text>
                        </TouchableOpacity>
                    )}

                    {(payloadData || htmlPayload) && (
                        <TouchableOpacity 
                            style={[styles.actionButton, { backgroundColor: '#8B5CF6' }]} 
                            onPress={generateAndOpenLocalPdf}
                        >
                            <MaterialCommunityIcons name="file-document-edit-outline" size={20} color="white" />
                            <Text style={styles.actionButtonText}>Open</Text>
                        </TouchableOpacity>
                    )}


                    {hasFile && (
                        <TouchableOpacity 
                            style={[styles.attachmentButton, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]} 
                            onPress={openAttachment}
                        >
                            <MaterialCommunityIcons 
                                name={isPdf ? "file-pdf-box" : isWorksheet ? "file-document-outline" : "image"} 
                                size={20} 
                                color={isPdf ? "#EF4444" : isWorksheet ? "#8B5CF6" : "#10B981"} 
                            />
                            <Text style={[styles.attachmentText, { color: isPdf ? "#EF4444" : isWorksheet ? "#8B5CF6" : "#10B981" }]}>
                                {isPdf ? 'Download PDF' : isWorksheet ? 'Open Worksheet' : 'View Attachment'}
                            </Text>
                        </TouchableOpacity>
                    )}

                    <View style={[styles.teacherBadge, { backgroundColor: isDarkMode ? '#334155' : '#f1f5f9' }]}>
                        <MaterialCommunityIcons name="account-tie" size={14} color="#64748b" />
                        <Text style={[styles.teacher, { color: '#64748b' }]}>{item.teacher_name}</Text>
                    </View>
                </View>
            </View>
        );
    }, [theme, isDarkMode, navigation, user, expandedCardIds, activeTab, setLoading, handleStartLiveExam]);

    return (
        <LinearGradient 
            colors={isDarkMode ? ['#0f172a', '#1e1b4b'] : ['#e0c3fc', '#8ec5fc']} 
            style={styles.container}
        >
            <View style={styles.header}>
                <View style={{ flex: 1 }}>
                    <Text style={[styles.headerSubtitle, { color: theme.primary }]}>SCHOOL NOTIFICATIONS</Text>
                    <Text style={[styles.headerTitle, { color: theme.text }]}>Class</Text>
                    {joinedClasses.length > 0 && selectedClassId !== 'all' && (
                        <Text style={{ fontSize: 13, color: isDarkMode ? '#94a3b8' : '#334155', marginTop: 4, fontFamily: 'NotoSans-Bold' }}>
                            {joinedClasses.find(c => c.class_id === selectedClassId)?.class_name || joinedClasses[0].class_name} • Code: {joinedClasses.find(c => c.class_id === selectedClassId)?.class_code || joinedClasses[0].class_code || 'N/A'}
                        </Text>
                    )}
                    {joinedClasses.length > 0 && selectedClassId === 'all' && (
                        <Text style={{ fontSize: 13, color: isDarkMode ? '#94a3b8' : '#334155', marginTop: 4, fontFamily: 'NotoSans-Bold' }}>
                            {joinedClasses.length} Joined Class{joinedClasses.length > 1 ? 'es' : ''}
                        </Text>
                    )}
                </View>
                {!showJoinForm && (
                    <View style={styles.headerButtons}>
                        {joinedClasses.length > 0 && (
                            <TouchableOpacity 
                                style={[styles.chatBtnSmall, { backgroundColor: theme.primary }]} 
                                onPress={() => {
                                    if (selectedClassId === 'all') {
                                        Alert.alert("Select a Class", "Please select a specific subject from the chips below to chat with its teacher.");
                                        return;
                                    }
                                    navigation.navigate('Chat', { userId: user?.user_id || user?.id, classId: selectedClassId });
                                }}
                            >
                                <MaterialCommunityIcons name="chat" size={18} color="#FFF" />
                                <Text style={styles.chatBtnSmallText}>Chat</Text>
                            </TouchableOpacity>
                        )}
                        <TouchableOpacity 
                            style={[styles.joinBtnSmall, { borderColor: theme.primary + '40' }]} 
                            onPress={() => setShowJoinForm(true)}
                        >
                            <MaterialCommunityIcons name="plus-circle-outline" size={18} color={theme.primary} />
                            <Text style={[styles.joinBtnSmallText, { color: theme.primary }]}>Join</Text>
                        </TouchableOpacity>
                    </View>
                )}
            </View>

            {joinedClasses.length > 0 && !showJoinForm && (
                <View style={styles.chipScrollWrapper}>
                    <SectionList
                        horizontal
                        showsHorizontalScrollIndicator={false}
                        contentContainerStyle={styles.chipContainer}
                        sections={[{ data: [{ id: 'all', name: 'All Subjects' }, ...joinedClasses.map(c => ({ id: c.class_id, name: `${c.class_name} - ${c.teacher_name}` }))] }]}
                        keyExtractor={item => item.id.toString()}
                        renderItem={({ item }) => {
                            const isSelected = selectedClassId === item.id;
                            return (
                                <TouchableOpacity 
                                    style={[
                                        styles.chip, 
                                        { 
                                            backgroundColor: isSelected ? theme.primary : (isDarkMode ? '#1e293b' : '#fff'),
                                            borderColor: isSelected ? theme.primary : (isDarkMode ? '#334155' : '#e2e8f0')
                                        }
                                    ]}
                                    onPress={() => handleSelectClass(item.id)}
                                >
                                    <Text style={[
                                        styles.chipText, 
                                        { color: isSelected ? '#fff' : (isDarkMode ? '#94a3b8' : '#64748b') }
                                    ]}>
                                        {item.name}
                                    </Text>
                                </TouchableOpacity>
                            );
                        }}
                    />
                </View>
            )}

            {showJoinForm && (
                <View style={[styles.joinCard, { backgroundColor: isDarkMode ? '#1e293b' : '#fff' }]}>
                    <View style={styles.joinHeader}>
                        <MaterialCommunityIcons name="school-outline" size={24} color={theme.primary} />
                        <Text style={[styles.joinTitle, { color: theme.text }]}>Join Your Class</Text>
                    </View>
                    <Text style={[styles.joinSub, { color: theme.textSecondary }]}>
                        Enter the 6-digit code provided by your teacher to see class updates and assignments.
                    </Text>
                    
                    <View style={styles.inputWrapper}>
                        <MaterialCommunityIcons name="key-variant" size={20} color="#94a3b8" style={styles.inputIcon} />
                        <View style={{ flex: 1 }}>
                            <TextInput 
                                style={[styles.input, { color: theme.text }]}
                                placeholder="Enter 6-Digit Code"
                                placeholderTextColor="#64748b"
                                value={joinCode}
                                onChangeText={(text) => setJoinCode(text.toUpperCase())}
                                autoCapitalize="characters"
                                maxLength={6}
                            />
                        </View>
                    </View>

                    <View style={styles.joinActions}>
                        {joinedClasses.length > 0 && (
                            <TouchableOpacity style={styles.cancelBtn} onPress={() => setShowJoinForm(false)}>
                                <Text style={styles.cancelBtnText}>Cancel</Text>
                            </TouchableOpacity>
                        )}
                        <TouchableOpacity 
                            style={[styles.joinBtn, { backgroundColor: theme.primary }]} 
                            onPress={handleJoinClass}
                            disabled={joining}
                        >
                            {joining ? (
                                <ActivityIndicator color="white" size="small" />
                            ) : (
                                <Text style={styles.joinBtnText}>Connect to Class</Text>
                            )}
                        </TouchableOpacity>
                    </View>
                </View>
            )}

            {loading ? (
                <View style={styles.centerContainer}>
                    <ActivityIndicator size="large" color={theme.primary} />
                    <Text style={[styles.loadingText, { color: theme.textSecondary }]}>Fetching class updates...</Text>
                </View>
            ) : (
                <SectionList
                    sections={groupedData}
                    renderItem={renderItem}
                    ListHeaderComponent={() => (
                        <View style={{ marginBottom: 16 }}>
                            {activeSessions.length > 0 && (
                                <View style={{ marginBottom: 20 }}>
                                    <Text style={[styles.sectionTitle, { color: theme.textSecondary, marginBottom: 10, paddingLeft: 24 }]}>ACTIVE SESSIONS</Text>
                                    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: 24, paddingBottom: 10 }}>
                                        {activeSessions.map((session, index) => {
                                            const isClass = session.update_type === 'live_class';
                                            return (
                                                <View key={session.notification_id || index} style={[styles.activeSessionCard, { backgroundColor: isClass ? '#FEF2F2' : '#FEF3C7', borderColor: isClass ? '#FECACA' : '#FDE68A', marginRight: 12 }]}>
                                                    <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 8 }}>
                                                        <MaterialCommunityIcons name={isClass ? "youtube-tv" : "timer-outline"} size={20} color={isClass ? "#E11D48" : "#D97706"} />
                                                        <Text style={{ marginLeft: 6, fontSize: 11, fontWeight: '900', color: isClass ? "#E11D48" : "#D97706" }}>
                                                            {isClass ? "LIVE CLASS" : "LIVE EXAM"}
                                                        </Text>
                                                    </View>
                                                    <Text style={{ fontSize: 15, fontFamily: 'NotoSans-Bold', color: '#1f2937', marginBottom: 4 }} numberOfLines={1}>
                                                        {session.title}
                                                    </Text>
                                                    <Text style={{ fontSize: 12, color: '#6b7280', marginBottom: 12 }}>
                                                        {session.teacher_name}
                                                    </Text>
                                                    <TouchableOpacity 
                                                        style={[styles.activeSessionBtn, { backgroundColor: isClass ? '#E11D48' : '#D97706' }]}
                                                        onPress={() => {
                                                            if (isClass) {
                                                                navigation.navigate('LiveClass', { classUpdate: session, userId: user?.user_id || user?.id });
                                                            } else {
                                                                handleStartLiveExam(session);
                                                            }
                                                        }}
                                                    >
                                                        <Text style={{ color: 'white', fontSize: 12, fontWeight: 'bold' }}>{isClass ? 'Join Now' : 'Start Exam'}</Text>
                                                    </TouchableOpacity>
                                                </View>
                                            );
                                        })}
                                    </ScrollView>
                                </View>
                            )}

                            <View style={[styles.tabContainer, { backgroundColor: isDarkMode ? 'rgba(30, 41, 59, 0.7)' : 'rgba(255, 255, 255, 0.8)', borderColor: isDarkMode ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.05)' }]}>
                                {['Notifications', 'Worksheets', 'Recordings', 'Live Exams'].map(tab => {
                                    const isActive = activeTab === tab;
                                    if (isActive) {
                                        return (
                                            <TouchableOpacity 
                                                key={tab}
                                                style={styles.tabBtnActive}
                                                onPress={() => setActiveTab(tab)}
                                            >
                                                <LinearGradient
                                                    colors={['#8B5CF6', '#6366F1']}
                                                    start={{ x: 0, y: 0 }}
                                                    end={{ x: 1, y: 0 }}
                                                    style={styles.tabBtnGradient}
                                                >
                                                    <Text style={[styles.tabBtnText, { color: '#fff' }]}>
                                                        {tab}
                                                    </Text>
                                                </LinearGradient>
                                            </TouchableOpacity>
                                        );
                                    }
                                    return (
                                        <TouchableOpacity 
                                            key={tab}
                                            style={styles.tabBtn}
                                            onPress={() => setActiveTab(tab)}
                                        >
                                            <Text style={[styles.tabBtnText, { color: theme.textSecondary }]}>
                                                {tab}
                                            </Text>
                                        </TouchableOpacity>
                                    );
                                })}
                            </View>
                        </View>
                    )}
                    renderSectionHeader={({ section: { title } }) => (
                        <View style={[styles.sectionHeader, { backgroundColor: 'transparent' }]}>
                            <Text style={[styles.sectionTitle, { color: theme.textSecondary }]}>{title}</Text>
                            <View style={[styles.sectionLine, { backgroundColor: isDarkMode ? '#334155' : '#e2e8f0' }]} />
                        </View>
                    )}
                    keyExtractor={(item, index) => (item.notification_id ? item.notification_id.toString() : `notif-${index}`)}
                    contentContainerStyle={styles.listContent}
                    showsVerticalScrollIndicator={false}
                    stickySectionHeadersEnabled={false}
                    ListEmptyComponent={
                        <View style={styles.emptyContainer}>
                            <View style={[styles.emptyIconCircle, { backgroundColor: theme.primary + '10' }]}>
                                <MaterialCommunityIcons name="bell-off-outline" size={60} color={theme.primary} />
                            </View>
                            <Text style={[styles.emptyText, { color: theme.text }]}>No Notifications Yet</Text>
                            <Text style={[styles.emptySub, { color: theme.textSecondary }]}>
                                When your teacher sends homework, exams, or worksheets, they will appear here.
                            </Text>
                            <TouchableOpacity 
                                style={[styles.refreshBtn, { backgroundColor: theme.primary }]}
                                onPress={loadNotifications}
                            >
                                <Text style={styles.refreshBtnText}>Check for Notifications</Text>
                            </TouchableOpacity>
                        </View>
                    }
                    refreshControl={
                        <RefreshControl refreshing={loading} onRefresh={loadNotifications} tintColor={theme.primary} />
                    }
                />
            )}
            
            {/* Glowing Full-Screen Image Viewer Modal */}
            <Modal
                visible={imageViewVisible}
                transparent={true}
                animationType="fade"
                onRequestClose={handleCloseImageViewer}
            >
                <View style={styles.modalContainer}>
                    <TouchableOpacity 
                        style={styles.modalCloseButton} 
                        onPress={handleCloseImageViewer}
                    >
                        <MaterialCommunityIcons name="close-circle" size={36} color="white" />
                    </TouchableOpacity>
                    
                    <View style={styles.modalImageWrapper}>
                        {selectedImageViewUrl && !imageError && (
                            <Image 
                                source={{ uri: selectedImageViewUrl }} 
                                style={styles.modalImage} 
                                resizeMode="contain"
                                onLoadStart={() => setImageLoading(true)}
                                onLoadEnd={() => setImageLoading(false)}
                                onError={handleImageError}
                            />
                        )}
                        
                        {imageLoading && (
                            <View style={styles.modalLoaderContainer}>
                                <ActivityIndicator size="large" color="#3B82F6" />
                                <Text style={styles.modalLoadingText}>Loading attachment...</Text>
                            </View>
                        )}
                        
                        {imageError && (
                            <View style={styles.modalErrorContainer}>
                                <MaterialCommunityIcons name="alert-circle-outline" size={48} color="#EF4444" />
                                <Text style={styles.modalErrorText}>Failed to load image</Text>
                                <Text style={styles.modalErrorSubText}>
                                    The attachment might have been removed or the path is invalid.
                                </Text>
                            </View>
                        )}
                    </View>
                    
                    {/* Subtle debug URL display */}
                    {selectedImageViewUrl && (
                        <Text style={styles.modalDebugText} numberOfLines={2}>
                            URL: {selectedImageViewUrl}
                        </Text>
                    )}
                </View>
            </Modal>
        </LinearGradient>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    tabContainer: {
        flexDirection: 'row',
        padding: 4,
        borderRadius: 16,
        marginBottom: 10,
        borderWidth: 1,
        borderColor: 'rgba(0,0,0,0.05)',
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 4,
    },
    tabBtn: {
        flex: 1,
        paddingVertical: 10,
        alignItems: 'center',
        borderRadius: 12,
    },
    tabBtnText: {
        fontSize: 11,
        fontFamily: 'NotoSans-Bold',
    },
    activeSessionCard: {
        width: 220,
        padding: 16,
        borderRadius: 20,
        borderWidth: 1,
    },
    activeSessionBtn: {
        paddingVertical: 8,
        borderRadius: 10,
        alignItems: 'center',
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 24,
        paddingTop: 20,
        paddingBottom: 10,
    },
    chipScrollWrapper: {
        marginBottom: 10,
        paddingLeft: 24,
    },
    chipContainer: {
        paddingRight: 48,
        gap: 8,
    },
    chip: {
        paddingHorizontal: 16,
        paddingVertical: 8,
        borderRadius: 20,
        borderWidth: 1,
        marginRight: 8,
    },
    chipText: {
        fontSize: 13,
        fontFamily: 'NotoSans-Bold',
    },
    headerTitle: {
        fontSize: 28,
        fontFamily: 'NotoSans-Bold',
    },
    headerSubtitle: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 1,
    },
    centerContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    listContent: {
        paddingHorizontal: 24,
        paddingBottom: 120,
    },
    card: {
        borderRadius: 24,
        padding: 16,
        marginBottom: 16,
        elevation: 4,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.05,
        shadowRadius: 12,
        borderWidth: 1,
        borderColor: 'rgba(0,0,0,0.05)',
    },
    cardHeader: {
        flexDirection: 'row',
        marginBottom: 12,
    },
    iconContainer: {
        width: 48,
        height: 48,
        borderRadius: 16,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 12,
    },
    titleContainer: {
        flex: 1,
        justifyContent: 'center',
    },
    title: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        lineHeight: 22,
    },
    date: {
        fontSize: 11,
        color: '#94a3b8',
        fontFamily: 'NotoSans-Regular',
    },
    cardBody: {
        marginTop: 4,
    },
    message: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
        lineHeight: 20,
        marginBottom: 16,
    },
    attachmentButton: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 12,
        borderRadius: 16,
        marginBottom: 12,
    },
    attachmentText: {
        marginLeft: 8,
        fontSize: 13,
        fontWeight: 'bold',
    },
    teacherBadge: {
        flexDirection: 'row',
        alignItems: 'center',
        alignSelf: 'flex-start',
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 8,
        gap: 4
    },
    teacher: {
        fontSize: 11,
        fontFamily: 'NotoSans-Bold',
    },
    typeRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 4,
    },
    typeTag: {
        fontSize: 9,
        fontWeight: '900',
        letterSpacing: 0.5,
    },
    actionButton: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        padding: 14,
        borderRadius: 20,
        marginBottom: 12,
        gap: 10,
        elevation: 4,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 6,
    },
    actionButtonText: {
        color: 'white',
        fontSize: 15,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    sectionHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: 12,
        gap: 12,
    },
    sectionTitle: {
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 1,
        textTransform: 'uppercase',
    },
    sectionLine: {
        flex: 1,
        height: 1,
    },
    loadingText: {
        marginTop: 12,
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
    },
    emptyContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        paddingTop: 60,
        paddingHorizontal: 40,
    },
    emptyIconCircle: {
        width: 120,
        height: 120,
        borderRadius: 60,
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 24,
    },
    emptySub: {
        fontSize: 14,
        textAlign: 'center',
        lineHeight: 20,
        marginTop: 8,
        marginBottom: 30,
        fontFamily: 'NotoSans-Regular',
    },
    emptyText: {
        fontSize: 20,
        fontFamily: 'NotoSans-Bold',
    },
    refreshBtn: {
        paddingHorizontal: 24,
        paddingVertical: 12,
        borderRadius: 12,
    },
    refreshBtnText: {
        color: 'white',
        fontSize: 14,
        fontFamily: 'NotoSans-Bold',
    },
    joinCard: {
        margin: 20,
        padding: 20,
        borderRadius: 24,
        elevation: 10,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.1,
        shadowRadius: 20,
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.1)',
    },
    joinHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 12,
        gap: 10
    },
    joinTitle: {
        fontSize: 20,
        fontFamily: 'NotoSans-Bold',
    },
    joinSub: {
        fontSize: 14,
        lineHeight: 20,
        marginBottom: 20,
        fontFamily: 'NotoSans-Regular',
    },
    inputWrapper: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: 'rgba(148,163,184,0.1)',
        borderRadius: 16,
        paddingHorizontal: 16,
        marginBottom: 20,
        height: 56,
        borderWidth: 1,
        borderColor: 'rgba(148,163,184,0.2)',
    },
    inputIcon: {
        marginRight: 12,
    },
    input: {
        fontSize: 18,
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 2,
        flex: 1
    },
    joinActions: {
        flexDirection: 'row',
        gap: 12,
    },
    joinBtn: {
        flex: 1,
        height: 50,
        borderRadius: 14,
        justifyContent: 'center',
        alignItems: 'center',
    },
    joinBtnText: {
        color: 'white',
        fontSize: 16,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    cancelBtn: {
        paddingHorizontal: 20,
        justifyContent: 'center',
        alignItems: 'center',
    },
    cancelBtnText: {
        color: '#64748b',
        fontSize: 14,
        fontWeight: '600',
    },
    joinBtnSmall: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 20,
        borderWidth: 1,
        gap: 4
    },
    joinBtnSmallText: {
        fontSize: 12,
        fontWeight: 'bold',
    },
    headerButtons: {
        flexDirection: 'row',
        gap: 8,
        alignItems: 'center',
    },
    chatBtnSmall: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingVertical: 8,
        borderRadius: 20,
        gap: 6,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.2,
        shadowRadius: 4,
    },
    chatBtnSmallText: {
        fontSize: 13,
        fontWeight: 'bold',
        color: '#FFF',
    },
    worksheetCard: {
        borderRadius: 28,
        padding: 22,
        marginBottom: 20,
        elevation: 10,
        shadowColor: '#8B5CF6',
        shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.12,
        shadowRadius: 20,
        borderWidth: 1,
        borderColor: 'rgba(139, 92, 246, 0.15)',
        position: 'relative',
        overflow: 'hidden',
    },
    worksheetCardAccent: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: 8,
    },
    worksheetHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 14,
    },
    worksheetIconContainer: {
        width: 56,
        height: 56,
        borderRadius: 28,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 16,
        elevation: 1,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 4,
    },
    worksheetTitleContainer: {
        flex: 1,
    },
    worksheetTitle: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        lineHeight: 22,
    },
    worksheetTypeRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 4,
    },
    worksheetTypeTag: {
        fontSize: 10,
        fontWeight: '900',
        letterSpacing: 0.8,
        textTransform: 'uppercase',
    },
    worksheetDate: {
        fontSize: 11,
        color: '#94a3b8',
        fontFamily: 'NotoSans-Regular',
    },
    worksheetBody: {
        marginTop: 4,
        gap: 14,
    },
    worksheetMetaRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    worksheetOpenButton: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 14,
        borderRadius: 20,
        elevation: 4,
        shadowColor: '#8B5CF6',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.25,
        shadowRadius: 10,
    },
    worksheetOpenButtonText: {
        color: 'white',
        fontSize: 15,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    tabBtnActive: {
        flex: 1,
        borderRadius: 12,
        overflow: 'hidden',
    },
    tabBtnGradient: {
        flex: 1,
        paddingVertical: 10,
        alignItems: 'center',
        justifyContent: 'center',
    },
    tabContainer: {
        flexDirection: 'row',
        padding: 4,
        borderRadius: 18,
        marginBottom: 16,
        borderWidth: 1,
        elevation: 6,
        shadowColor: '#8B5CF6',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.12,
        shadowRadius: 16,
    },
    updateCard: {
        borderRadius: 28,
        padding: 22,
        marginBottom: 20,
        elevation: 10,
        shadowColor: '#3B82F6',
        shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.12,
        shadowRadius: 20,
        borderWidth: 1,
        borderColor: 'rgba(59, 130, 246, 0.15)',
        position: 'relative',
        overflow: 'hidden',
    },
    updateCardAccent: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: 8,
    },
    updateHeaderTouch: {
        width: '100%',
    },
    divider: {
        height: 1,
        marginVertical: 12,
        opacity: 0.8,
    },
    expandedContent: {
        marginTop: 4,
    },
    expandedMessage: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
        lineHeight: 22,
        marginBottom: 16,
        paddingHorizontal: 2,
    },
    examCard: {
        borderRadius: 28,
        padding: 22,
        marginBottom: 20,
        elevation: 10,
        shadowColor: '#D97706',
        shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.12,
        shadowRadius: 20,
        borderWidth: 1,
        borderColor: 'rgba(217, 119, 6, 0.15)',
        position: 'relative',
        overflow: 'hidden',
    },
    examCardAccent: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: 8,
    },
    examHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 14,
    },
    examIconContainer: {
        width: 52,
        height: 52,
        borderRadius: 18,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 14,
        elevation: 1,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
    },
    examTitleContainer: {
        flex: 1,
    },
    examTitle: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        lineHeight: 22,
    },
    examTypeRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 4,
    },
    examTypeTag: {
        fontSize: 10,
        fontWeight: '900',
        letterSpacing: 0.8,
        textTransform: 'uppercase',
    },
    examDate: {
        fontSize: 11,
        color: '#94a3b8',
        fontFamily: 'NotoSans-Regular',
    },
    examBody: {
        marginTop: 4,
        gap: 14,
    },
    examMessage: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
        lineHeight: 20,
        marginBottom: 2,
    },
    examMetaRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    examStartButton: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 12,
        borderRadius: 16,
        elevation: 3,
        shadowColor: '#D97706',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 8,
    },
    examStartButtonText: {
        color: 'white',
        fontSize: 15,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    recordingCard: {
        borderRadius: 24,
        padding: 20,
        marginBottom: 16,
        elevation: 8,
        shadowColor: '#EF4444',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.1,
        shadowRadius: 16,
        borderWidth: 1,
        borderColor: 'rgba(239, 68, 68, 0.08)',
        position: 'relative',
        overflow: 'hidden',
    },
    recordingCardAccent: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: 6,
    },
    recordingHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 14,
    },
    recordingIconContainer: {
        width: 52,
        height: 52,
        borderRadius: 18,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 14,
        elevation: 1,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
    },
    recordingTitleContainer: {
        flex: 1,
    },
    recordingTitle: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        lineHeight: 22,
    },
    recordingTypeRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 4,
    },
    recordingTypeTag: {
        fontSize: 10,
        fontWeight: '900',
        letterSpacing: 0.8,
        textTransform: 'uppercase',
    },
    recordingDate: {
        fontSize: 11,
        color: '#94a3b8',
        fontFamily: 'NotoSans-Regular',
    },
    recordingBody: {
        marginTop: 4,
        gap: 14,
    },
    recordingMessage: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
        overflow: 'hidden',
    },
    updateCardAccent: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: 6,
    },
    updateHeaderTouch: {
        width: '100%',
    },
    divider: {
        height: 1,
        marginVertical: 12,
        opacity: 0.8,
    },
    expandedContent: {
        marginTop: 4,
    },
    expandedMessage: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
        lineHeight: 22,
        marginBottom: 16,
        paddingHorizontal: 2,
    },
    examCard: {
        borderRadius: 24,
        padding: 20,
        marginBottom: 16,
        elevation: 8,
        shadowColor: '#D97706',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.1,
        shadowRadius: 16,
        borderWidth: 1,
        borderColor: 'rgba(217, 119, 6, 0.08)',
        position: 'relative',
        overflow: 'hidden',
    },
    examCardAccent: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: 6,
    },
    examHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 14,
    },
    examIconContainer: {
        width: 52,
        height: 52,
        borderRadius: 18,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 14,
        elevation: 1,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
    },
    examTitleContainer: {
        flex: 1,
    },
    examTitle: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        lineHeight: 22,
    },
    examTypeRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 4,
    },
    examTypeTag: {
        fontSize: 10,
        fontWeight: '900',
        letterSpacing: 0.8,
        textTransform: 'uppercase',
    },
    examDate: {
        fontSize: 11,
        color: '#94a3b8',
        fontFamily: 'NotoSans-Regular',
    },
    examBody: {
        marginTop: 4,
        gap: 14,
    },
    examMessage: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
        lineHeight: 20,
        marginBottom: 2,
    },
    examMetaRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    examStartButton: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 12,
        borderRadius: 16,
        elevation: 3,
        shadowColor: '#D97706',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 8,
    },
    examStartButtonText: {
        color: 'white',
        fontSize: 15,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    recordingCard: {
        borderRadius: 24,
        padding: 20,
        marginBottom: 16,
        elevation: 8,
        shadowColor: '#EF4444',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.1,
        shadowRadius: 16,
        borderWidth: 1,
        borderColor: 'rgba(239, 68, 68, 0.08)',
        position: 'relative',
        overflow: 'hidden',
    },
    recordingCardAccent: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: 6,
    },
    recordingHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 14,
    },
    recordingIconContainer: {
        width: 52,
        height: 52,
        borderRadius: 18,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 14,
        elevation: 1,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
    },
    recordingTitleContainer: {
        flex: 1,
    },
    recordingTitle: {
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        lineHeight: 22,
    },
    recordingTypeRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 4,
    },
    recordingTypeTag: {
        fontSize: 10,
        fontWeight: '900',
        letterSpacing: 0.8,
        textTransform: 'uppercase',
    },
    recordingDate: {
        fontSize: 11,
        color: '#94a3b8',
        fontFamily: 'NotoSans-Regular',
    },
    recordingBody: {
        marginTop: 4,
        gap: 14,
    },
    recordingMessage: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
        lineHeight: 20,
        marginBottom: 2,
    },
    recordingMetaRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    recordingStartButton: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 12,
        borderRadius: 16,
        elevation: 3,
        shadowColor: '#EF4444',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 8,
    },
    recordingStartButtonText: {
        color: 'white',
        fontSize: 15,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    modalContainer: {
        flex: 1,
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        justifyContent: 'center',
        alignItems: 'center',
    },
    modalCloseButton: {
        position: 'absolute',
        top: Platform.OS === 'ios' ? 60 : 30,
        right: 20,
        zIndex: 10,
    },
    modalImageWrapper: {
        width: '90%',
        height: '80%',
        borderRadius: 24,
        overflow: 'hidden',
        backgroundColor: 'rgba(30, 41, 59, 0.5)',
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.1)',
        justifyContent: 'center',
        alignItems: 'center',
        elevation: 10,
        shadowColor: '#3B82F6',
        shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.3,
        shadowRadius: 20,
    },
    modalImage: {
        width: '100%',
        height: '100%',
    },
    modalLoaderContainer: {
        ...StyleSheet.absoluteFillObject,
        backgroundColor: 'rgba(15, 23, 42, 0.6)',
        justifyContent: 'center',
        alignItems: 'center',
    },
    modalLoadingText: {
        color: '#94A3B8',
        marginTop: 10,
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
    },
    modalErrorContainer: {
        ...StyleSheet.absoluteFillObject,
        backgroundColor: 'rgba(15, 23, 42, 0.9)',
        justifyContent: 'center',
        alignItems: 'center',
        padding: 20,
    },
    modalErrorText: {
        color: '#EF4444',
        fontSize: 18,
        fontWeight: 'bold',
        marginTop: 12,
        fontFamily: 'NotoSans-Bold',
    },
    modalErrorSubText: {
        color: '#94A3B8',
        fontSize: 14,
        textAlign: 'center',
        marginTop: 6,
        fontFamily: 'NotoSans-Regular',
    },
    modalDebugText: {
        color: 'rgba(148, 163, 184, 0.5)',
        fontSize: 11,
        textAlign: 'center',
        position: 'absolute',
        bottom: Platform.OS === 'ios' ? 40 : 20,
        paddingHorizontal: 20,
        fontFamily: 'NotoSans-Regular',
    }
});

export default ClassUpdatesScreen;
