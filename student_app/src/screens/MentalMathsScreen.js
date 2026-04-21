import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, SafeAreaView, Platform } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { useTheme } from '../context/ThemeContext';
import { Ionicons } from '@expo/vector-icons';
import { Audio } from 'expo-av';

import { fetchMentalMathProgress } from '../api/mentalMath';
import ClassicMathTab from '../components/math/ClassicMathTab';
import AbacusTab from '../components/math/AbacusTab';

const MentalMathsScreen = ({ navigation, user }) => {
    const { theme, isDarkMode } = useTheme();
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('classic'); // 'classic' or 'abacus'

    // Max levels unlocked by user
    const [maxClassicLevel, setMaxClassicLevel] = useState(1);
    const [maxAbacusLevel, setMaxAbacusLevel] = useState(1);

    const [sounds, setSounds] = useState({ correct: null, wrong: null, levelup: null });

    useEffect(() => {
        loadSounds();
        loadProgress();
        return () => unloadSounds();
    }, []);

    const loadSounds = async () => {
        try {
            const { sound: sCorrect } = await Audio.Sound.createAsync(
                { uri: 'https://assets.mixkit.co/active_storage/sfx/2000/2000-preview.mp3' }
            );
            const { sound: sWrong } = await Audio.Sound.createAsync(
                { uri: 'https://assets.mixkit.co/active_storage/sfx/2003/2003-preview.mp3' }
            );
            const { sound: sLevelUp } = await Audio.Sound.createAsync(
                { uri: 'https://assets.mixkit.co/active_storage/sfx/1435/1435-preview.mp3' }
            );
            setSounds({ correct: sCorrect, wrong: sWrong, levelup: sLevelUp });
        } catch (error) {
            console.log('Error loading sounds', error);
        }
    };

    const unloadSounds = async () => {
        if (sounds.correct) await sounds.correct.unloadAsync();
        if (sounds.wrong) await sounds.wrong.unloadAsync();
        if (sounds.levelup) await sounds.levelup.unloadAsync();
    };

    const loadProgress = async () => {
        setLoading(true);
        try {
            const res = await fetchMentalMathProgress(user.user_id, true);
            if (res.status === 'success') {
                 setMaxClassicLevel(res.mental_math_level || 1);
                 setMaxAbacusLevel(res.abacus_level || 1);
            }
        } catch (error) {
            console.log('Local progress used', error);
        } finally {
            setLoading(false);
        }
    };

    // Callback so child tabs can inform parent that a new level unlocked
    const handleProgressUpdate = (type, newLevel) => {
        if (type === 'classic') setMaxClassicLevel(newLevel);
        if (type === 'abacus') setMaxAbacusLevel(newLevel);
    };

    if (loading) {
        return (
            <View style={[styles.container, { backgroundColor: theme.background, justifyContent: 'center' }]}>
                <ActivityIndicator size="large" color={theme.primary} />
            </View>
        );
    }

    const getGradientColors = () => {
        if (activeTab === 'abacus') {
            return isDarkMode ? ['#4c0519', '#881337'] : ['#fecdd3', '#fda4af']; // Rosy/Red Theme for Flash
        }
        return isDarkMode ? ['#0f172a', '#1e293b'] : ['#4facfe', '#00f2fe']; // Blue Theme for Classic
    };

    return (
        <View style={styles.container}>
            <LinearGradient
                colors={getGradientColors()}
                style={styles.background}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
            />

            <SafeAreaView style={styles.safeArea}>
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                        <Ionicons name="arrow-back" size={24} color="#fff" />
                    </TouchableOpacity>
                    <View style={styles.headerBadge}>
                        <Ionicons name="trophy" size={16} color="#FFD700" style={{ marginRight: 4 }} />
                        <Text style={styles.headerBadgeText}>
                            {activeTab === 'classic' ? `Lvl ${maxClassicLevel}` : `Lvl ${maxAbacusLevel}`}
                        </Text>
                    </View>
                </View>

                {/* Custom Segmented Tab Bar */}
                <View style={styles.tabContainer}>
                    <TouchableOpacity 
                        style={[styles.tabButton, activeTab === 'classic' && styles.activeTab]}
                        onPress={() => setActiveTab('classic')}
                        activeOpacity={0.8}
                    >
                        <Text style={[styles.tabText, activeTab === 'classic' && styles.activeTabText]}>Mental Math</Text>
                    </TouchableOpacity>
                    
                    <TouchableOpacity 
                        style={[styles.tabButton, activeTab === 'abacus' && styles.activeTabAbacus]}
                        onPress={() => setActiveTab('abacus')}
                        activeOpacity={0.8}
                    >
                        <Text style={[styles.tabText, activeTab === 'abacus' && styles.activeTabText]}>Abacus</Text>
                        <View style={styles.newBadge}>
                            <Text style={styles.newBadgeTxt}>NEW</Text>
                        </View>
                    </TouchableOpacity>
                </View>

                <View style={styles.content}>
                    {activeTab === 'classic' ? (
                        <ClassicMathTab 
                            userLevel={maxClassicLevel} 
                            maxLevelAllowed={maxClassicLevel}
                            onProgressUpdate={handleProgressUpdate}
                            user={user}
                            sounds={sounds}
                        />
                    ) : (
                        <AbacusTab 
                            userLevel={maxAbacusLevel}
                            maxLevelAllowed={maxAbacusLevel}
                            onProgressUpdate={handleProgressUpdate}
                            user={user}
                            sounds={sounds}
                        />
                    )}
                </View>

            </SafeAreaView>
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    background: { position: 'absolute', left: 0, right: 0, top: 0, bottom: 0 },
    safeArea: { flex: 1 },
    header: {
        flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
        paddingHorizontal: 20, paddingVertical: 10, marginTop: Platform.OS === 'android' ? 30 : 0,
    },
    backButton: {
        width: 40, height: 40, backgroundColor: 'rgba(255,255,255,0.2)',
        borderRadius: 20, justifyContent: 'center', alignItems: 'center',
    },
    headerBadge: {
        flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.3)',
        paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20,
        borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)',
    },
    headerBadgeText: { color: '#fff', fontWeight: 'bold', fontSize: 14 },
    
    // Tab Bar Styles
    tabContainer: {
        flexDirection: 'row',
        backgroundColor: 'rgba(255,255,255,0.2)',
        marginHorizontal: 20,
        borderRadius: 30,
        padding: 4,
        marginTop: 10
    },
    tabButton: {
        flex: 1,
        paddingVertical: 12,
        alignItems: 'center',
        justifyContent: 'center',
        borderRadius: 25,
        flexDirection: 'row'
    },
    activeTab: {
        backgroundColor: '#3b82f6', // Blue for Classic
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.2,
        elevation: 3
    },
    activeTabAbacus: {
        backgroundColor: '#e11d48', // Red for Abacus
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.2,
        elevation: 3
    },
    tabText: {
        color: 'rgba(255,255,255,0.7)',
        fontWeight: 'bold',
        fontSize: 15
    },
    activeTabText: {
        color: '#fff',
    },
    newBadge: {
        backgroundColor: '#fde047',
        paddingHorizontal: 6,
        paddingVertical: 2,
        borderRadius: 10,
        marginLeft: 6,
        transform: [{translateY: -8}]
    },
    newBadgeTxt: {
        fontSize: 9,
        fontWeight: '900',
        color: '#ca8a04'
    },
    content: {
        flex: 1
    }
});

export default MentalMathsScreen;