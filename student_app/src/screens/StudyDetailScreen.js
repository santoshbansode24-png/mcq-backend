import React, { useState, useEffect, useRef } from 'react';
import { 
    View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, 
    Alert, ScrollView, Animated, Dimensions 
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';
import { API_URL } from '../api/config';

const { width } = Dimensions.get('window');

const StudyDetailScreen = ({ route, navigation, user }) => {
    const job = route.params?.job;
    const [loading, setLoading] = useState(true);
    const [studyData, setStudyData] = useState(null);
    const [syncMsg, setSyncMsg] = useState('Checking local storage...');
    
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

    return (
        <LinearGradient colors={['#0f172a', '#020617']} style={styles.container}>
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
                {/* Delete Button */}
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

                    {/* SMART NOTES SECTION */}
                    {getCounts().notes > 0 && (
                        <TouchableOpacity 
                            style={[styles.glassCard, { marginBottom: 20 }]} 
                            activeOpacity={0.8}
                            onPress={() => navigation.navigate('AIPdfNotes', { notes: getNotesObject(), subjectName: job.file_name })}
                        >
                            <LinearGradient colors={['#f59e0b15', '#d9770605']} style={styles.cardHeader}>
                                <View style={styles.cardHeaderLeft}>
                                    <View style={[styles.iconBox, { backgroundColor: '#f59e0b20' }]}>
                                        <MaterialCommunityIcons name="lightning-bolt-circle" size={26} color="#fbbf24" />
                                    </View>
                                    <View>
                                        <Text style={styles.cardTitle}>Smart Notes</Text>
                                        <Text style={styles.cardSubtitle}>Tap to read key summaries</Text>
                                    </View>
                                </View>
                                <MaterialCommunityIcons name="chevron-right-circle" size={26} color="#fbbf24" style={{marginLeft: 'auto'}} />
                            </LinearGradient>
                        </TouchableOpacity>
                    )}

                    {/* MCQ SECTION */}
                    {getCounts().mcqs > 0 && (
                        <View style={styles.glassCard}>
                            <LinearGradient colors={['#38bdf815', '#0ea5e905']} style={styles.cardHeader}>
                                <View style={styles.cardHeaderLeft}>
                                    <View style={[styles.iconBox, { backgroundColor: '#0ea5e920' }]}>
                                        <MaterialCommunityIcons name="format-list-checks" size={24} color="#38bdf8" />
                                    </View>
                                    <View>
                                        <Text style={styles.cardTitle}>MCQ Quizzes</Text>
                                        <Text style={styles.cardSubtitle}>{getCounts().mcqs} Expert Questions</Text>
                                    </View>
                                </View>
                            </LinearGradient>
                            <View style={styles.setsWrap}>
                                {renderSets('quiz', ['#38bdf8', '#0284c7'])}
                            </View>
                        </View>
                    )}

                    {/* FLASHCARD SECTION */}
                    {getCounts().flashcards > 0 && (
                        <View style={[styles.glassCard, { marginTop: 20 }]}>
                            <LinearGradient colors={['#a855f715', '#7e22ce05']} style={styles.cardHeader}>
                                <View style={styles.cardHeaderLeft}>
                                    <View style={[styles.iconBox, { backgroundColor: '#a855f720' }]}>
                                        <MaterialCommunityIcons name="cards-outline" size={24} color="#c084fc" />
                                    </View>
                                    <View>
                                        <Text style={styles.cardTitle}>Flashcards</Text>
                                        <Text style={styles.cardSubtitle}>{getCounts().flashcards} Core Concepts</Text>
                                    </View>
                                </View>
                            </LinearGradient>
                            <View style={styles.setsWrap}>
                                {renderSets('flash', ['#c084fc', '#7e22ce'])}
                            </View>
                        </View>
                    )}

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
    body: { padding: 20, paddingBottom: 50 },
    heroStats: { flexDirection: 'row', marginBottom: 25, gap: 10 },
    statPill: { 
        flexDirection: 'row', alignItems: 'center', backgroundColor: '#1e293b', 
        paddingHorizontal: 12, paddingVertical: 8, borderRadius: 20, 
        borderWidth: 1, borderColor: '#334155' 
    },
    statPillText: { color: '#cbd5e1', fontSize: 13, fontWeight: '600', marginLeft: 6 },
    glassCard: { 
        backgroundColor: '#1e293b60', borderRadius: 24, 
        borderWidth: 1, borderColor: '#334155', overflow: 'hidden' 
    },
    cardHeader: { padding: 20, borderBottomWidth: 1, borderColor: '#33415560' },
    cardHeaderLeft: { flexDirection: 'row', alignItems: 'center' },
    iconBox: { width: 48, height: 48, borderRadius: 16, justifyContent: 'center', alignItems: 'center', marginRight: 15 },
    cardTitle: { color: 'white', fontSize: 18, fontWeight: 'bold' },
    cardSubtitle: { color: '#94a3b8', fontSize: 13, marginTop: 2, fontWeight: '500' },
    setsWrap: { padding: 10 },
    setRow: { 
        flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', 
        padding: 12, borderRadius: 16, backgroundColor: '#0f172a50', marginBottom: 8 
    },
    setRowLeft: { flexDirection: 'row', alignItems: 'center' },
    setBadge: { 
        width: 40, height: 40, borderRadius: 12, 
        alignItems: 'center', justifyContent: 'center', marginRight: 15,
        shadowColor: '#000', shadowOffset: {width: 0, height: 2}, shadowOpacity: 0.2, shadowRadius: 3
    },
    setBadgeText: { color: 'white', fontWeight: '900', fontSize: 16 },
    setRowTitle: { color: '#e2e8f0', fontSize: 16, fontWeight: '700' },
    setRowSub: { color: '#64748b', fontSize: 12, marginTop: 3 }
});

export default StudyDetailScreen;
