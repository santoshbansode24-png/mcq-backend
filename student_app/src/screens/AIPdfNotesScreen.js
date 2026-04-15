import React, { useRef, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Animated } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';

const NotesSection = ({ title, icon, color, items, delay = 0 }) => {
    const slideAnim = useRef(new Animated.Value(30)).current;
    const fadeAnim = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        Animated.sequence([
            Animated.delay(delay),
            Animated.parallel([
                Animated.timing(fadeAnim, { toValue: 1, duration: 500, useNativeDriver: true }),
                Animated.spring(slideAnim, { toValue: 0, tension: 50, friction: 7, useNativeDriver: true })
            ])
        ]).start();
    }, []);

    if (!items || items.length === 0) return null;

    return (
        <Animated.View style={[styles.sectionContainer, { opacity: fadeAnim, transform: [{ translateY: slideAnim }] }]}>
            <View style={styles.sectionHeader}>
                <View style={[styles.iconBox, { backgroundColor: color + '20' }]}>
                    <MaterialCommunityIcons name={icon} size={24} color={color} />
                </View>
                <Text style={styles.sectionTitle}>{title}</Text>
            </View>
            <View style={styles.card}>
                {items.map((item, index) => (
                    <View key={index} style={styles.bulletRow}>
                        <View style={[styles.bulletDot, { backgroundColor: color }]} />
                        <Text style={styles.bulletText}>{item}</Text>
                    </View>
                ))}
            </View>
        </Animated.View>
    );
};

const AIPdfNotesScreen = ({ route, navigation }) => {
    const { notes, subjectName } = route.params || {};

    if (!notes) {
        return (
            <LinearGradient colors={['#0f172a', '#020617']} style={styles.container}>
                <SafeAreaView edges={['top']} style={styles.header}>
                    <TouchableOpacity style={styles.backBtn} onPress={() => navigation.goBack()}>
                        <MaterialCommunityIcons name="arrow-left" size={28} color="white" />
                    </TouchableOpacity>
                    <Text style={styles.headerTitle}>Smart Notes</Text>
                </SafeAreaView>
                <View style={styles.emptyWrap}>
                    <Text style={{color: 'white'}}>No notes available.</Text>
                </View>
            </LinearGradient>
        );
    }

    const n = notes || {};
    const getItems = (key) => {
        // If the AI somehow returned an array instead of an object, try to extract relevant items
        if (Array.isArray(n)) {
            return n.filter(item => {
                if (typeof item === 'string') return item.toLowerCase().includes(key.replace('_', ' '));
                if (typeof item === 'object') {
                    const vals = Object.values(item).join(' ').toLowerCase();
                    return vals.includes(key.replace('_', ' '));
                }
                return false;
            }).map(item => typeof item === 'string' ? item : Object.values(item)[0]);
        }
        
        // Typical object handling: Try exact, then capitalized, then lowercase
        return n[key] || n[key.charAt(0).toUpperCase() + key.slice(1)] || n[key.toLowerCase()] || n['smart_' + key] || n['Smart' + key] || [];
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
                    <Text style={styles.title} numberOfLines={1}>{subjectName || 'Study Material'}</Text>
                    <Text style={styles.subtitle}>Veeru Lens Smart Notes</Text>
                </View>
            </SafeAreaView>

            <ScrollView contentContainerStyle={styles.body} showsVerticalScrollIndicator={false}>
                <NotesSection 
                    title="Key Definitions" 
                    icon="book-open-page-variant" 
                    color="#38bdf8" 
                    items={getItems('definitions')} 
                    delay={100}
                />
                
                <NotesSection 
                    title="Essential Facts" 
                    icon="lightning-bolt" 
                    color="#f59e0b" 
                    items={getItems('key_facts')} 
                    delay={200}
                />
                
                <NotesSection 
                    title="Core Concepts" 
                    icon="brain" 
                    color="#a855f7" 
                    items={getItems('core_concepts')} 
                    delay={300}
                />
                
                <View style={{height: 40}} /> 
            </ScrollView>
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
    body: { padding: 20 },
    emptyWrap: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    
    sectionContainer: { marginBottom: 30 },
    sectionHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 15 },
    iconBox: { width: 42, height: 42, borderRadius: 12, justifyContent: 'center', alignItems: 'center', marginRight: 12 },
    sectionTitle: { color: 'white', fontSize: 20, fontWeight: 'bold' },
    
    card: {
        backgroundColor: '#1e293b60', borderRadius: 20, 
        borderWidth: 1, borderColor: '#334155', overflow: 'hidden',
        padding: 20, paddingTop: 10, paddingBottom: 10
    },
    bulletRow: { flexDirection: 'row', alignItems: 'flex-start', marginVertical: 10 },
    bulletDot: { width: 8, height: 8, borderRadius: 4, marginTop: 6, marginRight: 12 },
    bulletText: { color: '#cbd5e1', fontSize: 16, lineHeight: 24, flex: 1, fontWeight: '500' }
});

export default AIPdfNotesScreen;
