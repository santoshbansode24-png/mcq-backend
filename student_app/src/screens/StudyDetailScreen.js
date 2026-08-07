import React, { useState, useEffect, useRef } from 'react';
import { 
    View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, 
    Alert, Animated, Modal 
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';
import { streamFetch } from '../api/streaming';
import { API_URL } from '../api/config';

const StudyDetailScreen = ({ route, navigation, user }) => {
    const job = route.params?.job;
    const [loading, setLoading] = useState(true);
    const [studyData, setStudyData] = useState(null);
    const [syncMsg, setSyncMsg] = useState('Checking local storage...');
    const [activeTab, setActiveTab] = useState('mcq'); // 'mcq' | 'flashcard' | 'notes'
    const [generatingMore, setGeneratingMore] = useState(false);
    const [generatingSpecific, setGeneratingSpecific] = useState(false);
    const [generatingType, setGeneratingType] = useState('');
    const [segmentIndex, setSegmentIndex] = useState(1);
    const [engineProgress, setEngineProgress] = useState('');
    
    // Animations
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const slideAnim = useRef(new Animated.Value(30)).current;

    useEffect(() => {
        if (!job) {
            navigation.goBack();
            return;
        }
        loadStudyData();
    }, [job]);

    useEffect(() => {
        if (!loading) {
            Animated.parallel([
                Animated.timing(fadeAnim, { toValue: 1, duration: 600, useNativeDriver: true }),
                Animated.spring(slideAnim, { toValue: 0, tension: 50, friction: 7, useNativeDriver: true })
            ]).start();
        }
    }, [loading]);

    const getCacheKey = () => `study_job_${job.job_id}`;

    const loadStudyData = async () => {
        try {
            const cacheKey = getCacheKey();
            const localRaw = await AsyncStorage.getItem(cacheKey);
            
            if (localRaw) {
                setStudyData(JSON.parse(localRaw));
                setLoading(false);
                return;
            }

            setSyncMsg('Downloading secure study pack...');
            const formData = new FormData();
            formData.append('user_id', user?.user_id?.toString() || '0');
            formData.append('job_id', job.job_id.toString());
            formData.append('action', 'fetch');

            const res = await axios.post(`${API_URL}/sync_pdf_study_content.php`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            if (res.data.status === 'success' && res.data.study_pack) {
                const pack = res.data.study_pack;
                
                setSyncMsg('Preparing your study material...');
                await AsyncStorage.setItem(cacheKey, JSON.stringify(pack));
                setStudyData(pack);
                
                setSyncMsg('Almost ready! Please wait a moment...');
                const ackData = new FormData();
                ackData.append('user_id', user?.user_id?.toString() || '0');
                ackData.append('job_id', job.job_id.toString());
                ackData.append('action', 'acknowledge');
                await axios.post(`${API_URL}/sync_pdf_study_content.php`, ackData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                
                setLoading(false);
            } else {
                throw new Error("Invalid response or not ready.");
            }
        } catch (e) {
            Alert.alert("Error", e.response?.data?.message || "Failed to load study material. It may still be processing.");
            navigation.goBack();
        }
    };

    const deleteLocalData = async () => {
        Alert.alert(
            "Delete Study Pack?",
            "This will remove the data from your phone. You will need to upload the PDF again to get it back.",
            [
                { text: "Cancel", style: "cancel" },
                { 
                    text: "Delete", 
                    style: "destructive",
                    onPress: async () => {
                        await AsyncStorage.removeItem(getCacheKey());
                        navigation.goBack();
                    }
                }
            ]
        );
    };

    const refreshLocalData = async () => {
        Alert.alert(
            "Refresh Data?",
            "This will clear the cached data and download fresh content from the server.",
            [
                { text: "Cancel", style: "cancel" },
                {
                    text: "Refresh",
                    onPress: async () => {
                        await AsyncStorage.removeItem(getCacheKey());
                        setLoading(true);
                        setStudyData(null);
                        setSyncMsg('Re-downloading study pack...');
                        loadStudyData();
                    }
                }
            ]
        );
    };

    const getNotesObject = () => {
        if (!studyData) return null;
        return studyData.notes || studyData.Notes || studyData.smart_notes || studyData.SmartNotes || null;
    };

    const getCounts = () => {
        if (!studyData) return { mcqs: 0, flashcards: 0, notes: 0 };
        
        const n = getNotesObject();
        let hasNotes = false;
        
        if (n) {
            if (Array.isArray(n)) {
                 hasNotes = n.length > 0;
            } else if (typeof n === 'object') {
                 Object.values(n).forEach(val => {
                     if (Array.isArray(val) && val.length > 0) hasNotes = true;
                     else if (typeof val === 'string' && val.trim().length > 0) hasNotes = true;
                 });
            } else if (typeof n === 'string') {
                 hasNotes = n.trim().length > 0;
            }
        }
             
        const mcqCount = (studyData.mcqs || studyData.MCQs || []).length;
        const flashcardCount = (studyData.flashcards || studyData.Flashcards || studyData.FlashCards || []).length;

        return { 
            mcqs: mcqCount, 
            flashcards: flashcardCount,
            notes: hasNotes ? 1 : 0
        };
    };

    const handleGenerateSpecific = async (type) => {
        if (generatingSpecific) return;
        setGeneratingSpecific(true);
        setGeneratingType(type);

        try {
            const formData = new FormData();
            formData.append('job_id', job.job_id.toString());
            formData.append('type', type);

            const res = await axios.post(`${API_URL}/generate_specific_type.php`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            if (res.data.status === 'success' && res.data.data) {
                const updated = res.data.data;
                setStudyData(updated);
                await AsyncStorage.setItem(getCacheKey(), JSON.stringify(updated));
                Alert.alert("Success 🎉", res.data.message || `Generated new ${type}!`);
            } else {
                throw new Error(res.data.message || "Failed to generate content.");
            }
        } catch (e) {
            Alert.alert("Generation Error", e.response?.data?.message || e.message || "Could not generate content.");
        } finally {
            setGeneratingSpecific(false);
            setGeneratingType('');
        }
    };

    const generateMore = async () => {
        if (generatingMore) return;
        setGeneratingMore(true);
        setEngineProgress('Initializing Engine...');

        const nextSegment = segmentIndex + 1;
        const url = `${API_URL}/ai_pdf_engine.php?job_id=${job.job_id}&user_id=${user?.user_id || 0}&segment_index=${nextSegment}&language=English`;

        streamFetch(
            url,
            { method: 'GET' },
            (chunk) => {
                if (chunk.status === 'progress') {
                    setEngineProgress(chunk.message || 'Processing...');
                } else if (chunk.status === 'success') {
                    const newData = chunk.data;
                    setStudyData(prev => {
                        const updated = { ...prev };

                        const existingMcqSet = new Set((updated.mcqs || []).map(m => (m.q || m.question || '').trim().toLowerCase()));
                        const newMcqs = (newData.mcqs || []).filter(n => {
                            const key = (n.q || n.question || '').trim().toLowerCase();
                            if (key && !existingMcqSet.has(key)) { existingMcqSet.add(key); return true; }
                            return false;
                        });
                        updated.mcqs = [...(updated.mcqs || []), ...newMcqs];

                        const existingCardSet = new Set((updated.flashcards || []).map(f => (f.q || f.question || '').trim().toLowerCase()));
                        const newCards = (newData.flashcards || []).filter(n => {
                            const key = (n.q || n.question || '').trim().toLowerCase();
                            if (key && !existingCardSet.has(key)) { existingCardSet.add(key); return true; }
                            return false;
                        });
                        updated.flashcards = [...(updated.flashcards || []), ...newCards];

                        const incomingNotes = newData.notes || newData.Notes || newData.smart_notes || newData.SmartNotes;
                        if (incomingNotes) {
                            if (!updated.notes || typeof updated.notes !== 'object' || Array.isArray(updated.notes)) {
                                updated.notes = { definitions: [], key_facts: [], core_concepts: [] };
                            }
                            const newNotesObj = Array.isArray(incomingNotes)
                                ? { definitions: incomingNotes }
                                : incomingNotes;
                            const mergeNotes = (existing, incoming) => {
                                const safeExisting = Array.isArray(existing) ? existing : [];
                                const safeIncoming = Array.isArray(incoming) ? incoming : [];
                                const seen = new Set(safeExisting.map(s => (typeof s === 'string' ? s : JSON.stringify(s)).trim().toLowerCase()));
                                return [...safeExisting, ...safeIncoming.filter(i => {
                                    if (!i) return false;
                                    const val = (typeof i === 'string' ? i : JSON.stringify(i)).trim().toLowerCase();
                                    if (seen.has(val)) return false;
                                    seen.add(val);
                                    return true;
                                })];
                            };
                            updated.notes.definitions  = mergeNotes(updated.notes.definitions  || [], newNotesObj.definitions  || newNotesObj.Definitions || []);
                            updated.notes.key_facts    = mergeNotes(updated.notes.key_facts    || [], newNotesObj.key_facts    || newNotesObj.keyFacts || newNotesObj.Key_facts || []);
                            updated.notes.core_concepts= mergeNotes(updated.notes.core_concepts|| [], newNotesObj.core_concepts|| newNotesObj.coreConcepts || newNotesObj.Core_concepts || []);
                        }

                        AsyncStorage.setItem(getCacheKey(), JSON.stringify(updated));
                        return updated;
                    });
                }
            },
            () => {
                setGeneratingMore(false);
                setSegmentIndex(nextSegment);
            },
            (err) => {
                setGeneratingMore(false);
                Alert.alert('Engine Error', err?.message || 'Failed to generate more content.');
            }
        );
    };

    const renderSets = (mode, colors) => {
        const items = mode === 'quiz' ? (studyData?.mcqs || []) : (studyData?.flashcards || []);
        const total = items.length;
        if (total === 0) return null;
        
        const numSets = Math.ceil(total / 10);
        return Array.from({length: numSets}, (_, i) => i).map((setIndex) => {
            const start = setIndex * 10 + 1;
            const end = Math.min((setIndex + 1) * 10, total);
            return (
                <TouchableOpacity 
                    key={setIndex}
                    style={styles.setRow}
                    activeOpacity={0.7}
                    onPress={() => startStudy(mode, setIndex)}
                >
                    <View style={styles.setRowLeft}>
                        <LinearGradient colors={colors} style={styles.setBadge}>
                            <Text style={styles.setBadgeText}>{setIndex + 1}</Text>
                        </LinearGradient>
                        <View>
                            <Text style={styles.setRowTitle}>Study Set {setIndex + 1}</Text>
                            <Text style={styles.setRowSub}>Items {start} to {end} • Tap to start</Text>
                        </View>
                    </View>
                    <MaterialCommunityIcons name="chevron-right-circle" size={26} color={colors[1]} />
                </TouchableOpacity>
            );
        });
    };

    const startStudy = (mode, setIndex = 0) => {
        if (!studyData) return;
        
        if (mode === 'quiz') {
            const allMcqs = (studyData.mcqs || []).map((m, i) => ({
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
            navigation.navigate('MyExamTest', { questions: subset, subjectName: `${job.file_name}`, isAI: true });
        } else {
            const allCards = (studyData.flashcards || []).map((f, i) => ({
                flashcard_id: i,
                question_front: f.front || f.f || f.q || f.question || '',
                answer_back: f.back || f.b || f.a || f.answer || '',
                subject: 'AI Vault',
                topic: job.file_name
            }));
            const subset = allCards.slice(setIndex * 10, (setIndex + 1) * 10);
            navigation.navigate('Flashcards', { flashcardsData: subset, chapterId: `ai_${job.job_id}`, chapterName: `${job.file_name}`, isAI: true });
        }
    };

    return (
        <LinearGradient colors={['#0f172a', '#020617']} style={styles.container}>
            {/* GENERATING LOADING MODAL */}
            <Modal visible={generatingSpecific} transparent animationType="fade">
                <View style={styles.modalBackdrop}>
                    <View style={styles.modalCard}>
                        <LinearGradient colors={['#1e293b', '#0f172a']} style={styles.modalGradient}>
                            <View style={styles.modalGlowRing}>
                                <ActivityIndicator size="large" color="#38bdf8" />
                            </View>
                            <Text style={styles.modalTitle}>Veeru AI Engine</Text>
                            <Text style={styles.modalSub}>
                                Generating new <Text style={{ color: '#38bdf8', fontWeight: 'bold' }}>{generatingType}</Text> from {job?.file_name}...
                            </Text>
                            <Text style={styles.modalHint}>Derived 100% strictly from your document text.</Text>
                        </LinearGradient>
                    </View>
                </View>
            </Modal>

            <SafeAreaView edges={['top']} style={styles.header}>
                <TouchableOpacity style={styles.backBtn} onPress={() => navigation.goBack()}>
                    <View style={styles.glassBtn}>
                        <MaterialCommunityIcons name="arrow-left" size={24} color="white" />
                    </View>
                </TouchableOpacity>
                <View style={styles.titleContainer}>
                    <Text style={styles.title} numberOfLines={1}>{job?.file_name}</Text>
                    <Text style={styles.subtitle}>Secure Local Study Pack</Text>
                </View>
                {!loading && (
                    <View style={{ flexDirection: 'row', gap: 8 }}>
                        <TouchableOpacity style={styles.backBtn} onPress={refreshLocalData}>
                            <View style={[styles.glassBtn, { backgroundColor: '#38bdf820' }]}>
                                <MaterialCommunityIcons name="refresh" size={22} color="#38bdf8" />
                            </View>
                        </TouchableOpacity>
                        <TouchableOpacity style={styles.backBtn} onPress={deleteLocalData}>
                            <View style={[styles.glassBtn, { backgroundColor: '#ef444420' }]}>
                                <MaterialCommunityIcons name="trash-can-outline" size={22} color="#fdba74" />
                            </View>
                        </TouchableOpacity>
                    </View>
                )}
            </SafeAreaView>

            {loading ? (
                <View style={styles.loadingCenter}>
                    <View style={styles.glowRing}>
                        <ActivityIndicator size="large" color="#38bdf8" />
                    </View>
                    <Text style={styles.loadingText}>{syncMsg}</Text>
                    <Text style={styles.loadingSubtext}>Please don't close the app</Text>
                </View>
            ) : (
                <Animated.ScrollView 
                    contentContainerStyle={styles.body}
                    showsVerticalScrollIndicator={false}
                    style={{ opacity: fadeAnim, transform: [{ translateY: slideAnim }] }}
                >
                    {/* STATS HEADER */}
                    <View style={styles.heroStats}>
                        <View style={styles.statPill}>
                            <MaterialCommunityIcons name="file-document-outline" size={20} color="#94a3b8" />
                            <Text style={styles.statPillText}>{job?.total_pages || 0} Pages</Text>
                        </View>
                        <View style={[styles.statPill, { backgroundColor: '#38bdf820', borderColor: '#38bdf840' }]}>
                            <MaterialCommunityIcons name="clock-check-outline" size={20} color="#38bdf8" />
                            <Text style={[styles.statPillText, { color: '#38bdf8' }]}>Fully Synced</Text>
                        </View>
                    </View>

                    {/* INTERACTIVE CATEGORY TAB SELECTOR */}
                    <View style={styles.tabContainer}>
                        <TouchableOpacity 
                            style={[styles.tabBtn, activeTab === 'mcq' && styles.activeTabBtn]} 
                            activeOpacity={0.8}
                            onPress={() => setActiveTab('mcq')}
                        >
                            <LinearGradient 
                                colors={activeTab === 'mcq' ? ['#38bdf8', '#0284c7'] : ['#1e293b', '#0f172a']} 
                                style={styles.tabGradient}
                            >
                                <MaterialCommunityIcons name="format-list-checks" size={18} color="white" />
                                <Text style={styles.tabText}>MCQ Quiz ({getCounts().mcqs})</Text>
                            </LinearGradient>
                        </TouchableOpacity>

                        <TouchableOpacity 
                            style={[styles.tabBtn, activeTab === 'flashcard' && styles.activeTabBtn]} 
                            activeOpacity={0.8}
                            onPress={() => setActiveTab('flashcard')}
                        >
                            <LinearGradient 
                                colors={activeTab === 'flashcard' ? ['#a855f7', '#7e22ce'] : ['#1e293b', '#0f172a']} 
                                style={styles.tabGradient}
                            >
                                <MaterialCommunityIcons name="cards-outline" size={18} color="white" />
                                <Text style={styles.tabText}>Flashcards ({getCounts().flashcards})</Text>
                            </LinearGradient>
                        </TouchableOpacity>

                        <TouchableOpacity 
                            style={[styles.tabBtn, activeTab === 'notes' && styles.activeTabBtn]} 
                            activeOpacity={0.8}
                            onPress={() => setActiveTab('notes')}
                        >
                            <LinearGradient 
                                colors={activeTab === 'notes' ? ['#f59e0b', '#d97706'] : ['#1e293b', '#0f172a']} 
                                style={styles.tabGradient}
                            >
                                <MaterialCommunityIcons name="file-pdf-box" size={18} color="white" />
                                <Text style={styles.tabText}>PDF Notes</Text>
                            </LinearGradient>
                        </TouchableOpacity>
                    </View>

                    {/* TAB CONTENT: MCQ QUIZZES */}
                    {activeTab === 'mcq' && (
                        <View style={styles.glassCard}>
                            <LinearGradient colors={['#38bdf815', '#0ea5e905']} style={styles.cardHeader}>
                                <View style={styles.cardHeaderLeft}>
                                    <View style={[styles.iconBox, { backgroundColor: '#0ea5e920' }]}>
                                        <MaterialCommunityIcons name="format-list-checks" size={24} color="#38bdf8" />
                                    </View>
                                    <View>
                                        <Text style={styles.cardTitle}>MCQ Quizzes</Text>
                                        <Text style={styles.cardSubtitle}>{getCounts().mcqs} Questions Available</Text>
                                    </View>
                                </View>
                            </LinearGradient>

                            <View style={styles.setsWrap}>
                                {getCounts().mcqs > 0 ? (
                                    renderSets('quiz', ['#38bdf8', '#0284c7'])
                                ) : (
                                    <Text style={styles.emptyText}>No MCQ Quizzes generated yet.</Text>
                                )}

                                {/* ON-DEMAND GENERATE MCQS BUTTON */}
                                <TouchableOpacity 
                                    style={[styles.generateMoreCardBtn, { marginTop: 12 }]} 
                                    activeOpacity={0.85}
                                    onPress={() => handleGenerateSpecific('mcqs')}
                                >
                                    <LinearGradient colors={['#38bdf8', '#0284c7']} style={styles.generateMoreGradient}>
                                        <MaterialCommunityIcons name="plus-circle" size={20} color="white" />
                                        <Text style={styles.generateMoreText}>⚡ Generate +10 More MCQs</Text>
                                    </LinearGradient>
                                </TouchableOpacity>
                            </View>
                        </View>
                    )}

                    {/* TAB CONTENT: FLASHCARDS */}
                    {activeTab === 'flashcard' && (
                        <View style={styles.glassCard}>
                            <LinearGradient colors={['#a855f715', '#7e22ce05']} style={styles.cardHeader}>
                                <View style={styles.cardHeaderLeft}>
                                    <View style={[styles.iconBox, { backgroundColor: '#a855f720' }]}>
                                        <MaterialCommunityIcons name="cards-outline" size={24} color="#c084fc" />
                                    </View>
                                    <View>
                                        <Text style={styles.cardTitle}>Flashcards</Text>
                                        <Text style={styles.cardSubtitle}>{getCounts().flashcards} Flashcards Available</Text>
                                    </View>
                                </View>
                            </LinearGradient>

                            <View style={styles.setsWrap}>
                                {getCounts().flashcards > 0 ? (
                                    renderSets('flash', ['#c084fc', '#7e22ce'])
                                ) : (
                                    <Text style={styles.emptyText}>No Flashcards generated yet.</Text>
                                )}

                                {/* ON-DEMAND GENERATE FLASHCARDS BUTTON */}
                                <TouchableOpacity 
                                    style={[styles.generateMoreCardBtn, { marginTop: 12 }]} 
                                    activeOpacity={0.85}
                                    onPress={() => handleGenerateSpecific('flashcards')}
                                >
                                    <LinearGradient colors={['#a855f7', '#7e22ce']} style={styles.generateMoreGradient}>
                                        <MaterialCommunityIcons name="plus-circle" size={20} color="white" />
                                        <Text style={styles.generateMoreText}>⚡ Generate +10 More Flashcards</Text>
                                    </LinearGradient>
                                </TouchableOpacity>
                            </View>
                        </View>
                    )}

                    {/* TAB CONTENT: PDF NOTES */}
                    {activeTab === 'notes' && (
                        <View style={styles.glassCard}>
                            <LinearGradient colors={['#f59e0b15', '#d9770605']} style={styles.cardHeader}>
                                <View style={styles.cardHeaderLeft}>
                                    <View style={[styles.iconBox, { backgroundColor: '#f59e0b20' }]}>
                                        <MaterialCommunityIcons name="lightning-bolt-circle" size={26} color="#fbbf24" />
                                    </View>
                                    <View>
                                        <Text style={styles.cardTitle}>Smart PDF Revision Notes</Text>
                                        <Text style={styles.cardSubtitle}>Key definitions, facts & core concepts</Text>
                                    </View>
                                </View>
                            </LinearGradient>

                            <View style={styles.setsWrap}>
                                {getCounts().notes > 0 ? (
                                    <TouchableOpacity 
                                        style={styles.setRow} 
                                        activeOpacity={0.7}
                                        onPress={() => navigation.navigate('AIPdfNotes', { notes: getNotesObject(), subjectName: job.file_name, jobId: job.job_id })}
                                    >
                                        <View style={styles.setRowLeft}>
                                            <LinearGradient colors={['#f59e0b', '#d97706']} style={styles.setBadge}>
                                                <MaterialCommunityIcons name="book-open-page-variant" size={20} color="white" />
                                            </LinearGradient>
                                            <View>
                                                <Text style={styles.setRowTitle}>View Revision Notes</Text>
                                                <Text style={styles.setRowSub}>Tap to read or download as PDF</Text>
                                            </View>
                                        </View>
                                        <MaterialCommunityIcons name="chevron-right-circle" size={26} color="#fbbf24" />
                                    </TouchableOpacity>
                                ) : (
                                    <Text style={styles.emptyText}>No Smart Notes generated yet.</Text>
                                )}

                                <View style={{ flexDirection: 'row', gap: 10, marginTop: 12 }}>
                                    <TouchableOpacity 
                                        style={[styles.generateMoreCardBtn, { flex: 1 }]} 
                                        activeOpacity={0.85}
                                        onPress={() => handleGenerateSpecific('notes')}
                                    >
                                        <LinearGradient colors={['#f59e0b', '#d97706']} style={styles.generateMoreGradient}>
                                            <MaterialCommunityIcons name="sparkles" size={18} color="white" />
                                            <Text style={styles.generateMoreText}>✨ Expand Notes</Text>
                                        </LinearGradient>
                                    </TouchableOpacity>

                                    <TouchableOpacity 
                                        style={[styles.generateMoreCardBtn, { flex: 1 }]} 
                                        activeOpacity={0.85}
                                        onPress={() => navigation.navigate('AIPdfNotes', { notes: getNotesObject(), subjectName: job.file_name, jobId: job.job_id })}
                                    >
                                        <LinearGradient colors={['#10b981', '#059669']} style={styles.generateMoreGradient}>
                                            <MaterialCommunityIcons name="download" size={18} color="white" />
                                            <Text style={styles.generateMoreText}>📥 PDF Notes</Text>
                                        </LinearGradient>
                                    </TouchableOpacity>
                                </View>
                            </View>
                        </View>
                    )}

                    {/* SCAN NEXT SECTION (FULL PDF DEEP SCAN) */}
                    <View style={styles.engineContainer}>
                        {generatingMore ? (
                            <View style={styles.engineLoading}>
                                <ActivityIndicator size="small" color="#38bdf8" />
                                <Text style={styles.engineLoadingText}>{engineProgress}</Text>
                            </View>
                        ) : (
                            <TouchableOpacity style={styles.engineBtn} onPress={generateMore}>
                                <LinearGradient colors={['#38bdf820', '#1e293b']} style={styles.engineGradient}>
                                    <MaterialCommunityIcons name="auto-fix" size={20} color="#38bdf8" />
                                    <Text style={styles.engineBtnText}>Scan Next PDF Section</Text>
                                    <View style={styles.segmentBadge}>
                                        <Text style={styles.segmentText}>Part {segmentIndex + 1}</Text>
                                    </View>
                                </LinearGradient>
                            </TouchableOpacity>
                        )}
                        <Text style={styles.engineHint}>
                            Deep Scan: Reads the next chapter to extract all MCQs, Flashcards & Notes.
                        </Text>
                    </View>

                </Animated.ScrollView>
            )}
        </LinearGradient>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    header: { 
        flexDirection: 'row', alignItems: 'center', 
        paddingHorizontal: 15, paddingVertical: 15,
        backgroundColor: '#0f172a', borderBottomWidth: 1, borderColor: '#1e293b'
    },
    backBtn: { zIndex: 10 },
    glassBtn: { 
        width: 40, height: 40, borderRadius: 20, 
        backgroundColor: '#1e293b80', borderWidth: 1, borderColor: '#334155',
        justifyContent: 'center', alignItems: 'center'
    },
    titleContainer: { flex: 1, marginLeft: 15 },
    title: { color: 'white', fontSize: 18, fontWeight: '800', letterSpacing: 0.5 },
    subtitle: { color: '#10b981', fontSize: 12, fontWeight: '600', marginTop: 2 },
    loadingCenter: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    glowRing: { 
        padding: 20, borderRadius: 50, backgroundColor: '#38bdf815', 
        borderWidth: 1, borderColor: '#38bdf830', marginBottom: 20 
    },
    loadingText: { color: '#e2e8f0', fontSize: 16, fontWeight: '700' },
    loadingSubtext: { color: '#64748b', fontSize: 13, marginTop: 5 },
    body: { padding: 15, paddingBottom: 50 },
    heroStats: { flexDirection: 'row', marginBottom: 15, gap: 10 },
    statPill: { 
        flexDirection: 'row', alignItems: 'center', backgroundColor: '#1e293b', 
        paddingHorizontal: 12, paddingVertical: 8, borderRadius: 20, 
        borderWidth: 1, borderColor: '#334155' 
    },
    statPillText: { color: '#cbd5e1', fontSize: 13, fontWeight: '600', marginLeft: 6 },
    tabContainer: { flexDirection: 'row', gap: 6, marginBottom: 20 },
    tabBtn: { flex: 1, borderRadius: 12, overflow: 'hidden', borderWidth: 1, borderColor: '#334155' },
    activeTabBtn: { borderColor: '#38bdf8', elevation: 4 },
    tabGradient: { paddingVertical: 12, paddingHorizontal: 6, alignItems: 'center', justifyContent: 'center', flexDirection: 'row', gap: 4 },
    tabText: { color: 'white', fontSize: 12, fontWeight: '800' },
    glassCard: { 
        backgroundColor: '#1e293b60', borderRadius: 20, 
        borderWidth: 1, borderColor: '#334155', overflow: 'hidden' 
    },
    cardHeader: { padding: 16, borderBottomWidth: 1, borderColor: '#33415560' },
    cardHeaderLeft: { flexDirection: 'row', alignItems: 'center' },
    iconBox: { width: 42, height: 42, borderRadius: 14, justifyContent: 'center', alignItems: 'center', marginRight: 12 },
    cardTitle: { color: 'white', fontSize: 16, fontWeight: 'bold' },
    cardSubtitle: { color: '#94a3b8', fontSize: 12, marginTop: 2, fontWeight: '500' },
    setsWrap: { padding: 12 },
    emptyText: { color: '#64748b', fontSize: 13, textAlign: 'center', marginVertical: 15 },
    setRow: { 
        flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', 
        padding: 12, borderRadius: 14, backgroundColor: '#0f172a60', marginBottom: 8 
    },
    setRowLeft: { flexDirection: 'row', alignItems: 'center' },
    setBadge: { 
        width: 38, height: 38, borderRadius: 10, 
        alignItems: 'center', justifyContent: 'center', marginRight: 12
    },
    setBadgeText: { color: 'white', fontWeight: '900', fontSize: 15 },
    setRowTitle: { color: '#e2e8f0', fontSize: 15, fontWeight: '700' },
    setRowSub: { color: '#64748b', fontSize: 12, marginTop: 2 },
    generateMoreCardBtn: { borderRadius: 12, overflow: 'hidden' },
    generateMoreGradient: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 12, paddingHorizontal: 12, gap: 6 },
    generateMoreText: { color: 'white', fontSize: 13, fontWeight: '800' },
    engineContainer: { marginTop: 30, alignItems: 'center' },
    engineBtn: { borderRadius: 18, overflow: 'hidden', borderWidth: 1, borderColor: '#38bdf840', width: '100%' },
    engineGradient: { flexDirection: 'row', alignItems: 'center', padding: 16, justifyContent: 'center' },
    engineBtnText: { color: '#38bdf8', fontWeight: 'bold', fontSize: 15, marginLeft: 8 },
    engineLoading: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#1e293b80', padding: 16, borderRadius: 18, width: '100%', justifyContent: 'center' },
    engineLoadingText: { color: '#38bdf8', fontSize: 14, fontWeight: '600', marginLeft: 10 },
    engineHint: { color: '#475569', fontSize: 12, marginTop: 10, textAlign: 'center' },
    segmentBadge: { backgroundColor: '#38bdf8', paddingHorizontal: 8, paddingVertical: 2, borderRadius: 6, marginLeft: 8 },
    segmentText: { color: '#0f172a', fontSize: 10, fontWeight: 'bold' },
    modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.75)', justifyContent: 'center', alignItems: 'center', padding: 20 },
    modalCard: { width: '90%', borderRadius: 24, overflow: 'hidden', borderWidth: 1, borderColor: '#334155' },
    modalGradient: { padding: 30, alignItems: 'center' },
    modalGlowRing: { padding: 16, borderRadius: 40, backgroundColor: '#38bdf815', borderWidth: 1, borderColor: '#38bdf830', marginBottom: 15 },
    modalTitle: { color: 'white', fontSize: 18, fontWeight: 'bold' },
    modalSub: { color: '#cbd5e1', fontSize: 14, marginTop: 8, textAlign: 'center', lineHeight: 20 },
    modalHint: { color: '#64748b', fontSize: 12, marginTop: 12, textAlign: 'center' }
});

export default StudyDetailScreen;
